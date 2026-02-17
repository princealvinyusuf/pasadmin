<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/auth_guard.php';

// DB connection (same pattern as kemitraan_submission.php)
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db_prod';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

function table_exists(mysqli $conn, string $table): bool {
    $t = $conn->real_escape_string($table);
    $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

if (!table_exists($conn, 'walkin_gallery_items')) {
    $_SESSION['error'] = 'Tabel walkin_gallery_items belum ada. Jalankan migration di Laravel terlebih dahulu.';
}

// Storage target:
// - Prefer absolute known path if present (XAMPP/Linux deployment)
// - Else try to resolve Laravel root relative to /public/pasadmin
// - Fallback: <public>/storage (symlink created by `php artisan storage:link`)
$publicDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..'); // .../public
$laravelRoot = $publicDir ? realpath($publicDir . DIRECTORY_SEPARATOR . '..') : null; // .../<root>
$projectRoot = $laravelRoot ?: dirname(__DIR__); // fallback for repo layout

$preferredStorageAppPublic = '/opt/lampp/htdocs/paskerid/storage/app/public';
$storageAppPublic = is_dir($preferredStorageAppPublic)
    ? $preferredStorageAppPublic
    : ($projectRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'public');
$publicStorage = ($publicDir ?: $projectRoot) . DIRECTORY_SEPARATOR . 'storage';
$storageBase = is_dir($storageAppPublic) ? $storageAppPublic : $publicStorage;

$relativeDir = 'kemitraan_galeri';
$uploadDir = $storageBase . DIRECTORY_SEPARATOR . $relativeDir;
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0775, true);
}

function store_upload(array $file, string $uploadDir, array $allowedExt, ?string &$errorMsg = null, string $relativeDir = 'kemitraan_galeri'): ?string {
    $errorMsg = null;
    if (!isset($file['tmp_name'])) {
        $errorMsg = 'File tidak ditemukan.';
        return null;
    }
    if (isset($file['error']) && (int)$file['error'] !== UPLOAD_ERR_OK) {
        $code = (int)$file['error'];
        $map = [
            UPLOAD_ERR_INI_SIZE => 'Ukuran file melebihi batas upload_max_filesize.',
            UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas MAX_FILE_SIZE.',
            UPLOAD_ERR_PARTIAL => 'File terupload sebagian.',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file yang dipilih.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ada.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk.',
            UPLOAD_ERR_EXTENSION => 'Upload diblokir oleh ekstensi PHP.',
        ];
        $errorMsg = $map[$code] ?? ('Upload error code: ' . $code);
        return null;
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        $errorMsg = 'Upload gagal (tmp_name tidak valid).';
        return null;
    }
    $name = $file['name'] ?? '';
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        $errorMsg = 'Format file tidak didukung.';
        return null;
    }
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true)) {
        $errorMsg = 'Gagal membuat folder upload. Periksa permission server.';
        return null;
    }
    if (!is_writable($uploadDir)) {
        $errorMsg = 'Folder upload tidak writable. Periksa permission server.';
        return null;
    }
    $safe = bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
    $dest = $uploadDir . DIRECTORY_SEPARATOR . $safe;
    if (@move_uploaded_file($file['tmp_name'], $dest)) {
        return rtrim($relativeDir, '/\\') . '/' . $safe; // relative to /storage
    }
    $errorMsg = 'move_uploaded_file gagal. Periksa permission dan batas upload.';
    return null;
}

function has_file_upload(array $file): bool {
    if (!isset($file['error'])) return false;
    $err = (int)$file['error'];
    return $err !== UPLOAD_ERR_NO_FILE;
}

