<?php
// Database connection (copied from db.php, but using paskerid_db)
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db_prod';

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
if (!(current_user_can('settings_information_manage') || current_user_can('manage_settings'))) { http_response_code(403); echo 'Forbidden'; exit; }

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Create documents directory if it doesn't exist
$documents_dir = $_SERVER['DOCUMENT_ROOT'] . '/documents';
if (!file_exists($documents_dir)) {
    mkdir($documents_dir, 0777, true);
} else {
    // Ensure write permissions
    chmod($documents_dir, 0777);
}

// Also set permissions for parent directories
$parent_dir = dirname($documents_dir);
if (file_exists($parent_dir)) {
    chmod($parent_dir, 0777);
}
$grandparent_dir = dirname($parent_dir);
if (file_exists($grandparent_dir)) {
    chmod($grandparent_dir, 0777);
}

// Test if directory is writable by creating a test file
$test_file = $documents_dir . '/test_write.txt';
if (file_put_contents($test_file, 'test') !== false) {
    unlink($test_file); // Remove test file
    $directory_writable = true;
} else {
    $directory_writable = false;
}

// Initialize variables
$id = $title = $description = $date = $type = $subject = $file_url = $iframe_url = '';
$status = '';
$created_at = $updated_at = '';
$edit_mode = false;
$upload_error = '';

