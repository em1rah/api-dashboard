<?php
require 'db.php';
header('Content-Type: application/json');

$commodity = $_GET['commodity'] ?? '';
$params = [];

if (!empty($commodity)) {
    $sql = "SELECT DISTINCT r.name 
            FROM price_records p 
            JOIN regions r ON p.region_id = r.id 
            JOIN commodities c ON p.commodity_id = c.id 
            WHERE p.price IS NOT NULL AND c.name = ? 
            ORDER BY r.name";
    $params[] = $commodity;
} else {
    $sql = "SELECT DISTINCT name FROM regions ORDER BY name";
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $regions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['regions' => $regions]);
} catch (PDOException $e) {
    echo json_encode(['regions' => []]);
}
