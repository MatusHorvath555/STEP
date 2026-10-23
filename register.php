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

        <label>Meno:</label><br>
        <input type="text" name="name" required><br><br>

        <label>Email:</label><br>
        <input type="email" name="email" required><br><br>

        <label>Heslo:</label><br>
        <input type="password" name="password" required><br><br>

        <button type="submit">
            Registrovať
        </button>

    </form>

</body>

</html>