<?php
// Database connection (adapted from db.php)
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db_prod';

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';
if (!current_user_can('manage_settings')) { http_response_code(403); echo 'Forbidden'; exit; }

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Handle CRUD operations
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? '';

// Add or Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $position = $_POST['position'];
    $company = $_POST['company'];
    $photo_url = $_POST['photo_url'];
    $quote = $_POST['quote'];
    if (isset($_POST['id']) && $_POST['id'] !== '') {
        // Update
        $stmt = $conn->prepare("UPDATE testimonials SET name=?, position=?, company=?, photo_url=?, quote=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param('sssssi', $name, $position, $company, $photo_url, $quote, $_POST['id']);
        $stmt->execute();
    } else {
        // Insert
        $stmt = $conn->prepare("INSERT INTO testimonials (name, position, company, photo_url, quote, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param('sssss', $name, $position, $company, $photo_url, $quote);
        $stmt->execute();
    }
    header('Location: testimonials_settings.php');
    exit;
}

// Delete
if ($action === 'delete' && $id) {
    $stmt = $conn->prepare("DELETE FROM testimonials WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    header('Location: testimonials_settings.php');
    exit;
}

// Fetch for edit
$edit_data = null;
if ($action === 'edit' && $id) {
    $stmt = $conn->prepare("SELECT * FROM testimonials WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_data = $result->fetch_assoc();
}

// Fetch all testimonials
$result = $conn->query("SELECT * FROM testimonials ORDER BY id DESC");
$testimonials = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testimonials Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .navbar-brand { font-weight: bold; letter-spacing: 1px; }
    </style>
</head>
<body class="bg-light">
      <?php include 'navbar.php'; ?>
      <!-- End Navigation Bar -->
    <div class="container py-4">
        <h2 class="mb-4">Testimonials Settings</h2>
        <div class="card mb-4">
            <div class="card-header">Add / Edit Testimonial</div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($edit_data['id'] ?? '') ?>">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($edit_data['name'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Position</label>
                        <input type="text" class="form-control" name="position" value="<?= htmlspecialchars($edit_data['position'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Company</label>
                        <input type="text" class="form-control" name="company" value="<?= htmlspecialchars($edit_data['company'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Photo URL</label>
                        <input type="text" class="form-control" name="photo_url" value="<?= htmlspecialchars($edit_data['photo_url'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quote</label>
                        <textarea class="form-control" name="quote" rows="3" required><?= htmlspecialchars($edit_data['quote'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Save</button>
                    <?php if ($edit_data): ?>
                        <a href="testimonials_settings.php" class="btn btn-secondary">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header">All Testimonials</div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Position</th>
                            <th>Company</th>
                            <th>Photo</th>
                            <th>Quote</th>
                            <th>Created At</th>
                            <th>Updated At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($testimonials as $row): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['position']) ?></td>
                            <td><?= htmlspecialchars($row['company']) ?></td>
                            <td><?php if ($row['photo_url']): ?><img src="<?= htmlspecialchars($row['photo_url']) ?>" alt="Photo" width="40" height="40" style="object-fit:cover;border-radius:50%;"><?php endif; ?></td>
                            <td><?= nl2br(htmlspecialchars($row['quote'])) ?></td>
                            <td><?= $row['created_at'] ?></td>
                            <td><?= $row['updated_at'] ?></td>
                            <td>
                                <a href="testimonials_settings.php?action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                <a href="testimonials_settings.php?action=delete&id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this testimonial?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 