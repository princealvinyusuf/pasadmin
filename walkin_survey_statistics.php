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

function first_row_with_params(mysqli $conn, string $sql, string $types, array $params): array {
    $rows = fetch_rows_with_params($conn, $sql, $types, $params);
    return $rows[0] ?? [];
}

function esc($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$hasResponseTable = table_exists($conn, 'walk_in_survey_responses');
$hasInitiatorSnapshotCol = $hasResponseTable && column_exists($conn, 'walk_in_survey_responses', 'walkin_initiator_snapshot');
$hasWalkinDateCol = $hasResponseTable && column_exists($conn, 'walk_in_survey_responses', 'walkin_date');
$hasGenderCol = $hasResponseTable && column_exists($conn, 'walk_in_survey_responses', 'gender');
$hasAgeRangeCol = $hasResponseTable && column_exists($conn, 'walk_in_survey_responses', 'age_range');
$hasEducationCol = $hasResponseTable && column_exists($conn, 'walk_in_survey_responses', 'education');

$companies = [];
if (table_exists($conn, 'company_walk_in_survey')) {
    $resCompany = $conn->query("SELECT id, company_name FROM company_walk_in_survey ORDER BY sort_order ASC, company_name ASC");
    if ($resCompany) {
        while ($row = $resCompany->fetch_assoc()) {
            $companies[] = $row;
        }
    }
}

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

$dateExpr = $hasWalkinDateCol ? 'DATE(walkin_date)' : 'DATE(created_at)';
$whereSql = " WHERE 1=1";
$types = '';
$params = [];

if ($companyId > 0) {
    $whereSql .= " AND company_walk_in_survey_id = ?";
    $types .= 'i';
    $params[] = $companyId;
}
if ($filterDateFrom !== '' && $filterDateTo !== '') {
    $whereSql .= " AND {$dateExpr} BETWEEN ? AND ?";
    $types .= 'ss';
    $params[] = $filterDateFrom;
    $params[] = $filterDateTo;
} elseif ($filterDateFrom !== '') {
    $whereSql .= " AND {$dateExpr} >= ?";
    $types .= 's';
    $params[] = $filterDateFrom;
} elseif ($filterDateTo !== '') {
    $whereSql .= " AND {$dateExpr} <= ?";
    $types .= 's';
    $params[] = $filterDateTo;
}

$summary = [];
$ratingDistribution = [];
$companyStats = [];
$initiatorStats = [];
$dailyStats = [];
$genderStats = [];
$ageStats = [];
$educationStats = [];

if ($hasResponseTable) {
    $summary = first_row_with_params(
        $conn,
        "SELECT
            COUNT(*) AS total_responses,
            ROUND(AVG(rating_satisfaction), 2) AS avg_satisfaction,
            SUM(CASE WHEN rating_satisfaction >= 4 THEN 1 ELSE 0 END) AS positive_responses,
            COUNT(DISTINCT LOWER(TRIM(COALESCE(email, '')))) AS unique_emails
         FROM walk_in_survey_responses{$whereSql}",
        $types,
        $params
    );

    $ratingDistribution = fetch_rows_with_params(
        $conn,
        "SELECT rating_satisfaction, COUNT(*) AS total
         FROM walk_in_survey_responses{$whereSql}
         GROUP BY rating_satisfaction
         ORDER BY rating_satisfaction DESC",
        $types,
        $params
    );

    $companyStats = fetch_rows_with_params(
        $conn,
        "SELECT
            company_name_snapshot,
            COUNT(*) AS total,
            ROUND(AVG(rating_satisfaction), 2) AS avg_rating
         FROM walk_in_survey_responses{$whereSql}
         GROUP BY company_name_snapshot
         ORDER BY total DESC, company_name_snapshot ASC
         LIMIT 15",
        $types,
        $params
    );

    if ($hasInitiatorSnapshotCol) {
        $initiatorStats = fetch_rows_with_params(
            $conn,
            "SELECT
                COALESCE(NULLIF(TRIM(walkin_initiator_snapshot), ''), 'Tidak diketahui') AS initiator_name,
                COUNT(*) AS total
             FROM walk_in_survey_responses{$whereSql}
             GROUP BY initiator_name
             ORDER BY total DESC, initiator_name ASC
             LIMIT 15",
            $types,
            $params
        );
    }

    $dailyStats = fetch_rows_with_params(
        $conn,
        "SELECT {$dateExpr} AS survey_date, COUNT(*) AS total
         FROM walk_in_survey_responses{$whereSql}
         GROUP BY survey_date
         ORDER BY survey_date DESC
         LIMIT 31",
        $types,
        $params
    );

    if ($hasGenderCol) {
        $genderStats = fetch_rows_with_params(
            $conn,
            "SELECT COALESCE(NULLIF(TRIM(gender), ''), 'Tidak diketahui') AS label, COUNT(*) AS total
             FROM walk_in_survey_responses{$whereSql}
             GROUP BY label
             ORDER BY total DESC, label ASC",
            $types,
            $params
        );
    }
    if ($hasAgeRangeCol) {
        $ageStats = fetch_rows_with_params(
            $conn,
            "SELECT COALESCE(NULLIF(TRIM(age_range), ''), 'Tidak diketahui') AS label, COUNT(*) AS total
             FROM walk_in_survey_responses{$whereSql}
             GROUP BY label
             ORDER BY total DESC, label ASC",
            $types,
            $params
        );
    }
    if ($hasEducationCol) {
        $educationStats = fetch_rows_with_params(
            $conn,
            "SELECT COALESCE(NULLIF(TRIM(education), ''), 'Tidak diketahui') AS label, COUNT(*) AS total
             FROM walk_in_survey_responses{$whereSql}
             GROUP BY label
             ORDER BY total DESC, label ASC",
            $types,
            $params
        );
    }
}

$totalResponses = (int) ($summary['total_responses'] ?? 0);
$avgSatisfaction = (float) ($summary['avg_satisfaction'] ?? 0);
$positiveResponses = (int) ($summary['positive_responses'] ?? 0);
$uniqueEmails = (int) ($summary['unique_emails'] ?? 0);
$positiveRate = $totalResponses > 0 ? round(($positiveResponses / $totalResponses) * 100, 2) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-in Survey Statistik</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container mt-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h3 class="mb-0">Walk-in Survey Statistik</h3>
        <a href="walkin_survey_responses.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali ke Responses</a>
    </div>

    <?php if (!$hasResponseTable): ?>
        <div class="alert alert-warning">Table <code>walk_in_survey_responses</code> belum tersedia. Jalankan migration Laravel terlebih dahulu.</div>
    <?php else: ?>
    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-2">
                <div class="col-md-6 col-xl-4">
                    <label class="form-label mb-1">Perusahaan</label>
                    <select class="form-select" name="company_id">
                        <option value="0">Semua perusahaan</option>
                        <?php foreach ($companies as $c): ?>
                            <option value="<?php echo (int) $c['id']; ?>" <?php echo ((int) $companyId === (int) $c['id']) ? 'selected' : ''; ?>>
                                <?php echo esc($c['company_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label mb-1">Date From</label>
                    <input type="date" class="form-control" name="date_from" value="<?php echo esc($filterDateFrom); ?>">
                </div>
                <div class="col-md-6 col-xl-3">
                    <label class="form-label mb-1">Date To</label>
                    <input type="date" class="form-control" name="date_to" value="<?php echo esc($filterDateTo); ?>">
                </div>
                <div class="col-12 col-xl-2 d-flex align-items-end">
                    <div class="d-flex gap-2 w-100 justify-content-xl-end">
                        <button class="btn btn-primary w-100" type="submit"><i class="bi bi-search me-1"></i>Filter</button>
                        <a href="walkin_survey_statistics.php" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6 col-xl-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Total Responses</div><div class="fs-3 fw-semibold"><?php echo $totalResponses; ?></div></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Average Satisfaction</div><div class="fs-3 fw-semibold"><?php echo number_format($avgSatisfaction, 2); ?><span class="fs-6 text-muted">/5</span></div></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Positive Rate (4-5)</div><div class="fs-3 fw-semibold"><?php echo number_format($positiveRate, 2); ?>%</div></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Unique Emails</div><div class="fs-3 fw-semibold"><?php echo $uniqueEmails; ?></div></div></div></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><strong>Distribusi Kepuasan</strong></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead><tr><th>Rating</th><th>Total</th></tr></thead>
                            <tbody>
                            <?php if (empty($ratingDistribution)): ?>
                                <tr><td colspan="2" class="text-center text-muted">Belum ada data.</td></tr>
                            <?php else: foreach ($ratingDistribution as $row): ?>
                                <tr><td><?php echo (int) $row['rating_satisfaction']; ?>/5</td><td><?php echo (int) $row['total']; ?></td></tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><strong>Responses per Hari (31 terakhir)</strong></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead><tr><th>Tanggal</th><th>Total</th></tr></thead>
                            <tbody>
                            <?php if (empty($dailyStats)): ?>
                                <tr><td colspan="2" class="text-center text-muted">Belum ada data.</td></tr>
                            <?php else: foreach ($dailyStats as $row): ?>
                                <tr><td><?php echo esc($row['survey_date']); ?></td><td><?php echo (int) $row['total']; ?></td></tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><strong>Top Perusahaan</strong></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead><tr><th>Perusahaan</th><th>Total</th><th>Avg Rating</th></tr></thead>
                            <tbody>
                            <?php if (empty($companyStats)): ?>
                                <tr><td colspan="3" class="text-center text-muted">Belum ada data.</td></tr>
                            <?php else: foreach ($companyStats as $row): ?>
                                <tr>
                                    <td><?php echo esc($row['company_name_snapshot'] ?: '-'); ?></td>
                                    <td><?php echo (int) $row['total']; ?></td>
                                    <td><?php echo number_format((float) $row['avg_rating'], 2); ?>/5</td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><strong>Top Walk In Initiator</strong></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead><tr><th>Initiator</th><th>Total</th></tr></thead>
                            <tbody>
                            <?php if (empty($initiatorStats)): ?>
                                <tr><td colspan="2" class="text-center text-muted">Data initiator belum tersedia.</td></tr>
                            <?php else: foreach ($initiatorStats as $row): ?>
                                <tr><td><?php echo esc($row['initiator_name']); ?></td><td><?php echo (int) $row['total']; ?></td></tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><strong>Gender</strong></div>
                <div class="card-body">
                    <?php if (empty($genderStats)): ?>
                        <p class="text-muted mb-0">Data gender belum tersedia.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($genderStats as $row): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo esc($row['label']); ?>
                                    <span class="badge text-bg-primary rounded-pill"><?php echo (int) $row['total']; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><strong>Umur</strong></div>
                <div class="card-body">
                    <?php if (empty($ageStats)): ?>
                        <p class="text-muted mb-0">Data umur belum tersedia.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($ageStats as $row): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo esc($row['label']); ?>
                                    <span class="badge text-bg-primary rounded-pill"><?php echo (int) $row['total']; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header"><strong>Pendidikan</strong></div>
                <div class="card-body">
                    <?php if (empty($educationStats)): ?>
                        <p class="text-muted mb-0">Data pendidikan belum tersedia.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($educationStats as $row): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo esc($row['label']); ?>
                                    <span class="badge text-bg-primary rounded-pill"><?php echo (int) $row['total']; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
