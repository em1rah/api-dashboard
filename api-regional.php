<?php
require 'db.php';
header('Content-Type: application/json');

$sql = "SELECT geolocation, AVG(price) as avg_price 
        FROM farmgate_prices 
        GROUP BY geolocation 
        ORDER BY avg_price DESC";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll();

$regions = []; $prices = [];
foreach ($rows as $r) {
    $regions[] = $r['geolocation'];
    $prices[] = round($r['avg_price'], 2);
}

echo json_encode(['regions' => $regions, 'prices' => $prices]);