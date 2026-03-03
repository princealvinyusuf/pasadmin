<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

function e(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES);
}

$kind = strtolower(trim((string)($_GET['kind'] ?? 'file')));
if (!in_array($kind, ['document', 'file'], true)) {
    $kind = 'file';
}
$name = trim((string)($_GET['name'] ?? 'Shared File'));
if ($name === '') {
    $name = 'Shared File';
}
$name = mb_substr($name, 0, 80);

$title = $kind === 'document' ? 'Document Shared' : 'File Shared';
$accent = $kind === 'document' ? '#16a34a' : '#0ea5e9';
$iconText = $kind === 'document' ? 'DOC' : 'FILE';

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=300');

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630" viewBox="0 0 1200 630" role="img" aria-label="Pasker Drive shared file">
  <rect x="0" y="0" width="1200" height="630" fill="#0b1324"/>
  <rect x="40" y="40" width="1120" height="550" rx="24" fill="#101a31" stroke="#1f2a44" stroke-width="2"/>
  <rect x="100" y="120" width="180" height="220" rx="16" fill="<?php echo e($accent); ?>"/>
  <rect x="135" y="165" width="110" height="145" rx="10" fill="#ffffff"/>
  <rect x="160" y="205" width="60" height="10" rx="5" fill="#cbd5e1"/>
  <rect x="160" y="228" width="60" height="10" rx="5" fill="#cbd5e1"/>
  <rect x="160" y="251" width="60" height="10" rx="5" fill="#cbd5e1"/>
  <text x="190" y="325" text-anchor="middle" font-family="Arial, sans-serif" font-size="30" font-weight="700" fill="#ffffff"><?php echo e($iconText); ?></text>

  <text x="330" y="180" font-family="Arial, sans-serif" font-size="48" font-weight="700" fill="#f8fafc">Pasker Drive</text>
  <text x="330" y="240" font-family="Arial, sans-serif" font-size="36" font-weight="600" fill="#cbd5e1"><?php echo e($title); ?></text>
  <text x="330" y="312" font-family="Arial, sans-serif" font-size="34" font-weight="500" fill="#e2e8f0"><?php echo e($name); ?></text>
  <text x="330" y="370" font-family="Arial, sans-serif" font-size="28" fill="#94a3b8">Shared file preview and download link</text>

  <rect x="330" y="430" width="340" height="64" rx="12" fill="<?php echo e($accent); ?>"/>
  <text x="500" y="472" text-anchor="middle" font-family="Arial, sans-serif" font-size="30" font-weight="700" fill="#ffffff">OPEN SHARED FILE</text>
</svg>
