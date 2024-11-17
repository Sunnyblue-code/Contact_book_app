<?php
$host = 'localhost';
$dbname = 'contact_book';  // Your database name
$username = 'root';        // Typical Laragon MySQL username
$password = '';            // Default Laragon MySQL password is empty

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Database connection error: ' . $e->getMessage());
    die('Connection failed: ' . $e->getMessage());
}
