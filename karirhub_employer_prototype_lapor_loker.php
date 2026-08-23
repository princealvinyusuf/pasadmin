<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_ui.php';

if (!kh_proto_can_access('karirhub_employer_prototype_lapor_loker_view')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karirhub Employer Prototype - Lapor Loker Prototype</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php kh_proto_render_styles(); ?>
    <style>
        .ll-proto-wrap { background: #fff; border: 1px solid #dfe8f3; border-radius: 14px; padding: 24px; }
        .ll-title { color: #1f3550; font-size: 34px; font-weight: 700; line-height: 1.2; margin-bottom: 8px; }
        .ll-meta-list { display: flex; flex-wrap: wrap; gap: 20px; color: #5f748b; font-size: 15px; margin-bottom: 10px; }
        .ll-meta-item { display: inline-flex; align-items: center; gap: 8px; }
        .ll-meta-item i { color: #7f94a9; }
        .ll-share { display: flex; align-items: center; gap: 10px; color: #556b83; margin-top: 8px; font-size: 14px; }
        .ll-share-link { width: 28px; height: 28px; border-radius: 999px; border: 1px solid #d7e2ef; color: #4d6278; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }
        .ll-share-link:hover { background: #f1f7ff; color: #0a7f96; border-color: #bfdaee; }
        .ll-apply-btn { min-width: 220px; background: #2e8fe8; border-color: #2e8fe8; font-weight: 700; border-radius: 10px; padding: 12px 24px; }
        .ll-apply-btn:hover { background: #1e7fd8; border-color: #1e7fd8; }
        .ll-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px 28px; margin-top: 26px; padding-top: 20px; border-top: 1px solid #edf2f8; }
        .ll-grid-label { font-size: 14px; color: #8c9db0; margin-bottom: 4px; }
        .ll-grid-value { font-size: 17px; color: #233d58; font-weight: 600; }
        .ll-company-card { border: 1px solid #e1eaf4; border-radius: 12px; padding: 18px; background: #fbfdff; }
        .ll-company-name { font-size: 24px; font-weight: 700; color: #223b55; margin-bottom: 8px; }
        .ll-company-address { color: #5f7489; font-size: 15px; line-height: 1.5; margin-bottom: 12px; }
        .ll-company-badge { display: inline-block; font-size: 12px; padding: 6px 10px; border-radius: 999px; background: #ecf3fb; color: #4f6982; margin-bottom: 12px; }
        .ll-company-link { display: inline-flex; align-items: center; gap: 6px; text-decoration: none; font-weight: 600; }
        .ll-description { margin-top: 28px; border-top: 1px solid #edf2f8; padding-top: 24px; }
        .ll-description-title { color: #1f3550; font-size: 32px; font-weight: 700; margin-bottom: 14px; }
        .ll-list-title { color: #304a64; font-weight: 700; margin-bottom: 10px; }
        .ll-list { color: #4f647a; font-size: 16px; line-height: 1.6; padding-left: 20px; }
        .ll-report-wrap { margin-top: 28px; border-top: 1px solid #edf2f8; padding-top: 24px; }
        .ll-report-toggle { width: 100%; border: 1px solid #d7e3f0; border-radius: 10px; background: #f8fbff; padding: 14px 16px; color: #1f3550; font-size: 21px; font-weight: 700; display: flex; align-items: center; justify-content: space-between; text-align: left; }
        .ll-report-toggle:hover { background: #f2f8ff; }
        .ll-report-toggle i { font-size: 16px; color: #56718d; transition: transform .2s ease; }
        .ll-report-toggle[aria-expanded="true"] i { transform: rotate(180deg); }
        .ll-report-card { border: 1px solid #dce7f2; border-top: 0; border-radius: 0 0 12px 12px; padding: 18px; background: #fff; }
        .ll-safe-box { border: 1px solid #ffe4a6; background: #fff9eb; color: #775d21; border-radius: 8px; padding: 10px 12px; font-size: 14px; margin-bottom: 16px; }
        .ll-form-label { color: #304a64; font-weight: 600; font-size: 14px; margin-bottom: 6px; }
        .ll-form-note { color: #7f92a7; font-size: 12px; margin-top: 4px; }
        .ll-report-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-top: 16px; }
        .ll-report-primary { background: #0a8f8a; border-color: #0a8f8a; font-weight: 600; }
        .ll-report-primary:hover { background: #087a76; border-color: #087a76; }
        .ll-report-cancel { color: #4f667d; text-decoration: none; font-weight: 600; }
        .ll-report-cancel:hover { color: #23415f; text-decoration: underline; }
        .ll-error-text { color: #c0342a; font-size: 12px; margin-top: 4px; display: none; }
        .ll-success-card { border: 1px solid #cae8d8; background: #f4fff8; border-radius: 10px; padding: 16px; }
        .ll-success-title { color: #1f5f43; font-size: 22px; font-weight: 700; margin-bottom: 10px; }
        .ll-success-meta { color: #305345; font-size: 15px; margin-bottom: 8px; }
        .ll-success-meta strong { color: #1f3550; }
        .ll-success-note { border: 1px solid #dce8f7; background: #f6f9ff; color: #355271; border-radius: 8px; padding: 10px 12px; font-size: 14px; margin: 10px 0; }
        .ll-success-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 14px; }
        .ll-proto-note { background: #f1f8ff; color: #2f5c87; border: 1px solid #d6e8fb; border-radius: 10px; padding: 10px 12px; font-size: 13px; margin-bottom: 14px; }
        @media (max-width: 1199px) {
            .ll-title { font-size: 30px; }
            .ll-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .ll-company-name { font-size: 20px; }
            .ll-description-title { font-size: 28px; }
        }
        @media (max-width: 767px) {
            .ll-proto-wrap { padding: 18px; }
            .ll-title { font-size: 24px; }
            .ll-meta-list { gap: 12px; font-size: 14px; }
            .ll-grid { grid-template-columns: 1fr; }
            .ll-description-title { font-size: 24px; }
        }
    </style>
</head>
<body class="kh-proto-page">
<?php include 'navbar.php'; ?>

<div class="kh-content-wrap">
    <div class="container py-4">
        <div class="ll-proto-note">
            Halaman ini adalah prototype UI. Konten lowongan dan perusahaan masih berupa data simulasi.
        </div>
        <div class="ll-proto-wrap">
            <div class="row g-4 align-items-start">
                <div class="col-12 col-xl-8">
                    <h1 class="ll-title"><?php echo h('Sales Executive - DKI Jakarta'); ?></h1>
                    <div class="ll-meta-list">
                        <div class="ll-meta-item"><i class="bi bi-geo-alt-fill"></i><span>Ciracas, Kota Jakarta Timur, DKI Jakarta, Indonesia</span></div>
                        <div class="ll-meta-item"><i class="bi bi-calendar-check"></i><span>Diposting sekitar 1 jam yang lalu</span></div>
                        <div class="ll-meta-item"><i class="bi bi-briefcase-fill"></i><span>Jumlah lowongan: 2</span></div>
                        <div class="ll-meta-item"><i class="bi bi-alarm"></i><span>Batas waktu lamaran 12 Sep 2026</span></div>
                    </div>
                    <div class="ll-share">
                        <span>Bagikan:</span>
                        <a class="ll-share-link" href="#" aria-label="Bagikan ke WhatsApp"><i class="bi bi-whatsapp"></i></a>
                        <a class="ll-share-link" href="#" aria-label="Bagikan ke LinkedIn"><i class="bi bi-linkedin"></i></a>
                        <a class="ll-share-link" href="#" aria-label="Bagikan ke X"><i class="bi bi-twitter-x"></i></a>
                        <a class="ll-share-link" href="#" aria-label="Bagikan ke Facebook"><i class="bi bi-facebook"></i></a>
                        <a class="ll-share-link" href="#" aria-label="Salin tautan"><i class="bi bi-link-45deg"></i></a>
                    </div>
                    <div class="ll-grid">
                        <div>
                            <div class="ll-grid-label">Bidang pekerjaan</div>
                            <div class="ll-grid-value">Penjualan dan Pemasaran</div>
                        </div>
                        <div>
                            <div class="ll-grid-label">Jenis pekerjaan</div>
                            <div class="ll-grid-value">Contract</div>
                        </div>
                        <div>
                            <div class="ll-grid-label">Tipe pekerjaan</div>
                            <div class="ll-grid-value">Lowongan dalam negeri</div>
                        </div>
                        <div>
                            <div class="ll-grid-label">Jenis kelamin</div>
                            <div class="ll-grid-value">Laki-laki / Perempuan</div>
                        </div>
                        <div>
                            <div class="ll-grid-label">Rentang gaji</div>
                            <div class="ll-grid-value">Dirahasiakan</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-xl-4">
                    <div class="d-grid mb-3">
                        <button type="button" class="btn btn-primary ll-apply-btn">Lamar Sekarang</button>
                    </div>
                    <aside class="ll-company-card">
                        <div class="ll-company-name">PT Finaccel Finance Indonesia</div>
                        <div class="ll-company-address">
                            Dipo Tower Lt. 3, Jl. Gatot Subroto No.Kav 50-52, RW.7, Petamburan, Kecamatan Tanah Abang, Jakarta, DKI Jakarta 10260
                        </div>
                        <div class="ll-company-badge">Banking &amp; Financial Services</div><br>
                        <a class="ll-company-link" href="karirhub_employer_prototype_lapor_loker_company_profile">
                            <i class="bi bi-building"></i> Lihat Profil Perusahaan
                        </a>
                        <div class="small text-muted mt-3">Lowongan dari Karirhub</div>
                    </aside>
                </div>
            </div>

            <section class="ll-description">
                <h2 class="ll-description-title">Deskripsi Pekerjaan</h2>
                <div class="ll-list-title">Tanggung Jawab:</div>
                <ul class="ll-list">
                    <li>Melayani apa yang diinginkan pelanggan dan merekomendasikan layanan sebagai moda pembayaran.</li>
                    <li>Meningkatkan product knowledge dan menyampaikan pemahaman produk dengan bahasa yang mudah dipahami.</li>
                    <li>Mempresentasikan dan mendemonstrasikan layanan kepada calon pelanggan.</li>
                    <li>Menindaklanjuti keluhan serta permintaan pelanggan dan memberikan panduan penggunaan aplikasi.</li>
                    <li>Memenuhi target harian serta mengirimkan laporan capaian kepada pihak manajerial.</li>
                    <li>Bertugas memegang 20-50 toko atau merchant pada 1 area atau mall.</li>
                </ul>
            </section>

            <section class="ll-report-wrap">
                <button
                    class="ll-report-toggle"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#laporLowonganPanel"
                    aria-expanded="true"
                    aria-controls="laporLowonganPanel"
                >
                    <span>Laporkan lowongan ini</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div id="laporLowonganPanel" class="collapse show">
                    <div class="ll-report-card">
                        <div id="vacancyReportFormWrap">
                            <div class="ll-safe-box">
                                Hati-hati: Jangan pernah membagikan PIN, OTP, password, detail rekening/kartu, atau melakukan pembayaran yang tidak semestinya selama proses melamar.
                            </div>
                            <form onsubmit="return false;" aria-label="Prototype form lapor lowongan">
                                <div class="mb-3">
                                    <label class="ll-form-label" for="reportEmail">Alamat email kamu</label>
                                    <input id="reportEmail" type="email" class="form-control" value="email@example.com" readonly>
                                    <div class="ll-form-note">Diisi otomatis dari akun yang sudah login (prototype read-only).</div>
                                </div>
                                <div class="mb-3">
                                    <label class="ll-form-label" for="reportReason">Alasan pelaporan lowongan</label>
                                    <select id="reportReason" class="form-select">
                                        <option selected>Silakan pilih</option>
                                        <option>Penipuan / lowongan fiktif</option>
                                        <option>Mencurigakan / informasi menyesatkan</option>
                                        <option>Meminta biaya/pembayaran</option>
                                        <option>Diskriminasi / persyaratan tidak patut</option>
                                        <option>Gaji di bawah upah minimum / informasi upah tidak sesuai</option>
                                        <option>Meminta data pribadi sensitif/kredensial</option>
                                        <option>Identitas pemberi kerja tidak sesuai</option>
                                        <option>Konten tidak pantas/tidak sesuai ketentuan</option>
                                        <option>Lowongan sudah tidak tersedia/kedaluwarsa</option>
                                        <option>Lainnya</option>
                                    </select>
                                    <div id="vacancyReasonError" class="ll-error-text">Pilih alasan pelaporan terlebih dahulu.</div>
                                </div>
                                <div id="reportReasonOtherWrap" class="mb-3 d-none">
                                    <label class="ll-form-label" for="reportReasonOther">Alasan pelaporan lowongan Lainnya</label>
                                    <input id="reportReasonOther" type="text" class="form-control" placeholder="Tuliskan alasan pelaporan lainnya...">
                                    <div id="vacancyReasonOtherError" class="ll-error-text">Isi alasan pelaporan lainnya terlebih dahulu.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="ll-form-label" for="reportComment">Komentar tambahan</label>
                                    <textarea id="reportComment" class="form-control" rows="4" placeholder="Jelaskan informasi yang membantu proses pemeriksaan..."></textarea>
                                    <div class="ll-form-note">Opsional. Tambahkan detail yang membantu pemeriksaan laporan.</div>
                                </div>
                                <div class="mb-3">
                                    <label class="ll-form-label" for="reportEvidence">Tambahkan bukti</label>
                                    <input id="reportEvidence" type="file" class="form-control" accept=".pdf,image/*">
                                    <div class="ll-form-note">Tipe file contoh: PDF, JPG, PNG.</div>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="" id="reportConsent">
                                    <label class="form-check-label" for="reportConsent">
                                        Saya menyampaikan laporan ini dengan itikad baik.
                                    </label>
                                    <div id="vacancyConsentError" class="ll-error-text">Centang pernyataan itikad baik untuk melanjutkan.</div>
                                </div>
                                <div class="ll-report-actions">
                                    <button id="submitVacancyReportBtn" type="button" class="btn btn-primary ll-report-primary">Laporkan lowongan</button>
                                    <a id="cancelVacancyReportBtn" href="#" class="ll-report-cancel">Batal</a>
                                </div>
                            </form>
                        </div>
                        <div id="vacancyReportSuccessWrap" class="d-none">
                            <div class="ll-success-card">
                                <div class="ll-success-title">Laporan berhasil dikirim</div>
                                <div class="ll-success-meta"><strong>Nomor laporan:</strong> <span id="vacancyReportIdText">VRP-2026-000001</span></div>
                                <div class="ll-success-meta"><strong>Status awal:</strong> Pending</div>
                                <div class="ll-success-note">
                                    Laporan Anda akan diperiksa oleh Admin. Pengiriman laporan tidak secara otomatis menghapus lowongan.
                                    Identitas pelapor tidak disampaikan kepada Pemberi Kerja.
                                </div>
                                <div class="ll-success-actions">
                                    <a class="btn btn-outline-primary" href="karirhub_employer_prototype_lapor_loker">Kembali ke lowongan</a>
                                    <a class="btn btn-primary" href="laporan_saya_prototype">Lihat Laporan Saya</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        const submitBtn = document.getElementById('submitVacancyReportBtn');
        const cancelBtn = document.getElementById('cancelVacancyReportBtn');
        const formWrap = document.getElementById('vacancyReportFormWrap');
        const successWrap = document.getElementById('vacancyReportSuccessWrap');
        const reasonField = document.getElementById('reportReason');
        const reasonOtherWrap = document.getElementById('reportReasonOtherWrap');
        const reasonOtherField = document.getElementById('reportReasonOther');
        const commentField = document.getElementById('reportComment');
        const consentField = document.getElementById('reportConsent');
        const reasonError = document.getElementById('vacancyReasonError');
        const reasonOtherError = document.getElementById('vacancyReasonOtherError');
        const consentError = document.getElementById('vacancyConsentError');
        const reportIdText = document.getElementById('vacancyReportIdText');

        if (!submitBtn || !formWrap || !successWrap || !reasonField || !reasonOtherWrap || !reasonOtherField || !commentField || !consentField || !reportIdText) {
            return;
        }

        function hideErrors() {
            if (reasonError) reasonError.style.display = 'none';
            if (reasonOtherError) reasonOtherError.style.display = 'none';
            if (consentError) consentError.style.display = 'none';
        }

        function isOtherReasonSelected() {
            return reasonField.value.trim().toLowerCase() === 'lainnya';
        }

        function syncReasonOtherVisibility() {
            const showOther = isOtherReasonSelected();
            reasonOtherWrap.classList.toggle('d-none', !showOther);
            if (!showOther) {
                reasonOtherField.value = '';
                if (reasonOtherError) reasonOtherError.style.display = 'none';
            }
        }

        function generateMockReportId() {
            const now = new Date();
            const year = now.getFullYear();
            const random = Math.floor(Math.random() * 900000) + 100000;
            return 'VRP-' + year + '-' + String(random);
        }

        reasonField.addEventListener('change', syncReasonOtherVisibility);
        syncReasonOtherVisibility();

        submitBtn.addEventListener('click', function () {
            hideErrors();
            let hasError = false;
            const reasonValue = reasonField.value.trim().toLowerCase();
            const reasonOtherValue = reasonOtherField.value.trim();
            const reasonNotChosen = (reasonValue === '' || reasonValue === 'silakan pilih');
            const reasonIsOther = isOtherReasonSelected();

            if (reasonNotChosen) {
                hasError = true;
                if (reasonError) reasonError.style.display = 'block';
            }

            if (reasonIsOther && reasonOtherValue === '') {
                hasError = true;
                if (reasonOtherError) reasonOtherError.style.display = 'block';
            }

            if (!consentField.checked) {
                hasError = true;
                if (consentError) consentError.style.display = 'block';
            }

            if (hasError) {
                return;
            }

            reportIdText.textContent = generateMockReportId();
            formWrap.classList.add('d-none');
            successWrap.classList.remove('d-none');
        });

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function (evt) {
                evt.preventDefault();
                reasonField.selectedIndex = 0;
                reasonOtherField.value = '';
                commentField.value = '';
                consentField.checked = false;
                syncReasonOtherVisibility();
                hideErrors();
            });
        }
    })();
</script>
<?php kh_proto_render_sidebar_script(); ?>
</body>
</html>
