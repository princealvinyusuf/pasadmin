<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
if (!(current_user_can('settings_home_popup_manage') || current_user_can('manage_settings'))) {
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

$conn->query("CREATE TABLE IF NOT EXISTS home_popup_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    title VARCHAR(255) NULL,
    subtitle TEXT NULL,
    image_base64 LONGTEXT NULL,
    mime_type VARCHAR(100) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$resInit = $conn->query("SELECT id FROM home_popup_settings WHERE id = 1 LIMIT 1");
if (!$resInit || $resInit->num_rows === 0) {
    $conn->query("INSERT INTO home_popup_settings (id, is_enabled, title, subtitle, image_base64, mime_type) VALUES (1, 0, '', '', NULL, NULL)");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
    $title = trim((string)($_POST['title'] ?? ''));
    $subtitle = trim((string)($_POST['subtitle'] ?? ''));
    $removeImage = isset($_POST['remove_image']) ? 1 : 0;
    $imageBase64 = null;
    $mimeType = null;

    if ($isEnabled === 1 && $title === '') {
        $_SESSION['error'] = 'Judul popup wajib diisi saat popup diaktifkan.';
        header('Location: home_popup_settings');
        exit;
    }

    if ($isEnabled === 1 && $subtitle === '') {
        $_SESSION['error'] = 'Subjudul popup wajib diisi saat popup diaktifkan.';
        header('Location: home_popup_settings');
        exit;
    }

    if (isset($_FILES['image_file']) && (int)($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $tmp = (string)($_FILES['image_file']['tmp_name'] ?? '');
        $imageInfo = @getimagesize($tmp);
        if ($imageInfo === false) {
            $_SESSION['error'] = 'File gambar tidak valid.';
            header('Location: home_popup_settings');
            exit;
        }
        $imageData = @file_get_contents($tmp);
        if ($imageData === false) {
            $_SESSION['error'] = 'Gagal membaca file gambar.';
            header('Location: home_popup_settings');
            exit;
        }
        $imageBase64 = base64_encode($imageData);
        $mimeType = (string)($imageInfo['mime'] ?? 'image/jpeg');
    }

    if ($imageBase64 !== null) {
        $stmt = $conn->prepare("UPDATE home_popup_settings SET is_enabled = ?, title = ?, subtitle = ?, image_base64 = ?, mime_type = ? WHERE id = 1");
        if ($stmt) {
            $stmt->bind_param('issss', $isEnabled, $title, $subtitle, $imageBase64, $mimeType);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Home popup berhasil disimpan.';
        } else {
            $_SESSION['error'] = 'Gagal menyimpan pengaturan: ' . $conn->error;
        }
    } elseif ($removeImage === 1) {
        $stmt = $conn->prepare("UPDATE home_popup_settings SET is_enabled = ?, title = ?, subtitle = ?, image_base64 = NULL, mime_type = NULL WHERE id = 1");
        if ($stmt) {
            $stmt->bind_param('iss', $isEnabled, $title, $subtitle);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Home popup berhasil disimpan.';
        } else {
            $_SESSION['error'] = 'Gagal menyimpan pengaturan: ' . $conn->error;
        }
    } else {
        $stmt = $conn->prepare("UPDATE home_popup_settings SET is_enabled = ?, title = ?, subtitle = ? WHERE id = 1");
        if ($stmt) {
            $stmt->bind_param('iss', $isEnabled, $title, $subtitle);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Home popup berhasil disimpan.';
        } else {
            $_SESSION['error'] = 'Gagal menyimpan pengaturan: ' . $conn->error;
        }
    }

    header('Location: home_popup_settings');
    exit;
}

$settings = [
    'is_enabled' => 0,
    'title' => '',
    'subtitle' => '',
    'image_base64' => null,
    'mime_type' => null,
    'updated_at' => null,
];

$res = $conn->query("SELECT is_enabled, title, subtitle, image_base64, mime_type, updated_at FROM home_popup_settings WHERE id = 1 LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $settings = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home Popup Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container mt-4">
    <h3 class="mb-3">Home Popup Settings</h3>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_enabled" name="is_enabled" <?php echo ((int)$settings['is_enabled'] === 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_enabled">
                            Aktifkan popup ucapan selamat datang di Home Page
                        </label>
                    </div>
                </div>

                <div class="col-md-6">
                    <label for="title" class="form-label">Text 1 (Judul)</label>
                    <input type="text" maxlength="255" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars((string)$settings['title']); ?>" placeholder="Contoh: Selamat Datang di Pasker ID">
                </div>

                <div class="col-md-6">
                    <label for="subtitle" class="form-label">Text 2 (Subjudul)</label>
                    <input type="text" class="form-control" id="subtitle" name="subtitle" value="<?php echo htmlspecialchars((string)$settings['subtitle']); ?>" placeholder="Contoh: Akses layanan pasar kerja terbaru.">
                </div>

                <div class="col-12">
                    <label for="image_file" class="form-label">Gambar Popup</label>
                    <input type="file" class="form-control" id="image_file" name="image_file" accept="image/*">
                    <div class="form-text">Upload gambar baru jika ingin mengganti gambar saat ini.</div>
                </div>

                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="1">
                        <label class="form-check-label" for="remove_image">Hapus gambar yang sedang dipakai</label>
                    </div>
                </div>

                <?php if (!empty($settings['image_base64'])): ?>
                    <div class="col-12">
                        <label class="form-label d-block">Preview gambar saat ini</label>
                        <img src="data:<?php echo htmlspecialchars((string)($settings['mime_type'] ?: 'image/jpeg')); ?>;base64,<?php echo htmlspecialchars((string)$settings['image_base64']); ?>" alt="Current popup image" class="img-thumbnail" style="max-width: 280px;">
                    </div>
                <?php endif; ?>

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
