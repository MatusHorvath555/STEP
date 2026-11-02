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
    <title>Prihlásenie</title>
</head>

<body>
    <?php require_once "navigation.php"; ?>

    <h2>Prihlásenie</h2>

    <?php if (!empty($error)): ?>
        <p style="color:red;">
            <?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?>
        </p>
    <?php endif; ?>

    <form method="POST">

        <label for="email">Email:</label><br>
        <input
            type="email"
            id="email"
            name="email"
            required><br><br>

        <label for="password">Heslo:</label><br>
        <input
            type="password"
            id="password"
            name="password"
            required>

        <button
            type="button"
            onclick="togglePassword()"
            aria-label="Zobraziť alebo skryť heslo">
            👁
        </button>

        <br><br>

        <button type="submit">
            Prihlásiť sa
        </button>

    </form>

    <script>
        function togglePassword() {

            const password = document.getElementById("password");

            if (password.type === "password") {
                password.type = "text";
            } else {
                password.type = "password";
            }
        }
    </script>

</body>

</html>