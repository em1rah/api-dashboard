<?php
require 'db.php';
header('Content-Type: application/json');

$region = $_GET['region'] ?? '';
$params = [];

if (!empty($region) && $region !== 'all') {
    $sql = "SELECT DISTINCT c.name 
            FROM price_records p 
            JOIN commodities c ON p.commodity_id = c.id 
            JOIN regions r ON p.region_id = r.id 
            WHERE p.price IS NOT NULL AND r.name = ? 
            ORDER BY c.name";
    $params[] = $region;
} else {
    $sql = "SELECT DISTINCT name FROM commodities ORDER BY name";
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $commodities = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['commodities' => $commodities]);
} catch (PDOException $e) {
    echo json_encode(['commodities' => []]);
}