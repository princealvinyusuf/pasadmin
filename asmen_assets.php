<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/asmen_lib.php';

if (!current_user_can('asmen_manage_assets')) { http_response_code(403); echo 'Forbidden'; exit; }

function to_label(string $field): string {
	$label = str_replace('_', ' ', $field);
	$label = ucwords($label);
	return $label;
}

// Discover columns dynamically from DB
$columns = [];
$rawCols = [];
if (!$result = $conn->query('SHOW COLUMNS FROM asmen_assets')) {
	die('Failed to read asmen_assets schema');
}
while ($c = $result->fetch_assoc()) { $rawCols[] = $c; }

$hiddenFields = ['id','qr_secret','created_at','updated_at'];
foreach ($rawCols as $col) {
	$field = $col['Field'];
	if ($field === 'id') { continue; }
	$type = strtolower($col['Type']);
	$inputType = 'text';
	if (strpos($type, 'timestamp') !== false || strpos($type, 'datetime') !== false) {
		$inputType = 'datetime-local';
	} elseif (strpos($type, 'date') !== false) {
		$inputType = 'date';
	} elseif (strpos($type, 'text') !== false) {
		$inputType = 'textarea';
	} elseif (strpos($type, 'int') !== false || strpos($type, 'decimal') !== false) {
		$inputType = 'number';
	}
	$columns[$field] = ['label' => to_label($field), 'type' => $inputType, 'show' => !in_array($field, $hiddenFields, true)];
}

function sanitize_like(string $s): string { return '%' . str_replace(['%', '_'], ['\\%','\\_'], $s) . '%'; }

$action = $_GET['action'] ?? '';

// Delete
if ($action === 'delete' && isset($_GET['id'])) {
	$id = intval($_GET['id']);
	$stmt = $conn->prepare('DELETE FROM asmen_assets WHERE id=?');
	$stmt->bind_param('i', $id);
	$stmt->execute();
	header('Location: asmen_assets.php');
	exit;
}

// Create/Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$fields = array_keys($columns);
	$fieldsForSql = [];
	$values = [];
	$types = '';
	foreach ($fields as $f) {
		if ($f === 'qr_secret' || $f === 'created_at' || $f === 'updated_at') { continue; }
		$fieldsForSql[] = $f;
		$values[] = $_POST[$f] ?? null;
		$types .= 's';
	}

	if (isset($_POST['id']) && $_POST['id'] !== '') {
		$id = intval($_POST['id']);
		$set = [];
		foreach ($fieldsForSql as $f) { $set[] = "$f=?"; }
		$sql = 'UPDATE asmen_assets SET ' . implode(', ', $set) . ' WHERE id=?';
		$stmt = $conn->prepare($sql);
		$stmt->bind_param($types . 'i', ...array_merge($values, [$id]));
		$stmt->execute();

		// Recompute service plan
		$sel = $conn->prepare('SELECT * FROM asmen_assets WHERE id=?');
		$sel->bind_param('i', $id);
		$sel->execute();
		$asset = $sel->get_result()->fetch_assoc();
		$sel->close();
		$plan = asmen_compute_service_plan($asset);
		$upd = $conn->prepare('UPDATE asmen_assets SET service_interval_months=?, next_service_date=?, service_priority=?, service_reason=? WHERE id=?');
		$upd->bind_param('isssi', $plan['interval_months'], $plan['next_service_date'], $plan['priority'], $plan['reason'], $id);
		$upd->execute();
		$upd->close();
	} else {
		$placeholders = implode(', ', array_fill(0, count($fieldsForSql), '?'));
		$sql = 'INSERT INTO asmen_assets (' . implode(', ', $fieldsForSql) . ") VALUES ($placeholders)";
		$stmt = $conn->prepare($sql);
		$stmt->bind_param($types, ...$values);
		$stmt->execute();
		$newId = $stmt->insert_id;
		$secret = asmen_ensure_qr_secret($conn, $newId);
		$sel = $conn->prepare('SELECT * FROM asmen_assets WHERE id=?');
		$sel->bind_param('i', $newId);
		$sel->execute();
		$asset = $sel->get_result()->fetch_assoc();
		$sel->close();
		$plan = asmen_compute_service_plan($asset);
		$upd = $conn->prepare('UPDATE asmen_assets SET service_interval_months=?, next_service_date=?, service_priority=?, service_reason=? WHERE id=?');
		$upd->bind_param('isssi', $plan['interval_months'], $plan['next_service_date'], $plan['priority'], $plan['reason'], $newId);
		$upd->execute();
		$upd->close();
	}
	header('Location: asmen_assets.php');
	exit;
}

