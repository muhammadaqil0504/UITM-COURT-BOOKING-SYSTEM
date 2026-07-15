<?php

// Railway environment variables (fallback to local Docker values)
$host = getenv('MYSQLHOST') ?: 'db';
$port = getenv('MYSQLPORT') ?: '3306';
$dbname = getenv('MYSQLDATABASE') ?: 'uitm_court_db';
$username = getenv('MYSQLUSER') ?: 'uitm_user';
$password = getenv('MYSQLPASSWORD') ?: 'uitm_password';

try {

    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die("Database Connection Failure: " . $e->getMessage());

}

?>