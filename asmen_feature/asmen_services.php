<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../access_helper.php';
require_once __DIR__ . '/asmen_lib.php';

if (!(current_user_can('asmen_view_services') || current_user_can('asmen_manage_assets'))) { http_response_code(403); echo 'Forbidden'; exit; }

$status = $_GET['status'] ?? 'overdue';
$days = max(1, intval($_GET['days'] ?? 30));

// Mark serviced today
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_serviced_id'])) {
	$aid = intval($_POST['mark_serviced_id']);
	$today = date('Y-m-d');
	$ins = $conn->prepare('INSERT INTO asmen_service_history (asset_id, service_date, action, notes) VALUES (?,?,"Routine","Auto-mark from Services")');
	$ins->bind_param('is', $aid, $today);
	$ins->execute();
	$ins->close();
	$upd1 = $conn->prepare('UPDATE asmen_assets SET last_service_date=? WHERE id=?');
	$upd1->bind_param('si', $today, $aid);
	$upd1->execute();
	$upd1->close();
	$sel = $conn->prepare('SELECT * FROM asmen_assets WHERE id=?');
	$sel->bind_param('i', $aid);
	$sel->execute();
	$asset = $sel->get_result()->fetch_assoc();
	$sel->close();
	$plan = asmen_compute_service_plan($asset);
	$upd2 = $conn->prepare('UPDATE asmen_assets SET service_interval_months=?, next_service_date=?, service_priority=?, service_reason=? WHERE id=?');
	$upd2->bind_param('isssi', $plan['interval_months'], $plan['next_service_date'], $plan['priority'], $plan['reason'], $aid);
	$upd2->execute();
	$upd2->close();
	header('Location: asmen_services.php?status=' . urlencode($status) . '&days=' . $days);
	exit;
}

$today = date('Y-m-d');
$params = [];
$types = '';
$where = 'WHERE next_service_date IS NOT NULL ';
if ($status === 'overdue') {
	$where .= 'AND next_service_date < ?';
	$params[] = $today; $types .= 's';
} elseif ($status === 'today') {
	$where .= 'AND next_service_date = ?';
	$params[] = $today; $types .= 's';
} elseif ($status === 'upcoming') {
	$where .= 'AND next_service_date > ? AND next_service_date <= DATE_ADD(?, INTERVAL ? DAY)';
	$params[] = $today; $types .= 's';
	$params[] = $today; $types .= 's';
	$params[] = $days; $types .= 'i';
} else {
	$where .= '';
}

$sql = 'SELECT id, nama_barang, kode_barang, nup, no_polisi, nama_satker, provinsi, kab_kota, service_priority, next_service_date FROM asmen_assets ' . $where . ' ORDER BY next_service_date ASC, service_priority DESC';
$stmt = $conn->prepare($sql);
if ($types !== '') { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>AsMen Services</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include '../navbar.php'; ?>
<div class="container py-4">
	<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
		<h2 class="mb-2 mb-md-0">AsMen - Services</h2>
		<form class="d-flex gap-2" method="get">
			<select class="form-select" name="status" style="max-width:160px">
				<option value="overdue" <?php echo $status==='overdue'?'selected':''; ?>>Overdue</option>
				<option value="today" <?php echo $status==='today'?'selected':''; ?>>Today</option>
				<option value="upcoming" <?php echo $status==='upcoming'?'selected':''; ?>>Upcoming</option>
				<option value="all" <?php echo $status==='all'?'selected':''; ?>>All</option>
			</select>
			<input type="number" class="form-control" name="days" min="1" value="<?php echo $days; ?>" style="max-width:120px" placeholder="Days">
			<button class="btn btn-primary" type="submit"><i class="bi bi-filter"></i> Filter</button>
		</form>
	</div>

	<div class="card">
		<div class="table-responsive">
			<table class="table table-striped mb-0">
				<thead>
					<tr>
						<th>Actions</th>
						<th>Next Service</th>
						<th>Priority</th>
						<th>Nama Barang</th>
						<th>Kode Barang</th>
						<th>NUP</th>
						<th>No Polisi</th>
						<th>Satker</th>
						<th>Lokasi</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($rows as $row): ?>
					<tr>
						<td>
							<form method="post" class="d-inline">
								<input type="hidden" name="mark_serviced_id" value="<?php echo $row['id']; ?>">
								<button class="btn btn-sm btn-outline-success" type="submit" onclick="return confirm('Mark as serviced today?');">Mark Serviced</button>
							</form>
							<a class="btn btn-sm btn-outline-primary" href="asmen_asset.php?id=<?php echo $row['id']; ?>">Detail</a>
						</td>
						<td><?php echo htmlspecialchars($row['next_service_date']); ?></td>
						<td><?php echo htmlspecialchars($row['service_priority']); ?></td>
						<td><?php echo htmlspecialchars($row['nama_barang']); ?></td>
						<td><?php echo htmlspecialchars($row['kode_barang']); ?></td>
						<td><?php echo htmlspecialchars($row['nup']); ?></td>
						<td><?php echo htmlspecialchars($row['no_polisi']); ?></td>
						<td><?php echo htmlspecialchars($row['nama_satker']); ?></td>
						<td><?php echo htmlspecialchars(trim(($row['provinsi'] ?? '') . ', ' . ($row['kab_kota'] ?? ''), ', ')); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


