<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

if (!(current_user_can('karirhub_employer_prototype_view') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$summaryCards = [
    ['label' => 'Lowongan Dilaporkan', 'value' => '124', 'tone' => 'primary'],
    ['label' => 'Lowongan Aktif', 'value' => '38', 'tone' => 'info'],
    ['label' => 'Sudah Terisi', 'value' => '71', 'tone' => 'success'],
    ['label' => 'Perlu Update Status', 'value' => '15', 'tone' => 'warning'],
];

$recentActivities = [
    ['tanggal' => '20 Mei 2026 08:10', 'aksi' => 'Buat Laporan Lowongan', 'no_reg' => 'WLLP-2026-0519-001278', 'status' => 'Terverifikasi'],
    ['tanggal' => '19 Mei 2026 16:34', 'aksi' => 'Cetak Bukti Lapor', 'no_reg' => 'WLLP-2026-0518-001249', 'status' => 'Dicetak'],
    ['tanggal' => '19 Mei 2026 11:52', 'aksi' => 'Update Status Keterisian', 'no_reg' => 'WLLP-2026-0517-001230', 'status' => 'Posisi Terisi'],
    ['tanggal' => '18 Mei 2026 09:14', 'aksi' => 'Buat Laporan Lowongan', 'no_reg' => 'WLLP-2026-0518-001249', 'status' => 'Terverifikasi'],
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
                        <div class="fw-semibold text-success">Patuh</div>
                        <div class="small text-muted">22 dari 24 lowongan telah dilaporkan tepat waktu.</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded border bg-white h-100">
                        <div class="text-muted small">Bukti Lapor Terbaru</div>
                        <div class="fw-semibold">WLLP-2026-0519-001278</div>
                        <div class="small text-muted">Diterbitkan 20 Mei 2026</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded border bg-white h-100">
                        <div class="text-muted small">Masa Berlaku Monitoring</div>
                        <div class="fw-semibold">31 Mei 2026</div>
                        <div class="small text-muted">2 lowongan mendekati batas update status keterisian.</div>
                    </div>
                </div>
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
                                <td><?php echo h($row['tanggal']); ?></td>
                                <td><?php echo h($row['aksi']); ?></td>
                                <td><?php echo h($row['no_reg']); ?></td>
                                <td><?php echo h($row['status']); ?></td>
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
