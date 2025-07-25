<?php
// Use the same DB connection method as db.php, but connect to 'paskerid_db'
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Handle Create
if (isset($_POST['add'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $date = $_POST['date'];
    $location = $_POST['location'];
    $organizer = $_POST['organizer'];
    $image_url = $_POST['image_url'];
    $registration_url = $_POST['registration_url'];
    $stmt = $conn->prepare("INSERT INTO virtual_karir_agendas (title, description, date, location, organizer, image_url, registration_url, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param("sssssss", $title, $description, $date, $location, $organizer, $image_url, $registration_url);
    $stmt->execute();
    $stmt->close();
    header("Location: agenda_settings.php");
    exit();
}

// Handle Update
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $date = $_POST['date'];
    $location = $_POST['location'];
    $organizer = $_POST['organizer'];
    $image_url = $_POST['image_url'];
    $registration_url = $_POST['registration_url'];
    $stmt = $conn->prepare("UPDATE virtual_karir_agendas SET title=?, description=?, date=?, location=?, organizer=?, image_url=?, registration_url=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param("sssssssi", $title, $description, $date, $location, $organizer, $image_url, $registration_url, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: agenda_settings.php");
    exit();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM virtual_karir_agendas WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: agenda_settings.php");
    exit();
}

// Handle Edit (fetch data)
$edit_agenda = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $result = $conn->query("SELECT * FROM virtual_karir_agendas WHERE id=$id");
    $edit_agenda = $result->fetch_assoc();
}

// Fetch all agendas
$agendas = $conn->query("SELECT * FROM virtual_karir_agendas ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda Settings</title>
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
        input[type="text"], input[type="date"], textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
            margin-top: 4px;
            background: #fff;
            transition: border 0.2s;
        }
        input[type="text"]:focus, input[type="date"]:focus, textarea:focus {
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
        @media (max-width: 700px) {
            .container { padding: 8px; }
            form { padding: 12px 6px; }
            th, td { font-size: 0.95rem; padding: 8px 4px; }
        }
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
    <div class="container">
        <h2>Agenda Settings</h2>
        <h3><?php echo $edit_agenda ? 'Edit Agenda' : 'Add Agenda'; ?></h3>
        <form method="post">
            <?php if ($edit_agenda): ?>
                <input type="hidden" name="id" value="<?php echo $edit_agenda['id']; ?>">
            <?php endif; ?>
            <label>Title:
                <input type="text" name="title" required value="<?php echo $edit_agenda['title'] ?? ''; ?>">
            </label>
            <label>Description:
                <textarea name="description" required><?php echo $edit_agenda['description'] ?? ''; ?></textarea>
            </label>
            <label>Date:
                <input type="date" name="date" required value="<?php echo $edit_agenda['date'] ?? ''; ?>">
            </label>
            <label>Location:
                <input type="text" name="location" value="<?php echo $edit_agenda['location'] ?? ''; ?>">
            </label>
            <label>Organizer:
                <input type="text" name="organizer" value="<?php echo $edit_agenda['organizer'] ?? ''; ?>">
            </label>
            <label>Image URL:
                <input type="text" name="image_url" value="<?php echo $edit_agenda['image_url'] ?? ''; ?>">
            </label>
            <label>Registration URL:
                <input type="text" name="registration_url" value="<?php echo $edit_agenda['registration_url'] ?? ''; ?>">
            </label>
            <button type="submit" class="btn" name="<?php echo $edit_agenda ? 'update' : 'add'; ?>"><?php echo $edit_agenda ? 'Update' : 'Add'; ?></button>
            <?php if ($edit_agenda): ?>
                <a href="agenda_settings.php" class="btn cancel">Cancel</a>
            <?php endif; ?>
        </form>
        <h3>All Agendas</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Description</th>
                <th>Date</th>
                <th>Location</th>
                <th>Organizer</th>
                <th>Image URL</th>
                <th>Registration URL</th>
                <th>Created At</th>
                <th>Updated At</th>
                <th>Actions</th>
            </tr>
            <?php while ($row = $agendas->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['title']); ?></td>
                <td><?php echo nl2br(htmlspecialchars($row['description'])); ?></td>
                <td><?php echo $row['date']; ?></td>
                <td><?php echo htmlspecialchars($row['location']); ?></td>
                <td><?php echo htmlspecialchars($row['organizer']); ?></td>
                <td><?php echo htmlspecialchars($row['image_url']); ?></td>
                <td><?php echo htmlspecialchars($row['registration_url']); ?></td>
                <td><?php echo $row['created_at']; ?></td>
                <td><?php echo $row['updated_at']; ?></td>
                <td class="actions">
                    <a href="agenda_settings.php?edit=<?php echo $row['id']; ?>" class="btn">Edit</a>
                    <a href="agenda_settings.php?delete=<?php echo $row['id']; ?>" class="btn delete" onclick="return confirm('Delete this agenda?');">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?> 