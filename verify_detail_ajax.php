<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';
if (!current_user_can('naker_award_verify') && !current_user_can('manage_settings')) { http_response_code(403); echo 'Forbidden'; exit; }

$assessmentId = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;
if ($assessmentId <= 0) { http_response_code(400); echo 'Missing assessment_id'; exit; }

// Load company name
$stmt = $conn->prepare('SELECT company_name FROM naker_award_assessments WHERE id=?');
$stmt->bind_param('i', $assessmentId);
$stmt->execute();
$stmt->bind_result($companyName);
$stmt->fetch();
$stmt->close();

// Load Mandatory Data
$m = $conn->query('SELECT * FROM naker_award_second_mandatory WHERE assessment_id=' . intval($assessmentId))->fetch_assoc() ?: [];
// Load General Data
$g = $conn->query('SELECT * FROM naker_award_third_general WHERE assessment_id=' . intval($assessmentId))->fetch_assoc() ?: [];

header('Content-Type: text/html; charset=utf-8');
?>
<div class="mb-2"><strong><?php echo htmlspecialchars($companyName ?: ('ID ' . $assessmentId)); ?></strong></div>
<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-light fw-bold">Mandatory Data</div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item"><strong>Status WLKP:</strong> <?php echo htmlspecialchars($m['wlkp_status'] ?? '-'); ?></li>
                    <li class="list-group-item"><strong>Kode WLKP:</strong> <?php echo htmlspecialchars($m['wlkp_code'] ?? '-'); ?></li>
                    <li class="list-group-item"><strong>Clearance Hukum:</strong> <?php echo !empty($m['clearance_no_law_path'])?'<a target="_blank" href="'.htmlspecialchars($m['clearance_no_law_path']).'">View</a>':'-'; ?></li>
                    <li class="list-group-item"><strong>BPJS-TK:</strong> <?php echo !empty($m['bpjstk_membership_doc_path'])?'<a target="_blank" href="'.htmlspecialchars($m['bpjstk_membership_doc_path']).'">View</a>':'-'; ?></li>
                    <li class="list-group-item"><strong>Upah Minimum:</strong> <?php echo !empty($m['minimum_wage_doc_path'])?'<a target="_blank" href="'.htmlspecialchars($m['minimum_wage_doc_path']).'">View</a>':'-'; ?></li>
                    <li class="list-group-item"><strong>Clearance SMK3:</strong> <?php echo !empty($m['clearance_smk3_status_doc_path'])?'<a target="_blank" href="'.htmlspecialchars($m['clearance_smk3_status_doc_path']).'">View</a>':'-'; ?></li>
                    <li class="list-group-item"><strong>Sertifikat SMK3:</strong> <?php echo !empty($m['smk3_certificate_copy_path'])?'<a target="_blank" href="'.htmlspecialchars($m['smk3_certificate_copy_path']).'">View</a>':'-'; ?></li>
                    <li class="list-group-item"><strong>Zero Accident 2025:</strong> <?php echo !empty($m['clearance_zero_accident_doc_path'])?'<a target="_blank" href="'.htmlspecialchars($m['clearance_zero_accident_doc_path']).'">View</a>':'-'; ?></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-light fw-bold">General Data</div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item"><strong>Status Badan Hukum:</strong> <?php echo !empty($g['legal_entity_status_doc_path'])?'<a target="_blank" href="'.htmlspecialchars($g['legal_entity_status_doc_path']).'">View</a>':'-'; ?></li>
                    <li class="list-group-item"><strong>Surat Izin Operasional:</strong> <?php echo !empty($g['operational_permit_doc_path'])?'<a target="_blank" href="'.htmlspecialchars($g['operational_permit_doc_path']).'">View</a>':'-'; ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>


