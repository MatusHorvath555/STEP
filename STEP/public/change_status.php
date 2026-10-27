<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require "database.php";

$taskId = $_GET['id'];

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

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $status = $_POST['status'];

    $update = $pdo->prepare("
        UPDATE tasks
        SET status = :status
        WHERE id = :id
    ");

    $update->execute([
        'status' => $status,
        'id' => $taskId
    ]);

    header("Location: my_tasks.php");
    exit();
}

?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Zmena stavu</title>
</head>

<body>

    <h2>Zmena stavu úlohy</h2>

    <p>
        Úloha:
        <strong><?php echo htmlspecialchars($task['title']); ?></strong>
    </p>

    <form method="POST">

        <label>Status:</label>

        <select name="status">

            <option value="To Do"
                <?php if ($task['status'] == "To Do") echo "selected"; ?>>
                To Do
            </option>

            <option value="In Progress"
                <?php if ($task['status'] == "In Progress") echo "selected"; ?>>
                In Progress
            </option>

            <option value="Done"
                <?php if ($task['status'] == "Done") echo "selected"; ?>>
                Done
            </option>

        </select>

        <br><br>

        <button type="submit">
            Uložiť
        </button>

    </form>

    <p>
        <a href="my_tasks.php">Späť na moje úlohy</a>
    </p>

</body>

</html>