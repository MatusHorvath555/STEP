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

/*
 * VYTVORENIE NOVÉHO TÍMU
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST['team_name']);

    if (!empty($name)) {

        $stmt = $pdo->prepare("
            INSERT INTO teams (name)
            VALUES (:name)
        ");

        $stmt->execute([
            'name' => $name
        ]);

        $message = "Tím bol úspešne vytvorený.";
    }
}

/*
 * NAČÍTANIE TÍMOV
 */
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Správa tímov</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

    <div class="container mt-4">

        <h1>Správa tímov</h1>

        <a href="dashboard.php" class="btn btn-secondary mb-3">
            Dashboard
        </a>
        <a href="team_members.php" class="btn btn-secondary mb-3">
            Pridaj členov tímov
        </a>

        <?php if (!empty($message)): ?>

            <div class="alert alert-success">
                <?php echo $message; ?>
            </div>

        <?php endif; ?>

        <div class="card mb-4">

            <div class="card-header">
                Vytvorenie nového tímu
            </div>

            <div class="card-body">

                <form method="POST">

                    <div class="mb-3">

                        <label class="form-label">
                            Názov tímu
                        </label>

                        <input
                            type="text"
                            name="team_name"
                            class="form-control"
                            required>

                    </div>

                    <button
                        type="submit"
                        class="btn btn-primary">

                        Vytvoriť tím

                    </button>

                </form>

            </div>

        </div>

        <div class="card">

            <div class="card-header">
                Existujúce tímy
            </div>

            <div class="card-body">

                <table class="table table-striped table-hover">

                    <thead class="table-dark">

                        <tr>
                            <th>ID</th>
                            <th>Názov tímu</th>
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

        </div>

    </div>





</body>

</html>