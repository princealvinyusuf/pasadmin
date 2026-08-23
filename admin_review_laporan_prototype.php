<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

if (!current_user_can('admin_review_laporan_prototype_view') && !current_user_can('manage_settings')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$companyReports = [
    [
        'report_id' => 'CRP-2026-103421',
        'tanggal_masuk' => '13 Aug 2026 09:14',
        'perusahaan' => 'PT Finaccel Finance Indonesia',
        'employer_type' => 'Perusahaan',
        'wilayah' => 'Jakarta Pusat - DKI Jakarta',
        'reason' => 'Meminta biaya / pungutan',
        'severity' => 'High',
        'sla' => 'Approaching',
        'status' => 'PENDING_REVIEW',
        'assigned_to' => '-',
    ],
    [
        'report_id' => 'CRP-2026-103109',
        'tanggal_masuk' => '13 Aug 2026 08:02',
        'perusahaan' => 'PT Maju Karier Nusantara',
        'employer_type' => 'Perusahaan',
        'wilayah' => 'Bandung - Jawa Barat',
        'reason' => 'Identitas perusahaan tidak sesuai',
        'severity' => 'High',
        'sla' => 'On Time',
        'status' => 'IN_REVIEW',
        'assigned_to' => 'admin.kabkota.bdg',
    ],
    [
        'report_id' => 'CRP-2026-102883',
        'tanggal_masuk' => '12 Aug 2026 16:33',
        'perusahaan' => 'CV Mitra Giat Sentosa',
        'employer_type' => 'Lembaga',
        'wilayah' => 'Surabaya - Jawa Timur',
        'reason' => 'Perilaku rekrutmen tidak pantas',
        'severity' => 'High',
        'sla' => 'Overdue',
        'status' => 'ESCALATED',
        'assigned_to' => 'admin.pusat.layanan',
    ],
];

$vacancyReports = [
    [
        'report_id' => 'VRP-2026-304511',
        'waktu_masuk' => '13 Aug 2026 10:20',
        'lowongan' => 'Sales Executive - DKI Jakarta',
        'pemberi_kerja' => 'PT Finaccel Finance Indonesia',
        'wilayah' => 'Jakarta Timur - DKI Jakarta',
        'reason' => 'Meminta biaya/pembayaran',
        'severity' => 'High',
        'report_count' => '4',
        'sla' => 'Approaching',
        'status' => 'PENDING_REVIEW',
        'assigned_to' => '-',
        'source' => 'NATIVE',
    ],
    [
        'report_id' => 'VRP-2026-304477',
        'waktu_masuk' => '13 Aug 2026 09:11',
        'lowongan' => 'Kasir',
        'pemberi_kerja' => 'CV Maju Sejahtera',
        'wilayah' => 'Tangerang - Banten',
        'reason' => 'Informasi menyesatkan',
        'severity' => 'High',
        'report_count' => '2',
        'sla' => 'On Time',
        'status' => 'IN_REVIEW',
        'assigned_to' => 'admin.kabkota.tng',
        'source' => 'INTEGRATION',
    ],
    [
        'report_id' => 'VRP-2026-304220',
        'waktu_masuk' => '12 Aug 2026 15:40',
        'lowongan' => 'Finance Accounting',
        'pemberi_kerja' => 'PT Samudra Arta',
        'wilayah' => 'Makassar - Sulawesi Selatan',
        'reason' => 'Data sensitif/kredensial',
        'severity' => 'High',
        'report_count' => '1',
        'sla' => 'Overdue',
        'status' => 'ESCALATED',
        'assigned_to' => 'admin.pusat.layanan',
        'source' => 'NATIVE',
    ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Lowongan & Perusahaan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f4f7fb; }
        .arp-shell { background: #fff; border: 1px solid #dce6f1; border-radius: 12px; padding: 20px; }
        .arp-title { font-size: 26px; font-weight: 700; color: #1f3550; }
        .arp-sub { color: #60778f; font-size: 14px; }
        .arp-note { border: 1px solid #dce8f7; background: #f6f9ff; color: #325277; border-radius: 10px; padding: 10px 12px; font-size: 13px; }
        .arp-kpi-card { border: 1px solid #e3ebf5; border-radius: 10px; padding: 14px; background: #fff; height: 100%; }
        .arp-kpi-label { color: #6e849a; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; }
        .arp-kpi-value { color: #1f3550; font-size: 24px; font-weight: 700; line-height: 1.2; margin-top: 4px; }
        .arp-chip { display: inline-flex; align-items: center; border-radius: 999px; padding: 3px 10px; font-size: 12px; font-weight: 600; }
        .arp-chip.critical { background: #ffe3e3; color: #a52121; }
        .arp-chip.high { background: #fff2df; color: #b06313; }
        .arp-chip.medium { background: #e9f3ff; color: #205c9f; }
        .arp-chip.low { background: #eaf8ed; color: #1b7a3b; }
        .arp-chip.pending { background: #edf2ff; color: #4457b5; }
        .arp-chip.review { background: #e8f8ff; color: #1f6f95; }
        .arp-chip.waiting { background: #fff4dd; color: #8f6319; }
        .arp-chip.overdue { background: #ffe7e9; color: #9d2831; }
        .arp-chip.ontime { background: #eaf8ed; color: #1d763c; }
        .arp-chip.approaching { background: #fff4dd; color: #8f6319; }
        .arp-table thead th { background: #f5f9fd; color: #324a63; font-weight: 600; white-space: nowrap; }
        .arp-table td { vertical-align: middle; color: #2b455f; font-size: 13px; }
        .arp-tabs .nav-link { font-weight: 600; color: #4b637c; }
        .arp-tabs .nav-link.active { color: #fff; background: #1f5f99; border-color: #1f5f99; }
        .arp-actions { white-space: nowrap; }
        .arp-booking-box { border: 1px solid #d9e7f8; background: #f4f9ff; color: #284e76; border-radius: 10px; padding: 10px 12px; font-size: 13px; }
        .arp-booking-box strong { color: #173b61; }
        .arp-booking-msg { font-size: 12px; margin-top: 6px; display: none; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container py-4">
    <div class="arp-shell">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div>
                <div class="arp-title">Laporan Lowongan & Perusahaan</div>
                <div class="arp-sub">UI version untuk proses pemeriksaan laporan perusahaan dan laporan loker (referensi FSD Lapor Perusahaan + Lapor Loker).</div>
            </div>
            <button type="button" class="btn btn-primary btn-sm"><i class="bi bi-download me-1"></i>Export Prototype</button>
        </div>

        <div class="arp-note mb-3">
            Prinsip workflow: semua laporan masuk status <strong>Pending</strong>, admin harus klik <strong>Ambil Kasus</strong> (manual booking),
            maksimal 5 case aktif per admin, dan tidak ada keputusan otomatis hanya karena severity/SLA.
        </div>
        <div class="arp-booking-box mb-3">
            Case aktif saya (simulasi): <strong><span id="activeCaseCountText">0</span>/5</strong>
            <div id="bookingStatusMessage" class="arp-booking-msg"></div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-2">
                <div class="arp-kpi-card">
                    <div class="arp-kpi-label">Pending Review</div>
                    <div class="arp-kpi-value">32</div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="arp-kpi-card">
                    <div class="arp-kpi-label">Dalam Verifikasi</div>
                    <div class="arp-kpi-value">18</div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="arp-kpi-card">
                    <div class="arp-kpi-label">Approaching SLA</div>
                    <div class="arp-kpi-value">7</div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="arp-kpi-card">
                    <div class="arp-kpi-label">Overdue</div>
                    <div class="arp-kpi-value">5</div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="arp-kpi-card">
                    <div class="arp-kpi-label">High</div>
                    <div class="arp-kpi-value">14</div>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="arp-kpi-card">
                    <div class="arp-kpi-label">Selesai</div>
                    <div class="arp-kpi-value">9</div>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs arp-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#queueLoker" type="button" role="tab">Queue Laporan Loker</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#queuePerusahaan" type="button" role="tab">Queue Laporan Perusahaan</button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="queueLoker" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover arp-table align-middle">
                        <thead>
                            <tr>
                                <th>Report ID</th>
                                <th>Waktu Masuk</th>
                                <th>Lowongan</th>
                                <th>Pemberi Kerja</th>
                                <th>Wilayah</th>
                                <th>Reason</th>
                                <th>Severity</th>
                                <th>Report Count</th>
                                <th>SLA</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Source</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($vacancyReports as $row): ?>
                            <?php $isPending = $row['status'] === 'PENDING_REVIEW'; ?>
                            <tr>
                                <td><?php echo h($row['report_id']); ?></td>
                                <td><?php echo h($row['waktu_masuk']); ?></td>
                                <td><?php echo h($row['lowongan']); ?></td>
                                <td><?php echo h($row['pemberi_kerja']); ?></td>
                                <td><?php echo h($row['wilayah']); ?></td>
                                <td><?php echo h($row['reason']); ?></td>
                                <td><span class="arp-chip <?php echo strtolower($row['severity']); ?>"><?php echo h($row['severity']); ?></span></td>
                                <td><?php echo h($row['report_count']); ?></td>
                                <td>
                                    <?php
                                    $slaClass = strtolower(str_replace(' ', '', $row['sla']));
                                    if ($slaClass === 'ontime') { $slaClass = 'ontime'; }
                                    if ($slaClass === 'approaching') { $slaClass = 'approaching'; }
                                    if ($slaClass === 'overdue') { $slaClass = 'overdue'; }
                                    ?>
                                    <span class="arp-chip <?php echo h($slaClass); ?>"><?php echo h($row['sla']); ?></span>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = 'review';
                                    if ($row['status'] === 'PENDING_REVIEW') { $statusClass = 'pending'; }
                                    if ($row['status'] === 'WAITING_REPORTER_INFO' || $row['status'] === 'WAITING_EMPLOYER_CLARIFICATION') { $statusClass = 'waiting'; }
                                    if ($row['status'] === 'ESCALATED') { $statusClass = 'overdue'; }
                                    ?>
                                    <span class="arp-chip <?php echo h($statusClass); ?> js-status-chip"><?php echo h($row['status']); ?></span>
                                </td>
                                <td class="js-assigned-cell"><?php echo h($row['assigned_to']); ?></td>
                                <td><?php echo h($row['source']); ?></td>
                                <td class="arp-actions">
                                    <a class="btn btn-sm btn-outline-primary" href="admin_review_laporan_case_detail_prototype?type=vacancy&report_id=<?php echo rawurlencode($row['report_id']); ?>">Detail</a>
                                    <button
                                        class="btn btn-sm btn-primary js-book-case"
                                        data-report-id="<?php echo h($row['report_id']); ?>"
                                        data-case-type="vacancy"
                                        <?php echo $isPending ? '' : 'disabled'; ?>
                                    >
                                        <?php echo $isPending ? 'Ambil Kasus' : 'Sedang Diproses'; ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="queuePerusahaan" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover arp-table align-middle">
                        <thead>
                            <tr>
                                <th>Report ID</th>
                                <th>Tanggal Masuk</th>
                                <th>Perusahaan</th>
                                <th>Employer Type</th>
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
                        <?php foreach ($companyReports as $row): ?>
                            <?php $isPending = $row['status'] === 'PENDING_REVIEW'; ?>
                            <tr>
                                <td><?php echo h($row['report_id']); ?></td>
                                <td><?php echo h($row['tanggal_masuk']); ?></td>
                                <td><?php echo h($row['perusahaan']); ?></td>
                                <td><?php echo h($row['employer_type']); ?></td>
                                <td><?php echo h($row['wilayah']); ?></td>
                                <td><?php echo h($row['reason']); ?></td>
                                <td><span class="arp-chip <?php echo strtolower($row['severity']); ?>"><?php echo h($row['severity']); ?></span></td>
                                <td>
                                    <?php
                                    $slaClass = strtolower(str_replace(' ', '', $row['sla']));
                                    if ($slaClass === 'ontime') { $slaClass = 'ontime'; }
                                    if ($slaClass === 'approaching') { $slaClass = 'approaching'; }
                                    if ($slaClass === 'overdue') { $slaClass = 'overdue'; }
                                    ?>
                                    <span class="arp-chip <?php echo h($slaClass); ?>"><?php echo h($row['sla']); ?></span>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = 'review';
                                    if ($row['status'] === 'PENDING_REVIEW') { $statusClass = 'pending'; }
                                    if ($row['status'] === 'WAITING_REPORTER_INFO' || $row['status'] === 'WAITING_EMPLOYER_CLARIFICATION') { $statusClass = 'waiting'; }
                                    if ($row['status'] === 'ESCALATED') { $statusClass = 'overdue'; }
                                    ?>
                                    <span class="arp-chip <?php echo h($statusClass); ?> js-status-chip"><?php echo h($row['status']); ?></span>
                                </td>
                                <td class="js-assigned-cell"><?php echo h($row['assigned_to']); ?></td>
                                <td class="arp-actions">
                                    <a class="btn btn-sm btn-outline-primary" href="admin_review_laporan_case_detail_prototype?type=company&report_id=<?php echo rawurlencode($row['report_id']); ?>">Detail</a>
                                    <button
                                        class="btn btn-sm btn-primary js-book-case"
                                        data-report-id="<?php echo h($row['report_id']); ?>"
                                        data-case-type="company"
                                        <?php echo $isPending ? '' : 'disabled'; ?>
                                    >
                                        <?php echo $isPending ? 'Ambil Kasus' : 'Sedang Diproses'; ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        const MAX_ACTIVE_CASE = 5;
        const STORAGE_KEY = 'adminReviewPrototypeBookedCases';
        const ACTIVE_USER = 'admin.sesi.anda';

        const activeCountText = document.getElementById('activeCaseCountText');
        const statusMessage = document.getElementById('bookingStatusMessage');
        const bookButtons = document.querySelectorAll('.js-book-case');

        function readBookedCases() {
            try {
                const raw = localStorage.getItem(STORAGE_KEY);
                const parsed = raw ? JSON.parse(raw) : [];
                if (!Array.isArray(parsed)) return [];
                return parsed;
            } catch (e) {
                return [];
            }
        }

        function saveBookedCases(cases) {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(cases));
        }

        function showMessage(text, isError) {
            if (!statusMessage) return;
            statusMessage.style.display = 'block';
            statusMessage.classList.toggle('text-danger', !!isError);
            statusMessage.classList.toggle('text-success', !isError);
            statusMessage.textContent = text;
        }

        function refreshCounter() {
            const cases = readBookedCases();
            if (activeCountText) activeCountText.textContent = String(cases.length);
            return cases;
        }

        function markRowAsBooked(btn) {
            const row = btn.closest('tr');
            if (!row) return;
            const statusChip = row.querySelector('.js-status-chip');
            const assignedCell = row.querySelector('.js-assigned-cell');
            if (statusChip) {
                statusChip.textContent = 'IN_REVIEW';
                statusChip.classList.remove('pending', 'waiting', 'overdue');
                statusChip.classList.add('review');
            }
            if (assignedCell) assignedCell.textContent = ACTIVE_USER;
            btn.disabled = true;
            btn.textContent = 'Sudah Diambil';
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-outline-secondary');
        }

        function markBookedRowsFromStorage() {
            const booked = readBookedCases();
            bookButtons.forEach(function (btn) {
                const reportId = btn.getAttribute('data-report-id') || '';
                const alreadyBooked = booked.includes(reportId);
                if (alreadyBooked) {
                    markRowAsBooked(btn);
                }
            });
        }

        refreshCounter();
        markBookedRowsFromStorage();

        bookButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.disabled) return;
                const reportId = btn.getAttribute('data-report-id') || '';
                if (!reportId) return;

                const booked = refreshCounter();
                if (booked.includes(reportId)) {
                    markRowAsBooked(btn);
                    showMessage('Case ini sudah pernah diambil pada sesi prototype.', false);
                    return;
                }

                if (booked.length >= MAX_ACTIVE_CASE) {
                    showMessage('Gagal mengambil case: batas maksimal 5 case aktif per admin tercapai.', true);
                    return;
                }

                booked.push(reportId);
                saveBookedCases(booked);
                refreshCounter();
                markRowAsBooked(btn);
                showMessage('Berhasil mengambil case ' + reportId + ' ke status IN_REVIEW.', false);
            });
        });
    })();
</script>
</body>
</html>
