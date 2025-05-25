<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $host = 'localhost';
    $dbname = 'ecommerce';
    $username = 'root'; // Update in production
    $password = '';     // Update in production

    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("A database error occurred. Please try again later.");
}
?>