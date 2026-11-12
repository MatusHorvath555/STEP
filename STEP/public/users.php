<?php

session_start();
require_once __DIR__ . "/../config/config.php";

// Dočasný náhľad povolený iba na localhoste.
$allowedHosts = ['localhost', '127.0.0.1', '::1'];
$currentHost = $_SERVER['HTTP_HOST'] ?? '';
$currentHost = explode(':', $currentHost)[0];

$isLocalhost = in_array($currentHost, $allowedHosts, true);

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Mimo localhostu je prístup povolený iba administrátorovi.
if (!$isAdmin && !$isLocalhost) {
    http_response_code(403);
    exit('Prístup zamietnutý. Táto stránka je určená iba pre administrátora.');
}

$previewMode = !$isAdmin;

// Ochrana formulárov proti CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Spracovanie administračných zmien
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // V režime náhľadu nie sú zmeny povolené
    if (!$isAdmin) {
        http_response_code(403);
        exit('Na túto akciu nemáš oprávnenie.');
    }

    // Kontrola CSRF tokenu
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(403);
        exit('Neplatná požiadavka.');
    }

    $action = $_POST['action'] ?? '';
    $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);

    // Predpokladáme, že ID prihláseného používateľa je uložené v session
    $currentUserId = $_SESSION['user_id'] ?? null;

    if (!$userId || !$currentUserId) {
        $_SESSION['admin_message'] = 'Nepodarilo sa overiť používateľa.';
    } elseif ($userId === (int)$currentUserId) {
        $_SESSION['admin_message'] = 'Vlastný účet nemôžeš upravovať ani deaktivovať.';
    } else {
        try {

            if ($action === 'change_role') {

                $newRole = $_POST['new_role'] ?? '';

                // Cez túto stránku meníme iba roly student a teacher
                if (!in_array($newRole, ['student', 'teacher'], true)) {
                    throw new Exception('Neplatná rola.');
                }

                $updateStmt = $pdo->prepare("
                    UPDATE users
                    SET role = :role
                    WHERE id = :id
                    AND role IN ('student', 'teacher')
                ");

                $updateStmt->execute([
                    'role' => $newRole,
                    'id' => $userId
                ]);

                $_SESSION['admin_message'] = $updateStmt->rowCount() > 0
                    ? 'Rola používateľa bola úspešne zmenená.'
                    : 'Rola sa nezmenila. Používateľ možno nemá upraviteľnú rolu.';

            } elseif ($action === 'deactivate' || $action === 'activate') {

                if ($action === 'deactivate') {
                    $updateStmt = $pdo->prepare("
                        UPDATE users
                        SET is_active = FALSE
                        WHERE id = :id
                        AND role <> 'admin'
                        RETURNING id
                    ");
                    $successMessage = 'Účet bol deaktivovaný.';
                } else {
                    $updateStmt = $pdo->prepare("
                        UPDATE users
                        SET is_active = TRUE
                        WHERE id = :id
                        AND role <> 'admin'
                        RETURNING id
                    ");
                    $successMessage = 'Účet bol aktivovaný.';
                }

                $updateStmt->execute(['id' => $userId]);

                if ($updateStmt->fetchColumn()) {
                    $_SESSION['admin_message'] = $successMessage;
                } else {
                    $_SESSION['admin_message'] = 'Účet sa nepodarilo zmeniť. Skontroluj, či existuje a nie je administrátorský.';
                }

            } else {
                $_SESSION['admin_message'] = 'Neznáma akcia.';
            }

        } catch (Exception $e) {
            $_SESSION['admin_message'] = 'Zmenu sa nepodarilo vykonať: ' . $e->getMessage();
        }
    }

    header('Location: users.php');
    exit;
}

// Správa po vykonaní akcie
$adminMessage = $_SESSION['admin_message'] ?? '';
unset($_SESSION['admin_message']);

// Vyhľadávanie a filtrovanie
$search = trim($_GET['search'] ?? '');
$selectedRole = $_GET['role'] ?? '';

$allowedRoles = ['student', 'teacher', 'team_leader', 'admin'];

if (!in_array($selectedRole, $allowedRoles, true)) {
    $selectedRole = '';
}

// Štatistiky
$statsStmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        COUNT(*) FILTER (WHERE role = 'student') AS students,
        COUNT(*) FILTER (WHERE role = 'teacher') AS teachers,
        COUNT(*) FILTER (WHERE role = 'admin') AS admins
    FROM users
");

$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Počet všetkých registrovaných používateľov
$totalUsers = (int) $stats['total'];

// Zostavenie SQL podmienok
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(name ILIKE :search OR email ILIKE :search)";
    $params['search'] = '%' . $search . '%';
}

if ($selectedRole !== '') {
    $where[] = "role = :role";
    $params['role'] = $selectedRole;
}

$sql = "
    SELECT id, name, email, role, created_at, is_active
    FROM users
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY created_at DESC, id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pomocná funkcia na bezpečný výpis
function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Preklad rolí
function roleLabel($role)
{
    $roles = [
        'student' => 'Študent',
        'teacher' => 'Učiteľ',
        'team_leader' => 'Vedúci tímu',
        'admin' => 'Administrátor'
    ];

    return $roles[$role] ?? 'Neznáma rola';
}

function roleClass($role)
{
    $classes = [
        'student' => 'student',
        'teacher' => 'teacher',
        'team_leader' => 'leader',
        'admin' => 'admin'
    ];

    return $classes[$role] ?? 'unknown';
}

?>

<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>STEP | Správa používateľov</title>

    <link rel="stylesheet" href="css/premenne.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin.css">
</head>

<body class="admin-users-page">

    <header class="step-navigation">
        <a href="dashboard.php" class="step-logo" aria-label="STEP – domov">
            <img src="logo.png" alt="STEP" width="50" height="40">
        </a>

        <div class="header-right">
            <span>Administrácia systému</span>
            <a href="logout.php">ODHLÁSIŤ SA</a>
        </div>
    </header>

    <main class="container admin-users-container">

        <div class="page-heading">
            <div>
                <h1>Správa používateľov</h1>
                <div class="subtitle">
                    Administrácia používateľských účtov v systéme STEP
                </div>
            </div>

            <div class="admin-label">
                ADMINISTRÁCIA
            </div>
        </div>

        <?php if ($previewMode): ?>
            <?php if ($adminMessage !== ''): ?>
                <div class="admin-message">
                    <?= e($adminMessage) ?>
                </div>
            <?php endif; ?>
            <div class="preview-warning">
                <strong>Režim náhľadu:</strong>
                Stránka je otvorená bez administrátorského účtu.
                Údaje môžeš prezerať, ale administrátorské úpravy nie sú dostupné.
                Tento režim funguje iba na localhoste.
            </div>
        <?php endif; ?>

        <!-- ŠTATISTIKY -->

        <div class="stats">

            <div class="stat-card">
                <div class="stat-number teal">
                    <?= (int)$stats['total'] ?>
                </div>
                <div class="stat-label">Všetci používatelia</div>
            </div>

            <div class="stat-card">
                <div class="stat-number green">
                    <?= (int)$stats['students'] ?>
                </div>
                <div class="stat-label">Študenti</div>
            </div>

            <div class="stat-card">
                <div class="stat-number blue">
                    <?= (int)$stats['teachers'] ?>
                </div>
                <div class="stat-label">Učitelia</div>
            </div>

            <div class="stat-card">
                <div class="stat-number orange">
                    <?= (int)$stats['admins'] ?>
                </div>
                <div class="stat-label">Administrátori</div>
            </div>

        </div>

        <!-- VYHĽADÁVANIE A FILTROVANIE -->

        <div class="filter-card">

            <div class="filter-title">
                Vyhľadávanie a filtrovanie používateľov
            </div>

            <form method="GET" class="filter-form">

                <input
                    type="text"
                    name="search"
                    placeholder="Vyhľadať podľa mena alebo e-mailu..."
                    value="<?= e($search) ?>">

                <select name="role">
                    <option value="">Všetky roly</option>

                    <option value="student"
                        <?= $selectedRole === 'student' ? 'selected' : '' ?>>
                        Študent
                    </option>

                    <option value="teacher"
                        <?= $selectedRole === 'teacher' ? 'selected' : '' ?>>
                        Učiteľ
                    </option>

                    <option value="team_leader"
                        <?= $selectedRole === 'team_leader' ? 'selected' : '' ?>>
                        Vedúci tímu
                    </option>

                    <option value="admin"
                        <?= $selectedRole === 'admin' ? 'selected' : '' ?>>
                        Administrátor
                    </option>
                </select>

                <button type="submit" class="btn">Filtrovať</button>

                <a href="users.php" class="reset-button">Resetovať</a>

            </form>

        </div>

        <!-- TABUĽKA POUŽÍVATEĽOV -->

        <div class="table-card">

            <div class="table-heading">
                <h2>Zoznam používateľov</h2>

                <div class="result-count">
                    Zobrazených používateľov: <?= count($users) ?>
                    / <?= $totalUsers ?>
                </div>
            </div>

            <div class="table-wrapper">

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Používateľ</th>
                            <th>Rola a stav</th>
                            <th>Dátum registrácie</th>
                            <th>Akcie</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (empty($users)): ?>

                            <tr>
                                <td colspan="5" class="empty">
                                    Nenašli sa žiadni používatelia.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($users as $user): ?>

                                <tr class="<?= !$user['is_active'] ? 'inactive-user' : '' ?>">

                                    <td><?= (int)$user['id'] ?></td>

                                    <td>
                                        <div class="user-name">
                                            <?= e($user['name']) ?>
                                        </div>

                                        <div class="user-email">
                                            <?= e($user['email']) ?>
                                        </div>
                                    </td>

                                    <td>
                                        <span class="role <?= e(roleClass($user['role'])) ?>">
                                            <?= e(roleLabel($user['role'])) ?>
                                        </span>

                                        <div>
                                            <span class="account-status <?= $user['is_active'] ? 'active' : 'inactive' ?>">
                                                <?= $user['is_active'] ? 'Aktívny' : 'Neaktívny' ?>
                                            </span>
                                        </div>
                                    </td>

                                    <td>
                                        <?= $user['created_at']
                                            ? e(date('d.m.Y', strtotime($user['created_at'])))
                                            : '—' ?>
                                    </td>

                                    <td class="user-actions">

                                        <?php if ($isAdmin && (int)$user['id'] !== (int)($_SESSION['user_id'] ?? 0)): ?>

                                            <?php if (in_array($user['role'], ['student', 'teacher'], true)): ?>

                                                <form method="POST" class="role-edit-form">
                                                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                                    <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                                    <input type="hidden" name="action" value="change_role">

                                                    <select name="new_role">
                                                        <option value="student" <?= $user['role'] === 'student' ? 'selected' : '' ?>>
                                                            Študent
                                                        </option>

                                                        <option value="teacher" <?= $user['role'] === 'teacher' ? 'selected' : '' ?>>
                                                            Učiteľ
                                                        </option>
                                                    </select>

                                                    <button type="submit" class="admin-action-btn">
                                                        Zmeniť rolu
                                                    </button>
                                                </form>

                                            <?php endif; ?>

                                            <?php if ($user['role'] !== 'admin'): ?>

                                                <form method="POST"
                                                    onsubmit="return confirm('Naozaj chceš <?= $user['is_active'] ? 'deaktivovať' : 'aktivovať' ?> tento účet?');">

                                                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                                    <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="<?= $user['is_active'] ? 'deactivate' : 'activate' ?>">

                                                    <button
                                                        type="submit"
                                                        class="admin-action-btn <?= $user['is_active'] ? 'deactivate-btn' : 'activate-btn' ?>">

                                                        <?= $user['is_active'] ? 'Deaktivovať' : 'Aktivovať' ?>

                                                    </button>
                                                </form>

                                            <?php endif; ?>

                                        <?php elseif (!$isAdmin): ?>

                                            <span>Len na prezeranie</span>

                                        <?php else: ?>

                                            <span>Vlastný účet</span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>
                </table>

            </div>

        </div>

    </main>

    <footer>
        STEP – Tímový projekt | Administrácia
    </footer>

</body>

</html>