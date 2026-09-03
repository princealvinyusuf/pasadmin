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
    ['label' => 'Total Lowongan Dilaporkan', 'value' => (string)$funnel['dilaporkan'], 'tone' => 'blue', 'icon' => 'bi-briefcase-fill', 'sub' => $deltaLabel],
    ['label' => 'Lowongan Aktif', 'value' => (string)$funnel['aktif'], 'tone' => 'cyan', 'icon' => 'bi-broadcast-pin', 'sub' => 'Status belum terisi dan masa berlaku aktif'],
    ['label' => 'Sudah Terisi', 'value' => (string)$funnel['terisi'], 'tone' => 'green', 'icon' => 'bi-person-check-fill', 'sub' => 'Data status keterisian terkonfirmasi'],
    ['label' => 'Belum Terisi', 'value' => (string)$funnel['perlu_update'], 'tone' => 'orange', 'icon' => 'bi-hourglass-split', 'sub' => 'Butuh tindak lanjut employer/unit'],
];

$activeFilterCount = 0;
foreach ($filters as $filterKey => $filterValue) {
    $isDefault = in_array($filterKey, ['anchor_mulai', 'anchor_sampai'], true)
        ? $filterValue === ''
        : $filterValue === 'all';
    if (!$isDefault) {
        $activeFilterCount++;
    }
}

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
        .kh-admin-dashboard {
            --admin-ink: #172b4d;
            --admin-muted: #6b7f94;
            --admin-line: #dfe8f2;
            --admin-blue: #155eef;
        }
        .kh-admin-dashboard .kh-proto-main { color: var(--admin-ink); }
        .kh-admin-hero {
            position: relative;
            overflow: hidden;
            padding: clamp(1.2rem, 3vw, 1.8rem);
            border-radius: 1rem;
            color: #fff;
            background:
                radial-gradient(circle at 88% 10%, rgba(255,255,255,.16), transparent 28%),
                linear-gradient(125deg, #102f6f 0%, #155eef 58%, #397ff0 100%);
            box-shadow: 0 15px 34px rgba(21, 94, 239, .2);
        }
        .kh-admin-hero::after {
            position: absolute;
            right: -55px;
            bottom: -95px;
            width: 220px;
            height: 220px;
            border: 34px solid rgba(255,255,255,.07);
            border-radius: 50%;
            content: "";
        }
        .kh-admin-hero-content { position: relative; z-index: 1; }
        .kh-admin-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            margin-bottom: .4rem;
            padding: .25rem .6rem;
            border-radius: 999px;
            color: #dce9ff;
            font-size: .68rem;
            font-weight: 750;
            letter-spacing: .07em;
            text-transform: uppercase;
            background: rgba(255,255,255,.12);
        }
        .kh-admin-hero h3 { font-size: clamp(1.35rem, 3vw, 1.85rem); font-weight: 760; }
        .kh-admin-hero-copy { color: #dce8ff; font-size: .82rem; }
        .kh-admin-hero .btn-outline-light { border-color: rgba(255,255,255,.65); }
        .kh-admin-hero .btn-light { color: #124dbf; font-weight: 700; }
        .kh-admin-filter-card,
        .kh-admin-panel,
        .kh-admin-summary-card {
            border: 1px solid var(--admin-line) !important;
            border-radius: .95rem !important;
            box-shadow: 0 8px 24px rgba(36, 67, 104, .06) !important;
        }
        .kh-admin-filter-card { overflow: hidden; }
        .kh-admin-filter-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .9rem 1.1rem;
            border-bottom: 1px solid #e7edf4;
            background: linear-gradient(135deg, #f8fbff, #f2f7ff);
        }
        .kh-admin-filter-title { color: #1e3b5c; font-size: .85rem; font-weight: 750; }
        .kh-admin-filter-badge {
            display: inline-flex;
            padding: .2rem .5rem;
            border-radius: 999px;
            color: #155eef;
            font-size: .65rem;
            font-weight: 700;
            background: #e7f0ff;
        }
        .kh-admin-filter .form-label {
            margin-bottom: 5px;
            color: #526a82;
            font-size: 11px;
            font-weight: 650;
        }
        .kh-admin-filter .form-control,
        .kh-admin-filter .form-select {
            min-height: 39px;
            border-color: #cedae7;
            border-radius: .55rem;
            color: #294663;
            background-color: #fff;
        }
        .kh-admin-filter .form-control:focus,
        .kh-admin-filter .form-select:focus {
            border-color: #5790f5;
            box-shadow: 0 0 0 .2rem rgba(21, 94, 239, .11);
        }
        .kh-admin-summary-card {
            height: 100%;
            padding: 1rem;
            background: #fff;
            transition: transform 180ms ease, box-shadow 180ms ease;
        }
        .kh-admin-summary-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 13px 28px rgba(36, 67, 104, .11) !important;
        }
        .kh-admin-summary-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: .75rem;
        }
        .kh-admin-summary-label {
            color: #61758a;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .kh-admin-summary-value {
            margin-top: .3rem;
            color: #142b4b;
            font-size: 1.65rem;
            font-weight: 780;
            line-height: 1;
        }
        .kh-admin-summary-icon {
            display: grid;
            width: 40px;
            height: 40px;
            place-items: center;
            border-radius: .72rem;
        }
        .kh-admin-summary-icon.blue { color: #155eef; background: #eaf1ff; }
        .kh-admin-summary-icon.cyan { color: #1689a8; background: #e9f9fc; }
        .kh-admin-summary-icon.green { color: #087e5b; background: #eaf9f4; }
        .kh-admin-summary-icon.orange { color: #c46a16; background: #fff4e8; }
        .kh-admin-card-sub { margin-top: .65rem; color: #7b8ca0; font-size: 11px; }
        .kh-admin-panel { height: 100%; overflow: hidden; background: #fff; }
        .kh-admin-panel .card-body { padding: 1rem 1.1rem; }
        .kh-admin-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            margin-bottom: .85rem;
        }
        .kh-admin-panel-title {
            margin: 0;
            color: #183858;
            font-size: .92rem;
            font-weight: 750;
        }
        .kh-admin-panel-sub { color: #8090a2; font-size: .65rem; }
        .kh-admin-panel-icon {
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: .6rem;
            color: #155eef;
            background: #eaf2ff;
        }
        .kh-admin-chart-wrap { min-height: 280px; }
        .kh-admin-table { margin: 0; }
        .kh-admin-table thead th {
            padding: .7rem .65rem;
            border: 0;
            border-bottom: 1px solid #dfe7f0;
            color: #66788c;
            font-size: .63rem;
            font-weight: 750;
            letter-spacing: .04em;
            text-transform: uppercase;
            background: #f8fafd !important;
            white-space: nowrap;
        }
        .kh-admin-table tbody td {
            padding: .7rem .65rem;
            border-color: #ebf0f5;
            color: #354f69;
            font-size: .71rem;
        }
        .kh-admin-table tbody tr:hover { background: #fbfdff; }
        .kh-admin-table .btn {
            border-radius: .45rem;
            font-size: .65rem;
        }
        .kh-compliance-wrap { min-width: 105px; }
        .kh-compliance-value { color: #31506e; font-size: .67rem; font-weight: 700; }
        .kh-compliance-track {
            height: 5px;
            margin-top: .25rem;
            overflow: hidden;
            border-radius: 999px;
            background: #e9eef4;
        }
        .kh-compliance-fill {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #0b8f69, #3fc39a);
        }
        .kh-admin-status {
            display: inline-flex;
            padding: .23rem .52rem;
            border-radius: 999px;
            color: #087e5b;
            font-size: .63rem;
            font-weight: 700;
            background: #eaf9f4;
            white-space: nowrap;
        }
        .kh-admin-data-quality {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .75rem;
            padding-top: .4rem;
        }
        .kh-admin-quality-item {
            padding: 1rem;
            border: 1px solid #e1e9f2;
            border-radius: .75rem;
            background: #f9fbfd;
        }
        .kh-admin-quality-value { color: #183858; font-size: 1.25rem; font-weight: 780; }
        .kh-admin-quality-label { color: #708298; font-size: .68rem; }
        @media (max-width: 767px) {
            .kh-admin-hero-actions { width: 100%; }
            .kh-admin-hero-actions .btn { flex: 1 1 auto; }
            .kh-admin-data-quality { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="kh-proto-page kh-admin-dashboard">
<?php include 'navbar.php'; ?>
<?php kh_proto_render_hero('Dashboard WLLP Admin', 'Analitik lintas employer untuk monitoring WLLP prototype.', 'Lowongan Kerja', 'karirhub_employer_prototype_pelaporan_lowongan', 'Admin', 'karirhub_employer_prototype_dashboard_wllp_admin', false); ?>

<div class="kh-content-wrap">
<div class="container py-4">
    <div class="kh-proto-shell">
    <?php kh_proto_render_sidebar('dashboard_wllp_admin'); ?>
    <main class="kh-proto-main">
    <section class="kh-admin-hero mb-3">
        <div class="kh-admin-hero-content d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="kh-admin-eyebrow"><i class="bi bi-shield-lock-fill"></i> Admin Analytics</div>
                <h3 class="mb-1">Dashboard WLLP Admin</h3>
                <div class="kh-admin-hero-copy">Pantau pelaporan lintas employer, unit, periode, dan wilayah dalam satu tampilan.</div>
            </div>
            <div class="kh-admin-hero-actions d-flex flex-wrap gap-2">
            <a class="btn btn-outline-light btn-sm" href="karirhub_employer_prototype_bukti_lapor?<?php echo h(http_build_query(['status' => 'all', 'unit' => $filters['unit'] === 'all' ? 'all' : $filters['unit']])); ?>">
                <i class="bi bi-file-earmark-check me-1"></i>Lihat Bukti Lapor
            </a>
            <a class="btn btn-light btn-sm" href="karirhub_employer_prototype_dashboard_wllp_admin">
                <i class="bi bi-arrow-clockwise me-1"></i>Reset Filter
            </a>
            </div>
        </div>
    </section>

    <div class="card kh-admin-filter-card border-0 shadow-sm mb-3">
        <div class="kh-admin-filter-head">
            <div>
                <div class="kh-admin-filter-title"><i class="bi bi-sliders me-1"></i>Filter Analitik</div>
                <div class="kh-admin-panel-sub">Sesuaikan data yang ditampilkan pada seluruh panel</div>
            </div>
            <span class="kh-admin-filter-badge"><?php echo h((string)$activeFilterCount); ?> filter aktif</span>
        </div>
        <div class="card-body p-3">
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
                <div class="col-6 col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel me-1"></i>Terapkan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <?php foreach ($summaryCards as $card): ?>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="kh-admin-summary-card">
                    <div class="kh-admin-summary-head">
                        <div>
                            <div class="kh-admin-summary-label"><?php echo h($card['label']); ?></div>
                            <div class="kh-admin-summary-value"><?php echo h($card['value']); ?></div>
                        </div>
                        <span class="kh-admin-summary-icon <?php echo h($card['tone']); ?>">
                            <i class="bi <?php echo h($card['icon']); ?>"></i>
                        </span>
                    </div>
                    <div class="kh-admin-card-sub"><?php echo h($card['sub']); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-8">
            <div class="card kh-admin-panel border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="kh-admin-panel-head">
                        <div>
                            <h5 class="kh-admin-panel-title">Tren Pelaporan per Periode</h5>
                            <span class="kh-admin-panel-sub">Detail per bulan dan periode anchor</span>
                        </div>
                        <span class="kh-admin-panel-icon"><i class="bi bi-graph-up-arrow"></i></span>
                    </div>
                    <div class="kh-admin-chart-wrap"><canvas id="trendChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="card kh-admin-panel border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="kh-admin-panel-head">
                        <div>
                            <h5 class="kh-admin-panel-title">Funnel WLLP</h5>
                            <span class="kh-admin-panel-sub">Status agregat lowongan</span>
                        </div>
                        <span class="kh-admin-panel-icon"><i class="bi bi-funnel-fill"></i></span>
                    </div>
                    <div class="kh-admin-chart-wrap"><canvas id="funnelChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-4">
            <div class="card kh-admin-panel border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="kh-admin-panel-head">
                        <div>
                            <h5 class="kh-admin-panel-title">Match NIK SIAPKerja</h5>
                            <span class="kh-admin-panel-sub">Match vs unmatch</span>
                        </div>
                        <span class="kh-admin-panel-icon"><i class="bi bi-person-vcard"></i></span>
                    </div>
                    <div class="kh-admin-chart-wrap"><canvas id="nikMatchChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-8">
            <div class="card kh-admin-panel border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="kh-admin-panel-head">
                        <div>
                            <h5 class="kh-admin-panel-title">Kualitas Data Identitas</h5>
                            <span class="kh-admin-panel-sub">Ringkasan validasi NIK terhadap SIAPKerja</span>
                        </div>
                        <span class="kh-admin-panel-icon"><i class="bi bi-database-check"></i></span>
                    </div>
                    <div class="kh-admin-data-quality">
                        <div class="kh-admin-quality-item">
                            <div class="kh-admin-quality-value text-success">80%</div>
                            <div class="kh-admin-quality-label">NIK berhasil dipadankan dengan SIAPKerja</div>
                        </div>
                        <div class="kh-admin-quality-item">
                            <div class="kh-admin-quality-value text-danger">20%</div>
                            <div class="kh-admin-quality-label">NIK memerlukan verifikasi atau perbaikan</div>
                        </div>
                    </div>
                    <div class="alert alert-info small mt-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        Prioritaskan tindak lanjut pada data yang belum cocok untuk menjaga kualitas pelaporan.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-5">
            <div class="card kh-admin-panel border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="kh-admin-panel-head">
                        <div>
                            <h5 class="kh-admin-panel-title">Distribusi Wilayah</h5>
                            <span class="kh-admin-panel-sub">Top provinsi berdasarkan jumlah lowongan</span>
                        </div>
                        <span class="kh-admin-panel-icon"><i class="bi bi-geo-alt-fill"></i></span>
                    </div>
                    <div class="kh-admin-chart-wrap"><canvas id="geoChart"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-7">
            <div class="card kh-admin-panel border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="kh-admin-panel-head">
                        <div>
                            <h5 class="kh-admin-panel-title">Kepatuhan per Employer</h5>
                            <span class="kh-admin-panel-sub">Tingkat pembaruan status lowongan</span>
                        </div>
                        <span class="kh-admin-panel-icon"><i class="bi bi-building-check"></i></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm kh-admin-table align-middle mb-0">
                            <thead>
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
                                        <td>
                                            <div class="kh-compliance-wrap">
                                                <div class="kh-compliance-value"><?php echo h((string)$row['patuh_pct']); ?>%</div>
                                                <div class="kh-compliance-track">
                                                    <div class="kh-compliance-fill" style="width: <?php echo h((string)$row['patuh_pct']); ?>%;"></div>
                                                </div>
                                            </div>
                                        </td>
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
            <div class="card kh-admin-panel border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="kh-admin-panel-head">
                        <div>
                            <h5 class="kh-admin-panel-title">Detail Tren Periode</h5>
                            <span class="kh-admin-panel-sub">Rincian performa berdasarkan periode</span>
                        </div>
                        <span class="kh-admin-panel-icon"><i class="bi bi-calendar3"></i></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm kh-admin-table align-middle mb-0">
                            <thead>
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
            <div class="card kh-admin-panel border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="kh-admin-panel-head">
                        <div>
                            <h5 class="kh-admin-panel-title">Detail Geografis</h5>
                            <span class="kh-admin-panel-sub">Sebaran lowongan per provinsi</span>
                        </div>
                        <span class="kh-admin-panel-icon"><i class="bi bi-map"></i></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm kh-admin-table align-middle mb-0">
                            <thead>
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

    <div class="card kh-admin-panel border-0 shadow-sm">
        <div class="card-body">
            <div class="kh-admin-panel-head">
                <div>
                    <h5 class="kh-admin-panel-title">Rincian Lowongan</h5>
                    <span class="kh-admin-panel-sub">10 lowongan terbaru untuk filter saat ini</span>
                </div>
                <span class="kh-admin-panel-icon"><i class="bi bi-list-check"></i></span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm kh-admin-table align-middle mb-0">
                    <thead>
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
                                <td><span class="kh-admin-status"><?php echo h((string)$row['status_keterisian']); ?></span></td>
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
        const nikMatchLabels = ['Match', 'Unmatch'];
        const nikMatchData = [80, 20];

        Chart.defaults.color = '#6b7f94';
        Chart.defaults.font.family = 'system-ui, -apple-system, "Segoe UI", sans-serif';
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.boxWidth = 8;

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
                            borderColor: '#155eef',
                            backgroundColor: 'rgba(21,94,239,0.10)',
                            borderWidth: 2,
                            pointRadius: 3,
                            pointBackgroundColor: '#155eef',
                            fill: true,
                            tension: 0.35
                        },
                        {
                            label: 'Terisi',
                            data: trendTerisi,
                            borderColor: '#0b8f69',
                            backgroundColor: 'rgba(11,143,105,0.08)',
                            borderWidth: 2,
                            pointRadius: 3,
                            pointBackgroundColor: '#0b8f69',
                            fill: true,
                            tension: 0.35
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, grid: { color: '#edf1f5' } }
                    }
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
                        backgroundColor: ['#155eef', '#28b4d0', '#0b8f69', '#efa13c'],
                        borderRadius: 7,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, grid: { color: '#edf1f5' } }
                    }
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
                        backgroundColor: ['#155eef', '#6e56cf', '#9b51cf', '#d74d86', '#ed8b32', '#0b8f69', '#24aa93', '#28b4d0'],
                        borderColor: '#ffffff',
                        borderWidth: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '58%',
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }

        const nikMatchCtx = document.getElementById('nikMatchChart');
        if (nikMatchCtx) {
            new Chart(nikMatchCtx, {
                type: 'doughnut',
                data: {
                    labels: nikMatchLabels,
                    datasets: [{
                        data: nikMatchData,
                        backgroundColor: ['#0b8f69', '#e35d6a'],
                        borderColor: '#ffffff',
                        borderWidth: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '62%',
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }
    })();
</script>
<?php kh_proto_render_sidebar_script(); ?>
</body>
</html>
