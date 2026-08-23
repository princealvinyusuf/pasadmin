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

$type = strtolower(trim((string)($_GET['type'] ?? 'vacancy')));
$reportId = trim((string)($_GET['report_id'] ?? ''));

$companyCases = [
    'CRP-2026-103421' => [
        'report_id' => 'CRP-2026-103421',
        'object_type' => 'Laporan Perusahaan',
        'status' => 'PENDING_REVIEW',
        'severity' => 'High',
        'sla' => 'Approaching',
        'assigned_to' => '-',
        'wilayah' => 'Jakarta Pusat - DKI Jakarta',
        'submit_at' => '13 Aug 2026 09:14',
        'subject' => 'PT Finaccel Finance Indonesia',
        'reason' => 'Meminta biaya / pungutan',
        'comment' => 'Pelamar diminta transfer biaya administrasi sebelum interview.',
        'reporter_ref' => 'usr-92811 (email@example.com)',
        'evidence' => 'bukti_transfer.jpg, screenshot_chat.pdf',
        'snapshot' => 'Profil terverifikasi, employer_type Perusahaan, verification_status=VERIFIED, enforcement_status=ACTIVE.',
        'current_data' => 'Masih terverifikasi, memiliki 12 lowongan aktif native.',
        'history' => 'Laporan perusahaan serupa 2x dalam 30 hari terakhir.',
        'related' => 'Terkait lowongan: Sales Executive - DKI Jakarta (opsional context).',
    ],
    'CRP-2026-103109' => [
        'report_id' => 'CRP-2026-103109',
        'object_type' => 'Laporan Perusahaan',
        'status' => 'IN_REVIEW',
        'severity' => 'High',
        'sla' => 'On Time',
        'assigned_to' => 'admin.kabkota.bdg',
        'wilayah' => 'Bandung - Jawa Barat',
        'submit_at' => '13 Aug 2026 08:02',
        'subject' => 'PT Maju Karier Nusantara',
        'reason' => 'Identitas perusahaan tidak sesuai',
        'comment' => 'Alamat domain email berbeda dengan profil legal perusahaan.',
        'reporter_ref' => 'usr-81221 (john@mail.com)',
        'evidence' => 'domain_mismatch.png',
        'snapshot' => 'company_id=COMP-22911, website profile: maju-karier.co.id',
        'current_data' => 'Website saat ini berubah menjadi majukariers.id',
        'history' => 'Pernah ada permintaan klarifikasi saat verifikasi awal.',
        'related' => 'Tidak ada related_vacancy_id.',
    ],
];

$vacancyCases = [
    'VRP-2026-304511' => [
        'report_id' => 'VRP-2026-304511',
        'object_type' => 'Laporan Loker',
        'status' => 'PENDING_REVIEW',
        'severity' => 'High',
        'sla' => 'Approaching',
        'assigned_to' => '-',
        'wilayah' => 'Jakarta Timur - DKI Jakarta',
        'submit_at' => '13 Aug 2026 10:20',
        'subject' => 'Sales Executive - DKI Jakarta',
        'reason' => 'Meminta biaya/pembayaran',
        'comment' => 'Ada biaya pendaftaran dan biaya pelatihan sebelum offering.',
        'reporter_ref' => 'usr-73100 (rina@mail.com)',
        'evidence' => 'biaya_pelatihan.png, invoice.pdf',
        'snapshot' => 'vacancy_id=VAC-99311, publication_status=ACTIVE, source_flag=NATIVE.',
        'current_data' => 'Lowongan masih tayang dan belum ada perubahan status.',
        'history' => 'Report count lowongan ini: 4.',
        'related' => 'company_id=COMP-00012026 (PT Finaccel Finance Indonesia).',
    ],
    'VRP-2026-304477' => [
        'report_id' => 'VRP-2026-304477',
        'object_type' => 'Laporan Loker',
        'status' => 'IN_REVIEW',
        'severity' => 'Medium',
        'sla' => 'On Time',
        'assigned_to' => 'admin.kabkota.tng',
        'wilayah' => 'Tangerang - Banten',
        'submit_at' => '13 Aug 2026 09:11',
        'subject' => 'Kasir',
        'reason' => 'Mencurigakan / informasi menyesatkan',
        'comment' => 'Deskripsi lowongan tidak konsisten dengan posisi yang ditawarkan.',
        'reporter_ref' => 'usr-65520 (anna@mail.com)',
        'evidence' => 'chat_rekrutmen.jpg',
        'snapshot' => 'vacancy_id=VAC-88119, source_flag=INTEGRATION.',
        'current_data' => 'Listing masih aktif, menunggu klarifikasi mitra.',
        'history' => 'Report count lowongan ini: 2.',
        'related' => 'company_id=COMP-98122 (CV Maju Sejahtera).',
    ],
];

