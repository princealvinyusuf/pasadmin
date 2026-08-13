<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

if (!(current_user_can('lapor_perusahaan_manage') || current_user_can('manage_settings') || current_user_can('laporan_masuk_admin_manage'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$conn = new mysqli('localhost', 'root', '', 'paskerid_db_prod');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$conn->query("CREATE TABLE IF NOT EXISTS company_hoax_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id VARCHAR(40) NOT NULL UNIQUE,
    company_id VARCHAR(80) NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    reason_code VARCHAR(120) NOT NULL,
    reason_label VARCHAR(255) NOT NULL,
    reporter_email VARCHAR(255) NOT NULL,
    related_vacancy_title VARCHAR(255) DEFAULT NULL,
    comment_text TEXT DEFAULT NULL,
    evidence_path VARCHAR(500) DEFAULT NULL,
    evidence_name VARCHAR(255) DEFAULT NULL,
    status ENUM('pending','in_review','resolved','rejected') NOT NULL DEFAULT 'pending',
    reviewer_note TEXT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_status (status),
    KEY idx_company (company_id, company_name),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    $rowId = intval($_POST['row_id'] ?? 0);

    if ($rowId <= 0 || !in_array($action, ['set_status', 'delete'], true)) {
        $_SESSION['error'] = 'Aksi tidak valid.';
        header('Location: lapor_perusahaan_reports');
        exit;
    }

    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM company_hoax_reports WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $rowId);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Laporan perusahaan berhasil dihapus.';
        } else {
            $_SESSION['error'] = 'Gagal menghapus data: ' . $conn->error;
        }
        header('Location: lapor_perusahaan_reports');
        exit;
    }

    $nextStatus = trim((string)($_POST['next_status'] ?? 'pending'));
    $reviewerNote = trim((string)($_POST['reviewer_note'] ?? ''));
    $allowedStatus = ['pending', 'in_review', 'resolved', 'rejected'];
    if (!in_array($nextStatus, $allowedStatus, true)) {
        $_SESSION['error'] = 'Status tidak valid.';
        header('Location: lapor_perusahaan_reports');
        exit;
    }

    $stmt = $conn->prepare("UPDATE company_hoax_reports
        SET status = ?, reviewer_note = ?, reviewed_at = CASE WHEN ? IN ('in_review','resolved','rejected') THEN NOW() ELSE reviewed_at END
        WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('sssi', $nextStatus, $reviewerNote, $nextStatus, $rowId);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = 'Status laporan perusahaan berhasil diperbarui.';
    } else {
        $_SESSION['error'] = 'Gagal memperbarui status: ' . $conn->error;
    }
    header('Location: lapor_perusahaan_reports');
    exit;
}

$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$allowedFilters = ['all', 'pending', 'in_review', 'resolved', 'rejected'];
if (!in_array($statusFilter, $allowedFilters, true)) {
    $statusFilter = 'all';
}

$query = "SELECT id, report_id, company_id, company_name, reason_code, reason_label, reporter_email, related_vacancy_title, comment_text, evidence_path, evidence_name, status, reviewer_note, reviewed_at, created_at
FROM company_hoax_reports";
$types = '';
$params = [];
if ($statusFilter !== 'all') {
    $query .= " WHERE status = ?";
    $types = 's';
    $params[] = $statusFilter;
}
$query .= " ORDER BY created_at DESC, id DESC";

$rows = [];
$stmt = $conn->prepare($query);
if ($stmt) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($r = $result->fetch_assoc()) {
        $rows[] = $r;
    }
    $stmt->close();
}

$summary = ['total' => 0, 'pending' => 0, 'in_review' => 0, 'resolved' => 0, 'rejected' => 0];
$summaryRes = $conn->query("SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN status='in_review' THEN 1 ELSE 0 END) AS in_review,
    SUM(CASE WHEN status='resolved' THEN 1 ELSE 0 END) AS resolved,
    SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) AS rejected
