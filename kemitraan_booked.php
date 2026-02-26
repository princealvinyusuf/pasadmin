<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

if (!(current_user_can('settings_kemitraan_booked_manage') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db_prod';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

function h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function column_exists(mysqli $conn, string $table, string $column): bool
{
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($column);
    $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function parse_info_items($raw): array
{
    $value = trim((string) ($raw ?? ''));
    if ($value === '') {
        return [];
    }

    $items = [];
    if (str_starts_with($value, '[')) {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            foreach ($decoded as $v) {
                $s = trim((string) $v);
                if ($s !== '') {
                    $items[] = $s;
                }
            }
        }
    }

    if (empty($items)) {
        $items[] = $value;
    }

    return array_values(array_unique($items));
}

function encode_info_items(array $items): ?string
{
    $clean = [];
    foreach ($items as $item) {
        $s = trim((string) $item);
        if ($s !== '') {
            $clean[] = $s;
        }
    }
    $clean = array_values(array_unique($clean));
    if (empty($clean)) {
        return null;
    }
    return json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function detect_info_type(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'none';
    }
    $path = parse_url($value, PHP_URL_PATH);
    $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
        return 'image';
    }
    if ($ext === 'pdf') {
        return 'pdf';
    }
    return 'link';
}

function normalize_info_href(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('/^https?:\/\//i', $value)) {
        return $value;
    }
    return '/' . ltrim($value, '/');
}

function is_internal_walkin_file(string $value): bool
{
    $v = trim($value);
    return $v !== '' && preg_match('#^/?documents/walkin_info/#i', $v) === 1;
}

function remove_internal_walkin_file(string $value): void
{
    if (!is_internal_walkin_file($value)) {
        return;
    }
    $publicDir = get_public_dir();
    if (!$publicDir) {
        return;
    }
    $rel = ltrim($value, '/');
    $abs = realpath($publicDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel));
    if ($abs && str_starts_with($abs, $publicDir) && is_file($abs)) {
        @unlink($abs);
    }
}

function remove_internal_walkin_files(array $items): void
{
    foreach ($items as $item) {
        remove_internal_walkin_file((string) $item);
    }
}

function get_public_dir(): ?string
{
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
    if ($docRoot && is_dir($docRoot)) {
        return $docRoot;
    }

    $candidates = [
        realpath(__DIR__ . '/../public'), // repo layout: /project/pasadmin + /project/public
        realpath(__DIR__ . '/..'),        // deployed layout: /public/pasadmin
    ];
    foreach ($candidates as $dir) {
        if ($dir && is_dir($dir)) {
            return $dir;
        }
    }
    return null;
}

// Ensure schema: add booked_date.informasi_lainnya if missing.
if (!column_exists($conn, 'booked_date', 'informasi_lainnya')) {
    $conn->query("ALTER TABLE booked_date ADD COLUMN informasi_lainnya TEXT NULL AFTER booked_time_finish");
}

