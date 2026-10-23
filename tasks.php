<?php

require "database.php";

$sql = "

SELECT
    t.id,
    t.title,
    t.status,
    t.priority,
    t.deadline,

    u.name AS assigned_user,

    tm.name AS team_name

FROM tasks t

LEFT JOIN users u
ON t.assigned_to = u.id

LEFT JOIN teams tm
ON t.team_id = tm.id

ORDER BY t.id DESC

";

$stmt = $pdo->query($sql);

$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8">
  <title>Úlohy</title>
</head>

<body>

  <h2>Zoznam úloh</h2>

  <table border="1" cellpadding="10">

    <tr>
      <th>ID</th>
      <th>Názov</th>
      <th>Tím</th>
      <th>Priradené</th>
      <th>Status</th>
      <th>Priorita</th>
      <th>Deadline</th>
    </tr>

    <?php foreach ($tasks as $task): ?>

      <tr>

        <td><?= $task['id'] ?></td>

        <td><?= $task['title'] ?></td>

        <td><?= $task['team_name'] ?></td>

        <td><?= $task['assigned_user'] ?></td>

        <td><?= $task['status'] ?></td>

        <td><?= $task['priority'] ?></td>

        <td><?= $task['deadline'] ?></td>

      </tr>

    <?php endforeach; ?>

  </table>

</body>

</html>