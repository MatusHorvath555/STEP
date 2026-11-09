<?php

session_start();

// Kontrola prihlásenia
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Kontrola oprávnenia učiteľa
if (($_SESSION['role'] ?? '') !== 'teacher') {
    http_response_code(403);
    die("Prístup zamietnutý.");
}

require_once __DIR__ . "/../config/config.php";

$message = "";
$messageType = "success";

// Správa po presmerovaní (zabráni opätovnému odoslaniu formulára po F5)
if (!empty($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

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
        http_response_code(400);
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

        $_SESSION['flash_message'] = "Tím bol úspešne vytvorený.";
        $_SESSION['flash_type'] = "success";

        header("Location: teams.php");
        exit();
    }
}

/*
 * NAČÍTANIE TÍMOV (aj s počtom členov)
 */

$stmt = $pdo->query("
    SELECT
        t.id,
        t.name,
        t.created_at,
        COUNT(tm.id) AS member_count
    FROM teams t
    LEFT JOIN team_members tm ON tm.team_id = t.id
    GROUP BY t.id, t.name, t.created_at
    ORDER BY t.id DESC
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
            <div class="heading-actions">
                <a href="teacher_dashboard.php" class="btn">← Dashboard</a>
                <a href="team_members.php" class="btn btn-secondary">Správa členov tímov</a>
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

        <!-- Karta s tabuľkou tímov (rovnaká štruktúra ako karta formulára) -->
        <div class="card">
            <h2>Existujúce tímy</h2>

            <?php if (empty($teams)): ?>
                <p class="subtitle">Zatiaľ nebol vytvorený žiadny tím.</p>
            <?php else: ?>
                <div class="table-responsive" style="border: 1px solid #e1ebeb; border-radius: 10px;">
                    <table class="step-table">
                        <thead>
                            <tr>
                                <th class="col-id">ID</th>
                                <th>Názov tímu</th>
                                <th>Počet členov</th>
                                <th>Dátum vytvorenia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teams as $team): ?>
                                <?php
                                $dateFormatted = $team['created_at'] ?? '';
                                if (!empty($dateFormatted)) {
                                    $dateFormatted = date('d.m.Y H:i', strtotime($dateFormatted));
                                }
                                ?>
                                <tr>
                                    <td class="col-id">
                                        <?php echo (int) $team['id']; ?>
                                    </td>
                                    <td class="table-name">
                                        <?php echo htmlspecialchars($team['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td>
                                        <?php echo (int) $team['member_count']; ?>
                                    </td>
                                    <td class="table-date">
                                        <?php echo htmlspecialchars($dateFormatted ?: 'Neznámy', ENT_QUOTES, 'UTF-8'); ?>
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