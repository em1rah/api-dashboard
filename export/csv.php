<?php
require '../includes/db.php';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="agri_prices_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Commodity', 'Region', 'Year', 'Period', 'Price (PHP/kg)']);

$where = ["period = 'Annual'"];
$params = [];
if (isset($_GET['commodity']) && $_GET['commodity']) { $where[] = "c.id = ?"; $params[] = $_GET['commodity']; }
if (isset($_GET['region']) && $_GET['region']) { $where[] = "g.id = ?"; $params[] = $_GET['region']; }
if (isset($_GET['y1'])) { $where[] = "p.year >= ?"; $params[] = $_GET['y1']; }
if (isset($_GET['y2'])) { $where[] = "p.year <= ?"; $params[] = $_GET['y2']; }

$sql = "SELECT c.name AS commodity, g.name AS region, p.year, p.period, p.price_php
        FROM prices p
        JOIN commodities c ON p.commodity_id = c.id
        JOIN geolocations g ON p.geolocation_id = g.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY p.year DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, $row);
}
fclose($output);
?>