// Searchable fields
$preferredSearch = ['nama_barang','kode_barang','nup','no_polisi','kode_register','nama_satker','provinsi','kab_kota'];
$searchable = array_values(array_intersect($preferredSearch, array_keys($columns)));
if (count($searchable) === 0) { $searchable = array_slice(array_keys($columns), 0, min(5, count($columns))); }

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === '1') {
	$search = trim($_GET['search'] ?? '');
	$where = '';
	$params = [];
	$types = '';
	if ($search !== '') {
		$like = sanitize_like($search);
		$or = [];
		foreach ($searchable as $f) { $or[] = "$f LIKE ?"; $params[] = $like; $types .= 's'; }
		$where = 'WHERE ' . implode(' OR ', $or);
	}
	$sql = 'SELECT * FROM asmen_assets ' . $where . ' ORDER BY id DESC';
	$stmt = $conn->prepare($sql);
	if ($where !== '') { $stmt->bind_param($types, ...$params); }
	$stmt->execute();
	$res = $stmt->get_result();
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename="asmen_assets_' . date('Ymd_His') . '.csv"');
	$out = fopen('php://output', 'w');
	$header = array_keys($res->fetch_assoc() ?: []);
	if ($header) { fputcsv($out, $header); }
	$res->data_seek(0);
	while ($row = $res->fetch_assoc()) { fputcsv($out, $row); }
	fclose($out);
	exit;
}

// List with pagination and search
$perPage = 25;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$search = trim($_GET['search'] ?? '');
$where = '';
$params = [];
$types = '';
if ($search !== '') {
	$like = sanitize_like($search);
	$or = [];
	foreach ($searchable as $f) { $or[] = "$f LIKE ?"; $params[] = $like; $types .= 's'; }
	$where = 'WHERE ' . implode(' OR ', $or);
}

