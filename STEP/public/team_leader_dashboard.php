<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once "config.php";

$userId = (int) $_SESSION['user_id'];

// Vedúci tímu musí byť prihlásený študent.
if (($_SESSION['role'] ?? '') !== 'student') {
    http_response_code(403);
    exit("Prístup zamietnutý.");
}

$statuses = ['To Do', 'In Progress', 'Done'];
$priorities = ['Low', 'Medium', 'High'];

$message = $_SESSION['flash_message'] ?? '';
$messageType = $_SESSION['flash_type'] ?? 'info';

unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

// Pomocné funkcie
function h($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function statusLabel($status)
{
    $labels = [
        'To Do' => 'Na spracovanie',
        'In Progress' => 'Rozpracované',
        'Done' => 'Dokončené'
    ];

    return $labels[$status] ?? $status;
}

function statusClass($status)
{
    $classes = [
        'To Do' => 'todo',
        'In Progress' => 'progress',
        'Done' => 'done'
    ];

    return $classes[$status] ?? '';
}

// Kontrola, či je používateľ vedúcim konkrétneho tímu.
function managesTeam($pdo, $teamId, $userId)
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM team_members tm
        INNER JOIN users u ON u.id = tm.user_id
        WHERE tm.team_id = :team_id
          AND tm.user_id = :user_id
          AND tm.is_team_leader = TRUE
          AND u.role = 'student'
        LIMIT 1
    ");

    $stmt->execute([
        'team_id' => $teamId,
        'user_id' => $userId
    ]);

    return (bool) $stmt->fetchColumn();
}

// Kontrola, či je študent členom konkrétneho tímu.
function isTeamStudent($pdo, $teamId, $studentId)
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM team_members tm
        INNER JOIN users u ON u.id = tm.user_id
        WHERE tm.team_id = :team_id
          AND tm.user_id = :student_id
          AND u.role = 'student'
        LIMIT 1
    ");

    $stmt->execute([
        'team_id' => $teamId,
        'student_id' => $studentId
    ]);

    return (bool) $stmt->fetchColumn();
}

