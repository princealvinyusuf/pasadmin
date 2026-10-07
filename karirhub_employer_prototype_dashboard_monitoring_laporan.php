<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_ui.php';

if (!kh_proto_can_access('karirhub_employer_prototype_monitoring_laporan_view')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

require_once __DIR__ . '/karirhub_employer_prototype_monitoring_storage.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

kh_monitoring_ensure_tables($conn);
$periodDays = kh_monitoring_period_days((int)($_GET['days'] ?? 30));
$dashboardData = kh_monitoring_dashboard_data($conn, $periodDays);
$summaryValues = $dashboardData['summary'];
$reasons = $dashboardData['reasons'];
$regions = $dashboardData['regions'];
$recentReports = $dashboardData['recent_reports'];

$summary = [
    ['key' => 'reports', 'value_key' => 'total_reports', 'label' => 'Jumlah Laporan', 'unit' => 'Laporan', 'icon' => 'bi-flag', 'tone' => 'blue', 'tooltip' => 'Jumlah seluruh laporan pada periode yang dipilih.'],
    ['key' => 'reporters', 'value_key' => 'total_reporters', 'label' => 'Jumlah Pelapor', 'unit' => 'Pelapor', 'icon' => 'bi-people', 'tone' => 'blue', 'tooltip' => 'Jumlah pelapor unik pada periode yang dipilih.'],
    ['key' => 'vacancies', 'value_key' => 'total_vacancies', 'label' => 'Jumlah lowongan yang dilaporkan', 'unit' => 'Lowongan', 'icon' => 'bi-briefcase', 'tone' => 'cyan', 'tooltip' => 'Jumlah lowongan unik yang dilaporkan pada periode yang dipilih.'],
    ['key' => 'pending', 'value_key' => 'pending_reports', 'label' => 'Menunggu Verifikasi', 'unit' => 'Laporan', 'icon' => 'bi-hourglass-split', 'tone' => 'indigo', 'tooltip' => 'Laporan baru yang belum diambil admin.'],
    ['key' => 'reviewing', 'value_key' => 'reviewing_reports', 'label' => 'Dalam Verifikasi', 'unit' => 'Laporan', 'icon' => 'bi-search', 'tone' => 'cyan', 'tooltip' => 'Laporan yang sedang diperiksa admin.'],
    ['key' => 'completed', 'value_key' => 'completed_reports', 'label' => 'Selesai', 'unit' => 'Laporan', 'icon' => 'bi-check-circle', 'tone' => 'green', 'tooltip' => 'Laporan yang sudah selesai ditinjau.'],
    ['key' => 'blocked-vacancies', 'value_key' => 'blocked_vacancies', 'label' => 'Jumlah Lowongan di Blokir', 'unit' => 'Lowongan di Blokir', 'icon' => 'bi-briefcase-fill', 'tone' => 'red', 'tooltip' => 'Jumlah lowongan yang diblokir pada periode yang dipilih.'],
    ['key' => 'blocked-employers', 'value_key' => 'blocked_employers', 'label' => 'Jumlah Akun Pemberi Kerja di Blokir', 'unit' => 'Akun di Blokir', 'icon' => 'bi-person-x-fill', 'tone' => 'red', 'tooltip' => 'Jumlah akun pemberi kerja yang diblokir pada periode yang dipilih.'],
];
foreach ($summary as &$summaryItem) {
    $summaryItem['value'] = (int)($summaryValues[$summaryItem['value_key']] ?? 0);
}
unset($summaryItem);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Monitoring Laporan Lowongan Kerja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php kh_proto_render_styles(); ?>
    <style>
        body.kh-proto-page { background: #f4f7fb; color: #253b53; }
        .dml-shell { background: #fff; border: 1px solid #dce6f1; border-radius: 14px; padding: 22px; }
        .dml-title { margin: 0; color: #1f3550; font-size: 26px; font-weight: 700; }
        .dml-subtitle { margin: 5px 0 0; color: #688097; font-size: 14px; }
        .dml-period { min-width: 180px; color: #405b75; font-size: 13px; }
        .dml-kpi { height: 100%; padding: 15px; border: 1px solid #e2eaf3; border-radius: 12px; background: #fff; cursor: pointer; }
        .dml-kpi:hover, .dml-kpi:focus { border-color: #76a6d4; box-shadow: 0 4px 14px rgba(37, 82, 126, .08); outline: none; }
        .dml-kpi-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
        .dml-kpi-label { color: #70869c; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .035em; display: inline-flex; align-items: center; gap: 5px; }
        .dml-kpi-hint { color: #8aa0b6; font-size: 12px; line-height: 1; text-transform: none; }
        .dml-kpi-icon { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; border-radius: 9px; font-size: 16px; }
        .dml-kpi-value { margin-top: 9px; color: #1e3853; font-size: 27px; font-weight: 700; line-height: 1; }
        .dml-kpi-unit { margin-left: 4px; font-size: 13px; font-weight: 600; line-height: 1.25; }
        .dml-kpi-icon.blue { color: #286aa9; background: #eaf4ff; }
        .dml-kpi-icon.indigo { color: #515fc2; background: #eef0ff; }
        .dml-kpi-icon.cyan { color: #197494; background: #e7f7fc; }
        .dml-kpi-icon.amber { color: #93661c; background: #fff4dc; }
        .dml-kpi-icon.red { color: #a42e37; background: #ffe9eb; }
        .dml-kpi-icon.green { color: #247546; background: #e9f8ef; }
        .dml-panel { height: 100%; padding: 18px; border: 1px solid #e2eaf3; border-radius: 12px; background: #fff; }
        .dml-panel-title { margin: 0 0 17px; color: #29445f; font-size: 16px; font-weight: 700; }
        .dml-track { height: 9px; overflow: hidden; border-radius: 999px; background: #edf2f7; }
        .dml-fill { height: 100%; border-radius: inherit; }
        .dml-reason-row { display: grid; grid-template-columns: minmax(150px, 1.5fr) minmax(100px, 1fr) 30px; align-items: center; gap: 10px; margin-bottom: 13px; }
        .dml-reason-label { color: #4b6279; font-size: 12px; }
        .dml-reason-value { color: #2c455e; font-size: 12px; font-weight: 700; text-align: right; }
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
        .dml-chip.review { color: #315b82; background: #eaf3fc; }
        .dml-tabs { display: flex; flex-wrap: wrap; gap: 4px 28px; margin: 2px 0 14px; border-bottom: 1px solid #e7edf5; }
        .dml-tab { border: 0; background: transparent; padding: 8px 2px 10px; color: #7a8c9e; font-size: 14px; font-weight: 500; line-height: 1.2; border-bottom: 2px solid transparent; margin-bottom: -1px; }
        .dml-tab:hover { color: #0a8f8a; }
        .dml-tab.active { color: #0a8f8a; border-bottom-color: #0a8f8a; }
        .dml-tab:focus { outline: none; }
        .dml-filter-menu { width: min(720px, calc(100vw - 32px)); padding: 16px; }
        .dml-filter-label { color: #405b75; font-size: 12px; font-weight: 600; }
        .dml-empty { color: #75879a; text-align: center; padding: 22px 12px; }
        .dml-list-item { width: 100%; border: 0; border-bottom: 1px solid #e7edf4; background: #fff; padding: 13px 15px; text-align: left; }
        .dml-list-item:hover, .dml-list-item:focus { background: #f5f9fd; outline: none; }
        .dml-list-title { color: #29445f; font-size: 14px; font-weight: 700; }
        .dml-list-copy { color: #687f96; font-size: 12px; }
        .dml-detail-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .dml-detail-field { padding: 11px 12px; border: 1px solid #e5edf5; border-radius: 9px; background: #fbfdff; }
        .dml-detail-field dt { margin-bottom: 4px; color: #70869c; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .dml-detail-field dd { margin: 0; color: #2d4963; font-size: 13px; overflow-wrap: anywhere; white-space: pre-line; }
        @media (max-width: 767px) {
            .dml-shell { padding: 16px; }
            .dml-title { font-size: 23px; }
            .dml-period { width: 100%; }
            .dml-reason-row { grid-template-columns: minmax(130px, 1.5fr) minmax(75px, 1fr) 25px; gap: 7px; }
            .dml-detail-grid { grid-template-columns: 1fr; }
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
                    <h1 class="dml-title">Dashboard Monitoring Laporan Lowongan Kerja</h1>
                    <p class="dml-subtitle">Ringkasan pemantauan laporan, SLA, jenis aduan, dan wilayah pada dataset prototype.</p>
                </div>
                <select class="form-select form-select-sm dml-period" id="monitoringPeriod" aria-label="Periode monitoring">
                    <option value="7" <?php echo $periodDays === 7 ? 'selected' : ''; ?>>7 hari terakhir</option>
                    <option value="30" <?php echo $periodDays === 30 ? 'selected' : ''; ?>>30 hari terakhir</option>
                    <option value="90" <?php echo $periodDays === 90 ? 'selected' : ''; ?>>3 bulan terakhir</option>
                </select>
            </div>

            <div class="row g-3 mb-3">
                <?php foreach ($summary as $item): ?>
                    <div class="col-6 col-md-4 col-xl-3">
                        <div
                            class="dml-kpi"
                            tabindex="0"
                            role="button"
                            aria-label="Lihat daftar <?php echo h($item['label']); ?>"
                            data-card="<?php echo h($item['key']); ?>"
                            data-card-title="<?php echo h($item['label']); ?>"
                            data-value-key="<?php echo h($item['value_key']); ?>"
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
                            <div class="dml-kpi-value" data-summary-value="<?php echo h($item['value_key']); ?>" data-unit="<?php echo h($item['unit']); ?>">
                                <?php echo (int)$item['value']; ?><span class="dml-kpi-unit"><?php echo h($item['unit']); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12">
                    <section class="dml-panel" id="reasonPanel">
                        <h2 class="dml-panel-title">Alasan Pelaporan Terbanyak</h2>
                        <div id="reasonRows"><?php foreach ($reasons as $item): ?>
                            <div class="dml-reason-row">
                                <span class="dml-reason-label"><?php echo h($item['label']); ?></span>
                                <div class="dml-track">
                                    <div class="dml-fill" style="width: <?php echo (int)$item['percent']; ?>%; background: #4c8bc8;"></div>
                                </div>
                                <span class="dml-reason-value"><?php echo (int)$item['value']; ?></span>
                            </div>
                        <?php endforeach; ?></div>
                    </section>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-xl-9">
                    <section class="dml-panel">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <h2 class="dml-panel-title mb-0">Daftar Laporan</h2>
                            <div class="dropdown">
                                <button
                                    class="btn btn-sm btn-outline-primary dropdown-toggle"
                                    type="button"
                                    id="reportFilterButton"
                                    data-bs-toggle="dropdown"
                                    data-bs-auto-close="outside"
                                    aria-expanded="false"
                                >
                                    <i class="bi bi-funnel me-1"></i>Filter
                                </button>
                                <div class="dropdown-menu dropdown-menu-end dml-filter-menu" aria-labelledby="reportFilterButton">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label dml-filter-label" for="filterKeyword">Cari Laporan</label>
                                            <input class="form-control form-control-sm" id="filterKeyword" type="search" placeholder="Report ID, objek laporan, atau perusahaan">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label dml-filter-label" for="filterStatus">Status</label>
                                            <select class="form-select form-select-sm" id="filterStatus">
                                                <option value="">Semua status</option>
                                                <?php foreach (array_unique(array_column($recentReports, 'status')) as $value): ?>
                                                    <option value="<?php echo h($value); ?>"><?php echo h($value); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label dml-filter-label" for="filterSeverity">Severity</label>
                                            <select class="form-select form-select-sm" id="filterSeverity">
                                                <option value="">Semua severity</option>
                                                <?php foreach (array_unique(array_column($recentReports, 'severity')) as $value): ?>
                                                    <option value="<?php echo h($value); ?>"><?php echo h($value); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label dml-filter-label" for="filterSla">SLA</label>
                                            <select class="form-select form-select-sm" id="filterSla">
                                                <option value="">Semua SLA</option>
                                                <?php foreach (array_unique(array_column($recentReports, 'sla')) as $value): ?>
                                                    <option value="<?php echo h($value); ?>"><?php echo h($value); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label dml-filter-label" for="filterRegion">Wilayah</label>
                                            <select class="form-select form-select-sm" id="filterRegion">
                                                <option value="">Semua wilayah</option>
                                                <?php foreach (array_unique(array_column($recentReports, 'region')) as $value): ?>
                                                    <option value="<?php echo h($value); ?>"><?php echo h($value); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label dml-filter-label" for="filterReason">Alasan Pelaporan</label>
                                            <select class="form-select form-select-sm" id="filterReason">
                                                <option value="">Semua alasan</option>
                                                <?php foreach (array_unique(array_column($recentReports, 'reason')) as $value): ?>
                                                    <option value="<?php echo h($value); ?>"><?php echo h($value); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label dml-filter-label" for="filterAssigned">Assigned To</label>
                                            <select class="form-select form-select-sm" id="filterAssigned">
                                                <option value="">Semua admin</option>
                                                <?php foreach (array_unique(array_column($recentReports, 'assigned_to')) as $value): ?>
                                                    <option value="<?php echo h($value); ?>"><?php echo h($value); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-end gap-2 mt-3 pt-3 border-top">
                                        <button class="btn btn-sm btn-outline-secondary" type="button" id="resetReportFilter">Reset</button>
                                        <button class="btn btn-sm btn-primary" type="button" id="applyReportFilter">Terapkan Filter</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="dml-tabs" role="tablist" aria-label="Filter laporan terbaru">
                            <button type="button" class="dml-tab active" data-filter="semua">Semua</button>
                            <button type="button" class="dml-tab" data-filter="menunggu">Menunggu Verifikasi</button>
                            <button type="button" class="dml-tab" data-filter="dalam-verifikasi">Dalam Verifikasi</button>
                            <button type="button" class="dml-tab" data-filter="selesai">Selesai</button>
                            <button type="button" class="dml-tab" data-filter="overdue">Overdue</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover dml-table mb-0" id="laporanTerbaruTable">
                                <thead>
                                    <tr>
                                        <th>Report ID</th>
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
                                        <tr
                                            class="js-report-row"
                                            data-status="<?php echo h($report['status']); ?>"
                                            data-severity="<?php echo h($report['severity']); ?>"
                                            data-sla="<?php echo h($report['sla']); ?>"
                                            data-region="<?php echo h($report['region']); ?>"
                                            data-reason="<?php echo h($report['reason']); ?>"
                                            data-assigned="<?php echo h($report['assigned_to'] ?? '-'); ?>"
                                        >
                                            <td><?php echo h($report['id']); ?></td>
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
                                                <button class="btn btn-sm btn-outline-primary text-nowrap js-open-detail" type="button" data-record-type="report" data-record-id="<?php echo h($report['id']); ?>">Lihat Detail</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr id="laporanTerbaruEmpty" class="d-none">
                                        <td colspan="9" class="dml-empty">Tidak ada laporan yang sesuai dengan filter.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
                <div class="col-xl-3">
                    <section class="dml-panel">
                        <h2 class="dml-panel-title">Sebaran Wilayah</h2>
                        <div id="regionRows"><?php foreach ($regions as $region): ?>
                            <div class="dml-region-row">
                                <span><?php echo h($region['label']); ?></span>
                                <span class="dml-region-value"><?php echo (int)$region['value']; ?></span>
                            </div>
                        <?php endforeach; ?></div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="summaryListModal" tabindex="-1" aria-labelledby="summaryListModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title fs-5" id="summaryListModalLabel">Daftar Data</h2>
                    <div class="text-muted small" id="summaryListModalPeriod"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body p-0" id="summaryListModalBody"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="summaryDetailModal" tabindex="-1" aria-labelledby="summaryDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="summaryDetailModalLabel">Detail Data</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body" id="summaryDetailModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="backToSummaryList"><i class="bi bi-arrow-left me-1"></i>Kembali ke daftar</button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        const endpoint = 'karirhub_employer_prototype_monitoring_laporan_data.php';
        const periodField = document.getElementById('monitoringPeriod');
        const tabs = document.querySelectorAll('.dml-tab');
        const emptyRow = document.getElementById('laporanTerbaruEmpty');
        const filterButton = document.getElementById('reportFilterButton');
        const keywordField = document.getElementById('filterKeyword');
        const reportBody = document.querySelector('#laporanTerbaruTable tbody');
        const listElement = document.getElementById('summaryListModal');
        const detailElement = document.getElementById('summaryDetailModal');
        const listModal = bootstrap.Modal.getOrCreateInstance(listElement);
        const detailModal = bootstrap.Modal.getOrCreateInstance(detailElement);
        const listBody = document.getElementById('summaryListModalBody');
        const detailBody = document.getElementById('summaryDetailModalBody');
        const filterFields = {
            status: document.getElementById('filterStatus'),
            severity: document.getElementById('filterSeverity'),
            sla: document.getElementById('filterSla'),
            region: document.getElementById('filterRegion'),
            reason: document.getElementById('filterReason'),
            assigned: document.getElementById('filterAssigned')
        };
        let activeTabFilter = 'semua';
        let returnToList = false;

        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });

        function escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = value == null ? '' : String(value);
            return div.innerHTML;
        }

        async function getJson(params) {
            const response = await fetch(endpoint + '?' + new URLSearchParams(params), {
                headers: { 'Accept': 'application/json' }
            });
            const payload = await response.json();
            if (!response.ok || !payload.ok) {
                throw new Error(payload.message || 'Gagal memuat data.');
            }
            return payload.data;
        }

        function optionValues(key, reports) {
            return Array.from(new Set(reports.map(function (item) { return item[key] || '-'; }))).sort();
        }

        function refillFilter(field, placeholder, values) {
            const selected = field.value;
            field.innerHTML = '<option value="">' + escapeHtml(placeholder) + '</option>' + values.map(function (value) {
                return '<option value="' + escapeHtml(value) + '">' + escapeHtml(value) + '</option>';
            }).join('');
            field.value = values.includes(selected) ? selected : '';
        }

        function reportRow(report) {
            const company = report.type === 'Lowongan'
                ? '<div class="text-muted mt-1">' + escapeHtml(report.company) + '</div>'
                : '';
            const severityClass = String(report.severity || '').toLowerCase();
            const slaClass = String(report.sla || '').toLowerCase().replace(/\s/g, '');
            return '<tr class="js-report-row"'
                + ' data-status="' + escapeHtml(report.status) + '"'
                + ' data-severity="' + escapeHtml(report.severity) + '"'
                + ' data-sla="' + escapeHtml(report.sla) + '"'
                + ' data-region="' + escapeHtml(report.region) + '"'
                + ' data-reason="' + escapeHtml(report.reason) + '"'
                + ' data-assigned="' + escapeHtml(report.assigned_to || '-') + '">'
                + '<td>' + escapeHtml(report.id) + '</td>'
                + '<td><strong>' + escapeHtml(report.subject) + '</strong>' + company + '</td>'
                + '<td>' + escapeHtml(report.region) + '</td>'
                + '<td>' + escapeHtml(report.reason) + '</td>'
                + '<td><span class="dml-chip ' + escapeHtml(severityClass) + '">' + escapeHtml(report.severity) + '</span></td>'
                + '<td><span class="dml-chip ' + escapeHtml(slaClass) + '">' + escapeHtml(report.sla) + '</span></td>'
                + '<td>' + escapeHtml(report.status) + '</td>'
                + '<td>' + escapeHtml(report.assigned_to || '-') + '</td>'
                + '<td><button class="btn btn-sm btn-outline-primary text-nowrap js-open-detail" type="button" data-record-type="report" data-record-id="' + escapeHtml(report.id) + '">Lihat Detail</button></td>'
                + '</tr>';
        }

        function renderDashboard(data) {
            Object.keys(data.summary).forEach(function (key) {
                const node = document.querySelector('[data-summary-value="' + key + '"]');
                if (node) {
                    node.innerHTML = escapeHtml(data.summary[key]) + '<span class="dml-kpi-unit">' + escapeHtml(node.dataset.unit) + '</span>';
                }
            });
            document.getElementById('reasonRows').innerHTML = data.reasons.length ? data.reasons.map(function (item) {
                return '<div class="dml-reason-row"><span class="dml-reason-label">' + escapeHtml(item.label)
                    + '</span><div class="dml-track"><div class="dml-fill" style="width:' + Number(item.percent)
                    + '%;background:#4c8bc8"></div></div><span class="dml-reason-value">' + Number(item.value) + '</span></div>';
            }).join('') : '<div class="dml-empty">Belum ada data pada periode ini.</div>';
            document.getElementById('regionRows').innerHTML = data.regions.length ? data.regions.map(function (item) {
                return '<div class="dml-region-row"><span>' + escapeHtml(item.label)
                    + '</span><span class="dml-region-value">' + Number(item.value) + '</span></div>';
            }).join('') : '<div class="dml-empty">Belum ada data pada periode ini.</div>';
            reportBody.querySelectorAll('.js-report-row').forEach(function (row) { row.remove(); });
            emptyRow.insertAdjacentHTML('beforebegin', data.recent_reports.map(reportRow).join(''));
            refillFilter(filterFields.status, 'Semua status', optionValues('status', data.recent_reports));
            refillFilter(filterFields.severity, 'Semua severity', optionValues('severity', data.recent_reports));
            refillFilter(filterFields.sla, 'Semua SLA', optionValues('sla', data.recent_reports));
            refillFilter(filterFields.region, 'Semua wilayah', optionValues('region', data.recent_reports));
            refillFilter(filterFields.reason, 'Semua alasan', optionValues('reason', data.recent_reports));
            refillFilter(filterFields.assigned, 'Semua admin', optionValues('assigned_to', data.recent_reports));
            applyFilter();
        }

        function applyFilter() {
            let visible = 0;
            document.querySelectorAll('#laporanTerbaruTable tbody tr.js-report-row').forEach(function (row) {
                const status = row.getAttribute('data-status') || '';
                const sla = row.getAttribute('data-sla') || '';
                let show = activeTabFilter === 'semua'
                    || (activeTabFilter === 'menunggu' && status === 'Menunggu Verifikasi')
                    || (activeTabFilter === 'dalam-verifikasi' && status === 'Dalam Verifikasi')
                    || (activeTabFilter === 'selesai' && status === 'Selesai')
                    || (activeTabFilter === 'overdue' && sla === 'Overdue');

                Object.keys(filterFields).forEach(function (key) {
                    const selectedValue = filterFields[key].value;
                    if (selectedValue && row.getAttribute('data-' + key) !== selectedValue) {
                        show = false;
                    }
                });
                const keyword = keywordField.value.trim().toLowerCase();
                if (keyword && !row.textContent.toLowerCase().includes(keyword)) {
                    show = false;
                }

                row.classList.toggle('d-none', !show);
                if (show) visible += 1;
            });
            if (emptyRow) emptyRow.classList.toggle('d-none', visible > 0);
            const hasAdvancedFilter = Object.keys(filterFields).some(function (key) {
                return filterFields[key].value !== '';
            }) || keywordField.value.trim() !== '';
            filterButton.classList.toggle('active', hasAdvancedFilter);
        }

        async function refreshDashboard() {
            document.querySelectorAll('.dml-kpi').forEach(function (card) { card.setAttribute('aria-busy', 'true'); });
            try {
                const data = await getJson({ action: 'summary', days: periodField.value });
                renderDashboard(data);
                const url = new URL(window.location.href);
                url.searchParams.set('days', periodField.value);
                window.history.replaceState({}, '', url);
            } catch (error) {
                window.alert(error.message);
            } finally {
                document.querySelectorAll('.dml-kpi').forEach(function (card) { card.removeAttribute('aria-busy'); });
            }
        }

        function listItem(row) {
            return '<button type="button" class="dml-list-item js-list-detail" data-record-type="' + escapeHtml(row.record_type)
                + '" data-record-id="' + escapeHtml(row.id) + '"><div class="d-flex justify-content-between gap-3">'
                + '<div><div class="dml-list-title">' + escapeHtml(row.title) + '</div>'
                + '<div class="dml-list-copy mt-1">' + escapeHtml(row.subtitle) + '</div>'
                + '<div class="dml-list-copy mt-1">' + escapeHtml(row.meta) + '</div></div>'
                + '<div class="text-end flex-shrink-0"><span class="dml-chip review">' + escapeHtml(row.status || '-') + '</span>'
                + '<div class="dml-list-copy mt-2">' + escapeHtml(row.date_text || '') + '</div></div></div></button>';
        }

        async function openList(card) {
            const cardKey = card.dataset.card;
            document.getElementById('summaryListModalLabel').textContent = card.dataset.cardTitle;
            document.getElementById('summaryListModalPeriod').textContent = periodField.options[periodField.selectedIndex].text;
            listBody.innerHTML = '<div class="dml-empty"><span class="spinner-border spinner-border-sm me-2"></span>Memuat data...</div>';
            listModal.show();
            try {
                const rows = await getJson({ action: 'list', card: cardKey, days: periodField.value });
                listBody.innerHTML = rows.length ? rows.map(listItem).join('') : '<div class="dml-empty">Belum ada data pada periode ini.</div>';
            } catch (error) {
                listBody.innerHTML = '<div class="alert alert-danger m-3">' + escapeHtml(error.message) + '</div>';
            }
        }

        const detailLabels = {
            report_id: 'Report ID', object_type: 'Jenis Objek', subject: 'Objek Laporan', region: 'Wilayah',
            reason: 'Alasan', comment: 'Komentar Pelapor', evidence: 'Bukti', severity: 'Severity',
            sla_status: 'SLA', verification_status: 'Status Verifikasi', assigned_to: 'Assigned To',
            snapshot: 'Snapshot Saat Dilaporkan', current_data: 'Data Saat Ini', submitted_at: 'Waktu Masuk',
            reviewed_at: 'Waktu Selesai', reporter_name: 'Nama Pelapor', reporter_email: 'Email Pelapor',
            reporter_phone: 'Telepon Pelapor', employer_name: 'Pemberi Kerja', employer_type: 'Tipe Pemberi Kerja',
            employer_email: 'Email Pemberi Kerja', vacancy_title: 'Lowongan', vacancy_location: 'Lokasi Lowongan',
            reporter_id: 'ID Pelapor', name: 'Nama', email: 'Email', phone: 'Telepon', created_at: 'Terdaftar Pada',
            vacancy_id: 'ID Lowongan', employer_id: 'ID Pemberi Kerja', title: 'Judul Lowongan',
            location: 'Lokasi', job_field: 'Bidang Pekerjaan', job_type: 'Tipe Pekerjaan',
            posted_at: 'Tanggal Tayang', deadline: 'Batas Lamaran', publication_status: 'Status Publikasi',
            enforcement_status: 'Status Penindakan', blocked_at: 'Diblokir Pada', description: 'Deskripsi',
            business_field: 'Bidang Usaha', website: 'Website', address: 'Alamat', verification_status: 'Status Verifikasi'
        };

        function relatedSection(reports) {
            if (!Array.isArray(reports) || !reports.length) return '';
            return '<h3 class="fs-6 mt-4">Riwayat Laporan Terkait</h3><div class="table-responsive"><table class="table table-sm dml-table">'
                + '<thead><tr><th>Report ID</th><th>Objek</th><th>Alasan</th><th>Status</th><th>Waktu</th></tr></thead><tbody>'
                + reports.map(function (row) {
                    return '<tr><td>' + escapeHtml(row.report_id) + '</td><td>' + escapeHtml(row.subject)
                        + '</td><td>' + escapeHtml(row.reason) + '</td><td>' + escapeHtml(row.status)
                        + '</td><td>' + escapeHtml(row.submitted_at) + '</td></tr>';
                }).join('') + '</tbody></table></div>';
        }

        function vacanciesSection(vacancies) {
            if (!Array.isArray(vacancies) || !vacancies.length) return '';
            return '<h3 class="fs-6 mt-4">Lowongan Pemberi Kerja</h3><div class="table-responsive"><table class="table table-sm dml-table">'
                + '<thead><tr><th>ID</th><th>Lowongan</th><th>Lokasi</th><th>Status</th></tr></thead><tbody>'
                + vacancies.map(function (row) {
                    return '<tr><td>' + escapeHtml(row.vacancy_id) + '</td><td>' + escapeHtml(row.title)
                        + '</td><td>' + escapeHtml(row.location) + '</td><td>' + escapeHtml(row.enforcement_status) + '</td></tr>';
                }).join('') + '</tbody></table></div>';
        }

        function renderDetail(data) {
            const ignored = ['updated_at', 'related_reports', 'vacancies'];
            const fields = Object.keys(data).filter(function (key) {
                return !ignored.includes(key) && detailLabels[key] && data[key] !== null && data[key] !== '';
            });
            return '<dl class="dml-detail-grid mb-0">' + fields.map(function (key) {
                return '<div class="dml-detail-field"><dt>' + escapeHtml(detailLabels[key])
                    + '</dt><dd>' + escapeHtml(data[key]) + '</dd></div>';
            }).join('') + '</dl>' + relatedSection(data.related_reports) + vacanciesSection(data.vacancies);
        }

        async function openDetail(type, id, fromList) {
            returnToList = fromList;
            document.getElementById('summaryDetailModalLabel').textContent = 'Detail ' + id;
            detailBody.innerHTML = '<div class="dml-empty"><span class="spinner-border spinner-border-sm me-2"></span>Memuat detail...</div>';
            const showDetail = function () { detailModal.show(); };
            if (fromList && listElement.classList.contains('show')) {
                listElement.addEventListener('hidden.bs.modal', showDetail, { once: true });
                listModal.hide();
            } else {
                detailModal.show();
            }
            try {
                const data = await getJson({ action: 'detail', type: type, id: id });
                detailBody.innerHTML = renderDetail(data);
            } catch (error) {
                detailBody.innerHTML = '<div class="alert alert-danger">' + escapeHtml(error.message) + '</div>';
            }
        }

        document.querySelectorAll('.dml-kpi').forEach(function (card) {
            card.addEventListener('click', function () { openList(card); });
            card.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openList(card);
                }
            });
        });

        listBody.addEventListener('click', function (event) {
            const button = event.target.closest('.js-list-detail');
            if (button) openDetail(button.dataset.recordType, button.dataset.recordId, true);
        });

        document.addEventListener('click', function (event) {
            const button = event.target.closest('.js-open-detail');
            if (button) openDetail(button.dataset.recordType, button.dataset.recordId, false);
        });

        detailElement.addEventListener('hidden.bs.modal', function () {
            if (returnToList) {
                returnToList = false;
                listModal.show();
            }
        });
        document.getElementById('backToSummaryList').addEventListener('click', function () {
            detailModal.hide();
        });
        periodField.addEventListener('change', function () {
            listModal.hide();
            detailModal.hide();
            refreshDashboard();
        });

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (item) { item.classList.remove('active'); });
                tab.classList.add('active');
                activeTabFilter = tab.getAttribute('data-filter') || 'semua';
                applyFilter();
            });
        });

        document.getElementById('applyReportFilter').addEventListener('click', function () {
            applyFilter();
            bootstrap.Dropdown.getOrCreateInstance(filterButton).hide();
        });

        document.getElementById('resetReportFilter').addEventListener('click', function () {
            keywordField.value = '';
            Object.keys(filterFields).forEach(function (key) {
                filterFields[key].value = '';
            });
            applyFilter();
        });
    })();
</script>
<?php kh_proto_render_sidebar_script(); ?>
</body>
</html>
