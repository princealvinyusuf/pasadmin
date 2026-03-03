<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/blk_dashboard_data.php';

if (!(current_user_can('view_dashboard_blk') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$dashboardData = blk_get_dashboard_data();
$filters = $dashboardData['filters'];

$selectedPeriod = $_GET['period'] ?? '30 Hari';
$selectedLocation = $_GET['location'] ?? 'Semua Lokasi';
$selectedMajor = $_GET['major'] ?? 'Semua Kejuruan';
$selectedSource = $_GET['source'] ?? 'Semua Sumber';

if (!in_array($selectedPeriod, $filters['period_options'], true)) {
    $selectedPeriod = '30 Hari';
}
if (!in_array($selectedLocation, $filters['location_options'], true)) {
    $selectedLocation = 'Semua Lokasi';
}
if (!in_array($selectedMajor, $filters['major_options'], true)) {
    $selectedMajor = 'Semua Kejuruan';
}
if (!in_array($selectedSource, $filters['source_options'], true)) {
    $selectedSource = 'Semua Sumber';
}

$baseFilterQuery = http_build_query([
    'period' => $selectedPeriod,
    'location' => $selectedLocation,
    'major' => $selectedMajor,
    'source' => $selectedSource,
]);

$panelsById = [];
foreach ($dashboardData['panels'] as $panel) {
    $panelsById[$panel['id']] = $panel;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Eksekutif BLK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        body { background: #f5f7fb; }
        .panel-card { border: 0; border-radius: 12px; box-shadow: 0 3px 14px rgba(15, 23, 42, 0.08); }
        .panel-card .card-header { background: #fff; border-bottom: 1px solid #e9edf4; }
        .kpi-card { border: 0; border-radius: 12px; box-shadow: 0 2px 12px rgba(15, 23, 42, 0.07); background: #fff; }
        .kpi-title { color: #64748b; font-size: .85rem; margin-bottom: .25rem; }
        .kpi-value { font-size: 1.6rem; font-weight: 700; color: #0f172a; line-height: 1.1; }
        .kpi-delta { font-size: .8rem; color: #16a34a; }
        .section-title { font-weight: 700; color: #0f172a; margin-bottom: 12px; }
        .small-muted { color: #6b7280; font-size: .9rem; }
        .chart-box { min-height: 300px; }
        .metric-tile { border: 1px solid #e9edf4; border-radius: 10px; padding: .85rem; background: #fff; }
        .progress-thin { height: 8px; border-radius: 999px; }
        .quality-row { display: flex; justify-content: space-between; align-items: center; font-size: .9rem; margin-bottom: .55rem; }
        .quality-row:last-child { margin-bottom: 0; }
        .badge-status { font-size: .72rem; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
        <div>
            <h2 class="mb-1">Dashboard Eksekutif BLK</h2>
            <div class="small-muted">Monitoring kinerja pelatihan, integrasi data, dan outcome lulusan.</div>
        </div>
    </div>

    <form method="GET" class="card panel-card mb-4">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Periode</label>
                    <select name="period" class="form-select form-select-sm">
                        <?php foreach ($filters['period_options'] as $option): ?>
                            <option value="<?php echo e($option); ?>" <?php echo $option === $selectedPeriod ? 'selected' : ''; ?>>
                                <?php echo e($option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Lokasi Pelatihan</label>
                    <select name="location" class="form-select form-select-sm">
                        <?php foreach ($filters['location_options'] as $option): ?>
                            <option value="<?php echo e($option); ?>" <?php echo $option === $selectedLocation ? 'selected' : ''; ?>>
                                <?php echo e($option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Kejuruan</label>
                    <select name="major" class="form-select form-select-sm">
                        <?php foreach ($filters['major_options'] as $option): ?>
                            <option value="<?php echo e($option); ?>" <?php echo $option === $selectedMajor ? 'selected' : ''; ?>>
                                <?php echo e($option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label mb-1">Sumber</label>
                    <select name="source" class="form-select form-select-sm">
                        <?php foreach ($filters['source_options'] as $option): ?>
                            <option value="<?php echo e($option); ?>" <?php echo $option === $selectedSource ? 'selected' : ''; ?>>
                                <?php echo e($option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-1 d-grid">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                </div>
            </div>
        </div>
    </form>

    <h5 class="section-title">A. Ringkasan Utama</h5>
    <div class="row g-3 mb-4">
        <?php foreach ($dashboardData['summary_cards'] as $card): ?>
            <div class="col-12 col-md-6 col-lg">
                <div class="kpi-card p-3 h-100 d-flex flex-column">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="kpi-title"><?php echo e($card['title']); ?></div>
                        <span class="kpi-delta"><?php echo e($card['delta']); ?></span>
                    </div>
                    <div class="kpi-value mb-3"><?php echo e($card['value']); ?></div>
                    <div class="mt-auto d-grid">
                        <a class="btn btn-outline-primary btn-sm" href="dashboard_blk_detail.php?item=<?php echo e($card['id']); ?>&<?php echo e($baseFilterQuery); ?>">
                            <i class="bi bi-table me-1"></i>Detail
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <h5 class="section-title">B. Partisipasi Pelatihan</h5>
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-8">
            <div class="card panel-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><?php echo e($panelsById['partisipasi-tren']['title']); ?></span>
                    <a class="btn btn-sm btn-outline-primary" href="dashboard_blk_detail.php?item=partisipasi-tren&<?php echo e($baseFilterQuery); ?>">Detail</a>
                </div>
                <div class="card-body chart-box"><canvas id="chart-partisipasi-tren"></canvas></div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card panel-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><?php echo e($panelsById['partisipasi-rasio']['title']); ?></span>
                    <a class="btn btn-sm btn-outline-primary" href="dashboard_blk_detail.php?item=partisipasi-rasio&<?php echo e($baseFilterQuery); ?>">Detail</a>
                </div>
                <div class="card-body chart-box"><canvas id="chart-partisipasi-rasio"></canvas></div>
            </div>
        </div>
    </div>

    <h5 class="section-title">C. Output Pelatihan</h5>
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-8">
            <div class="card panel-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><?php echo e($panelsById['output-lulusan-sertifikat']['title']); ?></span>
                    <a class="btn btn-sm btn-outline-primary" href="dashboard_blk_detail.php?item=output-lulusan-sertifikat&<?php echo e($baseFilterQuery); ?>">Detail</a>
                </div>
                <div class="card-body chart-box"><canvas id="chart-output-lulusan-sertifikat"></canvas></div>
            </div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card panel-card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><?php echo e($panelsById['output-kelulusan']['title']); ?></span>
                    <a class="btn btn-sm btn-outline-primary" href="dashboard_blk_detail.php?item=output-kelulusan&<?php echo e($baseFilterQuery); ?>">Detail</a>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-1"><strong>88.5%</strong><span class="text-success">+1.2%</span></div>
                    <div class="progress progress-thin"><div class="progress-bar bg-success" style="width: 88.5%;"></div></div>
                </div>
            </div>
            <div class="card panel-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><?php echo e($panelsById['output-sertifikasi']['title']); ?></span>
                    <a class="btn btn-sm btn-outline-primary" href="dashboard_blk_detail.php?item=output-sertifikasi&<?php echo e($baseFilterQuery); ?>">Detail</a>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-1"><strong>92.1%</strong><span class="text-success">+0.8%</span></div>
                    <div class="progress progress-thin"><div class="progress-bar bg-warning" style="width: 92.1%;"></div></div>
                </div>
            </div>
        </div>
    </div>

    <h5 class="section-title">D. Distribusi Pelatihan</h5>
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-6">
            <div class="card panel-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><?php echo e($panelsById['distribusi-kejuruan']['title']); ?></span>
                    <a class="btn btn-sm btn-outline-primary" href="dashboard_blk_detail.php?item=distribusi-kejuruan&<?php echo e($baseFilterQuery); ?>">Detail</a>
                </div>
                <div class="card-body chart-box"><canvas id="chart-distribusi-kejuruan"></canvas></div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card panel-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><?php echo e($panelsById['distribusi-provinsi']['title']); ?></span>
                    <a class="btn btn-sm btn-outline-primary" href="dashboard_blk_detail.php?item=distribusi-provinsi&<?php echo e($baseFilterQuery); ?>">Detail</a>
                </div>
                <div class="card-body chart-box"><canvas id="chart-distribusi-provinsi"></canvas></div>
            </div>
        </div>
    </div>

    <h5 class="section-title">E. Integrasi Data (Kritis)</h5>
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-4">
            <div class="card panel-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><?php echo e($panelsById['integrasi-kpi']['title']); ?></span>
                    <a class="btn btn-sm btn-outline-primary" href="dashboard_blk_detail.php?item=integrasi-kpi&<?php echo e($baseFilterQuery); ?>">Detail</a>
                </div>
                <div class="card-body d-grid gap-2">
                    <div class="metric-tile">
                        <div class="small-muted">Sinkronisasi Karirhub</div>
                        <div class="d-flex justify-content-between"><strong>94.5%</strong><i class="bi bi-arrow-repeat text-primary"></i></div>
                    </div>
                    <div class="metric-tile">
                        <div class="small-muted">Data Belum Sinkron</div>
                        <div class="d-flex justify-content-between"><strong>145 Peserta</strong><i class="bi bi-exclamation-triangle text-warning"></i></div>
                    </div>
                    <div class="metric-tile">
                        <div class="small-muted">Rata-rata Waktu Sinkron</div>
                        <div class="d-flex justify-content-between"><strong>1.2 Hari</strong><i class="bi bi-clock text-success"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-8">
            <div class="card panel-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><?php echo e($panelsById['integrasi-gap']['title']); ?></span>
                    <a class="btn btn-sm btn-outline-primary" href="dashboard_blk_detail.php?item=integrasi-gap&<?php echo e($baseFilterQuery); ?>">Detail</a>
                </div>
                <div class="card-body chart-box"><canvas id="chart-integrasi-gap"></canvas></div>
            </div>
        </div>
    </div>

    <h5 class="section-title">F. Outcome Pasca Pelatihan</h5>
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-6">
            <div class="card panel-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><?php echo e($panelsById['outcome-konversi']['title']); ?></span>
                    <a class="btn btn-sm btn-outline-primary" href="dashboard_blk_detail.php?item=outcome-konversi&<?php echo e($baseFilterQuery); ?>">Detail</a>
                </div>
                <div class="card-body chart-box"><canvas id="chart-outcome-konversi"></canvas></div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card panel-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><?php echo e($panelsById['outcome-penempatan']['title']); ?></span>
                    <a class="btn btn-sm btn-outline-primary" href="dashboard_blk_detail.php?item=outcome-penempatan&<?php echo e($baseFilterQuery); ?>">Detail</a>
                </div>
                <div class="card-body chart-box"><canvas id="chart-outcome-penempatan"></canvas></div>
            </div>
        </div>
    </div>

    <h5 class="section-title">G. Teknis & Kualitas Data</h5>
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-4">
            <div class="card panel-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><?php echo e($panelsById['teknis-skor']['title']); ?></span>
                    <a class="btn btn-sm btn-outline-primary" href="dashboard_blk_detail.php?item=teknis-skor&<?php echo e($baseFilterQuery); ?>">Detail</a>
                </div>
                <div class="card-body">
                    <div class="quality-row"><span>Kelengkapan</span><strong>92%</strong></div>
                    <div class="progress progress-thin mb-2"><div class="progress-bar bg-primary" style="width: 92%;"></div></div>
                    <div class="quality-row"><span>Konsistensi</span><strong>95%</strong></div>
                    <div class="progress progress-thin mb-2"><div class="progress-bar bg-success" style="width: 95%;"></div></div>
                    <div class="quality-row"><span>Ketepatan Waktu</span><strong>88%</strong></div>
                    <div class="progress progress-thin"><div class="progress-bar bg-warning" style="width: 88%;"></div></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-8">
            <div class="card panel-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><?php echo e($panelsById['teknis-isu']['title']); ?></span>
                    <a class="btn btn-sm btn-outline-primary" href="dashboard_blk_detail.php?item=teknis-isu&<?php echo e($baseFilterQuery); ?>">Detail</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                            <tr>
                                <th>Jenis Isu</th>
                                <th>Jumlah Terdampak</th>
                                <th>Tingkat Keparahan</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td>NIK Duplikat</td>
                                <td>12 Record</td>
                                <td><span class="badge rounded-pill text-bg-danger">High</span></td>
                                <td><span class="badge rounded-pill text-bg-warning badge-status">Perlu Review</span></td>
                            </tr>
                            <tr>
                                <td>Format Tanggal Salah</td>
                                <td>45 Record</td>
                                <td><span class="badge rounded-pill text-bg-warning">Medium</span></td>
                                <td><span class="badge rounded-pill text-bg-warning badge-status">Perlu Review</span></td>
                            </tr>
                            <tr>
                                <td>Data Profil Tidak Lengkap</td>
                                <td>126 Record</td>
                                <td><span class="badge rounded-pill text-bg-primary">Low</span></td>
                                <td><span class="badge rounded-pill text-bg-warning badge-status">Perlu Review</span></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const palette = {
    blue: '#3b82f6',
    green: '#10b981',
    amber: '#f59e0b',
    slate: '#94a3b8',
    cyan: '#06b6d4',
    indigo: '#6366f1'
};

function buildChart(id, type, labels, datasets, options) {
    const el = document.getElementById(id);
    if (!el) return;
    new Chart(el, { type, data: { labels, datasets }, options });
}

buildChart(
    'chart-partisipasi-tren',
    'line',
    <?php echo json_encode($panelsById['partisipasi-tren']['chart_labels']); ?>,
    [{
        label: 'Kios SIAPkerja',
        data: <?php echo json_encode($panelsById['partisipasi-tren']['series'][0]['data']); ?>,
        borderColor: palette.amber,
        backgroundColor: 'rgba(245, 158, 11, 0.15)',
        fill: false,
        tension: 0.35
    }],
    { responsive: true, maintainAspectRatio: false }
);

buildChart(
    'chart-partisipasi-rasio',
    'doughnut',
    <?php echo json_encode($panelsById['partisipasi-rasio']['chart_labels']); ?>,
    [{
        data: <?php echo json_encode($panelsById['partisipasi-rasio']['series'][0]['data']); ?>,
        backgroundColor: [palette.amber, palette.blue],
        borderWidth: 0
    }],
    { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
);

buildChart(
    'chart-output-lulusan-sertifikat',
    'bar',
    <?php echo json_encode($panelsById['output-lulusan-sertifikat']['chart_labels']); ?>,
    [
        {
            label: 'Lulusan',
            data: <?php echo json_encode($panelsById['output-lulusan-sertifikat']['series'][0]['data']); ?>,
            backgroundColor: 'rgba(16, 185, 129, 0.85)'
        },
        {
            label: 'Sertifikat',
            data: <?php echo json_encode($panelsById['output-lulusan-sertifikat']['series'][1]['data']); ?>,
            backgroundColor: 'rgba(148, 163, 184, 0.85)'
        }
    ],
    { responsive: true, maintainAspectRatio: false }
);

buildChart(
    'chart-distribusi-kejuruan',
    'bar',
    <?php echo json_encode($panelsById['distribusi-kejuruan']['chart_labels']); ?>,
    [{
        label: 'Peserta',
        data: <?php echo json_encode($panelsById['distribusi-kejuruan']['series'][0]['data']); ?>,
        backgroundColor: 'rgba(59, 130, 246, 0.9)'
    }],
    {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } }
    }
);

buildChart(
    'chart-distribusi-provinsi',
    'bar',
    <?php echo json_encode($panelsById['distribusi-provinsi']['chart_labels']); ?>,
    [{
        label: 'Peserta',
        data: <?php echo json_encode($panelsById['distribusi-provinsi']['series'][0]['data']); ?>,
        backgroundColor: 'rgba(16, 185, 129, 0.85)'
    }],
    {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } }
    }
);

buildChart(
    'chart-integrasi-gap',
    'bar',
    <?php echo json_encode($panelsById['integrasi-gap']['chart_labels']); ?>,
    [
        {
            label: 'Belum Sinkron',
            data: <?php echo json_encode($panelsById['integrasi-gap']['series'][1]['data']); ?>,
            backgroundColor: 'rgba(148, 163, 184, 0.8)'
        },
        {
            label: 'Tersinkron',
            data: <?php echo json_encode($panelsById['integrasi-gap']['series'][0]['data']); ?>,
            backgroundColor: 'rgba(59, 130, 246, 0.85)'
        }
    ],
    {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        scales: { x: { stacked: true, max: 100 }, y: { stacked: true } }
    }
);

buildChart(
    'chart-outcome-konversi',
    'bar',
    <?php echo json_encode($panelsById['outcome-konversi']['chart_labels']); ?>,
    [{
        label: 'Jumlah',
        data: <?php echo json_encode($panelsById['outcome-konversi']['series'][0]['data']); ?>,
        backgroundColor: ['#3b82f6', '#6366f1', '#10b981']
    }],
    {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } }
    }
);

buildChart(
    'chart-outcome-penempatan',
    'line',
    <?php echo json_encode($panelsById['outcome-penempatan']['chart_labels']); ?>,
    [{
        label: 'Penempatan (%)',
        data: <?php echo json_encode($panelsById['outcome-penempatan']['series'][0]['data']); ?>,
        borderColor: palette.green,
        backgroundColor: 'rgba(16, 185, 129, 0.15)',
        fill: false,
        tension: 0.3
    }],
    { responsive: true, maintainAspectRatio: false, scales: { y: { min: 0, max: 100 } } }
);
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
