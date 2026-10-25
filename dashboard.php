<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="UTF-8">
    <title>STEP Dashboard</title>
</head>

<body>

    <h1>STEP</h1>

    <p>Vitaj, <?php echo $_SESSION['name']; ?></p>

    <p>Rola: <?php echo $_SESSION['role']; ?></p>

    <hr>

    <h2>Dostupné funkcie</h2>

    <?php if ($_SESSION['role'] == 'teacher'): ?>

        <ul>
            <li>students.phpŠtudenti</a></li>
            <li>teams.phpTímy</a></li>
            <li>tasks.phpVšetky úlohy</a></li>
            <li>create_task.phpVytvorenie úlohy</a></li>
        </ul>

    <?php endif; ?>

    <?php if ($_SESSION['role'] == 'student'): ?>

        <ul>
            <li>my_tasks.phpMoje úlohy</a></li>
            <li>profile.phpMôj profil</a></li>
        </ul>

    <?php endif; ?>

    <hr>

    logout.phpOdhlásiť sa</a>

</body>

</html>