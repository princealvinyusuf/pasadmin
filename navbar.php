<?php require_once __DIR__ . '/access_helper.php'; ?>
<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="index.php"><i class="bi bi-briefcase me-2"></i>Job Admin</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
<?php
    $canDashJobs = current_user_can('view_dashboard_jobs');
    $canDashSeekers = current_user_can('view_dashboard_job_seekers');
    $canJobs = current_user_can('manage_jobs');
    $canJobSeekers = current_user_can('manage_job_seekers');
    $canManageSettings = current_user_can('manage_settings');
    $canChart = current_user_can('settings_chart_manage');
    $canContribution = current_user_can('settings_contribution_manage');
    $canInformation = current_user_can('settings_information_manage');
    $canNews = current_user_can('settings_news_manage');
    $canServices = current_user_can('settings_services_manage');
    $canStatistics = current_user_can('settings_statistics_manage');
    $canTestimonials = current_user_can('settings_testimonials_manage');
    $canTopList = current_user_can('settings_top_list_manage');
    $canAgenda = current_user_can('settings_agenda_manage');
    $canJobFair = current_user_can('settings_job_fair_manage');
    $canVirtualKarir = current_user_can('settings_virtual_karir_service_manage');
    $canMitraKerja = current_user_can('settings_mitra_kerja_manage');
    $canPartnershipType = current_user_can('settings_partnership_type_manage');
    $canMitraSubmission = current_user_can('settings_mitra_submission_manage');
    $canKemitraanBooked = current_user_can('settings_kemitraan_booked_manage');
    $canPaskerRoom = current_user_can('settings_pasker_room_manage');
    $canDatabaseContact = current_user_can('settings_database_contact_manage');
    $canAccessControl = current_user_can('manage_access_control');
    $canBroadcast = current_user_can('use_broadcast');
    $canExtensions = current_user_can('view_extensions');
    $canAsmenDashboard = current_user_can('asmen_view_dashboard');
    $canAsmenAssets = current_user_can('asmen_manage_assets');
    $canAsmenServices = current_user_can('asmen_view_services');
    $canAsmenCalendar = current_user_can('asmen_view_calendar');
    $canAsmenQR = current_user_can('asmen_use_qr') || $canAsmenAssets;

    $hasDashboard = ($canDashJobs || $canDashSeekers);
    $hasMasterData = ($canJobs || $canJobSeekers);
    $hasSettings = ($canManageSettings || $canChart || $canContribution || $canInformation || $canNews || $canServices || $canStatistics || $canTestimonials || $canTopList || $canAgenda || $canJobFair || $canVirtualKarir || $canMitraKerja || $canAccessControl || $canBroadcast);
    $hasLayanan = ($canManageSettings || $canMitraKerja || $canPartnershipType || $canMitraSubmission || $canKemitraanBooked || $canPaskerRoom);
    $hasJejaring = ($canManageSettings || $canDatabaseContact);
    $hasAsmen = ($canAsmenDashboard || $canAsmenAssets || $canAsmenServices || $canAsmenCalendar || $canAsmenQR);
