<?php
// Database connection for paskerid_db_prod
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db_prod';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Helper: sanitize input
default_timezone_set('Asia/Jakarta');
function esc($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

// Handle Create
if (isset($_POST['add'])) {
    $stmt = $conn->prepare("INSERT INTO mitra_kerja (name, wilayah, divider, address, contact, email, website_url, pic, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param('ssssssss', $_POST['name'], $_POST['wilayah'], $_POST['divider'], $_POST['address'], $_POST['contact'], $_POST['email'], $_POST['website_url'], $_POST['pic']);
    if ($stmt->execute()) {
        $msg = 'Added successfully!';
    } else {
        $err = 'Add failed: ' . $stmt->error;
    }
    $stmt->close();
}

// Handle Update
if (isset($_POST['edit'])) {
    $stmt = $conn->prepare("UPDATE mitra_kerja SET name=?, wilayah=?, divider=?, address=?, contact=?, email=?, website_url=?, pic=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param('ssssssssi', $_POST['name'], $_POST['wilayah'], $_POST['divider'], $_POST['address'], $_POST['contact'], $_POST['email'], $_POST['website_url'], $_POST['pic'], $_POST['id']);
    if ($stmt->execute()) {
        $msg = 'Updated successfully!';
    } else {
        $err = 'Update failed: ' . $stmt->error;
    }
    $stmt->close();
}

// Handle Delete
if (isset($_POST['delete'])) {
    $stmt = $conn->prepare("DELETE FROM mitra_kerja WHERE id=?");
    $stmt->bind_param('i', $_POST['id']);
    if ($stmt->execute()) {
        $msg = 'Deleted successfully!';
    } else {
        $err = 'Delete failed: ' . $stmt->error;
    }
    $stmt->close();
}

// Fetch all records
$result = $conn->query("SELECT * FROM mitra_kerja ORDER BY id DESC");
$rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// If editing, fetch the record
$edit_row = null;
if (isset($_GET['edit_id'])) {
    $stmt = $conn->prepare("SELECT * FROM mitra_kerja WHERE id=?");
    $stmt->bind_param('i', $_GET['edit_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $edit_row = $res->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mitra Kerja Settings</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 30px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
        .msg { color: green; }
        .err { color: red; }
        form { margin-bottom: 20px; }
        input[type=text], input[type=email] { width: 100%; padding: 6px; margin: 2px 0; }
        input[type=submit], button { padding: 6px 16px; }
    </style>
</head>
<body>
    <h2>Mitra Kerja Settings</h2>
    <p><a href="navbar.html">Back to Menu</a></p>
    <?php if (!empty($msg)) echo "<div class='msg'>$msg</div>"; ?>
    <?php if (!empty($err)) echo "<div class='err'>$err</div>"; ?>

    <!-- Add/Edit Form -->
    <h3><?= $edit_row ? 'Edit' : 'Add New' ?> Mitra Kerja</h3>
    <form method="post">
        <?php if ($edit_row): ?>
            <input type="hidden" name="id" value="<?= esc($edit_row['id']) ?>">
        <?php endif; ?>
        <label>Name:<br><input type="text" name="name" required value="<?= esc($edit_row['name'] ?? '') ?>"></label><br>
        <label>Wilayah:<br><input type="text" name="wilayah" value="<?= esc($edit_row['wilayah'] ?? '') ?>"></label><br>
        <label>Divider:<br><input type="text" name="divider" value="<?= esc($edit_row['divider'] ?? '') ?>"></label><br>
        <label>Address:<br><input type="text" name="address" value="<?= esc($edit_row['address'] ?? '') ?>"></label><br>
        <label>Contact:<br><input type="text" name="contact" value="<?= esc($edit_row['contact'] ?? '') ?>"></label><br>
        <label>Email:<br><input type="email" name="email" value="<?= esc($edit_row['email'] ?? '') ?>"></label><br>
        <label>Website URL:<br><input type="text" name="website_url" value="<?= esc($edit_row['website_url'] ?? '') ?>"></label><br>
        <label>PIC:<br><input type="text" name="pic" value="<?= esc($edit_row['pic'] ?? '') ?>"></label><br>
        <label>Created At:<br><input type="text" value="<?= esc($edit_row['created_at'] ?? '-') ?>" readonly></label><br>
        <label>Updated At:<br><input type="text" value="<?= esc($edit_row['updated_at'] ?? '-') ?>" readonly></label><br>
        <input type="submit" name="<?= $edit_row ? 'edit' : 'add' ?>" value="<?= $edit_row ? 'Update' : 'Add' ?>">
        <?php if ($edit_row): ?>
            <a href="mitra_kerja_settings.php">Cancel</a>
        <?php endif; ?>
    </form>

    <!-- Data Table -->
    <h3>All Mitra Kerja</h3>
    <table>
        <tr>
            <th>ID</th><th>Name</th><th>Wilayah</th><th>Divider</th><th>Address</th><th>Contact</th><th>Email</th><th>Website URL</th><th>PIC</th><th>Created At</th><th>Updated At</th><th>Actions</th>
        </tr>
        <?php foreach ($rows as $row): ?>
        <tr>
            <td><?= esc($row['id']) ?></td>
            <td><?= esc($row['name']) ?></td>
            <td><?= esc($row['wilayah']) ?></td>
            <td><?= esc($row['divider']) ?></td>
            <td><?= esc($row['address']) ?></td>
            <td><?= esc($row['contact']) ?></td>
            <td><?= esc($row['email']) ?></td>
            <td><?= esc($row['website_url']) ?></td>
            <td><?= esc($row['pic']) ?></td>
            <td><?= esc($row['created_at']) ?></td>
            <td><?= esc($row['updated_at']) ?></td>
            <td>
                <form method="get" style="display:inline">
                    <input type="hidden" name="edit_id" value="<?= esc($row['id']) ?>">
                    <button type="submit">Edit</button>
                </form>
                <form method="post" style="display:inline" onsubmit="return confirm('Delete this entry?');">
                    <input type="hidden" name="id" value="<?= esc($row['id']) ?>">
                    <input type="submit" name="delete" value="Delete">
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html> 