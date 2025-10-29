<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
set_time_limit(0);
ignore_user_abort(true);

require_once __DIR__ . '/db.php';

// Auth: API key
$apiKey = '';
if (!empty($_SERVER['HTTP_X_API_KEY'])) { $apiKey = trim($_SERVER['HTTP_X_API_KEY']); }
if ($apiKey === '' && isset($_GET['key'])) { $apiKey = trim((string)$_GET['key']); }
if ($apiKey === '') {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'missing_api_key']);
    exit;
}

// Ensure api_keys exists
$conn->query("CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    api_key VARCHAR(128) NOT NULL UNIQUE,
    scopes VARCHAR(255) NOT NULL DEFAULT 'job_seekers_read',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Validate
$stmt = $conn->prepare('SELECT id, scopes FROM api_keys WHERE api_key=? AND is_active=1 LIMIT 1');
$stmt->bind_param('s', $apiKey);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
if (!$row) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'invalid_api_key']);
    exit;
}
$scopes = array_filter(array_map('trim', explode(',', (string)$row['scopes'])));
if (!in_array('job_seekers_read', $scopes, true)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'forbidden_scope']);
    exit;
}

// Update last_used_at
$conn->query('UPDATE api_keys SET last_used_at=NOW() WHERE id=' . intval($row['id']));

// Output format
$format = strtolower(trim($_GET['format'] ?? 'ndjson'));
if ($format === 'jsonl') { $format = 'ndjson'; }
if (!in_array($format, ['ndjson','csv','json'], true)) { $format = 'ndjson'; }

// Optional download hint
$download = isset($_GET['download']) && $_GET['download'] === '1';

// Disable output buffering for streaming
while (ob_get_level() > 0) { ob_end_flush(); }
flush();

// Stream query without buffering results
$result = $conn->query('SELECT * FROM job_seekers ORDER BY id DESC', MYSQLI_USE_RESULT);
if (!$result) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'query_failed']);
    exit;
}

if ($format === 'ndjson') {
    header('Content-Type: application/x-ndjson');
    if ($download) { header('Content-Disposition: attachment; filename="job_seekers.ndjson"'); }
    while ($r = $result->fetch_assoc()) {
        echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
        flush();
    }
} elseif ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    if ($download) { header('Content-Disposition: attachment; filename="job_seekers.csv"'); }
    $out = fopen('php://output', 'w');
    // Derive columns from table schema to avoid buffering first row
    $cols = [];
    if ($resCols = $conn->query('SHOW COLUMNS FROM job_seekers')) {
        while ($c = $resCols->fetch_assoc()) { $cols[] = $c['Field']; }
        $resCols->close();
    }
    if (!empty($cols)) { fputcsv($out, $cols); }
    while ($r = $result->fetch_assoc()) {
        if (empty($cols)) { $cols = array_keys($r); fputcsv($out, $cols); }
        $line = [];
        foreach ($cols as $k) { $line[] = isset($r[$k]) ? $r[$k] : ''; }
        fputcsv($out, $line);
        fflush($out);
    }
    fclose($out);
} else { // json array streaming
    header('Content-Type: application/json');
    if ($download) { header('Content-Disposition: attachment; filename="job_seekers.json"'); }
    echo '{"data":[';
    $first = true;
    while ($r = $result->fetch_assoc()) {
        if ($first) { $first = false; } else { echo ','; }
        echo json_encode($r, JSON_UNESCAPED_UNICODE);
        flush();
    }
    echo ']}';
}

// Cleanup
$result->close();
exit;


