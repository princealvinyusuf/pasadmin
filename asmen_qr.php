<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/asmen_lib.php';

$secret = isset($_GET['s']) ? trim((string)$_GET['s']) : '';
if ($secret === '') { http_response_code(400); echo 'Missing QR secret'; exit; }

$stmt = $conn->prepare('SELECT * FROM asmen_assets WHERE qr_secret=?');
$stmt->bind_param('s', $secret);
$stmt->execute();
$asset = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$asset) { http_response_code(404); echo 'Asset not found'; exit; }

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Asset Info</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
	<div class="card">
		<div class="card-body">
			<h4 class="mb-3">Asset Information</h4>
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
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


