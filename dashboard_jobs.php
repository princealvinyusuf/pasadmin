<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';

// Date filter (optional) applied to posting/created date
$start = isset($_GET['start']) && $_GET['start'] !== '' ? $_GET['start'] : null; // YYYY-MM-DD
$end = isset($_GET['end']) && $_GET['end'] !== '' ? $_GET['end'] : null;   // YYYY-MM-DD

// Use posting date if present; fallback to created_date
define('JOBS_DATE_EXPR', "DATE(COALESCE(tanggal_posting_lowongan, created_date))");

function buildDateWhere(?string $start, ?string $end): array {
	$where = '';
	$params = [];
	$types = '';
	if ($start !== null) {
		$where .= ($where === '' ? 'WHERE ' : ' AND ') . JOBS_DATE_EXPR . ' >= ?';
		$params[] = $start;
		$types .= 's';
	}
	if ($end !== null) {
		$where .= ($where === '' ? 'WHERE ' : ' AND ') . JOBS_DATE_EXPR . ' <= ?';
		$params[] = $end;
		$types .= 's';
	}
	return [$where, $types, $params];
}

function fetchScalar(mysqli $conn, string $sql, string $types = '', array $params = []): int {
	$stmt = $conn->prepare($sql);
	if ($types !== '') { $stmt->bind_param($types, ...$params); }
	$stmt->execute();
	$stmt->bind_result($val);
	$stmt->fetch();
	$stmt->close();
	return intval($val ?? 0);
}

function fetchGroupCounts(mysqli $conn, string $field, ?string $start, ?string $end, ?int $limit = null): array {
	$allowed = ['platform_lowongan','provinsi','tipe_pekerjaan','model_kerja','min_pendidikan','bidang_industri'];
	if (!in_array($field, $allowed, true)) {
		return [];
	}
	list($where, $types, $params) = buildDateWhere($start, $end);
	$sql = "SELECT IFNULL($field, 'Unknown') AS label, COUNT(*) AS cnt FROM jobs $where GROUP BY label ORDER BY cnt DESC" . ($limit ? ' LIMIT ' . intval($limit) : '');
	$stmt = $conn->prepare($sql);
	if ($types !== '') { $stmt->bind_param($types, ...$params); }
	$stmt->execute();
	$res = $stmt->get_result();
	$data = [];
	while ($r = $res->fetch_assoc()) { $data[$r['label'] === '' ? 'Unknown' : $r['label']] = intval($r['cnt']); }
	$stmt->close();
	return $data;
}

function fetchTrend(mysqli $conn, int $days = 30): array {
	$sql = 'SELECT ' . JOBS_DATE_EXPR . ' AS d, COUNT(*) AS c FROM jobs WHERE ' . JOBS_DATE_EXPR . ' >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY d ORDER BY d ASC';
	$stmt = $conn->prepare($sql);
	$stmt->bind_param('i', $days);
	$stmt->execute();
	$res = $stmt->get_result();
	$map = [];
	while ($r = $res->fetch_assoc()) { $map[$r['d']] = intval($r['c']); }
	$stmt->close();
	$labels = [];
	$values = [];
	for ($i = $days - 1; $i >= 0; $i--) {
		$day = date('Y-m-d', strtotime("-$i day"));
		$labels[] = $day;
		$values[] = $map[$day] ?? 0;
	}
	return [$labels, $values];
}

// KPIs
list($whereAll, $typesAll, $paramsAll) = buildDateWhere($start, $end);
$totalFiltered = fetchScalar($conn, 'SELECT COUNT(*) FROM jobs ' . $whereAll, $typesAll, $paramsAll);
$totalAll = fetchScalar($conn, 'SELECT COUNT(*) FROM jobs');

$firstDay = date('Y-m-01');
$lastDay = date('Y-m-t');
$totalThisMonth = fetchScalar($conn,
	'SELECT COUNT(*) FROM jobs WHERE ' . JOBS_DATE_EXPR . ' BETWEEN ? AND ?', 'ss', [$firstDay, $lastDay]
);

