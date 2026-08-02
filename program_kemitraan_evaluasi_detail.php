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

function value_or_null(string $value): ?string
{
    $trimmed = trim($value);
    return $trimmed === '' ? null : $trimmed;
}

function number_or_null(string $value): ?float
{
    $trimmed = trim($value);
    if ($trimmed === '' || !is_numeric($trimmed)) {
        return null;
    }
    return (float) $trimmed;
}

$recapResultCategories = ['Sangat baik', 'Baik', 'Cukup', 'Perlu perbaikan'];
$recapAchievementStatuses = ['Melebihi target', 'Mencapai target', 'Belum mencapai target'];
$defaultIndicatorLabels = [
    'Jumlah peserta/mitra yang hadir',
    'Persentase tingkat respons evaluasi',
    'Nilai Evaluasi Kegiatan',
    'Jumlah peluang/komitmen kemitraan',
    'Jangkauan atau interaksi promosi',
    'Persentase tindak lanjut tepat waktu',
];

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
$indicatorAchievements = decode_json_field($evaluation['indicator_achievements'] ?? '');

$formCSuccess = isset($_GET['form_c_saved']) && $_GET['form_c_saved'] === '1';
$formCError = '';

$scoreTotal = 0.0;
$scoreCount = 0;
foreach ($answers as $answerRow) {
    if (!isset($answerRow['score']) || $answerRow['score'] === null || (int) ($answerRow['is_not_applicable'] ?? 0) === 1) {
        continue;
    }
    $scoreTotal += (float) $answerRow['score'];
    $scoreCount++;
}
$derivedOverallScore = $scoreCount > 0 ? round(($scoreTotal / $scoreCount) * 20, 2) : null;
$derivedParticipantsPresent = isset($evaluation['participants_attended']) ? (int) $evaluation['participants_attended'] : null;
$derivedFormsDistributed = isset($evaluation['participants_invited']) ? (int) $evaluation['participants_invited'] : null;
$derivedFormsReceived = isset($evaluation['respondent_count']) ? (int) $evaluation['respondent_count'] : null;
$derivedFormsValid = $derivedFormsReceived;
$derivedResponseRate = null;
if ($derivedFormsDistributed !== null && $derivedFormsDistributed > 0 && $derivedFormsReceived !== null) {
    $derivedResponseRate = round(($derivedFormsReceived / $derivedFormsDistributed) * 100, 2);
}

