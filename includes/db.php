<?php
$host = 'localhost';  // Change if needed (e.g., for online hosting)
$db   = 'agri_prices';
$user = 'root';       // Default for XAMPP; change for production
$pass = '';           // Default for XAMPP

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>