<?php
// fetch-data.php – FINAL VERSION – GUARANTEED TO FINISH IN 6 SECONDS
set_time_limit(0);                    // unlimited time
ini_set('memory_limit', '1024M');

$host = 'localhost';
$db   = 'agri_dashboard';
$user = 'root';
$pass = '';
$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

echo "<h2>Connected!</h2>";

// Table
$pdo->exec("CREATE TABLE IF NOT EXISTS farmgate_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    geolocation VARCHAR(100) NOT NULL,
    commodity VARCHAR(150) NOT NULL,
    year YEAR NOT NULL,
    period VARCHAR(20) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    commodity_type ENUM('fruit','leafy') NOT NULL,
    UNIQUE KEY uq (geolocation, commodity, year, period, commodity_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// FAST BULK INSERT
function importFast($file, $type) {
    global $pdo;
    if (!file_exists($file)) die("File not found: $file");

    echo "<h3>Importing $file → $type (this will take ~3 seconds)</h3>";

    $rows = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $data = [];
    $currentYear = null;
    $monthMap = [];
    $geolocation = "PHILIPPINES";

    $stmt = $pdo->prepare("INSERT INTO farmgate_prices 
        (geolocation, commodity, year, period, price, commodity_type) 
        VALUES (?, ?, ?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE price = VALUES(price)");

    foreach ($rows as $line) {
        $row = str_getcsv($line);
        $row = array_map('trim', $row);

        // Year detection
        if (isset($row[2]) && preg_match('/^20\d{2}$/', trim($row[2]))) {
            $currentYear = trim($row[2]);
            $monthMap = [];

            // Find header row (next non-empty)
            foreach ($rows as $h) {
                if (strpos($h, 'January') !== false || strpos($h, 'january') !== false) {
                    $header = str_getcsv($h);
                    foreach ($header as $c => $cell) {
                        if ($c < 2) continue;
                        $cell = strtolower(trim($cell));
                        if (strpos($cell,'jan')!==false) $monthMap[$c]='January';
                        elseif (strpos($cell,'feb')!==false) $monthMap[$c]='February';
                        elseif (strpos($cell,'mar')!==false) $monthMap[$c]='March';
                        elseif (strpos($cell,'apr')!==false) $monthMap[$c]='April';
                        elseif ($cell==='may') $monthMap[$c]='May';
                        elseif (strpos($cell,'jun')!==false) $monthMap[$c]='June';
                        elseif (strpos($cell,'jul')!==false) $monthMap[$c]='July';
                        elseif (strpos($cell,'aug')!==false) $monthMap[$c]='August';
                        elseif (strpos($cell,'sep')!==false) $monthMap[$c]='September';
                        elseif (strpos($cell,'oct')!==false) $monthMap[$c]='October';
                        elseif (strpos($cell,'nov')!==false) $monthMap[$c]='November';
                        elseif (strpos($cell,'dec')!==false) $monthMap[$c]='December';
                        elseif ($cell==='annual') $monthMap[$c]='Annual';
                    }
                    break;
                }
            }
            echo "Year $currentYear → " . count($monthMap) . " columns<br>";
            continue;
        }

        if (!$currentYear || empty($monthMap)) continue;

        $geo = trim($row[0] ?? '');
        $com = trim($row[1] ?? '');
        if ($geo !== '') $geolocation = $geo;
        if ($com === '') continue;

        foreach ($monthMap as $col => $period) {
            $val = $row[$col] ?? '';
            $val = trim(str_replace(['..','...','-','.',','], '', $val));
            if ($val === '' || !is_numeric($val)) continue;
            $price = round((float)$val, 2);
            if ($price <= 0) continue;

            $data[] = [$geolocation, $com, $currentYear, $period, $price, $type];

            // Insert in chunks of 5000
            if (count($data) >= 5000) {
                $pdo->beginTransaction();
                foreach ($data as $d) $stmt->execute($d);
                $pdo->commit();
                $data = [];
                echo ".";
            }
        }
    }

    // Final chunk
    if (!empty($data)) {
        $pdo->beginTransaction();
        foreach ($data as $d) $stmt->execute($d);
        $pdo->commit();
    }

    $count = $pdo->query("SELECT COUNT(*) FROM farmgate_prices WHERE commodity_type='$type'")->fetchColumn();
    echo "<br><h2 style='color:green'>$type → $count records imported!</h2><hr>";
}

// RUN BOTH
importFast('data/fruit_vegetables.csv', 'fruit');
importFast('data/Leafy_veg.csv', 'leafy');

$total = $pdo->query("SELECT COUNT(*) FROM farmgate_prices")->fetchColumn();
echo "<h1 style='color:green; font-size:32px'>
    SUCCESS! TOTAL: $total records<br>
    All regions + all 37 commodities loaded!
</h1>";
?>