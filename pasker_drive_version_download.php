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

$versionId = intval($_GET['version_id'] ?? 0);
$userId = intval($_SESSION['user_id'] ?? 0);
$sessionUsername = strtolower((string)($_SESSION['username'] ?? ''));
$isDriveSuperUser = ($sessionUsername === 'datin_pasker');
if ($versionId <= 0 || $userId <= 0) {
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

$stmt = $conn->prepare("SELECT v.file_id, v.original_name, v.storage_name, v.mime_type, f.owner_user_id
                        FROM pasker_drive_file_versions v
                        JOIN pasker_drive_files f ON f.id=v.file_id
                        WHERE v.id=? LIMIT 1");
$stmt->bind_param('i', $versionId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    $conn->close();
    http_response_code(404);
    echo 'Version not found.';
    exit;
}

$fileId = intval($row['file_id']);
$allowed = $isDriveSuperUser || intval($row['owner_user_id']) === $userId;
if (!$allowed) {
    $uStmt = $conn->prepare("SELECT 1 FROM pasker_drive_permissions WHERE file_id=? AND principal_type='user' AND principal_id=? LIMIT 1");
    $uStmt->bind_param('ii', $fileId, $userId);
    $uStmt->execute();
    $uStmt->store_result();
    $allowed = $uStmt->num_rows > 0;
    $uStmt->close();
}
if (!$allowed) {
    $gStmt = $conn->prepare("SELECT group_id FROM user_access WHERE user_id=?");
    $gStmt->bind_param('i', $userId);
    $gStmt->execute();
    $gRes = $gStmt->get_result();
    $groupIds = [];
    while ($g = $gRes->fetch_assoc()) {
        $gid = intval($g['group_id']);
        if ($gid > 0) {
            $groupIds[] = $gid;
        }
    }
    $gStmt->close();
    if (!empty($groupIds)) {
        $in = implode(',', array_map('intval', array_unique($groupIds)));
        $rg = $conn->query("SELECT 1 FROM pasker_drive_permissions WHERE file_id=" . intval($fileId) . " AND principal_type='group' AND principal_id IN ($in) LIMIT 1");
        $allowed = $rg && $rg->num_rows > 0;
    }
}

$conn->close();
if (!$allowed) {
    http_response_code(403);
    echo 'No permission to access this version.';
    exit;
}

$absolutePath = __DIR__ . '/downloads/pasker_drive/' . basename((string)$row['storage_name']);
if (!is_file($absolutePath)) {
    http_response_code(404);
    echo 'Stored version file missing.';
    exit;
}

$size = filesize($absolutePath);
$mime = (is_string($row['mime_type']) && $row['mime_type'] !== '') ? $row['mime_type'] : 'application/octet-stream';
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', (string)$row['original_name']) . '"');
header('Content-Length: ' . $size);
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
readfile($absolutePath);
exit;
