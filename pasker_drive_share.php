<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES);
}

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '') {
    http_response_code(400);
    echo 'Missing token.';
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'job_admin_prod');
$sql = "SELECT f.original_name, f.storage_name, f.mime_type, f.size_bytes, s.can_download, s.expires_at
        FROM pasker_drive_shares s
        JOIN pasker_drive_files f ON f.id = s.file_id
        WHERE s.share_token=? AND s.access_type='anyone_with_link' AND f.is_trashed=0
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $token);
$stmt->execute();
$res = $stmt->get_result();
$file = $res->fetch_assoc();
$stmt->close();
$conn->close();

if (!$file) {
    http_response_code(404);
    echo 'Share link not found.';
    exit;
}

if (!empty($file['expires_at']) && strtotime((string)$file['expires_at']) < time()) {
    http_response_code(410);
    echo 'This share link has expired.';
    exit;
}

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '/pasker_drive_share.php';
$dir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
$basePath = ($dir === '' || $dir === '.') ? '/' : ($dir . '/');
$fullBase = 'https://' . $host . $basePath;
$shareUrl = $fullBase . 'pasker_drive_share.php?token=' . urlencode($token);
$previewUrl = $shareUrl . '&preview=1';
$downloadUrl = $shareUrl . '&download=1';
$isImage = strpos((string)($file['mime_type'] ?? ''), 'image/') === 0;
$ogImage = $isImage ? $previewUrl : 'https://paskerid.kemnaker.go.id/images/services/logo.png';
$ogTitle = 'Pasker Drive: ' . (string)$file['original_name'];
$ogDescription = 'Shared file preview and download link.';

if (intval($_GET['preview'] ?? 0) === 1) {
    $absolutePath = __DIR__ . '/downloads/pasker_drive/' . basename((string)$file['storage_name']);
    if (!is_file($absolutePath)) {
        http_response_code(404);
        echo 'File missing.';
        exit;
    }
    $mime = (string)($file['mime_type'] ?: 'application/octet-stream');
    $size = filesize($absolutePath);
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . str_replace('"', '', (string)$file['original_name']) . '"');
    header('Content-Length: ' . $size);
    header('Cache-Control: private, max-age=0, must-revalidate');
    readfile($absolutePath);
    exit;
}

if (intval($_GET['download'] ?? 0) === 1) {
    if (intval($file['can_download'] ?? 0) !== 1) {
        http_response_code(403);
        echo 'Download is disabled for this link.';
        exit;
    }
    $absolutePath = __DIR__ . '/downloads/pasker_drive/' . basename((string)$file['storage_name']);
    if (!is_file($absolutePath)) {
        http_response_code(404);
        echo 'File missing.';
        exit;
    }

    $mime = (string)($file['mime_type'] ?: 'application/octet-stream');
    $size = filesize($absolutePath);
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', (string)$file['original_name']) . '"');
    header('Content-Length: ' . $size);
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    readfile($absolutePath);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($ogTitle); ?></title>
    <meta name="description" content="<?= h($ogDescription); ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= h($ogTitle); ?>">
    <meta property="og:description" content="<?= h($ogDescription); ?>">
    <meta property="og:url" content="<?= h($shareUrl); ?>">
    <meta property="og:image" content="<?= h($ogImage); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= h($ogTitle); ?>">
    <meta name="twitter:description" content="<?= h($ogDescription); ?>">
    <meta name="twitter:image" content="<?= h($ogImage); ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <h4 class="mb-3">Pasker Drive - Shared File</h4>
                    <div class="mb-2"><strong>Name:</strong> <?= h($file['original_name']); ?></div>
                    <div class="mb-2"><strong>Size:</strong> <?= h(number_format(((int)$file['size_bytes']) / 1024, 2)); ?> KB</div>
                    <?php if (!empty($file['expires_at'])): ?>
                        <div class="mb-3"><strong>Expires at:</strong> <?= h($file['expires_at']); ?></div>
                    <?php endif; ?>
                    <?php if (intval($file['can_download'] ?? 0) === 1): ?>
                        <a class="btn btn-outline-primary me-2" href="<?= h($previewUrl); ?>" target="_blank" rel="noopener">Preview</a>
                        <a class="btn btn-primary" href="<?= h($downloadUrl); ?>">Download File</a>
                    <?php else: ?>
                        <a class="btn btn-outline-primary" href="<?= h($previewUrl); ?>" target="_blank" rel="noopener">Preview</a>
                        <div class="alert alert-warning mt-3 mb-0">Owner has disabled download for this link.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
