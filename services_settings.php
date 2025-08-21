<?php
// Use the same DB connection method as db.php, but connect to 'paskerid_db'
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db_prod';

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
if (!current_user_can('manage_settings')) { http_response_code(403); echo 'Forbidden'; exit; }

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Handle Create
if (isset($_POST['add'])) {
    $icon = $_POST['icon'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $link = $_POST['link'];
    $stmt = $conn->prepare("INSERT INTO services (icon, title, description, link, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param("ssss", $icon, $title, $description, $link);
    $stmt->execute();
    $stmt->close();
    header("Location: services_settings.php");
    exit();
}

// Handle Update
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $icon = $_POST['icon'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $link = $_POST['link'];
    $stmt = $conn->prepare("UPDATE services SET icon=?, title=?, description=?, link=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param("ssssi", $icon, $title, $description, $link, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: services_settings.php");
    exit();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM services WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: services_settings.php");
    exit();
}

// Handle Edit (fetch data)
$edit_service = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $result = $conn->query("SELECT * FROM services WHERE id=$id");
    $edit_service = $result->fetch_assoc();
}

// Fetch all services
$services = $conn->query("SELECT * FROM services ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services Settings</title>
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
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.03); }
        th, td { padding: 12px 10px; text-align: left; }
        th { background: #f1f5f9; color: #222; font-weight: 600; }
        tr:nth-child(even) { background: #f9fafb; }
        tr:hover { background: #e0e7ef; }
        td { vertical-align: top; }
        .actions a { margin-right: 8px; }
        @media (max-width: 700px) { .container { padding: 8px; } form { padding: 12px 6px; } th, td { font-size: 0.95rem; padding: 8px 4px; } }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <!-- End Navigation Bar -->
    <div class="container">
        <h2>Services Settings</h2>
        <h3><?php echo $edit_service ? 'Edit Service' : 'Add Service'; ?></h3>
        <form method="post">
            <?php if ($edit_service): ?>
                <input type="hidden" name="id" value="<?php echo $edit_service['id']; ?>">
            <?php endif; ?>
            <label>Icon:
                <input type="text" name="icon" required value="<?php echo $edit_service['icon'] ?? ''; ?>">
            </label>
            <label>Title:
                <input type="text" name="title" required value="<?php echo $edit_service['title'] ?? ''; ?>">
            </label>
            <label>Description:
                <textarea name="description" required><?php echo $edit_service['description'] ?? ''; ?></textarea>
            </label>
            <label>Link:
                <input type="text" name="link" required value="<?php echo $edit_service['link'] ?? ''; ?>">
            </label>
            <?php if ($edit_service): ?>
                <button class="btn" type="submit" name="update">Update</button>
                <a class="btn cancel" href="services_settings.php">Cancel</a>
            <?php else: ?>
                <button class="btn" type="submit" name="add">Add</button>
            <?php endif; ?>
        </form>
        <h3>All Services</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Icon</th>
                <th>Title</th>
                <th>Description</th>
                <th>Link</th>
                <th>Created At</th>
                <th>Updated At</th>
                <th>Actions</th>
            </tr>
            <?php while ($row = $services->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['icon']); ?></td>
                    <td><?php echo htmlspecialchars($row['title']); ?></td>
                    <td><?php echo nl2br(htmlspecialchars($row['description'])); ?></td>
                    <td><a href="<?php echo htmlspecialchars($row['link']); ?>" target="_blank">Link</a></td>
                    <td><?php echo $row['created_at']; ?></td>
                    <td><?php echo $row['updated_at']; ?></td>
                    <td class="actions">
                        <a class="btn" href="services_settings.php?edit=<?php echo $row['id']; ?>">Edit</a>
                        <a class="btn delete" href="services_settings.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this service?');">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 