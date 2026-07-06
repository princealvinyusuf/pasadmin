<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

if (!(current_user_can('settings_topic_data_manage') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db_prod';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

function app_base_url() {
    $default = '/pasadmin/';
    $candidates = [
        $_SERVER['REQUEST_URI'] ?? '',
        $_SERVER['PHP_SELF'] ?? '',
        $_SERVER['SCRIPT_NAME'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        $path = parse_url((string)$candidate, PHP_URL_PATH);
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

function is_absolute_url($value) {
    return filter_var($value, FILTER_VALIDATE_URL) !== false;
}

function ensure_dir($path) {
    if (is_dir($path)) {
        return true;
    }
    if (!@mkdir($path, 0777, true) && !is_dir($path)) {
        return false;
    }
    @chmod($path, 0777);
    return true;
}

function unique_filename($dir, $originalName) {
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $nameOnly = pathinfo($originalName, PATHINFO_FILENAME);
    $safeBase = preg_replace('/[^A-Za-z0-9_-]/', '_', $nameOnly);
    if ($safeBase === null || $safeBase === '') {
        $safeBase = 'file';
    }
    $candidate = $safeBase . ($extension !== '' ? ('.' . $extension) : '');
    $index = 1;

    while (file_exists($dir . DIRECTORY_SEPARATOR . $candidate)) {
        $candidate = $safeBase . '_' . $index . ($extension !== '' ? ('.' . $extension) : '');
        $index++;
    }

    return $candidate;
}

function uploaded_ok($key) {
    return isset($_FILES[$key]) && isset($_FILES[$key]['error']) && $_FILES[$key]['error'] === UPLOAD_ERR_OK;
}

function resolve_public_root() {
    $candidates = [];
    $docRoot = trim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($docRoot !== '') {
        $candidates[] = $docRoot;
    }

    // Fallback for this repo layout: /paskerid/pasadmin and /paskerid/public
    $repoPublic = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public';
    $candidates[] = $repoPublic;

    foreach ($candidates as $candidate) {
        $normalized = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate), DIRECTORY_SEPARATOR);
        if ($normalized === '') {
            continue;
        }
        if (is_dir($normalized)) {
            return $normalized;
        }
    }

    // Last resort: use the first candidate even if it must be created later.
    return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ($candidates[0] ?? $repoPublic)), DIRECTORY_SEPARATOR);
}

function try_move_upload($tmpName, $targetPath, &$errorMessage) {
    if (!is_uploaded_file($tmpName)) {
        $errorMessage = 'Temporary upload file is not recognized by PHP.';
        return false;
    }

    if (@move_uploaded_file($tmpName, $targetPath)) {
        return true;
    }

    $lastError = error_get_last();
    $errorMessage = $lastError && isset($lastError['message']) ? $lastError['message'] : 'Unknown upload move error.';
    return false;
}

$publicRoot = resolve_public_root();
$documentsDir = $publicRoot . DIRECTORY_SEPARATOR . 'documents';
$imageDir = $publicRoot . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'topic_data';
$documentsDirReady = ensure_dir($documentsDir);
$imageDirReady = ensure_dir($imageDir);

$appBaseUrl = app_base_url();
$selfPath = $appBaseUrl . 'topic_data_settings';

$title = '';
$description = '';
$date = '';
$fileUrl = '';
$imageUrl = '';
$editId = 0;
$editMode = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $editId = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $date = trim($_POST['date'] ?? '');

    $existingFileUrl = trim($_POST['existing_file_url'] ?? '');
    $externalFileUrl = trim($_POST['external_file_url'] ?? '');
    $existingImageUrl = trim($_POST['existing_image_url'] ?? '');
    $externalImageUrl = trim($_POST['external_image_url'] ?? '');

    if ($title === '') {
        $errors[] = 'Title is required.';
    }

    $resolvedFileUrl = $existingFileUrl;
    if ($externalFileUrl !== '') {
        $resolvedFileUrl = $externalFileUrl;
    }

    $resolvedImageUrl = $existingImageUrl;
    if ($externalImageUrl !== '') {
        $resolvedImageUrl = $externalImageUrl;
    }

    if (uploaded_ok('file_upload')) {
        $upload = $_FILES['file_upload'];
        $extension = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'];
        if (!in_array($extension, $allowedExtensions, true)) {
            $errors[] = 'Invalid document type. Allowed: ' . implode(', ', $allowedExtensions);
        } elseif (($upload['size'] ?? 0) > (20 * 1024 * 1024)) {
            $errors[] = 'Document is too large (max 20MB).';
        } elseif (!$documentsDirReady || !is_dir($documentsDir) || !is_writable($documentsDir)) {
            $errors[] = 'Documents directory is not writable: ' . $documentsDir;
        } else {
            $newName = unique_filename($documentsDir, $upload['name']);
            $target = $documentsDir . DIRECTORY_SEPARATOR . $newName;
            $uploadError = '';
            if (!try_move_upload($upload['tmp_name'], $target, $uploadError)) {
                $errors[] = 'Failed to upload document file. Target: ' . $target . ' | Temp: ' . ($upload['tmp_name'] ?? '-') . ' | Error: ' . $uploadError;
            } else {
                $resolvedFileUrl = $newName;
            }
        }
    }

    if (uploaded_ok('image_upload')) {
        $upload = $_FILES['image_upload'];
        $extension = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
        $allowedImageExtensions = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'];
        if (!in_array($extension, $allowedImageExtensions, true)) {
            $errors[] = 'Invalid image type. Allowed: ' . implode(', ', $allowedImageExtensions);
        } elseif (($upload['size'] ?? 0) > (5 * 1024 * 1024)) {
            $errors[] = 'Image is too large (max 5MB).';
        } elseif (!$imageDirReady || !is_dir($imageDir) || !is_writable($imageDir)) {
            $errors[] = 'Image directory is not writable: ' . $imageDir;
        } else {
            $newName = unique_filename($imageDir, $upload['name']);
            $target = $imageDir . DIRECTORY_SEPARATOR . $newName;
            $uploadError = '';
            if (!try_move_upload($upload['tmp_name'], $target, $uploadError)) {
                $errors[] = 'Failed to upload image file. Target: ' . $target . ' | Temp: ' . ($upload['tmp_name'] ?? '-') . ' | Error: ' . $uploadError;
            } else {
                $resolvedImageUrl = 'images/topic_data/' . $newName;
            }
        }
    }

    if (empty($errors)) {
        $dateValue = $date !== '' ? $date : null;

        if (isset($_POST['save'])) {
            $stmt = $conn->prepare('INSERT INTO topic_data (title, description, date, file_url, image_url, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->bind_param('sssss', $title, $description, $dateValue, $resolvedFileUrl, $resolvedImageUrl);
            $stmt->execute();
            $stmt->close();
            header('Location: ' . $selfPath . '?msg=created');
            exit;
        }

        if (isset($_POST['update']) && $editId > 0) {
            $stmt = $conn->prepare('UPDATE topic_data SET title=?, description=?, date=?, file_url=?, image_url=?, updated_at=NOW() WHERE id=?');
            $stmt->bind_param('sssssi', $title, $description, $dateValue, $resolvedFileUrl, $resolvedImageUrl, $editId);
            $stmt->execute();
            $stmt->close();
            $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            header('Location: ' . $selfPath . '?page=' . $currentPage . '&msg=updated');
            exit;
        }
    }
}

if (isset($_GET['delete'])) {
    $deleteId = intval($_GET['delete']);
    if ($deleteId > 0) {
        $stmt = $conn->prepare('SELECT file_url, image_url FROM topic_data WHERE id=? LIMIT 1');
        $stmt->bind_param('i', $deleteId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($row) {
            $existingFile = trim($row['file_url'] ?? '');
            if ($existingFile !== '' && !is_absolute_url($existingFile)) {
                $filePath = $documentsDir . DIRECTORY_SEPARATOR . basename($existingFile);
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            $existingImage = trim($row['image_url'] ?? '');
            if ($existingImage !== '' && !is_absolute_url($existingImage)) {
                $imagePath = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $existingImage), DIRECTORY_SEPARATOR);
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
        }

        $stmt = $conn->prepare('DELETE FROM topic_data WHERE id=?');
        $stmt->bind_param('i', $deleteId);
        $stmt->execute();
        $stmt->close();
    }

    $currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    header('Location: ' . $selfPath . '?page=' . $currentPage . '&msg=deleted');
    exit;
}

if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    if ($editId > 0) {
        $stmt = $conn->prepare('SELECT * FROM topic_data WHERE id=? LIMIT 1');
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($row) {
            $title = $row['title'] ?? '';
            $description = $row['description'] ?? '';
            $date = $row['date'] ?? '';
            $fileUrl = $row['file_url'] ?? '';
            $imageUrl = $row['image_url'] ?? '';
            $editMode = true;
        }
    }
}

$recordsPerPage = 20;
$countResult = $conn->query('SELECT COUNT(*) AS total FROM topic_data');
$totalRecords = ($countResult && $countResult->num_rows > 0) ? intval($countResult->fetch_assoc()['total']) : 0;
$totalPages = max(1, (int)ceil($totalRecords / $recordsPerPage));
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}
$offset = ($currentPage - 1) * $recordsPerPage;

