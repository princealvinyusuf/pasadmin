<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';
if (!current_user_can('manage_settings')) { http_response_code(403); echo 'Forbidden'; exit; }

// Create intervals table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS naker_award_intervals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    indicator VARCHAR(50) NOT NULL,
    operator ENUM('<','<=','>','>=','==','between') NOT NULL DEFAULT 'between',
    min_value DECIMAL(15,4) NULL,
    max_value DECIMAL(15,4) NULL,
    nilai_akhir INT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_indicator_sort (indicator, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function seed_default_intervals(mysqli $conn): void {
    $check = $conn->query("SELECT COUNT(*) AS c FROM naker_award_intervals");
    $row = $check ? $check->fetch_assoc() : ['c' => 0];
    if (intval($row['c'] ?? 0) > 0) { return; }
    $rows = [];
    // postings
    $rows[] = ['postings','<=',0,null,0,10,1];
    $rows[] = ['postings','between',1,10,60,20,1];
    $rows[] = ['postings','between',11,50,80,30,1];
    $rows[] = ['postings','>',50,null,100,40,1];
    // quota
    $rows[] = ['quota','<=',0,null,0,10,1];
    $rows[] = ['quota','between',1,50,60,20,1];
    $rows[] = ['quota','between',51,100,80,30,1];
    $rows[] = ['quota','>',100,null,100,40,1];
    // ratio (percent)
    $rows[] = ['ratio','<',10,null,60,10,1];
    $rows[] = ['ratio','between',10,50,80,20,1];
    $rows[] = ['ratio','>',50,null,100,30,1];
    // realization (percent)
    $rows[] = ['realization','<',10,null,60,10,1];
    $rows[] = ['realization','between',10,50,80,20,1];
    $rows[] = ['realization','>',50,null,100,30,1];
    // tindak (percent)
    $rows[] = ['tindak','<',10,null,60,10,1];
    $rows[] = ['tindak','between',10,50,80,20,1];
    $rows[] = ['tindak','>',50,null,100,30,1];
    // disability
    $rows[] = ['disability','==',0,null,0,10,1];
    $rows[] = ['disability','between',1,5,60,20,1];
    $rows[] = ['disability','between',6,10,80,30,1];
    $rows[] = ['disability','>',10,null,100,40,1];

    $stmt = $conn->prepare('INSERT INTO naker_award_intervals (indicator, operator, min_value, max_value, nilai_akhir, sort_order, active) VALUES (?,?,?,?,?,?,?)');
    foreach ($rows as $r) {
        [$indicator,$op,$min,$max,$nilai,$sort,$active] = $r;
        $minParam = isset($min) ? (string)$min : null;
        $maxParam = isset($max) ? (string)$max : null;
        $stmt->bind_param('sssdsii', $indicator, $op, $minParam, $maxParam, $nilai, $sort, $active);
        $stmt->execute();
    }
    $stmt->close();
}

// Handle POST actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $indicator = trim((string)($_POST['indicator'] ?? ''));
        $operator = trim((string)($_POST['operator'] ?? 'between'));
        $min = $_POST['min_value'] !== '' ? (string)$_POST['min_value'] : null;
        $max = $_POST['max_value'] !== '' ? (string)$_POST['max_value'] : null;
        $nilai = intval($_POST['nilai_akhir'] ?? 0);
        $sort = intval($_POST['sort_order'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;
        if ($indicator === '' || $operator === '') {
            $error = 'Indicator and operator are required.';
        } else {
            $stmt = $conn->prepare('INSERT INTO naker_award_intervals (indicator, operator, min_value, max_value, nilai_akhir, sort_order, active) VALUES (?,?,?,?,?,?,?)');
            $stmt->bind_param('sssdsii', $indicator, $operator, $min, $max, $nilai, $sort, $active);
            $stmt->execute();
            $stmt->close();
            $message = 'Interval added.';
        }
    } elseif ($action === 'save') {
        // Bulk update rows
        $ids = $_POST['id'] ?? [];
        if (is_array($ids)) {
            $stmt = $conn->prepare('UPDATE naker_award_intervals SET indicator=?, operator=?, min_value=?, max_value=?, nilai_akhir=?, sort_order=?, active=? WHERE id=?');
            foreach ($ids as $idx => $id) {
                $id = intval($id);
                $indicator = trim((string)($_POST['indicator'][$idx] ?? ''));
                $operator = trim((string)($_POST['operator'][$idx] ?? 'between'));
                $min = ($_POST['min_value'][$idx] !== '') ? (string)$_POST['min_value'][$idx] : null;
                $max = ($_POST['max_value'][$idx] !== '') ? (string)$_POST['max_value'][$idx] : null;
                $nilai = intval($_POST['nilai_akhir'][$idx] ?? 0);
                $sort = intval($_POST['sort_order'][$idx] ?? 0);
                $active = isset($_POST['active'][$idx]) ? 1 : 0;
                $stmt->bind_param('sssdsiii', $indicator, $operator, $min, $max, $nilai, $sort, $active, $id);
                $stmt->execute();
            }
            $stmt->close();
            $message = 'Changes saved.';
        }
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) { $conn->query('DELETE FROM naker_award_intervals WHERE id=' . $id); $message = 'Interval deleted.'; }
    } elseif ($action === 'reset_defaults') {
        $conn->query('TRUNCATE TABLE naker_award_intervals');
        seed_default_intervals($conn);
        $message = 'Defaults restored.';
    }
}

