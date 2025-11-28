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

// $pdo = new PDO("mysql:host=localhost;dbname=agri_dashboard;charset=utf8mb4", "root", "");
// $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


$fromYear  = max(2010, min(2025, (int)($_GET['from_year'] ?? 2010)));
$toYear    = max($fromYear, min(2025, (int)($_GET['to_year'] ?? date('Y'))));
$commodity = trim($_GET['commodity'] ?? '');
$region    = $_GET['region'] ?? 'all';

// Dropdown options
$commodities = $pdo->query("SELECT DISTINCT name FROM commodities ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$regions = $pdo->query("SELECT DISTINCT name FROM regions ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

// Summary Cards (Current Year Averages)
$leafyAvg = $pdo->query("SELECT COALESCE(ROUND(AVG(price),2),0) FROM price_records p JOIN commodities c ON p.commodity_id=c.id WHERE c.category='leafy' AND p.year=YEAR(CURDATE()) AND p.period!='Annual' AND price>0")->fetchColumn();
$fruitAvg = $pdo->query("SELECT COALESCE(ROUND(AVG(price),2),0) FROM price_records p JOIN commodities c ON p.commodity_id=c.id WHERE c.category='fruit_vegetable' AND p.year=YEAR(CURDATE()) AND p.period!='Annual' AND p.price>0")->fetchColumn();

// Price Trend (existing)
$trendSql = "SELECT p.year, c.name commodity, ROUND(AVG(p.price),2) avg_price 
             FROM price_records p 
             JOIN commodities c ON p.commodity_id=c.id 
             JOIN regions r ON p.region_id=r.id 
             WHERE p.year BETWEEN ? AND ? AND p.period!='Annual' AND p.price>0";
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
$colors = ['#2d6a4f','#e76f51','#2a9d8f','#264653','#8ac926','#4361ee','#7209b7','#06d6a0','#f4a261','#9b2226'];
$i = 0;
foreach ($commData as $c => $prices) {
    $color = $colors[$i % count($colors)];
    $data = array_map(fn($y) => $prices[$y] ?? 0, $years);  // FIX: Use 0 instead of null
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

// Activity Trend (Proxy for "Production Trends")
$activitySql = "SELECT p.year, COUNT(*) as transactions 
                FROM price_records p 
                JOIN commodities c ON p.commodity_id=c.id 
                JOIN regions r ON p.region_id=r.id 
                WHERE p.year BETWEEN ? AND ? AND p.period!='Annual' AND p.price>0";
$actParams = [$fromYear, $toYear];
if ($commodity) { $activitySql .= " AND c.name=?"; $actParams[] = $commodity; }
if ($region !== 'all') { $activitySql .= " AND r.name=?"; $actParams[] = $region; }
$activitySql .= " GROUP BY p.year ORDER BY p.year";
$actStmt = $pdo->prepare($activitySql);
$actStmt->execute($actParams);
$activityData = $actStmt->fetchAll();

$activityYears = array_column($activityData, 'year');
$activityValues = array_column($activityData, 'transactions');

// Regional Activity (Proxy for "Regional Agricultural Output")
$regionActivitySql = "SELECT r.name region, COUNT(*) as records 
                      FROM price_records p 
                      JOIN regions r ON p.region_id=r.id 
                      JOIN commodities c ON p.commodity_id=c.id 
                      WHERE p.year BETWEEN ? AND ? AND p.period!='Annual' AND p.price>0";
$regActParams = [$fromYear, $toYear];
if ($commodity) { $regionActivitySql .= " AND c.name=?"; $regActParams[] = $commodity; }
$regionActivitySql .= " GROUP BY r.name ORDER BY records DESC";
$regActStmt = $pdo->prepare($regionActivitySql);
$regActStmt->execute($regActParams);
$regionActivity = $regActStmt->fetchAll();

// Top 10 Most Expensive - FIXED: Always shows top 10 from latest year, ignoring filters
$latestYear = $pdo->query("SELECT MAX(year) FROM price_records WHERE price > 0 AND period != 'Annual'")->fetchColumn();
$topStmt = $pdo->prepare("SELECT c.name, ROUND(AVG(p.price),2) avg_price 
                          FROM price_records p 
                          JOIN commodities c ON p.commodity_id=c.id 
                          WHERE p.year = ? AND p.period != 'Annual' AND p.price > 0 
                          GROUP BY c.name 
                          ORDER BY avg_price DESC 
                          LIMIT 10");
$topStmt->execute([$latestYear]);
$topCommodities = $topStmt->fetchAll();

// Regional Prices
$regPriceStmt = $pdo->prepare("SELECT r.name region, ROUND(AVG(p.price),2) avg_price FROM price_records p JOIN regions r ON p.region_id=r.id WHERE p.year BETWEEN ? AND ? AND p.period!='Annual' AND p.price>0 " . ($commodity ? "AND EXISTS(SELECT 1 FROM price_records p2 JOIN commodities c ON p2.commodity_id=c.id WHERE p2.region_id=p.region_id AND c.name=?)" : "") . " GROUP BY r.name ORDER BY avg_price DESC");
$regPriceParams = [$fromYear, $toYear]; if($commodity) $regPriceParams[] = $commodity;
$regPriceStmt->execute($regPriceParams);
$regionPrices = $regPriceStmt->fetchAll();

// Rankings
$rankStmt = $pdo->prepare("SELECT c.name commodity, ROUND(AVG(p.price),2) avg_price FROM price_records p JOIN commodities c ON p.commodity_id=c.id WHERE p.year BETWEEN ? AND ? AND p.period!='Annual' AND p.price>0 GROUP BY c.name ORDER BY avg_price DESC");
$rankStmt->execute([$fromYear, $toYear]);
$rankings = $rankStmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Philippine Farmgate Prices Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        body { background:#f8f9fa; font-family: system-ui, sans-serif; color: #333; }
        .card { border:none; border-radius:16px; box-shadow:0 4px 20px rgba(0,0,0,0.08); }
        h1, h4 { color:#2d6a4f; }
        .price-big { font-size:3.5rem; font-weight:800; color:#2d6a4f; }
        .filter-box { background:white; padding:1.5rem; border-radius:16px; box-shadow:0 4px 15px rgba(0,0,0,0.06); }
        .chart-wrapper { position:relative; height:420px; width:100%; }
        .chart-wrapper canvas { position:absolute; top:0; left:0; width:100% !important; height:100% !important; }
        .btn-primary { background:#2d6a4f; border:none; }
        .btn-success { background:#1a5d3a; border:none; }
        .chart-actions { margin-top: 1rem; }
    </style>
</head>
<body class="pb-5">

<div class="container py-5" id="reportContent">
    <div class="text-center mb-5">
        <h1 class="display-5 fw-bold">Philippine Farmgate Prices Dashboard</h1>
        <p class="lead">Real-time Agricultural Price Monitoring (<?= $fromYear ?>–<?= $toYear ?>)</p>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-5">
        <div class="col-md-6">
            <div class="card p-4 text-center">
                <h5 class="text-success">Leafy Vegetables (<?= date('Y') ?>)</h5>
                <div class="price-big">₱<?= number_format($leafyAvg,2) ?></div>
                <small class="text-muted">avg farmgate price per kg</small>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-4 text-center">
                <h5 class="text-success">Fruit Vegetables (<?= date('Y') ?>)</h5>
                <div class="price-big">₱<?= number_format($fruitAvg,2) ?></div>
                <small class="text-muted">avg farmgate price per kg</small>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-box mb-5">
        <form method="GET" class="row g-3 align-items-end">
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
            <div class="col-md-3">
                <label class="form-label fw-bold">Commodity</label>
                <select name="commodity" class="form-select">
                    <option value="">All Commodities</option>
                    <?php foreach($commodities as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= $c==$commodity?'selected':'' ?>><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Region</label>
                <select name="region" class="form-select">
                    <option value="all">All Regions</option>
                    <?php foreach($regions as $r): ?>
                        <option value="<?= htmlspecialchars($r) ?>" <?= $r==$region?'selected':'' ?>><?= htmlspecialchars($r) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100">Filter</button>
            </div>
        </form>
    </div>

    <!-- Charts Grid -->
    <div class="row g-4 mb-5">
        <!-- Price Trends -->
        <div class="">
            <div class="card p-4">
                <h4>Price Trends Over Years</h4>
                <?php if (!empty($datasets)): ?>
                    <div class="chart-wrapper"><canvas id="priceTrendChart"></canvas></div>
                    <div class="chart-actions text-center">
                        <button onclick="exportChartCSV('priceTrend')" class="btn btn-sm btn-success me-2">Export CSV</button>
                        <button onclick="exportChartPDF('priceTrendChart', 'Price Trends')" class="btn btn-sm btn-danger">Export PDF</button>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size:3rem;"></i>
                        <p class="text-muted mt-3">No price trend data for selected filters</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Activity Trends -->
        <div class="">
            <div class="card p-4">
                <h4>Market Activity Trends (No. of Records)</h4>
                <?php if (!empty($activityData)): ?>
                    <div class="chart-wrapper"><canvas id="activityChart"></canvas></div>
                    <small class="text-muted text-center d-block mt-2">Higher = more active trading/reporting</small>
                    <div class="chart-actions text-center">
                        <button onclick="exportChartCSV('activity')" class="btn btn-sm btn-success me-2">Export CSV</button>
                        <button onclick="exportChartPDF('activityChart', 'Market Activity')" class="btn btn-sm btn-danger">Export PDF</button>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size:3rem;"></i>
                        <p class="text-muted mt-3">No activity data for selected filters</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top 10 Most Expensive -->
        <div class="col-lg-6">
            <div class="card p-4">
                <h4>Top 10 Most Expensive Commodities</h4>
                <?php if (!empty($topCommodities)): ?>
                    <div class="chart-wrapper"><canvas id="topChart"></canvas></div>
                    <div class="chart-actions text-center">
                        <button onclick="exportChartCSV('top10')" class="btn btn-sm btn-success me-2">Export CSV</button>
                        <button onclick="exportChartPDF('topChart', 'Top 10 Commodities')" class="btn btn-sm btn-danger">Export PDF</button>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size:3rem;"></i>
                        <p class="text-muted mt-3">No top commodities data</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Regional Activity -->
        <div class="col-lg-6">
            <div class="card p-4">
                <h4>Regional Market Activity</h4>
                <?php if (!empty($regionActivity)): ?>
                    <div class="chart-wrapper"><canvas id="regionActivityChart"></canvas></div>
                    <div class="chart-actions text-center">
                        <button onclick="exportChartCSV('regionalActivity')" class="btn btn-sm btn-success me-2">Export CSV</button>
                        <button onclick="exportChartPDF('regionActivityChart', 'Regional Activity')" class="btn btn-sm btn-danger">Export PDF</button>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size:3rem;"></i>
                        <p class="text-muted mt-3">No regional activity data for selected filters</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Average Price by Region -->
        <div class="col-lg-6">
            <div class="card p-4">
                <h4>Average Price by Region</h4>
                <?php if (!empty($regionPrices)): ?>
                    <div class="chart-wrapper"><canvas id="regionPriceChart"></canvas></div>
                    <div class="chart-actions text-center">
                        <button onclick="exportChartCSV('regionalPrices')" class="btn btn-sm btn-success me-2">Export CSV</button>
                        <button onclick="exportChartPDF('regionPriceChart', 'Regional Prices')" class="btn btn-sm btn-danger">Export PDF</button>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size:3rem;"></i>
                        <p class="text-muted mt-3">No regional price data for selected filters</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Rankings Table -->
        <div class="col-lg-6">
            <div class="card p-0 overflow-hidden">
                <div class="p-4 bg-success text-white"><h4 class="mb-0">Commodity Price Rankings</h4></div>
                <?php if (!empty($rankings)): ?>
                    <div class="table-responsive" style="max-height:420px;">
                        <table class="table table-striped mb-0">
                            <thead class="table-dark sticky-top">
                                <tr><th>#</th><th>Commodity</th><th class="text-end">Avg Price</th></tr>
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
                    <div class="chart-actions text-center p-3">
                        <button onclick="exportChartCSV('rankings')" class="btn btn-sm btn-success me-2">Export CSV</button>
                        <button onclick="exportTablePDF()" class="btn btn-sm btn-danger">Export PDF</button>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size:3rem;"></i>
                        <p class="text-muted mt-3">No ranking data for selected filters</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// ============================================
// CASCADING FILTER LOGIC
// ============================================

const commoditySelect = document.querySelector('select[name="commodity"]');
const regionSelect = document.querySelector('select[name="region"]');

function setLoading(select, isLoading) {
    select.disabled = isLoading;
    select.style.opacity = isLoading ? '0.6' : '1';
}

async function updateRegions() {
    const commodity = commoditySelect.value;
    const currentRegion = regionSelect.value;
    setLoading(regionSelect, true);
    
    try {
        const url = commodity 
            ? `api-filter-regions.php?commodity=${encodeURIComponent(commodity)}`
            : 'api-filter-regions.php';
        
        const response = await fetch(url);
        const data = await response.json();
        
        regionSelect.innerHTML = '<option value="all">All Regions</option>';
        data.regions.forEach(region => {
            const option = document.createElement('option');
            option.value = region;
            option.textContent = region;
            option.selected = region === currentRegion;
            regionSelect.appendChild(option);
        });
    } catch (error) {
        console.error('Error updating regions:', error);
    } finally {
        setLoading(regionSelect, false);
    }
}

async function updateCommodities() {
    const region = regionSelect.value;
    const currentCommodity = commoditySelect.value;
    setLoading(commoditySelect, true);
    
    try {
        const url = region && region !== 'all'
            ? `api-filter-commodities.php?region=${encodeURIComponent(region)}`
            : 'api-filter-commodities.php';
        
        const response = await fetch(url);
        const data = await response.json();
        
        commoditySelect.innerHTML = '<option value="">All Commodities</option>';
        data.commodities.forEach(commodity => {
            const option = document.createElement('option');
            option.value = commodity;
            option.textContent = commodity;
            option.selected = commodity === currentCommodity;
            commoditySelect.appendChild(option);
        });
    } catch (error) {
        console.error('Error updating commodities:', error);
    } finally {
        setLoading(commoditySelect, false);
    }
}

commoditySelect.addEventListener('change', updateRegions);
regionSelect.addEventListener('change', updateCommodities);

// ============================================
// CHART INITIALIZATION
// ============================================

<?php if (!empty($datasets)): ?>
new Chart(document.getElementById('priceTrendChart'), {
    type: 'line',
    data: { labels: <?= json_encode($years) ?>, datasets: <?= json_encode($datasets) ?> },
    options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'top'}} }
});
<?php endif; ?>

<?php if (!empty($activityData)): ?>
new Chart(document.getElementById('activityChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($activityYears) ?>,
        datasets: [{
            label: 'Number of Price Records',
            data: <?= json_encode($activityValues) ?>,
            borderColor: '#2d6a4f',
            backgroundColor: '#2d6a4f40',
            fill: true,
            tension: 0.3
        }]
    },
    options: { responsive:true, maintainAspectRatio:false }
});
<?php endif; ?>

<?php if (!empty($topCommodities)): ?>
new Chart(document.getElementById('topChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($topCommodities,'name')) ?>,
        datasets: [{ label:'₱/kg', data: <?= json_encode(array_column($topCommodities,'avg_price')) ?>, backgroundColor:'#e76f51' }]
    },
    options: { indexAxis:'y', responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}} }
});
<?php endif; ?>

<?php if (!empty($regionActivity)): ?>
new Chart(document.getElementById('regionActivityChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($regionActivity,'region')) ?>,
        datasets: [{ data: <?= json_encode(array_column($regionActivity,'records')) ?>, backgroundColor: <?= json_encode($colors) ?> }]
    },
    options: { responsive:true, maintainAspectRatio:false }
});
<?php endif; ?>

<?php if (!empty($regionPrices)): ?>
new Chart(document.getElementById('regionPriceChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($regionPrices,'region')) ?>,
        datasets: [{ label:'Avg Price (₱/kg)', data: <?= json_encode(array_column($regionPrices,'avg_price')) ?>, backgroundColor:'#2d6a4f' }]
    },
    options: { responsive:true, maintainAspectRatio:false }
});
<?php endif; ?>

