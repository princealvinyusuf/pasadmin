<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/auth_guard.php';

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

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

// Prefer DB that already has counseling_results, else fall back to the one used by Career Boost Day.
$candidates = ['job_admin_prod', 'paskerid_db_prod'];
$conn = null;
$activeDb = null;
foreach ($candidates as $dbName) {
    $tmp = try_connect_db($dbName);
    if ($tmp && table_exists($tmp, 'counseling_results')) {
        $conn = $tmp;
        $activeDb = $dbName;
        break;
    }
    if ($tmp) $tmp->close();
}
if (!$conn) {
    foreach ($candidates as $dbName) {
        $tmp = try_connect_db($dbName);
        if ($tmp && table_exists($tmp, 'career_boostday_consultations')) {
            $conn = $tmp;
            $activeDb = $dbName;
            break;
        }
        if ($tmp) $tmp->close();
    }
}

if (!$conn) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Cannot connect to candidate databases: " . implode(', ', $candidates) . "\n";
    exit;
}

if (!table_exists($conn, 'counseling_results')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Table counseling_results not found in database: $activeDb\n";
    echo "Hint: run Laravel migrations (php artisan migrate).\n";
    exit;
}

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Actions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    $action = (string)$_POST['action'];
    $id = intval($_POST['id'] ?? 0);
    $redir = 'form_hasil_konseling.php';
    if (!empty($_GET['q']) || !empty($_GET['page'])) {
        $qs = [];
        if (!empty($_GET['q'])) $qs[] = 'q=' . urlencode((string)$_GET['q']);
        if (!empty($_GET['page'])) $qs[] = 'page=' . urlencode((string)$_GET['page']);
        $redir .= '?' . implode('&', $qs);
    }

    if ($id <= 0) {
        $_SESSION['error'] = 'ID tidak valid.';
        header('Location: ' . $redir);
        exit;
    }

    if ($action === 'delete') {
        // delete evidences first (FK may not exist in admin DB)
        if (table_exists($conn, 'counseling_result_evidences')) {
            $stmt = $conn->prepare("SELECT file_path FROM counseling_result_evidences WHERE counseling_result_id=?");
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) {
                    while ($r = $res->fetch_assoc()) {
                        $fp = (string)($r['file_path'] ?? '');
                        if ($fp) {
                            $full = $_SERVER['DOCUMENT_ROOT'] . '/storage/' . ltrim($fp, '/');
                            if (is_file($full)) @unlink($full);
                        }
                    }
                }
                $stmt->close();
            }

            $stmt = $conn->prepare("DELETE FROM counseling_result_evidences WHERE counseling_result_id=?");
            if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); }
        }

        $stmt = $conn->prepare("DELETE FROM counseling_results WHERE id=?");
        if ($stmt) { $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close(); }

        $_SESSION['success'] = 'Data berhasil dihapus.';
        header('Location: ' . $redir);
        exit;
    }

    if ($action === 'edit') {
        $namaKonselor = trim((string)($_POST['nama_konselor'] ?? ''));
        $namaKonseli = trim((string)($_POST['nama_konseli'] ?? ''));
        $tanggal = trim((string)($_POST['tanggal_konseling'] ?? ''));
        $jenis = trim((string)($_POST['jenis_konseling'] ?? ''));
        $dibahas = trim((string)($_POST['hal_yang_dibahas'] ?? ''));
        $saran = trim((string)($_POST['saran_untuk_pencaker'] ?? ''));

        if ($namaKonselor === '' || $namaKonseli === '' || $tanggal === '' || $jenis === '' || $dibahas === '' || $saran === '') {
            $_SESSION['error'] = 'Semua field wajib diisi.';
            header('Location: ' . $redir);
            exit;
        }

        $stmt = $conn->prepare("UPDATE counseling_results
            SET nama_konselor=?, nama_konseli=?, tanggal_konseling=?, jenis_konseling=?, hal_yang_dibahas=?, saran_untuk_pencaker=?, updated_at=NOW()
            WHERE id=?");
        if ($stmt) {
            $stmt->bind_param('ssssssi', $namaKonselor, $namaKonseli, $tanggal, $jenis, $dibahas, $saran, $id);
            $stmt->execute();
            $stmt->close();
        }
        $_SESSION['success'] = 'Data berhasil diupdate.';
        header('Location: ' . $redir);
        exit;
    }
}

$where = '';
$params = [];
$types = '';
if ($q !== '') {
    $where = "WHERE nama_konselor LIKE ? OR nama_konseli LIKE ? OR jenis_konseling LIKE ?";
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
    $types = 'sss';
}

// Count
$total = 0;
if ($where) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM counseling_results $where");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();
} else {
    $res = $conn->query("SELECT COUNT(*) AS c FROM counseling_results");
    $row = $res ? $res->fetch_assoc() : null;
    $total = $row ? intval($row['c']) : 0;
}

// Fetch rows
$rows = [];
$sql = "SELECT id, created_at, nama_konselor, nama_konseli, tanggal_konseling, jenis_konseling, hal_yang_dibahas, saran_untuk_pencaker
        FROM counseling_results
        $where
        ORDER BY tanggal_konseling DESC, id DESC
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

