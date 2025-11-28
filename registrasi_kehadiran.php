<?php
// Registrasi Kehadiran CRUD
// Uses paskerid_db_prod database similar to other settings pages
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'paskerid_db_prod';

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

// Permission gate
if (!(current_user_can('registrasi_kehadiran_manage') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Ensure table exists
$conn->query("
CREATE TABLE IF NOT EXISTS registrasi_kehadiran (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    jabatan VARCHAR(255) DEFAULT NULL,
    asal_instansi VARCHAR(255) DEFAULT NULL,
    nama_instansi VARCHAR(255) DEFAULT NULL,
    nomor_handphone VARCHAR(50) DEFAULT NULL,
    lokasi VARCHAR(255) DEFAULT NULL,
    waktu_kedatangan DATETIME DEFAULT NULL,
    kehadiran ENUM('YA','TIDAK') NOT NULL DEFAULT 'YA',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email_created (email, created_at),
    INDEX idx_lokasi_created (lokasi, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Ensure new column exists on existing installations
$chkCol = $conn->query("SHOW COLUMNS FROM registrasi_kehadiran LIKE 'waktu_kedatangan'");
if ($chkCol && $chkCol->num_rows === 0) {
    $conn->query("ALTER TABLE registrasi_kehadiran ADD COLUMN waktu_kedatangan DATETIME NULL AFTER lokasi");
}

// Simple helpers
function rk_post($key, $default = '')
{
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

function rk_parse_datetime_local(?string $val): ?string
{
    if ($val === null) { return null; }
    $val = trim($val);
    if ($val === '') { return null; }
    // Convert HTML datetime-local (YYYY-MM-DDTHH:MM) to MySQL DATETIME
    $val = str_replace('T', ' ', $val);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $val)) {
        $val .= ':00';
    }
    return $val;
}

// Handle Create
if (isset($_POST['add'])) {
    $nama            = rk_post('nama');
    $email           = rk_post('email');
    $jabatan         = rk_post('jabatan');
    $asal_instansi   = rk_post('asal_instansi');
    $nama_instansi   = rk_post('nama_instansi');
    $nomor_handphone = rk_post('nomor_handphone');
    $lokasi          = rk_post('lokasi');
    $waktu_kedatangan = rk_parse_datetime_local($_POST['waktu_kedatangan'] ?? null);
    $kehadiran       = rk_post('kehadiran', 'YA');
    if ($kehadiran !== 'YA' && $kehadiran !== 'TIDAK') {
        $kehadiran = 'YA';
    }

    $stmt = $conn->prepare("
        INSERT INTO registrasi_kehadiran
        (nama, email, jabatan, asal_instansi, nama_instansi, nomor_handphone, lokasi, waktu_kedatangan, kehadiran, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,?,NOW(),NOW())
    ");
    $stmt->bind_param(
        'sssssssss',
        $nama,
        $email,
        $jabatan,
        $asal_instansi,
        $nama_instansi,
        $nomor_handphone,
        $lokasi,
        $waktu_kedatangan,
        $kehadiran
    );
    $stmt->execute();
    $stmt->close();
    header('Location: registrasi_kehadiran.php');
    exit;
}

// Handle Update
if (isset($_POST['update'])) {
    $id              = intval(rk_post('id'));
    $nama            = rk_post('nama');
    $email           = rk_post('email');
    $jabatan         = rk_post('jabatan');
    $asal_instansi   = rk_post('asal_instansi');
    $nama_instansi   = rk_post('nama_instansi');
    $nomor_handphone = rk_post('nomor_handphone');
    $lokasi          = rk_post('lokasi');
    $waktu_kedatangan = rk_parse_datetime_local($_POST['waktu_kedatangan'] ?? null);
    $kehadiran       = rk_post('kehadiran', 'YA');
    if ($kehadiran !== 'YA' && $kehadiran !== 'TIDAK') {
        $kehadiran = 'YA';
    }

    $stmt = $conn->prepare("
        UPDATE registrasi_kehadiran
        SET nama=?, email=?, jabatan=?, asal_instansi=?, nama_instansi=?,
            nomor_handphone=?, lokasi=?, waktu_kedatangan=?, kehadiran=?, updated_at=NOW()
        WHERE id=?
    ");
    $stmt->bind_param(
        'sssssssssi',
        $nama,
        $email,
        $jabatan,
        $asal_instansi,
        $nama_instansi,
        $nomor_handphone,
        $lokasi,
        $waktu_kedatangan,
        $kehadiran,
        $id
    );
    $stmt->execute();
    $stmt->close();
    header('Location: registrasi_kehadiran.php');
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare('DELETE FROM registrasi_kehadiran WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    header('Location: registrasi_kehadiran.php');
    exit;
}

// Handle Edit (fetch single row)
$edit_row = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare('SELECT * FROM registrasi_kehadiran WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_row = $result->fetch_assoc();
    $stmt->close();
}

// Global statistics (all data, not affected by search filter)
$totalAll   = 0;
$totalYa    = 0;
$totalTidak = 0;
$statRes = $conn->query("SELECT 
    COUNT(*) AS total,
    SUM(kehadiran = 'YA') AS total_ya,
    SUM(kehadiran = 'TIDAK') AS total_tidak
FROM registrasi_kehadiran");
if ($statRes && $statRow = $statRes->fetch_assoc()) {
    $totalAll   = (int)($statRow['total'] ?? 0);
    $totalYa    = (int)($statRow['total_ya'] ?? 0);
    $totalTidak = (int)($statRow['total_tidak'] ?? 0);
}

// Search handling
$search      = isset($_GET['q']) ? trim($_GET['q']) : '';
$filterKehad = isset($_GET['f_kehadiran']) ? trim($_GET['f_kehadiran']) : '';
if ($filterKehad !== 'YA' && $filterKehad !== 'TIDAK') {
    $filterKehad = '';
}

// Build query with optional filters (newest first)
$baseSql = 'FROM registrasi_kehadiran WHERE 1=1';
$params  = [];
$types   = '';

if ($search !== '') {
    $baseSql .= ' AND (nama LIKE ? OR email LIKE ? OR jabatan LIKE ? OR asal_instansi LIKE ? OR nama_instansi LIKE ? OR lokasi LIKE ? OR nomor_handphone LIKE ?)';
    $like = '%' . $search . '%';
    // 7 placeholders
    for ($i = 0; $i < 7; $i++) {
        $params[] = $like;
        $types   .= 's';
    }
}

if ($filterKehad !== '') {
    $baseSql .= ' AND kehadiran = ?';
    $params[] = $filterKehad;
    $types   .= 's';
}

// Fetch rows
$sqlList = 'SELECT * ' . $baseSql . ' ORDER BY created_at DESC, id DESC';
if ($types !== '') {
    $stmtList = $conn->prepare($sqlList);
    $stmtList->bind_param($types, ...$params);
    $stmtList->execute();
    $rows = $stmtList->get_result();
} else {
    $rows = $conn->query('SELECT * FROM registrasi_kehadiran ORDER BY created_at DESC, id DESC');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Kehadiran</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f7fb;
            color: #1f2933;
        }
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            margin-top: 16px;
        }
        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .page-subtitle {
            margin: 0;
            font-size: 0.95rem;
            color: #6b7280;
        }
        .card-modern {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
            overflow: hidden;
            background: #ffffff;
        }
        .card-modern-header {
            padding: 1.25rem 1.5rem 0.75rem 1.5rem;
            border-bottom: 1px solid rgba(148, 163, 184, 0.25);
            background: linear-gradient(120deg, #2563eb0d, #22c55e0d);
        }
        .card-modern-body {
            padding: 1.25rem 1.5rem 1.5rem 1.5rem;
        }
        .form-label {
            font-weight: 500;
            color: #111827;
        }
        .form-control, .form-select {
            border-radius: 0.5rem;
            border-color: #d1d5db;
            font-size: 0.95rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 1px rgba(37, 99, 235, 0.15);
        }
        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border: none;
            border-radius: 0.5rem;
            font-weight: 500;
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.35);
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            box-shadow: 0 10px 24px rgba(30, 64, 175, 0.45);
        }
        .btn-outline-secondary {
            border-radius: 0.5rem;
        }
        .table-modern {
            border-radius: 0.75rem;
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.35);
            background: #ffffff;
        }
        .table-modern thead {
            background: linear-gradient(120deg, #eff6ff, #f0fdf4);
        }
        .table-modern thead th {
            border-bottom: none;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #4b5563;
        }
        .table-modern tbody tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .table-modern tbody tr:hover {
            background-color: #e5edff;
        }
        .badge-ya {
            background-color: #22c55e1a;
            color: #15803d;
            border-radius: 999px;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-tidak {
            background-color: #fee2e2;
            color: #b91c1c;
            border-radius: 999px;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-lokasi {
            background-color: #e0f2fe;
            color: #0369a1;
            border-radius: 999px;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .actions .btn-sm {
            border-radius: 999px;
            padding-inline: 0.75rem;
            font-size: 0.78rem;
        }
        .stats-card {
            border-radius: 0.9rem;
            padding: 1rem 1.2rem;
            background: linear-gradient(135deg, #eff6ff, #eef2ff);
            border: 1px solid rgba(148, 163, 184, 0.35);
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.06);
            display: flex;
            align-items: center;
            gap: 0.9rem;
        }
        .stats-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #2563eb15;
            color: #1d4ed8;
        }
        .stats-card-title {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #6b7280;
            margin-bottom: 0.1rem;
        }
        .stats-card-value {
            font-size: 1.3rem;
            font-weight: 600;
            color: #111827;
        }
        .stats-card-sub {
            font-size: 0.8rem;
            color: #6b7280;
        }
        .stats-card.success {
            background: linear-gradient(135deg, #ecfdf3, #dcfce7);
        }
        .stats-card.success .stats-card-icon {
            background: #22c55e20;
            color: #16a34a;
        }
        .stats-card.danger {
            background: linear-gradient(135deg, #fef2f2, #fee2e2);
        }
        .stats-card.danger .stats-card-icon {
            background: #f9737320;
            color: #b91c1c;
        }
        .search-row {
            margin-bottom: 1rem;
        }
        .search-row .form-control,
        .search-row .form-select {
            font-size: 0.9rem;
        }
        .search-row .btn {
            font-size: 0.9rem;
        }
        @media (max-width: 991.98px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.35rem;
            }
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <div class="container my-4">
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="bi bi-clipboard-check"></i>
                    Registrasi Kehadiran
                </h1>
                <p class="page-subtitle">
                    Kelola data kehadiran peserta dengan mudah: tambah, ubah, hapus, dan tinjau seluruh riwayat.
                </p>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12 mb-1">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="stats-card">
                            <div class="stats-card-icon">
                                <i class="bi bi-people"></i>
                            </div>
                            <div>
                                <div class="stats-card-title">Total Registrasi</div>
                                <div class="stats-card-value">
                                    <?php echo number_format($totalAll); ?>
                                </div>
                                <div class="stats-card-sub">
                                    Seluruh data yang pernah terdaftar
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card success">
                            <div class="stats-card-icon">
                                <i class="bi bi-check2-circle"></i>
                            </div>
                            <div>
                                <div class="stats-card-title">Hadir (YA)</div>
                                <div class="stats-card-value">
                                    <?php echo number_format($totalYa); ?>
                                </div>
                                <div class="stats-card-sub">
                                    Peserta yang terkonfirmasi hadir
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stats-card danger">
                            <div class="stats-card-icon">
                                <i class="bi bi-x-circle"></i>
                            </div>
                            <div>
                                <div class="stats-card-title">Tidak Hadir</div>
                                <div class="stats-card-value">
                                    <?php echo number_format($totalTidak); ?>
                                </div>
                                <div class="stats-card-sub">
                                    Peserta yang tidak hadir / batal
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card-modern mb-3">
                    <div class="card-modern-header">
                        <h5 class="mb-1">
                            <?php echo $edit_row ? 'Edit Data Kehadiran' : 'Tambah Registrasi Kehadiran'; ?>
                        </h5>
                        <small class="text-muted">
                            <?php echo $edit_row ? 'Perbarui informasi peserta yang sudah terdaftar.' : 'Isi formulir di bawah untuk menambahkan peserta.'; ?>
                        </small>
                    </div>
                    <div class="card-modern-body">
                        <form method="post" autocomplete="off">
                            <?php if ($edit_row): ?>
                                <input type="hidden" name="id" value="<?php echo (int)$edit_row['id']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Nama <span class="text-danger">*</span></label>
                                <input type="text" name="nama" class="form-control" required
                                       value="<?php echo htmlspecialchars($edit_row['nama'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" required
                                       value="<?php echo htmlspecialchars($edit_row['email'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Jabatan</label>
                                <input type="text" name="jabatan" class="form-control"
                                       value="<?php echo htmlspecialchars($edit_row['jabatan'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Asal Instansi</label>
                                <input type="text" name="asal_instansi" class="form-control"
                                       value="<?php echo htmlspecialchars($edit_row['asal_instansi'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Nama Instansi</label>
                                <input type="text" name="nama_instansi" class="form-control"
                                       value="<?php echo htmlspecialchars($edit_row['nama_instansi'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Nomor Handphone</label>
                                <input type="text" name="nomor_handphone" class="form-control"
                                       value="<?php echo htmlspecialchars($edit_row['nomor_handphone'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Lokasi</label>
                                <input type="text" name="lokasi" class="form-control"
                                       placeholder="Contoh: Jakarta, Bandung, dll."
                                       value="<?php echo htmlspecialchars($edit_row['lokasi'] ?? ''); ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Waktu Kedatangan</label>
                                <input
                                        type="datetime-local"
                                        name="waktu_kedatangan"
                                        class="form-control"
                                        value="<?php
                                            if (!empty($edit_row['waktu_kedatangan'])) {
                                                echo htmlspecialchars(date('Y-m-d\\TH:i', strtotime($edit_row['waktu_kedatangan'])));
                                            }
                                        ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Kehadiran</label>
                                <select name="kehadiran" class="form-select">
                                    <?php
                                        $currentKehadiran = $edit_row['kehadiran'] ?? 'YA';
                                    ?>
                                    <option value="YA" <?php echo ($currentKehadiran === 'YA') ? 'selected' : ''; ?>>YA</option>
                                    <option value="TIDAK" <?php echo ($currentKehadiran === 'TIDAK') ? 'selected' : ''; ?>>TIDAK</option>
                                </select>
                            </div>

                            <div class="d-flex align-items-center gap-2">
                                <button type="submit"
                                        name="<?php echo $edit_row ? 'update' : 'add'; ?>"
                                        class="btn btn-primary">
                                    <i class="bi <?php echo $edit_row ? 'bi-save' : 'bi-plus-circle'; ?> me-1"></i>
                                    <?php echo $edit_row ? 'Simpan Perubahan' : 'Tambah Data'; ?>
                                </button>
                                <?php if ($edit_row): ?>
                                    <a href="registrasi_kehadiran.php" class="btn btn-outline-secondary">
                                        Batal
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card-modern mb-3">
                    <div class="card-modern-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-1">Daftar Registrasi Kehadiran</h5>
                            <small class="text-muted">
                                Total data: <?php echo (int)$rows->num_rows; ?>
                                <?php if ($search !== '' || $filterKehad !== ''): ?>
                                    &middot; Filter aktif
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                    <div class="card-modern-body">
                        <form method="get" class="row align-items-end g-2 search-row">
                            <div class="col-md-6">
                                <label class="form-label mb-1">Cari Peserta / Instansi / Lokasi</label>
                                <input type="text" name="q" class="form-control"
                                       placeholder="Ketik nama, email, instansi, lokasi, atau no. HP"
                                       value="<?php echo htmlspecialchars($search, ENT_QUOTES); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-1">Kehadiran</label>
                                <select name="f_kehadiran" class="form-select">
                                    <option value="">Semua</option>
                                    <option value="YA" <?php echo ($filterKehad === 'YA') ? 'selected' : ''; ?>>YA</option>
                                    <option value="TIDAK" <?php echo ($filterKehad === 'TIDAK') ? 'selected' : ''; ?>>TIDAK</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex gap-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-search me-1"></i>Cari
                                </button>
                                <?php if ($search !== '' || $filterKehad !== ''): ?>
                                    <a href="registrasi_kehadiran.php" class="btn btn-outline-secondary">
                                        Reset
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                        <div class="table-responsive table-modern">
                            <table class="table mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Peserta</th>
                                        <th>Instansi</th>
                                        <th>Kontak</th>
                                        <th>Lokasi</th>
                                        <th>Kehadiran</th>
                                        <th>Timestamp</th>
                                        <th class="text-end">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($rows->num_rows === 0): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                Belum ada data registrasi kehadiran.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php $i = 1; ?>
                                        <?php while ($r = $rows->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $i++; ?></td>
                                                <td>
                                                    <div class="fw-semibold">
                                                        <?php echo htmlspecialchars($r['nama']); ?>
                                                    </div>
                                                    <div class="text-muted small">
                                                        <?php echo htmlspecialchars($r['email']); ?>
                                                    </div>
                                                    <?php if (!empty($r['jabatan'])): ?>
                                                        <div class="text-muted small">
                                                            <?php echo htmlspecialchars($r['jabatan']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($r['nama_instansi'])): ?>
                                                        <div class="fw-semibold">
                                                            <?php echo htmlspecialchars($r['nama_instansi']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($r['asal_instansi'])): ?>
                                                        <div class="text-muted small">
                                                            <?php echo htmlspecialchars($r['asal_instansi']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($r['nomor_handphone'])): ?>
                                                        <div class="small">
                                                            <i class="bi bi-telephone me-1"></i>
                                                            <?php echo htmlspecialchars($r['nomor_handphone']); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted small">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($r['lokasi'])): ?>
                                                        <span class="badge-lokasi">
                                                            <i class="bi bi-geo-alt me-1"></i>
                                                            <?php echo htmlspecialchars($r['lokasi']); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted small">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($r['kehadiran'] === 'YA'): ?>
                                                        <span class="badge-ya">
                                                            <i class="bi bi-check2-circle me-1"></i>YA
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge-tidak">
                                                            <i class="bi bi-x-circle me-1"></i>TIDAK
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="small">
                                                    <div>
                                                        <span class="text-muted">Dibuat:</span>
                                                        <?php echo htmlspecialchars($r['created_at']); ?>
                                                    </div>
                                                    <?php if (!empty($r['waktu_kedatangan'])): ?>
                                                        <div>
                                                            <span class="text-muted">Datang:</span>
                                                            <?php echo htmlspecialchars($r['waktu_kedatangan']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end actions">
                                                    <a href="registrasi_kehadiran.php?edit=<?php echo (int)$r['id']; ?>"
                                                       class="btn btn-sm btn-outline-primary me-1">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </a>
                                                    <a href="registrasi_kehadiran.php?delete=<?php echo (int)$r['id']; ?>"
                                                       class="btn btn-sm btn-outline-danger"
                                                       onclick="return confirm('Hapus data ini?');">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>


