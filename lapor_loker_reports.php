<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

if (!(current_user_can('lapor_loker_manage') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$conn = new mysqli('localhost', 'root', '', 'paskerid_db_prod');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$conn->query("CREATE TABLE IF NOT EXISTS job_hoax_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email_terduga_pelaku VARCHAR(255) NOT NULL,
    tanggal_terdeteksi DATE NOT NULL,
    nama_perusahaan_digunakan VARCHAR(255) NOT NULL,
    nama_hr_digunakan VARCHAR(255) NOT NULL,
    provinsi VARCHAR(150) NOT NULL,
    kota VARCHAR(150) NOT NULL,
    nomor_kontak_terduga VARCHAR(60) NOT NULL,
    platform_sumber VARCHAR(120) NOT NULL,
    tautan_informasi VARCHAR(500) NOT NULL,
    bukti_pendukung_path VARCHAR(500) DEFAULT NULL,
    bukti_pendukung_nama VARCHAR(255) DEFAULT NULL,
    kronologi TEXT DEFAULT NULL,
    pelapor_nama VARCHAR(120) NOT NULL,
    pelapor_email VARCHAR(255) NOT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    approved_at TIMESTAMP NULL DEFAULT NULL,
    rejected_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_status (status),
    KEY idx_tanggal_terdeteksi (tanggal_terdeteksi),
    KEY idx_provinsi_kota (provinsi, kota)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("ALTER TABLE job_hoax_reports ADD COLUMN IF NOT EXISTS bukti_pendukung_path VARCHAR(500) DEFAULT NULL AFTER tautan_informasi");
$conn->query("ALTER TABLE job_hoax_reports ADD COLUMN IF NOT EXISTS bukti_pendukung_nama VARCHAR(255) DEFAULT NULL AFTER bukti_pendukung_path");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reportId = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;
    $action = trim((string)($_POST['action'] ?? ''));

    if ($reportId <= 0 || !in_array($action, ['approve', 'reject'], true)) {
        $_SESSION['error'] = 'Aksi tidak valid.';
        header('Location: lapor_loker_reports');
        exit;
    }

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE job_hoax_reports SET status = 'approved', approved_at = NOW(), rejected_at = NULL WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $reportId);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Laporan berhasil disetujui dan ditampilkan di UI publik.';
        } else {
            $_SESSION['error'] = 'Gagal memproses persetujuan: ' . $conn->error;
        }
    } else {
        $stmt = $conn->prepare("UPDATE job_hoax_reports SET status = 'rejected', rejected_at = NOW(), approved_at = NULL WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $reportId);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Laporan berhasil ditolak.';
        } else {
            $_SESSION['error'] = 'Gagal memproses penolakan: ' . $conn->error;
        }
    }

    header('Location: lapor_loker_reports');
    exit;
}

$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$allowedFilters = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($statusFilter, $allowedFilters, true)) {
    $statusFilter = 'all';
}

$query = "SELECT
    id,
    email_terduga_pelaku,
    tanggal_terdeteksi,
    nama_perusahaan_digunakan,
    nama_hr_digunakan,
    provinsi,
    kota,
    nomor_kontak_terduga,
    platform_sumber,
    tautan_informasi,
    bukti_pendukung_path,
    bukti_pendukung_nama,
    kronologi,
    pelapor_nama,
    pelapor_email,
    status,
    created_at
FROM job_hoax_reports";

