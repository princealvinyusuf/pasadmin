<?php

if (!function_exists('kh_proto_h')) {
    function kh_proto_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('kh_proto_render_styles')) {
    function kh_proto_render_styles(): void
    {
        echo '<style>
            body.kh-proto-page { background: #eef3f8; }
            .kh-hero { background: #0b3b66; color: #fff; padding: 0 0 24px; border-bottom: 1px solid rgba(255,255,255,0.2); }
            .kh-topnav { display: flex; align-items: center; justify-content: space-between; gap: 14px; min-height: 56px; border-bottom: 1px solid rgba(255,255,255,0.14); margin-bottom: 24px; }
            .kh-topnav-left { display: flex; align-items: center; gap: 18px; min-width: 0; }
            .kh-topnav-logo { width: 220px; max-width: 38vw; height: auto; display: block; }
            .kh-topnav-links { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
            .kh-topnav-links a { color: #dbe8f3; text-decoration: none; font-size: 13px; font-weight: 500; }
            .kh-topnav-links a:hover { color: #ffffff; }
            .kh-topnav-links a.active { color: #ffffff; font-weight: 700; }
            .kh-topnav-right { display: flex; align-items: center; gap: 8px; }
            .kh-avatar { width: 24px; height: 24px; border-radius: 999px; background: #d8e5f3; color: #0b3b66; font-size: 11px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; }
            .kh-nav-btn { border-radius: 4px; font-size: 12px; line-height: 1.2; padding: 6px 10px; }
            .kh-nav-btn-outline { background: transparent; border: 1px solid rgba(255,255,255,0.35); color: #fff; }
            .kh-nav-btn-outline:hover { background: rgba(255,255,255,0.12); color: #fff; }
            .kh-nav-btn-teal { background: #0a8f8a; border: 1px solid #0a8f8a; color: #fff; }
            .kh-nav-btn-teal:hover { background: #087a76; border-color: #087a76; color: #fff; }
            .kh-nav-btn-light { background: #fff; border: 1px solid #d7e2ee; color: #1b2f45; }
            .kh-nav-btn-light:hover { background: #f5f8fb; color: #1b2f45; }
            .kh-hero-title { font-size: 34px; font-weight: 700; margin-bottom: 6px; }
            .kh-hero-sub { color: #d2e1ef; font-size: 14px; margin-bottom: 18px; }
            .kh-hero-tools { background: #f8fbff; border-radius: 8px; padding: 10px; border: 1px solid #dbe7f3; }
            .kh-tool-input { border: 1px solid #ced9e6; border-radius: 6px; font-size: 14px; min-height: 38px; }
            .kh-btn-primary { background: #0a7f96; border: 1px solid #0a7f96; color: #fff; }
            .kh-btn-primary:hover { background: #096f84; border-color: #096f84; color: #fff; }
            .kh-btn-dark { background: #163e63; border: 1px solid #163e63; color: #fff; }
            .kh-btn-dark:hover { background: #12334f; border-color: #12334f; color: #fff; }
            .kh-status-strip { margin-top: 10px; color: #d4e4f2; font-size: 13px; }
            .kh-chip { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.14); border: 1px solid rgba(255,255,255,0.25); border-radius: 14px; padding: 2px 10px; margin-right: 8px; }
            .kh-content-wrap { margin-top: -10px; }
            .kh-content-wrap .card { border: 1px solid #d7e2ee; border-radius: 8px; box-shadow: none; }
            .kh-content-wrap .table thead th { background: #f5f9fd; color: #30465f; font-weight: 600; }
            @media (max-width: 991px) {
                .kh-topnav { flex-direction: column; align-items: flex-start; padding: 10px 0; }
                .kh-topnav-right { width: 100%; flex-wrap: wrap; }
                .kh-topnav-logo { width: 190px; max-width: 60vw; }
            }
        </style>';
    }
}

if (!function_exists('kh_proto_render_hero')) {
    function kh_proto_render_hero(
        string $title,
        string $subtitle,
        string $primaryLabel = 'Lowongan Kerja',
        string $primaryHref = 'karirhub_employer_prototype_pelaporan_lowongan',
        string $secondaryLabel = 'Proyek',
        string $secondaryHref = 'karirhub_employer_prototype_dashboard_wllp'
    ): void {
        ?>
        <section class="kh-hero">
            <div class="container">
                <div class="kh-topnav">
                    <div class="kh-topnav-left">
                        <img class="kh-topnav-logo" src="images/logo-white.png" alt="Logo Kemnaker">
                        <div class="kh-topnav-links">
                            <a class="active" href="<?php echo kh_proto_h($secondaryHref); ?>">Daftar Pekerjaan</a>
                            <a href="karirhub_employer_prototype_no_reg_bukti">Talent Search</a>
                            <a href="karirhub_employer_prototype_bukti_lapor">Profil + Ulasan Perusahaan</a>
                            <a href="karirhub_employer_prototype_monitoring_kepatuhan">Lainnya <i class="bi bi-chevron-down"></i></a>
                        </div>
                    </div>
                    <div class="kh-topnav-right">
                        <span class="kh-avatar">PK</span>
                        <button type="button" class="btn kh-nav-btn kh-nav-btn-outline">+ Posting</button>
                        <button type="button" class="btn kh-nav-btn kh-nav-btn-teal">PT. Pandu J.. <i class="bi bi-caret-down-fill"></i></button>
                        <button type="button" class="btn kh-nav-btn kh-nav-btn-light"><i class="bi bi-grid-3x3-gap-fill me-1"></i>Layanan</button>
                    </div>
                </div>
                <div class="kh-hero-title"><?php echo kh_proto_h($title); ?></div>
                <div class="kh-hero-sub"><?php echo kh_proto_h($subtitle); ?></div>
                <div class="kh-hero-tools">
                    <div class="row g-2 align-items-center">
                        <div class="col-12 col-lg-5">
                            <input type="text" class="form-control kh-tool-input" placeholder="Cari berdasarkan judul lowongan atau nama perusahaan">
                        </div>
                        <div class="col-12 col-lg-3">
                            <select class="form-select kh-tool-input">
                                <option>Masukan Lokasi</option>
                                <option>DKI Jakarta</option>
                                <option>Jawa Barat</option>
                                <option>Jawa Timur</option>
                            </select>
                        </div>
                        <div class="col-12 col-lg-1 d-grid">
                            <button class="btn kh-btn-primary" type="button"><i class="bi bi-search"></i></button>
                        </div>
                        <div class="col-6 col-lg-2 d-grid">
                            <a class="btn kh-btn-primary" href="<?php echo kh_proto_h($primaryHref); ?>"><?php echo kh_proto_h($primaryLabel); ?></a>
                        </div>
                        <div class="col-6 col-lg-1 d-grid">
                            <a class="btn kh-btn-dark" href="<?php echo kh_proto_h($secondaryHref); ?>"><?php echo kh_proto_h($secondaryLabel); ?></a>
                        </div>
                    </div>
                </div>
                <div class="kh-status-strip">
                    <span class="kh-chip"><i class="bi bi-circle-fill" style="font-size:7px"></i>Status Lowongan: Aktif</span>
                    <span class="kh-chip"><i class="bi bi-square"></i>Loker Terbatas</span>
                </div>
            </div>
        </section>
        <?php
    }
}

