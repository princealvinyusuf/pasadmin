<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db_prod';

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
if (!(current_user_can('settings_pasker_facility_manage') || current_user_can('settings_pasker_room_manage') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$isSuperAdmin = current_user_is_super_admin();
$scopedLocationId = current_user_walkin_location_id();
$locationFilterSql = $isSuperAdmin ? '1=1' : ($scopedLocationId !== null ? ('id=' . intval($scopedLocationId)) : '1=0');
$facilityFilterSql = $isSuperAdmin ? '1=1' : ($scopedLocationId !== null ? ('f.walkin_location_id=' . intval($scopedLocationId)) : '1=0');

$locationsResult = $conn->query("SELECT id, location_name FROM walkin_locations WHERE {$locationFilterSql} ORDER BY location_name ASC");
$locations = [];
if ($locationsResult) {
    while ($loc = $locationsResult->fetch_assoc()) {
        $locations[] = $loc;
    }
}

if (isset($_POST['add'])) {
    $facility_name = $conn->real_escape_string($_POST['facility_name']);
    $walkin_location_id = isset($_POST['walkin_location_id']) && $_POST['walkin_location_id'] !== '' ? intval($_POST['walkin_location_id']) : 'NULL';
    if (!$isSuperAdmin && ($scopedLocationId === null || intval($walkin_location_id) !== intval($scopedLocationId))) {
        header('Location: pasker_facility_settings');
        exit();
    }
    $now = date('Y-m-d H:i:s');
    $sql = "INSERT INTO pasker_facility (walkin_location_id, facility_name, created_at, updated_at) VALUES ($walkin_location_id, '$facility_name', '$now', '$now')";
    $conn->query($sql);
    header('Location: pasker_facility_settings');
    exit();
}

$edit_facility = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_result = $conn->query("SELECT * FROM pasker_facility f WHERE f.id=$edit_id AND {$facilityFilterSql}");
    $edit_facility = $edit_result ? $edit_result->fetch_assoc() : null;
}

if (isset($_POST['update'])) {
    $id = intval($_POST['id']);
    $facility_name = $conn->real_escape_string($_POST['facility_name']);
    $walkin_location_id = isset($_POST['walkin_location_id']) && $_POST['walkin_location_id'] !== '' ? intval($_POST['walkin_location_id']) : 'NULL';
    if (!$isSuperAdmin && ($scopedLocationId === null || intval($walkin_location_id) !== intval($scopedLocationId))) {
        header('Location: pasker_facility_settings');
        exit();
    }
    $now = date('Y-m-d H:i:s');
    $sql = "UPDATE pasker_facility f SET walkin_location_id=$walkin_location_id, facility_name='$facility_name', updated_at='$now' WHERE id=$id AND {$facilityFilterSql}";
    $conn->query($sql);
    header('Location: pasker_facility_settings');
    exit();
}

if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $conn->query("DELETE FROM pasker_facility f WHERE f.id=$delete_id AND {$facilityFilterSql}");
    header('Location: pasker_facility_settings');
    exit();
}

$facilities = $conn->query("
    SELECT f.*, l.location_name
    FROM pasker_facility f
    LEFT JOIN walkin_locations l ON l.id = f.walkin_location_id
    WHERE {$facilityFilterSql}
    ORDER BY f.id DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pasker Facility Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { font-family: Arial, sans-serif; }
        h2 { margin-top: 0; }
        form label { display: block; margin: 12px 0 6px; }
        form input[type="text"], form select { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
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
    <h2>Pasker Facility Settings</h2>
    <h3><?php echo $edit_facility ? 'Edit Facility' : 'Add Facility'; ?></h3>
    <form method="post">
        <?php if ($edit_facility): ?>
            <input type="hidden" name="id" value="<?php echo (int) $edit_facility['id']; ?>">
        <?php endif; ?>
        <label>Lokasi Walk In:
            <select name="walkin_location_id" required>
                <option value="">-- Pilih Lokasi --</option>
                <?php foreach ($locations as $location): ?>
                    <option value="<?php echo (int) $location['id']; ?>" <?php echo ((string)($edit_facility['walkin_location_id'] ?? '') === (string)$location['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($location['location_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Facility Name:
            <input type="text" name="facility_name" required value="<?php echo htmlspecialchars($edit_facility['facility_name'] ?? ''); ?>">
        </label>
        <?php if ($edit_facility): ?>
            <button class="btn" type="submit" name="update">Update</button>
            <a class="btn cancel" href="pasker_facility_settings">Cancel</a>
        <?php else: ?>
            <button class="btn" type="submit" name="add">Add</button>
        <?php endif; ?>
    </form>

    <h3>All Facilities</h3>
    <table>
        <tr>
            <th>ID</th>
            <th>Lokasi</th>
            <th>Facility Name</th>
            <th>Created At</th>
            <th>Updated At</th>
            <th>Actions</th>
        </tr>
        <?php if ($facilities): ?>
            <?php while ($row = $facilities->fetch_assoc()): ?>
                <tr>
                    <td><?php echo (int) $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['location_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($row['facility_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                    <td><?php echo htmlspecialchars($row['updated_at']); ?></td>
                    <td class="actions">
                        <a class="btn" href="pasker_facility_settings?edit=<?php echo (int) $row['id']; ?>">Edit</a>
                        <a class="btn delete" href="pasker_facility_settings?delete=<?php echo (int) $row['id']; ?>" onclick="return confirm('Delete this facility?');">Delete</a>
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