// Seed defaults on first run
seed_default_intervals($conn);

function get_all_intervals_grouped(mysqli $conn): array {
    $out = [];
    $res = $conn->query("SELECT * FROM naker_award_intervals ORDER BY indicator ASC, sort_order ASC, id ASC");
    while ($r = $res->fetch_assoc()) {
        $ind = $r['indicator'];
        if (!isset($out[$ind])) { $out[$ind] = []; }
        $out[$ind][] = $r;
    }
    return $out;
}

function operator_label(string $op): string {
    switch ($op) {
        case '<': return '<';
        case '<=': return '≤';
        case '>': return '>';
        case '>=': return '≥';
        case '==': return '=';
        default: return 'between';
    }
}

$grouped = get_all_intervals_grouped($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Naker Award - Interval Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f6f8fa; }
        .card { box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        .table thead th { background: #f1f5f9; }
    </style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Naker Award - Interval Settings</h2>
        <form method="post" class="mb-0" onsubmit="return confirm('Reset all intervals to defaults?');">
            <input type="hidden" name="action" value="reset_defaults">
            <button class="btn btn-outline-danger" type="submit"><i class="bi bi-arrow-counterclockwise"></i> Reset Defaults</button>
        </form>
    </div>

    <?php if (!empty($message)): ?><div class="alert alert-success py-2 px-3 mb-3"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="alert alert-danger py-2 px-3 mb-3"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="mb-3">Add Interval</h5>
            <form method="post" class="row g-2 align-items-end">
                <input type="hidden" name="action" value="add">
                <div class="col-12 col-md-3">
                    <label class="form-label">Indicator</label>
                    <select name="indicator" class="form-select" required>
                        <option value="postings">Jumlah Postingan</option>
                        <option value="quota">Jumlah Kuota</option>
                        <option value="ratio">Rasio terhadap WLKP (%)</option>
                        <option value="realization">Realisasi Penempatan (%)</option>
                        <option value="tindak">Tindak Lanjut (%)</option>
                        <option value="disability">Kebutuhan Disabilitas</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label">Operator</label>
                    <select name="operator" class="form-select">
                        <option value="between">between</option>
                        <option value="<">&lt;</option>
                        <option value="<=">&le;</option>
                        <option value=">">&gt;</option>
                        <option value=">=">&ge;</option>
                        <option value="==">=</option>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Min</label>
                    <input type="number" step="0.01" name="min_value" class="form-control">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label">Max</label>
                    <input type="number" step="0.01" name="max_value" class="form-control">
                </div>
                <div class="col-6 col-md-1">
                    <label class="form-label">Nilai</label>
                    <input type="number" name="nilai_akhir" class="form-control" required>
                </div>
                <div class="col-6 col-md-1">
                    <label class="form-label">Sort</label>
                    <input type="number" name="sort_order" class="form-control" value="0">
                </div>
                <div class="col-6 col-md-1">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="active" id="add_active" checked>
                        <label class="form-check-label" for="add_active">Active</label>
                    </div>
                </div>
                <div class="col-12 col-md-12">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-plus"></i> Add</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="mb-3">Intervals</h5>
            <form method="post">
                <input type="hidden" name="action" value="save">
                <?php foreach ($grouped as $indicator => $items): ?>
                    <div class="mb-4">
                        <h6 class="mb-2 text-uppercase">Indicator: <?php echo htmlspecialchars($indicator); ?></h6>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle table-sm">
                                <thead>
                                    <tr>
                                        <th style="width:60px">ID</th>
                                        <th>Operator</th>
                                        <th>Min</th>
                                        <th>Max</th>
                                        <th>Nilai</th>
                                        <th>Sort</th>
                                        <th>Active</th>
                                        <th style="width:90px">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $it): ?>
                                        <tr>
                                            <td>
                                                <input type="hidden" name="id[]" value="<?php echo intval($it['id']); ?>">
                                                <input type="hidden" name="indicator[]" value="<?php echo htmlspecialchars($indicator); ?>">
                                                <?php echo intval($it['id']); ?>
                                            </td>
                                            <td>
                                                <select name="operator[]" class="form-select form-select-sm">
                                                    <?php foreach (['between','<','<=','>','>=','=='] as $op): ?>
                                                        <option value="<?php echo $op; ?>" <?php echo ($it['operator'] === $op ? 'selected' : ''); ?>><?php echo htmlspecialchars($op); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td><input type="number" step="0.01" name="min_value[]" class="form-control form-control-sm" value="<?php echo htmlspecialchars((string)$it['min_value']); ?>"></td>
                                            <td><input type="number" step="0.01" name="max_value[]" class="form-control form-control-sm" value="<?php echo htmlspecialchars((string)$it['max_value']); ?>"></td>
                                            <td><input type="number" name="nilai_akhir[]" class="form-control form-control-sm" value="<?php echo intval($it['nilai_akhir']); ?>"></td>
                                            <td><input type="number" name="sort_order[]" class="form-control form-control-sm" value="<?php echo intval($it['sort_order']); ?>"></td>
                                            <td class="text-center">
                                                <input class="form-check-input" type="checkbox" name="active[<?php echo intval($it['id']); ?>]" <?php echo ($it['active'] ? 'checked' : ''); ?>>
                                            </td>
                                            <td>
                                                <form method="post" onsubmit="return confirm('Delete this interval?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo intval($it['id']); ?>">
                                                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="mt-3">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


