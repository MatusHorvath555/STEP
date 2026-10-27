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
            <li><a href="sql.php">Študenti</a></li>
            <li><a href="teams.php">Tímy</a></li>
            <li><a href="team_members.php">Členovia tímov</a></li>
            <li><a href="tasks.php">Všetky úlohy</a></li>
            <li><a href="create_task.php">Vytvorenie úlohy</a></li>
        </ul>

    <?php endif; ?>

    <?php if ($_SESSION['role'] == 'student'): ?>

        <ul>
            <li><a href="my_tasks.php">Moje úlohy</a></li>
            <li><a href="profile.php">Môj profil</a></li>
        </ul>

    <?php endif; ?>

    <hr>

    <a href="logout.php">Odhlásiť sa</a>
    <a href="change_password.php">Zmeniť heslo </a>

</body>

</html>