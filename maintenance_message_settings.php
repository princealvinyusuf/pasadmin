<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
if (!(current_user_can('settings_maintenance_message_manage') || current_user_can('manage_settings'))) {
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

$conn->query("CREATE TABLE IF NOT EXISTS maintenance_message_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    maintenance_at DATETIME NULL,
    duration_minutes INT UNSIGNED NOT NULL DEFAULT 60,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$resInit = $conn->query("SELECT id FROM maintenance_message_settings WHERE id = 1 LIMIT 1");
if (!$resInit || $resInit->num_rows === 0) {
    $conn->query("INSERT INTO maintenance_message_settings (id, is_enabled, maintenance_at, duration_minutes) VALUES (1, 0, NULL, 60)");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
    $maintenanceAtInput = trim((string)($_POST['maintenance_at'] ?? ''));
    $durationMinutes = (int)($_POST['duration_minutes'] ?? 0);

    if ($durationMinutes < 1) {
        $_SESSION['error'] = 'Durasi maintenance minimal 1 menit.';
        header('Location: maintenance_message_settings');
        exit;
    }

    $maintenanceAtSql = null;
    if ($maintenanceAtInput !== '') {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $maintenanceAtInput);
        if (!$dt) {
            $_SESSION['error'] = 'Format tanggal & jam tidak valid.';
            header('Location: maintenance_message_settings');
            exit;
        }
        $maintenanceAtSql = $dt->format('Y-m-d H:i:s');
    }

    if ($isEnabled === 1 && $maintenanceAtSql === null) {
        $_SESSION['error'] = 'Tanggal & jam maintenance wajib diisi saat pesan diaktifkan.';
        header('Location: maintenance_message_settings');
        exit;
    }

    $stmt = $conn->prepare('UPDATE maintenance_message_settings SET is_enabled = ?, maintenance_at = ?, duration_minutes = ? WHERE id = 1');
    if ($stmt) {
        $stmt->bind_param('isi', $isEnabled, $maintenanceAtSql, $durationMinutes);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = 'Pengaturan maintenance message berhasil disimpan.';
    } else {
        $_SESSION['error'] = 'Gagal menyimpan pengaturan: ' . $conn->error;
    }

    header('Location: maintenance_message_settings');
    exit;
}

$settings = [
    'is_enabled' => 0,
    'maintenance_at' => null,
    'duration_minutes' => 60,
    'updated_at' => null,
];

$res = $conn->query('SELECT is_enabled, maintenance_at, duration_minutes, updated_at FROM maintenance_message_settings WHERE id = 1 LIMIT 1');
if ($res && $row = $res->fetch_assoc()) {
    $settings = $row;
}

$maintenanceAtValue = '';
if (!empty($settings['maintenance_at'])) {
    $maintenanceAtValue = date('Y-m-d\TH:i', strtotime((string)$settings['maintenance_at']));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Message Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container mt-4">
    <h3 class="mb-3">Maintenance Message Settings</h3>

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
                        <input class="form-check-input" type="checkbox" id="is_enabled" name="is_enabled" <?php echo ((int)$settings['is_enabled'] === 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_enabled">
                            Aktifkan Maintenance Message di bawah top navigation bar website
                        </label>
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="maintenance_at" class="form-label">Tanggal & Jam Maintenance</label>
                    <input type="datetime-local" class="form-control" id="maintenance_at" name="maintenance_at" value="<?php echo htmlspecialchars($maintenanceAtValue); ?>">
                </div>

                <div class="col-md-6">
                    <label for="duration_minutes" class="form-label">Durasi (menit)</label>
                    <input type="number" min="1" class="form-control" id="duration_minutes" name="duration_minutes" value="<?php echo (int)$settings['duration_minutes']; ?>" required>
                </div>

                <div class="col-12">
                    <div class="alert alert-secondary mb-0">
                        Template pesan:
                        <br>
                        <em>Dalam rangka peningkatan kualitas layanan dan performa sistem, website kami akan menjalani maintenance pada [tanggal & jam] selama XX menit, sehingga untuk sementara tidak dapat diakses, mohon maaf atas ketidaknyamanannya.</em>
                    </div>
                </div>

                <div class="col-12">
                    <div class="small text-muted">Last updated: <?php echo htmlspecialchars((string)($settings['updated_at'] ?? '-')); ?></div>
                </div>

                <div class="col-12">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
