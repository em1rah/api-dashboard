<?php
require 'db.php';
header('Content-Type: application/json');

$where = [];
$params = [];

if (!empty($_GET['year_from'])) {
    $where[] = "year >= ?";
    $params[] = $_GET['year_from'];
}
if (!empty($_GET['year_to'])) {+
    $where[] = "year <= ?";
    $params[] = $_GET['year_to'];
}
if (!empty($_GET['commodity'])) {
    $where[] = "commodity LIKE ?";
    $params[] = '%' . $_GET['commodity'] . '%';
}

$sql = "SELECT year, AVG(price) as avg_price 
        FROM farmgate_prices";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " GROUP BY year ORDER BY year";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$years = [];
$prices = [];

foreach ($rows as $row) {
    $years[] = $row['year'];
    $prices[] = round($row['avg_price'], 2);
}

echo json_encode(['years' => $years, 'prices' => $prices]);