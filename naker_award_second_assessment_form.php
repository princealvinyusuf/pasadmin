<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';
if (!current_user_can('naker_award_manage_second') && !current_user_can('manage_settings')) { http_response_code(403); echo 'Forbidden'; exit; }

// Assessment ID
$assessmentId = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;
if ($assessmentId <= 0) { echo 'Missing assessment_id'; exit; }

// Ensure table for mandatory data
$conn->query("CREATE TABLE IF NOT EXISTS naker_award_second_mandatory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    wlkp_status VARCHAR(100) DEFAULT NULL,
    wlkp_code VARCHAR(150) DEFAULT NULL,
    clearance_no_law_path VARCHAR(255) DEFAULT NULL,
    clearance_industrial_dispute_doc_path VARCHAR(255) DEFAULT NULL,
    bpjstk_membership_doc_path VARCHAR(255) DEFAULT NULL,
    minimum_wage_doc_path VARCHAR(255) DEFAULT NULL,
    clearance_smk3_status_doc_path VARCHAR(255) DEFAULT NULL,
    smk3_certificate_copy_path VARCHAR(255) DEFAULT NULL,
    clearance_zero_accident_doc_path VARCHAR(255) DEFAULT NULL,
    final_submitted TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_assessment (assessment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure new column exists for deployments where table was created earlier
// Backfill column if missing (compatible with older MySQL versions)
$colCheckSql = "SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'naker_award_second_mandatory' AND COLUMN_NAME = 'clearance_industrial_dispute_doc_path'";
$colCheckRes = $conn->query($colCheckSql);
if ($colCheckRes && ($row = $colCheckRes->fetch_assoc()) && intval($row['cnt']) === 0) {
    $conn->query("ALTER TABLE naker_award_second_mandatory ADD COLUMN clearance_industrial_dispute_doc_path VARCHAR(255) DEFAULT NULL");
}

// Fetch company info
$stmt = $conn->prepare('SELECT company_name FROM naker_award_assessments WHERE id=?');
$stmt->bind_param('i', $assessmentId);
$stmt->execute();
$stmt->bind_result($companyName);
$stmt->fetch();
$stmt->close();

// Prepare upload dir
$uploadDir = __DIR__ . '/uploads/naker_award_second/';
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }

// Helper to save or update a single text field
function save_text(mysqli $conn, int $assessmentId, string $field, ?string $value): void {
    $allowed = ['wlkp_status','wlkp_code'];
    if (!in_array($field, $allowed, true)) { return; }
    $stmt = $conn->prepare('INSERT INTO naker_award_second_mandatory (assessment_id, ' . $field . ') VALUES (?, ?) ON DUPLICATE KEY UPDATE ' . $field . '=VALUES(' . $field . ')');
    $stmt->bind_param('is', $assessmentId, $value);
    $stmt->execute();
    $stmt->close();
}

// Helper to save file
function save_file(mysqli $conn, int $assessmentId, string $field, array $file, string $uploadDir): ?string {
    $allowed = [
        'clearance_no_law_path',
        'clearance_industrial_dispute_doc_path',
        'bpjstk_membership_doc_path',
        'minimum_wage_doc_path',
        'clearance_smk3_status_doc_path',
        'smk3_certificate_copy_path',
        'clearance_zero_accident_doc_path'
    ];
    if (!in_array($field, $allowed, true)) { return null; }
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) { return null; }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $basename = $assessmentId . '_' . $field . '_' . uniqid('', true) . ($ext ? ('.' . $ext) : '');
    $dest = rtrim($uploadDir, '/\\') . '/' . $basename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) { return null; }
    $rel = 'uploads/naker_award_second/' . $basename;
    $stmt = $conn->prepare('INSERT INTO naker_award_second_mandatory (assessment_id, ' . $field . ') VALUES (?, ?) ON DUPLICATE KEY UPDATE ' . $field . '=VALUES(' . $field . ')');
    $stmt->bind_param('is', $assessmentId, $rel);
    $stmt->execute();
    $stmt->close();
    return $rel;
}

// Handle per-field save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_field') {
    if (isset($_POST['field']) && $_POST['field'] === 'wlkp_status') {
        $val = trim($_POST['value'] ?? '');
        save_text($conn, $assessmentId, 'wlkp_status', $val);
    } elseif (isset($_POST['field']) && $_POST['field'] === 'wlkp_code') {
        $val = trim($_POST['value'] ?? '');
        save_text($conn, $assessmentId, 'wlkp_code', $val);
    } elseif (isset($_POST['field']) && isset($_FILES['file'])) {
        $field = $_POST['field'];
        save_file($conn, $assessmentId, $field, $_FILES['file'], $uploadDir);
    }
    header('Location: naker_award_second_assessment_form.php?assessment_id=' . $assessmentId);
    exit;
}

// Handle final submit (lock)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'final_submit') {
    $conn->query('UPDATE naker_award_second_mandatory SET final_submitted=1 WHERE assessment_id=' . intval($assessmentId));
    header('Location: naker_award_second_assessment_form.php?assessment_id=' . $assessmentId);
    exit;
}

