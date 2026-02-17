<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

// RBAC: only allow Walk-in Gallery managers (or global settings managers)
if (!current_user_can('walkin_gallery_manage') && !current_user_can('manage_settings')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db_prod';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

function table_exists(mysqli $conn, string $table): bool {
    $t = $conn->real_escape_string($table);
    $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES); }

if (!table_exists($conn, 'walkin_gallery_comments')) {
    $_SESSION['error'] = 'Tabel walkin_gallery_comments belum ada. Jalankan migration di Laravel terlebih dahulu.';
    header('Location: walkin_gallery.php');
    exit();
}

// Handle moderation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id = intval($_POST['id']);
    $action = $_POST['action'];
    if ($id > 0) {
        if ($action === 'approve' || $action === 'reject') {
            $status = $action === 'approve' ? 'approved' : 'rejected';
            if ($stmt = $conn->prepare("UPDATE walkin_gallery_comments SET status=?, updated_at=NOW() WHERE id=?")) {
                $stmt->bind_param("si", $status, $id);
                $stmt->execute();
                $stmt->close();
                $_SESSION['success'] = 'Status komentar diupdate.';
            }
        } elseif ($action === 'delete') {
            if ($stmt = $conn->prepare("DELETE FROM walkin_gallery_comments WHERE id=?")) {
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();
                $_SESSION['success'] = 'Komentar dihapus.';
            }
        }
    }
    header('Location: walkin_gallery_comments.php');
    exit();
}

$filter = $_GET['status'] ?? 'pending';
$allowed = ['pending','approved','rejected','all'];
if (!in_array($filter, $allowed, true)) $filter = 'pending';

$where = '';
if ($filter !== 'all') {
    $safe = $conn->real_escape_string($filter);
    $where = "WHERE c.status='$safe'";
}

$sql = "SELECT c.*, i.type AS item_type, i.title AS item_title
        FROM walkin_gallery_comments c
        LEFT JOIN walkin_gallery_items i ON i.id = c.walkin_gallery_item_id
        $where
        ORDER BY c.id DESC
        LIMIT 300";
$rows = [];
$res = $conn->query($sql);
if ($res) {
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $res->free();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderasi Komentar Galeri</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-0">Moderasi Komentar Galeri</h3>
            <div class="text-muted">Approve/reject komentar publik (tanpa login).</div>
        </div>
        <a class="btn btn-outline-secondary" href="walkin_gallery.php"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= h($_SESSION['error']); ?></div>
    <?php unset($_SESSION['error']); endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= h($_SESSION['success']); ?></div>
    <?php unset($_SESSION['success']); endif; ?>

    <div class="mb-3 d-flex gap-2 flex-wrap">
        <a class="btn btn-sm <?= $filter==='pending'?'btn-primary':'btn-outline-primary' ?>" href="?status=pending">Pending</a>
        <a class="btn btn-sm <?= $filter==='approved'?'btn-primary':'btn-outline-primary' ?>" href="?status=approved">Approved</a>
        <a class="btn btn-sm <?= $filter==='rejected'?'btn-primary':'btn-outline-primary' ?>" href="?status=rejected">Rejected</a>
        <a class="btn btn-sm <?= $filter==='all'?'btn-primary':'btn-outline-primary' ?>" href="?status=all">All</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Status</th>
                            <th>Nama</th>
                            <th>Komentar</th>
                            <th>Item</th>
                            <th>IP</th>
                            <th>Created</th>
                            <th style="width:220px">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= intval($r['id']); ?></td>
                            <td><span class="badge bg-secondary"><?= h($r['status']); ?></span></td>
                            <td><?= h($r['name']); ?></td>
                            <td style="max-width:420px"><?= h($r['comment']); ?></td>
                            <td><?= h(($r['item_title'] ?: '-') . ($r['item_type'] ? ' (' . $r['item_type'] . ')' : '')); ?></td>
                            <td class="text-muted small"><?= h($r['ip_address']); ?></td>
                            <td class="text-muted small"><?= h($r['created_at']); ?></td>
                            <td>
                                <form method="post" class="d-flex gap-2 flex-wrap">
                                    <input type="hidden" name="id" value="<?= intval($r['id']); ?>">
                                    <button class="btn btn-sm btn-success" name="action" value="approve" type="submit">Approve</button>
                                    <button class="btn btn-sm btn-warning" name="action" value="reject" type="submit">Reject</button>
                                    <button class="btn btn-sm btn-outline-danger" name="action" value="delete" type="submit" onclick="return confirm('Hapus komentar ini?');">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($rows) === 0): ?>
                        <tr><td colspan="8" class="text-center text-muted">Tidak ada data.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>



