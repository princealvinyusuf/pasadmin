<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

if (!current_user_can('pasker_drive_manage') && !current_user_can('manage_settings')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$fileId = intval($_GET['id'] ?? 0);
$userId = intval($_SESSION['user_id'] ?? 0);
if ($fileId <= 0 || $userId <= 0) {
    http_response_code(400);
    echo 'Invalid request.';
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'job_admin_prod');
$stmt = $conn->prepare('SELECT original_name, storage_name, mime_type FROM pasker_drive_files WHERE id=? AND owner_user_id=? AND is_trashed=0 LIMIT 1');
$stmt->bind_param('ii', $fileId, $userId);
$stmt->execute();
$stmt->bind_result($originalName, $storageName, $mimeType);
$found = $stmt->fetch();
$stmt->close();
$conn->close();

if (!$found) {
    http_response_code(404);
    echo 'File not found.';
    exit;
}

$absolutePath = __DIR__ . '/downloads/pasker_drive/' . basename((string)$storageName);
if (!is_file($absolutePath)) {
    http_response_code(404);
    echo 'Stored file missing.';
    exit;
}

$mime = (is_string($mimeType) && $mimeType !== '') ? $mimeType : 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . str_replace('"', '', (string)$originalName) . '"');
header('Content-Length: ' . filesize($absolutePath));
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($absolutePath);
exit;
