<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/../config/config.php";

$userId = (int) $_SESSION['user_id'];

function h($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}
// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// Tím prihláseného používateľa
$stmt = $pdo->prepare("
    SELECT t.id, t.name
    FROM teams t
    INNER JOIN team_members tm ON tm.team_id = t.id
    WHERE tm.user_id = :user_id
    ORDER BY t.name
    LIMIT 1
");
$stmt->execute(['user_id' => $userId]);
$team = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$team) {
    exit("Nie si v žiadnom tíme.");
}

// Úlohy tímu aj s menom priradeného člena, najbližší termín prvý
$stmt = $pdo->prepare("
    SELECT t.id, t.title, t.status, t.priority, t.deadline, u.name AS assigned_name
    FROM tasks t
    LEFT JOIN users u ON u.id = t.assigned_to
    WHERE t.team_id = :team_id
    ORDER BY t.deadline
");
$stmt->execute(['team_id' => $team['id']]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statuses = ['To Do', 'In Progress', 'Done'];
// Počet úloh v každom stave, napr. ['To Do' => 4, 'In Progress' => 2, 'Done' => 4]
// Stav, v ktorom nie je žiadna úloha, v poli chýba – preto nižšie "?? 0"
$counts = array_count_values(array_column($tasks, 'status'));
$total = count($tasks);
$done = $counts['Done'] ?? 0;

// Percento dokončených úloh (bez úloh by sa delilo nulou)
if ($total > 0) {
    $donePercent = round($done / $total * 100);
} else {
    $donePercent = 0;
}

// Členovia tímu, vedúci ako prvý
$stmt = $pdo->prepare("
    SELECT u.name, tm.is_team_leader
    FROM team_members tm
    INNER JOIN users u ON u.id = tm.user_id
    WHERE tm.team_id = :team_id
    ORDER BY tm.is_team_leader DESC, u.name
");
$stmt->execute(['team_id' => $team['id']]);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

$teachers = $pdo->query("SELECT name FROM users WHERE role = 'teacher' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nástenka – <?= h($team['name']) ?></title>
    <link rel="stylesheet" href="css/premenne.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/kanban.css">

</head>

<body>
    <?php require_once "navigation.php"; ?>

            <main class="container">

        <!-- Dlaždice hore – rovnaké triedy ako v Paneli vedúceho, takže majú hotový štýl -->
        <section class="stats">
            <div class="stat">
                <div class="stat-number teal"><?= (int) $team['id'] ?></div>
                <div class="stat-label">Číslo tímu</div>
            </div>

            <div class="stat">
                <div class="stat-number teal"><?= $counts['To Do'] ?? 0 ?></div>
                <div class="stat-label">To Do</div>
            </div>
            <div class="stat">
                <div class="stat-number orange"><?= $counts['In Progress'] ?? 0 ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat">
                <div class="stat-number green"><?= $counts['Done'] ?? 0 ?></div>
                <div class="stat-label">Done</div>
            </div>
        </section>

        <!-- Nástenka -->
        <section class="card">
            <h1>Tím: <?= h($team['name']) ?></h1>
            <p>Dokončené: <?= $donePercent ?> %</p>

            <div class="nastenka">

                <!-- Tri stĺpce s kartami -->
                <div class="stlpce">
                    <?php foreach ($statuses as $status): ?>
                        <section class="stlpec">
                            <h2><?= h($status) ?></h2>

                            <div class="zoznam" data-status="<?= h($status) ?>">
                                <?php foreach ($tasks as $task): ?>
                                    <?php if ($task['status'] !== $status) continue; ?>

                                    <article class="karta <?= h(strtolower($task['priority'])) ?>" data-id="<?= (int) $task['id'] ?>">
                                        <a href="task_detail.php?id=<?= (int) $task['id'] ?>"><?= h($task['title']) ?></a>
                                        <p><?= $task['deadline'] ? h(date('d.m.Y', strtotime($task['deadline']))) : 'Bez termínu' ?></p>
                                        <p><?= h($task['assigned_name'] ?? 'Nepridelené') ?></p>
                                        <p class="priorita">Priorita: <?= h($task['priority']) ?></p>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>

                <!-- Pravý panel: členovia a súčty -->
                <aside class="panel">
                    <h2>Členovia</h2>
                    <ul class="clenovia">
                        <?php foreach ($members as $member): ?>
                            <li>
                                <?= h($member['name']) ?>
                                <?php if ($member['is_team_leader']): ?>
                                    <span class="lead">Team Leader</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <table class="sucty">
                        <?php foreach ($statuses as $status): ?>
                            <tr>
                                <th><?= h($status) ?></th>
                                <td><?= $counts[$status] ?? 0 ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <th>Total</th>
                            <td><?= $total ?></td>
                        </tr>
                    </table>
                    
                    <h2>Vyučujúci</h2>
                    <ul class="clenovia">
                        <?php foreach ($teachers as $teacher): ?>
                            <li><?= h($teacher) ?></li>
                        <?php endforeach; ?>
                    </ul>

                </aside>

            </div>
        </section>
    </main>


    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>const CSRF = <?= json_encode($_SESSION['csrf_token']) ?>;</script>
    <script src="js/kanban.js"></script>
    
</body>

</html>
