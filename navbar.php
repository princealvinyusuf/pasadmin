<?php require_once __DIR__ . '/access_helper.php'; ?>
<?php
    // Determine context early so brand link uses correct root
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $isAsmenContext = strpos($scriptName, '/asmen_feature/') !== false;
    $isJejaringContext = strpos($scriptName, '/jejaring/') !== false;
    $isBackupContext = strpos($scriptName, '/backup/') !== false;
    $isSubdirContext = ($isAsmenContext || $isJejaringContext || $isBackupContext);
    // Compute absolute base URL for this app (ending with /pasadmin/)
    $appRootMarker = '/pasadmin/';
    $posApp = strpos($scriptName, $appRootMarker);
    $appBaseUrl = ($posApp !== false) ? substr($scriptName, 0, $posApp + strlen($appRootMarker)) : '/';
    // Absolute prefixes
    $rootUrl = $appBaseUrl; // e.g., /pasadmin/
    $asmenUrl = $appBaseUrl . 'asmen_feature/';
    $jejaringUrl = $appBaseUrl . 'jejaring/';
    // Back-compat variables used below
    $rootPrefix = $rootUrl;
    $asmenPrefix = $asmenUrl;
    $jejaringPrefix = $jejaringUrl;
?>
<?php // Ensure favicon is set on pages that include the navbar ?>
<link rel="icon" href="https://paskerid.kemnaker.go.id/paskerid/public/images/services/logo.png" type="image/png">
<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="<?php echo isset($rootPrefix) ? $rootPrefix : ''; ?>index.php">
            <img src="https://paskerid.kemnaker.go.id/paskerid/public/images/services/logo.png" alt="Logo" style="height:24px; width:auto;" class="me-2">
            Job Admin
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
<?php
    $canDashKebutuhan = current_user_can('view_dashboard_kebutuhan_tk');
    $canDashPersediaan = current_user_can('view_dashboard_persediaan_tk');
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
    $canApiKeys = current_user_can('manage_api_keys');
    $canAsmenDashboard = current_user_can('asmen_view_dashboard');
    $canAsmenAssets = current_user_can('asmen_manage_assets');
    $canAsmenServices = current_user_can('asmen_view_services');
    $canAsmenCalendar = current_user_can('asmen_view_calendar');
    $canAsmenQR = current_user_can('asmen_use_qr') || $canAsmenAssets;

    // Show Dashboard if user can view any dashboard or manage settings
    $hasDashboard = ($canDashKebutuhan || $canDashPersediaan || current_user_can('manage_settings'));
    
    $hasSettings = ($canManageSettings || $canChart || $canContribution || $canInformation || $canNews || $canServices || $canStatistics || $canTestimonials || $canTopList || $canAgenda || $canJobFair || $canVirtualKarir || $canMitraKerja || $canAccessControl || $canBroadcast);
    $hasApiKeys = ($canManageSettings || $canApiKeys);
    $hasLayanan = ($canManageSettings || $canMitraKerja || $canPartnershipType || $canMitraSubmission || $canKemitraanBooked || $canPaskerRoom);
    $canJejaringTahapan = current_user_can('jejaring_tahapan_manage');
    $hasJejaring = ($canManageSettings || $canDatabaseContact || $canJejaringTahapan);
    $hasAsmen = ($canAsmenDashboard || $canAsmenAssets || $canAsmenServices || $canAsmenCalendar || $canAsmenQR);
    // Naker Award flags
    $canNakerAssessment = current_user_can('naker_award_manage_assessment');
    $canNakerStage1 = current_user_can('naker_award_view_stage1') || $canNakerAssessment || $canManageSettings;
    $canNakerSecond = current_user_can('naker_award_manage_second') || $canManageSettings;
    $canNakerStage2 = current_user_can('naker_award_view_stage2') || $canNakerSecond || $canManageSettings;
    $canNakerThird = current_user_can('naker_award_manage_third') || $canManageSettings;
    $canNakerVerify = current_user_can('naker_award_verify') || $canManageSettings;
    $canNakerFinal = current_user_can('naker_award_final_nominees') || $canManageSettings;
    $hasNakerAward = (
        $canNakerAssessment ||
        $canNakerStage1 ||
        $canNakerSecond ||
        $canNakerStage2 ||
        $canNakerThird ||
        $canNakerVerify ||
        $canNakerFinal ||
        $canManageSettings
    );
