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

// Try both DBs used in this repo's admin pages (same logic as submissions page)
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
    exit;
}

// Ensure PIC table exists + seed defaults (keep in sync with career_boostday.php)
if (!table_exists($conn, 'career_boostday_pics')) {
    $conn->query("CREATE TABLE career_boostday_pics (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NULL,
        updated_at DATETIME NULL,
        INDEX idx_active (is_active),
        UNIQUE KEY uq_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
$res = $conn->query("SELECT COUNT(*) AS c FROM career_boostday_pics");
$row = $res ? $res->fetch_assoc() : null;
$count = $row ? intval($row['c']) : 0;
if ($count === 0) {
    $defaults = ['Rici', 'Arifa', 'Ryan', 'Widya', 'Nikira', 'Jules'];
    $stmt = $conn->prepare("INSERT INTO career_boostday_pics (name, is_active, created_at, updated_at) VALUES (?, 1, NOW(), NOW())");
    if ($stmt) {
        foreach ($defaults as $nm) {
            $stmt->bind_param('s', $nm);
            $stmt->execute();
        }
        $stmt->close();
    }
}

// Handle CRUD
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    $action = (string)$_POST['action'];
    if ($action === 'create') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            $_SESSION['error'] = 'Nama PIC wajib diisi.';
        } else {
            $stmt = $conn->prepare("INSERT INTO career_boostday_pics (name, is_active, created_at, updated_at) VALUES (?, 1, NOW(), NOW())");
            if ($stmt) {
                $stmt->bind_param('s', $name);
                $stmt->execute();
                $stmt->close();
                $_SESSION['success'] = 'PIC berhasil ditambahkan.';
            }
        }
        header('Location: career_boostday_pic.php');
        exit;
    }

    if ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $active = isset($_POST['is_active']) ? 1 : 0;
        if ($id <= 0 || $name === '') {
            $_SESSION['error'] = 'Data tidak valid.';
        } else {
            $stmt = $conn->prepare("UPDATE career_boostday_pics SET name=?, is_active=?, updated_at=NOW() WHERE id=?");
            if ($stmt) {
                $stmt->bind_param('sii', $name, $active, $id);
                $stmt->execute();
                $stmt->close();
                $_SESSION['success'] = 'PIC berhasil diupdate.';
            }
        }
        header('Location: career_boostday_pic.php');
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            // Soft delete: deactivate
            $stmt = $conn->prepare("UPDATE career_boostday_pics SET is_active=0, updated_at=NOW() WHERE id=?");
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
                $_SESSION['success'] = 'PIC berhasil dinonaktifkan.';
            }
        }
        header('Location: career_boostday_pic.php');
        exit;
    }
}

$pics = [];
$res = $conn->query("SELECT id, name, is_active, created_at, updated_at FROM career_boostday_pics ORDER BY is_active DESC, name ASC");
if ($res) {
    while ($r = $res->fetch_assoc()) $pics[] = $r;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Career BoostDay PIC | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include __DIR__ . '/navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">PIC - Career BoostDay</h1>
            <div class="text-muted small">Database: <code><?php echo h($activeDb); ?></code></div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="career_boostday.php"><i class="bi bi-arrow-left me-1"></i>Back to Submissions</a>
        </div>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo h($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo h($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form class="row g-2 align-items-end" method="POST" action="">
                <input type="hidden" name="action" value="create">
                <div class="col-12 col-md-6">
                    <label class="form-label">Tambah PIC</label>
                    <input class="form-control" name="name" placeholder="Nama PIC" required>
                </div>
                <div class="col-12 col-md-3">
                    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-plus-lg me-1"></i>Tambah</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead class="table-primary">
                    <tr>
                        <th style="width:80px;">ID</th>
                        <th>Nama</th>
                        <th style="width:120px;">Aktif</th>
                        <th style="width:220px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($pics)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">Belum ada PIC.</td></tr>
                <?php else: ?>
                    <?php foreach ($pics as $p): ?>
                        <tr>
                            <td><?php echo h($p['id']); ?></td>
                            <td class="fw-semibold"><?php echo h($p['name']); ?></td>
                            <td>
                                <?php if (intval($p['is_active']) === 1): ?>
                                    <span class="badge text-bg-success">active</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn btn-outline-secondary btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editPicModal"
                                        data-id="<?php echo h($p['id']); ?>"
                                        data-name="<?php echo h($p['name']); ?>"
                                        data-active="<?php echo h($p['is_active']); ?>"
                                    ><i class="bi bi-pencil-square me-1"></i>Edit</button>

                                    <form method="POST" action="" onsubmit="return confirm('Nonaktifkan PIC ini?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo h($p['id']); ?>">
                                        <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-slash-circle me-1"></i>Nonaktif</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit PIC Modal -->
<div class="modal fade" id="editPicModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="">
        <div class="modal-header">
          <h5 class="modal-title">Edit PIC</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" id="pic_edit_id" value="">

          <label class="form-label">Nama</label>
          <input class="form-control" name="name" id="pic_edit_name" required>

          <div class="form-check mt-3">
            <input class="form-check-input" type="checkbox" name="is_active" id="pic_edit_active" value="1">
            <label class="form-check-label" for="pic_edit_active">Aktif</label>
          </div>
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
    var m = document.getElementById('editPicModal');
    if (!m) return;
    m.addEventListener('show.bs.modal', function (event) {
      var btn = event.relatedTarget;
      if (!btn) return;
      document.getElementById('pic_edit_id').value = btn.getAttribute('data-id') || '';
      document.getElementById('pic_edit_name').value = btn.getAttribute('data-name') || '';
      var active = btn.getAttribute('data-active') || '0';
      document.getElementById('pic_edit_active').checked = (active === '1');
    });
  })();
</script>
</body>
</html>


