<?php
header('Content-Type: application/json');

$status_file = __DIR__ . '/backup_service.status';
$action = $_POST['action'] ?? '';

$response = ['success' => false, 'message' => 'Invalid action.'];

if ($action === 'start') {
    file_put_contents($status_file, 'Running');
    $response = ['success' => true, 'message' => 'Backup service started.'];
} elseif ($action === 'stop') {
    file_put_contents($status_file, 'Stopped');
    $response = ['success' => true, 'message' => 'Backup service stopped.'];
} else if ($action === 'check') {
    $status = file_exists($status_file) ? trim(file_get_contents($status_file)) : 'Stopped';
    $response = ['success' => true, 'status' => $status];
}

echo json_encode($response);
?>


