<?php

session_start();

require_once "config.php";

// Kontrola prihlásenia
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Presmerovanie učiteľa a administrátora
if ($_SESSION['role'] === 'teacher') {
    header("Location: teacher_dashboard.php");
    exit();
}

if ($_SESSION['role'] === 'admin') {
    header("Location: users.php");
    exit();
}

// Povolený je iba študent
if ($_SESSION['role'] !== 'student') {
    session_unset();
    session_destroy();

    header("Location: login.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>STEP Dashboard</title>
</head>

<body>

    <?php require_once "navigation.php"; ?>

    <main>
        <p>
            Vitaj,
            <?php echo htmlspecialchars($_SESSION['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
        </p>

        <p>
            Rola:
            <?php echo htmlspecialchars($_SESSION['role'], ENT_QUOTES, 'UTF-8'); ?>
        </p>

        <h2>Dostupné funkcie</h2>

        <ul>
            <li><a href="my_tasks.php">Moje úlohy</a></li>
            <li><a href="profile.php">Môj profil</a></li>
        </ul>
    </main>

</body>

</html>