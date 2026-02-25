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

function fmt_ts_gmt7(?string $dt): string {
    $dt = trim((string)$dt);
    if ($dt === '') return '';

    try {
        // Assumption: timestamps stored in DB are UTC (Laravel default in many setups).
        // Display requirement: show as GMT+7 (Asia/Jakarta).
        $d = new DateTime($dt, new DateTimeZone('UTC'));
        $d->setTimezone(new DateTimeZone('Asia/Jakarta'));
        return $d->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return $dt; // fallback to raw value if parsing fails
    }
}

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

    // Create Slots table (Jadwal Konseling)
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

    // Seed default slots if empty (match public defaults)
    if (table_exists($conn, 'career_boostday_slots')) {
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

    // Add workflow columns to consultations table (if missing)
    if (table_exists($conn, 'career_boostday_consultations')) {
        // Optional extra field from public form
        if (!column_exists($conn, 'career_boostday_consultations', 'jurusan')) {
            $conn->query("ALTER TABLE career_boostday_consultations ADD COLUMN jurusan VARCHAR(120) NULL AFTER pendidikan_terakhir");
        }
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

function slot_time_range_from_label(mysqli $conn, string $label): array
{
    if (!table_exists($conn, 'career_boostday_slots')) {
        return [null, null];
    }

    $label = trim($label);
    if ($label === '') {
        return [null, null];
    }

    $t1 = null;
    $t2 = null;
    if ($stmt = $conn->prepare("SELECT time_start, time_finish FROM career_boostday_slots WHERE label=? LIMIT 1")) {
        $stmt->bind_param('s', $label);
        $stmt->execute();
        $stmt->bind_result($timeStart, $timeFinish);
        if ($stmt->fetch()) {
            $t1 = (string)$timeStart;
            $t2 = (string)$timeFinish;
        }
        $stmt->close();
    }

    return [$t1 ?: null, $t2 ?: null];
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
        // Prefer slot table lookup, fallback to parsing label format
        [$timeStart, $timeFinish] = slot_time_range_from_label($conn, $slot);
        if (!$timeStart || !$timeFinish) {
            [$timeStart, $timeFinish] = parse_time_range_from_slot($slot);
        }

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
        $jurusan = trim((string)($_POST['jurusan'] ?? ''));
        $bookedDate = trim((string)($_POST['booked_date'] ?? '')); // optional (YYYY-MM-DD)

        if ($name === '' || $whatsapp === '' || $status === '' || $jenis === '' || $jadwal === '') {
            $_SESSION['error'] = 'Nama, WhatsApp, Status, Jenis Konseling, dan Jadwal wajib diisi.';
            header('Location: ' . $redir);
            exit;
        }

        $stmt = $conn->prepare("UPDATE career_boostday_consultations
            SET name=?, whatsapp=?, status=?, jenis_konseling=?, jadwal_konseling=?, pendidikan_terakhir=?, jurusan=NULLIF(?, ''), booked_date=NULLIF(?, ''), admin_updated_at=NOW()
            WHERE id=?");
        if ($stmt) {
            $stmt->bind_param('ssssssssi', $name, $whatsapp, $status, $jenis, $jadwal, $pend, $jurusan, $bookedDate, $id);
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
$dateFrom = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$whereClauses = [];
$params = [];
$types = '';

if ($q !== '') {
    $whereClauses[] = "(c.name LIKE ? OR c.whatsapp LIKE ? OR c.status LIKE ? OR c.jadwal_konseling LIKE ? OR c.admin_status LIKE ? OR p.name LIKE ?)";
    $like = '%' . $q . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like]);
    $types .= 'ssssss';
}
if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $whereClauses[] = "DATE(c.created_at) >= ?";
    $params[] = $dateFrom;
    $types .= 's';
}
if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $whereClauses[] = "DATE(c.created_at) <= ?";
    $params[] = $dateTo;
    $types .= 's';
}
$where = !empty($whereClauses) ? ('WHERE ' . implode(' AND ', $whereClauses)) : '';

// Download all filtered rows (JSON for client-side XLSX generation)
$export = isset($_GET['export']) ? trim((string)$_GET['export']) : '';
if ($export === 'json') {
    $exportRows = [];
    $sqlExport = "SELECT c.id, c.created_at, c.name, c.whatsapp, c.status, c.jenis_konseling, c.jadwal_konseling, c.pendidikan_terakhir, c.jurusan,
                         c.admin_status, p.name AS pic_name, c.booked_date, c.booked_time_start, c.booked_time_finish, c.keterangan, c.alasan
                  FROM career_boostday_consultations c
                  LEFT JOIN career_boostday_pics p ON p.id=c.pic_id
                  $where
                  ORDER BY c.created_at DESC";
    if ($stmt = $conn->prepare($sqlExport)) {
        if ($where) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $r['created_at_gmt7'] = fmt_ts_gmt7((string)($r['created_at'] ?? ''));
                $exportRows[] = $r;
            }
        }
        $stmt->close();
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['rows' => $exportRows], JSON_UNESCAPED_UNICODE);
    exit;
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

