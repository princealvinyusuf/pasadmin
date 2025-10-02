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
    postings_count INT NOT NULL DEFAULT 0,
    quota_count INT NOT NULL DEFAULT 0,
    ratio_wlkp_percent DECIMAL(8,2) NOT NULL DEFAULT 0,
    realization_percent DECIMAL(8,2) NOT NULL DEFAULT 0,
    disability_need_count INT NOT NULL DEFAULT 0,
    nilai_akhir_postings INT NOT NULL DEFAULT 0,
    indeks_postings DECIMAL(10,2) NOT NULL DEFAULT 0,
    nilai_akhir_quota INT NOT NULL DEFAULT 0,
    indeks_quota DECIMAL(10,2) NOT NULL DEFAULT 0,
    nilai_akhir_ratio INT NOT NULL DEFAULT 0,
    indeks_ratio DECIMAL(10,2) NOT NULL DEFAULT 0,
    nilai_akhir_realization INT NOT NULL DEFAULT 0,
    indeks_realization DECIMAL(10,2) NOT NULL DEFAULT 0,
    nilai_akhir_disability INT NOT NULL DEFAULT 0,
    indeks_disability DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_indeks DECIMAL(10,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Top 15 by total_indeks
$result = $conn->query('SELECT id, company_name, total_indeks, created_at FROM naker_award_assessments ORDER BY total_indeks DESC, company_name ASC LIMIT 15');
$rows = [];
while ($r = $result->fetch_assoc()) { $rows[] = $r; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Naker Award - Second Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Naker Award - Second Assessment</h2>
        <a class="btn btn-outline-secondary" href="naker_award_stage1_shortlisted_c.php">Back to Stage 1</a>
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


