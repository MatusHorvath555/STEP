
<?php

session_start();
require_once "config.php";

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
function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Preklad roli
function roleLabel($role) {
    $roles = [
        'student' => 'Študent',
        'teacher' => 'Učiteľ',
        'team_leader' => 'Vedúci tímu',
        'admin' => 'Administrátor'
    ];

    return $roles[$role] ?? 'Neznáma rola';
}

function roleClass($role) {
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

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f3f3;
            color: #164e57;
        }

        /* HORNA LISTA */

        header {
            height: 68px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 4%;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }

        .logo {
            font-size: 38px;
            font-weight: bold;
            letter-spacing: -2px;
            color: #008d91;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-right span {
            color: #555;
            font-size: 14px;
        }

        .header-right a {
            text-decoration: none;
            color: #444;
            border: 1px solid #555;
            border-radius: 8px;
            padding: 8px 15px;
            font-size: 13px;
            font-weight: bold;
        }

        /* HLAVNY OBSAH */

        .container {
            max-width: 1250px;
            margin: 35px auto;
            padding: 0 20px;
        }

        .page-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        h1 {
            margin: 0;
            font-size: 27px;
            color: #164e57;
        }

        .subtitle {
            margin-top: 5px;
            color: #245b64;
            font-size: 17px;
        }

        .admin-label {
            background: #008f9f;
            color: white;
            padding: 10px 22px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 14px;
        }

        /* NAHLAD */

        .preview-warning {
            background: #fff4d9;
            color: #765400;
            border: 1px solid #efd28b;
            border-radius: 10px;
            padding: 13px 16px;
            margin-bottom: 22px;
            font-size: 14px;
        }

        /* STATISTIKY */

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 17px;
            padding: 15px 22px;
            min-height: 95px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.16);
        }

        .stat-number {
            font-size: 43px;
            font-weight: bold;
            line-height: 1.1;
        }

        .stat-label {
            color: #555;
            font-size: 14px;
            font-weight: bold;
            margin-top: 5px;
        }

        .teal { color: #0096a8; }
        .green { color: #65c52d; }
        .orange { color: #f0442e; }
        .blue { color: #3984c6; }

        /* FILTRE */

        .filter-card {
            background: white;
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.14);
            margin-bottom: 25px;
        }

        .filter-title {
            color: #888;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .filter-form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        input, select {
            padding: 11px 13px;
            border: 1px solid #ccc;
            border-radius: 7px;
            font-size: 14px;
            color: #164e57;
            background: white;
        }

        input {
            flex: 1;
            min-width: 220px;
        }

        select {
            min-width: 180px;
        }

        button, .reset-button {
            border: none;
            border-radius: 7px;
            padding: 11px 23px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
        }

        button {
            background: #0c5059;
            color: white;
        }

        .reset-button {
            background: #dedede;
            color: #164e57;
        }

        button:hover {
            background: #008f9f;
        }

        /* TABULKA */

        .table-card {
            background: white;
            border-radius: 17px;
            padding: 22px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.15);
            overflow: hidden;
        }

        .table-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .table-heading h2 {
            margin: 0;
            font-size: 19px;
        }

        .result-count {
            color: #777;
            font-size: 13px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th {
            text-align: left;
            color: #888;
            font-size: 13px;
            padding: 13px 12px;
            border-bottom: 2px solid #e6e6e6;
        }

        td {
            padding: 15px 12px;
            border-bottom: 1px solid #e9e9e9;
            font-size: 14px;
        }

        tr:hover td {
            background: #f8fbfb;
        }

        .user-name {
            font-weight: bold;
            color: #164e57;
        }

        .user-email {
            color: #777;
            font-size: 13px;
            margin-top: 4px;
        }

        .role {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .role.student {
            color: #398a32;
            background: #e6f4df;
        }

        .role.teacher {
            color: #087e9a;
            background: #dff2f7;
        }

        .role.leader {
            color: #7656a6;
            background: #eee7f8;
        }

        .role.admin {
            color: #bd493d;
            background: #fbe7e3;
        }

        .role.unknown {
            color: #555;
            background: #eee;
        }

        .empty {
            text-align: center;
            padding: 35px;
            color: #888;
        }

        footer {
            text-align: center;
            padding: 25px;
            color: #888;
            font-size: 13px;
        }

        @media (max-width: 850px) {
            .stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
        }

        @media (max-width: 550px) {
            header {
                padding: 0 15px;
            }

            .logo {
                font-size: 30px;
            }

            .header-right span {
                display: none;
            }

            .container {
                margin-top: 22px;
                padding: 0 12px;
            }

            .stats {
                gap: 10px;
            }

            .stat-card {
                padding: 13px;
            }

            .stat-number {
                font-size: 34px;
            }

            .filter-form {
                flex-direction: column;
            }

            input, select {
                width: 100%;
            }

            .filter-form button,
            .reset-button {
                text-align: center;
            }
        }
    </style>
</head>

<body>

<header>
    <div class="logo">STEP</div>

    <div class="header-right">
        <span>Administrácia systému</span>
        <a href="logout.php">ODHLÁSIŤ SA</a>
    </div>
</header>

<main class="container">

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
                value="<?= e($search) ?>"
            >

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
