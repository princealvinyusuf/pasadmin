<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if the backup service is running
$status_file = __DIR__ . '/backup_service.status';
$service_status = file_exists($status_file) ? trim(file_get_contents($status_file)) : 'Stopped';

if ($service_status === 'Running') {
    require_once 'db.php'; // Include your database connection file for DB credentials

    // Database credentials from db.php
    $host = DB_HOST;
    $user = DB_USER;
    $pass = DB_PASS;
    $db_name = "paskerid_db_prod"; // The database to backup

    $backup_dir = __DIR__ . '/backups/';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }

    $filename = 'scheduled_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backup_dir . $filename;

    $command = sprintf(
        'mysqldump --host=%s --user=%s --password=%s %s > %s',
        escapeshellarg($host),
        escapeshellarg($user),
        escapeshellarg($pass),
        escapeshellarg($db_name),
        escapeshellarg($filepath)
    );

    $output = [];
    $return_var = 0;

    exec($command, $output, $return_var);

    if ($return_var === 0) {
        error_log('Scheduled backup successful: ' . $filepath);
    } else {
        error_log('Scheduled backup failed: ' . implode("\n", $output));
    }
} else {
    error_log('Scheduled backup skipped: service is not running.');
}
?>
