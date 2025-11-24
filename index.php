<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PSA AgriPrice Analytics Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', sans-serif; }
        .header-title { font-weight: 800; color: #1e40af; text-shadow: 2px 2px 4px rgba(0,0,0,0.1); }
        .summary-card { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); transition: all 0.3s; }
        .summary-card:hover { transform: translateY(-10px); }
        .filter-card { border-radius: 15px; background: white; box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .chart-card { border-radius: 15px; overflow: hidden; }
        .btn-export { border-radius: 50px; padding: 12px 30px; font-weight: bold; }
        footer { margin-top: 100px; padding: 30px; background: #1e3a8a; color: white; }
    </style>
</head>
<body>
<?php require 'db.php'; ?>

<!-- HEADER -->
<div class="bg-white shadow-sm py-4 mb-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col">
                <h1 class="header-title mb-0">
                    Philippine Farmgate Price Analytics
                </h1>
                <p class="text-muted mb-0">Real-time PSA Agricultural Price Monitoring System</p>
            </div>
            <div class="col-auto">
                <img src="images/psa-logo.jpg" width="80" alt="PSA Logo">
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- SUMMARY CARDS -->
    <div class="row mb-5">
        <?php
        $avg_leafy = $pdo->query("SELECT AVG(price) FROM farmgate_prices WHERE commodity_type='leafy'")->fetchColumn();
        $avg_fruit = $pdo->query("SELECT AVG(price) FROM farmgate_prices WHERE commodity_type='fruit'")->fetchColumn();
        $total_commodities = $pdo->query("SELECT COUNT(DISTINCT commodity) FROM farmgate_prices")->fetchColumn();
        $latest_year = $pdo->query("SELECT MAX(year) FROM farmgate_prices")->fetchColumn();
        ?>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="summary-card text-white bg-success p-4">
                <div class="d-flex justify-content-between">
                    <div>
                        <h3>₱<?= number_format($avg_leafy, 2) ?></h3>
                        <p class="mb-0 opacity-90">Avg Leafy Price</p>
                    </div>
                    <i class="fas fa-leaf fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="summary-card text-white bg-warning p-4">
                <div class="d-flex justify-content-between">
                    <div>
                        <h3>₱<?= number_format($avg_fruit, 2) ?></h3>
                        <p class="mb-0 opacity-90">Avg Fruit Veg Price</p>
                    </div>
                    <i class="fas fa-apple-alt fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="summary-card text-white bg-primary p-4">
                <div class="d-flex justify-content-between">
                    <div>
                        <h3><?= $total_commodities ?></h3>
                        <p class="mb-0 opacity-90">Total Commodities</p>
                    </div>
                    <i class="fas fa-carrot fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="summary-card text-white bg-danger p-4">
                <div class="d-flex justify-content-between">
                    <div>
                        <h3><?= $latest_year ?></h3>
                        <p class="mb-0 opacity-90">Latest Data Year</p>
                    </div>
                    <i class="fas fa-calendar-alt fa-3x opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTERS + EXPORT -->
    <div class="filter-card p-4 mb-5">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-bold">From Year</label>
                <input type="number" name="year_from" class="form-control" value="<?= $_GET['year_from'] ?? 2015 ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">To Year</label>
                <input type="number" name="year_to" class="form-control" value="<?= $_GET['year_to'] ?? 2024 ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Commodity</label>
                <input type="text" name="commodity" class="form-control" placeholder="e.g. Cabbage" value="<?= $_GET['commodity'] ?? '' ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Region</label>
                <select name="region" class="form-select">
                    <option value="">All Regions</option>
                    <?php
                    $regions = $pdo->query("SELECT DISTINCT geolocation FROM farmgate_prices ORDER BY geolocation")->fetchAll();
                    foreach ($regions as $r) {
                        $sel = ($_GET['region'] ?? '') === $r['geolocation'] ? 'selected' : '';
                        echo "<option value=\"{$r['geolocation']}\" $sel>{$r['geolocation']}</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-success btn-lg w-100 btn-export">
                    <i class="fas fa-filter"></i> Apply
                </button>
            </div>
        </form>

        <div class="text-center mt-4">
            <a href="reports/generate-pdf.php?<?= http_build_query($_GET) ?>" class="btn btn-danger btn-export mx-2">
                <i class="fas fa-file-pdf"></i> Download PDF Report
            </a>
            <a href="reports/export-csv.php?<?= http_build_query($_GET) ?>" class="btn btn-primary btn-export mx-2">
                <i class="fas fa-file-csv"></i> Export to CSV
            </a>
        </div>
    </div>

    <!-- PRICE TREND CHART -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card chart-card shadow-lg">
                <div class="card-header bg-gradient bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-chart-line"></i> Price Trend Over Years</h4>
                </div>
                <div class="card-body">
                    <canvas id="priceTrendChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- RANKINGS + REGIONAL COMPARISON -->
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card chart-card shadow-lg">
                <div class="card-header bg-gradient bg-danger text-white">
                    <h5><i class="fas fa-medal"></i> Top 10 Most Expensive Commodities</h5>
                </div>
                <div class="card-body">
                    <canvas id="topCommoditiesChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="card chart-card shadow-lg">
                <div class="card-header bg-gradient bg-info text-white">
                    <h5><i class="fas fa-map-marked-alt"></i> Average Price by Region</h5>
                </div>
                <div class="card-body">
                    <canvas id="regionalChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- FOOTER -->
<footer class="text-center">
    <div class="container">
        <p class="mb-0">
            <strong>Philippine Statistics Authority (PSA) OpenSTAT Data</strong><br>
            Agricultural Farmgate Price Analytics System © 2025 | Built with PHP, MySQL & Chart.js
        </p>
    </div>
</footer>

<script>
// Main Price Trend Chart
fetch('api-chart-data.php?<?= http_build_query($_GET) ?>')
    .then(r => r.json())
    .then(d => {
        new Chart(document.getElementById('priceTrendChart'), {
            type: 'line',
            data: { labels: d.years, datasets: [{ label: 'Average Farmgate Price (₱/kg)', data: d.prices, borderColor: '#1e40af', backgroundColor: 'rgba(30, 64, 175, 0.1)', tension: 0.4, fill: true }] },
            options: { responsive: true, plugins: { legend: { position: 'top' } } }
        });
    });

// Top Commodities + Regional Charts (same as before)
fetch('api-top-commodities.php').then(r=>r.json()).then(d=>new Chart(document.getElementById('topCommoditiesChart'), {type:'bar',data:{labels:d.commodities,datasets:[{label:'Price (₱)',data:d.prices,backgroundColor:'#ef4444'}]},options:{indexAxis:'y'}}));
fetch('api-regional.php').then(r=>r.json()).then(d=>new Chart(document.getElementById('regionalChart'), {type:'bar',data:{labels:d.regions,datasets:[{label:'Avg Price',data:d.prices,backgroundColor:'#3b82f6'}]}}));
</script>
</body>
</html>