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
require_once __DIR__ . "/../config/config.php";

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
    <title>Zoznam úloh | STEP</title>

    <link rel="stylesheet" href="css/premenne.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <?php require_once "navigation.php"; ?>

    <main class="container">

        <div class="heading">
            <div>
                <h1>Zoznam úloh</h1>
                <p class="subtitle">Prehľad všetkých úloh vytvorených v systéme</p>
            </div>
            <div class="heading-actions">
                <a href="teacher_dashboard.php" class="btn btn-secondary">
                    ← Späť na dashboard
                </a>
                <a href="create_task.php" class="btn">
                    + Pridať novú úlohu
                </a>
            </div>
        </div>

        <!-- Karta s tabuľkou úloh cez celú šírku -->
        <div class="card-table-wrapper">
            <h2>Všetky úlohy</h2>

            <?php if (empty($tasks)): ?>
                <div class="card-empty-box">
                    <div class="empty">
                        Zatiaľ neboli vytvorené žiadne úlohy.
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="step-table">
                        <thead>
                            <tr>
                                <th class="col-id">ID</th>
                                <th>Názov</th>
                                <th>Tím</th>
                                <th>Priradené</th>
                                <th>Status</th>
                                <th>Priorita</th>
                                <th>Deadline</th>
                                <th class="col-action">Akcia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td class="col-id">
                                        <?php echo (int) $task['id']; ?>
                                    </td>
                                    <td class="table-name">
                                        <?php echo htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($task['team_name'] ?? 'Bez tímu', ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($task['assigned_user'] ?? 'Nepriradené', ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $statusClass = 'todo';
                                            $st = $task['status'];
                                            if ($st === 'In Progress') $statusClass = 'progress';
                                            if ($st === 'Done') $statusClass = 'done';
                                        ?>
                                        <span class="status <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="priority">
                                            <?php echo htmlspecialchars($task['priority'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td class="table-date">
                                        <?php 
                                            $deadlineFormatted = $task['deadline'] ?? '';
                                            if (!empty($deadlineFormatted)) {
                                                $deadlineFormatted = date('d.m.Y', strtotime($deadlineFormatted));
                                            }
                                            echo htmlspecialchars($deadlineFormatted ?: 'Neurčený', ENT_QUOTES, 'UTF-8'); 
                                        ?>
                                    </td>
                                    <td class="col-action">
                                        <a href="task_detail.php?id=<?php echo (int) $task['id']; ?>" class="btn btn-table-action">
                                            Zobraziť
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>

</body>

</html>