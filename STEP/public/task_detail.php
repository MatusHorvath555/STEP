<?php

session_start();

// Kontrola prihlásenia
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/../config/config.php";

$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

// Povolené roly
if (!in_array($role, ['teacher', 'student'], true)) {
    http_response_code(403);
    exit("Prístup zamietnutý.");
}

// Pomocná funkcia na bezpečný výpis
function h($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Kontrola ID úlohy
if (
    !isset($_GET['id']) ||
    !ctype_digit((string) $_GET['id']) ||
    (int) $_GET['id'] <= 0
) {
    http_response_code(400);
    exit("Neplatné ID úlohy.");
}

$taskId = (int) $_GET['id'];

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

// Flash správy
$message = $_SESSION['flash_message'] ?? '';
$messageType = $_SESSION['flash_type'] ?? 'info';

unset($_SESSION['flash_message'], $_SESSION['flash_type']);

// Načítanie úlohy
$stmt = $pdo->prepare("
    SELECT
        t.*,
        u.name AS assigned_user,
        tm.name AS team_name
    FROM tasks t
    LEFT JOIN users u ON t.assigned_to = u.id
    LEFT JOIN teams tm ON t.team_id = tm.id
    WHERE t.id = :id
");

$stmt->execute(['id' => $taskId]);

$task = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    http_response_code(404);
    exit("Úloha neexistuje.");
}

/*
 * KONTROLA OPRÁVNENÍ
 *
 * Učiteľ:
 * - má prístup ku všetkým úlohám.
 *
 * Bežný študent:
 * - musí byť členom tímu,
 * - úloha mu musí byť pridelená.
 *
 * Vedúci tímu:
 * - musí byť členom tímu s is_team_leader = TRUE,
 * - môže pristupovať ku všetkým úlohám svojho tímu.
 */

$isTeamMember = false;
$isTeamLeader = false;

if ($role === 'student') {

    $accessStmt = $pdo->prepare("
        SELECT
            tm.is_team_leader
        FROM team_members tm
        INNER JOIN users u ON u.id = tm.user_id
        WHERE tm.team_id = :team_id
          AND tm.user_id = :user_id
          AND u.role = 'student'
        LIMIT 1
    ");

    $accessStmt->execute([
        'team_id' => $task['team_id'],
        'user_id' => $userId
    ]);

    $membership = $accessStmt->fetch(PDO::FETCH_ASSOC);

    if ($membership) {
        $isTeamMember = true;
        $isTeamLeader = ($membership['is_team_leader'] === true
            || $membership['is_team_leader'] === 't'
            || $membership['is_team_leader'] === 1
            || $membership['is_team_leader'] === '1');
    }

    $isAssigned = (int)($task['assigned_to'] ?? 0) === $userId;

    if (!$isTeamMember || (!$isAssigned && !$isTeamLeader)) {
        http_response_code(403);
        exit("Prístup zamietnutý.");
    }
}

/*
 * SPRACOVANIE FORMULÁROV
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        // Kontrola CSRF tokenu
        $submittedToken = $_POST['csrf_token'] ?? '';

        if (
            !is_string($submittedToken) ||
            !hash_equals($csrfToken, $submittedToken)
        ) {
            throw new Exception("Neplatný bezpečnostný token. Obnov stránku.");
        }

        // Zmena stavu úlohy
        if (isset($_POST['change_status'])) {

            $status = $_POST['status'] ?? '';

            $allowedStatuses = [
                'To Do',
                'In Progress',
                'Done'
            ];

            if (!is_string($status) || !in_array($status, $allowedStatuses, true)) {
                throw new Exception("Neplatný stav úlohy.");
            }

            if ($task['status'] !== $status) {

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

                // Záznam o aktivite
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
            }

            $message = "Stav úlohy bol úspešne aktualizovaný.";
            $messageType = 'success';

            // Pridanie komentára
        } elseif (isset($_POST['add_comment'])) {

            $content = $_POST['content'] ?? '';

            if (!is_string($content)) {
                throw new Exception("Neplatný obsah komentára.");
            }

            $content = trim($content);

            if ($content === '' || mb_strlen($content, 'UTF-8') > 5000) {
                throw new Exception("Komentár je povinný a môže mať maximálne 5000 znakov.");
            }

            $pdo->beginTransaction();

            $insert = $pdo->prepare("
                INSERT INTO comments (
                    task_id,
                    user_id,
                    content
                )
                VALUES (
                    :task_id,
                    :user_id,
                    :content
                )
            ");

            $insert->execute([
                'task_id' => $taskId,
                'user_id' => $userId,
                'content' => $content
            ]);

            // Záznam o aktivite
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

            $message = "Komentár bol úspešne pridaný.";
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

    // Uloženie správy a presmerovanie
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $messageType;

    header("Location: task_detail.php?id=" . $taskId);
    exit;
}

/*
 * NAČÍTANIE KOMENTÁROV
 */

$commentStmt = $pdo->prepare("
    SELECT
        c.*,
        u.name
    FROM comments c
    INNER JOIN users u ON c.user_id = u.id
    WHERE c.task_id = :id
    ORDER BY c.created_at DESC
");

$commentStmt->execute(['id' => $taskId]);

$comments = $commentStmt->fetchAll(PDO::FETCH_ASSOC);

// Odkaz späť podľa oprávnení
if ($role === 'teacher') {
    $backLink = 'teacher_dashboard.php';
    $backLabel = 'Späť na dashboard';
} elseif ($isTeamLeader) {
    $backLink = 'team_leader_dashboard.php';
    $backLabel = 'Späť na panel vedúceho';
} else {
    $backLink = 'my_tasks.php';
    $backLabel = 'Späť na moje úlohy';
}

?>

<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>STEP | Detail úlohy</title>

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

        .container {
            max-width: 950px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 14px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, .12);
            margin-bottom: 22px;
        }

        h1 {
            font-size: 27px;
            margin-top: 0;
        }

        h2 {
            font-size: 20px;
        }

        .subtitle {
            color: #557078;
            margin-bottom: 22px;
        }

        .message {
            padding: 13px 16px;
            border-radius: 8px;
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

        .task-info p {
            line-height: 1.7;
            margin: 10px 0;
        }

        .description {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            color: #555;
        }

        label {
            display: block;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        select,
        textarea {
            width: 100%;
            padding: 11px 13px;
            border: 1px solid #ccc;
            border-radius: 7px;
            font-family: inherit;
            font-size: 14px;
            color: #164e57;
            margin-bottom: 13px;
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            display: inline-block;
            border: none;
            border-radius: 7px;
            padding: 11px 19px;
            background: #0c5059;
            color: white;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
        }

        .btn:hover {
            background: #008f9f;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #008f9f;
            font-weight: bold;
            text-decoration: none;
        }

        .comment {
            border: 1px solid #e2e7e7;
            padding: 14px;
            margin-bottom: 12px;
            border-radius: 9px;
            background: #fafcfc;
        }

        .comment-date {
            color: #888;
            font-size: 12px;
            margin-top: 5px;
        }

        .comment-content {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            color: #555;
            margin-bottom: 0;
        }

        .empty {
            color: #888;
            text-align: center;
            padding: 20px;
        }

        footer {
            text-align: center;
            padding: 25px;
            color: #888;
            font-size: 13px;
        }

        @media(max-width: 550px) {
            .container {
                padding: 0 12px;
                margin-top: 22px;
            }

            .card {
                padding: 18px;
            }
        }
    </style>
</head>

<body>

    <?php require_once "navigation.php"; ?>

    <main class="container">

        <a href="<?= h($backLink) ?>" class="back-link">
            ← <?= h($backLabel) ?>
        </a>

        <div class="heading">
            <h1>Detail úlohy</h1>
            <div class="subtitle">
                Informácie, stav a komunikácia k úlohe
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="message <?= h($messageType) ?>">
                <?= h($message) ?>
            </div>
        <?php endif; ?>

        <!-- INFORMÁCIE O ÚLOHE -->

        <section class="card task-info">

            <h2><?= h($task['title']) ?></h2>

            <p>
                <strong>Tím:</strong>
                <?= h($task['team_name'] ?? 'Bez tímu') ?>
            </p>

            <p>
                <strong>Priradené:</strong>
                <?= h($task['assigned_user'] ?? 'Nepridelené') ?>
            </p>

            <p>
                <strong>Priorita:</strong>
                <?= h($task['priority']) ?>
            </p>

            <p>
                <strong>Stav:</strong>
                <?= h($task['status']) ?>
            </p>

            <p>
                <strong>Termín:</strong>
                <?= $task['deadline']
                    ? h(date('d.m.Y', strtotime($task['deadline'])))
                    : 'Bez termínu' ?>
            </p>

            <p>
                <strong>Popis:</strong>
            </p>

            <div class="description">
                <?= h($task['description'] ?? 'Bez popisu') ?>
            </div>

        </section>

        <!-- ZMENA STAVU -->

        <section class="card">

            <h2>Zmena stavu úlohy</h2>

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= h($csrfToken) ?>">

                <label for="status">Aktuálny stav</label>

                <select name="status" id="status" required>

                    <option value="To Do"
                        <?= $task['status'] === 'To Do' ? 'selected' : '' ?>>
                        Na spracovanie
                    </option>

                    <option value="In Progress"
                        <?= $task['status'] === 'In Progress' ? 'selected' : '' ?>>
                        Rozpracované
                    </option>

                    <option value="Done"
                        <?= $task['status'] === 'Done' ? 'selected' : '' ?>>
                        Dokončené
                    </option>

                </select>

                <button type="submit" name="change_status" class="btn">
                    Uložiť stav
                </button>

            </form>

        </section>

        <!-- PRIDANIE KOMENTÁRA -->

        <section class="card">

            <h2>Pridať komentár</h2>

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= h($csrfToken) ?>">

                <label for="content">Text komentára</label>

                <textarea
                    id="content"
                    name="content"
                    maxlength="5000"
                    placeholder="Napíš komentár..."
                    required></textarea>

                <button type="submit" name="add_comment" class="btn">
                    Pridať komentár
                </button>

            </form>

        </section>

        <!-- KOMENTÁRE -->

        <section class="card">

            <h2>Komentáre</h2>

            <?php if (empty($comments)): ?>

                <div class="empty">
                    Zatiaľ nebol pridaný žiadny komentár.
                </div>

            <?php else: ?>

                <?php foreach ($comments as $comment): ?>

                    <div class="comment">

                        <strong><?= h($comment['name']) ?></strong>

                        <div class="comment-date">
                            <?= h(date('d.m.Y H:i', strtotime($comment['created_at']))) ?>
                        </div>

                        <p class="comment-content">
                            <?= h($comment['content']) ?>
                        </p>

                    </div>

                <?php endforeach; ?>

            <?php endif; ?>

        </section>

    </main>

    <footer>
        STEP – Tímový projekt
    </footer>

</body>

</html>