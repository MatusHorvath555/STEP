<?php

// uprav_ulohu.php – uloží zmeny karty z nástenky (názov, popis, člen, termín, priorita)
// Volá ho js/kanban.js cez fetch. Vracia JSON: {"ok": true} alebo {"chyba": "..."}.
// Upravovať smie iba vedúci tímu, do ktorého úloha patrí.

session_start();
header('Content-Type: application/json; charset=utf-8');

// Ukončí skript s HTTP kódom a JSON odpoveďou
function odpoved($kod, $data)
{
    http_response_code($kod);
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    odpoved(401, ['chyba' => 'Nie si prihlásený.']);
}

require_once __DIR__ . "/../config/config.php";

$userId = (int) $_SESSION['user_id'];

// Kontrola CSRF tokenu (rovnako ako v zmen_stav.php)
$csrfToken = $_SESSION['csrf_token'] ?? '';
$submittedToken = $_POST['csrf_token'] ?? '';

if ($csrfToken === '' || !is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
    odpoved(403, ['chyba' => 'Platnosť stránky vypršala. Obnov ju a skús to znova.']);
}

// Vstupy z formulára
$taskId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$assignedTo = $_POST['assigned_to'] ?? '';
$deadline = $_POST['deadline'] ?? '';
$priority = $_POST['priority'] ?? '';

if (!$taskId) {
    odpoved(400, ['chyba' => 'Chýba úloha.']);
}

// Databáza prijme len text v UTF-8 (prehliadač ho tak posiela vždy)
if (!mb_check_encoding($title . $description, 'UTF-8')) {
    odpoved(400, ['chyba' => 'Text obsahuje neplatné znaky.']);
}

if ($title === '' || mb_strlen($title) > 200) {
    odpoved(400, ['chyba' => 'Názov je povinný a môže mať najviac 200 znakov.']);
}

if (!in_array($priority, ['Low', 'Medium', 'High'], true)) {
    odpoved(400, ['chyba' => 'Neplatná priorita.']);
}

// Termín: prázdny = bez termínu, inak dátum RRRR-MM-DD (taký posiela <input type="date">)
if ($deadline === '') {
    $deadline = null;
} else {
    $datum = DateTime::createFromFormat('Y-m-d', $deadline);
    if (!$datum || $datum->format('Y-m-d') !== $deadline) {
        odpoved(400, ['chyba' => 'Neplatný termín.']);
    }
}

// Úloha musí patriť do tímu, ktorého vedúcim je prihlásený používateľ
$stmt = $pdo->prepare("
    SELECT t.team_id
    FROM tasks t
    INNER JOIN team_members tm ON tm.team_id = t.team_id
    WHERE t.id = :task_id
      AND tm.user_id = :user_id
      AND tm.is_team_leader = TRUE
");
$stmt->execute([
    'task_id' => $taskId,
    'user_id' => $userId
]);
$teamId = $stmt->fetchColumn();

if (!$teamId) {
    odpoved(403, ['chyba' => 'Úlohu môže upraviť len vedúci jej tímu.']);
}

// Priradený člen: prázdny = nepridelené, inak musí byť členom toho istého tímu
if ($assignedTo === '') {
    $assignedTo = null;
} else {
    $assignedTo = filter_var($assignedTo, FILTER_VALIDATE_INT);
    $jeClen = false;

    if ($assignedTo) {
        $stmt = $pdo->prepare("SELECT 1 FROM team_members WHERE team_id = :team_id AND user_id = :user_id");
        $stmt->execute([
            'team_id' => $teamId,
            'user_id' => $assignedTo
        ]);
        $jeClen = (bool) $stmt->fetchColumn();
    }

    if (!$jeClen) {
        odpoved(400, ['chyba' => 'Vybraný člen nepatrí do tímu.']);
    }
}

// Zmena úlohy a záznam do aktivity naraz – buď sa uložia obe, alebo nič
try {
    $pdo->beginTransaction();

    $update = $pdo->prepare("
        UPDATE tasks
        SET title = :title,
            description = :description,
            assigned_to = :assigned_to,
            deadline = :deadline,
            priority = :priority,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $update->execute([
        'title' => $title,
        'description' => $description === '' ? null : $description,
        'assigned_to' => $assignedTo,
        'deadline' => $deadline,
        'priority' => $priority,
        'id' => $taskId
    ]);

    $activity = $pdo->prepare("
        INSERT INTO activity_log (task_id, user_id, action)
        VALUES (:task_id, :user_id, :action)
    ");
    $activity->execute([
        'task_id' => $taskId,
        'user_id' => $userId,
        'action' => 'Upravil úlohu'
    ]);

    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    odpoved(500, ['chyba' => 'Zmenu sa nepodarilo uložiť do databázy.']);
}

odpoved(200, ['ok' => true]);
