<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . "/../config/config.php";

$message = "";
$messageType = "";

// Inicializácia CSRF tokenu
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Overenie CSRF tokenu
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        die("Chyba overenia CSRF tokenu.");
    }

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';

    // Kontrola minimálnej dĺžky hesla
    if (strlen($newPassword) < 8) {
        $message = "Nové heslo musí mať aspoň 8 znakov.";
        $messageType = "error";
    } else {
        // Načítanie aktuálneho hesla z databázy
        $stmt = $pdo->prepare("
            SELECT password_hash
            FROM users
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $_SESSION['user_id']
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Overenie aktuálneho hesla
        if ($user && password_verify($currentPassword, $user['password_hash'])) {
            // Vytvorenie nového hashu
            $newHash = password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );

            // Aktualizácia hesla v databáze
            $update = $pdo->prepare("
                UPDATE users
                SET password_hash = :password
                WHERE id = :id
            ");

            $update->execute([
                'password' => $newHash,
                'id' => $_SESSION['user_id']
            ]);

            $message = "Heslo bolo úspešne zmenené.";
            $messageType = "success";
        } else {
            $message = "Aktuálne heslo nie je správne.";
            $messageType = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zmena hesla | STEP</title>

    <link rel="stylesheet" href="css/premenne.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <?php require_once "navigation.php"; ?>

    <main class="container">

        <div class="heading">
            <div>
                <h1>Zmena hesla</h1>
                <p class="subtitle">Zabezpečte svoj účet zmenou aktuálneho hesla</p>
            </div>
            <div>
                <a href="teacher_dashboard.php" class="btn btn-secondary">
                    ← Späť na dashboard
                </a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="card profile-card-wrapper">
            <h2>Nastavenie nového hesla</h2>

            <form method="POST" class="auth-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                <div class="form-group">
                    <label for="current_password">Aktuálne heslo</label>
                    <input id="current_password"
                        type="password"
                        name="current_password"
                        required>
                </div>

                <div class="form-checkbox-group checkbox-spacing-sm">
                    <input type="checkbox" id="show_current" onclick="togglePassword('current_password')">
                    <label for="show_current" class="checkbox-label-plain">Zobraziť aktuálne heslo</label>
                </div>

                <div class="form-group">
                    <label for="new_password">Nové heslo (min. 8 znakov)</label>
                    <input id="new_password"
                        type="password"
                        name="new_password"
                        required>
                </div>

                <div class="form-checkbox-group checkbox-spacing-md">
                    <input type="checkbox" id="show_new" onclick="togglePassword('new_password')">
                    <label for="show_new" class="checkbox-label-plain">Zobraziť nové heslo</label>
                </div>

                <div class="form-actions-spacing">
                    <button type="submit" class="btn btn-full">
                        Zmeniť heslo
                    </button>
                </div>

            </form>
        </div>

    </main>

    <script>
        function togglePassword(id) {
            const password = document.getElementById(id);
            if (password.type === "password") {
                password.type = "text";
            } else {
                password.type = "password";
            }
        }
    </script>

</body>

</html>