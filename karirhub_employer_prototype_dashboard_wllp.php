<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_data.php';

if (!(current_user_can('karirhub_employer_prototype_view') || current_user_can('manage_settings'))) {
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
$recentActivities = $dataset['activities'];
$metrics = karirhub_proto_dashboard_metrics($vacancies);
$complianceByUnit = karirhub_proto_compliance_by_unit($units, $vacancies);
$latestProof = $metrics['bukti_terbaru'];

$summaryCards = [
    ['label' => 'Lowongan Dilaporkan', 'value' => (string)$metrics['total_dilaporkan'], 'tone' => 'primary'],
    ['label' => 'Lowongan Aktif', 'value' => (string)$metrics['lowongan_aktif'], 'tone' => 'info'],
    ['label' => 'Sudah Terisi', 'value' => (string)$metrics['sudah_terisi'], 'tone' => 'success'],
    ['label' => 'Perlu Update Status', 'value' => (string)$metrics['perlu_update'], 'tone' => 'warning'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karirhub Employer Prototype - Dashboard WLLP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-0">Dashboard WLLP</h3>
            <div class="text-muted small">Karirhub Employer Prototype (reference only)</div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-primary btn-sm" href="karirhub_employer_prototype_bukti_lapor">
                <i class="bi bi-file-earmark-check me-1"></i>Bukti Lapor
            </a>
            <a class="btn btn-outline-secondary btn-sm" href="karirhub_employer_prototype_pelaporan_lowongan">
                <i class="bi bi-journal-plus me-1"></i>Pelaporan Lowongan
            </a>
            <a class="btn btn-primary btn-sm" href="karirhub_employer_prototype_no_reg_bukti">
                <i class="bi bi-upc-scan me-1"></i>No. Reg Bukti
            </a>
        </div>
    </div>

    <div class="alert alert-info py-2 mb-3">
        <i class="bi bi-lightbulb me-1"></i>
        Halaman ini adalah prototipe UI untuk referensi alur WLLP (belum terhubung ke API produksi).
    </div>

    <div class="row g-3 mb-3">
        <?php foreach ($summaryCards as $card): ?>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small"><?php echo h($card['label']); ?></div>
                        <div class="fs-4 fw-semibold text-<?php echo h($card['tone']); ?>">
                            <?php echo h($card['value']); ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h5 class="card-title mb-3">Ringkasan Kepatuhan</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="p-3 rounded border bg-white h-100">
                        <div class="text-muted small">Status Bulan Berjalan</div>
                        <?php $statusBulanan = $metrics['perlu_update'] > 1 ? 'Perlu Perhatian' : 'Patuh'; ?>
                        <div class="fw-semibold text-<?php echo h($statusBulanan === 'Patuh' ? 'success' : 'warning'); ?>"><?php echo h($statusBulanan); ?></div>
                        <div class="small text-muted">
                            <?php echo h((string)($metrics['total_dilaporkan'] - $metrics['perlu_update'])); ?> dari
                            <?php echo h((string)$metrics['total_dilaporkan']); ?> lowongan sudah update status.
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded border bg-white h-100">
                        <div class="text-muted small">Bukti Lapor Terbaru</div>
                        <div class="fw-semibold"><?php echo h((string)($latestProof['no_reg_bukti'] ?? '-')); ?></div>
                        <div class="small text-muted">Diterbitkan <?php echo h((string)($latestProof['tanggal_lapor'] ?? '-')); ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded border bg-white h-100">
                        <div class="text-muted small">Masa Berlaku Monitoring</div>
                        <div class="fw-semibold"><?php echo h((string)($latestProof['masa_berlaku_sampai'] ?? '-')); ?></div>
                        <div class="small text-muted"><?php echo h((string)$metrics['perlu_update']); ?> lowongan memerlukan update keterisian.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h5 class="card-title mb-3">Monitoring Kepatuhan per Unit</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Unit</th>
                            <th>Total Laporan</th>
                            <th>Terisi</th>
                            <th>Belum Update</th>
                            <th>Kepatuhan</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($complianceByUnit as $row): ?>
                        <tr>
                            <td><?php echo h($row['unit']); ?></td>
                            <td><?php echo h((string)$row['total']); ?></td>
                            <td><?php echo h((string)$row['terisi']); ?></td>
                            <td><?php echo h((string)$row['belum_update']); ?></td>
                            <td><?php echo h((string)$row['patuh_pct']); ?>%</td>
                            <td><span class="badge text-bg-<?php echo h(karirhub_proto_status_badge_class($row['status'])); ?>"><?php echo h($row['status']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3">Aktivitas Terbaru WLLP</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                            <th>No. Reg Bukti</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentActivities as $row): ?>
                            <tr>
                                <td><?php echo h($row['waktu']); ?></td>
                                <td><?php echo h($row['aksi']); ?></td>
                                <td><?php echo h($row['no_reg_bukti']); ?></td>
                                <td><span class="badge text-bg-<?php echo h(karirhub_proto_status_badge_class($row['status'])); ?>"><?php echo h($row['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
