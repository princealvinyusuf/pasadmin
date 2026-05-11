<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db_prod';

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
if (!(current_user_can('settings_walkin_location_manage') || current_user_can('settings_pasker_room_manage') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$adminConn = new mysqli('localhost', 'root', '', 'job_admin_prod');
if ($adminConn->connect_error) {
    die('Connection failed: ' . $adminConn->connect_error);
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

if (isset($_POST['save_user_location'])) {
    $targetUserId = intval($_POST['target_user_id'] ?? 0);
    $targetLocationId = intval($_POST['target_location_id'] ?? 0);

    if ($targetUserId > 0 && $targetLocationId > 0) {
        if ($stmt = $adminConn->prepare("INSERT INTO user_walkin_locations (user_id, walkin_location_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE walkin_location_id=VALUES(walkin_location_id), updated_at=CURRENT_TIMESTAMP")) {
            $stmt->bind_param('ii', $targetUserId, $targetLocationId);
            $stmt->execute();
            $stmt->close();
        }
    }
    header('Location: walkin_location_settings');
    exit();
}

if (isset($_POST['clear_user_location'])) {
    $targetUserId = intval($_POST['target_user_id'] ?? 0);
    if ($targetUserId > 0) {
        if ($stmt = $adminConn->prepare("DELETE FROM user_walkin_locations WHERE user_id=?")) {
            $stmt->bind_param('i', $targetUserId);
            $stmt->execute();
            $stmt->close();
        }
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

$locationOptions = [];
$locationOptionsRes = $conn->query("SELECT id, location_name FROM walkin_locations ORDER BY location_name ASC");
if ($locationOptionsRes) {
    while ($loc = $locationOptionsRes->fetch_assoc()) {
        $locationOptions[] = $loc;
    }
    $locationOptionsRes->free();
}

$userLabelColumn = 'username';
$usersCols = $adminConn->query("SHOW COLUMNS FROM users");
if ($usersCols) {
    $availableCols = [];
    while ($col = $usersCols->fetch_assoc()) {
        $availableCols[] = strtolower((string) ($col['Field'] ?? ''));
    }
    $usersCols->free();
    if (!in_array('username', $availableCols, true)) {
        if (in_array('name', $availableCols, true)) {
            $userLabelColumn = 'name';
        } elseif (in_array('email', $availableCols, true)) {
            $userLabelColumn = 'email';
        } else {
            $userLabelColumn = 'id';
        }
    }
}

$users = [];
$usersRes = $adminConn->query("SELECT id, {$userLabelColumn} AS label FROM users ORDER BY {$userLabelColumn} ASC");
if ($usersRes) {
    while ($u = $usersRes->fetch_assoc()) {
        $users[] = $u;
    }
    $usersRes->free();
}

$assignments = [];
$assignmentsRes = $adminConn->query("
    SELECT ul.user_id, ul.walkin_location_id, ul.updated_at, u.{$userLabelColumn} AS user_label
    FROM user_walkin_locations ul
    LEFT JOIN users u ON u.id = ul.user_id
    ORDER BY ul.updated_at DESC, ul.user_id ASC
");
if ($assignmentsRes) {
    while ($a = $assignmentsRes->fetch_assoc()) {
        $assignments[] = $a;
    }
    $assignmentsRes->free();
}

$locationNameMap = [];
foreach ($locationOptions as $loc) {
    $locationNameMap[(int) $loc['id']] = (string) $loc['location_name'];
}
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
        form select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
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

    <h3>Assign User Admin ke Lokasi</h3>
    <form method="post">
        <label>User Admin:
            <select name="target_user_id" required>
                <option value="">-- Pilih User --</option>
                <?php foreach ($users as $userRow): ?>
                    <option value="<?php echo (int) $userRow['id']; ?>">
                        <?php echo htmlspecialchars((string) ($userRow['label'] ?? ('User #' . $userRow['id']))); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Lokasi:
            <select name="target_location_id" required>
                <option value="">-- Pilih Lokasi --</option>
                <?php foreach ($locationOptions as $loc): ?>
                    <option value="<?php echo (int) $loc['id']; ?>">
                        <?php echo htmlspecialchars((string) $loc['location_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn" type="submit" name="save_user_location">Simpan Mapping</button>
    </form>

    <h3>User Admin Mapping</h3>
    <table>
        <tr>
            <th>User ID</th>
            <th>Account Name</th>
            <th>Lokasi</th>
            <th>Updated At</th>
            <th>Action</th>
        </tr>
        <?php if (!empty($assignments)): ?>
            <?php foreach ($assignments as $assignment): ?>
                <?php $locationId = (int) ($assignment['walkin_location_id'] ?? 0); ?>
                <tr>
                    <td><?php echo (int) ($assignment['user_id'] ?? 0); ?></td>
                    <td><?php echo htmlspecialchars((string) ($assignment['user_label'] ?? '-')); ?></td>
                    <td><?php echo htmlspecialchars($locationNameMap[$locationId] ?? ('ID ' . $locationId)); ?></td>
                    <td><?php echo htmlspecialchars((string) ($assignment['updated_at'] ?? '')); ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="target_user_id" value="<?php echo (int) ($assignment['user_id'] ?? 0); ?>">
                            <button class="btn delete" type="submit" name="clear_user_location" onclick="return confirm('Hapus mapping user ini?');">Hapus Mapping</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5">Belum ada mapping user ke lokasi.</td>
            </tr>
        <?php endif; ?>
    </table>

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
<?php
$conn->close();
$adminConn->close();
?>
