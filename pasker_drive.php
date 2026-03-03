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
    $scheme = 'https';
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

function get_user_group_ids(mysqli $conn, int $userId): array {
    $ids = [];
    if (!table_exists($conn, 'user_access')) {
        return $ids;
    }
    $stmt = $conn->prepare("SELECT group_id FROM user_access WHERE user_id=?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $gid = intval($r['group_id']);
        if ($gid > 0) {
            $ids[] = $gid;
        }
    }
    $stmt->close();
    return array_values(array_unique($ids));
}

function get_file_access_role(mysqli $conn, int $userId, int $fileId): ?string {
    $sessionUsername = strtolower((string)($_SESSION['username'] ?? ''));
    if ($sessionUsername === 'datin_pasker') {
        return 'owner';
    }

    $stmt = $conn->prepare("SELECT owner_user_id, is_trashed FROM pasker_drive_files WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $fileId);
    $stmt->execute();
    $stmt->bind_result($ownerUserId, $isTrashed);
    $found = $stmt->fetch();
    $stmt->close();
    if (!$found || intval($isTrashed) === 1) {
        return null;
    }
    if (intval($ownerUserId) === $userId) {
        return 'owner';
    }

    $best = null;
    $priority = ['viewer' => 1, 'commenter' => 2, 'editor' => 3];

    $u = $conn->prepare("SELECT role FROM pasker_drive_permissions WHERE file_id=? AND principal_type='user' AND principal_id=? LIMIT 1");
    $u->bind_param('ii', $fileId, $userId);
    $u->execute();
    $u->bind_result($uRole);
    if ($u->fetch()) {
        $best = $uRole;
    }
    $u->close();

    $groupIds = get_user_group_ids($conn, $userId);
    if (!empty($groupIds)) {
        $in = implode(',', array_map('intval', $groupIds));
        $res = $conn->query("SELECT role FROM pasker_drive_permissions WHERE file_id=" . intval($fileId) . " AND principal_type='group' AND principal_id IN ($in)");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $role = (string)$r['role'];
                if (!isset($priority[$role])) {
                    continue;
                }
                if ($best === null || $priority[$role] > $priority[$best]) {
                    $best = $role;
                }
            }
        }
    }
    return $best;
}

