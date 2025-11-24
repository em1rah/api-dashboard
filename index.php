<?php require 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agri Price Trends Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">

<div class="container py-5">
    <h1 class="mb-4 text-center"> Philippine Agricultural Farmgate Price Trends</h1>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <?php
        $avg_leafy = $pdo->query("SELECT AVG(price) as avg FROM farmgate_prices WHERE commodity_type='leafy'")->fetch()['avg'];
        $avg_fruit = $pdo->query("SELECT AVG(price) as avg FROM farmgate_prices WHERE commodity_type='fruit'")->fetch()['avg'];
        ?>
        <div class="col-md-6">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h5> Average Leafy Vegetable Price</h5>
                    <h3>₱<?= number_format($avg_leafy, 2) ?>/kg</h3>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h5> Average Fruit Vegetable Price</h5>
                    <h3>₱<?= number_format($avg_fruit, 2) ?>/kg</h3>
                </div>
            </div>
        </div>
    </div>

  <!-- UPGRADED FILTERS + EXPORT BUTTONS -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Year From</label>
                <input type="number" name="year_from" class="form-control" value="<?= $_GET['year_from'] ?? 2015 ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Year To</label>
                <input type="number" name="year_to" class="form-control" value="<?= $_GET['year_to'] ?? 2024 ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Commodity</label>
                <input type="text" name="commodity" class="form-control" placeholder="e.g. Cabbage, Tomato" value="<?= $_GET['commodity'] ?? '' ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Region</label>
                <select name="region" class="form-select">
                    <option value="">All Regions</option>
                    <?php
                    $regions = $pdo->query("SELECT DISTINCT geolocation FROM farmgate_prices ORDER BY geolocation")->fetchAll();
                    foreach ($regions as $r) {
                        $selected = ($_GET['region'] ?? '') === $r['geolocation'] ? 'selected' : '';
                        echo "<option value=\"{$r['geolocation']}\" $selected>{$r['geolocation']}</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-12 text-center mt-3">
                <button type="submit" class="btn btn-success btn-lg me-3">Apply Filter</button>
                
                <a href="reports/generate-pdf.php?<?= http_build_query($_GET) ?>" 
                   class="btn btn-danger btn-lg me-3">Download PDF</a>
                   
                <a href="reports/export-csv.php?<?= http_build_query($_GET) ?>" 
                   class="btn btn-primary btn-lg">Export CSV</a>
            </div>
        </form>
    </div>
</div>

    <!-- Chart -->
    <div class="card">
        <div class="card-body">
            <canvas id="priceChart"></canvas>
        </div>
    </div>
</div>

<!-- COMMODITY RANKINGS + REGIONAL COMPARISON -->
<div class="row mt-5">
    <!-- Top 10 Most Expensive Commodities -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h5>Top 10 Most Expensive Commodities (Latest Year)</h5>
            </div>
            <div class="card-body">
                <canvas id="topCommoditiesChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Regional Average Prices -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow">
            <div class="card-header bg-info text-white">
                <h5>Average Price by Region (Latest Data)</h5>
            </div>
            <div class="card-body">
                <canvas id="regionalChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
// Fetch data via AJAX for Chart.js
fetch('api-chart-data.php?<?= http_build_query($_GET) ?>')
    .then(r => r.json())
    .then(data => {
        new Chart(document.getElementById('priceChart'), {
            type: 'line',
            data: {
                labels: data.years,
                datasets: [{
                    label: 'Average Farmgate Price (₱/kg)',
                    data: data.prices,
                    borderColor: 'rgb(75, 192, 192)',
                    tension: 0.1
                }]
            },
            options: { responsive: true }
        });
    });

    // Top 10 Commodities
fetch('api-top-commodities.php?<?= http_build_query($_GET) ?>')
    .then(r => r.json())
    .then(data => {
        new Chart(document.getElementById('topCommoditiesChart'), {
            type: 'bar',
            data: {
                labels: data.commodities,
                datasets: [{
                    label: 'Average Price (₱/kg)',
                    data: data.prices,
                    backgroundColor: 'rgba(255, 99, 132, 0.8)'
                }]
            },
            options: { indexAxis: 'y', responsive: true }
        });
    });

// Regional Comparison
fetch('api-regional.php?<?= http_build_query($_GET) ?>')
    .then(r => r.json())
    .then(data => {
        new Chart(document.getElementById('regionalChart'), {
            type: 'bar',
            data: {
                labels: data.regions,
                datasets: [{
                    label: 'Avg Price (₱/kg)',
                    data: data.prices,
                    backgroundColor: 'rgba(54, 162, 235, 0.8)'
                }]
            },
            options: { responsive: true }
        });
    });
</script>
</body>
</html>