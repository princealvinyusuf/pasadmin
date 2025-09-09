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

$backup_dir = __DIR__ . '/backups/';
if (!is_dir($backup_dir)) {
	mkdir($backup_dir, 0777, true);
}

$filename = 'job_admin_backup_' . date('Y-m-d_H-i-s') . '.sql';
$filepath = $backup_dir . $filename;
$logpath = $backup_dir . str_replace('.sql', '.log', $filename);
$status_file = __DIR__ . '/job_admin_backup_status.json';

// Check if a backup is already running
if (file_exists($status_file)) {
	$existing = json_decode(@file_get_contents($status_file), true);
	if (is_array($existing) && ($existing['state'] ?? '') === 'running') {
		echo json_encode([
			'success' => false,
			'message' => 'A job_admin_prod backup is already running.',
			'status' => $existing
		]);
		exit;
	}
}

// Estimate database size in bytes for progress approximation
$db_size_bytes = 0;
try {
	// Use INFORMATION_SCHEMA with current credentials
	$query = "SELECT SUM(data_length + index_length) AS bytes FROM information_schema.TABLES WHERE table_schema = 'job_admin_prod'";
	$result = $conn->query($query);
	if ($result && $row = $result->fetch_assoc()) {
		$db_size_bytes = (int)($row['bytes'] ?? 0);
	}
} catch (Throwable $e) {
	// Ignore estimation errors; progress will be indeterminate until size available
}

$status = [
	'state' => 'running',
	'started_at' => date('c'),
	'filename' => $filename,
	'filePathRel' => 'backups/' . $filename,
	'filePathAbs' => $filepath,
	'log_path' => $logpath,
	'db_size_bytes' => $db_size_bytes,
	'progress' => 0
];
@file_put_contents($status_file, json_encode($status));

// Locate PHP binary
$php_path = 'php';
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
	$win_output = [];
	$win_return = 0;
	exec('where php 2> NUL', $win_output, $win_return);
	if ($win_return === 0 && !empty($win_output)) {
		$php_path = $win_output[0];
	}
} else {
	$which = @shell_exec('which php 2>/dev/null');
	if (!empty($which)) {
		$php_path = trim($which);
	}
}

$runner = __DIR__ . '/run_job_admin_dump.php';

// Build command to run the dump asynchronously
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
	$cmd = 'start /B "" ' . escapeshellarg($php_path) . ' ' . escapeshellarg($runner) . ' ' . escapeshellarg($filepath) . ' ' . escapeshellarg($logpath);
	@pclose(@popen($cmd, 'r'));
} else {
	$cmd = escapeshellarg($php_path) . ' ' . escapeshellarg($runner) . ' ' . escapeshellarg($filepath) . ' ' . escapeshellarg($logpath) . ' > /dev/null 2>&1 &';
	@exec($cmd);
}

echo json_encode([
	'success' => true,
	'message' => 'job_admin_prod backup started.',
	'status' => $status
]);
?>



