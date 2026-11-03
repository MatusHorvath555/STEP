<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'teacher') {
    header("Location: dashboard.php");
    exit();
}

require_once __DIR__ . "/../config/config.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

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
        'team_id' => $_POST['team_id'],
        'assigned_to' => $_POST['assigned_to'],
        'title' => $_POST['title'],
        'description' => $_POST['description'],
        'status' => 'To Do',
        'priority' => $_POST['priority'],
        'deadline' => $_POST['deadline']
    ]);

    $message = "Úloha bola vytvorená.";
}

$users = $pdo->query("SELECT * FROM users ORDER BY name")
    ->fetchAll(PDO::FETCH_ASSOC);

$teams = $pdo->query("SELECT * FROM teams ORDER BY name")
    ->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>STEP - Nová úloha</title>

   
    <link rel="stylesheet" href="css/premenne.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <?php require_once "navigation.php"; ?>

    <main class="login-container">

        <h2>Nová úloha</h2>

        <?php if (!empty($message)): ?>
            <p class="alert">
                <?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?>
            </p>
        <?php endif; ?>

        <form method="POST" class="auth-form">

            <section class="form-row">
                <label for="title">Názov úlohy:</label>
                <input type="text" id="title" name="title" required>
            </section>

            <section class="form-row">
                <label for="description">Popis:</label>
                <textarea id="description" name="description"></textarea>
            </section>

            <section class="form-row">
                <label for="team_id">Tím:</label>
                <select id="team_id" name="team_id" required>

                    <?php foreach ($teams as $team): ?>
                        <option value="<?php echo $team['id']; ?>">
                            <?php echo htmlspecialchars($team['name']); ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </section>

            <section class="form-row">
                <label for="assigned_to">Priradiť používateľovi:</label>
                <select id="assigned_to" name="assigned_to" required>

                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>">
                            <?php echo htmlspecialchars($user['name']); ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </section>

            <section class="form-row">
                <label for="priority">Priorita:</label>
                <select id="priority" name="priority">
                    <option value="Low">Low</option>
                    <option value="Medium">Medium</option>
                    <option value="High">High</option>
                </select>
            </section>

            <section class="form-row">
                <label for="deadline">Deadline:</label>
                <input type="date" id="deadline" name="deadline">
            </section>

            <button type="submit" class="btn-submit">
                Vytvoriť úlohu
            </button>

        </form>

    </main>

</body>
</html>