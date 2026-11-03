<?php

// zmen_stav.php – uloží nový stav úlohy po presune karty na nástenke
// Volá ho js/kanban.js cez fetch, preto vracia len stavový kód (204 = OK), nie stránku.

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

require_once __DIR__ . "/../config/config.php";

$userId = (int) $_SESSION['user_id'];
$taskId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$status = $_POST['status'] ?? '';

// Kontrola CSRF tokenu (prázdny token v session by inak prešiel s prázdnym tokenom z formulára)
$csrfToken = $_SESSION['csrf_token'] ?? '';
$submittedToken = $_POST['csrf_token'] ?? '';

if ($csrfToken === '' || !is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
    http_response_code(403);
    exit;
}

// Kontrola vstupov
if (!$taskId || !in_array($status, ['To Do', 'In Progress', 'Done'], true)) {
    http_response_code(400);
    exit;
}

// Rovnaké pravidlo ako v task_detail.php: člen tímu, ktorému je úloha pridelená, alebo vedúci tímu
$stmt = $pdo->prepare("
    SELECT 1
    FROM tasks t
    INNER JOIN team_members tm ON tm.team_id = t.team_id
    WHERE t.id = :task_id
      AND tm.user_id = :user_id
      AND (t.assigned_to = :assigned_id OR tm.is_team_leader = TRUE)
");
$stmt->execute([
    'task_id' => $taskId,
    'user_id' => $userId,
    'assigned_id' => $userId
]);

if (!$stmt->fetchColumn()) {
    http_response_code(403);
    exit;
}

// Zmena stavu a záznam do aktivity naraz – buď sa uložia obe, alebo nič
$pdo->beginTransaction();

$update = $pdo->prepare("
    UPDATE tasks
    SET status = :status,
        updated_at = CURRENT_TIMESTAMP
    WHERE id = :id
");
$update->execute([
    'status' => $status,
    'id' => $taskId
]);

$activity = $pdo->prepare("
    INSERT INTO activity_log (task_id, user_id, action)
    VALUES (:task_id, :user_id, :action)
");
$activity->execute([
    'task_id' => $taskId,
    'user_id' => $userId,
    'action' => 'Zmenil stav úlohy na: ' . $status
]);

$pdo->commit();

http_response_code(204);
