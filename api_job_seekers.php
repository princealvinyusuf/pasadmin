<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

// Read API key from header or query string
$apiKey = '';
if (!empty($_SERVER['HTTP_X_API_KEY'])) { $apiKey = trim($_SERVER['HTTP_X_API_KEY']); }
if ($apiKey === '' && isset($_GET['key'])) { $apiKey = trim((string)$_GET['key']); }

if ($apiKey === '') {
    http_response_code(401);
    echo json_encode(['error' => 'missing_api_key']);
    exit;
}

// Ensure api_keys table exists (defensive)
$conn->query("CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    api_key VARCHAR(128) NOT NULL UNIQUE,
    scopes VARCHAR(255) NOT NULL DEFAULT 'job_seekers_read',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Validate key
$stmt = $conn->prepare("SELECT id, scopes FROM api_keys WHERE api_key=? AND is_active=1 LIMIT 1");
$stmt->bind_param('s', $apiKey);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(401);
    echo json_encode(['error' => 'invalid_api_key']);
    exit;
}

// Check scope
$scopes = array_filter(array_map('trim', explode(',', (string)$row['scopes'])));
if (!in_array('job_seekers_read', $scopes, true)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden_scope']);
    exit;
}

// Update last_used_at
$conn->query('UPDATE api_keys SET last_used_at=NOW() WHERE id=' . intval($row['id']));

// Pagination to prevent memory exhaustion
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
if ($limit < 1) { $limit = 1; }
if ($limit > 1000) { $limit = 1000; }
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
if ($offset < 0) { $offset = 0; }

$stmt = $conn->prepare('SELECT * FROM job_seekers ORDER BY id DESC LIMIT ? OFFSET ?');
$stmt->bind_param('ii', $limit, $offset);
$stmt->execute();
$res2 = $stmt->get_result();
$data = [];
while ($r = $res2->fetch_assoc()) { $data[] = $r; }
$stmt->close();

$hasMore = count($data) === $limit; // heuristic without COUNT(*)

echo json_encode([
    'data' => $data,
    'pagination' => [
        'limit' => $limit,
        'offset' => $offset,
        'next_offset' => $offset + $limit,
        'has_more' => $hasMore
    ]
]);
exit;


