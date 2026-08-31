<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_ui.php';

if (!kh_proto_can_access('karirhub_employer_prototype_monitoring_laporan_view')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$summary = [
    [
        'label' => 'Total Laporan',
        'value' => 59,
        'icon' => 'bi-flag',
        'tone' => 'blue',
        'tooltip' => 'Jumlah seluruh laporan lowongan dan perusahaan pada periode yang dipilih.',
    ],
    [
        'label' => 'Pending Review',
        'value' => 32,
        'icon' => 'bi-hourglass-split',
        'tone' => 'indigo',
        'tooltip' => 'Laporan baru yang belum diambil admin. Semua laporan masuk ke status ini terlebih dahulu.',
    ],
    [
        'label' => 'Dalam Verifikasi',
        'value' => 18,
        'icon' => 'bi-search',
        'tone' => 'cyan',
        'tooltip' => 'Laporan yang sudah diambil (Ambil Kasus) dan sedang diperiksa admin.',
    ],
    [
        'label' => 'Approaching SLA',
        'value' => 7,
        'icon' => 'bi-clock-history',
        'tone' => 'amber',
        'tooltip' => 'Laporan yang mendekati batas waktu penanganan (SLA).',
    ],
    [
        'label' => 'Overdue',
        'value' => 5,
        'icon' => 'bi-exclamation-triangle',
        'tone' => 'red',
        'tooltip' => 'Laporan yang sudah melewati batas waktu penanganan (SLA).',
    ],
    [
        'label' => 'Selesai',
        'value' => 9,
        'icon' => 'bi-check-circle',
        'tone' => 'green',
        'tooltip' => 'Laporan yang sudah selesai ditinjau dan diberi keputusan.',
    ],
];

$reportTypes = [
    ['label' => 'Laporan Lowongan', 'value' => 35, 'percent' => 59, 'color' => '#3276c8'],
    ['label' => 'Laporan Perusahaan', 'value' => 24, 'percent' => 41, 'color' => '#54a17a'],
];

$reasons = [
    ['label' => 'Meminta biaya / pembayaran', 'value' => 22, 'percent' => 100],
    ['label' => 'Informasi menyesatkan', 'value' => 18, 'percent' => 82],
    ['label' => 'Data pribadi / kredensial', 'value' => 10, 'percent' => 45],
    ['label' => 'Praktik diskriminatif', 'value' => 9, 'percent' => 41],
];

$sla = [
    ['label' => 'On Time', 'value' => 38, 'class' => 'ontime'],
    ['label' => 'Approaching', 'value' => 7, 'class' => 'approaching'],
    ['label' => 'Overdue', 'value' => 5, 'class' => 'overdue'],
];

$regions = [
    ['label' => 'DKI Jakarta', 'value' => 17],
    ['label' => 'Jawa Barat', 'value' => 13],
    ['label' => 'Jawa Timur', 'value' => 11],
    ['label' => 'Banten', 'value' => 9],
    ['label' => 'Sulawesi Selatan', 'value' => 9],
];

$recentReports = [
    [
        'id' => 'VRP-2026-304511',
        'type' => 'Lowongan',
        'subject' => 'Sales Executive - DKI Jakarta',
        'company' => 'PT Finaccel Finance Indonesia',
        'region' => 'Jakarta Timur - DKI Jakarta',
        'reason' => 'Meminta biaya / pembayaran',
        'severity' => 'High',
        'sla' => 'Approaching',
        'status' => 'Dalam Verifikasi',
        'assigned_to' => '-',
        'detail_type' => 'vacancy',
    ],
    [
        'id' => 'CRP-2026-103421',
        'type' => 'Perusahaan',
        'subject' => 'PT Finaccel Finance Indonesia',
        'company' => 'PT Finaccel Finance Indonesia',
        'region' => 'Jakarta Pusat - DKI Jakarta',
        'reason' => 'Meminta biaya / pembayaran',
        'severity' => 'Urgent',
        'sla' => 'Approaching',
        'status' => 'Dalam Verifikasi',
        'assigned_to' => '-',
        'detail_type' => 'company',
    ],
    [
        'id' => 'VRP-2026-304477',
        'type' => 'Lowongan',
        'subject' => 'Kasir',
        'company' => 'CV Maju Sejahtera',
        'region' => 'Tangerang - Banten',
        'reason' => 'Informasi menyesatkan',
        'severity' => 'Medium',
        'sla' => 'On Time',
        'status' => 'Dalam Verifikasi',
        'assigned_to' => 'admin.kabkota.tng',
        'detail_type' => 'vacancy',
    ],
    [
        'id' => 'CRP-2026-103109',
        'type' => 'Perusahaan',
        'subject' => 'PT Maju Karier Nusantara',
        'company' => 'PT Maju Karier Nusantara',
        'region' => 'Bandung - Jawa Barat',
        'reason' => 'Perusahaan palsu / informasi menyesatkan',
        'severity' => 'Urgent',
        'sla' => 'On Time',
        'status' => 'Dalam Verifikasi',
        'assigned_to' => 'admin.kabkota.bdg',
        'detail_type' => 'company',
    ],
    [
        'id' => 'VRP-2026-304220',
        'type' => 'Lowongan',
        'subject' => 'Finance Accounting',
        'company' => 'PT Samudra Arta',
        'region' => 'Makassar - Sulawesi Selatan',
        'reason' => 'Data pribadi / kredensial',
        'severity' => 'High',
        'sla' => 'Overdue',
        'status' => 'Dalam Verifikasi',
        'assigned_to' => 'admin.pusat.layanan',
        'detail_type' => 'vacancy',
    ],
    [
        'id' => 'CRP-2026-102883',
        'type' => 'Perusahaan',
        'subject' => 'CV Mitra Giat Sentosa',
        'company' => 'CV Mitra Giat Sentosa',
        'region' => 'Surabaya - Jawa Timur',
        'reason' => 'Praktik diskriminatif',
        'severity' => 'Medium',
        'sla' => 'Overdue',
        'status' => 'Dalam Verifikasi',
        'assigned_to' => 'admin.pusat.layanan',
        'detail_type' => 'company',
    ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Monitoring Laporan Lowongan &amp; Perusahaan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php kh_proto_render_styles(); ?>
    <style>
        body.kh-proto-page { background: #f4f7fb; color: #253b53; }
        .dml-shell { background: #fff; border: 1px solid #dce6f1; border-radius: 14px; padding: 22px; }
        .dml-title { margin: 0; color: #1f3550; font-size: 26px; font-weight: 700; }
        .dml-subtitle { margin: 5px 0 0; color: #688097; font-size: 14px; }
        .dml-period { min-width: 180px; color: #405b75; font-size: 13px; }
        .dml-kpi { height: 100%; padding: 15px; border: 1px solid #e2eaf3; border-radius: 12px; background: #fff; cursor: help; }
        .dml-kpi:hover, .dml-kpi:focus { border-color: #b9cde4; outline: none; }
        .dml-kpi-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
        .dml-kpi-label { color: #70869c; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .035em; display: inline-flex; align-items: center; gap: 5px; }
        .dml-kpi-hint { color: #8aa0b6; font-size: 12px; line-height: 1; text-transform: none; }
        .dml-kpi-icon { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 9px; font-size: 16px; }
        .dml-kpi-value { margin-top: 9px; color: #1e3853; font-size: 27px; font-weight: 700; line-height: 1; }
        .dml-kpi-icon.blue { color: #286aa9; background: #eaf4ff; }
        .dml-kpi-icon.indigo { color: #515fc2; background: #eef0ff; }
        .dml-kpi-icon.cyan { color: #197494; background: #e7f7fc; }
        .dml-kpi-icon.amber { color: #93661c; background: #fff4dc; }
        .dml-kpi-icon.red { color: #a42e37; background: #ffe9eb; }
        .dml-kpi-icon.green { color: #247546; background: #e9f8ef; }
        .dml-panel { height: 100%; padding: 18px; border: 1px solid #e2eaf3; border-radius: 12px; background: #fff; }
        .dml-panel-title { margin: 0 0 17px; color: #29445f; font-size: 16px; font-weight: 700; }
        .dml-type-row + .dml-type-row { margin-top: 18px; }
        .dml-row-head { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 7px; font-size: 13px; }
        .dml-row-label { color: #405b75; font-weight: 600; }
        .dml-row-value { color: #1f3550; font-weight: 700; }
        .dml-track { height: 9px; overflow: hidden; border-radius: 999px; background: #edf2f7; }
        .dml-fill { height: 100%; border-radius: inherit; }
        .dml-reason-row { display: grid; grid-template-columns: minmax(150px, 1.5fr) minmax(100px, 1fr) 30px; align-items: center; gap: 10px; margin-bottom: 13px; }
        .dml-reason-label { color: #4b6279; font-size: 12px; }
        .dml-reason-value { color: #2c455e; font-size: 12px; font-weight: 700; text-align: right; }
        .dml-sla-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .dml-sla-item { padding: 13px 9px; border-radius: 10px; text-align: center; }
        .dml-sla-item.ontime { color: #247546; background: #eaf8ef; }
        .dml-sla-item.approaching { color: #8f6319; background: #fff4dd; }
        .dml-sla-item.overdue { color: #9d2831; background: #ffe7e9; }
        .dml-sla-value { display: block; font-size: 23px; font-weight: 700; }
        .dml-sla-label { display: block; margin-top: 3px; font-size: 11px; font-weight: 600; }
        .dml-region-row { display: flex; align-items: center; justify-content: space-between; padding: 9px 0; border-bottom: 1px solid #edf1f5; color: #455f78; font-size: 13px; }
        .dml-region-row:last-child { border-bottom: 0; }
        .dml-region-value { min-width: 28px; padding: 3px 8px; border-radius: 999px; background: #eef4fa; color: #315b82; font-weight: 700; text-align: center; }
        .dml-table thead th { background: #f5f9fd; color: #324a63; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .dml-table td { color: #3c566f; font-size: 12px; vertical-align: middle; }
        .dml-chip { display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 600; white-space: nowrap; }
        .dml-chip.lowongan { color: #255f9a; background: #eaf3fc; }
        .dml-chip.perusahaan { color: #28734c; background: #eaf8ef; }
        .dml-chip.urgent { color: #a52121; background: #ffe3e3; }
        .dml-chip.high { color: #a15d18; background: #fff0dc; }
        .dml-chip.medium { color: #205c9f; background: #e9f3ff; }
        .dml-chip.ontime { color: #1d763c; background: #eaf8ed; }
        .dml-chip.approaching { color: #8f6319; background: #fff4dd; }
        .dml-chip.overdue { color: #9d2831; background: #ffe7e9; }
        @media (max-width: 767px) {
            .dml-shell { padding: 16px; }
            .dml-title { font-size: 23px; }
            .dml-period { width: 100%; }
            .dml-reason-row { grid-template-columns: minmax(130px, 1.5fr) minmax(75px, 1fr) 25px; gap: 7px; }
        }
    </style>
</head>
<body class="kh-proto-page">
<?php include 'navbar.php'; ?>

<div class="kh-content-wrap">
    <div class="container-fluid px-3 px-lg-4 py-4">
        <div class="dml-shell">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                <div>
                    <h1 class="dml-title">Dashboard Monitoring Laporan Lowongan &amp; Perusahaan</h1>
                    <p class="dml-subtitle">Ringkasan pemantauan laporan, SLA, jenis aduan, dan wilayah pada dataset prototype.</p>
                </div>
                <select class="form-select form-select-sm dml-period" aria-label="Periode monitoring">
                    <option>7 hari terakhir</option>
                    <option selected>30 hari terakhir</option>
                    <option>3 bulan terakhir</option>
                </select>
            </div>

            <div class="row g-3 mb-3">
                <?php foreach ($summary as $item): ?>
                    <div class="col-6 col-md-4 col-xl-2">
                        <div
                            class="dml-kpi"
                            tabindex="0"
                            data-bs-toggle="tooltip"
                            data-bs-placement="bottom"
                            data-bs-title="<?php echo h($item['tooltip']); ?>"
                        >
                            <div class="dml-kpi-head">
                                <span class="dml-kpi-label">
                                    <?php echo h($item['label']); ?>
                                    <i class="bi bi-info-circle dml-kpi-hint" aria-hidden="true"></i>
                                </span>
                                <span class="dml-kpi-icon <?php echo h($item['tone']); ?>"><i class="bi <?php echo h($item['icon']); ?>"></i></span>
                            </div>
                            <div class="dml-kpi-value"><?php echo (int)$item['value']; ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-lg-4">
                    <section class="dml-panel">
                        <h2 class="dml-panel-title">Laporan Berdasarkan Jenis</h2>
                        <?php foreach ($reportTypes as $item): ?>
                            <div class="dml-type-row">
                                <div class="dml-row-head">
                                    <span class="dml-row-label"><?php echo h($item['label']); ?></span>
                                    <span class="dml-row-value"><?php echo (int)$item['value']; ?> (<?php echo (int)$item['percent']; ?>%)</span>
                                </div>
                                <div class="dml-track">
                                    <div class="dml-fill" style="width: <?php echo (int)$item['percent']; ?>%; background: <?php echo h($item['color']); ?>;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </section>
                </div>
                <div class="col-lg-5">
                    <section class="dml-panel">
                        <h2 class="dml-panel-title">Alasan Pelaporan Terbanyak</h2>
                        <?php foreach ($reasons as $item): ?>
                            <div class="dml-reason-row">
                                <span class="dml-reason-label"><?php echo h($item['label']); ?></span>
                                <div class="dml-track">
                                    <div class="dml-fill" style="width: <?php echo (int)$item['percent']; ?>%; background: #4c8bc8;"></div>
                                </div>
                                <span class="dml-reason-value"><?php echo (int)$item['value']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </section>
                </div>
                <div class="col-lg-3">
                    <section class="dml-panel">
                        <h2 class="dml-panel-title">Kondisi SLA Aktif</h2>
                        <div class="dml-sla-grid">
                            <?php foreach ($sla as $item): ?>
                                <div class="dml-sla-item <?php echo h($item['class']); ?>">
                                    <span class="dml-sla-value"><?php echo (int)$item['value']; ?></span>
                                    <span class="dml-sla-label"><?php echo h($item['label']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-xl-9">
                    <section class="dml-panel">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <h2 class="dml-panel-title mb-0">Laporan Terbaru</h2>
                            <a class="btn btn-sm btn-outline-primary" href="admin_review_laporan_prototype">Lihat Semua Laporan</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover dml-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Report ID</th>
                                        <th>Jenis</th>
                                        <th>Objek Laporan</th>
                                        <th>Wilayah</th>
                                        <th>Reason</th>
                                        <th>Severity</th>
                                        <th>SLA</th>
                                        <th>Status</th>
                                        <th>Assigned To</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentReports as $report): ?>
                                        <tr>
                                            <td><?php echo h($report['id']); ?></td>
                                            <td><span class="dml-chip <?php echo strtolower(h($report['type'])); ?>"><?php echo h($report['type']); ?></span></td>
                                            <td>
                                                <strong><?php echo h($report['subject']); ?></strong>
                                                <?php if ($report['type'] === 'Lowongan'): ?>
                                                    <div class="text-muted mt-1"><?php echo h($report['company']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo h($report['region']); ?></td>
                                            <td><?php echo h($report['reason']); ?></td>
                                            <td><span class="dml-chip <?php echo strtolower(h($report['severity'])); ?>"><?php echo h($report['severity']); ?></span></td>
                                            <td><span class="dml-chip <?php echo strtolower(str_replace(' ', '', h($report['sla']))); ?>"><?php echo h($report['sla']); ?></span></td>
                                            <td><?php echo h($report['status']); ?></td>
                                            <td><?php echo h($report['assigned_to'] ?? '-'); ?></td>
                                            <td>
                                                <a class="btn btn-sm btn-outline-primary text-nowrap" href="admin_review_laporan_case_detail_prototype?type=<?php echo rawurlencode($report['detail_type']); ?>&amp;report_id=<?php echo rawurlencode($report['id']); ?>">Lihat Detail</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
                <div class="col-xl-3">
                    <section class="dml-panel">
                        <h2 class="dml-panel-title">Sebaran Wilayah</h2>
                        <?php foreach ($regions as $region): ?>
                            <div class="dml-region-row">
                                <span><?php echo h($region['label']); ?></span>
                                <span class="dml-region-value"><?php echo (int)$region['value']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        bootstrap.Tooltip.getOrCreateInstance(el);
    });
</script>
<?php kh_proto_render_sidebar_script(); ?>
</body>
</html>
