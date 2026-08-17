<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_ui.php';

if (!kh_proto_can_access('karirhub_employer_prototype_lapor_loker_view')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karirhub Employer Prototype - Profil Perusahaan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php kh_proto_render_styles(); ?>
    <style>
        .ll-company-page { background: #fff; border: 1px solid #dfe8f3; border-radius: 14px; padding: 24px; }
        .ll-company-head { display: flex; align-items: flex-start; gap: 16px; margin-bottom: 14px; }
        .ll-company-logo { width: 76px; height: 76px; border-radius: 12px; border: 1px solid #e1ebf5; background: linear-gradient(135deg, #f5faff, #edf4ff); display: flex; align-items: center; justify-content: center; font-weight: 800; color: #3a85d6; font-size: 20px; }
        .ll-company-title { color: #223b55; font-size: 40px; font-weight: 700; line-height: 1.1; margin-bottom: 2px; }
        .ll-company-sub { color: #3e5c77; font-size: 30px; font-weight: 600; line-height: 1.2; margin-bottom: 4px; }
        .ll-company-since { color: #8297ad; font-size: 24px; }
        .ll-share { display: flex; align-items: center; gap: 10px; color: #556b83; margin: 12px 0 16px; font-size: 21px; }
        .ll-share-link { width: 34px; height: 34px; border-radius: 999px; border: 1px solid #d7e2ef; color: #4d6278; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }
        .ll-share-link:hover { background: #f1f7ff; color: #0a7f96; border-color: #bfdaee; }
        .ll-company-desc { color: #304a64; font-size: 29px; line-height: 1.6; margin-bottom: 20px; }
        .ll-info-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px 24px; margin-bottom: 22px; }
        .ll-info-label { color: #8ea0b4; font-size: 22px; margin-bottom: 4px; }
        .ll-info-value { color: #233d58; font-size: 28px; font-weight: 600; }
        .ll-map-title { color: #1f3550; font-size: 36px; font-weight: 700; margin-bottom: 10px; }
        .ll-map-card { border: 1px solid #dfe8f3; border-radius: 12px; overflow: hidden; }
        .ll-map-embed { width: 100%; height: 260px; border: 0; }
        .ll-map-footer { background: #fff; padding: 12px 14px; }
        .ll-map-city { display: flex; align-items: center; gap: 8px; color: #1f3550; font-size: 23px; font-weight: 700; margin-bottom: 5px; text-transform: uppercase; }
        .ll-map-city i { color: #2b95eb; }
        .ll-map-address { color: #667f96; font-size: 21px; }
        .ll-back-link { text-decoration: none; font-weight: 600; }
        .ll-report-wrap { margin-top: 28px; border-top: 1px solid #edf2f8; padding-top: 24px; }
        .ll-report-toggle { width: 100%; border: 1px solid #d7e3f0; border-radius: 10px; background: #f8fbff; padding: 14px 16px; color: #1f3550; font-size: 24px; font-weight: 700; display: flex; align-items: center; justify-content: space-between; text-align: left; }
        .ll-report-toggle:hover { background: #f2f8ff; }
        .ll-report-toggle i { font-size: 16px; color: #56718d; transition: transform .2s ease; }
        .ll-report-toggle[aria-expanded="true"] i { transform: rotate(180deg); }
        .ll-report-card { border: 1px solid #dce7f2; border-top: 0; border-radius: 0 0 12px 12px; padding: 18px; background: #fff; }
        .ll-helper-box { border: 1px solid #d7e5f5; background: #f5faff; color: #2a4f77; border-radius: 8px; padding: 10px 12px; font-size: 14px; margin-bottom: 12px; }
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
        @media (max-width: 1199px) {
            .ll-company-title { font-size: 30px; }
            .ll-company-sub { font-size: 22px; }
            .ll-company-since { font-size: 18px; }
            .ll-company-desc { font-size: 20px; }
            .ll-share { font-size: 16px; }
            .ll-info-label { font-size: 16px; }
            .ll-info-value { font-size: 19px; }
            .ll-map-title { font-size: 28px; }
            .ll-map-city { font-size: 18px; }
            .ll-map-address { font-size: 16px; }
        }
        @media (max-width: 767px) {
            .ll-company-page { padding: 16px; }
            .ll-company-head { align-items: center; }
            .ll-company-logo { width: 56px; height: 56px; font-size: 16px; }
            .ll-company-title { font-size: 22px; }
            .ll-company-sub { font-size: 16px; }
            .ll-company-since { font-size: 14px; }
            .ll-company-desc { font-size: 15px; }
            .ll-info-grid { grid-template-columns: 1fr; }
            .ll-map-title { font-size: 22px; }
            .ll-map-city { font-size: 15px; }
            .ll-map-address { font-size: 14px; }
            .ll-report-toggle { font-size: 18px; }
        }
    </style>
</head>
<body class="kh-proto-page">
<?php include 'navbar.php'; ?>
<?php kh_proto_render_hero('Profil Perusahaan', 'Simulasi halaman profil perusahaan dari lowongan Karirhub.', 'Lowongan Kerja', 'karirhub_employer_prototype_lapor_loker', 'Proyek', 'karirhub_employer_prototype_lapor_loker', false); ?>

<div class="kh-content-wrap">
    <div class="container py-4">
        <div class="mb-3">
            <a class="ll-back-link" href="karirhub_employer_prototype_lapor_loker"><i class="bi bi-arrow-left me-1"></i>Kembali ke detail lowongan</a>
        </div>
        <div class="ll-company-page">
            <div class="ll-company-head">
                <div class="ll-company-logo">K</div>
                <div>
                    <div class="ll-company-title">PT Finaccel Finance Indonesia</div>
                    <div class="ll-company-sub">Banking &amp; Financial Services</div>
                    <div class="ll-company-since">Terdaftar sejak 19 Mei 2023</div>
                </div>
            </div>

            <div class="ll-share">
                <span>Bagikan:</span>
                <a class="ll-share-link" href="#" aria-label="Bagikan ke WhatsApp"><i class="bi bi-whatsapp"></i></a>
                <a class="ll-share-link" href="#" aria-label="Bagikan ke LinkedIn"><i class="bi bi-linkedin"></i></a>
                <a class="ll-share-link" href="#" aria-label="Bagikan ke X"><i class="bi bi-twitter-x"></i></a>
                <a class="ll-share-link" href="#" aria-label="Bagikan ke Facebook"><i class="bi bi-facebook"></i></a>
                <a class="ll-share-link" href="#" aria-label="Salin tautan"><i class="bi bi-link-45deg"></i></a>
            </div>

            <div class="ll-company-desc">
                Kredivo adalah solusi kredit instan yang memberikan kamu kemudahan untuk beli sekarang dan bayar nanti dalam 30 hari
                atau cicilan 3 bulan dengan bunga 0% ataupun dengan cicilan 6 bulan atau 12 bulan dengan bunga 2.6% per bulan.
            </div>

            <div class="ll-info-grid">
                <div>
                    <div class="ll-info-label">Kontak perusahaan</div>
                    <div class="ll-info-value">(021) 22055677</div>
                </div>
                <div>
                    <div class="ll-info-label">Email perusahaan</div>
                    <div class="ll-info-value">clarint.septiani@finaccel.co</div>
                </div>
                <div>
                    <div class="ll-info-label">Website perusahaan</div>
                    <div class="ll-info-value">Tidak ada data</div>
                </div>
            </div>

            <div class="ll-map-title">Lokasi alamat perusahaan</div>
            <div class="ll-map-card">
                <iframe
                    class="ll-map-embed"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    src="https://maps.google.com/maps?q=Dipo%20Tower%20Gatot%20Subroto%20Jakarta&t=&z=14&ie=UTF8&iwloc=&output=embed"
                    title="Lokasi PT Finaccel Finance Indonesia"
                ></iframe>
                <div class="ll-map-footer">
                    <div class="ll-map-city"><i class="bi bi-geo-alt-fill"></i>Kota Adm. Jakarta Pusat</div>
                    <div class="ll-map-address">Gedung DIPO Tower, Lantai 3 Unit A-B, Jalan Jenderal Gatot Subroto Kavling 51</div>
                </div>
            </div>

            <section class="ll-report-wrap">
                <button
                    class="ll-report-toggle"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#laporPerusahaanPanel"
                    aria-expanded="true"
                    aria-controls="laporPerusahaanPanel"
                >
                    <span>Laporkan perusahaan ini</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div id="laporPerusahaanPanel" class="collapse show">
                    <div class="ll-report-card">
                        <div id="companyReportFormWrap">
                            <div class="ll-safe-box">
                                Hati-hati: Jangan pernah membagikan PIN, OTP, password, detail rekening/kartu, atau melakukan pembayaran yang tidak semestinya selama proses rekrutmen.
                            </div>
                            <form onsubmit="return false;" aria-label="Prototype form lapor perusahaan">
                                <div class="mb-3">
                                    <label class="ll-form-label" for="reportEmailCompany">Alamat email kamu</label>
                                    <input id="reportEmailCompany" type="email" class="form-control" value="email@example.com" readonly>
                                    <div class="ll-form-note">Diisi otomatis dari akun yang sudah login (prototype read-only).</div>
                                </div>

                                <div class="mt-3">
                                    <label class="ll-form-label" for="reportCompanyReason">Alasan pelaporan perusahaan</label>
                                    <select id="reportCompanyReason" class="form-select">
                                        <option selected>Silakan pilih</option>
                                        <option>Perusahaan palsu / informasi menyesatkan</option>
                                        <option>Penipuan / modus rekrutment</option>
                                        <option>Meminta biaya / pembayaran</option>
                                        <option>Meminta data pribadi sensitif / kredensial</option>
                                        <option>Praktik diskriminatif</option>
                                        <option>Konten tidak pantas / tidak sesuai ketentuan</option>
                                        <option>Dugaan penyalahgunaan akun perusahaan</option>
                                        <option>Lainnya</option>
                                    </select>
                                    <div id="reasonError" class="ll-error-text">Pilih alasan pelaporan terlebih dahulu.</div>
                                </div>

                                <div class="mt-3">
                                    <label class="ll-form-label" for="relatedVacancy">Lowongan terkait (opsional)</label>
                                    <input id="relatedVacancy" type="text" class="form-control" placeholder="Contoh: Sales Executive - DKI Jakarta">
                                    <div class="ll-form-note">Hanya sebagai konteks. Objek laporan tetap perusahaan.</div>
                                </div>

                                <div class="mt-3">
                                    <label class="ll-form-label" for="reportCompanyComment">Komentar tambahan</label>
                                    <textarea id="reportCompanyComment" class="form-control" rows="4" placeholder="Jelaskan informasi yang membantu proses pemeriksaan..."></textarea>
                                    <div class="ll-form-note">Komentar wajib jika memilih alasan “Lainnya”.</div>
                                    <div id="commentError" class="ll-error-text">Komentar wajib jika alasan yang dipilih adalah “Lainnya”.</div>
                                </div>

                                <div class="mt-3">
                                    <label class="ll-form-label" for="reportCompanyEvidence">Tambahkan bukti (opsional)</label>
                                    <input id="reportCompanyEvidence" type="file" class="form-control" accept=".pdf,image/*">
                                    <div class="ll-form-note">Tipe file contoh: PDF, JPG, PNG.</div>
                                </div>

                                <div class="form-check mt-3">
                                    <input class="form-check-input" type="checkbox" value="" id="reportCompanyConsent">
                                    <label class="form-check-label" for="reportCompanyConsent">
                                        Saya menyampaikan laporan ini dengan itikad baik.
                                    </label>
                                    <div id="consentError" class="ll-error-text">Centang pernyataan itikad baik untuk melanjutkan.</div>
                                </div>

                                <div class="ll-report-actions">
                                    <button id="submitCompanyReportBtn" type="button" class="btn btn-primary ll-report-primary">Laporkan perusahaan</button>
                                    <a id="cancelCompanyReportBtn" href="#" class="ll-report-cancel">Batal</a>
                                </div>
                            </form>
                        </div>
                        <div id="companyReportSuccessWrap" class="d-none">
                            <div class="ll-success-card">
                                <div class="ll-success-title">Laporan berhasil dikirim</div>
                                <div class="ll-success-meta"><strong>Nomor laporan:</strong> <span id="companyReportIdText">CRP-2026-000001</span></div>
                                <div class="ll-success-meta"><strong>Status awal:</strong> Pending</div>
                                <div class="ll-success-note">
                                    Laporan Anda akan diperiksa oleh Admin. Pengiriman laporan tidak secara otomatis membatasi akun perusahaan atau menurunkan lowongan.
                                    Identitas pelapor tidak disampaikan kepada Pemberi Kerja.
                                </div>
                                <div class="ll-success-actions">
                                    <a class="btn btn-outline-primary" href="karirhub_employer_prototype_lapor_loker_company_profile">Kembali ke profil perusahaan</a>
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
        const submitBtn = document.getElementById('submitCompanyReportBtn');
        const cancelBtn = document.getElementById('cancelCompanyReportBtn');
        const formWrap = document.getElementById('companyReportFormWrap');
        const successWrap = document.getElementById('companyReportSuccessWrap');
        const reasonField = document.getElementById('reportCompanyReason');
        const commentField = document.getElementById('reportCompanyComment');
        const consentField = document.getElementById('reportCompanyConsent');
        const reasonError = document.getElementById('reasonError');
        const commentError = document.getElementById('commentError');
        const consentError = document.getElementById('consentError');
        const reportIdText = document.getElementById('companyReportIdText');

        if (!submitBtn || !formWrap || !successWrap || !reasonField || !commentField || !consentField || !reportIdText) {
            return;
        }

        function hideErrors() {
            if (reasonError) reasonError.style.display = 'none';
            if (commentError) commentError.style.display = 'none';
            if (consentError) consentError.style.display = 'none';
        }

        function generateMockReportId() {
            const now = new Date();
            const year = now.getFullYear();
            const random = Math.floor(Math.random() * 900000) + 100000;
            return 'CRP-' + year + '-' + String(random);
        }

        submitBtn.addEventListener('click', function () {
            hideErrors();
            let hasError = false;
            const reasonValue = reasonField.value.trim().toLowerCase();
            const commentValue = commentField.value.trim();
            const reasonNotChosen = (reasonValue === '' || reasonValue === 'silakan pilih');
            const reasonIsOther = (reasonValue === 'lainnya');

            if (reasonNotChosen) {
                hasError = true;
                if (reasonError) reasonError.style.display = 'block';
            }

            if (reasonIsOther && commentValue === '') {
                hasError = true;
                if (commentError) commentError.style.display = 'block';
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
                commentField.value = '';
                consentField.checked = false;
                hideErrors();
            });
        }
    })();
</script>
<?php kh_proto_render_sidebar_script(); ?>
</body>
</html>
