<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require "database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];

    $stmt = $pdo->prepare("
        SELECT password_hash
        FROM users
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $_SESSION['user_id']
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($currentPassword, $user['password_hash'])) {

        $newHash = password_hash(
            $newPassword,
            PASSWORD_DEFAULT
        );

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
?>

<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="UTF-8">
    <title>Zmena hesla</title>
</head>

<body>

    <h2>Zmena hesla</h2>

    <?php if (!empty($message)): ?>
        <p><?php echo $message; ?></p>
    <?php endif; ?>

    <form method="POST">

        <label>Aktuálne heslo</label><br>
        <input type="password"
            name="current_password"
            required><br><br>

        <label>Nové heslo</label><br>
        <input type="password"
            name="new_password"
            required><br><br>

        <button type="submit">
            Zmeniť heslo
        </button>

    </form>

</body>

</html>