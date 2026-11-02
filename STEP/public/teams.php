
<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (($_SESSION['role'] ?? '') !== 'teacher') {
    die("Prístup zamietnutý.");
}

require_once "config.php";

$message = "";
$messageType = "success";

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/*
 * VYTVORENIE NOVÉHO TÍMU
 */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        die("Neplatná požiadavka. Obnovte stránku a skúste to znova.");
    }

    $name = trim($_POST['team_name'] ?? '');

    if ($name === '') {

        $message = "Zadajte názov tímu.";
        $messageType = "danger";

    } elseif (mb_strlen($name, 'UTF-8') > 255) {

        $message = "Názov tímu môže obsahovať maximálne 255 znakov.";
        $messageType = "danger";

    } else {

        $stmt = $pdo->prepare("
            INSERT INTO teams (name)
            VALUES (:name)
        ");

        $stmt->execute([
            'name' => $name
        ]);

        $message = "Tím bol úspešne vytvorený.";
        $messageType = "success";
    }
}

/*
 * NAČÍTANIE TÍMOV
 */

$stmt = $pdo->query("
    SELECT id, name, created_at
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

    <title>Správa tímov | STEP</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

    <div class="container mt-4">

        <h1>Správa tímov</h1>

        <a href="teacher_dashboard.php" class="btn btn-secondary mb-3">
            Dashboard
        </a>

        <a href="team_members.php" class="btn btn-secondary mb-3">
            Správa členov tímov
        </a>

        <?php if (!empty($message)): ?>

            <div class="alert alert-<?php echo $messageType; ?>">

                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>

            </div>

        <?php endif; ?>

        <div class="card mb-4">

            <div class="card-header">
                Vytvorenie nového tímu
            </div>

            <div class="card-body">

                <form method="POST">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"
                    >

                    <div class="mb-3">

                        <label class="form-label" for="team_name">
                            Názov tímu
                        </label>

                        <input
                            type="text"
                            name="team_name"
                            id="team_name"
                            class="form-control"
                            maxlength="255"
                            required
                        >

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

                <?php if (empty($teams)): ?>

                    <p class="text-muted">
                        Zatiaľ nebol vytvorený žiadny tím.
                    </p>

                <?php else: ?>

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
                                        <?php echo (int) $team['id']; ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($team['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($team['created_at'] ?? 'Neznámy', ENT_QUOTES, 'UTF-8'); ?>
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
