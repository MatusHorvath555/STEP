
<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (($_SESSION['role'] ?? '') !== 'teacher') {
    die("Prístup zamietnutý.");
}

require_once "config.php";

$message = "";
$messageType = "info";

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/*
 * PRIDANIE ŠTUDENTA DO TÍMU
 */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        die("Neplatná požiadavka. Obnovte stránku a skúste to znova.");
    }

    $team_id = filter_input(INPUT_POST, 'team_id', FILTER_VALIDATE_INT);
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

    if (!$team_id || !$user_id) {

        $message = "Vyberte platný tím a študenta.";
        $messageType = "danger";

    } else {

        // Kontrola existencie tímu
        $teamCheck = $pdo->prepare("
            SELECT id
            FROM teams
            WHERE id = :team_id
        ");

        $teamCheck->execute([
            'team_id' => $team_id
        ]);

        // Kontrola existencie študenta
        $studentCheck = $pdo->prepare("
            SELECT id
            FROM users
            WHERE id = :user_id
            AND role = 'student'
        ");

        $studentCheck->execute([
            'user_id' => $user_id
        ]);

        if (!$teamCheck->fetch()) {

            $message = "Vybraný tím neexistuje.";
            $messageType = "danger";

        } elseif (!$studentCheck->fetch()) {

            $message = "Vybraný študent neexistuje.";
            $messageType = "danger";

        } else {

            // Kontrola duplicitného členstva
            $check = $pdo->prepare("
                SELECT id
                FROM team_members
                WHERE team_id = :team_id
                AND user_id = :user_id
            ");

            $check->execute([
                'team_id' => $team_id,
                'user_id' => $user_id
            ]);

            if ($check->fetch()) {

                $message = "Študent už je členom tohto tímu.";
                $messageType = "warning";

            } else {

                $insert = $pdo->prepare("
                    INSERT INTO team_members (
                        team_id,
                        user_id
                    )
                    VALUES (
                        :team_id,
                        :user_id
                    )
                ");

                $insert->execute([
                    'team_id' => $team_id,
                    'user_id' => $user_id
                ]);

                $message = "Študent bol úspešne pridaný do tímu.";
                $messageType = "success";
            }
        }
    }
}

/*
 * NAČÍTANIE TÍMOV
 */

$teams = $pdo->query("
    SELECT id, name
    FROM teams
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

/*
 * NAČÍTANIE ŠTUDENTOV
 */

$students = $pdo->query("
    SELECT id, name, email
    FROM users
    WHERE role = 'student'
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

/*
 * ZOBRAZENIE ČLENOV TÍMOV
 */

$members = $pdo->query("
    SELECT
        tm.id,
        t.name AS team_name,
        u.name AS student_name,
        u.email
    FROM team_members tm
    JOIN teams t ON tm.team_id = t.id
    JOIN users u ON tm.user_id = u.id
    ORDER BY t.name, u.name
")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="sk">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Členovia tímov</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

    <div class="container mt-4">

        <h1>Členovia tímov</h1>

        <a href="teacher_dashboard.php" class="btn btn-secondary mb-3">
            Dashboard
        </a>

        <?php if (!empty($message)): ?>

            <div class="alert alert-<?php echo $messageType; ?>">

                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>

            </div>

        <?php endif; ?>

        <div class="card mb-4">

            <div class="card-header">
                <h2>Pridať študenta do tímu</h2>
            </div>

            <div class="card-body">

                <?php if (empty($teams)): ?>

                    <p>Najskôr vytvorte tím.</p>

                <?php elseif (empty($students)): ?>

                    <p>Zatiaľ nie sú registrovaní žiadni študenti.</p>

                <?php else: ?>

                    <form method="POST">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>"
                        >

                        <div class="mb-3">

                            <label class="form-label" for="team_id">
                                Vyberte tím
                            </label>

                            <select
                                name="team_id"
                                id="team_id"
                                class="form-select"
                                required>

                                <option value="">Vyberte tím</option>

                                <?php foreach ($teams as $team): ?>

                                    <option value="<?php echo (int) $team['id']; ?>">

                                        <?php echo htmlspecialchars($team['name'], ENT_QUOTES, 'UTF-8'); ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="mb-3">

                            <label class="form-label" for="user_id">
                                Vyberte študenta
                            </label>

                            <select
                                name="user_id"
                                id="user_id"
                                class="form-select"
                                required>

                                <option value="">Vyberte študenta</option>

                                <?php foreach ($students as $student): ?>

                                    <option value="<?php echo (int) $student['id']; ?>">

                                        <?php echo htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8'); ?>
                                        (<?php echo htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8'); ?>)

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <button
                            class="btn btn-primary"
                            type="submit">

                            Pridať študenta do tímu

                        </button>

                    </form>

                <?php endif; ?>

            </div>

        </div>

        <h3>Zoznam členov tímov</h3>

        <?php if (empty($members)): ?>

            <p>Zatiaľ žiadne tímy nemajú priradených študentov.</p>

        <?php else: ?>

            <table class="table table-striped table-hover">

                <thead class="table-dark">

                    <tr>
                        <th>Tím</th>
                        <th>Študent</th>
                        <th>Email</th>
                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($members as $member): ?>

                        <tr>

                            <td>
                                <?php echo htmlspecialchars($member['team_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($member['student_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </td>

                            <td>
                                <?php echo htmlspecialchars($member['email'], ENT_QUOTES, 'UTF-8'); ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>

</body>
</html>
