<?php

// zmaz_ulohu.php – natrvalo zmaže kartu z nástenky
// Volá ho js/kanban.js cez fetch. Vracia JSON: {"ok": true} alebo {"chyba": "..."}.
// Mazať smie iba vedúci tímu, do ktorého úloha patrí.
// Komentáre a záznamy aktivity úlohy zmaže databáza sama (ON DELETE CASCADE).

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

$taskId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$taskId) {
    odpoved(400, ['chyba' => 'Chýba úloha.']);
}

// Zmaže sa len úloha z tímu, ktorého vedúcim je prihlásený používateľ
$stmt = $pdo->prepare("
    DELETE FROM tasks
    WHERE id = :task_id
      AND team_id IN (
          SELECT team_id
          FROM team_members
          WHERE user_id = :user_id
            AND is_team_leader = TRUE
      )
");
$stmt->execute([
    'task_id' => $taskId,
    'user_id' => $userId
]);

if ($stmt->rowCount() === 0) {
    odpoved(403, ['chyba' => 'Úlohu môže zmazať len vedúci jej tímu.']);
}

odpoved(200, ['ok' => true]);