$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('n'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
if ($month < 1 || $month > 12) $month = intval(date('n'));
if ($year < 2000 || $year > 2100) $year = intval(date('Y'));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = trim((string) ($_POST['action'] ?? ''));
    $bookedId = intval($_POST['booked_id'] ?? 0);
    $monthKeep = intval($_POST['month'] ?? $month);
    $yearKeep = intval($_POST['year'] ?? $year);
    if ($monthKeep < 1 || $monthKeep > 12) $monthKeep = $month;
    if ($yearKeep < 2000 || $yearKeep > 2100) $yearKeep = $year;
    $redir = 'kemitraan_booked.php?month=' . $monthKeep . '&year=' . $yearKeep;

    if ($bookedId <= 0) {
        $_SESSION['error'] = 'ID booked tidak valid.';
        header('Location: ' . $redir);
        exit;
    }

    $currentInfoRaw = '';
    if ($stmt = $conn->prepare("SELECT informasi_lainnya FROM booked_date WHERE id=? LIMIT 1")) {
        $stmt->bind_param('i', $bookedId);
        $stmt->execute();
        $stmt->bind_result($existingInfo);
        if ($stmt->fetch()) {
            $currentInfoRaw = trim((string) ($existingInfo ?? ''));
        }
        $stmt->close();
    }
    $currentInfoItems = parse_info_items($currentInfoRaw);

    if ($action === 'clear_info') {
        if ($stmt = $conn->prepare("UPDATE booked_date SET informasi_lainnya=NULL, updated_at=NOW() WHERE id=?")) {
            $stmt->bind_param('i', $bookedId);
            $stmt->execute();
            $stmt->close();
        }
        remove_internal_walkin_files($currentInfoItems);
        $_SESSION['success'] = 'Informasi Lainnya berhasil dihapus.';
        header('Location: ' . $redir);
        exit;
    }

    if ($action === 'save_info') {
        $linksText = trim((string) ($_POST['info_links'] ?? ''));
        $newItems = [];

        if ($linksText !== '') {
            $parts = preg_split('/\r\n|\r|\n/', $linksText) ?: [];
            foreach ($parts as $part) {
                $linkValue = trim((string) $part);
                if ($linkValue === '') {
                    continue;
                }
                $isAbsoluteHttp = preg_match('/^https?:\/\//i', $linkValue) === 1;
                $isAbsoluteInternal = str_starts_with($linkValue, '/');
                if (!$isAbsoluteHttp && !$isAbsoluteInternal) {
                    $_SESSION['error'] = 'Setiap link harus diawali http(s):// atau /path-internal.';
                    header('Location: ' . $redir);
                    exit;
                }
                $newItems[] = $linkValue;
            }
        }

        $hasAnyUpload = false;
        if (isset($_FILES['info_files']) && is_array($_FILES['info_files']) && isset($_FILES['info_files']['name']) && is_array($_FILES['info_files']['name'])) {
            $names = $_FILES['info_files']['name'];
            $tmpNames = $_FILES['info_files']['tmp_name'] ?? [];
            $errors = $_FILES['info_files']['error'] ?? [];
            $sizes = $_FILES['info_files']['size'] ?? [];

            $publicDir = get_public_dir();
            if (!$publicDir) {
                $_SESSION['error'] = 'Folder public tidak ditemukan.';
                header('Location: ' . $redir);
                exit;
            }
            $relDir = 'documents/walkin_info';
            $absDir = $publicDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relDir);
            if (!is_dir($absDir) && !@mkdir($absDir, 0775, true)) {
                $_SESSION['error'] = 'Gagal membuat folder upload.';
                header('Location: ' . $redir);
                exit;
            }

            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
            foreach ($names as $i => $origNameRaw) {
                $origName = trim((string) $origNameRaw);
                $uploadErr = intval($errors[$i] ?? UPLOAD_ERR_NO_FILE);
                if ($uploadErr === UPLOAD_ERR_NO_FILE || $origName === '') {
                    continue;
                }
                $hasAnyUpload = true;
                if ($uploadErr !== UPLOAD_ERR_OK) {
                    $_SESSION['error'] = 'Salah satu upload file gagal.';
                    header('Location: ' . $redir);
                    exit;
                }

                $tmpName = (string) ($tmpNames[$i] ?? '');
                $size = intval($sizes[$i] ?? 0);
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed, true)) {
                    $_SESSION['error'] = 'File harus berupa gambar (JPG/PNG/GIF/WEBP) atau PDF.';
                    header('Location: ' . $redir);
                    exit;
                }
                if ($size <= 0 || $size > (8 * 1024 * 1024)) {
                    $_SESSION['error'] = 'Ukuran setiap file maksimal 8MB.';
                    header('Location: ' . $redir);
                    exit;
                }

                $fileName = 'walkin_info_' . $bookedId . '_' . date('YmdHis') . '_' . substr(md5((string) mt_rand()), 0, 8) . '_' . $i . '.' . $ext;
                $dest = $absDir . DIRECTORY_SEPARATOR . $fileName;
                if (!@move_uploaded_file($tmpName, $dest)) {
                    $_SESSION['error'] = 'Gagal menyimpan salah satu file upload.';
                    header('Location: ' . $redir);
                    exit;
                }
                $newItems[] = '/' . $relDir . '/' . $fileName;
            }
        }

        if (empty($newItems) && !$hasAnyUpload) {
            $_SESSION['error'] = 'Isi minimal satu link atau upload satu/lebih file untuk Informasi Lainnya.';
            header('Location: ' . $redir);
            exit;
        }

        // Merge with existing so previously uploaded items stay available
        $mergedItems = array_merge($currentInfoItems, $newItems);
        $newPayload = encode_info_items($mergedItems);
        if ($newPayload === null) {
            $_SESSION['error'] = 'Data Informasi Lainnya tidak valid.';
            header('Location: ' . $redir);
            exit;
        }

        if ($stmt = $conn->prepare("UPDATE booked_date SET informasi_lainnya=?, updated_at=NOW() WHERE id=?")) {
            $stmt->bind_param('si', $newPayload, $bookedId);
            $stmt->execute();
            $stmt->close();
        }

        $_SESSION['success'] = 'Informasi Lainnya berhasil disimpan.';
        header('Location: ' . $redir);
        exit;
    }

    $_SESSION['error'] = 'Aksi tidak dikenali.';
    header('Location: ' . $redir);
    exit;
}

