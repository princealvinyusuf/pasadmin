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
        'vacancy_snapshot' => null,
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
        'vacancy_snapshot' => null,
        'current_data' => 'Website saat ini berubah menjadi majukariers.id',
        'history' => 'Pernah ada permintaan klarifikasi saat verifikasi awal.',
        'related' => 'Tidak ada related_vacancy_id.',
    ],
];

$salesExecutiveSnapshot = [
    'title' => 'Sales Executive',
    'location' => 'DKI Jakarta',
    'posted_at' => '1 Agustus 2026',
    'vacancy_count' => '1',
    'deadline' => '25 Agt 2026',
    'job_field' => 'Penjualan dan Pengembangan Bisnis',
    'job_type' => 'Full time',
    'job_category' => 'Lowongan dalam negeri',
    'gender' => 'Laki-laki / Perempuan',
    'salary_range' => 'Dirahasiakan',
    'description' => [
        'Melakukan riset dan analisis pasar untuk mengetahui kebutuhan pelanggan, tren pasar, dan peluang bisnis.',
        'Menyusun dan melaksanakan strategi pemasaran sesuai dengan target perusahaan.',
        'Mengidentifikasi calon pelanggan dan mengembangkan peluang pasar baru.',
        'Menyusun program promosi untuk meningkatkan penjualan dan pengenalan produk perusahaan.',
        'Menjelaskan keunggulan, spesifikasi, dan manfaat produk kepada calon pelanggan.',
        'Menjalin dan memelihara hubungan baik dengan pelanggan, distributor, maupun mitra bisnis.',
    ],
    'special_requirements' => [
        'Memiliki pendidikan minimal S1 Pemasaran, Manajemen, Bisnis, Komunikasi, atau bidang yang relevan.',
        'Memiliki pengalaman kerja di bidang marketing, sales, business development, atau pengembangan pasar.',
        'Menguasai market research, analisis tren pasar, perilaku konsumen, dan analisis kompetitor.',
        'Memahami penyusunan dan implementasi strategi pemasaran dan promosi.',
        'Mampu melakukan analisis data pemasaran dan menyusun laporan hasil kegiatan pemasaran.',
        'Memiliki kemampuan dalam product knowledge, product positioning, dan market segmentation.',
        'Mampu mengembangkan dan memelihara hubungan dengan pelanggan, distributor, dan mitra bisnis.',
        'Memiliki kemampuan komunikasi, negosiasi, presentasi, dan koordinasi yang baik.',
        'Menguasai penggunaan Microsoft Office atau aplikasi pengolahan data dan pemasaran.',
        'Memiliki kemampuan bahasa Inggris, baik lisan maupun tulisan, terutama apabila berhubungan dengan pelanggan atau mitra internasional.',
    ],
    'general_requirements' => [
        'Minimal Pendidikan' => 'Diploma',
        'Status Pernikahan' => 'Tidak ada preferensi',
        'Minimal pengalaman' => 'Tidak ditentukan',
        'Kondisi fisik' => 'Non disabilitas',
        'Keterampilan' => 'Microsoft Excel, Microsoft Office, Risk Analysis',
    ],
];

