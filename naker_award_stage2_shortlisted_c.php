<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';
if (!current_user_can('naker_award_view_stage2') && !current_user_can('naker_award_manage_second') && !current_user_can('manage_settings')) { http_response_code(403); echo 'Forbidden'; exit; }

// Ensure tables exist
$conn->query("CREATE TABLE IF NOT EXISTS naker_award_second_mandatory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    wlkp_status VARCHAR(100) DEFAULT NULL,
    wlkp_code VARCHAR(150) DEFAULT NULL,
    clearance_no_law_path VARCHAR(255) DEFAULT NULL,
    bpjstk_membership_doc_path VARCHAR(255) DEFAULT NULL,
    minimum_wage_doc_path VARCHAR(255) DEFAULT NULL,
    clearance_smk3_status_doc_path VARCHAR(255) DEFAULT NULL,
    smk3_certificate_copy_path VARCHAR(255) DEFAULT NULL,
    clearance_zero_accident_doc_path VARCHAR(255) DEFAULT NULL,
    final_submitted TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_assessment (assessment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Query companies that submitted mandatory data
$sql = "SELECT a.id, a.company_name, a.total_indeks, m.wlkp_status, m.wlkp_code, m.clearance_no_law_path, m.bpjstk_membership_doc_path, m.minimum_wage_doc_path, m.clearance_smk3_status_doc_path, m.smk3_certificate_copy_path, m.clearance_zero_accident_doc_path, m.updated_at
        FROM naker_award_assessments a
        JOIN naker_award_second_mandatory m ON m.assessment_id=a.id AND m.final_submitted=1
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
    <title>Naker Award - Stage 2 Shortlisted C</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Naker Award - Stage 2 Shortlisted C</h2>
        <a class="btn btn-outline-secondary" href="naker_award_second_assessment.php">Back to Second Assessment</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama Perusahaan</th>
                        <th>Total Indeks WLLP</th>
                        <th>Actions</th>
                        <th>Last Update</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i=1; foreach ($rows as $row): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo htmlspecialchars($row['company_name']); ?></td>
                        <td><strong><?php echo number_format((float)$row['total_indeks'], 2); ?></strong></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary s2-detail"
                                data-company="<?php echo htmlspecialchars($row['company_name']); ?>"
                                data-wlkp-status="<?php echo htmlspecialchars((string)$row['wlkp_status']); ?>"
                                data-wlkp-code="<?php echo htmlspecialchars((string)$row['wlkp_code']); ?>"
                                data-no-law="<?php echo htmlspecialchars((string)$row['clearance_no_law_path']); ?>"
                                data-bpjstk="<?php echo htmlspecialchars((string)$row['bpjstk_membership_doc_path']); ?>"
                                data-wage="<?php echo htmlspecialchars((string)$row['minimum_wage_doc_path']); ?>"
                                data-smk3-status="<?php echo htmlspecialchars((string)$row['clearance_smk3_status_doc_path']); ?>"
                                data-smk3-copy="<?php echo htmlspecialchars((string)$row['smk3_certificate_copy_path']); ?>"
                                data-zeroacc="<?php echo htmlspecialchars((string)$row['clearance_zero_accident_doc_path']); ?>"
                                data-bs-toggle="modal" data-bs-target="#stage2DetailModal">Detail</button>
                        </td>
                        <td><?php echo htmlspecialchars($row['updated_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal fade" id="stage2DetailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Mandatory Data Detail: <span id="s2_company"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr><th>No</th><th>Jenis Data/Informasi</th><th>Deskripsi / Bukti</th></tr>
                            </thead>
                            <tbody>
                                <tr><td>1</td><td>Status WLKP Perusahaan</td><td id="s2_wlkp_status"></td></tr>
                                <tr><td>2</td><td>Kode WLKP Perusahaan</td><td id="s2_wlkp_code"></td></tr>
                                <tr><td>3</td><td>Surat Clearance: Tidak sedang dalam proses hukum / indikasi pelanggaran</td><td id="s2_no_law"></td></tr>
                                <tr><td>4</td><td>Status Keanggotaan BPJS-TK</td><td id="s2_bpjstk"></td></tr>
                                <tr><td>5</td><td>Daftar perusahaan menerapkan Upah Minimum</td><td id="s2_wage"></td></tr>
                                <tr><td>6</td><td>Surat Clearance: Sudah menerapkan SMK3</td><td id="s2_smk3_status"></td></tr>
                                <tr><td>7</td><td>Copy Sertifikat SMK3</td><td id="s2_smk3_copy"></td></tr>
                                <tr><td>8</td><td>Surat Clearance: Zero Accident 2025</td><td id="s2_zeroacc"></td></tr>
                            </tbody>
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
    function linkify(path){ if (!path) return '<span class="text-muted">-</span>'; return '<a href="'+ path +'" target="_blank">View document</a>'; }
    for (const btn of document.querySelectorAll('.s2-detail')){
        btn.addEventListener('click', function(){
            const d = this.dataset;
            document.getElementById('s2_company').textContent = d.company || '';
            document.getElementById('s2_wlkp_status').textContent = d.wlkpStatus || '';
            document.getElementById('s2_wlkp_code').textContent = d.wlkpCode || '';
            document.getElementById('s2_no_law').innerHTML = linkify(d.noLaw);
            document.getElementById('s2_bpjstk').innerHTML = linkify(d.bpjstk);
            document.getElementById('s2_wage').innerHTML = linkify(d.wage);
            document.getElementById('s2_smk3_status').innerHTML = linkify(d.smk3Status);
            document.getElementById('s2_smk3_copy').innerHTML = linkify(d.smk3Copy);
            document.getElementById('s2_zeroacc').innerHTML = linkify(d.zeroacc);
        });
    }
})();
</script>
</body>
</html>