$params = [];
$types = '';
if ($statusFilter !== 'all') {
    $query .= " WHERE status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

$query .= " ORDER BY created_at DESC, id DESC LIMIT 300";
$stmt = $conn->prepare($query);
if ($stmt && !empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$reports = [];
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lapor Loker Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container mt-4 mb-5">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
        <h3 class="mb-0">Lapor Loker Reports</h3>
        <form method="GET" class="d-flex align-items-center gap-2">
            <label for="status" class="form-label mb-0">Filter Status</label>
            <select name="status" id="status" class="form-select" onchange="this.form.submit()">
                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>Semua</option>
                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
        </form>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Email Terduga Pelaku</th>
                            <th>Tanggal Terdeteksi</th>
                            <th>Nama Perusahaan</th>
                            <th>Nama HR</th>
                            <th>Provinsi</th>
                            <th>Kota</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reports)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">Belum ada data laporan.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reports as $r): ?>
                                <?php
                                    $badge = 'secondary';
                                    if ($r['status'] === 'approved') {
                                        $badge = 'success';
                                    } elseif ($r['status'] === 'rejected') {
                                        $badge = 'danger';
                                    } elseif ($r['status'] === 'pending') {
                                        $badge = 'warning text-dark';
                                    }
                                ?>
                                <tr>
                                    <td><?php echo (int)$r['id']; ?></td>
                                    <td><?php echo htmlspecialchars((string)$r['email_terduga_pelaku']); ?></td>
                                    <td><?php echo htmlspecialchars((string)$r['tanggal_terdeteksi']); ?></td>
                                    <td><?php echo htmlspecialchars((string)$r['nama_perusahaan_digunakan']); ?></td>
                                    <td><?php echo htmlspecialchars((string)$r['nama_hr_digunakan']); ?></td>
                                    <td><?php echo htmlspecialchars((string)$r['provinsi']); ?></td>
                                    <td><?php echo htmlspecialchars((string)$r['kota']); ?></td>
                                    <td><span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars((string)$r['status']); ?></span></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-dark"
                                                data-bs-toggle="modal"
                                                data-bs-target="#detailModal<?php echo (int)$r['id']; ?>"
                                            >
                                                Detail
                                            </button>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="report_id" value="<?php echo (int)$r['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="report_id" value="<?php echo (int)$r['id']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
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

    <?php foreach ($reports as $r): ?>
        <div class="modal fade" id="detailModal<?php echo (int)$r['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Detail Laporan #<?php echo (int)$r['id']; ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6"><strong>Email Terduga Pelaku:</strong><br><?php echo htmlspecialchars((string)$r['email_terduga_pelaku']); ?></div>
                            <div class="col-md-6"><strong>Tanggal Terdeteksi:</strong><br><?php echo htmlspecialchars((string)$r['tanggal_terdeteksi']); ?></div>
                            <div class="col-md-6"><strong>Perusahaan Digunakan:</strong><br><?php echo htmlspecialchars((string)$r['nama_perusahaan_digunakan']); ?></div>
                            <div class="col-md-6"><strong>Nama HR Digunakan:</strong><br><?php echo htmlspecialchars((string)$r['nama_hr_digunakan']); ?></div>
                            <div class="col-md-6"><strong>Provinsi:</strong><br><?php echo htmlspecialchars((string)$r['provinsi']); ?></div>
                            <div class="col-md-6"><strong>Kota:</strong><br><?php echo htmlspecialchars((string)$r['kota']); ?></div>
                            <div class="col-md-6"><strong>Kontak Terduga:</strong><br><?php echo htmlspecialchars((string)($r['nomor_kontak_terduga'] ?? '-')); ?></div>
                            <div class="col-md-6"><strong>Platform Sumber:</strong><br><?php echo htmlspecialchars((string)($r['platform_sumber'] ?? '-')); ?></div>
                            <div class="col-12">
                                <strong>Tautan Informasi:</strong><br>
                                <?php if (!empty($r['tautan_informasi'])): ?>
                                    <a href="<?php echo htmlspecialchars((string)$r['tautan_informasi']); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars((string)$r['tautan_informasi']); ?></a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </div>
                            <div class="col-12">
                                <strong>Lampiran Bukti Pendukung:</strong><br>
                                <?php if (!empty($r['bukti_pendukung_path'])): ?>
                                    <a href="/storage/<?php echo htmlspecialchars((string)$r['bukti_pendukung_path']); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php echo htmlspecialchars((string)($r['bukti_pendukung_nama'] ?: basename((string)$r['bukti_pendukung_path']))); ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </div>
                            <div class="col-12"><strong>Kronologi:</strong><br><?php echo nl2br(htmlspecialchars((string)($r['kronologi'] ?? '-'))); ?></div>
                            <div class="col-md-6"><strong>Nama Pelapor:</strong><br><?php echo htmlspecialchars((string)($r['pelapor_nama'] ?? '-')); ?></div>
                            <div class="col-md-6"><strong>Email Pelapor:</strong><br><?php echo htmlspecialchars((string)($r['pelapor_email'] ?? '-')); ?></div>
                            <div class="col-md-6"><strong>Status:</strong><br><?php echo htmlspecialchars((string)$r['status']); ?></div>
                            <div class="col-md-6"><strong>Waktu Dilaporkan:</strong><br><?php echo htmlspecialchars((string)$r['created_at']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
