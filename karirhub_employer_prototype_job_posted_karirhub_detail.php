<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_ui.php';

if (!(current_user_can('karirhub_employer_prototype_view') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$jobTitle = trim((string)($_GET['job'] ?? 'IT Manager'));
$jobs = [
    'IT Manager' => [
        'lokasi' => 'Amban, Manokwari Barat, Kab. Manokwari, Papua Barat, Indonesia',
        'status' => 'Lowongan Ditutup',
        'status_class' => 'danger',
        'kuota' => '0 / 1 kuota telah terisi',
        'metrics' => ['leads' => 120308, 'lamaran' => 0, 'bookmark' => 0, 'ditawarkan' => 0, 'wawancara' => 0, 'diterima' => 0, 'arsip' => 0],
    ],
    'Kasir' => [
        'lokasi' => 'Bojongcae, Cibadak, KAB. LEBAK, BANTEN, Indonesia',
        'status' => 'Lowongan Ditutup',
        'status_class' => 'danger',
        'kuota' => '0 / 2 kuota telah terisi',
        'metrics' => ['leads' => 118244, 'lamaran' => 0, 'bookmark' => 0, 'ditawarkan' => 0, 'wawancara' => 0, 'diterima' => 0, 'arsip' => 0],
    ],
    'Finance Accounting' => [
        'lokasi' => 'Soreang, Soreang, KAB BANDUNG, JAWA BARAT, Indonesia',
        'status' => 'Lowongan Ditutup',
        'status_class' => 'danger',
        'kuota' => '0 / 2 kuota telah terisi',
        'metrics' => ['leads' => 107982, 'lamaran' => 0, 'bookmark' => 0, 'ditawarkan' => 0, 'wawancara' => 0, 'diterima' => 0, 'arsip' => 0],
    ],
    'Customer Relationship Officer' => [
        'lokasi' => 'Cicendo, Kota Bandung, Jawa Barat, Indonesia',
        'status' => 'Lowongan Aktif',
        'status_class' => 'success',
        'kuota' => '1 / 3 kuota telah terisi',
        'metrics' => ['leads' => 98944, 'lamaran' => 12, 'bookmark' => 8, 'ditawarkan' => 2, 'wawancara' => 3, 'diterima' => 1, 'arsip' => 0],
    ],
];
$selectedJob = $jobs[$jobTitle] ?? $jobs['IT Manager'];
$activeTab = trim((string)($_GET['tab'] ?? 'leads'));
$allowedTabs = ['leads', 'lamaran', 'bookmark', 'ditawarkan', 'wawancara', 'diterima', 'arsip'];
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'leads';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karirhub Employer Prototype - Detail Job Posted</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php kh_proto_render_styles(); ?>
    <style>
        .jp-head { background: #0b3b66; border: 1px solid rgba(255,255,255,.12); border-radius: 10px; padding: 16px; color: #fff; }
        .jp-badge { border-radius: 999px; font-size: 12px; font-weight: 700; padding: 6px 14px; }
        .jp-location { color: #cfe0f0; font-size: 13px; }
        .jp-actions .btn { border-radius: 6px; font-size: 13px; font-weight: 600; }
        .jp-actions .btn-outline-light { border-color: rgba(255,255,255,.5); }
        .jp-actions .btn-teal { background: #10a3a3; border-color: #10a3a3; color: #fff; }
        .jp-actions .btn-teal:hover { background: #0f9191; border-color: #0f9191; color: #fff; }
        .jp-panel { border: 1px solid #e7edf5; border-radius: 10px; background: #fff; overflow: hidden; }
        .jp-tabs { border-bottom: 1px solid #e7edf5; padding: 0 12px; display: flex; gap: 8px; flex-wrap: wrap; }
        .jp-tab { color: #24476a; text-decoration: none; padding: 10px 6px; border-bottom: 3px solid transparent; font-size: 14px; font-weight: 700; }
        .jp-tab.active { border-bottom-color: #0a8f8a; }
        .jp-tab .badge { margin-left: 4px; }
        .jp-search-wrap { padding: 12px; border-bottom: 1px solid #eef3f8; }
        .jp-filter-row { padding: 0 12px 12px; border-bottom: 1px solid #eef3f8; color: #546b84; font-size: 13px; display: flex; gap: 16px; flex-wrap: wrap; }
        .jp-empty { color: #75879a; text-align: center; padding: 58px 16px; }
    </style>
</head>
<body class="kh-proto-page">
<?php include 'navbar.php'; ?>
<?php kh_proto_render_hero('Daftar Pekerjaan', 'Detail performa lowongan yang diposting ke Karirhub.', 'Lowongan Kerja', 'karirhub_employer_prototype_job_posted_karirhub', 'Proyek', 'karirhub_employer_prototype_job_posted_karirhub'); ?>

<div class="kh-content-wrap">
<div class="container py-4">
    <div class="kh-proto-shell">
    <?php kh_proto_render_sidebar('wllp_job_posted'); ?>
    <main class="kh-proto-main">

    <div class="jp-head mb-3">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-lg-8">
                <span class="jp-badge text-bg-<?php echo h((string)$selectedJob['status_class']); ?>"><?php echo h((string)$selectedJob['status']); ?></span>
                <h3 class="mt-2 mb-1"><?php echo h($jobTitle); ?></h3>
                <div class="jp-location"><i class="bi bi-geo-alt-fill me-1"></i><?php echo h((string)$selectedJob['lokasi']); ?></div>
                <div class="jp-actions d-flex flex-wrap gap-2 mt-3">
                    <a class="btn btn-outline-light btn-sm" href="#"><i class="bi bi-eye me-1"></i>Lihat Lowongan</a>
                    <a class="btn btn-outline-light btn-sm" href="#"><i class="bi bi-pencil-square me-1"></i>Edit Lowongan</a>
                    <a class="btn btn-teal btn-sm" href="#"><i class="bi bi-send-fill me-1"></i>Buka Lowongan</a>
                    <a class="btn btn-outline-light btn-sm" href="#"><i class="bi bi-link-45deg me-1"></i>Salin Link Lowongan</a>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="small text-uppercase text-light opacity-75">Kuota</div>
                <div class="progress my-2" style="height:6px;">
                    <div class="progress-bar" role="progressbar" style="width: 18%;"></div>
                </div>
                <div class="small"><?php echo h((string)$selectedJob['kuota']); ?></div>
            </div>
        </div>
    </div>

    <div class="jp-panel">
        <div class="jp-tabs">
            <?php foreach ($allowedTabs as $tab): ?>
                <a class="jp-tab <?php echo $activeTab === $tab ? 'active' : ''; ?>"
                   href="karirhub_employer_prototype_job_posted_karirhub_detail?<?php echo h(http_build_query(['job' => $jobTitle, 'tab' => $tab])); ?>">
                    <?php echo h(ucfirst($tab)); ?>
                    <span class="badge text-bg-light"><?php echo h((string)($selectedJob['metrics'][$tab] ?? 0)); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="jp-search-wrap">
            <div class="input-group input-group-sm">
                <input type="text" class="form-control" placeholder="Cari berdasarkan nama">
                <button class="btn btn-outline-secondary" type="button"><i class="bi bi-search"></i></button>
            </div>
        </div>
        <div class="jp-filter-row">
            <span>Min Pendidikan (0)</span>
            <span>Lokasi (0)</span>
            <span>Pengalaman (0)</span>
            <span>Jenis kelamin (0)</span>
            <span>Usia (0)</span>
            <span><input class="form-check-input me-1" type="checkbox">Disabilitas</span>
        </div>
        <div class="jp-empty">Tidak ada data ditemukan.</div>
    </div>

    <div class="mt-3">
        <a class="btn btn-outline-primary btn-sm" href="karirhub_employer_prototype_job_posted_karirhub">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke daftar Job Posted Karirhub
        </a>
    </div>
    </main>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php kh_proto_render_sidebar_script(); ?>
</body>
</html>
