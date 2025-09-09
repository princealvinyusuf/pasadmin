<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once '../access_helper.php';
if (!current_user_is_super_admin()) {
	http_response_code(403);
	echo json_encode(['success' => false, 'message' => 'Forbidden: Super admin access required.']);
	exit;
}

$status_file = __DIR__ . '/job_admin_backup_status.json';

if (!file_exists($status_file)) {
	echo json_encode(['success' => true, 'status' => ['state' => 'idle']]);
	exit;
}

$status = json_decode(file_get_contents($status_file), true);
if (!is_array($status)) {
	$status = ['state' => 'idle'];
}

// Compute percent if possible
$bytes_written = (int)($status['bytes_written'] ?? 0);
$db_size_bytes = (int)($status['db_size_bytes'] ?? 0);
$percent = null;
if ($db_size_bytes > 0) {
	$percent = max(0, min(100, (int)round(($bytes_written / $db_size_bytes) * 100)));
}
$status['percent'] = $percent;

echo json_encode(['success' => true, 'status' => $status]);
?>



