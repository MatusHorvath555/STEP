
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

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            background-color: #f3f3f3;
            color: #164e57;
        }

        .container {
            max-width: 1200px;
            margin: 35px auto;
            padding: 0 20px;
        }

        h1 {
            color: #164e57;
            margin-bottom: 25px;
        }

        .user-info {
            background-color: white;
            padding: 25px;
            border-radius: 16px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, .10);
        }

        .avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background-color: #008f9f;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
            flex-shrink: 0;
        }

        .user-details h2 {
            margin: 0 0 12px;
        }

        .user-details p {
            margin: 7px 0;
            color: #555;
        }

        .profile-link {
            display: inline-block;
            margin-top: 8px;
            color: #008f9f;
            text-decoration: none;
            font-weight: bold;
        }

        .profile-link:hover {
            text-decoration: underline;
        }

        @media(max-width: 550px) {
            .container {
                padding: 0 12px;
                margin-top: 22px;
            }

            .user-info {
                align-items: flex-start;
                flex-direction: column;
            }
        }

    </style>

</head>

<body>

    <?php require_once "navigation.php"; ?>

    <main class="container">

        <h1>Učiteľský dashboard</h1>

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

    </main>

</body>
</html>
