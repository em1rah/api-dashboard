<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Philippine Agricultural Price Trends Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3"></script>
    <script src="https://cdn.jsdelivr.net/npm/luxon@3.4.4/build/global/luxon.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-luxon@1.3.1"></script>
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient:    linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', sans-serif;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }
        .stat-card {
            background: var(--primary-gradient);
            color: white;
            border-radius: 20px;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-10px); }
        .chart-container {
            position: relative;
            height: 400px;
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .filter-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }
        .loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 10;
        }
        .badge-updated {
            font-size: 0.9rem;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 0.8; }
            50% { opacity: 1; }
            100% { opacity: 0.8; }
        }
    </style>
</head>
<body class="pb-5">
    <?php require 'includes/db.php'; ?>
    <?php
    $lastUpdate = $pdo->query("SELECT MAX(updated_at) FROM prices")->fetchColumn();
    $avgPrice = $pdo->query("SELECT AVG(price_php) FROM prices WHERE year = YEAR(CURDATE()) AND period = 'Annual'")->fetchColumn() ?: 0;
    $totalCommodities = $pdo->query("SELECT COUNT(*) FROM commodities")->fetchColumn();
    $totalRegions = $pdo->query("SELECT COUNT(*) FROM geolocations")->fetchColumn();
    ?>

    <div class="container py-5">
        <!-- Header -->
        <div class="text-center mb-5">
            <h1 class="display-4 fw-bold text-primary mb-3">
                Philippine Farmgate Price Analytics
            </h1>
            <p class="lead text-muted">Real-time Agricultural Price Trends Dashboard • PSA OpenSTAT</p>
            <div class="d-flex justify-content-center align-items-center gap-3 flex-wrap">
                <span class="badge bg-success badge-updated">
                    Updated: <?= $lastUpdate ? date('M d, Y H:i', strtotime($lastUpdate)) : 'Never' ?>
                </span>
                <button class="btn btn-outline-primary btn-sm" onclick="document.documentElement.setAttribute('data-bs-theme', document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark')">
                    <i class="bi bi-moon-stars-fill"></i> Toggle Theme
                </button>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="stat-card p-4 text-center text-white">
                    <i class="bi bi-currency-exchange display-1 opacity-75"></i>
                    <h2 class="display-5 fw-bold mt-3 count-up">₱<?= number_format($avgPrice, 2) ?></h2>
                    <p class="mb-0 opacity-90">Average Price (2024–2025)</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card p-4 text-center text-white" style="background: var(--success-gradient);">
                    <i class="bi bi-basket3-fill display-1 opacity-75"></i>
                    <h2 class="display-5 fw-bold mt-3 count-up"><?= $totalCommodities ?></h2>
                    <p class="mb-0 opacity-90">Total Commodities</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card p-4 text-center text-white" style="background: var(--warning-gradient);">
                    <i class="bi bi-geo-alt-fill display-1 opacity-75"></i>
                    <h2 class="display-5 fw-bold mt-3 count-up"><?= $totalRegions ?></h2>
                    <p class="mb-0 opacity-90">Regions Covered</p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card p-4 mb-5 shadow-lg">
            <form id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Commodity</label>
                    <select name="commodity" class="form-select form-select-lg">
                        <option value="">All Commodities</option>
                        <?php foreach($pdo->query("SELECT id, name FROM commodities ORDER BY name") as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= @$_GET['commodity']==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Region</label>
                    <select name="region" class="form-select form-select-lg">
                        <option value="">All Regions</option>
                        <?php foreach($pdo->query("SELECT id, name FROM geolocations ORDER BY name") as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= @$_GET['region']==$r['id']?'selected':'' ?>><?= htmlspecialchars($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">From</label>
                    <input type="number" name="y1" class="form-control form-control-lg" value="<?= $_GET['y1'] ?? '2015' ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">To</label>
                    <input type="number" name="y2" class="form-control form-control-lg" value="<?= $_GET['y2'] ?? date('Y') ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="bi bi-search"></i> Apply
                    </button>
                </div>
            </form>
        </div>

        <!-- Charts -->
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="chart-container shadow-lg">
                    <div class="loading" id="loading"><div class="spinner-border text-primary" style="width:3rem;height:3rem;"></div></div>
                    <canvas id="mainChart"></canvas>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-container shadow-lg">
                    <h5 class="text-center mb-3">Top 10 Commodities (2024)</h5>
                    <canvas id="topChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Export Buttons -->
        <div class="text-center mt-5">
            <a href="export/csv.php?<?= http_build_query($_GET) ?>" class="btn btn-success btn-lg px-5 py-3 me-3">
                Export CSV
            </a>
            <a href="export/pdf.php?<?= http_build_query($_GET) ?>" class="btn btn-danger btn-lg px-5 py-3">
                Export PDF
            </a>
            <a href="admin/update_data.php" class="btn btn-warning btn-lg px-5 py-3 ms-3">
                Update Data Now
            </a>
        </div>
    </div>

    <script>
        // Animated counters
        document.querySelectorAll('.count-up').forEach(el => {
            const target = parseFloat(el.textContent.replace(/[^\d.]/g, '')) || 0;
            let current = 0;
            const increment = target / 50;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    el.textContent = el.textContent.includes('₱') ? '₱' + target.toFixed(2) : Math.round(target);
                    clearInterval(timer);
                } else {
                    el.textContent = el.textContent.includes('₱') ? '₱' + current.toFixed(2) : Math.round(current);
                }
            }, 40);
        });

        // Load chart data
        async function loadCharts() {
            document.getElementById('loading').style.display = 'block';
            const params = new URLSearchParams(new FormData(document.getElementById('filterForm')));
            const url = 'index.php?' + params.toString();

            // Simple reload with filters
            if (window.location.search !== '?' + params.toString()) {
                window.location.search = params.toString();
                return;
            }

            <?php
            // Same data query as before but output as JSON for JS
            $where = ["period = 'Annual'"];
            $params = [];
            if ($_GET['commodity']??'') { $where[] = "commodity_id=?"; $params[] = $_GET['commodity']; }
            if ($_GET['region']??'') { $where[] = "geolocation_id=?"; $params[] = $_GET['region']; }
            $where[] = "year BETWEEN ? AND ?";
            $params[] = $_GET['y1']??2015;
            $params[] = $_GET['y2']??date('Y');

            $sql = "SELECT c.name AS commodity, g.name AS region, p.year, AVG(p.price_php) AS price
                    FROM prices p
                    JOIN commodities c ON p.commodity_id = c.id
                    JOIN geolocations g ON p.geolocation_id = g.id
                    WHERE " . implode(' AND ', $where) . "
                    GROUP BY p.year, c.id, g.id ORDER BY p.year";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Build datasets
            $datasets = [];
            $current = '';
            foreach ($rows as $r) {
                $label = $r['commodity'] . ($r['region'] ? ' - ' . $r['region'] : '');
                if ($label !== $current) {
                    if ($current) $datasets[] = $temp;
                    $temp = ['label' => $label, 'data' => [], 'tension' => 0.4, 'fill' => false];
                    $current = $label;
                }
                $temp['data'][] = round($r['price'], 2);
                $years[] = $r['year'];
            }
            if ($current) $datasets[] = $temp;
            $years = array_values(array_unique($years ?? []));
            sort($years);
            ?>

            const years = <?= json_encode($years) ?>;
            const datasets = <?= json_encode($datasets) ?>;

            // Main Line Chart
            new Chart(document.getElementById('mainChart'), {
                type: 'line',
                data: { labels: years, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: { display: true, text: 'Farmgate Price Trends (Annual Average)', font: { size: 18 } },
                        legend: { position: 'bottom' }
                    },
                    scales: { y: { title: { display: true, text: 'Price (₱/kg)' } } }
                }
            });

            // Top 10 Bar Chart
            <?php
            $top = $pdo->query("SELECT c.name, AVG(p.price_php) as avg 
                                FROM prices p JOIN commodities c ON p.commodity_id = c.id 
                                WHERE p.year = 2024 AND period = 'Annual' 
                                GROUP BY c.id ORDER BY avg DESC LIMIT 10")->fetchAll();
            ?>
            new Chart(document.getElementById('topChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_column($top, 'name')) ?>,
                    datasets: [{
                        label: 'Average Price 2024',
                        data: <?= json_encode(array_column($top, 'avg')) ?>,
                        backgroundColor: 'rgba(102, 126, 234, 0.8)'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { title: { display: false } }
                }
            });

            document.getElementById('loading').style.display = 'none';
        }

        // Auto-load on page load
        window.addEventListener('load', loadCharts);
    </script>
</body>
</html>