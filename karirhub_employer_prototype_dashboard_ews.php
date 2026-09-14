<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_ui.php';

if (!kh_proto_can_access('karirhub_employer_prototype_ews_view')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function ews_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$summary = [
    ['label' => 'Pemberi Kerja Berisiko', 'value' => 18, 'meta' => 'Level Low–Urgent', 'icon' => 'bi-buildings', 'tone' => 'indigo'],
    ['label' => 'Lowongan Berisiko', 'value' => 31, 'meta' => 'Level Low–Urgent', 'icon' => 'bi-briefcase', 'tone' => 'cyan'],
    ['label' => 'Sinyal Baru 24 Jam', 'value' => 42, 'meta' => '+8 dari periode sebelumnya', 'icon' => 'bi-broadcast', 'tone' => 'blue'],
    ['label' => 'High', 'value' => 14, 'meta' => '9 lowongan · 5 employer', 'icon' => 'bi-exclamation-diamond', 'tone' => 'amber'],
    ['label' => 'Urgent', 'value' => 6, 'meta' => '5 lowongan · 1 employer', 'icon' => 'bi-shield-exclamation', 'tone' => 'red'],
    ['label' => 'Data Freshness', 'value' => '09:45', 'meta' => 'Scan terakhir 09:42', 'icon' => 'bi-arrow-clockwise', 'tone' => 'green'],
];

$riskDistribution = [
    ['label' => 'Normal', 'value' => 124, 'percent' => 100, 'tone' => 'normal'],
    ['label' => 'Low', 'value' => 37, 'percent' => 30, 'tone' => 'low'],
    ['label' => 'Medium', 'value' => 21, 'percent' => 17, 'tone' => 'medium'],
    ['label' => 'High', 'value' => 14, 'percent' => 11, 'tone' => 'high'],
    ['label' => 'Urgent', 'value' => 6, 'percent' => 5, 'tone' => 'urgent'],
];

$topSignals = [
    ['code' => 'EWS-CNT-01', 'category' => 'Content', 'label' => 'Permintaan biaya/pembayaran', 'value' => 16, 'percent' => 100],
    ['code' => 'EWS-CNT-03', 'category' => 'Content', 'label' => 'Tautan/kanal eksternal berisiko', 'value' => 12, 'percent' => 75],
    ['code' => 'EWS-CNT-04', 'category' => 'Content', 'label' => 'Identitas konten tidak sesuai', 'value' => 9, 'percent' => 56],
    ['code' => 'EWS-ID-03', 'category' => 'Identity', 'label' => 'Kontak digunakan lintas entitas', 'value' => 7, 'percent' => 44],
    ['code' => 'EWS-BHV-01', 'category' => 'Behavior', 'label' => 'Lonjakan posting lowongan', 'value' => 6, 'percent' => 38],
];

$riskTrend = [
    ['label' => '27 Agu', 'employer' => 8, 'vacancy' => 14],
    ['label' => '28 Agu', 'employer' => 10, 'vacancy' => 18],
    ['label' => '29 Agu', 'employer' => 9, 'vacancy' => 17],
    ['label' => '30 Agu', 'employer' => 13, 'vacancy' => 22],
    ['label' => '31 Agu', 'employer' => 14, 'vacancy' => 25],
    ['label' => '01 Sep', 'employer' => 16, 'vacancy' => 28],
    ['label' => '02 Sep', 'employer' => 18, 'vacancy' => 31],
];

$regions = [
    ['label' => 'DKI Jakarta', 'urgent' => 3, 'high' => 8, 'total' => 18, 'percent' => 100],
    ['label' => 'Jawa Barat', 'urgent' => 2, 'high' => 4, 'total' => 13, 'percent' => 72],
    ['label' => 'Jawa Timur', 'urgent' => 1, 'high' => 2, 'total' => 9, 'percent' => 50],
    ['label' => 'Sulawesi Selatan', 'urgent' => 0, 'high' => 2, 'total' => 6, 'percent' => 33],
];

$vacancies = [
    [
        'id' => 'VAC-2026-008431',
        'type' => 'Vacancy',
        'name' => 'Staff Administrasi',
        'employer' => 'PT Cahaya Karier Digital',
        'score' => 88,
        'level' => 'Urgent',
        'signal_code' => 'EWS-CNT-01',
        'signal' => 'Permintaan biaya/pembayaran',
        'category' => 'Content',
        'count' => 3,
        'region' => 'Jakarta Selatan',
        'verification' => 'Terverifikasi',
        'publication' => 'Tayang',
        'source' => 'Native',
        'scan' => '02 Sep 2026 09:31',
        'rule_version' => 'v1.2.0',
        'evidence' => 'Deskripsi memuat frasa “biaya administrasi Rp150.000” dan rekening tujuan.',
        'current_data' => 'Frasa dan rekening masih terdapat pada deskripsi aktif.',
        'detected_at' => '02 Sep 2026 09:31',
        'history' => '88 Urgent (02 Sep) ← 34 Low (01 Sep)',
        'linked' => 'PT Cahaya Karier Digital · EMP-2026-002145',
        'verification_ref' => 'VRF-VAC-260902-8431',
    ],
    [
        'id' => 'VAC-2026-008396', 'type' => 'Vacancy', 'name' => 'Customer Service', 'employer' => 'CV Mitra Karya Utama',
        'score' => 67, 'level' => 'High', 'signal_code' => 'EWS-CNT-03', 'signal' => 'Tautan eksternal berisiko', 'category' => 'Content', 'count' => 2,
        'region' => 'Bandung', 'verification' => 'Terverifikasi', 'publication' => 'Tayang', 'source' => 'Integration', 'scan' => '02 Sep 2026 08:54',
        'rule_version' => 'v1.2.0', 'evidence' => 'Short URL mengarah ke domain yang tidak selaras dengan employer.', 'current_data' => 'Short URL masih aktif pada deskripsi.',
        'detected_at' => '02 Sep 2026 08:54', 'history' => '67 High (02 Sep) ← 18 Low (30 Agu)', 'linked' => 'CV Mitra Karya Utama · EMP-2026-002098', 'verification_ref' => 'VRF-VAC-260902-8396',
    ],
    [
        'id' => 'VAC-2026-008362', 'type' => 'Vacancy', 'name' => 'Data Entry', 'employer' => 'PT Solusi Talenta',
        'score' => 48, 'level' => 'Medium', 'signal_code' => 'EWS-CNT-04', 'signal' => 'Identitas konten tidak sesuai', 'category' => 'Content', 'count' => 2,
        'region' => 'Surabaya', 'verification' => 'Menunggu', 'publication' => 'Draft', 'source' => 'Native', 'scan' => '02 Sep 2026 08:20',
        'rule_version' => 'v1.2.0', 'evidence' => 'Nama perusahaan pada konten berbeda dari profil employer.', 'current_data' => 'Konten belum diperbarui.',
        'detected_at' => '02 Sep 2026 08:20', 'history' => '48 Medium (02 Sep) ← Belum Dipindai', 'linked' => 'PT Solusi Talenta · EMP-2026-002054', 'verification_ref' => 'Belum tersedia',
    ],
];

$employers = [
    [
        'id' => 'EMP-2026-002145', 'type' => 'Employer', 'name' => 'PT Cahaya Karier Digital',
        'score' => 76, 'level' => 'High', 'signal_code' => 'EWS-ID-03', 'signal' => 'Kontak digunakan lintas entitas', 'category' => 'Identity', 'count' => 4,
        'region' => 'Jakarta Selatan', 'verification' => 'Terverifikasi', 'active_vacancies' => 12, 'high_urgent_vacancies' => 3, 'source' => 'Native', 'scan' => '02 Sep 2026 09:28',
        'rule_version' => 'v1.2.0', 'evidence' => 'Nomor kontak terdeteksi pada tiga employer_id berbeda.', 'current_data' => 'Nomor kontak tetap digunakan pada profil aktif.',
        'detected_at' => '02 Sep 2026 09:28', 'history' => '76 High (02 Sep) ← 52 Medium (26 Agu)', 'linked' => '12 lowongan aktif · 3 High/Urgent', 'verification_ref' => 'VRF-EMP-260811-2145',
    ],
    [
        'id' => 'EMP-2026-002098', 'type' => 'Employer', 'name' => 'CV Mitra Karya Utama',
        'score' => 55, 'level' => 'Medium', 'signal_code' => 'EWS-BHV-01', 'signal' => 'Lonjakan posting lowongan', 'category' => 'Behavior', 'count' => 3,
        'region' => 'Bandung', 'verification' => 'Terverifikasi', 'active_vacancies' => 8, 'high_urgent_vacancies' => 1, 'source' => 'Native', 'scan' => '02 Sep 2026 08:48',
        'rule_version' => 'v1.2.0', 'evidence' => 'Delapan lowongan dipublikasikan dalam jendela 24 jam.', 'current_data' => 'Delapan lowongan masih aktif.',
        'detected_at' => '02 Sep 2026 08:48', 'history' => '55 Medium (02 Sep) ← 12 Low (31 Agu)', 'linked' => '8 lowongan aktif · 1 High/Urgent', 'verification_ref' => 'VRF-EMP-260715-2098',
    ],
    [
        'id' => 'EMP-2026-001977', 'type' => 'Employer', 'name' => 'PT Nusantara Daya',
        'score' => 32, 'level' => 'Low', 'signal_code' => 'EWS-ID-02', 'signal' => 'Kontak/domain tidak konsisten', 'category' => 'Identity', 'count' => 2,
        'region' => 'Makassar', 'verification' => 'Menunggu', 'active_vacancies' => 2, 'high_urgent_vacancies' => 0, 'source' => 'Integration', 'scan' => '01 Sep 2026 16:12',
        'rule_version' => 'v1.1.3', 'evidence' => 'Domain email berubah tiga kali dalam tujuh hari.', 'current_data' => 'Domain terbaru belum terverifikasi.',
        'detected_at' => '01 Sep 2026 16:12', 'history' => '32 Low (01 Sep) ← 0 Normal (25 Agu)', 'linked' => '2 lowongan aktif · 0 High/Urgent', 'verification_ref' => 'Belum tersedia',
    ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Early Warning System - Lowongan &amp; Pemberi Kerja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php kh_proto_render_styles(); ?>
    <style>
        body.kh-proto-page { background: #f4f7fb; color: #253b53; }
        .ews-shell { background: #fff; border: 1px solid #dce6f1; border-radius: 14px; padding: 22px; }
        .ews-title { margin: 0; color: #1f3550; font-size: 26px; font-weight: 700; }
        .ews-subtitle { margin: 5px 0 0; color: #688097; font-size: 14px; }
        .ews-freshness { color: #71869b; font-size: 12px; }
        .ews-kpi { height: 100%; padding: 14px; border: 1px solid #e2eaf3; border-radius: 12px; background: #fff; }
        .ews-kpi-head { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        .ews-kpi-label { color: #70869c; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .025em; }
        .ews-kpi-icon { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 9px; font-size: 16px; }
        .ews-kpi-value { margin-top: 8px; color: #1e3853; font-size: 27px; font-weight: 700; line-height: 1; }
        .ews-kpi-meta { margin-top: 7px; color: #8294a6; font-size: 11px; }
        .ews-kpi-icon.blue { color: #286aa9; background: #eaf4ff; }
        .ews-kpi-icon.indigo { color: #515fc2; background: #eef0ff; }
        .ews-kpi-icon.cyan { color: #197494; background: #e7f7fc; }
        .ews-kpi-icon.amber { color: #93661c; background: #fff4dc; }
        .ews-kpi-icon.red { color: #a42e37; background: #ffe9eb; }
        .ews-kpi-icon.green { color: #28734f; background: #e7f7ef; }
        .ews-panel { height: 100%; padding: 18px; border: 1px solid #e2eaf3; border-radius: 12px; background: #fff; }
        .ews-panel-title { margin: 0; color: #29445f; font-size: 16px; font-weight: 700; }
        .ews-panel-subtitle { margin: 4px 0 0; color: #8294a6; font-size: 11px; }
        .ews-filter { background: #f7fafc; border: 1px solid #e2eaf3; border-radius: 12px; padding: 14px; }
        .ews-filter .form-label { color: #5d7287; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .ews-filter .form-select, .ews-filter .form-control { color: #405b75; font-size: 12px; }
        .ews-risk-row, .ews-signal-row { display: grid; align-items: center; gap: 10px; margin-top: 13px; }
        .ews-risk-row { grid-template-columns: 62px 1fr 36px; }
        .ews-signal-row { grid-template-columns: minmax(180px, 1.4fr) minmax(100px, 1fr) 28px; }
        .ews-row-label { color: #4b6279; font-size: 12px; }
        .ews-row-label small { display: block; color: #8a9bac; }
        .ews-row-value { color: #2c455e; font-size: 12px; font-weight: 700; text-align: right; }
        .ews-track { height: 8px; overflow: hidden; border-radius: 999px; background: #edf2f7; }
        .ews-fill { height: 100%; min-width: 4px; border-radius: inherit; background: #4387c4; }
        .ews-fill.normal { background: #75a68a; }
        .ews-fill.low { background: #5d94c4; }
        .ews-fill.medium { background: #dfad48; }
        .ews-fill.high { background: #e2793f; }
        .ews-fill.urgent { background: #c8434c; }
        .ews-trend { height: 210px; display: flex; align-items: end; gap: 12px; padding-top: 24px; }
        .ews-trend-group { flex: 1; min-width: 34px; text-align: center; }
        .ews-trend-bars { height: 160px; display: flex; align-items: end; justify-content: center; gap: 4px; border-bottom: 1px solid #dfe7f0; }
        .ews-trend-bar { width: 14px; min-height: 4px; border-radius: 4px 4px 0 0; background: #5d94c4; }
        .ews-trend-bar.vacancy { background: #14a098; }
        .ews-trend-label { margin-top: 7px; color: #7d90a4; font-size: 10px; white-space: nowrap; }
        .ews-legend { display: flex; flex-wrap: wrap; gap: 14px; color: #6e8194; font-size: 11px; }
        .ews-legend-dot { width: 8px; height: 8px; display: inline-block; margin-right: 5px; border-radius: 999px; background: #5d94c4; }
        .ews-legend-dot.vacancy { background: #14a098; }
        .ews-region-meta { display: flex; gap: 8px; color: #7d90a4; font-size: 10px; }
        .ews-tabs { display: flex; flex-wrap: wrap; gap: 4px 25px; border-bottom: 1px solid #e7edf5; }
        .ews-tab { border: 0; background: transparent; padding: 9px 2px 11px; color: #74889c; font-size: 14px; font-weight: 600; border-bottom: 2px solid transparent; margin-bottom: -1px; }
        .ews-tab.active { color: #087e79; border-bottom-color: #087e79; }
        .ews-table thead th { background: #f5f9fd; color: #324a63; font-size: 11px; font-weight: 700; white-space: nowrap; }
        .ews-table td { color: #3c566f; font-size: 12px; vertical-align: middle; }
        .ews-chip { display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; white-space: nowrap; }
        .ews-chip.urgent { color: #a5212b; background: #ffe5e7; }
        .ews-chip.high { color: #a05618; background: #fff0dc; }
        .ews-chip.medium { color: #876118; background: #fff5d8; }
        .ews-chip.low { color: #25659a; background: #e8f3fd; }
        .ews-chip.normal { color: #28734f; background: #e7f7ef; }
        .ews-note { padding: 11px 14px; border-left: 4px solid #2d8d88; border-radius: 8px; background: #edf9f8; color: #476b70; font-size: 12px; }
        .ews-empty { padding: 24px; color: #75879a; text-align: center; }
        .ews-modal-score { display: flex; align-items: center; gap: 12px; padding: 14px; border: 1px solid #e2eaf3; border-radius: 10px; background: #f8fbfd; }
        .ews-modal-score strong { color: #1e3853; font-size: 30px; line-height: 1; }
        .ews-detail-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .ews-detail-card { padding: 13px; border: 1px solid #e2eaf3; border-radius: 9px; }
        .ews-detail-card h3 { margin: 0 0 7px; color: #62778c; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .ews-detail-card p { margin: 0; color: #344f68; font-size: 12px; }
        @media (max-width: 767px) {
            .ews-shell { padding: 15px; }
            .ews-title { font-size: 22px; }
            .ews-signal-row { grid-template-columns: minmax(130px, 1.4fr) minmax(70px, 1fr) 24px; }
            .ews-detail-grid { grid-template-columns: 1fr; }
            .ews-trend { gap: 5px; }
        }
    </style>
</head>
<body class="kh-proto-page">
<?php include 'navbar.php'; ?>

<div class="kh-content-wrap">
    <div class="container-fluid px-3 px-lg-4 py-4">
        <div class="ews-shell">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <h1 class="ews-title">Early Warning System - Lowongan &amp; Pemberi Kerja</h1>
                        <span class="badge text-bg-light border">Prototype</span>
                    </div>
                    <p class="ews-subtitle">Deteksi dini berdasarkan data internal Karirhub sebelum adanya pelaporan pengguna.</p>
                </div>
                <div class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-primary"><i class="bi bi-download me-1"></i>Export</button>
                    <div class="ews-freshness mt-2">Scan terakhir: 02 Sep 2026 09:42 · Dashboard: 09:45</div>
                </div>
            </div>

            <div class="ews-note mb-3">
                <i class="bi bi-info-circle me-1"></i>
                Risk score merupakan indikator prioritas, bukan keputusan hoaks. EWS tidak melakukan blokir, suspend, penolakan, atau unpublish otomatis.
            </div>

            <form class="ews-filter mb-3" id="ewsFilterForm">
                <div class="row g-2 align-items-end">
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label">Periode</label>
                        <select class="form-select form-select-sm" id="periodFilter"><option>24 jam</option><option selected>7 hari</option><option>30 hari</option></select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label">Wilayah</label>
                        <select class="form-select form-select-sm" id="regionFilter"><option value="Semua" selected>Semua Wilayah</option><option>Jakarta Selatan</option><option>Bandung</option><option>Surabaya</option><option>Makassar</option></select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label">Jenis Entitas</label>
                        <select class="form-select form-select-sm" id="entityFilter"><option value="Semua" selected>Semua</option><option value="Vacancy">Lowongan</option><option value="Employer">Pemberi Kerja</option></select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label">Risk Level</label>
                        <select class="form-select form-select-sm" id="riskFilter"><option value="Semua" selected>Semua</option><option>Low</option><option>Medium</option><option>High</option><option>Urgent</option></select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label">Rule Category</label>
                        <select class="form-select form-select-sm" id="categoryFilter"><option value="Semua" selected>Semua</option><option>Content</option><option>Identity</option><option>Behavior</option></select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label">Source</label>
                        <select class="form-select form-select-sm" id="sourceFilter"><option value="Semua" selected>Semua</option><option>Native</option><option>Integration</option></select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label">Verification Status</label>
                        <select class="form-select form-select-sm" id="verificationFilter"><option value="Semua" selected>Semua</option><option>Terverifikasi</option><option>Menunggu</option></select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label">Publication Status</label>
                        <select class="form-select form-select-sm" id="publicationFilter"><option value="Semua" selected>Semua</option><option>Tayang</option><option>Draft</option></select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <button class="btn btn-sm btn-primary w-100" type="submit"><i class="bi bi-funnel me-1"></i>Terapkan</button>
                    </div>
                    <div class="col-6 col-md-3 col-xl-2">
                        <button class="btn btn-sm btn-outline-secondary w-100" id="resetFilters" type="button"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
                    </div>
                </div>
            </form>

            <div class="row g-3 mb-3">
                <?php foreach ($summary as $item): ?>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div class="ews-kpi">
                            <div class="ews-kpi-head">
                                <span class="ews-kpi-label"><?php echo ews_h($item['label']); ?></span>
                                <span class="ews-kpi-icon <?php echo ews_h($item['tone']); ?>"><i class="bi <?php echo ews_h($item['icon']); ?>"></i></span>
                            </div>
                            <div class="ews-kpi-value"><?php echo ews_h((string)$item['value']); ?></div>
                            <div class="ews-kpi-meta"><?php echo ews_h($item['meta']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-xl-7">
                    <section class="ews-panel">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <div>
                                <h2 class="ews-panel-title">Risk Trend</h2>
                                <p class="ews-panel-subtitle">Entity berisiko selama 7 hari terakhir</p>
                            </div>
                            <div class="ews-legend">
                                <span><i class="ews-legend-dot"></i>Pemberi Kerja</span>
                                <span><i class="ews-legend-dot vacancy"></i>Lowongan</span>
                            </div>
                        </div>
                        <div class="ews-trend" aria-label="Grafik tren risiko">
                            <?php foreach ($riskTrend as $point): ?>
                                <div class="ews-trend-group">
                                    <div class="ews-trend-bars">
                                        <div class="ews-trend-bar" title="<?php echo (int)$point['employer']; ?> employer" style="height: <?php echo (int)$point['employer'] * 4; ?>px"></div>
                                        <div class="ews-trend-bar vacancy" title="<?php echo (int)$point['vacancy']; ?> lowongan" style="height: <?php echo (int)$point['vacancy'] * 4; ?>px"></div>
                                    </div>
                                    <div class="ews-trend-label"><?php echo ews_h($point['label']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>
                <div class="col-xl-5">
                    <section class="ews-panel">
                        <h2 class="ews-panel-title">Distribusi Risk Level</h2>
                        <p class="ews-panel-subtitle">Populasi entity berdasarkan level aktif</p>
                        <?php foreach ($riskDistribution as $risk): ?>
                            <div class="ews-risk-row">
                                <span class="ews-row-label"><?php echo ews_h($risk['label']); ?></span>
                                <div class="ews-track"><div class="ews-fill <?php echo ews_h($risk['tone']); ?>" style="width: <?php echo (int)$risk['percent']; ?>%"></div></div>
                                <span class="ews-row-value"><?php echo (int)$risk['value']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </section>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-lg-7">
                    <section class="ews-panel">
                        <h2 class="ews-panel-title">Primary Signal &amp; Rule Category</h2>
                        <p class="ews-panel-subtitle">Sinyal aktif yang paling sering terdeteksi</p>
                        <?php foreach ($topSignals as $signal): ?>
                            <div class="ews-signal-row">
                                <span class="ews-row-label"><?php echo ews_h($signal['label']); ?><small><?php echo ews_h($signal['code']); ?> · <?php echo ews_h($signal['category']); ?></small></span>
                                <div class="ews-track"><div class="ews-fill" style="width: <?php echo (int)$signal['percent']; ?>%"></div></div>
                                <span class="ews-row-value"><?php echo (int)$signal['value']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </section>
                </div>
                <div class="col-lg-5">
                    <section class="ews-panel">
                        <h2 class="ews-panel-title">Risk by Region</h2>
                        <p class="ews-panel-subtitle">Sebaran provinsi dan kabupaten/kota</p>
                        <?php foreach ($regions as $region): ?>
                            <div class="ews-signal-row">
                                <span class="ews-row-label"><?php echo ews_h($region['label']); ?><small class="ews-region-meta"><span>Urgent <?php echo (int)$region['urgent']; ?></span><span>High <?php echo (int)$region['high']; ?></span></small></span>
                                <div class="ews-track"><div class="ews-fill high" style="width: <?php echo (int)$region['percent']; ?>%"></div></div>
                                <span class="ews-row-value"><?php echo (int)$region['total']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </section>
                </div>
            </div>

            <section class="ews-panel">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <div class="ews-tabs" role="tablist" aria-label="Entitas berisiko">
                        <button type="button" class="ews-tab active" data-entity-tab="Vacancy">Top 10 Lowongan Berisiko</button>
                        <button type="button" class="ews-tab" data-entity-tab="Employer">Top 10 Pemberi Kerja Berisiko</button>
                    </div>
                    <span class="ews-freshness">Detail membuka EWS Risk Profile read-only</span>
                </div>
                <div class="table-responsive js-entity-table" data-table-type="Vacancy">
                    <table class="table table-bordered table-hover ews-table mb-0">
                        <thead><tr><th>Vacancy ID / Judul</th><th>Pemberi Kerja</th><th>Risk</th><th>Primary Signal</th><th>Signals</th><th>Wilayah</th><th>Verification</th><th>Publication</th><th>Source</th><th>Last Scan</th><th>Detail</th></tr></thead>
                        <tbody>
                        <?php foreach ($vacancies as $entity): ?>
                            <tr class="js-entity-row" data-type="Vacancy" data-risk="<?php echo ews_h($entity['level']); ?>" data-region="<?php echo ews_h($entity['region']); ?>" data-category="<?php echo ews_h($entity['category']); ?>" data-source="<?php echo ews_h($entity['source']); ?>" data-verification="<?php echo ews_h($entity['verification']); ?>" data-publication="<?php echo ews_h($entity['publication']); ?>">
                                <td><strong><?php echo ews_h($entity['name']); ?></strong><div class="text-muted"><?php echo ews_h($entity['id']); ?></div></td>
                                <td><?php echo ews_h($entity['employer']); ?></td>
                                <td><span class="ews-chip <?php echo strtolower(ews_h($entity['level'])); ?>"><?php echo (int)$entity['score']; ?> · <?php echo ews_h($entity['level']); ?></span></td>
                                <td><?php echo ews_h($entity['signal']); ?><div class="text-muted"><?php echo ews_h($entity['signal_code']); ?></div></td>
                                <td class="text-center"><?php echo (int)$entity['count']; ?></td>
                                <td><?php echo ews_h($entity['region']); ?></td>
                                <td><?php echo ews_h($entity['verification']); ?></td>
                                <td><?php echo ews_h($entity['publication']); ?></td>
                                <td><?php echo ews_h($entity['source']); ?></td>
                                <td class="text-nowrap"><?php echo ews_h($entity['scan']); ?></td>
                                <td><button type="button" class="btn btn-sm btn-outline-primary js-risk-detail" data-profile="<?php echo ews_h(json_encode($entity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>" data-bs-toggle="modal" data-bs-target="#riskProfileModal">Detail</button></td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="js-empty-row d-none"><td colspan="11" class="ews-empty">Tidak ada lowongan yang sesuai filter.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="table-responsive js-entity-table d-none" data-table-type="Employer">
                    <table class="table table-bordered table-hover ews-table mb-0">
                        <thead><tr><th>Employer ID / Nama</th><th>Risk</th><th>Primary Signal</th><th>Signals</th><th>Wilayah</th><th>Verification</th><th>Active Vacancy</th><th>High/Urgent Vacancy</th><th>Last Scan</th><th>Detail</th></tr></thead>
                        <tbody>
                        <?php foreach ($employers as $entity): ?>
                            <tr class="js-entity-row" data-type="Employer" data-risk="<?php echo ews_h($entity['level']); ?>" data-region="<?php echo ews_h($entity['region']); ?>" data-category="<?php echo ews_h($entity['category']); ?>" data-source="<?php echo ews_h($entity['source']); ?>" data-verification="<?php echo ews_h($entity['verification']); ?>" data-publication="">
                                <td><strong><?php echo ews_h($entity['name']); ?></strong><div class="text-muted"><?php echo ews_h($entity['id']); ?></div><div class="text-muted"><?php echo ews_h($entity['source']); ?></div></td>
                                <td><span class="ews-chip <?php echo strtolower(ews_h($entity['level'])); ?>"><?php echo (int)$entity['score']; ?> · <?php echo ews_h($entity['level']); ?></span></td>
                                <td><?php echo ews_h($entity['signal']); ?><div class="text-muted"><?php echo ews_h($entity['signal_code']); ?></div></td>
                                <td class="text-center"><?php echo (int)$entity['count']; ?></td>
                                <td><?php echo ews_h($entity['region']); ?></td>
                                <td><?php echo ews_h($entity['verification']); ?></td>
                                <td class="text-center"><?php echo (int)$entity['active_vacancies']; ?></td>
                                <td class="text-center"><?php echo (int)$entity['high_urgent_vacancies']; ?></td>
                                <td class="text-nowrap"><?php echo ews_h($entity['scan']); ?></td>
                                <td><button type="button" class="btn btn-sm btn-outline-primary js-risk-detail" data-profile="<?php echo ews_h(json_encode($entity, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>" data-bs-toggle="modal" data-bs-target="#riskProfileModal">Detail</button></td>
                            </tr>
                        <?php endforeach; ?>
                            <tr class="js-empty-row d-none"><td colspan="10" class="ews-empty">Tidak ada pemberi kerja yang sesuai filter.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>

<div class="modal fade" id="riskProfileModal" tabindex="-1" aria-labelledby="riskProfileTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5" id="riskProfileTitle">EWS Risk Profile</h2>
                    <div class="ews-freshness mt-1" id="modalIdentity"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="ews-note mb-3"><i class="bi bi-eye me-1"></i>Profil ini hanya untuk informasi dan analisis. Tidak ada perubahan status objek dari Dashboard EWS.</div>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="ews-modal-score">
                            <strong id="modalScore">-</strong>
                            <div><span class="ews-chip" id="modalLevel">-</span><div class="ews-freshness mt-2">Risk score / level aktif</div></div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="ews-detail-grid">
                            <div class="ews-detail-card"><h3>Wilayah &amp; Source</h3><p id="modalScope">-</p></div>
                            <div class="ews-detail-card"><h3>Status Objek</h3><p id="modalStatus">-</p></div>
                            <div class="ews-detail-card"><h3>Last Scan</h3><p id="modalScan">-</p></div>
                            <div class="ews-detail-card"><h3>Rule Version</h3><p id="modalVersion">-</p></div>
                        </div>
                    </div>
                </div>
                <h3 class="ews-panel-title mb-2">Score Breakdown</h3>
                <div class="table-responsive mb-3">
                    <table class="table table-bordered ews-table mb-0">
                        <thead><tr><th>Rule ID</th><th>Label</th><th>Severity</th><th>Weight</th><th>Contribution</th><th>Detected At</th><th>Signal Status</th><th>Rule Version</th></tr></thead>
                        <tbody><tr><td id="modalRule">-</td><td id="modalSignal">-</td><td id="modalSeverity">-</td><td id="modalWeight">-</td><td id="modalContribution">-</td><td id="modalDetected">-</td><td>ACTIVE</td><td id="modalRuleVersion">-</td></tr></tbody>
                    </table>
                </div>
                <div class="ews-detail-grid">
                    <div class="ews-detail-card"><h3>Evidence Snapshot</h3><p id="modalEvidence">-</p></div>
                    <div class="ews-detail-card"><h3>Data Terkini</h3><p id="modalCurrent">-</p></div>
                    <div class="ews-detail-card"><h3>Histori Scan &amp; Risk</h3><p id="modalHistory">-</p></div>
                    <div class="ews-detail-card"><h3>Linked Entity</h3><p id="modalLinked">-</p></div>
                    <div class="ews-detail-card"><h3>Linked Verification Reference</h3><p id="modalVerificationRef">-</p></div>
                    <div class="ews-detail-card"><h3>Data Freshness</h3><p>Dashboard diperbarui 02 Sep 2026 09:45</p></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const form = document.getElementById('ewsFilterForm');
    const entityFilter = document.getElementById('entityFilter');
    const riskFilter = document.getElementById('riskFilter');
    const regionFilter = document.getElementById('regionFilter');
    const categoryFilter = document.getElementById('categoryFilter');
    const sourceFilter = document.getElementById('sourceFilter');
    const verificationFilter = document.getElementById('verificationFilter');
    const publicationFilter = document.getElementById('publicationFilter');
    const resetFilters = document.getElementById('resetFilters');
    const rows = document.querySelectorAll('.js-entity-row');
    const tabs = document.querySelectorAll('[data-entity-tab]');
    const tables = document.querySelectorAll('.js-entity-table');
    let activeType = 'Vacancy';

    function applyFilters() {
        if (entityFilter.value !== 'Semua') {
            activeType = entityFilter.value;
        }

        tabs.forEach(function (tab) {
            tab.classList.toggle('active', tab.dataset.entityTab === activeType);
        });
        tables.forEach(function (table) {
            table.classList.toggle('d-none', table.dataset.tableType !== activeType);
        });

        const visibleByType = { Vacancy: 0, Employer: 0 };
        rows.forEach(function (row) {
            const riskMatch = riskFilter.value === 'Semua' || row.dataset.risk === riskFilter.value;
            const regionMatch = regionFilter.value === 'Semua' || row.dataset.region === regionFilter.value;
            const categoryMatch = categoryFilter.value === 'Semua' || row.dataset.category === categoryFilter.value;
            const sourceMatch = sourceFilter.value === 'Semua' || row.dataset.source === sourceFilter.value;
            const verificationMatch = verificationFilter.value === 'Semua' || row.dataset.verification === verificationFilter.value;
            const publicationMatch = publicationFilter.value === 'Semua' || row.dataset.publication === publicationFilter.value;
            const show = riskMatch && regionMatch && categoryMatch && sourceMatch && verificationMatch && publicationMatch;
            row.classList.toggle('d-none', !show);
            if (show) {
                visibleByType[row.dataset.type] += 1;
            }
        });

        tables.forEach(function (table) {
            const emptyRow = table.querySelector('.js-empty-row');
            if (emptyRow) {
                emptyRow.classList.toggle('d-none', visibleByType[table.dataset.tableType] > 0);
            }
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        applyFilters();
    });

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            activeType = tab.dataset.entityTab || 'Vacancy';
            entityFilter.value = activeType;
            applyFilters();
        });
    });

    resetFilters.addEventListener('click', function () {
        form.reset();
        activeType = 'Vacancy';
        applyFilters();
    });

    document.querySelectorAll('.js-risk-detail').forEach(function (button) {
        button.addEventListener('click', function () {
            const profile = JSON.parse(button.dataset.profile || '{}');
            const publication = profile.publication ? ' · ' + profile.publication : '';
            const levelClass = String(profile.level || '').toLowerCase();

            document.getElementById('modalIdentity').textContent = (profile.id || '-') + ' · ' + (profile.name || '-');
            document.getElementById('modalScore').textContent = profile.score ?? '-';
            document.getElementById('modalLevel').textContent = profile.level || '-';
            document.getElementById('modalLevel').className = 'ews-chip ' + levelClass;
            document.getElementById('modalScope').textContent = (profile.region || '-') + ' · ' + (profile.source || '-');
            document.getElementById('modalStatus').textContent = (profile.verification || '-') + publication;
            document.getElementById('modalScan').textContent = profile.scan || '-';
            document.getElementById('modalVersion').textContent = profile.rule_version || '-';
            document.getElementById('modalRule').textContent = profile.signal_code || '-';
            document.getElementById('modalSignal').textContent = profile.signal || '-';
            document.getElementById('modalSeverity').textContent = profile.level || '-';
            document.getElementById('modalWeight').textContent = profile.score ?? '-';
            document.getElementById('modalContribution').textContent = profile.score ?? '-';
            document.getElementById('modalDetected').textContent = profile.detected_at || '-';
            document.getElementById('modalRuleVersion').textContent = profile.rule_version || '-';
            document.getElementById('modalEvidence').textContent = profile.evidence || 'Belum tersedia';
            document.getElementById('modalCurrent').textContent = profile.current_data || 'Belum tersedia';
            document.getElementById('modalHistory').textContent = profile.history || 'Belum tersedia';
            document.getElementById('modalLinked').textContent = profile.linked || 'Belum tersedia';
            document.getElementById('modalVerificationRef').textContent = profile.verification_ref || 'Belum tersedia';
        });
    });

    applyFilters();
})();
</script>
<?php kh_proto_render_sidebar_script(); ?>
</body>
</html>