// ============================================
// CHART-SPECIFIC EXPORT FUNCTIONS
// ============================================

// Price Trends CSV data
const priceTrendData = {
    years: <?= json_encode($years) ?>,
    commodities: <?= json_encode(array_keys($commData)) ?>,
    data: <?= json_encode($commData) ?>
};

// Activity CSV data
const activityData = {
    years: <?= json_encode($activityYears) ?>,
    values: <?= json_encode($activityValues) ?>
};

// Top 10 CSV data
const top10Data = {
    commodities: <?= json_encode(array_column($topCommodities,'name')) ?>,
    prices: <?= json_encode(array_column($topCommodities,'avg_price')) ?>
};

// Regional Activity CSV data
const regionalActivityData = {
    regions: <?= json_encode(array_column($regionActivity,'region')) ?>,
    records: <?= json_encode(array_column($regionActivity,'records')) ?>
};

// Regional Prices CSV data
const regionalPricesData = {
    regions: <?= json_encode(array_column($regionPrices,'region')) ?>,
    prices: <?= json_encode(array_column($regionPrices,'avg_price')) ?>
};

// Rankings CSV data
const rankingsData = {
    rankings: <?= json_encode($rankings) ?>
};

// Generic CSV export function
function exportChartCSV(chartType) {
    let csv = '';
    let filename = '';
    
    switch(chartType) {
        case 'priceTrend':
            filename = 'price_trends_<?= $fromYear ?>_<?= $toYear ?>.csv';
            csv = 'Year,' + priceTrendData.commodities.join(',') + '\n';
            priceTrendData.years.forEach(year => {
                let row = year;
                priceTrendData.commodities.forEach(commodity => {
                    row += ',' + (priceTrendData.data[commodity][year] ?? 0);
                });
                csv += row + '\n';
            });
            break;
            
        case 'activity':
            filename = 'market_activity_<?= $fromYear ?>_<?= $toYear ?>.csv';
            csv = 'Year,Number of Records\n';
            activityData.years.forEach((year, i) => {
                csv += year + ',' + activityData.values[i] + '\n';
            });
            break;
            
        case 'top10':
            filename = 'top10_commodities_<?= $fromYear ?>_<?= $toYear ?>.csv';
            csv = 'Rank,Commodity,Price (₱/kg)\n';
            top10Data.commodities.forEach((comm, i) => {
                csv += (i+1) + ',"' + comm + '",' + top10Data.prices[i] + '\n';
            });
            break;
            
        case 'regionalActivity':
            filename = 'regional_activity_<?= $fromYear ?>_<?= $toYear ?>.csv';
            csv = 'Region,Number of Records\n';
            regionalActivityData.regions.forEach((region, i) => {
                csv += '"' + region + '",' + regionalActivityData.records[i] + '\n';
            });
            break;
            
        case 'regionalPrices':
            filename = 'regional_prices_<?= $fromYear ?>_<?= $toYear ?>.csv';
            csv = 'Region,Average Price (₱/kg)\n';
            regionalPricesData.regions.forEach((region, i) => {
                csv += '"' + region + '",' + regionalPricesData.prices[i] + '\n';
            });
            break;
            
        case 'rankings':
            filename = 'commodity_rankings_<?= $fromYear ?>_<?= $toYear ?>.csv';
            csv = 'Rank,Commodity,Average Price (₱/kg)\n';
            rankingsData.rankings.forEach((row, i) => {
                csv += (i+1) + ',"' + row.commodity + '",' + row.avg_price + '\n';
            });
            break;
    }
    
    const a = document.createElement('a');
    a.href = 'data:text/csv,' + encodeURIComponent(csv);
    a.download = filename;
    a.click();
}

