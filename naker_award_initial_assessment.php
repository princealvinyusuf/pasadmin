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
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_company_created (company_name(100), created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Lightweight migration: convert numeric columns to VARCHAR to accept flexible inputs
// and avoid strict type failures during bulk imports. Safe to run repeatedly.
@$conn->query("ALTER TABLE naker_award_assessments 
    MODIFY postings_count VARCHAR(100) NOT NULL DEFAULT '0',
    MODIFY quota_count VARCHAR(100) NOT NULL DEFAULT '0',
    MODIFY ratio_wlkp_percent VARCHAR(100) NOT NULL DEFAULT '0',
    MODIFY realization_percent VARCHAR(100) NOT NULL DEFAULT '0',
    MODIFY disability_need_count VARCHAR(100) NOT NULL DEFAULT '0',
    MODIFY nilai_akhir_postings VARCHAR(100) NOT NULL DEFAULT '0',
    MODIFY indeks_postings VARCHAR(100) NOT NULL DEFAULT '0',
    MODIFY nilai_akhir_quota VARCHAR(100) NOT NULL DEFAULT '0',
    MODIFY indeks_quota VARCHAR(100) NOT NULL DEFAULT '0',
    MODIFY nilai_akhir_ratio VARCHAR(100) NOT NULL DEFAULT '0',
    MODIFY indeks_ratio VARCHAR(100) NOT NULL DEFAULT '0',
    MODIFY nilai_akhir_realization VARCHAR(100) NOT NULL DEFAULT '0',
    MODIFY indeks_realization VARCHAR(100) NOT NULL DEFAULT '0',
    MODIFY nilai_akhir_disability VARCHAR(100) NOT NULL DEFAULT '0',
    MODIFY indeks_disability VARCHAR(100) NOT NULL DEFAULT '0',
    MODIFY total_indeks VARCHAR(100) NOT NULL DEFAULT '0'");

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

$resultRow = null; $message = isset($_GET['msg']) ? (string)$_GET['msg'] : '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Naker Award - Initial Assessment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
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
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="naker_award_stage1_shortlisted_c.php">View Stage 1 Shortlisted C</a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bulkImportModal"><i class="bi bi-file-earmark-excel"></i> Import Excel</button>
            <a class="btn btn-outline-primary" href="https://paskerid.kemnaker.go.id/paskerid/public/pasadmin/download/TemplateBulking_Initial_Assessment.xlsx" target="_blank" rel="noopener">
                <i class="bi bi-download"></i> Download Template
            </a>
            <form method="post" class="d-inline" onsubmit="return confirmTruncatePin();">
                <input type="hidden" name="action" value="truncate_all">
                <input type="hidden" name="pin" id="truncate_pin" value="">
                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i> Clean All Tables</button>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <?php if (!empty($message)): ?>
                <div class="alert alert-info py-2 px-3 mb-3"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
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
                    <input type="number" step="0.01" min="0" name="angka_realisasi" id="angka_realisasi" class="form-control" required>
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
    // Bulk import handler (AJAX)
    if (isset($_POST['action']) && $_POST['action'] === 'bulk_import') {
        header('Content-Type: application/json');
        $rowsParam = $_POST['rows'] ?? null;
        if (is_string($rowsParam)) {
            $decoded = json_decode($rowsParam, true);
            $rows = is_array($decoded) ? $decoded : [];
        } elseif (is_array($rowsParam)) {
            $rows = $rowsParam;
        } else {
            $rows = [];
        }
        if (!is_array($rows)) { echo json_encode(['ok'=>false,'error'=>'Invalid rows JSON']); exit; }

        $inserted = 0; $errors = [];
        foreach ($rows as $idx => $r) {
            $company = trim((string)($r['company_name'] ?? ''));
            if ($company === '') { $errors[] = ['row'=>$idx+1,'error'=>'Missing company_name']; continue; }
            $postings = intval($r['postings_count'] ?? 0);
            $quota = intval($r['quota_count'] ?? 0);
            // Normalize decimals with commas for rencana and angka_realisasi
            $rencanaRaw = (string)($r['rencana_kebutuhan_wlkp'] ?? '0');
            $rencana = floatval(str_replace(',', '.', $rencanaRaw));
            $angkaRealisasiRaw = (string)($r['angka_realisasi'] ?? '0');
            $angkaRealisasi = floatval(str_replace(',', '.', $angkaRealisasiRaw));

            $ratio = ($rencana > 0) ? (($quota / $rencana) * 100.0) : 0.0;
            $realization = ($quota > 0) ? (($angkaRealisasi / $quota) * 100.0) : 0.0;
            $disability = intval($r['disability_need_count'] ?? 0);

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
            if (!$stmt) { $errors[] = ['row'=>$idx+1,'error'=>'Prepare failed: ' . $conn->error]; continue; }
            $s_company = $company;
            $s_postings = (string)$postings;
            $s_quota = (string)$quota;
            $s_ratio = (string)number_format($ratio, 2, '.', '');
            $s_realization = (string)number_format($realization, 2, '.', '');
            $s_disability = (string)$disability;
            $s_na_postings = (string)$na_postings;
            $s_idx_postings = (string)number_format($idx_postings, 2, '.', '');
            $s_na_quota = (string)$na_quota;
            $s_idx_quota = (string)number_format($idx_quota, 2, '.', '');
            $s_na_ratio = (string)$na_ratio;
            $s_idx_ratio = (string)number_format($idx_ratio, 2, '.', '');
            $s_na_realization = (string)$na_realization;
            $s_idx_realization = (string)number_format($idx_realization, 2, '.', '');
            $s_na_disability = (string)$na_disability;
            $s_idx_disability = (string)number_format($idx_disability, 2, '.', '');
            $s_total_indeks = (string)number_format($total_indeks, 2, '.', '');

            $okBind = $stmt->bind_param(
                'sssssssssssssssss',
                $s_company,
                $s_postings,
                $s_quota,
                $s_ratio,
                $s_realization,
                $s_disability,
                $s_na_postings,
                $s_idx_postings,
                $s_na_quota,
                $s_idx_quota,
                $s_na_ratio,
                $s_idx_ratio,
                $s_na_realization,
                $s_idx_realization,
                $s_na_disability,
                $s_idx_disability,
                $s_total_indeks
            );
            if (!$okBind) {
                $errors[] = ['row'=>$idx+1,'error'=>'Bind failed: ' . $stmt->error];
                $stmt->close();
                continue;
            }
            if (!$stmt->execute()) {
                $errors[] = ['row'=>$idx+1,'error'=>'Execute failed: ' . $stmt->error];
            } else {
                $inserted++;
            }
            $stmt->close();
        }
        echo json_encode(['ok'=>true,'inserted'=>$inserted,'errors'=>$errors]);
        exit;
    }
    // Truncate all related tables
    if (isset($_POST['action']) && $_POST['action'] === 'truncate_all') {
        if (!current_user_can('manage_settings')) { http_response_code(403); echo 'Forbidden'; exit; }
        $pin = trim((string)($_POST['pin'] ?? ''));
        $expectedPin = date('dmY');
        if ($pin !== $expectedPin) {
            header('Location: naker_award_initial_assessment.php?msg=' . urlencode('Invalid PIN'));
            exit;
        }
        // Disable FK checks to allow truncation order-insensitive
        $conn->query('SET FOREIGN_KEY_CHECKS=0');
        // List of all tables related to Naker Award
        $tables = [
            'naker_award_final_positions',
            'naker_award_verifications',
            'naker_award_third_general',
            'naker_award_second_mandatory',
            'naker_award_assessments'
        ];
        foreach ($tables as $t) { @$conn->query('TRUNCATE TABLE ' . $t); }
        $conn->query('SET FOREIGN_KEY_CHECKS=1');
        header('Location: naker_award_initial_assessment.php?msg=' . urlencode('All tables cleaned'));
        exit;
    }
    // Redo calculations for binding and save
    $company = trim($_POST['company_name'] ?? '');
    $postings = intval($_POST['postings_count'] ?? 0);
    $quota = intval($_POST['quota_count'] ?? 0);
    $rencanaRaw = $_POST['rencana_kebutuhan_wlkp'] ?? '0';
    $rencana = floatval(str_replace(',', '.', $rencanaRaw));
    $ratio = ($rencana > 0) ? (($quota / $rencana) * 100.0) : 0.0;
    $angkaRealisasiRaw = $_POST['angka_realisasi'] ?? '0';
    $angkaRealisasi = floatval(str_replace(',', '.', $angkaRealisasiRaw));
    $realization = ($quota > 0) ? (($angkaRealisasi / $quota) * 100.0) : 0.0;
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
    $s_company = $company;
    $s_postings = (string)$postings;
    $s_quota = (string)$quota;
    $s_ratio = (string)number_format($ratio, 2, '.', '');
    $s_realization = (string)number_format($realization, 2, '.', '');
    $s_disability = (string)$disability;
    $s_na_postings = (string)$na_postings;
    $s_idx_postings = (string)number_format($idx_postings, 2, '.', '');
    $s_na_quota = (string)$na_quota;
    $s_idx_quota = (string)number_format($idx_quota, 2, '.', '');
    $s_na_ratio = (string)$na_ratio;
    $s_idx_ratio = (string)number_format($idx_ratio, 2, '.', '');
    $s_na_realization = (string)$na_realization;
    $s_idx_realization = (string)number_format($idx_realization, 2, '.', '');
    $s_na_disability = (string)$na_disability;
    $s_idx_disability = (string)number_format($idx_disability, 2, '.', '');
    $s_total_indeks = (string)number_format($total_indeks, 2, '.', '');

    $stmt->bind_param(
        'sssssssssssssssss',
        $s_company,
        $s_postings,
        $s_quota,
        $s_ratio,
        $s_realization,
        $s_disability,
        $s_na_postings,
        $s_idx_postings,
        $s_na_quota,
        $s_idx_quota,
        $s_na_ratio,
        $s_idx_ratio,
        $s_na_realization,
        $s_idx_realization,
        $s_na_disability,
        $s_idx_disability,
        $s_total_indeks
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
<!-- Bulk Import Modal -->
<div class="modal fade" id="bulkImportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Excel - Initial Assessment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Choose Excel File (.xlsx)</label>
                    <input type="file" id="bulkFile" accept=".xlsx,.xls" class="form-control">
                    <div class="form-text">Expected columns: company_name, postings_count, quota_count, rencana_kebutuhan_wlkp, angka_realisasi, disability_need_count.</div>
                </div>
                <div id="bulkStatus" class="small text-muted"></div>
                <div class="progress mt-2" role="progressbar" aria-label="Bulk import" aria-valuemin="0" aria-valuemax="100">
                    <div id="bulkProgress" class="progress-bar" style="width: 0%"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-primary" id="startImportBtn" disabled>Start Import</button>
            </div>
        </div>
    </div>
    </div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var quotaInput = document.getElementById('quota_count');
    var rencanaInput = document.getElementById('rencana_kebutuhan_wlkp');
    var ratioInput = document.getElementById('ratio_wlkp_percent');
    var postingsInput = document.getElementById('postings_count');
    var angkaRealisasiInput = document.getElementById('angka_realisasi');
    var realizationInput = document.getElementById('realization_percent');

    function parseNumber(value) {
        if (typeof value === 'string') {
            value = value.replace(/,/g, '.');
        }
        var n = parseFloat(value);
        return isNaN(n) ? 0 : n;
    }

    function updateRatio() {
        var quota = parseNumber(quotaInput.value) || 0;
        var rencana = parseNumber(rencanaInput.value) || 0;
        var ratio = 0;
        if (rencana > 0) {
            ratio = (quota / rencana) * 100;
        }
        ratioInput.value = ratio.toFixed(2);
    }

    function updateRealization() {
        var quota = parseNumber(quotaInput.value) || 0;
        var angka = parseNumber(angkaRealisasiInput.value) || 0;
        var realization = 0;
        if (quota > 0) {
            realization = (angka / quota) * 100;
        }
        realizationInput.value = realization.toFixed(2);
    }

    quotaInput.addEventListener('input', updateRatio);
    rencanaInput.addEventListener('input', updateRatio);
    postingsInput.addEventListener('input', updateRealization);
    angkaRealisasiInput.addEventListener('input', updateRealization);
    updateRatio();
    updateRealization();

    // Bulk import logic
    var bulkFileInput = document.getElementById('bulkFile');
    var startImportBtn = document.getElementById('startImportBtn');
    var bulkStatus = document.getElementById('bulkStatus');
    var bulkProgress = document.getElementById('bulkProgress');

    function setProgress(done, total){
        var pct = total > 0 ? Math.round((done/total)*100) : 0;
        bulkProgress.style.width = pct + '%';
        bulkProgress.textContent = pct + '%';
    }

    bulkFileInput && bulkFileInput.addEventListener('change', function(){
        startImportBtn.disabled = !bulkFileInput.files || bulkFileInput.files.length === 0;
        bulkStatus.textContent = '';
        setProgress(0,1);
    });

    function readWorkbook(file){
        return new Promise(function(resolve, reject){
            var reader = new FileReader();
            reader.onload = function(e){
                try {
                    var data = new Uint8Array(e.target.result);
                    var wb = XLSX.read(data, {type:'array'});
                    resolve(wb);
                } catch(err){ reject(err); }
            };
            reader.onerror = reject;
            reader.readAsArrayBuffer(file);
        });
    }

    function normalizeNumber(x){
        if (x === undefined || x === null) return 0;
        if (typeof x === 'number') return x;
        var s = String(x).trim();
        if (!s) return 0;
        return parseFloat(s.replace(/,/g, '.')) || 0;
    }

    async function importRows(rows){
        var total = rows.length, ok = 0, fail = 0;
        setProgress(0, total);
        bulkStatus.textContent = 'Uploading ' + total + ' rows...';
        // Chunk to reduce payload size per request
        var chunkSize = 50;
        for (let i=0; i<rows.length; i+=chunkSize){
            var chunk = rows.slice(i, i+chunkSize);
            try {
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'bulk_import', rows: JSON.stringify(chunk) })
                });
                const data = await res.json();
                ok += (data.inserted || 0);
                fail += (data.errors ? data.errors.length : 0);
                if (data.errors && data.errors.length) {
                    const firstErr = data.errors[0];
                    bulkStatus.textContent = 'Error at row ' + firstErr.row + ': ' + firstErr.error;
                }
            } catch(err){
                fail += chunk.length;
            }
            setProgress(Math.min(i+chunk.length, total), total);
        }
        bulkStatus.textContent = 'Done. Inserted: ' + ok + ', Success: ' + fail + '.';
    }

    startImportBtn && startImportBtn.addEventListener('click', async function(){
        if (!bulkFileInput.files || bulkFileInput.files.length === 0) return;
        startImportBtn.disabled = true;
        try {
            var wb = await readWorkbook(bulkFileInput.files[0]);
            var firstSheet = wb.SheetNames[0];
            var ws = wb.Sheets[firstSheet];
            var json = XLSX.utils.sheet_to_json(ws, { defval: '', raw: false });
            // Map expected columns with fallback for common variants
            var rows = json.map(function(r){
                return {
                    company_name: r.company_name || r.Company || r['Nama Perusahaan'] || '',
                    postings_count: parseInt(r.postings_count || r.Postings || r['Jumlah Postingan Lowongan'] || '0', 10) || 0,
                    quota_count: parseInt(r.quota_count || r.Quota || r['Jumlah Kuota Lowongan'] || '0', 10) || 0,
                    rencana_kebutuhan_wlkp: normalizeNumber(r.rencana_kebutuhan_wlkp || r.Rencana || r['Rencana Kebutuhan Tenaga Kerja WLKP'] || '0'),
                    angka_realisasi: normalizeNumber(r.angka_realisasi || r.Realisasi || r['Angka Realisasi'] || '0'),
                    disability_need_count: parseInt(r.disability_need_count || r.Disability || r['Jumlah Kebutuhan Disabilitas'] || '0', 10) || 0
                };
            }).filter(function(r){ return (r.company_name || '').trim() !== ''; });
            await importRows(rows);
        } catch(err){
            bulkStatus.textContent = 'Failed: ' + (err && err.message ? err.message : String(err));
        } finally {
            startImportBtn.disabled = false;
        }
    });

    // Confirm truncate with PIN = ddmmyyyy
    window.confirmTruncatePin = function(){
        var pinInput = prompt('Enter PIN (ddmmyyyy) to confirm delete:');
        if (pinInput === null) return false;
        var now = new Date();
        var dd = String(now.getDate()).padStart(2, '0');
        var mm = String(now.getMonth() + 1).padStart(2, '0');
        var yyyy = String(now.getFullYear());
        var expected = dd + mm + yyyy;
        if (pinInput.trim() !== expected) {
            alert('Invalid PIN. Operation cancelled.');
            return false;
        }
        var ok = confirm('Are you absolutely sure? This will TRUNCATE all Naker Award tables.');
        if (!ok) return false;
        var hidden = document.getElementById('truncate_pin');
        if (hidden) hidden.value = expected;
        return true;
    };
});
</script>
</body>
</html>