?>
<?php // context already computed above ?>
            <ul class="navbar-nav ms-auto">
                <?php if ($hasDashboard): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="dashboardDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Dashboard
                        </a>
                    <ul class="dropdown-menu" aria-labelledby="dashboardDropdown">
                            
                            
                        <li><hr class="dropdown-divider"></li>
                            <?php if ($canDashKebutuhan || $canManageSettings): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>dashboard_kebutuhan_tenaga_kerja.php">Dashboard Kebutuhan Tenaga Kerja</a></li><?php endif; ?>
                            <?php if ($canDashPersediaan || $canManageSettings): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>dashboard_persediaan_tenaga_kerja.php">Dashboard Persediaan Tenaga Kerja</a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if ($hasJejaring): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="jejaringDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Jejaring
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="jejaringDropdown">
                        <?php if ($canManageSettings || $canDatabaseContact): ?><li><a class="dropdown-item" href="<?php echo $jejaringUrl; ?>database_contact.php">Database Contact</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canJejaringTahapan): ?><li><a class="dropdown-item" href="<?php echo $jejaringUrl; ?>tahapan/index.php">Tahapan Kerjasama</a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if ($hasApiKeys): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="apiKeyDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        API Key
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="apiKeyDropdown">
                        <li><a class="dropdown-item" href="<?php echo $rootUrl; ?>api_keys.php">API Key Job Seekers</a></li>
                    </ul>
                </li>
                <?php endif; ?>
                
                <?php if ($hasSettings): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="settingsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Settings
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="settingsDropdown">
                        <?php if ($canManageSettings || $canChart): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>chart_settings.php">Chart Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canContribution): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>contribution_settings.php">Contribution Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canInformation): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>information_settings.php">Information Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canNews): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>news_settings.php">News Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canServices): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>services_settings.php">Services Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canStatistics): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>statistics_settings.php">Statistics Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canTestimonials): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>testimonials_settings.php">Testimonial Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canTopList): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>top_list_settings.php">Top List Settings</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($canManageSettings || $canAgenda): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>agenda_settings.php">Agenda Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canJobFair): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>job_fair_settings.php">Job Fair Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canVirtualKarir): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>virtual_karir_service_settings.php">Virtual Karir Service Settings</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($canManageSettings || current_user_can('view_db_sessions')): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>active_db_sessions.php">Active DB Sessions</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($canManageSettings || $canMitraKerja): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>mitra_kerja_settings.php">Mitra Kerja Settings</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($canManageSettings || $canAccessControl): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>access_control.php">Access Control</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($canManageSettings || $canBroadcast): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>broadcast.php">Broadcast</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($canManageSettings): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>karirhub_ads_settings.php">KarirHub Ads Settings</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($canManageSettings): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>cron_settings.php">Other Settings</a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if (current_user_can('view_audit_trails') || $canManageSettings): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $rootUrl; ?>audit_trails.php">Audit Trails</a>
                </li>
                <?php endif; ?>
                <?php if ($hasLayanan): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="layananDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Layanan
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="layananDropdown">
                        <?php if ($canManageSettings || $canPartnershipType): ?><li><a class="dropdown-item" href="<?php echo $rootPrefix; ?>partnership_type_settings.php">Partnership Type Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canMitraSubmission): ?><li><a class="dropdown-item" href="<?php echo $rootPrefix; ?>kemitraan_submission.php">Mitra Kerja Submission</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canKemitraanBooked): ?><li><a class="dropdown-item" href="<?php echo $rootPrefix; ?>kemitraan_booked.php">Kemitraan Booked</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canPaskerRoom): ?><li><a class="dropdown-item" href="<?php echo $rootPrefix; ?>pasker_room_settings.php">Pasker Room Settings</a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if ($hasAsmen): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="asmenDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        AsMen
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="asmenDropdown">
                        <?php if ($canAsmenDashboard): ?><li><a class="dropdown-item" href="<?php echo $asmenPrefix; ?>asmen_dashboard.php">Dashboard</a></li><?php endif; ?>
                        <?php if ($canAsmenAssets): ?><li><a class="dropdown-item" href="<?php echo $asmenPrefix; ?>asmen_assets.php">Assets</a></li><?php endif; ?>
                        <?php if ($canAsmenServices): ?><li><a class="dropdown-item" href="<?php echo $asmenPrefix; ?>asmen_services.php">Services</a></li><?php endif; ?>
                        <?php if ($canAsmenCalendar): ?><li><a class="dropdown-item" href="<?php echo $asmenPrefix; ?>asmen_calendar.php">Calendar</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($canAsmenQR): ?><li><a class="dropdown-item" href="<?php echo $asmenPrefix; ?>asmen_qr_scan.php">QR Scanner</a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if ($hasNakerAward): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="nakerAwardDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        WLLP Award
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="nakerAwardDropdown">
                        <?php if ($canNakerAssessment || $canManageSettings): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>naker_award_initial_assessment.php">Initial Assessment</a></li><?php endif; ?>
                        <?php if ($canManageSettings): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>naker_award_bobot_settings.php">Bobot Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>naker_award_interval_settings.php">Interval Settings</a></li><?php endif; ?>
                        <?php if ($canNakerStage1 || $canManageSettings): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>naker_award_stage1_shortlisted_c.php">Stage 1 Shortlisted C</a></li><?php endif; ?>
                        <?php if ($canNakerSecond): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>naker_award_second_assessment.php">Second Assessment</a></li><?php endif; ?>
                        <?php if ($canNakerStage2): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>naker_award_stage2_shortlisted_c.php">Stage 2 Shortlisted C</a></li><?php endif; ?>
                        <?php if ($canNakerThird): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>naker_award_third_assessment.php">Third Assessment</a></li><?php endif; ?>
                        <?php if ($canNakerVerify): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>naker_award_verification.php">Verification</a></li><?php endif; ?>
                        <?php if ($canNakerFinal): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>naker_award_final_nominees.php">Final Nominees</a></li><?php endif; ?>
                        <?php if (current_user_can('naker_award_backup_nominees')): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>naker_award_backup_nominees.php">Backup Data Nominees</a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if (current_user_is_super_admin()): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $rootUrl; ?>backup/">Backup</a>
                </li>
                <?php endif; ?>
                <?php if ($canExtensions || $canManageSettings): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $rootUrl; ?>extensions.php">Extensions</a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $rootUrl; ?>split_screen.php">
                        <i class="bi bi-layout-split me-1"></i>Split Screen
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $rootUrl; ?>logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<!-- End Navigation Bar --> 