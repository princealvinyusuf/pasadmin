<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';
if (!current_user_can('naker_award_verify') && !current_user_can('manage_settings')) { http_response_code(403); echo 'Forbidden'; exit; }

// Ensure required tables exist
$conn->query("CREATE TABLE IF NOT EXISTS naker_award_second_mandatory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    final_submitted TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_assessment (assessment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS naker_award_third_general (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    final_submitted TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_assessment (assessment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS naker_award_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    verified_by INT DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL,
    UNIQUE KEY uniq_assessment (assessment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle verify action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify') {
    $aid = intval($_POST['assessment_id'] ?? 0);
    if ($aid > 0) {
        $uid = intval($_SESSION['user_id'] ?? 0);
        $stmt = $conn->prepare('INSERT INTO naker_award_verifications (assessment_id, verified_by, verified_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE verified_by=VALUES(verified_by), verified_at=VALUES(verified_at)');
        $stmt->bind_param('ii', $aid, $uid);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: naker_award_verification.php');
    exit;
}

// Pull candidates who completed both submissions
$sql = "SELECT a.id, a.company_name, a.total_indeks,
               s.updated_at AS s2_updated, t.updated_at AS s3_updated,
               v.verified_at
        FROM naker_award_assessments a
        JOIN naker_award_second_mandatory s ON s.assessment_id=a.id AND s.final_submitted=1
        JOIN naker_award_third_general t ON t.assessment_id=a.id AND t.final_submitted=1
        LEFT JOIN naker_award_verifications v ON v.assessment_id=a.id
        ORDER BY a.total_indeks DESC, a.company_name ASC";
$res = $conn->query($sql);
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Naker Award - Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Naker Award - Verification</h2>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama Perusahaan</th>
                        <th>Total Indeks</th>
                        <th>Status</th>
                        <th>Actions</th>
                        <th>Stage 2</th>
                        <th>Third</th>
                        <th>Verified At</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i=1; foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo htmlspecialchars($row['company_name']); ?></td>
                        <td><strong><?php echo number_format((float)$row['total_indeks'], 2); ?></strong></td>
                        <td>
                            <?php
                            $statusHtml = empty($row['verified_at'])
                                ? '<span class="badge bg-warning text-dark">Pending verification</span>'
                                : '<span class="badge bg-success">Verified</span>';
                            echo $statusHtml;
                            ?>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary v-detail" data-assessment-id="<?php echo intval($row['id']); ?>">Detail</button>
                            <form method="post" class="d-inline" onsubmit="return confirm('Are you sure all the information provided is correct and are you fully responsible for the assessment conditions? This action cannot be undone. Please be careful.');">
                                <input type="hidden" name="action" value="verify">
                                <input type="hidden" name="assessment_id" value="<?php echo intval($row['id']); ?>">
                                <button type="submit" class="btn btn-sm btn-success">Verify</button>
                            </form>
                        </td>
                        <td><?php echo htmlspecialchars($row['s2_updated']); ?></td>
                        <td><?php echo htmlspecialchars($row['s3_updated']); ?></td>
                        <td><?php echo htmlspecialchars($row['verified_at'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal fade" id="verifyDetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="verifyDetailBody">
                    <div class="text-muted">Loading...</div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
    async function fetchDetail(assessmentId){
        const res = await fetch('verify_detail_ajax.php?assessment_id=' + encodeURIComponent(assessmentId));
        return await res.text();
    }
    for (const btn of document.querySelectorAll('.v-detail')){
        btn.addEventListener('click', async function(){
            const id = this.dataset.assessmentId;
            const body = document.getElementById('verifyDetailBody');
            body.innerHTML = '<div class="text-muted">Loading...</div>';
            const html = await fetchDetail(id);
            body.innerHTML = html;
            new bootstrap.Modal(document.getElementById('verifyDetailModal')).show();
        });
    }
})();
</script>
</body>
</html>


