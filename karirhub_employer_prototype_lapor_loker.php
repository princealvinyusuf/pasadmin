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
<?php kh_proto_render_hero('Lapor Loker Prototype', 'Simulasi UI detail lowongan bergaya Karirhub untuk kebutuhan prototyping.', 'Lowongan Kerja', 'karirhub_employer_prototype_lapor_loker', 'Proyek', 'karirhub_employer_prototype_lapor_loker', false); ?>

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
                        <a class="ll-company-link" href="#">
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
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php kh_proto_render_sidebar_script(); ?>
</body>
</html>
