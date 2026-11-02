<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] === 'teacher') {
    header("Location: teacher_dashboard.php");
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

<p>Vitaj, <?php echo htmlspecialchars($_SESSION['name']); ?></p>

<p>Rola: <?php echo htmlspecialchars($_SESSION['role']); ?></p>

    <hr>

    <h2>Dostupné funkcie</h2>



    <?php if ($_SESSION['role'] === 'student'): ?>

        <ul>
            <li><a href="my_tasks.php">Moje úlohy</a></li>
            <li><a href="profile.php">Môj profil</a></li>
        </ul>

    <?php endif; ?>

    <hr>

<p><a href="change_password.php">Zmeniť heslo</a></p>
<p><a href="index.php">Späť na úvodnú stránku</a></p>
<p><a href="logout.php">Odhlásiť sa</a></p>

</body>

</html>
