<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

session_start();

// Handle Update
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $pic_name = $_POST['pic_name'];
    $pic_position = $_POST['pic_position'];
    $pic_email = $_POST['pic_email'];
    $pic_whatsapp = $_POST['pic_whatsapp'];
    $sector_category = $_POST['sector_category'];
    $institution_name = $_POST['institution_name'];
    $business_sector = $_POST['business_sector'];
    $institution_addre = $_POST['institution_addre'];
    $partnership_type = $_POST['partnership_type'];
    $needs = $_POST['needs'];
    $schedule = $_POST['schedule'];
    $request_letter = $_POST['request_letter'];
    $status = $_POST['status'];
    $stmt = $conn->prepare("UPDATE kemitraan SET pic_name=?, pic_position=?, pic_email=?, pic_whatsapp=?, sector_category=?, institution_name=?, business_sector=?, institution_addre=?, partnership_type=?, needs=?, schedule=?, request_letter=?, status=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param("ssssssssssssssi", $pic_name, $pic_position, $pic_email, $pic_whatsapp, $sector_category, $institution_name, $business_sector, $institution_addre, $partnership_type, $needs, $schedule, $request_letter, $status, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: kemitraan_submission.php");
    exit();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    // Delete all booked_date rows for this kemitraan
    $stmt = $conn->prepare("DELETE FROM booked_date WHERE kemitraan_id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    // Now delete the kemitraan row
    $stmt = $conn->prepare("DELETE FROM kemitraan WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: kemitraan_submission.php");
    exit();
}

