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

function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES);
}

function flash_set(string $key, string $message): void {
    $_SESSION[$key] = $message;
}

function flash_get(string $key): ?string {
    if (!isset($_SESSION[$key])) {
        return null;
    }
    $msg = (string)$_SESSION[$key];
    unset($_SESSION[$key]);
    return $msg;
}

function format_size(int $bytes): string {
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    $units = ['KB', 'MB', 'GB', 'TB'];
    $value = $bytes / 1024;
    $u = 0;
    while ($value >= 1024 && $u < count($units) - 1) {
        $value /= 1024;
        $u++;
    }
    return number_format($value, 2) . ' ' . $units[$u];
}

function sanitize_display_name(string $name): string {
    $name = trim($name);
    $name = preg_replace('/[[:cntrl:]]+/', '', $name);
    $name = str_replace(['/', '\\'], '-', $name);
    if ($name === '') {
        $name = 'file-' . time();
    }
    return mb_substr($name, 0, 255);
}

function normalize_folder_path(string $path): string {
    $path = trim(str_replace('\\', '/', $path));
    $path = trim($path, '/');
    if ($path === '') {
        return '';
    }
    $segments = explode('/', $path);
    $safe = [];
    foreach ($segments as $seg) {
        $seg = trim($seg);
        if ($seg === '' || $seg === '.' || $seg === '..') {
            continue;
        }
        $seg = preg_replace('/[^a-zA-Z0-9 _.\-]/', '', $seg);
        if ($seg !== '') {
            $safe[] = mb_substr($seg, 0, 60);
        }
    }
    $safePath = implode('/', $safe);
    return mb_substr($safePath, 0, 500);
}

function path_parent(string $path): string {
    $path = normalize_folder_path($path);
    if ($path === '' || strpos($path, '/') === false) {
        return '';
    }
    return substr($path, 0, strrpos($path, '/'));
}

function path_join(string $base, string $leaf): string {
    $base = normalize_folder_path($base);
    $leaf = normalize_folder_path($leaf);
    if ($base === '') {
        return $leaf;
    }
    if ($leaf === '') {
        return $base;
    }
    return normalize_folder_path($base . '/' . $leaf);
}

function app_base_url(): string {
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $appRootMarker = '/pasadmin/';
    $pos = strpos($scriptName, $appRootMarker);
    return ($pos !== false) ? substr($scriptName, 0, $pos + strlen($appRootMarker)) : '/';
}

function app_full_base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . app_base_url();
}

function table_exists(mysqli $conn, string $table): bool {
    $t = $conn->real_escape_string($table);
    $res = $conn->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' LIMIT 1");
    return $res && $res->num_rows > 0;
}

function column_exists(mysqli $conn, string $table, string $column): bool {
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($column);
    $res = $conn->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1");
    return $res && $res->num_rows > 0;
}

function ensure_column(mysqli $conn, string $table, string $column, string $ddl): void {
    if (!column_exists($conn, $table, $column)) {
        $conn->query("ALTER TABLE $table ADD COLUMN $ddl");
    }
}

