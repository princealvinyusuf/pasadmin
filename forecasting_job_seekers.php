<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';
if (!current_user_can('view_dashboard_job_seekers')) { http_response_code(403); echo 'Forbidden'; exit; }

// Date filter (optional)
$start = isset($_GET['start']) && $_GET['start'] !== '' ? $_GET['start'] : null; // YYYY-MM-DD
$end = isset($_GET['end']) && $_GET['end'] !== '' ? $_GET['end'] : null;   // YYYY-MM-DD

function buildDateWhere(?string $start, ?string $end): array {
    $where = '';
    $params = [];
    $types = '';
    if ($start !== null) {
        $where .= ($where === '' ? 'WHERE ' : ' AND ') . 'DATE(COALESCE(created_date, tanggal_daftar)) >= ?';
        $params[] = $start;
        $types .= 's';
    }
    if ($end !== null) {
        $where .= ($where === '' ? 'WHERE ' : ' AND ') . 'DATE(COALESCE(created_date, tanggal_daftar)) <= ?';
        $params[] = $end;
        $types .= 's';
    }
    return [$where, $types, $params];
}

function fetchTimeSeriesData(mysqli $conn, ?string $start, ?string $end): array {
    list($where, $types, $params) = buildDateWhere($start, $end);
    $sql = "SELECT 
                DATE(COALESCE(created_date, tanggal_daftar)) as date,
                COUNT(*) as count
            FROM job_seekers 
            $where 
            GROUP BY DATE(COALESCE(created_date, tanggal_daftar))
            ORDER BY date";
    
    $stmt = $conn->prepare($sql);
    if ($types !== '') { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $res = $stmt->get_result();
    $data = [];
    while ($r = $res->fetch_assoc()) {
        $data[] = [
            'date' => $r['date'],
            'count' => intval($r['count'])
        ];
    }
    $stmt->close();
    return $data;
}

function fetchMonthlyTrends(mysqli $conn, ?string $start, ?string $end): array {
    list($where, $types, $params) = buildDateWhere($start, $end);
    $sql = "SELECT 
                DATE_FORMAT(COALESCE(created_date, tanggal_daftar), '%Y-%m') as month,
                COUNT(*) as count
            FROM job_seekers 
            $where 
            GROUP BY DATE_FORMAT(COALESCE(created_date, tanggal_daftar), '%Y-%m')
            ORDER BY month";
    
    $stmt = $conn->prepare($sql);
    if ($types !== '') { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $res = $stmt->get_result();
    $data = [];
    while ($r = $res->fetch_assoc()) {
        $data[] = [
            'month' => $r['month'],
            'count' => intval($r['count'])
        ];
    }
    $stmt->close();
    return $data;
}

function fetchRegionalForecast(mysqli $conn, ?string $start, ?string $end): array {
    list($where, $types, $params) = buildDateWhere($start, $end);
    $sql = "SELECT 
                provinsi,
                COUNT(*) as current_count,
                COUNT(CASE WHEN DATE(COALESCE(created_date, tanggal_daftar)) >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent_count
            FROM job_seekers 
            $where 
            GROUP BY provinsi
            ORDER BY current_count DESC
            LIMIT 15";
    
    $stmt = $conn->prepare($sql);
    if ($types !== '') { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $res = $stmt->get_result();
    $data = [];
    while ($r = $res->fetch_assoc()) {
        $data[] = [
            'provinsi' => $r['provinsi'] ?: 'Unknown',
            'current_count' => intval($r['current_count']),
            'recent_count' => intval($r['recent_count']),
            'growth_rate' => $r['current_count'] > 0 ? round((($r['recent_count'] / $r['current_count']) * 100), 1) : 0
        ];
    }
    $stmt->close();
    return $data;
}

function fetchDemographicForecast(mysqli $conn, ?string $start, ?string $end): array {
    list($where, $types, $params) = buildDateWhere($start, $end);
    $sql = "SELECT 
                kelompok_umur,
                jenis_kelamin,
                COUNT(*) as count,
                COUNT(CASE WHEN DATE(COALESCE(created_date, tanggal_daftar)) >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent_count
            FROM job_seekers 
            $where 
            GROUP BY kelompok_umur, jenis_kelamin
            ORDER BY count DESC";
    
    $stmt = $conn->prepare($sql);
    if ($types !== '') { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $res = $stmt->get_result();
    $data = [];
    while ($r = $res->fetch_assoc()) {
        $data[] = [
            'age_group' => $r['kelompok_umur'] ?: 'Unknown',
            'gender' => $r['jenis_kelamin'] ?: 'Unknown',
            'count' => intval($r['count']),
            'recent_count' => intval($r['recent_count']),
            'trend' => $r['count'] > 0 ? round((($r['recent_count'] / $r['count']) * 100), 1) : 0
        ];
    }
    $stmt->close();
    return $data;
}

function fetchSkillsForecast(mysqli $conn, ?string $start, ?string $end): array {
    list($where, $types, $params) = buildDateWhere($start, $end);
    
    // Build the skills filter condition
    $skillsWhere = "TRIM(IFNULL(keahlian, '')) <> ''";
    if ($where !== '') {
        $where .= " AND " . $skillsWhere;
    } else {
        $where = "WHERE " . $skillsWhere;
    }
    
    $sql = "SELECT 
                keahlian,
                COUNT(*) as count,
                COUNT(CASE WHEN DATE(COALESCE(created_date, tanggal_daftar)) >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent_count
            FROM job_seekers 
            $where 
            GROUP BY keahlian
            ORDER BY count DESC
            LIMIT 20";
    
    $stmt = $conn->prepare($sql);
    if ($types !== '') { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $res = $stmt->get_result();
    $data = [];
    while ($r = $res->fetch_assoc()) {
        $data[] = [
            'skill' => $r['keahlian'],
            'count' => intval($r['count']),
            'recent_count' => intval($r['recent_count']),
            'demand_trend' => $r['count'] > 0 ? round((($r['recent_count'] / $r['count']) * 100), 1) : 0
        ];
    }
    $stmt->close();
    return $data;
}

// Fetch data for forecasting
$timeSeriesData = fetchTimeSeriesData($conn, $start, $end);
$monthlyTrends = fetchMonthlyTrends($conn, $start, $end);
$regionalForecast = fetchRegionalForecast($conn, $start, $end);
$demographicForecast = fetchDemographicForecast($conn, $start, $end);
$skillsForecast = fetchSkillsForecast($conn, $start, $end);

// Calculate forecasting metrics
$totalRecords = count($timeSeriesData);
$avgDailyGrowth = 0;
$projectedGrowth = 0;

if ($totalRecords > 1) {
    $firstDate = $timeSeriesData[0]['date'];
    $lastDate = $timeSeriesData[count($timeSeriesData) - 1]['date'];
    $firstCount = $timeSeriesData[0]['count'];
    $lastCount = $timeSeriesData[count($timeSeriesData) - 1]['count'];
    
    $daysDiff = (strtotime($lastDate) - strtotime($firstDate)) / (60 * 60 * 24);
    if ($daysDiff > 0) {
        $avgDailyGrowth = ($lastCount - $firstCount) / $daysDiff;
        $projectedGrowth = $lastCount + ($avgDailyGrowth * 30); // 30 days projection
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forecasting Job Seekers</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    	<style>
		body { background: #f6f8fa; }
		.card-title { font-weight: 600; font-size: 0.95rem; }
		.chart-card { min-height: 300px; }
		.forecast-card { min-height: 250px; }
		.trend-indicator {
			font-size: 0.75rem;
			font-weight: 500;
		}
		.trend-up { color: #28a745; }
		.trend-down { color: #dc3545; }
		.trend-stable { color: #6c757d; }
		.forecast-badge {
			font-size: 0.65rem;
			padding: 0.2rem 0.4rem;
		}
		.compact-text { font-size: 0.8rem; }
		.compact-heading { font-size: 1.1rem; }
		.compact-card { padding: 0.75rem; }
		.compact-table { font-size: 0.75rem; }
		.loading-spinner {
			display: none;
			text-align: center;
			padding: 2rem;
		}
		.chart-container { position: relative; }
		.alert-sm { font-size: 0.8rem; }
		.alert-sm .bi { font-size: 0.9rem; }
	</style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    	<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
		<div>
			<h2 class="mb-1 mb-md-0 compact-heading">
				<i class="bi bi-graph-up-arrow me-2"></i>Forecasting Job Seekers
			</h2>
			<p class="text-muted mb-0 compact-text">Predictive analytics for 5M+ job seeker records</p>
		</div>
		<form class="d-flex gap-2" method="get" action="forecasting_job_seekers.php">
			<input type="date" class="form-control form-control-sm" name="start" value="<?php echo htmlspecialchars($start ?? ''); ?>" placeholder="Start date">
			<input type="date" class="form-control form-control-sm" name="end" value="<?php echo htmlspecialchars($end ?? ''); ?>" placeholder="End date">
			<button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
			<a class="btn btn-outline-secondary btn-sm" href="forecasting_job_seekers.php">Reset</a>
		</form>
	</div>

	<!-- Performance Info -->
	<div class="alert alert-info alert-sm py-2 mb-3">
		<div class="d-flex align-items-center">
			<i class="bi bi-info-circle me-2"></i>
			<div class="compact-text">
				<strong>Performance Optimized:</strong> Charts are configured for large datasets (5M+ records). 
				Use date filters to improve loading speed.
			</div>
		</div>
	</div>

    	<!-- Forecasting Summary Cards -->
	<div class="row g-2 mb-3">
		<div class="col-12 col-md-6 col-xl-3">
			<div class="card shadow-sm border-0 compact-card">
				<div class="card-body p-2">
					<div class="d-flex align-items-center">
						<div class="flex-shrink-0">
							<i class="bi bi-calendar-trend text-primary" style="font-size: 1.5rem;"></i>
						</div>
						<div class="flex-grow-1 ms-2">
							<div class="text-muted compact-text">Data Points</div>
							<div class="h5 mb-0"><?php echo number_format($totalRecords); ?></div>
							<div class="text-muted compact-text">Available for analysis</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="col-12 col-md-6 col-xl-3">
			<div class="card shadow-sm border-0 compact-card">
				<div class="card-body p-2">
					<div class="d-flex align-items-center">
						<div class="flex-shrink-0">
							<i class="bi bi-arrow-up-right text-success" style="font-size: 1.5rem;"></i>
						</div>
						<div class="flex-grow-1 ms-2">
							<div class="text-muted compact-text">Daily Growth Rate</div>
							<div class="h5 mb-0"><?php echo number_format($avgDailyGrowth, 1); ?></div>
							<div class="text-muted compact-text">Average daily increase</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="col-12 col-md-6 col-xl-3">
			<div class="card shadow-sm border-0 compact-card">
				<div class="card-body p-2">
					<div class="d-flex align-items-center">
						<div class="flex-shrink-0">
							<i class="bi bi-graph-up text-warning" style="font-size: 1.5rem;"></i>
						</div>
						<div class="flex-grow-1 ms-2">
							<div class="text-muted compact-text">30-Day Projection</div>
							<div class="h5 mb-0"><?php echo number_format($projectedGrowth, 0); ?></div>
							<div class="text-muted compact-text">Projected growth</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="col-12 col-md-6 col-xl-3">
			<div class="card shadow-sm border-0 compact-card">
				<div class="card-body p-2">
					<div class="d-flex align-items-center">
						<div class="flex-shrink-0">
							<i class="bi bi-geo-alt text-info" style="font-size: 1.5rem;"></i>
						</div>
						<div class="flex-grow-1 ms-2">
							<div class="text-muted compact-text">Top Regions</div>
							<div class="h5 mb-0"><?php echo count($regionalForecast); ?></div>
							<div class="text-muted compact-text">Analyzed regions</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

    	<!-- Time Series Forecasting -->
	<div class="row g-2 mb-3">
		<div class="col-12">
			<div class="card chart-card shadow-sm">
				<div class="card-body compact-card">
					<h5 class="card-title mb-2">
						<i class="bi bi-clock-history me-2"></i>Time Series Forecasting - Daily Registrations
					</h5>
					<p class="text-muted compact-text mb-2">Historical trends with linear regression forecasting</p>
					<div class="chart-container">
						<canvas id="timeSeriesChart"></canvas>
					</div>
				</div>
			</div>
		</div>
	</div>

    	<!-- Monthly Trends & Regional Forecast -->
	<div class="row g-2 mb-3">
		<div class="col-12 col-xl-6">
			<div class="card chart-card shadow-sm">
				<div class="card-body compact-card">
					<h5 class="card-title mb-2">
						<i class="bi bi-calendar-month me-2"></i>Monthly Trends Analysis
					</h5>
					<p class="text-muted compact-text mb-2">Monthly registration patterns with trend indicators</p>
					<div class="chart-container">
						<canvas id="monthlyTrendsChart"></canvas>
					</div>
				</div>
			</div>
		</div>
		<div class="col-12 col-xl-6">
			<div class="card chart-card shadow-sm">
				<div class="card-body compact-card">
					<h5 class="card-title mb-2">
						<i class="bi bi-geo-alt me-2"></i>Regional Growth Forecasting
					</h5>
					<p class="text-muted compact-text mb-2">Province-wise growth rates and projections</p>
					<div class="chart-container">
						<canvas id="regionalForecastChart"></canvas>
					</div>
				</div>
			</div>
		</div>
	</div>

    	<!-- Demographic & Skills Forecasting -->
	<div class="row g-2 mb-3">
		<div class="col-12 col-xl-6">
			<div class="card chart-card shadow-sm">
				<div class="card-body compact-card">
					<h5 class="card-title mb-2">
						<i class="bi bi-people me-2"></i>Demographic Trends Forecast
					</h5>
					<p class="text-muted compact-text mb-2">Age group and gender-based growth patterns</p>
					<div class="chart-container">
						<canvas id="demographicForecastChart"></canvas>
					</div>
				</div>
			</div>
		</div>
		<div class="col-12 col-xl-6">
			<div class="card chart-card shadow-sm">
				<div class="card-body compact-card">
					<h5 class="card-title mb-2">
						<i class="bi bi-lightning me-2"></i>Skills Demand Forecasting
					</h5>
					<p class="text-muted compact-text mb-2">Emerging skills trends and demand prediction</p>
					<div class="chart-container">
						<canvas id="skillsForecastChart"></canvas>
					</div>
				</div>
			</div>
		</div>
	</div>

    	<!-- Detailed Forecast Tables -->
	<div class="row g-2">
		<div class="col-12 col-xl-6">
			<div class="card shadow-sm">
				<div class="card-header bg-light py-2">
					<h6 class="card-title mb-0 compact-text">
						<i class="bi bi-table me-2"></i>Regional Growth Forecast Details
					</h6>
				</div>
				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table table-sm mb-0 compact-table">
							<thead class="table-light">
								<tr>
									<th class="py-1">Province</th>
									<th class="py-1">Current</th>
									<th class="py-1">Recent</th>
									<th class="py-1">Growth %</th>
									<th class="py-1">Trend</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach (array_slice($regionalForecast, 0, 8) as $region): ?>
								<tr>
									<td class="py-1"><?php echo htmlspecialchars($region['provinsi']); ?></td>
									<td class="py-1"><?php echo number_format($region['current_count']); ?></td>
									<td class="py-1"><?php echo number_format($region['recent_count']); ?></td>
									<td class="py-1"><?php echo $region['growth_rate']; ?>%</td>
									<td class="py-1">
										<?php if ($region['growth_rate'] > 5): ?>
											<span class="badge bg-success forecast-badge">↑ Growing</span>
										<?php elseif ($region['growth_rate'] < -5): ?>
											<span class="badge bg-danger forecast-badge">↓ Declining</span>
										<?php else: ?>
											<span class="badge bg-secondary forecast-badge">→ Stable</span>
										<?php endif; ?>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
		<div class="col-12 col-xl-6">
			<div class="card shadow-sm">
				<div class="card-header bg-light py-2">
					<h6 class="card-title mb-0 compact-text">
						<i class="bi bi-list-check me-2"></i>Top Skills Demand Forecast
					</h6>
				</div>
				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table table-sm mb-0 compact-table">
							<thead class="table-light">
								<tr>
									<th class="py-1">Skill</th>
									<th class="py-1">Total</th>
									<th class="py-1">Recent</th>
									<th class="py-1">Demand Trend</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach (array_slice($skillsForecast, 0, 8) as $skill): ?>
								<tr>
									<td class="py-1"><?php echo htmlspecialchars($skill['skill']); ?></td>
									<td class="py-1"><?php echo number_format($skill['count']); ?></td>
									<td class="py-1"><?php echo number_format($skill['recent_count']); ?></td>
									<td class="py-1">
										<?php if ($skill['demand_trend'] > 10): ?>
											<span class="badge bg-success forecast-badge">🔥 High Demand</span>
										<?php elseif ($skill['demand_trend'] > 5): ?>
											<span class="badge bg-warning forecast-badge">📈 Growing</span>
										<?php else: ?>
											<span class="badge bg-secondary forecast-badge">→ Stable</span>
										<?php endif; ?>
									</td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
// Time Series Chart with Forecasting
const timeSeriesCtx = document.getElementById('timeSeriesChart').getContext('2d');
const timeSeriesData = <?php echo json_encode($timeSeriesData); ?>;

// Calculate linear regression for forecasting
const dates = timeSeriesData.map(d => new Date(d.date));
const counts = timeSeriesData.map(d => d.count);

// Simple linear regression
let sumX = 0, sumY = 0, sumXY = 0, sumX2 = 0;
dates.forEach((date, i) => {
    const x = i;
    const y = counts[i];
    sumX += x;
    sumY += y;
    sumXY += x * y;
    sumX2 += x * x;
});

const n = dates.length;
const slope = (n * sumXY - sumX * sumY) / (n * sumX2 - sumX * sumX);
const intercept = (sumY - slope * sumX) / n;

// Generate forecast data (next 30 days)
const forecastDates = [];
const forecastCounts = [];
for (let i = 0; i < 30; i++) {
    const futureDate = new Date(dates[dates.length - 1]);
    futureDate.setDate(futureDate.getDate() + i + 1);
    forecastDates.push(futureDate);
    forecastCounts.push(intercept + slope * (n + i));
}

new Chart(timeSeriesCtx, {
    type: 'line',
    data: {
        datasets: [{
            label: 'Actual Registrations',
            data: timeSeriesData.map(d => ({x: new Date(d.date), y: d.count})),
            borderColor: 'rgb(59, 130, 246)',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            fill: true,
            tension: 0.1
        }, {
            label: 'Forecast (30 days)',
            data: forecastDates.map((date, i) => ({x: date, y: forecastCounts[i]})),
            borderColor: 'rgb(255, 193, 7)',
            backgroundColor: 'rgba(255, 193, 7, 0.1)',
            borderDash: [5, 5],
            fill: false,
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: false
            },
            tooltip: {
                mode: 'index',
                intersect: false,
                backgroundColor: 'rgba(0,0,0,0.8)',
                titleFont: { size: 12 },
                bodyFont: { size: 11 }
            },
            legend: {
                labels: {
                    font: { size: 11 },
                    usePointStyle: true
                }
            }
        },
        scales: {
            x: {
                type: 'time',
                time: {
                    unit: 'day',
                    displayFormats: {
                        day: 'MMM dd'
                    }
                },
                title: {
                    display: false
                },
                ticks: {
                    font: { size: 10 },
                    maxTicksLimit: 8
                }
            },
            y: {
                title: {
                    display: false
                },
                ticks: {
                    font: { size: 10 },
                    callback: function(value) {
                        return value >= 1000 ? (value/1000).toFixed(1) + 'K' : value;
                    }
                }
            }
        },
        interaction: {
            mode: 'nearest',
            axis: 'x',
            intersect: false
        }
    }
});

// Monthly Trends Chart
const monthlyCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
const monthlyData = <?php echo json_encode($monthlyTrends); ?>;

new Chart(monthlyCtx, {
    type: 'bar',
    data: {
        labels: monthlyData.map(d => d.month),
        datasets: [{
            label: 'Monthly Registrations',
            data: monthlyData.map(d => d.count),
            backgroundColor: 'rgba(59, 130, 246, 0.8)',
            borderColor: 'rgb(59, 130, 246)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: false
            },
            legend: {
                display: false
            }
        },
        scales: {
            x: {
                ticks: {
                    font: { size: 10 },
                    maxTicksLimit: 6
                }
            },
            y: {
                beginAtZero: true,
                title: {
                    display: false
                },
                ticks: {
                    font: { size: 10 },
                    callback: function(value) {
                        return value >= 1000 ? (value/1000).toFixed(1) + 'K' : value;
                    }
                }
            }
        }
    }
});

// Regional Forecast Chart
const regionalCtx = document.getElementById('regionalForecastChart').getContext('2d');
const regionalData = <?php echo json_encode($regionalForecast); ?>;

new Chart(regionalCtx, {
    type: 'bar',
    data: {
        labels: regionalData.map(d => d.provinsi),
        datasets: [{
            label: 'Current Count',
            data: regionalData.map(d => d.current_count),
            backgroundColor: 'rgba(59, 130, 246, 0.8)',
            borderColor: 'rgb(59, 130, 246)',
            borderWidth: 1
        }, {
            label: 'Recent Count (30 days)',
            data: regionalData.map(d => d.recent_count),
            backgroundColor: 'rgba(255, 193, 7, 0.8)',
            borderColor: 'rgb(255, 193, 7)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: false
            },
            legend: {
                labels: {
                    font: { size: 10 },
                    usePointStyle: true
                }
            }
        },
        scales: {
            x: {
                ticks: {
                    font: { size: 9 },
                    maxTicksLimit: 8
                }
            },
            y: {
                beginAtZero: true,
                title: {
                    display: false
                },
                ticks: {
                    font: { size: 10 },
                    callback: function(value) {
                        return value >= 1000 ? (value/1000).toFixed(1) + 'K' : value;
                    }
                }
            }
        }
    }
});

// Demographic Forecast Chart
const demographicCtx = document.getElementById('demographicForecastChart').getContext('2d');
const demographicData = <?php echo json_encode($demographicForecast); ?>;

// Group by age group for better visualization
const ageGroups = [...new Set(demographicData.map(d => d.age_group))];
const genderData = {
    'Laki-laki': ageGroups.map(age => {
        const item = demographicData.find(d => d.age_group === age && d.gender === 'Laki-laki');
        return item ? item.count : 0;
    }),
    'Perempuan': ageGroups.map(age => {
        const item = demographicData.find(d => d.age_group === age && d.gender === 'Perempuan');
        return item ? item.count : 0;
    })
};

new Chart(demographicCtx, {
    type: 'bar',
    data: {
        labels: ageGroups,
        datasets: [{
            label: 'Male',
            data: genderData['Laki-laki'],
            backgroundColor: 'rgba(59, 130, 246, 0.8)',
            borderColor: 'rgb(59, 130, 246)',
            borderWidth: 1
        }, {
            label: 'Female',
            data: genderData['Perempuan'],
            backgroundColor: 'rgba(255, 107, 107, 0.8)',
            borderColor: 'rgb(255, 107, 107)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: false
            },
            legend: {
                labels: {
                    font: { size: 10 },
                    usePointStyle: true
                }
            }
        },
        scales: {
            x: {
                ticks: {
                    font: { size: 9 },
                    maxTicksLimit: 6
                }
            },
            y: {
                beginAtZero: true,
                title: {
                    display: false
                },
                ticks: {
                    font: { size: 10 },
                    callback: function(value) {
                        return value >= 1000 ? (value/1000).toFixed(1) + 'K' : value;
                    }
                }
            }
        }
    }
});

// Skills Forecast Chart
const skillsCtx = document.getElementById('skillsForecastChart').getContext('2d');
const skillsData = <?php echo json_encode($skillsForecast); ?>;

new Chart(skillsCtx, {
    type: 'horizontalBar',
    data: {
        labels: skillsData.map(d => d.skill.length > 20 ? d.skill.substring(0, 20) + '...' : d.skill),
        datasets: [{
            label: 'Total Count',
            data: skillsData.map(d => d.count),
            backgroundColor: 'rgba(59, 130, 246, 0.8)',
            borderColor: 'rgb(59, 130, 246)',
            borderWidth: 1
        }, {
            label: 'Recent Count (30 days)',
            data: skillsData.map(d => d.recent_count),
            backgroundColor: 'rgba(255, 193, 7, 0.8)',
            borderColor: 'rgb(255, 193, 7)',
            borderWidth: 1
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: false
            },
            legend: {
                labels: {
                    font: { size: 10 },
                    usePointStyle: true
                }
            }
        },
        scales: {
            x: {
                beginAtZero: true,
                title: {
                    display: false
                },
                ticks: {
                    font: { size: 10 },
                    callback: function(value) {
                        return value >= 1000 ? (value/1000).toFixed(1) + 'K' : value;
                    }
                }
            },
            y: {
                ticks: {
                    font: { size: 9 },
                    maxTicksLimit: 8
                }
            }
        }
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>