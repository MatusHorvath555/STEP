<?php

require "database.php";

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
    <title>Vytvorenie úlohy</title>
</head>

<body>

    <h2>Nová úloha</h2>

    <?php if (!empty($message)): ?>
        <p><?php echo $message; ?></p>
    <?php endif; ?>

    <form method="POST">

        <label>Názov úlohy:</label><br>
        <input type="text" name="title" required><br><br>

        <label>Popis:</label><br>
        <textarea name="description"></textarea><br><br>

        <label>Tím:</label><br>
        <select name="team_id" required>

            <?php foreach ($teams as $team): ?>

                <option value="<?php echo $team['id']; ?>">
                    <?php echo $team['name']; ?>
                </option>

            <?php endforeach; ?>

        </select><br><br>

        <label>Priradiť používateľovi:</label><br>
        <select name="assigned_to" required>

            <?php foreach ($users as $user): ?>

                <option value="<?php echo $user['id']; ?>">
                    <?php echo $user['name']; ?>
                </option>

            <?php endforeach; ?>

        </select><br><br>

        <label>Priorita:</label><br>
        <select name="priority">
            <option value="Low">Low</option>
            <option value="Medium">Medium</option>
            <option value="High">High</option>
        </select><br><br>

        <label>Deadline:</label><br>
        <input type="date" name="deadline"><br><br>

        <button type="submit">
            Vytvoriť úlohu
        </button>

    </form>

</body>

</html>