function log_activity(mysqli $conn, int $userId, ?int $fileId, string $action, string $meta = ''): void {
    $stmt = $conn->prepare("INSERT INTO pasker_drive_activities (user_id, file_id, action_name, meta_info) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('iiss', $userId, $fileId, $action, $meta);
    $stmt->execute();
    $stmt->close();
}

$conn = new mysqli('localhost', 'root', '', 'job_admin_prod');

$conn->query("CREATE TABLE IF NOT EXISTS pasker_drive_files (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    storage_name VARCHAR(255) NOT NULL UNIQUE,
    mime_type VARCHAR(150) DEFAULT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    path_rel VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_owner_created (owner_user_id, created_at),
    INDEX idx_owner_name (owner_user_id, original_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

ensure_column($conn, 'pasker_drive_files', 'folder_path', "folder_path VARCHAR(500) NOT NULL DEFAULT '' AFTER path_rel");
ensure_column($conn, 'pasker_drive_files', 'is_starred', "is_starred TINYINT(1) NOT NULL DEFAULT 0 AFTER folder_path");
ensure_column($conn, 'pasker_drive_files', 'is_trashed', "is_trashed TINYINT(1) NOT NULL DEFAULT 0 AFTER is_starred");
ensure_column($conn, 'pasker_drive_files', 'trashed_at', "trashed_at DATETIME DEFAULT NULL AFTER is_trashed");

$conn->query("CREATE TABLE IF NOT EXISTS pasker_drive_shares (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_id BIGINT UNSIGNED NOT NULL UNIQUE,
    share_token VARCHAR(64) NOT NULL UNIQUE,
    access_type ENUM('private','anyone_with_link') NOT NULL DEFAULT 'private',
    can_download TINYINT(1) NOT NULL DEFAULT 1,
    expires_at DATETIME DEFAULT NULL,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pasker_drive_shares_file
        FOREIGN KEY (file_id) REFERENCES pasker_drive_files(id) ON DELETE CASCADE,
    INDEX idx_share_token (share_token),
    INDEX idx_access_type (access_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS pasker_drive_folders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT NOT NULL,
    folder_path VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_owner_folder (owner_user_id, folder_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS pasker_drive_activities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    file_id BIGINT UNSIGNED NULL,
    action_name VARCHAR(80) NOT NULL,
    meta_info VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_file_created (file_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$storageDir = __DIR__ . '/downloads/pasker_drive';
if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0775, true);
}

$userId = intval($_SESSION['user_id'] ?? 0);
$maxUploadBytes = 100 * 1024 * 1024;
$dangerousExt = ['php', 'phtml', 'phar', 'htaccess', 'cgi', 'pl', 'exe', 'sh', 'bat'];

$view = (string)($_GET['view'] ?? 'all');
if (!in_array($view, ['all', 'starred', 'recent', 'trash'], true)) {
    $view = 'all';
}
$folder = normalize_folder_path((string)($_GET['folder'] ?? ''));
$search = trim((string)($_GET['q'] ?? ''));
$querySuffix = '&view=' . urlencode($view) . '&folder=' . urlencode($folder) . '&q=' . urlencode($search);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $redirectFolder = normalize_folder_path((string)($_POST['folder_path'] ?? $folder));
    $redirectView = (string)($_POST['view'] ?? $view);
    if (!in_array($redirectView, ['all', 'starred', 'recent', 'trash'], true)) {
        $redirectView = 'all';
    }
    $redirect = 'pasker_drive.php?view=' . urlencode($redirectView) . '&folder=' . urlencode($redirectFolder) . '&q=' . urlencode($search);

    if ($action === 'create_folder') {
        $folderName = normalize_folder_path((string)($_POST['folder_name'] ?? ''));
        if ($folderName === '') {
            flash_set('pasker_drive_error', 'Folder name is required.');
            header('Location: ' . $redirect);
            exit;
        }
        $targetFolder = path_join($redirectFolder, $folderName);
        $stmt = $conn->prepare("INSERT IGNORE INTO pasker_drive_folders (owner_user_id, folder_path) VALUES (?, ?)");
        $stmt->bind_param('is', $userId, $targetFolder);
        $stmt->execute();
        $stmt->close();
        log_activity($conn, $userId, null, 'create_folder', $targetFolder);
        flash_set('pasker_drive_success', 'Folder created.');
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'delete_folder') {
        $targetFolder = normalize_folder_path((string)($_POST['target_folder'] ?? ''));
        if ($targetFolder === '') {
            flash_set('pasker_drive_error', 'Invalid folder.');
            header('Location: ' . $redirect);
            exit;
        }
        $stmt = $conn->prepare("SELECT COUNT(*) FROM pasker_drive_files WHERE owner_user_id=? AND folder_path=? AND is_trashed=0");
        $stmt->bind_param('is', $userId, $targetFolder);
        $stmt->execute();
        $stmt->bind_result($fileCount);
        $stmt->fetch();
        $stmt->close();
        $stmt = $conn->prepare("SELECT COUNT(*) FROM pasker_drive_folders WHERE owner_user_id=? AND folder_path LIKE CONCAT(?, '/%')");
        $stmt->bind_param('is', $userId, $targetFolder);
        $stmt->execute();
        $stmt->bind_result($childCount);
        $stmt->fetch();
        $stmt->close();
        if ($fileCount > 0 || $childCount > 0) {
            flash_set('pasker_drive_error', 'Folder is not empty.');
            header('Location: ' . $redirect);
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM pasker_drive_folders WHERE owner_user_id=? AND folder_path=?");
        $stmt->bind_param('is', $userId, $targetFolder);
        $stmt->execute();
        $stmt->close();
        log_activity($conn, $userId, null, 'delete_folder', $targetFolder);
        flash_set('pasker_drive_success', 'Folder deleted.');
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'upload') {
        if (!isset($_FILES['files'])) {
            flash_set('pasker_drive_error', 'No file payload received.');
            header('Location: ' . $redirect);
            exit;
        }

        $uploaded = 0;
        $failed = 0;
        $uploadFolder = normalize_folder_path((string)($_POST['folder_path'] ?? ''));
        if ($uploadFolder !== '') {
            $insFolder = $conn->prepare("INSERT IGNORE INTO pasker_drive_folders (owner_user_id, folder_path) VALUES (?, ?)");
            $insFolder->bind_param('is', $userId, $uploadFolder);
            $insFolder->execute();
            $insFolder->close();
        }

        $names = $_FILES['files']['name'] ?? [];
        $tmpNames = $_FILES['files']['tmp_name'] ?? [];
        $errors = $_FILES['files']['error'] ?? [];
        $sizes = $_FILES['files']['size'] ?? [];

        if (!is_array($names)) {
            $names = [$names];
            $tmpNames = [$tmpNames];
            $errors = [$errors];
            $sizes = [$sizes];
        }

        for ($i = 0; $i < count($names); $i++) {
            $err = intval($errors[$i] ?? UPLOAD_ERR_NO_FILE);
            if ($err === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($err !== UPLOAD_ERR_OK) {
                $failed++;
                continue;
            }
            $size = intval($sizes[$i] ?? 0);
            if ($size <= 0 || $size > $maxUploadBytes) {
                $failed++;
                continue;
            }
            $originalName = sanitize_display_name((string)($names[$i] ?? ''));
            $ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
            if (in_array($ext, $dangerousExt, true)) {
                $failed++;
                continue;
            }

            $storageName = bin2hex(random_bytes(16));
            if ($ext !== '') {
                $storageName .= '.' . $ext;
            }
            $dest = $storageDir . '/' . $storageName;
            $tmpPath = (string)($tmpNames[$i] ?? '');
            if (!is_uploaded_file($tmpPath) || !@move_uploaded_file($tmpPath, $dest)) {
                $failed++;
                continue;
            }

            $mime = null;
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $mimeDetected = finfo_file($finfo, $dest);
                    if (is_string($mimeDetected) && $mimeDetected !== '') {
                        $mime = $mimeDetected;
                    }
                    finfo_close($finfo);
                }
            }

            $pathRel = 'downloads/pasker_drive/' . $storageName;
            $stmt = $conn->prepare('INSERT INTO pasker_drive_files (owner_user_id, original_name, storage_name, mime_type, size_bytes, path_rel, folder_path) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('isssiss', $userId, $originalName, $storageName, $mime, $size, $pathRel, $uploadFolder);
            $stmt->execute();
            $newFileId = intval($stmt->insert_id);
            $stmt->close();
            log_activity($conn, $userId, $newFileId, 'upload', $originalName);
            $uploaded++;
        }

        if ($uploaded > 0) {
            flash_set('pasker_drive_success', 'Upload complete: ' . $uploaded . ' file(s) uploaded.');
        }
        if ($failed > 0) {
            flash_set('pasker_drive_error', 'Some files failed to upload (' . $failed . ').');
        }
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'rename') {
        $fileId = intval($_POST['file_id'] ?? 0);
        $newName = sanitize_display_name((string)($_POST['new_name'] ?? ''));
        if ($fileId <= 0 || $newName === '') {
            flash_set('pasker_drive_error', 'Invalid rename request.');
            header('Location: ' . $redirect);
            exit;
        }
        $stmt = $conn->prepare('UPDATE pasker_drive_files SET original_name=? WHERE id=? AND owner_user_id=?');
        $stmt->bind_param('sii', $newName, $fileId, $userId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        if ($affected > 0) {
            log_activity($conn, $userId, $fileId, 'rename', $newName);
            flash_set('pasker_drive_success', 'File renamed.');
        } else {
            flash_set('pasker_drive_error', 'File not found or no change.');
        }
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'toggle_star') {
        $fileId = intval($_POST['file_id'] ?? 0);
        $isStarred = intval($_POST['is_starred'] ?? 0) === 1 ? 1 : 0;
        $stmt = $conn->prepare('UPDATE pasker_drive_files SET is_starred=? WHERE id=? AND owner_user_id=?');
        $stmt->bind_param('iii', $isStarred, $fileId, $userId);
        $stmt->execute();
        $stmt->close();
        log_activity($conn, $userId, $fileId, $isStarred ? 'star' : 'unstar');
        flash_set('pasker_drive_success', $isStarred ? 'File starred.' : 'File unstarred.');
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'move_file') {
        $fileId = intval($_POST['file_id'] ?? 0);
        $targetFolder = normalize_folder_path((string)($_POST['target_folder'] ?? ''));
        if ($targetFolder !== '') {
            $ins = $conn->prepare("INSERT IGNORE INTO pasker_drive_folders (owner_user_id, folder_path) VALUES (?, ?)");
            $ins->bind_param('is', $userId, $targetFolder);
            $ins->execute();
            $ins->close();
        }
        $stmt = $conn->prepare('UPDATE pasker_drive_files SET folder_path=? WHERE id=? AND owner_user_id=?');
        $stmt->bind_param('sii', $targetFolder, $fileId, $userId);
        $stmt->execute();
        $stmt->close();
        log_activity($conn, $userId, $fileId, 'move', $targetFolder === '' ? 'root' : $targetFolder);
        flash_set('pasker_drive_success', 'File moved.');
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'trash') {
        $fileId = intval($_POST['file_id'] ?? 0);
        $stmt = $conn->prepare('UPDATE pasker_drive_files SET is_trashed=1, trashed_at=NOW() WHERE id=? AND owner_user_id=?');
        $stmt->bind_param('ii', $fileId, $userId);
        $stmt->execute();
        $stmt->close();
        log_activity($conn, $userId, $fileId, 'trash');
        flash_set('pasker_drive_success', 'File moved to trash.');
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'restore') {
        $fileId = intval($_POST['file_id'] ?? 0);
        $stmt = $conn->prepare('UPDATE pasker_drive_files SET is_trashed=0, trashed_at=NULL WHERE id=? AND owner_user_id=?');
        $stmt->bind_param('ii', $fileId, $userId);
        $stmt->execute();
        $stmt->close();
        log_activity($conn, $userId, $fileId, 'restore');
        flash_set('pasker_drive_success', 'File restored.');
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'delete_permanent') {
        $fileId = intval($_POST['file_id'] ?? 0);
        $select = $conn->prepare('SELECT storage_name FROM pasker_drive_files WHERE id=? AND owner_user_id=? LIMIT 1');
        $select->bind_param('ii', $fileId, $userId);
        $select->execute();
        $select->bind_result($storageName);
        $found = $select->fetch();
        $select->close();
        if (!$found) {
            flash_set('pasker_drive_error', 'File not found.');
            header('Location: ' . $redirect);
            exit;
        }
        $del = $conn->prepare('DELETE FROM pasker_drive_files WHERE id=? AND owner_user_id=?');
        $del->bind_param('ii', $fileId, $userId);
        $del->execute();
        $del->close();
        $absolute = $storageDir . '/' . basename((string)$storageName);
        if (is_file($absolute)) {
            @unlink($absolute);
        }
        log_activity($conn, $userId, $fileId, 'delete_permanent');
        flash_set('pasker_drive_success', 'File permanently deleted.');
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'share_update') {
        $fileId = intval($_POST['file_id'] ?? 0);
        $linkMode = (string)($_POST['link_mode'] ?? 'private');
        $canDownload = isset($_POST['can_download']) ? 1 : 0;
        $expiresRaw = trim((string)($_POST['expires_at'] ?? ''));

        if ($fileId <= 0 || !in_array($linkMode, ['private', 'anyone_with_link'], true)) {
            flash_set('pasker_drive_error', 'Invalid share request.');
            header('Location: ' . $redirect);
            exit;
        }

        $ownerCheck = $conn->prepare('SELECT id FROM pasker_drive_files WHERE id=? AND owner_user_id=? AND is_trashed=0 LIMIT 1');
        $ownerCheck->bind_param('ii', $fileId, $userId);
        $ownerCheck->execute();
        $ownerCheck->store_result();
        $exists = $ownerCheck->num_rows > 0;
        $ownerCheck->close();
        if (!$exists) {
            flash_set('pasker_drive_error', 'File not found.');
            header('Location: ' . $redirect);
            exit;
        }

        if ($linkMode === 'private') {
            $stmt = $conn->prepare('DELETE FROM pasker_drive_shares WHERE file_id=?');
            $stmt->bind_param('i', $fileId);
            $stmt->execute();
            $stmt->close();
            log_activity($conn, $userId, $fileId, 'share_disable');
            flash_set('pasker_drive_success', 'Sharing disabled.');
            header('Location: ' . $redirect);
            exit;
        }

        $expiresAt = null;
        if ($expiresRaw !== '') {
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $expiresRaw);
            if (!$dt) {
                flash_set('pasker_drive_error', 'Invalid expiration format.');
                header('Location: ' . $redirect);
                exit;
            }
            $expiresAt = $dt->format('Y-m-d H:i:s');
        }

        $current = $conn->prepare('SELECT share_token FROM pasker_drive_shares WHERE file_id=? LIMIT 1');
        $current->bind_param('i', $fileId);
        $current->execute();
        $current->bind_result($existingToken);
        $hasShare = $current->fetch();
        $current->close();

        if ($hasShare) {
            $up = $conn->prepare('UPDATE pasker_drive_shares SET access_type=?, can_download=?, expires_at=?, updated_at=NOW() WHERE file_id=?');
            $accessType = 'anyone_with_link';
            $up->bind_param('sisi', $accessType, $canDownload, $expiresAt, $fileId);
            $up->execute();
            $up->close();
        } else {
            $token = bin2hex(random_bytes(24));
            $ins = $conn->prepare('INSERT INTO pasker_drive_shares (file_id, share_token, access_type, can_download, expires_at, created_by) VALUES (?, ?, ?, ?, ?, ?)');
            $accessType = 'anyone_with_link';
            $ins->bind_param('issisi', $fileId, $token, $accessType, $canDownload, $expiresAt, $userId);
            $ins->execute();
            $ins->close();
        }
        log_activity($conn, $userId, $fileId, 'share_update', $linkMode);
        flash_set('pasker_drive_success', 'Sharing updated.');
        header('Location: ' . $redirect);
        exit;
    }
}

$allFolders = [];
$folderRes = $conn->prepare("SELECT folder_path FROM pasker_drive_folders WHERE owner_user_id=? ORDER BY folder_path");
$folderRes->bind_param('i', $userId);
$folderRes->execute();
$folderData = $folderRes->get_result();
while ($r = $folderData->fetch_assoc()) {
    $fp = normalize_folder_path((string)$r['folder_path']);
    if ($fp !== '') {
        $allFolders[$fp] = true;
    }
}
$folderRes->close();

$scanFromFiles = $conn->prepare("SELECT DISTINCT folder_path FROM pasker_drive_files WHERE owner_user_id=? AND folder_path<>''");
$scanFromFiles->bind_param('i', $userId);
$scanFromFiles->execute();
$fres = $scanFromFiles->get_result();
while ($r = $fres->fetch_assoc()) {
    $fp = normalize_folder_path((string)$r['folder_path']);
    if ($fp !== '') {
        $allFolders[$fp] = true;
    }
}
$scanFromFiles->close();
$allFolderPaths = array_keys($allFolders);
sort($allFolderPaths);

$currentChildFolders = [];
if ($view === 'all') {
    foreach ($allFolderPaths as $fp) {
        if (path_parent($fp) === $folder) {
            $currentChildFolders[] = $fp;
        }
    }
}

$sql = "SELECT f.*, s.share_token, s.access_type, s.can_download, s.expires_at
        FROM pasker_drive_files f
        LEFT JOIN pasker_drive_shares s ON s.file_id=f.id
        WHERE f.owner_user_id=?";
$params = [$userId];
$types = 'i';

if ($view === 'trash') {
    $sql .= " AND f.is_trashed=1";
} elseif ($view === 'starred') {
    $sql .= " AND f.is_trashed=0 AND f.is_starred=1";
} elseif ($view === 'recent') {
    $sql .= " AND f.is_trashed=0";
} else {
    $sql .= " AND f.is_trashed=0 AND f.folder_path=?";
    $types .= 's';
    $params[] = $folder;
}

if ($search !== '') {
    $sql .= " AND f.original_name LIKE ?";
    $types .= 's';
    $params[] = '%' . $search . '%';
}

if ($view === 'recent') {
    $sql .= " ORDER BY f.updated_at DESC LIMIT 100";
} else {
    $sql .= " ORDER BY f.updated_at DESC";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();
$files = [];
$totalBytes = 0;
while ($row = $res->fetch_assoc()) {
    $files[] = $row;
    $totalBytes += intval($row['size_bytes'] ?? 0);
}
$stmt->close();

$activityRows = [];
$act = $conn->prepare("SELECT action_name, meta_info, created_at, file_id FROM pasker_drive_activities WHERE user_id=? ORDER BY id DESC LIMIT 12");
$act->bind_param('i', $userId);
$act->execute();
$ares = $act->get_result();
while ($r = $ares->fetch_assoc()) {
    $activityRows[] = $r;
}
$act->close();

$crumbs = [];
if ($folder !== '') {
    $segments = explode('/', $folder);
    $current = '';
    foreach ($segments as $seg) {
        $current = $current === '' ? $seg : ($current . '/' . $seg);
        $crumbs[] = ['name' => $seg, 'path' => $current];
    }
}

$success = flash_get('pasker_drive_success');
$error = flash_get('pasker_drive_error');
$baseUrl = app_base_url();
$fullBaseUrl = app_full_base_url();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pasker Drive</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .drive-sidebar .list-group-item.active {
            background: #0d6efd;
            border-color: #0d6efd;
        }
        .dropzone {
            border: 2px dashed #b6d4fe;
            border-radius: 12px;
            background: #f8fbff;
            padding: 20px;
            text-align: center;
            transition: all .15s ease;
        }
        .dropzone.dragover {
            border-color: #0d6efd;
            background: #e7f1ff;
        }
        .folder-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 12px;
            background: #fff;
        }
    </style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container-fluid py-4 px-3 px-lg-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h3 class="mb-1">Pasker Drive</h3>
            <div class="text-muted">Cloud files, folders, sharing, preview, and activity tracking.</div>
        </div>
        <div class="text-end">
            <div class="small text-muted">Visible files</div>
            <div class="fw-semibold"><?= count($files); ?> file(s)</div>
            <div class="small text-muted">Visible storage: <?= h(format_size($totalBytes)); ?></div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= h($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= h($error); ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-2">
            <div class="card shadow-sm drive-sidebar">
                <div class="list-group list-group-flush">
                    <a class="list-group-item list-group-item-action <?= $view === 'all' ? 'active' : ''; ?>" href="pasker_drive.php?view=all&folder=<?= urlencode($folder); ?>">
                        <i class="bi bi-folder2-open me-1"></i>My Drive
                    </a>
                    <a class="list-group-item list-group-item-action <?= $view === 'recent' ? 'active' : ''; ?>" href="pasker_drive.php?view=recent">
                        <i class="bi bi-clock-history me-1"></i>Recent
                    </a>
                    <a class="list-group-item list-group-item-action <?= $view === 'starred' ? 'active' : ''; ?>" href="pasker_drive.php?view=starred">
                        <i class="bi bi-star me-1"></i>Starred
                    </a>
                    <a class="list-group-item list-group-item-action <?= $view === 'trash' ? 'active' : ''; ?>" href="pasker_drive.php?view=trash">
                        <i class="bi bi-trash me-1"></i>Trash
                    </a>
                </div>
            </div>
            <div class="card shadow-sm mt-3">
                <div class="card-body">
                    <h6 class="mb-2">Recent Activity</h6>
                    <?php foreach ($activityRows as $a): ?>
                        <div class="small mb-2">
                            <div><span class="badge bg-light text-dark border"><?= h($a['action_name']); ?></span> <?= h($a['meta_info']); ?></div>
                            <div class="text-muted"><?= h($a['created_at']); ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (count($activityRows) === 0): ?>
                        <div class="small text-muted">No activity yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-10">
            <?php if ($view === 'all'): ?>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="pasker_drive.php?view=all&folder=">My Drive</a></li>
                            <?php foreach ($crumbs as $c): ?>
                                <li class="breadcrumb-item"><a href="pasker_drive.php?view=all&folder=<?= urlencode($c['path']); ?>"><?= h($c['name']); ?></a></li>
                            <?php endforeach; ?>
                        </ol>
                    </nav>
                </div>
                <form method="post" class="d-flex gap-2">
                    <input type="hidden" name="action" value="create_folder">
                    <input type="hidden" name="folder_path" value="<?= h($folder); ?>">
                    <input type="hidden" name="view" value="<?= h($view); ?>">
                    <input type="text" class="form-control form-control-sm" name="folder_name" placeholder="New folder name" required>
                    <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-folder-plus me-1"></i>Create Folder</button>
                </form>
            </div>
            <?php endif; ?>

            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h5 class="mb-3"><i class="bi bi-cloud-upload me-1"></i>Upload</h5>
                    <form method="post" enctype="multipart/form-data" class="row g-2" id="upload-form">
                        <input type="hidden" name="action" value="upload">
                        <input type="hidden" name="folder_path" value="<?= h($folder); ?>">
                        <input type="hidden" name="view" value="<?= h($view); ?>">
                        <div class="col-md-9">
                            <div class="dropzone" id="dropzone">
                                <div class="mb-2"><i class="bi bi-cloud-arrow-up fs-4"></i></div>
                                <div class="fw-semibold">Drag & drop files here</div>
                                <div class="text-muted small">or use the file picker below</div>
                                <input id="file-input" type="file" class="form-control mt-3" name="files[]" multiple required>
                            </div>
                            <div class="form-text">Max file size: 100 MB each.</div>
                        </div>
                        <div class="col-md-3 d-grid">
                            <button class="btn btn-primary" type="submit"><i class="bi bi-upload me-1"></i>Upload</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <form class="row g-2 mb-3" method="get">
                        <input type="hidden" name="view" value="<?= h($view); ?>">
                        <input type="hidden" name="folder" value="<?= h($folder); ?>">
                        <div class="col-md-9">
                            <input type="text" class="form-control" name="q" placeholder="Search files..." value="<?= h($search); ?>">
                        </div>
                        <div class="col-md-3 d-grid">
                            <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search me-1"></i>Search</button>
                        </div>
                    </form>

                    <?php if ($view === 'all' && count($currentChildFolders) > 0): ?>
                        <div class="mb-3">
                            <h6 class="mb-2">Folders</h6>
                            <div class="row g-2">
                                <?php foreach ($currentChildFolders as $childFolder): ?>
                                    <?php $name = basename($childFolder); ?>
                                    <div class="col-md-4 col-xl-3">
                                        <div class="folder-card h-100 d-flex flex-column justify-content-between">
                                            <a class="text-decoration-none fw-semibold mb-2" href="pasker_drive.php?view=all&folder=<?= urlencode($childFolder); ?>">
                                                <i class="bi bi-folder-fill text-warning me-1"></i><?= h($name); ?>
                                            </a>
                                            <form method="post" onsubmit="return confirm('Delete empty folder?');">
                                                <input type="hidden" name="action" value="delete_folder">
                                                <input type="hidden" name="folder_path" value="<?= h($folder); ?>">
                                                <input type="hidden" name="target_folder" value="<?= h($childFolder); ?>">
                                                <input type="hidden" name="view" value="<?= h($view); ?>">
                                                <button class="btn btn-sm btn-outline-danger w-100" type="submit">Delete Folder</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <hr>
                        </div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>File</th>
                                    <th>Folder</th>
                                    <th>Size</th>
                                    <th>Type</th>
                                    <th>Updated</th>
                                    <th style="min-width: 340px;">Share</th>
                                    <th style="min-width: 360px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($files as $file): ?>
                                <?php
                                $fileId = intval($file['id']);
                                $shared = (($file['access_type'] ?? 'private') === 'anyone_with_link') && !empty($file['share_token']);
                                $shareUrl = $shared ? ($fullBaseUrl . 'pasker_drive_share.php?token=' . urlencode((string)$file['share_token'])) : '';
                                $expiresInput = '';
                                if (!empty($file['expires_at'])) {
                                    $expiresInput = date('Y-m-d\TH:i', strtotime((string)$file['expires_at']));
                                }
                                $isPreviewable = false;
                                $mime = (string)($file['mime_type'] ?? '');
                                if (strpos($mime, 'image/') === 0 || strpos($mime, 'video/') === 0 || $mime === 'application/pdf' || strpos($mime, 'text/') === 0) {
                                    $isPreviewable = true;
                                }
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= h($file['original_name']); ?></div>
                                        <div class="text-muted small">ID #<?= $fileId; ?></div>
                                    </td>
                                    <td><?= h($file['folder_path'] === '' ? '/' : $file['folder_path']); ?></td>
                                    <td><?= h(format_size(intval($file['size_bytes'] ?? 0))); ?></td>
                                    <td><?= h($mime ?: '-'); ?></td>
                                    <td><?= h($file['updated_at']); ?></td>
                                    <td>
                                        <?php if (intval($file['is_trashed']) === 0): ?>
                                        <form method="post" class="row g-2">
                                            <input type="hidden" name="action" value="share_update">
                                            <input type="hidden" name="file_id" value="<?= $fileId; ?>">
                                            <input type="hidden" name="folder_path" value="<?= h($folder); ?>">
                                            <input type="hidden" name="view" value="<?= h($view); ?>">
                                            <div class="col-12">
                                                <select class="form-select form-select-sm" name="link_mode">
                                                    <option value="private" <?= !$shared ? 'selected' : ''; ?>>Private</option>
                                                    <option value="anyone_with_link" <?= $shared ? 'selected' : ''; ?>>Anyone with link</option>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <input type="datetime-local" class="form-control form-control-sm" name="expires_at" value="<?= h($expiresInput); ?>">
                                            </div>
                                            <div class="col-12 form-check ms-1">
                                                <input class="form-check-input" type="checkbox" name="can_download" id="can_download_<?= $fileId; ?>" <?= intval($file['can_download'] ?? 1) === 1 ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="can_download_<?= $fileId; ?>">Allow download</label>
                                            </div>
                                            <div class="col-12 d-grid">
                                                <button class="btn btn-sm btn-outline-primary" type="submit">Update Share</button>
                                            </div>
                                        </form>
                                        <?php if ($shared): ?>
                                            <div class="mt-2">
                                                <input type="text" class="form-control form-control-sm" readonly value="<?= h($shareUrl); ?>">
                                            </div>
                                        <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Trashed item cannot be shared</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-grid gap-2">
                                            <?php if (intval($file['is_trashed']) === 0): ?>
                                                <?php if ($isPreviewable): ?>
                                                    <button class="btn btn-sm btn-outline-primary preview-btn" data-preview-url="<?= h($baseUrl); ?>pasker_drive_preview.php?id=<?= $fileId; ?>">
                                                        <i class="bi bi-eye me-1"></i>Preview
                                                    </button>
                                                <?php endif; ?>
                                                <a class="btn btn-sm btn-success" href="<?= h($baseUrl); ?>pasker_drive_download.php?id=<?= $fileId; ?>">
                                                    <i class="bi bi-download me-1"></i>Download
                                                </a>
                                                <form method="post" class="d-flex gap-2">
                                                    <input type="hidden" name="action" value="rename">
                                                    <input type="hidden" name="file_id" value="<?= $fileId; ?>">
                                                    <input type="hidden" name="folder_path" value="<?= h($folder); ?>">
                                                    <input type="hidden" name="view" value="<?= h($view); ?>">
                                                    <input type="text" class="form-control form-control-sm" name="new_name" value="<?= h($file['original_name']); ?>" maxlength="255" required>
                                                    <button class="btn btn-sm btn-outline-secondary" type="submit">Rename</button>
                                                </form>
                                                <form method="post" class="d-flex gap-2">
                                                    <input type="hidden" name="action" value="move_file">
                                                    <input type="hidden" name="file_id" value="<?= $fileId; ?>">
                                                    <input type="hidden" name="folder_path" value="<?= h($folder); ?>">
                                                    <input type="hidden" name="view" value="<?= h($view); ?>">
                                                    <select class="form-select form-select-sm" name="target_folder">
                                                        <option value="">/ (Root)</option>
                                                        <?php foreach ($allFolderPaths as $target): ?>
                                                            <option value="<?= h($target); ?>" <?= $target === $file['folder_path'] ? 'selected' : ''; ?>><?= h($target); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button class="btn btn-sm btn-outline-info" type="submit">Move</button>
                                                </form>
                                                <div class="d-flex gap-2">
                                                    <form method="post" class="w-50">
                                                        <input type="hidden" name="action" value="toggle_star">
                                                        <input type="hidden" name="file_id" value="<?= $fileId; ?>">
                                                        <input type="hidden" name="is_starred" value="<?= intval($file['is_starred']) === 1 ? '0' : '1'; ?>">
                                                        <input type="hidden" name="folder_path" value="<?= h($folder); ?>">
                                                        <input type="hidden" name="view" value="<?= h($view); ?>">
                                                        <button class="btn btn-sm btn-outline-warning w-100" type="submit">
                                                            <i class="bi <?= intval($file['is_starred']) === 1 ? 'bi-star-fill' : 'bi-star'; ?> me-1"></i>
                                                            <?= intval($file['is_starred']) === 1 ? 'Unstar' : 'Star'; ?>
                                                        </button>
                                                    </form>
                                                    <form method="post" class="w-50" onsubmit="return confirm('Move this file to trash?');">
                                                        <input type="hidden" name="action" value="trash">
                                                        <input type="hidden" name="file_id" value="<?= $fileId; ?>">
                                                        <input type="hidden" name="folder_path" value="<?= h($folder); ?>">
                                                        <input type="hidden" name="view" value="<?= h($view); ?>">
                                                        <button class="btn btn-sm btn-outline-danger w-100" type="submit">Trash</button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <div class="d-flex gap-2">
                                                    <form method="post" class="w-50">
                                                        <input type="hidden" name="action" value="restore">
                                                        <input type="hidden" name="file_id" value="<?= $fileId; ?>">
                                                        <input type="hidden" name="folder_path" value="<?= h($folder); ?>">
                                                        <input type="hidden" name="view" value="<?= h($view); ?>">
                                                        <button class="btn btn-sm btn-outline-success w-100" type="submit">Restore</button>
                                                    </form>
                                                    <form method="post" class="w-50" onsubmit="return confirm('Permanently delete this file?');">
                                                        <input type="hidden" name="action" value="delete_permanent">
                                                        <input type="hidden" name="file_id" value="<?= $fileId; ?>">
                                                        <input type="hidden" name="folder_path" value="<?= h($folder); ?>">
                                                        <input type="hidden" name="view" value="<?= h($view); ?>">
                                                        <button class="btn btn-sm btn-danger w-100" type="submit">Delete</button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($files) === 0): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No files found in this view.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">File Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="height:75vh;">
                <iframe id="previewFrame" src="" style="border:0; width:100%; height:100%;"></iframe>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
    const dropzone = document.getElementById('dropzone');
    const input = document.getElementById('file-input');
    if (dropzone && input) {
        ['dragenter', 'dragover'].forEach((evt) => {
            dropzone.addEventListener(evt, (e) => {
                e.preventDefault();
                dropzone.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach((evt) => {
            dropzone.addEventListener(evt, (e) => {
                e.preventDefault();
                dropzone.classList.remove('dragover');
            });
        });
        dropzone.addEventListener('drop', (e) => {
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                input.files = e.dataTransfer.files;
            }
        });
    }

    const previewModalEl = document.getElementById('previewModal');
    const previewFrame = document.getElementById('previewFrame');
    if (previewModalEl && previewFrame) {
        const modal = new bootstrap.Modal(previewModalEl);
        document.querySelectorAll('.preview-btn').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                previewFrame.src = btn.getAttribute('data-preview-url') || '';
                modal.show();
            });
        });
        previewModalEl.addEventListener('hidden.bs.modal', () => {
            previewFrame.src = '';
        });
    }
})();
</script>
</body>
</html>
<?php $conn->close(); ?>
