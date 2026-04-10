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
    laporan_mitra VARCHAR(120) DEFAULT NULL,
    tindak_lanjut_tutup_lowongan TINYINT(1) NOT NULL DEFAULT 0,
    tindak_lanjut_tutup_akun_perusahaan TINYINT(1) NOT NULL DEFAULT 0,
    tindak_lanjut_lainnya_checked TINYINT(1) NOT NULL DEFAULT 0,
    tindak_lanjut_lainnya_text TEXT DEFAULT NULL,
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
$conn->query("ALTER TABLE job_hoax_reports ADD COLUMN IF NOT EXISTS laporan_mitra VARCHAR(120) DEFAULT NULL AFTER pelapor_email");
$conn->query("ALTER TABLE job_hoax_reports ADD COLUMN IF NOT EXISTS tindak_lanjut_tutup_lowongan TINYINT(1) NOT NULL DEFAULT 0 AFTER laporan_mitra");
$conn->query("ALTER TABLE job_hoax_reports ADD COLUMN IF NOT EXISTS tindak_lanjut_tutup_akun_perusahaan TINYINT(1) NOT NULL DEFAULT 0 AFTER tindak_lanjut_tutup_lowongan");
$conn->query("ALTER TABLE job_hoax_reports ADD COLUMN IF NOT EXISTS tindak_lanjut_lainnya_checked TINYINT(1) NOT NULL DEFAULT 0 AFTER tindak_lanjut_tutup_akun_perusahaan");
$conn->query("ALTER TABLE job_hoax_reports ADD COLUMN IF NOT EXISTS tindak_lanjut_lainnya_text TEXT DEFAULT NULL AFTER tindak_lanjut_lainnya_checked");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reportId = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;
    $action = trim((string)($_POST['action'] ?? ''));

    if ($reportId <= 0 || !in_array($action, ['approve', 'reject', 'edit', 'delete', 'tindak_lanjut'], true)) {
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
    } elseif ($action === 'tindak_lanjut') {
        $tutupLowongan = isset($_POST['tindak_lanjut_tutup_lowongan']) ? 1 : 0;
        $tutupAkun = isset($_POST['tindak_lanjut_tutup_akun_perusahaan']) ? 1 : 0;
        $lainnyaChecked = isset($_POST['tindak_lanjut_lainnya_checked']) ? 1 : 0;
        $lainnyaText = trim((string)($_POST['tindak_lanjut_lainnya_text'] ?? ''));

        if ($lainnyaChecked === 1 && $lainnyaText === '') {
            $_SESSION['error'] = 'Mohon isi penjelasan untuk opsi Lainnya.';
            header('Location: lapor_loker_reports');
            exit;
        }
        if ($lainnyaChecked !== 1) {
            $lainnyaText = null;
        }

        $stmt = $conn->prepare("UPDATE job_hoax_reports
            SET tindak_lanjut_tutup_lowongan = ?,
                tindak_lanjut_tutup_akun_perusahaan = ?,
                tindak_lanjut_lainnya_checked = ?,
                tindak_lanjut_lainnya_text = ?
            WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('iiisi', $tutupLowongan, $tutupAkun, $lainnyaChecked, $lainnyaText, $reportId);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Tindak lanjut berhasil disimpan.';
        } else {
            $_SESSION['error'] = 'Gagal menyimpan tindak lanjut: ' . $conn->error;
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
        $laporanMitra = trim((string)($_POST['laporan_mitra'] ?? ''));
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
            laporan_mitra = ?,
            status = ?,
            approved_at = CASE WHEN ? = 'approved' THEN NOW() ELSE NULL END,
            rejected_at = CASE WHEN ? = 'rejected' THEN NOW() ELSE NULL END
            WHERE id = ?");

        if ($stmt) {
            $stmt->bind_param(
                'ssssssssssssssssssi',
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
                $laporanMitra,
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

$platformFilter = trim((string)($_GET['platform_sumber'] ?? 'all'));
$mitraFilter = trim((string)($_GET['laporan_mitra'] ?? 'all'));

$platformOptions = [];
$resPlatform = $conn->query("SELECT DISTINCT platform_sumber FROM job_hoax_reports WHERE platform_sumber IS NOT NULL AND platform_sumber <> '' ORDER BY platform_sumber ASC");
if ($resPlatform) {
    while ($row = $resPlatform->fetch_assoc()) {
        $platformOptions[] = (string) $row['platform_sumber'];
    }
}
if ($platformFilter !== 'all' && !in_array($platformFilter, $platformOptions, true)) {
    $platformFilter = 'all';
}

$mitraOptions = [];
$resMitra = $conn->query("SELECT DISTINCT laporan_mitra FROM job_hoax_reports WHERE laporan_mitra IS NOT NULL AND laporan_mitra <> '' ORDER BY laporan_mitra ASC");
if ($resMitra) {
    while ($row = $resMitra->fetch_assoc()) {
        $mitraOptions[] = (string) $row['laporan_mitra'];
    }
}
if ($mitraFilter !== 'all' && !in_array($mitraFilter, $mitraOptions, true)) {
    $mitraFilter = 'all';
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
    laporan_mitra,
    tindak_lanjut_tutup_lowongan,
    tindak_lanjut_tutup_akun_perusahaan,
    tindak_lanjut_lainnya_checked,
    tindak_lanjut_lainnya_text,
    status,
    approved_at,
    rejected_at,
    created_at,
    updated_at
FROM job_hoax_reports";

$params = [];
$types = '';
$conditions = [];

if ($statusFilter !== 'all') {
    $conditions[] = "status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}
if ($platformFilter !== 'all') {
    $conditions[] = "platform_sumber = ?";
    $params[] = $platformFilter;
    $types .= 's';
}
if ($mitraFilter !== 'all') {
    $conditions[] = "laporan_mitra = ?";
    $params[] = $mitraFilter;
    $types .= 's';
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " ORDER BY created_at DESC, id DESC";
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

$summary = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
];
$resSummary = $conn->query("SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) AS approved,
    SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) AS rejected
FROM job_hoax_reports");
if ($resSummary && $rowSummary = $resSummary->fetch_assoc()) {
    $summary['total'] = (int) ($rowSummary['total'] ?? 0);
    $summary['pending'] = (int) ($rowSummary['pending'] ?? 0);
    $summary['approved'] = (int) ($rowSummary['approved'] ?? 0);
    $summary['rejected'] = (int) ($rowSummary['rejected'] ?? 0);
}
$filteredCount = count($reports);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lapor Loker Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .lapor-loker-edit-modal .modal-content {
            max-height: calc(100vh - 2rem);
            overflow: hidden;
        }

        .lapor-loker-edit-modal form {
            display: flex;
            flex-direction: column;
            height: 100%;
            min-height: 0;
        }

        .lapor-loker-edit-modal .modal-body {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
        }
    </style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container mt-4 mb-5">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
        <h3 class="mb-0">Lapor Loker Reports</h3>
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-auto">
                <label for="status" class="form-label mb-0">Status</label>
                <select name="status" id="status" class="form-select">
                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>Semua</option>
                    <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            <div class="col-auto">
                <label for="platform_sumber" class="form-label mb-0">Platform Sumber</label>
                <select name="platform_sumber" id="platform_sumber" class="form-select">
                    <option value="all" <?php echo $platformFilter === 'all' ? 'selected' : ''; ?>>Semua</option>
                    <?php foreach ($platformOptions as $platformOption): ?>
                        <option value="<?php echo htmlspecialchars($platformOption); ?>" <?php echo $platformFilter === $platformOption ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($platformOption); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label for="laporan_mitra" class="form-label mb-0">Laporan Mitra</label>
                <select name="laporan_mitra" id="laporan_mitra" class="form-select">
                    <option value="all" <?php echo $mitraFilter === 'all' ? 'selected' : ''; ?>>Semua</option>
                    <?php foreach ($mitraOptions as $mitraOption): ?>
                        <option value="<?php echo htmlspecialchars($mitraOption); ?>" <?php echo $mitraFilter === $mitraOption ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($mitraOption); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Terapkan</button>
                <a href="lapor_loker_reports" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-lg-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Laporan</div>
                    <div class="h4 mb-0"><?php echo number_format($summary['total']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Pending</div>
                    <div class="h4 mb-0 text-warning"><?php echo number_format($summary['pending']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Approved</div>
                    <div class="h4 mb-0 text-success"><?php echo number_format($summary['approved']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-2">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Rejected</div>
                    <div class="h4 mb-0 text-danger"><?php echo number_format($summary['rejected']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Data Ditampilkan</div>
                    <div class="h4 mb-0"><?php echo number_format($filteredCount); ?></div>
                </div>
            </div>
        </div>
    </div>

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
                            <th>Laporan Mitra</th>
                            <th>Status</th>
                            <th>Tindak Lanjut</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reports)): ?>
                            <tr>
                                <td colspan="17" class="text-center text-muted py-4">Belum ada data laporan.</td>
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
                                    <td><?php echo htmlspecialchars((string)($r['laporan_mitra'] ?: '-')); ?></td>
                                    <td><span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars((string)$r['status']); ?></span></td>
                                    <td style="min-width: 240px;">
                                        <?php
                                            $followUps = [];
                                            if ((int)($r['tindak_lanjut_tutup_lowongan'] ?? 0) === 1) {
                                                $followUps[] = 'Menutup Lowongan Kerja';
                                            }
                                            if ((int)($r['tindak_lanjut_tutup_akun_perusahaan'] ?? 0) === 1) {
                                                $followUps[] = 'Menutup Akun Perusahaan';
                                            }
                                            if ((int)($r['tindak_lanjut_lainnya_checked'] ?? 0) === 1) {
                                                $other = trim((string)($r['tindak_lanjut_lainnya_text'] ?? ''));
                                                $followUps[] = $other !== '' ? ('Lainnya: ' . $other) : 'Lainnya';
                                            }
                                        ?>
                                        <?php if (empty($followUps)): ?>
                                            -
                                        <?php else: ?>
                                            <?php echo nl2br(htmlspecialchars(implode("\n", $followUps))); ?>
                                        <?php endif; ?>
                                    </td>
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
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-info"
                                                data-bs-toggle="modal"
                                                data-bs-target="#tindakLanjutModal<?php echo (int)$r['id']; ?>"
                                            >
                                                Tindak Lanjut
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
                            <div class="col-12"><strong>Kronologi:</strong><br><?php echo nl2br(htmlspecialchars((string)($r['kronologi'] ?? '-'))); ?></div>
                            <div class="col-md-6"><strong>Nama Pelapor:</strong><br><?php echo htmlspecialchars((string)($r['pelapor_nama'] ?? '-')); ?></div>
                            <div class="col-md-6"><strong>Email Pelapor:</strong><br><?php echo htmlspecialchars((string)($r['pelapor_email'] ?? '-')); ?></div>
                            <div class="col-md-6"><strong>Laporan Mitra:</strong><br><?php echo htmlspecialchars((string)($r['laporan_mitra'] ?? '-')); ?></div>
                            <div class="col-12">
                                <strong>Tindak Lanjut:</strong><br>
                                <?php
                                    $detailFollowUps = [];
                                    if ((int)($r['tindak_lanjut_tutup_lowongan'] ?? 0) === 1) {
                                        $detailFollowUps[] = 'Menutup Lowongan Kerja';
                                    }
                                    if ((int)($r['tindak_lanjut_tutup_akun_perusahaan'] ?? 0) === 1) {
                                        $detailFollowUps[] = 'Menutup Akun Perusahaan';
                                    }
                                    if ((int)($r['tindak_lanjut_lainnya_checked'] ?? 0) === 1) {
                                        $detailOther = trim((string)($r['tindak_lanjut_lainnya_text'] ?? ''));
                                        $detailFollowUps[] = $detailOther !== '' ? ('Lainnya: ' . $detailOther) : 'Lainnya';
                                    }
                                ?>
                                <?php echo !empty($detailFollowUps) ? nl2br(htmlspecialchars(implode("\n", $detailFollowUps))) : '-'; ?>
                            </div>
                            <div class="col-md-6"><strong>Status:</strong><br><?php echo htmlspecialchars((string)$r['status']); ?></div>
                            <div class="col-md-6"><strong>Waktu Dilaporkan:</strong><br><?php echo htmlspecialchars((string)$r['created_at']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="tindakLanjutModal<?php echo (int)$r['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <form method="POST">
                        <input type="hidden" name="action" value="tindak_lanjut">
                        <input type="hidden" name="report_id" value="<?php echo (int)$r['id']; ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">Tindak Lanjut Laporan #<?php echo (int)$r['id']; ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="tindak_lanjut_tutup_lowongan_<?php echo (int)$r['id']; ?>" name="tindak_lanjut_tutup_lowongan" <?php echo ((int)($r['tindak_lanjut_tutup_lowongan'] ?? 0) === 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="tindak_lanjut_tutup_lowongan_<?php echo (int)$r['id']; ?>">
                                    Menutup Lowongan Kerja
                                </label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="tindak_lanjut_tutup_akun_<?php echo (int)$r['id']; ?>" name="tindak_lanjut_tutup_akun_perusahaan" <?php echo ((int)($r['tindak_lanjut_tutup_akun_perusahaan'] ?? 0) === 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="tindak_lanjut_tutup_akun_<?php echo (int)$r['id']; ?>">
                                    Menutup Akun Perusahaan
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input js-tindak-lanjut-lainnya-toggle" type="checkbox" id="tindak_lanjut_lainnya_checked_<?php echo (int)$r['id']; ?>" name="tindak_lanjut_lainnya_checked" data-target="#tindak_lanjut_lainnya_text_<?php echo (int)$r['id']; ?>" <?php echo ((int)($r['tindak_lanjut_lainnya_checked'] ?? 0) === 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="tindak_lanjut_lainnya_checked_<?php echo (int)$r['id']; ?>">
                                    Lainnya
                                </label>
                            </div>
                            <div>
                                <label class="form-label" for="tindak_lanjut_lainnya_text_<?php echo (int)$r['id']; ?>">Penjelasan Lainnya</label>
                                <textarea class="form-control js-tindak-lanjut-lainnya-text" id="tindak_lanjut_lainnya_text_<?php echo (int)$r['id']; ?>" name="tindak_lanjut_lainnya_text" rows="3" <?php echo ((int)($r['tindak_lanjut_lainnya_checked'] ?? 0) === 1) ? '' : 'disabled'; ?>><?php echo htmlspecialchars((string)($r['tindak_lanjut_lainnya_text'] ?? '')); ?></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-info text-white">Simpan Tindak Lanjut</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editModal<?php echo (int)$r['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable lapor-loker-edit-modal">
                <div class="modal-content">
                    <form method="POST" class="d-flex flex-column h-100">
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
                                    <label class="form-label">Kronologi</label>
                                    <textarea class="form-control" name="kronologi" rows="4"><?php echo htmlspecialchars((string)($r['kronologi'] ?? '')); ?></textarea>
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
                                    <label class="form-label">Laporan Mitra</label>
                                    <input type="text" class="form-control" name="laporan_mitra" value="<?php echo htmlspecialchars((string)($r['laporan_mitra'] ?? '')); ?>" placeholder="Nama Job Portal">
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
<script>
    (function () {
        var toggles = document.querySelectorAll('.js-tindak-lanjut-lainnya-toggle');
        toggles.forEach(function (checkbox) {
            var targetSelector = checkbox.getAttribute('data-target');
            if (!targetSelector) {
                return;
            }

            var textarea = document.querySelector(targetSelector);
            if (!textarea) {
                return;
            }

            var sync = function () {
                var enabled = checkbox.checked;
                textarea.disabled = !enabled;
                textarea.required = enabled;
                if (!enabled) {
                    textarea.value = '';
                }
            };

            checkbox.addEventListener('change', sync);
            sync();
        });
    })();
</script>
</body>
</html>
<?php $conn->close(); ?>
