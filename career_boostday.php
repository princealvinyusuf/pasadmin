<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

// Permission gate
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

function column_exists(mysqli $conn, string $table, string $column): bool {
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($column);
    $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function ensure_schema(mysqli $conn): void
{
    // Create PIC table
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

    // Seed default PICs if empty
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

    // Add workflow columns to consultations table (if missing)
    if (table_exists($conn, 'career_boostday_consultations')) {
        if (!column_exists($conn, 'career_boostday_consultations', 'admin_status')) {
            $conn->query("ALTER TABLE career_boostday_consultations ADD COLUMN admin_status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER cv_original_name");
        }
        if (!column_exists($conn, 'career_boostday_consultations', 'pic_id')) {
            $conn->query("ALTER TABLE career_boostday_consultations ADD COLUMN pic_id INT UNSIGNED NULL AFTER admin_status");
        }
        if (!column_exists($conn, 'career_boostday_consultations', 'keterangan')) {
            $conn->query("ALTER TABLE career_boostday_consultations ADD COLUMN keterangan TEXT NULL AFTER pic_id");
        }
        if (!column_exists($conn, 'career_boostday_consultations', 'alasan')) {
            $conn->query("ALTER TABLE career_boostday_consultations ADD COLUMN alasan TEXT NULL AFTER keterangan");
        }
        if (!column_exists($conn, 'career_boostday_consultations', 'admin_updated_at')) {
            $conn->query("ALTER TABLE career_boostday_consultations ADD COLUMN admin_updated_at DATETIME NULL AFTER alasan");
        }
        if (!column_exists($conn, 'career_boostday_consultations', 'booked_date')) {
            $conn->query("ALTER TABLE career_boostday_consultations ADD COLUMN booked_date DATE NULL AFTER admin_updated_at");
        }
        if (!column_exists($conn, 'career_boostday_consultations', 'booked_time_start')) {
            $conn->query("ALTER TABLE career_boostday_consultations ADD COLUMN booked_time_start TIME NULL AFTER booked_date");
        }
        if (!column_exists($conn, 'career_boostday_consultations', 'booked_time_finish')) {
            $conn->query("ALTER TABLE career_boostday_consultations ADD COLUMN booked_time_finish TIME NULL AFTER booked_time_start");
        }
    }
}

function parse_time_range_from_slot(string $slot): array
{
    // Example: "Senin (pukul 13.30 s/d 15.00)"
    $start = null;
    $finish = null;
    if (preg_match('/pukul\s+(\d{2})[.:](\d{2})\s*s\/d\s*(\d{2})[.:](\d{2})/i', $slot, $m)) {
        $start = sprintf('%02d:%02d:00', intval($m[1]), intval($m[2]));
        $finish = sprintf('%02d:%02d:00', intval($m[3]), intval($m[4]));
    }
    return [$start, $finish];
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

$conn->set_charset('utf8mb4');
ensure_schema($conn);

// Handle actions (accept/reject/edit)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    $action = (string)$_POST['action'];
    $id = intval($_POST['id'] ?? 0);
    $qs = '';
    if (!empty($_GET['q'])) $qs .= 'q=' . urlencode((string)$_GET['q']);
    if (!empty($_GET['page'])) $qs .= ($qs ? '&' : '') . 'page=' . urlencode((string)$_GET['page']);
    $redir = 'career_boostday.php' . ($qs ? ('?' . $qs) : '');

    if ($id <= 0) {
        $_SESSION['error'] = 'ID tidak valid.';
        header('Location: ' . $redir);
        exit;
    }

    if ($action === 'accept') {
        $picId = intval($_POST['pic_id'] ?? 0);
        $keterangan = trim((string)($_POST['keterangan'] ?? ''));
        $bookedDate = trim((string)($_POST['booked_date'] ?? ''));
        if ($picId <= 0) {
            $_SESSION['error'] = 'PIC wajib dipilih.';
            header('Location: ' . $redir);
            exit;
        }
        if ($bookedDate === '') {
            $_SESSION['error'] = 'Tanggal Booked wajib diisi.';
            header('Location: ' . $redir);
            exit;
        }

        // derive time range from the slot (jadwal_konseling)
        $slot = '';
        if ($s = $conn->prepare("SELECT jadwal_konseling FROM career_boostday_consultations WHERE id=?")) {
            $s->bind_param('i', $id);
            $s->execute();
            $s->bind_result($slotRes);
            if ($s->fetch()) $slot = (string)$slotRes;
            $s->close();
        }
        [$timeStart, $timeFinish] = parse_time_range_from_slot($slot);

        $stmt = $conn->prepare("UPDATE career_boostday_consultations
            SET admin_status='accepted', pic_id=?, keterangan=?, alasan=NULL, booked_date=?, booked_time_start=?, booked_time_finish=?, admin_updated_at=NOW()
            WHERE id=?");
        if ($stmt) {
            // 6 params: i (pic_id), s (keterangan), s (booked_date), s (time_start), s (time_finish), i (id)
            $stmt->bind_param('issssi', $picId, $keterangan, $bookedDate, $timeStart, $timeFinish, $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Data berhasil di-accept.';
        }
        header('Location: ' . $redir);
        exit;
    }

    if ($action === 'reject') {
        $alasan = trim((string)($_POST['alasan'] ?? ''));
        if ($alasan === '') {
            $_SESSION['error'] = 'Alasan wajib diisi.';
            header('Location: ' . $redir);
            exit;
        }
        $stmt = $conn->prepare("UPDATE career_boostday_consultations
            SET admin_status='rejected', alasan=?, pic_id=NULL, keterangan=NULL, admin_updated_at=NOW()
            WHERE id=?");
        if ($stmt) {
            $stmt->bind_param('si', $alasan, $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Data berhasil di-reject.';
        }
        header('Location: ' . $redir);
        exit;
    }

    if ($action === 'delete') {
        // Remove CV file if present
        $cvPath = null;
        if ($stmt = $conn->prepare("SELECT cv_path FROM career_boostday_consultations WHERE id=?")) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->bind_result($cvPathRes);
            if ($stmt->fetch()) {
                $cvPath = $cvPathRes;
            }
            $stmt->close();
        }

        if (!empty($cvPath)) {
            $full = $_SERVER['DOCUMENT_ROOT'] . '/storage/' . ltrim((string)$cvPath, '/');
            if (is_file($full)) {
                @unlink($full);
            }
        }

        $stmt = $conn->prepare("DELETE FROM career_boostday_consultations WHERE id=?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Data berhasil dihapus.';
        }
        header('Location: ' . $redir);
        exit;
    }

    if ($action === 'edit') {
        $name = trim((string)($_POST['name'] ?? ''));
        $whatsapp = trim((string)($_POST['whatsapp'] ?? ''));
        $status = trim((string)($_POST['status'] ?? ''));
        $jenis = trim((string)($_POST['jenis_konseling'] ?? ''));
        $jadwal = trim((string)($_POST['jadwal_konseling'] ?? ''));
        $pend = trim((string)($_POST['pendidikan_terakhir'] ?? ''));
        $bookedDate = trim((string)($_POST['booked_date'] ?? '')); // optional (YYYY-MM-DD)

        if ($name === '' || $whatsapp === '' || $status === '' || $jenis === '' || $jadwal === '') {
            $_SESSION['error'] = 'Nama, WhatsApp, Status, Jenis Konseling, dan Jadwal wajib diisi.';
            header('Location: ' . $redir);
            exit;
        }

        $stmt = $conn->prepare("UPDATE career_boostday_consultations
            SET name=?, whatsapp=?, status=?, jenis_konseling=?, jadwal_konseling=?, pendidikan_terakhir=?, booked_date=NULLIF(?, ''), admin_updated_at=NOW()
            WHERE id=?");
        if ($stmt) {
            $stmt->bind_param('sssssssi', $name, $whatsapp, $status, $jenis, $jadwal, $pend, $bookedDate, $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Data berhasil di-update.';
        }
        header('Location: ' . $redir);
        exit;
    }

    $_SESSION['error'] = 'Aksi tidak dikenali.';
    header('Location: ' . $redir);
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
    $where = "WHERE c.name LIKE ? OR c.whatsapp LIKE ? OR c.status LIKE ? OR c.jadwal_konseling LIKE ? OR c.admin_status LIKE ? OR p.name LIKE ?";
    $like = '%' . $q . '%';
    $params = [$like, $like, $like, $like, $like, $like];
    $types = 'ssssss';
}

// Count total
$total = 0;
if ($where) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM career_boostday_consultations c LEFT JOIN career_boostday_pics p ON p.id=c.pic_id $where");
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

// Fetch PICs (for accept modal)
$pics = [];
$resPics = $conn->query("SELECT id, name FROM career_boostday_pics WHERE is_active=1 ORDER BY name ASC");
if ($resPics) {
    while ($r = $resPics->fetch_assoc()) $pics[] = $r;
}

// Fetch rows
$rows = [];
$sql = "SELECT c.id, c.created_at, c.name, c.whatsapp, c.status, c.jenis_konseling, c.jadwal_konseling, c.pendidikan_terakhir, c.cv_path, c.cv_original_name,
               c.admin_status, c.pic_id, c.keterangan, c.alasan, c.admin_updated_at, c.booked_date, c.booked_time_start, c.booked_time_finish,
               p.name AS pic_name
        FROM career_boostday_consultations c
        LEFT JOIN career_boostday_pics p ON p.id=c.pic_id
        $where
        ORDER BY c.created_at DESC
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
    <title>Career Boost Day | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include __DIR__ . '/navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Career Boost Day</h1>
            <div class="text-muted small">Database: <code><?php echo h($activeDb); ?></code> • Total: <b><?php echo number_format($total); ?></b></div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <a class="btn btn-outline-secondary" href="career_boostday_pic.php"><i class="bi bi-people me-1"></i>PIC</a>
            <form class="d-flex gap-2" method="GET" action="">
            <input class="form-control" name="q" value="<?php echo h($q); ?>" placeholder="Cari nama / WA / status / jadwal" style="min-width: 280px;">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Cari</button>
            </form>
        </div>
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
                        <th style="width: 170px;">Timestamp</th>
                        <th>Nama</th>
                        <th style="width: 170px;">Nomor WhatsApp</th>
                        <th style="width: 220px;">Apakah Saudara/i</th>
                        <th style="width: 150px;">Jenis Konseling</th>
                        <th style="width: 220px;">Jadwal Konseling</th>
                        <th style="width: 170px;">Pendidikan Terakhir</th>
                        <th style="width: 120px;">Status</th>
                        <th style="width: 140px;">PIC</th>
                        <th style="width: 140px;">Upload CV</th>
                        <th style="width: 200px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="11" class="text-center text-muted py-4">Belum ada data.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <?php
                            $cvLink = '';
                            if (!empty($r['cv_path'])) {
                                $cvLink = '/storage/' . ltrim($r['cv_path'], '/');
                            }
                            $st = $r['admin_status'] ?? 'pending';
                            $badge = 'secondary';
                            if ($st === 'accepted') $badge = 'success';
                            elseif ($st === 'rejected') $badge = 'danger';
                            elseif ($st === 'pending') $badge = 'warning';
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
                                <span class="badge text-bg-<?php echo h($badge); ?>"><?php echo h($st); ?></span>
                                <?php if ($st === 'rejected' && !empty($r['alasan'])): ?>
                                    <div class="small text-muted mt-1"><?php echo h(mb_strimwidth((string)$r['alasan'], 0, 60, '...')); ?></div>
                                <?php endif; ?>
                                <?php if ($st === 'accepted' && !empty($r['keterangan'])): ?>
                                    <div class="small text-muted mt-1"><?php echo h(mb_strimwidth((string)$r['keterangan'], 0, 60, '...')); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo h($r['pic_name'] ?? '-'); ?></td>
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
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <button
                                        class="btn btn-outline-secondary btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editModal"
                                        data-id="<?php echo h($r['id']); ?>"
                                        data-name="<?php echo h($r['name']); ?>"
                                        data-whatsapp="<?php echo h($r['whatsapp']); ?>"
                                        data-status="<?php echo h($r['status']); ?>"
                                        data-jenis="<?php echo h($r['jenis_konseling']); ?>"
                                        data-jadwal="<?php echo h($r['jadwal_konseling']); ?>"
                                        data-pendidikan="<?php echo h($r['pendidikan_terakhir']); ?>"
                                        data-booked-date="<?php echo h($r['booked_date']); ?>"
                                    ><i class="bi bi-pencil-square me-1"></i>Edit</button>

                                    <?php if (($r['admin_status'] ?? 'pending') === 'pending'): ?>
                                        <button
                                            class="btn btn-outline-success btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#acceptModal"
                                            data-id="<?php echo h($r['id']); ?>"
                                        ><i class="bi bi-check2-circle me-1"></i>Accept</button>

                                        <button
                                            class="btn btn-outline-danger btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#rejectModal"
                                            data-id="<?php echo h($r['id']); ?>"
                                        ><i class="bi bi-x-circle me-1"></i>Reject</button>
                                    <?php endif; ?>

                                    <form method="POST" action="" onsubmit="return confirm('Hapus data ini?');" class="d-inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo h($r['id']); ?>">
                                        <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash me-1"></i>Delete</button>
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
          <h5 class="modal-title">Edit Data</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="id" id="edit_id" value="">

          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label">Nama</label>
              <input class="form-control" name="name" id="edit_name" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Nomor WhatsApp</label>
              <input class="form-control" name="whatsapp" id="edit_whatsapp" required>
            </div>
            <div class="col-12">
              <label class="form-label">Apakah Saudara/i</label>
              <input class="form-control" name="status" id="edit_status" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Jenis Konseling</label>
              <input class="form-control" name="jenis_konseling" id="edit_jenis" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Jadwal Konseling</label>
              <input class="form-control" name="jadwal_konseling" id="edit_jadwal" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Tanggal Booked</label>
              <input class="form-control" type="date" name="booked_date" id="edit_booked_date">
              <div class="form-text">Opsional. Digunakan untuk tampilan kalender Booked.</div>
            </div>
            <div class="col-12">
              <label class="form-label">Pendidikan Terakhir</label>
              <input class="form-control" name="pendidikan_terakhir" id="edit_pendidikan">
            </div>
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

<!-- Accept Modal -->
<div class="modal fade" id="acceptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST" action="">
        <div class="modal-header">
          <h5 class="modal-title">Accept Konsultasi</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="accept">
          <input type="hidden" name="id" id="accept_id" value="">

          <div class="row g-3">
            <div class="col-12 col-md-5">
              <label class="form-label">Tanggal Booked</label>
              <input type="date" class="form-control" name="booked_date" required>
              <div class="form-text">Tanggal konsultasi yang dipilih (akan tampil di kalender Booked).</div>
            </div>
            <div class="col-12">
              <label class="form-label">PIC</label>
              <select class="form-select" name="pic_id" required>
                <option value="" selected disabled>Pilih PIC</option>
                <?php foreach ($pics as $p): ?>
                  <option value="<?php echo h($p['id']); ?>"><?php echo h($p['name']); ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Daftar PIC dapat dikelola di menu PIC.</div>
            </div>
            <div class="col-12">
              <label class="form-label">Keterangan</label>
              <textarea class="form-control" name="keterangan" rows="3" placeholder="(opsional)"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-success">Accept</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST" action="">
        <div class="modal-header">
          <h5 class="modal-title">Reject Konsultasi</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="reject">
          <input type="hidden" name="id" id="reject_id" value="">

          <label class="form-label">Alasan</label>
          <textarea class="form-control" name="alasan" rows="4" required placeholder="Tulis alasan penolakan..."></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger">Reject</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  (function () {
    var editModal = document.getElementById('editModal');
    if (editModal) {
      editModal.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        if (!btn) return;
        document.getElementById('edit_id').value = btn.getAttribute('data-id') || '';
        document.getElementById('edit_name').value = btn.getAttribute('data-name') || '';
        document.getElementById('edit_whatsapp').value = btn.getAttribute('data-whatsapp') || '';
        document.getElementById('edit_status').value = btn.getAttribute('data-status') || '';
        document.getElementById('edit_jenis').value = btn.getAttribute('data-jenis') || '';
        document.getElementById('edit_jadwal').value = btn.getAttribute('data-jadwal') || '';
        document.getElementById('edit_pendidikan').value = btn.getAttribute('data-pendidikan') || '';
        var bd = btn.getAttribute('data-booked-date') || '';
        var bdEl = document.getElementById('edit_booked_date');
        if (bdEl) bdEl.value = bd;
      });
    }

    var acceptModal = document.getElementById('acceptModal');
    if (acceptModal) {
      acceptModal.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        if (!btn) return;
        document.getElementById('accept_id').value = btn.getAttribute('data-id') || '';
      });
    }

    var rejectModal = document.getElementById('rejectModal');
    if (rejectModal) {
      rejectModal.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        if (!btn) return;
        document.getElementById('reject_id').value = btn.getAttribute('data-id') || '';
      });
    }
  })();
</script>
</body>
</html>


