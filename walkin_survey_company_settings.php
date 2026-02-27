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

function column_exists(mysqli $conn, string $table, string $column): bool {
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($column);
    $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND COLUMN_NAME = '$c' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function clean_string(mysqli $conn, string $value): string {
    return trim($conn->real_escape_string($value));
}

if (!table_exists($conn, 'company_walk_in_survey')) {
    $_SESSION['error'] = 'Table company_walk_in_survey belum ada. Jalankan migration Laravel terlebih dahulu.';
}

$tableReady = table_exists($conn, 'company_walk_in_survey');
$hasResponseTable = table_exists($conn, 'walk_in_survey_responses');
$hasInitiatorTable = table_exists($conn, 'walk_in_survey_initiators');
$hasInitiatorColumn = $tableReady && column_exists($conn, 'company_walk_in_survey', 'walk_in_initiator_id');

$initiators = [];
if ($hasInitiatorTable) {
    $resInitiator = $conn->query("SELECT id, initiator_name FROM walk_in_survey_initiators WHERE is_active = 1 ORDER BY sort_order ASC, initiator_name ASC");
    if ($resInitiator) {
        while ($i = $resInitiator->fetch_assoc()) {
            $initiators[] = $i;
        }
    }
}

if ($tableReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $companyName = isset($_POST['company_name']) ? clean_string($conn, $_POST['company_name']) : '';
    $initiatorId = isset($_POST['walk_in_initiator_id']) ? (int) $_POST['walk_in_initiator_id'] : 0;
    $sortOrder = isset($_POST['sort_order']) ? max(0, (int) $_POST['sort_order']) : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($companyName === '') {
        $_SESSION['error'] = 'Nama perusahaan wajib diisi.';
        header('Location: walkin_survey_company_settings.php');
        exit();
    }
    if ($hasInitiatorColumn && $initiatorId <= 0) {
        $_SESSION['error'] = 'Walk In Initiator wajib dipilih.';
        header('Location: walkin_survey_company_settings.php');
        exit();
    }

    if ($id > 0) {
        if ($hasInitiatorColumn) {
            $stmt = $conn->prepare("UPDATE company_walk_in_survey SET company_name = ?, walk_in_initiator_id = ?, is_active = ?, sort_order = ?, updated_at = NOW() WHERE id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE company_walk_in_survey SET company_name = ?, is_active = ?, sort_order = ?, updated_at = NOW() WHERE id = ?");
        }
        if ($stmt) {
            if ($hasInitiatorColumn) {
                $stmt->bind_param('siiii', $companyName, $initiatorId, $isActive, $sortOrder, $id);
            } else {
                $stmt->bind_param('siii', $companyName, $isActive, $sortOrder, $id);
            }
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Data perusahaan survey berhasil diperbarui.';
        } else {
            $_SESSION['error'] = 'Gagal update data: ' . $conn->error;
        }
    } else {
        if ($hasInitiatorColumn) {
            $stmt = $conn->prepare("INSERT INTO company_walk_in_survey (company_name, walk_in_initiator_id, is_active, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
        } else {
            $stmt = $conn->prepare("INSERT INTO company_walk_in_survey (company_name, is_active, sort_order, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
        }
        if ($stmt) {
            if ($hasInitiatorColumn) {
                $stmt->bind_param('siii', $companyName, $initiatorId, $isActive, $sortOrder);
            } else {
                $stmt->bind_param('sii', $companyName, $isActive, $sortOrder);
            }
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Data perusahaan survey berhasil ditambahkan.';
        } else {
            $_SESSION['error'] = 'Gagal simpan data: ' . $conn->error;
        }
    }

    header('Location: walkin_survey_company_settings.php');
    exit();
}

if ($tableReady && isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $usedCount = 0;
    if ($hasResponseTable) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM walk_in_survey_responses WHERE company_walk_in_survey_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->bind_result($usedCount);
            $stmt->fetch();
            $stmt->close();
        }
    }

    if ($usedCount > 0) {
        $_SESSION['error'] = 'Data tidak dapat dihapus karena sudah dipakai pada response survey.';
    } else {
        $stmt = $conn->prepare("DELETE FROM company_walk_in_survey WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Data perusahaan survey berhasil dihapus.';
        } else {
            $_SESSION['error'] = 'Gagal hapus data: ' . $conn->error;
        }
    }

    header('Location: walkin_survey_company_settings.php');
    exit();
}

$rows = [];
if ($tableReady) {
    $sql = "SELECT c.id, c.company_name, c.is_active, c.sort_order, c.created_at, c.updated_at";
    if ($hasInitiatorColumn && $hasInitiatorTable) {
        $sql .= ", c.walk_in_initiator_id, i.initiator_name";
    } elseif ($hasInitiatorColumn) {
        $sql .= ", c.walk_in_initiator_id, NULL AS initiator_name";
    } else {
        $sql .= ", NULL AS walk_in_initiator_id, NULL AS initiator_name";
    }
    if ($hasResponseTable) {
        $sql .= ", COUNT(r.id) AS peserta_hadir_count, ROUND(AVG(r.rating_satisfaction), 2) AS average_rating
                 FROM company_walk_in_survey c
                 LEFT JOIN walk_in_survey_responses r ON r.company_walk_in_survey_id = c.id";
    } else {
        $sql .= ", 0 AS peserta_hadir_count, NULL AS average_rating FROM company_walk_in_survey c";
    }
    if ($hasInitiatorColumn && $hasInitiatorTable) {
        $sql .= " LEFT JOIN walk_in_survey_initiators i ON i.id = c.walk_in_initiator_id";
        $sql .= " GROUP BY c.id, c.company_name, c.walk_in_initiator_id, i.initiator_name, c.is_active, c.sort_order, c.created_at, c.updated_at";
    } elseif ($hasInitiatorColumn) {
        $sql .= " GROUP BY c.id, c.company_name, c.walk_in_initiator_id, c.is_active, c.sort_order, c.created_at, c.updated_at";
    } else {
        $sql .= " GROUP BY c.id, c.company_name, c.is_active, c.sort_order, c.created_at, c.updated_at";
    }
    $sql .= " ORDER BY c.sort_order ASC, c.company_name ASC";

    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
}

// Fetch available institution names from kemitraan for company_name autocomplete
$availableCompanies = [];
if (table_exists($conn, 'kemitraan')) {
    $instRes = $conn->query("SELECT DISTINCT institution_name FROM kemitraan WHERE institution_name IS NOT NULL AND institution_name != '' ORDER BY institution_name");
    if ($instRes) {
        while ($instRow = $instRes->fetch_assoc()) {
            $availableCompanies[] = trim($instRow['institution_name']);
        }
        $instRes->free();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-in Survey Company Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container mt-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h3 class="mb-0">Walk-in Survey Company Settings</h3>
        <a href="walkin_survey_initiator_settings.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-people me-1"></i>Manage Initiators</a>
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
                    <label class="form-label">Nama Perusahaan</label>
                    <input type="text" class="form-control" name="company_name" id="form_company_name" list="company_names_list" required>
                    <?php if (!empty($availableCompanies)): ?>
                        <datalist id="company_names_list">
                            <?php foreach ($availableCompanies as $comp): ?>
                                <option value="<?php echo htmlspecialchars($comp); ?>">
                            <?php endforeach; ?>
                        </datalist>
                        <div class="form-text">Pilih dari daftar Institution Name di Kemitraan atau ketik manual</div>
                    <?php endif; ?>
                </div>
                <?php if ($hasInitiatorColumn): ?>
                <div class="col-md-4">
                    <label class="form-label">Walk In Initiator</label>
                    <select class="form-select" name="walk_in_initiator_id" id="form_walk_in_initiator_id" required>
                        <option value="">Pilih initiator</option>
                        <?php if (empty($initiators)): ?>
                            <option value="" disabled>Belum ada initiator aktif</option>
                        <?php endif; ?>
                        <?php foreach ($initiators as $i): ?>
                            <option value="<?php echo (int) $i['id']; ?>"><?php echo htmlspecialchars($i['initiator_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
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
                    <th>Nama Perusahaan</th>
                    <?php if ($hasInitiatorColumn): ?><th>Walk In Initiator</th><?php endif; ?>
                    <th style="width:100px;">Urutan</th>
                    <th style="width:110px;">Status</th>
                    <th style="width:140px;">Peserta Hadir</th>
                    <th style="width:120px;">Ratings</th>
                    <th style="width:140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="<?php echo $hasInitiatorColumn ? '9' : '8'; ?>" class="text-center text-muted">Belum ada data perusahaan survey.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo (int) $r['id']; ?></td>
                        <td><?php echo htmlspecialchars($r['company_name']); ?></td>
                        <?php if ($hasInitiatorColumn): ?><td><?php echo htmlspecialchars((string) ($r['initiator_name'] ?? '-')); ?></td><?php endif; ?>
                        <td><?php echo (int) $r['sort_order']; ?></td>
                        <td>
                            <?php if ((int) $r['is_active'] === 1): ?>
                                <span class="badge text-bg-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo (int) $r['peserta_hadir_count']; ?></td>
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
    document.getElementById('form_company_name').value = row.company_name || '';
    if (document.getElementById('form_walk_in_initiator_id')) {
        document.getElementById('form_walk_in_initiator_id').value = row.walk_in_initiator_id || '';
    }
    document.getElementById('form_sort_order').value = row.sort_order || 0;
    document.getElementById('form_is_active').checked = Number(row.is_active) === 1;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('form_id').value = '';
    document.getElementById('form_company_name').value = '';
    if (document.getElementById('form_walk_in_initiator_id')) {
        document.getElementById('form_walk_in_initiator_id').value = '';
    }
    document.getElementById('form_sort_order').value = 0;
    document.getElementById('form_is_active').checked = true;
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>


