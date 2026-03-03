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

$conn->query("CREATE TABLE IF NOT EXISTS pasker_drive_permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_id BIGINT UNSIGNED NOT NULL,
    principal_type ENUM('user','group') NOT NULL,
    principal_id INT NOT NULL,
    role ENUM('viewer','commenter','editor') NOT NULL DEFAULT 'viewer',
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_file_principal (file_id, principal_type, principal_id),
    INDEX idx_principal (principal_type, principal_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$stmt = $conn->prepare('SELECT id, owner_user_id, original_name, storage_name, mime_type FROM pasker_drive_files WHERE id=? AND is_trashed=0 LIMIT 1');
$stmt->bind_param('i', $fileId);
$stmt->execute();
$res = $stmt->get_result();
$file = $res->fetch_assoc();
$stmt->close();

if (!$file) {
    $conn->close();
    http_response_code(404);
    echo 'File not found.';
    exit;
}

$allowed = intval($file['owner_user_id']) === $userId;
if (!$allowed) {
    $pStmt = $conn->prepare("SELECT 1 FROM pasker_drive_permissions WHERE file_id=? AND principal_type='user' AND principal_id=? LIMIT 1");
    $pStmt->bind_param('ii', $fileId, $userId);
    $pStmt->execute();
    $pStmt->store_result();
    $allowed = $pStmt->num_rows > 0;
    $pStmt->close();
}
if (!$allowed) {
    $grpStmt = $conn->prepare("SELECT group_id FROM user_access WHERE user_id=?");
    $grpStmt->bind_param('i', $userId);
    $grpStmt->execute();
    $grpRes = $grpStmt->get_result();
    $gids = [];
    while ($g = $grpRes->fetch_assoc()) {
        $gid = intval($g['group_id']);
        if ($gid > 0) {
            $gids[] = $gid;
        }
    }
    $grpStmt->close();
    if (!empty($gids)) {
        $in = implode(',', array_map('intval', array_unique($gids)));
        $resG = $conn->query("SELECT 1 FROM pasker_drive_permissions WHERE file_id=" . intval($fileId) . " AND principal_type='group' AND principal_id IN ($in) LIMIT 1");
        $allowed = $resG && $resG->num_rows > 0;
    }
}
if (!$allowed) {
    $pubStmt = $conn->prepare("SELECT 1 FROM pasker_drive_shares WHERE file_id=? AND access_type='anyone_with_link' AND (expires_at IS NULL OR expires_at >= NOW()) LIMIT 1");
    $pubStmt->bind_param('i', $fileId);
    $pubStmt->execute();
    $pubStmt->store_result();
    $allowed = $pubStmt->num_rows > 0;
    $pubStmt->close();
}

$conn->close();
if (!$allowed) {
    http_response_code(403);
    echo 'No permission to preview this file.';
    exit;
}

$absolutePath = __DIR__ . '/downloads/pasker_drive/' . basename((string)$file['storage_name']);
if (!is_file($absolutePath)) {
    http_response_code(404);
    echo 'Stored file missing.';
    exit;
}

$mime = (is_string($file['mime_type']) && $file['mime_type'] !== '') ? $file['mime_type'] : 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . str_replace('"', '', (string)$file['original_name']) . '"');
header('Content-Length: ' . filesize($absolutePath));
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($absolutePath);
exit;