// Load current data
$data = [
    'wlkp_status' => null,
    'wlkp_code' => null,
    'clearance_no_law_path' => null,
    'clearance_industrial_dispute_doc_path' => null,
    'bpjstk_membership_doc_path' => null,
    'minimum_wage_doc_path' => null,
    'clearance_smk3_status_doc_path' => null,
    'smk3_certificate_copy_path' => null,
    'clearance_zero_accident_doc_path' => null,
    'final_submitted' => 0
];
$stmt = $conn->prepare('SELECT wlkp_status,wlkp_code,clearance_no_law_path,clearance_industrial_dispute_doc_path,bpjstk_membership_doc_path,minimum_wage_doc_path,clearance_smk3_status_doc_path,smk3_certificate_copy_path,clearance_zero_accident_doc_path,final_submitted FROM naker_award_second_mandatory WHERE assessment_id=?');
$stmt->bind_param('i', $assessmentId);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) { $data = array_merge($data, $row); }
$stmt->close();

$isLocked = intval($data['final_submitted']) === 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Second Assessment - Mandatory Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Mandatory Data: <?php echo htmlspecialchars($companyName ?: ('ID ' . $assessmentId)); ?></h2>
        <a class="btn btn-outline-secondary" href="naker_award_second_assessment.php">Back</a>
    </div>

    <?php if ($isLocked): ?>
        <div class="alert alert-success">This record has been submitted and locked. Editing is disabled.</div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th style="width:60px;">No</th>
                            <th>Jenis Data/Informasi</th>
                            <th style="width:320px;">Deskripsi / Input</th>
                            <th style="width:120px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td>Status WLKP Perusahaan (Shortlisted Nominees)</td>
                            <td>
                                <form method="post" class="d-flex gap-2">
                                    <input type="hidden" name="action" value="save_field">
                                    <input type="hidden" name="field" value="wlkp_status">
                                    <input type="text" class="form-control" name="value" placeholder="Daftar/Lapor" value="<?php echo htmlspecialchars($data['wlkp_status'] ?? ''); ?>" <?php echo $isLocked?'disabled':''; ?>>
                                    <button class="btn btn-primary" type="submit" <?php echo $isLocked?'disabled':''; ?>>Save</button>
                                </form>
                            </td>
                            <td>Daftar/Lapor</td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td>Kode WLKP Perusahaan (Shortlisted Nominees)</td>
                            <td>
                                <form method="post" class="d-flex gap-2">
                                    <input type="hidden" name="action" value="save_field">
                                    <input type="hidden" name="field" value="wlkp_code">
                                    <input type="text" class="form-control" name="value" placeholder="No. Pendaftaran WLKP" value="<?php echo htmlspecialchars($data['wlkp_code'] ?? ''); ?>" <?php echo $isLocked?'disabled':''; ?>>
                                    <button class="btn btn-primary" type="submit" <?php echo $isLocked?'disabled':''; ?>>Save</button>
                                </form>
                            </td>
                            <td>No. Pendaftaran WLKP</td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td>Surat Clearance Tidak sedang dalam proses hukum / memiliki indikasi pelanggaran ketenagakerjaan</td>
                            <td>
                                <form method="post" enctype="multipart/form-data" class="d-flex gap-2">
                                    <input type="hidden" name="action" value="save_field">
                                    <input type="hidden" name="field" value="clearance_no_law_path">
                                    <input type="file" name="file" class="form-control" <?php echo $isLocked?'disabled':''; ?>>
                                    <button class="btn btn-primary" type="submit" <?php echo $isLocked?'disabled':''; ?>>Save</button>
                                </form>
                                <?php if (!empty($data['clearance_no_law_path'])): ?>
                                <div class="mt-1"><a href="<?php echo htmlspecialchars($data['clearance_no_law_path']); ?>" target="_blank">View document</a></div>
                                <?php endif; ?>
                            </td>
                            <td>Dokumen</td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td>Tidak sedang dalam proses kasus perselisihan hubungan industrial</td>
                            <td>
                                <form method="post" enctype="multipart/form-data" class="d-flex gap-2">
                                    <input type="hidden" name="action" value="save_field">
                                    <input type="hidden" name="field" value="clearance_industrial_dispute_doc_path">
                                    <input type="file" name="file" class="form-control" <?php echo $isLocked?'disabled':''; ?>>
                                    <button class="btn btn-primary" type="submit" <?php echo $isLocked?'disabled':''; ?>>Save</button>
                                </form>
                                <?php if (!empty($data['clearance_industrial_dispute_doc_path'])): ?>
                                <div class="mt-1"><a href="<?php echo htmlspecialchars($data['clearance_industrial_dispute_doc_path']); ?>" target="_blank">View document</a></div>
                                <?php endif; ?>
                            </td>
                            <td>Dokumen</td>
                        </tr>
                        <tr>
                            <td>5</td>
                            <td>Status Keanggotaan BPJS-TK (pemadanan data)</td>
                            <td>
                                <form method="post" enctype="multipart/form-data" class="d-flex gap-2">
                                    <input type="hidden" name="action" value="save_field">
                                    <input type="hidden" name="field" value="bpjstk_membership_doc_path">
                                    <input type="file" name="file" class="form-control" <?php echo $isLocked?'disabled':''; ?>>
                                    <button class="btn btn-primary" type="submit" <?php echo $isLocked?'disabled':''; ?>>Save</button>
                                </form>
                                <?php if (!empty($data['bpjstk_membership_doc_path'])): ?>
                                <div class="mt-1"><a href="<?php echo htmlspecialchars($data['bpjstk_membership_doc_path']); ?>" target="_blank">View document</a></div>
                                <?php endif; ?>
                            </td>
                            <td>Dokumen / No. Anggota</td>
                        </tr>
                        <tr>
                            <td>6</td>
                            <td>Daftar Perusahaan yang menerapkan Upah Minimum di setiap Provinsi</td>
                            <td>
                                <form method="post" enctype="multipart/form-data" class="d-flex gap-2">
                                    <input type="hidden" name="action" value="save_field">
                                    <input type="hidden" name="field" value="minimum_wage_doc_path">
                                    <input type="file" name="file" class="form-control" <?php echo $isLocked?'disabled':''; ?>>
                                    <button class="btn btn-primary" type="submit" <?php echo $isLocked?'disabled':''; ?>>Save</button>
                                </form>
                                <?php if (!empty($data['minimum_wage_doc_path'])): ?>
                                <div class="mt-1"><a href="<?php echo htmlspecialchars($data['minimum_wage_doc_path']); ?>" target="_blank">View document</a></div>
                                <?php endif; ?>
                            </td>
                            <td>Dokumen</td>
                        </tr>
                        <tr>
                            <td>7</td>
                            <td>Surat Clearance Sudah menerapkan SMK3</td>
                            <td>
                                <form method="post" enctype="multipart/form-data" class="d-flex gap-2">
                                    <input type="hidden" name="action" value="save_field">
                                    <input type="hidden" name="field" value="clearance_smk3_status_doc_path">
                                    <input type="file" name="file" class="form-control" <?php echo $isLocked?'disabled':''; ?>>
                                    <button class="btn btn-primary" type="submit" <?php echo $isLocked?'disabled':''; ?>>Save</button>
                                </form>
                                <?php if (!empty($data['clearance_smk3_status_doc_path'])): ?>
                                <div class="mt-1"><a href="<?php echo htmlspecialchars($data['clearance_smk3_status_doc_path']); ?>" target="_blank">View document</a></div>
                                <?php endif; ?>
                            </td>
                            <td>Dokumen</td>
                        </tr>
                        <tr>
                            <td>8</td>
                            <td>Copy Sertifikat SMK3</td>
                            <td>
                                <form method="post" enctype="multipart/form-data" class="d-flex gap-2">
                                    <input type="hidden" name="action" value="save_field">
                                    <input type="hidden" name="field" value="smk3_certificate_copy_path">
                                    <input type="file" name="file" class="form-control" <?php echo $isLocked?'disabled':''; ?>>
                                    <button class="btn btn-primary" type="submit" <?php echo $isLocked?'disabled':''; ?>>Save</button>
                                </form>
                                <?php if (!empty($data['smk3_certificate_copy_path'])): ?>
                                <div class="mt-1"><a href="<?php echo htmlspecialchars($data['smk3_certificate_copy_path']); ?>" target="_blank">View document</a></div>
                                <?php endif; ?>
                            </td>
                            <td>Dokumen</td>
                        </tr>
                        <tr>
                            <td>9</td>
                            <td>Surat Clearance Zero Accident 2025</td>
                            <td>
                                <form method="post" enctype="multipart/form-data" class="d-flex gap-2">
                                    <input type="hidden" name="action" value="save_field">
                                    <input type="hidden" name="field" value="clearance_zero_accident_doc_path">
                                    <input type="file" name="file" class="form-control" <?php echo $isLocked?'disabled':''; ?>>
                                    <button class="btn btn-primary" type="submit" <?php echo $isLocked?'disabled':''; ?>>Save</button>
                                </form>
                                <?php if (!empty($data['clearance_zero_accident_doc_path'])): ?>
                                <div class="mt-1"><a href="<?php echo htmlspecialchars($data['clearance_zero_accident_doc_path']); ?>" target="_blank">View document</a></div>
                                <?php endif; ?>
                            </td>
                            <td>Dokumen</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <form method="post" onsubmit="return confirm('Are you sure to submit? Data that has been submitted cannot be withdrawn.');">
        <input type="hidden" name="action" value="final_submit">
        <button type="submit" class="btn btn-primary" <?php echo $isLocked?'disabled':''; ?>>Submit Data</button>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


