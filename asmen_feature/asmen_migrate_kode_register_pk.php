<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../access_helper.php';

if (!current_user_can('asmen_manage_assets')) { http_response_code(403); echo 'Forbidden'; exit; }

$action = $_GET['action'] ?? '';

function fetch_all(mysqli_stmt $stmt): array {
	$stmt->execute();
	$res = $stmt->get_result();
	$rows = [];
	while ($r = $res->fetch_assoc()) { $rows[] = $r; }
	$stmt->close();
	return $rows;
}

// Checks
$nulls = $conn->query("SELECT COUNT(*) AS c FROM asmen_assets WHERE kode_register IS NULL OR kode_register = ''")->fetch_assoc()['c'] ?? 0;
$dupes = fetch_all($conn->prepare("SELECT kode_register, COUNT(*) c FROM asmen_assets GROUP BY kode_register HAVING c > 1 ORDER BY c DESC LIMIT 50"));

$canMigrate = ($nulls == 0) && (count($dupes) === 0);

if ($action === 'migrate' && $canMigrate) {
	// Perform migration: make kode_register NOT NULL PK, keep id UNIQUE so FKs remain valid
	$conn->begin_transaction();
	try {
		$conn->query("ALTER TABLE asmen_assets MODIFY kode_register VARCHAR(150) NOT NULL");
		// Ensure id is UNIQUE (in case older schema didn't set it)
		$conn->query("ALTER TABLE asmen_assets ADD UNIQUE KEY uq_asmen_id (id)");
		$conn->query("ALTER TABLE asmen_assets DROP PRIMARY KEY, ADD PRIMARY KEY (kode_register)");
		$conn->commit();
		$done = true;
	} catch (Throwable $e) {
		$conn->rollback();
		$err = $e->getMessage();
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>AsMen Migration: kode_register as PK</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include '../navbar.php'; ?>
<div class="container py-4">
	<div class="card">
		<div class="card-body">
			<h4 class="mb-3">AsMen Migration: Make kode_register the Primary Key</h4>
			<?php if (!empty($done)): ?>
			<div class="alert alert-success">Migration completed successfully.</div>
			<?php elseif (!empty($err)): ?>
			<div class="alert alert-danger">Migration failed: <?php echo htmlspecialchars($err); ?></div>
			<?php endif; ?>
			<ul class="list-group mb-3">
				<li class="list-group-item d-flex justify-content-between align-items-center">
					Assets with NULL/empty kode_register
					<span class="badge bg-<?php echo $nulls==0?'success':'danger'; ?> rounded-pill"><?php echo (int)$nulls; ?></span>
				</li>
				<li class="list-group-item">
					Duplicate kode_register (top 50 shown):
					<?php if (count($dupes) === 0): ?>
						<div class="text-success">None</div>
					<?php else: ?>
						<div class="table-responsive mt-2">
							<table class="table table-sm">
								<thead><tr><th>kode_register</th><th>count</th></tr></thead>
								<tbody>
									<?php foreach ($dupes as $d): ?>
									<tr><td><?php echo htmlspecialchars($d['kode_register']); ?></td><td><?php echo (int)$d['c']; ?></td></tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</li>
			</ul>
			<?php if ($canMigrate): ?>
				<a class="btn btn-primary" href="asmen_migrate_kode_register_pk.php?action=migrate" onclick="return confirm('Proceed with migration?');">Run Migration</a>
			<?php else: ?>
				<div class="alert alert-warning">Fix NULL/empty or duplicate kode_register values before migrating.</div>
			<?php endif; ?>
		</div>
	</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


