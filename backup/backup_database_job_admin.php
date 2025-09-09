<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../db.php';
require_once '../access_helper.php';
if (!current_user_is_super_admin()) {
	http_response_code(403);
	echo json_encode(['success' => false, 'message' => 'Forbidden: Super admin access required.']);
	exit;
}

// Database credentials from db.php
$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$db_name = "job_admin_prod"; // The database to backup

// Try to locate mysqldump (cross-platform)
$mysqldump_path = '';

// Windows: use 'where'
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
	$win_output = [];
	$win_return = 0;
	exec('where mysqldump 2> NUL', $win_output, $win_return);
	if ($win_return === 0 && !empty($win_output)) {
		$mysqldump_path = $win_output[0];
	}
}

// Unix-like: try common paths and 'which'
if (empty($mysqldump_path)) {
	$paths = ['mysqldump', '/usr/bin/mysqldump', '/usr/local/bin/mysqldump', '/opt/lampp/bin/mysqldump'];
	foreach ($paths as $path) {
		$check = @shell_exec("which $path 2>/dev/null");
		if (!empty($check)) {
			$mysqldump_path = trim($path);
			break;
		}
	}
}

if (empty($mysqldump_path)) {
	echo json_encode([
		'success' => false,
		'message' => 'mysqldump command not found. Please ensure MySQL is installed and mysqldump is in your PATH.',
		'details' => 'Tried Windows where and Unix which checks.'
	]);
	exit;
}

$backup_dir = __DIR__ . '/backups/';
if (!is_dir($backup_dir)) {
	mkdir($backup_dir, 0777, true);
}

$filename = 'job_admin_backup_' . date('Y-m-d_H-i-s') . '.sql';
$filepath = $backup_dir . $filename;

$command = sprintf(
	'%s --host=%s --user=%s --password=%s %s > %s 2>&1',
	escapeshellarg($mysqldump_path),
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
		'message' => 'Database backup (job_admin_prod) created successfully.',
		'filePath' => 'backups/' . $filename
	]);
} else {
	error_log('Mysqldump command failed: ' . implode("\n", $output));
	echo json_encode([
		'success' => false,
		'message' => 'Failed to create database backup for job_admin_prod.',
		'details' => implode("\n", $output)
	]);
}
?>



