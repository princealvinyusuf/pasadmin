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

$sql = 'SELECT a.id, a.company_name, a.total_indeks, a.created_at, m.final_submitted, m.id AS m_id, '
    . $tierCase . ' AS tier '
    . ' FROM naker_award_assessments a '
    . ' LEFT JOIN naker_award_second_mandatory m ON m.assessment_id = a.id '
    . " WHERE CAST(IFNULL(NULLIF(a.total_indeks,'') ,'0') AS DECIMAL(15,4)) >= 60 "
    . ' ORDER BY tier ASC, '
    . " CAST(IFNULL(NULLIF(a.total_indeks,'') ,'0') AS DECIMAL(15,4)) DESC, "
    . " CAST(IFNULL(NULLIF(a.postings_count,'') ,'0') AS DECIMAL(15,4)) DESC, "
    . " CAST(IFNULL(NULLIF(a.quota_count,'') ,'0') AS DECIMAL(15,4)) DESC "
    . ' LIMIT 72';
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
        <a class="btn btn-outline-secondary" href="naker_award_stage1_shortlisted_c.php">Back to Stage 1</a>
    </div>

    <div class="mb-3">
        <span class="badge bg-warning text-dark">Criteria ranking active: Top 72 • Total Indeks ≥ 60</span>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama Perusahaan</th>
                        <th>Total Indeks WLLP</th>
                        <th>Tier</th>
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
                        <td><?php echo intval($row['tier']); ?></td>
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


