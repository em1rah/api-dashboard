<?php
try {
    $pdo = new PDO(
        "mysql:host=sql300.infinityfree.com;dbname=if0_40530383_agri_dashboard;charset=utf8mb4",
        "if0_40530383",
        "3x1gprkc"   // ← your real password
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$fromYear  = max(2010, min(2025, (int)($_GET['from_year'] ?? 2010)));
$toYear    = max($fromYear, min(2025, (int)($_GET['to_year'] ?? date('Y'))));
$commodity = trim($_GET['commodity'] ?? '');
$region    = $_GET['region'] ?? 'all';

$commodities = $pdo->query("SELECT DISTINCT name FROM commodities ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$regions = $pdo->query("SELECT DISTINCT name FROM regions ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

$leafyAvg = $pdo->query("SELECT COALESCE(ROUND(AVG(price),2),0) FROM price_records p JOIN commodities c ON p.commodity_id=c.id WHERE c.category='leafy' AND p.year=YEAR(CURDATE()) AND p.period!='Annual' AND price>0")->fetchColumn();
$fruitAvg = $pdo->query("SELECT COALESCE(ROUND(AVG(price),2),0) FROM price_records p JOIN commodities c ON p.commodity_id=c.id WHERE c.category='fruit_vegetable' AND p.year=YEAR(CURDATE()) AND p.period!='Annual' AND price>0")->fetchColumn();

$trendSql = "SELECT p.year, c.name commodity, ROUND(AVG(p.price),2) avg_price FROM price_records p JOIN commodities c ON p.commodity_id=c.id JOIN regions r ON p.region_id=r.id WHERE p.year BETWEEN ? AND ? AND p.period!='Annual' AND p.price IS NOT NULL";
$params = [$fromYear, $toYear];
if ($commodity) { $trendSql .= " AND c.name=?"; $params[] = $commodity; }
if ($region !== 'all') { $trendSql .= " AND r.name=?"; $params[] = $region; }
$trendSql .= " GROUP BY p.year, c.name ORDER BY c.name, p.year";
$trendStmt = $pdo->prepare($trendSql);
$trendStmt->execute($params);
$rawTrend = $trendStmt->fetchAll();

$years = range($fromYear, $toYear);
$commData = [];
foreach ($rawTrend as $r) $commData[$r['commodity']][$r['year']] = (float)$r['avg_price'];

$datasets = [];
$colors = ['#2d6a4f','#e76f51','#2a9d8f','#264653','#8ac926','#4361ee','#7209b7','#06d6a0'];
$i = 0;
foreach ($commData as $c => $prices) {
    $color = $colors[$i % count($colors)];
    $data = array_map(fn($y) => $prices[$y] ?? null, $years);
    $datasets[] = [
        'label' => $c,
        'data' => $data,
        'borderColor' => $color,
        'backgroundColor' => $color . '40',
        'fill' => true,
        'tension' => 0.3,
        'pointRadius' => 4
    ];
    $i++;
}

// Top 10, Regional, Rankings 
$topStmt = $pdo->prepare("SELECT c.name, ROUND(AVG(p.price),2) avg_price FROM price_records p JOIN commodities c ON p.commodity_id=c.id WHERE p.year BETWEEN ? AND ? AND p.period!='Annual' AND p.price>0 " . ($region!=='all' ? "AND EXISTS(SELECT 1 FROM price_records p2 JOIN regions r ON p2.region_id=r.id WHERE p2.commodity_id=p.commodity_id AND r.name=?) " : "") . "GROUP BY c.name ORDER BY avg_price DESC LIMIT 10");
$topParams = [$fromYear, $toYear]; if($region!=='all') $topParams[] = $region;
$topStmt->execute($topParams);
$topCommodities = $topStmt->fetchAll();

$regStmt = $pdo->prepare("SELECT r.name region, ROUND(AVG(p.price),2) avg_price FROM price_records p JOIN regions r ON p.region_id=r.id WHERE p.year BETWEEN ? AND ? AND p.period!='Annual' AND p.price>0 " . ($commodity ? "AND EXISTS(SELECT 1 FROM price_records p2 JOIN commodities c ON p2.commodity_id=c.id WHERE p2.region_id=p.region_id AND c.name=?) " : "") . "GROUP BY r.name ORDER BY avg_price DESC");
$regParams = [$fromYear, $toYear]; if($commodity) $regParams[] = $commodity;
$regStmt->execute($regParams);
$regionData = $regStmt->fetchAll();

$rankStmt = $pdo->prepare("SELECT c.name commodity, ROUND(AVG(p.price),2) avg_price FROM price_records p JOIN commodities c ON p.commodity_id=c.id WHERE p.year BETWEEN ? AND ? AND p.period!='Annual' GROUP BY c.name ORDER BY avg_price DESC");
$rankStmt->execute([$fromYear, $toYear]);
$rankings = $rankStmt->fetchAll();

// Check if we have data to display
$hasData = !empty($datasets);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmgate Prices • Philippines</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background:#f8f9fa; font-family: system-ui, sans-serif; color: #333; }
        .card { border:none; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,0.08); }
        h1, h4 { color:#2d6a4f; }
        .price-big { font-size:3.5rem; font-weight:800; color:#2d6a4f; }
        .filter-box { background:white; padding:1.5rem; border-radius:16px; box-shadow:0 4px 15px rgba(0,0,0,0.06); }
        
       
        .chart-wrapper { position:relative; height:480px; width:100%; }
        .no-data-message { min-height: 480px; display: flex; align-items: center; justify-content: center; }
        .chart-wrapper canvas { position:absolute; top:0; left:0; width:100% !important; height:100% !important; }
        
        .btn-primary { background:#2d6a4f; border:none; border-radius:12px; padding:10px 24px; }
        .table th { background:#2d6a4f; color:white; }
    </style>
</head>
<body class="pb-5">

<div class="container py-5">
    <div class="text-center mb-5">
        <h1 class="display-5 fw-bold">Philippine Farmgate Prices</h1>
       
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-6">
            <div class="card p-4 text-center">
                <h5 class="text-success">Leafy Vegetables (<?= date('Y') ?>)</h5>
                <div class="price-big">₱<?= number_format($leafyAvg,2) ?></div>
                <small class="text-muted">average per kg</small>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-4 text-center">
                <h5 class="text-success">Fruit Vegetables (<?= date('Y') ?>)</h5>
                <div class="price-big">₱<?= number_format($fruitAvg,2) ?></div>
                <small class="text-muted">average per kg</small>
            </div>
        </div>
    </div>

    <!-- Simple Filters -->
    <div class="filter-box mb-5">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label fw-bold">From</label>
                <select name="from_year" class="form-select">
                    <?php for($y=2010;$y<=2025;$y++): ?>
                        <option value="<?= $y ?>" <?= $y==$fromYear?'selected':'' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">To</label>
                <select name="to_year" class="form-select">
                    <?php for($y=2010;$y<=2025;$y++): ?>
                        <option value="<?= $y ?>" <?= $y==$toYear?'selected':'' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Commodity</label>
                <select name="commodity" id="commodityFilter" class="form-select">
                    <option value="">All Commodities</option>
                    <?php foreach($commodities as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= $c==$commodity?'selected':'' ?>><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Region</label>
                <select name="region" id="regionFilter" class="form-select">
                    <option value="all">All Regions</option>
                    <?php foreach($regions as $r): ?>
                        <option value="<?= htmlspecialchars($r) ?>" <?= $r==$region?'selected':'' ?>><?= htmlspecialchars($r) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100">Update</button>
            </div>
        </form>
    </div>

    <!-- Charts Section -->
    <?php if ($hasData): ?>
    <div class="row g-4">
        <!-- Price Trend -->
        <div class="col-lg-8">
            <div class="card p-4">
                <h4 class="mb-4">Price Trend (<?= $fromYear ?>–<?= $toYear ?>)</h4>
                <div class="chart-wrapper">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Top 10 -->
        <div class="col-lg-4">
            <div class="card p-4">
                <h4 class="mb-4">Top 10 Most Expensive</h4>
                <div class="chart-wrapper">
                    <canvas id="topChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Regional -->
        <div class="col-lg-6">
            <div class="card p-4">
                <h4 class="mb-4">Average Price by Region</h4>
                <div class="chart-wrapper" style="height:400px;">
                    <canvas id="regionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Rankings Table -->
        <div class="col-lg-6">
            <div class="card p-0 overflow-hidden">
                <div class="p-4 bg-success text-white">
                    <h4 class="mb-0">Full Price Rankings</h4>
                </div>
                <div class="table-responsive" style="max-height:400px;">
                    <table class="table table-striped mb-0">
                        <thead class="table-dark">
                            <tr><th>#</th><th>Commodity</th><th class="text-end">Price</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($rankings as $i => $row): ?>
                            <tr>
                                <td><strong><?= $i+1 ?></strong></td>
                                <td><?= htmlspecialchars($row['commodity']) ?></td>
                                <td class="text-end fw-bold">₱<?= number_format($row['avg_price'],2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center my-5">
        <button onclick="exportCSV()" class="btn btn-success btn-lg px-5 me-3">Export CSV</button>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-lg px-5">Print</button>
    </div>
    
    <?php else: ?>
    <!-- No Data Message -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="no-data-message">
                    <div class="text-center p-5">
                        <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 4rem;"></i>
                        <h3 class="mt-4 text-muted">No Available Data</h3>
                        <p class="text-muted">No price records found for the selected filters. Try adjusting your selection.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// Simple clean charts
<?php if ($hasData): ?>
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: { labels: <?= json_encode($years) ?>, datasets: <?= json_encode($datasets) ?> },
    options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'top'}} }
});

new Chart(document.getElementById('topChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($topCommodities,'name')) ?>,
        datasets: [{ label:'₱/kg', data: <?= json_encode(array_column($topCommodities,'avg_price')) ?>, backgroundColor:'#2d6a4f' }]
    },
    options: { indexAxis:'y', responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}} }
});

