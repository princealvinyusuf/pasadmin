<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
if (!(current_user_can('walkin_form_manage') || current_user_can('manage_settings'))) {
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

// Create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS walkin_form_access_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    passcode_hash VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Initialize default row if not exists
$resInit = $conn->query("SELECT id FROM walkin_form_access_settings WHERE id = 1 LIMIT 1");
if (!$resInit || $resInit->num_rows === 0) {
    $defaultHash = password_hash('123456', PASSWORD_BCRYPT);
    $stmtInit = $conn->prepare("INSERT INTO walkin_form_access_settings (id, is_enabled, passcode_hash) VALUES (1, 1, ?)");
    if ($stmtInit) {
        $stmtInit->bind_param('s', $defaultHash);
        $stmtInit->execute();
        $stmtInit->close();
        $_SESSION['success'] = 'Pengaturan passcode berhasil diinisialisasi. Passcode default: 123456 (segera ganti).';
    }
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
    $newPasscode = trim((string) ($_POST['new_passcode'] ?? ''));
    $confirmPasscode = trim((string) ($_POST['confirm_passcode'] ?? ''));

    $current = $conn->query("SELECT passcode_hash FROM walkin_form_access_settings WHERE id = 1 LIMIT 1");
    $currentRow = $current ? $current->fetch_assoc() : null;
    $hash = (string) ($currentRow['passcode_hash'] ?? '');

    if ($newPasscode !== '') {
        if (strlen($newPasscode) < 4) {
            $_SESSION['error'] = 'Passcode minimal 4 karakter.';
            header('Location: walkin_form_access_settings.php');
            exit;
        }
        if ($newPasscode !== $confirmPasscode) {
            $_SESSION['error'] = 'Konfirmasi passcode tidak sama.';
            header('Location: walkin_form_access_settings.php');
            exit;
        }
        $hash = password_hash($newPasscode, PASSWORD_BCRYPT);
    }

    if ($hash === '') {
        $_SESSION['error'] = 'Passcode hash tidak valid. Silakan set passcode baru.';
        header('Location: walkin_form_access_settings.php');
        exit;
    }

    $stmt = $conn->prepare("UPDATE walkin_form_access_settings SET is_enabled = ?, passcode_hash = ? WHERE id = 1");
    if ($stmt) {
        $stmt->bind_param('is', $isEnabled, $hash);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = 'Pengaturan akses Form Pendaftaran Walk In berhasil disimpan.';
    } else {
        $_SESSION['error'] = 'Gagal menyimpan pengaturan: ' . $conn->error;
    }

    header('Location: walkin_form_access_settings.php');
    exit;
}

// Fetch current settings
$settings = [
    'is_enabled' => 1,
    'updated_at' => null,
];
$res = $conn->query("SELECT is_enabled, updated_at FROM walkin_form_access_settings WHERE id = 1 LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $settings = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-in Form Access Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container mt-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h3 class="mb-0">Walk-in Form Pendaftaran Access Settings</h3>
        <a href="kemitraan_submission.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Submissions</a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_enabled" name="is_enabled" <?php echo ((int) ($settings['is_enabled'] ?? 1) === 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_enabled">
                            Aktifkan passcode untuk menu <strong>Form Pendaftaran Walk In (Pemberi Kerja)</strong> di halaman Kemitraan
                        </label>
                    </div>
                    <div class="form-text">Jika aktif, user wajib memasukkan passcode yang benar saat menekan tab Form Pendaftaran Walk In.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Passcode Baru (opsional)</label>
                    <input type="password" class="form-control" name="new_passcode" placeholder="Kosongkan jika tidak ingin mengganti">
                    <div class="form-text">Minimal 4 karakter.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Konfirmasi Passcode Baru</label>
                    <input type="password" class="form-control" name="confirm_passcode" placeholder="Ulangi passcode baru">
                </div>

                <div class="col-12">
                    <div class="small text-muted">Last updated: <?php echo htmlspecialchars((string) ($settings['updated_at'] ?? '-')); ?></div>
                </div>

                <div class="col-12">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">Related Settings</h5>
        </div>
        <div class="card-body">
            <a href="walkin_survey_access_settings.php" class="btn btn-outline-primary">
                <i class="bi bi-shield-lock me-1"></i>Survei Evaluasi Access Settings
            </a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>

