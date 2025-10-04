<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';
if (!current_user_can('naker_award_manage_assessment') && !current_user_can('manage_settings')) { http_response_code(403); echo 'Forbidden'; exit; }

// Ensure table exists
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
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_company_created (company_name(100), created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Weights
$WEIGHT_POSTINGS = 30;   // %
$WEIGHT_QUOTA = 25;      // %
$WEIGHT_RATIO = 10;      // %
$WEIGHT_REALIZATION = 20;// %
$WEIGHT_DISABILITY = 15; // %

function calculate_nilai_akhir_for_postings(int $count): int {
    if ($count <= 0) { return 0; }
    if ($count >= 1 && $count <= 10) { return 60; }
    if ($count >= 11 && $count <= 50) { return 80; }
    return 100; // > 50
}

function calculate_nilai_akhir_for_quota(int $count): int {
    if ($count <= 0) { return 0; }
    if ($count >= 1 && $count <= 50) { return 60; }
    if ($count >= 51 && $count <= 100) { return 80; }
    return 100; // > 100
}

function calculate_nilai_akhir_for_percent(float $pct): int { // used by ratio & realization
    if ($pct < 10) { return 60; }
    if ($pct <= 50) { return 80; }
    return 100; // > 50
}

function calculate_nilai_akhir_for_disability(int $count): int {
    if ($count <= 0) { return 0; }
    if ($count >= 1 && $count <= 5) { return 60; }
    if ($count >= 6 && $count <= 10) { return 80; }
    return 100; // > 10
}

function compute_indeks(float $weightPercent, int $nilaiAkhir): float {
    return round(($weightPercent * $nilaiAkhir) / 100.0, 2);
}

$resultRow = null; $message = '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Naker Award - Initial Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f6f8fa; }
        .table thead th { background: #f1f5f9; }
    </style>
    </head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Naker Award - Initial Assessment</h2>
        <a class="btn btn-outline-secondary" href="naker_award_stage1_shortlisted_c.php">View Stage 1 Shortlisted C</a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label">Nama Perusahaan</label>
                    <input type="text" name="company_name" class="form-control" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Jumlah Postingan Lowongan</label>
                    <input type="number" name="postings_count" id="postings_count" min="0" class="form-control" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Jumlah Kuota Lowongan</label>
                    <input type="number" name="quota_count" id="quota_count" min="0" class="form-control" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Rencana Kebutuhan Tenaga Kerja WLKP</label>
                    <input type="number" step="0.01" min="0" name="rencana_kebutuhan_wlkp" id="rencana_kebutuhan_wlkp" class="form-control" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Ratio Lowongan Terhadap WLKP (%)</label>
                    <input type="number" step="0.01" min="0" name="ratio_wlkp_percent" id="ratio_wlkp_percent" class="form-control" readonly>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Angka Realisasi</label>
                    <input type="number" step="1" min="0" name="angka_realisasi" id="angka_realisasi" class="form-control" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Realisasi Penempatan TK (%)</label>
                    <input type="number" step="0.01" min="0" name="realization_percent" id="realization_percent" class="form-control" readonly>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Jumlah Kebutuhan Disabilitas</label>
                    <input type="number" min="0" name="disability_need_count" class="form-control" required>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Submit Assessment</button>
                </div>
            </form>
        </div>
    </div>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Redo calculations for binding and save
    $company = trim($_POST['company_name'] ?? '');
    $postings = intval($_POST['postings_count'] ?? 0);
    $quota = intval($_POST['quota_count'] ?? 0);
    $rencana = floatval($_POST['rencana_kebutuhan_wlkp'] ?? 0);
    $ratio = ($rencana > 0) ? (($quota / $rencana) * 100.0) : 0.0;
    $angkaRealisasi = floatval($_POST['angka_realisasi'] ?? 0);
    $realization = ($postings > 0) ? (($angkaRealisasi / $postings) * 100.0) : 0.0;
    $disability = intval($_POST['disability_need_count'] ?? 0);

    $na_postings = calculate_nilai_akhir_for_postings($postings);
    $na_quota = calculate_nilai_akhir_for_quota($quota);
    $na_ratio = calculate_nilai_akhir_for_percent($ratio);
    $na_realization = calculate_nilai_akhir_for_percent($realization);
    $na_disability = calculate_nilai_akhir_for_disability($disability);

    $idx_postings = compute_indeks($WEIGHT_POSTINGS, $na_postings);
    $idx_quota = compute_indeks($WEIGHT_QUOTA, $na_quota);
    $idx_ratio = compute_indeks($WEIGHT_RATIO, $na_ratio);
    $idx_realization = compute_indeks($WEIGHT_REALIZATION, $na_realization);
    $idx_disability = compute_indeks($WEIGHT_DISABILITY, $na_disability);
    $total_indeks = round($idx_postings + $idx_quota + $idx_ratio + $idx_realization + $idx_disability, 2);

    $stmt = $conn->prepare('INSERT INTO naker_award_assessments (
        company_name, postings_count, quota_count, ratio_wlkp_percent, realization_percent, disability_need_count,
        nilai_akhir_postings, indeks_postings, nilai_akhir_quota, indeks_quota, nilai_akhir_ratio, indeks_ratio,
        nilai_akhir_realization, indeks_realization, nilai_akhir_disability, indeks_disability, total_indeks
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->bind_param(
        'siiddiidididididd',
        $company,
        $postings,
        $quota,
        $ratio,
        $realization,
        $disability,
        $na_postings,
        $idx_postings,
        $na_quota,
        $idx_quota,
        $na_ratio,
        $idx_ratio,
        $na_realization,
        $idx_realization,
        $na_disability,
        $idx_disability,
        $total_indeks
    );
    $stmt->execute();
    $stmt->close();

    $resultRow = [
        'company_name' => $company,
        'postings_count' => $postings,
        'quota_count' => $quota,
        'ratio_wlkp_percent' => $ratio,
        'realization_percent' => $realization,
        'disability_need_count' => $disability,
        'na_postings' => $na_postings,
        'na_quota' => $na_quota,
        'na_ratio' => $na_ratio,
        'na_realization' => $na_realization,
        'na_disability' => $na_disability,
        'idx_postings' => $idx_postings,
        'idx_quota' => $idx_quota,
        'idx_ratio' => $idx_ratio,
        'idx_realization' => $idx_realization,
        'idx_disability' => $idx_disability,
        'total_indeks' => $total_indeks
    ];
}
?>

<?php if (!empty($resultRow)): ?>
    <div class="card">
        <div class="card-body">
            <h5 class="mb-3">Hasil Penilaian: <?php echo htmlspecialchars($resultRow['company_name']); ?></h5>
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>Deskripsi</th>
                            <th>Bobot</th>
                            <th>Nilai Aktual</th>
                            <th>Nilai Akhir</th>
                            <th>Indeks WLLP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Jumlah Postingan Lowongan</td>
                            <td>30%</td>
                            <td><?php echo intval($resultRow['postings_count']); ?></td>
                            <td><?php echo intval($resultRow['na_postings']); ?></td>
                            <td><?php echo number_format($resultRow['idx_postings'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>Jumlah Kuota Lowongan</td>
                            <td>25%</td>
                            <td><?php echo intval($resultRow['quota_count']); ?></td>
                            <td><?php echo intval($resultRow['na_quota']); ?></td>
                            <td><?php echo number_format($resultRow['idx_quota'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>Ratio Lowongan Terhadap WLKP</td>
                            <td>10%</td>
                            <td><?php echo number_format($resultRow['ratio_wlkp_percent'], 2); ?>%</td>
                            <td><?php echo intval($resultRow['na_ratio']); ?></td>
                            <td><?php echo number_format($resultRow['idx_ratio'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>Realisasi Penempatan TK</td>
                            <td>20%</td>
                            <td><?php echo number_format($resultRow['realization_percent'], 2); ?>%</td>
                            <td><?php echo intval($resultRow['na_realization']); ?></td>
                            <td><?php echo number_format($resultRow['idx_realization'], 2); ?></td>
                        </tr>
                        <tr>
                            <td>Jumlah Kebutuhan Disabilitas</td>
                            <td>15%</td>
                            <td><?php echo intval($resultRow['disability_need_count']); ?></td>
                            <td><?php echo intval($resultRow['na_disability']); ?></td>
                            <td><?php echo number_format($resultRow['idx_disability'], 2); ?></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-end">TOTAL INDEKS WLLP</th>
                            <th><?php echo number_format($resultRow['total_indeks'], 2); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var quotaInput = document.getElementById('quota_count');
    var rencanaInput = document.getElementById('rencana_kebutuhan_wlkp');
    var ratioInput = document.getElementById('ratio_wlkp_percent');
    var postingsInput = document.getElementById('postings_count');
    var angkaRealisasiInput = document.getElementById('angka_realisasi');
    var realizationInput = document.getElementById('realization_percent');

    function updateRatio() {
        var quota = parseFloat(quotaInput.value) || 0;
        var rencana = parseFloat(rencanaInput.value) || 0;
        var ratio = 0;
        if (rencana > 0) {
            ratio = (quota / rencana) * 100;
        }
        ratioInput.value = ratio.toFixed(2);
    }

    function updateRealization() {
        var postings = parseFloat(postingsInput.value) || 0;
        var angka = parseFloat(angkaRealisasiInput.value) || 0;
        var realization = 0;
        if (postings > 0) {
            realization = (angka / postings) * 100;
        }
        realizationInput.value = realization.toFixed(2);
    }

    quotaInput.addEventListener('input', updateRatio);
    rencanaInput.addEventListener('input', updateRatio);
    postingsInput.addEventListener('input', updateRealization);
    angkaRealisasiInput.addEventListener('input', updateRealization);
    updateRatio();
    updateRealization();
});
</script>
</body>
</html>


