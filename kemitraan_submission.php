<?php
// Debug: show all errors in this page
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

// Standalone DB connection for paskerid_db_prod
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db_prod';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

session_start();

// Helper for safe COUNT queries
function safe_count(mysqli $conn, string $sql): int {
    $res = $conn->query($sql);
    if ($res === false) {
        return 0;
    }
    $row = $res->fetch_row();
    return $row ? intval($row[0]) : 0;
}

// Helper: check if a column exists in a table (no get_result, mysqlnd-safe)
function column_exists(mysqli $conn, string $table, string $column): bool {
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($column);
    $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    // Fetch request_letter path to remove file from storage
    $filePath = null;
    if ($stmt = $conn->prepare("SELECT request_letter FROM kemitraan WHERE id=?")) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->bind_result($request_letter);
        if ($stmt->fetch()) {
            $filePath = $request_letter;
        }
        $stmt->close();
    }

    // Delete all booked_date rows for this kemitraan
    $stmt = $conn->prepare("DELETE FROM booked_date WHERE kemitraan_id=?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    // Remove the uploaded letter file from storage if present
    if (!empty($filePath)) {
        $publicDir = dirname(__DIR__); // .../public
        $laravelRoot = dirname($publicDir); // project root
        $absStoragePath = $laravelRoot . '/storage/app/public/' . ltrim($filePath, '/');
        $absPublicStoragePath = $publicDir . '/storage/' . ltrim($filePath, '/');
        if (is_file($absStoragePath)) {
            @unlink($absStoragePath);
        } elseif (is_file($absPublicStoragePath)) {
            @unlink($absPublicStoragePath);
        }
    }

    // Now delete the kemitraan row
    $stmt = $conn->prepare("DELETE FROM kemitraan WHERE id=?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: kemitraan_submission.php");
    exit();
}

