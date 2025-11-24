<?php
require '../includes/db.php';
echo "<h2>Importing Data from Local CSV Files</h2>";
echo "This is fast and 100% reliable!<br><br>";

// Mapping: category name → CSV file
$sources = [
    'Fruit Vegetables' => 'fruit_vegetables.csv',
    'Leafy Vegetables' => 'leafy_vegetables.csv'
];

foreach ($sources as $categoryName => $filename) {
    $filePath = __DIR__ . '/../csv/' . $filename;

    if (!file_exists($filePath)) {
        echo "File not found: csv/$filename<br>";
        continue;
    }

    echo "Importing $categoryName from $filename...<br>";
    flush();

    $handle = fopen($filePath, "r");
    if (!$handle) {
        echo "Cannot open file: $filename<br>";
        continue;
    }

    // First row = headers (skip)
    $headers = fgetcsv($handle);
    $catId = $pdo->query("SELECT id FROM categories WHERE name = '$categoryName'")->fetchColumn();

    $count = 0;
    while (($row = fgetcsv($handle)) !== false) {
        // PSA CSV format: Geolocation, Commodity, Year, Period, Value
        if (count($row) < 5) continue;

        $geo     = trim($row[0]);
        $commodity = trim($row[1]);
        $year    = trim($row[2]);
        $period  = trim($row[3]);
        $price   = trim($row[4]);

        if (!is_numeric($price) || $price == '') continue;

        // Insert geolocation
        $pdo->prepare("INSERT IGNORE INTO geolocations (code, name) VALUES (?, ?)")->execute([$geo, $geo]);
        $geoId = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM geolocations WHERE code = " . $pdo->quote($geo))->fetchColumn();

        // Insert commodity
        $pdo->prepare("INSERT IGNORE INTO commodities (code, name, category_id) VALUES (?, ?, ?)")->execute([$commodity, $commodity, $catId]);
        $commId = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM commodities WHERE code = " . $pdo->quote($commodity))->fetchColumn();

        // Insert price
        $pdo->prepare("INSERT INTO prices (commodity_id, geolocation_id, year, period, price_php) 
                       VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE price_php = ?")
            ->execute([$commId, $geoId, $year, $period, $price, $price]);

        $count++;
    }
    fclose($handle);
    echo "Successfully imported $count rows for $categoryName<br><br>";
}

echo "<hr><h3 style='color:green;'>All CSV data imported successfully!</h3>";
echo "<a href='../index.php'>Go to Dashboard →</a>";
?>