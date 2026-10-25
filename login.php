<?php

session_start();

require "database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST["email"]);
    $password = $_POST["password"];

    $sql = "SELECT * FROM users WHERE email = :email";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        'email' => $email
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];

        header("Location: dashboard.php");
        exit();
    } else {

        $error = "Nesprávny email alebo heslo.";
    }
}
?>

<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="UTF-8">
    <title>Prihlásenie</title>
</head>

<body>

    <h2>Prihlásenie</h2>

    <?php if (!empty($error)): ?>
        <p style="color:red;">
            <?php echo $error; ?>
        </p>
    <?php endif; ?>

    <form method="POST">

        <label>Email:</label><br>
        <input
            type="email"
            name="email"
            required><br><br>

        <label>Heslo:</label><br>
        <input
            type="password"
            id="password"
            name="password"
            required>

        <button
            type="button"
            onclick="togglePassword()">
            👁
        </button>

        <br><br>

        <button type="submit">
            Prihlásiť sa
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

</body>

</html>