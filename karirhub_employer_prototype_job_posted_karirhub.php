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

$statusFilter = trim((string)($_GET['status'] ?? 'all'));
if (!in_array($statusFilter, ['all', 'aktif', 'ditutup'], true)) {
    $statusFilter = 'all';
}
$lokerTerbatas = trim((string)($_GET['loker_terbatas'] ?? '0')) === '1';

$jobs = [
    [
        'judul' => 'IT Manager',
        'lokasi' => 'Amban, Manokwari Barat, Kab. Manokwari, Papua Barat, Indonesia',
        'status' => 'ditutup',
        'metrics' => ['leads' => 120308, 'lamaran' => 0, 'bookmark' => 0, 'ditawarkan' => 0, 'wawancara' => 0, 'diterima' => 0, 'arsip' => 0],
    ],
    [
        'judul' => 'Kasir',
        'lokasi' => 'Bojongcae, Cibadak, KAB. LEBAK, BANTEN, Indonesia',
        'status' => 'ditutup',
        'metrics' => ['leads' => 118244, 'lamaran' => 0, 'bookmark' => 0, 'ditawarkan' => 0, 'wawancara' => 0, 'diterima' => 0, 'arsip' => 0],
    ],
    [
        'judul' => 'Finance Accounting',
        'lokasi' => 'Soreang, Soreang, KAB BANDUNG, JAWA BARAT, Indonesia',
        'status' => 'ditutup',
        'metrics' => ['leads' => 107982, 'lamaran' => 0, 'bookmark' => 0, 'ditawarkan' => 0, 'wawancara' => 0, 'diterima' => 0, 'arsip' => 0],
    ],
    [
        'judul' => 'Customer Relationship Officer',
        'lokasi' => 'Cicendo, Kota Bandung, Jawa Barat, Indonesia',
        'status' => 'aktif',
        'metrics' => ['leads' => 98944, 'lamaran' => 12, 'bookmark' => 8, 'ditawarkan' => 2, 'wawancara' => 3, 'diterima' => 1, 'arsip' => 0],
    ],
];

