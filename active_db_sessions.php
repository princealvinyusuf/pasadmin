<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';

if (!current_user_can('view_db_sessions') && !current_user_can('manage_settings')) { http_response_code(403); echo 'Forbidden'; exit; }

$currentId = 0;
try { $currentId = $conn->thread_id; } catch (Throwable $e) { $currentId = 0; }

$action = $_POST['action'] ?? '';
if ($action === 'kill' && isset($_POST['id'])) {
    if (!current_user_can('kill_db_session') && !current_user_can('manage_settings')) { http_response_code(403); echo 'Forbidden'; exit; }
    $killId = intval($_POST['id']);
    if ($killId === $currentId) {
        $error = 'Refusing to kill current session ID ' . $killId;
    } else {
        try {
            $conn->query('KILL ' . $killId);
            $message = 'Killed session ID ' . $killId;
        } catch (Throwable $e) {
            $error = 'Failed to kill session ID ' . $killId . ': ' . $e->getMessage();
        }
    }
}

// Filters
$userFilter = trim($_GET['user'] ?? '');
$hostFilter = trim($_GET['host'] ?? '');
$dbFilter = trim($_GET['db'] ?? '');
$commandFilter = trim($_GET['command'] ?? '');
$stateFilter = trim($_GET['state'] ?? '');
$textFilter = trim($_GET['text'] ?? '');

