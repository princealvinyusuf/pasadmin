<?php
// Database connection (adapted from db.php)
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db';

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
     <!-- Navigation Bar -->
     <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.html"><i class="bi bi-briefcase me-2"></i>Job Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="dashboardDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Dashboard
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="dashboardDropdown">
                            <li><a class="dropdown-item" href="index.html">Dashboard Jobs</a></li>
                            <li><a class="dropdown-item" href="job_seeker_dashboard.html">Dashboard Job Seekers</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="masterDataDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Master Data
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="masterDataDropdown">
                            <li><a class="dropdown-item" href="jobs.html">Jobs</a></li>
                            <li><a class="dropdown-item" href="job_seekers.html">Job Seekers</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="cleansingDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Cleansing
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="cleansingDropdown">
                            <li><a class="dropdown-item" href="cleansing_snaphunt.php">Snaphunt</a></li>
                            <li><a class="dropdown-item" href="cleansing_makaryo.php">Makaryo</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="settingsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Settings
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="settingsDropdown">
                            <li><a class="dropdown-item" href="chart_settings.php">Chart Settings</a></li>
                            <li><a class="dropdown-item" href="contribution_settings.php">Contribution Settings</a></li>
                            <li><a class="dropdown-item" href="information_settings.php">Information Settings</a></li>
                            <li><a class="dropdown-item" href="news_settings.php">News Settings</a></li>
                            <li><a class="dropdown-item" href="services_settings.php">Services Settings</a></li>
                            <li><a class="dropdown-item" href="statistics_settings.php">Statistics Settings</a></li>
                            <li><a class="dropdown-item" href="testimonials_settings.php">Testimonial Settings</a></li>
                            <li><a class="dropdown-item" href="top_list_settings.php">Top List Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="agenda_settings.php">Agenda Settings</a></li>
                            <li><a class="dropdown-item" href="job_fair_settings.php">Job Fair Settings</a></li>
                            <li><a class="dropdown-item" href="virtual_karir_service_settings.php">Virtual Karir Service Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="cron_settings.php">Other Settings</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="extensions.html">Extensions</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
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
</body>
</html> 