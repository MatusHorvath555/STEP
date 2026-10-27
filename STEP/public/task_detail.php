<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require "database.php";

if (!isset($_GET['id'])) {
    die("Úloha nebola zadaná.");
}

$taskId = $_GET['id'];

/*
 * DETAIL ÚLOHY
 */

$sql = "

SELECT

    t.*,

    u.name AS assigned_user,

    tm.name AS team_name

FROM tasks t

LEFT JOIN users u
ON t.assigned_to = u.id

LEFT JOIN teams tm
ON t.team_id = tm.id

WHERE t.id = :id

";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    'id' => $taskId
]);

$task = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$task) {
    die("Úloha neexistuje.");
}

/*
 * ŠTUDENT VIDÍ IBA SVOJE ÚLOHY
 */

if (
    $_SESSION['role'] == 'student'
    &&
    $task['assigned_to'] != $_SESSION['user_id']
) {
    die("Prístup zamietnutý.");
}

/*
 * ZMENA STAVU
 */

if (
    isset($_POST['change_status'])
) {

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

    header("Location: task_detail.php?id=" . $taskId);
    exit();
}

/*
 * NOVÝ KOMENTÁR
 */

if (
    isset($_POST['add_comment'])
) {

    $content = trim($_POST['content']);

    if (!empty($content)) {

        $insert = $pdo->prepare("
            INSERT INTO comments
            (
                task_id,
                user_id,
                content
            )
            VALUES
            (
                :task_id,
                :user_id,
                :content
            )
        ");

        $insert->execute([
            'task_id' => $taskId,
            'user_id' => $_SESSION['user_id'],
            'content' => $content
        ]);

        header("Location: task_detail.php?id=" . $taskId);
        exit();
    }
}

/*
 * KOMENTÁRE
 */

$commentStmt = $pdo->prepare("

SELECT

    c.*,

    u.name

FROM comments c

JOIN users u
ON c.user_id = u.id

WHERE c.task_id = :id

ORDER BY c.created_at DESC

");

$commentStmt->execute([
    'id' => $taskId
]);

$comments =
    $commentStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="UTF-8">
    <title>Detail úlohy</title>
</head>

<body>

    <h1>Detail úlohy</h1>

    <p>

        <a href="my_tasks.php">
            Späť na úlohy
        </a>

    </p>

    <hr>

    <h2>

        <?php echo htmlspecialchars($task['title']); ?>

    </h2>

    <p>

        <strong>Tím:</strong>

        <?php echo htmlspecialchars($task['team_name']); ?>

    </p>

    <p>

        <strong>Priradené:</strong>

        <?php echo htmlspecialchars($task['assigned_user']); ?>

    </p>

    <p>

        <strong>Priorita:</strong>

        <?php echo htmlspecialchars($task['priority']); ?>

    </p>

    <p>

        <strong>Status:</strong>

        <?php echo htmlspecialchars($task['status']); ?>

    </p>

    <p>

        <strong>Deadline:</strong>

        <?php echo htmlspecialchars($task['deadline']); ?>

    </p>

    <p>

        <strong>Popis:</strong>

        <br><br>

        <?php echo nl2br(htmlspecialchars($task['description'])); ?>

    </p>

    <hr>

    <h3>Zmena stavu</h3>

    <form method="POST">

        <select name="status">

            <option
                value="To Do"
                <?php if ($task['status'] == "To Do") echo "selected"; ?>>
                To Do
            </option>

            <option
                value="In Progress"
                <?php if ($task['status'] == "In Progress") echo "selected"; ?>>
                In Progress
            </option>

            <option
                value="Done"
                <?php if ($task['status'] == "Done") echo "selected"; ?>>
                Done
            </option>

        </select>

        <button
            type="submit"
            name="change_status">

            Uložiť stav

        </button>

    </form>

    <hr>

    <h3>Pridať komentár</h3>

    <form method="POST">

        <textarea
            name="content"
            rows="4"
            cols="60"
            required></textarea>

        <br><br>

        <button
            type="submit"
            name="add_comment">

            Pridať komentár

        </button>

    </form>

    <hr>

    <h3>Komentáre</h3>

    <?php foreach ($comments as $comment): ?>

        <div
            style="
        border:1px solid #ccc;
        padding:10px;
        margin-bottom:10px;">

            <strong>

                <?php echo htmlspecialchars($comment['name']); ?>

            </strong>

            <br>

            <small>

                <?php echo $comment['created_at']; ?>

            </small>

            <p>

                <?php
                echo nl2br(
                    htmlspecialchars(
                        $comment['content']
                    )
                );
                ?>

            </p>

        </div>

    <?php endforeach; ?>

</body>

</html>