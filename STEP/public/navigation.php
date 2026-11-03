<?php

// Spoločná navigácia STEP.
// Predpokladá, že session už bola spustená.

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

$role = $_SESSION['role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);

$links = [];

// Navigácia pre učiteľa
if ($role === 'teacher') {

    $homePage = 'teacher_dashboard.php';

    $links = [
        'teacher_dashboard.php' => 'DASHBOARD',
        'students.php' => 'ŠTUDENTI',
        'teams.php' => 'TÍMY',
        'team_members.php' => 'ČLENOVIA TÍMOV',
        'tasks.php' => 'VŠETKY ÚLOHY',
        'create_task.php' => 'VYTVORIŤ ÚLOHU',
        'teacher_profile.php' => 'MÔJ PROFIL',
        'change_password.php' => 'ZMENIŤ HESLO'
    ];

// Navigácia pre študenta a vedúceho tímu
} elseif ($role === 'student') {

    $homePage = 'dashboard.php';

    $links = [
        'dashboard.php' => 'DASHBOARD',
        'my_tasks.php' => 'MOJE ÚLOHY',
        'profile.php' => 'MÔJ PROFIL',
        'change_password.php' => 'ZMENIŤ HESLO'
    ];

    // Overenie, či je študent vedúcim aspoň jedného tímu
    if (!isset($pdo)) {
        require_once __DIR__ . "/../config/config.php";

    }

    $leaderStmt = $pdo->prepare("
        SELECT EXISTS (
            SELECT 1
            FROM team_members
            WHERE user_id = :user_id
              AND is_team_leader = TRUE
        )
    ");

    $leaderStmt->execute([
        'user_id' => $userId
    ]);

    if ($leaderStmt->fetchColumn()) {
        $links = [
            'dashboard.php' => 'DASHBOARD',
            'team_leader_dashboard.php' => 'PANEL VEDÚCEHO',
            'my_tasks.php' => 'MOJE ÚLOHY',
            'profile.php' => 'MÔJ PROFIL',
            'change_password.php' => 'ZMENIŤ HESLO'
        ];
    }

// Navigácia pre administrátora
} elseif ($role === 'admin') {

    $homePage = 'users.php';

    $links = [
        'users.php' => 'POUŽÍVATELIA',
        'change_password.php' => 'ZMENIŤ HESLO'
    ];

} else {
    $homePage = 'login.php';
}

?>

<header class="step-navigation">

<a href="<?= htmlspecialchars($homePage, ENT_QUOTES, 'UTF-8') ?>"
   class="step-logo"
   aria-label="STEP – domov">
    <img src="logo.png" alt="STEP" width="50" height="40">
</a>

    <nav class="step-navigation-links" aria-label="Hlavná navigácia">

        <?php foreach ($links as $url => $label): ?>

            <a
                href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"
                class="<?= $currentPage === $url ? 'active' : '' ?>">
                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
            </a>

        <?php endforeach; ?>

        <a href="logout.php" class="logout-link">
            ODHLÁSIŤ SA
        </a>

    </nav>

</header>
