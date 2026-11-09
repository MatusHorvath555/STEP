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

    <link rel="stylesheet" href="css/premenne.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <?php require_once "navigation.php"; ?>


    <main class="container">

        <h1>Učiteľský dashboard</h1>

        <div class="card">

            <div class="user-info">

                <div class="avatar">
                    <?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?>
                </div>

                <div class="user-details">

                    <h2>
                        <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
                    </h2>

                    <a href="teacher_profile.php" class="profile-link">
                        Zobraziť môj profil →
                    </a>

                </div>

            </div>

            <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-top: 25px;">

                <div style="flex: 1; min-width: 220px; padding: 18px; border: 1px solid #e1ebeb; border-radius: 10px;">
                    <p class="subtitle" style="margin-bottom: 8px;">Email</p>
                    <strong>
                        <?php echo htmlspecialchars($teacher['email'], ENT_QUOTES, 'UTF-8'); ?>
                    </strong>
                </div>

                <div style="flex: 1; min-width: 150px; padding: 18px; border: 1px solid #e1ebeb; border-radius: 10px;">
                    <p class="subtitle" style="margin-bottom: 8px;">Rola</p>
                    <strong>Učiteľ</strong>
                </div>

            </div>

        </div>

    </main>
    ```

</body>

</html>