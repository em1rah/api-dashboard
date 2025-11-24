<?php
require '../db.php';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="PSA_Farmgate_Prices_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Region', 'Commodity', 'Year', 'Period', 'Price (₱/kg)', 'Type']);

$where = []; $params = [];
if (!empty($_GET['year_from'])) { $where[] = "year >= ?"; $params[] = $_GET['year_from']; }
if (!empty($_GET['year_to']))   { $where[] = "year <= ?"; $params[] = $_GET['year_to']; }
if (!empty($_GET['commodity'])) { $where[] = "commodity LIKE ?"; $params[] = '%'.$_GET['commodity'].'%'; }
if (!empty($_GET['region']))    { $where[] = "geolocation = ?"; $params[] = $_GET['region']; }

$sql = "SELECT geolocation, commodity, year, period, price, commodity_type FROM farmgate_prices";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY year DESC, commodity";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $row['geolocation'],
        $row['commodity'],
        $row['year'],
        $row['period'],
        number_format($row['price'], 2),
        $row['commodity_type']
    ]);
}
exit;