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
									<td id="dm_postings_actual"></td>
									<td id="dm_postings_na"></td>
									<td id="dm_postings_idx"></td>
								</tr>
								<tr>
									<td>Jumlah Kuota Lowongan</td>
									<td>25%</td>
									<td id="dm_quota_actual"></td>
									<td id="dm_quota_na"></td>
									<td id="dm_quota_idx"></td>
								</tr>
								<tr>
									<td>Ratio Lowongan Terhadap WLKP</td>
									<td>10%</td>
									<td id="dm_ratio_actual"></td>
									<td id="dm_ratio_na"></td>
									<td id="dm_ratio_idx"></td>
								</tr>
								<tr>
									<td>Realisasi Penempatan TK</td>
									<td>20%</td>
									<td id="dm_real_actual"></td>
									<td id="dm_real_na"></td>
									<td id="dm_real_idx"></td>
								</tr>
								<tr>
									<td>Jumlah Kebutuhan Disabilitas</td>
									<td>15%</td>
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
	function fmtPct(v){ return Number(v || 0).toFixed(2) + '%'; }
	for (const btn of document.querySelectorAll('.detail-btn')){
		btn.addEventListener('click', function(){
			const d = this.dataset;
			setText('dm_company', d.company || '');
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
			setText('dm_total', fmtDec(d.total));
		});
	}
})();
</script>
</body>
</html>