$dataset = $type === 'company' ? $companyCases : $vacancyCases;
$case = $dataset[$reportId] ?? null;

if ($case === null) {
    http_response_code(404);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Case Prototype</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f4f7fb; }
        .ard-shell { background: #fff; border: 1px solid #dce6f1; border-radius: 12px; padding: 20px; }
        .ard-title { font-size: 24px; font-weight: 700; color: #1f3550; margin-bottom: 4px; }
        .ard-meta { color: #60778f; font-size: 13px; }
        .ard-badge { border-radius: 999px; padding: 4px 10px; font-size: 12px; font-weight: 600; }
        .ard-section { border: 1px solid #e3ebf5; border-radius: 10px; padding: 14px; margin-top: 12px; background: #fff; }
        .ard-section h6 { font-weight: 700; color: #2b455f; margin-bottom: 8px; }
        .ard-text { color: #2f4b66; font-size: 14px; margin-bottom: 0; }
        .ard-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
        .ard-item { border: 1px solid #edf2f8; border-radius: 8px; padding: 10px; background: #fbfdff; }
        .ard-item-label { color: #6c8298; font-size: 12px; margin-bottom: 4px; }
        .ard-item-value { color: #1f3550; font-weight: 600; font-size: 14px; }
        .ard-actions { margin-top: 16px; display: flex; flex-wrap: wrap; gap: 8px; }
        .ard-feedback { margin-top: 12px; font-size: 13px; display: none; }
        .ard-outcome { border: 1px solid #d9e7f8; background: #f4f9ff; border-radius: 10px; padding: 12px; margin-top: 12px; display: none; }
        .ard-outcome-title { font-weight: 700; color: #1f3550; margin-bottom: 6px; }
        .ard-outcome-text { color: #31506f; font-size: 13px; margin-bottom: 0; }
        @media (max-width: 991px) {
            .ard-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <?php if ($case === null): ?>
        <div class="alert alert-warning">
            Case tidak ditemukan atau belum tersedia pada dataset prototype.
            <a href="admin_review_laporan_prototype" class="alert-link">Kembali ke queue</a>.
        </div>
    <?php else: ?>
        <div class="mb-3">
            <a href="admin_review_laporan_prototype" class="text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Kembali ke queue</a>
        </div>
        <div class="ard-shell">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <div class="ard-title"><?php echo h($case['object_type']); ?> - <?php echo h($case['report_id']); ?></div>
                    <div class="ard-meta">Subject: <?php echo h($case['subject']); ?> | Submit: <?php echo h($case['submit_at']); ?></div>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <span id="caseStatusBadge" class="ard-badge text-bg-primary"><?php echo h(($case['status'] === 'PENDING_REVIEW' || $case['status'] === 'IN_REVIEW') ? 'Dalam Verifikasi' : $case['status']); ?></span>
                        <span class="ard-badge text-bg-danger"><?php echo h($case['severity']); ?></span>
                        <span class="ard-badge text-bg-warning"><?php echo h($case['sla']); ?></span>
                    </div>
                </div>
            </div>

            <div class="ard-section">
                <h6>Header Case</h6>
                <div class="ard-grid">
                    <div class="ard-item"><div class="ard-item-label">Assigned To</div><div class="ard-item-value"><?php echo h($case['assigned_to']); ?></div></div>
                    <div class="ard-item"><div class="ard-item-label">Wilayah</div><div class="ard-item-value"><?php echo h($case['wilayah']); ?></div></div>
                    <div class="ard-item"><div class="ard-item-label">Reason Utama</div><div class="ard-item-value"><?php echo h($case['reason']); ?></div></div>
                </div>
            </div>

            <div class="ard-section">
                <h6>Panel Laporan Pelapor</h6>
                <p class="ard-text"><strong>Reporter Ref:</strong> <?php echo h($case['reporter_ref']); ?></p>
                <p class="ard-text"><strong>Komentar:</strong> <?php echo h($case['comment']); ?></p>
                <p class="ard-text"><strong>Evidensi:</strong> <?php echo h($case['evidence']); ?></p>
            </div>

            <div class="ard-section">
                <h6>Panel Snapshot</h6>
                <p class="ard-text"><?php echo h($case['snapshot']); ?></p>
            </div>

            <div class="ard-section">
                <h6>Panel Data Terkini</h6>
                <p class="ard-text"><?php echo h($case['current_data']); ?></p>
            </div>

            <div class="ard-section">
                <h6>Panel Histori dan Keterkaitan</h6>
                <p class="ard-text"><strong>Histori:</strong> <?php echo h($case['history']); ?></p>
                <p class="ard-text"><strong>Konteks Relasi:</strong> <?php echo h($case['related']); ?></p>
            </div>

            <div class="ard-section">
                <h6>Panel Keputusan & Action Scope (Prototype)</h6>
                <div class="row g-2">
                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1">Keputusan</label>
                        <select id="decisionSelect" class="form-select form-select-sm">
                            <option>PENDING_REVIEW</option>
                            <option>IN_REVIEW</option>
                            <option>WAITING_REPORTER_INFO</option>
                            <option>WAITING_EMPLOYER_CLARIFICATION</option>
                            <option>NOT_PROVEN</option>
                            <option>VALID_ACTIONED</option>
                            <option>ESCALATED</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1">Enforcement Action</label>
                        <select id="actionSelect" class="form-select form-select-sm">
                            <option>NONE</option>
                            <option>WARNING</option>
                            <option>PROFILE_CORRECTION_REQUIRED</option>
                            <option>RESTRICT_POSTING</option>
                            <option>SUSPEND_EMPLOYER_ACCOUNT</option>
                            <option>BLOCK_EMPLOYER_ACCOUNT</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small mb-1">Action Scope Lowongan</label>
                        <select id="scopeSelect" class="form-select form-select-sm">
                            <option>NONE</option>
                            <option>SELECTED_VACANCIES</option>
                            <option>ALL_ACTIVE_NATIVE_VACANCIES</option>
                        </select>
                    </div>
                </div>
                <div class="ard-actions">
                    <button id="requestReporterInfoBtn" class="btn btn-sm btn-outline-secondary" type="button">Minta Info Pelapor</button>
                    <button id="requestEmployerClarificationBtn" class="btn btn-sm btn-outline-secondary" type="button">Minta Klarifikasi Pemberi Kerja</button>
                    <button id="saveDecisionBtn" class="btn btn-sm btn-primary" type="button">Simpan Keputusan (Prototype)</button>
                </div>
                <div id="decisionFeedback" class="ard-feedback"></div>
                <div id="decisionOutcome" class="ard-outcome">
                    <div class="ard-outcome-title">Ringkasan Outcome Prototype</div>
                    <p id="decisionOutcomeText" class="ard-outcome-text"></p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        const statusBadge = document.getElementById('caseStatusBadge');
        const decisionSelect = document.getElementById('decisionSelect');
        const actionSelect = document.getElementById('actionSelect');
        const scopeSelect = document.getElementById('scopeSelect');
        const requestReporterBtn = document.getElementById('requestReporterInfoBtn');
        const requestEmployerBtn = document.getElementById('requestEmployerClarificationBtn');
        const saveBtn = document.getElementById('saveDecisionBtn');
        const feedback = document.getElementById('decisionFeedback');
        const outcome = document.getElementById('decisionOutcome');
        const outcomeText = document.getElementById('decisionOutcomeText');

        if (!statusBadge || !decisionSelect || !actionSelect || !scopeSelect || !saveBtn || !feedback || !outcome || !outcomeText) {
            return;
        }

        function setStatusBadge(status) {
            const displayStatus = (status === 'PENDING_REVIEW' || status === 'IN_REVIEW') ? 'Dalam Verifikasi' : status;
            statusBadge.textContent = displayStatus;
            statusBadge.classList.remove('text-bg-primary', 'text-bg-warning', 'text-bg-info', 'text-bg-success', 'text-bg-secondary');
            if (status === 'WAITING_REPORTER_INFO' || status === 'WAITING_EMPLOYER_CLARIFICATION') {
                statusBadge.classList.add('text-bg-warning');
                return;
            }
            if (status === 'ESCALATED') {
                statusBadge.classList.add('text-bg-secondary');
                return;
            }
            if (status === 'VALID_ACTIONED' || status === 'CLOSED' || status === 'NOT_PROVEN') {
                statusBadge.classList.add('text-bg-success');
                return;
            }
            if (status === 'IN_REVIEW' || status === 'PENDING_REVIEW') {
                statusBadge.classList.add('text-bg-info');
                return;
            }
            statusBadge.classList.add('text-bg-primary');
        }

        function showFeedback(message, isError) {
            feedback.style.display = 'block';
            feedback.classList.toggle('text-danger', !!isError);
            feedback.classList.toggle('text-success', !isError);
            feedback.textContent = message;
        }

        function showOutcome(message) {
            outcome.style.display = 'block';
            outcomeText.textContent = message;
        }

        if (requestReporterBtn) {
            requestReporterBtn.addEventListener('click', function () {
                decisionSelect.value = 'WAITING_REPORTER_INFO';
                setStatusBadge('WAITING_REPORTER_INFO');
                showFeedback('Status diubah ke WAITING_REPORTER_INFO. Permintaan info akan tampil di Laporan Saya (prototype).', false);
                showOutcome('Menunggu informasi tambahan dari pelapor. Tidak ada perubahan otomatis pada perusahaan/lowongan.');
            });
        }

        if (requestEmployerBtn) {
            requestEmployerBtn.addEventListener('click', function () {
                decisionSelect.value = 'WAITING_EMPLOYER_CLARIFICATION';
                setStatusBadge('WAITING_EMPLOYER_CLARIFICATION');
                showFeedback('Status diubah ke WAITING_EMPLOYER_CLARIFICATION. Klarifikasi ke pemberi kerja disiapkan (prototype).', false);
                showOutcome('Menunggu klarifikasi pemberi kerja. Identitas pelapor harus tetap terlindungi.');
            });
        }

        saveBtn.addEventListener('click', function () {
            const decision = decisionSelect.value;
            const action = actionSelect.value;
            const scope = scopeSelect.value;

            if (decision === 'VALID_ACTIONED' && action === 'NONE') {
                showFeedback('Untuk VALID_ACTIONED, Enforcement Action tidak boleh NONE.', true);
                return;
            }

            if ((action === 'SUSPEND_EMPLOYER_ACCOUNT' || action === 'BLOCK_EMPLOYER_ACCOUNT') && scope === 'NONE') {
                showFeedback('Untuk SUSPEND/BLOCK, pilih Action Scope Lowongan secara eksplisit bila ada dampak lowongan.', true);
                return;
            }

            setStatusBadge(decision);
            showFeedback('Keputusan prototype berhasil disimpan.', false);

            let outcomeMessage = 'Decision: ' + decision + '. ';
            if (decision === 'NOT_PROVEN') {
                outcomeMessage += 'Case dapat ditutup tanpa perubahan perusahaan/lowongan.';
            } else if (decision === 'VALID_ACTIONED') {
                outcomeMessage += 'Action: ' + action + ' dengan scope ' + scope + '.';
            } else if (decision === 'ESCALATED') {
                outcomeMessage += 'Case dieskalasi ke role berwenang.';
            } else {
                outcomeMessage += 'Case tetap pada alur review.';
            }
            showOutcome(outcomeMessage);
        });
    })();
</script>
</body>
</html>
