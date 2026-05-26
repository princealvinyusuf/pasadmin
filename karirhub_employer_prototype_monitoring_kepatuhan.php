<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_data.php';
require_once __DIR__ . '/karirhub_employer_prototype_ui.php';

if (!kh_proto_can_access('karirhub_employer_prototype_monitoring_kepatuhan_view')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$dataset = karirhub_proto_dataset();
$units = $dataset['units'];
$vacancies = $dataset['vacancies'];
$summaryRows = karirhub_proto_compliance_by_unit($units, $vacancies);

$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$allowed = ['all', 'patuh', 'perlu perhatian', 'tidak patuh'];
if (!in_array($statusFilter, $allowed, true)) {
    $statusFilter = 'all';
}

$filteredSummary = array_values(array_filter($summaryRows, static function (array $row) use ($statusFilter): bool {
    if ($statusFilter === 'all') {
        return true;
    }
    return strtolower($row['status']) === $statusFilter;
}));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karirhub Employer Prototype - Monitoring Kepatuhan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php kh_proto_render_styles(); ?>
</head>
<body class="kh-proto-page">
<?php include 'navbar.php'; ?>
<?php kh_proto_render_hero('Daftar Lowongan Kerja', 'Monitoring kepatuhan pelaporan lowongan pada prototipe employer.', 'Lowongan Kerja', 'karirhub_employer_prototype_pelaporan_lowongan', 'Proyek', 'karirhub_employer_prototype_dashboard_wllp'); ?>

<div class="kh-content-wrap">
<div class="container py-4">
    <div class="kh-proto-shell">
    <?php kh_proto_render_sidebar('wllp_monitoring'); ?>
    <main class="kh-proto-main">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-0">Monitoring Kepatuhan</h3>
            <div class="text-muted small">Ringkasan kepatuhan WLLP per unit (dummy data)</div>
        </div>
        <a class="btn btn-outline-primary btn-sm" href="karirhub_employer_prototype_dashboard_wllp">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard WLLP
        </a>
    </div>

    <form method="GET" class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label mb-1">Filter Status Kepatuhan</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="all"<?php echo $statusFilter === 'all' ? ' selected' : ''; ?>>Semua</option>
                        <option value="patuh"<?php echo $statusFilter === 'patuh' ? ' selected' : ''; ?>>Patuh</option>
                        <option value="perlu perhatian"<?php echo $statusFilter === 'perlu perhatian' ? ' selected' : ''; ?>>Perlu Perhatian</option>
                        <option value="tidak patuh"<?php echo $statusFilter === 'tidak patuh' ? ' selected' : ''; ?>>Tidak Patuh</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-grid">
                    <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Unit</th>
                            <th>Total Laporan</th>
                            <th>Status Terisi</th>
                            <th>Belum Update</th>
                            <th>Kepatuhan (%)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($filteredSummary)): ?>
                        <tr><td colspan="6" class="text-center text-muted">Data tidak tersedia untuk filter ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($filteredSummary as $row): ?>
                            <tr>
                                <td><?php echo h($row['unit']); ?></td>
                                <td><?php echo h((string)$row['total']); ?></td>
                                <td><?php echo h((string)$row['terisi']); ?></td>
                                <td><?php echo h((string)$row['belum_update']); ?></td>
                                <td><?php echo h((string)$row['patuh_pct']); ?>%</td>
                                <td><span class="badge text-bg-<?php echo h(karirhub_proto_status_badge_class($row['status'])); ?>"><?php echo h($row['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h6 class="mb-2">Kriteria Dummy Kepatuhan</h6>
            <ul class="mb-0 small text-muted">
                <li>Patuh: mayoritas laporan sudah memiliki status keterisian terbaru.</li>
                <li>Perlu Perhatian: ada lebih dari satu lowongan yang belum update status.</li>
                <li>Tidak Patuh: persentase kepatuhan di bawah 60%.</li>
            </ul>
        </div>
    </div>
    </main>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php kh_proto_render_sidebar_script(); ?>
</body>
</html>