// Handle Approve
if (isset($_POST['approve_id'])) {
    $id = intval($_POST['approve_id']);
    // Fetch schedule, time start/finish, and partnership type info from new schema
    $stmt = $conn->prepare("SELECT k.schedule, k.scheduletimestart, k.scheduletimefinish, k.type_of_partnership_id, top.name AS type_name FROM kemitraan k LEFT JOIN type_of_partnership top ON top.id = k.type_of_partnership_id WHERE k.id = ?");
    if (!$stmt) {
        $_SESSION['error'] = 'DB prepare failed: ' . $conn->error;
        header("Location: kemitraan_submission.php");
        exit();
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($scheduleRes, $timeStartRes, $timeFinishRes, $typeIdRes, $typeNameRes);
    $found = $stmt->fetch();
    $stmt->close();

    if (!$found) {
        $_SESSION['error'] = "Data kemitraan tidak ditemukan.";
        header("Location: kemitraan_submission.php");
        exit();
    }

    $schedule = trim($scheduleRes ?? '');
    $scheduletimestart = $timeStartRes ? trim($timeStartRes) : null; // expected HH:MM:SS
    $scheduletimefinish = $timeFinishRes ? trim($timeFinishRes) : null; // expected HH:MM:SS
    $type_id = intval($typeIdRes);
    $type_name = trim($typeNameRes ?? '');

    // Partnership type limits (by name)
    $type_limits = [
        'Walk-in Interview' => 10,
        'Pendidikan Pasar Kerja' => 5,
        'Talenta Muda' => 8,
        'Job Fair' => 7,
        'Konsultasi Informasi Pasar Kerja' => 3,
        'Konsultasi Pasar Kerja' => 3,
    ];
    $max_bookings = isset($type_limits[$type_name]) ? $type_limits[$type_name] : 10;

    // Parse schedule into dates
    $dates_to_check = [];
    if (preg_match('/^(\d{4}-\d{2}-\d{2})\s*to\s*(\d{4}-\d{2}-\d{2})$/', $schedule, $matches)) {
        $start = $matches[1];
        $end = $matches[2];
        $current = strtotime($start);
        $end_ts = strtotime($end);
        while ($current <= $end_ts) {
            $dates_to_check[] = date('Y-m-d', $current);
            $current = strtotime('+1 day', $current);
        }
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $schedule)) {
        $dates_to_check[] = $schedule;
    }

    // Past date guard
    $today = date('Y-m-d');
    foreach ($dates_to_check as $date) {
        if ($date < $today) {
            $_SESSION['error'] = "Tanggal $date sudah lewat. Tidak dapat approve.";
            header("Location: kemitraan_submission.php");
            exit();
        }
    }

    // Detect booked_date schema
    $has_range = column_exists($conn, 'booked_date', 'booked_date_start');

    // Check fully booked: for each date in the range, count overlapping bookings by type
    $fully_booked_date = '';
    if ($has_range) {
        $checkStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM booked_date bd WHERE ? BETWEEN bd.booked_date_start AND bd.booked_date_finish AND bd.type_of_partnership_id = ?");
        if (!$checkStmt) {
            $_SESSION['error'] = 'DB prepare failed: ' . $conn->error;
            header("Location: kemitraan_submission.php");
            exit();
        }
        foreach ($dates_to_check as $date) {
            $checkStmt->bind_param("si", $date, $type_id);
            $checkStmt->execute();
            $checkStmt->bind_result($cnt);
            $checkStmt->fetch();
            $current_count = intval($cnt ?? 0);
            if ($current_count >= $max_bookings) {
                $fully_booked_date = $date;
                break;
            }
        }
        $checkStmt->close();
    } else {
        // Fallback schema: single booked_date (and possibly no type column)
        $has_type_col = column_exists($conn, 'booked_date', 'type_of_partnership_id');
        $sql = $has_type_col
            ? "SELECT COUNT(*) AS cnt FROM booked_date bd WHERE bd.booked_date = ? AND bd.type_of_partnership_id = ?"
            : "SELECT COUNT(*) AS cnt FROM booked_date bd WHERE bd.booked_date = ?";
        $checkStmt = $conn->prepare($sql);
        if (!$checkStmt) {
            $_SESSION['error'] = 'DB prepare failed: ' . $conn->error;
            header("Location: kemitraan_submission.php");
            exit();
        }
        foreach ($dates_to_check as $date) {
            if ($has_type_col) {
                $checkStmt->bind_param("si", $date, $type_id);
            } else {
                $checkStmt->bind_param("s", $date);
            }
            $checkStmt->execute();
            $checkStmt->bind_result($cnt);
            $checkStmt->fetch();
            $current_count = intval($cnt ?? 0);
            if ($current_count >= $max_bookings) {
                $fully_booked_date = $date;
                break;
            }
        }
        $checkStmt->close();
    }

    if ($fully_booked_date) {
        $_SESSION['error'] = "Tanggal $fully_booked_date untuk $type_name sudah penuh. Tidak dapat approve.";
        header("Location: kemitraan_submission.php");
        exit();
    }

    // Approve
    $stmt = $conn->prepare("UPDATE kemitraan SET status='approved', updated_at=NOW() WHERE id=?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    // Insert booking rows based on schema
    if ($has_range) {
        // Insert a single row with start/finish (use provided times if present)
        $start_date = $dates_to_check[0];
        $end_date = $dates_to_check[count($dates_to_check) - 1];
        $ins = $conn->prepare("INSERT INTO booked_date (kemitraan_id, booked_date_start, booked_time_start, booked_date_finish, booked_time_finish, type_of_partnership_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        if ($ins) {
            $time_start = $scheduletimestart ?: '00:00:00';
            $time_finish = $scheduletimefinish ?: '23:59:59';
            $ins->bind_param("issssi", $id, $start_date, $time_start, $end_date, $time_finish, $type_id);
            $ins->execute();
            $ins->close();
        }
    } else {
        // Insert per-day rows with booked_date and booked_time (use provided start time if present)
        $has_time_col = column_exists($conn, 'booked_date', 'booked_time');
        $has_type_col = column_exists($conn, 'booked_date', 'type_of_partnership_id');
        if ($has_time_col && $has_type_col) {
            $ins = $conn->prepare("INSERT INTO booked_date (kemitraan_id, booked_date, booked_time, type_of_partnership_id, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $time_default = $scheduletimestart ?: '00:00:00';
            foreach ($dates_to_check as $date) {
                $ins->bind_param("issi", $id, $date, $time_default, $type_id);
                $ins->execute();
            }
            $ins->close();
        } elseif ($has_time_col) {
            $ins = $conn->prepare("INSERT INTO booked_date (kemitraan_id, booked_date, booked_time, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
            $time_default = $scheduletimestart ?: '00:00:00';
            foreach ($dates_to_check as $date) {
                $ins->bind_param("iss", $id, $date, $time_default);
                $ins->execute();
            }
            $ins->close();
        } else {
            // Last resort: if only booked_date exists
            $ins = $conn->prepare("INSERT INTO booked_date (kemitraan_id, booked_date, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            foreach ($dates_to_check as $date) {
                $ins->bind_param("is", $id, $date);
                $ins->execute();
            }
            $ins->close();
        }
    }

    $_SESSION['success'] = 'Pengajuan berhasil di-approve!';
    header("Location: kemitraan_submission.php");
    exit();
}

// Handle Reject
if (isset($_POST['reject_id'])) {
    $id = intval($_POST['reject_id']);
    $stmt = $conn->prepare("UPDATE kemitraan SET status='rejected', updated_at=NOW() WHERE id=?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: kemitraan_submission.php");
    exit();
}

// Fetch all kemitraan with joins for names
$kemitraans = $conn->query(
    "SELECT k.*, cs.sector_name, top.name AS partnership_type_name, pr.room_name, pf.facility_name,
    (SELECT GROUP_CONCAT(DISTINCT pr2.room_name ORDER BY pr2.room_name SEPARATOR ', ')
       FROM kemitraan_pasker_room kpr2
       LEFT JOIN pasker_room pr2 ON pr2.id = kpr2.pasker_room_id
      WHERE kpr2.kemitraan_id = k.id) AS rooms_concat,
    (SELECT GROUP_CONCAT(DISTINCT pf2.facility_name ORDER BY pf2.facility_name SEPARATOR ', ')
       FROM kemitraan_pasker_facility kpf2
       LEFT JOIN pasker_facility pf2 ON pf2.id = kpf2.pasker_facility_id
      WHERE kpf2.kemitraan_id = k.id) AS facilities_concat
     FROM kemitraan k
     LEFT JOIN company_sectors cs ON cs.id = k.company_sectors_id
     LEFT JOIN type_of_partnership top ON top.id = k.type_of_partnership_id
     LEFT JOIN pasker_room pr ON pr.id = k.pasker_room_id
     LEFT JOIN pasker_facility pf ON pf.id = k.pasker_facility_id
     ORDER BY k.id DESC"
);
if ($kemitraans === false) {
    $_SESSION['error'] = 'Query error: ' . $conn->error;
}

// Fetch summary counts safely
$pending_count = safe_count($conn, "SELECT COUNT(*) FROM kemitraan WHERE status='pending'");
$approved_count = safe_count($conn, "SELECT COUNT(*) FROM kemitraan WHERE status='approved'");
$rejected_count = safe_count($conn, "SELECT COUNT(*) FROM kemitraan WHERE status='rejected'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mitra Kerja Submission</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .navbar-brand { font-weight: bold; letter-spacing: 1px; }
        h2, h3 {
            text-align: center;
            color: #222;
        }
        form {
            background: #f9fafb;
            border-radius: 8px;
            padding: 24px 20px 16px 20px;
            margin-bottom: 32px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        label { display: block; margin-bottom: 14px; color: #333; font-weight: 500; }
        input[type="text"], input[type="email"], textarea { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem; margin-top: 4px; background: #fff; transition: border 0.2s; }
        input[type="text"]:focus, input[type="email"]:focus, textarea:focus { border: 1.5px solid #2563eb; outline: none; }
        textarea { min-height: 60px; resize: vertical; }
        .btn { display: inline-block; padding: 8px 22px; border: none; border-radius: 6px; background: #2563eb; color: #fff; font-size: 1rem; font-weight: 500; cursor: pointer; margin-right: 8px; margin-top: 8px; transition: background 0.2s; text-decoration: none; }
        .btn:hover { background: #1d4ed8; }
        .btn.cancel { background: #e5e7eb; color: #222; }
        .btn.cancel:hover { background: #d1d5db; }
        .btn.delete { background: #ef4444; }
        .btn.delete:hover { background: #b91c1c; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.03); }
        th, td { padding: 12px 10px; text-align: left; }
        th { background: #f1f5f9; color: #222; font-weight: 600; }
        tr:nth-child(even) { background: #f9fafb; }
        tr:hover { background: #e0e7ef; }
        td { vertical-align: top; }
        .actions a, .actions button { margin-right: 8px; }
        @media (max-width: 900px) {
            .container { padding: 8px; }
            form { padding: 12px 6px; }
            th, td { font-size: 0.95rem; padding: 8px 4px; }
            .table-responsive { overflow-x: auto; }
            .btn, .btn-sm { width: 100%; margin-bottom: 6px; }
            .actions { min-width: 110px; }
        }
        .modal-content { border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,0.10); border: 1px solid #e5e7eb; }
        .modal-header { background: #f8fafc; border-bottom: 1px solid #e5e7eb; }
        .modal-title { font-size: 1.35rem; font-weight: 600; color: #2563eb; letter-spacing: 0.5px; }
        .modal-body { background: #f9fafb; padding-top: 18px; padding-bottom: 10px; }
        #downloadLetterContainer { margin-top: 18px; text-align: right; }
        #downloadLetterContainer .btn { font-size: 1rem; padding: 7px 20px; }
        .table-detail th { text-align: right; color: #6b7280; width: 220px; background: #f1f5f9; font-weight: 500; vertical-align: top; }
        .table-detail td { background: #fff; vertical-align: top; }
        .table-detail tr:nth-child(even) td { background: #f9fafb; }
        .table-detail tr:hover td { background: #e0e7ef; }
        .btn-detail { background: #2563eb; color: #fff; border: none; }
        .btn-detail:hover { background: #1d4ed8; color: #fff; }
        .btn-delete { background: #ef4444; color: #fff; border: none; }
        .btn-delete:hover { background: #b91c1c; color: #fff; }
        .btn-approve { background: #22c55e; color: #fff; border: none; }
        .btn-approve:hover { background: #15803d; color: #fff; }
        .btn-reject { background: #f59e42; color: #fff; border: none; }
        .btn-reject:hover { background: #d97706; color: #fff; }
    </style>
</head>
<body class="bg-light">
     <?php include 'navbar.php'; ?>
     <!-- End Navigation Bar -->
    <div class="container mt-4">
        <h2 class="mb-3" style="font-size:1.4rem; font-weight:600; color:#222; letter-spacing:0.5px;">Activity Summary</h2>
        <div class="row g-4 mb-4">
            <div class="col-12 col-md-4">
                <div class="card text-center shadow-sm">
                    <div class="card-body">
                        <div class="fs-2 mb-2 text-warning"><i class="bi bi-hourglass-split"></i></div>
                        <h5 class="card-title">Pending</h5>
                        <div class="fs-4 fw-bold"><?php echo $pending_count; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="card text-center shadow-sm">
                    <div class="card-body">
                        <div class="fs-2 mb-2 text-success"><i class="bi bi-check-circle"></i></div>
                        <h5 class="card-title">Approved</h5>
                        <div class="fs-4 fw-bold"><?php echo $approved_count; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="card text-center shadow-sm">
                    <div class="card-body">
                        <div class="fs-2 mb-2 text-danger"><i class="bi bi-x-circle"></i></div>
                        <h5 class="card-title">Rejected</h5>
                        <div class="fs-4 fw-bold"><?php echo $rejected_count; ?></div>
                    </div>
                </div>
            </div>
        </div>
        <h3>All Mitra Kerja Submission</h3>
    
        <div class="table-responsive">
        <table class="table table-bordered" style="min-width:1200px">
            <tr>
                <th>Actions</th>
                <th>ID</th>
                <th>PIC Name</th>
                <th>PIC Position</th>
                <th>PIC Email</th>
                <th>PIC Whatsapp</th>
                <th>Company Sector</th>
                <th>Institution Name</th>
                <th>Business Sector</th>
                <th>Institution Address</th>
                <th>Partnership Type</th>
                <th>Room</th>
                <th>Other Room</th>
                <th>Facility</th>
                <th>Other Facility</th>
                <th>Schedule</th>
                <th>Time</th>
                <th>Request Letter</th>
                <th>Status</th>
                <th>Created At</th>
                <th>Updated At</th>
            </tr>
            <?php if ($kemitraans && $kemitraans->num_rows > 0): ?>
            <?php while ($row = $kemitraans->fetch_assoc()): ?>
            <tr>
                <td class="actions">
                    <button type="button" class="btn btn-detail btn-sm detail-btn mb-1" data-id="<?php echo $row['id']; ?>">Detail</button>
                    <a href="kemitraan_submission.php?delete=<?php echo $row['id']; ?>" class="btn btn-delete btn-sm mb-1" onclick="return confirm('Delete this submission?');">Delete</a>
                    <?php if (isset($row['status']) && $row['status'] === 'pending'): ?>
                        <button type="button" class="btn btn-approve btn-sm approve-btn mb-1" data-id="<?php echo $row['id']; ?>">Approved</button>
                        <button type="button" class="btn btn-reject btn-sm reject-btn mb-1" data-id="<?php echo $row['id']; ?>">Rejected</button>
                    <?php endif; ?>
                </td>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['pic_name']); ?></td>
                <td><?php echo htmlspecialchars($row['pic_position']); ?></td>
                <td><?php echo htmlspecialchars($row['pic_email']); ?></td>
                <td><?php echo htmlspecialchars($row['pic_whatsapp']); ?></td>
                <td><?php echo htmlspecialchars($row['sector_name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['institution_name']); ?></td>
                <td><?php echo htmlspecialchars($row['business_sector']); ?></td>
                <td><?php echo htmlspecialchars($row['institution_address']); ?></td>
                <td><?php echo htmlspecialchars($row['partnership_type_name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars(($row['rooms_concat'] ?? '') !== '' ? $row['rooms_concat'] : ($row['room_name'] ?? '')); ?></td>
                <td><?php echo htmlspecialchars($row['other_pasker_room'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars(($row['facilities_concat'] ?? '') !== '' ? $row['facilities_concat'] : ($row['facility_name'] ?? '')); ?></td>
                <td><?php echo htmlspecialchars($row['other_pasker_facility'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['schedule']); ?></td>
                <td>
                    <?php
                        $ts = isset($row['scheduletimestart']) && $row['scheduletimestart'] ? substr($row['scheduletimestart'], 0, 5) : '';
                        $tf = isset($row['scheduletimefinish']) && $row['scheduletimefinish'] ? substr($row['scheduletimefinish'], 0, 5) : '';
                        $timeLabel = '';
                        if ($ts && $tf) { $timeLabel = $ts . ' - ' . $tf; }
                        elseif ($ts) { $timeLabel = $ts; }
                        echo htmlspecialchars($timeLabel);
                    ?>
                </td>
                <td><?php echo htmlspecialchars($row['request_letter'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($row['status'] ?? ''); ?></td>
                <td><?php echo $row['created_at']; ?></td>
                <td><?php echo $row['updated_at']; ?></td>
            </tr>
            <?php endwhile; ?>
            <?php else: ?>
            <tr><td colspan="20" class="text-center">No submissions found or query failed.</td></tr>
            <?php endif; ?>
        </table>
        </div>
        <!-- Detail Modal -->
        <div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="detailModalLabel">Mitra Kerja Submission Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <table class="table table-bordered table-detail table-striped table-hover mb-0">
                  <tbody id="detailModalBody">
                    <!-- Details will be injected here -->
                  </tbody>
                </table>
                <div id="downloadLetterContainer" class="mb-2"></div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              </div>
            </div>
          </div>
        </div>
        <!-- Approve Modal -->
        <div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="approveModalLabel">Approve Submission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                Are you sure to Approve this submission?
              </div>
              <div class="modal-footer">
                <form method="post" id="approveForm">
                  <input type="hidden" name="approve_id" id="approve_id">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-success">Approve</button>
                </form>
              </div>
            </div>
          </div>
        </div>
        <!-- Reject Modal -->
        <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="rejectModalLabel">Reject Submission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                Are you sure to Reject this submission?
              </div>
              <div class="modal-footer">
                <form method="post" id="rejectForm">
                  <input type="hidden" name="reject_id" id="reject_id">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-warning">Reject</button>
                </form>
              </div>
            </div>
          </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
          const detailButtons = document.querySelectorAll('.detail-btn');
          detailButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
              const row = btn.closest('tr');
              const cells = row.querySelectorAll('td');
              // skip the first cell (actions)
              const headers = [
                'ID', 'PIC Name', 'PIC Position', 'PIC Email', 'PIC Whatsapp', 'Company Sector',
                'Institution Name', 'Business Sector', 'Institution Address', 'Partnership Type',
                'Room', 'Other Room', 'Facility', 'Other Facility', 'Schedule', 'Time', 'Request Letter', 'Status', 'Created At', 'Updated At'
              ];
              let html = '';
              for (let i = 1; i < headers.length + 1; i++) {
                html += `<tr><th>${headers[i-1]}</th><td>${cells[i].innerHTML}</td></tr>`;
              }
              document.getElementById('detailModalBody').innerHTML = html;
              // Download Letter button logic
              const requestLetter = cells[17].innerText.trim();
              const downloadContainer = document.getElementById('downloadLetterContainer');
              if (requestLetter && requestLetter !== '-') {
                // Base path: set window.LARAVEL_PUBLIC_BASE to override, default to '/paskerid/public'
                const basePath = (window.LARAVEL_PUBLIC_BASE || '/paskerid/public');
                const url = basePath.replace(/\/$/, '') + '/storage/' + requestLetter;
                downloadContainer.innerHTML = `<a href="${url}" class="btn btn-success" target="_blank" download>Download Letter</a>`;
              } else {
                downloadContainer.innerHTML = '';
              }
              var detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
              detailModal.show();
            });
          });
          // Approve button logic
          const approveButtons = document.querySelectorAll('.approve-btn');
          approveButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
              document.getElementById('approve_id').value = btn.getAttribute('data-id');
              var approveModal = new bootstrap.Modal(document.getElementById('approveModal'));
              approveModal.show();
            });
          });
          // Reject button logic
          const rejectButtons = document.querySelectorAll('.reject-btn');
          rejectButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
              document.getElementById('reject_id').value = btn.getAttribute('data-id');
              var rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));
              rejectModal.show();
            });
          });
        });
        </script>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php if (isset($_SESSION['error'])): ?>
<script>alert("<?= addslashes($_SESSION['error']) ?>");</script>
<?php unset($_SESSION['error']); endif; ?>
<?php if (isset($_SESSION['success'])): ?>
<script>alert("<?= addslashes($_SESSION['success']) ?>");</script>
<?php unset($_SESSION['success']); endif; ?>
</body>
</html>
<?php $conn->close(); ?> 