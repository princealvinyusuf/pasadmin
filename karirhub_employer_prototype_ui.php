<?php

if (!function_exists('kh_proto_h')) {
    function kh_proto_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('kh_proto_can_access')) {
    function kh_proto_can_access(?string $specificCode = null): bool
    {
        if (current_user_can('manage_settings') || current_user_can('karirhub_employer_prototype_view')) {
            return true;
        }
        if ($specificCode === null || $specificCode === '') {
            return false;
        }
        return current_user_can($specificCode);
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
            .kh-topnav-links .dropdown-toggle { color: #dbe8f3; text-decoration: none; font-size: 13px; font-weight: 500; background: transparent; border: none; padding: 0; }
            .kh-topnav-links .dropdown-toggle:hover,
            .kh-topnav-links .dropdown-toggle:focus { color: #ffffff; }
            .kh-topnav-links .dropdown-toggle::after { margin-left: 6px; vertical-align: 0.15em; }
            .kh-wllp-menu { min-width: 220px; border-radius: 8px; border: 1px solid #dbe5f1; padding: 6px 0; }
            .kh-wllp-menu .dropdown-item { font-size: 13px; padding: 8px 14px; display: flex; align-items: center; gap: 8px; color: #1f2f42; }
            .kh-wllp-menu .dropdown-item i { color: #0d3f6d; }
            .kh-wllp-menu .dropdown-divider { margin: 6px 0; }
            .kh-wllp-menu .kh-submenu-toggle { width: 100%; border: 0; background: transparent; text-align: left; }
            .kh-wllp-menu .kh-submenu-toggle .bi-chevron-right { margin-left: auto; font-size: 11px; color: #7a8ea5; }
            .kh-wllp-menu .dropdown-menu { min-width: 210px; border-radius: 8px; border: 1px solid #dbe5f1; padding: 6px 0; }
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
            .kh-proto-shell { display: flex; align-items: stretch; min-height: calc(100vh - 220px); }
            .kh-side { width: 290px; flex: 0 0 290px; transition: all .2s ease; padding: 0 0 24px; }
            .kh-side-inner { min-height: 100%; border: 1px solid #d7e2ee; border-radius: 8px; overflow: hidden; background: linear-gradient(90deg, #048b87 0, #048b87 80px, #ffffff 80px, #ffffff 100%); position: relative; }
            .kh-side-logo { padding: 24px 16px 6px 100px; font-size: 42px; font-weight: 800; color: #12263a; line-height: 1; }
            .kh-side-logo-sub { padding: 0 16px 16px 102px; color: #7b8ea6; font-size: 12px; }
            .kh-side-menu { list-style: none; margin: 0; padding: 4px 8px 16px 92px; }
            .kh-side-menu-title { font-size: 12px; font-weight: 700; color: #a1afc3; letter-spacing: .04em; text-transform: uppercase; padding: 8px 6px; }
            .kh-side-item { margin: 2px 0; }
            .kh-side-link { display: flex; align-items: center; gap: 10px; color: #7789a1; text-decoration: none; font-weight: 600; padding: 9px 10px; border-radius: 8px; font-size: 16px; }
            .kh-side-link:hover { color: #0f6f87; background: #f2fbfa; }
            .kh-side-link.active { background: #effcfb; color: #03a39a; }
            .kh-side-link i { color: #9aa8ba; font-size: 17px; width: 18px; text-align: center; }
            .kh-side-link.active i { color: #03a39a; }
            .kh-side-submenu { list-style: none; margin: 0 0 6px 30px; padding: 0; border-left: 1px dashed #d8e2ee; }
            .kh-side-submenu li { margin: 0; }
            .kh-side-submenu a { display: flex; align-items: center; gap: 8px; text-decoration: none; color: #58708d; padding: 7px 10px; font-size: 14px; font-weight: 600; }
            .kh-side-submenu a:hover { color: #0f6f87; background: #f4f9ff; }
            .kh-side-submenu a i { font-size: 14px; width: 16px; text-align: center; }
            .kh-side-divider { border-top: 1px solid #e4ebf3; margin: 8px 0; }
            .kh-side-bottom { position: absolute; left: 20px; bottom: 14px; width: 46px; height: 46px; border-radius: 999px; background: #d9edf0; color: #0b3b66; display: flex; align-items: center; justify-content: center; font-weight: 700; border: 2px solid #fff; }
            .kh-proto-main { flex: 1 1 auto; min-width: 0; padding-left: 16px; transition: all .2s ease; }
            .kh-side-toggle { position: fixed; left: 10px; top: 120px; z-index: 1040; border: 1px solid #0e6f87; background: #0a8f8a; color: #fff; border-radius: 6px; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; }
            .kh-sidebar-collapsed .kh-side { width: 0; flex-basis: 0; padding: 0; overflow: hidden; }
            .kh-sidebar-collapsed .kh-proto-main { padding-left: 0; }
            .kh-content-wrap .card { border: 1px solid #d7e2ee; border-radius: 8px; box-shadow: none; }
            .kh-content-wrap .table thead th { background: #f5f9fd; color: #30465f; font-weight: 600; }
            @media (max-width: 991px) {
                .kh-topnav { flex-direction: column; align-items: flex-start; padding: 10px 0; }
                .kh-topnav-right { width: 100%; flex-wrap: wrap; }
                .kh-topnav-logo { width: 190px; max-width: 60vw; }
                .kh-side-toggle { top: 96px; }
                .kh-side { width: 100%; flex-basis: 100%; padding: 0 0 14px; }
                .kh-proto-shell { flex-direction: column; }
                .kh-proto-main { padding-left: 0; }
                .kh-sidebar-collapsed .kh-side { display: none; }
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
        string $secondaryHref = 'karirhub_employer_prototype_dashboard_wllp',
        bool $showHeroContent = true
    ): void {
        $canDashboardWllp = kh_proto_can_access('karirhub_employer_prototype_dashboard_wllp_view');
        $canDashboardWllpAdmin = kh_proto_can_access('karirhub_employer_prototype_dashboard_wllp_admin_view');
        $canJobPosted = kh_proto_can_access('karirhub_employer_prototype_job_posted_view');
        $canBuktiLapor = kh_proto_can_access('karirhub_employer_prototype_bukti_lapor_view');
        $canPelaporan = kh_proto_can_access('karirhub_employer_prototype_pelaporan_lowongan_view');
        $canStatusKeterisian = kh_proto_can_access('karirhub_employer_prototype_status_keterisian_view');
        $canPaskerConnect = kh_proto_can_access('karirhub_employer_prototype_pasker_connect_view');
        $hasPelaporanNav = ($canPelaporan || $canJobPosted);
        $hasCoreWllpNav = ($canDashboardWllp || $canDashboardWllpAdmin || $canBuktiLapor);
        $hasAnyWllpMenu = (
            $canDashboardWllp
            || $canDashboardWllpAdmin
            || $canJobPosted
            || $canBuktiLapor
            || $canPelaporan
            || $canStatusKeterisian
            || $canPaskerConnect
        );
        ?>
        <section class="kh-hero">
            <div class="container">
                <div class="kh-topnav">
                    <div class="kh-topnav-left">
                        <img class="kh-topnav-logo" src="images/logo-white backup.png" alt="Logo Kemnaker">
                        <div class="kh-topnav-links">
                            <a class="active" href="<?php echo kh_proto_h($secondaryHref); ?>">Daftar Pekerjaan</a>
                            <?php if ($canBuktiLapor): ?>
                                <a href="karirhub_employer_prototype_bukti_lapor">Profil + Ulasan Perusahaan</a>
                            <?php endif; ?>
                            <?php if ($hasAnyWllpMenu): ?>
                            <div class="dropdown">
                                <button class="dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    WLLP
                                </button>
                                <ul class="dropdown-menu kh-wllp-menu">
                                    <?php if ($canDashboardWllp): ?><li><a class="dropdown-item" href="karirhub_employer_prototype_dashboard_wllp"><i class="bi bi-speedometer2"></i>Dashboard WLLP</a></li><?php endif; ?>
                                    <?php if ($canDashboardWllpAdmin): ?><li><a class="dropdown-item" href="karirhub_employer_prototype_dashboard_wllp_admin"><i class="bi bi-bar-chart-line"></i>Dashboard WLLP Admin</a></li><?php endif; ?>
                                    <?php if ($canBuktiLapor): ?><li><a class="dropdown-item" href="karirhub_employer_prototype_bukti_lapor"><i class="bi bi-file-earmark-check"></i>Bukti Lapor</a></li><?php endif; ?>
                                    <?php if (($hasPelaporanNav || $canStatusKeterisian || $canPaskerConnect) && $hasCoreWllpNav): ?><li><hr class="dropdown-divider"></li><?php endif; ?>
                                    <?php if ($hasPelaporanNav): ?>
                                        <li class="dropend">
                                            <button class="dropdown-item kh-submenu-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-journal-plus"></i>Pelaporan Lowongan
                                                <i class="bi bi-chevron-right"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <?php if ($canJobPosted): ?><li><a class="dropdown-item" href="karirhub_employer_prototype_job_posted_karirhub"><i class="bi bi-briefcase"></i>Job Posted Karirhub</a></li><?php endif; ?>
                                                <?php if ($canPelaporan): ?><li><a class="dropdown-item" href="karirhub_employer_prototype_pelaporan_lowongan"><i class="bi bi-list-ul"></i>Sumber Lainnya</a></li><?php endif; ?>
                                            </ul>
                                        </li>
                                    <?php endif; ?>
                                    <?php if ($canStatusKeterisian): ?><li><a class="dropdown-item" href="karirhub_employer_prototype_status_keterisian"><i class="bi bi-list-task"></i>Status Keterisian</a></li><?php endif; ?>
                                    <?php if ($canPaskerConnect): ?><li><a class="dropdown-item" href="karirhub_employer_prototype_pasker_connect"><i class="bi bi-plug"></i>Pasker Connect</a></li><?php endif; ?>
                                </ul>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="kh-topnav-right">
                        <span class="kh-avatar">PK</span>
                        <button type="button" class="btn kh-nav-btn kh-nav-btn-outline">+ Posting</button>
                        <button type="button" class="btn kh-nav-btn kh-nav-btn-teal">PT. Pandu J.. <i class="bi bi-caret-down-fill"></i></button>
                        <button type="button" class="btn kh-nav-btn kh-nav-btn-light"><i class="bi bi-grid-3x3-gap-fill me-1"></i>Layanan</button>
                    </div>
                </div>
                <?php if ($showHeroContent): ?>
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
                <?php endif; ?>
            </div>
        </section>
        <?php
    }
}

if (!function_exists('kh_proto_render_sidebar')) {
    function kh_proto_render_sidebar(string $activeKey = 'dashboard_wllp'): void
    {
        // Sidebar intentionally hidden for Karirhub Employer Prototype pages.
        return;

        $canDashboardWllp = kh_proto_can_access('karirhub_employer_prototype_dashboard_wllp_view');
        $canDashboardWllpAdmin = kh_proto_can_access('karirhub_employer_prototype_dashboard_wllp_admin_view');
        $canJobPosted = kh_proto_can_access('karirhub_employer_prototype_job_posted_view');
        $canBuktiLapor = kh_proto_can_access('karirhub_employer_prototype_bukti_lapor_view');
        $canPelaporan = kh_proto_can_access('karirhub_employer_prototype_pelaporan_lowongan_view');
        $canStatusKeterisian = kh_proto_can_access('karirhub_employer_prototype_status_keterisian_view');
        $canPaskerConnect = kh_proto_can_access('karirhub_employer_prototype_pasker_connect_view');
        $isWllpExpanded = str_starts_with($activeKey, 'wllp_');
        $hasAnyWllpMenu = (
            $canDashboardWllp
            || $canDashboardWllpAdmin
            || $canJobPosted
            || $canBuktiLapor
            || $canPelaporan
            || $canStatusKeterisian
            || $canPaskerConnect
        );
        $collapseClass = $isWllpExpanded ? 'show' : '';
        $menu = [
            'dashboard_wllp' => ['icon' => 'bi-speedometer2', 'label' => 'Dashboard WLLP', 'href' => 'karirhub_employer_prototype_dashboard_wllp'],
            'dashboard_wllp_admin' => ['icon' => 'bi-bar-chart-line', 'label' => 'Dashboard WLLP Admin', 'href' => 'karirhub_employer_prototype_dashboard_wllp_admin'],
            'wllp_job_posted' => ['icon' => 'bi-briefcase', 'label' => 'Job Posted Karirhub', 'href' => 'karirhub_employer_prototype_job_posted_karirhub'],
            'wllp_bukti_lapor' => ['icon' => 'bi-file-earmark-check', 'label' => 'Bukti Lapor', 'href' => 'karirhub_employer_prototype_bukti_lapor'],
            'wllp_pelaporan' => ['icon' => 'bi-journal-plus', 'label' => 'Pelaporan Lowongan', 'href' => 'karirhub_employer_prototype_pelaporan_lowongan'],
            'wllp_status_keterisian' => ['icon' => 'bi-list-task', 'label' => 'Status Keterisian', 'href' => 'karirhub_employer_prototype_status_keterisian'],
            'wllp_pasker_connect' => ['icon' => 'bi-plug', 'label' => 'Pasker Connect', 'href' => 'karirhub_employer_prototype_pasker_connect'],
        ];
        ?>
        <button type="button" class="kh-side-toggle" id="khSideToggleBtn" title="Hide/Show Sidebar">
            <i class="bi bi-layout-sidebar-inset"></i>
        </button>
        <aside class="kh-side" id="khSidePanel">
            <div class="kh-side-inner">
                <div class="kh-side-logo">Karirhub</div>
                <div class="kh-side-logo-sub">Employer Prototype</div>
                <ul class="kh-side-menu">
                    <li class="kh-side-menu-title">Dashboard</li>
                    <?php if ($canDashboardWllp): ?>
                    <li class="kh-side-item">
                        <a class="kh-side-link <?php echo $activeKey === 'dashboard_wllp' ? 'active' : ''; ?>" href="<?php echo kh_proto_h($menu['dashboard_wllp']['href']); ?>">
                            <i class="bi <?php echo kh_proto_h($menu['dashboard_wllp']['icon']); ?>"></i>
                            <?php echo kh_proto_h($menu['dashboard_wllp']['label']); ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($canDashboardWllpAdmin): ?>
                    <li class="kh-side-item">
                        <a class="kh-side-link <?php echo $activeKey === 'dashboard_wllp_admin' ? 'active' : ''; ?>" href="<?php echo kh_proto_h($menu['dashboard_wllp_admin']['href']); ?>">
                            <i class="bi <?php echo kh_proto_h($menu['dashboard_wllp_admin']['icon']); ?>"></i>
                            <?php echo kh_proto_h($menu['dashboard_wllp_admin']['label']); ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($hasAnyWllpMenu): ?>
                    <li class="kh-side-item">
                        <a class="kh-side-link <?php echo $isWllpExpanded ? 'active' : ''; ?>" data-bs-toggle="collapse" href="#khSideWllpMenu" role="button" aria-expanded="<?php echo $isWllpExpanded ? 'true' : 'false'; ?>" aria-controls="khSideWllpMenu">
                            <i class="bi bi-diagram-3"></i>
                            WLLP
                        </a>
                    </li>
                    <li>
                        <div class="collapse <?php echo $collapseClass; ?>" id="khSideWllpMenu">
                            <ul class="kh-side-submenu">
                                <?php if ($canDashboardWllp): ?><li><a href="<?php echo kh_proto_h($menu['dashboard_wllp']['href']); ?>"><i class="bi bi-speedometer2"></i>Dashboard WLLP</a></li><?php endif; ?>
                                <?php if ($canDashboardWllpAdmin): ?><li><a href="<?php echo kh_proto_h($menu['dashboard_wllp_admin']['href']); ?>"><i class="bi bi-bar-chart-line"></i>Dashboard WLLP Admin</a></li><?php endif; ?>
                                <?php if ($canJobPosted): ?><li><a href="<?php echo kh_proto_h($menu['wllp_job_posted']['href']); ?>"><i class="bi bi-briefcase"></i>Job Posted Karirhub</a></li><?php endif; ?>
                                <?php if ($canBuktiLapor): ?><li><a href="<?php echo kh_proto_h($menu['wllp_bukti_lapor']['href']); ?>"><i class="bi bi-file-earmark-check"></i>Bukti Lapor</a></li><?php endif; ?>
                                <?php if (($canPelaporan || $canStatusKeterisian) && ($canDashboardWllp || $canDashboardWllpAdmin || $canJobPosted || $canBuktiLapor)): ?><li class="kh-side-divider"></li><?php endif; ?>
                                <?php if ($canPelaporan): ?><li><a href="<?php echo kh_proto_h($menu['wllp_pelaporan']['href']); ?>"><i class="bi bi-journal-plus"></i>Pelaporan Lowongan</a></li><?php endif; ?>
                                <?php if ($canStatusKeterisian): ?><li><a href="<?php echo kh_proto_h($menu['wllp_status_keterisian']['href']); ?>"><i class="bi bi-list-task"></i>Status Keterisian</a></li><?php endif; ?>
                                <?php if ($canPaskerConnect): ?><li><a href="<?php echo kh_proto_h($menu['wllp_pasker_connect']['href']); ?>"><i class="bi bi-plug"></i>Pasker Connect</a></li><?php endif; ?>
                            </ul>
                        </div>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="kh-side-bottom">PK</div>
            </div>
        </aside>
        <?php
    }
}

if (!function_exists('kh_proto_render_sidebar_script')) {
    function kh_proto_render_sidebar_script(): void
    {
        ?>
        <script>
            (function () {
                const body = document.body;
                const key = 'khProtoSidebarCollapsed';
                const btn = document.getElementById('khSideToggleBtn');
                if (!btn) return;

                const saved = localStorage.getItem(key);
                if (saved === '1') {
                    body.classList.add('kh-sidebar-collapsed');
                }

                btn.addEventListener('click', function () {
                    body.classList.toggle('kh-sidebar-collapsed');
                    localStorage.setItem(key, body.classList.contains('kh-sidebar-collapsed') ? '1' : '0');
                });
            })();
        </script>
        <?php
    }
}