$indicatorLabelMap = [];
foreach ($defaultIndicatorLabels as $idx => $label) {
    $indicatorLabelMap[$idx] = $label;
}
foreach ($indicatorAchievements as $idx => $row) {
    if (isset($row['indicator']) && trim((string) $row['indicator']) !== '') {
        $indicatorLabelMap[$idx] = trim((string) $row['indicator']);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'save_form_c') {
    $collectionPeriod = value_or_null((string) ($_POST['recap_collection_period'] ?? ''));
    $highestAspect = value_or_null((string) ($_POST['recap_highest_aspect'] ?? ''));
    $highestValue = number_or_null((string) ($_POST['recap_highest_value'] ?? ''));
    $lowestAspect = value_or_null((string) ($_POST['recap_lowest_aspect'] ?? ''));
    $lowestValue = number_or_null((string) ($_POST['recap_lowest_value'] ?? ''));
    $resultCategory = value_or_null((string) ($_POST['recap_result_category'] ?? ''));
    $internalTarget = number_or_null((string) ($_POST['recap_internal_target'] ?? ''));
    $achievementStatus = value_or_null((string) ($_POST['recap_achievement_status'] ?? ''));
    $generalConclusion = value_or_null((string) ($_POST['recap_general_conclusion'] ?? ''));

    if ($resultCategory !== null && !in_array($resultCategory, $recapResultCategories, true)) {
        $resultCategory = null;
    }
    if ($achievementStatus !== null && !in_array($achievementStatus, $recapAchievementStatuses, true)) {
        $achievementStatus = null;
    }

    $postedIndicators = $_POST['indicator_achievements'] ?? [];
    $updatedIndicators = [];
    foreach ($indicatorLabelMap as $idx => $label) {
        $row = isset($postedIndicators[$idx]) && is_array($postedIndicators[$idx]) ? $postedIndicators[$idx] : [];
        $updatedIndicators[] = [
            'indicator' => $label,
            'target' => value_or_null((string) ($row['target'] ?? '')),
            'realization' => value_or_null((string) ($row['realization'] ?? '')),
            'status' => value_or_null((string) ($row['status'] ?? '')),
        ];
    }

    $indicatorJson = json_encode($updatedIndicators, JSON_UNESCAPED_UNICODE);
    if ($indicatorJson === false) {
        $formCError = 'Gagal memproses data indikator.';
    } else {
        $updateStmt = $conn->prepare("
            UPDATE program_kemitraan_evaluations
            SET
                recap_participants_present = ?,
                recap_forms_distributed = ?,
                recap_forms_received = ?,
                recap_forms_valid = ?,
                recap_response_rate_percent = ?,
                recap_collection_period = ?,
                recap_highest_aspect = ?,
                recap_highest_value = ?,
                recap_lowest_aspect = ?,
                recap_lowest_value = ?,
                recap_overall_score = ?,
                recap_result_category = ?,
                recap_internal_target = ?,
                recap_achievement_status = ?,
                recap_general_conclusion = ?,
                indicator_achievements = ?
            WHERE id = ?
            LIMIT 1
        ");

        if (!$updateStmt) {
            $formCError = 'Gagal menyiapkan penyimpanan Form C: ' . $conn->error;
        } else {
            $participantsPresent = $derivedParticipantsPresent;
            $formsDistributed = $derivedFormsDistributed;
            $formsReceived = $derivedFormsReceived;
            $formsValid = $derivedFormsValid;
            $responseRate = $derivedResponseRate;
            $overallScore = $derivedOverallScore;

            $updateStmt->bind_param(
                'iiiidssdsddsdsssi',
                $participantsPresent,
                $formsDistributed,
                $formsReceived,
                $formsValid,
                $responseRate,
                $collectionPeriod,
                $highestAspect,
                $highestValue,
                $lowestAspect,
                $lowestValue,
                $overallScore,
                $resultCategory,
                $internalTarget,
                $achievementStatus,
                $generalConclusion,
                $indicatorJson,
                $id
            );

            if (!$updateStmt->execute()) {
                $formCError = 'Gagal menyimpan Form C: ' . $updateStmt->error;
            }
            $updateStmt->close();
        }
    }

    if ($formCError === '') {
        header('Location: program_kemitraan_evaluasi_detail?id=' . $id . '&form_c_saved=1');
        exit;
    }
}
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
            <?php if ($formCSuccess): ?>
                <div class="alert alert-success">Formulir C berhasil disimpan.</div>
            <?php endif; ?>
            <?php if ($formCError !== ''): ?>
                <div class="alert alert-danger"><?php echo e($formCError); ?></div>
            <?php endif; ?>

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

            <h6 class="fw-bold mb-2" id="form-c-admin">IV Formulir C - Rekapitulasi dan Analisis Hasil (Admin)</h6>
            <form method="POST" class="mb-4">
                <input type="hidden" name="action" value="save_form_c">

                <?php
                $collectionPeriodValue = (string) ($_POST['recap_collection_period'] ?? ($evaluation['recap_collection_period'] ?? ''));
                $highestAspectValue = (string) ($_POST['recap_highest_aspect'] ?? ($evaluation['recap_highest_aspect'] ?? ''));
                $highestValueValue = (string) ($_POST['recap_highest_value'] ?? ($evaluation['recap_highest_value'] ?? ''));
                $lowestAspectValue = (string) ($_POST['recap_lowest_aspect'] ?? ($evaluation['recap_lowest_aspect'] ?? ''));
                $lowestValueValue = (string) ($_POST['recap_lowest_value'] ?? ($evaluation['recap_lowest_value'] ?? ''));
                $resultCategoryValue = (string) ($_POST['recap_result_category'] ?? ($evaluation['recap_result_category'] ?? ''));
                $internalTargetValue = (string) ($_POST['recap_internal_target'] ?? ($evaluation['recap_internal_target'] ?? ''));
                $achievementStatusValue = (string) ($_POST['recap_achievement_status'] ?? ($evaluation['recap_achievement_status'] ?? ''));
                $generalConclusionValue = (string) ($_POST['recap_general_conclusion'] ?? ($evaluation['recap_general_conclusion'] ?? ''));
                ?>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Nama Kegiatan</label>
                        <input type="text" class="form-control" value="<?php echo e((string) ($evaluation['activity_name'] ?? '-')); ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Jumlah peserta hadir</label>
                        <input type="text" class="form-control" value="<?php echo e($derivedParticipantsPresent !== null ? (string) $derivedParticipantsPresent : '-'); ?>" readonly>
                        <div class="small-muted">Auto dari data kegiatan.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Formulir dibagikan</label>
                        <input type="text" class="form-control" value="<?php echo e($derivedFormsDistributed !== null ? (string) $derivedFormsDistributed : '-'); ?>" readonly>
                        <div class="small-muted">Auto dari undangan kegiatan.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Formulir diterima</label>
                        <input type="text" class="form-control" value="<?php echo e($derivedFormsReceived !== null ? (string) $derivedFormsReceived : '-'); ?>" readonly>
                        <div class="small-muted">Auto dari jumlah responden.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Formulir valid</label>
                        <input type="text" class="form-control" value="<?php echo e($derivedFormsValid !== null ? (string) $derivedFormsValid : '-'); ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Tingkat respons (%)</label>
                        <input type="text" class="form-control" value="<?php echo e($derivedResponseRate !== null ? number_format($derivedResponseRate, 2) : '-'); ?>" readonly>
                        <div class="small-muted">Auto dari formulir diterima / dibagikan.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Nilai keseluruhan /100</label>
                        <input type="text" class="form-control" value="<?php echo e($derivedOverallScore !== null ? number_format($derivedOverallScore, 2) : '-'); ?>" readonly>
                        <div class="small-muted">Auto dari rerata skor jawaban rubrik.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Periode pengumpulan</label>
                        <input type="text" class="form-control" name="recap_collection_period" value="<?php echo e($collectionPeriodValue); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Nilai tertinggi (aspek)</label>
                        <input type="text" class="form-control" name="recap_highest_aspect" value="<?php echo e($highestAspectValue); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Nilai tertinggi (angka)</label>
                        <input type="number" min="0" max="100" step="0.01" class="form-control" name="recap_highest_value" value="<?php echo e($highestValueValue); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Nilai terendah (aspek)</label>
                        <input type="text" class="form-control" name="recap_lowest_aspect" value="<?php echo e($lowestAspectValue); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Nilai terendah (angka)</label>
                        <input type="number" min="0" max="100" step="0.01" class="form-control" name="recap_lowest_value" value="<?php echo e($lowestValueValue); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Kategori hasil</label>
                        <select class="form-select" name="recap_result_category">
                            <option value="">-- Pilih kategori --</option>
                            <?php foreach ($recapResultCategories as $category): ?>
                                <option value="<?php echo e($category); ?>" <?php echo $resultCategoryValue === $category ? 'selected' : ''; ?>><?php echo e($category); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Target internal /100</label>
                        <input type="number" min="0" max="100" step="0.01" class="form-control" name="recap_internal_target" value="<?php echo e($internalTargetValue); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Status capaian</label>
                        <select class="form-select" name="recap_achievement_status">
                            <option value="">-- Pilih status --</option>
                            <?php foreach ($recapAchievementStatuses as $status): ?>
                                <option value="<?php echo e($status); ?>" <?php echo $achievementStatusValue === $status ? 'selected' : ''; ?>><?php echo e($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Kesimpulan umum</label>
                        <textarea class="form-control" name="recap_general_conclusion" rows="3"><?php echo e($generalConclusionValue); ?></textarea>
                    </div>
                </div>

                <h6 class="fw-semibold mb-2">Pencapaian Indikator Kegiatan</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-bordered table-sm mb-0">
                        <thead>
                            <tr>
                                <th style="width:56px;">No.</th>
                                <th>Indikator</th>
                                <th>Target</th>
                                <th>Realisasi</th>
                                <th>Status/Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($indicatorLabelMap as $idx => $indicatorLabel): ?>
                                <?php
                                $postedIndicator = isset($_POST['indicator_achievements'][$idx]) && is_array($_POST['indicator_achievements'][$idx])
                                    ? $_POST['indicator_achievements'][$idx]
                                    : [];
                                $existingIndicator = isset($indicatorAchievements[$idx]) && is_array($indicatorAchievements[$idx])
                                    ? $indicatorAchievements[$idx]
                                    : [];
                                $targetValue = (string) ($postedIndicator['target'] ?? ($existingIndicator['target'] ?? ''));
                                $realizationValue = (string) ($postedIndicator['realization'] ?? ($existingIndicator['realization'] ?? ''));
                                $statusValue = (string) ($postedIndicator['status'] ?? ($existingIndicator['status'] ?? ''));
                                ?>
                                <tr>
                                    <td><?php echo (int) $idx + 1; ?></td>
                                    <td><input type="text" class="form-control" value="<?php echo e($indicatorLabel); ?>" readonly></td>
                                    <td><input type="text" class="form-control" name="indicator_achievements[<?php echo (int) $idx; ?>][target]" value="<?php echo e($targetValue); ?>"></td>
                                    <td><input type="text" class="form-control" name="indicator_achievements[<?php echo (int) $idx; ?>][realization]" value="<?php echo e($realizationValue); ?>"></td>
                                    <td><input type="text" class="form-control" name="indicator_achievements[<?php echo (int) $idx; ?>][status]" value="<?php echo e($statusValue); ?>"></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="btn btn-primary">Simpan Formulir C</button>
            </form>

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

            <h6 class="fw-bold mb-2">Pemantauan Lanjutan</h6>
            <div class="table-responsive">
                <table class="table table-bordered table-sm data-table mb-0">
                    <tbody>
                        <tr><th>Media pemantauan</th><td><?php echo e(implode(', ', array_map('strval', $monitoringMedia))); ?> <?php echo e((string) ($evaluation['monitoring_media_other'] ?? '')); ?></td></tr>
                        <tr><th>Frekuensi pemantauan</th><td><?php echo e((string) ($evaluation['monitoring_frequency'] ?? '-')); ?></td></tr>
                        <tr><th>Koordinator pemantauan</th><td><?php echo e((string) ($evaluation['monitoring_coordinator'] ?? '-')); ?></td></tr>
                        <tr><th>Tanggal reviu pertama</th><td><?php echo e((string) ($evaluation['first_review_date'] ?? '-')); ?></td></tr>
                        <tr><th>Dokumen bukti</th><td><?php echo e((string) ($evaluation['evidence_documents'] ?? '-')); ?></td></tr>
                        <tr><th>Catatan pimpinan</th><td><?php echo e((string) ($evaluation['leader_notes'] ?? '-')); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>
<?php $conn->close(); ?>
