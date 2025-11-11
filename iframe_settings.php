<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';
if (!(current_user_can('settings_iframe_manage') || current_user_can('manage_settings'))) { http_response_code(403); echo 'Forbidden'; exit; }

// Create table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS iframes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    logo_url VARCHAR(500) NOT NULL,
    external_link VARCHAR(500) NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle Create
if (isset($_POST['add'])) {
    $slug = $_POST['slug'];
    $title = $_POST['title'];
    $logo_url = $_POST['logo_url'];
    $external_link = $_POST['external_link'];
    $description = $_POST['description'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $stmt = $conn->prepare("INSERT INTO iframes (slug, title, logo_url, external_link, description, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param("sssssi", $slug, $title, $logo_url, $external_link, $description, $is_active);
    
    if ($stmt->execute()) {
        header("Location: iframe_settings.php?success=1");
        exit();
    } else {
        $error = "Error: " . $stmt->error;
    }
    $stmt->close();
}

// Handle Update
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $slug = $_POST['slug'];
    $title = $_POST['title'];
    $logo_url = $_POST['logo_url'];
    $external_link = $_POST['external_link'];
    $description = $_POST['description'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE iframes SET slug=?, title=?, logo_url=?, external_link=?, description=?, is_active=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param("sssssii", $slug, $title, $logo_url, $external_link, $description, $is_active, $id);
    
    if ($stmt->execute()) {
        header("Location: iframe_settings.php?success=1");
        exit();
    } else {
        $error = "Error: " . $stmt->error;
    }
    $stmt->close();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM iframes WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: iframe_settings.php?success=1");
    exit();
}

// Handle Edit (fetch data)
$edit_iframe = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM iframes WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_iframe = $result->fetch_assoc();
    $stmt->close();
}

// Fetch all iframes
$iframes = $conn->query("SELECT * FROM iframes ORDER BY id DESC");

// Get base URL for embed code
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$appRootMarker = '/pasadmin/';
$posApp = strpos($scriptName, $appRootMarker);
$appBaseUrl = ($posApp !== false) ? substr($scriptName, 0, $posApp + strlen($appRootMarker)) : '/';
$baseUrl = 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $appBaseUrl;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iFrame Settings</title>
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
        
        h2, h3 { text-align: center; color: #222; }
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
        label { display: block; margin-bottom: 14px; color: #333; font-weight: 500; }
        input[type="text"], textarea {
            width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem; margin-top: 4px; background: #fff; transition: border 0.2s;
        }
        input[type="text"]:focus, textarea:focus { border: 1.5px solid #2563eb; outline: none; }
        .btn { display: inline-block; padding: 8px 22px; border: none; border-radius: 6px; background: #2563eb; color: #fff; font-size: 1rem; font-weight: 500; cursor: pointer; margin-right: 8px; margin-top: 8px; transition: background 0.2s; text-decoration: none; }
        .btn:hover { background: #1d4ed8; }
        .btn.cancel { background: #e5e7eb; color: #222; }
        .btn.cancel:hover { background: #d1d5db; }
        .btn.delete { background: #ef4444; }
        .btn.delete:hover { background: #b91c1c; }
        .btn.copy { background: #10b981; }
        .btn.copy:hover { background: #059669; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.03); }
        th, td { padding: 12px 10px; text-align: left; }
        th { background: #f1f5f9; color: #222; font-weight: 600; }
        tr:nth-child(even) { background: #f9fafb; }
        tr:hover { background: #e0e7ef; }
        td { vertical-align: top; }
        .actions a { margin-right: 8px; }
        .embed-code { background: #f3f4f6; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 0.9rem; word-break: break-all; }
        .success-message { background: #10b981; color: white; padding: 12px; border-radius: 6px; margin-bottom: 20px; text-align: center; }
        .error-message { background: #ef4444; color: white; padding: 12px; border-radius: 6px; margin-bottom: 20px; text-align: center; }
        @media (max-width: 700px) { .container { padding: 8px; } form { padding: 12px 6px; } th, td { font-size: 0.95rem; padding: 8px 4px; } }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <!-- End Navigation Bar -->
    <div class="container">
        <h2>iFrame Settings</h2>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="success-message">Operation completed successfully!</div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <h3><?php echo $edit_iframe ? 'Edit iFrame' : 'Add iFrame'; ?></h3>
        <form method="post">
            <?php if ($edit_iframe): ?>
                <input type="hidden" name="id" value="<?php echo $edit_iframe['id']; ?>">
            <?php endif; ?>
            <label>Slug (Unique identifier for URL):
                <input type="text" name="slug" required value="<?php echo htmlspecialchars($edit_iframe['slug'] ?? ''); ?>" pattern="[a-z0-9-]+" placeholder="e.g., partner-company-1">
                <small style="color: #666;">Only lowercase letters, numbers, and hyphens</small>
            </label>
            <label>Title:
                <input type="text" name="title" required value="<?php echo htmlspecialchars($edit_iframe['title'] ?? ''); ?>">
            </label>
            <label>Logo URL:
                <input type="text" name="logo_url" required value="<?php echo htmlspecialchars($edit_iframe['logo_url'] ?? ''); ?>" placeholder="https://example.com/logo.png">
            </label>
            <label>External Hyperlink:
                <input type="text" name="external_link" required value="<?php echo htmlspecialchars($edit_iframe['external_link'] ?? ''); ?>" placeholder="https://example.com">
            </label>
            <label>Description:
                <textarea name="description" rows="3"><?php echo htmlspecialchars($edit_iframe['description'] ?? ''); ?></textarea>
            </label>
            <label style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="is_active" <?php echo (isset($edit_iframe['is_active']) && $edit_iframe['is_active']) ? 'checked' : ''; ?>>
                <span>Active</span>
            </label>
            <?php if ($edit_iframe): ?>
                <button class="btn" type="submit" name="update">Update</button>
                <a class="btn cancel" href="iframe_settings.php">Cancel</a>
            <?php else: ?>
                <button class="btn" type="submit" name="add">Add</button>
            <?php endif; ?>
        </form>
        <h3>All iFrames</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Slug</th>
                <th>Title</th>
                <th>Logo</th>
                <th>External Link</th>
                <th>Status</th>
                <th>Embed Code</th>
                <th>Created At</th>
                <th>Updated At</th>
                <th>Actions</th>
            </tr>
            <?php while ($row = $iframes->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['slug']); ?></td>
                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                    <td><img src="<?php echo htmlspecialchars($row['logo_url']); ?>" alt="Logo" style="max-height: 40px; max-width: 100px;"></td>
                    <td><a href="<?php echo htmlspecialchars($row['external_link']); ?>" target="_blank">Link</a></td>
                    <td><?php echo $row['is_active'] ? '<span style="color: green;">Active</span>' : '<span style="color: red;">Inactive</span>'; ?></td>
                    <td>
                        <div class="embed-code" id="embed-<?php echo $row['id']; ?>">
                            &lt;iframe src="<?php echo htmlspecialchars($baseUrl); ?>iframe_embed.php?slug=<?php echo htmlspecialchars($row['slug']); ?>" width="100%" height="600" frameborder="0"&gt;&lt;/iframe&gt;
                        </div>
                        <button class="btn copy" onclick="copyEmbedCode(<?php echo $row['id']; ?>)">Copy</button>
                    </td>
                    <td><?php echo $row['created_at']; ?></td>
                    <td><?php echo $row['updated_at']; ?></td>
                    <td class="actions">
                        <a class="btn" href="iframe_settings.php?edit=<?php echo $row['id']; ?>">Edit</a>
                        <a class="btn delete" href="iframe_settings.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this iframe?');">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyEmbedCode(id) {
            const embedDiv = document.getElementById('embed-' + id);
            const text = embedDiv.textContent;
            navigator.clipboard.writeText(text).then(function() {
                alert('Embed code copied to clipboard!');
            }, function() {
                // Fallback for older browsers
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                alert('Embed code copied to clipboard!');
            });
        }
    </script>
</body>
</html>

