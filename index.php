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

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET">
                <div class="row">
                    <div class="col-md-3">
                        <label>Year Range</label>
                        <input type="number" name="year_from" class="form-control" value="<?= $_GET['year_from'] ?? 2015 ?>">
                    </div>
                    <div class="col-md-3">
                        <label>to</label>
                        <input type="number" name="year_to" class="form-control" value="<?= $_GET['year_to'] ?? 2024 ?>">
                    </div>
                    <div class="col-md-3">
                        <label>Commodity</label>
                        <input type="text" name="commodity" class="form-control" placeholder="e.g., Ampalaya, Cabbage">
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary mt-4">Filter</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- PDF Download Button - ADD THIS -->
<div class="text-center my-5">
    <a href="reports/generate-pdf.php" class="btn btn-danger btn-lg px-5 py-3 shadow-lg" style="font-size: 1.3rem;">
        📄 Download PDF Report
    </a>
</div>

    <!-- Chart -->
    <div class="card">
        <div class="card-body">
            <canvas id="priceChart"></canvas>
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
</script>
</body>
</html>