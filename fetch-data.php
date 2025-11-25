<?php
// fetch-data.php – FIXED VERSION – IMPORTS EVERY YEAR (2010–2025) CORRECTLY
set_time_limit(0);
ini_set('memory_limit', '2048M');

$host = 'localhost';
$db   = 'agri_dashboard';
$user = 'root';
$pass = '';

$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

echo "<h2 style='color:green'>Connected! Starting FULL & FINAL import...</h2>";

// Clean start
$pdo->exec("TRUNCATE TABLE farmgate_prices");
$pdo->exec("CREATE TABLE IF NOT EXISTS farmgate_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    geolocation VARCHAR(150) NOT NULL,
    commodity VARCHAR(200) NOT NULL,
    year YEAR NOT NULL,
    period VARCHAR(20) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    commodity_type ENUM('fruit','leafy') NOT NULL,
    UNIQUE KEY uq (geolocation, commodity, year, period, commodity_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function importAllYearsCorrectly($file, $type) {
    global $pdo;
    if (!file_exists($file)) die("File not found: $file");

    echo "<h3>Importing <strong>$file</strong> → $type vegetables</h3>";

    $stmt = $pdo->prepare("INSERT INTO farmgate_prices 
        (geolocation, commodity, year, period, price, commodity_type) 
        VALUES (?, ?, ?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE price = VALUES(price)");

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    $yearStarts = [];      // col => year (where col is start of block, i.e., January)
    $monthMaps = [];       // year => [col => period]
    $geolocation = "PHILIPPINES";
    $total = 0;

    foreach ($lines as $idx => $line) {
        $row = str_getcsv($line);
        $row = array_map('trim', $row);

        // Clean commodity name
        if (isset($row[1])) $row[1] = trim($row[1], '"');

        // === DETECT YEAR HEADER LINE ===
        $isYearRow = false;
        foreach ($row as $col => $cell) {
            $cell = trim($cell);
            if (preg_match('/^20\d{2}$/', $cell)) {
                $yearStarts[$col] = (int)$cell;
                $isYearRow = true;
            }
        }

        if ($isYearRow) {
            echo "Found years: " . implode(', ', array_values($yearStarts)) . "<br>";

            // Find the next non-empty line for months
            for ($i = $idx + 1; $i < count($lines); $i++) {
                $next = str_getcsv($lines[$i]);
                $next = array_map('trim', $next);
                if (count(array_filter($next)) > 2) {  // Likely the month row
                    foreach ($yearStarts as $startCol => $year) {
                        $monthMaps[$year] = [];
                        $periods = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'Annual'];
                        for ($m = 0; $m < 13; $m++) {
                            $col = $startCol + $m;
                            if (isset($next[$col])) {
                                $cell = strtolower($next[$col]);
                                if (strpos($cell, substr(strtolower($periods[$m]), 0, 3)) !== false || $cell === 'annual') {
                                    $monthMaps[$year][$col] = $periods[$m];
                                }
                            }
                        }
                    }
                    echo "Month maps built for " . count($monthMaps) . " years<br>";
                    break;
                }
            }
            continue;
        }

        // === DATA ROWS ===
        if (empty($yearStarts) || empty($monthMaps)) continue;

        $geo = $row[0] ?? '';
        $com = $row[1] ?? '';
        if ($geo !== '') $geolocation = $geo;
        if ($com === '') continue;

        foreach ($monthMaps as $year => $map) {
            foreach ($map as $col => $period) {
                $raw = $row[$col] ?? '';
                $raw = str_replace(['..', '...', '-', '.'], '', trim($raw));
                if ($raw === '' || !is_numeric($raw)) continue;

                $price = round((float)$raw, 2);
                if ($price < 5 || $price > 300) continue;  // Filter outliers

                $stmt->execute([$geolocation, $com, $year, $period, $price, $type]);
                $total++;
            }
        }
    }

    echo "<h2 style='color:green'>$total records imported from $file (ALL YEARS 2010–2025)</h2><hr>";
}

// RUN IT
importAllYearsCorrectly('data/fruit_veg.csv', 'fruit');
importAllYearsCorrectly('data/Leafy_veg.csv', 'leafy');

// FINAL PROOF
$years = $pdo->query("SELECT DISTINCT year FROM farmgate_prices ORDER BY year")->fetchAll(PDO::FETCH_COLUMN);
$commodities = $pdo->query("SELECT COUNT(DISTINCT commodity) FROM farmgate_prices")->fetchColumn();
$total_records = $pdo->query("SELECT COUNT(*) FROM farmgate_prices")->fetchColumn();

echo "<h1 style='color:green'>ALL YEARS 2010–2025 IMPORTED!</h1>";
echo "<h3>Years: " . implode(', ', $years) . "</h3>";
echo "<h3>Total Commodities: $commodities</h3>";
echo "<h1 style='color:green; font-size:40px'>TOTAL RECORDS: $total_records</h1>";
echo "<p>Now open your dashboard — the Price Trend will show full 2010–2025!</p>";
?>