$first_day = date('Y-m-01', strtotime("$year-$month-01"));
$last_day = date('Y-m-t', strtotime($first_day));

$has_range = column_exists($conn, 'booked_date', 'booked_date_start');
$date_select = $has_range
    ? 'bd.booked_date_start, bd.booked_date_finish'
    : 'bd.booked_date AS booked_date_start, bd.booked_date AS booked_date_finish';
$date_where = $has_range
    ? "(bd.booked_date_start <= '$last_day' AND bd.booked_date_finish >= '$first_day')"
    : "bd.booked_date BETWEEN '$first_day' AND '$last_day'";
$order_by = $has_range ? 'bd.booked_date_start' : 'bd.booked_date';

$has_time_range = column_exists($conn, 'booked_date', 'booked_time_start');
$has_time_single = column_exists($conn, 'booked_date', 'booked_time');
$time_select = $has_range
    ? ($has_time_range ? 'bd.booked_time_start, bd.booked_time_finish' : 'NULL AS booked_time_start, NULL AS booked_time_finish')
    : ($has_time_single ? 'bd.booked_time AS booked_time_start, NULL AS booked_time_finish' : 'NULL AS booked_time_start, NULL AS booked_time_finish');

$sql = "
    SELECT
        bd.id AS booked_id,
        $date_select,
        $time_select,
        bd.informasi_lainnya,
        k.institution_name,
        top.name AS partnership_type_name,
        pf.facility_name,
        k.other_pasker_facility,
        pr.room_name,
        k.other_pasker_room,
        k.scheduletimestart,
        k.scheduletimefinish,
        (SELECT GROUP_CONCAT(DISTINCT pr2.room_name ORDER BY pr2.room_name SEPARATOR ', ')
           FROM kemitraan_pasker_room kpr2
           LEFT JOIN pasker_room pr2 ON pr2.id = kpr2.pasker_room_id
          WHERE kpr2.kemitraan_id = k.id) AS rooms_concat,
        (SELECT GROUP_CONCAT(DISTINCT pf2.facility_name ORDER BY pf2.facility_name SEPARATOR ', ')
           FROM kemitraan_pasker_facility kpf2
           LEFT JOIN pasker_facility pf2 ON pf2.id = kpf2.pasker_facility_id
          WHERE kpf2.kemitraan_id = k.id) AS facilities_concat
    FROM booked_date bd
    JOIN kemitraan k ON bd.kemitraan_id = k.id
    LEFT JOIN type_of_partnership top ON top.id = k.type_of_partnership_id
    LEFT JOIN pasker_facility pf ON pf.id = k.pasker_facility_id
    LEFT JOIN pasker_room pr ON pr.id = k.pasker_room_id
    WHERE $date_where
    ORDER BY $order_by
";
$result = $conn->query($sql);

$activities = [];
$bookedRows = [];
$query_error = null;
if ($result === false) {
    $query_error = $conn->error;
} else {
    while ($row = $result->fetch_assoc()) {
        $bookedId = intval($row['booked_id'] ?? 0);
        if ($bookedId > 0 && !isset($bookedRows[$bookedId])) {
            $bookedRows[$bookedId] = $row;
        }

        $start = $row['booked_date_start'];
        $finish = $row['booked_date_finish'];
        if (!$start) continue;
        if (!$finish) $finish = $start;
        $cur = strtotime($start);
        $end = strtotime($finish);
        if ($cur === false || $end === false) continue;
        while ($cur <= $end) {
            $date = date('Y-m-d', $cur);
            $activities[$date][] = $row;
            $cur = strtotime('+1 day', $cur);
        }
    }
}

