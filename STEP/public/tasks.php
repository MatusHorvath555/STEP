<?php

session_start();

// Kontrola prihlásenia
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Kontrola oprávnenia učiteľa
if (($_SESSION['role'] ?? '') !== 'teacher') {
    die("Prístup zamietnutý.");
}

// Pripojenie k databáze
require_once "config.php";

// Načítanie všetkých úloh
$sql = "
    SELECT
        t.id,
        t.title,
        t.status,
        t.priority,
        t.deadline,
        u.name AS assigned_user,
        tm.name AS team_name
    FROM tasks t
    LEFT JOIN users u ON t.assigned_to = u.id
    LEFT JOIN teams tm ON t.team_id = tm.id
    ORDER BY t.id DESC
";

$stmt = $pdo->query($sql);

$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Zoznam úloh</title>
</head>

<body>
    <?php require_once "navigation.php"; ?>

    <h1>Zoznam úloh</h1>

    <p>
        <a href="teacher_dashboard.php">
            Späť na dashboard
        </a>
    </p>

    <p>
        <a href="create_task.php">
            + Pridať novú úlohu
        </a>
    </p>

    <hr>

    <?php if (empty($tasks)): ?>

        <p>Zatiaľ neboli vytvorené žiadne úlohy.</p>

    <?php else: ?>

        <table border="1" cellpadding="10">

            <thead>
                <tr>
                    <th>ID</th>
                    <th>Názov</th>
                    <th>Tím</th>
                    <th>Priradené</th>
                    <th>Status</th>
                    <th>Priorita</th>
                    <th>Deadline</th>
                    <th>Detail</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($tasks as $task): ?>

                    <tr>

                        <td>
                            <?php echo (int) $task['id']; ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8'); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($task['team_name'] ?? 'Bez tímu', ENT_QUOTES, 'UTF-8'); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($task['assigned_user'] ?? 'Nepriradené', ENT_QUOTES, 'UTF-8'); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($task['status'], ENT_QUOTES, 'UTF-8'); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($task['priority'], ENT_QUOTES, 'UTF-8'); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($task['deadline'] ?? 'Neurčený', ENT_QUOTES, 'UTF-8'); ?>
                        </td>

                        <td>
                            <a href="task_detail.php?id=<?php echo (int) $task['id']; ?>">
                                Zobraziť
                            </a>
                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    <?php endif; ?>

</body>

</html>