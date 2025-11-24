<?php
require '../db.php';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="agri-report.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Region', 'Commodity', 'Year', 'Period', 'Price (₱)']);

$stmt = $pdo->query("SELECT geolocation, commodity, year, period, price FROM farmgate_prices ORDER BY year DESC");
while ($row = $stmt->fetch()) {
    fputcsv($output, $row);
}