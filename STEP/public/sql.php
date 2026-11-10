

<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    exit("Prístup zamietnutý.");
}

require_once __DIR__ . "/../config/config.php";

$stmt = $pdo->query("
    SELECT
        id,
        name,
        email,
        role,
        created_at
    FROM users
    WHERE role = 'student'
    ORDER BY name
");

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zoznam používateľov | STEP</title>

    <link rel="stylesheet" href="css/premenne.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <?php require_once "navigation.php"; ?>

    <main class="container">

        <div class="heading">
            <div>
                <h1>Zoznam používateľov (Študenti)</h1>
                <p class="subtitle">Prehľad všetkých registrovaných študentov v systéme</p>
            </div>
            <div>
                <a href="teacher_dashboard.php" class="btn btn-secondary">
                    ← Späť na Dashboard
                </a>
            </div>
        </div>

        <div class="card">
            <?php if (empty($users)): ?>
                <div class="empty">
                    V systéme zatiaľ nie sú žiadni zaregistrovaní študenti.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Meno</th>
                                <th>Email</th>
                                <th>Rola</th>
                                <th>Vytvorený</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo (int) $user['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <span class="role-badge"><?php echo htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['created_at'] ?? 'Neznáme', ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <footer class="site-footer">
        STEP – Tímový projekt
    </footer>

</body>

</html>