// Add backend logic to handle approve action
if (isset($_POST['approve_id'])) {
    $id = $_POST['approve_id'];
    // Update kemitraan status
    $stmt = $conn->prepare("UPDATE kemitraan SET status='approved', updated_at=NOW() WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    // Fetch schedule and partnership_type for this kemitraan
    $result = $conn->query("SELECT schedule, partnership_type FROM kemitraan WHERE id=$id");
    $row = $result->fetch_assoc();
    $schedule = trim($row['schedule']);
    $partnership_type = trim($row['partnership_type']);

    // Partnership type limits
    $type_limits = [
        'Walk-in Interview' => 10,
        'Pendidikan Pasar Kerja' => 5,
        'Talenta Muda' => 8,
        'Job Fair' => 7,
        'Konsultasi Informasi Pasar Kerja' => 3,
    ];
    $max_bookings = isset($type_limits[$partnership_type]) ? $type_limits[$partnership_type] : 10;

    // Helper function to convert 'd F Y' (e.g. '20 Oktober 2025') to 'Y-m-d'
    function parseIndoDate($str) {
        $bulan = [
            'Januari' => '01', 'Februari' => '02', 'Maret' => '03', 'April' => '04', 'Mei' => '05', 'Juni' => '06',
            'Juli' => '07', 'Agustus' => '08', 'September' => '09', 'Oktober' => '10', 'November' => '11', 'Desember' => '12'
        ];
        if (preg_match('/^(\d{1,2}) ([A-Za-z]+) (\d{4})$/u', trim($str), $m)) {
            $d = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $mth = isset($bulan[$m[2]]) ? $bulan[$m[2]] : '01';
            $y = $m[3];
            return "$y-$mth-$d";
        }
        return false;
    }

    // Helper to check if date is fully booked, with debug output
    function is_fully_booked($conn, $date, $partnership_type, $max_bookings) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM booked_date WHERE booked_date=? AND partnership_type=?");
        $stmt->bind_param("ss", $date, $partnership_type);
        $stmt->execute();
        $current_count = 0;
        $stmt->bind_result($current_count);
        $stmt->fetch();
        $stmt->close();
        // Debug output
        error_log("DEBUG: date=$date, partnership_type=$partnership_type, current_count=$current_count, max_bookings=$max_bookings");
        return $current_count >= $max_bookings;
    }

    $success = true;
    $error_date = '';
    // Parse and insert into booked_date
    if (preg_match('/^(\d{4}-\d{2}-\d{2})\s*to\s*(\d{4}-\d{2}-\d{2})$/', $schedule, $matches)) {
        // Range: YYYY-MM-DD to YYYY-MM-DD
        $start = $matches[1];
        $end = $matches[2];
        $current = strtotime($start);
        $end_ts = strtotime($end);
        while ($current <= $end_ts) {
            $date = date('Y-m-d', $current);
            if (is_fully_booked($conn, $date, $partnership_type, $max_bookings)) {
                $_SESSION['error'] = "Tanggal $date untuk $partnership_type sudah penuh.";
                $success = false;
                $error_date = $date;
                break;
            }
            $current = strtotime('+1 day', $current);
        }
        // Only insert if all dates are available
        if ($success) {
            $current = strtotime($start);
            while ($current <= $end_ts) {
                $date = date('Y-m-d', $current);
                $stmt = $conn->prepare("INSERT INTO booked_date (kemitraan_id, partnership_type, max_bookings, booked_date, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                $stmt->bind_param("isis", $id, $partnership_type, $max_bookings, $date);
                $stmt->execute();
                $stmt->close();
                $current = strtotime('+1 day', $current);
            }
        }
    } elseif ($parsed = parseIndoDate($schedule)) {
        // Single Indo date
        if (is_fully_booked($conn, $parsed, $partnership_type, $max_bookings)) {
            $_SESSION['error'] = "Tanggal $parsed untuk $partnership_type sudah penuh.";
            $success = false;
            $error_date = $parsed;
        } else {
            $stmt = $conn->prepare("INSERT INTO booked_date (kemitraan_id, partnership_type, max_bookings, booked_date, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("isis", $id, $partnership_type, $max_bookings, $parsed);
            $stmt->execute();
            $stmt->close();
        }
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $schedule)) {
        // Single ISO date
        if (is_fully_booked($conn, $schedule, $partnership_type, $max_bookings)) {
            $_SESSION['error'] = "Tanggal $schedule untuk $partnership_type sudah penuh.";
            $success = false;
            $error_date = $schedule;
        } else {
            $stmt = $conn->prepare("INSERT INTO booked_date (kemitraan_id, partnership_type, max_bookings, booked_date, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param("isis", $id, $partnership_type, $max_bookings, $schedule);
            $stmt->execute();
            $stmt->close();
        }
    }
    if ($success) {
        $_SESSION['success'] = 'Pengajuan berhasil di-approve!';
    }
    header("Location: kemitraan_submission.php");
    exit();
}

// Add backend logic to handle reject action
if (isset($_POST['reject_id'])) {
    $id = $_POST['reject_id'];
    $stmt = $conn->prepare("UPDATE kemitraan SET status='rejected', updated_at=NOW() WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: kemitraan_submission.php");
    exit();
}

// Handle Edit (fetch data)
$edit_kemitraan = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $result = $conn->query("SELECT * FROM kemitraan WHERE id=$id");
    $edit_kemitraan = $result->fetch_assoc();
}

// Fetch all kemitraan
$kemitraans = $conn->query("SELECT * FROM kemitraan ORDER BY id DESC");

// Fetch summary counts
$pending_count = $conn->query("SELECT COUNT(*) FROM kemitraan WHERE status='pending'")->fetch_row()[0];
$approved_count = $conn->query("SELECT COUNT(*) FROM kemitraan WHERE status='approved'")->fetch_row()[0];
$rejected_count = $conn->query("SELECT COUNT(*) FROM kemitraan WHERE status='rejected'")->fetch_row()[0];
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
        label {
            display: block;
            margin-bottom: 14px;
            color: #333;
            font-weight: 500;
        }
        input[type="text"], input[type="email"], textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
            margin-top: 4px;
            background: #fff;
            transition: border 0.2s;
        }
        input[type="text"]:focus, input[type="email"]:focus, textarea:focus {
            border: 1.5px solid #2563eb;
            outline: none;
        }
        textarea {
            min-height: 60px;
            resize: vertical;
        }
        .btn {
            display: inline-block;
            padding: 8px 22px;
            border: none;
            border-radius: 6px;
            background: #2563eb;
            color: #fff;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            margin-right: 8px;
            margin-top: 8px;
            transition: background 0.2s;
            text-decoration: none;
        }
        .btn:hover {
            background: #1d4ed8;
        }
        .btn.cancel {
            background: #e5e7eb;
            color: #222;
        }
        .btn.cancel:hover {
            background: #d1d5db;
        }
        .btn.delete {
            background: #ef4444;
        }
        .btn.delete:hover {
            background: #b91c1c;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        th, td {
            padding: 12px 10px;
            text-align: left;
        }
        th {
            background: #f1f5f9;
            color: #222;
            font-weight: 600;
        }
        tr:nth-child(even) {
            background: #f9fafb;
        }
        tr:hover {
            background: #e0e7ef;
        }
        td {
            vertical-align: top;
        }
        .actions a {
            margin-right: 8px;
        }
        @media (max-width: 900px) {
            .container { padding: 8px; }
            form { padding: 12px 6px; }
            th, td { font-size: 0.95rem; padding: 8px 4px; }
            .table-responsive { overflow-x: auto; }
            .btn, .btn-sm { width: 100%; margin-bottom: 6px; }
            .actions { min-width: 110px; }
        }
        .modal-content {
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.10);
            border: 1px solid #e5e7eb;
        }
        .modal-header {
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
        }
        .modal-title {
            font-size: 1.35rem;
            font-weight: 600;
            color: #2563eb;
            letter-spacing: 0.5px;
        }
        .modal-body {
            background: #f9fafb;
            padding-top: 18px;
            padding-bottom: 10px;
        }
        #downloadLetterContainer {
            margin-top: 18px;
            text-align: right;
        }
        #downloadLetterContainer .btn {
            font-size: 1rem;
            padding: 7px 20px;
        }
        .table-detail th {
            text-align: right;
            color: #6b7280;
            width: 220px;
            background: #f1f5f9;
            font-weight: 500;
            vertical-align: top;
        }
        .table-detail td {
            background: #fff;
            vertical-align: top;
        }
        .table-detail tr:nth-child(even) td {
            background: #f9fafb;
        }
        .table-detail tr:hover td {
            background: #e0e7ef;
        }
        .btn-detail { background: #2563eb; color: #fff; border: none; }
        .btn-detail:hover { background: #1d4ed8; color: #fff; }
        .btn-edit { background: #6366f1; color: #fff; border: none; }
        .btn-edit:hover { background: #4338ca; color: #fff; }
        .btn-delete { background: #ef4444; color: #fff; border: none; }
        .btn-delete:hover { background: #b91c1c; color: #fff; }
        .btn-approve { background: #22c55e; color: #fff; border: none; }
        .btn-approve:hover { background: #15803d; color: #fff; }
        .btn-reject { background: #f59e42; color: #fff; border: none; }
        .btn-reject:hover { background: #d97706; color: #fff; }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.html"><i class="bi bi-briefcase me-2"></i>Job Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="settingsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Settings
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="settingsDropdown">
                            <li><a class="dropdown-item" href="mitra_kerja_settings.php">Mitra Kerja Settings</a></li>
                            <li><a class="dropdown-item" href="kemitraan_submission.php">Mitra Kerja Submission</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
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
        <h3><?php echo $edit_kemitraan ? 'Edit Mitra Kerja Submission' : 'All Mitra Kerja Submission'; ?></h3>
        <?php if ($edit_kemitraan): ?>
        <form method="post">
            <input type="hidden" name="id" value="<?php echo $edit_kemitraan['id']; ?>">
            <label>PIC Name:
                <input type="text" name="pic_name" required value="<?php echo htmlspecialchars($edit_kemitraan['pic_name']); ?>">
            </label>
            <label>PIC Position:
                <input type="text" name="pic_position" value="<?php echo htmlspecialchars($edit_kemitraan['pic_position']); ?>">
            </label>
            <label>PIC Email:
                <input type="email" name="pic_email" value="<?php echo htmlspecialchars($edit_kemitraan['pic_email']); ?>">
            </label>
            <label>PIC Whatsapp:
                <input type="text" name="pic_whatsapp" value="<?php echo htmlspecialchars($edit_kemitraan['pic_whatsapp']); ?>">
            </label>
            <label>Sector Category:
                <input type="text" name="sector_category" value="<?php echo htmlspecialchars($edit_kemitraan['sector_category']); ?>">
            </label>
            <label>Institution Name:
                <input type="text" name="institution_name" value="<?php echo htmlspecialchars($edit_kemitraan['institution_name']); ?>">
            </label>
            <label>Business Sector:
                <input type="text" name="business_sector" value="<?php echo htmlspecialchars($edit_kemitraan['business_sector']); ?>">
            </label>
            <label>Institution Address:
                <input type="text" name="institution_addre" value="<?php echo htmlspecialchars($edit_kemitraan['institution_addre']); ?>">
            </label>
            <label>Partnership Type:
                <input type="text" name="partnership_type" value="<?php echo htmlspecialchars($edit_kemitraan['partnership_type']); ?>">
            </label>
            <label>Needs:
                <textarea name="needs"><?php echo htmlspecialchars($edit_kemitraan['needs']); ?></textarea>
            </label>
            <label>Schedule:
                <input type="text" name="schedule" value="<?php echo htmlspecialchars($edit_kemitraan['schedule']); ?>">
            </label>
            <label>Request Letter:
                <input type="text" name="request_letter" value="<?php echo htmlspecialchars($edit_kemitraan['request_letter']); ?>">
            </label>
            <label>Status:
                <input type="text" name="status" value="<?php echo htmlspecialchars($edit_kemitraan['status']); ?>">
            </label>
            <button type="submit" class="btn" name="update">Update</button>
            <a href="kemitraan_submission.php" class="btn cancel">Cancel</a>
        </form>
        <?php endif; ?>
    
        <div class="table-responsive">
        <table class="table table-bordered" style="min-width:1200px">
            <tr>
                <th>Actions</th>
                <th>ID</th>
                <th>PIC Name</th>
                <th>PIC Position</th>
                <th>PIC Email</th>
                <th>PIC Whatsapp</th>
                <th>Sector Category</th>
                <th>Institution Name</th>
                <th>Business Sector</th>
                <th>Institution Address</th>
                <th>Partnership Type</th>
                <th>Needs</th>
                <th>Schedule</th>
                <th>Request Letter</th>
                <th>Status</th>
                <th>Created At</th>
                <th>Updated At</th>
            </tr>
            <?php while ($row = $kemitraans->fetch_assoc()): ?>
            <tr>
                <td class="actions">
                    <button type="button" class="btn btn-detail btn-sm detail-btn mb-1" data-id="<?php echo $row['id']; ?>">Detail</button>
                    <a href="kemitraan_submission.php?edit=<?php echo $row['id']; ?>" class="btn btn-edit btn-sm mb-1">Edit</a>
                    <a href="kemitraan_submission.php?delete=<?php echo $row['id']; ?>" class="btn btn-delete btn-sm mb-1" onclick="return confirm('Delete this submission?');">Delete</a>
                    <?php if ($row['status'] === 'pending'): ?>
                        <button type="button" class="btn btn-approve btn-sm approve-btn mb-1" data-id="<?php echo $row['id']; ?>">Approved</button>
                        <button type="button" class="btn btn-reject btn-sm reject-btn mb-1" data-id="<?php echo $row['id']; ?>">Rejected</button>
                    <?php endif; ?>
                </td>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['pic_name']); ?></td>
                <td><?php echo htmlspecialchars($row['pic_position']); ?></td>
                <td><?php echo htmlspecialchars($row['pic_email']); ?></td>
                <td><?php echo htmlspecialchars($row['pic_whatsapp']); ?></td>
                <td><?php echo htmlspecialchars($row['sector_category']); ?></td>
                <td><?php echo htmlspecialchars($row['institution_name']); ?></td>
                <td><?php echo htmlspecialchars($row['business_sector']); ?></td>
                <td><?php echo htmlspecialchars($row['institution_addre']); ?></td>
                <td><?php echo htmlspecialchars($row['partnership_type']); ?></td>
                <td><?php echo nl2br(htmlspecialchars($row['needs'])); ?></td>
                <td><?php echo htmlspecialchars($row['schedule']); ?></td>
                <td><?php echo htmlspecialchars($row['request_letter']); ?></td>
                <td><?php echo htmlspecialchars($row['status']); ?></td>
                <td><?php echo $row['created_at']; ?></td>
                <td><?php echo $row['updated_at']; ?></td>
            </tr>
            <?php endwhile; ?>
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
                'ID', 'PIC Name', 'PIC Position', 'PIC Email', 'PIC Whatsapp', 'Sector Category',
                'Institution Name', 'Business Sector', 'Institution Address', 'Partnership Type',
                'Needs', 'Schedule', 'Request Letter', 'Status', 'Created At', 'Updated At'
              ];
              let html = '';
              for (let i = 1; i < headers.length + 1; i++) {
                html += `<tr><th>${headers[i-1]}</th><td>${cells[i].innerHTML}</td></tr>`;
              }
              document.getElementById('detailModalBody').innerHTML = html;
              // Download Letter button logic
              const requestLetter = cells[13].innerText.trim();
              const downloadContainer = document.getElementById('downloadLetterContainer');
              if (requestLetter && requestLetter !== '-') {
                const url = 'https://www.psid.run.place/paskerid/storage/app/public/' + requestLetter;
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