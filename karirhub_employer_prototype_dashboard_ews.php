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
    ['label' => 'Sinyal Baru 24 Jam', 'value' => 42, 'icon' => 'bi-broadcast', 'tone' => 'blue'],
    ['label' => 'Pemberi Kerja Berisiko', 'value' => 18, 'icon' => 'bi-buildings', 'tone' => 'indigo'],
    ['label' => 'Lowongan Berisiko', 'value' => 31, 'icon' => 'bi-briefcase', 'tone' => 'cyan'],
    ['label' => 'High', 'value' => 14, 'icon' => 'bi-exclamation-diamond', 'tone' => 'amber'],
    ['label' => 'Urgent', 'value' => 6, 'icon' => 'bi-shield-exclamation', 'tone' => 'red'],
    ['label' => 'Dalam Pemeriksaan', 'value' => 11, 'icon' => 'bi-search', 'tone' => 'purple'],
    ['label' => 'Overdue', 'value' => 4, 'icon' => 'bi-clock-history', 'tone' => 'rose'],
];

$riskDistribution = [
    ['label' => 'Normal', 'value' => 124, 'percent' => 100, 'tone' => 'normal'],
    ['label' => 'Low', 'value' => 37, 'percent' => 30, 'tone' => 'low'],
    ['label' => 'Medium', 'value' => 21, 'percent' => 17, 'tone' => 'medium'],
    ['label' => 'High', 'value' => 14, 'percent' => 11, 'tone' => 'high'],
    ['label' => 'Urgent', 'value' => 6, 'percent' => 5, 'tone' => 'urgent'],
];

$topSignals = [
    ['code' => 'EWS-CNT-01', 'label' => 'Indikasi permintaan biaya/pembayaran', 'value' => 16, 'percent' => 100],
    ['code' => 'EWS-CNT-03', 'label' => 'Tautan/kanal eksternal berisiko', 'value' => 12, 'percent' => 75],
    ['code' => 'EWS-CNT-04', 'label' => 'Identitas konten tidak sesuai', 'value' => 9, 'percent' => 56],
    ['code' => 'EWS-ID-03', 'label' => 'Kontak digunakan lintas entitas', 'value' => 7, 'percent' => 44],
    ['code' => 'EWS-BHV-01', 'label' => 'Lonjakan posting lowongan', 'value' => 6, 'percent' => 38],
];