// Fetch evidences per result (bulk)
$evidences = [];
if (table_exists($conn, 'counseling_result_evidences')) {
    $ids = array_map(fn($r) => intval($r['id']), $rows);
    if (!empty($ids)) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $typesIn = str_repeat('i', count($ids));
        $stmt = $conn->prepare("SELECT counseling_result_id, file_path, original_name FROM counseling_result_evidences WHERE counseling_result_id IN ($in) ORDER BY id ASC");
        if ($stmt) {
            $stmt->bind_param($typesIn, ...$ids);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $rid = intval($r['counseling_result_id']);
                    if (!isset($evidences[$rid])) $evidences[$rid] = [];
                    $evidences[$rid][] = $r;
                }
            }
            $stmt->close();
        }
    }
}

$totalPages = max(1, (int)ceil($total / $perPage));
$baseQuery = $q !== '' ? ('&q=' . urlencode($q)) : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Form Hasil Konseling | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include __DIR__ . '/navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Form Hasil Konseling</h1>
            <div class="text-muted small">Database: <code><?php echo h($activeDb); ?></code> • Total: <b><?php echo number_format($total); ?></b></div>
        </div>
        <form class="d-flex gap-2" method="GET" action="">
            <input class="form-control" name="q" value="<?php echo h($q); ?>" placeholder="Cari konselor / konseli / jenis" style="min-width: 280px;">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Cari</button>
        </form>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo h($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo h($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead class="table-primary">
                    <tr>
                        <th style="width: 170px;">Created</th>
                        <th style="width: 140px;">Tanggal</th>
                        <th>Nama Konselor</th>
                        <th>Nama Konseli</th>
                        <th style="width: 220px;">Jenis</th>
                        <th style="width: 220px;">Bukti</th>
                        <th style="width: 220px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <?php $rid = intval($r['id']); $files = $evidences[$rid] ?? []; ?>
                        <tr>
                            <td><?php echo h($r['created_at']); ?></td>
                            <td class="fw-semibold"><?php echo h($r['tanggal_konseling']); ?></td>
                            <td class="fw-semibold"><?php echo h($r['nama_konselor']); ?></td>
                            <td><?php echo h($r['nama_konseli']); ?></td>
                            <td><?php echo h($r['jenis_konseling']); ?></td>
                            <td>
                                <?php if (empty($files)): ?>
                                    <span class="text-muted">-</span>
                                <?php else: ?>
                                    <?php foreach ($files as $f): ?>
                                        <?php $link = '/storage/' . ltrim((string)$f['file_path'], '/'); ?>
                                        <div><a target="_blank" href="<?php echo h($link); ?>"><?php echo h($f['original_name'] ?: 'file'); ?></a></div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <button class="btn btn-outline-secondary btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editModal"
                                        data-id="<?php echo h($rid); ?>"
                                        data-nk="<?php echo h($r['nama_konselor']); ?>"
                                        data-np="<?php echo h($r['nama_konseli']); ?>"
                                        data-tgl="<?php echo h($r['tanggal_konseling']); ?>"
                                        data-jenis="<?php echo h($r['jenis_konseling']); ?>"
                                        data-dibahas="<?php echo h($r['hal_yang_dibahas']); ?>"
                                        data-saran="<?php echo h($r['saran_untuk_pencaker']); ?>"
                                    ><i class="bi bi-pencil-square me-1"></i>Edit</button>

                                    <form method="POST" action="" onsubmit="return confirm('Hapus data ini?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo h($rid); ?>">
                                        <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash me-1"></i>Hapus</button>
                                    </form>
                                </div>
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

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST" action="">
        <div class="modal-header">
          <h5 class="modal-title">Edit Hasil Konseling</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="id" id="edit_id" value="">

          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label">Nama Konselor</label>
              <input class="form-control" name="nama_konselor" id="edit_nk" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Nama Konseli/Pencaker</label>
              <input class="form-control" name="nama_konseli" id="edit_np" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Tanggal Konseling</label>
              <input class="form-control" type="date" name="tanggal_konseling" id="edit_tgl" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Jenis Konseling</label>
              <input class="form-control" name="jenis_konseling" id="edit_jenis" required>
            </div>
            <div class="col-12">
              <label class="form-label">Hal yang Dibahas</label>
              <textarea class="form-control" name="hal_yang_dibahas" id="edit_dibahas" rows="4" required></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Saran untuk Pencaker</label>
              <textarea class="form-control" name="saran_untuk_pencaker" id="edit_saran" rows="4" required></textarea>
            </div>
          </div>
          <div class="form-text mt-2">Catatan: bukti file tidak diedit di sini. (Jika perlu, bisa kita tambahkan.)</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  (function () {
    var editModal = document.getElementById('editModal');
    if (!editModal) return;
    editModal.addEventListener('show.bs.modal', function (event) {
      var btn = event.relatedTarget;
      if (!btn) return;
      document.getElementById('edit_id').value = btn.getAttribute('data-id') || '';
      document.getElementById('edit_nk').value = btn.getAttribute('data-nk') || '';
      document.getElementById('edit_np').value = btn.getAttribute('data-np') || '';
      document.getElementById('edit_tgl').value = btn.getAttribute('data-tgl') || '';
      document.getElementById('edit_jenis').value = btn.getAttribute('data-jenis') || '';
      document.getElementById('edit_dibahas').value = btn.getAttribute('data-dibahas') || '';
      document.getElementById('edit_saran').value = btn.getAttribute('data-saran') || '';
    });
  })();
</script>
</body>
</html>


