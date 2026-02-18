<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/auth_guard.php';

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function try_connect_db(string $dbName): ?mysqli {
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $conn = @new mysqli($host, $user, $pass, $dbName);
    if ($conn->connect_error) return null;
    $conn->set_charset('utf8mb4');
    return $conn;
}

function table_exists(mysqli $conn, string $table): bool {
    $t = $conn->real_escape_string($table);
    $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

// Try both DBs used in this repo's admin pages.
$candidates = ['job_admin_prod', 'paskerid_db_prod'];
$conn = null;
$activeDb = null;
foreach ($candidates as $dbName) {
    $tmp = try_connect_db($dbName);
    if ($tmp && table_exists($tmp, 'career_boostday_consultations')) {
        $conn = $tmp;
        $activeDb = $dbName;
        break;
    }
    if ($tmp) $tmp->close();
}

if (!$conn) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Cannot find table career_boostday_consultations in candidate databases: " . implode(', ', $candidates) . "\n";
    echo "Hint: run migrations on the same DB used by the public site.\n";
    exit;
}

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$where = '';
$params = [];
$types = '';
if ($q !== '') {
    $where = "WHERE name LIKE ? OR whatsapp LIKE ? OR status LIKE ? OR jadwal_konseling LIKE ?";
    $like = '%' . $q . '%';
    $params = [$like, $like, $like, $like];
    $types = 'ssss';
}

// Count total
$total = 0;
if ($where) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM career_boostday_consultations $where");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();
} else {
    $res = $conn->query("SELECT COUNT(*) AS c FROM career_boostday_consultations");
    $row = $res ? $res->fetch_assoc() : null;
    $total = $row ? intval($row['c']) : 0;
}

// Fetch rows
$rows = [];
$sql = "SELECT id, created_at, name, whatsapp, status, jenis_konseling, jadwal_konseling, pendidikan_terakhir, cv_path, cv_original_name
        FROM career_boostday_consultations
        $where
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?";

if ($stmt = $conn->prepare($sql)) {
    if ($where) {
        $types2 = $types . 'ii';
        $params2 = array_merge($params, [$perPage, $offset]);
        $stmt->bind_param($types2, ...$params2);
    } else {
        $stmt->bind_param('ii', $perPage, $offset);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($r = $res->fetch_assoc()) $rows[] = $r;
    }
    $stmt->close();
}

$totalPages = max(1, (int)ceil($total / $perPage));
$baseQuery = $q !== '' ? ('&q=' . urlencode($q)) : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Career BoostDay | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include __DIR__ . '/navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Career BoostDay</h1>
            <div class="text-muted small">Database: <code><?php echo h($activeDb); ?></code> • Total: <b><?php echo number_format($total); ?></b></div>
        </div>
        <form class="d-flex gap-2" method="GET" action="">
            <input class="form-control" name="q" value="<?php echo h($q); ?>" placeholder="Cari nama / WA / status / jadwal" style="min-width: 280px;">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Cari</button>
        </form>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead class="table-primary">
                    <tr>
                        <th style="width: 170px;">Timestamp</th>
                        <th>Nama</th>
                        <th style="width: 170px;">Nomor WhatsApp</th>
                        <th style="width: 220px;">Apakah Saudara/i</th>
                        <th style="width: 150px;">Jenis Konseling</th>
                        <th style="width: 220px;">Jadwal Konseling</th>
                        <th style="width: 170px;">Pendidikan Terakhir</th>
                        <th style="width: 140px;">Upload CV</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada data.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <?php
                            $cvLink = '';
                            if (!empty($r['cv_path'])) {
                                $cvLink = '/storage/' . ltrim($r['cv_path'], '/');
                            }
                        ?>
                        <tr>
                            <td><?php echo h($r['created_at']); ?></td>
                            <td class="fw-semibold"><?php echo h($r['name']); ?></td>
                            <td><?php echo h($r['whatsapp']); ?></td>
                            <td><?php echo h($r['status']); ?></td>
                            <td><?php echo h($r['jenis_konseling']); ?></td>
                            <td><?php echo h($r['jadwal_konseling']); ?></td>
                            <td><?php echo h($r['pendidikan_terakhir']); ?></td>
                            <td>
                                <?php if ($cvLink): ?>
                                    <a class="btn btn-outline-primary btn-sm" target="_blank" href="<?php echo h($cvLink); ?>">
                                        <i class="bi bi-file-earmark-arrow-down me-1"></i>CV
                                    </a>
                                    <div class="small text-muted mt-1"><?php echo h($r['cv_original_name']); ?></div>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div class="text-muted small">Page <?php echo $page; ?> of <?php echo $totalPages; ?></div>
            <nav>
                <ul class="pagination mb-0">
                    <?php
                        $prev = max(1, $page - 1);
                        $next = min($totalPages, $page + 1);
                    ?>
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $prev . $baseQuery; ?>">Prev</a>
                    </li>
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $next . $baseQuery; ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