function get_file_owner_id(mysqli $conn, int $fileId): int {
    $stmt = $conn->prepare("SELECT owner_user_id FROM pasker_drive_files WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $fileId);
    $stmt->execute();
    $stmt->bind_result($ownerUserId);
    $found = $stmt->fetch();
    $stmt->close();
    return $found ? intval($ownerUserId) : 0;
}

function get_group_user_ids(mysqli $conn, int $groupId): array {
    $ids = [];
    if (!table_exists($conn, 'user_access')) {
        return $ids;
    }
    $stmt = $conn->prepare("SELECT user_id FROM user_access WHERE group_id=?");
    $stmt->bind_param('i', $groupId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $uid = intval($r['user_id']);
        if ($uid > 0) {
            $ids[] = $uid;
        }
    }
    $stmt->close();
    return array_values(array_unique($ids));
}

function create_notification(mysqli $conn, int $recipientUserId, int $actorUserId, ?int $fileId, string $type, string $message): void {
    if ($recipientUserId <= 0 || $recipientUserId === $actorUserId) {
        return;
    }
    $stmt = $conn->prepare("INSERT INTO pasker_drive_notifications (recipient_user_id, actor_user_id, file_id, notif_type, message_text) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('iiiss', $recipientUserId, $actorUserId, $fileId, $type, $message);
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

$conn->query("CREATE TABLE IF NOT EXISTS pasker_drive_permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_id BIGINT UNSIGNED NOT NULL,
    principal_type ENUM('user','group') NOT NULL,
    principal_id INT NOT NULL,
    role ENUM('viewer','commenter','editor') NOT NULL DEFAULT 'viewer',
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_file_principal (file_id, principal_type, principal_id),
    INDEX idx_principal (principal_type, principal_id),
    CONSTRAINT fk_pasker_drive_permissions_file
        FOREIGN KEY (file_id) REFERENCES pasker_drive_files(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS pasker_drive_comments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_id BIGINT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    comment_text TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_file_created (file_id, created_at),
    INDEX idx_user_created (user_id, created_at),
    CONSTRAINT fk_pasker_drive_comments_file
        FOREIGN KEY (file_id) REFERENCES pasker_drive_files(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS pasker_drive_comment_replies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    comment_id BIGINT UNSIGNED NOT NULL,
    file_id BIGINT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    reply_text TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_comment_created (comment_id, created_at),
    INDEX idx_file_created (file_id, created_at),
    CONSTRAINT fk_pasker_drive_comment_replies_comment
        FOREIGN KEY (comment_id) REFERENCES pasker_drive_comments(id) ON DELETE CASCADE,
    CONSTRAINT fk_pasker_drive_comment_replies_file
        FOREIGN KEY (file_id) REFERENCES pasker_drive_files(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS pasker_drive_notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_user_id INT NOT NULL,
    actor_user_id INT NOT NULL,
    file_id BIGINT UNSIGNED NULL,
    notif_type VARCHAR(80) NOT NULL,
    message_text VARCHAR(255) NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_recipient_created (recipient_user_id, created_at),
    INDEX idx_recipient_read (recipient_user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS pasker_drive_file_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_id BIGINT UNSIGNED NOT NULL,
    version_no INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    storage_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(150) DEFAULT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    path_rel VARCHAR(255) NOT NULL,
    uploaded_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_file_version (file_id, version_no),
    INDEX idx_file_created (file_id, created_at),
    CONSTRAINT fk_pasker_drive_file_versions_file
        FOREIGN KEY (file_id) REFERENCES pasker_drive_files(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$storageDir = __DIR__ . '/downloads/pasker_drive';
if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0775, true);
}

$userId = intval($_SESSION['user_id'] ?? 0);
$sessionUsername = strtolower((string)($_SESSION['username'] ?? ''));
$isDriveSuperUser = ($sessionUsername === 'datin_pasker');
$maxUploadBytes = 100 * 1024 * 1024;
$dangerousExt = ['php', 'phtml', 'phar', 'htaccess', 'cgi', 'pl', 'exe', 'sh', 'bat'];

$view = (string)($_GET['view'] ?? 'all');
if (!in_array($view, ['all', 'starred', 'recent', 'trash', 'shared'], true)) {
    $view = 'all';
}
$folder = normalize_folder_path((string)($_GET['folder'] ?? ''));
$search = trim((string)($_GET['q'] ?? ''));
$querySuffix = '&view=' . urlencode($view) . '&folder=' . urlencode($folder) . '&q=' . urlencode($search);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $redirectFolder = normalize_folder_path((string)($_POST['folder_path'] ?? $folder));
    $redirectView = (string)($_POST['view'] ?? $view);
    if (!in_array($redirectView, ['all', 'starred', 'recent', 'trash', 'shared'], true)) {
        $redirectView = 'all';
    }
    $redirect = 'pasker_drive.php?view=' . urlencode($redirectView) . '&folder=' . urlencode($redirectFolder) . '&q=' . urlencode($search);

    if ($action === 'mark_notification_read') {
        $notificationId = intval($_POST['notification_id'] ?? 0);
        if ($notificationId > 0) {
            $stmt = $conn->prepare("UPDATE pasker_drive_notifications SET is_read=1 WHERE id=? AND recipient_user_id=?");
            $stmt->bind_param('ii', $notificationId, $userId);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: ' . $redirect);
        exit;
    }

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
        $role = get_file_access_role($conn, $userId, $fileId);
        if (!in_array($role, ['owner', 'editor'], true)) {
            flash_set('pasker_drive_error', 'You do not have permission to rename this file.');
            header('Location: ' . $redirect);
            exit;
        }
        $stmt = $conn->prepare('UPDATE pasker_drive_files SET original_name=? WHERE id=?');
        $stmt->bind_param('si', $newName, $fileId);
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
        $role = get_file_access_role($conn, $userId, $fileId);
        if (!in_array($role, ['owner', 'editor'], true)) {
            flash_set('pasker_drive_error', 'You do not have permission to star this file.');
            header('Location: ' . $redirect);
            exit;
        }
        $stmt = $conn->prepare('UPDATE pasker_drive_files SET is_starred=? WHERE id=?');
        $stmt->bind_param('ii', $isStarred, $fileId);
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
        $role = get_file_access_role($conn, $userId, $fileId);
        if (!in_array($role, ['owner', 'editor'], true)) {
            flash_set('pasker_drive_error', 'You do not have permission to move this file.');
            header('Location: ' . $redirect);
            exit;
        }
        $ownerId = $userId;
        $oStmt = $conn->prepare("SELECT owner_user_id FROM pasker_drive_files WHERE id=? LIMIT 1");
        $oStmt->bind_param('i', $fileId);
        $oStmt->execute();
        $oStmt->bind_result($ownerUserIdForMove);
        if ($oStmt->fetch()) {
            $ownerId = intval($ownerUserIdForMove);
        }
        $oStmt->close();

        if ($targetFolder !== '') {
            $ins = $conn->prepare("INSERT IGNORE INTO pasker_drive_folders (owner_user_id, folder_path) VALUES (?, ?)");
            $ins->bind_param('is', $ownerId, $targetFolder);
            $ins->execute();
            $ins->close();
        }
        $stmt = $conn->prepare('UPDATE pasker_drive_files SET folder_path=? WHERE id=?');
        $stmt->bind_param('si', $targetFolder, $fileId);
        $stmt->execute();
        $stmt->close();
        log_activity($conn, $userId, $fileId, 'move', $targetFolder === '' ? 'root' : $targetFolder);
        flash_set('pasker_drive_success', 'File moved.');
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'trash') {
        $fileId = intval($_POST['file_id'] ?? 0);
        $role = get_file_access_role($conn, $userId, $fileId);
        if (!in_array($role, ['owner', 'editor'], true)) {
            flash_set('pasker_drive_error', 'You do not have permission to trash this file.');
            header('Location: ' . $redirect);
            exit;
        }
        $stmt = $conn->prepare('UPDATE pasker_drive_files SET is_trashed=1, trashed_at=NOW() WHERE id=?');
        $stmt->bind_param('i', $fileId);
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

    if ($action === 'share_principal') {
        $fileId = intval($_POST['file_id'] ?? 0);
        $target = (string)($_POST['principal_target'] ?? '');
        $parts = explode(':', $target, 2);
        $principalType = $parts[0] ?? '';
        $principalId = intval($parts[1] ?? 0);
        $role = (string)($_POST['principal_role'] ?? 'viewer');
        if (!in_array($principalType, ['user', 'group'], true) || $principalId <= 0 || !in_array($role, ['viewer', 'commenter', 'editor'], true)) {
            flash_set('pasker_drive_error', 'Invalid direct share data.');
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
        $stmt = $conn->prepare('INSERT INTO pasker_drive_permissions (file_id, principal_type, principal_id, role, created_by) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE role=VALUES(role)');
        $stmt->bind_param('isisi', $fileId, $principalType, $principalId, $role, $userId);
        $stmt->execute();
        $stmt->close();
        if ($principalType === 'user') {
            create_notification($conn, $principalId, $userId, $fileId, 'share_granted', 'A file was shared with you as ' . $role);
        } else {
            foreach (get_group_user_ids($conn, $principalId) as $groupUserId) {
                create_notification($conn, $groupUserId, $userId, $fileId, 'share_granted_group', 'A file was shared with your group as ' . $role);
            }
        }
        log_activity($conn, $userId, $fileId, 'share_direct', $principalType . ':' . $principalId . ':' . $role);
        flash_set('pasker_drive_success', 'Direct share permission saved.');
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'revoke_principal') {
        $fileId = intval($_POST['file_id'] ?? 0);
        $principalType = (string)($_POST['principal_type'] ?? '');
        $principalId = intval($_POST['principal_id'] ?? 0);
        if (!in_array($principalType, ['user', 'group'], true) || $principalId <= 0) {
            flash_set('pasker_drive_error', 'Invalid revoke request.');
            header('Location: ' . $redirect);
            exit;
        }
        $ownerCheck = $conn->prepare('SELECT id FROM pasker_drive_files WHERE id=? AND owner_user_id=? LIMIT 1');
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
        $stmt = $conn->prepare('DELETE FROM pasker_drive_permissions WHERE file_id=? AND principal_type=? AND principal_id=?');
        $stmt->bind_param('isi', $fileId, $principalType, $principalId);
        $stmt->execute();
        $stmt->close();
        log_activity($conn, $userId, $fileId, 'share_revoke', $principalType . ':' . $principalId);
        flash_set('pasker_drive_success', 'Permission revoked.');
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'add_comment') {
        $fileId = intval($_POST['file_id'] ?? 0);
        $commentText = trim((string)($_POST['comment_text'] ?? ''));
        if ($fileId <= 0 || $commentText === '') {
            flash_set('pasker_drive_error', 'Comment cannot be empty.');
            header('Location: ' . $redirect);
            exit;
        }
        $role = get_file_access_role($conn, $userId, $fileId);
        if (!in_array($role, ['owner', 'editor', 'commenter'], true)) {
            flash_set('pasker_drive_error', 'You do not have permission to comment on this file.');
            header('Location: ' . $redirect);
            exit;
        }
        $commentText = mb_substr($commentText, 0, 2000);
        $stmt = $conn->prepare("INSERT INTO pasker_drive_comments (file_id, user_id, comment_text) VALUES (?, ?, ?)");
        $stmt->bind_param('iis', $fileId, $userId, $commentText);
        $stmt->execute();
        $stmt->close();
        $ownerId = get_file_owner_id($conn, $fileId);
        create_notification($conn, $ownerId, $userId, $fileId, 'comment_add', 'New comment on your file');
        log_activity($conn, $userId, $fileId, 'comment_add', mb_substr($commentText, 0, 80));
        flash_set('pasker_drive_success', 'Comment added.');
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'delete_comment') {
        $commentId = intval($_POST['comment_id'] ?? 0);
        $fileId = intval($_POST['file_id'] ?? 0);
        if ($commentId <= 0 || $fileId <= 0) {
            flash_set('pasker_drive_error', 'Invalid delete comment request.');
            header('Location: ' . $redirect);
            exit;
        }
        $stmt = $conn->prepare("SELECT user_id FROM pasker_drive_comments WHERE id=? AND file_id=? LIMIT 1");
        $stmt->bind_param('ii', $commentId, $fileId);
        $stmt->execute();
        $stmt->bind_result($commentOwnerId);
        $found = $stmt->fetch();
        $stmt->close();
        if (!$found) {
            flash_set('pasker_drive_error', 'Comment not found.');
            header('Location: ' . $redirect);
            exit;
        }
        $role = get_file_access_role($conn, $userId, $fileId);
        $canDelete = intval($commentOwnerId) === $userId || in_array($role, ['owner', 'editor'], true);
        if (!$canDelete) {
            flash_set('pasker_drive_error', 'You do not have permission to delete this comment.');
            header('Location: ' . $redirect);
            exit;
        }
        $del = $conn->prepare("DELETE FROM pasker_drive_comments WHERE id=?");
        $del->bind_param('i', $commentId);
        $del->execute();
        $del->close();
        log_activity($conn, $userId, $fileId, 'comment_delete', 'comment_id:' . $commentId);
        flash_set('pasker_drive_success', 'Comment deleted.');
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'add_comment_reply') {
        $fileId = intval($_POST['file_id'] ?? 0);
        $commentId = intval($_POST['comment_id'] ?? 0);
        $replyText = trim((string)($_POST['reply_text'] ?? ''));
        if ($fileId <= 0 || $commentId <= 0 || $replyText === '') {
            flash_set('pasker_drive_error', 'Reply cannot be empty.');
            header('Location: ' . $redirect);
            exit;
        }
        $role = get_file_access_role($conn, $userId, $fileId);
        if (!in_array($role, ['owner', 'editor', 'commenter'], true)) {
            flash_set('pasker_drive_error', 'You do not have permission to reply.');
            header('Location: ' . $redirect);
            exit;
        }
        $replyText = mb_substr($replyText, 0, 2000);
        $stmt = $conn->prepare("INSERT INTO pasker_drive_comment_replies (comment_id, file_id, user_id, reply_text) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('iiis', $commentId, $fileId, $userId, $replyText);
        $stmt->execute();
        $stmt->close();
        log_activity($conn, $userId, $fileId, 'comment_reply_add', mb_substr($replyText, 0, 80));
        $ownerId = get_file_owner_id($conn, $fileId);
        create_notification($conn, $ownerId, $userId, $fileId, 'comment_reply', 'New comment reply on your file');
        flash_set('pasker_drive_success', 'Reply added.');
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'delete_comment_reply') {
        $replyId = intval($_POST['reply_id'] ?? 0);
        $fileId = intval($_POST['file_id'] ?? 0);
        if ($replyId <= 0 || $fileId <= 0) {
            flash_set('pasker_drive_error', 'Invalid delete reply request.');
            header('Location: ' . $redirect);
            exit;
        }
        $stmt = $conn->prepare("SELECT user_id FROM pasker_drive_comment_replies WHERE id=? AND file_id=? LIMIT 1");
        $stmt->bind_param('ii', $replyId, $fileId);
        $stmt->execute();
        $stmt->bind_result($replyOwnerId);
        $found = $stmt->fetch();
        $stmt->close();
        if (!$found) {
            flash_set('pasker_drive_error', 'Reply not found.');
            header('Location: ' . $redirect);
            exit;
        }
        $role = get_file_access_role($conn, $userId, $fileId);
        $canDelete = intval($replyOwnerId) === $userId || in_array($role, ['owner', 'editor'], true);
        if (!$canDelete) {
            flash_set('pasker_drive_error', 'You do not have permission to delete this reply.');
            header('Location: ' . $redirect);
            exit;
        }
        $del = $conn->prepare("DELETE FROM pasker_drive_comment_replies WHERE id=?");
        $del->bind_param('i', $replyId);
        $del->execute();
        $del->close();
        log_activity($conn, $userId, $fileId, 'comment_reply_delete', 'reply_id:' . $replyId);
        flash_set('pasker_drive_success', 'Reply deleted.');
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'upload_new_version') {
        $fileId = intval($_POST['file_id'] ?? 0);
        if ($fileId <= 0 || !isset($_FILES['version_file'])) {
            flash_set('pasker_drive_error', 'No version file uploaded.');
            header('Location: ' . $redirect);
            exit;
        }
        $role = get_file_access_role($conn, $userId, $fileId);
        if (!in_array($role, ['owner', 'editor'], true)) {
            flash_set('pasker_drive_error', 'You do not have permission to upload a new version.');
            header('Location: ' . $redirect);
            exit;
        }

        $f = $_FILES['version_file'];
        if (intval($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash_set('pasker_drive_error', 'Upload error for version file.');
            header('Location: ' . $redirect);
            exit;
        }
        $size = intval($f['size'] ?? 0);
        if ($size <= 0 || $size > $maxUploadBytes) {
            flash_set('pasker_drive_error', 'Invalid file size for new version.');
            header('Location: ' . $redirect);
            exit;
        }
        $newName = sanitize_display_name((string)($f['name'] ?? ''));
        $ext = strtolower((string)pathinfo($newName, PATHINFO_EXTENSION));
        if (in_array($ext, $dangerousExt, true)) {
            flash_set('pasker_drive_error', 'Disallowed file type.');
            header('Location: ' . $redirect);
            exit;
        }

        $cur = $conn->prepare("SELECT original_name, storage_name, mime_type, size_bytes, path_rel FROM pasker_drive_files WHERE id=? LIMIT 1");
        $cur->bind_param('i', $fileId);
        $cur->execute();
        $cur->bind_result($oldOriginalName, $oldStorageName, $oldMimeType, $oldSizeBytes, $oldPathRel);
        $found = $cur->fetch();
        $cur->close();
        if (!$found) {
            flash_set('pasker_drive_error', 'File not found.');
            header('Location: ' . $redirect);
            exit;
        }

        $vStmt = $conn->prepare("SELECT COALESCE(MAX(version_no), 0) + 1 FROM pasker_drive_file_versions WHERE file_id=?");
        $vStmt->bind_param('i', $fileId);
        $vStmt->execute();
        $vStmt->bind_result($nextVersionNo);
        $vStmt->fetch();
        $vStmt->close();

        $insVer = $conn->prepare("INSERT INTO pasker_drive_file_versions (file_id, version_no, original_name, storage_name, mime_type, size_bytes, path_rel, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $insVer->bind_param('iisssisi', $fileId, $nextVersionNo, $oldOriginalName, $oldStorageName, $oldMimeType, $oldSizeBytes, $oldPathRel, $userId);
        $insVer->execute();
        $insVer->close();

        $newStorage = bin2hex(random_bytes(16));
        if ($ext !== '') {
            $newStorage .= '.' . $ext;
        }
        $dest = $storageDir . '/' . $newStorage;
        $tmpPath = (string)($f['tmp_name'] ?? '');
        if (!is_uploaded_file($tmpPath) || !@move_uploaded_file($tmpPath, $dest)) {
            flash_set('pasker_drive_error', 'Failed to store new version file.');
            header('Location: ' . $redirect);
            exit;
        }
        $newMime = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = finfo_file($finfo, $dest);
                if (is_string($detected) && $detected !== '') {
                    $newMime = $detected;
                }
                finfo_close($finfo);
            }
        }
        $newPathRel = 'downloads/pasker_drive/' . $newStorage;
        $up = $conn->prepare("UPDATE pasker_drive_files SET original_name=?, storage_name=?, mime_type=?, size_bytes=?, path_rel=? WHERE id=?");
        $up->bind_param('sssisi', $newName, $newStorage, $newMime, $size, $newPathRel, $fileId);
        $up->execute();
        $up->close();

        log_activity($conn, $userId, $fileId, 'version_upload', 'v' . $nextVersionNo . ': ' . $newName);
        $ownerId = get_file_owner_id($conn, $fileId);
        create_notification($conn, $ownerId, $userId, $fileId, 'version_upload', 'A new file version was uploaded');
        flash_set('pasker_drive_success', 'New version uploaded.');
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'restore_version') {
        $fileId = intval($_POST['file_id'] ?? 0);
        $versionId = intval($_POST['version_id'] ?? 0);
        if ($fileId <= 0 || $versionId <= 0) {
            flash_set('pasker_drive_error', 'Invalid restore version request.');
            header('Location: ' . $redirect);
            exit;
        }
        $role = get_file_access_role($conn, $userId, $fileId);
        if (!in_array($role, ['owner', 'editor'], true)) {
            flash_set('pasker_drive_error', 'You do not have permission to restore versions.');
            header('Location: ' . $redirect);
            exit;
        }

        $cur = $conn->prepare("SELECT original_name, storage_name, mime_type, size_bytes, path_rel FROM pasker_drive_files WHERE id=? LIMIT 1");
        $cur->bind_param('i', $fileId);
        $cur->execute();
        $cur->bind_result($curOriginalName, $curStorageName, $curMimeType, $curSizeBytes, $curPathRel);
        $foundCur = $cur->fetch();
        $cur->close();
        if (!$foundCur) {
            flash_set('pasker_drive_error', 'File not found.');
            header('Location: ' . $redirect);
            exit;
        }

        $ver = $conn->prepare("SELECT original_name, storage_name, mime_type, size_bytes, path_rel, version_no FROM pasker_drive_file_versions WHERE id=? AND file_id=? LIMIT 1");
        $ver->bind_param('ii', $versionId, $fileId);
        $ver->execute();
        $ver->bind_result($verOriginalName, $verStorageName, $verMimeType, $verSizeBytes, $verPathRel, $verNo);
        $foundVer = $ver->fetch();
        $ver->close();
        if (!$foundVer) {
            flash_set('pasker_drive_error', 'Version not found.');
            header('Location: ' . $redirect);
            exit;
        }

        $vStmt = $conn->prepare("SELECT COALESCE(MAX(version_no), 0) + 1 FROM pasker_drive_file_versions WHERE file_id=?");
        $vStmt->bind_param('i', $fileId);
        $vStmt->execute();
        $vStmt->bind_result($nextVersionNo);
        $vStmt->fetch();
        $vStmt->close();

        $snapshot = $conn->prepare("INSERT INTO pasker_drive_file_versions (file_id, version_no, original_name, storage_name, mime_type, size_bytes, path_rel, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $snapshot->bind_param('iisssisi', $fileId, $nextVersionNo, $curOriginalName, $curStorageName, $curMimeType, $curSizeBytes, $curPathRel, $userId);
        $snapshot->execute();
        $snapshot->close();

        $up = $conn->prepare("UPDATE pasker_drive_files SET original_name=?, storage_name=?, mime_type=?, size_bytes=?, path_rel=? WHERE id=?");
        $up->bind_param('sssisi', $verOriginalName, $verStorageName, $verMimeType, $verSizeBytes, $verPathRel, $fileId);
        $up->execute();
        $up->close();

        log_activity($conn, $userId, $fileId, 'version_restore', 'restored v' . intval($verNo));
        $ownerId = get_file_owner_id($conn, $fileId);
        create_notification($conn, $ownerId, $userId, $fileId, 'version_restore', 'A file version was restored');
        flash_set('pasker_drive_success', 'Version restored as current file.');
        header('Location: ' . $redirect);
        exit;
    }

    if ($action === 'save_inline_text') {
        $fileId = intval($_POST['file_id'] ?? 0);
        $inlineContent = (string)($_POST['inline_content'] ?? '');
        if ($fileId <= 0) {
            flash_set('pasker_drive_error', 'Invalid inline edit request.');
            header('Location: ' . $redirect);
            exit;
        }
        $role = get_file_access_role($conn, $userId, $fileId);
        if (!in_array($role, ['owner', 'editor'], true)) {
            flash_set('pasker_drive_error', 'You do not have permission to edit this file.');
            header('Location: ' . $redirect);
            exit;
        }
        $stmt = $conn->prepare("SELECT original_name, storage_name, mime_type, size_bytes, path_rel FROM pasker_drive_files WHERE id=? LIMIT 1");
        $stmt->bind_param('i', $fileId);
        $stmt->execute();
        $stmt->bind_result($curOriginalName, $curStorageName, $curMimeType, $curSizeBytes, $curPathRel);
        $found = $stmt->fetch();
        $stmt->close();
        if (!$found) {
            flash_set('pasker_drive_error', 'File not found.');
            header('Location: ' . $redirect);
            exit;
        }
        $mime = strtolower((string)$curMimeType);
        $editableMime = (strpos($mime, 'text/') === 0) || in_array($mime, ['application/json', 'application/xml', 'application/javascript'], true);
        if (!$editableMime) {
            flash_set('pasker_drive_error', 'This file type is not editable inline.');
            header('Location: ' . $redirect);
            exit;
        }

        $vStmt = $conn->prepare("SELECT COALESCE(MAX(version_no), 0) + 1 FROM pasker_drive_file_versions WHERE file_id=?");
        $vStmt->bind_param('i', $fileId);
        $vStmt->execute();
        $vStmt->bind_result($nextVersionNo);
        $vStmt->fetch();
        $vStmt->close();
        $insVer = $conn->prepare("INSERT INTO pasker_drive_file_versions (file_id, version_no, original_name, storage_name, mime_type, size_bytes, path_rel, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $insVer->bind_param('iisssisi', $fileId, $nextVersionNo, $curOriginalName, $curStorageName, $curMimeType, $curSizeBytes, $curPathRel, $userId);
        $insVer->execute();
        $insVer->close();

        $absolute = $storageDir . '/' . basename((string)$curStorageName);
        if (!is_file($absolute) || @file_put_contents($absolute, $inlineContent) === false) {
            flash_set('pasker_drive_error', 'Failed to save inline edits.');
            header('Location: ' . $redirect);
            exit;
        }
        $newSize = filesize($absolute);
        $up = $conn->prepare("UPDATE pasker_drive_files SET size_bytes=?, mime_type=? WHERE id=?");
        $up->bind_param('isi', $newSize, $curMimeType, $fileId);
        $up->execute();
        $up->close();
        log_activity($conn, $userId, $fileId, 'inline_edit_save', 'text content updated');
        $ownerId = get_file_owner_id($conn, $fileId);
        create_notification($conn, $ownerId, $userId, $fileId, 'inline_edit', 'A file was edited inline');
        flash_set('pasker_drive_success', 'Inline text saved.');
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

$userLabelColumn = null;
if (table_exists($conn, 'users')) {
    $colRes = $conn->query("SHOW COLUMNS FROM users");
    while ($c = $colRes->fetch_assoc()) {
        if ($c['Field'] === 'username') {
            $userLabelColumn = 'username';
            break;
        }
        if ($c['Field'] === 'name') {
            $userLabelColumn = 'name';
        }
    }
}
$shareUserOptions = [];
if ($userLabelColumn !== null) {
    $resUsers = $conn->query("SELECT id, " . $userLabelColumn . " AS label FROM users ORDER BY id DESC LIMIT 500");
    while ($u = $resUsers->fetch_assoc()) {
        $uid = intval($u['id']);
        if ($uid > 0) {
            $shareUserOptions[$uid] = trim((string)$u['label']) !== '' ? trim((string)$u['label']) : ('User #' . $uid);
        }
    }
}

$shareGroupOptions = [];
if (table_exists($conn, 'access_groups')) {
    $resGroups = $conn->query("SELECT id, name FROM access_groups ORDER BY name");
    while ($g = $resGroups->fetch_assoc()) {
        $gid = intval($g['id']);
        if ($gid > 0) {
            $shareGroupOptions[$gid] = (string)$g['name'];
        }
    }
}

$myGroupIds = [];
if (table_exists($conn, 'user_access')) {
    $myGrpStmt = $conn->prepare("SELECT group_id FROM user_access WHERE user_id=?");
    $myGrpStmt->bind_param('i', $userId);
    $myGrpStmt->execute();
    $myGrpRes = $myGrpStmt->get_result();
    while ($gr = $myGrpRes->fetch_assoc()) {
        $gid = intval($gr['group_id']);
        if ($gid > 0) {
            $myGroupIds[] = $gid;
        }
    }
    $myGrpStmt->close();
}
$myGroupIds = array_values(array_unique($myGroupIds));

$allFolders = [];
if ($isDriveSuperUser) {
    $folderData = $conn->query("SELECT folder_path FROM pasker_drive_folders ORDER BY folder_path");
} else {
    $folderRes = $conn->prepare("SELECT folder_path FROM pasker_drive_folders WHERE owner_user_id=? ORDER BY folder_path");
    $folderRes->bind_param('i', $userId);
    $folderRes->execute();
    $folderData = $folderRes->get_result();
}
while ($r = $folderData->fetch_assoc()) {
    $fp = normalize_folder_path((string)$r['folder_path']);
    if ($fp !== '') {
        $allFolders[$fp] = true;
    }
}
if (!$isDriveSuperUser && isset($folderRes)) {
    $folderRes->close();
}

if ($isDriveSuperUser) {
    $fres = $conn->query("SELECT DISTINCT folder_path FROM pasker_drive_files WHERE folder_path<>''");
} else {
    $scanFromFiles = $conn->prepare("SELECT DISTINCT folder_path FROM pasker_drive_files WHERE owner_user_id=? AND folder_path<>''");
    $scanFromFiles->bind_param('i', $userId);
    $scanFromFiles->execute();
    $fres = $scanFromFiles->get_result();
}
while ($r = $fres->fetch_assoc()) {
    $fp = normalize_folder_path((string)$r['folder_path']);
    if ($fp !== '') {
        $allFolders[$fp] = true;
    }
}
if (!$isDriveSuperUser && isset($scanFromFiles)) {
    $scanFromFiles->close();
}
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

$files = [];
$totalBytes = 0;
$sharedRoleByFileId = [];

if ($isDriveSuperUser) {
    $sql = "SELECT f.*, s.share_token, s.access_type, s.can_download, s.expires_at
            FROM pasker_drive_files f
            LEFT JOIN pasker_drive_shares s ON s.file_id=f.id
            WHERE 1=1";
    $params = [];
    $types = '';
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
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $files[] = $row;
        $totalBytes += intval($row['size_bytes'] ?? 0);
        if ($view === 'shared') {
            $sharedRoleByFileId[intval($row['id'])] = 'owner';
        }
    }
    $stmt->close();
} elseif ($view === 'shared') {
    $rolePriority = ['viewer' => 1, 'commenter' => 2, 'editor' => 3];
    $permRows = [];
    $uPerm = $conn->prepare("SELECT file_id, role FROM pasker_drive_permissions WHERE principal_type='user' AND principal_id=?");
    $uPerm->bind_param('i', $userId);
    $uPerm->execute();
    $uPermRes = $uPerm->get_result();
    while ($r = $uPermRes->fetch_assoc()) {
        $permRows[] = $r;
    }
    $uPerm->close();

    if (!empty($myGroupIds)) {
        $in = implode(',', array_map('intval', $myGroupIds));
        $gPermRes = $conn->query("SELECT file_id, role FROM pasker_drive_permissions WHERE principal_type='group' AND principal_id IN ($in)");
        while ($r = $gPermRes->fetch_assoc()) {
            $permRows[] = $r;
        }
    }

    foreach ($permRows as $pr) {
        $fid = intval($pr['file_id']);
        $role = (string)$pr['role'];
        if ($fid <= 0 || !isset($rolePriority[$role])) {
            continue;
        }
        if (!isset($sharedRoleByFileId[$fid]) || $rolePriority[$role] > $rolePriority[$sharedRoleByFileId[$fid]]) {
            $sharedRoleByFileId[$fid] = $role;
        }
    }

    $sharedIds = array_keys($sharedRoleByFileId);
    if (!empty($sharedIds)) {
        $placeholders = implode(',', array_fill(0, count($sharedIds), '?'));
        $types = str_repeat('i', count($sharedIds));
        $params = array_map('intval', $sharedIds);
        $sql = "SELECT f.*, s.share_token, s.access_type, s.can_download, s.expires_at
                FROM pasker_drive_files f
                LEFT JOIN pasker_drive_shares s ON s.file_id=f.id
                WHERE f.id IN ($placeholders) AND f.owner_user_id<>? AND f.is_trashed=0";
        $types .= 'i';
        $params[] = $userId;
        if ($search !== '') {
            $sql .= " AND f.original_name LIKE ?";
            $types .= 's';
            $params[] = '%' . $search . '%';
        }
        $sql .= " ORDER BY f.updated_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $files[] = $row;
            $totalBytes += intval($row['size_bytes'] ?? 0);
        }
        $stmt->close();
    }
} else {
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
    while ($row = $res->fetch_assoc()) {
        $files[] = $row;
        $totalBytes += intval($row['size_bytes'] ?? 0);
    }
    $stmt->close();
}

$filePermissionMap = [];
if (!empty($files)) {
    $ids = array_map(static function ($x) {
        return intval($x['id'] ?? 0);
    }, $files);
    $ids = array_values(array_filter($ids, static function ($x) {
        return $x > 0;
    }));
    if (!empty($ids)) {
        $in = implode(',', array_map('intval', $ids));
        $permRes = $conn->query("SELECT file_id, principal_type, principal_id, role FROM pasker_drive_permissions WHERE file_id IN ($in)");
        while ($p = $permRes->fetch_assoc()) {
            $fid = intval($p['file_id']);
            if (!isset($filePermissionMap[$fid])) {
                $filePermissionMap[$fid] = [];
            }
            $ptype = (string)$p['principal_type'];
            $pid = intval($p['principal_id']);
            $label = $ptype === 'group'
                ? ('Group: ' . ($shareGroupOptions[$pid] ?? ('#' . $pid)))
                : ('User: ' . ($shareUserOptions[$pid] ?? ('#' . $pid)));
            $filePermissionMap[$fid][] = [
                'principal_type' => $ptype,
                'principal_id' => $pid,
                'role' => (string)$p['role'],
                'label' => $label,
            ];
        }
    }
}

$fileCommentsMap = [];
if (!empty($files)) {
    $ids = array_map(static function ($x) {
        return intval($x['id'] ?? 0);
    }, $files);
    $ids = array_values(array_filter($ids, static function ($x) {
        return $x > 0;
    }));
    if (!empty($ids)) {
        $in = implode(',', array_map('intval', $ids));
        $comRes = $conn->query("SELECT id, file_id, user_id, comment_text, created_at FROM pasker_drive_comments WHERE file_id IN ($in) ORDER BY id DESC");
        while ($c = $comRes->fetch_assoc()) {
            $fid = intval($c['file_id']);
            if (!isset($fileCommentsMap[$fid])) {
                $fileCommentsMap[$fid] = [];
            }
            if (count($fileCommentsMap[$fid]) < 5) {
                $uid = intval($c['user_id']);
                $userLabel = $shareUserOptions[$uid] ?? ('User #' . $uid);
                $fileCommentsMap[$fid][] = [
                    'id' => intval($c['id']),
                    'user_id' => $uid,
                    'user_label' => $userLabel,
                    'comment_text' => (string)$c['comment_text'],
                    'created_at' => (string)$c['created_at'],
                ];
            }
        }
    }
}

$commentRepliesMap = [];
if (!empty($fileCommentsMap)) {
    $commentIds = [];
    foreach ($fileCommentsMap as $rows) {
        foreach ($rows as $cm) {
            $cid = intval($cm['id'] ?? 0);
            if ($cid > 0) {
                $commentIds[] = $cid;
            }
        }
    }
    $commentIds = array_values(array_unique($commentIds));
    if (!empty($commentIds)) {
        $in = implode(',', array_map('intval', $commentIds));
        $repRes = $conn->query("SELECT id, comment_id, file_id, user_id, reply_text, created_at FROM pasker_drive_comment_replies WHERE comment_id IN ($in) ORDER BY id DESC");
        while ($rep = $repRes->fetch_assoc()) {
            $cid = intval($rep['comment_id']);
            if (!isset($commentRepliesMap[$cid])) {
                $commentRepliesMap[$cid] = [];
            }
            if (count($commentRepliesMap[$cid]) < 4) {
                $uid = intval($rep['user_id']);
                $commentRepliesMap[$cid][] = [
                    'id' => intval($rep['id']),
                    'file_id' => intval($rep['file_id']),
                    'user_id' => $uid,
                    'user_label' => $shareUserOptions[$uid] ?? ('User #' . $uid),
                    'reply_text' => (string)$rep['reply_text'],
                    'created_at' => (string)$rep['created_at'],
                ];
            }
        }
    }
}

$fileVersionsMap = [];
if (!empty($files)) {
    $ids = array_map(static function ($x) {
        return intval($x['id'] ?? 0);
    }, $files);
    $ids = array_values(array_filter($ids, static function ($x) {
        return $x > 0;
    }));
    if (!empty($ids)) {
        $in = implode(',', array_map('intval', $ids));
        $verRes = $conn->query("SELECT id, file_id, version_no, original_name, size_bytes, created_at FROM pasker_drive_file_versions WHERE file_id IN ($in) ORDER BY file_id, version_no DESC");
        while ($v = $verRes->fetch_assoc()) {
            $fid = intval($v['file_id']);
            if (!isset($fileVersionsMap[$fid])) {
                $fileVersionsMap[$fid] = [];
            }
            if (count($fileVersionsMap[$fid]) < 5) {
                $fileVersionsMap[$fid][] = $v;
            }
        }
    }
}

$inlineEditableMap = [];
if (!empty($files)) {
    foreach ($files as $f) {
        $fid = intval($f['id'] ?? 0);
        $mime = strtolower((string)($f['mime_type'] ?? ''));
        $size = intval($f['size_bytes'] ?? 0);
        if ($fid <= 0 || $size > 200000) {
            continue;
        }
        $editableMime = (strpos($mime, 'text/') === 0) || in_array($mime, ['application/json', 'application/xml', 'application/javascript'], true);
        if (!$editableMime) {
            continue;
        }
        $abs = $storageDir . '/' . basename((string)($f['storage_name'] ?? ''));
        if (!is_file($abs)) {
            continue;
        }
        $content = @file_get_contents($abs);
        if (!is_string($content)) {
            continue;
        }
        $inlineEditableMap[$fid] = mb_substr($content, 0, 200000);
    }
}

$notificationRows = [];
$notifStmt = $conn->prepare("SELECT id, actor_user_id, file_id, notif_type, message_text, is_read, created_at FROM pasker_drive_notifications WHERE recipient_user_id=? ORDER BY id DESC LIMIT 15");
$notifStmt->bind_param('i', $userId);
$notifStmt->execute();
$nRes = $notifStmt->get_result();
while ($nr = $nRes->fetch_assoc()) {
    $notificationRows[] = $nr;
}
$notifStmt->close();

$activityFilterFileId = intval($_GET['activity_file_id'] ?? 0);
$activityFilterUserId = intval($_GET['activity_user_id'] ?? 0);
$activityRows = [];
$activitySql = "SELECT action_name, meta_info, created_at, file_id, user_id FROM pasker_drive_activities WHERE 1=1";
$activityTypes = '';
$activityParams = [];

if ($activityFilterFileId > 0) {
    $activitySql .= " AND file_id=?";
    $activityTypes .= 'i';
    $activityParams[] = $activityFilterFileId;
}
if ($activityFilterUserId > 0) {
    $activitySql .= " AND user_id=?";
    $activityTypes .= 'i';
    $activityParams[] = $activityFilterUserId;
}
if ($view === 'shared') {
    if (!empty($sharedRoleByFileId)) {
        $sharedIdsForAct = array_map('intval', array_keys($sharedRoleByFileId));
        $activitySql .= " AND file_id IN (" . implode(',', $sharedIdsForAct) . ")";
    } else {
        $activitySql .= " AND 1=0";
    }
} else {
    $activitySql .= " AND (user_id=? OR file_id IN (SELECT id FROM pasker_drive_files WHERE owner_user_id=?))";
    $activityTypes .= 'ii';
    $activityParams[] = $userId;
    $activityParams[] = $userId;
}
$activitySql .= " ORDER BY id DESC LIMIT 20";

$act = $conn->prepare($activitySql);
if ($activityTypes !== '') {
    $act->bind_param($activityTypes, ...$activityParams);
}
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
                    <a class="list-group-item list-group-item-action <?= $view === 'shared' ? 'active' : ''; ?>" href="pasker_drive.php?view=shared">
                        <i class="bi bi-people me-1"></i>Shared with me
                    </a>
                    <a class="list-group-item list-group-item-action <?= $view === 'trash' ? 'active' : ''; ?>" href="pasker_drive.php?view=trash">
                        <i class="bi bi-trash me-1"></i>Trash
                    </a>
                </div>
            </div>
            <div class="card shadow-sm mt-3">
                <div class="card-body">
                    <h6 class="mb-2">Notifications</h6>
                    <?php foreach ($notificationRows as $n): ?>
                        <div class="small border rounded p-2 mb-2 <?= intval($n['is_read']) === 0 ? 'bg-light' : ''; ?>">
                            <div><?= h($n['message_text']); ?></div>
                            <div class="text-muted">
                                from <?= h($shareUserOptions[intval($n['actor_user_id'])] ?? ('User #' . intval($n['actor_user_id']))); ?>
                                at <?= h($n['created_at']); ?>
                            </div>
                            <?php if (intval($n['is_read']) === 0): ?>
                                <form method="post" class="mt-1 text-end">
                                    <input type="hidden" name="action" value="mark_notification_read">
                                    <input type="hidden" name="notification_id" value="<?= intval($n['id']); ?>">
                                    <input type="hidden" name="folder_path" value="<?= h($folder); ?>">
                                    <input type="hidden" name="view" value="<?= h($view); ?>">
                                    <button class="btn btn-sm btn-outline-secondary py-0 px-2" type="submit">Mark read</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (count($notificationRows) === 0): ?>
                        <div class="small text-muted">No notifications.</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card shadow-sm mt-3">
                <div class="card-body">
                    <h6 class="mb-2">Recent Activity</h6>
                    <form method="get" class="row g-2 mb-2">
                        <input type="hidden" name="view" value="<?= h($view); ?>">
                        <input type="hidden" name="folder" value="<?= h($folder); ?>">
                        <input type="hidden" name="q" value="<?= h($search); ?>">
                        <div class="col-12">
                            <select class="form-select form-select-sm" name="activity_file_id">
                                <option value="0">All files</option>
                                <?php foreach ($files as $af): ?>
                                    <?php $afid = intval($af['id']); ?>
                                    <option value="<?= $afid; ?>" <?= $activityFilterFileId === $afid ? 'selected' : ''; ?>>
                                        <?= h(mb_substr((string)$af['original_name'], 0, 35)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <select class="form-select form-select-sm" name="activity_user_id">
                                <option value="0">All users</option>
                                <?php foreach ($shareUserOptions as $auid => $aulabel): ?>
                                    <option value="<?= intval($auid); ?>" <?= $activityFilterUserId === intval($auid) ? 'selected' : ''; ?>>
                                        <?= h($aulabel . ' (#' . intval($auid) . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 d-grid">
                            <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
                        </div>
                        <div class="col-6 d-grid">
                            <a class="btn btn-sm btn-outline-secondary" href="pasker_drive.php?view=<?= urlencode($view); ?>&folder=<?= urlencode($folder); ?>&q=<?= urlencode($search); ?>">Reset</a>
                        </div>
                    </form>
                    <?php foreach ($activityRows as $a): ?>
                        <div class="small mb-2">
                            <div><span class="badge bg-light text-dark border"><?= h($a['action_name']); ?></span> <?= h($a['meta_info']); ?></div>
                            <div class="text-muted">by <?= h($shareUserOptions[intval($a['user_id'])] ?? ('User #' . intval($a['user_id']))); ?></div>
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

            <?php if ($view !== 'shared'): ?>
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
            <?php endif; ?>

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
                                    <?php if ($view === 'shared'): ?><th>Your Role</th><?php endif; ?>
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
                                $isOwner = $isDriveSuperUser || intval($file['owner_user_id'] ?? 0) === $userId;
                                $currentRole = $isOwner ? 'owner' : ($sharedRoleByFileId[$fileId] ?? 'viewer');
                                $canEditFile = in_array($currentRole, ['owner', 'editor'], true);
                                $canCommentFile = in_array($currentRole, ['owner', 'editor', 'commenter'], true);
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
                                    <?php if ($view === 'shared'): ?>
                                        <td><span class="badge bg-info text-dark"><?= h($currentRole); ?></span></td>
                                    <?php endif; ?>
                                    <td>
                                        <?php if (intval($file['is_trashed']) === 0 && $isOwner): ?>
                                            <form method="post" class="row g-2 mb-2">
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
                                                    <button class="btn btn-sm btn-outline-primary" type="submit">Update Link Share</button>
                                                </div>
                                            </form>
                                            <form method="post" class="row g-2 mb-2">
                                                <input type="hidden" name="action" value="share_principal">
                                                <input type="hidden" name="file_id" value="<?= $fileId; ?>">
                                                <input type="hidden" name="folder_path" value="<?= h($folder); ?>">
                                                <input type="hidden" name="view" value="<?= h($view); ?>">
                                                <div class="col-12">
                                                    <select class="form-select form-select-sm" name="principal_target" required>
                                                        <?php foreach ($shareUserOptions as $uid => $ulabel): ?>
                                                            <option value="<?= h('user:' . $uid); ?>"><?= h('User: ' . $ulabel . ' (#' . $uid . ')'); ?></option>
                                                        <?php endforeach; ?>
                                                        <?php foreach ($shareGroupOptions as $gid => $glabel): ?>
                                                            <option value="<?= h('group:' . $gid); ?>"><?= h('Group: ' . $glabel . ' (#' . $gid . ')'); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-8">
                                                    <select class="form-select form-select-sm" name="principal_role">
                                                        <option value="viewer">Viewer</option>
                                                        <option value="commenter">Commenter</option>
                                                        <option value="editor">Editor</option>
                                                    </select>
                                                </div>
                                                <div class="col-4 d-grid">
                                                    <button class="btn btn-sm btn-outline-success" type="submit">Grant</button>
                                                </div>
                                            </form>
                                            <?php if (!empty($filePermissionMap[$fileId])): ?>
                                                <div class="small">
                                                    <?php foreach ($filePermissionMap[$fileId] as $perm): ?>
                                                        <div class="d-flex justify-content-between align-items-center border rounded px-2 py-1 mb-1">
                                                            <span><?= h($perm['label'] . ' - ' . $perm['role']); ?></span>
                                                            <form method="post" class="ms-2">
                                                                <input type="hidden" name="action" value="revoke_principal">
                                                                <input type="hidden" name="file_id" value="<?= $fileId; ?>">
                                                                <input type="hidden" name="principal_type" value="<?= h($perm['principal_type']); ?>">
                                                                <input type="hidden" name="principal_id" value="<?= intval($perm['principal_id']); ?>">
                                                                <input type="hidden" name="folder_path" value="<?= h($folder); ?>">
                                                                <input type="hidden" name="view" value="<?= h($view); ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">x</button>
                                                            </form>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($shared): ?>
                                                <div class="mt-2 input-group input-group-sm">
                                                    <input type="text" class="form-control" readonly id="share_link_<?= $fileId; ?>" value="<?= h($shareUrl); ?>">
                                                    <button class="btn btn-outline-secondary copy-link-btn" type="button" data-copy-target="share_link_<?= $fileId; ?>">Copy link</button>
                                                </div>
                                            <?php endif; ?>
                                        <?php elseif (intval($file['is_trashed']) === 0): ?>
                                            <span class="badge bg-info text-dark">Shared to you (<?= h($currentRole); ?>)</span>
                                            <?php if ($shared): ?>
                                                <div class="mt-2 input-group input-group-sm">
                                                    <input type="text" class="form-control" readonly id="share_link_shared_<?= $fileId; ?>" value="<?= h($shareUrl); ?>">
                                                    <button class="btn btn-outline-secondary copy-link-btn" type="button" data-copy-target="share_link_shared_<?= $fileId; ?>">Copy link</button>
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
                                                <?php if ($canEditFile): ?>
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
                                                            <?php if (($file['folder_path'] ?? '') !== '' && !in_array($file['folder_path'], $allFolderPaths, true)): ?>
                                                                <option value="<?= h($file['folder_path']); ?>" selected><?= h($file['folder_path']); ?></option>
                                                            <?php endif; ?>
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
                                                    <form method="post" enctype="multipart/form-data" class="d-flex gap-2">
                                                        <input type="hidden" name="action" value="upload_new_version">
                                                        <input type="hidden" name="file_id" value="<?= $fileId; ?>">
                                                        <input type="hidden" name="folder_path" value="<?= h($folder); ?>">
                                                        <input type="hidden" name="view" value="<?= h($view); ?>">
                                                        <input type="file" class="form-control form-control-sm" name="version_file" required>
                                                        <button class="btn btn-sm btn-outline-dark" type="submit">New Version</button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if (!empty($fileVersionsMap[$fileId])): ?>
                                                    <div class="border rounded p-2">
                                                        <div class="small fw-semibold mb-1">Version History</div>
                                                        <?php foreach ($fileVersionsMap[$fileId] as $ver): ?>
                                                            <div class="small border rounded px-2 py-1 mb-1 d-flex justify-content-between align-items-center">
                                                                <span><?= h('v' . intval($ver['version_no']) . ' - ' . $ver['original_name']); ?> (<?= h(format_size(intval($ver['size_bytes']))); ?>)</span>
                                                                <div class="d-flex gap-1">
                                                                    <a class="btn btn-sm btn-outline-secondary py-0 px-2" href="<?= h($baseUrl); ?>pasker_drive_version_download.php?version_id=<?= intval($ver['id']); ?>">Download</a>
                                                                    <?php if ($canEditFile): ?>
                                                                        <form method="post" onsubmit="return confirm('Restore this version as current?');">
                                                                            <input type="hidden" name="action" value="restore_version">
                                                                            <input type="hidden" name="file_id" value="<?= $fileId; ?>">
                                                                            <input type="hidden" name="version_id" value="<?= intval($ver['id']); ?>">
                                                                            <input type="hidden" name="folder_path" value="<?= h($folder); ?>">
                                                                            <input type="hidden" name="view" value="<?= h($view); ?>">
                                                                            <button class="btn btn-sm btn-outline-primary py-0 px-2" type="submit">Restore</button>
                                                                        </form>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($canEditFile && isset($inlineEditableMap[$fileId])): ?>
                                                    <details class="border rounded p-2">
                                                        <summary class="small fw-semibold">Inline Text Editor</summary>
                                                        <form method="post" class="mt-2">
                                                            <input type="hidden" name="action" value="save_inline_text">
                                                            <input type="hidden" name="file_id" value="<?= $fileId; ?>">
                                                            <input type="hidden" name="folder_path" value="<?= h($folder); ?>">
                                                            <input type="hidden" name="view" value="<?= h($view); ?>">
                                                            <textarea class="form-control form-control-sm mb-2" name="inline_content" rows="8"><?= h($inlineEditableMap[$fileId]); ?></textarea>
                                                            <button class="btn btn-sm btn-outline-primary" type="submit">Save Inline Edit</button>
                                                        </form>
                                                    </details>
                                                <?php endif; ?>
                                                <div class="border rounded p-2">
                                                    <div class="small fw-semibold mb-1">Comments</div>
                                                    <?php if (!empty($fileCommentsMap[$fileId])): ?>
                                                        <?php foreach ($fileCommentsMap[$fileId] as $cm): ?>
                                                            <div class="small border rounded p-1 mb-1">
                                                                <div class="d-flex justify-content-between align-items-start">
                                                                    <span><strong><?= h($cm['user_label']); ?></strong></span>
                                                                    <span class="text-muted"><?= h($cm['created_at']); ?></span>
                                                                </div>
                                                                <div><?= h($cm['comment_text']); ?></div>
                                                                <?php if ($canEditFile || intval($cm['user_id']) === $userId): ?>
                                                                    <form method="post" class="mt-1 text-end">
                                                                        <input type="hidden" name="action" value="delete_comment">
                                                                        <input type="hidden" name="file_id" value="<?= $fileId; ?>">
                                                                        <input type="hidden" name="comment_id" value="<?= intval($cm['id']); ?>">
                                                                        <input type="hidden" name="folder_path" value="<?= h($folder); ?>">
                                                                        <input type="hidden" name="view" value="<?= h($view); ?>">
                                                                        <button class="btn btn-sm btn-outline-danger py-0 px-2" type="submit">Delete</button>
                                                                    </form>
                                                                <?php endif; ?>
                                                                <?php if (!empty($commentRepliesMap[intval($cm['id'])])): ?>
                                                                    <div class="ms-2 mt-1">
                                                                        <?php foreach ($commentRepliesMap[intval($cm['id'])] as $rep): ?>
                                                                            <div class="border rounded p-1 mb-1 bg-light">
                                                                                <div class="d-flex justify-content-between">
                                                                                    <span><strong><?= h($rep['user_label']); ?></strong></span>
                                                                                    <span class="text-muted"><?= h($rep['created_at']); ?></span>
                                                                                </div>
                                                                                <div><?= h($rep['reply_text']); ?></div>
                                                                                <?php if ($canEditFile || intval($rep['user_id']) === $userId): ?>
                                                                                    <form method="post" class="mt-1 text-end">
                                                                                        <input type="hidden" name="action" value="delete_comment_reply">
                                                                                        <input type="hidden" name="file_id" value="<?= $fileId; ?>">
                                                                                        <input type="hidden" name="reply_id" value="<?= intval($rep['id']); ?>">
                                                                                        <input type="hidden" name="folder_path" value="<?= h($folder); ?>">
                                                                                        <input type="hidden" name="view" value="<?= h($view); ?>">
                                                                                        <button class="btn btn-sm btn-outline-danger py-0 px-2" type="submit">Delete Reply</button>
                                                                                    </form>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <?php if ($canCommentFile): ?>
                                                                    <form method="post" class="d-flex gap-1 mt-1">
                                                                        <input type="hidden" name="action" value="add_comment_reply">
                                                                        <input type="hidden" name="file_id" value="<?= $fileId; ?>">
                                                                        <input type="hidden" name="comment_id" value="<?= intval($cm['id']); ?>">
                                                                        <input type="hidden" name="folder_path" value="<?= h($folder); ?>">
                                                                        <input type="hidden" name="view" value="<?= h($view); ?>">
                                                                        <input type="text" class="form-control form-control-sm" name="reply_text" maxlength="2000" placeholder="Reply..." required>
                                                                        <button class="btn btn-sm btn-outline-secondary" type="submit">Reply</button>
                                                                    </form>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <div class="small text-muted mb-1">No comments yet.</div>
                                                    <?php endif; ?>
                                                    <?php if ($canCommentFile): ?>
                                                        <form method="post" class="d-flex gap-1">
                                                            <input type="hidden" name="action" value="add_comment">
                                                            <input type="hidden" name="file_id" value="<?= $fileId; ?>">
                                                            <input type="hidden" name="folder_path" value="<?= h($folder); ?>">
                                                            <input type="hidden" name="view" value="<?= h($view); ?>">
                                                            <input type="text" class="form-control form-control-sm" name="comment_text" maxlength="2000" placeholder="Write a comment..." required>
                                                            <button class="btn btn-sm btn-outline-primary" type="submit">Send</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <?php if ($isOwner): ?>
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
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($files) === 0): ?>
                                <tr>
                                    <td colspan="<?= $view === 'shared' ? '8' : '7'; ?>" class="text-center text-muted">No files found in this view.</td>
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

    document.querySelectorAll('.copy-link-btn').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const targetId = btn.getAttribute('data-copy-target');
            const input = targetId ? document.getElementById(targetId) : null;
            if (!input) return;
            const text = input.value || '';
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                } else {
                    input.focus();
                    input.select();
                    document.execCommand('copy');
                }
                const original = btn.textContent;
                btn.textContent = 'Copied';
                setTimeout(() => { btn.textContent = original; }, 1200);
            } catch (e) {
                const original = btn.textContent;
                btn.textContent = 'Failed';
                setTimeout(() => { btn.textContent = original; }, 1200);
            }
        });
    });
})();
</script>
</body>
</html>
<?php $conn->close(); ?>
