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

    <link rel="stylesheet" href="css/premenne.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <?php require_once "navigation.php"; ?>

    <main class="container">

        <div class="heading">
            <div>
                <h1>Môj profil</h1>
                <p class="subtitle">Podrobné informácie o vašom používateľskom konte</p>
            </div>
            <div>
                <a href="teacher_dashboard.php" class="btn btn-secondary">
                    ← Späť na dashboard
                </a>
            </div>
        </div>

        <div class="card profile-card-wrapper">

            <div class="profile-header-section">
                <div class="avatar">
                    <?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?>
                </div>

                <h2>
                    <?php echo htmlspecialchars($teacher['name'], ENT_QUOTES, 'UTF-8'); ?>
                </h2>

                <p class="profile-role-badge">
                    Učiteľ
                </p>

                <p class="photo-note">
                    Profilová fotografia bude dostupná v budúcej verzii.
                </p>
            </div>

            <div class="profile-details-list">

                <div class="profile-row">
                    <span class="profile-label">ID používateľa:</span>
                    <span class="profile-value"><?php echo (int) $teacher['id']; ?></span>
                </div>

                <div class="profile-row">
                    <span class="profile-label">Meno a priezvisko:</span>
                    <span class="profile-value"><?php echo htmlspecialchars($teacher['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>

                <div class="profile-row">
                    <span class="profile-label">Email:</span>
                    <span class="profile-value"><?php echo htmlspecialchars($teacher['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>

                <div class="profile-row">
                    <span class="profile-label">Rola:</span>
                    <span class="profile-value">Učiteľ</span>
                </div>

                <div class="profile-row">
                    <span class="profile-label">Dátum registrácie:</span>
                    <span class="profile-value"><?php echo htmlspecialchars($teacher['created_at'] ?? 'Neznámy', ENT_QUOTES, 'UTF-8'); ?></span>
                </div>

            </div>

        </div>

    </main>

</body>

</html>