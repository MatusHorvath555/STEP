<?php

// Spoločná navigácia STEP.
// Predpokladá, že session už bola spustená.

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

$role = $_SESSION['role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);

$links = [];

// Navigácia pre učiteľa
if ($role === 'teacher') {

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

    $links = [
        'dashboard.php' => 'ŠTUDENTSKÝ DASHBOARD',
        'my_tasks.php' => 'MOJE ÚLOHY',
        'profile.php' => 'MÔJ PROFIL',
        'change_password.php' => 'ZMENIŤ HESLO'
    ];

    // Overenie, či je študent vedúcim aspoň jedného tímu
    if (!isset($pdo)) {
        require_once "config.php";
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
            'dashboard.php' => 'ŠTUDENTSKÝ DASHBOARD',
            'team_leader_dashboard.php' => 'PANEL VEDÚCEHO',
            'my_tasks.php' => 'MOJE ÚLOHY',
            'profile.php' => 'MÔJ PROFIL',
            'change_password.php' => 'ZMENIŤ HESLO'
        ];
    }

    // Navigácia pre administrátora
} elseif ($role === 'admin') {

    $links = [
        'users.php' => 'SPRÁVA POUŽÍVATEĽOV',
        'change_password.php' => 'ZMENIŤ HESLO'
    ];
}

?>

<style>
    .step-navigation {
        width: 100%;
        min-height: 68px;
        padding: 12px 4%;
        background: white;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        flex-wrap: wrap;
        box-shadow: 0 1px 4px rgba(0, 0, 0, .08);
        box-sizing: border-box;
    }

    .step-navigation * {
        box-sizing: border-box;
    }

    .step-logo {
        color: #008d91;
        font-size: 36px;
        font-weight: bold;
        letter-spacing: -2px;
        text-decoration: none;
        flex-shrink: 0;
    }

    .step-navigation-links {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 9px;
        flex-wrap: wrap;
    }

    .step-navigation-links a {
        color: #164e57;
        border: 1px solid #d5e0e1;
        border-radius: 8px;
        padding: 9px 13px;
        font-size: 12px;
        font-weight: bold;
        text-decoration: none;
        transition: .2s;
    }

    .step-navigation-links a:hover {
        background: #008f9f;
        border-color: #008f9f;
        color: white;
    }

    .step-navigation-links a.active {
        background: #e3f4f4;
        border-color: #008f9f;
        color: #008f9f;
    }

    .step-navigation-links a.logout-link {
        border-color: #e3caca;
        color: #a33c3c;
    }

    .step-navigation-links a.logout-link:hover {
        background: #a33c3c;
        border-color: #a33c3c;
        color: white;
    }

    @media (max-width: 750px) {
        .step-navigation {
            align-items: flex-start;
            flex-direction: column;
            gap: 12px;
            padding: 12px 15px;
        }

        .step-navigation-links {
            justify-content: flex-start;
            width: 100%;
        }
    }
</style>

<header class="step-navigation">

    <a href="<?= $role === 'teacher' ? 'teacher_dashboard.php' : ($role === 'admin' ? 'users.php' : 'dashboard.php') ?>"
        class="step-logo">
        STEP
    </a>

    <nav class="step-navigation-links">

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