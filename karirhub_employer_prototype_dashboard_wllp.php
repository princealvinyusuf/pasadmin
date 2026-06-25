<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_data.php';
require_once __DIR__ . '/karirhub_employer_prototype_storage.php';
require_once __DIR__ . '/karirhub_employer_prototype_ui.php';
require_once __DIR__ . '/db.php';

if (!kh_proto_can_access('karirhub_employer_prototype_dashboard_wllp_view')) {
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
kh_proto_ensure_multi_tables($conn);
kh_proto_seed_multi_from_dataset($conn, $dataset, $units);

$vacancies = [];
$recentActivities = [];
$resItems = $conn->query("
    SELECT
        d.no_reg_bukti,
        d.id_lowongan,
        d.unit_kode,
        d.jabatan,
        d.masa_berlaku_mulai AS tanggal_lapor,
        d.masa_berlaku_sampai,
        d.status_verifikasi,
        COALESCE(s.status_saat_ini, 'Belum Terisi') AS status_keterisian
    FROM karirhub_proto_wllp_pelaporan d
    LEFT JOIN karirhub_proto_wllp_status s ON s.no_reg_bukti = d.no_reg_bukti AND s.id_lowongan = d.id_lowongan
    ORDER BY d.created_at DESC, d.no_reg_bukti DESC
");
if ($resItems) {
    while ($row = $resItems->fetch_assoc()) {
        $vacancies[] = $row;
    }
}

$resActivities = $conn->query("
    SELECT
        CONCAT(DATE_FORMAT(h.created_at, '%d %M %Y'), ' ', DATE_FORMAT(h.created_at, '%H:%i')) AS waktu,
        'Buat Laporan Lowongan' AS aksi,
        h.no_reg_bukti,
        h.status_verifikasi AS status
    FROM karirhub_proto_wllp_laporan h
    ORDER BY h.created_at DESC
    LIMIT 10
");
if ($resActivities) {
    while ($row = $resActivities->fetch_assoc()) {
        $recentActivities[] = $row;
    }
}

$metrics = karirhub_proto_dashboard_metrics($vacancies);

$summaryCards = [
    ['label' => 'Lowongan Dilaporkan', 'value' => (string)$metrics['total_dilaporkan'], 'tone' => 'primary'],
    ['label' => 'Lowongan Aktif', 'value' => (string)$metrics['lowongan_aktif'], 'tone' => 'info'],
    ['label' => 'Sudah Terisi', 'value' => (string)$metrics['sudah_terisi'], 'tone' => 'success'],
    ['label' => 'Belum Terisi', 'value' => (string)$metrics['perlu_update'], 'tone' => 'warning'],
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
    <?php kh_proto_render_styles(); ?>
</head>
<body class="kh-proto-page">
<?php include 'navbar.php'; ?>
<?php kh_proto_render_hero('Daftar Lowongan Kerja', 'Kelola prototipe WLLP dengan tampilan bergaya Karirhub Employer.', 'Lowongan Kerja', 'karirhub_employer_prototype_pelaporan_lowongan', 'Proyek', 'karirhub_employer_prototype_dashboard_wllp', false); ?>

<div class="kh-content-wrap">
<div class="container py-4">
    <div class="kh-proto-shell">
    <?php kh_proto_render_sidebar('dashboard_wllp'); ?>
    <main class="kh-proto-main">
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
    </main>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php kh_proto_render_sidebar_script(); ?>
</body>
</html>
