<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$kind = strtolower(trim((string)($_GET['kind'] ?? 'file')));
if (!in_array($kind, ['document', 'file'], true)) {
    $kind = 'file';
}

$name = trim((string)($_GET['name'] ?? 'Shared File'));
if ($name === '') {
    $name = 'Shared File';
}
$name = mb_substr($name, 0, 70);

$width = 1200;
$height = 630;
$img = imagecreatetruecolor($width, $height);
imageantialias($img, true);

$bg = imagecolorallocate($img, 11, 19, 36);
$panel = imagecolorallocate($img, 16, 26, 49);
$panelBorder = imagecolorallocate($img, 31, 42, 68);
$white = imagecolorallocate($img, 248, 250, 252);
$muted = imagecolorallocate($img, 203, 213, 225);
$muted2 = imagecolorallocate($img, 148, 163, 184);
$accent = $kind === 'document'
    ? imagecolorallocate($img, 22, 163, 74)
    : imagecolorallocate($img, 14, 165, 233);

imagefilledrectangle($img, 0, 0, $width, $height, $bg);
imagefilledrectangle($img, 40, 40, 1160, 590, $panel);
imagerectangle($img, 40, 40, 1160, 590, $panelBorder);

// File icon box
imagefilledrectangle($img, 100, 120, 280, 340, $accent);
imagefilledrectangle($img, 135, 165, 245, 310, $white);
$line = imagecolorallocate($img, 203, 213, 225);
imagefilledrectangle($img, 160, 205, 220, 214, $line);
imagefilledrectangle($img, 160, 228, 220, 237, $line);
imagefilledrectangle($img, 160, 251, 220, 260, $line);

// Text (built-in font for high compatibility)
$title = $kind === 'document' ? 'Document Shared' : 'File Shared';
$iconText = $kind === 'document' ? 'DOC' : 'FILE';
imagestring($img, 5, 165, 320, $iconText, $white);
imagestring($img, 5, 330, 150, 'Pasker Drive', $white);
imagestring($img, 5, 330, 205, $title, $muted);
imagestring($img, 5, 330, 260, $name, $white);
imagestring($img, 4, 330, 315, 'Shared file preview and download link', $muted2);

// CTA bar
imagefilledrectangle($img, 330, 430, 670, 494, $accent);
imagestring($img, 5, 390, 452, 'OPEN SHARED FILE', $white);

header('Content-Type: image/png');
header('Cache-Control: public, max-age=300');
imagepng($img);
imagedestroy($img);
exit;
