<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require "database.php";

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        email,
        role,
        created_at
    FROM users
    WHERE id = :id
");

$stmt->execute([
    'id' => $_SESSION['user_id']
]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="sk">

<head>

    <meta charset="UTF-8">

    <title>Môj profil</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

    <div class="container mt-4">

        <h1>Môj profil</h1>

        <a href="dashboard.php" class="btn btn-secondary mb-3">
            Dashboard
        </a>

        <div class="card">

            <div class="card-header">
                Informácie o používateľovi
            </div>

            <div class="card-body">

                <p>
                    <strong>ID:</strong>
                    <?php echo $user['id']; ?>
                </p>

                <p>
                    <strong>Meno:</strong>
                    <?php echo htmlspecialchars($user['name']); ?>
                </p>

                <p>
                    <strong>Email:</strong>
                    <?php echo htmlspecialchars($user['email']); ?>
                </p>

                <p>
                    <strong>Rola:</strong>
                    <?php echo htmlspecialchars($user['role']); ?>
                </p>

                <p>
                    <strong>Registrovaný od:</strong>
                    <?php echo $user['created_at']; ?>
                </p>

            </div>

        </div>

        <br>

        <a href="change_password.php" class="btn btn-warning">
            Zmeniť heslo
        </a>
        <a href="dashboard.php" class="btn btn-warning">
            Dashboard
        </a>

        <a href="index.php" class="btn btn-warning">
            Domovská stránka
        </a>

    </div>

</body>

</html>