FROM company_hoax_reports");
if ($summaryRes && ($s = $summaryRes->fetch_assoc())) {
    $summary['total'] = (int)($s['total'] ?? 0);
    $summary['pending'] = (int)($s['pending'] ?? 0);
    $summary['in_review'] = (int)($s['in_review'] ?? 0);
    $summary['resolved'] = (int)($s['resolved'] ?? 0);
    $summary['rejected'] = (int)($s['rejected'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Perusahaan Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container mt-4 mb-5">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
        <h3 class="mb-0">Laporan Perusahaan Reports</h3>
        <form method="GET" class="d-flex align-items-center gap-2">
            <select name="status" class="form-select">
                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>Semua Status</option>
                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="in_review" <?php echo $statusFilter === 'in_review' ? 'selected' : ''; ?>>In Review</option>
                <option value="resolved" <?php echo $statusFilter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
            <button type="submit" class="btn btn-primary">Terapkan</button>
            <a href="lapor_perusahaan_reports" class="btn btn-outline-secondary">Reset</a>
        </form>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Total</div><div class="h4 mb-0"><?php echo number_format($summary['total']); ?></div></div></div></div>
        <div class="col-6 col-lg-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Pending</div><div class="h4 mb-0 text-warning"><?php echo number_format($summary['pending']); ?></div></div></div></div>
        <div class="col-6 col-lg-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">In Review</div><div class="h4 mb-0 text-primary"><?php echo number_format($summary['in_review']); ?></div></div></div></div>
        <div class="col-6 col-lg-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Resolved</div><div class="h4 mb-0 text-success"><?php echo number_format($summary['resolved']); ?></div></div></div></div>
        <div class="col-6 col-lg-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Rejected</div><div class="h4 mb-0 text-danger"><?php echo number_format($summary['rejected']); ?></div></div></div></div>
        <div class="col-6 col-lg-2"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Ditampilkan</div><div class="h4 mb-0"><?php echo number_format(count($rows)); ?></div></div></div></div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Report ID</th>
                            <th>Company</th>
                            <th>Reason</th>
                            <th>Reporter</th>
                            <th>Context Vacancy</th>
                            <th>Status</th>
                            <th>Reviewed At</th>
                            <th>Created At</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">Belum ada laporan perusahaan masuk.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                                $badge = 'secondary';
                                if ($r['status'] === 'pending') { $badge = 'warning text-dark'; }
                                elseif ($r['status'] === 'in_review') { $badge = 'primary'; }
                                elseif ($r['status'] === 'resolved') { $badge = 'success'; }
                                elseif ($r['status'] === 'rejected') { $badge = 'danger'; }
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars((string)$r['report_id']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars((string)$r['company_id']); ?></small></td>
                                <td><?php echo htmlspecialchars((string)$r['company_name']); ?></td>
                                <td><?php echo htmlspecialchars((string)$r['reason_label']); ?><br><small class="text-muted"><?php echo htmlspecialchars((string)$r['reason_code']); ?></small></td>
                                <td><?php echo htmlspecialchars((string)$r['reporter_email']); ?></td>
                                <td><?php echo htmlspecialchars((string)($r['related_vacancy_title'] ?: '-')); ?></td>
                                <td><span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars((string)$r['status']); ?></span></td>
                                <td><?php echo htmlspecialchars((string)($r['reviewed_at'] ?: '-')); ?></td>
                                <td><?php echo htmlspecialchars((string)$r['created_at']); ?></td>
                                <td style="min-width: 250px;">
                                    <form method="POST" class="d-flex flex-column gap-1">
                                        <input type="hidden" name="action" value="set_status">
                                        <input type="hidden" name="row_id" value="<?php echo (int)$r['id']; ?>">
                                        <select class="form-select form-select-sm" name="next_status">
                                            <option value="pending" <?php echo $r['status'] === 'pending' ? 'selected' : ''; ?>>pending</option>
                                            <option value="in_review" <?php echo $r['status'] === 'in_review' ? 'selected' : ''; ?>>in_review</option>
                                            <option value="resolved" <?php echo $r['status'] === 'resolved' ? 'selected' : ''; ?>>resolved</option>
                                            <option value="rejected" <?php echo $r['status'] === 'rejected' ? 'selected' : ''; ?>>rejected</option>
                                        </select>
                                        <input class="form-control form-control-sm" name="reviewer_note" value="<?php echo htmlspecialchars((string)($r['reviewer_note'] ?? '')); ?>" placeholder="Catatan pemeriksaan admin">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Update</button>
                                    </form>
                                    <form method="POST" class="mt-1" onsubmit="return confirm('Hapus laporan perusahaan ini?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="row_id" value="<?php echo (int)$r['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="9" class="bg-light">
                                    <strong>Komentar:</strong> <?php echo nl2br(htmlspecialchars((string)($r['comment_text'] ?: '-'))); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
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