usort($bookedRows, function ($a, $b) {
    $aDate = (string) ($a['booked_date_start'] ?? '');
    $bDate = (string) ($b['booked_date_start'] ?? '');
    if ($aDate === $bDate) {
        return intval($a['booked_id'] ?? 0) <=> intval($b['booked_id'] ?? 0);
    }
    return $aDate <=> $bDate;
});

$first_day_of_week = intval(date('w', strtotime($first_day))); // 0=Sunday
$days_in_month = intval(date('t', strtotime($first_day)));
$calendar = [];
$week = [];
for ($i = 0; $i < $first_day_of_week; $i++) $week[] = '';
for ($day = 1; $day <= $days_in_month; $day++) {
    $date_str = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $week[] = $date_str;
    if (count($week) === 7) {
        $calendar[] = $week;
        $week = [];
    }
}
if (count($week) > 0) {
    while (count($week) < 7) $week[] = '';
    $calendar[] = $week;
}

$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}
$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Kemitraan Booked Calendar</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f7fa; margin: 0; padding: 0; }
        .calendar-container { max-width: 1100px; margin: 30px auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); padding: 24px; }
        .calendar-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .calendar-nav a { text-decoration: none; color: #388e3c; font-weight: bold; font-size: 1.2em; }
        .calendar-nav span { font-size: 1.3em; font-weight: 600; color: #333; }
        table.calendar { border-collapse: collapse; width: 100%; background: #fff; }
        table.calendar th, table.calendar td { border: 1px solid #e0e0e0; width: 14.2%; vertical-align: top; min-height: 90px; padding: 6px 4px; }
        table.calendar th { background: #8bc34a; color: #fff; font-size: 1.1em; }
        table.calendar td { background: #fafbfc; position: relative; }
        .today { background: #e3f2fd !important; border: 2px solid #1976d2 !important; }
        .activity { margin: 6px 0; padding: 6px 8px; border-radius: 6px; font-size: 0.95em; background: #f1f8e9; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
        .activity.Walk-in\ Interview { background: #e3f2fd; color: #1976d2; }
        .activity.Pendidikan\ Pasar\ Kerja { background: #ffebee; color: #c62828; }
        .activity.Talenta\ Muda { background: #e8f5e9; color: #388e3c; }
        .activity.Job\ Fair { background: #fffde7; color: #fbc02d; }
        .activity.Konsultasi\ Informasi\ Pasar\ Kerja { background: #efebe9; color: #6d4c41; }
        .date-num { font-weight: bold; font-size: 1.1em; margin-bottom: 4px; display: block; }
        .activity[title] { position: relative; cursor: pointer; }
        .activity[title]:hover:after {
            content: attr(title);
            position: absolute;
            left: 0; top: 100%;
            background: #333; color: #fff; padding: 6px 10px; border-radius: 6px;
            white-space: pre-line; font-size: 0.95em; z-index: 10; min-width: 180px;
            margin-top: 4px;
        }
        .info-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid rgba(0,0,0,0.1);
            background: #f8fafc;
        }
        @media (max-width: 700px) {
            .calendar-container { padding: 8px; }
            table.calendar th, table.calendar td { font-size: 0.9em; min-height: 60px; padding: 2px 1px; }
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="calendar-container">
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo h($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo h($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <?php if ($query_error): ?>
        <div class="alert alert-danger" role="alert">
            Query failed: <?php echo h($query_error); ?>
        </div>
    <?php endif; ?>

    <div class="calendar-nav">
        <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>">&laquo; Prev</a>
        <span><?php echo h(date('F Y', strtotime($first_day))); ?></span>
        <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>">Next &raquo;</a>
    </div>

    <table class="calendar">
        <tr>
            <th>Sunday</th><th>Monday</th><th>Tuesday</th><th>Wednesday</th>
            <th>Thursday</th><th>Friday</th><th>Saturday</th>
        </tr>
        <?php foreach ($calendar as $week): ?>
            <tr>
                <?php foreach ($week as $date): ?>
                    <?php $is_today = ($date && $date === $today); ?>
                    <td class="<?php echo $is_today ? 'today' : ''; ?>">
                        <?php if ($date): ?>
                            <span class="date-num"><?php echo h(date('j', strtotime($date))); ?></span>
                            <?php if (!empty($activities[$date])): ?>
                                <?php foreach ($activities[$date] as $act): ?>
                                    <?php
                                        $ptype = $act['partnership_type_name'] ?: 'Kegiatan';
                                        $roomLabelRaw = (($act['rooms_concat'] ?? '') !== '' ? $act['rooms_concat'] : ($act['room_name'] ?: $act['other_pasker_room'] ?: '-'));
                                        $facilityLabelRaw = (($act['facilities_concat'] ?? '') !== '' ? $act['facilities_concat'] : ($act['facility_name'] ?: $act['other_pasker_facility'] ?: '-'));
                                        $roomLabel = h($roomLabelRaw);
                                        $facilityLabel = h($facilityLabelRaw);
                                        $tStart = $act['booked_time_start'] ?? '';
                                        $tFinish = $act['booked_time_finish'] ?? '';
                                        if ($tStart === '' && isset($act['scheduletimestart'])) $tStart = $act['scheduletimestart'];
                                        if ($tFinish === '' && isset($act['scheduletimefinish'])) $tFinish = $act['scheduletimefinish'];
                                        $fmt = function ($t) { return $t ? substr((string) $t, 0, 5) : ''; };
                                        $tStartFmt = $fmt($tStart);
                                        $tFinishFmt = $fmt($tFinish);
                                        $timeLabel = '';
                                        if ($tStartFmt && $tFinishFmt) $timeLabel = $tStartFmt . ' - ' . $tFinishFmt;
                                        elseif ($tStartFmt) $timeLabel = $tStartFmt;
                                        $tooltip = "Instansi: " . h($act['institution_name']) . "\nRuangan: " . $roomLabel . "\nFasilitas: " . $facilityLabel;
                                        $infoRaw = trim((string) ($act['informasi_lainnya'] ?? ''));
                                    ?>
                                    <div class="activity <?php echo str_replace(' ', '\\ ', $ptype); ?>" title="<?php echo $tooltip; ?>">
                                        <strong><?php echo h($ptype); ?></strong><br>
                                        <?php if ($timeLabel !== ''): ?>
                                            <span class="text-muted" style="font-size: 0.95em;"><?php echo h($timeLabel); ?></span><br>
                                        <?php endif; ?>
                                        <?php echo h($act['institution_name']); ?>
                                        <?php if ($infoRaw !== ''): ?>
                                            <div class="mt-1">
                                                <span class="info-pill">
                                                    <i class="bi bi-info-circle"></i> Informasi Lainnya
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    </table>

    <div class="card shadow-sm mt-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">Kelola Informasi Lainnya (Jadwal Walk In)</h5>
                <div class="text-muted small">Bisa diisi link, gambar, atau PDF.</div>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 150px;">Tanggal</th>
                            <th>Instansi</th>
                            <th style="width: 220px;">Informasi Lainnya</th>
                            <th style="width: 210px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($bookedRows)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">Belum ada data booked pada bulan ini.</td></tr>
                    <?php else: ?>
                        <?php foreach ($bookedRows as $r): ?>
                            <?php
                                $start = (string) ($r['booked_date_start'] ?? '');
                                $finish = (string) ($r['booked_date_finish'] ?? '');
                                $dateLabel = $start;
                                if ($start !== '' && $finish !== '' && $start !== $finish) {
                                    $dateLabel = date('d M Y', strtotime($start)) . ' s/d ' . date('d M Y', strtotime($finish));
                                } elseif ($start !== '') {
                                    $dateLabel = date('d M Y', strtotime($start));
                                }
                                $infoRaw = trim((string) ($r['informasi_lainnya'] ?? ''));
                                $infoItems = parse_info_items($infoRaw);
                            ?>
                            <tr>
                                <td><?php echo h($dateLabel); ?></td>
                                <td><?php echo h($r['institution_name'] ?? '-'); ?></td>
                                <td>
                                    <?php if (empty($infoItems)): ?>
                                        <span class="text-muted">-</span>
                                    <?php else: ?>
                                        <div class="d-flex flex-column gap-1">
                                            <?php foreach ($infoItems as $infoOne): ?>
                                                <?php
                                                    $infoType = detect_info_type((string) $infoOne);
                                                    $infoHref = normalize_info_href((string) $infoOne);
                                                ?>
                                                <?php if ($infoType === 'image'): ?>
                                                    <a class="btn btn-outline-info btn-sm" href="<?php echo h($infoHref); ?>" target="_blank" rel="noopener">Lihat Gambar</a>
                                                <?php elseif ($infoType === 'pdf'): ?>
                                                    <a class="btn btn-outline-danger btn-sm" href="<?php echo h($infoHref); ?>" target="_blank" rel="noopener">Lihat PDF</a>
                                                <?php else: ?>
                                                    <a class="btn btn-outline-success btn-sm" href="<?php echo h($infoHref); ?>" target="_blank" rel="noopener">Buka Link</a>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <button
                                            class="btn btn-outline-primary btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#infoModal"
                                            data-booked-id="<?php echo h($r['booked_id']); ?>"
                                            data-instansi="<?php echo h($r['institution_name'] ?? '-'); ?>"
                                            data-info="<?php echo h($infoRaw); ?>"
                                        >
                                            <i class="bi bi-pencil-square me-1"></i>Atur
                                        </button>
                                        <?php if (!empty($infoItems)): ?>
                                            <form method="POST" action="" onsubmit="return confirm('Hapus Informasi Lainnya ini?');" class="d-inline">
                                                <input type="hidden" name="action" value="clear_info">
                                                <input type="hidden" name="booked_id" value="<?php echo h($r['booked_id']); ?>">
                                                <input type="hidden" name="month" value="<?php echo h($month); ?>">
                                                <input type="hidden" name="year" value="<?php echo h($year); ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Hapus</button>
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
        </div>
    </div>
</div>

<div class="modal fade" id="infoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="POST" action="" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title">Atur Informasi Lainnya</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="save_info">
          <input type="hidden" name="booked_id" id="info_booked_id" value="">
          <input type="hidden" name="month" value="<?php echo h($month); ?>">
          <input type="hidden" name="year" value="<?php echo h($year); ?>">

          <div class="mb-2">
            <div class="small text-muted">Instansi</div>
            <div class="fw-semibold" id="info_instansi">-</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Link Informasi Lainnya (opsional)</label>
            <textarea class="form-control" name="info_links" id="info_links" rows="3" placeholder="Satu link per baris&#10;https://contoh.com/file.pdf&#10;/documents/file-internal.pdf"></textarea>
            <div class="form-text">Bisa lebih dari satu link (satu baris satu link).</div>
          </div>

          <div class="mb-2">
            <label class="form-label fw-semibold">Upload Gambar/PDF (opsional)</label>
            <input type="file" class="form-control" name="info_files[]" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" multiple>
            <div class="form-text">Bisa upload banyak file sekaligus (gambar + PDF), maksimal 8MB per file.</div>
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
    var infoModal = document.getElementById('infoModal');
    if (!infoModal) return;
    infoModal.addEventListener('show.bs.modal', function (event) {
      var btn = event.relatedTarget;
      if (!btn) return;
      var bookedId = btn.getAttribute('data-booked-id') || '';
      var instansi = btn.getAttribute('data-instansi') || '-';
      var info = btn.getAttribute('data-info') || '';
      var bookedInput = document.getElementById('info_booked_id');
      var instansiEl = document.getElementById('info_instansi');
      var infoInput = document.getElementById('info_links');
      var infoLines = '';
      try {
        var parsed = JSON.parse(info);
        if (Array.isArray(parsed)) {
          infoLines = parsed.map(function (it) { return String(it || '').trim(); }).filter(Boolean).join('\n');
        } else {
          infoLines = String(info || '').trim();
        }
      } catch (e) {
        infoLines = String(info || '').trim();
      }
      if (bookedInput) bookedInput.value = bookedId;
      if (instansiEl) instansiEl.textContent = instansi;
      if (infoInput) infoInput.value = infoLines;
    });
  })();
</script>
</body>
</html>
<?php $conn->close(); ?>


