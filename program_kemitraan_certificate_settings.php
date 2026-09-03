<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

if (!(current_user_can('program_kemitraan_certificate_manage') || current_user_can('program_kemitraan_manage') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$conn = new mysqli('localhost', 'root', '', 'paskerid_db_prod');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function column_exists(mysqli $conn, string $tableName, string $columnName): bool
{
    $table = $conn->real_escape_string($tableName);
    $column = $conn->real_escape_string($columnName);
    $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function app_base_url(): string
{
    $default = '/pasadmin/';
    $candidates = [
        $_SERVER['REQUEST_URI'] ?? '',
        $_SERVER['PHP_SELF'] ?? '',
        $_SERVER['SCRIPT_NAME'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        $path = parse_url((string) $candidate, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            continue;
        }
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        foreach ($segments as $segment) {
            if (strcasecmp($segment, 'pasadmin') === 0) {
                return '/' . $segment . '/';
            }
        }
    }

    return $default;
}

function ensure_dir(string $path): bool
{
    if (is_dir($path)) {
        return true;
    }
    if (!@mkdir($path, 0777, true) && !is_dir($path)) {
        return false;
    }
    @chmod($path, 0777);
    return true;
}

function resolve_public_root(): string
{
    $docRoot = trim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($docRoot !== '' && is_dir($docRoot)) {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $docRoot), DIRECTORY_SEPARATOR);
    }

    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public';
}

function resolve_local_asset_path(string $storedPath, string $publicRoot): ?string
{
    $storedPath = trim($storedPath);
    if ($storedPath === '' || filter_var($storedPath, FILTER_VALIDATE_URL) !== false) {
        return null;
    }

    $normalized = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $storedPath), DIRECTORY_SEPARATOR);
    $candidates = [
        $publicRoot . DIRECTORY_SEPARATOR . $normalized,
        dirname(__DIR__) . DIRECTORY_SEPARATOR . $normalized,
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function unique_filename(string $dir, string $originalName): string
{
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $base = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
    if ($base === null || $base === '') {
        $base = 'signature';
    }
    $candidate = $base . ($extension !== '' ? '.' . $extension : '');
    $i = 1;

    while (file_exists($dir . DIRECTORY_SEPARATOR . $candidate)) {
        $candidate = $base . '_' . $i . ($extension !== '' ? '.' . $extension : '');
        $i++;
    }

    return $candidate;
}

$conn->query("
    CREATE TABLE IF NOT EXISTS program_kemitraan_certificate_settings (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        signature_image_path VARCHAR(255) NULL,
        background_image_path VARCHAR(255) NULL,
        logo_image_path VARCHAR(255) NULL,
        ministry_header_text VARCHAR(255) NOT NULL DEFAULT 'KEMENTERIAN KETENAGAKERJAAN REPUBLIK INDONESIA',
        signer_name VARCHAR(255) NOT NULL DEFAULT 'R. Nurhidajat, S.E., M.Ec.Dev.',
        signer_position VARCHAR(255) NOT NULL DEFAULT 'Kepala Pusat Pasar Kerja',
        sign_place VARCHAR(255) NOT NULL DEFAULT 'Jakarta',
        certificate_title VARCHAR(255) NOT NULL DEFAULT 'Sertifikat',
        participation_role_default VARCHAR(255) NOT NULL DEFAULT 'Peserta',
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
if (!column_exists($conn, 'program_kemitraan_certificate_settings', 'background_image_path')) {
    $conn->query("ALTER TABLE program_kemitraan_certificate_settings ADD COLUMN background_image_path VARCHAR(255) NULL AFTER signature_image_path");
}
$newColumns = [
    'logo_image_path' => "ALTER TABLE program_kemitraan_certificate_settings ADD COLUMN logo_image_path VARCHAR(255) NULL AFTER background_image_path",
    'ministry_header_text' => "ALTER TABLE program_kemitraan_certificate_settings ADD COLUMN ministry_header_text VARCHAR(255) NOT NULL DEFAULT 'KEMENTERIAN KETENAGAKERJAAN REPUBLIK INDONESIA' AFTER logo_image_path",
    'signer_position' => "ALTER TABLE program_kemitraan_certificate_settings ADD COLUMN signer_position VARCHAR(255) NOT NULL DEFAULT 'Kepala Pusat Pasar Kerja' AFTER signer_name",
    'sign_place' => "ALTER TABLE program_kemitraan_certificate_settings ADD COLUMN sign_place VARCHAR(255) NOT NULL DEFAULT 'Jakarta' AFTER signer_position",
    'participation_role_default' => "ALTER TABLE program_kemitraan_certificate_settings ADD COLUMN participation_role_default VARCHAR(255) NOT NULL DEFAULT 'Peserta' AFTER certificate_title",
];
foreach ($newColumns as $columnName => $alterSql) {
    if (!column_exists($conn, 'program_kemitraan_certificate_settings', $columnName)) {
        $conn->query($alterSql);
    }
}
$conn->query("
    UPDATE program_kemitraan_certificate_settings
    SET certificate_title = 'Sertifikat'
    WHERE certificate_title = 'Sertifikat Partisipasi'
");

$settingRow = null;
$settingRes = $conn->query("SELECT * FROM program_kemitraan_certificate_settings ORDER BY id ASC LIMIT 1");
if ($settingRes && $settingRes->num_rows > 0) {
    $settingRow = $settingRes->fetch_assoc();
}
if ($settingRes) {
    $settingRes->free();
}

$publicRoot = resolve_public_root();
$signatureDir = $publicRoot . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'program_kemitraan_certificates' . DIRECTORY_SEPARATOR . 'signatures';
$backgroundDir = $publicRoot . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'program_kemitraan_certificates' . DIRECTORY_SEPARATOR . 'backgrounds';
$logoDir = $publicRoot . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'program_kemitraan_certificates' . DIRECTORY_SEPARATOR . 'logos';
$signatureDirReady = ensure_dir($signatureDir) && is_writable($signatureDir);
$backgroundDirReady = ensure_dir($backgroundDir) && is_writable($backgroundDir);
$logoDirReady = ensure_dir($logoDir) && is_writable($logoDir);

$flash = trim((string) ($_GET['msg'] ?? ''));
$errors = [];

$signerName = trim((string) ($settingRow['signer_name'] ?? 'R. Nurhidajat, S.E., M.Ec.Dev.'));
$signerPosition = trim((string) ($settingRow['signer_position'] ?? 'Kepala Pusat Pasar Kerja'));
$signPlace = trim((string) ($settingRow['sign_place'] ?? 'Jakarta'));
$certificateTitle = trim((string) ($settingRow['certificate_title'] ?? 'Sertifikat'));
$ministryHeaderText = trim((string) ($settingRow['ministry_header_text'] ?? 'KEMENTERIAN KETENAGAKERJAAN REPUBLIK INDONESIA'));
$participationRoleDefault = trim((string) ($settingRow['participation_role_default'] ?? 'Peserta'));
$signatureImagePath = trim((string) ($settingRow['signature_image_path'] ?? ''));
$backgroundImagePath = trim((string) ($settingRow['background_image_path'] ?? ''));
$logoImagePath = trim((string) ($settingRow['logo_image_path'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $signerName = trim((string) ($_POST['signer_name'] ?? ''));
    $signerPosition = trim((string) ($_POST['signer_position'] ?? ''));
    $signPlace = trim((string) ($_POST['sign_place'] ?? ''));
    $certificateTitle = trim((string) ($_POST['certificate_title'] ?? 'Sertifikat'));
    $ministryHeaderText = trim((string) ($_POST['ministry_header_text'] ?? ''));
    $participationRoleDefault = trim((string) ($_POST['participation_role_default'] ?? ''));
    $signatureImagePath = trim((string) ($_POST['existing_signature_image_path'] ?? ''));
    $backgroundImagePath = trim((string) ($_POST['existing_background_image_path'] ?? ''));
    $logoImagePath = trim((string) ($_POST['existing_logo_image_path'] ?? ''));

    if ($signerName === '') {
        $errors[] = 'Nama penandatangan wajib diisi.';
    }
    if ($signerPosition === '') {
        $errors[] = 'Jabatan penandatangan wajib diisi.';
    }
    if ($signPlace === '') {
        $errors[] = 'Tempat penandatanganan wajib diisi.';
    }
    if ($certificateTitle === '') {
        $errors[] = 'Judul sertifikat wajib diisi.';
    }
    if ($ministryHeaderText === '') {
        $errors[] = 'Nama kementerian wajib diisi.';
    }
    if ($participationRoleDefault === '') {
        $errors[] = 'Peran peserta default wajib diisi.';
    }

    if (isset($_FILES['signature_image']) && (int) ($_FILES['signature_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $file = $_FILES['signature_image'];
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $allowedExtensions = ['png'];
        $size = (int) ($file['size'] ?? 0);
        if (!in_array($extension, $allowedExtensions, true)) {
            $errors[] = 'Format tanda tangan harus PNG transparan.';
        } elseif ($size <= 0 || $size > (5 * 1024 * 1024)) {
            $errors[] = 'Ukuran file tanda tangan maksimum 5MB.';
        } elseif (!$signatureDirReady) {
            $errors[] = 'Direktori upload tanda tangan tidak dapat ditulis: ' . $signatureDir;
        } else {
            $newName = unique_filename($signatureDir, (string) ($file['name'] ?? 'signature.png'));
            $targetPath = $signatureDir . DIRECTORY_SEPARATOR . $newName;
            if (!@move_uploaded_file((string) ($file['tmp_name'] ?? ''), $targetPath)) {
                $errors[] = 'Gagal mengunggah file tanda tangan.';
            } else {
                $oldPath = resolve_local_asset_path($signatureImagePath, $publicRoot);
                if ($oldPath !== null && is_file($oldPath)) {
                    @unlink($oldPath);
                }
                $signatureImagePath = 'images/program_kemitraan_certificates/signatures/' . $newName;
            }
        }
    } elseif (isset($_POST['remove_signature']) && $_POST['remove_signature'] === '1') {
        $oldPath = resolve_local_asset_path($signatureImagePath, $publicRoot);
        if ($oldPath !== null && is_file($oldPath)) {
            @unlink($oldPath);
        }
        $signatureImagePath = '';
    }

    if (isset($_FILES['background_image']) && (int) ($_FILES['background_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $file = $_FILES['background_image'];
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $allowedExtensions = ['png', 'jpg', 'jpeg', 'webp'];
        $size = (int) ($file['size'] ?? 0);
        if (!in_array($extension, $allowedExtensions, true)) {
            $errors[] = 'Format background harus PNG/JPG/JPEG/WEBP.';
        } elseif ($size <= 0 || $size > (10 * 1024 * 1024)) {
            $errors[] = 'Ukuran file background maksimum 10MB.';
        } elseif (!$backgroundDirReady) {
            $errors[] = 'Direktori upload background tidak dapat ditulis: ' . $backgroundDir;
        } else {
            $newName = unique_filename($backgroundDir, (string) ($file['name'] ?? 'background.jpg'));
            $targetPath = $backgroundDir . DIRECTORY_SEPARATOR . $newName;
            if (!@move_uploaded_file((string) ($file['tmp_name'] ?? ''), $targetPath)) {
                $errors[] = 'Gagal mengunggah file background sertifikat.';
            } else {
                $oldPath = resolve_local_asset_path($backgroundImagePath, $publicRoot);
                if ($oldPath !== null && is_file($oldPath)) {
                    @unlink($oldPath);
                }
                $backgroundImagePath = 'images/program_kemitraan_certificates/backgrounds/' . $newName;
            }
        }
    } elseif (isset($_POST['remove_background']) && $_POST['remove_background'] === '1') {
        $oldPath = resolve_local_asset_path($backgroundImagePath, $publicRoot);
        if ($oldPath !== null && is_file($oldPath)) {
            @unlink($oldPath);
        }
        $backgroundImagePath = '';
    }

    if (isset($_FILES['logo_image']) && (int) ($_FILES['logo_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $file = $_FILES['logo_image'];
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $allowedExtensions = ['png', 'jpg', 'jpeg', 'webp'];
        $size = (int) ($file['size'] ?? 0);
        if (!in_array($extension, $allowedExtensions, true)) {
            $errors[] = 'Format logo harus PNG/JPG/JPEG/WEBP.';
        } elseif ($size <= 0 || $size > (5 * 1024 * 1024)) {
            $errors[] = 'Ukuran file logo maksimum 5MB.';
        } elseif (!$logoDirReady) {
            $errors[] = 'Direktori upload logo tidak dapat ditulis: ' . $logoDir;
        } else {
            $newName = unique_filename($logoDir, (string) ($file['name'] ?? 'logo.png'));
            $targetPath = $logoDir . DIRECTORY_SEPARATOR . $newName;
            if (!@move_uploaded_file((string) ($file['tmp_name'] ?? ''), $targetPath)) {
                $errors[] = 'Gagal mengunggah file logo.';
            } else {
                $oldPath = resolve_local_asset_path($logoImagePath, $publicRoot);
                if ($oldPath !== null && is_file($oldPath)) {
                    @unlink($oldPath);
                }
                $logoImagePath = 'images/program_kemitraan_certificates/logos/' . $newName;
            }
        }
    } elseif (isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1') {
        $oldPath = resolve_local_asset_path($logoImagePath, $publicRoot);
        if ($oldPath !== null && is_file($oldPath)) {
            @unlink($oldPath);
        }
        $logoImagePath = '';
    }

    if (empty($errors)) {
        $existingId = isset($settingRow['id']) ? (int) $settingRow['id'] : 0;
        if ($existingId > 0) {
            $stmt = $conn->prepare("
                UPDATE program_kemitraan_certificate_settings
                SET signer_name = ?, signer_position = ?, sign_place = ?, certificate_title = ?,
                    ministry_header_text = ?, participation_role_default = ?, signature_image_path = ?,
                    background_image_path = ?, logo_image_path = ?, updated_at = NOW()
                WHERE id = ?
            ");
            if ($stmt) {
                $stmt->bind_param(
                    'sssssssssi',
                    $signerName,
                    $signerPosition,
                    $signPlace,
                    $certificateTitle,
                    $ministryHeaderText,
                    $participationRoleDefault,
                    $signatureImagePath,
                    $backgroundImagePath,
                    $logoImagePath,
                    $existingId
                );
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare("
                INSERT INTO program_kemitraan_certificate_settings
                    (signer_name, signer_position, sign_place, certificate_title, ministry_header_text,
                     participation_role_default, signature_image_path, background_image_path, logo_image_path,
                     created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            if ($stmt) {
                $stmt->bind_param(
                    'sssssssss',
                    $signerName,
                    $signerPosition,
                    $signPlace,
                    $certificateTitle,
                    $ministryHeaderText,
                    $participationRoleDefault,
                    $signatureImagePath,
                    $backgroundImagePath,
                    $logoImagePath
                );
                $stmt->execute();
                $stmt->close();
            }
        }

        header('Location: ' . app_base_url() . 'program_kemitraan_certificate_settings?msg=saved');
        exit;
    }
}

$previewImageUrl = '';
if ($signatureImagePath !== '') {
    $previewImageUrl = '/' . ltrim($signatureImagePath, '/');
}
$previewBackgroundUrl = '';
if ($backgroundImagePath !== '') {
    $previewBackgroundUrl = '/' . ltrim($backgroundImagePath, '/');
}
$previewLogoUrl = '';
if ($logoImagePath !== '') {
    $previewLogoUrl = '/' . ltrim($logoImagePath, '/');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Program Kemitraan Sertifikat Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include __DIR__ . '/navbar.php'; ?>
<div class="container mt-4 mb-5">
    <div class="card shadow-sm">
        <div class="card-header">
            <h4 class="mb-0">Program Kemitraan - Sertifikat Settings</h4>
        </div>
        <div class="card-body">
            <p class="text-muted mb-4">Atur identitas kementerian, isi, penandatangan, logo, dan background untuk sertifikat PDF.</p>

            <?php if ($flash === 'saved'): ?>
                <div class="alert alert-success">Pengaturan sertifikat berhasil disimpan.</div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <div><?php echo esc($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="existing_signature_image_path" value="<?php echo esc($signatureImagePath); ?>">
                <input type="hidden" name="existing_background_image_path" value="<?php echo esc($backgroundImagePath); ?>">
                <input type="hidden" name="existing_logo_image_path" value="<?php echo esc($logoImagePath); ?>">
                <div class="col-12">
                    <label class="form-label">Nama Kementerian</label>
                    <input type="text" class="form-control" name="ministry_header_text" value="<?php echo esc($ministryHeaderText); ?>" maxlength="255" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Judul Sertifikat</label>
                    <input type="text" class="form-control" name="certificate_title" value="<?php echo esc($certificateTitle); ?>" maxlength="255" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Peran Peserta Default</label>
                    <input type="text" class="form-control" name="participation_role_default" value="<?php echo esc($participationRoleDefault); ?>" maxlength="255" required>
                    <div class="form-text">Dipakai jika jabatan/peran responden tidak tersedia.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Nama Penandatangan</label>
                    <input type="text" class="form-control" name="signer_name" value="<?php echo esc($signerName); ?>" maxlength="255" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Jabatan Penandatangan</label>
                    <input type="text" class="form-control" name="signer_position" value="<?php echo esc($signerPosition); ?>" maxlength="255" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tempat Penandatanganan</label>
                    <input type="text" class="form-control" name="sign_place" value="<?php echo esc($signPlace); ?>" maxlength="255" required>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Upload Logo Kementerian</label>
                    <input type="file" class="form-control" name="logo_image" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
                    <div class="form-text">Maksimal 5MB. PNG transparan direkomendasikan. Jika kosong, PDF memakai simbol bawaan.</div>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="removeLogo" name="remove_logo">
                        <label class="form-check-label" for="removeLogo">
                            Hapus logo saat ini
                        </label>
                    </div>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Upload Tanda Tangan (PNG transparan)</label>
                    <input type="file" class="form-control" name="signature_image" accept=".png,image/png">
                    <div class="form-text">Maksimal 5MB. Gunakan PNG transparan agar hasil PDF rapi.</div>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="removeSignature" name="remove_signature">
                        <label class="form-check-label" for="removeSignature">
                            Hapus tanda tangan saat ini
                        </label>
                    </div>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Upload Background Sertifikat</label>
                    <input type="file" class="form-control" name="background_image" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp">
                    <div class="form-text">Maksimal 10MB. Disarankan rasio landscape mendekati A4 (mis. 1600x1100).</div>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="removeBackground" name="remove_background">
                        <label class="form-check-label" for="removeBackground">
                            Hapus background saat ini
                        </label>
                    </div>
                </div>

                <?php if ($previewLogoUrl !== ''): ?>
                    <div class="col-12">
                        <div class="small text-muted mb-2">Preview logo saat ini:</div>
                        <img src="<?php echo esc($previewLogoUrl); ?>" alt="Logo preview" style="max-width:160px;max-height:120px;object-fit:contain;background:#f8fafc;border:1px solid #e2e8f0;padding:0.75rem;">
                    </div>
                <?php endif; ?>
                <?php if ($previewImageUrl !== ''): ?>
                    <div class="col-12">
                        <div class="small text-muted mb-2">Preview tanda tangan saat ini:</div>
                        <img src="<?php echo esc($previewImageUrl); ?>" alt="Signature preview" style="max-width:300px;max-height:120px;object-fit:contain;background:#f8fafc;border:1px solid #e2e8f0;padding:0.75rem;">
                    </div>
                <?php endif; ?>
                <?php if ($previewBackgroundUrl !== ''): ?>
                    <div class="col-12">
                        <div class="small text-muted mb-2">Preview background saat ini:</div>
                        <img src="<?php echo esc($previewBackgroundUrl); ?>" alt="Background preview" style="max-width:100%;max-height:220px;object-fit:contain;background:#f8fafc;border:1px solid #e2e8f0;padding:0.5rem;">
                    </div>
                <?php endif; ?>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle me-1"></i>Simpan Pengaturan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