new Chart(document.getElementById('regionChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($regionData,'region')) ?>,
        datasets: [{ data: <?= json_encode(array_column($regionData,'avg_price')) ?>, backgroundColor: <?= json_encode($colors) ?> }]
    },
    options: { responsive:true, maintainAspectRatio:false }
});

function exportCSV() {
    let csv = "Rank,Commodity,Price\n";
    <?php foreach($rankings as $i => $row): ?>
        csv += "<?= $i+1 ?>,<?= addslashes($row['commodity']) ?>,<?= $row['avg_price'] ?>\n";
    <?php endforeach; ?>
    const a = document.createElement('a');
    a.href = 'data:text/csv,' + encodeURIComponent(csv);
    a.download = 'farmgate_prices_<?= $fromYear ?>_<?= $toYear ?>.csv';
    a.click();
}
<?php endif; ?>

// Dynamic filter functionality
let isUpdating = false;

function updateFilters(triggeredBy) {
    if (isUpdating) return;
    
    const regionSelect = document.getElementById('regionFilter');
    const commoditySelect = document.getElementById('commodityFilter');
    
    isUpdating = true;

    if (triggeredBy === 'region') {
        const region = regionSelect.value;
        fetch(`api-filter-commodities.php?region=${encodeURIComponent(region)}`)
            .then(r => r.json())
            .then(data => {
                const currentCommodity = commoditySelect.value;
                commoditySelect.innerHTML = '<option value="">All Commodities</option>';
                data.commodities.forEach(commodity => {
                    const option = new Option(commodity, commodity);
                    if (commodity === currentCommodity) option.selected = true;
                    commoditySelect.add(option);
                });
                isUpdating = false;
            });
    } else if (triggeredBy === 'commodity') {
        const commodity = commoditySelect.value;
        fetch(`api-filter-regions.php?commodity=${encodeURIComponent(commodity)}`)
            .then(r => r.json())
            .then(data => {
                const currentRegion = regionSelect.value;
                regionSelect.innerHTML = '<option value="all">All Regions</option>';
                data.regions.forEach(region => {
                    const option = new Option(region, region);
                    if (region === currentRegion) option.selected = true;
                    regionSelect.add(option);
                });
                isUpdating = false;
            });
    }
}

// Add event listeners
document.getElementById('regionFilter').addEventListener('change', () => updateFilters('region'));
document.getElementById('commodityFilter').addEventListener('change', () => updateFilters('commodity'));
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>