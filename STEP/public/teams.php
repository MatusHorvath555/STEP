<?php

session_start();

// Kontrola prihlásenia
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Kontrola oprávnenia učiteľa
if (($_SESSION['role'] ?? '') !== 'teacher') {
    die("Prístup zamietnutý.");
}

require_once __DIR__ . "/../config/config.php";

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
        $messageType = "error";
    } elseif (mb_strlen($name, 'UTF-8') > 255) {
        $message = "Názov tímu môže obsahovať maximálne 255 znakov.";
        $messageType = "error";
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

    <link rel="stylesheet" href="css/premenne.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <?php require_once "navigation.php"; ?>

    <main class="container">

        <div class="heading">
            <div>
                <h1>Správa tímov</h1>
                <p class="subtitle">Vytváranie a prehľad existujúcich tímov v systéme</p>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="teacher_dashboard.php" class="btn">
                    ← Dashboard
                </a>
                <a href="team_members.php" class="btn btn-secondary">
                    Správa členov tímov
                </a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <!-- Karta s formulárom -->
        <div class="card" style="margin-bottom: 30px;">
            <h2>Vytvorenie nového tímu</h2>

            <form method="POST" class="auth-form" style="max-width: 100%;">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                <div class="form-row">
                    <label for="team_name">Názov tímu</label>
                    <input
                        type="text"
                        name="team_name"
                        id="team_name"
                        maxlength="255"
                        required>
                </div>

                <div style="display: flex; justify-content: flex-end; margin-top: 10px;">
                    <button type="submit" class="btn" style="width: auto; min-width: 160px;">
                        Vytvoriť tím
                    </button>
                </div>

            </form>
        </div>

        <!-- Karta s tabuľkou tímov cez celú šírku -->
        <div class="card-table-wrapper">
            <h2>Existujúce tímy</h2>

            <?php if (empty($teams)): ?>
                <div class="card-empty-box">
                    <div class="empty">
                        Zatiaľ nebol vytvorený žiadny tím.
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="step-table">
                        <thead>
                            <tr>
                                <th class="col-id">ID</th>
                                <th>Názov tímu</th>
                                <th>Dátum vytvorenia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teams as $team): ?>
                                <tr>
                                    <td class="col-id">
                                        <?php echo (int) $team['id']; ?>
                                    </td>
                                    <td class="table-name">
                                        <?php echo htmlspecialchars($team['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td class="table-date">
                                        <?php 
                                            $dateFormatted = $team['created_at'] ?? '';
                                            if (!empty($dateFormatted)) {
                                                $dateFormatted = date('d.m.Y H:i', strtotime($dateFormatted));
                                            }
                                            echo htmlspecialchars($dateFormatted ?: 'Neznámy', ENT_QUOTES, 'UTF-8'); 
                                        ?>
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