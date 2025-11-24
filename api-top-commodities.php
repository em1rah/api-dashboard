<?php
require 'db.php';
header('Content-Type: application/json');

$latest_year = $pdo->query("SELECT MAX(year) FROM farmgate_prices")->fetchColumn();

$sql = "SELECT commodity, AVG(price) as avg_price 
        FROM farmgate_prices 
        WHERE year = ? 
        GROUP BY commodity 
        ORDER BY avg_price DESC LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute([$latest_year]);
$rows = $stmt->fetchAll();

$commodities = []; $prices = [];
foreach ($rows as $r) {
    $commodities[] = $r['commodity'];
    $prices[] = round($r['avg_price'], 2);
}

echo json_encode(['commodities' => $commodities, 'prices' => $prices]);