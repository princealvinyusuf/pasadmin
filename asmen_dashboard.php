<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/asmen_lib.php';

if (!(current_user_can('asmen_view_dashboard') || current_user_can('asmen_manage_assets'))) { http_response_code(403); echo 'Forbidden'; exit; }

function scalar(mysqli $conn, string $sql, string $types = '', array $params = []) {
	$stmt = $conn->prepare($sql);
	if ($types !== '') { $stmt->bind_param($types, ...$params); }
	$stmt->execute();
	$stmt->bind_result($val);
	$stmt->fetch();
	$stmt->close();
	return $val ?? 0;
}

$totalAssets = scalar($conn, 'SELECT COUNT(*) FROM asmen_assets');
$overdue = scalar($conn, 'SELECT COUNT(*) FROM asmen_assets WHERE next_service_date IS NOT NULL AND next_service_date < CURDATE()');
$dueToday = scalar($conn, 'SELECT COUNT(*) FROM asmen_assets WHERE next_service_date = CURDATE()');
$upcoming30 = scalar($conn, 'SELECT COUNT(*) FROM asmen_assets WHERE next_service_date > CURDATE() AND next_service_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)');

// Breakdown by kondisi
$byCondition = [];
$res = $conn->query('SELECT COALESCE(kondisi, "Unknown") kondisi, COUNT(*) cnt FROM asmen_assets GROUP BY kondisi ORDER BY cnt DESC');
while ($row = $res->fetch_assoc()) { $byCondition[] = $row; }

// Breakdown by jenis_bmn
$byJenis = [];
$res2 = $conn->query('SELECT COALESCE(jenis_bmn, "Unknown") jenis_bmn, COUNT(*) cnt FROM asmen_assets GROUP BY jenis_bmn ORDER BY cnt DESC LIMIT 10');
while ($row = $res2->fetch_assoc()) { $byJenis[] = $row; }

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>AsMen Dashboard</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
	<h2 class="mb-3">AsMen - Dashboard</h2>
	<div class="row g-3">
		<div class="col-12 col-md-6 col-lg-3">
			<div class="card text-center">
				<div class="card-body">
					<div class="text-muted small">Total Assets</div>
					<div class="display-6"><?php echo (int)$totalAssets; ?></div>
				</div>
			</div>
		</div>
		<div class="col-12 col-md-6 col-lg-3">
			<div class="card text-center">
				<div class="card-body">
					<div class="text-muted small">Overdue</div>
					<div class="display-6 text-danger"><?php echo (int)$overdue; ?></div>
				</div>
			</div>
		</div>
		<div class="col-12 col-md-6 col-lg-3">
			<div class="card text-center">
				<div class="card-body">
					<div class="text-muted small">Due Today</div>
					<div class="display-6 text-warning"><?php echo (int)$dueToday; ?></div>
				</div>
			</div>
		</div>
		<div class="col-12 col-md-6 col-lg-3">
			<div class="card text-center">
				<div class="card-body">
					<div class="text-muted small">Upcoming 30d</div>
					<div class="display-6 text-primary"><?php echo (int)$upcoming30; ?></div>
				</div>
			</div>
		</div>
	</div>

	<div class="row g-3 mt-1">
		<div class="col-12 col-lg-6">
			<div class="card">
				<div class="card-body">
					<h6 class="mb-3">Assets by Condition</h6>
					<canvas id="condChart" height="220"></canvas>
				</div>
			</div>
		</div>
		<div class="col-12 col-lg-6">
			<div class="card">
				<div class="card-body">
					<h6 class="mb-3">Top Jenis BMN</h6>
					<canvas id="jenisChart" height="220"></canvas>
				</div>
			</div>
		</div>
	</div>
</div>
<script>
const condLabels = <?php echo json_encode(array_column($byCondition, 'kondisi')); ?>;
const condData = <?php echo json_encode(array_map('intval', array_column($byCondition, 'cnt'))); ?>;
const jenisLabels = <?php echo json_encode(array_column($byJenis, 'jenis_bmn')); ?>;
const jenisData = <?php echo json_encode(array_map('intval', array_column($byJenis, 'cnt'))); ?>;

new Chart(document.getElementById('condChart'), {
	type: 'doughnut',
	data: { labels: condLabels, datasets: [{ data: condData }] }, options: { plugins: { legend: { position: 'bottom' } } }
});
new Chart(document.getElementById('jenisChart'), {
	type: 'bar',
	data: { labels: jenisLabels, datasets: [{ data: jenisData, backgroundColor: '#0d6efd' }] }, options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


