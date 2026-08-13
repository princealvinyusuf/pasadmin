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
        'severity' => 'Critical',
        'sla' => 'Approaching',
        'assigned_to' => '-',
        'wilayah' => 'Jakarta Pusat - DKI Jakarta',
        'submit_at' => '13 Aug 2026 09:14',
        'subject' => 'PT Finaccel Finance Indonesia',
        'reason' => 'Meminta biaya / pungutan',
        'comment' => 'Pelamar diminta transfer biaya administrasi sebelum interview.',
        'reporter_ref' => 'usr-92811 (masked: em***@example.com)',
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
        'reporter_ref' => 'usr-81221 (masked: jo***@mail.com)',
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
        'severity' => 'Critical',
        'sla' => 'Approaching',
        'assigned_to' => '-',
        'wilayah' => 'Jakarta Timur - DKI Jakarta',
        'submit_at' => '13 Aug 2026 10:20',
        'subject' => 'Sales Executive - DKI Jakarta',
        'reason' => 'Meminta biaya/pembayaran',
        'comment' => 'Ada biaya pendaftaran dan biaya pelatihan sebelum offering.',
        'reporter_ref' => 'usr-73100 (masked: ri***@mail.com)',
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
        'severity' => 'High',
        'sla' => 'On Time',
        'assigned_to' => 'admin.kabkota.tng',
        'wilayah' => 'Tangerang - Banten',
        'submit_at' => '13 Aug 2026 09:11',
        'subject' => 'Kasir',
        'reason' => 'Informasi menyesatkan',
        'comment' => 'Deskripsi lowongan tidak konsisten dengan posisi yang ditawarkan.',
        'reporter_ref' => 'usr-65520 (masked: an***@mail.com)',
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
                </div>
                <div class="d-flex gap-2">
                    <span class="ard-badge text-bg-primary"><?php echo h($case['status']); ?></span>
                    <span class="ard-badge text-bg-danger"><?php echo h($case['severity']); ?></span>
                    <span class="ard-badge text-bg-warning"><?php echo h($case['sla']); ?></span>
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
                        <select class="form-select form-select-sm">
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
                        <select class="form-select form-select-sm">
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
                        <select class="form-select form-select-sm">
                            <option>NONE</option>
                            <option>SELECTED_VACANCIES</option>
                            <option>ALL_ACTIVE_NATIVE_VACANCIES</option>
                        </select>
                    </div>
                </div>
                <div class="ard-actions">
                    <button class="btn btn-sm btn-outline-secondary" type="button">Minta Info Pelapor</button>
                    <button class="btn btn-sm btn-outline-secondary" type="button">Minta Klarifikasi Pemberi Kerja</button>
                    <button class="btn btn-sm btn-primary" type="button">Simpan Keputusan (Prototype)</button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
