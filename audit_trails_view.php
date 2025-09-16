<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
if (!current_user_can('view_audit_trails') && !current_user_can('manage_settings')) { http_response_code(403); echo 'Forbidden'; exit; }

$conn = new mysqli('localhost','root','', 'job_admin_prod');
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$stmt = $conn->prepare('SELECT * FROM audits WHERE id=?');
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Detail</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <a href="audit_trails.php" class="btn btn-outline-secondary mb-3">Back</a>
    <?php if (!$row): ?>
        <div class="alert alert-warning">Audit not found.</div>
    <?php else: ?>
        <div class="card">
            <div class="card-header">Audit #<?php echo $row['id']; ?></div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-3">User</dt><dd class="col-sm-9"><?php echo htmlspecialchars(($row['username'] ?? '') . ' (#' . $row['user_id'] . ')'); ?></dd>
                    <dt class="col-sm-3">IP</dt><dd class="col-sm-9"><?php echo htmlspecialchars($row['ip_address'] ?? ''); ?></dd>
                    <dt class="col-sm-3">User-Agent</dt><dd class="col-sm-9"><small><?php echo htmlspecialchars($row['user_agent'] ?? ''); ?></small></dd>
                    <dt class="col-sm-3">Method</dt><dd class="col-sm-9"><?php echo htmlspecialchars($row['method'] ?? ''); ?></dd>
                    <dt class="col-sm-3">Path</dt><dd class="col-sm-9"><code><?php echo htmlspecialchars($row['path'] ?? ''); ?></code></dd>
                    <dt class="col-sm-3">Query</dt><dd class="col-sm-9"><code><?php echo htmlspecialchars($row['query_string'] ?? ''); ?></code></dd>
                    <dt class="col-sm-3">Post Data</dt><dd class="col-sm-9"><pre class="mb-0" style="white-space: pre-wrap;"><?php echo htmlspecialchars($row['post_data'] ?? ''); ?></pre></dd>
                    <dt class="col-sm-3">Time</dt><dd class="col-sm-9"><?php echo htmlspecialchars($row['created_at'] ?? ''); ?></dd>
                </dl>
            </div>
        </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


