<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

if (!(current_user_can('laporan_masuk_admin_manage') || current_user_can('manage_settings') || current_user_can('lapor_loker_manage') || current_user_can('lapor_perusahaan_manage'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
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

$lokerSummary = ['total' => 0, 'pending' => 0];
$resLoker = $conn->query("SELECT COUNT(*) AS total, SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending FROM job_hoax_reports");
if ($resLoker && ($r = $resLoker->fetch_assoc())) {
    $lokerSummary['total'] = (int)($r['total'] ?? 0);
    $lokerSummary['pending'] = (int)($r['pending'] ?? 0);
}

$companySummary = ['total' => 0, 'pending' => 0];
$resCompany = $conn->query("SELECT COUNT(*) AS total, SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending FROM company_hoax_reports");
if ($resCompany && ($r = $resCompany->fetch_assoc())) {
    $companySummary['total'] = (int)($r['total'] ?? 0);
    $companySummary['pending'] = (int)($r['pending'] ?? 0);
}

$latestLoker = [];
$resLatestLoker = $conn->query("SELECT id, nama_perusahaan_digunakan, platform_sumber, status, created_at FROM job_hoax_reports ORDER BY created_at DESC, id DESC LIMIT 8");
if ($resLatestLoker) {
    while ($row = $resLatestLoker->fetch_assoc()) {
        $latestLoker[] = $row;
    }
}

$latestCompany = [];
$resLatestCompany = $conn->query("SELECT report_id, company_name, reason_label, status, created_at FROM company_hoax_reports ORDER BY created_at DESC, id DESC LIMIT 8");
if ($resLatestCompany) {
    while ($row = $resLatestCompany->fetch_assoc()) {
        $latestCompany[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pusat Pemeriksaan Laporan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container mt-4 mb-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-1">Pusat Pemeriksaan Laporan</h3>
            <div class="text-muted">Monitoring cepat laporan masuk untuk laporan loker dan laporan perusahaan.</div>
        </div>
        <div class="d-flex gap-2">
            <?php if (current_user_can('lapor_loker_manage') || current_user_can('manage_settings')): ?>
                <a class="btn btn-outline-primary" href="lapor_loker_reports"><i class="bi bi-flag me-1"></i>Buka Laporan Loker</a>
            <?php endif; ?>
            <?php if (current_user_can('lapor_perusahaan_manage') || current_user_can('manage_settings')): ?>
                <a class="btn btn-primary" href="lapor_perusahaan_reports"><i class="bi bi-buildings me-1"></i>Buka Laporan Perusahaan</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Laporan Loker</h5>
                        <span class="badge bg-warning text-dark">Pending: <?php echo number_format($lokerSummary['pending']); ?></span>
                    </div>
                    <div class="display-6 fw-bold"><?php echo number_format($lokerSummary['total']); ?></div>
                    <div class="text-muted small">Total laporan loker tercatat.</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Laporan Perusahaan</h5>
                        <span class="badge bg-warning text-dark">Pending: <?php echo number_format($companySummary['pending']); ?></span>
                    </div>
                    <div class="display-6 fw-bold"><?php echo number_format($companySummary['total']); ?></div>
                    <div class="text-muted small">Total laporan perusahaan tercatat.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>Laporan Loker Terbaru</strong>
                    <a href="lapor_loker_reports" class="small text-decoration-none">Lihat semua</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Perusahaan</th>
                                    <th>Platform</th>
                                    <th>Status</th>
                                    <th>Masuk</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($latestLoker)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">Belum ada data.</td></tr>
                            <?php else: ?>
                                <?php foreach ($latestLoker as $item): ?>
                                    <tr>
                                        <td><?php echo (int)$item['id']; ?></td>
                                        <td><?php echo htmlspecialchars((string)$item['nama_perusahaan_digunakan']); ?></td>
                                        <td><?php echo htmlspecialchars((string)$item['platform_sumber']); ?></td>
                                        <td><?php echo htmlspecialchars((string)$item['status']); ?></td>
                                        <td><?php echo htmlspecialchars((string)$item['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>Laporan Perusahaan Terbaru</strong>
                    <a href="lapor_perusahaan_reports" class="small text-decoration-none">Lihat semua</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Report ID</th>
                                    <th>Perusahaan</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Masuk</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($latestCompany)): ?>
                                <tr><td colspan="5" class="text-center text-muted py-3">Belum ada data.</td></tr>
                            <?php else: ?>
                                <?php foreach ($latestCompany as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string)$item['report_id']); ?></td>
                                        <td><?php echo htmlspecialchars((string)$item['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars((string)$item['reason_label']); ?></td>
                                        <td><?php echo htmlspecialchars((string)$item['status']); ?></td>
                                        <td><?php echo htmlspecialchars((string)$item['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
