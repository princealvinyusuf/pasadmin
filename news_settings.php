<?php
// Use the same DB connection method as db.php, but connect to 'paskerid_db'
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db_prod';

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
if (!(current_user_can('settings_news_manage') || current_user_can('manage_settings'))) { http_response_code(403); echo 'Forbidden'; exit; }

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Create images directory if it doesn't exist
$upload_base_dir = $_SERVER['DOCUMENT_ROOT'] . '/paskerid/public/images/contents/';
$db_image_path_prefix = 'images/contents/';

if (!file_exists($upload_base_dir)) {
    if (!mkdir($upload_base_dir, 0755, true)) {
        error_log("Failed to create upload directory: " . $upload_base_dir);
    }
}

// Helper function to get correct image URL for display
function getImageDisplayUrl($image_path) {
    if (empty($image_path)) return '';

    // If the image path starts with /paskerid/public/images/contents/, convert to /images/contents/
    if (strpos($image_path, '/paskerid/public/images/contents/') === 0) {
        return str_replace('/paskerid/public/images/contents/', '/images/contents/', $image_path);
    }

    // If it's in the format images/contents/filename.jpg (from DB), prepend /images/contents/
    if (strpos($image_path, 'images/contents/') === 0) {
        return '/' . $image_path;
    }

    // If the image path starts with /images/contents/ already, just return it
    if (strpos($image_path, '/images/contents/') === 0) {
        return $image_path;
    }

    return $image_path;
}

// Helper function to get correct file system path for deletion or access
function getImageFilePath($image_path) {
    if (empty($image_path)) return '';
    
    // If the image_path from DB is like 'images/contents/filename.jpg'
    // prepend DOCUMENT_ROOT and the application base path
    return $_SERVER['DOCUMENT_ROOT'] . '/paskerid/public/' . $image_path;
}

// Handle Create
if (isset($_POST['add'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $date = $_POST['date'];
    $author = $_POST['author'];
    
    $image_path_for_db = ''; // Path to store in DB
    $target_file_system_path = ''; // Path for file system operations

    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $file_info = pathinfo($_FILES['image']['name']);
        $extension = strtolower($file_info['extension']);
        
        // Check if file is an image
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($extension, $allowed_extensions)) {
            // Generate unique filename
            $filename = uniqid() . '_' . time() . '.' . $extension;
            $target_file_system_path = $upload_base_dir . $filename;
            $image_path_for_db = $db_image_path_prefix . $filename;

            error_log("Attempting to upload file: " . $_FILES['image']['tmp_name'] . " to " . $target_file_system_path);
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file_system_path)) {
                error_log("File uploaded successfully to: " . $target_file_system_path);
                // Correctly set the path for database storage
                $image_path_for_db = $db_image_path_prefix . $filename; 
            } else {
                error_log("Failed to move uploaded file. Error: " . error_get_last()['message']);
                $image_path_for_db = ''; // Reset if upload fails
            }
        } else {
            error_log("Invalid file extension: " . $extension);
        }
    } else if (isset($_FILES['image'])) {
        error_log("File upload error: " . $_FILES['image']['error']);
    }
    
    $stmt = $conn->prepare("INSERT INTO news (title, content, image_url, date, author) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $title, $content, $image_path_for_db, $date, $author);
    
    // Debug database insertion
    error_log("Inserting into database - Title: " . $title);
    error_log("Inserting into database - Image path: " . $image_path_for_db);
    
    $result = $stmt->execute();
    if (!$result) {
        error_log("Database insert failed: " . $stmt->error);
    } else {
        error_log("Database insert successful. Affected rows: " . $stmt->affected_rows);
    }
    
    $stmt->close();
    header("Location: news_settings.php");
    exit();
}

