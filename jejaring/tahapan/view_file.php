<?php
function renderErrorPage($title, $message) {
    http_response_code($title === '404 Not Found' ? 404 : 400);
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - {$title}</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f8f9fa; color: #343a40; text-align: center; padding: 50px; }
            .container { max-width: 600px; margin: auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
            h1 { color: #dc3545; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Oops! Terjadi Kesalahan</h1>
            <p>{$message}</p>
            <a href="javascript:history.back()">Kembali ke halaman sebelumnya</a>
        </div>
    </body>
    </html>
HTML;
    exit;
}

if (!isset($_GET['file']) || empty(trim($_GET['file']))) {
    renderErrorPage('400 Bad Request', 'Parameter nama file tidak valid atau kosong.');
}

$file = basename($_GET['file']);
$path = __DIR__ . "/uploads/" . $file;

if (!file_exists($path) || !is_readable($path)) {
    renderErrorPage('404 Not Found', 'File yang Anda minta tidak ditemukan di server.');
}

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mime_types = [
    'pdf'  => 'application/pdf',
    'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png'  => 'image/png',
    'gif'  => 'image/gif',  'webp' => 'image/webp', 'svg'  => 'image/svg+xml',
    'doc'  => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt'  => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
];

$office_docs = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
$viewable_directly = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

if (in_array($ext, $office_docs)) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $publicUrl = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/uploads/" . rawurlencode($file);
    
    $googleViewerUrl = "https://docs.google.com/gview?url=" . urlencode($publicUrl) . "&embedded=true";
    header("Location: " . $googleViewerUrl);
    exit;
} elseif (in_array($ext, $viewable_directly)) {
    $mime = $mime_types[$ext] ?? 'application/octet-stream';
    
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . basename($path) . '"');
    header('Content-Length: ' . filesize($path));
    header('Accept-Ranges: bytes');
    
    @ob_end_clean();
    flush();
    readfile($path);
    exit;
} else {
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Pratinjau Tidak Tersedia</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f8f9fa; color: #343a40; text-align: center; padding: 50px; }
            .container { max-width: 600px; margin: auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
            h1 { color: #007bff; }
            .filename { font-family: monospace; background: #e9ecef; padding: 5px 10px; border-radius: 4px; }
            .download-btn { display: inline-block; margin-top: 20px; padding: 12px 25px; background-color: #28a745; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Pratinjau Tidak Tersedia</h1>
            <p>Pratinjau tidak dapat ditampilkan untuk file dengan format <strong class="filename">.{$ext}</strong>.</p>
            <p>Silakan unduh file untuk melihat isinya.</p>
            <a href="uploads/{$file}" class="download-btn" download>Unduh File</a>
        </div>
    </body>
    </html>
HTML;
    exit;
}


