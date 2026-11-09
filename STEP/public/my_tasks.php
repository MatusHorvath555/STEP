<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . "/../config/config.php";

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moje úlohy | STEP</title>

    <link rel="stylesheet" href="css/premenne.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <?php require_once "navigation.php"; ?>

    <main class="container my-tasks-page">

        <div class="heading">
            <div>
                <h1>Moje úlohy</h1>
                <p class="subtitle">Zoznam úloh priradených pre: <strong><?php echo htmlspecialchars($_SESSION['name'], ENT_QUOTES, 'UTF-8'); ?></strong></p>
            </div>
            <div>
                <span class="role-label">
                    <?= htmlspecialchars($_SESSION['role'] ?? 'Používateľ', ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>
        </div>

        <?php if (count($tasks) == 0): ?>

            <div class="card">
                <div class="empty">
                    Momentálne nemáte pridelené žiadne úlohy.
                </div>
            </div>

        <?php else: ?>

            <div class="task-list">

                <?php foreach ($tasks as $task): ?>
                    <?php
                    // Určenie CSS triedy pre stav úlohy
                    $statusClass = 'todo';
                    if ($task['status'] === 'V procese' || $task['status'] === 'in_progress') {
                        $statusClass = 'progress';
                    } elseif ($task['status'] === 'Dokončené' || $task['status'] === 'done') {
                        $statusClass = 'done';
                    }
                    ?>

                    <div class="task">
                        <div class="task-header">
                            <h3><?php echo htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <span class="status <?php echo $statusClass; ?>">
                                <?php echo htmlspecialchars($task['status'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </div>

                        <div class="task-meta">
                            <p><strong>Tím:</strong> <?php echo htmlspecialchars($task['team_name'] ?? 'Bez tímu', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><strong>Priorita:</strong> <span class="priority"><?php echo htmlspecialchars($task['priority'], ENT_QUOTES, 'UTF-8'); ?></span></p>
                            <p><strong>Deadline:</strong> <?php echo htmlspecialchars($task['deadline'] ?? 'Neurčený', ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>

                        <?php if (!empty($task['description'])): ?>
                            <div class="task-description">
                                <?php echo nl2br(htmlspecialchars($task['description'], ENT_QUOTES, 'UTF-8')); ?>
                            </div>
                        <?php endif; ?>

                        <div class="task-actions-row">
                            <a href="change_status.php?id=<?php echo (int)$task['id']; ?>" class="btn btn-accent">
                                Zmeniť stav
                            </a>
                            <a href="task_detail.php?id=<?php echo (int)$task['id']; ?>" class="btn">
                                Zobraziť detail
                            </a>
                        </div>
                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </main>

</body>

</html>