// Handle Add or Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : null;
    $title = $_POST['title'];
    $description = $_POST['description'];
    $date = $_POST['date'];
    $type = $_POST['type'];
    $subject = $_POST['subject'];
    $iframe_url = $_POST['iframe_url'];
    $status = isset($_POST['status']) ? $_POST['status'] : '';

    // Handle file upload
    $file_url = '';
    $upload_debug = array();
    
    if (isset($_FILES['file_upload'])) {
        $upload_debug[] = "FILES array exists";
        $upload_debug[] = "Upload error code: " . $_FILES['file_upload']['error'];
        
        if ($_FILES['file_upload']['error'] === UPLOAD_ERR_OK) {
            $uploaded_file = $_FILES['file_upload'];
            $file_name = $uploaded_file['name'];
            $file_tmp = $uploaded_file['tmp_name'];
            $file_size = $uploaded_file['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Allowed file extensions
            $allowed_extensions = array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt');
            
            if (in_array($file_ext, $allowed_extensions)) {
                if ($file_size <= 10 * 1024 * 1024) { // 10MB limit
                    // Use original filename, but ensure it's unique
                    $original_name = pathinfo($file_name, PATHINFO_FILENAME);
                    $new_file_name = $original_name . '.' . $file_ext;
                    $file_path = $documents_dir . '/' . $new_file_name;
                    
                    // If file already exists, add a number suffix
                    $counter = 1;
                    while (file_exists($file_path)) {
                        $new_file_name = $original_name . '_' . $counter . '.' . $file_ext;
                        $file_path = $documents_dir . '/' . $new_file_name;
                        $counter++;
                    }
                    
                    if (move_uploaded_file($file_tmp, $file_path)) {
                        $file_url = 'https://paskerid.kemnaker.go.id/documents/' . $new_file_name;
                    } else {
                        $upload_error = 'Failed to move uploaded file. Error: ' . error_get_last()['message'] . ' | Directory writable: ' . (is_writable($documents_dir) ? 'Yes' : 'No') . ' | File exists: ' . (file_exists($file_tmp) ? 'Yes' : 'No');
                    }
                } else {
                    $upload_error = 'File size too large. Maximum size is 10MB.';
                }
            } else {
                $upload_error = 'Invalid file type. Allowed types: ' . implode(', ', $allowed_extensions);
            }
        }
    } elseif (isset($_POST['file_url']) && !empty($_POST['file_url'])) {
        // Keep existing file URL if no new file uploaded
        $file_url = $_POST['file_url'];
    }

    if (empty($upload_error)) {
        if (isset($_POST['save'])) {
            // Add new record, set created_at and updated_at to NOW()
            $stmt = $conn->prepare("INSERT INTO information (title, description, date, type, subject, file_url, iframe_url, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->bind_param('ssssssss', $title, $description, $date, $type, $subject, $file_url, $iframe_url, $status);
            $stmt->execute();
            $stmt->close();
            // Redirect to page 1 since new record appears at the top
            header('Location: information_settings.php?page=1');
            exit();
        } elseif (isset($_POST['update'])) {
            // Update record, set updated_at to NOW()
            $stmt = $conn->prepare("UPDATE information SET title=?, description=?, date=?, type=?, subject=?, file_url=?, iframe_url=?, status=?, updated_at=NOW() WHERE id=?");
            $stmt->bind_param('ssssssssi', $title, $description, $date, $type, $subject, $file_url, $iframe_url, $status, $id);
            $stmt->execute();
            $stmt->close();
            // Preserve current page after update
            $current_page_param = isset($_GET['page']) ? '?page=' . intval($_GET['page']) : '';
            header('Location: information_settings.php' . $current_page_param);
            exit();
        }
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    
    // Get file URL before deleting to remove the file
    $result = $conn->query("SELECT file_url FROM information WHERE id=$id");
    if ($result && $row = $result->fetch_assoc()) {
        $file_url = $row['file_url'];
        if (!empty($file_url)) {
            // Extract filename from URL and delete file
            $file_name = basename(parse_url($file_url, PHP_URL_PATH));
            $file_path = $documents_dir . '/' . $file_name;
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
    }
    $conn->query("DELETE FROM information WHERE id=$id");
    
    // After delete, check if current page still exists
    $total_after_delete = $conn->query("SELECT COUNT(*) as total FROM information")->fetch_assoc()['total'];
    $total_pages_after = ceil($total_after_delete / 20);
    
    // If current page is beyond total pages, go to last page
    $redirect_page = min($current_page, max(1, $total_pages_after));
    header('Location: information_settings.php?page=' . $redirect_page);
    exit();
}

// Handle Edit (fetch data)
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $result = $conn->query("SELECT * FROM information WHERE id=$id");
    if ($result && $row = $result->fetch_assoc()) {
        $title = $row['title'];
        $description = $row['description'];
        $date = $row['date'];
        $type = $row['type'];
        $subject = $row['subject'];
        $file_url = $row['file_url'];
        $iframe_url = $row['iframe_url'];
        $status = $row['status'];
        $created_at = $row['created_at'];
        $updated_at = $row['updated_at'];
        $edit_mode = true;
    }
}

// Helper function to build URL with query parameters
function build_url($params = array()) {
    $base_url = 'information_settings.php';
    $query_parts = array();
    foreach ($params as $key => $value) {
        if ($value !== null && $value !== '') {
            $query_parts[] = urlencode($key) . '=' . urlencode($value);
        }
    }
    return $base_url . (!empty($query_parts) ? '?' . implode('&', $query_parts) : '');
}

// Helper function to build pagination URL
function pagination_url($page, $edit = null) {
    $params = array('page' => $page);
    if ($edit !== null) {
        $params['edit'] = $edit;
    }
    return build_url($params);
}

// Pagination settings
$records_per_page = 20;

// Get total number of records first
$total_records_result = $conn->query("SELECT COUNT(*) as total FROM information");
$total_records = $total_records_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Get current page and validate it
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
if ($total_pages > 0 && $current_page > $total_pages) {
    // Redirect to last valid page if current page is beyond total pages
    header('Location: information_settings.php?page=' . $total_pages);
    exit();
} elseif ($total_pages == 0) {
    // If no records, ensure we're on page 1
    $current_page = 1;
}
$offset = ($current_page - 1) * $records_per_page;

// Fetch records for current page
$records = $conn->query("SELECT * FROM information ORDER BY id DESC LIMIT $records_per_page OFFSET $offset");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Information Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .navbar-brand { font-weight: bold; letter-spacing: 1px; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f6f8fa;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        .page-wrapper {
            width: 100%;
            min-height: calc(100vh - 56px);
            padding: 20px;
            box-sizing: border-box;
        }
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 24px;
            width: 100%;
        }
        .modern-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(44,62,80,0.10);
            padding: 32px 28px 24px 28px;
            width: 100%;
        }
        .modern-table-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(44,62,80,0.10);
            padding: 24px 18px 18px 18px;
            width: 100%;
            flex: 1;
            min-height: 400px;
            display: flex;
            flex-direction: column;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .form-grid .full-width {
            grid-column: 1 / -1;
        }
        form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        form input[type="text"],
        form input[type="date"],
        form input[type="datetime-local"],
        form input[type="file"],
        form textarea {
            width: 100%;
            padding: 10px 12px;
            margin-top: 4px;
            margin-bottom: 0;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            background: #f9fafb;
            transition: border 0.2s;
            box-sizing: border-box;
        }
        form input:focus, form textarea:focus {
            border: 1.5px solid #1976d2;
            outline: none;
            background: #fff;
        }
        form button, .btn {
            background: linear-gradient(90deg, #1976d2 0%, #1565c0 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 22px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            margin-right: 8px;
            transition: background 0.2s, box-shadow 0.2s;
            box-shadow: 0 1px 4px rgba(44,62,80,0.08);
        }
        form button:hover, .btn:hover {
            background: linear-gradient(90deg, #1565c0 0%, #1976d2 100%);
        }
        .btn-cancel {
            background: #e0e5ea;
            color: #2d3e50;
        }
        .btn-cancel:hover {
            background: #cfd8df;
        }
        .error-message {
            color: #d32f2f;
            background: #ffebee;
            border: 1px solid #ffcdd2;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 18px;
            font-size: 0.9rem;
            width: 100%;
        }
        .file-info {
            background: #e8f5e9;
            border: 1px solid #c8e6c9;
            border-radius: 8px;
            padding: 12px;
            margin-top: 8px;
            margin-bottom: 0;
            font-size: 0.9rem;
            color: #2e7d32;
        }
        .current-file {
            background: #fff3e0;
            border: 1px solid #ffcc02;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 0;
            font-size: 0.9rem;
            color: #f57c00;
        }
        .table-container {
            flex: 1;
            min-height: 0;
            overflow: auto;
            width: 100%;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            min-width: 1000px;
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(44,62,80,0.10);
            margin-bottom: 0;
        }
        th, td {
            border-bottom: 1px solid #e5e7eb;
            padding: 12px 10px;
            text-align: left;
        }
        th {
            background: #f3f4f6;
            font-weight: 600;
        }
        tr:last-child td {
            border-bottom: none;
        }
        tr:hover {
            background: #f6f8fa;
        }
        .actions a {
            text-decoration: none;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 0.97rem;
            margin-right: 6px;
            transition: background 0.2s, color 0.2s;
        }
        .actions a:first-child {
            background: #e3f2fd;
            color: #1976d2;
        }
        .actions a:first-child:hover {
            background: #bbdefb;
            color: #0d47a1;
        }
        .actions a:last-child {
            background: #ffebee;
            color: #c62828;
        }
        .actions a:last-child:hover {
            background: #ffcdd2;
            color: #b71c1c;
        }
        .pagination-container {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            margin-top: 20px;
            padding: 20px;
            gap: 12px;
        }
        .pagination {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .pagination li {
            display: inline-block;
        }
        .pagination a,
        .pagination span {
            display: inline-block;
            padding: 8px 14px;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s;
            border: 1px solid #d1d5db;
            color: #374151;
            background: #fff;
        }
        .pagination a:hover {
            background: #f3f4f6;
            border-color: #9ca3af;
            color: #1f2937;
        }
        .pagination .active span {
            background: #1976d2;
            color: #fff;
            border-color: #1976d2;
            cursor: default;
        }
        .pagination .disabled span {
            background: #f9fafb;
            color: #9ca3af;
            border-color: #e5e7eb;
            cursor: not-allowed;
        }
        .pagination-info {
            margin: 0 16px;
            color: #6b7280;
            font-size: 0.95rem;
        }
        @media (max-width: 900px) {
            .page-wrapper {
                padding: 16px;
            }
            .main-content { 
                gap: 16px; 
            }
            .modern-card { 
                padding: 24px 20px 20px 20px; 
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            .modern-table-card { 
                max-height: calc(100vh - 300px);
                padding: 20px 16px 16px 16px; 
            }
            table { 
                font-size: 0.97rem; 
            }
        }
        @media (max-width: 600px) {
            .page-wrapper {
                padding: 12px;
            }
            .main-content { 
                gap: 12px; 
            }
            .modern-card, .modern-table-card { 
                padding: 16px 12px 12px 12px; 
            }
            .modern-table-card {
                max-height: calc(100vh - 350px);
                min-height: 300px;
            }
            .form-grid {
                gap: 16px;
            }
            th, td { 
                padding: 8px 6px; 
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body class="bg-light">
      <?php include 'navbar.php'; ?>
      <!-- End Navigation Bar -->
    <div class="page-wrapper">
        <div class="main-content">
            <div class="modern-card">
                <?php if (!empty($upload_error)): ?>
                    <div class="error-message">
                        <strong>Upload Error:</strong> <?php echo htmlspecialchars($upload_error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($upload_debug)): ?>
                    <div class="file-info" style="background:#e3f2fd;border-color:#2196f3;color:#1976d2;">
                        <strong>Debug Information:</strong><br>
                        <?php foreach ($upload_debug as $debug): ?>
                            <?php echo htmlspecialchars($debug); ?><br>
                        <?php endforeach; ?>
                        Directory: <?php echo htmlspecialchars($documents_dir); ?><br>
                        Directory Writable: <?php echo is_writable($documents_dir) ? 'Yes' : 'No'; ?><br>
                        Directory Test Write: <?php echo isset($directory_writable) && $directory_writable ? 'Success' : 'Failed'; ?>
                    </div>
                <?php endif; ?>
                
                <form method="post" enctype="multipart/form-data">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
                    <?php endif; ?>
                    <div class="form-grid">
                        <label>Title:
                            <input type="text" name="title" value="<?php echo htmlspecialchars($title); ?>" required>
                        </label>
                        <label>Date:
                            <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>" required>
                        </label>
                        <label>Type:
                            <input type="text" name="type" value="<?php echo htmlspecialchars($type); ?>" required>
                        </label>
                        <label>Subject:
                            <input type="text" name="subject" value="<?php echo htmlspecialchars($subject); ?>" required>
                        </label>
                        <label>Status:
                            <input type="text" name="status" value="<?php echo htmlspecialchars($status); ?>" required>
                        </label>
                        <label class="full-width">Description:
                            <textarea name="description" rows="3" required><?php echo htmlspecialchars($description); ?></textarea>
                        </label>
                        <label class="full-width">File Upload:
                            <input type="file" name="file_upload" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt">
                            <div class="file-info">
                                <strong>Allowed file types:</strong> PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT<br>
                                <strong>Maximum file size:</strong> 10MB
                            </div>
                        </label>
                        
                        <?php if ($edit_mode && !empty($file_url)): ?>
                            <div class="current-file full-width">
                                <strong>Current file:</strong> <?php echo htmlspecialchars($file_url); ?><br>
                                <small>Upload a new file to replace the current one, or leave empty to keep the existing file.</small>
                            </div>
                            <input type="hidden" name="file_url" value="<?php echo htmlspecialchars($file_url); ?>">
                        <?php endif; ?>
                        
                        <label class="full-width">Iframe URL:
                            <input type="text" name="iframe_url" value="<?php echo htmlspecialchars($iframe_url); ?>">
                        </label>
                        <?php if ($edit_mode): ?>
                            <label>Created At:
                                <input type="text" value="<?php echo htmlspecialchars($created_at); ?>" readonly>
                            </label>
                            <label>Updated At:
                                <input type="text" value="<?php echo htmlspecialchars($updated_at); ?>" readonly>
                            </label>
                        <?php endif; ?>
                    </div>
                    <div style="margin-top: 20px;">
                        <?php if ($edit_mode): ?>
                            <button type="submit" name="update">Update</button>
                            <a href="<?php echo build_url(array('page' => $current_page)); ?>" class="btn btn-cancel">Cancel</a>
                        <?php else: ?>
                            <button type="submit" name="save">Add</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <div class="modern-table-card">
                <div class="table-container">
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>File URL</th>
                        <th>Iframe URL</th>
                        <th>Created At</th>
                        <th>Updated At</th>
                        <th>Actions</th>
                    </tr>
                    <?php if ($records && $records->num_rows > 0): ?>
                        <?php while ($row = $records->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($row['description'])); ?></td>
                                <td><?php echo $row['date']; ?></td>
                                <td><?php echo htmlspecialchars($row['type']); ?></td>
                                <td><?php echo htmlspecialchars($row['subject']); ?></td>
                                <td><?php echo htmlspecialchars($row['status']); ?></td>
                                <td><?php echo htmlspecialchars($row['file_url']); ?></td>
                                <td><?php echo htmlspecialchars($row['iframe_url']); ?></td>
                                <td><?php echo $row['created_at']; ?></td>
                                <td><?php echo $row['updated_at']; ?></td>
                                <td class="actions">
                                    <?php if (!empty($row['file_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($row['file_url']); ?>" target="_blank" class="btn" style="background:#e8f5e9;color:#388e3c;margin-bottom:4px;">See Document</a>
                                    <?php endif; ?>
                                    <a href="<?php echo build_url(array('edit' => $row['id'], 'page' => $current_page)); ?>">Edit</a>
                                    <a href="<?php echo build_url(array('delete' => $row['id'], 'page' => $current_page)); ?>" onclick="return confirm('Are you sure you want to delete this record?');">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="12">No records found.</td></tr>
                    <?php endif; ?>
                </table>
                </div>
                <div class="pagination-container">
                    <?php if ($total_pages > 1): ?>
                    <ul class="pagination">
                        <?php
                        // Get edit parameter if exists
                        $edit_param = isset($_GET['edit']) ? intval($_GET['edit']) : null;
                        ?>
                        <?php if ($current_page > 1): ?>
                            <li><a href="<?php echo pagination_url($current_page - 1, $edit_param); ?>">&laquo; Prev</a></li>
                        <?php else: ?>
                            <li class="disabled"><span>&laquo; Prev</span></li>
                        <?php endif; ?>
                        
                        <?php
                        // Calculate page range to show
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        // Show first page if not in range
                        if ($start_page > 1): ?>
                            <li><a href="<?php echo pagination_url(1, $edit_param); ?>">1</a></li>
                            <?php if ($start_page > 2): ?>
                                <li><span>...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <?php if ($i == $current_page): ?>
                                <li class="active"><span><?php echo $i; ?></span></li>
                            <?php else: ?>
                                <li><a href="<?php echo pagination_url($i, $edit_param); ?>"><?php echo $i; ?></a></li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php
                        // Show last page if not in range
                        if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li><span>...</span></li>
                            <?php endif; ?>
                            <li><a href="<?php echo pagination_url($total_pages, $edit_param); ?>"><?php echo $total_pages; ?></a></li>
                        <?php endif; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <li><a href="<?php echo pagination_url($current_page + 1, $edit_param); ?>">Next &raquo;</a></li>
                        <?php else: ?>
                            <li class="disabled"><span>Next &raquo;</span></li>
                        <?php endif; ?>
                    </ul>
                    <?php endif; ?>
                    <?php if ($total_records > 0): ?>
                    <div class="pagination-info">
                        Showing <?php echo ($offset + 1); ?> - <?php echo min($offset + $records_per_page, $total_records); ?> of <?php echo $total_records; ?> records
                    </div>
                    <?php else: ?>
                    <div class="pagination-info">
                        No records found
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?> 