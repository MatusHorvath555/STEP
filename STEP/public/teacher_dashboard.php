
<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (($_SESSION['role'] ?? '') !== 'teacher') {
    die("Prístup zamietnutý.");
}

require_once "config.php";

// Načítanie údajov učiteľa
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

// Vytvorenie iniciálok
$nameParts = preg_split('/\s+/', trim($name));
$initials = '';

foreach ($nameParts as $part) {
    if ($part !== '') {
        $initials .= mb_substr($part, 0, 1, 'UTF-8');
    }
}

$initials = mb_strtoupper($initials, 'UTF-8');

?>

<!DOCTYPE html>
<html lang="sk">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Teacher Dashboard | STEP</title>

    <style>

        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            background-color: #f4f4f4;
        }

        h1 {
            color: #333;
        }

        .user-info {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background-color: #4a90e2;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
            flex-shrink: 0;
        }

        .user-details p {
            margin: 7px 0;
        }

        .profile-link {
            display: inline-block;
            margin-top: 8px;
            color: #357abd;
            text-decoration: none;
            font-weight: bold;
        }

        .profile-link:hover {
            text-decoration: underline;
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

    </style>

</head>

<body>

    <h1>STEP – Teacher Dashboard</h1>

    <div class="user-info">

        <div class="avatar">
            <?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?>
        </div>

        <div class="user-details">

            <h2>
                <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
            </h2>

            <p>
                Email:
                <?php echo htmlspecialchars($teacher['email'], ENT_QUOTES, 'UTF-8'); ?>
            </p>

            <p>
                Rola: Učiteľ
            </p>

            <a href="teacher_profile.php" class="profile-link">
                Zobraziť môj profil →
            </a>

        </div>

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
