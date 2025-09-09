<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../db.php';

$status_file = __DIR__ . '/job_admin_backup_status.json';

// Args: [1] target filepath, [2] log path
$targetFile = $argv[1] ?? '';
$logPath = $argv[2] ?? '';

if ($targetFile === '' || $logPath === '') {
	exit(1);
}

$host = DB_HOST;
$user = DB_USER;
$pass = DB_PASS;
$db_name = 'job_admin_prod';

// Locate mysqldump
$mysqldump_path = '';
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
	$win_output = [];
	$win_return = 0;
	exec('where mysqldump 2> NUL', $win_output, $win_return);
	if ($win_return === 0 && !empty($win_output)) {
		$mysqldump_path = $win_output[0];
	}
}
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
	$status = ['state' => 'error', 'message' => 'mysqldump not found'];
	@file_put_contents($status_file, json_encode($status));
	exit(1);
}

// Use minimal lock options for large production dump
$command = sprintf(
	'%s --host=%s --user=%s --password=%s --single-transaction --quick --routines --events --triggers %s > %s 2> %s',
	escapeshellarg($mysqldump_path),
	escapeshellarg($host),
	escapeshellarg($user),
	escapeshellarg($pass),
	escapeshellarg($db_name),
	escapeshellarg($targetFile),
	escapeshellarg($logPath)
);

// Initialize status
$status = @json_decode(@file_get_contents($status_file), true) ?: [];
$status['state'] = 'running';
$status['pid'] = getmypid();
$status['bytes_written'] = 0;
$status['request_stop'] = false;
@file_put_contents($status_file, json_encode($status));

// Start dump process
$descriptorspec = [
	0 => ['pipe', 'r'],
	1 => ['pipe', 'w'],
	2 => ['file', $logPath, 'a']
];

$process = proc_open($command, $descriptorspec, $pipes);
if (!is_resource($process)) {
	$status['state'] = 'error';
	$status['message'] = 'Failed to start dump process';
	@file_put_contents($status_file, json_encode($status));
	exit(1);
}

fclose($pipes[0]);
stream_set_blocking($pipes[1], false);

// Poll output file size to estimate progress
while (true) {
	$stat = proc_get_status($process);
	$running = $stat && $stat['running'];
	$bytes = file_exists($targetFile) ? filesize($targetFile) : 0;
	$status['bytes_written'] = $bytes;

	// Check for stop request
	$current = @json_decode(@file_get_contents($status_file), true) ?: [];
	if (!empty($current['request_stop'])) {
		$status['state'] = 'stopping';
		@file_put_contents($status_file, json_encode($status));
		// Try to terminate gracefully
		@proc_terminate($process);
		usleep(500000);
		$stat = proc_get_status($process);
		if ($stat && $stat['running']) {
			// Force kill if still running
			@proc_terminate($process, 9);
		}
		break;
	}

	@file_put_contents($status_file, json_encode($status));
	if (!$running) break;
	usleep(500000); // 500ms
}

$exit_code = proc_close($process);

// Finalize status
$status['exit_code'] = $exit_code;
if ($exit_code === 0) {
	$status['state'] = 'completed';
} else {
	// If stopping, mark stopped instead of error
	$cur = @json_decode(@file_get_contents($status_file), true) ?: [];
	if (($cur['state'] ?? '') === 'stopping' || !empty($cur['request_stop'])) {
		$status['state'] = 'stopped';
		$status['message'] = 'Backup was stopped by user.';
	} else {
		$status['state'] = 'error';
		$status['message'] = 'mysqldump exited with code ' . $exit_code;
	}
}
@file_put_contents($status_file, json_encode($status));

exit($exit_code);
?>



