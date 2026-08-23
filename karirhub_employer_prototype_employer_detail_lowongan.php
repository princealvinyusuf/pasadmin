<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_ui.php';

if (!kh_proto_can_access('karirhub_employer_prototype_employer_detail_lowongan_view')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$job = [
    'title' => 'Sales Executive - DKI Jakarta',
    'status' => 'Dibuka',
    'posted_at' => '19 Agt 2026',
    'expired_at' => '19 Nov 2026',
    'education' => 'Sarjana',
    'location' => 'Ciracas, Kota Jakarta Timur, DKI Jakarta, Indonesia',
    'quota_filled' => 0,
    'quota_total' => 1,
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Employer - Detail Lowongan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php kh_proto_render_styles(); ?>
    <style>
        body.kh-proto-page { background: #f7f9fc; }
        .edl-shell { background: #fff; border: 1px solid #e6edf5; border-radius: 14px; padding: 24px; }
        .edl-back { color: #5f738a; text-decoration: none; font-size: 14px; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 18px; }
        .edl-back:hover { color: #1f3550; }
        .edl-layout { display: grid; grid-template-columns: minmax(0, 1fr) 280px; gap: 28px; align-items: start; }
        .edl-title-row { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 14px; }
        .edl-title { margin: 0; font-size: 34px; font-weight: 700; color: #1a1a1a; line-height: 1.2; }
        .edl-status { display: inline-flex; align-items: center; border-radius: 999px; background: #eef1f4; color: #5b6775; font-size: 12px; font-weight: 600; padding: 4px 10px; }
        .edl-meta { display: flex; flex-direction: column; gap: 10px; }
        .edl-meta-item { display: flex; align-items: flex-start; gap: 10px; color: #4b5563; font-size: 15px; line-height: 1.45; }
        .edl-meta-item i { color: #8a97a8; margin-top: 2px; }
        .edl-quota-card { background: #f3f5f7; border-radius: 12px; padding: 16px; }
        .edl-quota-label { color: #6b7280; font-size: 14px; margin-bottom: 10px; }
        .edl-quota-bar { height: 8px; border-radius: 999px; background: #d7dde5; overflow: hidden; margin-bottom: 10px; }
        .edl-quota-fill { height: 100%; width: 0%; background: #94a3b8; border-radius: 999px; }
        .edl-quota-text { color: #4b5563; font-size: 14px; }
        .edl-reopen-btn { width: 100%; margin-top: 12px; background: #4ea3f0; border-color: #4ea3f0; color: #fff; font-weight: 600; border-radius: 10px; padding: 10px 14px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .edl-reopen-btn:hover { background: #3b93e3; border-color: #3b93e3; color: #fff; }
        .edl-action-stack { display: flex; flex-direction: column; gap: 10px; margin-top: 12px; }
        .edl-followup-btn { width: 100%; background: #fff; border: 1px solid #4ea3f0; color: #2f7fc7; font-weight: 600; border-radius: 10px; padding: 10px 14px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .edl-followup-btn:hover { background: #f2f8ff; border-color: #3b93e3; color: #226aa8; }
        .edl-feedback { margin-top: 14px; display: none; }
        .edl-form-label { color: #304a64; font-weight: 600; font-size: 14px; margin-bottom: 6px; }
        .edl-required { color: #c0342a; }
        .edl-form-note { color: #7f92a7; font-size: 12px; margin-top: 4px; }
        .edl-modal-error { color: #c0342a; font-size: 12px; margin-top: 4px; display: none; }
        @media (max-width: 991px) {
            .edl-layout { grid-template-columns: 1fr; }
            .edl-title { font-size: 28px; }
        }
    </style>
</head>
<body class="kh-proto-page">
<?php include 'navbar.php'; ?>

<div class="kh-content-wrap">
    <div class="container py-4">
        <div class="edl-shell">
            <a class="edl-back" href="javascript:history.back()"><i class="bi bi-arrow-left"></i>Kembali</a>

            <div class="edl-layout">
                <div>
                    <div class="edl-title-row">
                        <h1 class="edl-title"><?php echo h($job['title']); ?></h1>
                        <span class="edl-status" id="jobStatusBadge"><?php echo h($job['status']); ?></span>
                    </div>
                    <div class="edl-meta">
                        <div class="edl-meta-item">
                            <i class="bi bi-calendar3"></i>
                            <span>Ditayangkan: <?php echo h($job['posted_at']); ?> — Kadaluarsa: <?php echo h($job['expired_at']); ?></span>
                        </div>
                        <div class="edl-meta-item">
                            <i class="bi bi-mortarboard"></i>
                            <span>Pendidikan Minimal: <?php echo h($job['education']); ?></span>
                        </div>
                        <div class="edl-meta-item">
                            <i class="bi bi-geo-alt"></i>
                            <span>Lokasi: <?php echo h($job['location']); ?></span>
                        </div>
                    </div>
                    <div id="reopenFeedback" class="alert alert-success edl-feedback" role="alert"></div>
                </div>

                <div>
                    <div class="edl-quota-card">
                        <div class="edl-quota-label">Kuota</div>
                        <div class="edl-quota-bar">
                            <div
                                class="edl-quota-fill"
                                style="width: <?php echo (int)$job['quota_total'] > 0 ? round(((int)$job['quota_filled'] / (int)$job['quota_total']) * 100) : 0; ?>%;"
                            ></div>
                        </div>
                        <div class="edl-quota-text">
                            <?php echo (int)$job['quota_filled']; ?> / <?php echo (int)$job['quota_total']; ?> kuota telah terisi
                        </div>
                    </div>
                    <div class="edl-action-stack">
                        <button type="button" class="btn edl-reopen-btn" id="reopenJobBtn">
                            <i class="bi bi-arrow-repeat"></i>
                            Buka Kembali Lowongan
                        </button>
                        <button type="button" class="btn edl-followup-btn" data-bs-toggle="modal" data-bs-target="#tindakLanjutAduanModal">
                            <i class="bi bi-chat-dots"></i>
                            Tindak Lanjut Aduan
                        </button>
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
                    <label class="edl-form-label" for="pesanAdminField">Pesan Admin</label>
                    <textarea
                        id="pesanAdminField"
                        class="form-control"
                        rows="3"
                        readonly
                    >Terdapat aduan atas lowongan yang dibuka ini, mohon untuk memberikan klarifikasi</textarea>
                </div>
                <div class="mb-3">
                    <label class="edl-form-label" for="tanggapanField">Masukkan Tanggapan <span class="edl-required">*</span></label>
                    <textarea
                        id="tanggapanField"
                        class="form-control"
                        rows="4"
                        placeholder="Tuliskan tanggapan Anda..."
                    ></textarea>
                    <div id="tanggapanError" class="edl-modal-error">Masukkan tanggapan terlebih dahulu.</div>
                </div>
                <div class="mb-0">
                    <label class="edl-form-label" for="buktiField">Tambahkan bukti <span class="edl-required">*</span></label>
                    <input id="buktiField" type="file" class="form-control" accept=".pdf,image/*">
                    <div class="edl-form-note">Tipe file contoh: PDF, JPG, PNG.</div>
                    <div id="buktiError" class="edl-modal-error">Tambahkan bukti terlebih dahulu.</div>
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
        const reopenBtn = document.getElementById('reopenJobBtn');
        const statusBadge = document.getElementById('jobStatusBadge');
        const feedback = document.getElementById('reopenFeedback');
        const modalEl = document.getElementById('tindakLanjutAduanModal');
        const tanggapanField = document.getElementById('tanggapanField');
        const buktiField = document.getElementById('buktiField');
        const tanggapanError = document.getElementById('tanggapanError');
        const buktiError = document.getElementById('buktiError');
        const submitBtn = document.getElementById('submitTindakLanjutBtn');

        if (!reopenBtn || !statusBadge || !feedback || !modalEl || !tanggapanField || !buktiField || !submitBtn) return;

        const tindakLanjutModal = bootstrap.Modal.getOrCreateInstance(modalEl);

        function hideErrors() {
            if (tanggapanError) tanggapanError.style.display = 'none';
            if (buktiError) buktiError.style.display = 'none';
        }

        reopenBtn.addEventListener('click', function () {
            statusBadge.textContent = 'Aktif';
            statusBadge.style.background = '#e7f8ed';
            statusBadge.style.color = '#1d7a3d';
            feedback.textContent = 'Lowongan berhasil dibuka kembali (prototype).';
            feedback.style.display = 'block';
            reopenBtn.disabled = true;
            reopenBtn.textContent = 'Lowongan Sudah Dibuka';
        });

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

            feedback.textContent = 'Tanggapan aduan berhasil dikirim (prototype).';
            feedback.style.display = 'block';
            tindakLanjutModal.hide();
        });
    })();
</script>
<?php kh_proto_render_sidebar_script(); ?>
</body>
</html>
