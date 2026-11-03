
<?php

require_once __DIR__ . "/../config/config.php";

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirmPassword = $_POST["confirm_password"];

    // Kontrola, či sa heslá zhodujú
    if ($password !== $confirmPassword) {

        $message = "Zadané heslá sa nezhodujú.";
        $messageType = "error";

    } elseif (strlen($password) < 8) {

        $message = "Heslo musí mať aspoň 8 znakov.";
        $messageType = "error";

    } else {

        // Kontrola existujúceho emailu
        $check = $pdo->prepare("
            SELECT id
            FROM users
            WHERE email = :email
        ");

        $check->execute([
            'email' => $email
        ]);

        if ($check->fetch()) {

            $message = "Používateľ s týmto emailom už existuje.";
            $messageType = "error";

        } else {

            // Zahashovanie hesla
            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            // Vytvorenie používateľa
            $sql = "
                INSERT INTO users
                (name, email, password_hash, role)
                VALUES
                (:name, :email, :password, :role)
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                'name' => $name,
                'email' => $email,
                'password' => $passwordHash,
                'role' => 'student'
            ]);

            $message = "Registrácia úspešná!";
            $messageType = "success";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="sk">

<head>
  <meta charset="UTF-8">
    <title>STEP - Registrácia</title>
    <!-- Pripojenie CSS premenných a hlavného štýlu -->
<link rel="stylesheet" href="css/premenne.css">
<link rel="stylesheet" href="css/style.css">  
</head>

<body>

<main class="login-container">
        <h2>Registrácia používateľa</h2>

        <?php if ($message): ?>
            <p class="alert">
                <?php echo htmlspecialchars($message); ?>
            </p>
        <?php endif; ?>

        <form method="POST" class="auth-form">

    <section class="form-row">
        <label for="name">Meno a priezvisko:</label>
        <input type="text" id="name" name="name" required>
    </section>

    <section class="form-row">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>
    </section>

    <section class="form-row">
        <label for="password">Heslo:</label>
        <input type="password" id="password" name="password" required>
    </section>

    <section class="form-row">
        <label for="confirm_password">Potvrdenie hesla:</label>
        <input type="password" id="confirm_password"
               name="confirm_password" required>
    </section>

    <section class="form-checkbox-group">
        <input type="checkbox" id="show-pass" onclick="togglePassword()">
        <label for="show-pass">Zobraziť heslá</label>
    </section>

    <button type="submit" class="btn-submit">
        Registrovať
    </button>

</form>
        <nav class="auth-links">
            <a href="login.php">Už máte účet? Prihláste sa tu.</a>
            <a href="index.php">Späť na úvodnú stránku</a>
        </nav>
    </main>

    <script>
        function togglePassword() {
            const password = document.getElementById("password");
            const confirmPassword = document.getElementById("confirm_password");
            const newType = password.type === "password" ? "text" : "password";

            password.type = newType;
            confirmPassword.type = newType;
        }
    </script>

</body>
</html>