$employerProfile = [
    'name' => 'Naga Bumi Pratama',
    'field' => 'Consultancy (Business & Management)',
    'registered_since' => '11 Agustus 2026',
    'contact' => '081218916348',
    'email' => 'nagabumipratama@gmail.com',
    'website' => 'www.nagabumipratama.com',
    'address' => 'Kota Adm. Jakarta Selatan, The CEO Building Level 12, Jl. TB Simatupang No. 18C',
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
        'vacancy_snapshot' => $salesExecutiveSnapshot,
        'employer_profile' => $employerProfile,
        'current_data' => 'Lowongan masih tayang dan belum ada perubahan status.',
        'verified_by_admin' => 'admin.pusat.verifikasi',
        'history_count' => 4,
        'same_vacancy_reports' => [
            [
                'report_id' => 'VRP-2026-304511',
                'waktu_masuk' => '13 Aug 2026 10:20',
                'reason' => 'Meminta biaya/pembayaran',
                'severity' => 'High',
                'status' => 'Dalam Verifikasi',
                'reporter_ref' => 'usr-73100 (rina@mail.com)',
                'is_current' => true,
            ],
            [
                'report_id' => 'VRP-2026-304498',
                'waktu_masuk' => '12 Aug 2026 18:05',
                'reason' => 'Meminta biaya/pembayaran',
                'severity' => 'High',
                'status' => 'Dalam Verifikasi',
                'reporter_ref' => 'usr-74211 (budi@mail.com)',
                'is_current' => false,
            ],
            [
                'report_id' => 'VRP-2026-304450',
                'waktu_masuk' => '11 Aug 2026 14:22',
                'reason' => 'Mencurigakan / informasi menyesatkan',
                'severity' => 'Medium',
                'status' => 'Dalam Verifikasi',
                'reporter_ref' => 'usr-70933 (sari@mail.com)',
                'is_current' => false,
            ],
            [
                'report_id' => 'VRP-2026-304401',
                'waktu_masuk' => '10 Aug 2026 09:48',
                'reason' => 'Meminta biaya/pembayaran',
                'severity' => 'High',
                'status' => 'Dalam Verifikasi',
                'reporter_ref' => 'usr-68820 (andi@mail.com)',
                'is_current' => false,
            ],
        ],
        'company_other_reports' => [
            [
                'report_id' => 'VRP-2026-303812',
                'waktu_masuk' => '08 Aug 2026 11:15',
                'lowongan' => 'Account Executive - Jakarta',
                'reason' => 'Meminta data pribadi sensitif / kredensial',
                'severity' => 'High',
                'status' => 'Dalam Verifikasi',
            ],
            [
                'report_id' => 'VRP-2026-303640',
                'waktu_masuk' => '05 Aug 2026 16:40',
                'lowongan' => 'Business Development Staff',
                'reason' => 'Mencurigakan / informasi menyesatkan',
                'severity' => 'Medium',
                'status' => 'Dalam Verifikasi',
            ],
            [
                'report_id' => 'CRP-2026-103421',
                'waktu_masuk' => '13 Aug 2026 09:14',
                'lowongan' => 'Laporan Perusahaan',
                'reason' => 'Meminta biaya / pungutan',
                'severity' => 'High',
                'status' => 'Dalam Verifikasi',
            ],
        ],
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
        'vacancy_snapshot' => array_merge($salesExecutiveSnapshot, [
            'title' => 'Kasir',
            'location' => 'Tangerang - Banten',
            'job_field' => 'Penjualan dan Pengembangan Bisnis',
            'job_type' => 'Full time',
        ]),
        'employer_profile' => $employerProfile,
        'current_data' => 'Listing masih aktif, menunggu klarifikasi mitra.',
        'verified_by_admin' => 'admin.kabkota.tng',
        'history_count' => 2,
        'same_vacancy_reports' => [
            [
                'report_id' => 'VRP-2026-304477',
                'waktu_masuk' => '13 Aug 2026 09:11',
                'reason' => 'Mencurigakan / informasi menyesatkan',
                'severity' => 'Medium',
                'status' => 'Dalam Verifikasi',
                'reporter_ref' => 'usr-65520 (anna@mail.com)',
                'is_current' => true,
            ],
            [
                'report_id' => 'VRP-2026-304410',
                'waktu_masuk' => '11 Aug 2026 13:05',
                'reason' => 'Mencurigakan / informasi menyesatkan',
                'severity' => 'Medium',
                'status' => 'Dalam Verifikasi',
                'reporter_ref' => 'usr-64110 (dewi@mail.com)',
                'is_current' => false,
            ],
        ],
        'company_other_reports' => [
            [
                'report_id' => 'VRP-2026-303901',
                'waktu_masuk' => '07 Aug 2026 10:30',
                'lowongan' => 'Pramuniaga - Tangerang',
                'reason' => 'Meminta biaya/pembayaran',
                'severity' => 'High',
                'status' => 'Dalam Verifikasi',
            ],
        ],
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
        .ard-vacancy-title { font-size: 22px; font-weight: 700; color: #1f3550; margin-bottom: 2px; }
        .ard-vacancy-location { color: #4f667d; font-size: 15px; margin-bottom: 6px; }
        .ard-vacancy-meta { color: #6c8298; font-size: 13px; margin-bottom: 14px; }
        .ard-vacancy-meta span + span::before { content: "•"; margin: 0 8px; color: #9aadc0; }
        .ard-vacancy-attrs { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 18px; margin-bottom: 16px; }
        .ard-vacancy-attr { font-size: 14px; color: #2f4b66; }
        .ard-vacancy-attr strong { color: #1f3550; font-weight: 600; }
        .ard-vacancy-block { margin-top: 14px; }
        .ard-vacancy-block-title { font-size: 15px; font-weight: 700; color: #1f3550; margin-bottom: 8px; }
        .ard-vacancy-list { margin: 0; padding-left: 18px; color: #2f4b66; font-size: 14px; line-height: 1.55; }
        .ard-vacancy-list li { margin-bottom: 6px; }
        .ard-vacancy-kv { margin: 0; padding: 0; list-style: none; color: #2f4b66; font-size: 14px; }
        .ard-vacancy-kv li { margin-bottom: 6px; }
        .ard-history-toggle { border: 0; background: transparent; padding: 0; color: #2f4b66; font-size: 14px; display: inline-flex; align-items: center; gap: 6px; text-align: left; }
        .ard-history-toggle:hover { color: #1f3550; }
        .ard-history-toggle .bi-chevron-down { transition: transform .2s ease; font-size: 12px; color: #56718d; }
        .ard-history-toggle[aria-expanded="true"] .bi-chevron-down { transform: rotate(180deg); }
        .ard-related-table { width: 100%; margin-top: 10px; border-collapse: collapse; font-size: 13px; }
        .ard-related-table th { background: #f5f9fd; color: #324a63; font-weight: 600; padding: 8px 10px; border: 1px solid #e3ebf5; white-space: nowrap; }
        .ard-related-table td { color: #2b455f; padding: 8px 10px; border: 1px solid #e3ebf5; vertical-align: middle; }
        .ard-related-current { background: #f3f9ff; }
        .ard-subfield { margin-top: 14px; }
        .ard-subfield-title { font-size: 14px; font-weight: 700; color: #1f3550; margin-bottom: 8px; }
        .ard-aksi-list { display: flex; flex-direction: column; gap: 8px; }
        .ard-aksi-item { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border: 1px solid #e3ebf5; border-radius: 8px; background: #fbfdff; color: #1f3550; font-size: 14px; }
        .ard-aksi-item.is-disabled { background: #f1f4f8; color: #9aa9b8; border-color: #e6ebf1; }
        .ard-aksi-item.is-disabled .form-check-input { opacity: .55; }
        @media (max-width: 991px) {
            .ard-grid { grid-template-columns: 1fr; }
            .ard-vacancy-attrs { grid-template-columns: 1fr; }
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
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#tindakanModal">
                        <i class="bi bi-lightning-charge me-1"></i>Tindakan
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="requestEmployerClarificationBtn">
                        <i class="bi bi-chat-left-text me-1"></i>Minta Klarifikasi Pemberi Kerja
                    </button>
                </div>
            </div>
            <div id="tindakanSuccessAlert" class="alert alert-success mt-3 mb-0 d-none" role="alert"></div>

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
                <p class="ard-text d-flex flex-wrap align-items-center gap-2 mb-0">
                    <span><strong>Evidensi:</strong> <?php echo h($case['evidence']); ?></span>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="downloadEvidenceBtn">
                        <i class="bi bi-download me-1"></i>Download Dokumen
                    </button>
                </p>
            </div>

            <div class="ard-section">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <h6 class="mb-0">Panel Snapshot</h6>
                    <?php if (!empty($case['vacancy_snapshot']) && is_array($case['vacancy_snapshot'])): ?>
                        <a
                            class="btn btn-sm btn-outline-primary"
                            href="https://karirhub.kemnaker.go.id/lowongan-dalam-negeri/lowongan/marketing-specialist-4934b8f4-5744-4530-afdd-d6365136bcb0"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <i class="bi bi-box-arrow-up-right me-1"></i>Lihat Lowongan
                        </a>
                    <?php endif; ?>
                </div>
                <?php if (!empty($case['vacancy_snapshot']) && is_array($case['vacancy_snapshot'])): ?>
                    <?php $vacancy = $case['vacancy_snapshot']; ?>
                    <div class="ard-vacancy-title"><?php echo h($vacancy['title']); ?></div>
                    <div class="ard-vacancy-location"><?php echo h($vacancy['location']); ?></div>
                    <div class="ard-vacancy-meta">
                        <span>Diposting <?php echo h($vacancy['posted_at']); ?></span>
                        <span>Jumlah lowongan: <?php echo h($vacancy['vacancy_count']); ?></span>
                    </div>
                    <div class="ard-vacancy-meta">Batas waktu lamaran <?php echo h($vacancy['deadline']); ?></div>

                    <div class="ard-vacancy-attrs">
                        <div class="ard-vacancy-attr"><strong>Bidang pekerjaan :</strong> <?php echo h($vacancy['job_field']); ?></div>
                        <div class="ard-vacancy-attr"><strong>Jenis pekerjaan :</strong> <?php echo h($vacancy['job_type']); ?></div>
                        <div class="ard-vacancy-attr"><strong>Tipe pekerjaan :</strong> <?php echo h($vacancy['job_category']); ?></div>
                        <div class="ard-vacancy-attr"><strong>Jenis kelamin :</strong> <?php echo h($vacancy['gender']); ?></div>
                        <div class="ard-vacancy-attr"><strong>Rentang gaji :</strong> <?php echo h($vacancy['salary_range']); ?></div>
                    </div>

                    <div class="ard-vacancy-block">
                        <div class="ard-vacancy-block-title">Deskripsi Pekerjaan:</div>
                        <ul class="ard-vacancy-list">
                            <?php foreach ($vacancy['description'] as $item): ?>
                                <li><?php echo h($item); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="ard-vacancy-block">
                        <div class="ard-vacancy-block-title">Persyaratan Khusus:</div>
                        <ul class="ard-vacancy-list">
                            <?php foreach ($vacancy['special_requirements'] as $item): ?>
                                <li><?php echo h($item); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="ard-vacancy-block">
                        <div class="ard-vacancy-block-title">Persyaratan Umum:</div>
                        <ul class="ard-vacancy-kv">
                            <?php foreach ($vacancy['general_requirements'] as $label => $value): ?>
                                <li><strong><?php echo h($label); ?> :</strong> <?php echo h($value); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php else: ?>
                    <p class="ard-text"><?php echo h($case['snapshot']); ?></p>
                <?php endif; ?>
            </div>

            <div class="ard-section">
                <h6>Status</h6>
                <p class="ard-text"><?php echo h($case['current_data']); ?></p>
                <?php if (!empty($case['verified_by_admin'])): ?>
                    <p class="ard-text mt-2">Lowongan ini sebelumnya diverifikasi tayang oleh: <strong><?php echo h($case['verified_by_admin']); ?></strong></p>
                <?php endif; ?>
            </div>

            <?php if (!empty($case['employer_profile']) && is_array($case['employer_profile'])): ?>
                <?php $employer = $case['employer_profile']; ?>
                <div class="ard-section">
                    <h6>Profil Pemberi Kerja</h6>
                    <div class="ard-vacancy-attrs">
                        <div class="ard-vacancy-attr"><strong>Nama Pemberi Kerja:</strong> <?php echo h($employer['name']); ?></div>
                        <div class="ard-vacancy-attr"><strong>Bidang :</strong> <?php echo h($employer['field']); ?></div>
                        <div class="ard-vacancy-attr"><strong>Terdaftar sejak :</strong> <?php echo h($employer['registered_since']); ?></div>
                    </div>
                    <div class="ard-vacancy-attrs mb-0">
                        <div class="ard-vacancy-attr"><strong>Kontak perusahaan :</strong> <?php echo h($employer['contact']); ?></div>
                        <div class="ard-vacancy-attr"><strong>Email perusahaan :</strong> <?php echo h($employer['email']); ?></div>
                        <div class="ard-vacancy-attr"><strong>Website perusahaan :</strong> <?php echo h($employer['website']); ?></div>
                        <div class="ard-vacancy-attr"><strong>Lokasi alamat perusahaan :</strong> <?php echo h($employer['address']); ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="ard-section">
                <h6>Panel Histori dan Keterkaitan</h6>
                <?php
                $sameVacancyReports = $case['same_vacancy_reports'] ?? [];
                $companyOtherReports = $case['company_other_reports'] ?? [];
                $historyCount = (int)($case['history_count'] ?? count($sameVacancyReports));
                ?>
                <?php if (!empty($sameVacancyReports)): ?>
                    <button
                        class="ard-history-toggle"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#sameVacancyHistory"
                        aria-expanded="false"
                        aria-controls="sameVacancyHistory"
                    >
                        <strong>Histori:</strong> Report count lowongan ini: <?php echo h((string)$historyCount); ?>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="collapse" id="sameVacancyHistory">
                        <div class="table-responsive">
                            <table class="ard-related-table">
                                <thead>
                                    <tr>
                                        <th>Report ID</th>
                                        <th>Waktu Masuk</th>
                                        <th>Reason</th>
                                        <th>Severity</th>
                                        <th>Status</th>
                                        <th>Pelapor</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sameVacancyReports as $report): ?>
                                        <tr class="<?php echo !empty($report['is_current']) ? 'ard-related-current' : ''; ?>">
                                            <td>
                                                <?php echo h($report['report_id']); ?>
                                                <?php if (!empty($report['is_current'])): ?>
                                                    <span class="ard-badge text-bg-info ms-1">Current</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo h($report['waktu_masuk']); ?></td>
                                            <td><?php echo h($report['reason']); ?></td>
                                            <td><?php echo h($report['severity']); ?></td>
                                            <td><?php echo h($report['status']); ?></td>
                                            <td><?php echo h($report['reporter_ref']); ?></td>
                                            <td>
                                                <a
                                                    class="btn btn-sm btn-outline-primary"
                                                    href="admin_review_laporan_case_detail_prototype?type=vacancy&report_id=<?php echo rawurlencode($report['report_id']); ?>"
                                                >Lihat Detail Laporan</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="ard-text"><strong>Histori:</strong> <?php echo h($case['history'] ?? '-'); ?></p>
                <?php endif; ?>

                <div class="ard-subfield">
                    <div class="ard-subfield-title">Laporan lowongan lainnya dari Perusahaan Ini</div>
                    <?php if (!empty($companyOtherReports)): ?>
                        <div class="table-responsive">
                            <table class="ard-related-table">
                                <thead>
                                    <tr>
                                        <th>Report ID</th>
                                        <th>Waktu Masuk</th>
                                        <th>Lowongan / Objek</th>
                                        <th>Reason</th>
                                        <th>Severity</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($companyOtherReports as $report): ?>
                                        <?php
                                        $relatedType = str_starts_with($report['report_id'], 'CRP-') ? 'company' : 'vacancy';
                                        ?>
                                        <tr>
                                            <td><?php echo h($report['report_id']); ?></td>
                                            <td><?php echo h($report['waktu_masuk']); ?></td>
                                            <td><?php echo h($report['lowongan']); ?></td>
                                            <td><?php echo h($report['reason']); ?></td>
                                            <td><?php echo h($report['severity']); ?></td>
                                            <td><?php echo h($report['status']); ?></td>
                                            <td>
                                                <a
                                                    class="btn btn-sm btn-outline-primary"
                                                    href="admin_review_laporan_case_detail_prototype?type=<?php echo rawurlencode($relatedType); ?>&report_id=<?php echo rawurlencode($report['report_id']); ?>"
                                                >Lihat Detail Laporan</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="ard-text">Belum ada laporan lain dari perusahaan ini pada dataset prototype.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="modal fade" id="tindakanModal" tabindex="-1" aria-labelledby="tindakanModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="tindakanModalLabel">Panel Tindakan</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label small mb-1" for="decisionSelect">Keputusan</label>
                                    <select id="decisionSelect" class="form-select form-select-sm">
                                        <option>Tidak Terbukti</option>
                                        <option>Valid</option>
                                        <option>Warning</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small mb-2">Aksi</label>
                                    <div class="ard-aksi-list" id="aksiCheckboxList">
                                        <label class="ard-aksi-item" data-aksi="Tidak ada">
                                            <input class="form-check-input m-0 js-aksi-check" type="checkbox" value="Tidak ada">
                                            <span>Tidak ada</span>
                                        </label>
                                        <label class="ard-aksi-item" data-aksi="Tangguhkan Lowongan">
                                            <input class="form-check-input m-0 js-aksi-check" type="checkbox" value="Tangguhkan Lowongan">
                                            <span>Tangguhkan Lowongan</span>
                                        </label>
                                        <label class="ard-aksi-item" data-aksi="Blokir Lowongan">
                                            <input class="form-check-input m-0 js-aksi-check" type="checkbox" value="Blokir Lowongan">
                                            <span>Blokir Lowongan</span>
                                        </label>
                                        <label class="ard-aksi-item" data-aksi="Blokir Akun Pemberi Kerja">
                                            <input class="form-check-input m-0 js-aksi-check" type="checkbox" value="Blokir Akun Pemberi Kerja">
                                            <span>Blokir Akun Pemberi Kerja</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div id="decisionFeedback" class="ard-feedback"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                            <button id="saveDecisionBtn" class="btn btn-primary btn-sm" type="button">Simpan Tindakan</button>
                        </div>
                    </div>
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
        const aksiChecks = Array.prototype.slice.call(document.querySelectorAll('.js-aksi-check'));
        const saveBtn = document.getElementById('saveDecisionBtn');
        const feedback = document.getElementById('decisionFeedback');
        const successAlert = document.getElementById('tindakanSuccessAlert');
        const tindakanModalEl = document.getElementById('tindakanModal');
        const requestEmployerBtn = document.getElementById('requestEmployerClarificationBtn');

        if (!statusBadge || !decisionSelect || aksiChecks.length === 0 || !saveBtn || !feedback || !successAlert || !tindakanModalEl) {
            return;
        }

        const tindakanModal = bootstrap.Modal.getOrCreateInstance(tindakanModalEl);

        const aksiRules = {
            'Tidak Terbukti': {
                enabled: ['Tidak ada'],
                checked: ['Tidak ada'],
                locked: true
            },
            'Valid': {
                enabled: ['Blokir Lowongan', 'Blokir Akun Pemberi Kerja'],
                checked: ['Blokir Lowongan', 'Blokir Akun Pemberi Kerja'],
                locked: true
            },
            'Warning': {
                enabled: ['Tangguhkan Lowongan', 'Blokir Lowongan'],
                checked: [],
                locked: false
            }
        };

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
            if (status === 'Valid' || status === 'CLOSED' || status === 'Tidak Terbukti' || status === 'Warning') {
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

        function hideFeedback() {
            feedback.style.display = 'none';
            feedback.textContent = '';
        }

        function getSelectedAksi() {
            return aksiChecks
                .filter(function (input) { return input.checked && !input.disabled; })
                .map(function (input) { return input.value; });
        }

        function syncAksiByDecision() {
            const decision = decisionSelect.value;
            const rule = aksiRules[decision] || { enabled: [], checked: [], locked: false };

            aksiChecks.forEach(function (input) {
                const enabled = rule.enabled.indexOf(input.value) !== -1;
                const shouldCheck = rule.checked.indexOf(input.value) !== -1;
                input.disabled = !enabled;
                input.checked = shouldCheck;
                const item = input.closest('.ard-aksi-item');
                if (item) {
                    item.classList.toggle('is-disabled', !enabled);
                }
            });
        }

        decisionSelect.addEventListener('change', function () {
            hideFeedback();
            syncAksiByDecision();
        });

        aksiChecks.forEach(function (input) {
            input.addEventListener('change', function () {
                const decision = decisionSelect.value;
                const rule = aksiRules[decision];
                if (!rule || rule.locked) {
                    syncAksiByDecision();
                }
            });
        });

        syncAksiByDecision();

        saveBtn.addEventListener('click', function () {
            const decision = decisionSelect.value;
            const selectedAksi = getSelectedAksi();

            if (decision === 'Warning' && selectedAksi.length === 0) {
                showFeedback('Pilih minimal satu Aksi untuk Warning.', true);
                return;
            }

            if (selectedAksi.length === 0) {
                showFeedback('Pilih Aksi terlebih dahulu.', true);
                return;
            }

            hideFeedback();
            setStatusBadge(decision);
            statusBadge.textContent = 'Ditutup';
            statusBadge.classList.remove('text-bg-primary', 'text-bg-warning', 'text-bg-info', 'text-bg-secondary');
            statusBadge.classList.add('text-bg-success');

            successAlert.textContent = 'Tindakan atas Pelaporan Ini telah berhasil disimpan, Case ditutup dengan Aksi ' + selectedAksi.join(', ');
            successAlert.classList.remove('d-none');
            tindakanModal.hide();
        });

        tindakanModalEl.addEventListener('shown.bs.modal', function () {
            hideFeedback();
            syncAksiByDecision();
        });

        tindakanModalEl.addEventListener('hidden.bs.modal', function () {
            hideFeedback();
        });

        if (requestEmployerBtn) {
            requestEmployerBtn.addEventListener('click', function () {
                statusBadge.textContent = 'Menunggu Klarifikasi';
                statusBadge.classList.remove('text-bg-primary', 'text-bg-info', 'text-bg-success', 'text-bg-secondary');
                statusBadge.classList.add('text-bg-warning');
                successAlert.textContent = 'Permintaan klarifikasi kepada pemberi kerja telah disiapkan (prototype).';
                successAlert.classList.remove('d-none');
            });
        }
    })();
</script>
</body>
</html>