// Try INFORMATION_SCHEMA first (requires appropriate privileges)
$rows = [];
try {
    $conditions = [];
    $params = [];
    $types = '';
    if ($userFilter !== '') { $conditions[] = 'USER LIKE ?'; $params[] = '%' . $userFilter . '%'; $types .= 's'; }
    if ($hostFilter !== '') { $conditions[] = 'HOST LIKE ?'; $params[] = '%' . $hostFilter . '%'; $types .= 's'; }
    if ($dbFilter !== '') { $conditions[] = 'DB LIKE ?'; $params[] = '%' . $dbFilter . '%'; $types .= 's'; }
    if ($commandFilter !== '') { $conditions[] = 'COMMAND LIKE ?'; $params[] = '%' . $commandFilter . '%'; $types .= 's'; }
    if ($stateFilter !== '') { $conditions[] = 'STATE LIKE ?'; $params[] = '%' . $stateFilter . '%'; $types .= 's'; }
    if ($textFilter !== '') { $conditions[] = 'INFO LIKE ?'; $params[] = '%' . $textFilter . '%'; $types .= 's'; }
    $where = count($conditions) > 0 ? ('WHERE ' . implode(' AND ', $conditions)) : '';
    $sql = 'SELECT ID, USER, HOST, DB, COMMAND, TIME, STATE, INFO FROM INFORMATION_SCHEMA.PROCESSLIST ' . $where . ' ORDER BY TIME DESC';
    $stmt = $conn->prepare($sql);
    if ($where !== '') { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    $stmt->close();
} catch (Throwable $e) {
    // Fallback to SHOW FULL PROCESSLIST (no filtering server-side)
    try {
        $res = $conn->query('SHOW FULL PROCESSLIST');
        while ($r = $res->fetch_assoc()) { $rows[] = $r; }
    } catch (Throwable $e2) {
        $error = 'Failed to read processlist: ' . $e2->getMessage();
    }
}

// Client-side filter if fallback used
if (!empty($rows) && ($userFilter !== '' || $hostFilter !== '' || $dbFilter !== '' || $commandFilter !== '' || $stateFilter !== '' || $textFilter !== '')) {
    $rows = array_values(array_filter($rows, function($r) use ($userFilter,$hostFilter,$dbFilter,$commandFilter,$stateFilter,$textFilter) {
        $ok = true;
        if ($userFilter !== '') { $ok = $ok && stripos($r['User'] ?? ($r['USER'] ?? ''), $userFilter) !== false; }
        if ($hostFilter !== '') { $ok = $ok && stripos($r['Host'] ?? ($r['HOST'] ?? ''), $hostFilter) !== false; }
        if ($dbFilter !== '') { $ok = $ok && stripos((string)($r['db'] ?? ($r['DB'] ?? '')), $dbFilter) !== false; }
        if ($commandFilter !== '') { $ok = $ok && stripos($r['Command'] ?? ($r['COMMAND'] ?? ''), $commandFilter) !== false; }
        if ($stateFilter !== '') { $ok = $ok && stripos((string)($r['State'] ?? ($r['STATE'] ?? '')), $stateFilter) !== false; }
        if ($textFilter !== '') { $ok = $ok && stripos((string)($r['Info'] ?? ($r['INFO'] ?? '')), $textFilter) !== false; }
        return $ok;
    }));
}

// Normalize keys for display
$norm = [];
foreach ($rows as $r) {
    $norm[] = [
        'ID' => $r['Id'] ?? $r['ID'] ?? null,
        'USER' => $r['User'] ?? $r['USER'] ?? '',
        'HOST' => $r['Host'] ?? $r['HOST'] ?? '',
        'DB' => $r['db'] ?? $r['DB'] ?? '',
        'COMMAND' => $r['Command'] ?? $r['COMMAND'] ?? '',
        'TIME' => intval($r['Time'] ?? $r['TIME'] ?? 0),
        'STATE' => $r['State'] ?? $r['STATE'] ?? '',
        'INFO' => $r['Info'] ?? $r['INFO'] ?? ''
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Active DB Sessions</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<style>
		body { background: #f6f8fa; }
		.table thead th { background: #f1f5f9; }
		code.sql { white-space: pre-wrap; }
	</style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
	<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
		<h2 class="mb-2 mb-md-0">Active DB Sessions</h2>
	</div>

	<?php if (!empty($message)): ?>
	<div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
	<?php endif; ?>
	<?php if (!empty($error)): ?>
	<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
	<?php endif; ?>

	<div class="card mb-3">
		<div class="card-body">
			<form class="row g-3" method="get" action="active_db_sessions.php">
				<div class="col-12 col-md-2">
					<label class="form-label">User</label>
					<input class="form-control" type="text" name="user" value="<?php echo htmlspecialchars($userFilter); ?>">
				</div>
				<div class="col-12 col-md-2">
					<label class="form-label">Host</label>
					<input class="form-control" type="text" name="host" value="<?php echo htmlspecialchars($hostFilter); ?>">
				</div>
				<div class="col-12 col-md-2">
					<label class="form-label">DB</label>
					<input class="form-control" type="text" name="db" value="<?php echo htmlspecialchars($dbFilter); ?>">
				</div>
				<div class="col-12 col-md-2">
					<label class="form-label">Command</label>
					<input class="form-control" type="text" name="command" value="<?php echo htmlspecialchars($commandFilter); ?>">
				</div>
				<div class="col-12 col-md-2">
					<label class="form-label">State</label>
					<input class="form-control" type="text" name="state" value="<?php echo htmlspecialchars($stateFilter); ?>">
				</div>
				<div class="col-12 col-md-2">
					<label class="form-label">Text contains</label>
					<input class="form-control" type="text" name="text" value="<?php echo htmlspecialchars($textFilter); ?>">
				</div>
				<div class="col-12">
					<button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Filter</button>
					<a class="btn btn-secondary" href="active_db_sessions.php">Reset</a>
				</div>
			</form>
		</div>
	</div>

	<div class="card">
		<div class="table-responsive">
			<table class="table table-striped mb-0">
				<thead>
					<tr>
						<th>Actions</th>
						<th>ID</th>
						<th>User</th>
						<th>Host</th>
						<th>DB</th>
						<th>Command</th>
						<th>Time (s)</th>
						<th>State</th>
						<th style="min-width: 320px;">Info</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($norm as $row): ?>
					<tr>
						<td>
							<?php if ((current_user_can('kill_db_session') || current_user_can('manage_settings')) && (int)$row['ID'] !== (int)$currentId): ?>
							<form method="post" action="active_db_sessions.php" onsubmit="return confirm('Kill session ID <?php echo (int)$row['ID']; ?>?');" style="display:inline-block">
								<input type="hidden" name="action" value="kill">
								<input type="hidden" name="id" value="<?php echo (int)$row['ID']; ?>">
								<button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-x-circle"></i> Kill</button>
							</form>
							<?php endif; ?>
						</td>
						<td><?php echo htmlspecialchars((string)$row['ID']); ?></td>
						<td><?php echo htmlspecialchars((string)$row['USER']); ?></td>
						<td><?php echo htmlspecialchars((string)$row['HOST']); ?></td>
						<td><?php echo htmlspecialchars((string)$row['DB']); ?></td>
						<td><?php echo htmlspecialchars((string)$row['COMMAND']); ?></td>
						<td><?php echo (int)$row['TIME']; ?></td>
						<td><?php echo htmlspecialchars((string)$row['STATE']); ?></td>
						<td><code class="sql"><?php echo htmlspecialchars((string)$row['INFO']); ?></code></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div class="card-body">
			<div class="text-muted small">Total <?php echo count($norm); ?> sessions</div>
		</div>
	</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


