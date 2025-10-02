<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';
if (!current_user_can('naker_award_final_nominees') && !current_user_can('manage_settings')) { http_response_code(403); echo 'Forbidden'; exit; }

// Ensure tables exist
$conn->query("CREATE TABLE IF NOT EXISTS naker_award_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    verified_by INT DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL,
    UNIQUE KEY uniq_assessment (assessment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("CREATE TABLE IF NOT EXISTS naker_award_final_positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    position_rank INT DEFAULT NULL,
    rejected TINYINT(1) NOT NULL DEFAULT 0,
    decided_by INT DEFAULT NULL,
    decided_at DATETIME DEFAULT NULL,
    UNIQUE KEY uniq_assessment (assessment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle Assign
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign') {
    $aid = intval($_POST['assessment_id'] ?? 0);
    $pos = intval($_POST['position'] ?? 0);
    if ($aid > 0 && $pos >= 1 && $pos <= 5) {
        $uid = intval($_SESSION['user_id'] ?? 0);
        $stmt = $conn->prepare('INSERT INTO naker_award_final_positions (assessment_id, position_rank, rejected, decided_by, decided_at) VALUES (?, ?, 0, ?, NOW()) ON DUPLICATE KEY UPDATE position_rank=VALUES(position_rank), rejected=0, decided_by=VALUES(decided_by), decided_at=VALUES(decided_at)');
        $stmt->bind_param('iii', $aid, $pos, $uid);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: naker_award_final_nominees.php');
    exit;
}

// Handle Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reject') {
    $aid = intval($_POST['assessment_id'] ?? 0);
    if ($aid > 0) {
        $uid = intval($_SESSION['user_id'] ?? 0);
        $stmt = $conn->prepare('INSERT INTO naker_award_final_positions (assessment_id, rejected, decided_by, decided_at) VALUES (?, 1, ?, NOW()) ON DUPLICATE KEY UPDATE rejected=1, decided_by=VALUES(decided_by), decided_at=VALUES(decided_at)');
        $stmt->bind_param('ii', $aid, $uid);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: naker_award_final_nominees.php');
    exit;
}

// Candidates: verified
$sql = "SELECT a.id, a.company_name, a.total_indeks, v.verified_at, p.position_rank, p.rejected
        FROM naker_award_verifications v
        JOIN naker_award_assessments a ON a.id=v.assessment_id
        LEFT JOIN naker_award_final_positions p ON p.assessment_id=a.id
        ORDER BY COALESCE(p.position_rank, 999), a.total_indeks DESC, a.company_name ASC";
$res = $conn->query($sql);
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Naker Award - Final Nominees</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Naker Award - Final Nominees</h2>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama Perusahaan</th>
                        <th>Total Indeks</th>
                        <th>Ranking</th>
                        <th>Actions</th>
                        <th>Verified At</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i=1; foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo htmlspecialchars($row['company_name']); ?></td>
                        <td><strong><?php echo number_format((float)$row['total_indeks'], 2); ?></strong></td>
                        <td><?php echo ($row['rejected'] ? '<span class="text-danger">Rejected</span>' : (is_null($row['position_rank']) ? '<span class="text-muted">-</span>' : '<strong>#' . intval($row['position_rank']) . '</strong>')); ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-primary assign-btn" data-id="<?php echo intval($row['id']); ?>" data-company="<?php echo htmlspecialchars($row['company_name']); ?>" <?php echo $row['rejected']? 'disabled':''; ?>>Assign</button>
                            <form method="post" class="d-inline" onsubmit="return confirm('Reject this company from final nominees?');">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="assessment_id" value="<?php echo intval($row['id']); ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                            </form>
                        </td>
                        <td><?php echo htmlspecialchars($row['verified_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Assign Modal -->
    <div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" onsubmit="return confirmAssign();">
                    <div class="modal-header">
                        <h5 class="modal-title">Set As Position</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="assign">
                        <input type="hidden" name="assessment_id" id="assign_assessment_id" value="">
                        <div class="mb-2"><strong id="assign_company"></strong></div>
                        <div class="mb-2">
                            <label class="form-label">Set As Position</label>
                            <select class="form-select" name="position" id="assign_position" required>
                                <option value="">Select position...</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Set</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentCompanyName = '';
function confirmAssign(){
    const pos = document.getElementById('assign_position').value;
    if (!pos) return false;
    return confirm('Hereby declares the company ' + currentCompanyName + ' as the position ' + pos + ', in determining the recipient of the 2025 Naker Award.');
}
(function(){
    const modalEl = document.getElementById('assignModal');
    const modal = new bootstrap.Modal(modalEl);
    for (const btn of document.querySelectorAll('.assign-btn')){
        btn.addEventListener('click', function(){
            document.getElementById('assign_assessment_id').value = this.dataset.id;
            currentCompanyName = this.dataset.company || '';
            document.getElementById('assign_company').textContent = currentCompanyName;
            document.getElementById('assign_position').value = '';
            modal.show();
        });
    }
})();
</script>
</body>
</html>