$stmt = $conn->prepare('SELECT * FROM topic_data ORDER BY id DESC LIMIT ? OFFSET ?');
$stmt->bind_param('ii', $recordsPerPage, $offset);
$stmt->execute();
$records = $stmt->get_result();
$stmt->close();

$flash = trim($_GET['msg'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Topic Data Settings</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container py-4">
        <h2 class="mb-3">Topic Data Settings</h2>
        <p class="text-muted mb-4">Manage cards shown in the "Topik Data" section on the homepage.</p>

        <?php if ($flash === 'created'): ?>
            <div class="alert alert-success">Topic data created.</div>
        <?php elseif ($flash === 'updated'): ?>
            <div class="alert alert-success">Topic data updated.</div>
        <?php elseif ($flash === 'deleted'): ?>
            <div class="alert alert-success">Topic data deleted.</div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header"><?php echo $editMode ? 'Edit Topic Data' : 'Add Topic Data'; ?></div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?php echo intval($editId); ?>">
                    <input type="hidden" name="existing_file_url" value="<?php echo htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="existing_image_url" value="<?php echo htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($date, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Image Upload</label>
                            <input type="file" name="image_upload" class="form-control" accept=".png,.jpg,.jpeg,.webp,.gif,.svg">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Document Upload</label>
                            <input type="file" name="file_upload" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv">
                            <small class="text-muted">Used for download button (max 20MB).</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">External Document URL</label>
                            <input type="url" name="external_file_url" class="form-control" placeholder="https://..." value="">
                            <small class="text-muted">Optional override if file is hosted externally.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">External Image URL</label>
                            <input type="url" name="external_image_url" class="form-control" placeholder="https://..." value="">
                            <small class="text-muted">Optional override for image from URL.</small>
                        </div>
                    </div>

                    <?php if ($editMode && $imageUrl !== ''): ?>
                        <div class="mt-3">
                            <div class="small text-muted mb-1">Current Image</div>
                            <?php
                                $previewImage = is_absolute_url($imageUrl) ? $imageUrl : ('/' . ltrim($imageUrl, '/'));
                            ?>
                            <img src="<?php echo htmlspecialchars($previewImage, ENT_QUOTES, 'UTF-8'); ?>" alt="Current image" style="max-width:72px; max-height:72px; object-fit:contain;">
                        </div>
                    <?php endif; ?>

                    <?php if ($editMode && $fileUrl !== ''): ?>
                        <div class="mt-2">
                            <span class="small text-muted">Current file: </span>
                            <span class="small fw-semibold"><?php echo htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="mt-4 d-flex gap-2">
                        <?php if ($editMode): ?>
                            <button type="submit" name="update" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Update</button>
                            <a href="<?php echo htmlspecialchars($selfPath, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary">Cancel</a>
                        <?php else: ?>
                            <button type="submit" name="save" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i>Save</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Topic Data List</span>
                <span class="badge text-bg-secondary"><?php echo $totalRecords; ?> records</span>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th style="width: 70px;">ID</th>
                            <th style="width: 90px;">Image</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th style="width: 130px;">Date</th>
                            <th>File URL</th>
                            <th style="width: 170px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($records && $records->num_rows > 0): ?>
                        <?php while ($row = $records->fetch_assoc()): ?>
                            <?php
                                $itemId = intval($row['id']);
                                $itemImage = trim($row['image_url'] ?? '');
                                $itemFile = trim($row['file_url'] ?? '');
                                $displayImage = $itemImage !== '' ? (is_absolute_url($itemImage) ? $itemImage : ('/' . ltrim($itemImage, '/'))) : '';
                                $downloadUrl = '';
                                if ($itemFile !== '') {
                                    $downloadUrl = is_absolute_url($itemFile) ? $itemFile : ('/topic-data/download/' . $itemId);
                                }
                            ?>
                            <tr>
                                <td><?php echo $itemId; ?></td>
                                <td>
                                    <?php if ($displayImage !== ''): ?>
                                        <img src="<?php echo htmlspecialchars($displayImage, ENT_QUOTES, 'UTF-8'); ?>" alt="icon" style="width:48px;height:48px;object-fit:contain;">
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($row['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($row['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($row['date'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php if ($downloadUrl !== ''): ?>
                                        <a href="<?php echo htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Open</a>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars($selfPath . '?page=' . $currentPage . '&edit=' . $itemId, ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <a
                                        class="btn btn-sm btn-outline-danger"
                                        href="<?php echo htmlspecialchars($selfPath . '?page=' . $currentPage . '&delete=' . $itemId, ENT_QUOTES, 'UTF-8'); ?>"
                                        onclick="return confirm('Delete this topic data?');"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No records found.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPages > 1): ?>
                <div class="card-footer">
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                                <li class="page-item <?php echo $page === $currentPage ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo htmlspecialchars($selfPath . '?page=' . $page, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $page; ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
