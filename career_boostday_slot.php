<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

// Permission gate (reuse Career Boost Day manage permission)
if (!(current_user_can('career_boost_day_manage') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

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

function ensure_slots_schema(mysqli $conn): void
{
    if (!table_exists($conn, 'career_boostday_slots')) {
        $conn->query("CREATE TABLE career_boostday_slots (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            day_name VARCHAR(20) NOT NULL,
            time_start TIME NOT NULL,
            time_finish TIME NOT NULL,
            label VARCHAR(120) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            INDEX idx_active_sort (is_active, sort_order),
            UNIQUE KEY uq_label (label)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // Seed defaults if empty
    $res = $conn->query("SELECT COUNT(*) AS c FROM career_boostday_slots");
    $row = $res ? $res->fetch_assoc() : null;
    $count = $row ? intval($row['c']) : 0;
    if ($count === 0) {
        $defaults = [
            ['Senin', '09:00:00', '11:00:00', 'Senin (pukul 09.00 s/d 11.00)', 10],
            ['Senin', '13:30:00', '15:00:00', 'Senin (pukul 13.30 s/d 15.00)', 20],
            ['Kamis', '09:00:00', '11:00:00', 'Kamis (pukul 09.00 s/d 11.00)', 30],
            ['Kamis', '13:30:00', '15:00:00', 'Kamis (pukul 13.30 s/d 15.00)', 40],
        ];
        $stmt = $conn->prepare("INSERT INTO career_boostday_slots (day_name, time_start, time_finish, label, sort_order, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())");
        if ($stmt) {
            foreach ($defaults as $d) {
                [$day, $t1, $t2, $label, $sort] = $d;
                $stmt->bind_param('ssssi', $day, $t1, $t2, $label, $sort);
                $stmt->execute();
            }
            $stmt->close();
        }
    }
}

function normalize_time(string $t): ?string
{
    $t = trim($t);
    if ($t === '') return null;
    if (preg_match('/^\d{2}:\d{2}$/', $t)) return $t . ':00';
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $t)) return $t;
    return null;
}

function label_for_slot(string $day, string $t1, string $t2): string
{
    $t1s = str_replace(':', '.', substr($t1, 0, 5));
    $t2s = str_replace(':', '.', substr($t2, 0, 5));
    return $day . ' (pukul ' . $t1s . ' s/d ' . $t2s . ')';
}

// Connect to DB used by submissions table
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

ensure_slots_schema($conn);

$days = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'];

// Handle CRUD
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    $action = (string)$_POST['action'];

    if ($action === 'create') {
        $day = trim((string)($_POST['day_name'] ?? ''));
        $t1 = normalize_time((string)($_POST['time_start'] ?? ''));
        $t2 = normalize_time((string)($_POST['time_finish'] ?? ''));
        $sort = intval($_POST['sort_order'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;

        if (!in_array($day, $days, true) || !$t1 || !$t2) {
            $_SESSION['error'] = 'Data tidak valid. Pastikan hari dan jam diisi dengan benar.';
        } else {
            $label = label_for_slot($day, $t1, $t2);
            $stmt = $conn->prepare("INSERT INTO career_boostday_slots (day_name, time_start, time_finish, label, sort_order, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
            if ($stmt) {
                $stmt->bind_param('ssssii', $day, $t1, $t2, $label, $sort, $active);
                $ok = $stmt->execute();
                $stmt->close();
                $_SESSION[$ok ? 'success' : 'error'] = $ok ? 'Jadwal berhasil ditambahkan.' : 'Gagal menambahkan jadwal (kemungkinan label duplikat).';
            }
        }
        header('Location: career_boostday_slot.php');
        exit;
    }

    if ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $day = trim((string)($_POST['day_name'] ?? ''));
        $t1 = normalize_time((string)($_POST['time_start'] ?? ''));
        $t2 = normalize_time((string)($_POST['time_finish'] ?? ''));
        $sort = intval($_POST['sort_order'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;

        if ($id <= 0 || !in_array($day, $days, true) || !$t1 || !$t2) {
            $_SESSION['error'] = 'Data tidak valid.';
        } else {
            $label = label_for_slot($day, $t1, $t2);
            $stmt = $conn->prepare("UPDATE career_boostday_slots
                SET day_name=?, time_start=?, time_finish=?, label=?, sort_order=?, is_active=?, updated_at=NOW()
                WHERE id=?");
            if ($stmt) {
                $stmt->bind_param('ssssiii', $day, $t1, $t2, $label, $sort, $active, $id);
                $ok = $stmt->execute();
                $stmt->close();
                $_SESSION[$ok ? 'success' : 'error'] = $ok ? 'Jadwal berhasil diupdate.' : 'Gagal mengupdate jadwal (kemungkinan label duplikat).';
            }
        }
        header('Location: career_boostday_slot.php');
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            // Soft delete: deactivate
            $stmt = $conn->prepare("UPDATE career_boostday_slots SET is_active=0, updated_at=NOW() WHERE id=?");
            if ($stmt) {
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
                $_SESSION['success'] = 'Jadwal berhasil dinonaktifkan.';
            }
        }
        header('Location: career_boostday_slot.php');
        exit;
    }
}

// Fetch rows
$slots = [];
$res = $conn->query("SELECT id, day_name, time_start, time_finish, label, sort_order, is_active, created_at, updated_at
    FROM career_boostday_slots
    ORDER BY sort_order ASC, day_name ASC, time_start ASC, id ASC");
if ($res) {
    while ($r = $res->fetch_assoc()) $slots[] = $r;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Career Boost Day Jadwal | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include __DIR__ . '/navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Jadwal Konseling - Career Boost Day</h1>
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
                <div class="col-12 col-md-3">
                    <label class="form-label">Hari</label>
                    <select class="form-select" name="day_name" required>
                        <?php foreach ($days as $d): ?>
                            <option value="<?php echo h($d); ?>"><?php echo h($d); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Jam Mulai</label>
                    <input class="form-control" type="time" name="time_start" required>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Jam Selesai</label>
                    <input class="form-control" type="time" name="time_finish" required>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Sort</label>
                    <input class="form-control" type="number" name="sort_order" value="0">
                </div>
                <div class="col-6 col-md-1">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="is_active" id="create_active" value="1" checked>
                        <label class="form-check-label" for="create_active">Aktif</label>
                    </div>
                </div>
                <div class="col-12 col-md-2">
                    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-plus-lg me-1"></i>Tambah</button>
                </div>
                <div class="form-text mt-2">
                    Label otomatis dibuat dengan format: <code>Hari (pukul HH.MM s/d HH.MM)</code>.
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
                        <th>Label</th>
                        <th style="width:140px;">Hari</th>
                        <th style="width:180px;">Jam</th>
                        <th style="width:100px;">Sort</th>
                        <th style="width:120px;">Aktif</th>
                        <th style="width:220px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($slots)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Belum ada jadwal.</td></tr>
                <?php else: ?>
                    <?php foreach ($slots as $s): ?>
                        <tr>
                            <td><?php echo h($s['id']); ?></td>
                            <td class="fw-semibold"><?php echo h($s['label']); ?></td>
                            <td><?php echo h($s['day_name']); ?></td>
                            <td><?php echo h(substr((string)$s['time_start'],0,5) . ' - ' . substr((string)$s['time_finish'],0,5)); ?></td>
                            <td><?php echo h($s['sort_order']); ?></td>
                            <td>
                                <?php if (intval($s['is_active']) === 1): ?>
                                    <span class="badge text-bg-success">active</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn btn-outline-secondary btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editSlotModal"
                                        data-id="<?php echo h($s['id']); ?>"
                                        data-day="<?php echo h($s['day_name']); ?>"
                                        data-start="<?php echo h(substr((string)$s['time_start'],0,5)); ?>"
                                        data-finish="<?php echo h(substr((string)$s['time_finish'],0,5)); ?>"
                                        data-sort="<?php echo h($s['sort_order']); ?>"
                                        data-active="<?php echo h($s['is_active']); ?>"
                                    ><i class="bi bi-pencil-square me-1"></i>Edit</button>

                                    <form method="POST" action="" onsubmit="return confirm('Nonaktifkan jadwal ini?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo h($s['id']); ?>">
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

<!-- Edit Slot Modal -->
<div class="modal fade" id="editSlotModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="">
        <div class="modal-header">
          <h5 class="modal-title">Edit Jadwal</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" id="slot_edit_id" value="">

          <label class="form-label">Hari</label>
          <select class="form-select" name="day_name" id="slot_edit_day" required>
            <?php foreach ($days as $d): ?>
              <option value="<?php echo h($d); ?>"><?php echo h($d); ?></option>
            <?php endforeach; ?>
          </select>

          <div class="row g-2 mt-1">
            <div class="col-6">
              <label class="form-label">Jam Mulai</label>
              <input class="form-control" type="time" name="time_start" id="slot_edit_start" required>
            </div>
            <div class="col-6">
              <label class="form-label">Jam Selesai</label>
              <input class="form-control" type="time" name="time_finish" id="slot_edit_finish" required>
            </div>
          </div>

          <div class="row g-2 mt-1">
            <div class="col-6">
              <label class="form-label">Sort</label>
              <input class="form-control" type="number" name="sort_order" id="slot_edit_sort" value="0">
            </div>
            <div class="col-6 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="is_active" id="slot_edit_active" value="1">
                <label class="form-check-label" for="slot_edit_active">Aktif</label>
              </div>
            </div>
          </div>

          <div class="form-text mt-2">Label dibuat otomatis saat disimpan.</div>
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
    var m = document.getElementById('editSlotModal');
    if (!m) return;
    m.addEventListener('show.bs.modal', function (event) {
      var btn = event.relatedTarget;
      if (!btn) return;
      document.getElementById('slot_edit_id').value = btn.getAttribute('data-id') || '';
      document.getElementById('slot_edit_day').value = btn.getAttribute('data-day') || 'Senin';
      document.getElementById('slot_edit_start').value = btn.getAttribute('data-start') || '';
      document.getElementById('slot_edit_finish').value = btn.getAttribute('data-finish') || '';
      document.getElementById('slot_edit_sort').value = btn.getAttribute('data-sort') || '0';
      var active = btn.getAttribute('data-active') || '0';
      document.getElementById('slot_edit_active').checked = (active === '1');
    });
  })();
</script>
</body>
</html>


