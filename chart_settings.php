<?php
// Database connection (same as db.php, but with paskerid_db)
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$error = '';

// Handle Create
if (isset($_POST['add'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $chart_type = $conn->real_escape_string($_POST['chart_type']);
    $data_json = $conn->real_escape_string($_POST['data_json']);
    $order = intval($_POST['order']);
    // Check for duplicate order
    $check = $conn->query("SELECT id FROM charts WHERE `order` = $order");
    if ($check && $check->num_rows > 0) {
        $error = 'Order value already exists. Please choose a different order.';
    } else {
        $sql = "INSERT INTO charts (title, description, chart_type, data_json, `order`) VALUES ('$title', '$description', '$chart_type', '$data_json', $order)";
        $conn->query($sql);
        header('Location: chart_settings.php');
        exit();
    }
}

// Handle Update
if (isset($_POST['update'])) {
    $id = intval($_POST['id']);
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $chart_type = $conn->real_escape_string($_POST['chart_type']);
    $data_json = $conn->real_escape_string($_POST['data_json']);
    $order = intval($_POST['order']);
    // Check for duplicate order (exclude current row)
    $check = $conn->query("SELECT id FROM charts WHERE `order` = $order AND id != $id");
    if ($check && $check->num_rows > 0) {
        $error = 'Order value already exists. Please choose a different order.';
    } else {
        $sql = "UPDATE charts SET title='$title', description='$description', chart_type='$chart_type', data_json='$data_json', `order`=$order WHERE id=$id";
        $conn->query($sql);
        header('Location: chart_settings.php');
        exit();
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM charts WHERE id=$id");
    header('Location: chart_settings.php');
    exit();
}

// Fetch all charts
$result = $conn->query("SELECT * FROM charts ORDER BY `order`");

// Fetch single chart for editing
$edit_chart = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $res = $conn->query("SELECT * FROM charts WHERE id=$id");
    $edit_chart = $res->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chart Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f6f8fa;
            margin: 0;
            padding: 0;
        }
    
        h1 {
            text-align: center;
            color: #2d3748;
            margin-bottom: 8px;
        }
        h2 {
            color: #4a5568;
            margin-top: 0;
            margin-bottom: 18px;
            font-size: 1.2em;
        }
        .error {
            color: #fff;
            background: #e53e3e;
            padding: 10px 18px;
            border-radius: 6px;
            margin-bottom: 18px;
            font-size: 1em;
        }
        form {
            background: #f7fafc;
            border-radius: 10px;
            padding: 22px 20px 18px 20px;
            margin-bottom: 32px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        label {
            display: block;
            margin-bottom: 12px;
            color: #2d3748;
            font-weight: 500;
        }
        input[type="text"], input[type="number"], textarea {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 1em;
            margin-top: 4px;
            margin-bottom: 10px;
            background: #fff;
            transition: border 0.2s;
        }
        input[type="text"]:focus, input[type="number"]:focus, textarea:focus {
            border: 1.5px solid #3182ce;
            outline: none;
        }
        textarea[readonly] {
            background: #f1f5f9;
            color: #4a5568;
            border: 1px solid #e2e8f0;
            resize: vertical;
        }
        button[type="submit"], .btn-cancel {
            background: #3182ce;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 8px 22px;
            font-size: 1em;
            font-weight: 500;
            cursor: pointer;
            margin-right: 10px;
            box-shadow: 0 2px 8px rgba(49,130,206,0.08);
            transition: background 0.2s;
        }
        button[type="submit"]:hover, .btn-cancel:hover {
            background: #2563eb;
        }
        .btn-cancel {
            background: #a0aec0;
        }
        table {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        th, td {
            padding: 12px 10px;
            text-align: left;
        }
        th {
            background: #f1f5f9;
            color: #2d3748;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }
        tr:not(:last-child) td {
            border-bottom: 1px solid #e2e8f0;
        }
        .actions a {
            display: inline-block;
            margin-right: 8px;
            color: #3182ce;
            text-decoration: none;
            font-weight: 500;
            padding: 4px 10px;
            border-radius: 5px;
            transition: background 0.15s, color 0.15s;
        }
        .actions a:hover {
            background: #e0e7ef;
            color: #2563eb;
        }
        @media (max-width: 800px) {
            .container { padding: 10px; }
            th, td { font-size: 0.95em; padding: 8px 4px; }
            form { padding: 12px 8px; }
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
                    <li class="nav-item">
                        <a class="nav-link" href="index.html">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="jobs.html">Jobs</a>
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
                            <li><a class="dropdown-item" href="cron_settings.php">Other Settings</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <!-- End Navigation Bar -->
    <div class="container">
        <h1>Chart Settings</h1>
        <h2><?php echo $edit_chart ? 'Edit Chart' : 'Add New Chart'; ?></h2>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post">
            <?php if ($edit_chart): ?>
                <input type="hidden" name="id" value="<?php echo $edit_chart['id']; ?>">
            <?php endif; ?>
            <label>Title:
                <input type="text" name="title" required value="<?php echo $edit_chart ? htmlspecialchars($edit_chart['title']) : ''; ?>">
            </label>
            <label>Description:
                <textarea name="description" required rows="2"><?php echo $edit_chart ? htmlspecialchars($edit_chart['description']) : ''; ?></textarea>
            </label>
            <label>Chart Type:
                <input type="text" name="chart_type" required value="<?php echo $edit_chart ? htmlspecialchars($edit_chart['chart_type']) : ''; ?>">
            </label>
            <label>Data JSON:
                <textarea name="data_json" required rows="3"><?php echo $edit_chart ? htmlspecialchars($edit_chart['data_json']) : ''; ?></textarea>
            </label>
            <label>Order:
                <input type="number" name="order" required value="<?php echo $edit_chart ? intval($edit_chart['order']) : 0; ?>">
            </label>
            <button type="submit" name="<?php echo $edit_chart ? 'update' : 'add'; ?>"><?php echo $edit_chart ? 'Update' : 'Add'; ?></button>
            <?php if ($edit_chart): ?>
                <a href="chart_settings.php" class="btn-cancel">Cancel</a>
            <?php endif; ?>
        </form>
        <h2>All Charts</h2>
        <div style="overflow-x:auto;">
        <table>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Description</th>
                <th>Chart Type</th>
                <th>Data JSON</th>
                <th>Order</th>
                <th>Created At</th>
                <th>Updated At</th>
                <th>Actions</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['title']); ?></td>
                <td><?php echo htmlspecialchars($row['description']); ?></td>
                <td><?php echo htmlspecialchars($row['chart_type']); ?></td>
                <td><textarea readonly style="width:140px;height:38px;font-size:0.97em;"><?php echo htmlspecialchars($row['data_json']); ?></textarea></td>
                <td><?php echo $row['order']; ?></td>
                <td><?php echo $row['created_at']; ?></td>
                <td><?php echo $row['updated_at']; ?></td>
                <td class="actions">
                    <a href="chart_settings.php?edit=<?php echo $row['id']; ?>">Edit</a>
                    <a href="chart_settings.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Delete this chart?');">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
        </div>
    </div>
</body>
</html> 