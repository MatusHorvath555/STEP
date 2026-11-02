<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . "/../config/config.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];

    // Kontrola minimálnej dĺžky hesla
    if (strlen($newPassword) < 8) {

        $message = "Nové heslo musí mať aspoň 8 znakov.";
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
        } else {

            $message = "Aktuálne heslo nie je správne.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="UTF-8">
    <title>Zmena hesla</title>
</head>

<body>
    <?php require_once "navigation.php"; ?>

    <h2>Zmena hesla</h2>

    <?php if (!empty($message)): ?>
        <p><?php echo $message; ?></p>
    <?php endif; ?>


    <form method="POST">

        <label>Aktuálne heslo:</label><br>
        <input id="current_password"
            type="password"
            name="current_password"
            required><br>

        <input type="checkbox"
            onclick="togglePassword('current_password')">
        Zobraziť heslo

        <br><br>

        <label>Nové heslo:</label><br>
        <input id="new_password"
            type="password"
            name="new_password"
            required><br>

        <input type="checkbox"
            onclick="togglePassword('new_password')">
        Zobraziť heslo

        <br><br>

        <button type="submit">
            Zmeniť heslo
        </button>

    </form>

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