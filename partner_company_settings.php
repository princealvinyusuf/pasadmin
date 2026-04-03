<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
if (!(current_user_can('partner_company_manage') || current_user_can('manage_settings'))) {
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

function table_exists(mysqli $conn, string $table): bool
{
    $t = $conn->real_escape_string($table);
    $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function column_exists(mysqli $conn, string $table, string $column): bool
{
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($column);
    $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND COLUMN_NAME = '$c' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function clean_string(mysqli $conn, string $value): string
{
    return trim($conn->real_escape_string($value));
}

function resolve_storage_paths(): array
{
    $publicDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
    $laravelRoot = $publicDir ? realpath($publicDir . DIRECTORY_SEPARATOR . '..') : null;
    $projectRoot = $laravelRoot ?: dirname(__DIR__);

    $preferredStorageAppPublic = '/opt/lampp/htdocs/paskerid/storage/app/public';
    $storageAppPublic = is_dir($preferredStorageAppPublic)
        ? $preferredStorageAppPublic
        : ($projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'public');
    $publicStorage = ($publicDir ?: $projectRoot) . DIRECTORY_SEPARATOR . 'storage';
    $storageBase = is_dir($storageAppPublic) ? $storageAppPublic : $publicStorage;

    return [$storageBase, 'partner_companies'];
}

function store_logo_upload(array $file, string $uploadDir, ?string &$errorMsg, string $relativeDir = 'partner_companies'): ?string
{
    $errorMsg = null;
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $errorMsg = 'File logo tidak valid.';
        return null;
    }

    $name = $file['name'] ?? '';
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        $errorMsg = 'Format logo harus JPG, PNG, atau WEBP.';
        return null;
    }

    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true)) {
        $errorMsg = 'Gagal membuat folder upload logo.';
        return null;
    }
    if (!is_writable($uploadDir)) {
        $errorMsg = 'Folder upload logo tidak writable.';
        return null;
    }

    $safe = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
    $dest = $uploadDir . DIRECTORY_SEPARATOR . $safe;
    if (!@move_uploaded_file($file['tmp_name'], $dest)) {
        $errorMsg = 'Gagal menyimpan file logo.';
        return null;
    }

    return rtrim($relativeDir, '/\\') . '/' . $safe;
}

[$storageBase, $relativeDir] = resolve_storage_paths();
$uploadDir = $storageBase . DIRECTORY_SEPARATOR . $relativeDir;

$tableReady = table_exists($conn, 'partner_companies');
if (!$tableReady) {
    $_SESSION['error'] = 'Table partner_companies belum ada. Jalankan migration Laravel terlebih dahulu.';
}