?>
            <ul class="navbar-nav ms-auto">
                <?php if ($hasDashboard): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="dashboardDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Dashboard
                        </a>
                    <ul class="dropdown-menu" aria-labelledby="dashboardDropdown">
                        <?php if ($canDashJobs || $canManageSettings): ?><li><a class="dropdown-item" href="dashboard_jobs.php">Dashboard Jobs</a></li><?php endif; ?>
                        <?php if ($canDashSeekers || $canManageSettings): ?><li><a class="dropdown-item" href="dashboard_job_seekers.php">Dashboard Job Seekers</a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if ($hasJejaring): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="jejaringDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Jejaring
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="jejaringDropdown">
                        <?php if ($canManageSettings || $canDatabaseContact): ?><li><a class="dropdown-item" href="database_contact.php">Database Contact</a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if ($hasMasterData): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="masterDataDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Master Data
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="masterDataDropdown">
                        <?php if ($canJobs || $canManageSettings): ?><li><a class="dropdown-item" href="jobs.php">Jobs</a></li><?php endif; ?>
                        <?php if ($canJobSeekers || $canManageSettings): ?><li><a class="dropdown-item" href="job_seekers.php">Job Seekers</a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if ($hasSettings): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="settingsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Settings
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="settingsDropdown">
                        <?php if ($canManageSettings || $canChart): ?><li><a class="dropdown-item" href="chart_settings.php">Chart Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canContribution): ?><li><a class="dropdown-item" href="contribution_settings.php">Contribution Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canInformation): ?><li><a class="dropdown-item" href="information_settings.php">Information Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canNews): ?><li><a class="dropdown-item" href="news_settings.php">News Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canServices): ?><li><a class="dropdown-item" href="services_settings.php">Services Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canStatistics): ?><li><a class="dropdown-item" href="statistics_settings.php">Statistics Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canTestimonials): ?><li><a class="dropdown-item" href="testimonials_settings.php">Testimonial Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canTopList): ?><li><a class="dropdown-item" href="top_list_settings.php">Top List Settings</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($canManageSettings || $canAgenda): ?><li><a class="dropdown-item" href="agenda_settings.php">Agenda Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canJobFair): ?><li><a class="dropdown-item" href="job_fair_settings.php">Job Fair Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canVirtualKarir): ?><li><a class="dropdown-item" href="virtual_karir_service_settings.php">Virtual Karir Service Settings</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($canManageSettings || $canMitraKerja): ?><li><a class="dropdown-item" href="mitra_kerja_settings.php">Mitra Kerja Settings</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($canManageSettings || $canAccessControl): ?><li><a class="dropdown-item" href="access_control.php">Access Control</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($canManageSettings || $canBroadcast): ?><li><a class="dropdown-item" href="broadcast.php">Broadcast</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($canManageSettings): ?><li><a class="dropdown-item" href="karirhub_ads_settings.php">KarirHub Ads Settings</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($canManageSettings): ?><li><a class="dropdown-item" href="cron_settings.php">Other Settings</a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if ($hasLayanan): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="layananDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Layanan
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="layananDropdown">
                        <?php if ($canManageSettings || $canPartnershipType): ?><li><a class="dropdown-item" href="partnership_type_settings.php">Partnership Type Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canMitraSubmission): ?><li><a class="dropdown-item" href="kemitraan_submission.php">Mitra Kerja Submission</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canKemitraanBooked): ?><li><a class="dropdown-item" href="kemitraan_booked.php">Kemitraan Booked</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canPaskerRoom): ?><li><a class="dropdown-item" href="pasker_room_settings.php">Pasker Room Settings</a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if ($hasAsmen): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="asmenDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        AsMen
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="asmenDropdown">
                        <?php if ($canAsmenDashboard): ?><li><a class="dropdown-item" href="asmen_feature/asmen_dashboard.php">Dashboard</a></li><?php endif; ?>
                        <?php if ($canAsmenAssets): ?><li><a class="dropdown-item" href="asmen_feature/asmen_assets.php">Assets</a></li><?php endif; ?>
                        <?php if ($canAsmenServices): ?><li><a class="dropdown-item" href="asmen_feature/asmen_services.php">Services</a></li><?php endif; ?>
                        <?php if ($canAsmenCalendar): ?><li><a class="dropdown-item" href="asmen_feature/asmen_calendar.php">Calendar</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($canAsmenQR): ?><li><a class="dropdown-item" href="asmen_feature/asmen_qr_scan.php">QR Scanner</a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="backup.php">Backup</a>
                </li>
                <?php if ($canExtensions || $canManageSettings): ?>
                <li class="nav-item">
                    <a class="nav-link" href="extensions.php">Extensions</a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<!-- End Navigation Bar --> 