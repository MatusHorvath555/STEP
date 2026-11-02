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
require_once "config.php";

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

    <title>Študenti</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <?php require_once "navigation.php"; ?>

    <div class="container mt-4">

        <h1>Zoznam študentov</h1>

        <a href="teacher_dashboard.php" class="btn btn-secondary mb-3">
            Dashboard
        </a>

        <div class="card">

            <div class="card-header">
                Registrovaní študenti
            </div>

            <div class="card-body">

                <?php if (empty($students)): ?>

                    <p class="text-muted">
                        Zatiaľ nie sú registrovaní žiadni študenti.
                    </p>

                <?php else: ?>

                    <table class="table table-striped table-hover">

                        <thead class="table-dark">
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

                                    <td>
                                        <?php echo htmlspecialchars($student['name']); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($student['email']); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($student['created_at'] ?? 'Neznáme'); ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                <?php endif; ?>

            </div>

        </div>

    </div>

</body>

</html>