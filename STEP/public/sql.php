<?php

session_start();

if (!isset($_SESSION['user_id'])) {
  header("Location: <public/login.php");
  exit();
}

if ($_SESSION['role'] != 'teacher') {
  die("Prístup zamietnutý.");
}

require "database.php";

$stmt = $pdo->query("
    SELECT
        id,
        name,
        email,
        role,
        created_at
    FROM users
    WHERE role = 'student'
    ORDER BY name
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="sk">

<head>

  <meta charset="UTF-8">

  <title>Zoznam používateľov</title>

  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    rel="stylesheet">

</head>

<body>

  <div class="container mt-4">

    <h2>Zoznam používateľov</h2>

    <table class="table table-striped table-bordered">

      <thead class="table-dark">

        <tr>
          <th>ID</th>
          <th>Meno</th>
          <th>Email</th>
          <th>Rola</th>
          <th>Vytvorený</th>
        </tr>

      </thead>

      <tbody>

        <?php foreach ($users as $user): ?>

          <tr>

            <td><?php echo $user['id']; ?></td>

            <td><?php echo htmlspecialchars($user['name']); ?></td>

            <td><?php echo htmlspecialchars($user['email']); ?></td>

            <td><?php echo htmlspecialchars($user['role']); ?></td>

            <td><?php echo $user['created_at']; ?></td>

          </tr>

        <?php endforeach; ?>

      </tbody>

    </table>

    <a href="public/teacher_dashboard.php" class="btn btn-primary">
      Späť na Dashboard
    </a>

  </div>

</body>

</html>