// Active jobs: not expired or expired >= today
list($whereActive, $typesActive, $paramsActive) = buildDateWhere($start, $end);
$whereActive .= ($whereActive === '' ? 'WHERE ' : ' AND ') . "(tanggal_expired_lowongan IS NULL OR tanggal_expired_lowongan = '' OR tanggal_expired_lowongan >= CURDATE())";
$activeJobs = fetchScalar($conn, 'SELECT COUNT(*) FROM jobs ' . $whereActive, $typesActive, $paramsActive);

// Salary averages from text fields by stripping non-digits
list($whereSal, $typesSal, $paramsSal) = buildDateWhere($start, $end);
$cleanMin = "CAST(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(gaji_minimum,''), 'Rp',''), 'IDR',''), ',', ''), '.', ''), ' ', '') AS UNSIGNED)";
$cleanMax = "CAST(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(gaji_maksimum,''), 'Rp',''), 'IDR',''), ',', ''), '.', ''), ' ', '') AS UNSIGNED)";
$avgMin = fetchScalar($conn, 'SELECT AVG(' . $cleanMin . ') FROM jobs ' . $whereSal, $typesSal, $paramsSal);
$avgMax = fetchScalar($conn, 'SELECT AVG(' . $cleanMax . ') FROM jobs ' . $whereSal, $typesSal, $paramsSal);

// Grouped data
$byPlatform = fetchGroupCounts($conn, 'platform_lowongan', $start, $end, 10);
$byProvince = fetchGroupCounts($conn, 'provinsi', $start, $end, 10);
$byType = fetchGroupCounts($conn, 'tipe_pekerjaan', $start, $end, null);
$byModel = fetchGroupCounts($conn, 'model_kerja', $start, $end, null);
$byEdu = fetchGroupCounts($conn, 'min_pendidikan', $start, $end, 8);
$byIndustry = fetchGroupCounts($conn, 'bidang_industri', $start, $end, 10);

