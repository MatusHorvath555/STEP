<?php

session_start();

require_once __DIR__ . "/../config/config.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    $sql = "SELECT id, name, email, password_hash, role
            FROM users
            WHERE email = :email
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        "email" => $email
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user["password_hash"])) {

        session_regenerate_id(true);

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["name"] = $user["name"];
        $_SESSION["role"] = $user["role"];

        // Presmerovanie podľa globálnej roly
        if ($user["role"] === "teacher") {

            header("Location: teacher_dashboard.php");
            exit();
        } elseif ($user["role"] === "admin") {

            header("Location: users.php");
            exit();
        } elseif ($user["role"] === "student") {

            // Overíme, či je študent vedúcim aspoň jedného tímu
            $leaderStmt = $pdo->prepare("
                SELECT EXISTS (
                    SELECT 1
                    FROM team_members
                    WHERE user_id = :user_id
                    AND is_team_leader = TRUE
                )
            ");

            $leaderStmt->execute([
                "user_id" => $user["id"]
            ]);

            $isTeamLeader = $leaderStmt->fetchColumn();

            if ($isTeamLeader) {

                header("Location: team_leader_dashboard.php");
                exit();
            } else {

                header("Location: dashboard.php");
                exit();
            }
        } else {

            // Neznáma rola – nepovolíme prístup
            session_unset();
            session_destroy();

            header("Location: login.php");
            exit();
        }
    } else {

        $error = "Nesprávny email alebo heslo.";
    }
}
?>


<!DOCTYPE html>
<html lang="sk">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>STEP - Prihlásenie</title>
    <link rel="stylesheet" href="css/premenne.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <main class="login-container">

        <h2>Prihlásenie</h2>

        <!-- Chybová správa -->
        <?php if (!empty($error)): ?>
            <p class="alert error">
                <?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?>
            </p>
        <?php endif; ?>

        <form method="POST" class="auth-form">

            <section class="form-row">
                <label for="email">Email:</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    required>
            </section>

            <section class="form-row">
                <label for="password">Heslo:</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required>
            </section>

            <section class="form-checkbox-group">
                <input
                    type="checkbox"
                    id="show-pass"
                    onclick="togglePassword()">

                <label for="show-pass">Zobraziť heslo</label>
            </section>

            <button type="submit" class="btn-submit">
                Prihlásiť sa
            </button>

        </form>

        <nav class="auth-links">
            <a href="registracia.php">
                Ešte nemáte účet? Zaregistrujte sa.
            </a>

            <a href="index.php">
                Späť na úvodnú stránku
            </a>
        </nav>

    </main>

    <script>
        function togglePassword() {
            const password = document.getElementById("password");

            password.type = password.type === "password"
                ? "text"
                : "password";
        }
    </script>

</body>
</html>