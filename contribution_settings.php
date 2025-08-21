<?php
// Contribution Settings - CRUD for 'contributions' table in paskerid_db
// Connect to paskerid_db (reuse db.php logic, but override db name)
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db_prod';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Handle Add
if (isset($_POST['add'])) {
    $icon = $conn->real_escape_string($_POST['icon']);
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $now = date('Y-m-d H:i:s');
    $sql = "INSERT INTO contributions (icon, title, description, created_at, updated_at) VALUES ('$icon', '$title', '$description', '$now', '$now')";
    $conn->query($sql);
    header('Location: contribution_settings.php');
    exit();
}
// Handle Edit
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_result = $conn->query("SELECT * FROM contributions WHERE id=$edit_id");
    $edit_contribution = $edit_result->fetch_assoc();
}
// Handle Update
if (isset($_POST['update'])) {
    $id = intval($_POST['id']);
    $icon = $conn->real_escape_string($_POST['icon']);
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $now = date('Y-m-d H:i:s');
    $sql = "UPDATE contributions SET icon='$icon', title='$title', description='$description', updated_at='$now' WHERE id=$id";
    $conn->query($sql);
    header('Location: contribution_settings.php');
    exit();
}
// Handle Delete
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $conn->query("DELETE FROM contributions WHERE id=$delete_id");
    header('Location: contribution_settings.php');
    exit();
}
// Fetch all contributions
$contributions = $conn->query("SELECT * FROM contributions ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contribution Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background: linear-gradient(120deg, #f8fafc 60%, #e3e9f7 100%);
            min-height: 100vh;
        }
        .main-header {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 32px;
        }
        .main-header i {
            font-size: 2.2rem;
            color: #2563eb;
        }
        .container {
            max-width: 950px;
            margin-top: 48px;
        }
        .card-form {
            box-shadow: 0 4px 24px rgba(49,130,206,0.08);
            border-radius: 18px;
            border: none;
            margin-bottom: 32px;
            transition: box-shadow 0.2s;
            background: #fff;
            animation: fadeInDown 0.7s;
        }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .card-form .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #2563eb;
        }
        .form-label {
            font-weight: 500;
            color: #374151;
        }
        .form-control {
            border-radius: 8px;
            border: 1px solid #cbd5e1;
        }
        .btn-primary, .btn-outline-primary {
            border-radius: 7px;
            font-weight: 500;
            transition: background 0.18s, color 0.18s;
        }
        .btn-primary {
            background: linear-gradient(90deg, #2563eb 0%, #38bdf8 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(90deg, #38bdf8 0%, #2563eb 100%);
        }
        .btn-outline-primary {
            border: 1.5px solid #2563eb;
            color: #2563eb;
        }
        .btn-outline-primary:hover {
            background: #2563eb;
            color: #fff;
        }
        .btn-outline-danger {
            border-radius: 7px;
        }
        .btn-secondary {
            border-radius: 7px;
        }
        .table-responsive {
            box-shadow: 0 2px 16px rgba(49,130,206,0.07);
            border-radius: 16px;
            background: #fff;
            padding: 18px 18px 8px 18px;
        }
        table {
            background: transparent;
            border-radius: 10px;
            overflow: hidden;
        }
        th, td {
            vertical-align: middle;
        }
        th {
            background: #f1f5f9;
            color: #2563eb;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }
        tr:not(:last-child) td {
            border-bottom: 1px solid #e2e8f0;
        }
        .actions a {
            margin-right: 8px;
            min-width: 70px;
        }
        .actions .btn {
            box-shadow: 0 1px 4px rgba(49,130,206,0.07);
        }
        .actions .btn-outline-primary {
            border-color: #38bdf8;
            color: #38bdf8;
        }
        .actions .btn-outline-primary:hover {
            background: #38bdf8;
            color: #fff;
        }
        .actions .btn-outline-danger {
            border-color: #f87171;
            color: #f87171;
        }
        .actions .btn-outline-danger:hover {
            background: #f87171;
            color: #fff;
        }
        .fa {
            font-size: 1.2em;
            margin-right: 6px;
            color: #2563eb;
        }
        @media (max-width: 800px) {
            .container { padding: 10px; }
            th, td { font-size: 0.97em; padding: 8px 4px; }
            .main-header { flex-direction: column; gap: 8px; }
        }
        .navbar-brand { font-weight: bold; letter-spacing: 1px; }
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
                        <a class="nav-link" href="extensions.php">Extensions</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <!-- End Navigation Bar -->
    <div class="container">
        <div class="main-header mb-4">
            <i class="bi bi-stars"></i>
            <div>
                <h1 class="mb-0" style="font-size:2rem;font-weight:700;letter-spacing:1px;">Contribution Settings</h1>
                <div class="text-muted" style="font-size:1.08em;">Manage your platform's contribution highlights elegantly</div>
            </div>
        </div>
        <div class="card card-form mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3"><?php echo isset($edit_contribution) ? '<i class="bi bi-pencil-square"></i> Edit Contribution' : '<i class="bi bi-plus-circle"></i> Add New Contribution'; ?></h5>
                <form method="post" autocomplete="off">
                    <?php if (isset($edit_contribution)): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_contribution['id']; ?>">
                    <?php endif; ?>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Icon (FontAwesome)</label>
                            <input type="text" name="icon" class="form-control" required value="<?php echo isset($edit_contribution) ? htmlspecialchars($edit_contribution['icon']) : ''; ?>" placeholder="e.g. fa-users">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" required value="<?php echo isset($edit_contribution) ? htmlspecialchars($edit_contribution['title']) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" class="form-control" required value="<?php echo isset($edit_contribution) ? htmlspecialchars($edit_contribution['description']) : ''; ?>">
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" name="<?php echo isset($edit_contribution) ? 'update' : 'add'; ?>" class="btn btn-primary px-4">
                            <?php echo isset($edit_contribution) ? '<i class="bi bi-save"></i> Update' : '<i class="bi bi-plus-circle"></i> Add'; ?>
                        </button>
                        <?php if (isset($edit_contribution)): ?>
                            <a href="contribution_settings.php" class="btn btn-secondary ms-2 px-4"><i class="bi bi-x-circle"></i> Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <h4 class="mb-3" style="font-weight:600;color:#2563eb;"><i class="bi bi-list-stars"></i> All Contributions</h4>
        <div class="table-responsive">
            <table class="table table-hover align-middle mt-2">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Icon</th>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Created At</th>
                        <th>Updated At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = $contributions->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><i class="fa <?php echo htmlspecialchars($row['icon']); ?>"></i> <span style="color:#64748b;font-size:0.98em;"><?php echo htmlspecialchars($row['icon']); ?></span></td>
                        <td style="font-weight:500;"><?php echo htmlspecialchars($row['title']); ?></td>
                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                        <td><span class="badge bg-light text-dark border border-1 border-secondary-subtle"><?php echo $row['created_at']; ?></span></td>
                        <td><span class="badge bg-light text-dark border border-1 border-secondary-subtle"><?php echo $row['updated_at']; ?></span></td>
                        <td class="actions">
                            <a href="contribution_settings.php?edit=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
                            <a href="contribution_settings.php?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this contribution?');"><i class="bi bi-trash"></i> Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- FontAwesome CDN for icons -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/js/all.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 