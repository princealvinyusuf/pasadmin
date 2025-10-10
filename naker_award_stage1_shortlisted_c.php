<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';
if (!current_user_can('naker_award_view_stage1') && !current_user_can('naker_award_manage_assessment') && !current_user_can('manage_settings')) { http_response_code(403); echo 'Forbidden'; exit; }

// Load intervals to display dynamic ranges
try {
    $conn->query("CREATE TABLE IF NOT EXISTS naker_award_intervals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        indicator VARCHAR(50) NOT NULL,
        operator ENUM('<','<=','>','>=','==','between') NOT NULL DEFAULT 'between',
        min_value DECIMAL(15,4) NULL,
        max_value DECIMAL(15,4) NULL,
        nilai_akhir INT NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_indicator_sort (indicator, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {}
function get_intervals_grouped(mysqli $conn): array {
    $out = [];
    try {
        $res = $conn->query("SELECT * FROM naker_award_intervals WHERE active=1 ORDER BY indicator ASC, sort_order ASC, id ASC");
        while ($r = $res->fetch_assoc()) { $out[$r['indicator']][] = $r; }
    } catch (Throwable $e) {}
    return $out;
}
$intervals = get_intervals_grouped($conn);

// Ensure table exists (in case this page is opened first)
// Ensure assessments table exists with the latest columns
$conn->query("CREATE TABLE IF NOT EXISTS naker_award_assessments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(200) NOT NULL,
    postings_count VARCHAR(100) NOT NULL DEFAULT '0',
    quota_count VARCHAR(100) NOT NULL DEFAULT '0',
    ratio_wlkp_percent VARCHAR(100) NOT NULL DEFAULT '0',
    realization_percent VARCHAR(100) NOT NULL DEFAULT '0',
    tindak_lanjut_total VARCHAR(100) NOT NULL DEFAULT '0',
    tindak_lanjut_percent VARCHAR(100) NOT NULL DEFAULT '0',
    disability_need_count VARCHAR(100) NOT NULL DEFAULT '0',
    nilai_akhir_postings VARCHAR(100) NOT NULL DEFAULT '0',
    indeks_postings VARCHAR(100) NOT NULL DEFAULT '0',
    nilai_akhir_quota VARCHAR(100) NOT NULL DEFAULT '0',
    indeks_quota VARCHAR(100) NOT NULL DEFAULT '0',
    nilai_akhir_ratio VARCHAR(100) NOT NULL DEFAULT '0',
    indeks_ratio VARCHAR(100) NOT NULL DEFAULT '0',
    nilai_akhir_realization VARCHAR(100) NOT NULL DEFAULT '0',
    indeks_realization VARCHAR(100) NOT NULL DEFAULT '0',
    nilai_akhir_tindak VARCHAR(100) NOT NULL DEFAULT '0',
    indeks_tindak VARCHAR(100) NOT NULL DEFAULT '0',
    nilai_akhir_disability VARCHAR(100) NOT NULL DEFAULT '0',
    indeks_disability VARCHAR(100) NOT NULL DEFAULT '0',
    total_indeks VARCHAR(100) NOT NULL DEFAULT '0',
    kbli1 VARCHAR(100) DEFAULT NULL,
    kbli5 VARCHAR(100) DEFAULT NULL,
    kab_kota VARCHAR(200) DEFAULT NULL,
    provinsi VARCHAR(200) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Backfill new text columns if missing
try {
    $conn->query("ALTER TABLE naker_award_assessments ADD COLUMN IF NOT EXISTS kbli1 VARCHAR(100) DEFAULT NULL");
    $conn->query("ALTER TABLE naker_award_assessments ADD COLUMN IF NOT EXISTS kbli5 VARCHAR(100) DEFAULT NULL");
    $conn->query("ALTER TABLE naker_award_assessments ADD COLUMN IF NOT EXISTS kab_kota VARCHAR(200) DEFAULT NULL");
    $conn->query("ALTER TABLE naker_award_assessments ADD COLUMN IF NOT EXISTS provinsi VARCHAR(200) DEFAULT NULL");
} catch (Throwable $e) {
    try { $c=$conn->query("SHOW COLUMNS FROM naker_award_assessments LIKE 'kbli1'"); if($c&&$c->num_rows===0){$conn->query("ALTER TABLE naker_award_assessments ADD COLUMN kbli1 VARCHAR(100) DEFAULT NULL");} } catch (Throwable $e1) {}
    try { $c=$conn->query("SHOW COLUMNS FROM naker_award_assessments LIKE 'kbli5'"); if($c&&$c->num_rows===0){$conn->query("ALTER TABLE naker_award_assessments ADD COLUMN kbli5 VARCHAR(100) DEFAULT NULL");} } catch (Throwable $e2) {}
    try { $c=$conn->query("SHOW COLUMNS FROM naker_award_assessments LIKE 'kab_kota'"); if($c&&$c->num_rows===0){$conn->query("ALTER TABLE naker_award_assessments ADD COLUMN kab_kota VARCHAR(200) DEFAULT NULL");} } catch (Throwable $e3) {}
    try { $c=$conn->query("SHOW COLUMNS FROM naker_award_assessments LIKE 'provinsi'"); if($c&&$c->num_rows===0){$conn->query("ALTER TABLE naker_award_assessments ADD COLUMN provinsi VARCHAR(200) DEFAULT NULL");} } catch (Throwable $e4) {}
}

// Ensure weights table and load dynamic weights
$conn->query("CREATE TABLE IF NOT EXISTS naker_award_weights (
    id INT PRIMARY KEY,
    weight_postings INT NOT NULL DEFAULT 30,
    weight_quota INT NOT NULL DEFAULT 25,
    weight_ratio INT NOT NULL DEFAULT 10,
    weight_realization INT NOT NULL DEFAULT 20,
    weight_disability INT NOT NULL DEFAULT 15,
    weight_tindak INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$resW = $conn->query('SELECT weight_postings, weight_quota, weight_ratio, weight_realization, weight_disability, weight_tindak FROM naker_award_weights WHERE id=1');
$w = $resW ? $resW->fetch_assoc() : null;
$WEIGHT_POSTINGS = intval($w['weight_postings'] ?? 30);
$WEIGHT_QUOTA = intval($w['weight_quota'] ?? 25);
$WEIGHT_RATIO = intval($w['weight_ratio'] ?? 10);
$WEIGHT_REALIZATION = intval($w['weight_realization'] ?? 20);
$WEIGHT_DISABILITY = intval($w['weight_disability'] ?? 15);
$WEIGHT_TINDAK = intval($w['weight_tindak'] ?? 0);

// Fetch results sorted by total indeks desc; allow showing all via query param
$showAll = isset($_GET['all']) && $_GET['all'] === '1';
$useCriteria = isset($_GET['criteria']) && $_GET['criteria'] === '1';

// Build SQL based on criteria flag
if ($useCriteria) {
    // Map kriteria1..6 to our fields (as numeric > 0):
    // k1=postings_count, k2=quota_count, k3=ratio_wlkp_percent, k4=tindak_lanjut_percent, k5=realisasi (realization_percent), k6=disability_need_count
    $k1 = "(CAST(IFNULL(NULLIF(postings_count,''),'0') AS DECIMAL(15,4)) > 0)";
    $k2 = "(CAST(IFNULL(NULLIF(quota_count,''),'0') AS DECIMAL(15,4)) > 0)";
    $k3 = "(CAST(IFNULL(NULLIF(ratio_wlkp_percent,''),'0') AS DECIMAL(15,4)) > 0)";
    $k4 = "(CAST(IFNULL(NULLIF(tindak_lanjut_percent,''),'0') AS DECIMAL(15,4)) > 0)";
    $k5 = "(CAST(IFNULL(NULLIF(realization_percent,''),'0') AS DECIMAL(15,4)) > 0)";
    $k6 = "(CAST(IFNULL(NULLIF(disability_need_count,''),'0') AS DECIMAL(15,4)) > 0)";

    $all6    = "(($k1) AND ($k2) AND ($k3) AND ($k4) AND ($k5) AND ($k6))";
    $first5  = "(($k1) AND ($k2) AND ($k3) AND ($k4) AND ($k5))";
    $m1234   = "(($k1) AND ($k2) AND ($k3) AND ($k4))";
    $m1236   = "(($k1) AND ($k2) AND ($k3) AND ($k6))";
    $cntNZ   = "( ($k1) + ($k2) + ($k3) + ($k4) + ($k5) + ($k6) )";

    $tierCase = "CASE\n"
        . "    WHEN $all6 THEN 1\n"
        . "    WHEN (NOT $all6 AND $first5) THEN 2\n"
        . "    WHEN (NOT $all6 AND NOT $first5 AND $m1234) THEN 3\n"
        . "    WHEN (NOT $all6 AND NOT $first5 AND NOT $m1234 AND $m1236) THEN 4\n"
        . "    WHEN (NOT $all6 AND NOT $first5 AND NOT $m1234 AND NOT $m1236 AND $cntNZ = 3) THEN 5\n"
        . "    ELSE 6\n"
        . "END";

    $sqlList = 'SELECT *, ' . $tierCase . ' AS tier FROM naker_award_assessments '
        . ' WHERE CAST(IFNULL(NULLIF(total_indeks,\'\'),\'0\') AS DECIMAL(15,4)) >= 60 '
        . ' ORDER BY tier ASC, CAST(IFNULL(NULLIF(total_indeks,\'\'),\'0\') AS DECIMAL(15,4)) DESC, '
        . ' CAST(IFNULL(NULLIF(postings_count,\'\'),\'0\') AS DECIMAL(15,4)) DESC, '
        . ' CAST(IFNULL(NULLIF(quota_count,\'\'),\'0\') AS DECIMAL(15,4)) DESC '
        . ' LIMIT 72';
} else {
    $sqlList = 'SELECT * FROM naker_award_assessments ORDER BY total_indeks DESC, company_name ASC' . ($showAll ? '' : ' LIMIT 72');
}
$res = $conn->query($sqlList);
$rows = [];
while ($r = $res->fetch_assoc()) { $rows[] = $r; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WLLP Award - Stage 1 Shortlisted C</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* Sticky page header and table header */
        .sticky-page-header { position: sticky; top: 0; z-index: 1030; background: #f8f9fa; }
        .table-sticky { border-collapse: separate; border-spacing: 0; }
        .table-sticky thead th { position: sticky; top: 0; z-index: 2; background: #f8f9fa; }
    </style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 sticky-page-header py-2">
        <div class="d-flex flex-column">
            <h2 class="mb-0">WLLP Award - Stage 1 Shortlisted C</h2>
            <div class="text-muted small">All data is under authority of Datin</div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="naker_award_initial_assessment.php">Add Assessment</a>
            <a class="btn btn-primary" href="naker_award_stage1_shortlisted_c.php?all=1">Show All Data</a>
            <?php if ($useCriteria): ?>
            <a class="btn btn-outline-warning" href="naker_award_stage1_shortlisted_c.php">Clear Criteria Filter</a>
            <?php else: ?>
            <a class="btn btn-warning" href="naker_award_stage1_shortlisted_c.php?criteria=1">Filter Top 72 Indeks By Criteria</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
                    <div class="table-responsive">
            <table class="table table-striped mb-0 table-sticky">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama Perusahaan</th>
                        <th>Total Indeks WLLP</th>
                        <th>Nilai Akhir (Postingan, Kuota, Rasio, Tindak Lanjut, Penempatan, Disabilitas)</th>
						<th>Actions</th>
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
                                 intval($row['nilai_akhir_tindak']) . ', ' .
                                 intval($row['nilai_akhir_realization']) . ', ' .
                                 intval($row['nilai_akhir_disability']);
                            ?>
                        </td>
						<td>
							<button type="button" class="btn btn-sm btn-outline-primary detail-btn"
								data-company="<?php echo htmlspecialchars($row['company_name']); ?>"
								data-postings="<?php echo intval($row['postings_count']); ?>"
								data-quota="<?php echo intval($row['quota_count']); ?>"
								data-ratio="<?php echo number_format((float)$row['ratio_wlkp_percent'], 2, '.', ''); ?>"
								data-realization="<?php echo number_format((float)$row['realization_percent'], 2, '.', ''); ?>"
							data-disability="<?php echo intval($row['disability_need_count']); ?>"
							data-kbli1="<?php echo htmlspecialchars($row['kbli1'] ?? ''); ?>"
							data-kbli5="<?php echo htmlspecialchars($row['kbli5'] ?? ''); ?>"
							data-kabkota="<?php echo htmlspecialchars($row['kab_kota'] ?? ''); ?>"
							data-provinsi="<?php echo htmlspecialchars($row['provinsi'] ?? ''); ?>"
							data-tindak-actual="<?php echo number_format((float)($row['tindak_lanjut_percent'] ?? 0), 2, '.', ''); ?>"
							data-na-tindak="<?php echo intval($row['nilai_akhir_tindak'] ?? 0); ?>"
							data-idx-tindak="<?php echo number_format((float)($row['indeks_tindak'] ?? 0), 2, '.', ''); ?>"
								data-na-postings="<?php echo intval($row['nilai_akhir_postings']); ?>"
								data-idx-postings="<?php echo number_format((float)$row['indeks_postings'], 2, '.', ''); ?>"
								data-na-quota="<?php echo intval($row['nilai_akhir_quota']); ?>"
								data-idx-quota="<?php echo number_format((float)$row['indeks_quota'], 2, '.', ''); ?>"
								data-na-ratio="<?php echo intval($row['nilai_akhir_ratio']); ?>"
								data-idx-ratio="<?php echo number_format((float)$row['indeks_ratio'], 2, '.', ''); ?>"
								data-na-real="<?php echo intval($row['nilai_akhir_realization']); ?>"
								data-idx-real="<?php echo number_format((float)$row['indeks_realization'], 2, '.', ''); ?>"
								data-na-disab="<?php echo intval($row['nilai_akhir_disability']); ?>"
								data-idx-disab="<?php echo number_format((float)$row['indeks_disability'], 2, '.', ''); ?>"
								data-total="<?php echo number_format((float)$row['total_indeks'], 2, '.', ''); ?>"
								data-bs-toggle="modal" data-bs-target="#detailModal">Detail</button>
						</td>
                        <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

	<!-- Detail Modal -->
	<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
		<div class="modal-dialog modal-xl modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title">Hasil Penilaian: <span id="dm_company"></span></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
                <div class="modal-body">
                    <div class="mb-3 small">
                        <div class="row g-2">
                            <div class="col-12 col-md-3"><strong>KBLI1:</strong> <span id="dm_kbli1"></span></div>
                            <div class="col-12 col-md-3"><strong>KBLI5:</strong> <span id="dm_kbli5"></span></div>
                            <div class="col-12 col-md-3"><strong>Kab/Kota:</strong> <span id="dm_kabkota"></span></div>
                            <div class="col-12 col-md-3"><strong>Provinsi:</strong> <span id="dm_provinsi"></span></div>
                        </div>
                    </div>
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
									<td>Jumlah Postingan Loker</td>
                                    <td><?php echo intval($WEIGHT_POSTINGS); ?>%</td>
									<td id="dm_postings_actual"></td>
									<td id="dm_postings_na"></td>
									<td id="dm_postings_idx"></td>
								</tr>
								<tr>
                            <td>Jumlah Kuota Loker</td>
                                    <td><?php echo intval($WEIGHT_QUOTA); ?>%</td>
									<td id="dm_quota_actual"></td>
									<td id="dm_quota_na"></td>
									<td id="dm_quota_idx"></td>
								</tr>
								<tr>
                            <td>Rasio Loker Karirhub terhadap RTK WLKP</td>
                                    <td><?php echo intval($WEIGHT_RATIO); ?>%</td>
									<td id="dm_ratio_actual"></td>
									<td id="dm_ratio_na"></td>
									<td id="dm_ratio_idx"></td>
								</tr>
                                <tr>
                                    <td>Tindak Lanjut Lamaran</td>
                                    <td><?php echo intval($WEIGHT_TINDAK); ?>%</td>
                                    <td id="dm_tindak_actual"></td>
                                    <td id="dm_tindak_na"></td>
                                    <td id="dm_tindak_idx"></td>
                                </tr>
                                <tr>
                                    <td>Realisasi Penempatan TK</td>
                                    <td><?php echo intval($WEIGHT_REALIZATION); ?>%</td>
                                    <td id="dm_real_actual"></td>
                                    <td id="dm_real_na"></td>
                                    <td id="dm_real_idx"></td>
                                </tr>
								<tr>
									<td>Jumlah Kebutuhan Loker Disabilitas</td>
                                    <td><?php echo intval($WEIGHT_DISABILITY); ?>%</td>
									<td id="dm_disab_actual"></td>
									<td id="dm_disab_na"></td>
									<td id="dm_disab_idx"></td>
								</tr>
							</tbody>
							<tfoot>
								<tr>
									<th colspan="4" class="text-end">TOTAL INDEKS WLLP</th>
									<th id="dm_total"></th>
								</tr>
							</tfoot>
						</table>
					</div>
				<div class="mt-4">
					<h6 class="mb-2">Keterangan Perhitungan</h6>
					<p class="small text-muted mb-3">Nilai Akhir ditentukan dari Nilai Aktual berdasarkan kriteria pada tabel di bawah. Indeks WLLP dihitung dengan rumus: <strong>(Bobot &times; Nilai Akhir) / 100</strong>. Total Indeks WLLP adalah penjumlahan seluruh indeks indikator.</p>
					<div class="table-responsive">
						<table class="table table-bordered table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Indikator</th>
                                    <th>Interval</th>
                                    <th>Nilai</th>
                                </tr>
                            </thead>
							<tbody>
								<tr class="table-light"><th colspan="3">Jumlah Postingan Loker (Bobot <?php echo intval($WEIGHT_POSTINGS); ?>%)</th></tr>
                                <?php foreach (($intervals['postings'] ?? []) as $it): ?>
                                <tr>
                                    <td></td>
                                    <td>
                                        <?php
                                        $op = $it['operator']; $min = $it['min_value']; $max = $it['max_value'];
                                        $minI = ($min === null || $min === '') ? '' : (string)intval($min);
                                        $maxI = ($max === null || $max === '') ? '' : (string)intval($max);
                                        if ($op === 'between') { echo htmlspecialchars($minI) . ' - ' . htmlspecialchars($maxI); }
                                        elseif ($op === '<') { echo '&lt; ' . htmlspecialchars($minI); }
                                        elseif ($op === '<=') { echo '&le; ' . htmlspecialchars($minI); }
                                        elseif ($op === '>') { echo '&gt; ' . htmlspecialchars($minI); }
                                        elseif ($op === '>=') { echo '&ge; ' . htmlspecialchars($minI); }
                                        elseif ($op === '==') { echo '= ' . htmlspecialchars($minI); }
                                        ?>
                                    </td>
                                    <td><?php echo intval($it['nilai_akhir']); ?></td>
                                </tr>
                                <?php endforeach; ?>

                                <tr class="table-light"><th colspan="3">Jumlah Kuota Lowongan (Bobot <?php echo intval($WEIGHT_QUOTA); ?>%)</th></tr>
                                <?php foreach (($intervals['quota'] ?? []) as $it): ?>
                                <tr>
                                    <td></td>
                                    <td>
                                        <?php
                                        $op = $it['operator']; $min = $it['min_value']; $max = $it['max_value'];
                                        $minI = ($min === null || $min === '') ? '' : (string)intval($min);
                                        $maxI = ($max === null || $max === '') ? '' : (string)intval($max);
                                        if ($op === 'between') { echo htmlspecialchars($minI) . ' - ' . htmlspecialchars($maxI); }
                                        elseif ($op === '<') { echo '&lt; ' . htmlspecialchars($minI); }
                                        elseif ($op === '<=') { echo '&le; ' . htmlspecialchars($minI); }
                                        elseif ($op === '>') { echo '&gt; ' . htmlspecialchars($minI); }
                                        elseif ($op === '>=') { echo '&ge; ' . htmlspecialchars($minI); }
                                        elseif ($op === '==') { echo '= ' . htmlspecialchars($minI); }
                                        ?>
                                    </td>
                                    <td><?php echo intval($it['nilai_akhir']); ?></td>
                                </tr>
                                <?php endforeach; ?>

                                <tr class="table-light"><th colspan="3">Rasio Lowongan terhadap WLKP (Bobot <?php echo intval($WEIGHT_RATIO); ?>%)</th></tr>
                                <?php foreach (($intervals['ratio'] ?? []) as $it): ?>
                                <tr>
                                    <td></td>
                                    <td>
                                        <?php
                                        $op = $it['operator']; $min = $it['min_value']; $max = $it['max_value'];
                                        $minI = ($min === null || $min === '') ? '' : (string)intval($min);
                                        $maxI = ($max === null || $max === '') ? '' : (string)intval($max);
                                        if ($op === 'between') { echo htmlspecialchars($minI) . '% - ' . htmlspecialchars($maxI) . '%'; }
                                        elseif ($op === '<') { echo '&lt; ' . htmlspecialchars($minI) . '%'; }
                                        elseif ($op === '<=') { echo '&le; ' . htmlspecialchars($minI) . '%'; }
                                        elseif ($op === '>') { echo '&gt; ' . htmlspecialchars($minI) . '%'; }
                                        elseif ($op === '>=') { echo '&ge; ' . htmlspecialchars($minI) . '%'; }
                                        elseif ($op === '==') { echo '= ' . htmlspecialchars($minI) . '%'; }
                                        ?>
                                    </td>
                                    <td><?php echo intval($it['nilai_akhir']); ?></td>
                                </tr>
                                <?php endforeach; ?>

                                <tr class="table-light"><th colspan="3">Tindak Lanjut Lamaran (Bobot <?php echo intval($WEIGHT_TINDAK); ?>%)</th></tr>
                                <?php foreach (($intervals['tindak'] ?? []) as $it): ?>
                                <tr>
                                    <td></td>
                                    <td>
                                        <?php
                                        $op = $it['operator']; $min = $it['min_value']; $max = $it['max_value'];
                                        $minI = ($min === null || $min === '') ? '' : (string)intval($min);
                                        $maxI = ($max === null || $max === '') ? '' : (string)intval($max);
                                        if ($op === 'between') { echo htmlspecialchars($minI) . '% - ' . htmlspecialchars($maxI) . '%'; }
                                        elseif ($op === '<') { echo '&lt; ' . htmlspecialchars($minI) . '%'; }
                                        elseif ($op === '<=') { echo '&le; ' . htmlspecialchars($minI) . '%'; }
                                        elseif ($op === '>') { echo '&gt; ' . htmlspecialchars($minI) . '%'; }
                                        elseif ($op === '>=') { echo '&ge; ' . htmlspecialchars($minI) . '%'; }
                                        elseif ($op === '==') { echo '= ' . htmlspecialchars($minI) . '%'; }
                                        ?>
                                    </td>
                                    <td><?php echo intval($it['nilai_akhir']); ?></td>
                                </tr>
                                <?php endforeach; ?>

                                <tr class="table-light"><th colspan="3">Realisasi Penempatan Tenaga Kerja (Bobot <?php echo intval($WEIGHT_REALIZATION); ?>%)</th></tr>
                                <?php foreach (($intervals['realization'] ?? []) as $it): ?>
                                <tr>
                                    <td></td>
                                    <td>
                                        <?php
                                        $op = $it['operator']; $min = $it['min_value']; $max = $it['max_value'];
                                        $minI = ($min === null || $min === '') ? '' : (string)intval($min);
                                        $maxI = ($max === null || $max === '') ? '' : (string)intval($max);
                                        if ($op === 'between') { echo htmlspecialchars($minI) . '% - ' . htmlspecialchars($maxI) . '%'; }
                                        elseif ($op === '<') { echo '&lt; ' . htmlspecialchars($minI) . '%'; }
                                        elseif ($op === '<=') { echo '&le; ' . htmlspecialchars($minI) . '%'; }
                                        elseif ($op === '>') { echo '&gt; ' . htmlspecialchars($minI) . '%'; }
                                        elseif ($op === '>=') { echo '&ge; ' . htmlspecialchars($minI) . '%'; }
                                        elseif ($op === '==') { echo '= ' . htmlspecialchars($minI) . '%'; }
                                        ?>
                                    </td>
                                    <td><?php echo intval($it['nilai_akhir']); ?></td>
                                </tr>
                                <?php endforeach; ?>

								<tr class="table-light"><th colspan="3">Jumlah Kebutuhan Loker Disabilitas (Bobot <?php echo intval($WEIGHT_DISABILITY); ?>%)</th></tr>
                                <?php foreach (($intervals['disability'] ?? []) as $it): ?>
                                <tr>
                                    <td></td>
                                    <td>
                                        <?php
                                        $op = $it['operator']; $min = $it['min_value']; $max = $it['max_value'];
                                        $minI = ($min === null || $min === '') ? '' : (string)intval($min);
                                        $maxI = ($max === null || $max === '') ? '' : (string)intval($max);
                                        if ($op === 'between') { echo htmlspecialchars($minI) . ' - ' . htmlspecialchars($maxI); }
                                        elseif ($op === '<') { echo '&lt; ' . htmlspecialchars($minI); }
                                        elseif ($op === '<=') { echo '&le; ' . htmlspecialchars($minI); }
                                        elseif ($op === '>') { echo '&gt; ' . htmlspecialchars($minI); }
                                        elseif ($op === '>=') { echo '&ge; ' . htmlspecialchars($minI); }
                                        elseif ($op === '==') { echo '= ' . htmlspecialchars($minI); }
                                        ?>
                                    </td>
                                    <td><?php echo intval($it['nilai_akhir']); ?></td>
                                </tr>
                                <?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
				</div>
			</div>
		</div>
	</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
	function setText(id, text){ var el = document.getElementById(id); if (el) el.textContent = text; }
	function fmtInt(v){ return parseInt(v || 0, 10); }
	function fmtDec(v){ return Number(v || 0).toFixed(2); }
    function fmtPct(v){ return parseInt(Number(v || 0), 10) + '%'; }
	for (const btn of document.querySelectorAll('.detail-btn')){
		btn.addEventListener('click', function(){
			const d = this.dataset;
			setText('dm_company', d.company || '');
            setText('dm_kbli1', d.kbli1 || '');
            setText('dm_kbli5', d.kbli5 || '');
            setText('dm_kabkota', d.kabkota || '');
            setText('dm_provinsi', d.provinsi || '');
			setText('dm_postings_actual', fmtInt(d.postings));
			setText('dm_postings_na', fmtInt(d.naPostings));
			setText('dm_postings_idx', fmtDec(d.idxPostings));
			setText('dm_quota_actual', fmtInt(d.quota));
			setText('dm_quota_na', fmtInt(d.naQuota));
			setText('dm_quota_idx', fmtDec(d.idxQuota));
			setText('dm_ratio_actual', fmtPct(d.ratio));
			setText('dm_ratio_na', fmtInt(d.naRatio));
			setText('dm_ratio_idx', fmtDec(d.idxRatio));
			setText('dm_real_actual', fmtPct(d.realization));
			setText('dm_real_na', fmtInt(d.naReal));
			setText('dm_real_idx', fmtDec(d.idxReal));
			setText('dm_disab_actual', fmtInt(d.disability));
			setText('dm_disab_na', fmtInt(d.naDisab));
			setText('dm_disab_idx', fmtDec(d.idxDisab));
			setText('dm_tindak_actual', fmtPct(d.tindakActual));
			setText('dm_tindak_na', fmtInt(d.naTindak));
			setText('dm_tindak_idx', fmtDec(d.idxTindak));
			setText('dm_total', fmtDec(d.total));
		});
	}
})();
</script>
</body>
</html>


