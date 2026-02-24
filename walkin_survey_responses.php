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

function decode_json_array($value): array {
    if (!is_string($value) || $value === '') {
        return [];
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function fetch_rows_with_params(mysqli $conn, string $sql, string $types, array $params): array {
    $rows = [];
    if ($types === '') {
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $rows;
    }

    $bindParams = [];
    $bindParams[] = &$types;
    foreach ($params as $k => $value) {
        $bindParams[] = &$params[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindParams);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $stmt->close();
    return $rows;
}

$hasResponseTable = table_exists($conn, 'walk_in_survey_responses');
$hasInitiatorSnapshotCol = $hasResponseTable && column_exists($conn, 'walk_in_survey_responses', 'walkin_initiator_snapshot');
$hasWalkinDateCol = $hasResponseTable && column_exists($conn, 'walk_in_survey_responses', 'walkin_date');
if (!$hasResponseTable) {
    $_SESSION['error'] = 'Table walk_in_survey_responses belum ada. Jalankan migration Laravel terlebih dahulu.';
}

if ($hasResponseTable && isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM walk_in_survey_responses WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = 'Response survey berhasil dihapus.';
    } else {
        $_SESSION['error'] = 'Gagal menghapus response: ' . $conn->error;
    }
    header('Location: walkin_survey_responses.php');
    exit();
}

$companies = [];
$filtersApplied = false;
$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$companyId = isset($_GET['company_id']) ? (int) $_GET['company_id'] : 0;
$singleDate = isset($_GET['date']) ? trim((string) $_GET['date']) : '';
$singleDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $singleDate) ? $singleDate : '';
$filterDateFrom = isset($_GET['date_from']) ? trim((string) $_GET['date_from']) : '';
$filterDateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateFrom) ? $filterDateFrom : '';
$filterDateTo = isset($_GET['date_to']) ? trim((string) $_GET['date_to']) : '';
$filterDateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateTo) ? $filterDateTo : '';
if ($singleDate !== '' && $filterDateFrom === '' && $filterDateTo === '') {
    $filterDateFrom = $singleDate;
    $filterDateTo = $singleDate;
}
if ($filterDateFrom !== '' && $filterDateTo !== '' && $filterDateFrom > $filterDateTo) {
    $tmp = $filterDateFrom;
    $filterDateFrom = $filterDateTo;
    $filterDateTo = $tmp;
}
$viewId = isset($_GET['view']) ? (int) $_GET['view'] : 0;

if (table_exists($conn, 'company_walk_in_survey')) {
    $resCompany = $conn->query("SELECT id, company_name FROM company_walk_in_survey ORDER BY sort_order ASC, company_name ASC");
    if ($resCompany) {
        while ($row = $resCompany->fetch_assoc()) {
            $companies[] = $row;
        }
    }
}

$selected = null;
if ($hasResponseTable && $viewId > 0) {
    $stmt = $conn->prepare("SELECT * FROM walk_in_survey_responses WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $viewId);
        $stmt->execute();
        $res = $stmt->get_result();
        $selected = $res ? $res->fetch_assoc() : null;
        $stmt->close();
    }
}

$rows = [];
$exportRows = [];
if ($hasResponseTable) {
    $dateExpr = $hasWalkinDateCol ? 'DATE(walkin_date)' : 'DATE(created_at)';
    $whereSql = " WHERE 1=1";
    $types = '';
    $params = [];

    if ($companyId > 0) {
        $whereSql .= " AND company_walk_in_survey_id = ?";
        $types .= 'i';
        $params[] = $companyId;
        $filtersApplied = true;
    }
    if ($search !== '') {
        $whereSql .= " AND (name LIKE ? OR email LIKE ? OR company_name_snapshot LIKE ?)";
        $like = '%' . $search . '%';
        $types .= 'sss';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $filtersApplied = true;
    }
    if ($filterDateFrom !== '' && $filterDateTo !== '') {
        $whereSql .= " AND {$dateExpr} BETWEEN ? AND ?";
        $types .= 'ss';
        $params[] = $filterDateFrom;
        $params[] = $filterDateTo;
        $filtersApplied = true;
    } elseif ($filterDateFrom !== '') {
        $whereSql .= " AND {$dateExpr} >= ?";
        $types .= 's';
        $params[] = $filterDateFrom;
        $filtersApplied = true;
    } elseif ($filterDateTo !== '') {
        $whereSql .= " AND {$dateExpr} <= ?";
        $types .= 's';
        $params[] = $filterDateTo;
        $filtersApplied = true;
    }

    $initiatorSelect = $hasInitiatorSnapshotCol ? 'walkin_initiator_snapshot' : 'NULL AS walkin_initiator_snapshot';
    $walkinDateSelect = $hasWalkinDateCol ? 'walkin_date' : 'NULL AS walkin_date';
    $sql = "SELECT id, company_name_snapshot, {$initiatorSelect}, {$walkinDateSelect}, name, email, phone, rating_satisfaction, created_at
            FROM walk_in_survey_responses" . $whereSql . " ORDER BY id DESC LIMIT 500";
    $rows = fetch_rows_with_params($conn, $sql, $types, $params);

    // Export includes complete filtered rows from DB (not only visible columns in the table).
    $exportSql = "SELECT * FROM walk_in_survey_responses" . $whereSql . " ORDER BY id DESC";
    $exportRows = fetch_rows_with_params($conn, $exportSql, $types, $params);
}

