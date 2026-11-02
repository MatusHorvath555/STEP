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

// Načítanie profilu učiteľa
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

// Iniciály učiteľa
$nameParts = preg_split('/\s+/', trim($teacher['name']));
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

    <title>Môj profil | STEP</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            background-color: #f4f4f4;
        }

        .profile-container {
            max-width: 650px;
            margin: 30px auto;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
        }

        h1 {
            text-align: center;
            color: #333;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background-color: #4a90e2;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            font-weight: bold;
            margin: 0 auto 15px;
        }

        .profile-header h2 {
            margin-bottom: 5px;
        }

        .role {
            color: #777;
        }

        .profile-details {
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }

        .detail {
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }

        .detail strong {
            display: inline-block;
            width: 170px;
        }

        .back-link {
            display: inline-block;
            margin-top: 25px;
            padding: 10px 18px;
            background-color: #4a90e2;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .back-link:hover {
            background-color: #357abd;
        }

        .photo-note {
            margin-top: 15px;
            color: #888;
            font-size: 13px;
        }
    </style>

</head>

<body>
    <?php require_once "navigation.php"; ?>

    <div class="profile-container">

        <h1>Môj profil</h1>

        <div class="profile-header">

            <div class="avatar">
                <?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?>
            </div>

            <h2>
                <?php echo htmlspecialchars($teacher['name'], ENT_QUOTES, 'UTF-8'); ?>
            </h2>

            <p class="role">
                Učiteľ
            </p>

            <p class="photo-note">
                Profilová fotografia bude dostupná v budúcej verzii.
            </p>

        </div>

        <div class="profile-details">

            <div class="detail">
                <strong>ID používateľa:</strong>
                <?php echo (int) $teacher['id']; ?>
            </div>

            <div class="detail">
                <strong>Meno a priezvisko:</strong>
                <?php echo htmlspecialchars($teacher['name'], ENT_QUOTES, 'UTF-8'); ?>
            </div>

            <div class="detail">
                <strong>Email:</strong>
                <?php echo htmlspecialchars($teacher['email'], ENT_QUOTES, 'UTF-8'); ?>
            </div>

            <div class="detail">
                <strong>Rola:</strong>
                Učiteľ
            </div>

            <div class="detail">
                <strong>Dátum registrácie:</strong>
                <?php echo htmlspecialchars($teacher['created_at'] ?? 'Neznámy', ENT_QUOTES, 'UTF-8'); ?>
            </div>

        </div>

        <a href="teacher_dashboard.php" class="back-link">
            Späť na dashboard
        </a>

    </div>

</body>

</html>