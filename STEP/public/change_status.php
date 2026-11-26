<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . "/../config/config.php";
require_once __DIR__ . "/webhook.php";

$taskId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$taskId) {
    die("Neplatné ID úlohy.");
}

// Inicializácia CSRF tokenu
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$stmt = $pdo->prepare("
    SELECT *
    FROM tasks
    WHERE id = :id
    AND assigned_to = :user_id
");

$stmt->execute([
    'id' => $taskId,
    'user_id' => $_SESSION['user_id']
]);

$task = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    die("Úloha neexistuje alebo k nej nemáte prístup.");
}

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        die("Chyba overenia CSRF tokenu.");
    }

    $status = $_POST['status'] ?? '';
    $allowedStatuses = ['To Do', 'In Progress', 'Done'];

    if (!in_array($status, $allowedStatuses, true)) {
        $message = "Neplatný stav úlohy.";
        $messageType = "error";
    } else {
        $update = $pdo->prepare("
            UPDATE tasks
            SET status = :status
            WHERE id = :id
            AND assigned_to = :user_id
        ");

        $update->execute([
            'status' => $status,
            'id' => $taskId,
            'user_id' => $_SESSION['user_id']
        ]);

        // len pri skutočnej zmene na Done, nie keď už hotovú úlohu uloží znova
        if ($status === 'Done' && $task['status'] !== 'Done') {
            oznamHotovuUlohu($task['title']);
        }

        header("Location: my_tasks.php");
        exit();
    }
}

?>

<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zmena stavu úlohy | STEP</title>

    <link rel="stylesheet" href="css/premenne.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <?php require_once "navigation.php"; ?>

    <main class="container">

        <div class="heading">
            <div>
                <h1>Zmena stavu úlohy</h1>
                <p class="subtitle">Aktualizujte aktuálny stav riešenia tejto úlohy</p>
            </div>
            <div>
                <a href="my_tasks.php" class="btn btn-secondary">
                    ← Späť na moje úlohy
                </a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="card profile-card-wrapper">
            <h2>Úloha: <?php echo htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8'); ?></h2>

            <form method="POST" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                <div class="form-group">
                    <label for="status">Status úlohy</label>
                    <select id="status" name="status">
                        <option value="To Do" <?php if ($task['status'] == "To Do") echo "selected"; ?>>
                            To Do
                        </option>
                        <option value="In Progress" <?php if ($task['status'] == "In Progress") echo "selected"; ?>>
                            In Progress
                        </option>
                        <option value="Done" <?php if ($task['status'] == "Done") echo "selected"; ?>>
                            Done
                        </option>
                    </select>
                </div>

                <div class="form-actions-spacing">
                    <button type="submit" class="btn btn-full">
                        Uložiť zmeny
                    </button>
                </div>

            </form>
        </div>

    </main>

</body>

</html>