// Handle create/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['create', 'update'], true)) {
    if (!table_exists($conn, 'walkin_gallery_items')) {
        header('Location: walkin_gallery.php');
        exit();
    }

    $action = $_POST['action'];
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $type = trim($_POST['type'] ?? '');
    $company_name = trim($_POST['company_name'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $caption = trim($_POST['caption'] ?? '');
    $embed_url = trim($_POST['embed_url'] ?? '');
    $embed_thumb = trim($_POST['embed_thumbnail_url'] ?? '');
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    $sort_order = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;

    $media_path = null;
    $thumb_path = null;
    $uploadError = null;
    $thumbError = null;

    if ($type === 'photo') {
        // create: required upload. update: optional upload.
        if ($action === 'create' || has_file_upload($_FILES['media_file'] ?? [])) {
            $media_path = store_upload($_FILES['media_file'] ?? [], $uploadDir, ['jpg','jpeg','png','webp'], $uploadError, $relativeDir);
            if (!$media_path) {
                $_SESSION['error'] = 'Upload foto gagal: ' . ($uploadError ?: 'unknown error');
                header('Location: walkin_gallery.php');
                exit();
            }
        }
        // photo does not use thumbnail
        $thumb_path = null;
    } elseif ($type === 'video_upload') {
        // create: required upload. update: optional upload.
        if ($action === 'create' || has_file_upload($_FILES['media_file'] ?? [])) {
            $media_path = store_upload($_FILES['media_file'] ?? [], $uploadDir, ['mp4','webm','ogg'], $uploadError, $relativeDir);
            if (!$media_path) {
                $_SESSION['error'] = 'Upload video gagal: ' . ($uploadError ?: 'unknown error');
                header('Location: walkin_gallery.php');
                exit();
            }
        }
        // thumbnail optional (both create/update)
        if (has_file_upload($_FILES['thumbnail_file'] ?? [])) {
            $thumb_path = store_upload($_FILES['thumbnail_file'] ?? [], $uploadDir, ['jpg','jpeg','png','webp'], $thumbError, $relativeDir);
        }
    } elseif ($type === 'video_embed') {
        if ($embed_url === '') {
            $_SESSION['error'] = 'Embed URL wajib diisi untuk video_embed.';
            header('Location: walkin_gallery.php');
            exit();
        }
        // no media upload; optional thumbnail upload (stored to thumbnail_path)
        if (has_file_upload($_FILES['thumbnail_file'] ?? [])) {
            $thumb_path = store_upload($_FILES['thumbnail_file'] ?? [], $uploadDir, ['jpg','jpeg','png','webp'], $thumbError, $relativeDir);
        }
        $media_path = null;
    } else {
        $_SESSION['error'] = 'Type tidak valid.';
        header('Location: walkin_gallery.php');
        exit();
    }

    if ($action === 'create') {
        $companyVal = ($company_name !== '' ? $company_name : null);
        $titleVal = ($title !== '' ? $title : null);
        $captionVal = ($caption !== '' ? $caption : null);
        // Avoid stale embed fields on non-embed items
        $embedVal = ($type === 'video_embed' && $embed_url !== '' ? $embed_url : null);
        // Deprecated in this admin UI: use uploaded thumbnail -> thumbnail_path instead
        $embedThumbVal = null;

        $stmt = $conn->prepare("INSERT INTO walkin_gallery_items (type, company_name, title, caption, media_path, thumbnail_path, embed_url, embed_thumbnail_url, is_published, sort_order, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?, ?,NOW(),NOW())");
        if ($stmt) {
            $stmt->bind_param(
                "ssssssssii",
                $type,
                $companyVal,
                $titleVal,
                $captionVal,
                $media_path,
                $thumb_path,
                $embedVal,
                $embedThumbVal,
                $is_published,
                $sort_order
            );
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Item galeri berhasil ditambahkan.';
        } else {
            $_SESSION['error'] = 'DB prepare failed: ' . $conn->error;
        }
    } else {
        // Update: keep old media_path unless new file uploaded
        $cur = null;
        $curThumb = null;
        $curEmbed = null;
        $curEmbedThumb = null;
        if ($id > 0 && ($sel = $conn->prepare("SELECT media_path, thumbnail_path FROM walkin_gallery_items WHERE id=?"))) {
            $sel->bind_param("i", $id);
            $sel->execute();
            $sel->bind_result($cur, $curThumb);
            $sel->fetch();
            $sel->close();
        }
        // Keep old file paths only when relevant & when user didn't upload new ones
        if ($type === 'video_embed') {
            $media_path = null;
            // keep existing thumbnail unless a new file was uploaded
            if (!$thumb_path) $thumb_path = $curThumb;
        } else {
            if (!$media_path) $media_path = $cur;
            if ($type === 'video_upload') {
                if (!$thumb_path) $thumb_path = $curThumb;
            } else {
                // photo: no thumbnail
                $thumb_path = null;
            }
        }

        $stmt = $conn->prepare("UPDATE walkin_gallery_items SET type=?, company_name=?, title=?, caption=?, media_path=?, thumbnail_path=?, embed_url=?, embed_thumbnail_url=?, is_published=?, sort_order=?, updated_at=NOW() WHERE id=?");
        if ($stmt) {
            $companyVal = ($company_name !== '' ? $company_name : null);
            $titleVal = ($title !== '' ? $title : null);
            $captionVal = ($caption !== '' ? $caption : null);
            // Avoid stale embed fields on non-embed items
            $embedVal = ($type === 'video_embed' && $embed_url !== '' ? $embed_url : null);
            // Deprecated in this admin UI: use uploaded thumbnail -> thumbnail_path instead
            $embedThumbVal = null;
            $stmt->bind_param("ssssssssiii", $type, $companyVal, $titleVal, $captionVal, $media_path, $thumb_path, $embedVal, $embedThumbVal, $is_published, $sort_order, $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Item galeri berhasil diupdate.';
        } else {
            $_SESSION['error'] = 'DB prepare failed: ' . $conn->error;
        }
    }

    header('Location: walkin_gallery.php');
    exit();
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($id > 0 && table_exists($conn, 'walkin_gallery_items')) {
        $stmt = $conn->prepare("DELETE FROM walkin_gallery_items WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Item galeri dihapus.';
        }
    }
    header('Location: walkin_gallery.php');
    exit();
}

// Handle edit (load single item)
$editItem = null;
$editingId = 0;
if (isset($_GET['edit'])) {
    $editingId = intval($_GET['edit']);
    if ($editingId > 0 && table_exists($conn, 'walkin_gallery_items')) {
        $stmt = $conn->prepare("SELECT * FROM walkin_gallery_items WHERE id=? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $editingId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res) {
                $editItem = $res->fetch_assoc() ?: null;
            }
            $stmt->close();
        }
        if (!$editItem) {
            $_SESSION['error'] = 'Item tidak ditemukan untuk diedit.';
            header('Location: walkin_gallery.php');
            exit();
        }
    }
}

// Fetch items
$items = [];
if (table_exists($conn, 'walkin_gallery_items')) {
    $res = $conn->query("SELECT * FROM walkin_gallery_items ORDER BY sort_order ASC, id DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) $items[] = $row;
        $res->free();
    }
}

function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-in Gallery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .thumb { width: 120px; height: 70px; object-fit: cover; border-radius: 8px; border: 1px solid #e5e7eb; background: #f3f4f6; }
        .pill { display:inline-block; padding: 2px 10px; border-radius:999px; font-size:12px; font-weight:600; background:#eef2f7; color:#334155; }
        .pill.pub { background:#dcfce7; color:#166534; }
        .pill.draft { background:#fee2e2; color:#991b1b; }
    </style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-0">Walk-in Gallery</h3>
            <div class="text-muted">Manage items shown on `kemitraan/create` gallery.</div>
        </div>
        <a class="btn btn-outline-secondary" href="walkin_gallery_comments.php"><i class="bi bi-chat-dots me-1"></i>Moderasi Komentar</a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= h($_SESSION['error']); ?></div>
    <?php unset($_SESSION['error']); endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= h($_SESSION['success']); ?></div>
    <?php unset($_SESSION['success']); endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><?= $editItem ? 'Edit Item #' . intval($editItem['id']) : 'Tambah Item'; ?></h5>
                <?php if ($editItem): ?>
                    <a class="btn btn-sm btn-outline-secondary" href="walkin_gallery.php"><i class="bi bi-x-circle me-1"></i>Batal Edit</a>
                <?php endif; ?>
            </div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?= $editItem ? 'update' : 'create'; ?>">
                <?php if ($editItem): ?>
                    <input type="hidden" name="id" value="<?= intval($editItem['id']); ?>">
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="type" id="typeSelect" required>
                            <?php $curType = $editItem['type'] ?? 'photo'; ?>
                            <option value="photo" <?= $curType === 'photo' ? 'selected' : ''; ?>>photo</option>
                            <option value="video_upload" <?= $curType === 'video_upload' ? 'selected' : ''; ?>>video_upload</option>
                            <option value="video_embed" <?= $curType === 'video_embed' ? 'selected' : ''; ?>>video_embed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Company Name</label>
                        <input type="text" class="form-control" name="company_name" placeholder="Contoh: PT ABC" maxlength="255" value="<?= h($editItem['company_name'] ?? ''); ?>">
                        <div class="form-text">Kosong = masuk ke kategori "Umum".</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Publish</label>
                        <div class="form-check mt-2">
                            <?php $pubChecked = $editItem ? !empty($editItem['is_published']) : true; ?>
                            <input class="form-check-input" type="checkbox" name="is_published" id="pubCheck" <?= $pubChecked ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="pubCheck">Published</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sort Order</label>
                        <input type="number" class="form-control" name="sort_order" value="<?= h($editItem['sort_order'] ?? 0); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Title (optional)</label>
                        <input type="text" class="form-control" name="title" maxlength="255" value="<?= h($editItem['title'] ?? ''); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Caption (optional)</label>
                        <textarea class="form-control" name="caption" rows="2"><?= h($editItem['caption'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-md-6" id="mediaFileWrap">
                        <label class="form-label">Media File (photo/video)</label>
                        <input type="file" class="form-control" name="media_file" id="mediaFile">
                        <div class="form-text">
                            Photo: JPG/PNG/WEBP. Video: MP4/WEBM/OGG.
                            <?php if ($editItem && !empty($editItem['media_path'])): ?>
                                <br>File saat ini: <code>/storage/<?= h(ltrim($editItem['media_path'], '/')); ?></code> (kosongkan jika tidak ingin ganti)
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6" id="thumbFileWrap">
                        <label class="form-label">Thumbnail (optional)</label>
                        <input type="file" class="form-control" name="thumbnail_file" id="thumbFile" accept="image/*">
                        <?php if ($editItem && !empty($editItem['thumbnail_path'])): ?>
                            <div class="form-text">Thumbnail saat ini: <code>/storage/<?= h(ltrim($editItem['thumbnail_path'], '/')); ?></code> (kosongkan jika tidak ingin ganti)</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 d-none" id="embedUrlWrap">
                        <label class="form-label">Embed URL (video_embed)</label>
                        <input type="text" class="form-control" name="embed_url" placeholder="https://..." value="<?= h($editItem['embed_url'] ?? ''); ?>">
                    </div>
                    <!-- Embed Thumbnail URL removed: use file upload Thumbnail instead -->
                </div>
                <div class="mt-3">
                    <?php if ($editItem): ?>
                        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Update</button>
                    <?php else: ?>
                        <button class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Tambah</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="mb-3">Daftar Item</h5>
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th style="width:140px">Preview</th>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Company</th>
                            <th>Title</th>
                            <th>Caption</th>
                            <th>Published</th>
                            <th>Sort</th>
                            <th style="width:120px">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $it): ?>
                        <?php
                            $preview = '';
                            if (!empty($it['thumbnail_path'])) $preview = '/storage/' . ltrim($it['thumbnail_path'], '/');
                            elseif (!empty($it['media_path'])) $preview = '/storage/' . ltrim($it['media_path'], '/');
                            elseif (!empty($it['embed_thumbnail_url'])) $preview = $it['embed_thumbnail_url'];
                        ?>
                        <tr>
                            <td>
                                <?php if ($preview): ?>
                                    <img class="thumb" src="<?= h($preview); ?>" alt="">
                                <?php else: ?>
                                    <div class="thumb d-flex align-items-center justify-content-center text-muted">-</div>
                                <?php endif; ?>
                            </td>
                            <td><?= intval($it['id']); ?></td>
                            <td><span class="pill"><?= h($it['type']); ?></span></td>
                            <td><?= h(($it['company_name'] ?? '') !== '' ? $it['company_name'] : 'Umum'); ?></td>
                            <td><?= h($it['title']); ?></td>
                            <td style="max-width:360px"><?= h($it['caption']); ?></td>
                            <td>
                                <?php if (!empty($it['is_published'])): ?>
                                    <span class="pill pub">Published</span>
                                <?php else: ?>
                                    <span class="pill draft">Draft</span>
                                <?php endif; ?>
                            </td>
                            <td><?= intval($it['sort_order']); ?></td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a class="btn btn-sm btn-outline-primary" href="?edit=<?= intval($it['id']); ?>">Edit</a>
                                    <a class="btn btn-sm btn-outline-danger" href="?delete=<?= intval($it['id']); ?>" onclick="return confirm('Hapus item ini?');">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($items) === 0): ?>
                        <tr><td colspan="9" class="text-center text-muted">Belum ada item.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-muted small">Catatan: gunakan tombol <b>Edit</b> untuk mengubah data; saat edit, upload file bersifat opsional.</div>
        </div>
    </div>
</div>

<script>
    (function () {
        const typeSelect = document.getElementById('typeSelect');
        const mediaWrap = document.getElementById('mediaFileWrap');
        const thumbWrap = document.getElementById('thumbFileWrap');
        const embedUrlWrap = document.getElementById('embedUrlWrap');
        if (!typeSelect) return;
        function sync() {
            const t = typeSelect.value;
            const isEmbed = t === 'video_embed';
            mediaWrap.classList.toggle('d-none', isEmbed);
            thumbWrap.classList.toggle('d-none', !(t === 'video_upload' || t === 'video_embed'));
            embedUrlWrap.classList.toggle('d-none', !isEmbed);
        }
        typeSelect.addEventListener('change', sync);
        sync();
    })();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>



