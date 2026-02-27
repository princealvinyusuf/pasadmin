<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

// Permission gate
if (!(current_user_can('career_boost_day_testimonial_manage') || current_user_can('career_boost_day_manage') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function try_connect_db(string $dbName): ?mysqli {
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $conn = @new mysqli($host, $user, $pass, $dbName);
    if ($conn->connect_error) return null;
    $conn->set_charset('utf8mb4');
    return $conn;
}

function table_exists(mysqli $conn, string $table): bool {
    $t = $conn->real_escape_string($table);
    $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function ensure_testimonials_table(mysqli $conn): void {
    if (!table_exists($conn, 'career_boostday_testimonials')) {
        $conn->query("CREATE TABLE career_boostday_testimonials (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            job_title VARCHAR(200) NULL,
            photo_url VARCHAR(500) NULL,
            testimony TEXT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            INDEX idx_active (is_active),
            INDEX idx_sort (sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

// Determine database
$databases = ['paskerid_db_prod', 'job_admin_prod', 'paskerid_db'];
$conn = null;
$activeDb = '';
foreach ($databases as $dbName) {
    $conn = try_connect_db($dbName);
    if ($conn) {
        $activeDb = $dbName;
        break;
    }
}
if (!$conn) {
    die('Database connection failed.');
}

ensure_testimonials_table($conn);

// Setup upload directory
$upload_base_dir = $_SERVER['DOCUMENT_ROOT'] . '/images/career_boostday_testimonials/';
$db_image_path_prefix = 'images/career_boostday_testimonials/';

if (!file_exists($upload_base_dir)) {
    if (!mkdir($upload_base_dir, 0755, true)) {
        error_log("Failed to create upload directory: " . $upload_base_dir);
    }
}

// Helper function to get image display URL
function getPhotoDisplayUrl($photo_path) {
    if (empty($photo_path)) return '';
    
    // If it's already a full URL (http/https), return as is
    if (preg_match('/^https?:\/\//', $photo_path)) {
        return $photo_path;
    }
    
    // If it's in the format images/career_boostday_testimonials/filename.jpg, prepend /
    if (strpos($photo_path, 'images/career_boostday_testimonials/') === 0) {
        return '/' . $photo_path;
    }
    
    // If it starts with /images/, return as is
    if (strpos($photo_path, '/images/') === 0) {
        return $photo_path;
    }
    
    return $photo_path;
}

// Helper function to get file system path
function getPhotoFilePath($photo_path) {
    if (empty($photo_path)) return '';
    
    // If it's a full URL, return empty (can't delete external URLs)
    if (preg_match('/^https?:\/\//', $photo_path)) {
        return '';
    }
    
    // If it's in the format images/career_boostday_testimonials/filename.jpg
    if (strpos($photo_path, 'images/career_boostday_testimonials/') === 0) {
        return $_SERVER['DOCUMENT_ROOT'] . '/' . $photo_path;
    }
    
    return $_SERVER['DOCUMENT_ROOT'] . '/' . $photo_path;
}

$message = '';
$messageType = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $name = trim($_POST['name'] ?? '');
        $job_title = trim($_POST['job_title'] ?? '');
        $testimony = trim($_POST['testimony'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $sort_order = intval($_POST['sort_order'] ?? 0);
        
        if ($name === '' || $testimony === '') {
            $message = 'Nama dan Testimoni wajib diisi.';
            $messageType = 'danger';
        } else {
            $photo_url = '';
            
            // Handle file upload
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $file_info = pathinfo($_FILES['photo']['name']);
                $extension = strtolower($file_info['extension'] ?? '');
                
                // Check if file is an image
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (in_array($extension, $allowed_extensions)) {
                    // Generate unique filename
                    $filename = uniqid() . '_' . time() . '.' . $extension;
                    $target_file_system_path = $upload_base_dir . $filename;
                    $photo_url = $db_image_path_prefix . $filename;
                    
                    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $target_file_system_path)) {
                        $message = 'Gagal mengupload foto.';
                        $messageType = 'danger';
                        header('Location: career_boostday_testimonial.php?msg=' . urlencode($message) . '&type=' . urlencode($messageType));
                        exit;
                    }
                } else {
                    $message = 'Format file tidak didukung. Gunakan JPG, PNG, GIF, atau WEBP.';
                    $messageType = 'danger';
                    header('Location: career_boostday_testimonial.php?msg=' . urlencode($message) . '&type=' . urlencode($messageType));
                    exit;
                }
            } elseif ($action === 'update') {
                // Keep existing photo if no new file uploaded
                $id = intval($_POST['id'] ?? 0);
                $res = $conn->query("SELECT photo_url FROM career_boostday_testimonials WHERE id = $id LIMIT 1");
                if ($res && $res->num_rows > 0) {
                    $row = $res->fetch_assoc();
                    $photo_url = $row['photo_url'] ?? '';
                }
            }
            
            if ($action === 'create') {
                $stmt = $conn->prepare("INSERT INTO career_boostday_testimonials (name, job_title, photo_url, testimony, is_active, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->bind_param('ssssii', $name, $job_title, $photo_url, $testimony, $is_active, $sort_order);
                if ($stmt->execute()) {
                    $message = 'Testimoni berhasil ditambahkan.';
                    $messageType = 'success';
                } else {
                    $message = 'Gagal menambahkan testimoni.';
                    $messageType = 'danger';
                }
                $stmt->close();
            } else {
                $id = intval($_POST['id'] ?? 0);
                
                // If new photo uploaded, delete old photo
                if (!empty($photo_url) && isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $res = $conn->query("SELECT photo_url FROM career_boostday_testimonials WHERE id = $id LIMIT 1");
                    if ($res && $res->num_rows > 0) {
                        $row = $res->fetch_assoc();
                        $old_photo = $row['photo_url'] ?? '';
                        if (!empty($old_photo)) {
                            $old_file_path = getPhotoFilePath($old_photo);
                            if (!empty($old_file_path) && file_exists($old_file_path)) {
                                @unlink($old_file_path);
                            }
                        }
                    }
                }
                
                $stmt = $conn->prepare("UPDATE career_boostday_testimonials SET name=?, job_title=?, photo_url=?, testimony=?, is_active=?, sort_order=?, updated_at=NOW() WHERE id=?");
                $stmt->bind_param('ssssiis', $name, $job_title, $photo_url, $testimony, $is_active, $sort_order, $id);
                if ($stmt->execute()) {
                    $message = 'Testimoni berhasil diperbarui.';
                    $messageType = 'success';
                } else {
                    $message = 'Gagal memperbarui testimoni.';
                    $messageType = 'danger';
                }
                $stmt->close();
            }
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        
        // Get photo path before deleting
        $res = $conn->query("SELECT photo_url FROM career_boostday_testimonials WHERE id = $id LIMIT 1");
        $photo_path = '';
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $photo_path = $row['photo_url'] ?? '';
        }
        
        $stmt = $conn->prepare("DELETE FROM career_boostday_testimonials WHERE id=?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            // Delete photo file if exists
            if (!empty($photo_path)) {
                $file_path = getPhotoFilePath($photo_path);
                if (!empty($file_path) && file_exists($file_path)) {
                    @unlink($file_path);
                }
            }
            $message = 'Testimoni berhasil dihapus.';
            $messageType = 'success';
        } else {
            $message = 'Gagal menghapus testimoni.';
            $messageType = 'danger';
        }
        $stmt->close();
    } elseif ($action === 'toggle_active') {
        $id = intval($_POST['id'] ?? 0);
        $conn->query("UPDATE career_boostday_testimonials SET is_active = NOT is_active, updated_at = NOW() WHERE id = $id");
        $message = 'Status testimoni berhasil diubah.';
        $messageType = 'success';
    }
    
    // Redirect to avoid form resubmission
    header('Location: career_boostday_testimonial.php?msg=' . urlencode($message) . '&type=' . urlencode($messageType));
    exit;
}

// Handle GET message
if (isset($_GET['msg']) && $_GET['msg'] !== '') {
    $message = $_GET['msg'];
    $messageType = $_GET['type'] ?? 'info';
}

// Fetch edit data
$editData = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM career_boostday_testimonials WHERE id = $editId LIMIT 1");
    if ($res && $res->num_rows > 0) {
        $editData = $res->fetch_assoc();
    }
}

// Fetch all testimonials
$testimonials = [];
$res = $conn->query("SELECT * FROM career_boostday_testimonials ORDER BY sort_order ASC, id DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $testimonials[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Boost Day Testimonial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .testimonial-photo {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #dee2e6;
        }
        .testimonial-photo-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 1.5rem;
        }
        .testimony-preview {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body class="bg-light">
<?php include __DIR__ . '/navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
        <div>
            <h1 class="h4 mb-1"><i class="bi bi-chat-quote me-2"></i>Career Boost Day Testimonial</h1>
            <div class="text-muted small">Database: <code><?php echo h($activeDb); ?></code> • Total: <b><?php echo count($testimonials); ?></b></div>
        </div>
        <div>
            <a href="career_boostday.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
        </div>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-<?php echo h($messageType); ?> alert-dismissible fade show" role="alert">
        <?php echo h($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
    
    <!-- Form Add/Edit -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="bi bi-<?php echo $editData ? 'pencil' : 'plus-circle'; ?> me-1"></i>
            <?php echo $editData ? 'Edit Testimoni' : 'Tambah Testimoni Baru'; ?>
        </div>
        <div class="card-body">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="<?php echo $editData ? 'update' : 'create'; ?>">
                <?php if ($editData): ?>
                <input type="hidden" name="id" value="<?php echo h($editData['id']); ?>">
                <?php endif; ?>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Nama <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" value="<?php echo h($editData['name'] ?? ''); ?>" required placeholder="Nama peserta">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Pekerjaan yang Diperoleh</label>
                        <input type="text" class="form-control" name="job_title" value="<?php echo h($editData['job_title'] ?? ''); ?>" placeholder="Contoh: Staff Marketing di PT ABC">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Foto</label>
                        <input type="file" class="form-control" name="photo" id="photo_input" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp">
                        <div class="form-text">Upload foto (JPG, PNG, GIF, atau WEBP). Maksimal 5MB.</div>
                        <?php if ($editData && !empty($editData['photo_url'])): ?>
                        <div class="mt-2">
                            <small class="text-muted">Foto saat ini:</small><br>
                            <img src="<?php echo h(getPhotoDisplayUrl($editData['photo_url'])); ?>" alt="Current photo" class="mt-1" style="max-width: 150px; max-height: 150px; object-fit: cover; border-radius: 8px; border: 2px solid #dee2e6;">
                        </div>
                        <?php endif; ?>
                        <div id="photo_preview" class="mt-2" style="display: none;">
                            <small class="text-muted">Preview foto baru:</small><br>
                            <img id="photo_preview_img" src="" alt="Preview" style="max-width: 150px; max-height: 150px; object-fit: cover; border-radius: 8px; border: 2px solid #198754; margin-top: 8px;">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Urutan</label>
                        <input type="number" class="form-control" name="sort_order" value="<?php echo h($editData['sort_order'] ?? '0'); ?>" min="0">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Aktif</label>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?php echo (!$editData || ($editData['is_active'] ?? 1)) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">Tampilkan</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Testimoni <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="testimony" rows="4" required placeholder="Tulis testimoni peserta..."><?php echo h($editData['testimony'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-<?php echo $editData ? 'check-lg' : 'plus-lg'; ?> me-1"></i>
                            <?php echo $editData ? 'Simpan Perubahan' : 'Tambah Testimoni'; ?>
                        </button>
                        <?php if ($editData): ?>
                        <a href="career_boostday_testimonial.php" class="btn btn-secondary">Batal</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Testimonials Table -->
    <div class="card">
        <div class="card-header">
            <i class="bi bi-list-ul me-1"></i>Daftar Testimoni
        </div>
        <div class="card-body p-0">
            <?php if (empty($testimonials)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-chat-quote" style="font-size: 3rem;"></i>
                <p class="mt-2 mb-0">Belum ada testimoni.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 80px;">Foto</th>
                            <th>Nama</th>
                            <th>Pekerjaan</th>
                            <th>Testimoni</th>
                            <th style="width: 80px;">Urutan</th>
                            <th style="width: 80px;">Status</th>
                            <th style="width: 150px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($testimonials as $t): ?>
                        <tr>
                            <td>
                                <?php if (!empty($t['photo_url'])): ?>
                                <img src="<?php echo h(getPhotoDisplayUrl($t['photo_url'])); ?>" alt="<?php echo h($t['name']); ?>" class="testimonial-photo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="testimonial-photo-placeholder" style="display: none;">
                                    <i class="bi bi-person"></i>
                                </div>
                                <?php else: ?>
                                <div class="testimonial-photo-placeholder">
                                    <i class="bi bi-person"></i>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td class="fw-semibold"><?php echo h($t['name']); ?></td>
                            <td><?php echo h($t['job_title'] ?: '-'); ?></td>
                            <td>
                                <div class="testimony-preview" title="<?php echo h($t['testimony']); ?>">
                                    <?php echo h($t['testimony']); ?>
                                </div>
                            </td>
                            <td class="text-center"><?php echo h($t['sort_order']); ?></td>
                            <td class="text-center">
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="id" value="<?php echo h($t['id']); ?>">
                                    <button type="submit" class="btn btn-sm <?php echo $t['is_active'] ? 'btn-success' : 'btn-secondary'; ?>" title="Klik untuk toggle">
                                        <?php echo $t['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <a href="?edit=<?php echo h($t['id']); ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="" class="d-inline" onsubmit="return confirm('Hapus testimoni ini?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo h($t['id']); ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Image preview functionality
    document.addEventListener('DOMContentLoaded', function() {
        const photoInput = document.getElementById('photo_input');
        const photoPreview = document.getElementById('photo_preview');
        const photoPreviewImg = document.getElementById('photo_preview_img');
        
        if (photoInput) {
            photoInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Check file size (5MB max)
                    if (file.size > 5 * 1024 * 1024) {
                        alert('Ukuran file terlalu besar. Maksimal 5MB.');
                        photoInput.value = '';
                        photoPreview.style.display = 'none';
                        return;
                    }
                    
                    // Check file type
                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                    if (!allowedTypes.includes(file.type)) {
                        alert('Format file tidak didukung. Gunakan JPG, PNG, GIF, atau WEBP.');
                        photoInput.value = '';
                        photoPreview.style.display = 'none';
                        return;
                    }
                    
                    // Show preview
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        photoPreviewImg.src = e.target.result;
                        photoPreview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    photoPreview.style.display = 'none';
                }
            });
        }
    });
</script>
</body>
</html>