// Stats (follow current filter)
$stats = [
    'total' => (int)$total,
    'pending' => 0,
    'accepted' => 0,
    'rejected' => 0,
    'booked' => 0,
];

// Status breakdown (follow current filter)
if ($where) {
    $stmt = $conn->prepare("SELECT c.admin_status, COUNT(*) AS cnt
        FROM career_boostday_consultations c
        LEFT JOIN career_boostday_pics p ON p.id=c.pic_id
        $where
        GROUP BY c.admin_status");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $st = (string)($r['admin_status'] ?? '');
            $cnt = intval($r['cnt'] ?? 0);
            if (isset($stats[$st])) $stats[$st] = $cnt;
        }
    }
    $stmt->close();
} else {
    $res = $conn->query("SELECT admin_status, COUNT(*) AS cnt FROM career_boostday_consultations GROUP BY admin_status");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $st = (string)($r['admin_status'] ?? '');
            $cnt = intval($r['cnt'] ?? 0);
            if (isset($stats[$st])) $stats[$st] = $cnt;
        }
    }
}

// Booked count (accepted + booked_date not null), also follow current filter
if ($where) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS c
        FROM career_boostday_consultations c
        LEFT JOIN career_boostday_pics p ON p.id=c.pic_id
        $where
        AND c.admin_status='accepted'
        AND c.booked_date IS NOT NULL");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->bind_result($bookedCnt);
    $stmt->fetch();
    $stmt->close();
    $stats['booked'] = intval($bookedCnt ?? 0);
} else {
    $res = $conn->query("SELECT COUNT(*) AS c FROM career_boostday_consultations WHERE admin_status='accepted' AND booked_date IS NOT NULL");
    $row = $res ? $res->fetch_assoc() : null;
    $stats['booked'] = $row ? intval($row['c']) : 0;
}

// Fetch PICs (for accept modal)
$pics = [];
$resPics = $conn->query("SELECT id, name FROM career_boostday_pics WHERE is_active=1 ORDER BY name ASC");
if ($resPics) {
    while ($r = $resPics->fetch_assoc()) $pics[] = $r;
}

// Fetch Slots (for edit modal dropdown)
$slots = [];
if (table_exists($conn, 'career_boostday_slots')) {
    $resSlots = $conn->query("SELECT id, label FROM career_boostday_slots WHERE is_active=1 ORDER BY sort_order ASC, id ASC");
    if ($resSlots) {
        while ($r = $resSlots->fetch_assoc()) $slots[] = $r;
    }
}

