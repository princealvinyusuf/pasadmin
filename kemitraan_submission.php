<?php
// Debug: show all errors in this page
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/auth_guard.php';

// Standalone DB connection for paskerid_db_prod
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db_prod';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

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

// Helper: check if a table exists in current DB
function table_exists(mysqli $conn, string $table): bool {
    $t = $conn->real_escape_string($table);
    $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' LIMIT 1";
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
        // Only allow deletion inside /storage/kemitraan_letters for safety
        $storagePath = $_SERVER['DOCUMENT_ROOT'] . '/storage/' . ltrim($filePath, '/');
        if (is_file($storagePath)) {
            @unlink($storagePath);
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

    // Dynamic limit: read from type_of_partnership.max_bookings if available
    $max_bookings = 10;
    if ($stmt = $conn->prepare("SELECT max_bookings FROM type_of_partnership WHERE id = ?")) {
        $stmt->bind_param("i", $type_id);
        $stmt->execute();
        $stmt->bind_result($maxFromDb);
        if ($stmt->fetch()) {
            $max_bookings = intval($maxFromDb);
        }
        $stmt->close();
    }

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

// Handle Update
if (isset($_POST['update_id'])) {
    $id = intval($_POST['update_id']);
    $pic_name = trim($_POST['pic_name'] ?? '');
    $pic_position = trim($_POST['pic_position'] ?? '');
    $pic_email = trim($_POST['pic_email'] ?? '');
    $pic_whatsapp = trim($_POST['pic_whatsapp'] ?? '');
    $institution_name = trim($_POST['institution_name'] ?? '');
    $business_sector = trim($_POST['business_sector'] ?? '');
    $institution_address = trim($_POST['institution_address'] ?? '');
    $schedule = trim($_POST['schedule'] ?? '');
    $scheduletimestart = trim($_POST['scheduletimestart'] ?? '');
    $scheduletimefinish = trim($_POST['scheduletimefinish'] ?? '');
    $tipe_penyelenggara = trim($_POST['tipe_penyelenggara'] ?? '');
    $company_sectors_id = !empty($_POST['company_sectors_id']) ? intval($_POST['company_sectors_id']) : null;
    $type_of_partnership_id = !empty($_POST['type_of_partnership_id']) ? intval($_POST['type_of_partnership_id']) : null;
    $pasker_room_id = !empty($_POST['pasker_room_id']) ? intval($_POST['pasker_room_id']) : null;
    $other_pasker_room = trim($_POST['other_pasker_room'] ?? '');
    $pasker_facility_id = !empty($_POST['pasker_facility_id']) ? intval($_POST['pasker_facility_id']) : null;
    $other_pasker_facility = trim($_POST['other_pasker_facility'] ?? '');
    
    // Handle foto_kartu_pegawai_pic file upload
    $foto_kartu_pegawai_pic = null;
    if (isset($_POST['existing_foto_kartu_pegawai_pic'])) {
        $foto_kartu_pegawai_pic = trim($_POST['existing_foto_kartu_pegawai_pic']);
    }
    
    if (isset($_FILES['foto_kartu_pegawai_pic']) && $_FILES['foto_kartu_pegawai_pic']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/storage/kemitraan_photos/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }
        $fileName = 'pic_' . $id . '_' . time() . '_' . basename($_FILES['foto_kartu_pegawai_pic']['name']);
        $targetPath = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['foto_kartu_pegawai_pic']['tmp_name'], $targetPath)) {
            // Delete old file if exists
            if ($foto_kartu_pegawai_pic) {
                $oldPath = $_SERVER['DOCUMENT_ROOT'] . '/storage/' . ltrim($foto_kartu_pegawai_pic, '/');
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
            $foto_kartu_pegawai_pic = 'kemitraan_photos/' . $fileName;
        }
    }
    
    // Handle request_letter file upload
    $request_letter = null;
    if (isset($_POST['existing_request_letter'])) {
        $request_letter = trim($_POST['existing_request_letter']);
    }
    
    if (isset($_FILES['request_letter']) && $_FILES['request_letter']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/storage/kemitraan_letters/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0777, true);
        }
        $fileName = 'letter_' . $id . '_' . time() . '_' . basename($_FILES['request_letter']['name']);
        $targetPath = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['request_letter']['tmp_name'], $targetPath)) {
            // Delete old file if exists
            if ($request_letter) {
                $oldPath = $_SERVER['DOCUMENT_ROOT'] . '/storage/' . ltrim($request_letter, '/');
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
            $request_letter = 'kemitraan_letters/' . $fileName;
        }
    }
    
    // Build UPDATE query dynamically based on available columns
    $updateFields = [];
    $updateValues = [];
    $updateTypes = '';
    
    $updateFields[] = "pic_name=?";
    $updateValues[] = $pic_name;
    $updateTypes .= "s";
    
    $updateFields[] = "pic_position=?";
    $updateValues[] = $pic_position;
    $updateTypes .= "s";
    
    $updateFields[] = "pic_email=?";
    $updateValues[] = $pic_email;
    $updateTypes .= "s";
    
    $updateFields[] = "pic_whatsapp=?";
    $updateValues[] = $pic_whatsapp;
    $updateTypes .= "s";
    
    if ($foto_kartu_pegawai_pic !== null) {
        $updateFields[] = "foto_kartu_pegawai_pic=?";
        $updateValues[] = $foto_kartu_pegawai_pic;
        $updateTypes .= "s";
    }
    
    $updateFields[] = "institution_name=?";
    $updateValues[] = $institution_name;
    $updateTypes .= "s";
    
    $updateFields[] = "business_sector=?";
    $updateValues[] = $business_sector;
    $updateTypes .= "s";
    
    $updateFields[] = "institution_address=?";
    $updateValues[] = $institution_address;
    $updateTypes .= "s";
    
    $updateFields[] = "schedule=?";
    $updateValues[] = $schedule;
    $updateTypes .= "s";
    
    if (column_exists($conn, 'kemitraan', 'scheduletimestart')) {
        $updateFields[] = "scheduletimestart=?";
        $updateValues[] = $scheduletimestart ?: null;
        $updateTypes .= "s";
    }
    
    if (column_exists($conn, 'kemitraan', 'scheduletimefinish')) {
        $updateFields[] = "scheduletimefinish=?";
        $updateValues[] = $scheduletimefinish ?: null;
        $updateTypes .= "s";
    }
    
    if (column_exists($conn, 'kemitraan', 'tipe_penyelenggara')) {
        $updateFields[] = "tipe_penyelenggara=?";
        $updateValues[] = $tipe_penyelenggara;
        $updateTypes .= "s";
    }
    
    if ($company_sectors_id !== null && column_exists($conn, 'kemitraan', 'company_sectors_id')) {
        $updateFields[] = "company_sectors_id=?";
        $updateValues[] = $company_sectors_id;
        $updateTypes .= "i";
    }
    
    if ($type_of_partnership_id !== null && column_exists($conn, 'kemitraan', 'type_of_partnership_id')) {
        $updateFields[] = "type_of_partnership_id=?";
        $updateValues[] = $type_of_partnership_id;
        $updateTypes .= "i";
    }
    
    if ($pasker_room_id !== null && column_exists($conn, 'kemitraan', 'pasker_room_id')) {
        $updateFields[] = "pasker_room_id=?";
        $updateValues[] = $pasker_room_id;
        $updateTypes .= "i";
    }
    
    if (column_exists($conn, 'kemitraan', 'other_pasker_room')) {
        $updateFields[] = "other_pasker_room=?";
        $updateValues[] = $other_pasker_room;
        $updateTypes .= "s";
    }
    
    if ($pasker_facility_id !== null && column_exists($conn, 'kemitraan', 'pasker_facility_id')) {
        $updateFields[] = "pasker_facility_id=?";
        $updateValues[] = $pasker_facility_id;
        $updateTypes .= "i";
    }
    
    if (column_exists($conn, 'kemitraan', 'other_pasker_facility')) {
        $updateFields[] = "other_pasker_facility=?";
        $updateValues[] = $other_pasker_facility;
        $updateTypes .= "s";
    }
    
    if ($request_letter !== null && column_exists($conn, 'kemitraan', 'request_letter')) {
        $updateFields[] = "request_letter=?";
        $updateValues[] = $request_letter;
        $updateTypes .= "s";
    }
    
    // Handle status update
    $status = trim($_POST['status'] ?? '');
    if (in_array($status, ['pending', 'approved', 'rejected']) && column_exists($conn, 'kemitraan', 'status')) {
        $updateFields[] = "status=?";
        $updateValues[] = $status;
        $updateTypes .= "s";
    }
    
    $updateFields[] = "updated_at=NOW()";
    $updateValues[] = $id;
    $updateTypes .= "i";
    
    $sql = "UPDATE kemitraan SET " . implode(", ", $updateFields) . " WHERE id=?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($updateTypes, ...$updateValues);
        $stmt->execute();
        $stmt->close();
        
        // Handle multiple rooms (junction table)
        if (table_exists($conn, 'kemitraan_pasker_room')) {
            // Delete existing room associations
            $delRoomStmt = $conn->prepare("DELETE FROM kemitraan_pasker_room WHERE kemitraan_id=?");
            if ($delRoomStmt) {
                $delRoomStmt->bind_param("i", $id);
                $delRoomStmt->execute();
                $delRoomStmt->close();
            }
            
            // Insert new room associations if provided
            if (isset($_POST['pasker_room_ids']) && is_array($_POST['pasker_room_ids'])) {
                $insRoomStmt = $conn->prepare("INSERT INTO kemitraan_pasker_room (kemitraan_id, pasker_room_id) VALUES (?, ?)");
                if ($insRoomStmt) {
                    foreach ($_POST['pasker_room_ids'] as $roomId) {
                        $roomIdInt = intval($roomId);
                        if ($roomIdInt > 0) {
                            $insRoomStmt->bind_param("ii", $id, $roomIdInt);
                            $insRoomStmt->execute();
                        }
                    }
                    $insRoomStmt->close();
                }
            }
        }
        
        // Handle multiple facilities (junction table)
        if (table_exists($conn, 'kemitraan_pasker_facility')) {
            // Delete existing facility associations
            $delFacStmt = $conn->prepare("DELETE FROM kemitraan_pasker_facility WHERE kemitraan_id=?");
            if ($delFacStmt) {
                $delFacStmt->bind_param("i", $id);
                $delFacStmt->execute();
                $delFacStmt->close();
            }
            
            // Insert new facility associations if provided
            if (isset($_POST['pasker_facility_ids']) && is_array($_POST['pasker_facility_ids'])) {
                $insFacStmt = $conn->prepare("INSERT INTO kemitraan_pasker_facility (kemitraan_id, pasker_facility_id) VALUES (?, ?)");
                if ($insFacStmt) {
                    foreach ($_POST['pasker_facility_ids'] as $facilityId) {
                        $facilityIdInt = intval($facilityId);
                        if ($facilityIdInt > 0) {
                            $insFacStmt->bind_param("ii", $id, $facilityIdInt);
                            $insFacStmt->execute();
                        }
                    }
                    $insFacStmt->close();
                }
            }
        }
        
        // Handle Detail Lowongan update
        if (table_exists($conn, 'kemitraan_detail_lowongan')) {
            // Delete existing detail lowongan
            $delStmt = $conn->prepare("DELETE FROM kemitraan_detail_lowongan WHERE kemitraan_id=?");
            if ($delStmt) {
                $delStmt->bind_param("i", $id);
                $delStmt->execute();
                $delStmt->close();
            }
            
            // Insert new detail lowongan if provided
            if (isset($_POST['detail_lowongan']) && is_array($_POST['detail_lowongan'])) {
                $hasNamaPerusahaanCol = column_exists($conn, 'kemitraan_detail_lowongan', 'nama_perusahaan');
                
                foreach ($_POST['detail_lowongan'] as $dl) {
                    $jabatan = trim($dl['jabatan_yang_dibuka'] ?? '');
                    $jumlah = trim($dl['jumlah_kebutuhan'] ?? '');
                    $gender = trim($dl['gender'] ?? '');
                    $pendidikan = trim($dl['pendidikan_terakhir'] ?? '');
                    $pengalaman = trim($dl['pengalaman_kerja'] ?? '');
                    $kompetensi = trim($dl['kompetensi_yang_dibutuhkan'] ?? '');
                    $tahapan = trim($dl['tahapan_seleksi'] ?? '');
                    $lokasi = trim($dl['lokasi_penempatan'] ?? '');
                    
                    // Handle nama_perusahaan (can be array, comma-separated string, or single string)
                    $namaPerusahaanValue = null;
                    if (isset($dl['nama_perusahaan']) && !empty($dl['nama_perusahaan'])) {
                        if (is_array($dl['nama_perusahaan'])) {
                            $namaPerusahaanValue = json_encode(array_filter(array_map('trim', $dl['nama_perusahaan'])));
                        } else {
                            $namaPerusahaanStr = trim($dl['nama_perusahaan']);
                            // Check if it's comma-separated
                            if (strpos($namaPerusahaanStr, ',') !== false) {
                                $companies = array_filter(array_map('trim', explode(',', $namaPerusahaanStr)));
                                $namaPerusahaanValue = json_encode($companies);
                            } else {
                                $namaPerusahaanValue = json_encode([$namaPerusahaanStr]);
                            }
                        }
                    }
                    
                    if ($hasNamaPerusahaanCol) {
                        $insStmt = $conn->prepare("INSERT INTO kemitraan_detail_lowongan (kemitraan_id, jabatan_yang_dibuka, jumlah_kebutuhan, gender, pendidikan_terakhir, pengalaman_kerja, kompetensi_yang_dibutuhkan, tahapan_seleksi, lokasi_penempatan, nama_perusahaan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        if ($insStmt) {
                            $insStmt->bind_param("isssssssss", $id, $jabatan, $jumlah, $gender, $pendidikan, $pengalaman, $kompetensi, $tahapan, $lokasi, $namaPerusahaanValue);
                            $insStmt->execute();
                            $insStmt->close();
                        }
                    } else {
                        $insStmt = $conn->prepare("INSERT INTO kemitraan_detail_lowongan (kemitraan_id, jabatan_yang_dibuka, jumlah_kebutuhan, gender, pendidikan_terakhir, pengalaman_kerja, kompetensi_yang_dibutuhkan, tahapan_seleksi, lokasi_penempatan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        if ($insStmt) {
                            $insStmt->bind_param("issssssss", $id, $jabatan, $jumlah, $gender, $pendidikan, $pengalaman, $kompetensi, $tahapan, $lokasi);
                            $insStmt->execute();
                            $insStmt->close();
                        }
                    }
                }
            }
        }
        
        $_SESSION['success'] = 'Data berhasil diupdate!';
    } else {
        $_SESSION['error'] = 'Gagal update: ' . $conn->error;
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
    (SELECT GROUP_CONCAT(DISTINCT kpr2.pasker_room_id ORDER BY kpr2.pasker_room_id SEPARATOR ',')
       FROM kemitraan_pasker_room kpr2
      WHERE kpr2.kemitraan_id = k.id) AS room_ids,
    (SELECT GROUP_CONCAT(DISTINCT pf2.facility_name ORDER BY pf2.facility_name SEPARATOR ', ')
       FROM kemitraan_pasker_facility kpf2
       LEFT JOIN pasker_facility pf2 ON pf2.id = kpf2.pasker_facility_id
      WHERE kpf2.kemitraan_id = k.id) AS facilities_concat,
    (SELECT GROUP_CONCAT(DISTINCT kpf2.pasker_facility_id ORDER BY kpf2.pasker_facility_id SEPARATOR ',')
       FROM kemitraan_pasker_facility kpf2
      WHERE kpf2.kemitraan_id = k.id) AS facility_ids
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

// Fetch Detail Lowongan (new feature) - map by kemitraan_id
$detailLowonganByKemitraan = [];
if (table_exists($conn, 'kemitraan_detail_lowongan')) {
    $hasNamaPerusahaanCol = false;
    if ($colRes = $conn->query("SHOW COLUMNS FROM kemitraan_detail_lowongan LIKE 'nama_perusahaan'")) {
        $hasNamaPerusahaanCol = $colRes->num_rows > 0;
        $colRes->free();
    }
    $namaPerusahaanSelect = $hasNamaPerusahaanCol ? ", nama_perusahaan" : "";
    $dlRes = $conn->query("SELECT kemitraan_id, jabatan_yang_dibuka, jumlah_kebutuhan, gender, pendidikan_terakhir, pengalaman_kerja, kompetensi_yang_dibutuhkan, tahapan_seleksi, lokasi_penempatan{$namaPerusahaanSelect} FROM kemitraan_detail_lowongan ORDER BY kemitraan_id ASC, id ASC");
    if ($dlRes) {
        while ($dl = $dlRes->fetch_assoc()) {
            $kid = intval($dl['kemitraan_id']);
            if (!isset($detailLowonganByKemitraan[$kid])) {
                $detailLowonganByKemitraan[$kid] = [];
            }

            $namaPerusahaan = [];
            if ($hasNamaPerusahaanCol && isset($dl['nama_perusahaan']) && $dl['nama_perusahaan'] !== null && $dl['nama_perusahaan'] !== '') {
                $decoded = json_decode($dl['nama_perusahaan'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $name) {
                        $name = trim((string) $name);
                        if ($name !== '') {
                            $namaPerusahaan[] = $name;
                        }
                    }
                } else {
                    $single = trim((string) $dl['nama_perusahaan']);
                    if ($single !== '') {
                        $namaPerusahaan[] = $single;
                    }
                }
            }

            $detailLowonganByKemitraan[$kid][] = [
                'jabatan_yang_dibuka' => $dl['jabatan_yang_dibuka'] ?? '',
                'jumlah_kebutuhan' => $dl['jumlah_kebutuhan'] ?? '',
                'gender' => $dl['gender'] ?? '',
                'pendidikan_terakhir' => $dl['pendidikan_terakhir'] ?? '',
                'pengalaman_kerja' => $dl['pengalaman_kerja'] ?? '',
                'kompetensi_yang_dibutuhkan' => $dl['kompetensi_yang_dibutuhkan'] ?? '',
                'tahapan_seleksi' => $dl['tahapan_seleksi'] ?? '',
                'lokasi_penempatan' => $dl['lokasi_penempatan'] ?? '',
                'nama_perusahaan' => $namaPerusahaan,
            ];
        }
        $dlRes->free();
    }
}

// Fetch dropdown options for edit form
$company_sectors = [];
$sectorsRes = $conn->query("SELECT id, sector_name FROM company_sectors ORDER BY sector_name");
if ($sectorsRes) {
    while ($s = $sectorsRes->fetch_assoc()) {
        $company_sectors[] = $s;
    }
    $sectorsRes->free();
}

$partnership_types = [];
$typesRes = $conn->query("SELECT id, name FROM type_of_partnership ORDER BY name");
if ($typesRes) {
    while ($t = $typesRes->fetch_assoc()) {
        $partnership_types[] = $t;
    }
    $typesRes->free();
}

$pasker_rooms = [];
$roomsRes = $conn->query("SELECT id, room_name FROM pasker_room ORDER BY room_name");
if ($roomsRes) {
    while ($r = $roomsRes->fetch_assoc()) {
        $pasker_rooms[] = $r;
    }
    $roomsRes->free();
}

$pasker_facilities = [];
$facilitiesRes = $conn->query("SELECT id, facility_name FROM pasker_facility ORDER BY facility_name");
if ($facilitiesRes) {
    while ($f = $facilitiesRes->fetch_assoc()) {
        $pasker_facilities[] = $f;
    }
    $facilitiesRes->free();
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
        .btn-edit { background: #3b82f6; color: #fff; border: none; }
        .btn-edit:hover { background: #2563eb; color: #fff; }
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
                <th>Foto Kartu Pegawai PIC</th>
                <th>Company Sector</th>
                <th>Institution Name</th>
                <th>Business Sector</th>
                <th>Institution Address</th>
                <th>Partnership Type</th>
                <th>Tipe Penyelenggara</th>
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
            <tr
                data-request-letter="<?php echo htmlspecialchars($row['request_letter'] ?? '', ENT_QUOTES); ?>"
                data-detail-lowongan="<?php echo htmlspecialchars(json_encode($detailLowonganByKemitraan[intval($row['id'])] ?? [], JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>"
            >
                <td class="actions">
                    <button type="button" class="btn btn-detail btn-sm detail-btn mb-1" data-id="<?php echo $row['id']; ?>">Detail</button>
                    <button type="button" class="btn btn-edit btn-sm edit-btn mb-1" data-id="<?php echo $row['id']; ?>" data-row='<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES); ?>'>Edit</button>
                    <a href="kemitraan_submission.php?delete=<?php echo $row['id']; ?>" class="btn btn-delete btn-sm mb-1" onclick="return confirm('Delete this submission?');">Delete</a>
                    <?php if (isset($row['status']) && $row['status'] === 'pending'): ?>
                        <button type="button" class="btn btn-approve btn-sm approve-btn mb-1" data-id="<?php echo $row['id']; ?>">Approved</button>
                    <?php endif; ?>
                    <?php if (isset($row['status']) && ($row['status'] === 'pending' || $row['status'] === 'approved')): ?>
                        <button type="button" class="btn btn-reject btn-sm reject-btn mb-1" data-id="<?php echo $row['id']; ?>">Rejected</button>
                    <?php endif; ?>
                </td>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['pic_name']); ?></td>
                <td><?php echo htmlspecialchars($row['pic_position']); ?></td>
                <td><?php echo htmlspecialchars($row['pic_email']); ?></td>
                <td><?php echo htmlspecialchars($row['pic_whatsapp']); ?></td>
                <td style="max-width:110px">
                    <?php if (!empty($row['foto_kartu_pegawai_pic'])): ?>
                        <a href="/storage/<?php echo ltrim($row['foto_kartu_pegawai_pic'], '/'); ?>" target="_blank">
    <img src="/storage/<?php echo ltrim($row['foto_kartu_pegawai_pic'], '/'); ?>" alt="Kartu Pegawai PIC" class="img-thumbnail" style="max-width:90px;max-height:60px">
</a>
                    <?php else: ?>
                        <span class="text-muted">-</span>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($row['sector_name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['institution_name']); ?></td>
                <td><?php echo htmlspecialchars($row['business_sector']); ?></td>
                <td><?php echo htmlspecialchars($row['institution_address']); ?></td>
                <td><?php echo htmlspecialchars($row['partnership_type_name'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($row['tipe_penyelenggara'] ?? '-'); ?></td>
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
            <tr><td colspan="21" class="text-center">No submissions found or query failed.</td></tr>
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
        <!-- Edit Modal -->
        <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Edit Submission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <form method="post" id="editForm" enctype="multipart/form-data">
                  <input type="hidden" name="update_id" id="edit_id">
                  <input type="hidden" name="existing_foto_kartu_pegawai_pic" id="existing_foto_kartu_pegawai_pic">
                  
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">PIC Name <span class="text-danger">*</span></label>
                      <input type="text" name="pic_name" id="edit_pic_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">PIC Position</label>
                      <input type="text" name="pic_position" id="edit_pic_position" class="form-control">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">PIC Email</label>
                      <input type="email" name="pic_email" id="edit_pic_email" class="form-control">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">PIC Whatsapp</label>
                      <input type="text" name="pic_whatsapp" id="edit_pic_whatsapp" class="form-control">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Foto Kartu Pegawai PIC</label>
                      <input type="file" name="foto_kartu_pegawai_pic" id="edit_foto_kartu_pegawai_pic" class="form-control" accept="image/*">
                      <small class="text-muted">Leave empty to keep current image</small>
                      <div id="current_foto_preview" class="mt-2"></div>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Company Sector</label>
                      <select name="company_sectors_id" id="edit_company_sectors_id" class="form-select">
                        <option value="">-- Select --</option>
                        <?php foreach ($company_sectors as $sector): ?>
                          <option value="<?php echo $sector['id']; ?>"><?php echo htmlspecialchars($sector['sector_name']); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Institution Name <span class="text-danger">*</span></label>
                      <input type="text" name="institution_name" id="edit_institution_name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Business Sector</label>
                      <input type="text" name="business_sector" id="edit_business_sector" class="form-control">
                    </div>
                    <div class="col-12">
                      <label class="form-label">Institution Address</label>
                      <textarea name="institution_address" id="edit_institution_address" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Partnership Type</label>
                      <select name="type_of_partnership_id" id="edit_type_of_partnership_id" class="form-select">
                        <option value="">-- Select --</option>
                        <?php foreach ($partnership_types as $type): ?>
                          <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Tipe Penyelenggara</label>
                      <input type="text" name="tipe_penyelenggara" id="edit_tipe_penyelenggara" class="form-control">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Room (Single)</label>
                      <select name="pasker_room_id" id="edit_pasker_room_id" class="form-select">
                        <option value="">-- Select --</option>
                        <?php foreach ($pasker_rooms as $room): ?>
                          <option value="<?php echo $room['id']; ?>"><?php echo htmlspecialchars($room['room_name']); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Rooms (Multiple)</label>
                      <select name="pasker_room_ids[]" id="edit_pasker_room_ids" class="form-select" multiple size="3">
                        <?php foreach ($pasker_rooms as $room): ?>
                          <option value="<?php echo $room['id']; ?>"><?php echo htmlspecialchars($room['room_name']); ?></option>
                        <?php endforeach; ?>
                      </select>
                      <small class="text-muted">Hold Ctrl/Cmd to select multiple</small>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Other Room</label>
                      <input type="text" name="other_pasker_room" id="edit_other_pasker_room" class="form-control">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Facility (Single)</label>
                      <select name="pasker_facility_id" id="edit_pasker_facility_id" class="form-select">
                        <option value="">-- Select --</option>
                        <?php foreach ($pasker_facilities as $facility): ?>
                          <option value="<?php echo $facility['id']; ?>"><?php echo htmlspecialchars($facility['facility_name']); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Facilities (Multiple)</label>
                      <select name="pasker_facility_ids[]" id="edit_pasker_facility_ids" class="form-select" multiple size="3">
                        <?php foreach ($pasker_facilities as $facility): ?>
                          <option value="<?php echo $facility['id']; ?>"><?php echo htmlspecialchars($facility['facility_name']); ?></option>
                        <?php endforeach; ?>
                      </select>
                      <small class="text-muted">Hold Ctrl/Cmd to select multiple</small>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Other Facility</label>
                      <input type="text" name="other_pasker_facility" id="edit_other_pasker_facility" class="form-control">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Schedule</label>
                      <input type="text" name="schedule" id="edit_schedule" class="form-control" placeholder="YYYY-MM-DD or YYYY-MM-DD to YYYY-MM-DD">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Time Start</label>
                      <input type="time" name="scheduletimestart" id="edit_scheduletimestart" class="form-control">
                    </div>
                    <div class="col-md-3">
                      <label class="form-label">Time Finish</label>
                      <input type="time" name="scheduletimefinish" id="edit_scheduletimefinish" class="form-control">
                    </div>
                    <div class="col-12">
                      <label class="form-label">Request Letter</label>
                      <input type="file" name="request_letter" id="edit_request_letter" class="form-control" accept=".pdf,.doc,.docx">
                      <input type="hidden" name="existing_request_letter" id="existing_request_letter">
                      <small class="text-muted">Leave empty to keep current file</small>
                      <div id="current_request_letter_preview" class="mt-2"></div>
                    </div>
                    <div class="col-12">
                      <label class="form-label">Detail Lowongan</label>
                      <div id="detail_lowongan_container">
                        <!-- Detail Lowongan items will be dynamically added here -->
                      </div>
                      <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="add_detail_lowongan_btn">
                        <i class="bi bi-plus-circle"></i> Add Lowongan
                      </button>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Status</label>
                      <select name="status" id="edit_status" class="form-select">
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">ID</label>
                      <input type="text" id="edit_id_display" class="form-control" readonly style="background-color: #e9ecef;">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Created At</label>
                      <input type="text" id="edit_created_at_display" class="form-control" readonly style="background-color: #e9ecef;">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Updated At</label>
                      <input type="text" id="edit_updated_at_display" class="form-control" readonly style="background-color: #e9ecef;">
                    </div>
                  </div>
                </form>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="editForm" class="btn btn-primary">Update</button>
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
                'ID', 'PIC Name', 'PIC Position', 'PIC Email', 'PIC Whatsapp', 'Foto Kartu Pegawai PIC', 'Company Sector',
                'Institution Name', 'Business Sector', 'Institution Address', 'Partnership Type',
                'Tipe Penyelenggara', 'Room', 'Other Room', 'Facility', 'Other Facility', 'Schedule', 'Time', 'Request Letter', 'Status', 'Created At', 'Updated At'
              ];
              let html = '';

              function escapeHtml(str) {
                return String(str ?? '')
                  .replace(/&/g, '&amp;')
                  .replace(/</g, '&lt;')
                  .replace(/>/g, '&gt;')
                  .replace(/"/g, '&quot;')
                  .replace(/'/g, '&#039;');
              }

              for (let i = 1; i < headers.length + 1; i++) {
                if (headers[i-1] === 'Foto Kartu Pegawai PIC') {
                  const td = cells[i];
                  const img = td.querySelector('img,img-thumbnail');
                  if (img) {
    const url = img.parentNode.href;
    html += `<tr><th>${headers[i-1]}</th><td><a href="${url}" target="_blank">` + img.outerHTML + `</a><br><a href="${url}" class='btn btn-success btn-sm mt-1' download>Download Image</a></td></tr>`;
} else if (td.innerText.trim() !== '-') {
                    html += `<tr><th>${headers[i-1]}</th><td><span class='text-muted'>-</span></td></tr>`;
                  } else {
                    html += `<tr><th>${headers[i-1]}</th><td><span class='text-muted'>-</span></td></tr>`;
                  }
                } else {
                html += `<tr><th>${headers[i-1]}</th><td>${cells[i].innerHTML}</td></tr>`;
                }
              }

              // Detail Lowongan (new feature)
              try {
                const lowonganRaw = row.getAttribute('data-detail-lowongan') || '[]';
                const lowongan = JSON.parse(lowonganRaw);
                if (Array.isArray(lowongan) && lowongan.length > 0) {
                  let lowonganHtml = `<div class="d-flex flex-column gap-3">`;
                  lowongan.forEach((l, idx) => {
                    lowonganHtml += `
                      <div class="border rounded p-2 bg-white">
                        <div class="fw-semibold mb-2">Lowongan #${idx + 1}</div>
                        <div><span class="text-muted">Jabatan Yang Dibuka:</span> ${escapeHtml(l.jabatan_yang_dibuka)}</div>
                        <div><span class="text-muted">Jumlah Kebutuhan:</span> ${escapeHtml(l.jumlah_kebutuhan)}</div>
                        <div><span class="text-muted">Gender:</span> ${escapeHtml(l.gender || '-')}</div>
                        <div><span class="text-muted">Pendidikan Terakhir:</span> ${escapeHtml(l.pendidikan_terakhir || '-')}</div>
                        <div><span class="text-muted">Pengalaman Kerja:</span> ${escapeHtml(l.pengalaman_kerja || '-')}</div>
                        <div><span class="text-muted">Lokasi Penempatan:</span> ${escapeHtml(l.lokasi_penempatan || '-')}</div>
                        <div><span class="text-muted">Nama Perusahaan:</span> ${
                          (Array.isArray(l.nama_perusahaan) && l.nama_perusahaan.length > 0)
                            ? escapeHtml(l.nama_perusahaan.join(', '))
                            : '-'
                        }</div>
                        <div class="mt-2"><span class="text-muted">Kompetensi Yang Dibutuhkan:</span><br>${escapeHtml(l.kompetensi_yang_dibutuhkan || '-')}</div>
                        <div class="mt-2"><span class="text-muted">Tahapan Seleksi:</span><br>${escapeHtml(l.tahapan_seleksi || '-')}</div>
                      </div>
                    `;
                  });
                  lowonganHtml += `</div>`;
                  html += `<tr><th>Detail Lowongan</th><td>${lowonganHtml}</td></tr>`;
                } else {
                  html += `<tr><th>Detail Lowongan</th><td><span class='text-muted'>-</span></td></tr>`;
                }
              } catch (e) {
                html += `<tr><th>Detail Lowongan</th><td><span class='text-muted'>-</span></td></tr>`;
              }

              document.getElementById('detailModalBody').innerHTML = html;
              // Download Letter button logic - use data attribute for reliability
              const requestLetter = row.getAttribute('data-request-letter');
              const downloadContainer = document.getElementById('downloadLetterContainer');
              if (requestLetter && requestLetter.trim() !== '' && requestLetter.trim() !== '-') {
                  // Clean the path: remove leading slashes, storage/, or storage\ prefixes
                  let cleanPath = requestLetter.trim().replace(/^[\/\\]/, '').replace(/^storage[\\\/]/, '');
                  // Ensure it starts with kemitraan_letters/ if it's just a filename
                  if (!cleanPath.includes('/') && !cleanPath.includes('\\')) {
                      cleanPath = 'kemitraan_letters/' + cleanPath;
                  }
                  // Construct the final URL - normalize backslashes to forward slashes
                  const url = '/storage/' + cleanPath.replace(/\\/g, '/');
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
          
          // Edit button logic
          const editButtons = document.querySelectorAll('.edit-btn');
          editButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
              const rowData = JSON.parse(btn.getAttribute('data-row'));
              const id = btn.getAttribute('data-id');
              
              // Populate form fields
              document.getElementById('edit_id').value = id;
              document.getElementById('edit_id_display').value = id;
              document.getElementById('edit_pic_name').value = rowData.pic_name || '';
              document.getElementById('edit_pic_position').value = rowData.pic_position || '';
              document.getElementById('edit_pic_email').value = rowData.pic_email || '';
              document.getElementById('edit_pic_whatsapp').value = rowData.pic_whatsapp || '';
              document.getElementById('edit_institution_name').value = rowData.institution_name || '';
              document.getElementById('edit_business_sector').value = rowData.business_sector || '';
              document.getElementById('edit_institution_address').value = rowData.institution_address || '';
              document.getElementById('edit_schedule').value = rowData.schedule || '';
              document.getElementById('edit_tipe_penyelenggara').value = rowData.tipe_penyelenggara || '';
              document.getElementById('edit_other_pasker_room').value = rowData.other_pasker_room || '';
              document.getElementById('edit_other_pasker_facility').value = rowData.other_pasker_facility || '';
              document.getElementById('edit_status').value = rowData.status || 'pending';
              document.getElementById('edit_created_at_display').value = rowData.created_at || '';
              document.getElementById('edit_updated_at_display').value = rowData.updated_at || '';
              
              // Set dropdowns
              if (rowData.company_sectors_id) {
                document.getElementById('edit_company_sectors_id').value = rowData.company_sectors_id;
              }
              if (rowData.type_of_partnership_id) {
                document.getElementById('edit_type_of_partnership_id').value = rowData.type_of_partnership_id;
              }
              if (rowData.pasker_room_id) {
                document.getElementById('edit_pasker_room_id').value = rowData.pasker_room_id;
              }
              if (rowData.pasker_facility_id) {
                document.getElementById('edit_pasker_facility_id').value = rowData.pasker_facility_id;
              }
              
              // Handle multiple rooms
              const roomIdsSelect = document.getElementById('edit_pasker_room_ids');
              if (rowData.room_ids) {
                const roomIds = rowData.room_ids.split(',').map(id => id.trim()).filter(id => id);
                Array.from(roomIdsSelect.options).forEach(option => {
                  if (roomIds.includes(option.value)) {
                    option.selected = true;
                  }
                });
              }
              
              // Handle multiple facilities
              const facilityIdsSelect = document.getElementById('edit_pasker_facility_ids');
              if (rowData.facility_ids) {
                const facilityIds = rowData.facility_ids.split(',').map(id => id.trim()).filter(id => id);
                Array.from(facilityIdsSelect.options).forEach(option => {
                  if (facilityIds.includes(option.value)) {
                    option.selected = true;
                  }
                });
              }
              
              // Handle time fields
              if (rowData.scheduletimestart) {
                const timeStart = rowData.scheduletimestart.substring(0, 5);
                document.getElementById('edit_scheduletimestart').value = timeStart;
              }
              if (rowData.scheduletimefinish) {
                const timeFinish = rowData.scheduletimefinish.substring(0, 5);
                document.getElementById('edit_scheduletimefinish').value = timeFinish;
              }
              
              // Handle foto preview
              const fotoPreview = document.getElementById('current_foto_preview');
              const existingFoto = rowData.foto_kartu_pegawai_pic || '';
              document.getElementById('existing_foto_kartu_pegawai_pic').value = existingFoto;
              
              if (existingFoto) {
                const fotoUrl = '/storage/' + existingFoto.replace(/^\/+/, '');
                fotoPreview.innerHTML = '<small class="text-muted">Current:</small><br><img src="' + fotoUrl + '" alt="Current Photo" class="img-thumbnail mt-1" style="max-width:100px;max-height:80px">';
              } else {
                fotoPreview.innerHTML = '';
              }
              
              // Handle Request Letter preview
              const requestLetterPreview = document.getElementById('current_request_letter_preview');
              const existingLetter = rowData.request_letter || '';
              document.getElementById('existing_request_letter').value = existingLetter;
              
              if (existingLetter && existingLetter !== '-') {
                const letterUrl = '/storage/' + existingLetter.replace(/^\/+/, '').replace(/^storage[\/\\]/, '');
                requestLetterPreview.innerHTML = '<small class="text-muted">Current:</small><br><a href="' + letterUrl + '" target="_blank" class="btn btn-sm btn-outline-success mt-1"><i class="bi bi-download"></i> View Current Letter</a>';
              } else {
                requestLetterPreview.innerHTML = '';
              }
              
              // Handle Detail Lowongan
              const detailLowonganContainer = document.getElementById('detail_lowongan_container');
              detailLowonganContainer.innerHTML = '';
              detailLowonganIndex = 0; // Reset index
              
              try {
                const lowonganRaw = row.getAttribute('data-detail-lowongan') || '[]';
                const lowongan = JSON.parse(lowonganRaw);
                
                if (Array.isArray(lowongan) && lowongan.length > 0) {
                  lowongan.forEach((l, idx) => {
                    addDetailLowonganItem(l, idx);
                  });
                  detailLowonganIndex = lowongan.length; // Set next index
                }
              } catch (e) {
                console.error('Error parsing detail lowongan:', e);
              }
              
              var editModal = new bootstrap.Modal(document.getElementById('editModal'));
              editModal.show();
            });
          });
          
          // Detail Lowongan management
          let detailLowonganIndex = 0;
          
          function addDetailLowonganItem(data = null, index = null) {
            const idx = index !== null ? index : detailLowonganIndex++;
            const container = document.getElementById('detail_lowongan_container');
            
            const itemHtml = `
              <div class="border rounded p-3 mb-3 bg-light" data-index="${idx}">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <h6 class="mb-0">Lowongan #${idx + 1}</h6>
                  <button type="button" class="btn btn-sm btn-danger remove-lowongan-btn" data-index="${idx}">
                    <i class="bi bi-trash"></i> Remove
                  </button>
                </div>
                <div class="row g-2">
                  <div class="col-md-6">
                    <label class="form-label small">Jabatan Yang Dibuka</label>
                    <input type="text" name="detail_lowongan[${idx}][jabatan_yang_dibuka]" class="form-control form-control-sm" value="${data ? (data.jabatan_yang_dibuka || '') : ''}">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small">Jumlah Kebutuhan</label>
                    <input type="text" name="detail_lowongan[${idx}][jumlah_kebutuhan]" class="form-control form-control-sm" value="${data ? (data.jumlah_kebutuhan || '') : ''}">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small">Gender</label>
                    <input type="text" name="detail_lowongan[${idx}][gender]" class="form-control form-control-sm" value="${data ? (data.gender || '') : ''}">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small">Pendidikan Terakhir</label>
                    <input type="text" name="detail_lowongan[${idx}][pendidikan_terakhir]" class="form-control form-control-sm" value="${data ? (data.pendidikan_terakhir || '') : ''}">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small">Pengalaman Kerja</label>
                    <input type="text" name="detail_lowongan[${idx}][pengalaman_kerja]" class="form-control form-control-sm" value="${data ? (data.pengalaman_kerja || '') : ''}">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small">Lokasi Penempatan</label>
                    <input type="text" name="detail_lowongan[${idx}][lokasi_penempatan]" class="form-control form-control-sm" value="${data ? (data.lokasi_penempatan || '') : ''}">
                  </div>
                  <div class="col-12">
                    <label class="form-label small">Nama Perusahaan (comma-separated)</label>
                    <input type="text" name="detail_lowongan[${idx}][nama_perusahaan]" class="form-control form-control-sm" value="${data && data.nama_perusahaan ? (Array.isArray(data.nama_perusahaan) ? data.nama_perusahaan.join(', ') : data.nama_perusahaan) : ''}" placeholder="Company 1, Company 2">
                  </div>
                  <div class="col-12">
                    <label class="form-label small">Kompetensi Yang Dibutuhkan</label>
                    <textarea name="detail_lowongan[${idx}][kompetensi_yang_dibutuhkan]" class="form-control form-control-sm" rows="2">${data ? (data.kompetensi_yang_dibutuhkan || '') : ''}</textarea>
                  </div>
                  <div class="col-12">
                    <label class="form-label small">Tahapan Seleksi</label>
                    <textarea name="detail_lowongan[${idx}][tahapan_seleksi]" class="form-control form-control-sm" rows="2">${data ? (data.tahapan_seleksi || '') : ''}</textarea>
                  </div>
                </div>
              </div>
            `;
            
            container.insertAdjacentHTML('beforeend', itemHtml);
            
            // Update remove button handlers
            document.querySelectorAll('.remove-lowongan-btn').forEach(btn => {
              btn.addEventListener('click', function() {
                const itemIndex = this.getAttribute('data-index');
                const item = container.querySelector(`[data-index="${itemIndex}"]`);
                if (item) {
                  item.remove();
                  // Renumber remaining items
                  renumberDetailLowongan();
                }
              });
            });
          }
          
          function renumberDetailLowongan() {
            const container = document.getElementById('detail_lowongan_container');
            const items = container.querySelectorAll('[data-index]');
            items.forEach((item, idx) => {
              const newIndex = idx;
              item.setAttribute('data-index', newIndex);
              item.querySelector('h6').textContent = `Lowongan #${newIndex + 1}`;
              
              // Update all input names
              item.querySelectorAll('input, textarea').forEach(input => {
                const name = input.getAttribute('name');
                if (name) {
                  const newName = name.replace(/\[(\d+)\]/, `[${newIndex}]`);
                  input.setAttribute('name', newName);
                }
              });
            });
          }
          
          // Add new lowongan button
          document.getElementById('add_detail_lowongan_btn').addEventListener('click', function() {
            addDetailLowonganItem();
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