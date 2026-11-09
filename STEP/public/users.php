<?php

session_start();
require_once __DIR__ . "/../config/config.php";

// Docasny nahlad povoleny iba na localhoste.
$allowedHosts = ['localhost', '127.0.0.1', '::1'];
$currentHost = $_SERVER['HTTP_HOST'] ?? '';
$currentHost = explode(':', $currentHost)[0];

$isLocalhost = in_array($currentHost, $allowedHosts, true);

$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Mimo localhostu je pristup povoleny iba administratorovi.
if (!$isAdmin && !$isLocalhost) {
    http_response_code(403);
    exit('Pristup zamietnuty. Tato stranka je urcena iba pre administratora.');
}

$previewMode = !$isAdmin;

// Vyhladavanie a filtrovanie
$search = trim($_GET['search'] ?? '');
$selectedRole = $_GET['role'] ?? '';

$allowedRoles = ['student', 'teacher', 'team_leader', 'admin'];

if (!in_array($selectedRole, $allowedRoles, true)) {
    $selectedRole = '';
}

// Statistiky
$statsStmt = $pdo->query("
    SELECT
        COUNT(*) AS total,
        COUNT(*) FILTER (WHERE role = 'student') AS students,
        COUNT(*) FILTER (WHERE role = 'teacher') AS teachers,
        COUNT(*) FILTER (WHERE role = 'admin') AS admins
    FROM users
");

$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Pocet vsetkych registrovanych pouzivatelov
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
    SELECT id, name, email, role, created_at
    FROM users
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY created_at DESC, id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pomocna funkcia na bezpecny vypis
function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Preklad roli
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

    <title>| Správa používateľov</title>

    <link rel="stylesheet" href="css/premenne.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin.css">
</head>

<body class="admin-users-page">

    <header>
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
            <div class="preview-warning">
                <strong>Režim náhľadu:</strong>
                Stránka je otvorená bez administrátorského účtu.
                Údaje môžeš prezerať, ale administrátorské úpravy nie sú dostupné.
                Tento režim funguje iba na localhoste.
            </div>
        <?php endif; ?>

        <!-- STATISTIKY -->

        <section class="stats">

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

        </section>

        <!-- VYHLADAVANIE A FILTROVANIE -->

        <section class="filter-card">

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

                <button type="submit">Filtrovať</button>

                <a href="users.php" class="reset-button">Resetovať</a>

            </form>

        </section>

        <!-- TABULKA POUZIVATELOV -->

        <section class="table-card">

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
                            <th>Rola</th>
                            <th>Dátum registrácie</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (empty($users)): ?>

                            <tr>
                                <td colspan="4" class="empty">
                                    Nenašli sa žiadni používatelia.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($users as $user): ?>

                                <tr>
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
                                    </td>

                                    <td>
                                        <?= $user['created_at']
                                            ? e(date('d.m.Y', strtotime($user['created_at'])))
                                            : '—' ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

    <footer>
        STEP – Tímový projekt | Administrácia
    </footer>

</body>

</html>