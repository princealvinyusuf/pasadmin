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

$storageDir = __DIR__ . '/downloads/pasker_drive';
if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0775, true);
}

$userId = intval($_SESSION['user_id'] ?? 0);
$maxUploadBytes = 100 * 1024 * 1024; // 100 MB
$dangerousExt = ['php', 'phtml', 'phar', 'htaccess', 'cgi', 'pl', 'exe', 'sh', 'bat'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'upload') {
        if (!isset($_FILES['files'])) {
            flash_set('pasker_drive_error', 'No file payload received.');
            header('Location: pasker_drive.php');
            exit;
        }

        $uploaded = 0;
        $failed = 0;
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
            $stmt = $conn->prepare('INSERT INTO pasker_drive_files (owner_user_id, original_name, storage_name, mime_type, size_bytes, path_rel) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('isssis', $userId, $originalName, $storageName, $mime, $size, $pathRel);
            $stmt->execute();
            $stmt->close();
            $uploaded++;
        }

        if ($uploaded > 0) {
            flash_set('pasker_drive_success', 'Upload complete: ' . $uploaded . ' file(s) uploaded.');
        }
        if ($failed > 0) {
            flash_set('pasker_drive_error', 'Some files failed to upload (' . $failed . ').');
        }
        header('Location: pasker_drive.php');
        exit;
    }

    if ($action === 'rename') {
        $fileId = intval($_POST['file_id'] ?? 0);
        $newName = sanitize_display_name((string)($_POST['new_name'] ?? ''));

        if ($fileId <= 0 || $newName === '') {
            flash_set('pasker_drive_error', 'Invalid rename request.');
            header('Location: pasker_drive.php');
            exit;
        }

        $stmt = $conn->prepare('UPDATE pasker_drive_files SET original_name=? WHERE id=? AND owner_user_id=?');
        $stmt->bind_param('sii', $newName, $fileId, $userId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        if ($affected > 0) {
            flash_set('pasker_drive_success', 'File renamed.');
        } else {
            flash_set('pasker_drive_error', 'File not found or no change.');
        }
        header('Location: pasker_drive.php');
        exit;
    }

    if ($action === 'delete') {
        $fileId = intval($_POST['file_id'] ?? 0);
        if ($fileId <= 0) {
            flash_set('pasker_drive_error', 'Invalid delete request.');
            header('Location: pasker_drive.php');
            exit;
        }

        $select = $conn->prepare('SELECT storage_name FROM pasker_drive_files WHERE id=? AND owner_user_id=? LIMIT 1');
        $select->bind_param('ii', $fileId, $userId);
        $select->execute();
        $select->bind_result($storageName);
        $found = $select->fetch();
        $select->close();

        if (!$found) {
            flash_set('pasker_drive_error', 'File not found.');
            header('Location: pasker_drive.php');
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
        flash_set('pasker_drive_success', 'File deleted.');
        header('Location: pasker_drive.php');
        exit;
    }

    if ($action === 'share_update') {
        $fileId = intval($_POST['file_id'] ?? 0);
        $linkMode = (string)($_POST['link_mode'] ?? 'private');
        $canDownload = isset($_POST['can_download']) ? 1 : 0;
        $expiresRaw = trim((string)($_POST['expires_at'] ?? ''));

        if ($fileId <= 0 || !in_array($linkMode, ['private', 'anyone_with_link'], true)) {
            flash_set('pasker_drive_error', 'Invalid share request.');
            header('Location: pasker_drive.php');
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
            header('Location: pasker_drive.php');
            exit;
        }

        if ($linkMode === 'private') {
            $stmt = $conn->prepare('DELETE FROM pasker_drive_shares WHERE file_id=?');
            $stmt->bind_param('i', $fileId);
            $stmt->execute();
            $stmt->close();
            flash_set('pasker_drive_success', 'Sharing disabled.');
            header('Location: pasker_drive.php');
            exit;
        }

        $expiresAt = null;
        if ($expiresRaw !== '') {
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $expiresRaw);
            if (!$dt) {
                flash_set('pasker_drive_error', 'Invalid expiration format.');
                header('Location: pasker_drive.php');
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

        flash_set('pasker_drive_success', 'Sharing updated.');
        header('Location: pasker_drive.php');
        exit;
    }
}

$search = trim((string)($_GET['q'] ?? ''));
$whereSql = 'WHERE f.owner_user_id=?';
$types = 'i';
$params = [$userId];

if ($search !== '') {
    $whereSql .= ' AND f.original_name LIKE ?';
    $types .= 's';
    $params[] = '%' . $search . '%';
}

$sql = "SELECT f.*, s.share_token, s.access_type, s.can_download, s.expires_at
        FROM pasker_drive_files f
        LEFT JOIN pasker_drive_shares s ON s.file_id=f.id
        $whereSql
        ORDER BY f.updated_at DESC";
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
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h3 class="mb-1">Pasker Drive</h3>
            <div class="text-muted">Upload, share, and manage cloud files for your team.</div>
        </div>
        <div class="text-end">
            <div class="small text-muted">Total files</div>
            <div class="fw-semibold"><?= count($files); ?> file(s)</div>
            <div class="small text-muted">Total storage: <?= h(format_size($totalBytes)); ?></div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= h($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= h($error); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h5 class="mb-3"><i class="bi bi-cloud-upload me-1"></i>Upload Files</h5>
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="action" value="upload">
                <div class="col-md-9">
                    <input type="file" class="form-control" name="files[]" multiple required>
                    <div class="form-text">Max file size: 100 MB each. Multiple upload supported.</div>
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
                <div class="col-md-9">
                    <input type="text" class="form-control" name="q" placeholder="Search files by name..." value="<?= h($search); ?>">
                </div>
                <div class="col-md-3 d-grid">
                    <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search me-1"></i>Search</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead>
                    <tr>
                        <th>File</th>
                        <th>Size</th>
                        <th>Type</th>
                        <th>Updated</th>
                        <th style="min-width: 320px;">Share</th>
                        <th style="min-width: 260px;">Actions</th>
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
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= h($file['original_name']); ?></div>
                                <div class="text-muted small">ID #<?= $fileId; ?></div>
                            </td>
                            <td><?= h(format_size(intval($file['size_bytes'] ?? 0))); ?></td>
                            <td><?= h($file['mime_type'] ?: '-'); ?></td>
                            <td><?= h($file['updated_at']); ?></td>
                            <td>
                                <form method="post" class="row g-2">
                                    <input type="hidden" name="action" value="share_update">
                                    <input type="hidden" name="file_id" value="<?= $fileId; ?>">
                                    <div class="col-12">
                                        <select class="form-select form-select-sm" name="link_mode">
                                            <option value="private" <?= !$shared ? 'selected' : ''; ?>>Private (Only me)</option>
                                            <option value="anyone_with_link" <?= $shared ? 'selected' : ''; ?>>Anyone with link</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <input type="datetime-local" class="form-control form-control-sm" name="expires_at" value="<?= h($expiresInput); ?>" placeholder="Expiration (optional)">
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
                            </td>
                            <td>
                                <div class="d-grid gap-2">
                                    <a class="btn btn-sm btn-success" href="<?= h($baseUrl); ?>pasker_drive_download.php?id=<?= $fileId; ?>">
                                        <i class="bi bi-download me-1"></i>Download
                                    </a>
                                    <form method="post" class="d-flex gap-2">
                                        <input type="hidden" name="action" value="rename">
                                        <input type="hidden" name="file_id" value="<?= $fileId; ?>">
                                        <input type="text" class="form-control form-control-sm" name="new_name" value="<?= h($file['original_name']); ?>" maxlength="255" required>
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">Rename</button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('Delete this file?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="file_id" value="<?= $fileId; ?>">
                                        <button class="btn btn-sm btn-outline-danger w-100" type="submit"><i class="bi bi-trash me-1"></i>Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($files) === 0): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">No files yet. Upload your first file to start using Pasker Drive.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
