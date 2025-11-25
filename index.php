<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PSA AgriPrice Analytics Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; font-family: 'Segoe UI', sans-serif; }
        .header-title { font-weight: 800; color: #1e40af; text-shadow: 2px 2px 4px rgba(0,0,0,0.1); }
        .summary-card { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); transition: all 0.3s; }
        .summary-card:hover { transform: translateY(-10px); }
        .filter-card { border-radius: 15px; background: white; box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .chart-card { border-radius: 15px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.12); }
        .btn-export { border-radius: 50px; padding: 12px 30px; font-weight: bold; }
        footer { margin-top: 100px; padding: 40px; background: #1e3a8a; color: white; }
        .select2-container--bootstrap-5 .select2-selection { border-radius: 10px; height: 48px; padding-top: 8px; }
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
               
            </div>
            <div class="col-auto">
                <img src="images/psa-logo.png" width="150" alt="PSA Logo">
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- SUMMARY CARDS -->
    <div class="row mb-5">
        <?php
        $avg_leafy = $pdo->query("SELECT AVG(price) FROM farmgate_prices WHERE commodity_type='leafy'")->fetchColumn() ?: 0;
        $avg_fruit = $pdo->query("SELECT AVG(price) FROM farmgate_prices WHERE commodity_type='fruit'")->fetchColumn() ?: 0;
        $total_commodities = $pdo->query("SELECT COUNT(DISTINCT commodity) FROM farmgate_prices")->fetchColumn();
        $latest_year = $pdo->query("SELECT MAX(year) FROM farmgate_prices")->fetchColumn();
        ?>
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="summary-card text-white bg-success p-4">
                <div class="d-flex justify-content-between align-items-center">
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
                <div class="d-flex justify-content-between align-items-center">
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
                <div class="d-flex justify-content-between align-items-center">
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
                <div class="d-flex justify-content-between align-items-center">
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
                <input type="number" name="year_from" class="form-control" value="<?= $_GET['year_from'] ?? '2015' ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">To Year</label>
                <input type="number" name="year_to" class="form-control" value="<?= $_GET['year_to'] ?? '2024' ?>">
            </div>

            <!-- SEARCHABLE COMMODITY DROPDOWN -->
            <div class="col-md-4">
                <label class="form-label fw-bold">Commodity</label>
                <select name="commodity" class="form-select" id="commoditySelect">
                    <option value="">All Commodities</option>
                    <?php
                    $commodities = $pdo->query("SELECT DISTINCT commodity FROM farmgate_prices ORDER BY commodity ASC")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($commodities as $commodity) {
                        $selected = ($_GET['commodity'] ?? '') === $commodity ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($commodity) . "\" $selected>" . htmlspecialchars($commodity) . "</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label fw-bold">Region</label>
                <select name="region" class="form-select">
                    <option value="">All Regions</option>
                    <?php
                    $regions = $pdo->query("SELECT DISTINCT geolocation FROM farmgate_prices ORDER BY geolocation")->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($regions as $region) {
                        $selected = ($_GET['region'] ?? '') === $region ? 'selected' : '';
                        echo "<option value=\"" . htmlspecialchars($region) . "\" $selected>" . htmlspecialchars($region) . "</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-success btn-lg w-100 btn-export">
                    Apply Filter
                </button>
            </div>
        </form>

        <div class="text-center mt-4">
            <a href="reports/generate-pdf.php?<?= http_build_query($_GET) ?>" class="btn btn-danger btn-export mx-2">
                Download PDF Report
            </a>
            <a href="reports/export-csv.php?<?= http_build_query($_GET) ?>" class="btn btn-primary btn-export mx-2">
                Export to CSV
            </a>
        </div>
    </div>

    <!-- PRICE TREND CHART -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card chart-card">
                <div class="card-header bg-gradient bg-primary text-white text-center">
                    <h4 class="mb-0">Price Trend Over Years</h4>
                </div>
                <div class="card-body">
                    <canvas id="priceTrendChart" height="120"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- RANKINGS + REGIONAL -->
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card chart-card">
                <div class="card-header bg-gradient bg-danger text-white text-center">
                    <h5>Top 10 Most Expensive Commodities (Latest Year)</h5>
                </div>
                <div class="card-body">
                    <canvas id="topCommoditiesChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="card chart-card">
                <div class="card-header bg-gradient bg-info text-white text-center">
                    <h5>Average Price by Region</h5>
                </div>
                <div class="card-body">
                    <canvas id="regionalChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="text-center">
    <div class="container">
        <p class="mb-0">
            <strong>Philippine Statistics Authority (PSA) OpenSTAT</strong> • Agricultural Farmgate Price Analytics System © 2025<br>
            <small>Data updated automatically • Built with PHP, MySQL, Bootstrap & Chart.js</small>
        </p>
    </div>
</footer>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
// Initialize Select2
$(document).ready(function() {
    $('#commoditySelect').select2({
        theme: 'bootstrap-5',
        placeholder: "Search commodity (e.g. Tomato, Ampalaya, Cabbage)...",
        allowClear: true,
        width: '100%'
    });
});

// Charts
fetch('api-chart-data.php?<?= http_build_query($_GET) ?>').then(r=>r.json()).then(d=> {
    new Chart(document.getElementById('priceTrendChart'), {
        type: 'line',
        data: { labels: d.years, datasets: [{ label: 'Average Price (₱/kg)', data: d.prices, borderColor: '#1e40af', backgroundColor: 'rgba(30,64,175,0.1)', tension: 0.4, fill: true }] },
        options: { responsive: true, plugins: { legend: { position: 'top' } } }
    });
});

fetch('api-top-commodities.php').then(r=>r.json()).then(d=> {
    new Chart(document.getElementById('topCommoditiesChart'), {
        type: 'bar', data: { labels: d.commodities, datasets: [{ label: 'Price (₱)', data: d.prices, backgroundColor: '#ef4444' }] },
        options: { indexAxis: 'y', responsive: true }
    });
});

fetch('api-regional.php').then(r=>r.json()).then(d=> {
    new Chart(document.getElementById('regionalChart'), {
        type: 'bar', data: { labels: d.regions, datasets: [{ label: 'Avg Price (₱)', data: d.prices, backgroundColor: '#3b82f6' }] },
        options: { responsive: true }
    });
});
</script>
</body>
</html>