<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_ui.php';

if (!kh_proto_can_access('karirhub_employer_prototype_employer_individu_view')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$employer = [
    'type_label' => 'INDIVIDU',
    'name' => 'Andi Pratama',
    'email' => 'andi.pratama@email.com',
    'phone' => '081234567890',
    'nik_masked' => '3171 **** **** 0003',
    'address' => 'Jl. Melati No. 18, Ciracas, Kota Jakarta Timur, DKI Jakarta',
];

$jobs = [
    [
        'title' => 'Asisten Rumah Tangga',
        'status' => 'Dibuka',
        'location' => 'Ciracas, Jakarta Timur',
        'posted_at' => '20 Agt 2026',
        'quota' => '0 / 1',
    ],
    [
        'title' => 'Pengemudi Pribadi',
        'status' => 'Ditinjau',
        'location' => 'Ciracas, Jakarta Timur',
        'posted_at' => '12 Agt 2026',
        'quota' => '0 / 1',
    ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karirhub Employer Prototype - Employer Individu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php kh_proto_render_styles(); ?>
    <style>
        body.kh-proto-page { background: #f7f9fc; }
        .ei-note { background: #f1f8ff; color: #2f5c87; border: 1px solid #d6e8fb; border-radius: 10px; padding: 10px 12px; font-size: 13px; margin-bottom: 14px; }
        .ei-shell { background: #fff; border: 1px solid #e6edf5; border-radius: 14px; padding: 28px 28px 32px; }
        .ei-title { margin: 0 0 20px; font-size: 28px; font-weight: 700; color: #1f2937; line-height: 1.25; }
        .ei-card {
            background: #eef6ff;
            border-radius: 16px;
            padding: 20px 22px 22px;
            margin-bottom: 24px;
        }
        .ei-verified {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 18px;
        }
        .ei-verified-icon {
            width: 28px;
            height: 28px;
            border-radius: 999px;
            border: 2px solid #2f7fc7;
            color: #2f7fc7;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 2px;
            font-size: 14px;
            font-weight: 700;
        }
        .ei-verified-title {
            margin: 0 0 4px;
            color: #1d5f9a;
            font-size: 16px;
            font-weight: 700;
            line-height: 1.3;
        }
        .ei-verified-text {
            margin: 0;
            color: #2f5c87;
            font-size: 14px;
            line-height: 1.45;
        }
        .ei-body {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
        }
        .ei-profile {
            display: flex;
            align-items: center;
            gap: 16px;
            min-width: 0;
        }
        .ei-avatar {
            width: 72px;
            height: 72px;
            border-radius: 999px;
            background: #fff;
            border: 1px solid #d9e4ef;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: #3b82f6;
            font-size: 30px;
        }
        .ei-type {
            margin: 0 0 4px;
            color: #6b8799;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .ei-name {
            margin: 0 0 10px;
            color: #111827;
            font-size: 22px;
            font-weight: 700;
            line-height: 1.25;
        }
        .ei-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .ei-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #fff;
            border: 1px solid #d7e2ee;
            border-radius: 999px;
            padding: 7px 14px;
            color: #4b5563;
            font-size: 13px;
            line-height: 1.2;
        }
        .ei-pill i { color: #6b7280; font-size: 14px; }
        .ei-actions {
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
        }
        .ei-followup-btn {
            background: #fff;
            border: 1px solid #4ea3f0;
            color: #2f7fc7;
            font-weight: 600;
            border-radius: 10px;
            padding: 10px 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            white-space: nowrap;
        }
        .ei-followup-btn:hover {
            background: #f2f8ff;
            border-color: #3b93e3;
            color: #226aa8;
        }
        .ei-feedback {
            display: none;
            margin: 0;
            color: #1d7a3d;
            font-size: 13px;
            text-align: right;
        }
        .ei-section-title {
            margin: 0 0 12px;
            color: #1f3550;
            font-size: 20px;
            font-weight: 700;
        }
        .ei-table thead th {
            background: #f5f9fd;
            color: #324a63;
            font-weight: 600;
            white-space: nowrap;
        }
        .ei-table td {
            vertical-align: middle;
            color: #2b455f;
            font-size: 14px;
        }
        .ei-status {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            background: #eef1f4;
            color: #5b6775;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 10px;
        }
        .ei-status.open { background: #e7f8ed; color: #1d7a3d; }
        .ei-status.review { background: #fff4dd; color: #8f6319; }
        .ei-form-label { color: #304a64; font-weight: 600; font-size: 14px; margin-bottom: 6px; }
        .ei-required { color: #c0342a; }
        .ei-form-note { color: #7f92a7; font-size: 12px; margin-top: 4px; }
        .ei-modal-error { color: #c0342a; font-size: 12px; margin-top: 4px; display: none; }
        @media (max-width: 767px) {
            .ei-shell { padding: 20px 16px 24px; }
            .ei-title { font-size: 24px; }
            .ei-body {
                flex-direction: column;
                align-items: stretch;
            }
            .ei-profile { align-items: flex-start; }
            .ei-name { font-size: 20px; }
            .ei-actions { align-items: stretch; }
            .ei-followup-btn { width: 100%; }
            .ei-feedback { text-align: left; }
        }
    </style>
</head>
<body class="kh-proto-page">
<?php include 'navbar.php'; ?>

<div class="kh-content-wrap">
    <div class="container py-4">
        <div class="ei-note">
            Halaman ini adalah prototype UI Employer Individu. Data pemberi kerja dan lowongan masih berupa simulasi.
        </div>
        <div class="ei-shell">
            <h1 class="ei-title">Dashboard Employer Individu</h1>

            <div class="ei-card">
                <div class="ei-verified">
                    <span class="ei-verified-icon" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                    <div>
                        <h2 class="ei-verified-title">Pemberi Kerja Individu Terverifikasi</h2>
                        <p class="ei-verified-text">Akun individu ini telah diverifikasi dan dapat memposting lowongan serta merespons aduan terkait lowongan yang dibuka.</p>
                    </div>
                </div>

                <div class="ei-body">
                    <div class="ei-profile">
                        <div class="ei-avatar" aria-hidden="true">
                            <i class="bi bi-person"></i>
                        </div>
                        <div>
                            <p class="ei-type"><?php echo h($employer['type_label']); ?></p>
                            <h3 class="ei-name"><?php echo h($employer['name']); ?></h3>
                            <div class="ei-pills">
                                <span class="ei-pill"><i class="bi bi-envelope"></i><?php echo h($employer['email']); ?></span>
                                <span class="ei-pill"><i class="bi bi-telephone"></i><?php echo h($employer['phone']); ?></span>
                                <span class="ei-pill"><i class="bi bi-person-vcard"></i><?php echo h($employer['nik_masked']); ?></span>
                                <span class="ei-pill"><i class="bi bi-geo-alt"></i><?php echo h($employer['address']); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="ei-actions">
                        <button type="button" class="btn ei-followup-btn" data-bs-toggle="modal" data-bs-target="#tindakLanjutAduanModal">
                            <i class="bi bi-chat-dots"></i>
                            Tindak Lanjut Aduan
                        </button>
                        <p class="ei-feedback" id="tindakLanjutFeedback"></p>
                    </div>
                </div>
            </div>

            <h2 class="ei-section-title">Lowongan yang Dibuka</h2>
            <div class="table-responsive">
                <table class="table ei-table align-middle">
                    <thead>
                        <tr>
                            <th>Lowongan</th>
                            <th>Lokasi</th>
                            <th>Ditayangkan</th>
                            <th>Kuota</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $job): ?>
                            <?php $statusClass = $job['status'] === 'Dibuka' ? 'open' : 'review'; ?>
                            <tr>
                                <td>
                                    <a href="karirhub_employer_prototype_employer_detail_lowongan"><?php echo h($job['title']); ?></a>
                                </td>
                                <td><?php echo h($job['location']); ?></td>
                                <td><?php echo h($job['posted_at']); ?></td>
                                <td><?php echo h($job['quota']); ?></td>
                                <td><span class="ei-status <?php echo h($statusClass); ?>"><?php echo h($job['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="tindakLanjutAduanModal" tabindex="-1" aria-labelledby="tindakLanjutAduanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tindakLanjutAduanModalLabel">Tindak Lanjut Aduan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="ei-form-label" for="pesanAdminField">Pesan Admin</label>
                    <textarea
                        id="pesanAdminField"
                        class="form-control"
                        rows="3"
                        readonly
                    >Terdapat aduan atas pemberi kerja individu ini, mohon untuk memberikan klarifikasi</textarea>
                </div>
                <div class="mb-3">
                    <label class="ei-form-label" for="tanggapanField">Masukkan Tanggapan <span class="ei-required">*</span></label>
                    <textarea
                        id="tanggapanField"
                        class="form-control"
                        rows="4"
                        placeholder="Tuliskan tanggapan Anda..."
                    ></textarea>
                    <div id="tanggapanError" class="ei-modal-error">Masukkan tanggapan terlebih dahulu.</div>
                </div>
                <div class="mb-0">
                    <label class="ei-form-label" for="buktiField">Tambahkan bukti <span class="ei-required">*</span></label>
                    <input id="buktiField" type="file" class="form-control" accept=".pdf,image/*">
                    <div class="ei-form-note">Tipe file contoh: PDF, JPG, PNG.</div>
                    <div id="buktiError" class="ei-modal-error">Tambahkan bukti terlebih dahulu.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary btn-sm" id="submitTindakLanjutBtn">Kirim Tanggapan</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        const modalEl = document.getElementById('tindakLanjutAduanModal');
        const tanggapanField = document.getElementById('tanggapanField');
        const buktiField = document.getElementById('buktiField');
        const tanggapanError = document.getElementById('tanggapanError');
        const buktiError = document.getElementById('buktiError');
        const submitBtn = document.getElementById('submitTindakLanjutBtn');
        const feedback = document.getElementById('tindakLanjutFeedback');

        if (!modalEl || !tanggapanField || !buktiField || !submitBtn) return;

        const tindakLanjutModal = bootstrap.Modal.getOrCreateInstance(modalEl);

        function hideErrors() {
            if (tanggapanError) tanggapanError.style.display = 'none';
            if (buktiError) buktiError.style.display = 'none';
        }

        modalEl.addEventListener('hidden.bs.modal', function () {
            tanggapanField.value = '';
            buktiField.value = '';
            hideErrors();
        });

        submitBtn.addEventListener('click', function () {
            hideErrors();
            let hasError = false;
            if (tanggapanField.value.trim() === '') {
                hasError = true;
                if (tanggapanError) tanggapanError.style.display = 'block';
            }
            if (!(buktiField.files && buktiField.files.length > 0)) {
                hasError = true;
                if (buktiError) buktiError.style.display = 'block';
            }
            if (hasError) return;

            if (feedback) {
                feedback.textContent = 'Tanggapan aduan berhasil dikirim (prototype).';
                feedback.style.display = 'block';
            }
            tindakLanjutModal.hide();
        });
    })();
</script>
<?php kh_proto_render_sidebar_script(); ?>
</body>
</html>