$entities = [
    [
        'id' => 'VAC-2026-008431',
        'type' => 'Lowongan',
        'name' => 'Staff Administrasi',
        'employer' => 'PT Cahaya Karier Digital',
        'score' => 88,
        'level' => 'Urgent',
        'signal' => 'EWS-CNT-01 · Permintaan pembayaran',
        'count' => 3,
        'region' => 'Jakarta Selatan',
        'verification' => 'Terverifikasi',
        'publication' => 'Tayang',
        'source' => 'Native',
        'alert' => 'Pending',
        'assigned' => '-',
        'scan' => '02 Sep 2026 09:31',
    ],
    [
        'id' => 'VAC-2026-008396',
        'type' => 'Lowongan',
        'name' => 'Customer Service',
        'employer' => 'CV Mitra Karya Utama',
        'score' => 67,
        'level' => 'High',
        'signal' => 'EWS-CNT-03 · Tautan berisiko',
        'count' => 2,
        'region' => 'Bandung',
        'verification' => 'Terverifikasi',
        'publication' => 'Tayang',
        'source' => 'Integration',
        'alert' => 'Sedang Ditinjau',
        'assigned' => 'admin.kab.bdg',
        'scan' => '02 Sep 2026 08:54',
    ],
    [
        'id' => 'VAC-2026-008362',
        'type' => 'Lowongan',
        'name' => 'Data Entry',
        'employer' => 'PT Solusi Talenta',
        'score' => 48,
        'level' => 'Medium',
        'signal' => 'EWS-CNT-05 · Konten tidak konsisten',
        'count' => 2,
        'region' => 'Surabaya',
        'verification' => 'Menunggu',
        'publication' => 'Draft',
        'source' => 'Native',
        'alert' => 'Pending',
        'assigned' => '-',
        'scan' => '02 Sep 2026 08:20',
    ],
    [
        'id' => 'EMP-2026-002145',
        'type' => 'Pemberi Kerja',
        'name' => 'PT Cahaya Karier Digital',
        'employer' => '-',
        'score' => 76,
        'level' => 'High',
        'signal' => 'EWS-ID-03 · Kontak lintas entitas',
        'count' => 4,
        'region' => 'Jakarta Selatan',
        'verification' => 'Terverifikasi',
        'publication' => '12 lowongan aktif',
        'source' => 'Native',
        'alert' => 'Sedang Ditinjau',
        'assigned' => 'admin.pusat.ews',
        'scan' => '02 Sep 2026 09:28',
    ],
    [
        'id' => 'EMP-2026-002098',
        'type' => 'Pemberi Kerja',
        'name' => 'CV Mitra Karya Utama',
        'employer' => '-',
        'score' => 55,
        'level' => 'Medium',
        'signal' => 'EWS-BHV-01 · Lonjakan posting',
        'count' => 3,
        'region' => 'Bandung',
        'verification' => 'Terverifikasi',
        'publication' => '8 lowongan aktif',
        'source' => 'Native',
        'alert' => 'Pending',
        'assigned' => '-',
        'scan' => '02 Sep 2026 08:48',
    ],
    [
        'id' => 'EMP-2026-001977',
        'type' => 'Pemberi Kerja',
        'name' => 'PT Nusantara Daya',
        'employer' => '-',
        'score' => 32,
        'level' => 'Low',
        'signal' => 'EWS-ID-02 · Kontak/domain berubah',
        'count' => 2,
        'region' => 'Makassar',
        'verification' => 'Menunggu',
        'publication' => '2 lowongan aktif',
        'source' => 'Integration',
        'alert' => 'Watchlist',
        'assigned' => '-',
        'scan' => '01 Sep 2026 16:12',
    ],
];

