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

// Build safe filters (whitelisted fields only)
// Type hints: int | string | date | datetime
$allowedTypes = [
	'id' => 'int',
	'provinsi' => 'string',
	'kab_kota' => 'string',
	'kecamatan' => 'string',
	'kelurahan' => 'string',
	'umur' => 'string',
	'kelompok_umur' => 'string',
	'jenis_kelamin' => 'string',
	'kondisi_fisik' => 'string',
	'marital' => 'string',
	'status_bekerja' => 'string',
	'tanggal_daftar' => 'date',
	'tanggal_kedaluwarsa' => 'date',
	'status_profil' => 'string',
	'status_pencaker' => 'string',
	'pendidikan' => 'string',
	'kelompok_pengalaman' => 'string',
	'durasi_pengalaman' => 'string',
	'sertifikasi' => 'string',
	'lembaga' => 'string',
	'progpel' => 'string',
	'keahlian' => 'string',
	'rencana_kerja_luar_negeri' => 'string',
	'negara_tujuan' => 'string',
	'lamaran_diajukan' => 'string',
	'jurusan' => 'string',
	'lembaga_pendidikan' => 'string',
	'bulan_daftar' => 'string',
	'created_date' => 'datetime',
	'id_pencaker' => 'string',
	'jenis_disabilitas' => 'string',
	'tahun_input' => 'string',
	'pengalaman' => 'string',
	'nik' => 'string',
	'alamat' => 'string',
	'tanggal_perubahan_status_pencaker' => 'date'
];

// Intersect with existing table columns to avoid SQL errors if schema differs
$existingCols = [];
if ($resCols = $conn->query('SHOW COLUMNS FROM job_seekers')) {
	while ($c = $resCols->fetch_assoc()) { $existingCols[] = $c['Field']; }
}
$allowed = [];
foreach ($allowedTypes as $col => $t) {
	if (in_array($col, $existingCols, true)) { $allowed[$col] = $t; }
}

function sanitize_like_val(string $s): string {
	return '%' . str_replace(['%', '_'], ['\\%', '\\_'], $s) . '%';
}

$whereParts = [];
$types = '';
$params = [];

// Support: ids=1,2,3 (ID IN (...))
if (!empty($_GET['ids'])) {
	$ids = array_values(array_filter(array_map('intval', explode(',', (string)$_GET['ids'])), fn($v) => $v > 0));
	if (!empty($ids)) {
		$in = implode(',', array_fill(0, count($ids), '?'));
		$whereParts[] = 'id IN (' . $in . ')';
		$types .= str_repeat('i', count($ids));
		$params = array_merge($params, $ids);
	}
}

// Exact match and LIKE match per field
foreach ($allowed as $col => $t) {
	if (isset($_GET[$col]) && $_GET[$col] !== '') {
		$whereParts[] = "$col = ?";
		$types .= ($t === 'int') ? 'i' : 's';
		$params[] = $t === 'int' ? intval($_GET[$col]) : (string)$_GET[$col];
	}
	$likeKey = $col . '_like';
	if (isset($_GET[$likeKey]) && $_GET[$likeKey] !== '') {
		$whereParts[] = "$col LIKE ?";
		$types .= 's';
		$params[] = sanitize_like_val((string)$_GET[$likeKey]);
	}
	// Date/time ranges: field_from, field_to
	if (($t === 'date' || $t === 'datetime')) {
		$fromKey = $col . '_from';
		$toKey = $col . '_to';
		if (isset($_GET[$fromKey]) && $_GET[$fromKey] !== '') {
			$whereParts[] = "$col >= ?";
			$types .= 's';
			$params[] = (string)$_GET[$fromKey];
		}
		if (isset($_GET[$toKey]) && $_GET[$toKey] !== '') {
			$whereParts[] = "$col <= ?";
			$types .= 's';
			$params[] = (string)$_GET[$toKey];
		}
	}
}

// Pagination to prevent memory exhaustion
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
if ($limit < 1) { $limit = 1; }
if ($limit > 1000) { $limit = 1000; }
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
if ($offset < 0) { $offset = 0; }

$sql = 'SELECT * FROM job_seekers';
if (!empty($whereParts)) { $sql .= ' WHERE ' . implode(' AND ', $whereParts); }
$sql .= ' ORDER BY id DESC LIMIT ? OFFSET ?';

// Bind dynamic filters + limit/offset
$typesWithPage = $types . 'ii';
$stmt = $conn->prepare($sql);
if ($types !== '') {
	$stmt->bind_param($typesWithPage, ...array_merge($params, [$limit, $offset]));
} else {
	$stmt->bind_param('ii', $limit, $offset);
}
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


