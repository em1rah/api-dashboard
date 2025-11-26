<?php
// index.php - FULLY DYNAMIC YEAR RANGE + COMMODITY FILTER
$pdo = new PDO("mysql:host=localhost;dbname=agri_dashboard;charset=utf8mb4", "root", "");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get filters
$fromYear = $_GET['from_year'] ?? 2010;
$toYear   = $_GET['to_year']   ?? date('Y');
$commodity = $_GET['commodity'] ?? '';
$region    = $_GET['region']    ?? 'all';

// Validate years
$fromYear = max(2010, min(2025, (int)$fromYear));
$toYear   = max($fromYear, min(2025, (int)$toYear));

// Fetch commodities & regions
$commodities = $pdo->query("SELECT DISTINCT name FROM commodities ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$regions = $pdo->query("SELECT DISTINCT name FROM regions WHERE name != '' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

// Build WHERE clause
$where = "WHERE p.year BETWEEN ? AND ? AND p.period != 'Annual' AND p.price IS NOT NULL";
$params = [$fromYear, $toYear];

if ($commodity) {
    $where .= " AND c.name = ?";
    $params[] = $commodity;
}
if ($region !== 'all') {
    $where .= " AND r.name = ?";
    $params[] = $region;
}

// Fetch filtered data for table
$dataStmt = $pdo->prepare("
    SELECT c.name as commodity, r.name as region, p.year, p.period, p.price, c.category
    FROM price_records p
    JOIN commodities c ON p.commodity_id = c.id
    JOIN regions r ON p.region_id = r.id
    $where
    ORDER BY p.year DESC, p.period, c.name
");
$dataStmt->execute($params);
$data = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch trend data for chart (yearly average per commodity)
$trendStmt = $pdo->prepare("
    SELECT p.year, AVG(p.price) as avg_price, c.name as commodity
    FROM price_records p
    JOIN commodities c ON p.commodity_id = c.id
    JOIN regions r ON p.region_id = r.id
    WHERE p.year BETWEEN ? AND ? AND p.period != 'Annual' AND p.price IS NOT NULL
    " . ($commodity ? " AND c.name = ?" : "") . "
    " . ($region !== 'all' ? " AND r.name = ?" : "") . "
    GROUP BY p.year, c.name
    ORDER BY p.year
");
$trendParams = [$fromYear, $toYear];
if ($commodity) $trendParams[] = $commodity;
if ($region !== 'all') $trendParams[] = $region;
$trendStmt->execute($trendParams);
$trendData = $trendStmt->fetchAll(PDO::FETCH_ASSOC);

// Group by commodity for chart
$chartDatasets = [];
$years = range($fromYear, $toYear);
foreach ($trendData as $row) {
    $chartDatasets[$row['commodity']][$row['year']] = round($row['avg_price'], 2);
}

// Summary stats
$totalRecords = $pdo->query("SELECT COUNT(*) FROM price_records WHERE price IS NOT NULL")->fetchColumn();
$avgCurrent = $pdo->query("SELECT AVG(price) FROM price_records WHERE year = YEAR(CURDATE()) AND period != 'Annual' AND price > 0")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Philippine Farmgate Price Analytics (2010–2025)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> -->
    <script src="assets/chart.js"></script>
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; font-family: 'Segoe UI', sans-serif; }
        .card { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .price-big { font-size: 2.8rem; font-weight: 800; }
        .chart-container { background: white; border-radius: 15px; padding: 20px; }
    </style>
</head>
<body class="text-white">
<div class="container py-5">
    <div class="text-center mb-5">
        <h1 class="display-3 fw-bold text-white">
            Philippine Farmgate Price Trends
        </h1>
        <p class="lead">Leafy & Fruit Vegetables • 2010 – <?= date('Y') ?> • PSA OpenStat</p>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card bg-white text-dark">
                <div class="card-body text-center">
                    <h5><i class="bi bi-graph-up-arrow fs-1 text-success"></i><br>Avg Price (Current Year)</h5>
                    <div class="price-big text-success">₱<?= number_format($avgCurrent ?: 0, 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-white text-dark">
                <div class="card-body text-center">
                    <h5><i class="bi bi-layers fs-1 text-primary"></i><br>Total Records</h5>
                    <div class="price-big text-primary"><?= number_format($totalRecords) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-white text-dark">
                <div class="card-body text-center">
                    <h5><i class="bi bi-calendar-range fs-1 text-warning"></i><br>Year Range</h5>
                    <div class="price-big text-warning"><?= $fromYear ?> – <?= $toYear ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTERS -->
    <div class="card bg-white text-dark mb-5">
        <div class="card-header bg-dark text-white">
            <h4><i class="bi bi-funnel-fill"></i> Filter Price Trends</h4>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label fw-bold">From Year</label>
                    <select name="from_year" class="form-select form-select-lg">
                        <?php for($y=2010; $y<=2025; $y++): ?>
                            <option value="<?= $y ?>" <?= $y==$fromYear?'selected':'' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">To Year</label>
                    <select name="to_year" class="form-select form-select-lg">
                        <?php for($y=2010; $y<=2025; $y++): ?>
                            <option value="<?= $y ?>" <?= $y==$toYear?'selected':'' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Commodity</label>
                    <select name="commodity" class="form-select form-select-lg">
                        <option value="">All Commodities</option>
                        <?php foreach($commodities as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= $c==$commodity?'selected':'' ?>><?= $c ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Region</label>
                    <select name="region" class="form-select form-select-lg">
                        <option value="all">All Regions</option>
                        <?php foreach($regions as $r): ?>
                            <option value="<?= $r ?>" <?= $r==$region?'selected':'' ?>><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-grid align-items-end">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-search"></i> Apply Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- CHART: Yearly Price Trend -->
    <div class="chart-container shadow-lg mb-5">
        <h3 class="text-center mb-4 text-dark">
            Price Trend: <?= $fromYear ?> – <?= $toYear ?>
            <?= $commodity ? " • $commodity" : "" ?>
            <?= $region !== 'all' ? " • $region" : "" ?>
        </h3>
        <canvas id="trendChart" height="120"></canvas>
    </div>

    <!-- DATA TABLE -->
    <div class="card bg-white text-dark shadow-lg">
        <div class="card-header bg-dark text-white d-flex justify-content-between">
            <h4><i class="bi bi-table"></i> Detailed Price Records</h4>
            <div>
                <button onclick="exportCSV()" class="btn btn-outline-light btn-sm">Export CSV</button>
                <button onclick="window.print()" class="btn btn-danger btn-sm">Print / PDF</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="dataTable">
                    <thead class="table-success">
                        <tr>
                            <th>Commodity</th>
                            <th>Region</th>
                            <th>Year</th>
                            <th>Period</th>
                            <th>Price (₱/kg)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($data as $row): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['commodity']) ?></strong></td>
                            <td><?= htmlspecialchars($row['region']) ?></td>
                            <td><span class="badge bg-primary"><?= $row['year'] ?></span></td>
                            <td><?= $row['period'] ?></td>
                            <td class="text-end fw-bold text-success">₱<?= number_format($row['price'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Prepare chart data
const years = <?= json_encode($years) ?>;
const datasets = [];

<?php
$colors = ['#28a745','#dc3545','#007bff','#ffc107','#6f42c1','#fd7e14','#20c997','#e83e8c','#17a2b8','#f39c12'];
$i = 0;
foreach($chartDatasets as $comm => $prices):
    $dataPoints = [];
    foreach($years as $y) {
        $dataPoints[] = $prices[$y] ?? null;
    }
?>
datasets.push({
    label: '<?= addslashes($comm) ?>',
    data: <?= json_encode($dataPoints) ?>,
    borderColor: '<?= $colors[$i % count($colors)] ?>',
    backgroundColor: '<?= $colors[$i % count($colors)] ?>40',
    fill: false,
    tension: 0.4,
    pointRadius: 5
});
<?php $i++; endforeach; ?>

new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: { labels: years, datasets: datasets },
    options: {
        responsive: true,
        plugins: {
            title: { display: false },
            legend: { position: 'bottom', labels: { font: { size: 14 } } }
        },
        scales: {
            y: { beginAtZero: true, title: { display: true, text: 'Price (₱/kg)', font: { size: 16 } } },
            x: { title: { display: true, text: 'Year', font: { size: 16 } } }
        }
    }
});

function exportCSV() {
    let csv = "Commodity,Region,Year,Period,Price\n";
    document.querySelectorAll('#dataTable tbody tr').forEach(r => {
        csv += Array.from(r.cells).map(c => c.innerText.replace(/₱|,/g,'')).join(',') + "\n";
    });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], {type: 'text/csv'}));
    a.download = `farmgate_prices_<?= $fromYear ?>_<?= $toYear ?>.csv`;
    a.click();
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>