if ($tableReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $companyName = isset($_POST['company_name']) ? clean_string($conn, $_POST['company_name']) : '';
    $galleryCompanyName = isset($_POST['gallery_company_name']) ? clean_string($conn, $_POST['gallery_company_name']) : '';
    $jobCount = isset($_POST['job_count']) ? max(0, (int) $_POST['job_count']) : 0;
    $sortOrder = isset($_POST['sort_order']) ? max(0, (int) $_POST['sort_order']) : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $profileSummary = isset($_POST['profile_summary']) ? trim($_POST['profile_summary']) : '';

    if ($companyName === '') {
        $_SESSION['error'] = 'Nama perusahaan wajib diisi.';
        header('Location: partner_company_settings');
        exit();
    }

    $logoPath = null;
    $uploadError = null;
    if (isset($_FILES['logo_file']) && (int) ($_FILES['logo_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $logoPath = store_logo_upload($_FILES['logo_file'], $uploadDir, $uploadError, $relativeDir);
        if (!$logoPath) {
            $_SESSION['error'] = 'Upload logo gagal: ' . ($uploadError ?: 'unknown error');
            header('Location: partner_company_settings');
            exit();
        }
    }

    $galleryCompanyName = $galleryCompanyName !== '' ? $galleryCompanyName : null;
    $profileSummary = trim($profileSummary) !== '' ? trim($profileSummary) : null;

    if ($id > 0) {
        $currentLogo = null;
        $sel = $conn->prepare("SELECT logo_path FROM partner_companies WHERE id = ? LIMIT 1");
        if ($sel) {
            $sel->bind_param('i', $id);
            $sel->execute();
            $sel->bind_result($currentLogo);
            $sel->fetch();
            $sel->close();
        }
        if (!$logoPath) {
            $logoPath = $currentLogo;
        }

        $stmt = $conn->prepare("UPDATE partner_companies SET company_name = ?, gallery_company_name = ?, logo_path = ?, job_count = ?, profile_summary = ?, sort_order = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('sssisiii', $companyName, $galleryCompanyName, $logoPath, $jobCount, $profileSummary, $sortOrder, $isActive, $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Data perusahaan mitra berhasil diperbarui.';
        } else {
            $_SESSION['error'] = 'Gagal update data: ' . $conn->error;
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO partner_companies (company_name, gallery_company_name, logo_path, job_count, profile_summary, sort_order, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        if ($stmt) {
            $stmt->bind_param('sssisii', $companyName, $galleryCompanyName, $logoPath, $jobCount, $profileSummary, $sortOrder, $isActive);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Data perusahaan mitra berhasil ditambahkan.';
        } else {
            $_SESSION['error'] = 'Gagal simpan data: ' . $conn->error;
        }
    }

    header('Location: partner_company_settings');
    exit();
}

if ($tableReady && isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM partner_companies WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = 'Data perusahaan mitra berhasil dihapus.';
    } else {
        $_SESSION['error'] = 'Gagal hapus data: ' . $conn->error;
    }
    header('Location: partner_company_settings');
    exit();
}

$rows = [];
if ($tableReady) {
    $res = $conn->query("SELECT * FROM partner_companies ORDER BY sort_order ASC, company_name ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
}

$companyRatingMap = [];
if (table_exists($conn, 'company_walk_in_survey') && table_exists($conn, 'walk_in_survey_responses')) {
    $sql = "SELECT LOWER(TRIM(c.company_name)) AS company_key, ROUND(AVG(r.rating_satisfaction), 2) AS avg_rating
            FROM company_walk_in_survey c
            LEFT JOIN walk_in_survey_responses r ON r.company_walk_in_survey_id = c.id
            WHERE c.company_name IS NOT NULL AND TRIM(c.company_name) <> ''
            GROUP BY company_key";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $key = trim((string) ($row['company_key'] ?? ''));
            if ($key !== '') {
                $companyRatingMap[$key] = isset($row['avg_rating']) ? (float) $row['avg_rating'] : null;
            }
        }
        $res->free();
    }
}

$initiatorRatingMap = [];
if (
    table_exists($conn, 'walk_in_survey_initiators') &&
    table_exists($conn, 'company_walk_in_survey') &&
    table_exists($conn, 'walk_in_survey_responses') &&
    column_exists($conn, 'company_walk_in_survey', 'walk_in_initiator_id')
) {
    $sql = "SELECT LOWER(TRIM(i.initiator_name)) AS initiator_key, ROUND(AVG(r.rating_satisfaction), 2) AS avg_rating
            FROM walk_in_survey_initiators i
            LEFT JOIN company_walk_in_survey c ON c.walk_in_initiator_id = i.id
            LEFT JOIN walk_in_survey_responses r ON r.company_walk_in_survey_id = c.id
            WHERE i.initiator_name IS NOT NULL AND TRIM(i.initiator_name) <> ''
            GROUP BY initiator_key";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $key = trim((string) ($row['initiator_key'] ?? ''));
            if ($key !== '') {
                $initiatorRatingMap[$key] = isset($row['avg_rating']) ? (float) $row['avg_rating'] : null;
            }
        }
        $res->free();
    }
}

$reviewCountMap = [];
if (table_exists($conn, 'walkin_gallery_comments')) {
    $sql = "SELECT LOWER(TRIM(company_name)) AS company_key, COUNT(*) AS total
            FROM walkin_gallery_comments
            WHERE status = 'approved' AND company_name IS NOT NULL AND TRIM(company_name) <> ''
            GROUP BY company_key";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $key = trim((string) ($row['company_key'] ?? ''));
            if ($key !== '') {
                $reviewCountMap[$key] = (int) ($row['total'] ?? 0);
            }
        }
        $res->free();
    }
}

if (!empty($rows)) {
    foreach ($rows as &$r) {
        $mapSource = trim((string) (($r['gallery_company_name'] ?? '') !== '' ? $r['gallery_company_name'] : ($r['company_name'] ?? '')));
        $key = mb_strtolower($mapSource);
        $r['computed_rating'] = $initiatorRatingMap[$key] ?? $companyRatingMap[$key] ?? null;
        $r['computed_review_count'] = (int) ($reviewCountMap[$key] ?? 0);
    }
    unset($r);
}

