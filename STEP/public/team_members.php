<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SESSION['role'] != 'teacher') {
    die("Prístup zamietnutý.");
}

require "database.php";

$message = "";

/*
 * PRIDANIE ŠTUDENTA DO TÍMU
 */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $team_id = $_POST['team_id'];
    $user_id = $_POST['user_id'];

    $check = $pdo->prepare("
        SELECT *
        FROM team_members
        WHERE team_id = :team_id
        AND user_id = :user_id
    ");

    $check->execute([
        'team_id' => $team_id,
        'user_id' => $user_id
    ]);

    if (!$check->fetch()) {

        $insert = $pdo->prepare("
            INSERT INTO team_members
            (
                team_id,
                user_id
            )
            VALUES
            (
                :team_id,
                :user_id
            )
        ");

        $insert->execute([
            'team_id' => $team_id,
            'user_id' => $user_id
        ]);

        $message = "Člen bol pridaný do tímu.";
    } else {

        $message = "Používateľ už v tíme existuje.";
    }
}

/*
 * NAČÍTANIE TÍMOV
 */
$teams = $pdo->query("
    SELECT *
    FROM teams
    ORDER BY name
")->fetchAll(PDO::FETCH_ASSOC);

/*
 * NAČÍTANIE ŠTUDENTOV
 */
$students = $pdo->query("
    SELECT
    id,
    name,
    email
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

    JOIN teams t
    ON tm.team_id = t.id

    JOIN users u
    ON tm.user_id = u.id

    ORDER BY t.name, u.name

")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="sk">

<head>

    <meta charset="UTF-8">
    <title>Členovia tímov</title>
    <style>
        table,
        th,
        td {
            border: 1px solid green;
        }
    </style>


</head>

<body>

    <div class="container mt-4">

        <h1>Členovia tímov</h1>



        <?php if (!empty($message)): ?>

            <div class="alert alert-info">

                <?php echo $message; ?>

            </div>

        <?php endif; ?>


        <div class="card mb-4">

            <div class="card-header">

                <h2>Pridať študenta do tímu</h2>

            </div>

            <div class="card-body">

                <form method="POST">

                    <div class="mb-3">

                        <label class="form-label">

                            <li>Vyber team
                            <li>

                        </label>

                        <select
                            name="team_id"
                            class="form-control"
                            required>

                            <?php foreach ($teams as $team): ?>

                                <option value="<?php echo $team['id']; ?>">

                                    <?php echo $team['name']; ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">

                            <li>Vyber študenta</li>

                        </label>

                        <li><select
                                name="user_id"
                                class="form-control"
                                required>

                                <?php foreach ($students as $student): ?>

                                    <option value="<?php echo $student['id']; ?>">

                                        <?php echo $student['name']; ?>

                                    </option>

                                <?php endforeach; ?>

                            </select></li>

                    </div>
                    <br>
                    <button
                        class="btn btn-primary"
                        type="submit">

                        Pridaj študenta do tímu

                    </button>

                </form>

            </div>

        </div>


        <h3>Zoznam členov tímov</h3>

        <table class="table table-striped">

            <thead>

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

                            <?php echo $member['team_name']; ?>

                        </td>

                        <td>

                            <?php echo $member['student_name']; ?>

                        </td>

                        <td>

                            <?php echo $member['email']; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

    <a href="dashboard.php" class="btn btn-secondary mb-3">

        Dashboard

    </a>

</body>

</html>