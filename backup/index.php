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
                        <div class="d-grid gap-2 d-md-block">
                            <button id="downloadBackupBtn" class="btn btn-primary me-2"><i class="bi bi-download me-2"></i>Download .sql Backup (paskerid_db_prod)</button>
                            <button id="downloadJobAdminBackupBtn" class="btn btn-warning"><i class="bi bi-filetype-sql me-2"></i>Download .sql Backup (job_admin_prod)</button>
                        </div>
                        <div id="backupStatus" class="mt-3"></div>
                        <div id="jobAdminBackupStatus" class="mt-2"></div>
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

            $('#downloadJobAdminBackupBtn').on('click', function() {
                $('#jobAdminBackupStatus').html('<div class="spinner-border text-warning" role="status"><span class="visually-hidden">Loading...</span></div> Backing up job_admin_prod database...');
                $.ajax({
                    url: 'backup_database_job_admin.php',
                    method: 'GET',
                    success: function(response) {
                        if (response.success) {
                            $('#jobAdminBackupStatus').html('<div class="alert alert-warning" role="alert">' + response.message + ' <a href="' + response.filePath + '" class="alert-link">Download Backup</a></div>');
                        } else {
                            let errorMessage = response.message;
                            if (response.details) {
                                errorMessage += '<pre class="text-start mt-2">' + response.details + '</pre>';
                            }
                            $('#jobAdminBackupStatus').html('<div class="alert alert-danger" role="alert">Error: ' + errorMessage + '</div>');
                        }
                    },
                    error: function() {
                        $('#jobAdminBackupStatus').html('<div class="alert alert-danger" role="alert">An error occurred during job_admin_prod backup.</div>');
                    }
                });
            });

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


