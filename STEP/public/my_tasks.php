<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require "database.php";

$userId = $_SESSION['user_id'];

$sql = "

SELECT
    t.id,
    t.title,
    t.description,
    t.status,
    t.priority,
    t.deadline,

    tm.name AS team_name

FROM tasks t

LEFT JOIN teams tm
ON t.team_id = tm.id

WHERE t.assigned_to = :user_id

ORDER BY t.deadline ASC

";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    'user_id' => $userId
]);

$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="sk">

<head>

    <meta charset="UTF-8">
    <title>Moje úlohy</title>

</head>

<body>

    <h1>Moje úlohy</h1>

    <p>
        Prihlásený používateľ:
        <strong><?php echo htmlspecialchars($_SESSION['name']); ?></strong>
    </p>

    <p>
        <a href="dashboard.php">Dashboard</a>
    </p>

    <?php if (count($tasks) == 0): ?>

        <p>Momentálne nemáte pridelené žiadne úlohy.</p>

    <?php else: ?>

        <table border="1" cellpadding="10">

            <tr>
                <th>ID</th>
                <th>Názov</th>
                <th>Popis</th>
                <th>Tím</th>
                <th>Status</th>
                <th>Priorita</th>
                <th>Deadline</th>
                <th>Akcia</th>
                <th>Detail</th>
            </tr>

            <?php foreach ($tasks as $task): ?>

                <tr>

                    <td>
                        <?php echo $task['id']; ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($task['title']); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($task['description']); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($task['team_name']); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($task['status']); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($task['priority']); ?>
                    </td>

                    <td>
                        <?php echo htmlspecialchars($task['deadline']); ?>
                    </td>
                    <td>
                        <a href="change_status.php?id=<?php echo $task['id']; ?>">
                            Zmeniť stav
                        </a>
                    </td>
                    <td>
                        <a href="task_detail.php?id=<?php echo $task['id']; ?>">
                            Zobraziť detail
                        </a>
                </tr>

            <?php endforeach; ?>

        </table>

    <?php endif; ?>

</body>

</html>