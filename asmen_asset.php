<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/asmen_lib.php';

if (!current_user_can('asmen_manage_assets')) { http_response_code(403); echo 'Forbidden'; exit; }

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) { http_response_code(400); echo 'Invalid asset ID'; exit; }

// Ensure QR secret and fetch asset
$secret = asmen_ensure_qr_secret($conn, $id);
$stmt = $conn->prepare('SELECT * FROM asmen_assets WHERE id=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$asset = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$asset) { http_response_code(404); echo 'Asset not found'; exit; }

// Add service history entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['service_date'], $_POST['action'])) {
	$sdate = $_POST['service_date'];
	$action = trim((string)$_POST['action']);
	$notes = trim((string)($_POST['notes'] ?? ''));
	$ins = $conn->prepare('INSERT INTO asmen_service_history (asset_id, service_date, action, notes) VALUES (?,?,?,?)');
	$ins->bind_param('isss', $id, $sdate, $action, $notes);
	$ins->execute();
	$ins->close();
	// Update last and next service
	$upd1 = $conn->prepare('UPDATE asmen_assets SET last_service_date=? WHERE id=?');
	$upd1->bind_param('si', $sdate, $id);
	$upd1->execute();
	$upd1->close();
	$sel = $conn->prepare('SELECT * FROM asmen_assets WHERE id=?');
	$sel->bind_param('i', $id);
	$sel->execute();
	$asset = $sel->get_result()->fetch_assoc();
	$sel->close();
	$plan = asmen_compute_service_plan($asset);
	$upd2 = $conn->prepare('UPDATE asmen_assets SET service_interval_months=?, next_service_date=?, service_priority=?, service_reason=? WHERE id=?');
	$upd2->bind_param('isssi', $plan['interval_months'], $plan['next_service_date'], $plan['priority'], $plan['reason'], $id);
	$upd2->execute();
	$upd2->close();
	header('Location: asmen_asset.php?id=' . $id);
	exit;
}

// Load service history
$hist = [];
$h = $conn->prepare('SELECT * FROM asmen_service_history WHERE asset_id=? ORDER BY service_date DESC, id DESC');
$h->bind_param('i', $id);
$h->execute();
$res = $h->get_result();
while ($row = $res->fetch_assoc()) { $hist[] = $row; }
$h->close();

