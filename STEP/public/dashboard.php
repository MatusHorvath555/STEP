<?php

session_start();

require_once __DIR__ . "/../config/config.php";

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

    <link rel="stylesheet" href="css/premenne.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <?php require_once "navigation.php"; ?>

    <main class="dashboard-container">

        <section class="dashboard-welcome">
            <h1>
                Vitaj,
                <?php echo htmlspecialchars($_SESSION['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>!
            </h1>

            <p class="dashboard-role">
                Rola:
                <?php echo htmlspecialchars($_SESSION['role'], ENT_QUOTES, 'UTF-8'); ?>
            </p>
        </section>

        <section class="dashboard-content">
            <h2>Dostupné funkcie</h2>

            <nav class="dashboard-links">
                <a href="my_tasks.php" class="dashboard-link">
                    Moje úlohy
                </a>

                <a href="profile.php" class="dashboard-link">
                    Môj profil
                </a>
            </nav>
        </section>

    </main>

</body>

</html>