// Count total
$sqlCount = 'SELECT COUNT(*) FROM asmen_assets ' . $where;
$stmt = $conn->prepare($sqlCount);
if ($where !== '') { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();
$total = intval($total);
$totalPages = max(1, (int)ceil($total / $perPage));

// Fetch page
$selectFields = array_keys(array_filter($columns, fn($m, $k) => true, ARRAY_FILTER_USE_BOTH));
$sql = 'SELECT id, ' . implode(', ', $selectFields) . ' FROM asmen_assets ' . $where . ' ORDER BY id DESC LIMIT ? OFFSET ?';
$stmt = $conn->prepare($sql);
if ($where !== '') {
	$bindParams = array_merge($params, [$perPage, $offset]);
	$stmt->bind_param($types . 'ii', ...$bindParams);
} else {
	$stmt->bind_param('ii', $perPage, $offset);
}
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
	<title>AsMen Assets</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<style>
		body { background: #f6f8fa; }
		.table thead th { background: #f1f5f9; }
		textarea.form-control { min-height: 72px; }
		.sticky-actions { position: sticky; left: 0; background: #fff; }
	</style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
	<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
		<h2 class="mb-2 mb-md-0">AsMen - Assets</h2>
		<div class="d-flex gap-2">
			<form class="d-flex" method="get" action="asmen_assets.php">
				<input class="form-control me-2" type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
				<button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
			</form>
			<a class="btn btn-success" href="asmen_assets.php?export=1<?php echo $search !== '' ? '&search=' . urlencode($search) : '';?>"><i class="bi bi-file-earmark-excel"></i> Export</a>
		</div>
	</div>

	<div class="card mb-4">
		<div class="card-body">
			<form method="post">
				<div class="row g-3">
					<?php foreach ($columns as $name => $meta): if (in_array($name, ['qr_secret','created_at','updated_at'], true)) continue; ?>
					<div class="col-12 col-md-6 col-lg-4">
						<label class="form-label"><?php echo htmlspecialchars($meta['label']); ?></label>
						<?php if ($meta['type'] === 'textarea'): ?>
							<textarea class="form-control" name="<?php echo $name; ?>"></textarea>
						<?php else: ?>
							<input class="form-control" type="<?php echo $meta['type']; ?>" name="<?php echo $name; ?>">
						<?php endif; ?>
					</div>
					<?php endforeach; ?>
				</div>
				<div class="mt-3"><button class="btn btn-primary" type="submit">Add</button></div>
			</form>
		</div>
	</div>

	<div class="card">
		<div class="table-responsive">
			<table class="table table-striped mb-0">
				<thead>
					<tr>
						<th class="sticky-actions">Actions</th>
						<th>ID</th>
						<?php foreach ($columns as $name => $meta): if (!$meta['show']) continue; ?>
						<th><?php echo htmlspecialchars($meta['label']); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($rows as $row): ?>
					<tr>
						<td class="sticky-actions">
							<a class="btn btn-sm btn-outline-primary me-1" href="asmen_assets.php?action=edit&id=<?php echo $row['id']; ?>#edit">Edit</a>
							<a class="btn btn-sm btn-outline-danger me-1" href="asmen_assets.php?action=delete&id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this asset?');">Delete</a>
							<a class="btn btn-sm btn-outline-success" href="asmen_asset.php?id=<?php echo $row['id']; ?>">Detail</a>
						</td>
						<td><?php echo $row['id']; ?></td>
						<?php foreach ($columns as $name => $meta): if (!$meta['show']) continue; ?>
						<td><?php echo nl2br(htmlspecialchars((string)($row[$name] ?? ''))); ?></td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div class="card-body">
			<nav>
				<ul class="pagination mb-0">
					<?php $base = 'asmen_assets.php?search=' . urlencode($search) . '&page='; ?>
					<li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
						<a class="page-link" href="<?php echo $page <= 1 ? '#' : $base . ($page - 1); ?>">Prev</a>
					</li>
					<?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
					<li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
						<a class="page-link" href="<?php echo $base . $p; ?>"><?php echo $p; ?></a>
					</li>
					<?php endfor; ?>
					<li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
						<a class="page-link" href="<?php echo $page >= $totalPages ? '#' : $base . ($page + 1); ?>">Next</a>
					</li>
				</ul>
				<div class="text-muted small mt-2">Page <?php echo $page; ?> of <?php echo $totalPages; ?> (Total <?php echo $total; ?> records)</div>
			</nav>
		</div>
	</div>

	<?php if ($action === 'edit' && isset($_GET['id'])):
		$id = intval($_GET['id']);
		$stmt = $conn->prepare('SELECT * FROM asmen_assets WHERE id=?');
		$stmt->bind_param('i', $id);
		$stmt->execute();
		$editRow = $stmt->get_result()->fetch_assoc();
	?>
	<div id="edit" class="card mt-4">
		<div class="card-body">
			<h5 class="mb-3">Edit Asset (ID: <?php echo $id; ?>)</h5>
			<form method="post">
				<input type="hidden" name="id" value="<?php echo $id; ?>">
				<div class="row g-3">
					<?php foreach ($columns as $name => $meta): if (in_array($name, ['qr_secret','created_at','updated_at'], true)) continue; $val = $editRow[$name] ?? ''; ?>
					<div class="col-12 col-md-6 col-lg-4">
						<label class="form-label"><?php echo htmlspecialchars($meta['label']); ?></label>
						<?php if ($meta['type'] === 'textarea'): ?>
							<textarea class="form-control" name="<?php echo $name; ?>"><?php echo htmlspecialchars($val); ?></textarea>
						<?php else: ?>
							<?php if ($meta['type'] === 'datetime-local' && $val !== '') { $val = str_replace(' ', 'T', substr($val, 0, 16)); } ?>
							<input class="form-control" type="<?php echo $meta['type']; ?>" name="<?php echo $name; ?>" value="<?php echo htmlspecialchars($val); ?>">
						<?php endif; ?>
					</div>
					<?php endforeach; ?>
				</div>
				<div class="mt-3">
					<button class="btn btn-primary" type="submit">Update</button>
					<a class="btn btn-secondary" href="asmen_assets.php">Cancel</a>
				</div>
			</form>
		</div>
	</div>
	<?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

