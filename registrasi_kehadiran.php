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
    kehadiran ENUM('YA','TIDAK') NOT NULL DEFAULT 'YA',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email_created (email, created_at),
    INDEX idx_lokasi_created (lokasi, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Simple helpers
function rk_post($key, $default = '')
{
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
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
    $kehadiran       = rk_post('kehadiran', 'YA');
    if ($kehadiran !== 'YA' && $kehadiran !== 'TIDAK') {
        $kehadiran = 'YA';
    }

    $stmt = $conn->prepare("
        INSERT INTO registrasi_kehadiran
        (nama, email, jabatan, asal_instansi, nama_instansi, nomor_handphone, lokasi, kehadiran, created_at, updated_at)
        VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())
    ");
    $stmt->bind_param(
        'ssssssss',
        $nama,
        $email,
        $jabatan,
        $asal_instansi,
        $nama_instansi,
        $nomor_handphone,
        $lokasi,
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
    $kehadiran       = rk_post('kehadiran', 'YA');
    if ($kehadiran !== 'YA' && $kehadiran !== 'TIDAK') {
        $kehadiran = 'YA';
    }

    $stmt = $conn->prepare("
        UPDATE registrasi_kehadiran
        SET nama=?, email=?, jabatan=?, asal_instansi=?, nama_instansi=?,
            nomor_handphone=?, lokasi=?, kehadiran=?, updated_at=NOW()
        WHERE id=?
    ");
    $stmt->bind_param(
        'ssssssssi',
        $nama,
        $email,
        $jabatan,
        $asal_instansi,
        $nama_instansi,
        $nomor_handphone,
        $lokasi,
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

// Fetch all rows (newest first)
$rows = $conn->query('SELECT * FROM registrasi_kehadiran ORDER BY created_at DESC, id DESC');
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
                            <small class="text-muted">Total data: <?php echo (int)$rows->num_rows; ?></small>
                        </div>
                    </div>
                    <div class="card-modern-body">
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
                                                    <?php echo htmlspecialchars($r['created_at']); ?>
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


