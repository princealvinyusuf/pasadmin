<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';

// Column schema based on provided table structure
$columns = [
    'provinsi' => ['label' => 'Provinsi', 'type' => 'text'],
    'kab_kota' => ['label' => 'Kab/Kota', 'type' => 'text'],
    'kecamatan' => ['label' => 'Kecamatan', 'type' => 'text'],
    'kelurahan' => ['label' => 'Kelurahan', 'type' => 'text'],
    'umur' => ['label' => 'Umur', 'type' => 'text'],
    'kelompok_umur' => ['label' => 'Kelompok Umur', 'type' => 'text'],
    'jenis_kelamin' => ['label' => 'Jenis Kelamin', 'type' => 'text'],
    'kondisi_fisik' => ['label' => 'Kondisi Fisik', 'type' => 'text'],
    'marital' => ['label' => 'Marital', 'type' => 'text'],
    'status_bekerja' => ['label' => 'Status Bekerja', 'type' => 'text'],
    'tanggal_daftar' => ['label' => 'Tanggal Daftar', 'type' => 'date'],
    'tanggal_kedaluwarsa' => ['label' => 'Tanggal Kedaluwarsa', 'type' => 'date'],
    'status_profil' => ['label' => 'Status Profil', 'type' => 'text'],
    'status_pencaker' => ['label' => 'Status Pencaker', 'type' => 'text'],
    'pendidikan' => ['label' => 'Pendidikan', 'type' => 'text'],
    'pengalaman' => ['label' => 'Pengalaman', 'type' => 'text'],
    'pengalaman_tahun' => ['label' => 'Pengalaman (Tahun)', 'type' => 'text'],
    'sertifikasi' => ['label' => 'Sertifikasi', 'type' => 'textarea'],
    'lembaga' => ['label' => 'Lembaga', 'type' => 'text'],
    'progpel' => ['label' => 'Progpel', 'type' => 'text'],
    'keahlian' => ['label' => 'Keahlian', 'type' => 'textarea'],
    'rencana_kerja_luar_negeri' => ['label' => 'Rencana Kerja LN', 'type' => 'text'],
    'negara_tujuan' => ['label' => 'Negara Tujuan', 'type' => 'text'],
    'lamaran_diajukan' => ['label' => 'Lamaran Diajukan', 'type' => 'text'],
    'jurusan' => ['label' => 'Jurusan', 'type' => 'text'],
    'lembaga_pendidikan' => ['label' => 'Lembaga Pendidikan', 'type' => 'text'],
    'bulan_daftar' => ['label' => 'Bulan Daftar', 'type' => 'text'],
    'created_date' => ['label' => 'Created Date', 'type' => 'datetime-local'],
    'id_pencaker' => ['label' => 'ID Pencaker', 'type' => 'text'],
    'jenis_disabilitas' => ['label' => 'Jenis Disabilitas', 'type' => 'text'],
    'tahun_input' => ['label' => 'Tahun Input', 'type' => 'number'],
    'pengalaman_kerja' => ['label' => 'Pengalaman Kerja', 'type' => 'text'],
];

// Helpers
function sanitize_like($s) { return '%' . str_replace(['%', '_'], ['\\%', '\\_'], $s) . '%'; }

// CRUD handlers
$action = $_GET['action'] ?? '';

