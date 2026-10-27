<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] != 'teacher') {
    die("Prístup zamietnutý.");
}

?>

<!DOCTYPE html>
<html lang="sk">

<head>

    <meta charset="UTF-8">
    <title>Teacher Dashboard</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            background-color: #f4f4f4;
        }

        h1 {
            color: #333;
        }

        .menu {
            margin-top: 20px;
        }

        .menu a {

            display: block;

            width: 250px;

            padding: 12px;

            margin-bottom: 10px;

            background-color: #4a90e2;

            color: white;

            text-decoration: none;

            border-radius: 5px;
        }

        .menu a:hover {
            background-color: #357abd;
        }

        .user-info {

            background-color: white;

            padding: 15px;

            border-radius: 5px;

            margin-bottom: 20px;
        }
    </style>

</head>

<body>

    <h1>Teacher Dashboard</h1>

    <div class="user-info">

        <p>
            Prihlásený používateľ:
            <strong><?php echo $_SESSION['name']; ?></strong>
        </p>

        <p>
            Rola:
            <strong><?php echo $_SESSION['role']; ?></strong>
        </p>

    </div>

    <div class="menu">

        <a href="students.php">
            Správa študentov
        </a>

        <a href="teams.php">
            Správa tímov
        </a>

        <a href="team_members.php">
            Členovia tímov
        </a>

        <a href="tasks.php">
            Všetky úlohy
        </a>

        <a href="create_task.php">
            Vytvoriť úlohu
        </a>

        <a href="change_password.php">
            Zmeniť heslo
        </a>

        <a href="logout.php">
            Odhlásiť sa
        </a>

    </div>

</body>

</html>