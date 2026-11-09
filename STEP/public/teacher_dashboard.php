<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (($_SESSION['role'] ?? '') !== 'teacher') {
    die("Prístup zamietnutý.");
}

require_once __DIR__ . "/../config/config.php";

// ==========================================
// 1. NACITANIE UDAJOV UCITELA
// ==========================================

$stmt = $pdo->prepare("
    SELECT id, name, email, role, created_at
    FROM users
    WHERE id = :id
");

$stmt->execute([
    'id' => $_SESSION['user_id']
]);

$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    die("Používateľ nebol nájdený.");
}

$name = $teacher['name'];

// Vytvorenie inicialok
$nameParts = preg_split('/\s+/', trim($name));
$initials = '';

foreach ($nameParts as $part) {
    if ($part !== '') {
        $initials .= mb_substr($part, 0, 1, 'UTF-8');
    }
}

$initials = mb_strtoupper($initials, 'UTF-8');


// ==========================================
// 2. PREHLADOVE STATISTIKY
// ==========================================

// Pocet timov
$totalTeams = $pdo->query("
    SELECT COUNT(*) FROM teams
")->fetchColumn();

// Pocet studentov
$totalStudents = $pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role = 'student'
")->fetchColumn();

// Pocet uloh
$totalTasks = $pdo->query("
    SELECT COUNT(*) FROM tasks
")->fetchColumn();

// Pocet dokonc enych uloh
$completedTasks = $pdo->query("
    SELECT COUNT(*)
    FROM tasks
    WHERE status = 'Done'
")->fetchColumn();


// ==========================================
// 3. PREHLAD TIMOV
// ==========================================

$stmt = $pdo->query("
    SELECT
        teams.id,
        teams.name,
        COUNT(DISTINCT team_members.user_id) AS members_count,
        COUNT(DISTINCT tasks.id) AS tasks_count,
        COUNT(DISTINCT tasks.id)
            FILTER (WHERE tasks.status = 'Done') AS completed_count
    FROM teams

    LEFT JOIN team_members
        ON teams.id = team_members.team_id

    LEFT JOIN tasks
        ON teams.id = tasks.team_id

    GROUP BY teams.id, teams.name

    ORDER BY teams.name ASC
");

$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ==========================================
// 4. PREHLAD ULOH
// ==========================================

$stmt = $pdo->query("
    SELECT
        tasks.id,
        tasks.title,
        tasks.status,
        tasks.priority,
        tasks.deadline,
        teams.name AS team_name,
        users.name AS student_name

    FROM tasks

    LEFT JOIN teams
        ON tasks.team_id = teams.id

    LEFT JOIN users
        ON tasks.assigned_to = users.id

    ORDER BY
        CASE
            WHEN tasks.status = 'In Progress' THEN 1
            WHEN tasks.status = 'To Do' THEN 2
            WHEN tasks.status = 'Done' THEN 3
            ELSE 4
        END,
        tasks.deadline ASC NULLS LAST

    LIMIT 20
");

$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Teacher Dashboard | STEP</title>

    <link rel="stylesheet" href="css/premenne.css">
    <link rel="stylesheet" href="css/style.css">


</head>

<body>

    <?php require_once "navigation.php"; ?>

    <main class="container teacher-dashboard">

        <h1>Učiteľský dashboard</h1>


        <!-- ==================================
             PROFIL UCITELA
        =================================== -->

        <div class="card">

            <div class="user-info">

                <div class="avatar">
                    <?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?>
                </div>

                <div class="user-details">

                    <h2>
                        <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
                    </h2>

                    <a href="teacher_profile.php" class="profile-link">
                        Zobraziť môj profil →
                    </a>

                </div>

            </div>

            <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-top: 25px;">

                <div style="flex: 1; min-width: 220px; padding: 18px; border: 1px solid #e1ebeb; border-radius: 10px;">

                    <p class="subtitle" style="margin-bottom: 8px;">Email</p>

                    <strong>
                        <?php echo htmlspecialchars($teacher['email'], ENT_QUOTES, 'UTF-8'); ?>
                    </strong>

                </div>

                <div style="flex: 1; min-width: 150px; padding: 18px; border: 1px solid #e1ebeb; border-radius: 10px;">

                    <p class="subtitle" style="margin-bottom: 8px;">Rola</p>

                    <strong>Učiteľ</strong>

                </div>

            </div>

        </div>


        <!-- ==================================
             STATISTIKY
        =================================== -->

        <h2 style="margin-top: 30px;">Celkový prehľad</h2>

        <div class="stats-grid">

            <div class="stat-card">
                <p>Počet tímov</p>
                <h2><?php echo $totalTeams; ?></h2>
            </div>

            <div class="stat-card">
                <p>Počet študentov</p>
                <h2><?php echo $totalStudents; ?></h2>
            </div>

            <div class="stat-card">
                <p>Počet úloh</p>
                <h2><?php echo $totalTasks; ?></h2>
            </div>

            <div class="stat-card">
                <p>Dokončené úlohy</p>
                <h2><?php echo $completedTasks; ?></h2>
            </div>

        </div>


        <!-- ==================================
             PREHLAD TIMOV
        =================================== -->

        <section class="dashboard-section">

            <h2>Prehľad tímov</h2>

            <div class="table-wrapper">

                <table class="dashboard-table">

                    <thead>
                        <tr>
                            <th>Tím</th>
                            <th>Počet členov</th>
                            <th>Počet úloh</th>
                            <th>Dokončené úlohy</th>
                            <th>Postup</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (empty($teams)): ?>

                            <tr>
                                <td colspan="5" class="empty-message">
                                    Zatiaľ neexistujú žiadne tímy.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($teams as $team): ?>

                                <?php
                                $progress = $team['tasks_count'] > 0
                                    ? round(
                                        ($team['completed_count'] / $team['tasks_count']) * 100
                                    )
                                    : 0;
                                ?>

                                <tr>

                                    <td>
                                        <strong>
                                            <?php echo htmlspecialchars($team['name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?php echo $team['members_count']; ?>
                                    </td>

                                    <td>
                                        <?php echo $team['tasks_count']; ?>
                                    </td>

                                    <td>
                                        <?php echo $team['completed_count']; ?>
                                    </td>

                                    <td>
                                        <?php echo $progress; ?> %
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- ==================================
             PREHLAD ULOH
        =================================== -->

        <section class="dashboard-section">

            <h2>Prehľad úloh</h2>

            <div class="table-wrapper">

                <table class="dashboard-table">

                    <thead>
                        <tr>
                            <th>Úloha</th>
                            <th>Tím</th>
                            <th>Pridelený študent</th>
                            <th>Priorita</th>
                            <th>Stav</th>
                            <th>Termín</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (empty($tasks)): ?>

                            <tr>
                                <td colspan="6" class="empty-message">
                                    Zatiaľ neexistujú žiadne úlohy.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($tasks as $task): ?>

                                <?php
                                $statusClass = match ($task['status']) {
                                    'To Do' => 'status-todo',
                                    'In Progress' => 'status-progress',
                                    'Done' => 'status-done',
                                    default => 'status-other'
                                };

                                $priorityClass = match ($task['priority']) {
                                    'High' => 'priority-high',
                                    'Medium' => 'priority-medium',
                                    'Low' => 'priority-low',
                                    default => ''
                                };
                                ?>

                                <tr>

                                    <td>
                                        <strong>
                                            <?php echo htmlspecialchars($task['title'], ENT_QUOTES, 'UTF-8'); ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($task['team_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($task['student_name'] ?? 'Nepridelená', ENT_QUOTES, 'UTF-8'); ?>
                                    </td>

                                    <td>
                                        <span class="priority <?php echo $priorityClass; ?>">
                                            <?php echo htmlspecialchars($task['priority'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="status <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($task['status'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php
                                        echo $task['deadline']
                                            ? date('d.m.Y', strtotime($task['deadline']))
                                            : '-';
                                        ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</body>

</html>