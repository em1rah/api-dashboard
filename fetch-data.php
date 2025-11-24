<?php
require 'db.php';

function importCSV($file, $type) {
    global $pdo;
    
    if (!file_exists($file)) {
        echo "File not found: $file<br>";
        return;
    }

    $handle = fopen($file, "r");
    if ($handle === FALSE) return;

    // Skip header if needed
    $header = fgets($handle); // adjust if needed

    $stmt = $pdo->prepare("INSERT INTO farmgate_prices 
        (geolocation, commodity, year, period, price, commodity_type) 
        VALUES (?, ?, ?, ?, ?, ?)");

    while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
        // Adjust column indices based on your actual CSV structure
        // Example PSA CSV columns: "Geolocation","Commodity","Year","Period","Value"
        $geolocation = trim($row[0]);
        $commodity   = trim($row[1]);
        $year        = trim($row[2]);
        $period      = trim($row[3]);
        $price       = floatval(str_replace(',', '', $row[4])); // remove commas

        // Skip invalid rows
        if (empty($price) || $price == 0) continue;

        $stmt->execute([$geolocation, $commodity, $year, $period, $price, $type]);
    }
    fclose($handle);
    echo "Imported $file successfully!<br>";
}

// Import both datasets
importCSV('data/leafy_vegetables.csv', 'leafy');
importCSV('data/fruit_vegetables.csv', 'fruit');

echo "All data imported!";
?>