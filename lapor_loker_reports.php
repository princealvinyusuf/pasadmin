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

    if ($reportId <= 0 || !in_array($action, ['approve', 'reject', 'edit', 'delete'], true)) {
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
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE job_hoax_reports SET status = 'rejected', rejected_at = NOW(), approved_at = NULL WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $reportId);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Laporan berhasil ditolak.';
        } else {
            $_SESSION['error'] = 'Gagal memproses penolakan: ' . $conn->error;
        }
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM job_hoax_reports WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $reportId);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Laporan berhasil dihapus.';
        } else {
            $_SESSION['error'] = 'Gagal menghapus laporan: ' . $conn->error;
        }
    } else {
        $emailTerdugaPelaku = trim((string)($_POST['email_terduga_pelaku'] ?? ''));
        $tanggalTerdeteksi = trim((string)($_POST['tanggal_terdeteksi'] ?? ''));
        $namaPerusahaan = trim((string)($_POST['nama_perusahaan_digunakan'] ?? ''));
        $namaHr = trim((string)($_POST['nama_hr_digunakan'] ?? ''));
        $provinsi = trim((string)($_POST['provinsi'] ?? ''));
        $kota = trim((string)($_POST['kota'] ?? ''));
        $nomorKontak = trim((string)($_POST['nomor_kontak_terduga'] ?? ''));
        $platformSumber = trim((string)($_POST['platform_sumber'] ?? ''));
        $tautanInformasi = trim((string)($_POST['tautan_informasi'] ?? ''));
        $buktiPath = trim((string)($_POST['bukti_pendukung_path'] ?? ''));
        $buktiNama = trim((string)($_POST['bukti_pendukung_nama'] ?? ''));
        $kronologi = trim((string)($_POST['kronologi'] ?? ''));
        $pelaporNama = trim((string)($_POST['pelapor_nama'] ?? ''));
        $pelaporEmail = trim((string)($_POST['pelapor_email'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'pending'));

        if (
            $emailTerdugaPelaku === '' ||
            $tanggalTerdeteksi === '' ||
            $namaPerusahaan === '' ||
            $namaHr === '' ||
            $provinsi === '' ||
            $kota === '' ||
            $nomorKontak === '' ||
            $platformSumber === '' ||
            $tautanInformasi === '' ||
            $pelaporNama === '' ||
            $pelaporEmail === ''
        ) {
            $_SESSION['error'] = 'Gagal update: field wajib tidak boleh kosong.';
            header('Location: lapor_loker_reports');
            exit;
        }

        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $_SESSION['error'] = 'Status tidak valid.';
            header('Location: lapor_loker_reports');
            exit;
        }

        $stmt = $conn->prepare("UPDATE job_hoax_reports SET
            email_terduga_pelaku = ?,
            tanggal_terdeteksi = ?,
            nama_perusahaan_digunakan = ?,
            nama_hr_digunakan = ?,
            provinsi = ?,
            kota = ?,
            nomor_kontak_terduga = ?,
            platform_sumber = ?,
            tautan_informasi = ?,
            bukti_pendukung_path = ?,
            bukti_pendukung_nama = ?,
            kronologi = ?,
            pelapor_nama = ?,
            pelapor_email = ?,
            status = ?,
            approved_at = CASE WHEN ? = 'approved' THEN NOW() ELSE NULL END,
            rejected_at = CASE WHEN ? = 'rejected' THEN NOW() ELSE NULL END
            WHERE id = ?");

        if ($stmt) {
            $stmt->bind_param(
                'sssssssssssssssssi',
                $emailTerdugaPelaku,
                $tanggalTerdeteksi,
                $namaPerusahaan,
                $namaHr,
                $provinsi,
                $kota,
                $nomorKontak,
                $platformSumber,
                $tautanInformasi,
                $buktiPath,
                $buktiNama,
                $kronologi,
                $pelaporNama,
                $pelaporEmail,
                $status,
                $status,
                $status,
                $reportId
            );
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Laporan berhasil diperbarui.';
        } else {
            $_SESSION['error'] = 'Gagal memperbarui laporan: ' . $conn->error;
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
    approved_at,
    rejected_at,
    created_at,
    updated_at
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
                            <th>Nomor Kontak</th>
                            <th>Platform Sumber</th>
                            <th>Tautan Informasi</th>
                            <th>Bukti Pendukung</th>
                            <th>Nama Pelapor</th>
                            <th>Email Pelapor</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reports)): ?>
                            <tr>
                                <td colspan="15" class="text-center text-muted py-4">Belum ada data laporan.</td>
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
                                    <td><?php echo htmlspecialchars((string)$r['nomor_kontak_terduga']); ?></td>
                                    <td><?php echo htmlspecialchars((string)$r['platform_sumber']); ?></td>
                                    <td style="min-width: 180px;">
                                        <a href="<?php echo htmlspecialchars((string)$r['tautan_informasi']); ?>" target="_blank" rel="noopener noreferrer">Buka Tautan</a>
                                    </td>
                                    <td style="min-width: 180px;">
                                        <?php if (!empty($r['bukti_pendukung_path'])): ?>
                                            <a href="/storage/<?php echo htmlspecialchars((string)$r['bukti_pendukung_path']); ?>" target="_blank" rel="noopener noreferrer">
                                                <?php echo htmlspecialchars((string)($r['bukti_pendukung_nama'] ?: basename((string)$r['bukti_pendukung_path']))); ?>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars((string)$r['pelapor_nama']); ?></td>
                                    <td><?php echo htmlspecialchars((string)$r['pelapor_email']); ?></td>
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
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editModal<?php echo (int)$r['id']; ?>"
                                            >
                                                Edit
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
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus laporan ini?');">
                                                <input type="hidden" name="report_id" value="<?php echo (int)$r['id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
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
                            <div class="col-md-6"><strong>Nama Pelapor:</strong><br><?php echo htmlspecialchars((string)($r['pelapor_nama'] ?? '-')); ?></div>
                            <div class="col-md-6"><strong>Email Pelapor:</strong><br><?php echo htmlspecialchars((string)($r['pelapor_email'] ?? '-')); ?></div>
                            <div class="col-md-6"><strong>Status:</strong><br><?php echo htmlspecialchars((string)$r['status']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editModal<?php echo (int)$r['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <form method="POST">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="report_id" value="<?php echo (int)$r['id']; ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Laporan #<?php echo (int)$r['id']; ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Email Terduga Pelaku</label>
                                    <input type="email" class="form-control" name="email_terduga_pelaku" required value="<?php echo htmlspecialchars((string)$r['email_terduga_pelaku']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tanggal Terdeteksi</label>
                                    <input type="date" class="form-control" name="tanggal_terdeteksi" required value="<?php echo htmlspecialchars((string)$r['tanggal_terdeteksi']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nama Perusahaan</label>
                                    <input type="text" class="form-control" name="nama_perusahaan_digunakan" required value="<?php echo htmlspecialchars((string)$r['nama_perusahaan_digunakan']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nama HR</label>
                                    <input type="text" class="form-control" name="nama_hr_digunakan" required value="<?php echo htmlspecialchars((string)$r['nama_hr_digunakan']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Provinsi</label>
                                    <input type="text" class="form-control" name="provinsi" required value="<?php echo htmlspecialchars((string)$r['provinsi']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Kota</label>
                                    <input type="text" class="form-control" name="kota" required value="<?php echo htmlspecialchars((string)$r['kota']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nomor Kontak</label>
                                    <input type="text" class="form-control" name="nomor_kontak_terduga" required value="<?php echo htmlspecialchars((string)$r['nomor_kontak_terduga']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Platform Sumber</label>
                                    <input type="text" class="form-control" name="platform_sumber" required value="<?php echo htmlspecialchars((string)$r['platform_sumber']); ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Tautan Informasi</label>
                                    <input type="url" class="form-control" name="tautan_informasi" required value="<?php echo htmlspecialchars((string)$r['tautan_informasi']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Bukti Path</label>
                                    <input type="text" class="form-control" name="bukti_pendukung_path" value="<?php echo htmlspecialchars((string)($r['bukti_pendukung_path'] ?? '')); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Bukti Nama</label>
                                    <input type="text" class="form-control" name="bukti_pendukung_nama" value="<?php echo htmlspecialchars((string)($r['bukti_pendukung_nama'] ?? '')); ?>">
                                </div>
                                <div class="col-12">
                                    <input type="hidden" name="kronologi" value="<?php echo htmlspecialchars((string)($r['kronologi'] ?? '')); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Nama Pelapor</label>
                                    <input type="text" class="form-control" name="pelapor_nama" required value="<?php echo htmlspecialchars((string)$r['pelapor_nama']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email Pelapor</label>
                                    <input type="email" class="form-control" name="pelapor_email" required value="<?php echo htmlspecialchars((string)$r['pelapor_email']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status" required>
                                        <option value="pending" <?php echo $r['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="approved" <?php echo $r['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                        <option value="rejected" <?php echo $r['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