// Handle Update
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $title = $_POST['title'];
    $content = $_POST['content'];
    $date = $_POST['date'];
    $author = $_POST['author'];
    
    // Get current image path from DB
    $current_image_db_path = '';
    $result = $conn->query("SELECT image_url FROM news WHERE id=$id");
    if ($row = $result->fetch_assoc()) {
        $current_image_db_path = $row['image_url'];
    }
    
    $image_path_for_db = $current_image_db_path; // Default to current image
    $target_file_system_path = ''; // Path for file system operations

    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $file_info = pathinfo($_FILES['image']['name']);
        $extension = strtolower($file_info['extension']);
        
        // Check if file is an image
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($extension, $allowed_extensions)) {
            // Generate unique filename
            $filename = uniqid() . '_' . time() . '.' . $extension;
            $target_file_system_path = $upload_base_dir . $filename;
            $image_path_for_db = $db_image_path_prefix . $filename;

            error_log("Attempting to update file: " . $_FILES['image']['tmp_name'] . " to " . $target_file_system_path);
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file_system_path)) {
                error_log("New file uploaded successfully to: " . $target_file_system_path);
                // Delete old image if it exists and a new one was uploaded
                if ($current_image_db_path && file_exists(getImageFilePath($current_image_db_path))) {
                    error_log("Deleting old image: " . getImageFilePath($current_image_db_path));
                    unlink(getImageFilePath($current_image_db_path));
                }
            } else {
                error_log("Failed to move uploaded file during update. Error: " . error_get_last()['message']);
                $image_path_for_db = $current_image_db_path; // Keep old image path if new upload fails
            }
        } else {
            error_log("Invalid file extension during update: " . $extension);
        }
    } else if (isset($_FILES['image']) && $_FILES['image']['error'] != UPLOAD_ERR_NO_FILE) {
        error_log("File upload error during update: " . $_FILES['image']['error']);
    }
    
    $stmt = $conn->prepare("UPDATE news SET title=?, content=?, image_url=?, date=?, author=? WHERE id=?");
    $stmt->bind_param("sssssi", $title, $content, $image_path_for_db, $date, $author, $id);
    
    // Debug database update
    error_log("Updating database - ID: " . $id . ", Image path: " . $image_path_for_db);
    
    $result = $stmt->execute();
    if (!$result) {
        error_log("Database update failed: " . $stmt->error);
    } else {
        error_log("Database update successful. Affected rows: " . $stmt->affected_rows);
    }
    
    $stmt->close();
    header("Location: news_settings.php");
    exit();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Get image path before deleting
    $result = $conn->query("SELECT image_url FROM news WHERE id=$id");
    if ($row = $result->fetch_assoc()) {
        $image_path_for_db = $row['image_url'];
        // Delete image file if it exists
        if ($image_path_for_db && file_exists(getImageFilePath($image_path_for_db))) {
            error_log("Deleting image file: " . getImageFilePath($image_path_for_db));
            unlink(getImageFilePath($image_path_for_db));
        } else {
            error_log("Image file not found for deletion or path is empty: " . $image_path_for_db);
        }
    }
    
    $stmt = $conn->prepare("DELETE FROM news WHERE id=?");
    $stmt->bind_param("i", $id);
    
    // Debug database delete
    error_log("Deleting from database - ID: " . $id);
    
    $result = $stmt->execute();
    if (!$result) {
        error_log("Database delete failed: " . $stmt->error);
    } else {
        error_log("Database delete successful. Affected rows: " . $stmt->affected_rows);
    }
    
    $stmt->close();
    header("Location: news_settings.php");
    exit();
}

// Handle Edit (fetch data)
$edit_news = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $result = $conn->query("SELECT * FROM news WHERE id=$id");
    $edit_news = $result->fetch_assoc();
}

