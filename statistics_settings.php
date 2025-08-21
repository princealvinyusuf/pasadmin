<?php
// Statistics Settings - CRUD for 'statistics' table in 'paskerid_db'
// Connect to database (reuse db.php logic, but override db name)
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db_prod';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Handle Create
$error = '';
if (isset($_POST['create'])) {
    $title = $_POST['title'];
    $value = $_POST['value'];
    $unit = $_POST['unit'];
    $description = $_POST['description'];
    $type = $_POST['type'];
    $order = $_POST['order'];
    // Check for duplicate order
    $stmt = $conn->prepare("SELECT COUNT(*) FROM statistics WHERE `order` = ?");
    $stmt->bind_param("i", $order);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    if ($count > 0) {
        $error = 'Error: The order value must be unique.';
    } else {
        $stmt = $conn->prepare("INSERT INTO statistics (title, value, unit, description, type, `order`, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param("sssssi", $title, $value, $unit, $description, $type, $order);
        $stmt->execute();
        $stmt->close();
        header("Location: statistics_settings.php");
        exit();
    }
}

// Handle Update
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $title = $_POST['title'];
    $value = $_POST['value'];
    $unit = $_POST['unit'];
    $description = $_POST['description'];
    $type = $_POST['type'];
    $order = $_POST['order'];
    // Check for duplicate order (exclude current id)
    $stmt = $conn->prepare("SELECT COUNT(*) FROM statistics WHERE `order` = ? AND id != ?");
    $stmt->bind_param("ii", $order, $id);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    if ($count > 0) {
        $error = 'Error: The order value must be unique.';
    } else {
        $stmt = $conn->prepare("UPDATE statistics SET title=?, value=?, unit=?, description=?, type=?, `order`=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param("ssssssi", $title, $value, $unit, $description, $type, $order, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: statistics_settings.php");
        exit();
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM statistics WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: statistics_settings.php");
    exit();
}

// Fetch all statistics
$result = $conn->query("SELECT * FROM statistics ORDER BY `order` ASC, id DESC");

// Fetch single statistic for edit
$edit = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM statistics WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $edit = $res->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistics Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .navbar-brand { font-weight: bold; letter-spacing: 1px; }
        body {
            font-family: 'Roboto', Arial, sans-serif;
            background: #f6f8fa;
            margin: 0;
            padding: 0;
        }

        h1 {
            text-align: center;
            color: #2d3748;
            margin-bottom: 32px;
        }
        .card-form {
            background: #f9fafb;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            padding: 24px;
            margin-bottom: 32px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        .card-form label {
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
            font-weight: 500;
        }
        .card-form input[type="text"],
        .card-form input[type="number"],
        .card-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 1rem;
            background: #fff;
            transition: border 0.2s;
        }
        .card-form input:focus,
        .card-form textarea:focus {
            border-color: #3182ce;
            outline: none;
        }
        .card-form button {
            background: #3182ce;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .card-form button:hover {
            background: #2563eb;
        }
        .card-form a {
            margin-left: 16px;
            color: #718096;
            text-decoration: none;
            font-size: 0.98rem;
        }
        .card-form a:hover {
            text-decoration: underline;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        th, td {
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 10px;
            text-align: left;
        }
        th {
            background: #f1f5f9;
            color: #2d3748;
            font-weight: 700;
        }
        tr:last-child td {
            border-bottom: none;
        }
        tr:hover {
            background: #f0f4f8;
        }
        .actions a {
            margin-right: 10px;
            color: #3182ce;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        .actions a:hover {
            color: #e53e3e;
        }
        @media (max-width: 800px) {
            .container {
                padding: 10px;
            }
            .card-form {
                padding: 12px;
            }
            table, thead, tbody, th, td, tr {
                display: block;
            }
            th, td {
                padding: 8px 6px;
            }
            th {
                background: #f1f5f9;
            }
            tr {
                margin-bottom: 12px;
            }
            td {
                border: none;
                border-bottom: 1px solid #e2e8f0;
                position: relative;
                padding-left: 50%;
            }
            td:before {
                position: absolute;
                left: 10px;
                top: 8px;
                width: 45%;
                white-space: nowrap;
                font-weight: 700;
                color: #4a5568;
            }
            td:nth-of-type(1):before { content: "ID"; }
            td:nth-of-type(2):before { content: "Title"; }
            td:nth-of-type(3):before { content: "Value"; }
            td:nth-of-type(4):before { content: "Unit"; }
            td:nth-of-type(5):before { content: "Description"; }
            td:nth-of-type(6):before { content: "Type"; }
            td:nth-of-type(7):before { content: "Order"; }
            td:nth-of-type(8):before { content: "Created At"; }
            td:nth-of-type(9):before { content: "Updated At"; }
            td:nth-of-type(10):before { content: "Actions"; }
        }
    </style>
</head>
<body class="bg-light">
      <!-- Navigation Bar -->
      <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="bi bi-briefcase me-2"></i>Job Admin</a>
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
                            <li><a class="dropdown-item" href="index.php">Dashboard Jobs</a></li>
                            <li><a class="dropdown-item" href="index.php">Dashboard Job Seekers</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="masterDataDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Master Data
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="masterDataDropdown">
                            <li><a class="dropdown-item" href="index.php">Jobs</a></li>
                            <li><a class="dropdown-item" href="index.php">Job Seekers</a></li>
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
        <h1>Statistics Settings</h1>
        <div class="card-form">
            <h2 style="margin-top:0;"><?php echo $edit ? 'Edit Statistic' : 'Add New Statistic'; ?></h2>
            <?php if ($error): ?>
                <div style="color:#e53e3e; background:#fff5f5; border:1px solid #fed7d7; padding:10px 16px; border-radius:6px; margin-bottom:16px; font-weight:500;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            <form method="post">
                <?php if ($edit): ?>
                    <input type="hidden" name="id" value="<?php echo $edit['id']; ?>">
                <?php endif; ?>
                <label>Title:
                    <input type="text" name="title" required value="<?php echo $edit ? htmlspecialchars($edit['title']) : ''; ?>">
                </label>
                <label>Value:
                    <input type="text" name="value" required value="<?php echo $edit ? htmlspecialchars($edit['value']) : ''; ?>">
                </label>
                <label>Unit:
                    <input type="text" name="unit" value="<?php echo $edit ? htmlspecialchars($edit['unit']) : ''; ?>">
                </label>
                <label>Description:
                    <textarea name="description"><?php echo $edit ? htmlspecialchars($edit['description']) : ''; ?></textarea>
                </label>
                <label>Type:
                    <input type="text" name="type" required value="<?php echo $edit ? htmlspecialchars($edit['type']) : ''; ?>">
                </label>
                <label>Order:
                    <input type="number" name="order" value="<?php echo $edit ? htmlspecialchars($edit['order']) : '0'; ?>">
                </label>
                <button type="submit" name="<?php echo $edit ? 'update' : 'create'; ?>"><?php echo $edit ? 'Update' : 'Add'; ?></button>
                <?php if ($edit): ?>
                    <a href="statistics_settings.php">Cancel</a>
                <?php endif; ?>
            </form>
        </div>
        <h2 style="text-align:center;">All Statistics</h2>
        <div style="overflow-x:auto;">
        <table>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Value</th>
                <th>Unit</th>
                <th>Description</th>
                <th>Type</th>
                <th>Order</th>
                <th>Created At</th>
                <th>Updated At</th>
                <th>Actions</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['title']); ?></td>
                <td><?php echo htmlspecialchars($row['value']); ?></td>
                <td><?php echo htmlspecialchars($row['unit']); ?></td>
                <td><?php echo htmlspecialchars($row['description']); ?></td>
                <td><?php echo htmlspecialchars($row['type']); ?></td>
                <td><?php echo $row['order']; ?></td>
                <td><?php echo $row['created_at']; ?></td>
                <td><?php echo $row['updated_at']; ?></td>
                <td class="actions">
                    <a href="statistics_settings.php?edit=<?php echo $row['id']; ?>">Edit</a>
                    <a href="statistics_settings.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Delete this statistic?');">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?> 