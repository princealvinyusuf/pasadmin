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
	echo json_encode(['success' => true, 'message' => 'No running backup.']);
	exit;
}

$status = @json_decode(@file_get_contents($status_file), true) ?: [];
$status['request_stop'] = true;
@file_put_contents($status_file, json_encode($status));

echo json_encode(['success' => true, 'message' => 'Stop signal sent.']);
?>



