<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (($_SESSION['role'] ?? '') !== 'teacher') {
    http_response_code(403);
    die("Prístup zamietnutý.");
}

require_once __DIR__ . "/../config/config.php";

$message = "";
$messageType = "info";

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/*
 * SPRACOVANIE FORMULÁROV
 */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        http_response_code(400);
        die("Neplatná požiadavka. Obnovte stránku a skúste to znova.");
    }

    $action = $_POST['action'] ?? '';

    /*
     * PRIDANIE ŠTUDENTA DO TÍMU
     */

    if ($action === 'add_member') {

        $team_id = filter_input(INPUT_POST, 'team_id', FILTER_VALIDATE_INT);
        $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

        if (!$team_id || !$user_id || $team_id <= 0 || $user_id <= 0) {
            $message = "Vyberte platný tím a študenta.";
            $messageType = "error";
        } else {

            $teamCheck = $pdo->prepare("
                SELECT id
                FROM teams
                WHERE id = :team_id
            ");

            $teamCheck->execute(['team_id' => $team_id]);

            $studentCheck = $pdo->prepare("
                SELECT id
                FROM users
                WHERE id = :user_id
                AND role = 'student'
            ");

            $studentCheck->execute(['user_id' => $user_id]);

            if (!$teamCheck->fetch()) {
                $message = "Vybraný tím neexistuje.";
                $messageType = "error";
            } elseif (!$studentCheck->fetch()) {
                $message = "Vybraný študent neexistuje.";
                $messageType = "error";
            } else {

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
                    $messageType = "error";
                } else {

                    $insert = $pdo->prepare("
                        INSERT INTO team_members (team_id, user_id)
                        VALUES (:team_id, :user_id)
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
     * NASTAVENIE VEDÚCEHO TÍMU
     */ elseif ($action === 'set_leader') {

        $team_id = filter_input(INPUT_POST, 'team_id', FILTER_VALIDATE_INT);
        $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

        if (!$team_id || !$user_id || $team_id <= 0 || $user_id <= 0) {
            $message = "Neplatný tím alebo študent.";
            $messageType = "error";
        } else {

            try {
                $pdo->beginTransaction();

                // Overenie, že študent je členom vybraného tímu
                $memberCheck = $pdo->prepare("
                    SELECT tm.id
                    FROM team_members tm
                    JOIN users u ON u.id = tm.user_id
                    WHERE tm.team_id = :team_id
                    AND tm.user_id = :user_id
                    AND u.role = 'student'
                    FOR UPDATE OF tm
                ");

                $memberCheck->execute([
                    'team_id' => $team_id,
                    'user_id' => $user_id
                ]);

                if (!$memberCheck->fetch()) {
                    throw new RuntimeException(
                        "Vybraný študent nie je členom tohto tímu."
                    );
                }

                // Zrušenie aktuálneho vedúceho v danom tíme
                $removeOldLeader = $pdo->prepare("
                    UPDATE team_members
                    SET is_team_leader = FALSE
                    WHERE team_id = :team_id
                    AND is_team_leader = TRUE
                ");

                $removeOldLeader->execute([
                    'team_id' => $team_id
                ]);

                // Nastavenie nového vedúceho
                $setNewLeader = $pdo->prepare("
                    UPDATE team_members
                    SET is_team_leader = TRUE
                    WHERE team_id = :team_id
                    AND user_id = :user_id
                ");

                $setNewLeader->execute([
                    'team_id' => $team_id,
                    'user_id' => $user_id
                ]);

                $pdo->commit();

                $message = "Vedúci tímu bol úspešne nastavený.";
                $messageType = "success";
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log("Team leader update error: " . $e->getMessage());

                $message = "Vedúceho tímu sa nepodarilo nastaviť.";
                $messageType = "error";
            }
        }
    }

    /*
     * ODVOĽANIE VEDÚCEHO TÍMU
     */ elseif ($action === 'remove_leader') {

        $team_id = filter_input(INPUT_POST, 'team_id', FILTER_VALIDATE_INT);
        $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

        if (!$team_id || !$user_id || $team_id <= 0 || $user_id <= 0) {
            $message = "Neplatný tím alebo študent.";
            $messageType = "error";
        } else {

            $removeLeader = $pdo->prepare("
                UPDATE team_members
                SET is_team_leader = FALSE
                WHERE team_id = :team_id
                AND user_id = :user_id
                AND is_team_leader = TRUE
            ");

            $removeLeader->execute([
                'team_id' => $team_id,
                'user_id' => $user_id
            ]);

            if ($removeLeader->rowCount() > 0) {
                $message = "Vedúci tímu bol úspešne odvolaný.";
                $messageType = "success";
            } else {
                $message = "Tento študent nie je vedúcim vybraného tímu.";
                $messageType = "error";
            }
        }
    } else {
        $message = "Neznáma požiadavka.";
        $messageType = "error";
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
        tm.team_id,
        tm.user_id,
        tm.is_team_leader,
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
    <title>Členovia tímov | STEP</title>

    <link rel="stylesheet" href="css/premenne.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <?php require_once "navigation.php"; ?>

    <main class="container">

        <div class="heading">
            <div>
                <h1>Členovia tímov</h1>
                <p class="subtitle">Priraďovanie študentov do tímov a správa vedúcich</p>
            </div>
            <div>
                <a href="teams.php" class="btn btn-secondary">
                    ← Späť na správy tímov
                </a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <!-- Karta: Pridanie študenta do tímu -->
        <div class="card" style="margin-bottom: 30px;">
            <h2>Pridať študenta do tímu</h2>

            <?php if (empty($teams)): ?>
                <p class="subtitle">Najskôr musíte vytvoriť nejaký tím.</p>
            <?php elseif (empty($students)): ?>
                <p class="subtitle">Zatiaľ nie sú registrovaní žiadni študenti.</p>
            <?php else: ?>

                <form method="POST" class="auth-form" style="max-width: 100%;">

                    <input type="hidden" name="csrf_token"
                        value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                    <input type="hidden" name="action" value="add_member">

                    <div class="form-row">
                        <label for="team_id">Vyberte tím</label>
                        <select name="team_id" id="team_id" required>
                            <option value="">-- Vyberte tím --</option>
                            <?php foreach ($teams as $team): ?>
                                <option value="<?php echo (int) $team['id']; ?>">
                                    <?php echo htmlspecialchars($team['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <label for="user_id">Vyberte študenta</label>
                        <select name="user_id" id="user_id" required>
                            <option value="">-- Vyberte študenta --</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo (int) $student['id']; ?>">
                                    <?php echo htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8'); ?> 
                                    (<?php echo htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="display: flex; justify-content: flex-end; margin-top: 10px;">
                        <button type="submit" class="btn" style="width: auto; min-width: 200px;">
                            Pridať študenta do tímu
                        </button>
                    </div>

                </form>

            <?php endif; ?>

        </div>

        <!-- Karta s tabuľkou členov (cez celú šírku) -->
        <div class="card-table-wrapper">
            <h2>Zoznam členov tímov</h2>

            <?php if (empty($members)): ?>
                <div class="card-empty-box">
                    <div class="empty">
                        Zatiaľ žiadne tímy nemajú priradených študentov.
                    </div>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="step-table">
                        <thead>
                            <tr>
                                <th>Tím</th>
                                <th>Študent</th>
                                <th>Email</th>
                                <th>Funkcia</th>
                                <th style="text-align: right;">Akcia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $member): ?>
                                <tr>
                                    <td class="table-name">
                                        <?php echo htmlspecialchars($member['team_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($member['student_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td class="table-email">
                                        <?php echo htmlspecialchars($member['email'], ENT_QUOTES, 'URL_ENCODE' === 'URL_ENCODE' ? ENT_QUOTES : ENT_QUOTES); ?>
                                        <!-- Použité bezpečné escape cez htmlspecialchars -->
                                        <?php // echo htmlspecialchars($member['email'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td>
                                        <?php if ($member['is_team_leader']): ?>
                                            <span class="status done" style="display: inline-block; padding: 4px 10px; font-size: 11px;">
                                                Vedúci tímu
                                            </span>
                                        <?php else: ?>
                                            <span class="status progress" style="display: inline-block; padding: 4px 10px; font-size: 11px;">
                                                Člen tímu
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <?php if ($member['is_team_leader']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token"
                                                    value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="action" value="remove_leader">
                                                <input type="hidden" name="team_id" value="<?php echo (int) $member['team_id']; ?>">
                                                <input type="hidden" name="user_id" value="<?php echo (int) $member['user_id']; ?>">

                                                <button type="submit" class="btn" style="background-color: #a33c3c; font-size: 12px; padding: 6px 12px; min-height: 32px;"
                                                    onclick="return confirm('Naozaj chcete odvolať vedúceho tímu?');">
                                                    Odvolať vedúceho
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token"
                                                    value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="action" value="set_leader">
                                                <input type="hidden" name="team_id" value="<?php echo (int) $member['team_id']; ?>">
                                                <input type="hidden" name="user_id" value="<?php echo (int) $member['user_id']; ?>">

                                                <button type="submit" class="btn" style="background-color: var(--farba-akcent); font-size: 12px; padding: 6px 12px; min-height: 32px;"
                                                    onclick="return confirm('Naozaj chcete nastaviť tohto študenta za vedúceho tímu?');">
                                                    Nastaviť za vedúceho
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>

</body>

</html>