list($trendLabels, $trendValues) = fetchTrend($conn, 30);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Dashboard Jobs</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
	<style>
		body { background: #f6f8fa; }
		.card-title { font-weight: 600; }
		.chart-card { min-height: 420px; }
	</style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
	<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
		<h2 class="mb-2 mb-md-0">Dashboard Jobs</h2>
		<form class="d-flex gap-2" method="get" action="dashboard_jobs.php">
			<input type="date" class="form-control" name="start" value="<?php echo htmlspecialchars($start ?? ''); ?>" placeholder="Start date">
			<input type="date" class="form-control" name="end" value="<?php echo htmlspecialchars($end ?? ''); ?>" placeholder="End date">
			<button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
			<a class="btn btn-outline-secondary" href="dashboard_jobs.php">Reset</a>
		</form>
	</div>

	<div class="row g-3 mb-3">
		<div class="col-12 col-md-6 col-xl-3">
			<div class="card shadow-sm">
				<div class="card-body">
					<div class="text-muted">Total (Filtered)</div>
					<div class="display-6"><?php echo number_format($totalFiltered); ?></div>
					<div class="text-muted small">All-time total: <?php echo number_format($totalAll); ?></div>
				</div>
			</div>
		</div>
		<div class="col-12 col-md-6 col-xl-3">
			<div class="card shadow-sm">
				<div class="card-body">
					<div class="text-muted">New This Month</div>
					<div class="display-6"><?php echo number_format($totalThisMonth); ?></div>
					<div class="text-muted small">From <?php echo htmlspecialchars($firstDay); ?> to <?php echo htmlspecialchars($lastDay); ?></div>
				</div>
			</div>
		</div>
		<div class="col-12 col-md-6 col-xl-3">
			<div class="card shadow-sm">
				<div class="card-body">
					<div class="text-muted">Active Jobs</div>
					<div class="display-6"><?php echo number_format($activeJobs); ?></div>
				</div>
			</div>
		</div>
		<div class="col-12 col-md-6 col-xl-3">
			<div class="card shadow-sm">
				<div class="card-body">
					<div class="text-muted">Avg Salary (Min–Max)</div>
					<div class="display-6"><?php echo number_format($avgMin); ?> – <?php echo number_format($avgMax); ?></div>
				</div>
			</div>
		</div>
	</div>

	<div class="row g-3">
		<div class="col-12">
			<div class="card chart-card shadow-sm">
				<div class="card-body">
					<h5 class="card-title mb-3">Postings Last 30 Days</h5>
					<canvas id="chartTrend"></canvas>
				</div>
			</div>
		</div>
	</div>

	<div class="row g-3 mt-1">
		<div class="col-12 col-xl-6">
			<div class="card chart-card shadow-sm">
				<div class="card-body">
					<h5 class="card-title mb-3">Top Platforms</h5>
					<canvas id="chartPlatform"></canvas>
				</div>
			</div>
		</div>
		<div class="col-12 col-xl-6">
			<div class="card chart-card shadow-sm">
				<div class="card-body">
					<h5 class="card-title mb-3">Top Provinces</h5>
					<canvas id="chartProvince"></canvas>
				</div>
			</div>
		</div>
	</div>

	<div class="row g-3 mt-1">
		<div class="col-12 col-xl-6">
			<div class="card chart-card shadow-sm">
				<div class="card-body">
					<h5 class="card-title mb-3">Job Types</h5>
					<canvas id="chartType"></canvas>
				</div>
			</div>
		</div>
		<div class="col-12 col-xl-6">
			<div class="card chart-card shadow-sm">
				<div class="card-body">
					<h5 class="card-title mb-3">Work Models</h5>
					<canvas id="chartModel"></canvas>
				</div>
			</div>
		</div>
	</div>

	<div class="row g-3 mt-1">
		<div class="col-12 col-xl-6">
			<div class="card chart-card shadow-sm">
				<div class="card-body">
					<h5 class="card-title mb-3">Minimum Education</h5>
					<canvas id="chartEdu"></canvas>
				</div>
			</div>
		</div>
		<div class="col-12 col-xl-6">
			<div class="card chart-card shadow-sm">
				<div class="card-body">
					<h5 class="card-title mb-3">Industries</h5>
					<canvas id="chartIndustry"></canvas>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
const byPlatform = <?php echo json_encode($byPlatform, JSON_UNESCAPED_UNICODE); ?>;
const byProvince = <?php echo json_encode($byProvince, JSON_UNESCAPED_UNICODE); ?>;
const byType = <?php echo json_encode($byType, JSON_UNESCAPED_UNICODE); ?>;
const byModel = <?php echo json_encode($byModel, JSON_UNESCAPED_UNICODE); ?>;
const byEdu = <?php echo json_encode($byEdu, JSON_UNESCAPED_UNICODE); ?>;
const byIndustry = <?php echo json_encode($byIndustry, JSON_UNESCAPED_UNICODE); ?>;
const trendLabels = <?php echo json_encode($trendLabels, JSON_UNESCAPED_UNICODE); ?>;
const trendValues = <?php echo json_encode($trendValues, JSON_UNESCAPED_UNICODE); ?>;

const palette = ['#2563eb','#16a34a','#f59e0b','#dc2626','#9333ea','#0891b2','#4b5563','#fb7185','#84cc16','#06b6d4','#f97316'];

function doughnutChart(ctxId, dataMap) {
	const labels = Object.keys(dataMap);
	const data = Object.values(dataMap);
	return new Chart(document.getElementById(ctxId), {
		type: 'doughnut',
		data: { labels, datasets: [{ data, backgroundColor: palette }] },
		options: { plugins: { legend: { position: 'bottom' } } }
	});
}

function barChart(ctxId, dataMap) {
	const labels = Object.keys(dataMap);
	const data = Object.values(dataMap);
	return new Chart(document.getElementById(ctxId), {
		type: 'bar',
		data: { labels, datasets: [{ data, backgroundColor: palette[0] }] },
		options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
	});
}

function lineChart(ctxId, labels, values) {
	return new Chart(document.getElementById(ctxId), {
		type: 'line',
		data: { labels, datasets: [{ data: values, label: 'Postings', borderColor: palette[0], backgroundColor: 'rgba(37,99,235,0.1)', fill: true, tension: 0.3 }] },
		options: { plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
	});
}

lineChart('chartTrend', trendLabels, trendValues);
barChart('chartPlatform', byPlatform);
barChart('chartProvince', byProvince);
doughnutChart('chartType', byType);
doughnutChart('chartModel', byModel);
barChart('chartEdu', byEdu);
barChart('chartIndustry', byIndustry);
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 