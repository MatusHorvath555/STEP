<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (($_SESSION['role'] ?? '') !== 'teacher') {
    header("Location: dashboard.php");
    exit();
}

require_once __DIR__ . "/../config/config.php";

$message = "";
$messageType = "";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        die("Chyba overenia CSRF tokenu.");
    }

    $teamId = filter_input(INPUT_POST, 'team_id', FILTER_VALIDATE_INT);
    $assignedTo = filter_input(INPUT_POST, 'assigned_to', FILTER_VALIDATE_INT);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'Medium';
    $deadline = $_POST['deadline'] ?? null;

    if ($teamId && $assignedTo && $title !== '') {
        $sql = "INSERT INTO tasks
        (
            team_id,
            assigned_to,
            title,
            description,
            status,
            priority,
            deadline
        )
        VALUES
        (
            :team_id,
            :assigned_to,
            :title,
            :description,
            :status,
            :priority,
            :deadline
        )";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            'team_id' => $teamId,
            'assigned_to' => $assignedTo,
            'title' => $title,
            'description' => $description,
            'status' => 'To Do',
            'priority' => in_array($priority, ['Low', 'Medium', 'High'], true) ? $priority : 'Medium',
            'deadline' => ($deadline !== '') ? $deadline : null
        ]);

        $message = "Úloha bola úspešne vytvorená.";
        $messageType = "success";
    } else {
        $message = "Vyplňte povinné polia (Názov, Tím, Priradená osoba).";
        $messageType = "error";
    }
}

$users = $pdo->query("SELECT id, name FROM users ORDER BY name")
    ->fetchAll(PDO::FETCH_ASSOC);

$teams = $pdo->query("SELECT id, name FROM teams ORDER BY name")
    ->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vytvorenie úlohy | STEP</title>

    <link rel="stylesheet" href="css/premenne.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <?php require_once "navigation.php"; ?>

    <main class="container">

        <div class="heading">
            <div>
                <h1>Vytvorenie novej úlohy</h1>
                <p class="subtitle">Zadajte novú úlohu pre vybraný tím a priradeného člena</p>
            </div>
            <div class="heading-actions">
                <a href="tasks.php" class="btn btn-secondary">
                    ← Späť na zoznam úloh
                </a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>Detail úlohy</h2>

            <form method="POST" class="auth-form form-grid-layout">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                <div class="form-group full-width">
                    <label for="title">Názov úlohy *</label>
                    <input type="text" id="title" name="title" required placeholder="Zadajte názov úlohy...">
                </div>

                <div class="form-group full-width">
                    <label for="description">Popis</label>
                    <textarea id="description" name="description" placeholder="Zadajte podrobný popis úlohy..."></textarea>
                </div>

                <div class="form-group">
                    <label for="team_id">Tím *</label>
                    <select id="team_id" name="team_id" required>
                        <option value="">-- Vyberte tím --</option>
                        <?php foreach ($teams as $team): ?>
                            <option value="<?php echo (int) $team['id']; ?>">
                                <?php echo htmlspecialchars($team['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="assigned_to">Priradiť používateľovi *</label>
                    <select id="assigned_to" name="assigned_to" required>
                        <option value="">-- Vyberte používateľa --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo (int) $user['id']; ?>">
                                <?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="priority">Priorita</label>
                    <select id="priority" name="priority">
                        <option value="Low">Low</option>
                        <option value="Medium" selected>Medium</option>
                        <option value="High">High</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="deadline">Deadline</label>
                    <input type="date" id="deadline" name="deadline">
                </div>

                <div class="form-actions full-width">
                    <button type="submit" class="btn btn-full">
                        Vytvoriť úlohu
                    </button>
                </div>

            </form>
        </div>

    </main>

</body>

</html>