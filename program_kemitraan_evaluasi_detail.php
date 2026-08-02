<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

if (!(current_user_can('program_kemitraan_manage') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db_prod';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function decode_json_field($raw): array
{
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo 'Invalid ID';
    exit;
}

$stmt = $conn->prepare("SELECT * FROM program_kemitraan_evaluations WHERE id = ? LIMIT 1");
if (!$stmt) {
    die('Failed to prepare query: ' . $conn->error);
}
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$evaluation = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$evaluation) {
    http_response_code(404);
    echo 'Data evaluasi tidak ditemukan.';
    exit;
}

$answers = [];
$answerStmt = $conn->prepare("
    SELECT form_type, section_key, indicator_number, indicator_text, score, is_not_applicable
    FROM program_kemitraan_evaluation_answers
    WHERE evaluation_id = ?
    ORDER BY form_type ASC, section_key ASC, indicator_number ASC
");
if ($answerStmt) {
    $answerStmt->bind_param('i', $id);
    $answerStmt->execute();
    $answerRes = $answerStmt->get_result();
    if ($answerRes) {
        while ($row = $answerRes->fetch_assoc()) {
            $answers[] = $row;
        }
    }
    $answerStmt->close();
}

$rtlItems = [];
$rtlStmt = $conn->prepare("
    SELECT row_order, issue, follow_up, responsible_person, target_date, completion_indicator, status
    FROM program_kemitraan_evaluation_rtl_items
    WHERE evaluation_id = ?
    ORDER BY row_order ASC, id ASC
");
if ($rtlStmt) {
    $rtlStmt->bind_param('i', $id);
    $rtlStmt->execute();
    $rtlRes = $rtlStmt->get_result();
    if ($rtlRes) {
        while ($row = $rtlRes->fetch_assoc()) {
            $rtlItems[] = $row;
        }
    }
    $rtlStmt->close();
}

$preferredChannels = decode_json_field($evaluation['preferred_channels'] ?? '');
$monitoringMedia = decode_json_field($evaluation['monitoring_media'] ?? '');
$qualitativeFeedback = decode_json_field($evaluation['qualitative_feedback'] ?? '');
$indicatorAchievements = decode_json_field($evaluation['indicator_achievements'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Evaluasi Program Kemitraan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f4f7fb;
        }
        .shell {
            border: 1px solid rgba(148, 163, 184, 0.25);
            border-radius: 18px;
            box-shadow: 0 22px 45px rgba(15, 23, 42, 0.09);
            background: #fff;
            overflow: hidden;
        }
        .shell-header {
            padding: 1.1rem 1.3rem;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.10), rgba(16, 185, 129, 0.10));
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
        }
        .shell-content {
            padding: 1.1rem 1.2rem 1.25rem;
        }
        .data-table th {
            width: 320px;
            background: #f8fafc;
        }
        .small-muted {
            color: #64748b;
            font-size: 0.87rem;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>

<div class="container mt-4 mb-5">
    <div class="mb-3">
        <a href="program_kemitraan_evaluasi" class="btn btn-outline-secondary btn-sm">&larr; Kembali ke daftar evaluasi</a>
    </div>

    <div class="shell mb-4">
        <div class="shell-header">
            <h4 class="mb-1">Detail Form Evaluasi #<?php echo (int) $id; ?></h4>
            <div class="small-muted">
                <?php echo e((string) ($evaluation['activity_name'] ?? '-')); ?> |
                <?php echo e((string) ($evaluation['activity_date'] ?? '-')); ?> |
                Dibuat: <?php echo e((string) ($evaluation['created_at'] ?? '-')); ?>
            </div>
        </div>
        <div class="shell-content">
            <h6 class="fw-bold mb-2">Identitas Kegiatan</h6>
            <div class="table-responsive mb-4">
                <table class="table table-bordered table-sm data-table mb-0">
                    <tbody>
                        <tr><th>Nama kegiatan</th><td><?php echo e((string) ($evaluation['activity_name'] ?? '-')); ?></td></tr>
                        <tr><th>Tema/topik</th><td><?php echo e((string) ($evaluation['activity_theme'] ?? '-')); ?></td></tr>
                        <tr><th>Tanggal</th><td><?php echo e((string) ($evaluation['activity_date'] ?? '-')); ?></td></tr>
                        <tr><th>Waktu</th><td><?php echo e((string) ($evaluation['activity_start_time'] ?? '-')); ?> - <?php echo e((string) ($evaluation['activity_end_time'] ?? '-')); ?> <?php echo e((string) ($evaluation['activity_timezone'] ?? '')); ?></td></tr>
                        <tr><th>Tempat/media</th><td><?php echo e((string) ($evaluation['activity_location'] ?? '-')); ?></td></tr>
                        <tr><th>Penyelenggara</th><td><?php echo e((string) ($evaluation['activity_organizer'] ?? '-')); ?></td></tr>
                        <tr><th>Undangan / Hadir / Responden</th><td><?php echo e((string) ($evaluation['participants_invited'] ?? '-')); ?> / <?php echo e((string) ($evaluation['participants_attended'] ?? '-')); ?> / <?php echo e((string) ($evaluation['respondent_count'] ?? '-')); ?></td></tr>
                    </tbody>
                </table>
            </div>

            <h6 class="fw-bold mb-2">Form A + Form B (Jawaban Rubrik)</h6>
            <div class="table-responsive mb-4">
                <table class="table table-bordered table-striped table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Form</th>
                            <th>Section</th>
                            <th>No</th>
                            <th>Indikator</th>
                            <th>Skor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($answers)): ?>
                            <tr><td colspan="5" class="text-center text-muted">Tidak ada jawaban rubrik.</td></tr>
                        <?php else: ?>
                            <?php foreach ($answers as $answer): ?>
                                <tr>
                                    <td><?php echo e((string) ($answer['form_type'] ?? '-')); ?></td>
                                    <td><?php echo e((string) ($answer['section_key'] ?? '-')); ?></td>
                                    <td><?php echo e((string) ($answer['indicator_number'] ?? '-')); ?></td>
                                    <td><?php echo e((string) ($answer['indicator_text'] ?? '-')); ?></td>
                                    <td><?php echo ((int) ($answer['is_not_applicable'] ?? 0) === 1) ? 'NA' : e((string) ($answer['score'] ?? '-')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <h6 class="fw-bold mb-2">Ringkasan Isian Lanjutan</h6>
            <div class="table-responsive mb-4">
                <table class="table table-bordered table-sm data-table mb-0">
                    <tbody>
                        <tr><th>Kategori responden</th><td><?php echo e((string) ($evaluation['respondent_category'] ?? '-')); ?> <?php echo e((string) ($evaluation['respondent_category_other'] ?? '')); ?></td></tr>
                        <tr><th>Moda keikutsertaan</th><td><?php echo e((string) ($evaluation['participation_mode'] ?? '-')); ?></td></tr>
                        <tr><th>Kanal komunikasi disukai</th><td><?php echo e(implode(', ', array_map('strval', $preferredChannels))); ?></td></tr>
                        <tr><th>Evaluator</th><td><?php echo e((string) ($evaluation['evaluator_name'] ?? '-')); ?> (<?php echo e((string) ($evaluation['evaluator_role'] ?? '-')); ?>)</td></tr>
                        <tr><th>Kepuasan umum</th><td><?php echo e((string) ($evaluation['overall_satisfaction'] ?? '-')); ?></td></tr>
                        <tr><th>Skor rekap keseluruhan</th><td><?php echo e((string) ($evaluation['recap_overall_score'] ?? '-')); ?> / 100</td></tr>
                        <tr><th>Kategori hasil rekap</th><td><?php echo e((string) ($evaluation['recap_result_category'] ?? '-')); ?></td></tr>
                    </tbody>
                </table>
            </div>

            <h6 class="fw-bold mb-2">Form C - Analisis Kualitatif</h6>
            <div class="table-responsive mb-3">
                <table class="table table-bordered table-sm mb-0">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Tema</th>
                            <th>Ringkasan</th>
                            <th>Frekuensi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($qualitativeFeedback)): ?>
                            <tr><td colspan="4" class="text-center text-muted">Tidak ada data.</td></tr>
                        <?php else: ?>
                            <?php foreach ($qualitativeFeedback as $idx => $feedback): ?>
                                <tr>
                                    <td><?php echo (int) $idx + 1; ?></td>
                                    <td><?php echo e((string) ($feedback['theme'] ?? '-')); ?></td>
                                    <td><?php echo e((string) ($feedback['summary'] ?? '-')); ?></td>
                                    <td><?php echo e((string) ($feedback['frequency'] ?? '-')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <h6 class="fw-bold mb-2">Form C - Pencapaian Indikator</h6>
            <div class="table-responsive mb-4">
                <table class="table table-bordered table-sm mb-0">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Indikator</th>
                            <th>Target</th>
                            <th>Realisasi</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($indicatorAchievements)): ?>
                            <tr><td colspan="5" class="text-center text-muted">Tidak ada data.</td></tr>
                        <?php else: ?>
                            <?php foreach ($indicatorAchievements as $idx => $indicator): ?>
                                <tr>
                                    <td><?php echo (int) $idx + 1; ?></td>
                                    <td><?php echo e((string) ($indicator['indicator'] ?? '-')); ?></td>
                                    <td><?php echo e((string) ($indicator['target'] ?? '-')); ?></td>
                                    <td><?php echo e((string) ($indicator['realization'] ?? '-')); ?></td>
                                    <td><?php echo e((string) ($indicator['status'] ?? '-')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <h6 class="fw-bold mb-2">Rencana Tindak Lanjut (RTL)</h6>
            <div class="table-responsive mb-4">
                <table class="table table-bordered table-sm mb-0">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Temuan/Isu</th>
                            <th>Tindak Lanjut</th>
                            <th>PJ</th>
                            <th>Target</th>
                            <th>Indikator Selesai</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rtlItems)): ?>
                            <tr><td colspan="7" class="text-center text-muted">Tidak ada data RTL.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rtlItems as $rtl): ?>
                                <tr>
                                    <td><?php echo e((string) ($rtl['row_order'] ?? '-')); ?></td>
                                    <td><?php echo e((string) ($rtl['issue'] ?? '-')); ?></td>
                                    <td><?php echo e((string) ($rtl['follow_up'] ?? '-')); ?></td>
                                    <td><?php echo e((string) ($rtl['responsible_person'] ?? '-')); ?></td>
                                    <td><?php echo e((string) ($rtl['target_date'] ?? '-')); ?></td>
                                    <td><?php echo e((string) ($rtl['completion_indicator'] ?? '-')); ?></td>
                                    <td><?php echo e((string) ($rtl['status'] ?? '-')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <h6 class="fw-bold mb-2">Pengesahan dan Pengendalian Dokumen</h6>
            <div class="table-responsive">
                <table class="table table-bordered table-sm data-table mb-0">
                    <tbody>
                        <tr><th>Status pelaksanaan</th><td><?php echo e((string) ($evaluation['execution_status'] ?? '-')); ?></td></tr>
                        <tr><th>Rekomendasi status</th><td><?php echo e((string) ($evaluation['recommendation_status'] ?? '-')); ?></td></tr>
                        <tr><th>Rekomendasi utama</th><td><?php echo e((string) ($evaluation['recommendation_1'] ?? '-')); ?><br><?php echo e((string) ($evaluation['recommendation_2'] ?? '-')); ?><br><?php echo e((string) ($evaluation['recommendation_3'] ?? '-')); ?></td></tr>
                        <tr><th>Media pemantauan</th><td><?php echo e(implode(', ', array_map('strval', $monitoringMedia))); ?> <?php echo e((string) ($evaluation['monitoring_media_other'] ?? '')); ?></td></tr>
                        <tr><th>Kode / Versi Dokumen</th><td><?php echo e((string) ($evaluation['document_code'] ?? '-')); ?> / <?php echo e((string) ($evaluation['document_version'] ?? '-')); ?></td></tr>
                        <tr><th>Status dokumen</th><td><?php echo e((string) ($evaluation['document_status'] ?? '-')); ?></td></tr>
                        <tr><th>Lokasi / Akses / Penanggung jawab</th><td><?php echo e((string) ($evaluation['document_storage_location'] ?? '-')); ?> / <?php echo e((string) ($evaluation['document_access_level'] ?? '-')); ?> / <?php echo e((string) ($evaluation['document_owner'] ?? '-')); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>
<?php $conn->close(); ?>
