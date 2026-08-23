<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_ui.php';

if (!kh_proto_can_access('karirhub_employer_prototype_employer_profil_pemberi_kerja_view')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$employer = [
    'type_label' => 'PERUSAHAAN',
    'name' => 'PT Finaccel Finance Indonesia',
    'email' => 'hr@finaccel.co.id',
    'phone' => '0211500987',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Employer - Profil Pemberi Kerja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php kh_proto_render_styles(); ?>
    <style>
        body.kh-proto-page { background: #f7f9fc; }
        .epp-shell { background: #fff; border: 1px solid #e6edf5; border-radius: 14px; padding: 28px 28px 32px; }
        .epp-title { margin: 0 0 20px; font-size: 28px; font-weight: 700; color: #1f2937; line-height: 1.25; }
        .epp-card {
            background: #eaf7ee;
            border-radius: 16px;
            padding: 20px 22px 22px;
        }
        .epp-verified {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 18px;
        }
        .epp-verified-icon {
            width: 28px;
            height: 28px;
            border-radius: 999px;
            border: 2px solid #22a06b;
            color: #22a06b;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 2px;
            font-size: 14px;
            font-weight: 700;
        }
        .epp-verified-title {
            margin: 0 0 4px;
            color: #1f7a4d;
            font-size: 16px;
            font-weight: 700;
            line-height: 1.3;
        }
        .epp-verified-text {
            margin: 0;
            color: #2f6b4f;
            font-size: 14px;
            line-height: 1.45;
        }
        .epp-body {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
        }
        .epp-profile {
            display: flex;
            align-items: center;
            gap: 16px;
            min-width: 0;
        }
        .epp-logo {
            width: 72px;
            height: 72px;
            border-radius: 12px;
            background: #fff;
            border: 1px solid #d9e4ef;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: #3b82f6;
            font-size: 30px;
        }
        .epp-type {
            margin: 0 0 4px;
            color: #6b8799;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .epp-name {
            margin: 0 0 10px;
            color: #111827;
            font-size: 22px;
            font-weight: 700;
            line-height: 1.25;
        }
        .epp-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .epp-pill {
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
        .epp-pill i { color: #6b7280; font-size: 14px; }
        .epp-actions {
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
        }
        .epp-followup-btn {
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
        .epp-followup-btn:hover {
            background: #f2f8ff;
            border-color: #3b93e3;
            color: #226aa8;
        }
        .epp-feedback {
            display: none;
            margin: 0;
            color: #1d7a3d;
            font-size: 13px;
            text-align: right;
        }
        .epp-form-label { color: #304a64; font-weight: 600; font-size: 14px; margin-bottom: 6px; }
        .epp-required { color: #c0342a; }
        .epp-form-note { color: #7f92a7; font-size: 12px; margin-top: 4px; }
        .epp-modal-error { color: #c0342a; font-size: 12px; margin-top: 4px; display: none; }
        @media (max-width: 767px) {
            .epp-shell { padding: 20px 16px 24px; }
            .epp-title { font-size: 24px; }
            .epp-body {
                flex-direction: column;
                align-items: stretch;
            }
            .epp-profile { align-items: flex-start; }
            .epp-name { font-size: 20px; }
            .epp-actions { align-items: stretch; }
            .epp-followup-btn { width: 100%; }
            .epp-feedback { text-align: left; }
        }
    </style>
</head>
<body class="kh-proto-page">
<?php include 'navbar.php'; ?>

<div class="kh-content-wrap">
    <div class="container py-4">
        <div class="epp-shell">
            <h1 class="epp-title">Profil Pemberi Kerja</h1>

            <div class="epp-card">
                <div class="epp-verified">
                    <span class="epp-verified-icon" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                    <div>
                        <h2 class="epp-verified-title">Pemberi Kerja Terverifikasi</h2>
                        <p class="epp-verified-text">Selamat! Pemberi kerja Anda telah berhasil diverifikasi dan kini dapat menggunakan seluruh fitur yang tersedia.</p>
                    </div>
                </div>

                <div class="epp-body">
                    <div class="epp-profile">
                        <div class="epp-logo" aria-hidden="true">
                            <i class="bi bi-buildings"></i>
                        </div>
                        <div>
                            <p class="epp-type"><?php echo h($employer['type_label']); ?></p>
                            <h3 class="epp-name"><?php echo h($employer['name']); ?></h3>
                            <div class="epp-pills">
                                <span class="epp-pill"><i class="bi bi-envelope"></i><?php echo h($employer['email']); ?></span>
                                <span class="epp-pill"><i class="bi bi-telephone"></i><?php echo h($employer['phone']); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="epp-actions">
                        <button type="button" class="btn epp-followup-btn" data-bs-toggle="modal" data-bs-target="#tindakLanjutAduanModal">
                            <i class="bi bi-chat-dots"></i>
                            Tindak Lanjut Aduan
                        </button>
                        <p class="epp-feedback" id="tindakLanjutFeedback"></p>
                    </div>
                </div>
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
                    <label class="epp-form-label" for="pesanAdminField">Pesan Admin</label>
                    <textarea
                        id="pesanAdminField"
                        class="form-control"
                        rows="3"
                        readonly
                    >Terdapat aduan atas perusahaan ini, mohon untuk memberikan klarifikasi</textarea>
                </div>
                <div class="mb-3">
                    <label class="epp-form-label" for="tanggapanField">Masukkan Tanggapan <span class="epp-required">*</span></label>
                    <textarea
                        id="tanggapanField"
                        class="form-control"
                        rows="4"
                        placeholder="Tuliskan tanggapan Anda..."
                    ></textarea>
                    <div id="tanggapanError" class="epp-modal-error">Masukkan tanggapan terlebih dahulu.</div>
                </div>
                <div class="mb-0">
                    <label class="epp-form-label" for="buktiField">Tambahkan bukti <span class="epp-required">*</span></label>
                    <input id="buktiField" type="file" class="form-control" accept=".pdf,image/*">
                    <div class="epp-form-note">Tipe file contoh: PDF, JPG, PNG.</div>
                    <div id="buktiError" class="epp-modal-error">Tambahkan bukti terlebih dahulu.</div>
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
</body>
</html>
