<?php
session_start();
if (!isset($_SESSION['loggedin'])) {
    header('Location: login.php');
    exit;
}
require_once 'db.php'; // Include your database connection file
require_once 'access_control.php'; // For access control

$user_role = $_SESSION['role'];
checkAccess($user_role, 'Backup'); // Assuming 'Backup' is the new access level
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
        body {
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 50px;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
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
    <?php include 'navbar.php'; ?>
    <div class="container">
        <h2 class="text-center">Database Backup Management</h2>
        <div class="row mt-4">
            <div class="col-md-6 offset-md-3">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">Manual Backup</h5>
                        <p class="card-text">Create an immediate backup of the database.</p>
                        <button id="downloadBackupBtn" class="btn btn-primary"><i class="bi bi-download me-2"></i>Download .sql Backup</button>
                        <div id="backupStatus" class="mt-3"></div>
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
                            $('#backupStatus').html('<div class="alert alert-danger" role="alert">Error: ' + response.message + '</div>');
                        }
                    },
                    error: function() {
                        $('#backupStatus').html('<div class="alert alert-danger" role="alert">An error occurred during backup.</div>');
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