$queue = [
    ['id' => 'EWS-ALT-260902-041', 'entity' => 'Staff Administrasi', 'type' => 'Lowongan', 'risk' => 'Urgent', 'score' => 88, 'signal' => 'Permintaan pembayaran', 'region' => 'Jakarta Selatan', 'entered' => '02 Sep 09:31', 'sla' => 'On Time', 'status' => 'Pending', 'assigned' => '-'],
    ['id' => 'EWS-ALT-260902-039', 'entity' => 'PT Cahaya Karier Digital', 'type' => 'Pemberi Kerja', 'risk' => 'High', 'score' => 76, 'signal' => 'Kontak lintas entitas', 'region' => 'Jakarta Selatan', 'entered' => '02 Sep 09:28', 'sla' => 'Approaching', 'status' => 'Sedang Ditinjau', 'assigned' => 'admin.pusat.ews'],
    ['id' => 'EWS-ALT-260902-033', 'entity' => 'Customer Service', 'type' => 'Lowongan', 'risk' => 'High', 'score' => 67, 'signal' => 'Tautan berisiko', 'region' => 'Bandung', 'entered' => '02 Sep 08:54', 'sla' => 'Overdue', 'status' => 'Sedang Ditinjau', 'assigned' => 'admin.kab.bdg'],
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
        .ews-kpi-icon.blue { color: #286aa9; background: #eaf4ff; }
        .ews-kpi-icon.indigo { color: #515fc2; background: #eef0ff; }
        .ews-kpi-icon.cyan { color: #197494; background: #e7f7fc; }
        .ews-kpi-icon.amber { color: #93661c; background: #fff4dc; }
        .ews-kpi-icon.red { color: #a42e37; background: #ffe9eb; }
        .ews-kpi-icon.purple { color: #6f42a8; background: #f3eafd; }
        .ews-kpi-icon.rose { color: #a72f5a; background: #fdeaf1; }
        .ews-panel { height: 100%; padding: 18px; border: 1px solid #e2eaf3; border-radius: 12px; background: #fff; }
        .ews-panel-title { margin: 0; color: #29445f; font-size: 16px; font-weight: 700; }
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
        .ews-tabs { display: flex; flex-wrap: wrap; gap: 4px 25px; border-bottom: 1px solid #e7edf5; }
        .ews-tab { border: 0; background: transparent; padding: 9px 2px 11px; color: #74889c; font-size: 14px; font-weight: 600; border-bottom: 2px solid transparent; margin-bottom: -1px; }
        .ews-tab.active { color: #087e79; border-bottom-color: #087e79; }
        .ews-table thead th { background: #f5f9fd; color: #324a63; font-size: 11px; font-weight: 700; white-space: nowrap; }
        .ews-table td { color: #3c566f; font-size: 12px; vertical-align: middle; }
        .ews-chip { display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; white-space: nowrap; }
        .ews-chip.urgent, .ews-chip.overdue { color: #a5212b; background: #ffe5e7; }
        .ews-chip.high { color: #a05618; background: #fff0dc; }
        .ews-chip.medium, .ews-chip.approaching { color: #876118; background: #fff5d8; }
        .ews-chip.low, .ews-chip.ontime { color: #25659a; background: #e8f3fd; }
        .ews-chip.pending { color: #4f5eb0; background: #eef0ff; }
        .ews-chip.sedang-ditinjau { color: #156f75; background: #e4f7f5; }
        .ews-chip.watchlist { color: #596b7c; background: #edf1f5; }
        .ews-note { padding: 11px 14px; border-left: 4px solid #2d8d88; border-radius: 8px; background: #edf9f8; color: #476b70; font-size: 12px; }
        .ews-empty { padding: 24px; color: #75879a; text-align: center; }
        @media (max-width: 767px) {
            .ews-shell { padding: 15px; }
            .ews-title { font-size: 22px; }
            .ews-signal-row { grid-template-columns: minmax(130px, 1.4fr) minmax(70px, 1fr) 24px; }
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
                    <button type="button" class="btn btn-sm btn-primary"><i class="bi bi-arrow-repeat me-1"></i>Rescan</button>
                    <div class="ews-freshness mt-2">Scan terakhir: 02 Sep 2026 09:42 · Dashboard: 09:45</div>
                </div>
            </div>

            <div class="ews-note mb-3">
                <i class="bi bi-info-circle me-1"></i>
                Risk score merupakan indikator prioritas, bukan keputusan hoaks. EWS tidak melakukan blokir, suspend, penolakan, atau unpublish otomatis.
            </div>

            <form class="ews-filter mb-3" id="ewsFilterForm">
                <div class="row g-2 align-items-end">
                    <div class="col-6 col-md-3 col-xl">
                        <label class="form-label">Periode</label>
                        <select class="form-select form-select-sm"><option>24 jam</option><option selected>7 hari</option><option>30 hari</option></select>
                    </div>
                    <div class="col-6 col-md-3 col-xl">
                        <label class="form-label">Wilayah</label>
                        <select class="form-select form-select-sm"><option selected>Semua Wilayah</option><option>DKI Jakarta</option><option>Jawa Barat</option></select>
                    </div>
                    <div class="col-6 col-md-3 col-xl">
                        <label class="form-label">Jenis Entitas</label>
                        <select class="form-select form-select-sm" id="entityFilter"><option value="Semua" selected>Semua</option><option>Lowongan</option><option>Pemberi Kerja</option></select>
                    </div>
                    <div class="col-6 col-md-3 col-xl">
                        <label class="form-label">Risk Level</label>
                        <select class="form-select form-select-sm" id="riskFilter"><option value="Semua" selected>Semua</option><option>Low</option><option>Medium</option><option>High</option><option>Urgent</option></select>
                    </div>
                    <div class="col-6 col-md-3 col-xl">
                        <label class="form-label">Source</label>
                        <select class="form-select form-select-sm"><option selected>Semua</option><option>Native</option><option>Integration</option></select>
                    </div>
                    <div class="col-6 col-md-3 col-xl">
                        <button class="btn btn-sm btn-primary w-100" type="submit"><i class="bi bi-funnel me-1"></i>Terapkan</button>
                    </div>
                </div>
            </form>

            <div class="row g-3 mb-3">
                <?php foreach ($summary as $item): ?>
                    <div class="col-6 col-md-4 col-xl">
                        <div class="ews-kpi">
                            <div class="ews-kpi-head">
                                <span class="ews-kpi-label"><?php echo ews_h($item['label']); ?></span>
                                <span class="ews-kpi-icon <?php echo ews_h($item['tone']); ?>"><i class="bi <?php echo ews_h($item['icon']); ?>"></i></span>
                            </div>
                            <div class="ews-kpi-value"><?php echo (int)$item['value']; ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-lg-5">
                    <section class="ews-panel">
                        <h2 class="ews-panel-title">Distribusi Risk Level</h2>
                        <?php foreach ($riskDistribution as $risk): ?>
                            <div class="ews-risk-row">
                                <span class="ews-row-label"><?php echo ews_h($risk['label']); ?></span>
                                <div class="ews-track"><div class="ews-fill <?php echo ews_h($risk['tone']); ?>" style="width: <?php echo (int)$risk['percent']; ?>%"></div></div>
                                <span class="ews-row-value"><?php echo (int)$risk['value']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </section>
                </div>
                <div class="col-lg-7">
                    <section class="ews-panel">
                        <h2 class="ews-panel-title">Primary Signal Teratas</h2>
                        <?php foreach ($topSignals as $signal): ?>
                            <div class="ews-signal-row">
                                <span class="ews-row-label"><?php echo ews_h($signal['label']); ?><small><?php echo ews_h($signal['code']); ?></small></span>
                                <div class="ews-track"><div class="ews-fill" style="width: <?php echo (int)$signal['percent']; ?>%"></div></div>
                                <span class="ews-row-value"><?php echo (int)$signal['value']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </section>
                </div>
            </div>

            <section class="ews-panel mb-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <div class="ews-tabs" role="tablist" aria-label="Entitas berisiko">
                        <button type="button" class="ews-tab active" data-entity-tab="Semua">Semua Risiko</button>
                        <button type="button" class="ews-tab" data-entity-tab="Lowongan">Lowongan Berisiko</button>
                        <button type="button" class="ews-tab" data-entity-tab="Pemberi Kerja">Pemberi Kerja Berisiko</button>
                    </div>
                    <span class="ews-freshness">Klik Detail Risk untuk score breakdown dan evidence snapshot</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover ews-table mb-0" id="riskEntityTable">
                        <thead>
                            <tr>
                                <th>ID / Entitas</th><th>Jenis</th><th>Risk</th><th>Primary Signal</th><th>Signals</th>
                                <th>Wilayah</th><th>Status Objek</th><th>Source</th><th>Alert</th><th>Assigned To</th><th>Last Scan</th><th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($entities as $entity): ?>
                            <tr class="js-entity-row" data-type="<?php echo ews_h($entity['type']); ?>" data-risk="<?php echo ews_h($entity['level']); ?>">
                                <td><strong><?php echo ews_h($entity['name']); ?></strong><div class="text-muted"><?php echo ews_h($entity['id']); ?></div><?php if ($entity['employer'] !== '-'): ?><div class="text-muted"><?php echo ews_h($entity['employer']); ?></div><?php endif; ?></td>
                                <td><?php echo ews_h($entity['type']); ?></td>
                                <td><span class="ews-chip <?php echo strtolower(ews_h($entity['level'])); ?>"><?php echo (int)$entity['score']; ?> · <?php echo ews_h($entity['level']); ?></span></td>
                                <td><?php echo ews_h($entity['signal']); ?></td>
                                <td class="text-center"><?php echo (int)$entity['count']; ?></td>
                                <td><?php echo ews_h($entity['region']); ?></td>
                                <td><?php echo ews_h($entity['verification']); ?><div class="text-muted"><?php echo ews_h($entity['publication']); ?></div></td>
                                <td><?php echo ews_h($entity['source']); ?></td>
                                <td><span class="ews-chip <?php echo strtolower(str_replace(' ', '-', ews_h($entity['alert']))); ?>"><?php echo ews_h($entity['alert']); ?></span></td>
                                <td><?php echo ews_h($entity['assigned']); ?></td>
                                <td class="text-nowrap"><?php echo ews_h($entity['scan']); ?></td>
                                <td><button type="button" class="btn btn-sm btn-outline-primary text-nowrap">Detail Risk</button></td>
                            </tr>
                        <?php endforeach; ?>
                            <tr id="entityEmptyRow" class="d-none"><td colspan="12" class="ews-empty">Tidak ada entitas yang sesuai filter.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="ews-panel">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h2 class="ews-panel-title">EWS Alert Queue</h2>
                        <div class="ews-freshness mt-1">Urutan: Urgent → High → Medium → SLA → waktu masuk. Maksimal 5 kasus aktif per Admin.</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary"><i class="bi bi-person-check me-1"></i>Ambil Kasus</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover ews-table mb-0">
                        <thead><tr><th>Alert ID</th><th>Entitas</th><th>Risk</th><th>Primary Signal</th><th>Wilayah</th><th>Waktu Masuk</th><th>SLA</th><th>Status</th><th>Assigned To</th><th>Aksi</th></tr></thead>
                        <tbody>
                        <?php foreach ($queue as $case): ?>
                            <tr>
                                <td><?php echo ews_h($case['id']); ?></td>
                                <td><strong><?php echo ews_h($case['entity']); ?></strong><div class="text-muted"><?php echo ews_h($case['type']); ?></div></td>
                                <td><span class="ews-chip <?php echo strtolower(ews_h($case['risk'])); ?>"><?php echo (int)$case['score']; ?> · <?php echo ews_h($case['risk']); ?></span></td>
                                <td><?php echo ews_h($case['signal']); ?></td>
                                <td><?php echo ews_h($case['region']); ?></td>
                                <td class="text-nowrap"><?php echo ews_h($case['entered']); ?></td>
                                <td><span class="ews-chip <?php echo strtolower(str_replace(' ', '', ews_h($case['sla']))); ?>"><?php echo ews_h($case['sla']); ?></span></td>
                                <td><?php echo ews_h($case['status']); ?></td>
                                <td><?php echo ews_h($case['assigned']); ?></td>
                                <td><button type="button" class="btn btn-sm btn-outline-primary">Detail</button></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const form = document.getElementById('ewsFilterForm');
    const entityFilter = document.getElementById('entityFilter');
    const riskFilter = document.getElementById('riskFilter');
    const rows = document.querySelectorAll('.js-entity-row');
    const tabs = document.querySelectorAll('[data-entity-tab]');
    const emptyRow = document.getElementById('entityEmptyRow');
    let activeType = 'Semua';

    function applyFilters() {
        const selectedType = activeType !== 'Semua' ? activeType : entityFilter.value;
        const selectedRisk = riskFilter.value;
        let visible = 0;
        rows.forEach(function (row) {
            const typeMatch = selectedType === 'Semua' || row.dataset.type === selectedType;
            const riskMatch = selectedRisk === 'Semua' || row.dataset.risk === selectedRisk;
            const show = typeMatch && riskMatch;
            row.classList.toggle('d-none', !show);
            if (show) visible += 1;
        });
        emptyRow.classList.toggle('d-none', visible > 0);
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        activeType = 'Semua';
        tabs.forEach(function (tab) { tab.classList.toggle('active', tab.dataset.entityTab === 'Semua'); });
        applyFilters();
    });

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            activeType = tab.dataset.entityTab || 'Semua';
            tabs.forEach(function (item) { item.classList.remove('active'); });
            tab.classList.add('active');
            applyFilters();
        });
    });
})();
</script>
<?php kh_proto_render_sidebar_script(); ?>
</body>
</html>
