<?php

session_start();

// Kontrola prihlásenia
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Kontrola oprávnenia učiteľa
if ($_SESSION['role'] !== 'teacher') {
    die("Prístup zamietnutý.");
}

// Pripojenie k databáze
require_once __DIR__ . "/../config/config.php";

// Načítanie študentov
$stmt = $pdo->query("
    SELECT
        id,
        name,
        email,
        created_at
    FROM users
    WHERE role = 'student'
    ORDER BY name
");

$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Študenti | STEP</title>

    <link rel="stylesheet" href="css/premenne.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <?php require_once "navigation.php"; ?>

    <main class="container">

        <div class="heading">
            <div>
                <h1>Zoznam študentov</h1>
                <p class="subtitle">Prehľad všetkých registrovaných študentov v systéme</p>
            </div>
            <div>
                <a href="teacher_dashboard.php" class="btn">
                    ← Dashboard
                </a>
            </div>
        </div>

        <div class="card">
            <h2>Registrovaní študenti</h2>

            <?php if (empty($students)): ?>

                <div class="empty">
                    Zatiaľ nie sú registrovaní žiadni študenti.
                </div>

            <?php else: ?>

                <div class="table-responsive">
                    <table class="step-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Meno</th>
                                <th>Email</th>
                                <th>Dátum registrácie</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <?php echo (int) $student['id']; ?>
                                    </td>
                                    <td class="table-name">
                                        <?php echo htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td class="table-date">
                                        <?php echo htmlspecialchars($student['created_at'] ?? 'Neznáme', ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php endif; ?>

        </div>

    </main>

</body>

</html>