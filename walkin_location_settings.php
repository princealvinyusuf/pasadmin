<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db_prod';

require_once __DIR__ . '/auth_guard.php';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

if (isset($_POST['add'])) {
    $location_name = trim((string) ($_POST['location_name'] ?? ''));
    if ($location_name !== '') {
        $location_name = $conn->real_escape_string($location_name);
        $now = date('Y-m-d H:i:s');
        $conn->query("INSERT INTO walkin_locations (location_name, created_at, updated_at) VALUES ('$location_name', '$now', '$now')");
    }
    header('Location: walkin_location_settings');
    exit();
}

$edit_location = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_result = $conn->query("SELECT * FROM walkin_locations WHERE id=$edit_id");
    $edit_location = $edit_result ? $edit_result->fetch_assoc() : null;
}

if (isset($_POST['update'])) {
    $id = intval($_POST['id']);
    $location_name = trim((string) ($_POST['location_name'] ?? ''));
    if ($location_name !== '') {
        $location_name = $conn->real_escape_string($location_name);
        $now = date('Y-m-d H:i:s');
        $conn->query("UPDATE walkin_locations SET location_name='$location_name', updated_at='$now' WHERE id=$id");
    }
    header('Location: walkin_location_settings');
    exit();
}

if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $conn->query("DELETE FROM walkin_locations WHERE id=$delete_id");
    header('Location: walkin_location_settings');
    exit();
}

$locations = $conn->query("
    SELECT l.*,
           (SELECT COUNT(*) FROM pasker_room r WHERE r.walkin_location_id = l.id) AS room_count,
           (SELECT COUNT(*) FROM pasker_facility f WHERE f.walkin_location_id = l.id) AS facility_count
    FROM walkin_locations l
    ORDER BY l.id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-in Location Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; }
        h2 { margin-top: 0; }
        form label { display: block; margin: 12px 0 6px; }
        form input[type="text"] { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .btn { padding: 6px 18px; border: none; border-radius: 4px; background: #3182ce; color: #fff; cursor: pointer; margin-right: 8px; text-decoration: none; }
        .btn.delete { background: #e53e3e; }
        .btn.cancel { background: #aaa; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { border: 1px solid #eee; padding: 8px; text-align: left; }
        th { background: #f7fafc; }
        .actions { white-space: nowrap; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container">
    <h2>Walk-in Location Settings</h2>
    <h3><?php echo $edit_location ? 'Edit Location' : 'Add Location'; ?></h3>
    <form method="post">
        <?php if ($edit_location): ?>
            <input type="hidden" name="id" value="<?php echo (int) $edit_location['id']; ?>">
        <?php endif; ?>
        <label>Nama Lokasi:
            <input type="text" name="location_name" required value="<?php echo htmlspecialchars($edit_location['location_name'] ?? ''); ?>">
        </label>
        <?php if ($edit_location): ?>
            <button class="btn" type="submit" name="update">Update</button>
            <a class="btn cancel" href="walkin_location_settings">Cancel</a>
        <?php else: ?>
            <button class="btn" type="submit" name="add">Add</button>
        <?php endif; ?>
    </form>

    <h3>All Locations</h3>
    <table>
        <tr>
            <th>ID</th>
            <th>Nama Lokasi</th>
            <th>Jumlah Ruangan</th>
            <th>Jumlah Fasilitas</th>
            <th>Created At</th>
            <th>Updated At</th>
            <th>Actions</th>
        </tr>
        <?php if ($locations): ?>
            <?php while ($row = $locations->fetch_assoc()): ?>
                <tr>
                    <td><?php echo (int) $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['location_name']); ?></td>
                    <td><?php echo (int) ($row['room_count'] ?? 0); ?></td>
                    <td><?php echo (int) ($row['facility_count'] ?? 0); ?></td>
                    <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                    <td><?php echo htmlspecialchars($row['updated_at']); ?></td>
                    <td class="actions">
                        <a class="btn" href="walkin_location_settings?edit=<?php echo (int) $row['id']; ?>">Edit</a>
                        <a class="btn delete" href="walkin_location_settings?delete=<?php echo (int) $row['id']; ?>" onclick="return confirm('Delete this location? Data ruangan/fasilitas terkait akan menjadi tanpa lokasi.');">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php endif; ?>
    </table>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
