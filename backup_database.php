<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once 'db.php'; // Include your database connection file for DB credentials

// Database credentials from db.php
$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$db_name = "job_admin_prod"; // The database to backup

$backup_dir = __DIR__ . '/backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

$filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
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
    echo json_encode([
        'success' => true,
        'message' => 'Database backup created successfully.',
        'filePath' => 'backups/' . $filename // Relative path for download
    ]);
} else {
    error_log('Mysqldump command failed: ' . implode("\n", $output));
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create database backup.',
        'details' => implode("\n", $output)
    ]);
}
?>
