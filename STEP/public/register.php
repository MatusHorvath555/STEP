<?php

require "database.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = $_POST["name"];
    $email = $_POST["email"];
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT);

    $sql = "INSERT INTO users
            (name, email, password_hash, role)
            VALUES
            (:name, :email, :password, :role)";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'role' => 'student'
    ]);

    $message = "Registrácia úspešná!";
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Registrácia</title>

</head>

<body>

    <h2>Registrácia používateľa</h2>

    <?php if ($message): ?>
        <p><?php echo $message; ?></p>
    <?php endif; ?>

    <form method="POST">

        <label>Meno a priezvisko<?php:</label><br>
        <input type="text" name="name" required><br><br>

        <label>Email:</label><br>
        <input type="email" name="email" required><br><br>

        <label>Heslo:</label><br>
        <input id="password" type="password" name="password" required><br><br>

        <input type="checkbox" onclick="togglePassword()">Show Password

        <button type="submit">
            Registrovať
        </button>

    </form>

    <script>
        function togglePassword() {

            const password =
                document.getElementById("password");

            if (password.type === "password") {
                password.type = "text";
            } else {
                password.type = "password";
            }
        }
    </script>
<br><a href="login.php">Už máte účet? Prihláste sa tu.</a><br>
<a href="index.php">Späť na úvodnú stránku</a>
</body>

</html>