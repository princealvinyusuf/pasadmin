<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
if (!current_user_can('view_audit_trails') && !current_user_can('manage_settings')) { http_response_code(403); echo 'Forbidden'; exit; }

// Separate connection to job_admin_prod (audits table lives here)
$conn = new mysqli('localhost','root','', 'job_admin_prod');

// Filters
$userId = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? intval($_GET['user_id']) : null;
$path = isset($_GET['path']) && $_GET['path'] !== '' ? $_GET['path'] : null;
$start = isset($_GET['start']) && $_GET['start'] !== '' ? $_GET['start'] : null; // YYYY-MM-DD
$end = isset($_GET['end']) && $_GET['end'] !== '' ? $_GET['end'] : null; // YYYY-MM-DD

$where = '';
$types = '';
$params = [];
if ($userId !== null) { $where .= ($where ? ' AND ' : ' WHERE ') . 'user_id = ?'; $types .= 'i'; $params[] = $userId; }
if ($path !== null) { $where .= ($where ? ' AND ' : ' WHERE ') . 'path LIKE ?'; $types .= 's'; $params[] = '%' . $path . '%'; }
if ($start !== null) { $where .= ($where ? ' AND ' : ' WHERE ') . 'created_at >= ?'; $types .= 's'; $params[] = $start . ' 00:00:00'; }
if ($end !== null) { $where .= ($where ? ' AND ' : ' WHERE ') . 'created_at <= ?'; $types .= 's'; $params[] = $end . ' 23:59:59'; }

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$pageSize = 50;
$offset = ($page - 1) * $pageSize;

// Count total
$countSql = 'SELECT COUNT(*) FROM audits' . $where;
$countStmt = $conn->prepare($countSql);
if ($types !== '') { $countStmt->bind_param($types, ...$params); }
$countStmt->execute();
$countStmt->bind_result($totalRows);
$countStmt->fetch();
$countStmt->close();

// Fetch rows
$sql = 'SELECT id, user_id, username, ip_address, method, path, created_at FROM audits' . $where . ' ORDER BY id DESC LIMIT ?, ?';
$types2 = $types . 'ii';
$params2 = $params;
$params2[] = $offset;
$params2[] = $pageSize;
$stmt = $conn->prepare($sql);
if ($types !== '') { $stmt->bind_param($types2, ...$params2); } else { $stmt->bind_param('ii', $offset, $pageSize); }
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
$stmt->close();

// Compute pagination info
$totalPages = max(1, (int)ceil($totalRows / $pageSize));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Trails</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f6f8fa; }
        table { background: #fff; border-radius: 10px; overflow: hidden; }
        th { background: #f1f5f9; }
    </style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h2 class="mb-0">Audit Trails</h2>
    </div>

    <form class="row g-2 mb-3" method="get">
        <div class="col-12 col-md-2">
            <input type="number" class="form-control" name="user_id" placeholder="User ID" value="<?php echo htmlspecialchars($userId ?? ''); ?>">
        </div>
        <div class="col-12 col-md-3">
            <input type="text" class="form-control" name="path" placeholder="Path contains" value="<?php echo htmlspecialchars($path ?? ''); ?>">
        </div>
        <div class="col-6 col-md-2">
            <input type="date" class="form-control" name="start" value="<?php echo htmlspecialchars($start ?? ''); ?>">
        </div>
        <div class="col-6 col-md-2">
            <input type="date" class="form-control" name="end" value="<?php echo htmlspecialchars($end ?? ''); ?>">
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Filter</button>
            <a class="btn btn-outline-secondary" href="audit_trails.php">Reset</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>IP</th>
                    <th>Method</th>
                    <th>Path</th>
                    <th>At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="text-center">No records.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo $r['id']; ?></td>
                            <td><?php echo htmlspecialchars(($r['username'] ?? '') . ' (#' . $r['user_id'] . ')'); ?></td>
                            <td><?php echo htmlspecialchars($r['ip_address'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($r['method'] ?? ''); ?></td>
                            <td><code><?php echo htmlspecialchars($r['path']); ?></code></td>
                            <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                            <td><a class="btn btn-sm btn-outline-primary" href="audit_trails_view.php?id=<?php echo $r['id']; ?>">View</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <nav aria-label="Audit pagination" class="mt-3">
        <ul class="pagination">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $p])); ?>"><?php echo $p; ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


