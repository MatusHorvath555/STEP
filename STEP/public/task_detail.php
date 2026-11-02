
<?php

session_start();

// Kontrola prihlásenia
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Pripojenie k databáze
require_once "config.php";

// Kontrola ID úlohy
if (!isset($_GET['id']) || !ctype_digit((string) $_GET['id']) || (int) $_GET['id'] <= 0) {
    die("Neplatné ID úlohy.");
}

$taskId = (int) $_GET['id'];
$userId = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

// Povolené roly
if ($role !== 'teacher' && $role !== 'student') {
    die("Prístup zamietnutý.");
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Načítanie detailu úlohy
$sql = "
    SELECT
        t.*,
        u.name AS assigned_user,
        tm.name AS team_name
    FROM tasks t
    LEFT JOIN users u ON t.assigned_to = u.id
    LEFT JOIN teams tm ON t.team_id = tm.id
    WHERE t.id = :id
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $taskId]);

$task = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    die("Úloha neexistuje.");
}

// Kontrola oprávnení študenta
if ($role === 'student') {

    $accessStmt = $pdo->prepare("
        SELECT 1
        FROM team_members
        WHERE team_id = :team_id
        AND user_id = :user_id
    ");

    $accessStmt->execute([
        'team_id' => $task['team_id'],
        'user_id' => $userId
    ]);

    $isTeamMember = $accessStmt->fetchColumn();

    if (
        !$isTeamMember ||
        (int) $task['assigned_to'] !== $userId
    ) {
        die("Prístup zamietnutý.");
    }
}

// Spracovanie formulárov
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Kontrola CSRF tokenu
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        die("Neplatná požiadavka. Obnovte stránku a skúste to znova.");
    }

    // Zmena stavu úlohy
    if (isset($_POST['change_status'])) {

        $status = $_POST['status'] ?? '';

        $allowedStatuses = [
            'To Do',
            'In Progress',
            'Done'
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            die("Neplatný stav úlohy.");
        }

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

        header("Location: task_detail.php?id=" . $taskId);
        exit();
    }

    // Pridanie komentára
    if (isset($_POST['add_comment'])) {

        $content = trim($_POST['content'] ?? '');

        if ($content === '') {
            die("Komentár nemôže byť prázdny.");
        }

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

        header("Location: task_detail.php?id=" . $taskId);
        exit();
    }

    die("Neznáma požiadavka.");
}

// Načítanie komentárov
$commentStmt = $pdo->prepare("
    SELECT
        c.*,
        u.name
    FROM comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.task_id = :id
    ORDER BY c.created_at DESC
");

$commentStmt->execute([
    'id' => $taskId
]);

$comments = $commentStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Detail úlohy</title>
</head>

<body>

    <h1>Detail úlohy</h1>

    <p>
        <?php if ($role === 'teacher'): ?>
            <a href="teacher_dashboard.php">Späť na dashboard</a>
        <?php else: ?>
            <a href="my_tasks.php">Späť na úlohy</a>
        <?php endif; ?>
    </p>

    <hr>

    <h2>
        <?php echo htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8'); ?>
    </h2>

    <p>
        <strong>Tím:</strong>
        <?php echo htmlspecialchars($task['team_name'] ?? 'Bez tímu', ENT_QUOTES, 'UTF-8'); ?>
    </p>

    <p>
        <strong>Priradené:</strong>
        <?php echo htmlspecialchars($task['assigned_user'] ?? 'Nepriradené', ENT_QUOTES, 'UTF-8'); ?>
    </p>

    <p>
        <strong>Priorita:</strong>
        <?php echo htmlspecialchars($task['priority'], ENT_QUOTES, 'UTF-8'); ?>
    </p>

    <p>
        <strong>Stav:</strong>
        <?php echo htmlspecialchars($task['status'], ENT_QUOTES, 'UTF-8'); ?>
    </p>

    <p>
        <strong>Deadline:</strong>
        <?php echo htmlspecialchars($task['deadline'] ?? 'Neurčený', ENT_QUOTES, 'UTF-8'); ?>
    </p>

    <p>
        <strong>Popis:</strong>
        <br><br>
        <?php echo nl2br(htmlspecialchars($task['description'] ?? 'Bez popisu', ENT_QUOTES, 'UTF-8')); ?>
    </p>

    <hr>

    <h3>Zmena stavu</h3>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"
        >

        <select name="status" required>

            <option value="To Do" <?php echo $task['status'] === 'To Do' ? 'selected' : ''; ?>>
                To Do
            </option>

            <option value="In Progress" <?php echo $task['status'] === 'In Progress' ? 'selected' : ''; ?>>
                In Progress
            </option>

            <option value="Done" <?php echo $task['status'] === 'Done' ? 'selected' : ''; ?>>
                Done
            </option>

        </select>

        <button type="submit" name="change_status">
            Uložiť stav
        </button>

    </form>

    <hr>

    <h3>Pridať komentár</h3>

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"
        >

        <textarea
            name="content"
            rows="4"
            cols="60"
            required
        ></textarea>

        <br><br>

        <button type="submit" name="add_comment">
            Pridať komentár
        </button>

    </form>

    <hr>

    <h3>Komentáre</h3>

    <?php if (empty($comments)): ?>

        <p>Zatiaľ nebol pridaný žiadny komentár.</p>

    <?php else: ?>

        <?php foreach ($comments as $comment): ?>

            <div style="border: 1px solid #ccc; padding: 10px; margin-bottom: 10px;">

                <strong>
                    <?php echo htmlspecialchars($comment['name'], ENT_QUOTES, 'UTF-8'); ?>
                </strong>

                <br>

                <small>
                    <?php echo htmlspecialchars($comment['created_at'], ENT_QUOTES, 'UTF-8'); ?>
                </small>

                <p>
                    <?php echo nl2br(htmlspecialchars($comment['content'], ENT_QUOTES, 'UTF-8')); ?>
                </p>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</body>
</html>
