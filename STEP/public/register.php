
<?php

require_once "config.php";

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
    <title>Registrácia</title>
</head>

<body>

    <h2>Registrácia používateľa</h2>

    <?php if ($message): ?>
        <p>
            <?php echo htmlspecialchars($message); ?>
        </p>
    <?php endif; ?>

    <form method="POST">

        <label>Meno a priezvisko:</label><br>
        <input type="text" name="name" required><br><br>

        <label>Email:</label><br>
        <input type="email" name="email" required><br><br>

        <label>Heslo:</label><br>
        <input
            id="password"
            type="password"
            name="password"
            required
        ><br><br>

        <label>Potvrdenie hesla:</label><br>
        <input
            id="confirm_password"
            type="password"
            name="confirm_password"
            required
        ><br><br>

        <input
            type="checkbox"
            onclick="togglePassword()"
        >
        Zobraziť heslá

        <br><br>

        <button type="submit">
            Registrovať
        </button>

    </form>

    <script>
        function togglePassword() {

            const password = document.getElementById("password");
            const confirmPassword = document.getElementById("confirm_password");

            const newType = password.type === "password" ? "text" : "password";

            password.type = newType;
            confirmPassword.type = newType;
        }
    </script>

    <br>

    <a href="login.php">Už máte účet? Prihláste sa tu.</a>

    <br>

    <a href="index.php">Späť na úvodnú stránku</a>

</body>
</html>
