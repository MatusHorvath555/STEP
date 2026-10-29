<?php

$host = "host:aws-0-eu-west-1.pooler.supabase.com";
$dbname = "postgres";
$user = "postgres.jmgfuykbkuzjmcxxvfwv";
$password = "1nbOVXk8KQl4CrQv";
$port = "5432";

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Chyba pripojenia: " . $e->getMessage());
}
