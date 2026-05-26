<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/wllp_external_storage.php';

if (!current_user_can('pasker_connect_api_manage') && !current_user_can('manage_settings')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

wllp_external_ensure_schema($conn);

$action = (string)($_POST['action'] ?? '');
if ($action === 'save_client') {
    $clientId = trim((string)($_POST['client_id'] ?? ''));
    $clientName = trim((string)($_POST['client_name'] ?? ''));
    $clientSecret = trim((string)($_POST['client_secret'] ?? ''));
    $isActive = !empty($_POST['is_active']) ? 1 : 0;
    if ($clientId !== '' && $clientName !== '' && $clientSecret !== '') {
        $stmt = $conn->prepare("
            INSERT INTO wllp_api_clients(client_id, client_name, client_secret, is_active)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                client_name = VALUES(client_name),
                client_secret = VALUES(client_secret),
                is_active = VALUES(is_active)
        ");
        $stmt->bind_param('sssi', $clientId, $clientName, $clientSecret, $isActive);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: pasker_connect_api_clients');
    exit;
}

$clients = [];
$res = $conn->query("SELECT client_id, client_name, is_active, created_at, updated_at FROM wllp_api_clients ORDER BY client_id ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $clients[] = $row;
    }
}

$logs = [];
$logRes = $conn->query("
    SELECT client_id, request_id, request_timestamp, request_method, request_path, status_code, error_code, created_at
    FROM wllp_api_request_logs
    ORDER BY id DESC
    LIMIT 100
");
if ($logRes) {
    while ($r = $logRes->fetch_assoc()) {
        $logs[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pasker Connect API Clients</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Pasker Connect API Clients</h3>
        <a class="btn btn-outline-primary btn-sm" href="karirhub_employer_prototype_pasker_connect">
            <i class="bi bi-book me-1"></i>Open Docs
        </a>
    </div>

    <div class="card mb-3">
        <div class="card-header">Create / Update Client Credential</div>
        <div class="card-body">
            <form method="post" class="row g-2 align-items-end">
                <input type="hidden" name="action" value="save_client">
                <div class="col-12 col-md-3">
                    <label class="form-label">Client-Id</label>
                    <input type="text" name="client_id" class="form-control" placeholder="portal-abc" required>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">Client Name</label>
                    <input type="text" name="client_name" class="form-control" placeholder="Portal ABC" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Client Secret</label>
                    <input type="text" name="client_secret" class="form-control" placeholder="secret" required>
                </div>
                <div class="col-6 col-md-1">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>
                </div>
                <div class="col-6 col-md-1 d-grid">
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Registered Clients</div>
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Client-Id</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars((string)$client['client_id']); ?></code></td>
                        <td><?php echo htmlspecialchars((string)$client['client_name']); ?></td>
                        <td><?php echo (int)$client['is_active'] === 1 ? '<span class="badge bg-success">active</span>' : '<span class="badge bg-secondary">inactive</span>'; ?></td>
                        <td><?php echo htmlspecialchars((string)$client['created_at']); ?></td>
                        <td><?php echo htmlspecialchars((string)$client['updated_at']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($clients)): ?>
                    <tr><td colspan="5" class="text-center text-muted">No client credentials yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Latest API Requests (Replay Monitoring)</div>
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>Client-Id</th>
                        <th>Request-Id</th>
                        <th>Method</th>
                        <th>Path</th>
                        <th>Status</th>
                        <th>Error Code</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars((string)$log['client_id']); ?></code></td>
                        <td><code><?php echo htmlspecialchars((string)$log['request_id']); ?></code></td>
                        <td><?php echo htmlspecialchars((string)$log['request_method']); ?></td>
                        <td><code><?php echo htmlspecialchars((string)$log['request_path']); ?></code></td>
                        <td><?php echo (int)$log['status_code']; ?></td>
                        <td><?php echo htmlspecialchars((string)($log['error_code'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string)$log['created_at']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="7" class="text-center text-muted">No API request logs yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>