$availableGalleryCompanies = [];
if (table_exists($conn, 'walkin_gallery_items')) {
    $res = $conn->query("SELECT DISTINCT company_name FROM walkin_gallery_items WHERE company_name IS NOT NULL AND company_name != '' ORDER BY company_name ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $availableGalleryCompanies[] = trim((string) ($row['company_name'] ?? ''));
        }
        $res->free();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perusahaan Mitra Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .partner-logo-thumb { width: 56px; height: 56px; object-fit: contain; border-radius: 12px; border: 1px solid #e5e7eb; background: #fff; }
    </style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container mt-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h3 class="mb-0">Perusahaan Mitra</h3>
        <div class="text-muted small">Kelola data profil perusahaan untuk panel `kemitraan/create`.</div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="id" id="form_id" value="">
                <div class="col-md-4">
                    <label class="form-label">Nama Perusahaan</label>
                    <input type="text" class="form-control" name="company_name" id="form_company_name" required maxlength="255">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Mapping Company di Galeri Walk In</label>
                    <input type="text" class="form-control" name="gallery_company_name" id="form_gallery_company_name" list="gallery_company_names_list" maxlength="255">
                    <?php if (!empty($availableGalleryCompanies)): ?>
                        <datalist id="gallery_company_names_list">
                            <?php foreach ($availableGalleryCompanies as $name): ?>
                                <option value="<?php echo htmlspecialchars($name); ?>">
                            <?php endforeach; ?>
                        </datalist>
                        <div class="form-text">Pilih nama perusahaan sesuai yang dipakai di Galeri Walk In.</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Jumlah Pekerjaan</label>
                    <input type="number" min="0" class="form-control" name="job_count" id="form_job_count" value="0">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Urutan</label>
                    <input type="number" min="0" class="form-control" name="sort_order" id="form_sort_order" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Logo</label>
                    <input type="file" class="form-control" name="logo_file" id="form_logo_file" accept=".jpg,.jpeg,.png,.webp">
                    <div class="form-text">Opsional. Format JPG/PNG/WEBP.</div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="form_is_active" checked>
                        <label class="form-check-label" for="form_is_active">Aktif</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Ringkasan Profil (opsional)</label>
                    <textarea class="form-control" rows="2" name="profile_summary" id="form_profile_summary" maxlength="2000"></textarea>
                    <div class="form-text">Rating dan jumlah ulasan dihitung otomatis dari Galeri Walk In (read-only).</div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary me-2"><i class="bi bi-save me-1"></i>Simpan</button>
                    <button type="button" class="btn btn-secondary" onclick="resetForm()">Clear</button>
                </div>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th style="width:70px;">ID</th>
                    <th style="width:90px;">Logo</th>
                    <th>Perusahaan</th>
                    <th>Mapping Galeri</th>
                    <th style="width:90px;">Rating</th>
                    <th style="width:110px;">Ulasan</th>
                    <th style="width:120px;">Pekerjaan</th>
                    <th style="width:90px;">Urutan</th>
                    <th style="width:100px;">Status</th>
                    <th style="width:130px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="10" class="text-center text-muted">Belum ada data perusahaan mitra.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <?php $logoUrl = !empty($r['logo_path']) ? '/storage/' . ltrim((string) $r['logo_path'], '/') : ''; ?>
                    <tr>
                        <td><?php echo (int) $r['id']; ?></td>
                        <td>
                            <?php if ($logoUrl): ?>
                                <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="logo" class="partner-logo-thumb">
                            <?php else: ?>
                                <div class="partner-logo-thumb d-flex align-items-center justify-content-center text-muted small">-</div>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars((string) $r['company_name']); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['gallery_company_name'] ?? '')); ?></td>
                        <td>
                            <?php if ($r['computed_rating'] !== null): ?>
                                <?php echo htmlspecialchars(number_format((float) $r['computed_rating'], 2)); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo (int) ($r['computed_review_count'] ?? 0); ?></td>
                        <td><?php echo (int) ($r['job_count'] ?? 0); ?></td>
                        <td><?php echo (int) ($r['sort_order'] ?? 0); ?></td>
                        <td>
                            <?php if ((int) ($r['is_active'] ?? 0) === 1): ?>
                                <span class="badge text-bg-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick='editRow(<?php echo json_encode($r); ?>)'><i class="bi bi-pencil-square"></i></button>
                            <a class="btn btn-sm btn-outline-danger" href="?delete=<?php echo (int) $r['id']; ?>" onclick="return confirm('Hapus data ini?');"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function editRow(row) {
    document.getElementById('form_id').value = row.id || '';
    document.getElementById('form_company_name').value = row.company_name || '';
    document.getElementById('form_gallery_company_name').value = row.gallery_company_name || '';
    document.getElementById('form_job_count').value = row.job_count || 0;
    document.getElementById('form_sort_order').value = row.sort_order || 0;
    document.getElementById('form_profile_summary').value = row.profile_summary || '';
    document.getElementById('form_is_active').checked = Number(row.is_active) === 1;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('form_id').value = '';
    document.getElementById('form_company_name').value = '';
    document.getElementById('form_gallery_company_name').value = '';
    document.getElementById('form_job_count').value = 0;
    document.getElementById('form_sort_order').value = 0;
    document.getElementById('form_profile_summary').value = '';
    document.getElementById('form_logo_file').value = '';
    document.getElementById('form_is_active').checked = true;
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>