// Generic PDF export function
async function exportChartPDF(canvasId, title) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF('l', 'mm', 'a4');
    
    // Add title
    pdf.setFontSize(16);
    pdf.text(title, 20, 20);
    
    // Add chart
    const imgData = canvas.toDataURL('image/png');
    const imgWidth = 250;
    const imgHeight = (canvas.height * imgWidth) / canvas.width;
    pdf.addImage(imgData, 'PNG', 20, 30, imgWidth, imgHeight);
    
    // Add timestamp
    pdf.setFontSize(10);
    pdf.text('Generated: ' + new Date().toLocaleString(), 20, 200);
    
    pdf.save(title.replace(/\s+/g, '_') + '_<?= $fromYear ?>-<?= $toYear ?>.pdf');
}

// Special PDF export for rankings table
async function exportTablePDF() {
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF('p', 'mm', 'a4');
    
    pdf.setFontSize(16);
    pdf.text('Commodity Price Rankings', 20, 20);
    
    let y = 40;
    pdf.setFontSize(10);
    
    // Table headers
    pdf.text('Rank', 20, y);
    pdf.text('Commodity', 40, y);
    pdf.text('Price (₱/kg)', 150, y);
    y += 10;
    
    // Table rows
    rankingsData.rankings.forEach((row, i) => {
        if (y > 270) {
            pdf.addPage();
            y = 20;
        }
        pdf.text((i+1).toString(), 20, y);
        pdf.text(row.commodity, 40, y);
        pdf.text(row.avg_price.toString(), 150, y);
        y += 8;
    });
    
    pdf.text('Generated: ' + new Date().toLocaleString(), 20, 280);
    
    pdf.save('commodity_rankings_<?= $fromYear ?>-<?= $toYear ?>.pdf');
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>