$filteredJobs = array_values(array_filter($jobs, static function (array $job) use ($statusFilter): bool {
    if ($statusFilter === 'all') {
        return true;
    }
    return (string)$job['status'] === $statusFilter;
}));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karirhub Employer Prototype - Job Posted Karirhub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php kh_proto_render_styles(); ?>
    <style>
        .job-posted-note { background: #ffffff; border: 1px solid #e8eef5; border-radius: 6px; padding: 10px 12px; color: #61758b; font-size: 13px; }
        .job-posted-card { border: 1px solid #edf2f8; border-radius: 10px; background: #fff; overflow: hidden; margin-bottom: 12px; }
        .job-posted-card-head { padding: 14px 16px 10px; display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
        .job-posted-title { font-size: 22px; font-weight: 700; color: #23415f; margin-bottom: 2px; }
        .job-posted-title-link { color: #23415f; text-decoration: none; }
        .job-posted-title-link:hover { color: #0a8f8a; text-decoration: underline; }
        .job-posted-loc { color: #8596a8; font-size: 13px; }
        .job-posted-status { border-radius: 999px; padding: 6px 14px; font-size: 12px; font-weight: 700; color: #fff; white-space: nowrap; }
        .job-posted-status.ditutup { background: #ea3f51; }
        .job-posted-status.aktif { background: #18a365; }
        .job-posted-metrics { border-top: 1px solid #edf2f8; background: #fbfcfe; display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); }
        .job-metric { padding: 10px 8px; text-align: center; border-right: 1px solid #edf2f8; }
        .job-metric:last-child { border-right: none; }
        .job-metric-value { font-weight: 800; color: #24476a; font-size: 19px; line-height: 1.1; }
        .job-metric-label { color: #8a99aa; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }
        @media (max-width: 991px) {
            .job-posted-title { font-size: 18px; }
            .job-posted-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>
</head>
<body class="kh-proto-page">
<?php include 'navbar.php'; ?>
<?php kh_proto_render_hero('Daftar Pekerjaan', 'Pantau lowongan yang sudah diposting ke Karirhub.', 'Lowongan Kerja', 'karirhub_employer_prototype_pelaporan_lowongan', 'Proyek', 'karirhub_employer_prototype_job_posted_karirhub'); ?>

<div class="kh-content-wrap">
<div class="container py-4">
    <div class="kh-proto-shell">
    <?php kh_proto_render_sidebar('wllp_job_posted'); ?>
    <main class="kh-proto-main">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-0">Job Posted Karirhub</h3>
            <div class="text-muted small">Tampilan daftar lowongan posted seperti Karirhub Employer.</div>
        </div>
        <a class="btn btn-outline-primary btn-sm" href="karirhub_employer_prototype_dashboard_wllp">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard WLLP
        </a>
    </div>

    <form method="GET" class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 d-flex flex-wrap align-items-center gap-3">
            <div class="small fw-semibold text-muted">Status Lowongan:</div>
            <select name="status" class="form-select form-select-sm" style="width:auto;">
                <option value="all"<?php echo $statusFilter === 'all' ? ' selected' : ''; ?>>Semua</option>
                <option value="aktif"<?php echo $statusFilter === 'aktif' ? ' selected' : ''; ?>>Lowongan Aktif</option>
                <option value="ditutup"<?php echo $statusFilter === 'ditutup' ? ' selected' : ''; ?>>Lowongan Ditutup</option>
            </select>
            <div class="form-check m-0">
                <input class="form-check-input" type="checkbox" value="1" id="lokerTerbatas" name="loker_terbatas"<?php echo $lokerTerbatas ? ' checked' : ''; ?>>
                <label class="form-check-label small" for="lokerTerbatas">Loker Terbatas</label>
            </div>
            <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-funnel me-1"></i>Terapkan</button>
        </div>
    </form>

    <div class="job-posted-note mb-3">
        Perhatian: Data lowongan pekerjaan yang kamu input, akan masuk ke rencana penggunaan tenaga kerja pada layanan <strong>Wajib Lapor Ketenagakerjaan</strong>
    </div>

    <?php if (empty($filteredJobs)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-muted text-center py-4">Tidak ada lowongan sesuai filter saat ini.</div>
        </div>
    <?php else: ?>
        <?php foreach ($filteredJobs as $job): ?>
            <div class="job-posted-card">
                <div class="job-posted-card-head">
                    <div>
                        <div class="job-posted-title">
                            <a class="job-posted-title-link" href="karirhub_employer_prototype_job_posted_karirhub_detail?job=<?php echo rawurlencode((string)$job['judul']); ?>">
                                <?php echo h((string)$job['judul']); ?>
                            </a>
                        </div>
                        <div class="job-posted-loc"><i class="bi bi-geo-alt-fill me-1"></i><?php echo h((string)$job['lokasi']); ?></div>
                    </div>
                    <span class="job-posted-status <?php echo h((string)$job['status']); ?>">
                        <?php echo (string)$job['status'] === 'ditutup' ? 'Lowongan Ditutup' : 'Lowongan Aktif'; ?>
                    </span>
                </div>
                <div class="job-posted-metrics">
                    <div class="job-metric">
                        <div class="job-metric-value"><?php echo h((string)$job['metrics']['leads']); ?></div>
                        <div class="job-metric-label">Leads</div>
                    </div>
                    <div class="job-metric">
                        <div class="job-metric-value"><?php echo h((string)$job['metrics']['lamaran']); ?></div>
                        <div class="job-metric-label">Lamaran</div>
                    </div>
                    <div class="job-metric">
                        <div class="job-metric-value"><?php echo h((string)$job['metrics']['bookmark']); ?></div>
                        <div class="job-metric-label">Bookmark</div>
                    </div>
                    <div class="job-metric">
                        <div class="job-metric-value"><?php echo h((string)$job['metrics']['ditawarkan']); ?></div>
                        <div class="job-metric-label">Ditawarkan</div>
                    </div>
                    <div class="job-metric">
                        <div class="job-metric-value"><?php echo h((string)$job['metrics']['wawancara']); ?></div>
                        <div class="job-metric-label">Wawancara</div>
                    </div>
                    <div class="job-metric">
                        <div class="job-metric-value"><?php echo h((string)$job['metrics']['diterima']); ?></div>
                        <div class="job-metric-label">Diterima</div>
                    </div>
                    <div class="job-metric">
                        <div class="job-metric-value"><?php echo h((string)$job['metrics']['arsip']); ?></div>
                        <div class="job-metric-label">Arsip</div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </main>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php kh_proto_render_sidebar_script(); ?>
</body>
</html>