// Compute an absolute QR link (public view)
$scheme = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(dirname($_SERVER['REQUEST_URI'] ?? '/'), '/\\');
$qrUrl = $scheme . '://' . $host . $basePath . '/asmen_qr.php?s=' . urlencode($secret);

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Asset Detail</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
    #qrContainer canvas, #qrContainer img, #qrContainer div { display: block; margin-left: auto; margin-right: auto; }
    .qr-area { min-height: 260px; display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
	<div class="d-flex justify-content-between align-items-center mb-3">
		<h2 class="mb-0">Asset Detail (ID: <?php echo $asset['id']; ?>)</h2>
		<a class="btn btn-secondary" href="asmen_assets.php">Back</a>
	</div>

	<div class="row g-3">
		<div class="col-12 col-lg-8">
			<div class="card">
				<div class="card-body">
					<h5 class="mb-3">General Info</h5>
					<div class="row g-2">
						<?php foreach ($asset as $k => $v): if (in_array($k, ['qr_secret','created_at','updated_at'], true)) continue; ?>
						<div class="col-12 col-md-6">
							<div class="text-muted small"><?php echo htmlspecialchars(ucwords(str_replace('_',' ',$k))); ?></div>
							<div><?php echo nl2br(htmlspecialchars((string)$v)); ?></div>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
			<div class="card mt-3">
				<div class="card-body">
					<h5 class="mb-3">Service History</h5>
					<form method="post" class="row g-2">
						<div class="col-12 col-md-3">
							<label class="form-label">Service Date</label>
							<input type="date" class="form-control" name="service_date" required>
						</div>
						<div class="col-12 col-md-3">
							<label class="form-label">Action</label>
							<input type="text" class="form-control" name="action" placeholder="Routine / Repair" required>
						</div>
						<div class="col-12 col-md-6">
							<label class="form-label">Notes</label>
							<input type="text" class="form-control" name="notes" placeholder="Notes">
						</div>
						<div class="col-12">
							<button class="btn btn-primary" type="submit">Add Record</button>
						</div>
					</form>
					<div class="table-responsive mt-3">
						<table class="table table-striped">
							<thead>
								<tr><th>Date</th><th>Action</th><th>Notes</th></tr>
							</thead>
							<tbody>
								<?php foreach ($hist as $row): ?>
								<tr>
									<td><?php echo htmlspecialchars($row['service_date']); ?></td>
									<td><?php echo htmlspecialchars($row['action']); ?></td>
									<td><?php echo htmlspecialchars($row['notes']); ?></td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
		<div class="col-12 col-lg-4">
			<div class="card">
				<div class="card-body text-center">
					<h5 class="mb-2">QR Code</h5>
					<div id="qrContainer" class="qr-area">
						<canvas id="qrCanvas" style="display:none"></canvas>
						<div id="qrBox" style="display:none"></div>
					</div>
					<div class="small text-muted mt-2">Scan opens public detail</div>
					<div class="mt-3 d-flex justify-content-center gap-2">
						<button id="downloadQrBtn" class="btn btn-outline-primary btn-sm" type="button">Download QR</button>
						<a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars($qrUrl); ?>" target="_blank">Open Public View</a>
					</div>
				</div>
			</div>
			<div class="card mt-3">
				<div class="card-body">
					<h6 class="mb-2">Service Plan</h6>
					<div><strong>Interval:</strong> <?php echo htmlspecialchars((string)($asset['service_interval_months'] ?? '')); ?> months</div>
					<div><strong>Next Service:</strong> <?php echo htmlspecialchars((string)($asset['next_service_date'] ?? '')); ?></div>
					<div><strong>Priority:</strong> <?php echo htmlspecialchars((string)($asset['service_priority'] ?? '')); ?></div>
					<div class="small text-muted">Reason: <?php echo htmlspecialchars((string)($asset['service_reason'] ?? '')); ?></div>
				</div>
			</div>
		</div>
	</div>
</div>
<script>
const url = '<?php echo htmlspecialchars($qrUrl); ?>';
const canvas = document.getElementById('qrCanvas');
const drawWithQRCodeLib = () => {
	try { if (window.QRCode && QRCode.toCanvas) { QRCode.toCanvas(canvas, url, { width: 220 }, function (error) { if (error) console.error(error); else { canvas.style.display='block'; document.getElementById('qrBox').style.display='none'; } }); return true; } } catch(e) { console.error(e); }
	return false;
};
const drawWithQRCodeJS = () => {
	try { if (window.QRCode && typeof QRCode === 'function') { document.getElementById('qrBox').style.display='block'; new window.QRCode(document.getElementById('qrBox'), { text: url, width: 220, height: 220 }); return true; } } catch(e) { console.error(e); }
	return false;
};

if (!drawWithQRCodeLib()) { drawWithQRCodeJS(); }

// Download QR
document.getElementById('downloadQrBtn').addEventListener('click', function() {
	// Prefer canvas, else try to snapshot generated img inside qrBox
	if (canvas && canvas.style.display !== 'none') {
		const a = document.createElement('a');
		a.href = canvas.toDataURL('image/png');
		a.download = 'asset_qr_<?php echo (int)$asset['id']; ?>.png';
		a.click();
		return;
	}
	const qrBox = document.getElementById('qrBox');
	const img = qrBox ? qrBox.querySelector('img') : null;
	if (img && img.src) {
		const a = document.createElement('a');
		a.href = img.src;
		a.download = 'asset_qr_<?php echo (int)$asset['id']; ?>.png';
		a.click();
		return;
	}
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


