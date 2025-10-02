<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';
if (!current_user_can('naker_award_manage_third') && !current_user_can('manage_settings')) { http_response_code(403); echo 'Forbidden'; exit; }

$assessmentId = isset($_GET['assessment_id']) ? intval($_GET['assessment_id']) : 0;
if ($assessmentId <= 0) { echo 'Missing assessment_id'; exit; }

// Ensure table for general data
$conn->query("CREATE TABLE IF NOT EXISTS naker_award_third_general (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    legal_entity_status_doc_path VARCHAR(255) DEFAULT NULL,
    operational_permit_doc_path VARCHAR(255) DEFAULT NULL,
    final_submitted TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_assessment (assessment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Load company name
$stmt = $conn->prepare('SELECT company_name FROM naker_award_assessments WHERE id=?');
$stmt->bind_param('i', $assessmentId);
$stmt->execute();
$stmt->bind_result($companyName);
$stmt->fetch();
$stmt->close();

$uploadDir = __DIR__ . '/uploads/naker_award_third/';
if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }

function save_file_third(mysqli $conn, int $assessmentId, string $field, array $file, string $uploadDir): ?string {
    $allowed = ['legal_entity_status_doc_path','operational_permit_doc_path'];
    if (!in_array($field, $allowed, true)) { return null; }
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) { return null; }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $basename = $assessmentId . '_' . $field . '_' . uniqid('', true) . ($ext ? ('.' . $ext) : '');
    $dest = rtrim($uploadDir, '/\\') . '/' . $basename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) { return null; }
    $rel = 'uploads/naker_award_third/' . $basename;
    $stmt = $conn->prepare('INSERT INTO naker_award_third_general (assessment_id, ' . $field . ') VALUES (?, ?) ON DUPLICATE KEY UPDATE ' . $field . '=VALUES(' . $field . ')');
    $stmt->bind_param('is', $assessmentId, $rel);
    $stmt->execute();
    $stmt->close();
    return $rel;
}

// Handle per-field save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_field') {
    $field = $_POST['field'] ?? '';
    if (isset($_FILES['file'])) {
        save_file_third($conn, $assessmentId, $field, $_FILES['file'], $uploadDir);
    }
    header('Location: naker_award_third_assessment_form.php?assessment_id=' . $assessmentId);
    exit;
}

// Final submit lock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'final_submit') {
    $conn->query('INSERT INTO naker_award_third_general (assessment_id, final_submitted) VALUES (' . intval($assessmentId) . ', 1) ON DUPLICATE KEY UPDATE final_submitted=1');
    header('Location: naker_award_third_assessment_form.php?assessment_id=' . $assessmentId);
    exit;
}

// Load existing
$data = [
    'legal_entity_status_doc_path' => null,
    'operational_permit_doc_path' => null,
    'final_submitted' => 0
];
$stmt = $conn->prepare('SELECT legal_entity_status_doc_path, operational_permit_doc_path, final_submitted FROM naker_award_third_general WHERE assessment_id=?');
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
    <title>Third Assessment - General Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">General Data: <?php echo htmlspecialchars($companyName ?: ('ID ' . $assessmentId)); ?></h2>
        <a class="btn btn-outline-secondary" href="naker_award_third_assessment.php">Back</a>
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
                            <th style="width:360px;">Upload / Link</th>
                            <th style="width:120px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td>Status Badan Hukum Perusahaan (Shortlisted Nominees)</td>
                            <td>
                                <form method="post" enctype="multipart/form-data" class="d-flex gap-2">
                                    <input type="hidden" name="action" value="save_field">
                                    <input type="hidden" name="field" value="legal_entity_status_doc_path">
                                    <input type="file" name="file" class="form-control" <?php echo $isLocked?'disabled':''; ?>>
                                    <button class="btn btn-primary" type="submit" <?php echo $isLocked?'disabled':''; ?>>Save</button>
                                </form>
                                <?php if (!empty($data['legal_entity_status_doc_path'])): ?>
                                <div class="mt-1"><a href="<?php echo htmlspecialchars($data['legal_entity_status_doc_path']); ?>" target="_blank">View document</a></div>
                                <?php endif; ?>
                            </td>
                            <td>Dokumen</td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td>Surat Izin Operasional Resmi Perusahaan (Shortlisted Nominees)</td>
                            <td>
                                <form method="post" enctype="multipart/form-data" class="d-flex gap-2">
                                    <input type="hidden" name="action" value="save_field">
                                    <input type="hidden" name="field" value="operational_permit_doc_path">
                                    <input type="file" name="file" class="form-control" <?php echo $isLocked?'disabled':''; ?>>
                                    <button class="btn btn-primary" type="submit" <?php echo $isLocked?'disabled':''; ?>>Save</button>
                                </form>
                                <?php if (!empty($data['operational_permit_doc_path'])): ?>
                                <div class="mt-1"><a href="<?php echo htmlspecialchars($data['operational_permit_doc_path']); ?>" target="_blank">View document</a></div>
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


