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
$db_name = "paskerid_db_prod"; // The database to backup

// Check if mysqldump is available
$mysqldump_path = '';
$paths = ['mysqldump', '/usr/bin/mysqldump', '/usr/local/bin/mysqldump', '/opt/lampp/bin/mysqldump'];
foreach ($paths as $path) {
    if (shell_exec("which $path 2>/dev/null")) {
        $mysqldump_path = $path;
        break;
    }
}

if (empty($mysqldump_path)) {
    echo json_encode([
        'success' => false,
        'message' => 'mysqldump command not found. Please ensure MySQL is installed and mysqldump is in your PATH.',
        'details' => 'Available paths checked: ' . implode(', ', $paths)
    ]);
    exit;
}

$backup_dir = __DIR__ . '/backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

$filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
$filepath = $backup_dir . $filename;

$command = sprintf(
    '%s --host=%s --user=%s --password=%s %s > %s 2>&1',
    $mysqldump_path,
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
