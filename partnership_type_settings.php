<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/auth_guard.php';

// DB connection
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db_prod';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

function sanitize_string($conn, $val) { return trim($conn->real_escape_string($val)); }
function sanitize_int($val) { return max(0, intval($val)); }

// Handle create/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $name = isset($_POST['name']) ? sanitize_string($conn, $_POST['name']) : '';
    $max = isset($_POST['max_bookings']) ? sanitize_int($_POST['max_bookings']) : 0;
    if ($name === '') {
        $_SESSION['error'] = 'Name is required';
        header('Location: partnership_type_settings.php');
        exit();
    }
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE type_of_partnership SET name = ?, max_bookings = ?, updated_at = NOW() WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('sii', $name, $max, $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Updated successfully';
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO type_of_partnership (name, max_bookings, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        if ($stmt) {
            $stmt->bind_param('si', $name, $max);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Created successfully';
        }
    }
    header('Location: partnership_type_settings.php');
    exit();
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // Prevent delete if referenced by kemitraan
    $cnt = 0;
    if ($stmt = $conn->prepare("SELECT COUNT(*) FROM kemitraan WHERE type_of_partnership_id = ?")) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($cnt);
        $stmt->fetch();
        $stmt->close();
    }
    if ($cnt > 0) {
        $_SESSION['error'] = 'Cannot delete: used by existing kemitraan.';
    } else {
        if ($stmt = $conn->prepare("DELETE FROM type_of_partnership WHERE id = ?")) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Deleted successfully';
        }
    }
    header('Location: partnership_type_settings.php');
    exit();
}

// Fetch list
$rows = [];
$res = $conn->query("SELECT id, name, COALESCE(max_bookings, 10) AS max_bookings, created_at, updated_at FROM type_of_partnership ORDER BY id ASC");
if ($res) {
    while ($r = $res->fetch_assoc()) { $rows[] = $r; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partnership Type Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <div class="container mt-4">
        <h3 class="mb-3">Partnership Type Settings</h3>

        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <form method="post" class="row g-3">
                    <input type="hidden" name="id" id="form_id" value="">
                    <div class="col-md-6">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" id="form_name" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Max Bookings per Day</label>
                        <input type="number" min="0" class="form-control" name="max_bookings" id="form_max" value="10" required>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2"><i class="bi bi-save me-1"></i>Save</button>
                        <button type="button" class="btn btn-secondary" onclick="resetForm()">Clear</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th style="width:80px;">ID</th>
                        <th>Name</th>
                        <th style="width:180px;">Max Bookings</th>
                        <th style="width:160px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="4" class="text-center">No data</td></tr>
                    <?php else: foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo $r['id']; ?></td>
                            <td><?php echo htmlspecialchars($r['name']); ?></td>
                            <td><?php echo (int) $r['max_bookings']; ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick='editRow(<?php echo json_encode($r); ?>)'><i class="bi bi-pencil-square"></i></button>
                                <a class="btn btn-sm btn-outline-danger" href="?delete=<?php echo $r['id']; }?>" onclick="return confirm('Delete this type?');"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    function editRow(r) {
        document.getElementById('form_id').value = r.id;
        document.getElementById('form_name').value = r.name;
        document.getElementById('form_max').value = r.max_bookings;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    function resetForm() {
        document.getElementById('form_id').value = '';
        document.getElementById('form_name').value = '';
        document.getElementById('form_max').value = 10;
    }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>


