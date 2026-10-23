<?php
$host = "db.jmgfuykbkuzjmcxxvfwv.supabase.co";
$dbname = "postgres";
$user = "postgres";
$password = "1nbOVXk8KQl4CrQv";
$port = "5432";
try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $password
    );
    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );
    echo "Databáza pripojená";
} catch (PDOException $e) {
    die("Chyba: " . $e->getMessage());
}
