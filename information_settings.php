<?php
// Database connection (copied from db.php, but using paskerid_db)
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Initialize variables
$id = $title = $description = $date = $type = $subject = $file_url = $iframe_url = '';
$status = '';
$created_at = $updated_at = '';
$edit_mode = false;

// Handle Add or Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : null;
    $title = $_POST['title'];
    $description = $_POST['description'];
    $date = $_POST['date'];
    $type = $_POST['type'];
    $subject = $_POST['subject'];
    $file_url = $_POST['file_url'];
    $iframe_url = $_POST['iframe_url'];
    $status = isset($_POST['status']) ? $_POST['status'] : '';

    if (isset($_POST['save'])) {
        // Add new record, set created_at and updated_at to NOW()
        $stmt = $conn->prepare("INSERT INTO information (title, description, date, type, subject, file_url, iframe_url, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param('ssssssss', $title, $description, $date, $type, $subject, $file_url, $iframe_url, $status);
        $stmt->execute();
        $stmt->close();
        header('Location: information_settings.php');
        exit();
    } elseif (isset($_POST['update'])) {
        // Update record, set updated_at to NOW()
        $stmt = $conn->prepare("UPDATE information SET title=?, description=?, date=?, type=?, subject=?, file_url=?, iframe_url=?, status=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param('ssssssssi', $title, $description, $date, $type, $subject, $file_url, $iframe_url, $status, $id);
        $stmt->execute();
        $stmt->close();
        header('Location: information_settings.php');
        exit();
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM information WHERE id=$id");
    header('Location: information_settings.php');
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

// Fetch all records
$records = $conn->query("SELECT * FROM information ORDER BY id DESC");
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
        }
        .main-content {
            display: flex;
            flex-wrap: wrap;
            gap: 32px;
            justify-content: center;
            align-items: flex-start;
            margin-top: 40px;
        }
        .modern-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(44,62,80,0.10);
            padding: 32px 28px 24px 28px;
            margin-bottom: 32px;
            width: 100%;
            max-width: 480px;
        }
        .modern-table-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(44,62,80,0.10);
            padding: 24px 18px 18px 18px;
            margin-bottom: 32px;
            width: 100%;
            max-width: 1200px;
            overflow-x: auto;
        }
        form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        form input[type="text"],
        form input[type="date"],
        form input[type="datetime-local"],
        form textarea {
            width: 100%;
            padding: 10px 12px;
            margin-top: 4px;
            margin-bottom: 18px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            background: #f9fafb;
            transition: border 0.2s;
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
        table {
            border-collapse: collapse;
            width: 100%;
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
        @media (min-width: 1000px) {
            .main-content {
                flex-wrap: nowrap;
                align-items: flex-start;
            }
            .modern-card {
                flex: 1 1 350px;
                max-width: 400px;
            }
            .modern-table-card {
                flex: 2 1 700px;
                max-width: 900px;
            }
        }
        @media (max-width: 900px) {
            .main-content { gap: 16px; }
            .modern-card, .modern-table-card { max-width: 100vw; }
            .modern-card { padding: 18px 8px 16px 8px; }
            .modern-table-card { padding: 10px 2px 8px 2px; }
            table { font-size: 0.97rem; }
        }
        @media (max-width: 600px) {
            .main-content { flex-direction: column; gap: 8px; }
            .modern-card, .modern-table-card { padding: 8px 2px; }
            th, td { padding: 7px 4px; }
        }
    </style>
</head>
<body class="bg-light">
      <!-- Navigation Bar -->
      <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="job_dashboard.html"><i class="bi bi-briefcase me-2"></i>Job Admin</a>
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
                            <li><a class="dropdown-item" href="job_dashboard.html">Dashboard Jobs</a></li>
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
                            <li><a class="dropdown-item" href="mitra_kerja_settings.php">Mitra Kerja Settings</a></li>
                            <li><a class="dropdown-item" href="kemitraan_submission.php">Mitra Kerja Submission</a></li>
                            <li><a class="dropdown-item" href="kemitraan_booked.php">Kemitraan Booked</a></li>
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
    <!-- <div class="header">Information Settings</div> -->
    <div class="container">
        <div class="main-content">
            <div class="modern-card">
                <form method="post">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
                    <?php endif; ?>
                    <label>Title:
                        <input type="text" name="title" value="<?php echo htmlspecialchars($title); ?>" required>
                    </label>
                    <label>Description:
                        <textarea name="description" rows="3" required><?php echo htmlspecialchars($description); ?></textarea>
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
                    <label>File URL:
                        <input type="text" name="file_url" value="<?php echo htmlspecialchars($file_url); ?>">
                    </label>
                    <label>Iframe URL:
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
                    <?php if ($edit_mode): ?>
                        <button type="submit" name="update">Update</button>
                        <a href="information_settings.php" class="btn btn-cancel">Cancel</a>
                    <?php else: ?>
                        <button type="submit" name="save">Add</button>
                    <?php endif; ?>
                </form>
            </div>
            <div class="modern-table-card">
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
                                    <a href="information_settings.php?edit=<?php echo $row['id']; ?>">Edit</a>
                                    <a href="information_settings.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure you want to delete this record?');">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="12">No records found.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?> 