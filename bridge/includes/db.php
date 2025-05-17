<?php
header("Access-Control-Allow-Origin: http://firefly.test:5173");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

$env = getenv('APPLICATION_ENV');

if ($env === 'production') {
    // PRODUCTION
    $dsn = "mysql:host=localhost;dbname=cheaouej_firefly;charset=utf8mb4";
    $username = "cheaouej_admin";
    $password = "(Y$[nAanL37H";
} else {
    // DEV (or any other environment)
    $dsn = "mysql:host=localhost;dbname=firefly;charset=utf8mb4";
    $username = "root";
    $password = "jubilee";
}

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