if ($action === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare('DELETE FROM job_seekers WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header('Location: job_seekers.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Build dynamic insert/update from $columns
    $fields = array_keys($columns);
    if (isset($_POST['id']) && $_POST['id'] !== '') {
        // Update
        $id = intval($_POST['id']);
        $setParts = [];
        $types = '';
        $values = [];
        foreach ($fields as $f) {
            $setParts[] = "$f = ?";
            $types .= 's';
            $values[] = $_POST[$f] ?? null;
        }
        $types .= 'i';
        $values[] = $id;
        $sql = 'UPDATE job_seekers SET ' . implode(', ', $setParts) . ' WHERE id = ?';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
    } else {
        // Insert
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $types = str_repeat('s', count($fields));
        $values = [];
        foreach ($fields as $f) { $values[] = $_POST[$f] ?? null; }
        $sql = 'INSERT INTO job_seekers (' . implode(', ', $fields) . ") VALUES ($placeholders)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
    }
    header('Location: job_seekers.php');
    exit;
}

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === '1') {
    $search = trim($_GET['search'] ?? '');
    $where = '';
    $params = [];
    $types = '';
    if ($search !== '') {
        $like = sanitize_like($search);
        $where = 'WHERE provinsi LIKE ? OR kab_kota LIKE ? OR jenis_kelamin LIKE ? OR pendidikan LIKE ? OR id_pencaker LIKE ?';
        $params = [$like, $like, $like, $like, $like];
        $types = 'sssss';
    }
    $sql = 'SELECT id, ' . implode(', ', array_keys($columns)) . ' FROM job_seekers ' . $where . ' ORDER BY id DESC';
    $stmt = $conn->prepare($sql);
    if ($where !== '') { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $res = $stmt->get_result();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="job_seekers_export_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    $header = array_merge(['id'], array_map(function($k) use($columns){ return $columns[$k]['label']; }, array_keys($columns)) );
    fputcsv($out, $header);
    while ($row = $res->fetch_assoc()) {
        $line = [];
        $line[] = $row['id'];
        foreach ($columns as $name => $_) { $line[] = $row[$name] ?? ''; }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

// List view with pagination
$perPage = 50;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$search = trim($_GET['search'] ?? '');
$where = '';
$params = [];
$types = '';
if ($search !== '') {
    $like = sanitize_like($search);
    $where = 'WHERE provinsi LIKE ? OR kab_kota LIKE ? OR jenis_kelamin LIKE ? OR pendidikan LIKE ? OR id_pencaker LIKE ?';
    $params = [$like, $like, $like, $like, $like];
    $types = 'sssss';
}

// Count
$sqlCount = 'SELECT COUNT(*) AS cnt FROM job_seekers ' . $where;
$stmt = $conn->prepare($sqlCount);
if ($where !== '') { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$total = 0;
$stmt->bind_result($total);
$stmt->fetch();
$stmt->close();
$totalPages = max(1, intval(ceil($total / $perPage)));

// Fetch page data
$sqlList = 'SELECT id, ' . implode(', ', array_keys($columns)) . ' FROM job_seekers ' . $where . ' ORDER BY id DESC LIMIT ? OFFSET ?';
$stmt = $conn->prepare($sqlList);
if ($where !== '') {
    $stmt->bind_param($types . 'ii', ...$params, $perPage, $offset);
} else {
    $stmt->bind_param('ii', $perPage, $offset);
}
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Seekers</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f6f8fa; }
        .container { max-width: 1200px; }
        .table thead th { background: #f1f5f9; }
        .btn-sm { padding: .25rem .5rem; }
        .form-control, .form-select { border-radius: 6px; }
        .card { border-radius: 10px; }
        .sticky-actions { white-space: nowrap; }
        textarea.form-control { min-height: 70px; }
    </style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h2 class="mb-2 mb-md-0">Job Seekers</h2>
        <div class="d-flex gap-2">
            <form class="d-flex" method="get" action="job_seekers.php">
                <input class="form-control me-2" type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
            </form>
            <a class="btn btn-success" href="job_seekers.php?export=1<?php echo $search !== '' ? '&search=' . urlencode($search) : '';?>"><i class="bi bi-file-earmark-excel"></i> Export</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="post">
                <div class="row g-3">
                    <?php foreach ($columns as $name => $meta): ?>
                        <div class="col-12 col-md-6 col-lg-4">
                            <label class="form-label"><?php echo htmlspecialchars($meta['label']); ?></label>
                            <?php if ($meta['type'] === 'textarea'): ?>
                                <textarea class="form-control" name="<?php echo $name; ?>"></textarea>
                            <?php else: ?>
                                <input class="form-control" type="<?php echo $meta['type']; ?>" name="<?php echo $name; ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3">
                    <button class="btn btn-primary" type="submit">Add</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Actions</th>
                        <th>ID</th>
                        <?php foreach ($columns as $name => $meta): ?>
                            <th><?php echo htmlspecialchars($meta['label']); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="sticky-actions">
                            <a class="btn btn-sm btn-outline-primary" href="job_seekers.php?action=edit&id=<?php echo $row['id']; ?>#edit">Edit</a>
                            <a class="btn btn-sm btn-outline-danger" href="job_seekers.php?action=delete&id=<?php echo $row['id']; ?>" onclick="return confirm('Delete this record?');">Delete</a>
                        </td>
                        <td><?php echo $row['id']; ?></td>
                        <?php foreach ($columns as $name => $meta): ?>
                            <td><?php echo nl2br(htmlspecialchars((string)($row[$name] ?? ''))); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-body">
            <nav>
                <ul class="pagination mb-0">
                    <?php
                    $baseUrl = 'job_seekers.php?search=' . urlencode($search) . '&page=';
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    ?>
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo $page <= 1 ? '#' : $baseUrl . ($page - 1); ?>">Prev</a>
                    </li>
                    <?php for ($p = $start; $p <= $end; $p++): ?>
                        <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo $baseUrl . $p; ?>"><?php echo $p; ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="<?php echo $page >= $totalPages ? '#' : $baseUrl . ($page + 1); ?>">Next</a>
                    </li>
                </ul>
                <div class="text-muted small mt-2">Page <?php echo $page; ?> of <?php echo $totalPages; ?> (Total <?php echo $total; ?> records)</div>
            </nav>
        </div>
    </div>

    <?php if ($action === 'edit' && isset($_GET['id'])): 
        $id = intval($_GET['id']);
        $stmt = $conn->prepare('SELECT id, ' . implode(', ', array_keys($columns)) . ' FROM job_seekers WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $editRow = $stmt->get_result()->fetch_assoc();
    ?>
    <div id="edit" class="card mt-4">
        <div class="card-body">
            <h5 class="mb-3">Edit Job Seeker (ID: <?php echo $id; ?>)</h5>
            <form method="post">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <div class="row g-3">
                    <?php foreach ($columns as $name => $meta): $val = $editRow[$name] ?? ''; ?>
                        <div class="col-12 col-md-6 col-lg-4">
                            <label class="form-label"><?php echo htmlspecialchars($meta['label']); ?></label>
                            <?php if ($meta['type'] === 'textarea'): ?>
                                <textarea class="form-control" name="<?php echo $name; ?>"><?php echo htmlspecialchars($val); ?></textarea>
                            <?php else: ?>
                                <?php if ($meta['type'] === 'datetime-local' && $val !== '') { $val = str_replace(' ', 'T', substr($val, 0, 16)); } ?>
                                <input class="form-control" type="<?php echo $meta['type']; ?>" name="<?php echo $name; ?>" value="<?php echo htmlspecialchars($val); ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3">
                    <button class="btn btn-primary" type="submit">Update</button>
                    <a class="btn btn-secondary" href="job_seekers.php">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 