// Fetch all news
$news = $conn->query("SELECT * FROM news ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .navbar-brand { font-weight: bold; letter-spacing: 1px; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f6f8fa;
            margin: 0;
            padding: 0;
        }
       
        h2, h3 {
            text-align: center;
            color: #222;
        }
        form {
            background: #f9fafb;
            border-radius: 8px;
            padding: 24px 20px 16px 20px;
            margin-bottom: 32px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        label {
            display: block;
            margin-bottom: 14px;
            color: #333;
            font-weight: 500;
        }
        input[type="text"], input[type="date"], textarea, input[type="file"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
            margin-top: 4px;
            background: #fff;
            transition: border 0.2s;
        }
        input[type="file"] {
            padding: 8px 12px;
            background: #f8fafc;
        }
        input[type="text"]:focus, input[type="date"]:focus, textarea:focus, input[type="file"]:focus {
            border: 1.5px solid #2563eb;
            outline: none;
        }
        textarea {
            min-height: 80px;
            resize: vertical;
        }
        .btn {
            display: inline-block;
            padding: 8px 22px;
            border: none;
            border-radius: 6px;
            background: #2563eb;
            color: #fff;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            margin-right: 8px;
            margin-top: 8px;
            transition: background 0.2s;
            text-decoration: none;
        }
        .btn:hover {
            background: #1d4ed8;
        }
        .btn.cancel {
            background: #e5e7eb;
            color: #222;
        }
        .btn.cancel:hover {
            background: #d1d5db;
        }
        .btn.delete {
            background: #ef4444;
        }
        .btn.delete:hover {
            background: #b91c1c;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        th, td {
            padding: 12px 10px;
            text-align: left;
        }
        th {
            background: #f1f5f9;
            color: #222;
            font-weight: 600;
        }
        tr:nth-child(even) {
            background: #f9fafb;
        }
        tr:hover {
            background: #e0e7ef;
        }
        td {
            vertical-align: top;
        }
        .actions a {
            margin-right: 8px;
        }
        .news-image {
            max-width: 100px;
            max-height: 100px;
            border-radius: 4px;
            object-fit: cover;
        }
        .current-image {
            margin: 10px 0;
            padding: 10px;
            background: #f1f5f9;
            border-radius: 4px;
        }
        .current-image img {
            max-width: 150px;
            max-height: 150px;
            border-radius: 4px;
            margin-right: 10px;
        }
        @media (max-width: 700px) {
            .container { padding: 8px; }
            form { padding: 12px 6px; }
            th, td { font-size: 0.95rem; padding: 8px 4px; }
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <!-- End Navigation Bar -->
    <div class="container">
        <h2>News Settings</h2>
        <h3><?php echo $edit_news ? 'Edit News' : 'Add News'; ?></h3>
        <form method="post" enctype="multipart/form-data">
            <?php if ($edit_news): ?>
                <input type="hidden" name="id" value="<?php echo $edit_news['id']; ?>">
            <?php endif; ?>
            <label>Title:
                <input type="text" name="title" required value="<?php echo $edit_news['title'] ?? ''; ?>">
            </label>
            <label>Content:
                <textarea name="content" required><?php echo $edit_news['content'] ?? ''; ?></textarea>
            </label>
            <label>Image:
                <input type="file" name="image" accept="image/*">
                <?php if ($edit_news && $edit_news['image_url']): ?>
                    <div class="current-image">
                        <strong>Current Image:</strong><br>
                        <img src="<?php echo htmlspecialchars(getImageDisplayUrl($edit_news['image_url'])); ?>" alt="Current image">
                        <small><?php echo htmlspecialchars($edit_news['image_url']); ?></small>
                    </div>
                <?php endif; ?>
                <small class="text-muted">Leave empty to keep current image (when editing)</small>
            </label>
            <label>Date:
                <input type="date" name="date" required value="<?php echo $edit_news['date'] ?? ''; ?>">
            </label>
            <label>Author:
                <input type="text" name="author" value="<?php echo $edit_news['author'] ?? ''; ?>">
            </label>
            <button type="submit" class="btn" name="<?php echo $edit_news ? 'update' : 'add'; ?>"><?php echo $edit_news ? 'Update' : 'Add'; ?></button>
            <?php if ($edit_news): ?>
                <a href="news_settings.php" class="btn cancel">Cancel</a>
            <?php endif; ?>
        </form>
        <h3>All News</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Content</th>
                <th>Image</th>
                <th>Date</th>
                <th>Author</th>
                <th>Created At</th>
                <th>Updated At</th>
                <th>Actions</th>
            </tr>
            <?php while ($row = $news->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['title']); ?></td>
                <td><?php echo nl2br(htmlspecialchars($row['content'])); ?></td>
                <td>
                    <?php if ($row['image_url']): ?>
                        <img src="<?php echo htmlspecialchars(getImageDisplayUrl($row['image_url'])); ?>" alt="News image" class="news-image">
                        <br><small><?php echo htmlspecialchars($row['image_url']); ?></small>
                    <?php else: ?>
                        <span class="text-muted">No image</span>
                    <?php endif; ?>
                </td>
                <td><?php echo $row['date']; ?></td>
                <td><?php echo htmlspecialchars($row['author']); ?></td>
                <td><?php echo $row['created_at']; ?></td>
                <td><?php echo $row['updated_at']; ?></td>
                <td class="actions">
                    <a href="news_settings.php?edit=<?php echo $row['id']; ?>" class="btn">Edit</a>
                    <a href="news_settings.php?delete=<?php echo $row['id']; ?>" class="btn delete" onclick="return confirm('Delete this news?');">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?> 