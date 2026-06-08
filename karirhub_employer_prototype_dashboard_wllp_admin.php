<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_data.php';
require_once __DIR__ . '/karirhub_employer_prototype_storage.php';
require_once __DIR__ . '/wllp_external_storage.php';
require_once __DIR__ . '/karirhub_employer_prototype_ui.php';
require_once __DIR__ . '/db.php';

if (!kh_proto_can_access('karirhub_employer_prototype_dashboard_wllp_admin_view')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$dataset = karirhub_proto_dataset();
$units = $dataset['units'] ?? [];
$employers = $dataset['employers'] ?? [];
kh_proto_ensure_multi_tables($conn);
kh_proto_seed_multi_from_dataset($conn, $dataset, $units);
wllp_external_ensure_schema($conn);

$filters = [
    'periode_tipe' => trim((string)($_GET['periode_tipe'] ?? 'all')),
    'anchor_mulai' => trim((string)($_GET['anchor_mulai'] ?? '')),
    'anchor_sampai' => trim((string)($_GET['anchor_sampai'] ?? '')),
    'sumber' => trim((string)($_GET['sumber'] ?? 'all')),
    'employer' => trim((string)($_GET['employer'] ?? 'all')),
    'unit' => trim((string)($_GET['unit'] ?? 'all')),
    'status_keterisian' => trim((string)($_GET['status_keterisian'] ?? 'all')),
    'provinsi' => trim((string)($_GET['provinsi'] ?? 'all')),
];
$allowedPeriode = ['all', 'weekly', 'monthly'];
if (!in_array($filters['periode_tipe'], $allowedPeriode, true)) {
    $filters['periode_tipe'] = 'all';
}
$allowedStatus = ['all', 'Terisi', 'Belum Terisi', 'Proses Seleksi', 'Belum Update'];
if (!in_array($filters['status_keterisian'], $allowedStatus, true)) {
    $filters['status_keterisian'] = 'all';
}
$allowedSumber = ['all', 'internal', 'external'];
if (!in_array($filters['sumber'], $allowedSumber, true)) {
    $filters['sumber'] = 'all';
}

$rows = [];
$res = $conn->query("
    SELECT
        'internal' AS sumber_key,
        'WLLP Internal' AS sumber_label,
        h.no_reg_bukti,
        h.periode_tipe,
        CAST(h.periode_anchor AS CHAR) AS periode_anchor,
        CAST(h.periode_mulai AS CHAR) AS periode_mulai,
        CAST(h.periode_selesai AS CHAR) AS periode_selesai,
        h.status_verifikasi,
        d.id_lowongan,
        d.employer_kode,
        d.employer_nama,
        d.unit_kode,
        d.unit_nama,
        d.jabatan,
        d.jumlah_kebutuhan,
        d.provinsi,
        d.kota,
        CAST(d.masa_berlaku_sampai AS CHAR) AS masa_berlaku_sampai,
        COALESCE(s.status_saat_ini, 'Belum Terisi') AS status_keterisian
    FROM karirhub_proto_wllp_pelaporan d
    JOIN karirhub_proto_wllp_laporan h ON h.no_reg_bukti = d.no_reg_bukti
    LEFT JOIN karirhub_proto_wllp_status s ON s.no_reg_bukti = d.no_reg_bukti AND s.id_lowongan = d.id_lowongan
    ORDER BY h.periode_anchor DESC, d.created_at DESC
");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
}
$resExternal = $conn->query("
    SELECT
        'external' AS sumber_key,
        'WLLP External' AS sumber_label,
        r.no_reg_bukti,
        r.period_type AS periode_tipe,
        CAST(r.period_anchor AS CHAR) AS periode_anchor,
        CAST(r.period_start AS CHAR) AS periode_mulai,
        CAST(r.period_end AS CHAR) AS periode_selesai,
        r.verification_status AS status_verifikasi,
        i.id_lowongan,
        r.employer_code AS employer_kode,
        r.employer_name AS employer_nama,
        r.unit_code AS unit_kode,
        r.unit_name AS unit_nama,
        i.title AS jabatan,
        i.headcount_needed AS jumlah_kebutuhan,
        '' AS provinsi,
        '' AS kota,
        CAST(i.valid_until AS CHAR) AS masa_berlaku_sampai,
        COALESCE(s.status, 'Belum Terisi') AS status_keterisian
    FROM wllp_reports r
    JOIN wllp_report_items i ON i.report_id = r.id
    LEFT JOIN wllp_item_statuses s ON s.item_id = i.id
    ORDER BY r.period_anchor DESC, i.created_at DESC
");
if ($resExternal) {
    while ($r = $resExternal->fetch_assoc()) {
        $rows[] = $r;
    }
}

$employerOptions = ['all' => 'Semua Employer'];
$unitOptions = ['all' => 'Semua Unit'];
$provinsiOptions = ['all' => 'Semua Provinsi'];
foreach ($rows as $r) {
    $empKode = (string)($r['employer_kode'] ?? '');
    $empNama = (string)($r['employer_nama'] ?? $empKode);
    if ($empKode !== '' && !isset($employerOptions[$empKode])) {
        $employerOptions[$empKode] = $empNama;
    }
    $unitKode = (string)($r['unit_kode'] ?? '');
    $unitNama = (string)($r['unit_nama'] ?? $unitKode);
    if ($unitKode !== '' && !isset($unitOptions[$unitKode])) {
        $unitOptions[$unitKode] = $unitNama;
    }
    $prov = (string)($r['provinsi'] ?? '');
    if ($prov !== '' && !isset($provinsiOptions[$prov])) {
        $provinsiOptions[$prov] = $prov;
    }
}
foreach ($employers as $empCode => $emp) {
    if (!isset($employerOptions[$empCode])) {
        $employerOptions[$empCode] = (string)($emp['nama'] ?? $empCode);
    }
}
foreach ($units as $unitCode => $unit) {
    if (!isset($unitOptions[$unitCode])) {
        $unitOptions[$unitCode] = (string)($unit['nama'] ?? $unitCode);
    }
}
if (!isset($employerOptions[$filters['employer']])) {
    $filters['employer'] = 'all';
}
if (!isset($unitOptions[$filters['unit']])) {
    $filters['unit'] = 'all';
}
if ($filters['provinsi'] !== 'all' && !isset($provinsiOptions[$filters['provinsi']])) {
    $filters['provinsi'] = 'all';
}

$filteredRows = array_values(array_filter($rows, static function (array $row) use ($filters): bool {
    if ($filters['periode_tipe'] !== 'all' && strtolower((string)($row['periode_tipe'] ?? '')) !== $filters['periode_tipe']) {
        return false;
    }
    $anchor = (string)($row['periode_anchor'] ?? '');
    if ($filters['anchor_mulai'] !== '' && $anchor !== '' && $anchor < $filters['anchor_mulai']) {
        return false;
    }
    if ($filters['anchor_sampai'] !== '' && $anchor !== '' && $anchor > $filters['anchor_sampai']) {
        return false;
    }
    if ($filters['sumber'] !== 'all' && (string)($row['sumber_key'] ?? 'internal') !== $filters['sumber']) {
        return false;
    }
    if ($filters['employer'] !== 'all' && (string)($row['employer_kode'] ?? '') !== $filters['employer']) {
        return false;
    }
    if ($filters['unit'] !== 'all' && (string)($row['unit_kode'] ?? '') !== $filters['unit']) {
        return false;
    }
    if ($filters['status_keterisian'] !== 'all' && (string)($row['status_keterisian'] ?? '') !== $filters['status_keterisian']) {
        return false;
    }
    if ($filters['provinsi'] !== 'all' && (string)($row['provinsi'] ?? '') !== $filters['provinsi']) {
        return false;
    }
    return true;
}));

$today = date('Y-m-d');
$funnel = [
    'dilaporkan' => count($filteredRows),
    'aktif' => 0,
    'terisi' => 0,
    'perlu_update' => 0,
];
$trendMap = [];
$geoMap = [];
$complianceEmployerMap = [];
$complianceUnitMap = [];
$recentDetailRows = [];

foreach ($filteredRows as $row) {
    $statusKeterisian = (string)($row['status_keterisian'] ?? '');
    $statusVerifikasi = (string)($row['status_verifikasi'] ?? '');
    $masaBerlakuSampai = (string)($row['masa_berlaku_sampai'] ?? '');

    if ($statusKeterisian === 'Terisi') {
        $funnel['terisi']++;
    }
    if ($statusKeterisian === 'Belum Update' || $statusVerifikasi === 'Perlu Update') {
        $funnel['perlu_update']++;
    }
    if ($statusKeterisian !== 'Terisi' && ($masaBerlakuSampai === '' || $masaBerlakuSampai >= $today)) {
        $funnel['aktif']++;
    }

    $periodKey = substr((string)($row['periode_anchor'] ?? ''), 0, 7);
    if ($periodKey === '' || strlen($periodKey) < 7) {
        $periodKey = 'N/A';
    }
    if (!isset($trendMap[$periodKey])) {
        $trendMap[$periodKey] = ['period' => $periodKey, 'total' => 0, 'terisi' => 0, 'perlu_update' => 0, 'sample_no_reg' => (string)$row['no_reg_bukti']];
    }
    $trendMap[$periodKey]['total']++;
    if ($statusKeterisian === 'Terisi') {
        $trendMap[$periodKey]['terisi']++;
    }
    if ($statusKeterisian === 'Belum Update' || $statusVerifikasi === 'Perlu Update') {
        $trendMap[$periodKey]['perlu_update']++;
    }

    $geoKey = trim((string)($row['provinsi'] ?? ''));
    if ($geoKey === '') {
        $geoKey = 'Tanpa Provinsi';
    }
    if (!isset($geoMap[$geoKey])) {
        $geoMap[$geoKey] = ['provinsi' => $geoKey, 'total' => 0, 'terisi' => 0, 'kota_utama' => (string)($row['kota'] ?? '-'), 'sample_no_reg' => (string)$row['no_reg_bukti'], 'sample_id' => (string)$row['id_lowongan']];
    }
    $geoMap[$geoKey]['total']++;
    if ($statusKeterisian === 'Terisi') {
        $geoMap[$geoKey]['terisi']++;
    }

    $empCode = (string)($row['employer_kode'] ?? 'EMP-001');
    $empName = (string)($row['employer_nama'] ?? $empCode);
    if (!isset($complianceEmployerMap[$empCode])) {
        $complianceEmployerMap[$empCode] = [
            'employer_kode' => $empCode,
            'employer_nama' => $empName,
            'total' => 0,
            'terisi' => 0,
            'belum_update' => 0,
            'patuh_pct' => 0,
            'sample_unit' => (string)($row['unit_kode'] ?? 'all'),
            'sample_no_reg' => (string)$row['no_reg_bukti'],
        ];
    }
    $complianceEmployerMap[$empCode]['total']++;
    if ($statusKeterisian === 'Terisi') {
        $complianceEmployerMap[$empCode]['terisi']++;
    }
    if ($statusKeterisian === 'Belum Update' || $statusVerifikasi === 'Perlu Update') {
        $complianceEmployerMap[$empCode]['belum_update']++;
    }

    $unitCode = (string)($row['unit_kode'] ?? '');
    $unitName = (string)($row['unit_nama'] ?? $unitCode);
    if (!isset($complianceUnitMap[$unitCode])) {
        $complianceUnitMap[$unitCode] = [
            'unit_kode' => $unitCode,
            'unit_nama' => $unitName,
            'employer_nama' => $empName,
            'total' => 0,
            'terisi' => 0,
            'belum_update' => 0,
            'patuh_pct' => 0,
            'sample_no_reg' => (string)$row['no_reg_bukti'],
            'sample_id' => (string)$row['id_lowongan'],
        ];
    }
    $complianceUnitMap[$unitCode]['total']++;
    if ($statusKeterisian === 'Terisi') {
        $complianceUnitMap[$unitCode]['terisi']++;
    }
    if ($statusKeterisian === 'Belum Update' || $statusVerifikasi === 'Perlu Update') {
        $complianceUnitMap[$unitCode]['belum_update']++;
    }
}

foreach ($complianceEmployerMap as $k => $item) {
    $total = (int)$item['total'];
    $belumUpdate = (int)$item['belum_update'];
    $complianceEmployerMap[$k]['patuh_pct'] = $total > 0 ? (int)round((($total - $belumUpdate) / $total) * 100) : 0;
}
foreach ($complianceUnitMap as $k => $item) {
    $total = (int)$item['total'];
    $belumUpdate = (int)$item['belum_update'];
    $complianceUnitMap[$k]['patuh_pct'] = $total > 0 ? (int)round((($total - $belumUpdate) / $total) * 100) : 0;
}

$trendRows = array_values($trendMap);
usort($trendRows, static fn (array $a, array $b): int => strcmp($a['period'], $b['period']));

$geoRows = array_values($geoMap);
usort($geoRows, static fn (array $a, array $b): int => (int)$b['total'] <=> (int)$a['total']);
$geoRows = array_slice($geoRows, 0, 8);

$complianceByEmployer = array_values($complianceEmployerMap);
usort($complianceByEmployer, static fn (array $a, array $b): int => (int)$b['patuh_pct'] <=> (int)$a['patuh_pct']);

$complianceByUnit = array_values($complianceUnitMap);
usort($complianceByUnit, static fn (array $a, array $b): int => (int)$b['total'] <=> (int)$a['total']);

$recentDetailRows = $filteredRows;
usort($recentDetailRows, static fn (array $a, array $b): int => strcmp((string)$b['periode_anchor'], (string)$a['periode_anchor']));
$recentDetailRows = array_slice($recentDetailRows, 0, 10);

$currentPeriod = date('Y-m');
$previousPeriod = date('Y-m', strtotime('-1 month'));
$currentCount = 0;
$previousCount = 0;
foreach ($filteredRows as $row) {
    $p = substr((string)($row['periode_anchor'] ?? ''), 0, 7);
    if ($p === $currentPeriod) {
        $currentCount++;
    } elseif ($p === $previousPeriod) {
        $previousCount++;
    }
}
$deltaLabel = $previousCount > 0
    ? (($currentCount - $previousCount) >= 0 ? '+' : '') . (string)($currentCount - $previousCount) . ' vs periode sebelumnya'
    : 'Belum ada pembanding periode sebelumnya';

$summaryCards = [
    ['label' => 'Total Lowongan Dilaporkan', 'value' => (string)$funnel['dilaporkan'], 'tone' => 'primary', 'sub' => $deltaLabel],
    ['label' => 'Lowongan Aktif', 'value' => (string)$funnel['aktif'], 'tone' => 'info', 'sub' => 'Status belum terisi dan masa berlaku aktif'],
    ['label' => 'Sudah Terisi', 'value' => (string)$funnel['terisi'], 'tone' => 'success', 'sub' => 'Data status keterisian terkonfirmasi'],
    ['label' => 'Belum Terisi', 'value' => (string)$funnel['perlu_update'], 'tone' => 'warning', 'sub' => 'Butuh tindak lanjut employer/unit'],
];

$trendLabels = array_map(static fn (array $r): string => $r['period'], $trendRows);
$trendTotal = array_map(static fn (array $r): int => (int)$r['total'], $trendRows);
$trendTerisi = array_map(static fn (array $r): int => (int)$r['terisi'], $trendRows);

$funnelLabels = ['Dilaporkan', 'Aktif', 'Terisi', 'Belum Terisi'];
$funnelData = [$funnel['dilaporkan'], $funnel['aktif'], $funnel['terisi'], $funnel['perlu_update']];

$geoLabels = array_map(static fn (array $r): string => $r['provinsi'], $geoRows);
$geoData = array_map(static fn (array $r): int => (int)$r['total'], $geoRows);

$baseFilterParams = [
    'periode_tipe' => $filters['periode_tipe'],
    'anchor_mulai' => $filters['anchor_mulai'],
    'anchor_sampai' => $filters['anchor_sampai'],
    'sumber' => $filters['sumber'],
    'employer' => $filters['employer'],
    'unit' => $filters['unit'],
    'status_keterisian' => $filters['status_keterisian'],
    'provinsi' => $filters['provinsi'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karirhub Employer Prototype - Dashboard WLLP Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php kh_proto_render_styles(); ?>
    <style>
        .kh-admin-chart-wrap { min-height: 280px; }
        .kh-admin-card-sub { font-size: 12px; color: #6c757d; }
        .kh-admin-filter .form-label { font-size: 12px; margin-bottom: 4px; color: #54657a; }
    </style>
</head>
<body class="kh-proto-page">
<?php include 'navbar.php'; ?>
<?php kh_proto_render_hero('Dashboard WLLP Admin', 'Analitik lintas employer untuk monitoring WLLP prototype.', 'Lowongan Kerja', 'karirhub_employer_prototype_pelaporan_lowongan', 'Admin', 'karirhub_employer_prototype_dashboard_wllp_admin'); ?>

<div class="kh-content-wrap">
<div class="container py-4">
    <div class="kh-proto-shell">
    <?php kh_proto_render_sidebar('dashboard_wllp_admin'); ?>
    <main class="kh-proto-main">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-0">Dashboard WLLP Admin</h3>
            <div class="text-muted small">Visualisasi lintas employer, unit, periode, dan wilayah</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-primary btn-sm" href="karirhub_employer_prototype_bukti_lapor?<?php echo h(http_build_query(['status' => 'all', 'unit' => $filters['unit'] === 'all' ? 'all' : $filters['unit']])); ?>">
                <i class="bi bi-file-earmark-check me-1"></i>Lihat Bukti Lapor
            </a>
            <a class="btn btn-primary btn-sm" href="karirhub_employer_prototype_dashboard_wllp_admin">
                <i class="bi bi-arrow-clockwise me-1"></i>Reset Filter
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 kh-admin-filter">
                <div class="col-6 col-md-3">
                    <label class="form-label">Periode Tipe</label>
                    <select name="periode_tipe" class="form-select form-select-sm">
                        <option value="all"<?php echo $filters['periode_tipe'] === 'all' ? ' selected' : ''; ?>>Semua</option>
                        <option value="weekly"<?php echo $filters['periode_tipe'] === 'weekly' ? ' selected' : ''; ?>>Weekly</option>
                        <option value="monthly"<?php echo $filters['periode_tipe'] === 'monthly' ? ' selected' : ''; ?>>Monthly</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">Anchor Mulai</label>
                    <input type="date" name="anchor_mulai" value="<?php echo h($filters['anchor_mulai']); ?>" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">Anchor Sampai</label>
                    <input type="date" name="anchor_sampai" value="<?php echo h($filters['anchor_sampai']); ?>" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">Employer</label>
                    <select name="employer" class="form-select form-select-sm">
                        <?php foreach ($employerOptions as $code => $name): ?>
                            <option value="<?php echo h((string)$code); ?>"<?php echo $filters['employer'] === (string)$code ? ' selected' : ''; ?>><?php echo h((string)$name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">Sumber</label>
                    <select name="sumber" class="form-select form-select-sm">
                        <option value="all"<?php echo $filters['sumber'] === 'all' ? ' selected' : ''; ?>>Semua Sumber</option>
                        <option value="internal"<?php echo $filters['sumber'] === 'internal' ? ' selected' : ''; ?>>WLLP Internal</option>
                        <option value="external"<?php echo $filters['sumber'] === 'external' ? ' selected' : ''; ?>>WLLP External</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">Unit</label>
                    <select name="unit" class="form-select form-select-sm">
                        <?php foreach ($unitOptions as $code => $name): ?>
                            <option value="<?php echo h((string)$code); ?>"<?php echo $filters['unit'] === (string)$code ? ' selected' : ''; ?>><?php echo h((string)$name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">Status Keterisian</label>
                    <select name="status_keterisian" class="form-select form-select-sm">
                        <?php foreach ($allowedStatus as $st): ?>
                            <option value="<?php echo h($st); ?>"<?php echo $filters['status_keterisian'] === $st ? ' selected' : ''; ?>><?php echo h($st === 'all' ? 'Semua' : $st); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label">Provinsi</label>
                    <select name="provinsi" class="form-select form-select-sm">
                        <?php foreach ($provinsiOptions as $code => $name): ?>
                            <option value="<?php echo h((string)$code); ?>"<?php echo $filters['provinsi'] === (string)$code ? ' selected' : ''; ?>><?php echo h((string)$name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel me-1"></i>Terapkan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <?php foreach ($summaryCards as $card): ?>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small"><?php echo h($card['label']); ?></div>
                        <div class="fs-4 fw-semibold text-<?php echo h($card['tone']); ?>"><?php echo h($card['value']); ?></div>
                        <div class="kh-admin-card-sub"><?php echo h($card['sub']); ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="card-title mb-0">Tren Pelaporan per Periode</h5>
                        <span class="text-muted small">Detail per bulan/periode anchor</span>
                    </div>
                    <div class="kh-admin-chart-wrap"><canvas id="trendChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="card-title mb-0">Funnel WLLP</h5>
                        <span class="text-muted small">Status agregat</span>
                    </div>
                    <div class="kh-admin-chart-wrap"><canvas id="funnelChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title mb-2">Distribusi Wilayah (Top Provinsi)</h5>
                    <div class="kh-admin-chart-wrap"><canvas id="geoChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title mb-2">Kepatuhan per Employer</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Employer</th>
                                    <th>Total</th>
                                    <th>Terisi</th>
                                    <th>Belum Update</th>
                                    <th>Kepatuhan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($complianceByEmployer as $row): ?>
                                    <tr>
                                        <td><?php echo h((string)$row['employer_nama']); ?></td>
                                        <td><?php echo h((string)$row['total']); ?></td>
                                        <td><?php echo h((string)$row['terisi']); ?></td>
                                        <td><?php echo h((string)$row['belum_update']); ?></td>
                                        <td><?php echo h((string)$row['patuh_pct']); ?>%</td>
                                        <td>
                                            <a class="btn btn-outline-primary btn-sm"
                                               href="karirhub_employer_prototype_bukti_lapor?<?php echo h(http_build_query(['status' => 'all', 'unit' => (string)$row['sample_unit'], 'q' => (string)$row['sample_no_reg']])); ?>">
                                                Lihat Detail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($complianceByEmployer)): ?>
                                    <tr><td colspan="6" class="text-center text-muted py-3">Belum ada data.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title mb-2">Detail Tren Periode</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Periode</th>
                                    <th>Total</th>
                                    <th>Terisi</th>
                                    <th>Belum Terisi</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($trendRows as $row): ?>
                                    <tr>
                                        <td><?php echo h((string)$row['period']); ?></td>
                                        <td><?php echo h((string)$row['total']); ?></td>
                                        <td><?php echo h((string)$row['terisi']); ?></td>
                                        <td><?php echo h((string)$row['perlu_update']); ?></td>
                                        <td>
                                            <a class="btn btn-outline-secondary btn-sm"
                                               href="karirhub_employer_prototype_no_reg_bukti?<?php echo h(http_build_query(['q' => (string)$row['sample_no_reg'], 'verifikasi' => 'all'])); ?>">
                                                Lihat Detail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($trendRows)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-3">Belum ada data.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="card-title mb-2">Detail Geografis</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Provinsi</th>
                                    <th>Total</th>
                                    <th>Terisi</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($geoRows as $row): ?>
                                    <tr>
                                        <td><?php echo h((string)$row['provinsi']); ?></td>
                                        <td><?php echo h((string)$row['total']); ?></td>
                                        <td><?php echo h((string)$row['terisi']); ?></td>
                                        <td>
                                            <a class="btn btn-outline-secondary btn-sm"
                                               href="karirhub_employer_prototype_status_keterisian?<?php echo h(http_build_query(['simulate_no_reg' => (string)$row['sample_no_reg'], 'simulate_id_lowongan' => (string)$row['sample_id']])); ?>">
                                                Lihat Detail
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($geoRows)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">Belum ada data.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-2">Rincian Lowongan (Top 10)</h5>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>No. Reg</th>
                            <th>ID Lowongan</th>
                            <th>Employer</th>
                            <th>Unit</th>
                            <th>Jabatan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentDetailRows as $row): ?>
                            <tr>
                                <td><?php echo h((string)$row['no_reg_bukti']); ?></td>
                                <td><?php echo h((string)$row['id_lowongan']); ?></td>
                                <td><?php echo h((string)$row['employer_nama']); ?></td>
                                <td><?php echo h((string)$row['unit_nama']); ?></td>
                                <td><?php echo h((string)$row['jabatan']); ?></td>
                                <td><span class="badge text-bg-<?php echo h(karirhub_proto_status_badge_class((string)$row['status_keterisian'])); ?>"><?php echo h((string)$row['status_keterisian']); ?></span></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a class="btn btn-outline-primary btn-sm" href="karirhub_employer_prototype_bukti_lapor?<?php echo h(http_build_query(['action' => 'lihat', 'no_reg' => (string)$row['no_reg_bukti'], 'status' => 'all', 'unit' => (string)$row['unit_kode']])); ?>">Bukti</a>
                                        <a class="btn btn-outline-secondary btn-sm" href="karirhub_employer_prototype_status_keterisian?<?php echo h(http_build_query(['simulate_no_reg' => (string)$row['no_reg_bukti'], 'simulate_id_lowongan' => (string)$row['id_lowongan']])); ?>">Status</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentDetailRows)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-3">Belum ada data untuk filter saat ini.</td></tr>
                        <?php endif; ?>
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
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
    (function () {
        const trendLabels = <?php echo json_encode($trendLabels, JSON_UNESCAPED_UNICODE); ?>;
        const trendTotal = <?php echo json_encode($trendTotal); ?>;
        const trendTerisi = <?php echo json_encode($trendTerisi); ?>;
        const funnelLabels = <?php echo json_encode($funnelLabels, JSON_UNESCAPED_UNICODE); ?>;
        const funnelData = <?php echo json_encode($funnelData); ?>;
        const geoLabels = <?php echo json_encode($geoLabels, JSON_UNESCAPED_UNICODE); ?>;
        const geoData = <?php echo json_encode($geoData); ?>;

        const trendCtx = document.getElementById('trendChart');
        if (trendCtx) {
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [
                        {
                            label: 'Total Laporan',
                            data: trendTotal,
                            borderColor: '#0d6efd',
                            backgroundColor: 'rgba(13,110,253,0.2)',
                            tension: 0.3
                        },
                        {
                            label: 'Terisi',
                            data: trendTerisi,
                            borderColor: '#198754',
                            backgroundColor: 'rgba(25,135,84,0.2)',
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }

        const funnelCtx = document.getElementById('funnelChart');
        if (funnelCtx) {
            new Chart(funnelCtx, {
                type: 'bar',
                data: {
                    labels: funnelLabels,
                    datasets: [{
                        label: 'Jumlah',
                        data: funnelData,
                        backgroundColor: ['#0d6efd', '#0dcaf0', '#198754', '#ffc107']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });
        }

        const geoCtx = document.getElementById('geoChart');
        if (geoCtx) {
            new Chart(geoCtx, {
                type: 'doughnut',
                data: {
                    labels: geoLabels,
                    datasets: [{
                        label: 'Total',
                        data: geoData,
                        backgroundColor: ['#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#fd7e14', '#198754', '#20c997', '#0dcaf0']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }
    })();
</script>
<?php kh_proto_render_sidebar_script(); ?>
</body>
</html>
