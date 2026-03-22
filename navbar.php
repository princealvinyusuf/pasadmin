<?php require_once __DIR__ . '/access_helper.php'; ?>
<?php
    // Determine context and robust app base path from URL-like server vars.
    $rawContextPath = (string)($_SERVER['REQUEST_URI'] ?? ($_SERVER['PHP_SELF'] ?? ($_SERVER['SCRIPT_NAME'] ?? '')));
    $isAsmenContext = strpos($rawContextPath, '/asmen_feature/') !== false;
    $isJejaringContext = strpos($rawContextPath, '/jejaring/') !== false;
    $isBackupContext = strpos($rawContextPath, '/backup/') !== false;
    $isSubdirContext = ($isAsmenContext || $isJejaringContext || $isBackupContext);

    $appBaseUrl = '/pasadmin/';
    $candidateVars = [
        $_SERVER['REQUEST_URI'] ?? '',
        $_SERVER['PHP_SELF'] ?? '',
        $_SERVER['SCRIPT_NAME'] ?? '',
    ];
    foreach ($candidateVars as $candidate) {
        $path = parse_url((string)$candidate, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            continue;
        }
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        foreach ($segments as $segment) {
            if (strcasecmp($segment, 'pasadmin') === 0) {
                $appBaseUrl = '/' . $segment . '/';
                break 2;
            }
        }
    }

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
<link rel="icon" href="https://paskerid.kemnaker.go.id/images/services/logo.png" type="image/png">
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-VVRKTYE9YB"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-VVRKTYE9YB');
</script>
<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="<?php echo isset($rootPrefix) ? $rootPrefix : ''; ?>index.php">
            <img src="https://paskerid.kemnaker.go.id/images/services/logo.png" alt="Logo" style="height:24px; width:auto;" class="me-2">
            Job Admin
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
<?php
    $canDashKebutuhan = current_user_can('view_dashboard_kebutuhan_tk');
    $canDashPersediaan = current_user_can('view_dashboard_persediaan_tk');
    $canDashBlk = current_user_can('view_dashboard_blk');
    $canDashIntegrasiKarirhubMitra = current_user_can('view_dashboard_integrasi_karirhub_mitra');
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
    $canIntegrasiKarirhubMitraSettings = current_user_can('settings_integrasi_karirhub_mitra_manage');
    $canPartnershipType = current_user_can('settings_partnership_type_manage');
    $canMitraSubmission = current_user_can('settings_mitra_submission_manage');
    $canKemitraanBooked = current_user_can('settings_kemitraan_booked_manage');
    $canPaskerRoom = current_user_can('settings_pasker_room_manage');
    $canWalkinGallery = current_user_can('walkin_gallery_manage') || current_user_can('manage_settings');
    $canDatabaseContact = current_user_can('settings_database_contact_manage');
    $canIframe = current_user_can('settings_iframe_manage');
    $canRegistrasiKehadiran = current_user_can('registrasi_kehadiran_manage');
    $canSplitScreen = current_user_can('split_screen_access') || $canManageSettings;
    $canOUI = current_user_can('oui_access') || $canManageSettings;
    $canAuditTrails = current_user_can('view_audit_trails');
    $canAccessControl = current_user_can('manage_access_control');
    $canBroadcast = current_user_can('use_broadcast');
    $canEmailNotification = current_user_can('use_email_notification');
    $canExtensions = current_user_can('view_extensions');
    $canApiKeys = current_user_can('manage_api_keys');
    $canAsmenDashboard = current_user_can('asmen_view_dashboard');
    $canAsmenAssets = current_user_can('asmen_manage_assets');
    $canAsmenServices = current_user_can('asmen_view_services');
    $canAsmenCalendar = current_user_can('asmen_view_calendar');
    $canAsmenQR = current_user_can('asmen_use_qr') || $canAsmenAssets;

    // Show Dashboard if user can view any dashboard or manage settings
    $hasDashboard = ($canDashKebutuhan || $canDashPersediaan || $canDashBlk || $canDashIntegrasiKarirhubMitra || current_user_can('manage_settings'));
    $hasBlk = ($canDashBlk || $canManageSettings);
    
    $hasSettings = ($canManageSettings || $canChart || $canContribution || $canInformation || $canNews || $canServices || $canStatistics || $canTestimonials || $canTopList || $canAgenda || $canJobFair || $canVirtualKarir || $canMitraKerja || $canIntegrasiKarirhubMitraSettings || $canAccessControl || $canBroadcast || $canEmailNotification || $canIframe || $canAuditTrails);
    $hasApiKeys = ($canManageSettings || $canApiKeys);
    $canCareerBoostDay = current_user_can('career_boost_day_manage') || $canManageSettings;
    $canCareerBoostDayPic = current_user_can('career_boost_day_pic_manage') || $canManageSettings;
    $canCareerBoostDayBooked = current_user_can('career_boost_day_booked_view') || $canManageSettings;
    $canCareerBoostDayTestimonial = current_user_can('career_boost_day_testimonial_manage') || $canCareerBoostDay || $canManageSettings;
    $canCareerBoostDayAttendance = $canCareerBoostDay;
    $canFormHasilKonseling = current_user_can('form_hasil_konseling_manage') || $canManageSettings;
    $canMiniJobi = current_user_can('settings_minijobi_manage') || $canManageSettings;
    $canCareerBoostDaySlot = $canCareerBoostDay; // same permission
    $canWalkinSurvey = current_user_can('walkin_survey_manage') || $canManageSettings;
    $canPaskerDrive = current_user_can('pasker_drive_manage') || $canManageSettings;

    $hasLayanan = (
        $canManageSettings ||
        $canMitraKerja ||
        $canPartnershipType ||
        $canMitraSubmission ||
        $canKemitraanBooked ||
        $canPaskerRoom ||
        $canCareerBoostDay ||
        $canCareerBoostDayPic ||
        $canCareerBoostDayBooked ||
        $canCareerBoostDayAttendance ||
        $canFormHasilKonseling ||
        $canMiniJobi
        || $canWalkinSurvey
    );
    $canJejaringTahapan = current_user_can('jejaring_tahapan_manage');
    $hasJejaring = ($canManageSettings || $canDatabaseContact || $canJejaringTahapan);
    $hasAsmen = ($canAsmenDashboard || $canAsmenAssets || $canAsmenServices || $canAsmenCalendar || $canAsmenQR);
    $hasExtensionsMenu = ($canExtensions || $canManageSettings || $canRegistrasiKehadiran || $canSplitScreen);
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
                            <?php if ($canDashKebutuhan || $canManageSettings): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>dashboard_kebutuhan_tenaga_kerja">Dashboard Kebutuhan Tenaga Kerja</a></li><?php endif; ?>
                            <?php if ($canDashPersediaan || $canManageSettings): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>dashboard_persediaan_tenaga_kerja">Dashboard Persediaan Tenaga Kerja</a></li><?php endif; ?>
                            <?php if ($canDashBlk || $canManageSettings): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>dashboard_blk">Dashboard BLK</a></li><?php endif; ?>
                            <?php if ($canDashIntegrasiKarirhubMitra || $canManageSettings): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>dashboard_monitoring_integrasi_karirhub_mitra">Dashboard Monitoring Integrasi Karirhub x Mitra</a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if ($hasBlk): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="blkDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        BLK
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="blkDropdown">
                        <li><a class="dropdown-item" href="<?php echo $rootUrl; ?>dashboard_blk">Dashboard Visualisasi BLK</a></li>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if ($hasJejaring): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="jejaringDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Jejaring
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="jejaringDropdown">
                        <?php if ($canManageSettings || $canDatabaseContact): ?><li><a class="dropdown-item" href="<?php echo $jejaringUrl; ?>database_contact">Database Contact</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canJejaringTahapan): ?><li><a class="dropdown-item" href="<?php echo $jejaringUrl; ?>tahapan/index">Tahapan Kerjasama</a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if ($hasApiKeys): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="apiKeyDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        API Key
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="apiKeyDropdown">
                        <li><a class="dropdown-item" href="<?php echo $rootUrl; ?>api_keys">API Key Job Seekers</a></li>
                    </ul>
                </li>
                <?php endif; ?>
                
                <?php if ($hasSettings): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="settingsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Settings
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="settingsDropdown">
                        <?php if ($canManageSettings || $canChart): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>chart_settings">Chart Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canContribution): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>contribution_settings">Contribution Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canInformation): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>information_settings">Information Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canNews): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>news_settings">News Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canServices): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>services_settings">Services Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canStatistics): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>statistics_settings">Statistics Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canTestimonials): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>testimonials_settings">Testimonial Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canTopList): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>top_list_settings">Top List Settings</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($canManageSettings || $canAgenda): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>agenda_settings">Agenda Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canJobFair): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>job_fair_settings">Job Fair Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canVirtualKarir): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>virtual_karir_service_settings">Virtual Karir Service Settings</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($canManageSettings || current_user_can('view_db_sessions')): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>active_db_sessions">Active DB Sessions</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($canManageSettings || $canMitraKerja): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>mitra_kerja_settings">Mitra Kerja Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canIntegrasiKarirhubMitraSettings): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>dashboard_monitoring_integrasi_karirhub_mitra_settings">Monitoring Integrasi Karirhub x Mitra Settings</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($canManageSettings || $canAccessControl): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>access_control">Access Control</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canAuditTrails): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>audit_trails">Audit Trails</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($canManageSettings || $canBroadcast): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>broadcast">Broadcast</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canEmailNotification): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>email_notification">Email Notification</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($canManageSettings): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>karirhub_ads_settings">KarirHub Ads Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>split_screen_settings">Split Screen Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canIframe): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>iframe_settings">iFrame Settings</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($canManageSettings): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>cron_settings">Other Settings</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if (current_user_is_super_admin()): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>backup/">Backup</a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if ($hasLayanan): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="layananDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Layanan
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="layananDropdown">
                        <?php if ($canManageSettings || $canPartnershipType): ?><li><a class="dropdown-item" href="<?php echo $rootPrefix; ?>partnership_type_settings">Partnership Type Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canMitraSubmission): ?><li><a class="dropdown-item" href="<?php echo $rootPrefix; ?>kemitraan_submission">Mitra Kerja Submission</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canKemitraanBooked): ?><li><a class="dropdown-item" href="<?php echo $rootPrefix; ?>kemitraan_booked">Kemitraan Booked</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canPaskerRoom): ?><li><a class="dropdown-item" href="<?php echo $rootPrefix; ?>pasker_room_settings">Pasker Room Settings</a></li><?php endif; ?>
                        <?php if ($canMiniJobi): ?><li><a class="dropdown-item" href="<?php echo $rootPrefix; ?>mini_jobi_jobs">miniJobi Jobs</a></li><?php endif; ?>
                        <?php if (($canManageSettings || $canPaskerRoom) && ($canCareerBoostDay || $canCareerBoostDayPic || $canCareerBoostDayBooked || $canFormHasilKonseling || $canWalkinGallery)): ?><li><hr class="dropdown-divider"></li><?php endif; ?>
                        <?php if ($canCareerBoostDay): ?><li><a class="dropdown-item" href="<?php echo $rootPrefix; ?>career_boostday">Career Boost Day</a></li><?php endif; ?>
                        <?php if ($canCareerBoostDaySlot): ?><li><a class="dropdown-item" href="<?php echo $rootPrefix; ?>career_boostday_slot">Career Boost Day Jadwal</a></li><?php endif; ?>
                        <?php if ($canCareerBoostDayPic): ?><li><a class="dropdown-item" href="<?php echo $rootPrefix; ?>career_boostday_pic">Career Boost Day PIC</a></li><?php endif; ?>
                        <?php if ($canCareerBoostDayBooked): ?><li><a class="dropdown-item" href="<?php echo $rootPrefix; ?>career_boostday_booked">Career Boost Day Booked</a></li><?php endif; ?>
                        <?php if ($canCareerBoostDayAttendance): ?><li><a class="dropdown-item" href="<?php echo $rootPrefix; ?>career_boostday_attendance">Career Boost Day Konfirmasi Kehadiran</a></li><?php endif; ?>
                        <?php if ($canCareerBoostDayTestimonial): ?><li><a class="dropdown-item" href="<?php echo $rootPrefix; ?>career_boostday_testimonial">Career Boost Day Testimonial</a></li><?php endif; ?>
                        <?php if ($canFormHasilKonseling): ?><li><a class="dropdown-item" href="<?php echo $rootPrefix; ?>form_hasil_konseling">Form Hasil Konseling</a></li><?php endif; ?>
                        <?php if ($canFormHasilKonseling && $canWalkinSurvey): ?><li><hr class="dropdown-divider"></li><?php endif; ?>
                        <?php if ($canWalkinSurvey): ?><li><a class="dropdown-item" href="<?php echo $rootPrefix; ?>walkin_survey_initiator_settings">Walk-in Survey Initiators</a></li><?php endif; ?>
                        <?php if ($canWalkinSurvey): ?><li><a class="dropdown-item" href="<?php echo $rootPrefix; ?>walkin_survey_company_settings">Walk-in Survey Companies</a></li><?php endif; ?>
                        <?php if ($canWalkinSurvey): ?><li><a class="dropdown-item" href="<?php echo $rootPrefix; ?>walkin_survey_responses">Walk-in Survey Responses</a></li><?php endif; ?>
                        <?php if ($canWalkinSurvey): ?><li><a class="dropdown-item" href="<?php echo $rootPrefix; ?>walkin_survey_statistics">Walk-in Survey Statistik</a></li><?php endif; ?>
                        <?php if ($canWalkinSurvey): ?><li><a class="dropdown-item" href="<?php echo $rootPrefix; ?>walkin_survey_access_settings">Walk-in Survey Access</a></li><?php endif; ?>
                        <?php if ($canMitraSubmission || $canManageSettings): ?><li><a class="dropdown-item" href="<?php echo $rootPrefix; ?>walkin_form_access_settings">Walk-in Form Access</a></li><?php endif; ?>
                        <?php if ($canWalkinGallery): ?><li><hr class="dropdown-divider"></li><?php endif; ?>
                        <?php if ($canWalkinGallery): ?><li><a class="dropdown-item" href="<?php echo $rootPrefix; ?>walkin_gallery"><i class="bi bi-images me-1"></i>Walk-in Gallery</a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if ($hasAsmen): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="asmenDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        AsMen
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="asmenDropdown">
                        <?php if ($canAsmenDashboard): ?><li><a class="dropdown-item" href="<?php echo $asmenPrefix; ?>asmen_dashboard">Dashboard</a></li><?php endif; ?>
                        <?php if ($canAsmenAssets): ?><li><a class="dropdown-item" href="<?php echo $asmenPrefix; ?>asmen_assets">Assets</a></li><?php endif; ?>
                        <?php if ($canAsmenServices): ?><li><a class="dropdown-item" href="<?php echo $asmenPrefix; ?>asmen_services">Services</a></li><?php endif; ?>
                        <?php if ($canAsmenCalendar): ?><li><a class="dropdown-item" href="<?php echo $asmenPrefix; ?>asmen_calendar">Calendar</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <?php if ($canAsmenQR): ?><li><a class="dropdown-item" href="<?php echo $asmenPrefix; ?>asmen_qr_scan">QR Scanner</a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if ($hasNakerAward): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="nakerAwardDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        WLLP Award
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="nakerAwardDropdown">
                        <?php if ($canNakerAssessment || $canManageSettings): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>naker_award/naker_award_initial_assessment">Initial Assessment</a></li><?php endif; ?>
                        <?php if ($canManageSettings): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>naker_award/naker_award_bobot_settings">Bobot Settings</a></li><?php endif; ?>
                        <?php if ($canManageSettings): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>naker_award/naker_award_interval_settings">Interval Settings</a></li><?php endif; ?>
                        <?php if ($canNakerStage1 || $canManageSettings): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>naker_award/naker_award_stage1_shortlisted_c">Stage 1 Shortlisted C</a></li><?php endif; ?>
                        <?php if ($canNakerSecond): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>naker_award/naker_award_second_assessment">Second Assessment</a></li><?php endif; ?>
                        <?php if ($canNakerStage2): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>naker_award/naker_award_stage2_shortlisted_c">Stage 2 Shortlisted C</a></li><?php endif; ?>
                        <?php if ($canNakerThird): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>naker_award/naker_award_third_assessment">Third Assessment</a></li><?php endif; ?>
                        <?php if ($canNakerVerify): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>naker_award/naker_award_verification">Verification</a></li><?php endif; ?>
                        <?php if ($canNakerFinal): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>naker_award/naker_award_final_nominees">Final Nominees</a></li><?php endif; ?>
                        <?php if (current_user_can('naker_award_backup_nominees')): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>naker_award/naker_award_backup_nominees">Backup Data Nominees</a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if ($hasExtensionsMenu): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="extensionsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Extensions
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="extensionsDropdown">
                        <?php if ($canExtensions || $canManageSettings): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>extensions">Extensions</a></li><?php endif; ?>
                        <?php if ($canManageSettings || $canRegistrasiKehadiran): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>registrasi_kehadiran">Registrasi Kehadiran</a></li><?php endif; ?>
                        <?php if ($canSplitScreen): ?><li><a class="dropdown-item" href="<?php echo $rootUrl; ?>split_screen"><i class="bi bi-layout-split me-1"></i>Split Screen</a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php if ($canPaskerDrive): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $rootUrl; ?>pasker_drive"><i class="bi bi-cloud me-1"></i>Pasker Drive</a>
                </li>
                <?php endif; ?>
                <?php if ($canOUI): ?>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $rootUrl; ?>oui">OUI</a>
                </li>
                <?php endif; ?>
                <!-- <li class="nav-item">
                    <a class="nav-link" href="<?php echo $rootUrl; ?>classification_magang">
                        Magang
                    </a>
                </li> -->
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $rootUrl; ?>logout"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<!-- End Navigation Bar --> 