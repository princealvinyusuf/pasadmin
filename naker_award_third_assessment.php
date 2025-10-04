<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';
if (!current_user_can('naker_award_manage_third') && !current_user_can('manage_settings')) { http_response_code(403); echo 'Forbidden'; exit; }

// Ensure previous tables exist
$conn->query("CREATE TABLE IF NOT EXISTS naker_award_second_mandatory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    final_submitted TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_assessment (assessment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure third-stage general table exists (for status)
$conn->query("CREATE TABLE IF NOT EXISTS naker_award_third_general (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    legal_entity_status_doc_path VARCHAR(255) DEFAULT NULL,
    operational_permit_doc_path VARCHAR(255) DEFAULT NULL,
    final_submitted TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_assessment (assessment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Fetch only Stage 2 shortlisted, include third-stage status
$sql = "SELECT a.id, a.company_name, a.total_indeks, m.updated_at, g.id AS g_id, g.final_submitted AS g_final_submitted
        FROM naker_award_assessments a
        JOIN naker_award_second_mandatory m ON m.assessment_id=a.id AND m.final_submitted=1
        LEFT JOIN naker_award_third_general g ON g.assessment_id=a.id
        ORDER BY CAST(a.total_indeks AS DECIMAL(10,2)) DESC, a.company_name ASC";
$res = $conn->query($sql);
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Naker Award - Third Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Naker Award - Third Assessment</h2>
        <a class="btn btn-outline-secondary" href="naker_award_stage2_shortlisted_c.php">Back to Stage 2</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama Perusahaan</th>
                        <th>Total Indeks WLLP</th>
                        <th>Last Update</th>
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
                        <td><?php echo htmlspecialchars($row['updated_at']); ?></td>
                        <td>
                            <?php
                            $statusHtml = '<span class="badge bg-secondary">Not yet submitted</span>';
                            if (isset($row['g_id']) && $row['g_id']) {
                                if (intval($row['g_final_submitted']) === 1) {
                                    $statusHtml = '<span class="badge bg-success">Submitted</span>';
                                } else {
                                    $statusHtml = '<span class="badge bg-warning text-dark">In progress</span>';
                                }
                            }
                            echo $statusHtml;
                            ?>
                        </td>
                        <td>
                            <a class="btn btn-sm btn-primary" href="naker_award_third_assessment_form.php?assessment_id=<?php echo intval($row['id']); ?>">General Data</a>
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


