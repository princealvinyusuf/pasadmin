<?php
session_start();
require_once '../db.php';
require_once '../access_helper.php';
if (!current_user_is_super_admin()) {
    http_response_code(403);
    echo 'Forbidden: Super admin access required.';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* body {
            background-color: #f8f9fa;
        } */
        /* .container {
            margin-top: 50px;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        } */
        h2 {
            color: #343a40;
            margin-bottom: 20px;
        }
        .btn-group {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php include '../navbar.php'; ?>
    <div class="container">
        <h2 class="text-center">Database Backup Management</h2>
        <div class="row mt-4">
            <div class="col-md-6 offset-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Manual Backup</h5>
                        <p class="card-text">Create an immediate backup of the databases.</p>
                        <div class="d-grid gap-3 d-md-block">
                            <button id="downloadBackupBtn" class="btn btn-primary me-3"><i class="bi bi-download me-2"></i>Download .sql Backup (paskerid_db_prod)</button>
                            <button id="downloadJobAdminBackupBtn" class="btn btn-warning"><i class="bi bi-filetype-sql me-2"></i>Download .sql Backup (job_admin_prod)</button>
                        </div>
                        <div id="backupStatus" class="mt-3"></div>
                        <div id="jobAdminBackupStatus" class="mt-2"></div>
                        <div class="progress mt-2 d-none" id="jobAdminProgressWrap">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning" id="jobAdminProgressBar" role="progressbar" style="width: 0%">0%</div>
                        </div>
                        <div class="mt-2 d-none" id="jobAdminControls">
                            <button id="stopJobAdminBackupBtn" class="btn btn-outline-danger btn-sm"><i class="bi bi-stop-circle me-1"></i>Stop Backup</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col-md-6 offset-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Scheduled Backup Service</h5>
                        <p class="card-text">Manage the weekly scheduled backup service.</p>
                        <div class="btn-group" role="group">
                            <button id="startServiceBtn" class="btn btn-success"><i class="bi bi-play-circle me-2"></i>Start Service</button>
                            <button id="stopServiceBtn" class="btn btn-danger"><i class="bi bi-stop-circle me-2"></i>Stop Service</button>
                        </div>
                        <div id="serviceStatus" class="mt-3">Service Status: <span id="currentServiceStatus">Loading...</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Function to update service status
            function updateServiceStatus() {
                $.get('backup_service_status.php', function(data) {
                    $('#currentServiceStatus').text(data);
                });
            }

            // Initial service status load
            updateServiceStatus();
            setInterval(updateServiceStatus, 5000); // Update every 5 seconds

            $('#downloadBackupBtn').on('click', function() {
                $('#backupStatus').html('<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div> Backing up database...');
                $.ajax({
                    url: 'backup_database.php',
                    method: 'GET',
                    success: function(response) {
                        if (response.success) {
                            $('#backupStatus').html('<div class="alert alert-success" role="alert">' + response.message + ' <a href="' + response.filePath + '" class="alert-link">Download Backup</a></div>');
                        } else {
                            let errorMessage = response.message;
                            if (response.details) {
                                errorMessage += '<pre class="text-start mt-2">' + response.details + '</pre>';
                            }
                            $('#backupStatus').html('<div class="alert alert-danger" role="alert">Error: ' + errorMessage + '</div>');
                        }
                    },
                    error: function() {
                        $('#backupStatus').html('<div class="alert alert-danger" role="alert">An error occurred during backup.</div>');
                    }
                });
            });

            let jobAdminPollTimer = null;

            function pollJobAdminStatus() {
                $.get('status_job_admin_backup.php', function(res) {
                    if (!res || !res.success) { return; }
                    const st = res.status || {};
                    if (st.state === 'running') {
                        $('#jobAdminProgressWrap').removeClass('d-none');
                        $('#jobAdminControls').removeClass('d-none');
                        const percent = (st.percent !== null && st.percent !== undefined) ? parseInt(st.percent) : null;
                        if (percent !== null && !isNaN(percent)) {
                            $('#jobAdminProgressBar').css('width', percent + '%').text(percent + '%');
                        } else {
                            // Indeterminate: keep animation, show bytes written
                            const bytes = st.bytes_written || 0;
                            $('#jobAdminProgressBar').css('width', '100%').text('Working... ~' + bytes.toLocaleString() + ' bytes');
                        }
                    } else if (st.state === 'completed') {
                        clearInterval(jobAdminPollTimer);
                        jobAdminPollTimer = null;
                        $('#downloadJobAdminBackupBtn').prop('disabled', false);
                        $('#jobAdminProgressBar').removeClass('progress-bar-animated').css('width', '100%').text('100%');
                        const link = st.filePathRel ? '<a href="' + st.filePathRel + '" class="alert-link">Download Backup</a>' : '';
                        $('#jobAdminBackupStatus').html('<div class="alert alert-warning" role="alert">Backup completed. ' + link + '</div>');
                        $('#jobAdminControls').addClass('d-none');
                    } else if (st.state === 'error') {
                        clearInterval(jobAdminPollTimer);
                        jobAdminPollTimer = null;
                        $('#downloadJobAdminBackupBtn').prop('disabled', false);
                        $('#jobAdminProgressWrap').addClass('d-none');
                        $('#jobAdminControls').addClass('d-none');
                        const msg = st.message || 'Unknown error';
                        $('#jobAdminBackupStatus').html('<div class="alert alert-danger" role="alert">Error: ' + msg + '</div>');
                    } else if (st.state === 'stopped') {
                        clearInterval(jobAdminPollTimer);
                        jobAdminPollTimer = null;
                        $('#downloadJobAdminBackupBtn').prop('disabled', false);
                        $('#jobAdminProgressWrap').addClass('d-none');
                        $('#jobAdminControls').addClass('d-none');
                        $('#jobAdminBackupStatus').html('<div class="alert alert-secondary" role="alert">Backup stopped.</div>');
                    } else {
                        // idle or unknown
                        clearInterval(jobAdminPollTimer);
                        jobAdminPollTimer = null;
                        $('#downloadJobAdminBackupBtn').prop('disabled', false);
                        $('#jobAdminProgressWrap').addClass('d-none');
                        $('#jobAdminControls').addClass('d-none');
                    }
                });
            }

            $('#downloadJobAdminBackupBtn').on('click', function() {
                $('#downloadJobAdminBackupBtn').prop('disabled', true);
                $('#jobAdminBackupStatus').html('<div class="spinner-border text-warning" role="status"><span class="visually-hidden">Loading...</span></div> Starting backup for job_admin_prod...');
                $('#jobAdminProgressBar').addClass('progress-bar-animated').css('width', '0%').text('0%');
                $('#jobAdminProgressWrap').removeClass('d-none');
                $('#jobAdminControls').removeClass('d-none');

                $.ajax({
                    url: 'start_job_admin_backup.php',
                    method: 'POST',
                    success: function(response) {
                        if (response && response.success) {
                            $('#jobAdminBackupStatus').html('<div class="alert alert-warning" role="alert">Backup started. Tracking progress...</div>');
                            if (jobAdminPollTimer) { clearInterval(jobAdminPollTimer); }
                            jobAdminPollTimer = setInterval(pollJobAdminStatus, 1000);
                        } else {
                            let errorMessage = (response && response.message) ? response.message : 'Failed to start backup.';
                            $('#downloadJobAdminBackupBtn').prop('disabled', false);
                            $('#jobAdminProgressWrap').addClass('d-none');
                            $('#jobAdminControls').addClass('d-none');
                            $('#jobAdminBackupStatus').html('<div class="alert alert-danger" role="alert">Error: ' + errorMessage + '</div>');
                        }
                    },
                    error: function() {
                        $('#downloadJobAdminBackupBtn').prop('disabled', false);
                        $('#jobAdminProgressWrap').addClass('d-none');
                        $('#jobAdminControls').addClass('d-none');
                        $('#jobAdminBackupStatus').html('<div class="alert alert-danger" role="alert">An error occurred trying to start the backup.</div>');
                    }
                });
            });

            $('#stopJobAdminBackupBtn').on('click', function() {
                $('#stopJobAdminBackupBtn').prop('disabled', true);
                $.post('stop_job_admin_backup.php', {}, function(res) {
                    // UI will update on next poll
                    setTimeout(function() { $('#stopJobAdminBackupBtn').prop('disabled', false); }, 1000);
                });
            });

            // On page load, detect if a backup is already running and resume polling
            (function resumeIfRunning() {
                $.get('status_job_admin_backup.php', function(res) {
                    if (res && res.success && res.status && res.status.state === 'running') {
                        $('#downloadJobAdminBackupBtn').prop('disabled', true);
                        $('#jobAdminProgressWrap').removeClass('d-none');
                        $('#jobAdminControls').removeClass('d-none');
                        if (jobAdminPollTimer) { clearInterval(jobAdminPollTimer); }
                        jobAdminPollTimer = setInterval(pollJobAdminStatus, 1000);
                    }
                });
            })();

            $('#startServiceBtn').on('click', function() {
                $.post('backup_service_control.php', { action: 'start' }, function(response) {
                    if (response.success) {
                        alert('Backup service started.');
                        updateServiceStatus();
                    } else {
                        alert('Error starting service: ' + response.message);
                    }
                });
            });

            $('#stopServiceBtn').on('click', function() {
                $.post('backup_service_control.php', { action: 'stop' }, function(response) {
                    if (response.success) {
                        alert('Backup service stopped.');
                        updateServiceStatus();
                    } else {
                        alert('Error stopping service: ' + response.message);
                    }
                });
            });
        });
    </script>
</body>
</html>


