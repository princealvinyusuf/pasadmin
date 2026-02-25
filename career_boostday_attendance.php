<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

if (!(current_user_can('career_boost_day_manage') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function try_connect_db(string $dbName): ?mysqli
{
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $conn = @new mysqli($host, $user, $pass, $dbName);
    if ($conn->connect_error) {
        return null;
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

function table_exists(mysqli $conn, string $table): bool
{
    $t = $conn->real_escape_string($table);
    $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function column_exists(mysqli $conn, string $table, string $column): bool
{
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($column);
    $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function ensure_schema(mysqli $conn): void
{
    if (!table_exists($conn, 'career_boostday_consultations')) {
        return;
    }

    if (!column_exists($conn, 'career_boostday_consultations', 'attendance_confirmed_at')) {
        $conn->query("ALTER TABLE career_boostday_consultations ADD COLUMN attendance_confirmed_at DATETIME NULL AFTER booked_time_finish");
    }
}

function fmt_time(?string $t): string
{
    if (!$t) {
        return '-';
    }
    return substr((string) $t, 0, 5);
}

function fmt_dt_local(?string $dt): string
{
    $dt = trim((string) $dt);
    if ($dt === '') {
        return '';
    }
    try {
        $d = new DateTime($dt, new DateTimeZone('UTC'));
        $d->setTimezone(new DateTimeZone('Asia/Jakarta'));
        return $d->format('Y-m-d\TH:i');
    } catch (Throwable $e) {
        return '';
    }
}

function fmt_dt_text(?string $dt): string
{
    $dt = trim((string) $dt);
    if ($dt === '') {
        return '-';
    }
    try {
        $d = new DateTime($dt, new DateTimeZone('UTC'));
        $d->setTimezone(new DateTimeZone('Asia/Jakarta'));
        return $d->format('d M Y H:i');
    } catch (Throwable $e) {
        return $dt;
    }
}

function local_input_to_utc_sql(string $local): ?string
{
    $local = trim($local);
    if ($local === '') {
        return null;
    }
    try {
        $d = new DateTime($local, new DateTimeZone('Asia/Jakarta'));
        $d->setTimezone(new DateTimeZone('UTC'));
        return $d->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return null;
    }
}

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
    if ($tmp) {
        $tmp->close();
    }
}

if (!$conn) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Cannot find table career_boostday_consultations in candidate databases: " . implode(', ', $candidates) . "\n";
    exit;
}

ensure_schema($conn);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    $id = intval($_POST['id'] ?? 0);
    $qs = '';
    if (!empty($_GET['q'])) {
        $qs .= 'q=' . urlencode((string) $_GET['q']);
    }
    if (!empty($_GET['booked_from'])) {
        $qs .= ($qs ? '&' : '') . 'booked_from=' . urlencode((string) $_GET['booked_from']);
    }
    if (!empty($_GET['booked_to'])) {
        $qs .= ($qs ? '&' : '') . 'booked_to=' . urlencode((string) $_GET['booked_to']);
    }
    if (!empty($_GET['page'])) {
        $qs .= ($qs ? '&' : '') . 'page=' . urlencode((string) $_GET['page']);
    }
    $redir = 'career_boostday_attendance.php' . ($qs ? ('?' . $qs) : '');

    if ($action === 'create' || $action === 'update' || $action === 'delete') {
        if ($id <= 0) {
            $_SESSION['error'] = 'ID data tidak valid.';
            header('Location: ' . $redir);
            exit;
        }
    }

    if ($action === 'create') {
        $confirmedAtLocal = trim((string) ($_POST['confirmed_at'] ?? ''));
        $confirmedAtUtc = local_input_to_utc_sql($confirmedAtLocal);
        if ($confirmedAtLocal !== '' && !$confirmedAtUtc) {
            $_SESSION['error'] = 'Format tanggal konfirmasi tidak valid.';
            header('Location: ' . $redir);
            exit;
        }
        if ($confirmedAtUtc === null) {
            $confirmedAtUtc = gmdate('Y-m-d H:i:s');
        }

        $stmt = $conn->prepare("UPDATE career_boostday_consultations
            SET attendance_confirmed_at=?, updated_at=NOW()
            WHERE id=? AND admin_status='accepted' AND booked_date IS NOT NULL");
        if ($stmt) {
            $stmt->bind_param('si', $confirmedAtUtc, $id);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            if ($affected > 0) {
                $_SESSION['success'] = 'Konfirmasi kehadiran berhasil dibuat.';
            } else {
                $_SESSION['error'] = 'Data tidak ditemukan atau belum berstatus accepted + booked.';
            }
        }
        header('Location: ' . $redir);
        exit;
    }

    if ($action === 'update') {
        $confirmedAtLocal = trim((string) ($_POST['confirmed_at'] ?? ''));
        $confirmedAtUtc = local_input_to_utc_sql($confirmedAtLocal);
        if ($confirmedAtLocal !== '' && !$confirmedAtUtc) {
            $_SESSION['error'] = 'Format tanggal konfirmasi tidak valid.';
            header('Location: ' . $redir);
            exit;
        }

        $stmt = $conn->prepare("UPDATE career_boostday_consultations
            SET attendance_confirmed_at=?, updated_at=NOW()
            WHERE id=? AND admin_status='accepted' AND booked_date IS NOT NULL");
        if ($stmt) {
            $stmt->bind_param('si', $confirmedAtUtc, $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Konfirmasi kehadiran berhasil diperbarui.';
        }
        header('Location: ' . $redir);
        exit;
    }

    if ($action === 'delete') {
        $stmt = $conn->prepare("UPDATE career_boostday_consultations
            SET attendance_confirmed_at=NULL, updated_at=NOW()
            WHERE id=?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Konfirmasi kehadiran berhasil dihapus.';
        }
        header('Location: ' . $redir);
        exit;
    }

    $_SESSION['error'] = 'Aksi tidak dikenali.';
    header('Location: ' . $redir);
    exit;
}

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$bookedFrom = isset($_GET['booked_from']) ? trim((string) $_GET['booked_from']) : '';
$bookedTo = isset($_GET['booked_to']) ? trim((string) $_GET['booked_to']) : '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$whereClauses = ["c.admin_status='accepted'", 'c.booked_date IS NOT NULL'];
$params = [];
$types = '';

if ($q !== '') {
    $whereClauses[] = "(c.name LIKE ? OR c.whatsapp LIKE ? OR c.jenis_konseling LIKE ? OR c.jadwal_konseling LIKE ? OR p.name LIKE ?)";
    $like = '%' . $q . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
    $types .= 'sssss';
}
if ($bookedFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $bookedFrom)) {
    $whereClauses[] = 'c.booked_date >= ?';
    $params[] = $bookedFrom;
    $types .= 's';
}
if ($bookedTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $bookedTo)) {
    $whereClauses[] = 'c.booked_date <= ?';
    $params[] = $bookedTo;
    $types .= 's';
}
$where = 'WHERE ' . implode(' AND ', $whereClauses);

$stats = [
    'total' => 0,
    'confirmed' => 0,
    'unconfirmed' => 0,
];
$statsSql = "SELECT
    COUNT(*) AS total_cnt,
    SUM(CASE WHEN c.attendance_confirmed_at IS NOT NULL THEN 1 ELSE 0 END) AS confirmed_cnt
    FROM career_boostday_consultations c
    LEFT JOIN career_boostday_pics p ON p.id=c.pic_id
    $where";
if ($stmt = $conn->prepare($statsSql)) {
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && ($r = $res->fetch_assoc())) {
        $stats['total'] = intval($r['total_cnt'] ?? 0);
        $stats['confirmed'] = intval($r['confirmed_cnt'] ?? 0);
        $stats['unconfirmed'] = max(0, $stats['total'] - $stats['confirmed']);
    }
    $stmt->close();
}

$total = $stats['total'];

$rows = [];
$sql = "SELECT c.id, c.name, c.whatsapp, c.jenis_konseling, c.jadwal_konseling, c.booked_date, c.booked_time_start, c.booked_time_finish,
               c.attendance_confirmed_at, p.name AS pic_name
        FROM career_boostday_consultations c
        LEFT JOIN career_boostday_pics p ON p.id=c.pic_id
        $where
        ORDER BY c.booked_date ASC, c.booked_time_start ASC, c.created_at ASC
        LIMIT ? OFFSET ?";
if ($stmt = $conn->prepare($sql)) {
    if ($types !== '') {
        $types2 = $types . 'ii';
        $params2 = array_merge($params, [$perPage, $offset]);
        $stmt->bind_param($types2, ...$params2);
    } else {
        $stmt->bind_param('ii', $perPage, $offset);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    $stmt->close();
}

$createCandidates = [];
$sqlCreate = "SELECT c.id, c.name, c.booked_date, c.booked_time_start, c.booked_time_finish
    FROM career_boostday_consultations c
    WHERE c.admin_status='accepted'
      AND c.booked_date IS NOT NULL
      AND c.attendance_confirmed_at IS NULL
    ORDER BY c.booked_date ASC, c.booked_time_start ASC, c.created_at ASC
    LIMIT 300";
$resCreate = $conn->query($sqlCreate);
if ($resCreate) {
    while ($r = $resCreate->fetch_assoc()) {
        $createCandidates[] = $r;
    }
}

$totalPages = max(1, (int) ceil($total / $perPage));
$baseQuery = '';
if ($q !== '') $baseQuery .= '&q=' . urlencode($q);
if ($bookedFrom !== '') $baseQuery .= '&booked_from=' . urlencode($bookedFrom);
if ($bookedTo !== '') $baseQuery .= '&booked_to=' . urlencode($bookedTo);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Career Boost Day Konfirmasi Kehadiran | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include __DIR__ . '/navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Career Boost Day Konfirmasi Kehadiran</h1>
            <div class="text-muted small">Database: <code><?php echo h($activeDb); ?></code> • Data accepted + booked</div>
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

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="text-muted small">Total Data</div>
                    <div class="fs-4 fw-bold"><?php echo number_format($stats['total']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="text-muted small">Sudah Konfirmasi</div>
                    <div class="fs-4 fw-bold text-success"><?php echo number_format($stats['confirmed']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="text-muted small">Belum Konfirmasi</div>
                    <div class="fs-4 fw-bold text-warning"><?php echo number_format($stats['unconfirmed']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="fw-semibold mb-2">Tambah Konfirmasi Kehadiran (Create)</div>
            <form method="POST" action="" class="row g-2 align-items-end">
                <input type="hidden" name="action" value="create">
                <div class="col-12 col-lg-7">
                    <label class="form-label">Pilih Peserta</label>
                    <select class="form-select" name="id" required>
                        <option value="" selected disabled>Pilih peserta accepted + booked yang belum konfirmasi</option>
                        <?php foreach ($createCandidates as $c): ?>
                            <?php
                                $dateLabel = date('d M Y', strtotime((string) $c['booked_date']));
                                $timeLabel = fmt_time($c['booked_time_start']) . ' - ' . fmt_time($c['booked_time_finish']);
                            ?>
                            <option value="<?php echo h($c['id']); ?>">
                                <?php echo h($c['name']); ?> — <?php echo h($dateLabel); ?> (<?php echo h($timeLabel); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-lg-3">
                    <label class="form-label">Waktu Konfirmasi (opsional)</label>
                    <input class="form-control" type="datetime-local" name="confirmed_at">
                </div>
                <div class="col-12 col-lg-2 d-grid">
                    <button class="btn btn-success" type="submit"><i class="bi bi-check2-circle me-1"></i>Create</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body border-bottom">
            <form class="row g-2 align-items-end" method="GET" action="">
                <div class="col-12 col-md-3">
                    <label class="form-label">Booked From</label>
                    <input class="form-control" type="date" name="booked_from" value="<?php echo h($bookedFrom); ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Booked To</label>
                    <input class="form-control" type="date" name="booked_to" value="<?php echo h($bookedTo); ?>">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">Cari</label>
                    <input class="form-control" name="q" value="<?php echo h($q); ?>" placeholder="Nama / WA / Jenis / Jadwal / PIC">
                </div>
                <div class="col-12 col-md-2 d-grid">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Filter</button>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead class="table-primary">
                    <tr>
                        <th style="width: 130px;">Booked</th>
                        <th style="width: 120px;">Waktu</th>
                        <th>Nama</th>
                        <th style="width: 170px;">WhatsApp</th>
                        <th style="width: 170px;">PIC</th>
                        <th style="width: 220px;">Jenis</th>
                        <th style="width: 190px;">Konfirmasi Kehadiran</th>
                        <th style="width: 210px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada data.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                        <?php
                            $timeLabel = fmt_time($r['booked_time_start']) . ' - ' . fmt_time($r['booked_time_finish']);
                            $confirmed = trim((string) ($r['attendance_confirmed_at'] ?? '')) !== '';
                        ?>
                        <tr>
                            <td><?php echo h(date('d M Y', strtotime((string) $r['booked_date']))); ?></td>
                            <td><?php echo h($timeLabel); ?></td>
                            <td class="fw-semibold"><?php echo h($r['name']); ?></td>
                            <td><?php echo h($r['whatsapp']); ?></td>
                            <td><?php echo h($r['pic_name'] ?? '-'); ?></td>
                            <td><?php echo h($r['jenis_konseling']); ?></td>
                            <td>
                                <?php if ($confirmed): ?>
                                    <span class="badge text-bg-success">Sudah</span>
                                    <div class="small text-muted mt-1"><?php echo h(fmt_dt_text($r['attendance_confirmed_at'])); ?> (GMT+7)</div>
                                <?php else: ?>
                                    <span class="badge text-bg-warning">Belum</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <button
                                        class="btn btn-outline-secondary btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editConfirmModal"
                                        data-id="<?php echo h($r['id']); ?>"
                                        data-name="<?php echo h($r['name']); ?>"
                                        data-confirmed-at="<?php echo h(fmt_dt_local($r['attendance_confirmed_at'])); ?>"
                                    >
                                        <i class="bi bi-pencil-square me-1"></i>Update
                                    </button>
                                    <?php if (!$confirmed): ?>
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="action" value="create">
                                            <input type="hidden" name="id" value="<?php echo h($r['id']); ?>">
                                            <button class="btn btn-outline-success btn-sm" type="submit"><i class="bi bi-check2 me-1"></i>Confirm</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" action="" onsubmit="return confirm('Hapus konfirmasi kehadiran ini?');" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo h($r['id']); ?>">
                                            <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash me-1"></i>Delete</button>
                                        </form>
                                    <?php endif; ?>
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

<div class="modal fade" id="editConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title">Update Konfirmasi Kehadiran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id" value="">
                    <div class="mb-2">
                        <div class="text-muted small">Peserta</div>
                        <div class="fw-semibold" id="edit_name">-</div>
                    </div>
                    <div>
                        <label class="form-label">Waktu Konfirmasi (GMT+7)</label>
                        <input class="form-control" type="datetime-local" id="edit_confirmed_at" name="confirmed_at">
                        <div class="form-text">Kosongkan nilai untuk menghapus konfirmasi.</div>
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
    var editModal = document.getElementById('editConfirmModal');
    if (!editModal) return;
    editModal.addEventListener('show.bs.modal', function (event) {
      var btn = event.relatedTarget;
      if (!btn) return;
      var id = btn.getAttribute('data-id') || '';
      var name = btn.getAttribute('data-name') || '';
      var confirmedAt = btn.getAttribute('data-confirmed-at') || '';
      var idEl = document.getElementById('edit_id');
      var nameEl = document.getElementById('edit_name');
      var confirmedEl = document.getElementById('edit_confirmed_at');
      if (idEl) idEl.value = id;
      if (nameEl) nameEl.textContent = name;
      if (confirmedEl) confirmedEl.value = confirmedAt;
    });
  })();
</script>
</body>
</html>


