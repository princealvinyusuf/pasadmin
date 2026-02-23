<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
if (!(current_user_can('walkin_survey_manage') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$conn = new mysqli('localhost', 'root', '', 'paskerid_db_prod');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

function table_exists(mysqli $conn, string $table): bool {
    $t = $conn->real_escape_string($table);
    $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function clean_string(mysqli $conn, string $value): string {
    return trim($conn->real_escape_string($value));
}

if (!table_exists($conn, 'walk_in_survey_initiators')) {
    $_SESSION['error'] = 'Table walk_in_survey_initiators belum ada. Jalankan migration Laravel terlebih dahulu.';
}

$tableReady = table_exists($conn, 'walk_in_survey_initiators');
$hasCompanyTable = table_exists($conn, 'company_walk_in_survey');
$hasResponseTable = table_exists($conn, 'walk_in_survey_responses');

if ($tableReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $initiatorName = isset($_POST['initiator_name']) ? clean_string($conn, $_POST['initiator_name']) : '';
    $sortOrder = isset($_POST['sort_order']) ? max(0, (int) $_POST['sort_order']) : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($initiatorName === '') {
        $_SESSION['error'] = 'Nama initiator wajib diisi.';
        header('Location: walkin_survey_initiator_settings.php');
        exit();
    }

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE walk_in_survey_initiators SET initiator_name = ?, is_active = ?, sort_order = ?, updated_at = NOW() WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('siii', $initiatorName, $isActive, $sortOrder, $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Data initiator berhasil diperbarui.';
        } else {
            $_SESSION['error'] = 'Gagal update data: ' . $conn->error;
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO walk_in_survey_initiators (initiator_name, is_active, sort_order, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
        if ($stmt) {
            $stmt->bind_param('sii', $initiatorName, $isActive, $sortOrder);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Data initiator berhasil ditambahkan.';
        } else {
            $_SESSION['error'] = 'Gagal simpan data: ' . $conn->error;
        }
    }

    header('Location: walkin_survey_initiator_settings.php');
    exit();
}

if ($tableReady && isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $usedCount = 0;
    if ($hasCompanyTable) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM company_walk_in_survey WHERE walk_in_initiator_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->bind_result($usedCount);
            $stmt->fetch();
            $stmt->close();
        }
    }

    if ($usedCount > 0) {
        $_SESSION['error'] = 'Data tidak dapat dihapus karena sudah dipakai pada perusahaan survey.';
    } else {
        $stmt = $conn->prepare("DELETE FROM walk_in_survey_initiators WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Data initiator berhasil dihapus.';
        } else {
            $_SESSION['error'] = 'Gagal hapus data: ' . $conn->error;
        }
    }

    header('Location: walkin_survey_initiator_settings.php');
    exit();
}

$rows = [];
if ($tableReady) {
    $sql = "SELECT i.id, i.initiator_name, i.is_active, i.sort_order, i.created_at, i.updated_at";
    if ($hasCompanyTable && $hasResponseTable) {
        $sql .= ", COUNT(DISTINCT c.id) AS company_count, ROUND(AVG(r.rating_satisfaction), 2) AS average_rating
                 FROM walk_in_survey_initiators i
                 LEFT JOIN company_walk_in_survey c ON c.walk_in_initiator_id = i.id
                 LEFT JOIN walk_in_survey_responses r ON r.company_walk_in_survey_id = c.id";
    } elseif ($hasCompanyTable) {
        $sql .= ", COUNT(DISTINCT c.id) AS company_count, NULL AS average_rating
                 FROM walk_in_survey_initiators i
                 LEFT JOIN company_walk_in_survey c ON c.walk_in_initiator_id = i.id";
    } else {
        $sql .= ", 0 AS company_count, NULL AS average_rating FROM walk_in_survey_initiators i";
    }
    $sql .= " GROUP BY i.id, i.initiator_name, i.is_active, i.sort_order, i.created_at, i.updated_at
              ORDER BY i.sort_order ASC, i.initiator_name ASC";

    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-in Survey Initiator Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container mt-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h3 class="mb-0">Walk-in Survey Initiator Settings</h3>
        <a href="walkin_survey_company_settings.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-building me-1"></i>Manage Companies</a>
    </div>

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
                    <label class="form-label">Nama Initiator</label>
                    <input type="text" class="form-control" name="initiator_name" id="form_initiator_name" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Urutan</label>
                    <input type="number" min="0" class="form-control" name="sort_order" id="form_sort_order" value="0" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="is_active" id="form_is_active" checked>
                        <label class="form-check-label" for="form_is_active">Aktif</label>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2"><i class="bi bi-save me-1"></i>Simpan</button>
                    <button type="button" class="btn btn-secondary" onclick="resetForm()">Clear</button>
                </div>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th style="width:70px;">ID</th>
                    <th>Nama Initiator</th>
                    <th style="width:100px;">Urutan</th>
                    <th style="width:110px;">Status</th>
                    <th style="width:130px;">Companies</th>
                    <th style="width:120px;">Ratings</th>
                    <th style="width:140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="text-center text-muted">Belum ada data initiator.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo (int) $r['id']; ?></td>
                        <td><?php echo htmlspecialchars($r['initiator_name']); ?></td>
                        <td><?php echo (int) $r['sort_order']; ?></td>
                        <td>
                            <?php if ((int) $r['is_active'] === 1): ?>
                                <span class="badge text-bg-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo (int) $r['company_count']; ?></td>
                        <td>
                            <?php if ($r['average_rating'] !== null): ?>
                                <?php echo htmlspecialchars(number_format((float) $r['average_rating'], 2)); ?>/5
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick='editRow(<?php echo json_encode($r); ?>)'><i class="bi bi-pencil-square"></i></button>
                            <a class="btn btn-sm btn-outline-danger" href="?delete=<?php echo (int) $r['id']; ?>" onclick="return confirm('Hapus data ini?');"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function editRow(row) {
    document.getElementById('form_id').value = row.id || '';
    document.getElementById('form_initiator_name').value = row.initiator_name || '';
    document.getElementById('form_sort_order').value = row.sort_order || 0;
    document.getElementById('form_is_active').checked = Number(row.is_active) === 1;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('form_id').value = '';
    document.getElementById('form_initiator_name').value = '';
    document.getElementById('form_sort_order').value = 0;
    document.getElementById('form_is_active').checked = true;
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>

