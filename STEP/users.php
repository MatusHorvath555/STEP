<?php

require "database.php";

$stmt = $pdo->query("
    SELECT id, name, email, role
    FROM users
");

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($users);
echo "</pre>";
