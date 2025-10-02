<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';
if (!current_user_can('naker_award_view_stage1') && !current_user_can('naker_award_manage_assessment') && !current_user_can('manage_settings')) { http_response_code(403); echo 'Forbidden'; exit; }

// Ensure table exists (in case this page is opened first)
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

// Fetch all results sorted by total indeks desc
$res = $conn->query('SELECT * FROM naker_award_assessments ORDER BY total_indeks DESC, company_name ASC');
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Naker Award - Stage 1 Shortlisted C</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Naker Award - Stage 1 Shortlisted C</h2>
        <a class="btn btn-outline-secondary" href="naker_award_initial_assessment.php">Add Assessment</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama Perusahaan</th>
                        <th>Total Indeks WLLP</th>
                        <th>Nilai Akhir (Postingan, Kuota, Ratio, Realisasi, Disabilitas)</th>
                        <th>Tanggal</th>
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
                            echo intval($row['nilai_akhir_postings']) . ', ' .
                                 intval($row['nilai_akhir_quota']) . ', ' .
                                 intval($row['nilai_akhir_ratio']) . ', ' .
                                 intval($row['nilai_akhir_realization']) . ', ' .
                                 intval($row['nilai_akhir_disability']);
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['created_at']); ?></td>
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


