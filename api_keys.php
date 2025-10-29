<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';

if (!(current_user_can('manage_api_keys') || current_user_can('manage_settings'))) { http_response_code(403); echo 'Forbidden'; exit; }

// Ensure api_keys table exists
$conn->query("CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    api_key VARCHAR(128) NOT NULL UNIQUE,
    scopes VARCHAR(255) NOT NULL DEFAULT 'job_seekers_read',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NULL DEFAULT NULL,
    expires_at DATETIME NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure expires_at exists on older installs (ignore error if already exists)
try { $conn->query("ALTER TABLE api_keys ADD COLUMN expires_at DATETIME NULL DEFAULT NULL"); } catch (Throwable $e) {}

function generate_api_key(): string {
    return bin2hex(random_bytes(32)); // 64 hex chars
}

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $name = trim($_POST['name'] ?? '');
    $scopes = ['job_seekers_read']; // fixed for now
    $apiKey = generate_api_key();
    $expiresAt = trim($_POST['expires_at'] ?? '');
    if ($expiresAt !== '') { $expiresAt = str_replace('T', ' ', substr($expiresAt, 0, 19)); }
    if ($name !== '') {
        $stmt = $conn->prepare('INSERT INTO api_keys (name, api_key, scopes, is_active, expires_at) VALUES (?, ?, ?, 1, ?)');
        $scopesCsv = implode(',', $scopes);
        $stmt->bind_param('ssss', $name, $apiKey, $scopesCsv, $expiresAt);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: api_keys.php');
    exit;
}

if ($action === 'toggle') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        $conn->query('UPDATE api_keys SET is_active = IF(is_active=1,0,1) WHERE id=' . $id);
    }
    header('Location: api_keys.php');
    exit;
}

if ($action === 'regenerate') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        $newKey = generate_api_key();
        $stmt = $conn->prepare('UPDATE api_keys SET api_key=? WHERE id=?');
        $stmt->bind_param('si', $newKey, $id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: api_keys.php');
    exit;
}

if ($action === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare('DELETE FROM api_keys WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: api_keys.php');
    exit;
}

if ($action === 'set_expiry') {
    $id = intval($_POST['id'] ?? 0);
    $expiresAt = trim($_POST['expires_at'] ?? '');
    if ($id > 0) {
        if ($expiresAt !== '') { $expiresAt = str_replace('T', ' ', substr($expiresAt, 0, 19)); }
        $stmt = $conn->prepare('UPDATE api_keys SET expires_at=? WHERE id=?');
        $stmt->bind_param('si', $expiresAt, $id);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: api_keys.php');
    exit;
}

if ($action === 'clear_expiry') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        $conn->query('UPDATE api_keys SET expires_at=NULL WHERE id=' . $id);
    }
    header('Location: api_keys.php');
    exit;
}

// Fetch keys
$rows = [];
$res = $conn->query('SELECT id, name, api_key, scopes, is_active, created_at, last_used_at, expires_at FROM api_keys ORDER BY id DESC');
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>API Keys</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<style>
		body { background: #f6f8fa; }
		.key-mask { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
	</style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
	<div class="d-flex justify-content-between align-items-center mb-3">
		<h2 class="mb-0">API Key Job Seekers</h2>
	</div>

	<div class="card mb-4">
		<div class="card-body">
			<h5 class="mb-3">Create API Key</h5>
			<form method="post" class="row g-2 align-items-end">
				<input type="hidden" name="action" value="create">
				<div class="col-12 col-md-6">
					<label class="form-label">Name</label>
					<input class="form-control" type="text" name="name" placeholder="e.g. Reporting Integration" required>
				</div>
				<div class="col-12 col-md-6">
					<label class="form-label">Expires At (optional)</label>
					<input class="form-control" type="datetime-local" name="expires_at" placeholder="YYYY-MM-DDTHH:MM">
				</div>
				<div class="col-12 col-md-6">
					<label class="form-label">Scope</label>
					<input class="form-control" type="text" value="job_seekers_read" disabled>
					<div class="form-text">This key can read all data from job_seekers table via API.</div>
				</div>
				<div class="col-12">
					<button class="btn btn-primary" type="submit"><i class="bi bi-key me-1"></i>Create Key</button>
				</div>
			</form>
		</div>
	</div>

	<div class="card">
		<div class="table-responsive">
			<table class="table table-striped mb-0">
				<thead>
					<tr>
						<th>ID</th>
						<th>Name</th>
						<th>API Key</th>
						<th>Scope</th>
						<th>Status</th>
						<th>Created</th>
						<th>Expires</th>
						<th>Last Used</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($rows as $r): ?>
					<tr>
						<td><?php echo intval($r['id']); ?></td>
						<td><?php echo htmlspecialchars($r['name']); ?></td>
						<td class="key-mask"><code><?php echo htmlspecialchars($r['api_key']); ?></code></td>
						<td><span class="badge bg-secondary">job_seekers_read</span></td>
						<td>
							<?php
								$expired = false;
								if (!empty($r['expires_at'])) { $expired = strtotime($r['expires_at']) <= time(); }
								echo intval($r['is_active']) ? ($expired ? '<span class="badge bg-warning text-dark">expired</span>' : '<span class="badge bg-success">active</span>') : '<span class="badge bg-secondary">inactive</span>';
							?>
						</td>
						<td><?php echo htmlspecialchars($r['created_at']); ?></td>
						<td><?php echo htmlspecialchars($r['expires_at'] ?? ''); ?></td>
						<td><?php echo htmlspecialchars($r['last_used_at'] ?? ''); ?></td>
						<td>
							<form method="post" class="d-inline">
								<input type="hidden" name="action" value="toggle">
								<input type="hidden" name="id" value="<?php echo intval($r['id']); ?>">
								<button class="btn btn-sm btn-outline-secondary" type="submit"><?php echo intval($r['is_active']) ? 'Deactivate' : 'Activate'; ?></button>
							</form>
							<form method="post" class="d-inline" onsubmit="return confirm('Regenerate this API key? Existing clients will need the new key.');">
								<input type="hidden" name="action" value="regenerate">
								<input type="hidden" name="id" value="<?php echo intval($r['id']); ?>">
								<button class="btn btn-sm btn-outline-primary" type="submit">Regenerate</button>
							</form>
							<form method="post" class="d-inline">
								<input type="hidden" name="action" value="set_expiry">
								<input type="hidden" name="id" value="<?php echo intval($r['id']); ?>">
								<input type="datetime-local" class="form-control d-inline w-auto" name="expires_at" value="<?php echo !empty($r['expires_at']) ? str_replace(' ', 'T', substr($r['expires_at'], 0, 16)) : '';?>">
								<button class="btn btn-sm btn-outline-success" type="submit">Save Expiry</button>
							</form>
							<form method="post" class="d-inline" onsubmit="return confirm('Clear expiry for this key?');">
								<input type="hidden" name="action" value="clear_expiry">
								<input type="hidden" name="id" value="<?php echo intval($r['id']); ?>">
								<button class="btn btn-sm btn-outline-warning" type="submit">Clear Expiry</button>
							</form>
							<form method="post" class="d-inline" onsubmit="return confirm('Delete this API key?');">
								<input type="hidden" name="action" value="delete">
								<input type="hidden" name="id" value="<?php echo intval($r['id']); ?>">
								<button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
							</form>
						</td>
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


