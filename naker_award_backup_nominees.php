<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';
if (!(current_user_can('naker_award_backup_nominees') || current_user_can('naker_award_manage_assessment') || current_user_can('manage_settings'))) { http_response_code(403); echo 'Forbidden'; exit; }

// Ensure metadata table exists
$conn->query("CREATE TABLE IF NOT EXISTS naker_award_backup_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(300) NOT NULL,
    row_count INT NOT NULL DEFAULT 0,
    triggered_by INT DEFAULT NULL,
    note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Helpers
function sanitize_filename(string $name): string {
    $name = preg_replace('/[^A-Za-z0-9_\-\.]+/', '_', $name);
    return trim($name, '_');
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'backup') {
    $uid = intval($_SESSION['user_id'] ?? 0);
    $ts = date('Ymd_His');
    $baseName = 'naker_award_nominees_' . $ts . '.csv';
    $safeName = sanitize_filename($baseName);
    $dir = __DIR__ . '/downloads';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $filePath = $dir . '/' . $safeName;

    $sql = "SELECT a.*,
                   v.verified_at,
                   p.position_rank,
                   p.rejected,
                   p.decided_at
            FROM naker_award_assessments a
            LEFT JOIN naker_award_verifications v ON v.assessment_id=a.id
            LEFT JOIN naker_award_final_positions p ON p.assessment_id=a.id
            ORDER BY CAST(a.total_indeks AS DECIMAL(10,2)) DESC, a.company_name ASC";
    $res = $conn->query($sql);

    $rowCount = 0;
    $fp = fopen($filePath, 'w');
    if ($res && $fp) {
        if ($row = $res->fetch_assoc()) {
            fputcsv($fp, array_keys($row));
            fputcsv($fp, $row);
            $rowCount = 1;
            while ($row = $res->fetch_assoc()) { fputcsv($fp, $row); $rowCount++; }
        } else {
            // Empty header from schema if no rows
            $headers = ['id','company_name','postings_count','quota_count','ratio_wlkp_percent','realization_percent','tindak_lanjut_total','tindak_lanjut_percent','disability_need_count','nilai_akhir_postings','indeks_postings','nilai_akhir_quota','indeks_quota','nilai_akhir_ratio','indeks_ratio','nilai_akhir_realization','indeks_realization','nilai_akhir_tindak','indeks_tindak','nilai_akhir_disability','indeks_disability','total_indeks','created_at','verified_at','position_rank','rejected','decided_at'];
            fputcsv($fp, $headers);
        }
        fclose($fp);
        $stmt = $conn->prepare('INSERT INTO naker_award_backup_runs (file_name, file_path, row_count, triggered_by, note) VALUES (?, ?, ?, ?, ?)');
        $note = 'Manual backup';
        $stmt->bind_param('ssiis', $safeName, $filePath, $rowCount, $uid, $note);
        $stmt->execute();
        $stmt->close();
        $message = 'Backup created: ' . htmlspecialchars($safeName) . ' (' . intval($rowCount) . ' rows)';
    } else {
        if ($fp) { fclose($fp); }
        $message = 'Failed to create backup file.';
    }
}

// Fetch backups
$runs = [];
$resRuns = $conn->query('SELECT * FROM naker_award_backup_runs ORDER BY created_at DESC, id DESC');
while ($r = $resRuns->fetch_assoc()) { $runs[] = $r; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Naker Award - Backup Data Nominees</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Naker Award - Backup Data Nominees</h2>
        <form method="post">
            <input type="hidden" name="action" value="backup">
            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-cloud-arrow-down me-1"></i>Backup Now</button>
        </form>
    </div>

    <?php if (!empty($message)): ?>
    <div class="alert alert-info py-2 mb-3"><?php echo $message; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>File</th>
                        <th>Rows</th>
                        <th>Created At</th>
                        <th>Last Update</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=1; foreach ($runs as $run): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo htmlspecialchars($run['file_name']); ?></td>
                        <td><?php echo intval($run['row_count']); ?></td>
                        <td><?php echo htmlspecialchars($run['created_at']); ?></td>
                        <td>
                            <?php
                            // Use the file's mtime as "Last Update"
                            $mtime = @filemtime($run['file_path']);
                            echo $mtime ? date('Y-m-d H:i:s', $mtime) : '-';
                            ?>
                        </td>
                        <td>
                            <?php
                            $rel = 'downloads/' . rawurlencode($run['file_name']);
                            ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo $rel; ?>" download>
                                <i class="bi bi-download me-1"></i>Download
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($runs)): ?>
                    <tr><td colspan="6" class="text-center text-muted">No backups yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


