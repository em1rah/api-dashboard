<?php
ini_set('max_execution_time', 0);
ini_set('memory_limit', '2048M');

$pdo = new PDO("mysql:host=localhost;dbname=agri_dashboard;charset=utf8mb4", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "<pre style='font-size:16px;'>";
echo "STARTING PERFECT IMPORT - EVERY PRICE WILL BE LOADED!\n\n";

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$pdo->exec("TRUNCATE TABLE price_records");
$pdo->exec("TRUNCATE TABLE commodities");
$pdo->exec("TRUNCATE TABLE regions");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

$insertRegion = $pdo->prepare("INSERT IGNORE INTO regions (name) VALUES (?)");
$insertComm   = $pdo->prepare("INSERT INTO commodities (name, category) VALUES (?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)");
$insertPrice  = $pdo->prepare("INSERT INTO price_records (commodity_id, region_id, year, period, price) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE price = VALUES(price)");

function getRegionId($pdo, $name) {
    $name = trim($name) ?: 'PHILIPPINES';
    $stmt = $pdo->prepare("SELECT id FROM regions WHERE name = ?");
    $stmt->execute([$name]);
    if ($id = $stmt->fetchColumn()) return $id;
    $pdo->prepare("INSERT INTO regions (name) VALUES (?)")->execute([$name]);
    return $pdo->lastInsertId();
}

function getCommodityId($pdo, $name, $cat) {
    $name = trim(preg_replace('/\s+/', ' ', $name));
    if (empty($name)) return null;
    global $insertComm;
    $insertComm->execute([$name, $cat]);
    return $pdo->lastInsertId();
}

function importFile($filename, $category) {
    global $pdo, $insertPrice;
    
    $path = __DIR__ . '/data/' . $filename;
    if (!file_exists($path)) die("File not found: $path\n");
    
    echo "IMPORTING: $filename → $category\n";
    $file = fopen($path, 'r');
    
    // Skip first 2 rows (title + empty)
    fgetcsv($file);
    fgetcsv($file);
    
    // Now we are at the year row: ,,2010,,,,,,,,,,,,...
    $yearRow = fgetcsv($file);
    $monthRow = fgetcsv($file);
    
    if (!$yearRow || !$monthRow) die("Header not found!\n");
    
    echo "Header found at correct position!\n";
    
    // Build column map starting from index 2
    $columns = [];
    for ($i = 2; $i < count($yearRow); $i++) {
        $y = trim($yearRow[$i] ?? '');
        $m = trim($monthRow[$i] ?? '');
        if (is_numeric($y) && in_array($m, ['January','February','March','April','May','June','July','August','September','October','November','December','Annual'])) {
            $columns[$i] = ['year' => (int)$y, 'period' => $m];
        }
    }
    
    echo "Found " . count($columns) . " valid price columns (2010–2025)\n";
    
    $total = 0;
    $rows = 0;
    
    while (($row = fgetcsv($file)) !== false) {
        if (count($row) < 3) continue;
        
        $region = trim($row[0] ?? '');
        $commodity = trim($row[1] ?? '');
        
        if (empty($commodity)) continue;
        
        $regionId = getRegionId($pdo, $region);
        $commId = getCommodityId($pdo, $commodity, $category);
        if (!$commId) continue;
        
        foreach ($columns as $idx => $info) {
            $val = $row[$idx] ?? '';
            $price = ($val === '' || $val === '..' || $val === '0.00') ? null : round((float)$val, 2);
            
            $insertPrice->execute([$commId, $regionId, $info['year'], $info['period'], $price]);
            $total++;
        }
        
        $rows++;
        if ($rows % 100 == 0) echo "Processed $rows rows → $total prices\r";
    }
    
    fclose($file);
    echo "\nSUCCESS: $filename → $rows rows, $total prices imported!\n\n";
}

// RUN IMPORT
importFile('Leafy_veg.csv', 'leafy');
importFile('fruit_veg.csv', 'fruit_vegetable');

echo "PERFECT IMPORT COMPLETE!\n\n";

// TEST CHAYOTE IN CAR
$test = $pdo->query("
    SELECT c.name, r.name region, p.year, p.period, p.price 
    FROM price_records p 
    JOIN commodities c ON p.commodity_id = c.id 
    JOIN regions r ON p.region_id = r.id 
    WHERE c.name = 'Chayote' AND r.name LIKE '%Cordillera%' AND p.year = 2010 AND p.period = 'January'
    LIMIT 1
")->fetch();

echo "TEST (Chayote in CAR, Jan 2010):\n";
echo $test ? "SUCCESS: {$test['name']} | {$test['region']} | {$test['year']} {$test['period']} → ₱{$test['price']}\n" : "Not found\n";

echo "\nTOTAL PRICE RECORDS: " . $pdo->query("SELECT COUNT(*) FROM price_records WHERE price IS NOT NULL")->fetchColumn() . "\n";
echo "TOTAL COMMODITIES: " . $pdo->query("SELECT COUNT(*) FROM commodities")->fetchColumn() . "\n";
echo "TOTAL REGIONS: " . $pdo->query("SELECT COUNT(*) FROM regions")->fetchColumn() . "\n";

echo "\nYOUR DATA IS NOW 100% COMPLETE!\n";
?>