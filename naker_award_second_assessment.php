<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';
if (!current_user_can('naker_award_manage_second') && !current_user_can('manage_settings')) { http_response_code(403); echo 'Forbidden'; exit; }

// Ensure tables exist
$conn->query("CREATE TABLE IF NOT EXISTS naker_award_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(200) NOT NULL,
    postings_count VARCHAR(100) NOT NULL DEFAULT '0',
    quota_count VARCHAR(100) NOT NULL DEFAULT '0',
    ratio_wlkp_percent VARCHAR(100) NOT NULL DEFAULT '0',
    realization_percent VARCHAR(100) NOT NULL DEFAULT '0',
    disability_need_count VARCHAR(100) NOT NULL DEFAULT '0',
    nilai_akhir_postings VARCHAR(100) NOT NULL DEFAULT '0',
    indeks_postings VARCHAR(100) NOT NULL DEFAULT '0',
    nilai_akhir_quota VARCHAR(100) NOT NULL DEFAULT '0',
    indeks_quota VARCHAR(100) NOT NULL DEFAULT '0',
    nilai_akhir_ratio VARCHAR(100) NOT NULL DEFAULT '0',
    indeks_ratio VARCHAR(100) NOT NULL DEFAULT '0',
    nilai_akhir_realization VARCHAR(100) NOT NULL DEFAULT '0',
    indeks_realization VARCHAR(100) NOT NULL DEFAULT '0',
    nilai_akhir_disability VARCHAR(100) NOT NULL DEFAULT '0',
    indeks_disability VARCHAR(100) NOT NULL DEFAULT '0',
    total_indeks VARCHAR(100) NOT NULL DEFAULT '0',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure second mandatory table exists (for status)
$conn->query("CREATE TABLE IF NOT EXISTS naker_award_second_mandatory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    wlkp_status VARCHAR(100) DEFAULT NULL,
    wlkp_code VARCHAR(150) DEFAULT NULL,
    clearance_no_law_path VARCHAR(255) DEFAULT NULL,
    bpjstk_membership_doc_path VARCHAR(255) DEFAULT NULL,
    minimum_wage_doc_path VARCHAR(255) DEFAULT NULL,
    clearance_smk3_status_doc_path VARCHAR(255) DEFAULT NULL,
    smk3_certificate_copy_path VARCHAR(255) DEFAULT NULL,
    clearance_zero_accident_doc_path VARCHAR(255) DEFAULT NULL,
    final_submitted TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_assessment (assessment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Backfill new column for industrial dispute clearance if missing (compatibility with older deployments)
$colCheckSqlS = "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'naker_award_second_mandatory' AND COLUMN_NAME = 'clearance_industrial_dispute_doc_path'";
$colCheckResS = $conn->query($colCheckSqlS);
if ($colCheckResS && ($rowS = $colCheckResS->fetch_assoc()) && intval($rowS['cnt']) === 0) {
    $conn->query("ALTER TABLE naker_award_second_mandatory ADD COLUMN clearance_industrial_dispute_doc_path VARCHAR(255) DEFAULT NULL");
}

// Criteria-style ranking (same as Stage 1 criteria filter): tiering and >= 60 filter, top 72
$k1 = "(CAST(IFNULL(NULLIF(a.postings_count,''),'0') AS DECIMAL(15,4)) > 0)";
$k2 = "(CAST(IFNULL(NULLIF(a.quota_count,''),'0') AS DECIMAL(15,4)) > 0)";
$k3 = "(CAST(IFNULL(NULLIF(a.ratio_wlkp_percent,''),'0') AS DECIMAL(15,4)) > 0)";
$k4 = "(CAST(IFNULL(NULLIF(a.tindak_lanjut_percent,''),'0') AS DECIMAL(15,4)) > 0)";
$k5 = "(CAST(IFNULL(NULLIF(a.realization_percent,''),'0') AS DECIMAL(15,4)) > 0)";
$k6 = "(CAST(IFNULL(NULLIF(a.disability_need_count,''),'0') AS DECIMAL(15,4)) > 0)";

$all6   = "((" . $k1 . ") AND (" . $k2 . ") AND (" . $k3 . ") AND (" . $k4 . ") AND (" . $k5 . ") AND (" . $k6 . "))";
$first5 = "((" . $k1 . ") AND (" . $k2 . ") AND (" . $k3 . ") AND (" . $k4 . ") AND (" . $k5 . "))";
$m1234  = "((" . $k1 . ") AND (" . $k2 . ") AND (" . $k3 . ") AND (" . $k4 . "))";
$m1236  = "((" . $k1 . ") AND (" . $k2 . ") AND (" . $k3 . ") AND (" . $k6 . "))";
$cntNZ  = "( (" . $k1 . ") + (" . $k2 . ") + (" . $k3 . ") + (" . $k4 . ") + (" . $k5 . ") + (" . $k6 . ") )";

$tierCase = "CASE\n"
    . "    WHEN " . $all6 . " THEN 1\n"
    . "    WHEN (NOT " . $all6 . " AND " . $first5 . ") THEN 2\n"
    . "    WHEN (NOT " . $all6 . " AND NOT " . $first5 . " AND " . $m1234 . ") THEN 3\n"
    . "    WHEN (NOT " . $all6 . " AND NOT " . $first5 . " AND NOT " . $m1234 . " AND " . $m1236 . ") THEN 4\n"
    . "    WHEN (NOT " . $all6 . " AND NOT " . $first5 . " AND NOT " . $m1234 . " AND NOT " . $m1236 . " AND " . $cntNZ . " = 3) THEN 5\n"
    . "    ELSE 6\n"
    . "END";

// Criteria 1 filter: only companies with both Minimum Wage doc and Industrial Dispute Clearance uploaded
$criteria1Active = (isset($_GET['criteria1']) && $_GET['criteria1'] === '1');
$criteria2Active = (isset($_GET['criteria2']) && $_GET['criteria2'] === '1');
$bothCriteriaActive = ($criteria1Active && $criteria2Active);

$select = 'SELECT a.id, a.company_name, a.total_indeks, a.created_at, m.final_submitted, m.id AS m_id, ' . $tierCase . ' AS tier';
$from   = ' FROM naker_award_assessments a LEFT JOIN naker_award_second_mandatory m ON m.assessment_id = a.id';
$where  = " WHERE CAST(IFNULL(NULLIF(a.total_indeks,'') ,'0') AS DECIMAL(15,4)) >= 60";
if ($criteria1Active) {
    $where .= " AND m.minimum_wage_doc_path IS NOT NULL AND m.minimum_wage_doc_path <> ''"
            . " AND m.clearance_industrial_dispute_doc_path IS NOT NULL AND m.clearance_industrial_dispute_doc_path <> ''";
}
if ($criteria2Active) {
    $where .= " AND m.clearance_no_law_path IS NOT NULL AND m.clearance_no_law_path <> ''"
            . " AND m.clearance_smk3_status_doc_path IS NOT NULL AND m.clearance_smk3_status_doc_path <> ''"
            . " AND m.smk3_certificate_copy_path IS NOT NULL AND m.smk3_certificate_copy_path <> ''"
            . " AND m.clearance_zero_accident_doc_path IS NOT NULL AND m.clearance_zero_accident_doc_path <> ''";
}
$order  = ' ORDER BY tier ASC,'
        . " CAST(IFNULL(NULLIF(a.total_indeks,'') ,'0') AS DECIMAL(15,4)) DESC,"
        . " CAST(IFNULL(NULLIF(a.postings_count,'') ,'0') AS DECIMAL(15,4)) DESC,"
        . " CAST(IFNULL(NULLIF(a.quota_count,'') ,'0') AS DECIMAL(15,4)) DESC";
$limit  = ' LIMIT 72';
$sql    = $select . $from . $where . $order . $limit;

// Export CSV handler using the same criteria-ranked query
if (isset($_GET['export']) && $_GET['export'] === '1') {
    $exportSql = 'SELECT a.*, ' . $tierCase . ' AS tier '
        . ' FROM naker_award_assessments a '
        . ' LEFT JOIN naker_award_second_mandatory m ON m.assessment_id = a.id '
        . " WHERE CAST(IFNULL(NULLIF(a.total_indeks,'') ,'0') AS DECIMAL(15,4)) >= 60 "
        . ' ORDER BY tier ASC, '
        . " CAST(IFNULL(NULLIF(a.total_indeks,'') ,'0') AS DECIMAL(15,4)) DESC, "
        . " CAST(IFNULL(NULLIF(a.postings_count,'') ,'0') AS DECIMAL(15,4)) DESC, "
        . " CAST(IFNULL(NULLIF(a.quota_count,'') ,'0') AS DECIMAL(15,4)) DESC "
        . ' LIMIT 72';
    $exportRes = $conn->query($exportSql);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="second_assessment_criteria_top72.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'final_rank','tier','company_name','total_indeks',
        'postings_count','quota_count','ratio_wlkp_percent',
        'tindak_lanjut_percent','realization_percent','disability_need_count',
        'created_at'
    ]);
    $rank = 1;
    if ($exportRes) {
        while ($er = $exportRes->fetch_assoc()) {
            fputcsv($out, [
                $rank++,
                intval($er['tier'] ?? 0),
                $er['company_name'] ?? '',
                $er['total_indeks'] ?? '0',
                $er['postings_count'] ?? '0',
                $er['quota_count'] ?? '0',
                $er['ratio_wlkp_percent'] ?? '0',
                $er['tindak_lanjut_percent'] ?? '0',
                $er['realization_percent'] ?? '0',
                $er['disability_need_count'] ?? '0',
                $er['created_at'] ?? ''
            ]);
        }
    }
    fclose($out);
    exit;
}

$result = $conn->query($sql);
$rows = [];
while ($r = $result->fetch_assoc()) { $rows[] = $r; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WLLP Award - Second Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">WLLP Award - Second Assessment</h2>
        <div class="d-flex gap-2">
            <a class="btn btn-success" href="naker_award_second_assessment.php?export=1">Export Top 72 (criteria) CSV</a>
            <?php if ($criteria1Active): ?>
                <a class="btn btn-warning" href="naker_award_second_assessment.php<?php echo $criteria2Active ? '?criteria2=1' : ''; ?>">Criteria 1 Active (Clear)</a>
            <?php else: ?>
                <a class="btn btn-outline-primary" href="naker_award_second_assessment.php?criteria1=1<?php echo $criteria2Active ? '&criteria2=1' : ''; ?>">Criteria 1</a>
            <?php endif; ?>
            <?php if ($criteria2Active): ?>
                <a class="btn btn-warning" href="naker_award_second_assessment.php<?php echo $criteria1Active ? '?criteria1=1' : ''; ?>">Criteria 2 Active (Clear)</a>
            <?php else: ?>
                <a class="btn btn-outline-secondary" href="naker_award_second_assessment.php?criteria2=1<?php echo $criteria1Active ? '&criteria1=1' : ''; ?>">Criteria 2</a>
            <?php endif; ?>
            <?php if ($bothCriteriaActive): ?>
                <a class="btn btn-warning" href="naker_award_second_assessment.php">Both Criteria Active (Clear)</a>
            <?php else: ?>
                <a class="btn btn-outline-dark" href="naker_award_second_assessment.php?criteria1=1&criteria2=1">Both Criteria</a>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="naker_award_stage1_shortlisted_c.php">Back to Stage 1</a>
        </div>
    </div>

    <div class="mb-3">
        <span class="badge bg-warning text-dark">Criteria ranking active: Top 72 • Total Indeks ≥ 60</span>
        <?php if ($criteria1Active): ?>
            <span class="badge bg-info text-dark ms-2">Criteria 1: Upah Minimum + Clearance Perselisihan HI uploaded</span>
        <?php endif; ?>
        <?php if ($criteria2Active): ?>
            <span class="badge bg-info text-dark ms-2">Criteria 2: Clearance Hukum, Clearance SMK3, Sertifikat SMK3, Zero Accident 2025 uploaded</span>
        <?php endif; ?>
        <?php if ($bothCriteriaActive): ?>
            <span class="badge bg-primary text-light ms-2">Both Criteria active</span>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama Perusahaan</th>
                        <th>Total Indeks WLLP</th>
                        <th>Tanggal</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i=1; foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo htmlspecialchars($row['company_name']); ?></td>
                        <td><strong><?php echo number_format((float)$row['total_indeks'], 2); ?></strong></td>
                        <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                        <td>
                            <?php
                            $statusHtml = '<span class="badge bg-secondary">Not yet submitted</span>';
                            if (isset($row['m_id']) && $row['m_id']) {
                                if (intval($row['final_submitted']) === 1) {
                                    $statusHtml = '<span class="badge bg-success">Submitted</span>';
                                } else {
                                    $statusHtml = '<span class="badge bg-warning text-dark">In progress</span>';
                                }
                            }
                            echo $statusHtml;
                            ?>
                        </td>
                        <td>
                            <a class="btn btn-sm btn-primary" href="naker_award_second_assessment_form.php?assessment_id=<?php echo intval($row['id']); ?>">Mandatory Data</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