// Načítanie úlohy, ku ktorej má používateľ oprávnenie.
function getManagedTask($pdo, $taskId, $userId)
{
    $stmt = $pdo->prepare("
        SELECT t.*
        FROM tasks t
        INNER JOIN team_members tm
            ON tm.team_id = t.team_id
        INNER JOIN users u
            ON u.id = tm.user_id
        WHERE t.id = :task_id
          AND tm.user_id = :user_id
          AND tm.is_team_leader = TRUE
          AND u.role = 'student'
        LIMIT 1
    ");

    $stmt->execute([
        'task_id' => $taskId,
        'user_id' => $userId
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/*
 * NAČÍTANIE TÍMOV, V KTORÝCH JE POUŽÍVATEĽ VEDÚCIM
 */

$teamsStmt = $pdo->prepare("
    SELECT t.id, t.name
FROM teams t
INNER JOIN team_members tm ON tm.team_id = t.id
INNER JOIN users u ON u.id = tm.user_id
WHERE tm.user_id = :user_id
  AND tm.is_team_leader = TRUE
  AND u.role = 'student'
ORDER BY t.name
");

$teamsStmt->execute(['user_id' => $userId]);
$teams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);


/*
 * SPRACOVANIE FORMULÁROV
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        $submittedToken = $_POST['csrf_token'] ?? '';

        if (
            !is_string($submittedToken) ||
            !hash_equals($csrfToken, $submittedToken)
        ) {
            throw new Exception("Neplatný bezpečnostný token. Obnov stránku.");
        }

        $action = $_POST['action'] ?? '';

        /*
         * VYTVORENIE NOVEJ ÚLOHY
         */

        if ($action === 'create_task') {

            $teamId = filter_var(
                $_POST['team_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $priority = $_POST['priority'] ?? 'Medium';
            $deadline = trim($_POST['deadline'] ?? '');
            $assignedTo = $_POST['assigned_to'] ?? '';

            if (!$teamId || $teamId <= 0 || !managesTeam($pdo, $teamId, $userId)) {
                throw new Exception("Nemáš oprávnenie spravovať tento tím.");
            }

            if ($title === '' || mb_strlen($title, 'UTF-8') > 255) {
                throw new Exception("Názov úlohy je povinný a môže mať maximálne 255 znakov.");
            }

            if (!in_array($priority, $priorities, true)) {
                throw new Exception("Neplatná priorita úlohy.");
            }

            if ($deadline !== '') {
                $date = DateTime::createFromFormat('!Y-m-d', $deadline);

                if (!$date || $date->format('Y-m-d') !== $deadline) {
                    throw new Exception("Neplatný termín úlohy.");
                }
            } else {
                $deadline = null;
            }

            if ($assignedTo !== '') {

                $assignedTo = filter_var(
                    $assignedTo,
                    FILTER_VALIDATE_INT
                );

                if (
                    !$assignedTo ||
                    $assignedTo <= 0 ||
                    !isTeamStudent($pdo, $teamId, $assignedTo)
                ) {
                    throw new Exception("Vybraný študent nepatrí do zvoleného tímu.");
                }
            } else {
                $assignedTo = null;
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO tasks
                    (team_id, assigned_to, title, description,
                     status, priority, deadline, updated_at)
                VALUES
                    (:team_id, :assigned_to, :title, :description,
                     'To Do', :priority, :deadline, CURRENT_TIMESTAMP)
                RETURNING id
            ");

            $stmt->execute([
                'team_id' => $teamId,
                'assigned_to' => $assignedTo,
                'title' => $title,
                'description' => $description !== '' ? $description : null,
                'priority' => $priority,
                'deadline' => $deadline
            ]);

            $newTaskId = $stmt->fetchColumn();

            $activity = $pdo->prepare("
                INSERT INTO activity_log (task_id, user_id, action)
                VALUES (:task_id, :user_id, :action)
            ");

            $activity->execute([
                'task_id' => $newTaskId,
                'user_id' => $userId,
                'action' => 'Vytvoril novú úlohu: ' . $title
            ]);

            $pdo->commit();

            $message = "Úloha bola úspešne vytvorená.";
            $messageType = 'success';
        } elseif ($action === 'update_status') {

            /*
             * ZMENA STAVU ÚLOHY
             */

            $taskId = filter_var(
                $_POST['task_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $newStatus = $_POST['status'] ?? '';

            if (!$taskId || $taskId <= 0 || !in_array($newStatus, $statuses, true)) {
                throw new Exception("Neplatné údaje úlohy.");
            }

            $task = getManagedTask($pdo, $taskId, $userId);

            if (!$task) {
                throw new Exception("K tejto úlohe nemáš prístup.");
            }

            if ($task['status'] !== $newStatus) {

                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    UPDATE tasks
                    SET status = :status,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :task_id
                ");

                $stmt->execute([
                    'status' => $newStatus,
                    'task_id' => $taskId
                ]);

                $activity = $pdo->prepare("
                    INSERT INTO activity_log (task_id, user_id, action)
                    VALUES (:task_id, :user_id, :action)
                ");

                $activity->execute([
                    'task_id' => $taskId,
                    'user_id' => $userId,
                    'action' => 'Zmenil stav úlohy na: ' . $newStatus
                ]);

                $pdo->commit();
            }

            $message = "Stav úlohy bol aktualizovaný.";
            $messageType = 'success';
        } elseif ($action === 'add_comment') {

            /*
             * PRIDANIE KOMENTÁRA
             */

            $taskId = filter_var(
                $_POST['task_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $content = trim($_POST['content'] ?? '');

            if (!$taskId || $taskId <= 0 || $content === '' || strlen($content) > 5000) {
                throw new Exception("Komentár je povinný a môže mať maximálne 5000 znakov.");
            }

            $task = getManagedTask($pdo, $taskId, $userId);

            if (!$task) {
                throw new Exception("K tejto úlohe nemáš prístup.");
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO comments (task_id, user_id, content)
                VALUES (:task_id, :user_id, :content)
            ");

            $stmt->execute([
                'task_id' => $taskId,
                'user_id' => $userId,
                'content' => $content
            ]);

            $activity = $pdo->prepare("
                INSERT INTO activity_log (task_id, user_id, action)
                VALUES (:task_id, :user_id, :action)
            ");

            $activity->execute([
                'task_id' => $taskId,
                'user_id' => $userId,
                'action' => 'Pridal komentár'
            ]);

            $pdo->commit();

            $message = "Komentár bol pridaný.";
            $messageType = 'success';
        } else {
            throw new Exception("Neznáma požiadavka.");
        }
    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log($e->getMessage());

        $message = $e instanceof PDOException
            ? "Pri práci s databázou nastala chyba."
            : $e->getMessage();

        $messageType = 'error';
    }

    // Uloženie výsledku a presmerovanie po každom POST.
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $messageType;

    header("Location: team_leader_dashboard.php");
    exit;
}


/*
 * NAČÍTANIE ČLENOV VEDÚCICH TÍMOV
 *
 * Každý člen je priradený ku konkrétnemu tímu.
 */

$members = [];

if (!empty($teams)) {

    $membersStmt = $pdo->prepare("
    SELECT
        tm.team_id,
        u.id,
        u.name
    FROM team_members tm
    INNER JOIN users u ON u.id = tm.user_id
    WHERE u.role = 'student'
      AND EXISTS (
          SELECT 1
          FROM team_members leader
          INNER JOIN users leader_user
              ON leader_user.id = leader.user_id
          WHERE leader.team_id = tm.team_id
            AND leader.user_id = :leader_id
            AND leader.is_team_leader = TRUE
            AND leader_user.role = 'student'
      )
    ORDER BY u.name
");

    $membersStmt->execute([
        'leader_id' => $userId
    ]);

    $members = $membersStmt->fetchAll(PDO::FETCH_ASSOC);
}

/*
 * NAČÍTANIE ÚLOH IBA Z VEDÚCICH TÍMOV
 */

$tasksStmt = $pdo->prepare("
    SELECT
        t.id,
        t.title,
        t.description,
        t.status,
        t.priority,
        t.deadline,
        t.created_at,
        t.updated_at,
        t.team_id,
        tm.name AS team_name,
        u.name AS assigned_name
    FROM tasks t
    INNER JOIN teams tm ON tm.id = t.team_id
    LEFT JOIN users u ON u.id = t.assigned_to
    WHERE t.team_id IN (
        SELECT team_id
        FROM team_members
        WHERE user_id = :leader_id
          AND is_team_leader = TRUE
    )
    ORDER BY
        CASE t.status
            WHEN 'To Do' THEN 1
            WHEN 'In Progress' THEN 2
            WHEN 'Done' THEN 3
            ELSE 4
        END,
        t.deadline ASC NULLS LAST,
        t.created_at DESC
");

$tasksStmt->execute([
    'leader_id' => $userId
]);

$tasks = $tasksStmt->fetchAll(PDO::FETCH_ASSOC);

/*
 * ŠTATISTIKY
 */

$stats = [
    'To Do' => 0,
    'In Progress' => 0,
    'Done' => 0
];

foreach ($tasks as $task) {
    if (isset($stats[$task['status']])) {
        $stats[$task['status']]++;
    }
}

$totalTasks = count($tasks);
$completedTasks = $stats['Done'];

$progress = $totalTasks > 0
    ? round(($completedTasks / $totalTasks) * 100)
    : 0;

/*
 * AKTIVITY
 */

$activities = [];

if (!empty($teams)) {

    $activityStmt = $pdo->prepare("
        SELECT
            al.action,
            al.created_at,
            u.name AS user_name,
            t.title AS task_title,
            tm.name AS team_name
        FROM activity_log al
        INNER JOIN tasks t ON t.id = al.task_id
        INNER JOIN teams tm ON tm.id = t.team_id
        LEFT JOIN users u ON u.id = al.user_id
        WHERE t.team_id IN (
            SELECT team_id
            FROM team_members
            WHERE user_id = :leader_id
              AND is_team_leader = TRUE
        )
        ORDER BY al.created_at DESC
        LIMIT 15
    ");

    $activityStmt->execute([
        'leader_id' => $userId
    ]);

    $activities = $activityStmt->fetchAll(PDO::FETCH_ASSOC);
}

/*
 * KOMENTÁRE PRE JEDNOTLIVÉ ÚLOHY
 */

$commentsByTask = [];

foreach ($tasks as $task) {

    $commentStmt = $pdo->prepare("
        SELECT c.content, c.created_at, u.name
        FROM comments c
        LEFT JOIN users u ON u.id = c.user_id
        WHERE c.task_id = :task_id
        ORDER BY c.created_at DESC
        LIMIT 3
    ");

    $commentStmt->execute([
        'task_id' => $task['id']
    ]);

    $commentsByTask[$task['id']] = $commentStmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>STEP | Vedúci tímu</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f3f3;
            color: #164e57;
        }

        header {
            background: white;
            min-height: 68px;
            padding: 12px 4%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 4px rgba(0, 0, 0, .08);
        }

        .logo {
            color: #008d91;
            font-size: 36px;
            font-weight: bold;
            letter-spacing: -2px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .header-right a {
            color: #444;
            border: 1px solid #555;
            border-radius: 8px;
            padding: 8px 15px;
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
        }

        .container {
            max-width: 1250px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        h1 {
            margin: 0;
            font-size: 27px;
        }

        .subtitle {
            margin-top: 6px;
            color: #557078;
        }

        .role-label {
            background: #008f9f;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 13px;
        }

        .message {
            padding: 13px 16px;
            border-radius: 9px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .message.success {
            background: #e4f5e5;
            color: #287d35;
        }

        .message.error {
            background: #fde9e5;
            color: #b33a2d;
        }

        .message.info {
            background: #e7f1f5;
            color: #24596b;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 22px;
            margin-bottom: 25px;
        }

        .stat {
            background: white;
            border-radius: 16px;
            padding: 17px 22px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .14);
        }

        .stat-number {
            font-size: 42px;
            font-weight: bold;
        }

        .stat-label {
            font-size: 14px;
            font-weight: bold;
            color: #555;
        }

        .teal {
            color: #0096a8;
        }

        .green {
            color: #65c52d;
        }

        .orange {
            color: #f0442e;
        }

        .blue {
            color: #3984c6;
        }

        .progress-track {
            height: 12px;
            background: #e9e9e9;
            border-radius: 20px;
            overflow: hidden;
            margin-top: 12px;
        }

        .progress-bar {
            height: 100%;
            background: #65c52d;
            border-radius: 20px;
        }

        .card {
            background: white;
            padding: 22px;
            border-radius: 16px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, .13);
            margin-bottom: 25px;
        }

        .card h2 {
            margin: 0 0 18px;
            font-size: 19px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            font-size: 13px;
            font-weight: bold;
            color: #555;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 11px 13px;
            border: 1px solid #ccc;
            border-radius: 7px;
            font-size: 14px;
            font-family: inherit;
            color: #164e57;
            background: white;
        }

        textarea {
            resize: vertical;
            min-height: 85px;
        }

        .btn {
            display: inline-block;
            border: none;
            border-radius: 7px;
            padding: 11px 20px;
            background: #0c5059;
            color: white;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
        }

        .btn:hover {
            background: #008f9f;
        }

        .task-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .task {
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 17px;
            background: #fff;
        }

        .task-header {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .task h3 {
            margin: 0;
            font-size: 16px;
        }

        .task-description {
            color: #666;
            font-size: 13px;
            margin: 10px 0;
            white-space: pre-wrap;
        }

        .task-meta {
            color: #777;
            font-size: 12px;
            line-height: 1.8;
        }

        .status {
            display: inline-block;
            border-radius: 20px;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }

        .status.todo {
            background: #e9ecef;
            color: #555;
        }

        .status.progress {
            background: #f7f3cf;
            color: #776c00;
        }

        .status.done {
            background: #d9eee1;
            color: #216b39;
        }

        .priority {
            font-weight: bold;
        }

        .task-form {
            border-top: 1px solid #eee;
            padding-top: 13px;
            margin-top: 14px;
        }

        .task-form form {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .task-form select {
            flex: 1;
            min-width: 130px;
        }

        .task-form .btn {
            padding: 10px 14px;
            font-size: 12px;
        }

        .comments {
            border-top: 1px solid #eee;
            padding-top: 12px;
            margin-top: 13px;
        }

        .comments-title {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 9px;
        }

        .comment {
            background: #f5f7f7;
            border-radius: 7px;
            padding: 9px;
            margin-bottom: 7px;
            font-size: 12px;
            color: #555;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .comment strong {
            color: #164e57;
        }

        .comment-form {
            display: flex;
            gap: 7px;
            margin-top: 10px;
        }

        .comment-form input {
            flex: 1;
            min-width: 0;
        }

        .comment-form button {
            padding: 9px 13px;
        }

        .activity-item {
            padding: 13px 0;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-item strong {
            color: #164e57;
        }

        .activity-date {
            color: #888;
            font-size: 11px;
            margin-top: 5px;
        }

        .empty {
            color: #888;
            text-align: center;
            padding: 25px;
            background: white;
            border-radius: 10px;
        }

        footer {
            text-align: center;
            padding: 25px;
            color: #888;
            font-size: 13px;
        }

        @media(max-width: 850px) {
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .task-list {
                grid-template-columns: 1fr;
            }
        }

        @media(max-width: 550px) {
            .container {
                padding: 0 12px;
                margin-top: 22px;
            }

            header {
                padding: 12px 15px;
            }

            .logo {
                font-size: 29px;
            }

            .stats {
                gap: 10px;
            }

            .stat {
                padding: 13px;
            }

            .stat-number {
                font-size: 34px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }

            .comment-form {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>


    <?php require_once "navigation.php"; ?>

    <main class="container">

        <div class="heading">
            <div>
                <h1>Prehľad tímu</h1>
                <div class="subtitle">
                    Správa úloh, prideľovanie práce a komunikácia tímu
                </div>
            </div>

            <div class="role-label">VEDÚCI TÍMU</div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="message <?= h($messageType) ?>">
                <?= h($message) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($teams)): ?>

            <div class="card">
                <h2>Zatiaľ nie si vedúcim žiadneho tímu</h2>
                <p>
                    Učiteľ ťa môže nastaviť za vedúceho v správe členov tímu.
                </p>
                <a href="dashboard.php" class="btn">Späť na študentský dashboard</a>
            </div>

        <?php else: ?>

            <!-- MOJE TIMY -->

            <section class="card">
                <h2>Moje tímy</h2>

                <?php foreach ($teams as $team): ?>
                    <span class="role-label" style="display:inline-block; margin:0 8px 8px 0;">
                        <?= h($team['name']) ?>
                    </span>
                <?php endforeach; ?>
            </section>

            <!-- STATISTIKY -->

            <section class="stats">

                <div class="stat">
                    <div class="stat-number teal"><?= $totalTasks ?></div>
                    <div class="stat-label">Všetky úlohy</div>
                </div>

                <div class="stat">
                    <div class="stat-number orange"><?= $stats['To Do'] ?></div>
                    <div class="stat-label">Na spracovanie</div>
                </div>

                <div class="stat">
                    <div class="stat-number blue"><?= $stats['In Progress'] ?></div>
                    <div class="stat-label">Rozpracované</div>
                </div>

                <div class="stat">
                    <div class="stat-number green"><?= $completedTasks ?></div>
                    <div class="stat-label">Dokončené</div>
                </div>

            </section>

            <!-- CELKOVY POSTUP -->

            <section class="card">
                <h2>Celkový postup tímov</h2>

                <strong><?= $progress ?> % dokončených úloh</strong>

                <div class="progress-track">
                    <div class="progress-bar" style="width: <?= $progress ?>%"></div>
                </div>

                <p class="task-meta">
                    <?= $completedTasks ?> z <?= $totalTasks ?> úloh je dokončených.
                </p>
            </section>

            <!-- VYTVORENIE ULOHY -->

            <section class="card">

                <h2>+ Vytvoriť novú úlohu</h2>

                <form method="POST">

                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="action" value="create_task">

                    <div class="form-grid">

                        <div class="form-group">
                            <label for="title">Názov úlohy *</label>
                            <input type="text" id="title" name="title"
                                maxlength="255" required
                                placeholder="Zadaj názov úlohy">
                        </div>

                        <div class="form-group">
                            <label for="team_id">Tím *</label>

                            <select name="team_id" id="team_id" required>
                                <?php foreach ($teams as $team): ?>
                                    <option value="<?= (int)$team['id'] ?>">
                                        <?= h($team['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group full">
                            <label for="description">Popis úlohy</label>
                            <textarea id="description" name="description"
                                placeholder="Popíš zadanie úlohy..."></textarea>
                        </div>

                        <div class="form-group">
                            <label for="assigned_to">Prideliť študentovi</label>

                            <select name="assigned_to" id="assigned_to">
                                <option value="">Bez pridelenia</option>

                                <?php foreach ($members as $member): ?>
                                    <option
                                        value="<?= (int)$member['id'] ?>"
                                        data-team="<?= (int)$member['team_id'] ?>">
                                        <?= h($member['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="priority">Priorita</label>

                            <select name="priority" id="priority">
                                <option value="Low">Nízka</option>
                                <option value="Medium" selected>Stredná</option>
                                <option value="High">Vysoká</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="deadline">Termín dokončenia</label>
                            <input type="date" id="deadline" name="deadline">
                        </div>

                        <div class="form-group full">
                            <button type="submit" class="btn">Vytvoriť úlohu</button>
                        </div>

                    </div>
                </form>

            </section>

            <!-- ZOZNAM ULOH -->

            <section class="card">

                <h2>Nástenka úloh</h2>

                <?php if (empty($tasks)): ?>

                    <div class="empty">
                        V tvojich tímoch zatiaľ nie sú vytvorené žiadne úlohy.
                    </div>

                <?php else: ?>

                    <div class="task-list">

                        <?php foreach ($tasks as $task): ?>

                            <article class="task">

                                <div class="task-header">
                                    <h3><?= h($task['title']) ?></h3>

                                    <span class="status <?= h(statusClass($task['status'])) ?>">
                                        <?= h(statusLabel($task['status'])) ?>
                                    </span>
                                </div>

                                <div class="task-meta">
                                    Tím: <?= h($task['team_name']) ?><br>

                                    Pridelené:
                                    <?= h($task['assigned_name'] ?? 'Nepridelené') ?><br>

                                    Priorita:
                                    <span class="priority"><?= h($task['priority']) ?></span><br>

                                    Termín:
                                    <?= $task['deadline']
                                        ? h(date('d.m.Y', strtotime($task['deadline'])))
                                        : 'Bez termínu' ?>
                                </div>

                                <?php if (!empty($task['description'])): ?>
                                    <div class="task-description"><?= h($task['description']) ?></div>
                                <?php endif; ?>

                                <!-- ZMENA STAVU -->

                                <div class="task-form">

                                    <form method="POST">

                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">

                                        <select name="status">
                                            <?php foreach ($statuses as $status): ?>
                                                <option value="<?= h($status) ?>"
                                                    <?= $task['status'] === $status ? 'selected' : '' ?>>
                                                    <?= h(statusLabel($status)) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <button class="btn" type="submit">Zmeniť stav</button>

                                    </form>

                                </div>

                                <!-- KOMENTARE -->

                                <div class="comments">

                                    <div class="comments-title">Komentáre</div>

                                    <?php if (empty($commentsByTask[$task['id']])): ?>

                                        <div class="task-meta">Zatiaľ bez komentárov.</div>

                                    <?php else: ?>

                                        <?php foreach ($commentsByTask[$task['id']] as $comment): ?>

                                            <div class="comment">
                                                <strong><?= h($comment['name'] ?? 'Používateľ') ?></strong>
                                                <br>
                                                <?= h($comment['content']) ?>

                                                <div class="activity-date">
                                                    <?= h(date('d.m.Y H:i', strtotime($comment['created_at']))) ?>
                                                </div>
                                            </div>

                                        <?php endforeach; ?>

                                    <?php endif; ?>

                                    <form method="POST" class="comment-form">

                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="action" value="add_comment">
                                        <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">

                                        <input type="text" name="content" maxlength="5000"
                                            placeholder="Napíš komentár..." required>

                                        <button type="submit" class="btn">Odoslať</button>

                                    </form>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>

            <!-- AKTIVITA -->

            <section class="card">

                <h2>Posledná aktivita tímov</h2>

                <?php if (empty($activities)): ?>

                    <div class="empty">Zatiaľ neevidujeme žiadnu aktivitu.</div>

                <?php else: ?>

                    <?php foreach ($activities as $activity): ?>

                        <div class="activity-item">

                            <strong><?= h($activity['user_name'] ?? 'Používateľ') ?></strong>
                            <?= h($activity['action']) ?>

                            <br>

                            <span class="task-meta">
                                Úloha: <?= h($activity['task_title']) ?>
                                · Tím: <?= h($activity['team_name']) ?>
                            </span>

                            <div class="activity-date">
                                <?= h(date('d.m.Y H:i', strtotime($activity['created_at']))) ?>
                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </section>

        <?php endif; ?>

    </main>

    <footer>
        STEP – Tímový projekt | Vedúci tímu
    </footer>

    <script>
        const teamSelect = document.getElementById('team_id');
        const memberSelect = document.getElementById('assigned_to');

        function filterMembers() {
            if (!teamSelect || !memberSelect) return;

            const selectedTeam = teamSelect.value;

            Array.from(memberSelect.options).forEach(option => {
                if (!option.value) return;

                const belongsToTeam = option.dataset.team === selectedTeam;

                option.hidden = !belongsToTeam;
                option.disabled = !belongsToTeam;

                if (!belongsToTeam && option.selected) {
                    memberSelect.value = '';
                }
            });
        }

        if (teamSelect && memberSelect) {
            teamSelect.addEventListener('change', filterMembers);
            filterMembers();
        }
    </script>

</body>

</html>