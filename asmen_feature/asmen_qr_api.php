<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';

$s = isset($_GET['s']) ? trim((string)$_GET['s']) : '';
if ($s === '') {
	http_response_code(400);
	echo json_encode(['ok' => false, 'message' => 'Missing parameter']);
	exit;
}

// Prefer kode_register (new format), fallback qr_secret (legacy)
$stmt = $conn->prepare('SELECT * FROM asmen_assets WHERE kode_register=?');
$stmt->bind_param('s', $s);
$stmt->execute();
$asset = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$asset) {
	$stmt = $conn->prepare('SELECT * FROM asmen_assets WHERE qr_secret=?');
	$stmt->bind_param('s', $s);
	$stmt->execute();
	$asset = $stmt->get_result()->fetch_assoc();
	$stmt->close();
}

if (!$asset) {
	http_response_code(404);
	echo json_encode(['ok' => false, 'message' => 'Asset not found']);
	exit;
}

foreach (['qr_secret', 'created_at', 'updated_at'] as $hiddenField) {
	unset($asset[$hiddenField]);
}

echo json_encode(
	[
		'ok' => true,
		'asset' => $asset,
	],
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
exit;

