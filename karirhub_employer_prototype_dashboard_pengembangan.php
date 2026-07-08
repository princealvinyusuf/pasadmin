<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_ui.php';

if (!kh_proto_can_access('karirhub_employer_prototype_dashboard_pengembangan_view')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$progressRows = [
    [
        'id' => 'KH-DEV-001',
        'modul' => 'Modul Verifikasi Pemberi Kerja',
        'rilis' => 'Rilis Q3-2026',
        'owner' => 'Pasker',
        'prioritas' => 'Critical',
        'tahap' => 'Backlog',
        'status' => 'Todo',
        'progress' => 0,
        'target' => '20 Juli 2026',
        'dev_pct' => 0,
        'qa_pct' => 0,
        'uat_pct' => 0,
        'update_terakhir' => '06 Jul 2026',
    ],
    [
        'id' => 'KH-DEV-002',
        'modul' => 'Modul Verifikasi Lowongan Pekerjaan',
        'rilis' => 'Rilis Q3-2026',
        'owner' => 'Pasker',
        'prioritas' => 'Critical',
        'tahap' => 'Backlog',
        'status' => 'Todo',
        'progress' => 0,
        'target' => '20 Juli 2026',
        'dev_pct' => 0,
        'qa_pct' => 0,
        'uat_pct' => 0,
        'update_terakhir' => '06 Jul 2026',
    ],
    [
        'id' => 'KH-DEV-003',
        'modul' => 'Modul Skema Pendaftaran bagi Instansi Pemerintah (K/L)',
        'rilis' => 'Rilis Q3-2026',
        'owner' => 'Pasker',
        'prioritas' => 'Critical',
        'tahap' => 'Development',
        'status' => 'On Progress',
        'progress' => 0,
        'target' => '20 Juli 2026',
        'dev_pct' => 0,
        'qa_pct' => 0,
        'uat_pct' => 0,
        'update_terakhir' => '06 Jul 2026',
    ],
    [
        'id' => 'KH-DEV-004',
        'modul' => 'Modul Skema Invitation bagi Instansi Pemerintah (K/L)',
        'rilis' => 'Rilis Q4-2026',
        'owner' => 'Pasker',
        'prioritas' => 'Critical',
        'tahap' => 'Backlog',
        'status' => 'Todo',
        'progress' => 0,
        'target' => '20 Juli 2026',
        'dev_pct' => 0,
        'qa_pct' => 0,
        'uat_pct' => 0,
        'update_terakhir' => '06 Jul 2026',
    ],
    [
        'id' => 'KH-DEV-005',
        'modul' => 'Calendar Holiday Services',
        'rilis' => 'Rilis Q3-2026',
        'owner' => 'Pasker',
        'prioritas' => 'High',
        'tahap' => 'Backlog',
        'status' => 'Todo',
        'progress' => 0,
        'target' => '20 Juli 2026',
        'dev_pct' => 0,
        'qa_pct' => 0,
        'uat_pct' => 0,
        'update_terakhir' => '06 Jul 2026',
    ],
    [
        'id' => 'KH-DEV-006',
        'modul' => 'Role Admin',
        'rilis' => 'Rilis Q4-2026',
        'owner' => 'Pasker',
        'prioritas' => 'High',
        'tahap' => 'Backlog',
        'status' => 'On Hold',
        'progress' => 0,
        'target' => '20 Juli 2026',
        'dev_pct' => 0,
        'qa_pct' => 0,
        'uat_pct' => 0,
        'update_terakhir' => '06 Jul 2026',
    ],
];

$totalModule = count($progressRows);
$onProgressCount = 0;
$onHoldCount = 0;
$criticalCount = 0;
$totalProgress = 0;
$stageCounts = [
    'Backlog' => 0,
    'Development' => 0,
    'QA' => 0,
    'UAT' => 0,
    'Done' => 0,
];

foreach ($progressRows as $row) {
    $totalProgress += (int)$row['progress'];
    if ($row['status'] === 'On Progress') {
        $onProgressCount++;
    }
    if ($row['status'] === 'On Hold') {
        $onHoldCount++;
    }
    if ($row['prioritas'] === 'Critical') {
        $criticalCount++;
    }
    if (isset($stageCounts[$row['tahap']])) {
        $stageCounts[$row['tahap']]++;
    }
}

$averageProgress = $totalModule > 0 ? (int)round($totalProgress / $totalModule) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karirhub Employer Prototype - Dashboard Pengembangan Ekosistem</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php kh_proto_render_styles(); ?>
</head>
<body class="kh-proto-page">
<?php include 'navbar.php'; ?>
<?php kh_proto_render_hero('Daftar Lowongan Kerja', 'Monitoring progress pengembangan ekosistem aplikasi Karirhub.', 'Lowongan Kerja', 'karirhub_employer_prototype_pelaporan_lowongan', 'Proyek', 'karirhub_employer_prototype_dashboard_wllp', false); ?>

<div class="kh-content-wrap">
<div class="container py-4">
    <div class="kh-proto-shell">
    <?php kh_proto_render_sidebar('wllp_dashboard_pengembangan'); ?>
    <main class="kh-proto-main">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-0">Dashboard Pengembangan Ekosistem</h3>
            <div class="text-muted small">Snapshot update pengembangan aplikasi Karirhub</div>
        </div>
        <a class="btn btn-outline-primary btn-sm" href="karirhub_employer_prototype_dashboard_wllp">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard WLLP
        </a>
    </div>

    <div class="alert alert-info py-2 mb-3">
        <i class="bi bi-info-circle me-1"></i>
        Sumber data: Google Sheet "Update Progress Pengembangan Ekosistem Aplikasi Karirhub" (snapshot 06 Jul 2026).
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-2">
            <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Total Modul</div><div class="fs-4 fw-semibold text-primary"><?php echo h((string)$totalModule); ?></div></div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">On Progress</div><div class="fs-4 fw-semibold text-info"><?php echo h((string)$onProgressCount); ?></div></div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">On Hold</div><div class="fs-4 fw-semibold text-warning"><?php echo h((string)$onHoldCount); ?></div></div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Prioritas Critical</div><div class="fs-4 fw-semibold text-danger"><?php echo h((string)$criticalCount); ?></div></div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Rata-rata Progress</div><div class="fs-4 fw-semibold text-secondary"><?php echo h((string)$averageProgress); ?>%</div></div></div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="mb-3">Distribusi Tahap</h6>
                    <?php foreach ($stageCounts as $stage => $count): ?>
                        <?php $pct = $totalModule > 0 ? (int)round(($count / $totalModule) * 100) : 0; ?>
                        <div class="d-flex justify-content-between align-items-center mb-1 small">
                            <span><?php echo h($stage); ?></span>
                            <span class="text-muted"><?php echo h((string)$count); ?> modul</span>
                        </div>
                        <div class="progress mb-2" style="height: 8px;">
                            <div class="progress-bar" role="progressbar" style="width: <?php echo h((string)$pct); ?>%;" aria-valuenow="<?php echo h((string)$pct); ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h6 class="mb-3">Daftar Modul Prioritas</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Modul/Fitur</th>
                                <th>Rilis</th>
                                <th>Status</th>
                                <th>Progress</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($progressRows as $row): ?>
                                <tr>
                                    <td><?php echo h($row['id']); ?></td>
                                    <td><?php echo h($row['modul']); ?></td>
                                    <td><?php echo h($row['rilis']); ?></td>
                                    <td><?php echo h($row['status']); ?></td>
                                    <td><?php echo h((string)$row['progress']); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3">Detail Progress Per Modul</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Modul/Fitur</th>
                        <th>Prioritas</th>
                        <th>Tahap Saat Ini</th>
                        <th>Dev %</th>
                        <th>QA %</th>
                        <th>UAT %</th>
                        <th>% Progress</th>
                        <th>Target Selesai</th>
                        <th>Update Terakhir</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($progressRows as $row): ?>
                        <tr>
                            <td><?php echo h($row['id']); ?></td>
                            <td><?php echo h($row['modul']); ?></td>
                            <td><?php echo h($row['prioritas']); ?></td>
                            <td><?php echo h($row['tahap']); ?></td>
                            <td><?php echo h((string)$row['dev_pct']); ?>%</td>
                            <td><?php echo h((string)$row['qa_pct']); ?>%</td>
                            <td><?php echo h((string)$row['uat_pct']); ?>%</td>
                            <td><?php echo h((string)$row['progress']); ?>%</td>
                            <td><?php echo h($row['target']); ?></td>
                            <td><?php echo h($row['update_terakhir']); ?></td>
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