$filterQuery = [];
if ($search !== '') $filterQuery['q'] = $search;
if ($companyId > 0) $filterQuery['company_id'] = $companyId;
if ($filterDateFrom !== '') $filterQuery['date_from'] = $filterDateFrom;
if ($filterDateTo !== '') $filterQuery['date_to'] = $filterDateTo;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-in Survey Responses</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container mt-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h3 class="mb-0">Walk-in Survey Responses</h3>
        <div class="d-flex gap-2">
            <a href="walkin_survey_initiator_settings.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-people me-1"></i>Manage Initiators</a>
            <a href="walkin_survey_company_settings.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-building me-1"></i>Manage Companies</a>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-2">
                <div class="col-md-3">
                    <label class="form-label mb-1">Search</label>
                    <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nama / email / perusahaan">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Perusahaan</label>
                    <select class="form-select" name="company_id">
                        <option value="0">Semua perusahaan</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?php echo (int) $c['id']; ?>" <?php echo ((int) $companyId === (int) $c['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['company_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Date From</label>
                    <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">Date To</label>
                    <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($filterDateTo); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary me-2" type="submit"><i class="bi bi-search me-1"></i>Filter</button>
                    <a href="walkin_survey_responses.php" class="btn btn-secondary">Reset</a>
                    <button class="btn btn-success ms-2" type="button" id="btnDownloadExcel"><i class="bi bi-file-earmark-excel me-1"></i>Download To Excel</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($selected): ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Detail Response #<?php echo (int) $selected['id']; ?></strong>
                <a href="walkin_survey_responses.php<?php echo $filtersApplied ? '?' . http_build_query($filterQuery) : ''; ?>" class="btn btn-sm btn-outline-secondary">Tutup Detail</a>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><strong>Perusahaan:</strong> <?php echo htmlspecialchars($selected['company_name_snapshot']); ?></div>
                    <div class="col-md-6"><strong>Walk In Initiator:</strong> <?php echo htmlspecialchars((string) ($selected['walkin_initiator_snapshot'] ?? '-')); ?></div>
                    <div class="col-md-3"><strong>Tanggal Walk In:</strong> <?php echo htmlspecialchars((string) ($selected['walkin_date'] ?? '-')); ?></div>
                    <div class="col-md-3"><strong>Tanggal Submit:</strong> <?php echo htmlspecialchars((string) $selected['created_at']); ?></div>
                    <div class="col-md-4"><strong>Nama:</strong> <?php echo htmlspecialchars($selected['name']); ?></div>
                    <div class="col-md-4"><strong>Email:</strong> <?php echo htmlspecialchars($selected['email']); ?></div>
                    <div class="col-md-4"><strong>Phone:</strong> <?php echo htmlspecialchars($selected['phone']); ?></div>
                    <div class="col-md-4"><strong>Domisili:</strong> <?php echo htmlspecialchars($selected['domisili']); ?></div>
                    <div class="col-md-4"><strong>Gender:</strong> <?php echo htmlspecialchars($selected['gender']); ?></div>
                    <div class="col-md-4"><strong>Umur:</strong> <?php echo htmlspecialchars($selected['age_range']); ?></div>
                    <div class="col-md-4"><strong>Pendidikan:</strong> <?php echo htmlspecialchars($selected['education']); ?></div>
                    <div class="col-md-4"><strong>Kepuasan:</strong> <?php echo (int) $selected['rating_satisfaction']; ?>/5</div>
                    <div class="col-md-12"><strong>Masukan Umum:</strong><br><?php echo nl2br(htmlspecialchars($selected['general_feedback'])); ?></div>
                    <div class="col-md-12"><strong>Aspek Perbaikan:</strong> <?php echo htmlspecialchars(implode(', ', decode_json_array($selected['improvement_aspects']))); ?></div>
                    <div class="col-md-12"><strong>Kritik &amp; Saran Aspek Perbaikan:</strong><br><?php echo nl2br(htmlspecialchars($selected['feedback_improvement_aspects'])); ?></div>
                </div>
                <hr>
                <div class="row g-2 small">
                    <div class="col-md-6"><strong>Info source:</strong> <?php echo htmlspecialchars(implode(', ', decode_json_array($selected['info_sources']))); ?></div>
                    <div class="col-md-6"><strong>Job portal:</strong> <?php echo htmlspecialchars(implode(', ', decode_json_array($selected['job_portals']))); ?></div>
                    <div class="col-md-6"><strong>Kelebihan:</strong> <?php echo htmlspecialchars(implode(', ', decode_json_array($selected['strengths']))); ?></div>
                    <div class="col-md-6"><strong>Kurang/belum ada:</strong> <?php echo htmlspecialchars(implode(', ', decode_json_array($selected['missing_infos']))); ?></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-bordered align-middle" id="responsesTable">
            <thead>
                <tr>
                    <th style="width:70px;">ID</th>
                    <th>Perusahaan</th>
                    <th>Walk In Initiator</th>
                    <th>Nama</th>
                    <th>Email</th>
                    <th style="width:140px;">Tanggal Walk In</th>
                    <th style="width:110px;">Kepuasan</th>
                    <th style="width:180px;">Submitted</th>
                    <th style="width:150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="9" class="text-center text-muted">Belum ada data response survey.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo (int) $r['id']; ?></td>
                        <td><?php echo htmlspecialchars($r['company_name_snapshot']); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['walkin_initiator_snapshot'] ?? '-')); ?></td>
                        <td><?php echo htmlspecialchars($r['name']); ?></td>
                        <td><?php echo htmlspecialchars($r['email']); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['walkin_date'] ?? '-')); ?></td>
                        <td><?php echo (int) $r['rating_satisfaction']; ?>/5</td>
                        <td><?php echo htmlspecialchars((string) $r['created_at']); ?></td>
                        <td>
                            <a class="btn btn-sm btn-outline-primary" href="?<?php echo http_build_query(array_merge(['view' => (int) $r['id']], $filterQuery)); ?>">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a class="btn btn-sm btn-outline-danger" href="?delete=<?php echo (int) $r['id']; ?>" onclick="return confirm('Hapus response ini?');">
                                <i class="bi bi-trash"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('btnDownloadExcel');
    if (!btn || typeof XLSX === 'undefined') return;
    var exportRows = <?php echo json_encode($exportRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function toExcelCellValue(v) {
        if (v === null || typeof v === 'undefined') return '';
        if (Array.isArray(v)) {
            return v.map(function (item) { return String(item == null ? '' : item).trim(); }).filter(Boolean).join(', ');
        }
        if (typeof v === 'object') {
            try {
                return JSON.stringify(v);
            } catch (e) {
                return String(v);
            }
        }

        var str = String(v);
        var trimmed = str.trim();
        if ((trimmed.startsWith('[') && trimmed.endsWith(']')) || (trimmed.startsWith('{') && trimmed.endsWith('}'))) {
            try {
                var parsed = JSON.parse(trimmed);
                if (Array.isArray(parsed)) {
                    return parsed.map(function (item) { return String(item == null ? '' : item).trim(); }).filter(Boolean).join(', ');
                }
                if (parsed && typeof parsed === 'object') {
                    return Object.keys(parsed).map(function (key) {
                        return key + ': ' + String(parsed[key] == null ? '' : parsed[key]);
                    }).join(', ');
                }
            } catch (e) {
                // Keep original text when not valid JSON.
            }
        }
        return str;
    }

    btn.addEventListener('click', function () {
        if (!Array.isArray(exportRows) || exportRows.length === 0) {
            alert('Tidak ada data untuk diexport.');
            return;
        }

        var headerMap = {};
        exportRows.forEach(function (rowObj) {
            if (!rowObj || typeof rowObj !== 'object') return;
            Object.keys(rowObj).forEach(function (key) {
                headerMap[key] = true;
            });
        });
        var headers = Object.keys(headerMap);
        if (headers.length === 0) {
            alert('Data export tidak valid.');
            return;
        }

        var data = [headers];
        exportRows.forEach(function (rowObj) {
            var row = [];
            headers.forEach(function (key) {
                var v = rowObj && Object.prototype.hasOwnProperty.call(rowObj, key) ? rowObj[key] : '';
                row.push(toExcelCellValue(v));
            });
            data.push(row);
        });

        var ws = XLSX.utils.aoa_to_sheet(data);
        var wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Survey Responses');
        var fileDate = new Date().toISOString().slice(0, 10);
        var from = <?php echo json_encode($filterDateFrom); ?>;
        var to = <?php echo json_encode($filterDateTo); ?>;
        var suffix = '';
        if (from || to) {
            suffix = '_' + (from || 'all') + '_to_' + (to || 'all');
        }
        XLSX.writeFile(wb, 'walkin_survey_responses_full_' + fileDate + suffix + '.xlsx');
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>


