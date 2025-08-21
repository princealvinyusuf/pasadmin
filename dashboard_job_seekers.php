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
	$allowed = [
		'jenis_kelamin','status_bekerja','pendidikan','provinsi','kelompok_umur','status_profil','status_pencaker','jenis_disabilitas'
	];
	if (!in_array($field, $allowed, true)) {
		return [];
	}
	list($where, $types, $params) = buildDateWhere($start, $end);
	$sql = "SELECT IFNULL($field, 'Unknown') AS label, COUNT(*) AS cnt FROM job_seekers $where GROUP BY label ORDER BY cnt DESC" . ($limit ? ' LIMIT ' . intval($limit) : '');
	$stmt = $conn->prepare($sql);
	if ($types !== '') { $stmt->bind_param($types, ...$params); }
	$stmt->execute();
	$res = $stmt->get_result();
	$data = [];
	while ($r = $res->fetch_assoc()) { $data[$r['label'] === '' ? 'Unknown' : $r['label']] = intval($r['cnt']); }
	$stmt->close();
	return $data;
}

// Totals
list($whereAll, $typesAll, $paramsAll) = buildDateWhere($start, $end);
$totalFiltered = fetchScalar($conn, 'SELECT COUNT(*) FROM job_seekers ' . $whereAll, $typesAll, $paramsAll);
$totalAll = fetchScalar($conn, 'SELECT COUNT(*) FROM job_seekers');

// New this month (ignores filter for clarity)
$firstDay = date('Y-m-01');
$lastDay = date('Y-m-t');
$totalThisMonth = fetchScalar($conn,
	'SELECT COUNT(*) FROM job_seekers WHERE DATE(COALESCE(created_date, tanggal_daftar)) BETWEEN ? AND ?',
	'ss', [$firstDay, $lastDay]
);

// With certification (non-empty)
list($whereCert, $typesCert, $paramsCert) = buildDateWhere($start, $end);
$whereCert .= ($whereCert === '' ? 'WHERE ' : ' AND ') . "TRIM(IFNULL(sertifikasi, '')) <> ''";
$withCertification = fetchScalar($conn, 'SELECT COUNT(*) FROM job_seekers ' . $whereCert, $typesCert, $paramsCert);

// With disability (non-empty)
list($whereDis, $typesDis, $paramsDis) = buildDateWhere($start, $end);
$whereDis .= ($whereDis === '' ? 'WHERE ' : ' AND ') . "TRIM(IFNULL(jenis_disabilitas, '')) <> ''";
$withDisability = fetchScalar($conn, 'SELECT COUNT(*) FROM job_seekers ' . $whereDis, $typesDis, $paramsDis);

// Unique provinces
$uniqueProv = fetchScalar($conn, 'SELECT COUNT(DISTINCT provinsi) FROM job_seekers ' . $whereAll, $typesAll, $paramsAll);

// Grouped data
$byGender = fetchGroupCounts($conn, 'jenis_kelamin', $start, $end, null);
$byWorkStatus = fetchGroupCounts($conn, 'status_bekerja', $start, $end, null);
$byEducation = fetchGroupCounts($conn, 'pendidikan', $start, $end, 8);
$byProvince = fetchGroupCounts($conn, 'provinsi', $start, $end, 10);
$byAgeGroup = fetchGroupCounts($conn, 'kelompok_umur', $start, $end, null);
$byPencaker = fetchGroupCounts($conn, 'status_pencaker', $start, $end, null);

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Dashboard Job Seekers</title>
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
		<h2 class="mb-2 mb-md-0">Dashboard Job Seekers</h2>
		<form class="d-flex gap-2" method="get" action="dashboard_job_seekers.php">
			<input type="date" class="form-control" name="start" value="<?php echo htmlspecialchars($start ?? ''); ?>" placeholder="Start date">
			<input type="date" class="form-control" name="end" value="<?php echo htmlspecialchars($end ?? ''); ?>" placeholder="End date">
			<button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
			<a class="btn btn-outline-secondary" href="dashboard_job_seekers.php">Reset</a>
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
					<div class="text-muted">With Certification</div>
					<div class="display-6"><?php echo number_format($withCertification); ?></div>
				</div>
			</div>
		</div>
		<div class="col-12 col-md-6 col-xl-3">
			<div class="card shadow-sm">
				<div class="card-body">
					<div class="text-muted">With Disability Info</div>
					<div class="display-6"><?php echo number_format($withDisability); ?></div>
					<div class="text-muted small">Unique Provinces: <?php echo number_format($uniqueProv); ?></div>
				</div>
			</div>
		</div>
	</div>

	<div class="row g-3">
		<div class="col-12 col-xl-6">
			<div class="card chart-card shadow-sm">
				<div class="card-body">
					<h5 class="card-title mb-3">Gender Distribution</h5>
					<canvas id="chartGender"></canvas>
				</div>
			</div>
		</div>
		<div class="col-12 col-xl-6">
			<div class="card chart-card shadow-sm">
				<div class="card-body">
					<h5 class="card-title mb-3">Working Status</h5>
					<canvas id="chartWork"></canvas>
				</div>
			</div>
		</div>
	</div>

	<div class="row g-3 mt-1">
		<div class="col-12 col-xl-6">
			<div class="card chart-card shadow-sm">
				<div class="card-body">
					<h5 class="card-title mb-3">Top Education</h5>
					<canvas id="chartEdu"></canvas>
				</div>
			</div>
		</div>
		<div class="col-12 col-xl-6">
			<div class="card chart-card shadow-sm">
				<div class="card-body">
					<h5 class="card-title mb-3">Top Provinces</h5>
					<canvas id="chartProv"></canvas>
				</div>
			</div>
		</div>
	</div>

	<div class="row g-3 mt-1">
		<div class="col-12 col-xl-6">
			<div class="card chart-card shadow-sm">
				<div class="card-body">
					<h5 class="card-title mb-3">Age Groups</h5>
					<canvas id="chartAge"></canvas>
				</div>
			</div>
		</div>
		<div class="col-12 col-xl-6">
			<div class="card chart-card shadow-sm">
				<div class="card-body">
					<h5 class="card-title mb-3">Pencaker Status</h5>
					<canvas id="chartPencaker"></canvas>
				</div>
			</div>
		</div>
	</div>
</div>

<script>
const byGender = <?php echo json_encode($byGender, JSON_UNESCAPED_UNICODE); ?>;
const byWork = <?php echo json_encode($byWorkStatus, JSON_UNESCAPED_UNICODE); ?>;
const byEdu = <?php echo json_encode($byEducation, JSON_UNESCAPED_UNICODE); ?>;
const byProv = <?php echo json_encode($byProvince, JSON_UNESCAPED_UNICODE); ?>;
const byAge = <?php echo json_encode($byAgeGroup, JSON_UNESCAPED_UNICODE); ?>;
const byPencaker = <?php echo json_encode($byPencaker, JSON_UNESCAPED_UNICODE); ?>;

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

doughnutChart('chartGender', byGender);
doughnutChart('chartWork', byWork);
barChart('chartEdu', byEdu);
barChart('chartProv', byProv);
barChart('chartAge', byAge);
barChart('chartPencaker', byPencaker);
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 