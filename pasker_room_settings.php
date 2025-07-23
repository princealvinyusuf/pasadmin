<?php
// Pasker Room Settings - CRUD for 'pasker_room' table in paskerid_db
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Handle Add
if (isset($_POST['add'])) {
    $room_name = $conn->real_escape_string($_POST['room_name']);
    $image_base64 = $conn->real_escape_string($_POST['image_base64']);
    $mime_type = $conn->real_escape_string($_POST['mime_type']);
    $now = date('Y-m-d H:i:s');
    $sql = "INSERT INTO pasker_room (room_name, image_base64, mime_type, created_at, updated_at) VALUES ('$room_name', '$image_base64', '$mime_type', '$now', '$now')";
    $conn->query($sql);
    header('Location: pasker_room_settings.php');
    exit();
}
// Handle Edit
$edit_room = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_result = $conn->query("SELECT * FROM pasker_room WHERE id=$edit_id");
    $edit_room = $edit_result->fetch_assoc();
}
// Handle Update
if (isset($_POST['update'])) {
    $id = intval($_POST['id']);
    $room_name = $conn->real_escape_string($_POST['room_name']);
    $image_base64 = $conn->real_escape_string($_POST['image_base64']);
    $mime_type = $conn->real_escape_string($_POST['mime_type']);
    $now = date('Y-m-d H:i:s');
    $sql = "UPDATE pasker_room SET room_name='$room_name', image_base64='$image_base64', mime_type='$mime_type', updated_at='$now' WHERE id=$id";
    $conn->query($sql);
    header('Location: pasker_room_settings.php');
    exit();
}
// Handle Delete
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $conn->query("DELETE FROM pasker_room WHERE id=$delete_id");
    header('Location: pasker_room_settings.php');
    exit();
}
// Fetch all rooms
$rooms = $conn->query("SELECT * FROM pasker_room ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pasker Room Settings</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 900px; margin: 30px auto; background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 2px 8px #eee; }
        h2 { margin-top: 0; }
        form label { display: block; margin: 12px 0 6px; }
        form input[type="text"], form textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        form textarea { min-height: 60px; }
        .btn { padding: 6px 18px; border: none; border-radius: 4px; background: #3182ce; color: #fff; cursor: pointer; margin-right: 8px; }
        .btn.delete { background: #e53e3e; }
        .btn.cancel { background: #aaa; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { border: 1px solid #eee; padding: 8px; text-align: left; }
        th { background: #f7fafc; }
        .actions { white-space: nowrap; }
        .img-preview { max-width: 80px; max-height: 80px; display: block; }
    </style>
</head>
<body>
<div class="container">
    <h2>Pasker Room Settings</h2>
    <h3><?php echo $edit_room ? 'Edit Room' : 'Add Room'; ?></h3>
    <form method="post">
        <?php if ($edit_room): ?>
            <input type="hidden" name="id" value="<?php echo $edit_room['id']; ?>">
        <?php endif; ?>
        <label>Room Name:
            <input type="text" name="room_name" required value="<?php echo htmlspecialchars($edit_room['room_name'] ?? ''); ?>">
        </label>
        <label>Image (Base64):
            <textarea name="image_base64" placeholder="Paste base64 image string here..."><?php echo htmlspecialchars($edit_room['image_base64'] ?? ''); ?></textarea>
        </label>
        <label>MIME Type:
            <input type="text" name="mime_type" value="<?php echo htmlspecialchars($edit_room['mime_type'] ?? ''); ?>" placeholder="e.g. image/png">
        </label>
        <?php if ($edit_room): ?>
            <button class="btn" type="submit" name="update">Update</button>
            <a class="btn cancel" href="pasker_room_settings.php">Cancel</a>
        <?php else: ?>
            <button class="btn" type="submit" name="add">Add</button>
        <?php endif; ?>
    </form>
    <h3>All Rooms</h3>
    <table>
        <tr>
            <th>ID</th>
            <th>Room Name</th>
            <th>Image Preview</th>
            <th>MIME Type</th>
            <th>Created At</th>
            <th>Updated At</th>
            <th>Actions</th>
        </tr>
        <?php while ($row = $rooms->fetch_assoc()): ?>
        <tr>
            <td><?php echo $row['id']; ?></td>
            <td><?php echo htmlspecialchars($row['room_name']); ?></td>
            <td>
                <?php if ($row['image_base64'] && $row['mime_type']): ?>
                    <img class="img-preview" src="data:<?php echo htmlspecialchars($row['mime_type']); ?>;base64,<?php echo $row['image_base64']; ?>" alt="Room Image" />
                <?php else: ?>
                    -
                <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($row['mime_type']); ?></td>
            <td><?php echo $row['created_at']; ?></td>
            <td><?php echo $row['updated_at']; ?></td>
            <td class="actions">
                <a class="btn" href="pasker_room_settings.php?edit=<?php echo $row['id']; ?>">Edit</a>
                <a class="btn delete" href="pasker_room_settings.php?delete=<?php echo $row['id']; ?>" onclick="return confirm('Delete this room?');">Delete</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
</body>
</html>
<?php $conn->close(); ?> 