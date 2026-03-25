<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

// Permission gate
if (!(current_user_can('career_boost_day_booked_view') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

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

function app_base_url(): string
{
    $default = '/pasadmin/';
    $candidates = [
        $_SERVER['REQUEST_URI'] ?? '',
        $_SERVER['PHP_SELF'] ?? '',
        $_SERVER['SCRIPT_NAME'] ?? '',
    ];
    foreach ($candidates as $candidate) {
        $path = parse_url((string)$candidate, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            continue;
        }
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        foreach ($segments as $segment) {
            if (strcasecmp($segment, 'pasadmin') === 0) {
                return '/' . $segment . '/';
            }
        }
    }
    return $default;
}

// Connect to same DB used by submissions table
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

$appBaseUrl = app_base_url();
$selfPath = $appBaseUrl . 'career_boostday_booked';

// Month navigation (YYYY-MM)
$monthParam = isset($_GET['month']) ? preg_replace('/[^0-9\-]/', '', (string)$_GET['month']) : '';
if (!preg_match('/^\d{4}\-\d{2}$/', $monthParam)) {
    $monthParam = date('Y-m');
}
$year = intval(substr($monthParam, 0, 4));
$month = intval(substr($monthParam, 5, 2));
if ($year < 2000 || $year > 2100) $year = intval(date('Y'));
if ($month < 1 || $month > 12) $month = intval(date('m'));

$firstDay = sprintf('%04d-%02d-01', $year, $month);
$daysInMonth = (int)date('t', strtotime($firstDay));
$firstDow = (int)date('w', strtotime($firstDay)); // 0=Sun..6=Sat
$monthLabel = date('F Y', strtotime($firstDay));

$prevMonth = date('Y-m', strtotime($firstDay . ' -1 month'));
$nextMonth = date('Y-m', strtotime($firstDay . ' +1 month'));

$startDate = $firstDay;
$endDate = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

// Fetch accepted bookings for this month
$bookings = [];
$stmt = $conn->prepare("
    SELECT c.id, c.booked_date, c.booked_time_start, c.booked_time_finish, c.name, c.jenis_konseling, c.jadwal_konseling, c.attendance_confirmed_at,
           c.pic_id, p.name AS pic_name
    FROM career_boostday_consultations c
    LEFT JOIN career_boostday_pics p ON p.id=c.pic_id
    WHERE c.admin_status='accepted'
      AND c.booked_date IS NOT NULL
      AND c.booked_date BETWEEN ? AND ?
    ORDER BY c.booked_date ASC, c.booked_time_start ASC, c.created_at ASC
");
if ($stmt) {
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $d = $r['booked_date'];
            if (!isset($bookings[$d])) $bookings[$d] = [];
            $bookings[$d][] = $r;
        }
    }
    $stmt->close();
}

function fmt_time(?string $t): string {
    if (!$t) return '';
    // 'HH:MM:SS' -> 'HH:MM'
    return substr($t, 0, 5);
}

function build_export_booking_row(array $b): array {
    $t1 = fmt_time($b['booked_time_start'] ?? null);
    $t2 = fmt_time($b['booked_time_finish'] ?? null);
    $time = ($t1 && $t2) ? ($t1 . ' - ' . $t2) : (($b['jadwal_konseling'] ?? '') ?: '-');
    $confirmedAt = (string)($b['attendance_confirmed_at'] ?? '');
    $statusKehadiran = $confirmedAt !== '' ? 'Sudah Konfirmasi Hadir' : 'Belum Konfirmasi';

    return [
        'id' => (int)($b['id'] ?? 0),
        'tanggal_booked' => (string)($b['booked_date'] ?? ''),
        'waktu_booked' => $time,
        'nama' => (string)($b['name'] ?? ''),
        'pic' => (string)($b['pic_name'] ?? '-'),
        'jenis_konseling' => (string)($b['jenis_konseling'] ?? ''),
        'jadwal_konseling' => (string)($b['jadwal_konseling'] ?? ''),
        'status_kehadiran' => $statusKehadiran,
        'attendance_confirmed_at' => $confirmedAt,
    ];
}

// Fetch all accepted bookings (whole data export)
$allBookings = [];
$stmtAll = $conn->prepare("
    SELECT c.id, c.booked_date, c.booked_time_start, c.booked_time_finish, c.name, c.jenis_konseling, c.jadwal_konseling, c.attendance_confirmed_at,
           c.pic_id, p.name AS pic_name
    FROM career_boostday_consultations c
    LEFT JOIN career_boostday_pics p ON p.id=c.pic_id
    WHERE c.admin_status='accepted'
      AND c.booked_date IS NOT NULL
    ORDER BY c.booked_date ASC, c.booked_time_start ASC, c.created_at ASC
");
if ($stmtAll) {
    $stmtAll->execute();
    $resAll = $stmtAll->get_result();
    if ($resAll) {
        while ($r = $resAll->fetch_assoc()) {
            $allBookings[] = $r;
        }
    }
    $stmtAll->close();
}

$exportAllRows = [];
foreach ($allBookings as $b) {
    $exportAllRows[] = build_export_booking_row($b);
}
$exportAllRowsJson = json_encode($exportAllRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($exportAllRowsJson === false) {
    $exportAllRowsJson = '[]';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Career Boost Day Booked | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .cb-cal { background: #fff; border-radius: 14px; box-shadow: 0 8px 24px rgba(0,0,0,0.08); overflow: hidden; }
        .cb-cal-head { padding: 18px 20px; border-bottom: 1px solid #e9ecef; display:flex; align-items:center; justify-content:space-between;}
        .cb-cal-title { font-weight: 700; font-size: 1.25rem; }
        .cb-cal-nav a { text-decoration:none; font-weight: 600; }
        .cb-grid { display:grid; grid-template-columns: repeat(7, 1fr); }
        .cb-dow { background:#7cb342; color:#fff; font-weight:700; padding:10px 12px; border-right:1px solid rgba(255,255,255,0.25); }
        .cb-cell { min-height: 120px; border-right:1px solid #e9ecef; border-bottom:1px solid #e9ecef; padding:8px 10px; }
        .cb-cell:last-child { border-right:none; }
        .cb-day { font-weight: 800; color:#1b1f24; }
        .cb-item { background:#eef7e6; border-left: 4px solid #7cb342; border-radius: 10px; padding: 8px 10px; margin-top:8px; font-size: 0.9rem; }
        .cb-item-title { font-weight: 800; }
        .cb-item-sub { color:#334155; font-size: 0.86rem; margin-top: 2px; }
        .cb-item-confirmed { color:#0d6efd; font-weight: 700; }
        .cb-muted { color:#6c757d; }
        .cb-blank { background:#fafafa; }
        @media (max-width: 992px) { .cb-cell { min-height: 90px; } }
    </style>
</head>
<body class="bg-light">
<?php include __DIR__ . '/navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1">Career Boost Day Booked</h1>
            <div class="text-muted small">Database: <code><?php echo h($activeDb); ?></code> • Hanya data <b>accepted</b> yang punya Tanggal Booked.</div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-success" id="btnExportExcel">
                <i class="bi bi-file-earmark-excel me-1"></i>Export to Excel
            </button>
            <a class="btn btn-outline-secondary" href="<?php echo h($appBaseUrl . 'career_boostday'); ?>"><i class="bi bi-arrow-left me-1"></i>Back to Submissions</a>
        </div>
    </div>

    <div class="cb-cal mb-4">
        <div class="cb-cal-head">
            <div class="cb-cal-nav">
                <a href="<?php echo h($selfPath . '?month=' . $prevMonth); ?>">&laquo; Prev</a>
            </div>
            <div class="cb-cal-title"><?php echo h($monthLabel); ?></div>
            <div class="cb-cal-nav">
                <a href="<?php echo h($selfPath . '?month=' . $nextMonth); ?>">Next &raquo;</a>
            </div>
        </div>

        <div class="cb-grid">
            <?php foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $d): ?>
                <div class="cb-dow"><?php echo h($d); ?></div>
            <?php endforeach; ?>

            <?php
                // leading blanks
                for ($i=0; $i<$firstDow; $i++) {
                    echo '<div class="cb-cell cb-blank"></div>';
                }
                // days
                for ($day=1; $day<=$daysInMonth; $day++) {
                    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    echo '<div class="cb-cell">';
                    echo '<div class="cb-day">' . h($day) . '</div>';
                    if (!empty($bookings[$dateStr])) {
                        foreach ($bookings[$dateStr] as $b) {
                            $t1 = fmt_time($b['booked_time_start']);
                            $t2 = fmt_time($b['booked_time_finish']);
                            $time = ($t1 && $t2) ? ($t1 . ' - ' . $t2) : '';
                            $pic = $b['pic_name'] ? ('PIC: ' . $b['pic_name']) : 'PIC: -';
                            $isConfirmed = !empty($b['attendance_confirmed_at']);
                            echo '<div class="cb-item">';
                            echo '  <div class="cb-item-title">' . h($b['name']) . '</div>';
                            echo '  <div class="cb-item-sub">' . h($time ?: $b['jadwal_konseling']) . '</div>';
                            echo '  <div class="cb-item-sub cb-muted">' . h($pic) . '</div>';
                            if ($isConfirmed) {
                                echo '  <div class="cb-item-sub cb-item-confirmed">Sudah Konfirmasi Hadir</div>';
                            }
                            echo '</div>';
                        }
                    }
                    echo '</div>';
                }
                // trailing blanks
                $cellsUsed = $firstDow + $daysInMonth;
                $trailing = (7 - ($cellsUsed % 7)) % 7;
                for ($i=0; $i<$trailing; $i++) {
                    echo '<div class="cb-cell cb-blank"></div>';
                }
            ?>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="fw-bold mb-2">List Booked (<?php echo h($monthLabel); ?>)</div>
            <?php if (empty($bookings)): ?>
                <div class="text-muted">Belum ada data booked untuk bulan ini.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead class="table-primary">
                            <tr>
                                <th style="width:140px;">Tanggal</th>
                                <th style="width:140px;">Waktu</th>
                                <th>Nama</th>
                                <th style="width:180px;">PIC</th>
                                <th style="width:260px;">Jenis</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($bookings as $d => $items): ?>
                            <?php foreach ($items as $b): ?>
                                <?php
                                    $t1 = fmt_time($b['booked_time_start']);
                                    $t2 = fmt_time($b['booked_time_finish']);
                                    $time = ($t1 && $t2) ? ($t1 . ' - ' . $t2) : '-';
                                    $isConfirmed = !empty($b['attendance_confirmed_at']);
                                ?>
                                <tr>
                                    <td><?php echo h(date('d M Y', strtotime($d))); ?></td>
                                    <td><?php echo h($time); ?></td>
                                    <td class="fw-semibold"><?php echo h($b['name']); ?></td>
                                    <td>
                                        <?php echo h($b['pic_name'] ?? '-'); ?>
                                        <?php if ($isConfirmed): ?>
                                            <div class="small text-primary fw-semibold">Sudah Konfirmasi Hadir</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo h($b['jenis_konseling']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  (function () {
    var btn = document.getElementById('btnExportExcel');
    var allRows = <?php echo $exportAllRowsJson; ?>;
    if (!btn) return;
    btn.addEventListener('click', function () {
      if (typeof XLSX === 'undefined') {
        alert('Library Excel belum termuat.');
        return;
      }
      if (!Array.isArray(allRows) || allRows.length === 0) {
        alert('Belum ada data booked untuk diexport.');
        return;
      }
      var headers = [
        'ID',
        'Tanggal Booked',
        'Waktu Booked',
        'Nama',
        'PIC',
        'Jenis Konseling',
        'Jadwal Konseling',
        'Status Kehadiran',
        'Attendance Confirmed At'
      ];
      var aoa = [headers];
      allRows.forEach(function (row) {
        aoa.push([
          String(row.id || ''),
          String(row.tanggal_booked || ''),
          String(row.waktu_booked || ''),
          String(row.nama || ''),
          String(row.pic || ''),
          String(row.jenis_konseling || ''),
          String(row.jadwal_konseling || ''),
          String(row.status_kehadiran || ''),
          String(row.attendance_confirmed_at || '')
        ]);
      });
      var ws = XLSX.utils.aoa_to_sheet(aoa);
      var wb = XLSX.utils.book_new();
      XLSX.utils.book_append_sheet(wb, ws, 'Career Boost Day');
      XLSX.writeFile(wb, 'career_boostday_booked_all.xlsx');
    });
  })();
</script>
</body>
</html>


