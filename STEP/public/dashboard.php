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

    <?php
    if ($_SESSION['role'] == 'teacher') {
        header("Location: teacher_dashboard.php");
        exit; // Ukončí skript po presmerovaní
    }
    ?>

    <?php if ($_SESSION['role'] == 'student'): ?>

        <ul>
            <li><a href="tasks.php">Moje úlohy</a></li>
            <li><a href="profile.php">Môj profil</a></li>
        </ul>

    <?php endif; ?>

    <hr>

    <a href="logout.php">Odhlásiť sa</a>
    <a href="change_password.php">Zmeniť heslo </a>
    <a href="index.php">Späť na úvodnú stránku</a>

</body>

</html>