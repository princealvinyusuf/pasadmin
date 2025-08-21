<?php
// Use the same DB connection method as db.php, but connect to 'paskerid_db'
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db_prod';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Handle Create
if (isset($_POST['add'])) {
    $name = $_POST['name'];
    $wilayah = $_POST['wilayah'];
    $divider = $_POST['divider'];
    $address = $_POST['address'];
    $contact = $_POST['contact'];
    $email = $_POST['email'];
    $website_url = $_POST['website_url'];
    $pic = $_POST['pic'];
    $stmt = $conn->prepare("INSERT INTO mitra_kerja (name, wilayah, divider, address, contact, email, website_url, pic, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param("ssssssss", $name, $wilayah, $divider, $address, $contact, $email, $website_url, $pic);
    $stmt->execute();
    $stmt->close();
    header("Location: mitra_kerja_settings.php");
    exit();
}

// Handle Update
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $wilayah = $_POST['wilayah'];
    $divider = $_POST['divider'];
    $address = $_POST['address'];
    $contact = $_POST['contact'];
    $email = $_POST['email'];
    $website_url = $_POST['website_url'];
    $pic = $_POST['pic'];
    $stmt = $conn->prepare("UPDATE mitra_kerja SET name=?, wilayah=?, divider=?, address=?, contact=?, email=?, website_url=?, pic=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param("ssssssssi", $name, $wilayah, $divider, $address, $contact, $email, $website_url, $pic, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: mitra_kerja_settings.php");
    exit();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM mitra_kerja WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: mitra_kerja_settings.php");
    exit();
}

// Handle Edit (fetch data)
$edit_mitra = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $result = $conn->query("SELECT * FROM mitra_kerja WHERE id=$id");
    $edit_mitra = $result->fetch_assoc();
}

// Fetch all mitra kerja
$mitras = $conn->query("SELECT * FROM mitra_kerja ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mitra Kerja Settings</title>
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
        input[type="text"], textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
            margin-top: 4px;
            background: #fff;
            transition: border 0.2s;
        }
        input[type="text"]:focus, textarea:focus {
            border: 1.5px solid #2563eb;
            outline: none;
        }
        textarea {
            min-height: 60px;
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
    <?php include 'navbar.php'; ?>
    <!-- End Navigation Bar -->
    <div class="container">
        <h2>Mitra Kerja Settings</h2>
        <h3><?php echo $edit_mitra ? 'Edit Mitra Kerja' : 'Add Mitra Kerja'; ?></h3>
        <form method="post">
            <?php if ($edit_mitra): ?>
                <input type="hidden" name="id" value="<?php echo $edit_mitra['id']; ?>">
            <?php endif; ?>
            <label>Name:
                <input type="text" name="name" required value="<?php echo $edit_mitra['name'] ?? ''; ?>">
            </label>
            <label>Wilayah:
                <input type="text" name="wilayah" value="<?php echo $edit_mitra['wilayah'] ?? ''; ?>">
            </label>
            <label>Divider:
                <input type="text" name="divider" value="<?php echo $edit_mitra['divider'] ?? ''; ?>">
            </label>
            <label>Address:
                <input type="text" name="address" value="<?php echo $edit_mitra['address'] ?? ''; ?>">
            </label>
            <label>Contact:
                <input type="text" name="contact" value="<?php echo $edit_mitra['contact'] ?? ''; ?>">
            </label>
            <label>Email:
                <input type="text" name="email" value="<?php echo $edit_mitra['email'] ?? ''; ?>">
            </label>
            <label>Website URL:
                <input type="text" name="website_url" value="<?php echo $edit_mitra['website_url'] ?? ''; ?>">
            </label>
            <label>PIC (Person in Charge):
                <input type="text" name="pic" value="<?php echo $edit_mitra['pic'] ?? ''; ?>">
            </label>
            <button type="submit" class="btn" name="<?php echo $edit_mitra ? 'update' : 'add'; ?>"><?php echo $edit_mitra ? 'Update' : 'Add'; ?></button>
            <?php if ($edit_mitra): ?>
                <a href="mitra_kerja_settings.php" class="btn cancel">Cancel</a>
            <?php endif; ?>
        </form>
        <h3>All Mitra Kerja</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Wilayah</th>
                <th>Divider</th>
                <th>Address</th>
                <th>Contact</th>
                <th>Email</th>
                <th>Website URL</th>
                <th>PIC</th>
                <th>Created At</th>
                <th>Updated At</th>
                <th>Actions</th>
            </tr>
            <?php while ($row = $mitras->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['name']); ?></td>
                <td><?php echo htmlspecialchars($row['wilayah']); ?></td>
                <td><?php echo htmlspecialchars($row['divider']); ?></td>
                <td><?php echo htmlspecialchars($row['address']); ?></td>
                <td><?php echo htmlspecialchars($row['contact']); ?></td>
                <td><?php echo htmlspecialchars($row['email']); ?></td>
                <td><?php echo htmlspecialchars($row['website_url']); ?></td>
                <td><?php echo htmlspecialchars($row['pic']); ?></td>
                <td><?php echo $row['created_at']; ?></td>
                <td><?php echo $row['updated_at']; ?></td>
                <td class="actions">
                    <a href="mitra_kerja_settings.php?edit=<?php echo $row['id']; ?>" class="btn">Edit</a>
                    <a href="mitra_kerja_settings.php?delete=<?php echo $row['id']; ?>" class="btn delete" onclick="return confirm('Delete this mitra kerja?');">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?> 