<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] != 'teacher') {
    die("Prístup zamietnutý.");
}

require "database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST['team_name']);

    if (!empty($name)) {

        $sql = "
        INSERT INTO teams (name)
        VALUES (:name)
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            'name' => $name
        ]);

        $message = "Tím bol úspešne vytvorený.";
    }
}

$stmt = $pdo->query("
    SELECT *
    FROM teams
    ORDER BY id DESC
");

$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="UTF-8">
    <title>Správa tímov</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">
</head>

<body>

    <div class="container mt-4">

        <h1>Správa tímov</h1>

        <a href="dashboard.php" class="btn btn-secondary mb-3">
            Dashboard
        </a>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="card mb-4">

            <div class="card-header">
                Vytvorenie tímu
            </div>

            <div class="card-body">

                <form method="POST">

                    <label class="form-label">
                        Názov tímu
                    </label>

                    <input
                        type="text"
                        name="team_name"
                        class="form-control"
                        required>

                    <br>

                    <button
                        type="submit"
                        class="btn btn-primary">

                        Vytvoriť tím

                    </button>

                </form>

            </div>

        </div>

        <h3>Existujúce tímy</h3>

        <table class="table table-striped">

            <thead>

                <tr>
                    <th>ID</th>
                    <th>Názov</th>
                    <th>Dátum vytvorenia</th>
                </tr>

            </thead>

            <tbody>

                <?php foreach ($teams as $team): ?>

                    <tr>

                        <td>
                            <?php echo $team['id']; ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($team['name']); ?>
                        </td>

                        <td>
                            <?php echo $team['created_at']; ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</body>

</html>