// Fetch rows
$rows = [];
$sql = "SELECT c.id, c.created_at, c.name, c.whatsapp, c.status, c.jenis_konseling, c.jadwal_konseling, c.pendidikan_terakhir, c.jurusan, c.cv_path, c.cv_original_name,
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
$baseQuery = '';
if ($q !== '') $baseQuery .= '&q=' . urlencode($q);
if ($dateFrom !== '') $baseQuery .= '&date_from=' . urlencode($dateFrom);
if ($dateTo !== '') $baseQuery .= '&date_to=' . urlencode($dateTo);
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
            <a class="btn btn-outline-secondary" href="career_boostday_slot.php"><i class="bi bi-calendar-week me-1"></i>Jadwal</a>
            <a class="btn btn-outline-secondary" href="career_boostday_pic.php"><i class="bi bi-people me-1"></i>PIC</a>
            <a class="btn btn-outline-secondary" href="career_boostday_attendance.php"><i class="bi bi-check2-square me-1"></i>Konfirmasi Kehadiran</a>
            <form class="d-flex gap-2" method="GET" action="">
            <input class="form-control" type="date" name="date_from" value="<?php echo h($dateFrom); ?>" title="Tanggal awal">
            <input class="form-control" type="date" name="date_to" value="<?php echo h($dateTo); ?>" title="Tanggal akhir">
            <input class="form-control" name="q" value="<?php echo h($q); ?>" placeholder="Cari nama / WA / status / jadwal" style="min-width: 220px;">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Cari</button>
            <?php
                $excelUrl = '?export=json';
                if ($q !== '') $excelUrl .= '&q=' . urlencode($q);
                if ($dateFrom !== '') $excelUrl .= '&date_from=' . urlencode($dateFrom);
                if ($dateTo !== '') $excelUrl .= '&date_to=' . urlencode($dateTo);
            ?>
            <button
                id="btnDownloadExcel"
                type="button"
                class="btn btn-success"
                data-export-url="<?php echo h($excelUrl); ?>"
            ><i class="bi bi-file-earmark-excel me-1"></i>Download To Excel</button>
            </form>
        </div>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo h($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo h($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-lg">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Total</div>
                            <div class="fs-4 fw-bold"><?php echo number_format($stats['total']); ?></div>
                        </div>
                        <div class="text-primary fs-4"><i class="bi bi-collection"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Pending</div>
                            <div class="fs-4 fw-bold"><?php echo number_format($stats['pending']); ?></div>
                        </div>
                        <div class="text-warning fs-4"><i class="bi bi-hourglass-split"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Accepted</div>
                            <div class="fs-4 fw-bold"><?php echo number_format($stats['accepted']); ?></div>
                        </div>
                        <div class="text-success fs-4"><i class="bi bi-check2-circle"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Rejected</div>
                            <div class="fs-4 fw-bold"><?php echo number_format($stats['rejected']); ?></div>
                        </div>
                        <div class="text-danger fs-4"><i class="bi bi-x-circle"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-lg">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-muted small">Booked</div>
                            <div class="fs-4 fw-bold"><?php echo number_format($stats['booked']); ?></div>
                        </div>
                        <div class="text-info fs-4"><i class="bi bi-calendar2-check"></i></div>
                    </div>
                </div>
            </div>
        </div>
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
                        <th style="width: 180px;">Jurusan</th>
                        <th style="width: 120px;">Status</th>
                        <th style="width: 140px;">PIC</th>
                        <th style="width: 140px;">Upload CV</th>
                        <th style="width: 200px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="12" class="text-center text-muted py-4">Belum ada data.</td></tr>
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
                            <td><?php echo h(fmt_ts_gmt7($r['created_at'] ?? '')); ?></td>
                            <td class="fw-semibold"><?php echo h($r['name']); ?></td>
                            <td><?php echo h($r['whatsapp']); ?></td>
                            <td><?php echo h($r['status']); ?></td>
                            <td><?php echo h($r['jenis_konseling']); ?></td>
                            <td><?php echo h($r['jadwal_konseling']); ?></td>
                            <td><?php echo h($r['pendidikan_terakhir']); ?></td>
                            <td><?php echo h(trim((string)($r['jurusan'] ?? '')) !== '' ? $r['jurusan'] : '-'); ?></td>
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
                                        data-jurusan="<?php echo h($r['jurusan'] ?? ''); ?>"
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
              <select class="form-select" name="jadwal_konseling" id="edit_jadwal" required>
                <option value="" disabled selected>Pilih jadwal</option>
                <?php foreach ($slots as $s): ?>
                  <option value="<?php echo h($s['label']); ?>"><?php echo h($s['label']); ?></option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">Daftar jadwal dapat dikelola di menu Jadwal.</div>
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
            <div class="col-12">
              <label class="form-label">Jurusan <span class="text-muted">(opsional)</span></label>
              <input class="form-control" name="jurusan" id="edit_jurusan" placeholder="(opsional)">
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
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
  (function () {
    var excelBtn = document.getElementById('btnDownloadExcel');
    if (excelBtn) {
      excelBtn.addEventListener('click', async function () {
        var originalHtml = excelBtn.innerHTML;
        excelBtn.disabled = true;
        excelBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Exporting...';
        try {
          var url = excelBtn.getAttribute('data-export-url') || '?export=json';
          var res = await fetch(url, { credentials: 'same-origin' });
          if (!res.ok) throw new Error('Gagal mengambil data export.');
          var payload = await res.json();
          var rows = Array.isArray(payload.rows) ? payload.rows : [];

          var exportRows = rows.map(function (r) {
            var timeStart = (r.booked_time_start || '').toString().slice(0, 5);
            var timeFinish = (r.booked_time_finish || '').toString().slice(0, 5);
            var bookedTime = [timeStart, timeFinish].filter(Boolean).join(' - ');
            return {
              'ID': r.id || '',
              'Timestamp (GMT+7)': r.created_at_gmt7 || r.created_at || '',
              'Nama': r.name || '',
              'WhatsApp': r.whatsapp || '',
              'Status Peserta': r.status || '',
              'Jenis Konseling': r.jenis_konseling || '',
              'Jadwal Konseling': r.jadwal_konseling || '',
              'Pendidikan Terakhir': r.pendidikan_terakhir || '',
              'Jurusan': r.jurusan || '',
              'Status Admin': r.admin_status || '',
              'PIC': r.pic_name || '',
              'Tanggal Booked': r.booked_date || '',
              'Waktu Booked': bookedTime,
              'Keterangan': r.keterangan || '',
              'Alasan Reject': r.alasan || ''
            };
          });

          var ws = XLSX.utils.json_to_sheet(exportRows);
          var wb = XLSX.utils.book_new();
          XLSX.utils.book_append_sheet(wb, ws, 'Career Boostday');

          var d = new Date();
          var pad = function (n) { return String(n).padStart(2, '0'); };
          var fileName = 'career_boostday_' + d.getFullYear() + pad(d.getMonth() + 1) + pad(d.getDate()) + '_' + pad(d.getHours()) + pad(d.getMinutes()) + pad(d.getSeconds()) + '.xlsx';
          XLSX.writeFile(wb, fileName);
        } catch (err) {
          alert((err && err.message) ? err.message : 'Export gagal.');
        } finally {
          excelBtn.disabled = false;
          excelBtn.innerHTML = originalHtml;
        }
      });
    }

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
        var jadwalVal = btn.getAttribute('data-jadwal') || '';
        var jadwalEl = document.getElementById('edit_jadwal');
        if (jadwalEl) {
          // Ensure current value is selectable even if not in active slot list
          var exists = false;
          for (var i = 0; i < jadwalEl.options.length; i++) {
            if (jadwalEl.options[i].value === jadwalVal) { exists = true; break; }
          }
          if (!exists && jadwalVal) {
            var opt = document.createElement('option');
            opt.value = jadwalVal;
            opt.text = jadwalVal + ' (custom)';
            jadwalEl.appendChild(opt);
          }
          jadwalEl.value = jadwalVal;
        }
        document.getElementById('edit_pendidikan').value = btn.getAttribute('data-pendidikan') || '';
        var jurEl = document.getElementById('edit_jurusan');
        if (jurEl) jurEl.value = btn.getAttribute('data-jurusan') || '';
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


