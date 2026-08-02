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

function date_or_null(string $value): ?string
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return null;
    }
    $timestamp = strtotime($trimmed);
    return $timestamp ? date('Y-m-d', $timestamp) : null;
}

function sql_value(mysqli $conn, $value, bool $numeric = false): string
{
    if ($value === null || $value === '') {
        return 'NULL';
    }
    if ($numeric) {
        return is_numeric((string) $value) ? (string) $value : 'NULL';
    }
    return "'" . $conn->real_escape_string((string) $value) . "'";
}

$recapResultCategories = ['Sangat Baik', 'Baik', 'Cukup', 'Kurang', 'Sangat Kurang'];
$recapAchievementStatuses = ['Melampaui target', 'Mencapai target', 'Belum mencapai target'];
$defaultIndicatorLabels = [
    'Jumlah peserta/mitra yang hadir',
    'Persentase tingkat respons evaluasi',
    'Nilai Evaluasi Kegiatan',
    'Jumlah peluang/komitmen kemitraan',
    'Jangkauan atau interaksi promosi',
    'Persentase tindak lanjut tepat waktu',
];
$priorityOptions = [
    'Prioritas 1 - Mendesak dan berdampak besar',
    'Prioritas 2 - Penting dan perlu dijadwalkan',
    'Prioritas 3 - Penyempurnaan bertahap',
    'Dapat dipantau tanpa tindakan segera',
];
$monitoringFrequencies = ['Mingguan', 'Dua mingguan', 'Bulanan', 'Sesuai tenggat'];
$monitoringMediaOptions = ['Rapat', 'Lembar kendali', 'Sistem/aplikasi', 'Lainnya'];

$activityMasterId = (int) ($_GET['activity_master_id'] ?? 0);
if ($activityMasterId <= 0) {
    http_response_code(400);
    echo 'Invalid activity_master_id';
    exit;
}

$activityStmt = $conn->prepare("
    SELECT id, activity_name, activity_theme, activity_date, activity_organizer, participants_invited, participants_attended, respondent_count
    FROM program_kemitraan_evaluation_activities
    WHERE id = ?
    LIMIT 1
");
if (!$activityStmt) {
    die('Failed to prepare activity query: ' . $conn->error);
}
$activityStmt->bind_param('i', $activityMasterId);
$activityStmt->execute();
$activityRes = $activityStmt->get_result();
$activity = $activityRes ? $activityRes->fetch_assoc() : null;
$activityStmt->close();

if (!$activity) {
    http_response_code(404);
    echo 'Data kegiatan tidak ditemukan.';
    exit;
}

$evaluationIds = [];
$latestEvaluation = null;
$evalRes = $conn->query("SELECT * FROM program_kemitraan_evaluations WHERE activity_master_id = {$activityMasterId} ORDER BY id DESC");
if ($evalRes) {
    while ($row = $evalRes->fetch_assoc()) {
        $eid = (int) ($row['id'] ?? 0);
        if ($eid > 0) {
            $evaluationIds[] = $eid;
            if ($latestEvaluation === null) {
                $latestEvaluation = $row;
            }
        }
    }
    $evalRes->free();
}

if ($latestEvaluation === null) {
    $latestEvaluation = [];
}

$answers = [];
if (!empty($evaluationIds)) {
    $idList = implode(',', array_map('intval', $evaluationIds));
    $answerRes = $conn->query("
        SELECT score, is_not_applicable
        FROM program_kemitraan_evaluation_answers
        WHERE evaluation_id IN ({$idList})
    ");
    if ($answerRes) {
        while ($row = $answerRes->fetch_assoc()) {
            $answers[] = $row;
        }
        $answerRes->free();
    }
}

$rtlItems = [];
if (!empty($evaluationIds)) {
    $firstEvaluationId = (int) $evaluationIds[0];
    $rtlRes = $conn->query("
        SELECT row_order, issue, follow_up, responsible_person, target_date, completion_indicator, status
        FROM program_kemitraan_evaluation_rtl_items
        WHERE evaluation_id = {$firstEvaluationId}
        ORDER BY row_order ASC, id ASC
    ");
    if ($rtlRes) {
        while ($row = $rtlRes->fetch_assoc()) {
            $rtlItems[] = $row;
        }
        $rtlRes->free();
    }
}

$indicatorAchievements = decode_json_field($latestEvaluation['indicator_achievements'] ?? '');
$monitoringMedia = decode_json_field($latestEvaluation['monitoring_media'] ?? '');

$scoreTotal = 0.0;
$scoreCount = 0;
foreach ($answers as $answerRow) {
    if (!isset($answerRow['score']) || $answerRow['score'] === null || (int) ($answerRow['is_not_applicable'] ?? 0) === 1) {
        continue;
    }
    $scoreTotal += (float) $answerRow['score'];
    $scoreCount++;
}

$derivedParticipantsPresent = isset($activity['participants_attended']) ? (int) $activity['participants_attended'] : null;
$derivedFormsDistributed = isset($activity['participants_invited']) ? (int) $activity['participants_invited'] : null;
$derivedFormsReceived = count($evaluationIds);
$derivedFormsValid = $derivedFormsReceived;
$derivedResponseRate = null;
if ($derivedFormsDistributed !== null && $derivedFormsDistributed > 0) {
    $derivedResponseRate = round(($derivedFormsReceived / $derivedFormsDistributed) * 100, 2);
}
$derivedOverallScore = $scoreCount > 0 ? round(($scoreTotal / $scoreCount) * 20, 2) : null;

$indicatorLabelMap = [];
foreach ($defaultIndicatorLabels as $idx => $label) {
    $indicatorLabelMap[$idx] = $label;
}
foreach ($indicatorAchievements as $idx => $row) {
    if (isset($row['indicator']) && trim((string) $row['indicator']) !== '') {
        $indicatorLabelMap[$idx] = trim((string) $row['indicator']);
    }
}

$rtlByOrder = [];
foreach ($rtlItems as $rtlItemRow) {
    $rowOrder = (int) ($rtlItemRow['row_order'] ?? 0);
    if ($rowOrder > 0) {
        $rtlByOrder[$rowOrder] = $rtlItemRow;
    }
}

$formCSuccess = isset($_GET['form_c_saved']) && $_GET['form_c_saved'] === '1';
$formVSuccess = isset($_GET['form_v_saved']) && $_GET['form_v_saved'] === '1';
$formCError = '';
$formVError = '';

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
        $sql = "
            UPDATE program_kemitraan_evaluations
            SET
                recap_participants_present = " . sql_value($conn, $derivedParticipantsPresent, true) . ",
                recap_forms_distributed = " . sql_value($conn, $derivedFormsDistributed, true) . ",
                recap_forms_received = " . sql_value($conn, $derivedFormsReceived, true) . ",
                recap_forms_valid = " . sql_value($conn, $derivedFormsValid, true) . ",
                recap_response_rate_percent = " . sql_value($conn, $derivedResponseRate, true) . ",
                recap_collection_period = " . sql_value($conn, $collectionPeriod) . ",
                recap_highest_aspect = " . sql_value($conn, $highestAspect) . ",
                recap_highest_value = " . sql_value($conn, $highestValue, true) . ",
                recap_lowest_aspect = " . sql_value($conn, $lowestAspect) . ",
                recap_lowest_value = " . sql_value($conn, $lowestValue, true) . ",
                recap_overall_score = " . sql_value($conn, $derivedOverallScore, true) . ",
                recap_result_category = " . sql_value($conn, $resultCategory) . ",
                recap_internal_target = " . sql_value($conn, $internalTarget, true) . ",
                recap_achievement_status = " . sql_value($conn, $achievementStatus) . ",
                recap_general_conclusion = " . sql_value($conn, $generalConclusion) . ",
                indicator_achievements = " . sql_value($conn, $indicatorJson) . "
            WHERE activity_master_id = {$activityMasterId}
        ";

        if (!$conn->query($sql)) {
            $formCError = 'Gagal menyimpan Formulir C: ' . $conn->error;
        } else {
            header('Location: program_kemitraan_dashboard_analisis_hasil_detail?activity_master_id=' . $activityMasterId . '&form_c_saved=1#form-c-admin');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'save_form_v') {
    $priorityLevel = value_or_null((string) ($_POST['priority_level'] ?? ''));
    $monitoringCoordinator = value_or_null((string) ($_POST['monitoring_coordinator'] ?? ''));
    $monitoringFrequency = value_or_null((string) ($_POST['monitoring_frequency'] ?? ''));
    $monitoringMediaOther = value_or_null((string) ($_POST['monitoring_media_other'] ?? ''));
    $firstReviewDate = date_or_null((string) ($_POST['first_review_date'] ?? ''));
    $evidenceDocuments = value_or_null((string) ($_POST['evidence_documents'] ?? ''));
    $leaderNotes = value_or_null((string) ($_POST['leader_notes'] ?? ''));

    if ($priorityLevel !== null && !in_array($priorityLevel, $priorityOptions, true)) {
        $priorityLevel = null;
    }
    if ($monitoringFrequency !== null && !in_array($monitoringFrequency, $monitoringFrequencies, true)) {
        $monitoringFrequency = null;
    }

    $monitoringMediaPosted = $_POST['monitoring_media'] ?? [];
    $monitoringMediaSanitized = [];
    if (is_array($monitoringMediaPosted)) {
        foreach ($monitoringMediaPosted as $mediaItem) {
            $mediaValue = (string) $mediaItem;
            if (in_array($mediaValue, $monitoringMediaOptions, true)) {
                $monitoringMediaSanitized[] = $mediaValue;
            }
        }
    }
    $monitoringMediaSanitized = array_values(array_unique($monitoringMediaSanitized));
    if (in_array('Lainnya', $monitoringMediaSanitized, true) && $monitoringMediaOther === null) {
        $formVError = 'Kolom media lainnya wajib diisi jika opsi "Lainnya" dipilih.';
    }

    $rtlPosted = $_POST['rtl_items'] ?? [];
    $rtlRowsToSave = [];
    if (is_array($rtlPosted)) {
        foreach ($rtlPosted as $idx => $rtlPostedRow) {
            if (!is_array($rtlPostedRow)) {
                continue;
            }
            $rowOrder = (int) $idx + 1;
            $issue = value_or_null((string) ($rtlPostedRow['issue'] ?? ''));
            $followUp = value_or_null((string) ($rtlPostedRow['follow_up'] ?? ''));
            $responsiblePerson = value_or_null((string) ($rtlPostedRow['responsible_person'] ?? ''));
            $targetDate = date_or_null((string) ($rtlPostedRow['target_date'] ?? ''));
            $completionIndicator = value_or_null((string) ($rtlPostedRow['completion_indicator'] ?? ''));
            $status = value_or_null((string) ($rtlPostedRow['status'] ?? ''));

            if ($issue === null && $followUp === null && $responsiblePerson === null && $targetDate === null && $completionIndicator === null && $status === null) {
                continue;
            }

            $rtlRowsToSave[] = [
                'row_order' => $rowOrder,
                'issue' => $issue,
                'follow_up' => $followUp,
                'responsible_person' => $responsiblePerson,
                'target_date' => $targetDate,
                'completion_indicator' => $completionIndicator,
                'status' => $status,
            ];
        }
    }

    if ($formVError === '') {
        $monitoringMediaJson = json_encode($monitoringMediaSanitized, JSON_UNESCAPED_UNICODE);
        if ($monitoringMediaJson === false) {
            $formVError = 'Gagal memproses media pemantauan.';
        } else {
            $conn->begin_transaction();
            try {
                $sql = "
                    UPDATE program_kemitraan_evaluations
                    SET
                        priority_level = " . sql_value($conn, $priorityLevel) . ",
                        monitoring_coordinator = " . sql_value($conn, $monitoringCoordinator) . ",
                        monitoring_frequency = " . sql_value($conn, $monitoringFrequency) . ",
                        monitoring_media = " . sql_value($conn, $monitoringMediaJson) . ",
                        monitoring_media_other = " . sql_value($conn, $monitoringMediaOther) . ",
                        first_review_date = " . sql_value($conn, $firstReviewDate) . ",
                        evidence_documents = " . sql_value($conn, $evidenceDocuments) . ",
                        leader_notes = " . sql_value($conn, $leaderNotes) . "
                    WHERE activity_master_id = {$activityMasterId}
                ";
                if (!$conn->query($sql)) {
                    throw new RuntimeException('Gagal menyimpan data Form V: ' . $conn->error);
                }

                foreach ($evaluationIds as $evaluationId) {
                    $evaluationId = (int) $evaluationId;
                    if (!$conn->query("DELETE FROM program_kemitraan_evaluation_rtl_items WHERE evaluation_id = {$evaluationId}")) {
                        throw new RuntimeException('Gagal membersihkan RTL: ' . $conn->error);
                    }
                    if (!empty($rtlRowsToSave)) {
                        $insertStmt = $conn->prepare("
                            INSERT INTO program_kemitraan_evaluation_rtl_items
                                (evaluation_id, row_order, issue, follow_up, responsible_person, target_date, completion_indicator, status, created_at, updated_at)
                            VALUES
                                (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                        ");
                        if (!$insertStmt) {
                            throw new RuntimeException('Gagal menyiapkan simpan RTL: ' . $conn->error);
                        }
                        foreach ($rtlRowsToSave as $rtlRow) {
                            $insertStmt->bind_param(
                                'iissssss',
                                $evaluationId,
                                $rtlRow['row_order'],
                                $rtlRow['issue'],
                                $rtlRow['follow_up'],
                                $rtlRow['responsible_person'],
                                $rtlRow['target_date'],
                                $rtlRow['completion_indicator'],
                                $rtlRow['status']
                            );
                            if (!$insertStmt->execute()) {
                                throw new RuntimeException('Gagal menyimpan baris RTL: ' . $insertStmt->error);
                            }
                        }
                        $insertStmt->close();
                    }
                }

                $conn->commit();
                header('Location: program_kemitraan_dashboard_analisis_hasil_detail?activity_master_id=' . $activityMasterId . '&form_v_saved=1#form-v-admin');
                exit;
            } catch (Throwable $e) {
                $conn->rollback();
                $formVError = $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analisis Hasil Kegiatan Detail</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f4f7fb; }
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
        .shell-content { padding: 1.1rem 1.2rem 1.25rem; }
        .small-muted { color: #64748b; font-size: 0.87rem; }
    </style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>

<div class="container mt-4 mb-5">
    <div class="mb-3">
        <a href="program_kemitraan_dashboard_analisis_hasil" class="btn btn-outline-secondary btn-sm">&larr; Kembali ke dashboard analisis</a>
    </div>
    <div class="shell mb-4">
        <div class="shell-header">
            <h4 class="mb-1">Dashboard Analisis - <?php echo e((string) ($activity['activity_name'] ?? '-')); ?></h4>
            <div class="small-muted">
                <?php echo e((string) ($activity['activity_date'] ?? '-')); ?> |
                Total evaluasi: <?php echo count($evaluationIds); ?>
            </div>
        </div>
        <div class="shell-content">
            <?php if ($formCSuccess): ?><div class="alert alert-success">Formulir C berhasil disimpan.</div><?php endif; ?>
            <?php if ($formCError !== ''): ?><div class="alert alert-danger"><?php echo e($formCError); ?></div><?php endif; ?>
            <?php if ($formVSuccess): ?><div class="alert alert-success">Formulir V berhasil disimpan.</div><?php endif; ?>
            <?php if ($formVError !== ''): ?><div class="alert alert-danger"><?php echo e($formVError); ?></div><?php endif; ?>

            <h6 class="fw-bold mb-2" id="form-c-admin">Formulir Rekapitulasi dan Analisis Hasil</h6>
            <form method="POST" class="mb-4">
                <input type="hidden" name="action" value="save_form_c">
                <?php
                $collectionPeriodValue = (string) ($_POST['recap_collection_period'] ?? ($latestEvaluation['recap_collection_period'] ?? ''));
                $highestAspectValue = (string) ($_POST['recap_highest_aspect'] ?? ($latestEvaluation['recap_highest_aspect'] ?? ''));
                $highestValueValue = (string) ($_POST['recap_highest_value'] ?? ($latestEvaluation['recap_highest_value'] ?? ''));
                $lowestAspectValue = (string) ($_POST['recap_lowest_aspect'] ?? ($latestEvaluation['recap_lowest_aspect'] ?? ''));
                $lowestValueValue = (string) ($_POST['recap_lowest_value'] ?? ($latestEvaluation['recap_lowest_value'] ?? ''));
                $resultCategoryValue = (string) ($_POST['recap_result_category'] ?? ($latestEvaluation['recap_result_category'] ?? ''));
                $internalTargetValue = (string) ($_POST['recap_internal_target'] ?? ($latestEvaluation['recap_internal_target'] ?? ''));
                $achievementStatusValue = (string) ($_POST['recap_achievement_status'] ?? ($latestEvaluation['recap_achievement_status'] ?? ''));
                $generalConclusionValue = (string) ($_POST['recap_general_conclusion'] ?? ($latestEvaluation['recap_general_conclusion'] ?? ''));
                ?>
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><label class="form-label fw-semibold">Nama Kegiatan</label><input type="text" class="form-control" value="<?php echo e((string) ($activity['activity_name'] ?? '-')); ?>" readonly></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Jumlah peserta hadir</label><input type="text" class="form-control" value="<?php echo e($derivedParticipantsPresent !== null ? (string) $derivedParticipantsPresent : '-'); ?>" readonly></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Formulir dibagikan</label><input type="text" class="form-control" value="<?php echo e($derivedFormsDistributed !== null ? (string) $derivedFormsDistributed : '-'); ?>" readonly></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Formulir diterima</label><input type="text" class="form-control" value="<?php echo e($derivedFormsReceived !== null ? (string) $derivedFormsReceived : '-'); ?>" readonly></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Formulir valid</label><input type="text" class="form-control" value="<?php echo e($derivedFormsValid !== null ? (string) $derivedFormsValid : '-'); ?>" readonly></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Tingkat respons (%)</label><input type="text" class="form-control" value="<?php echo e($derivedResponseRate !== null ? number_format($derivedResponseRate, 2) : '-'); ?>" readonly></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Nilai keseluruhan /100</label><input type="text" class="form-control" value="<?php echo e($derivedOverallScore !== null ? number_format($derivedOverallScore, 2) : '-'); ?>" readonly></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Periode pengumpulan</label><input type="text" class="form-control" name="recap_collection_period" value="<?php echo e($collectionPeriodValue); ?>"></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Nilai tertinggi (aspek)</label><input type="text" class="form-control" name="recap_highest_aspect" value="<?php echo e($highestAspectValue); ?>"></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Nilai tertinggi (angka)</label><input type="number" min="0" max="100" step="0.01" class="form-control" name="recap_highest_value" value="<?php echo e($highestValueValue); ?>"></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Nilai terendah (aspek)</label><input type="text" class="form-control" name="recap_lowest_aspect" value="<?php echo e($lowestAspectValue); ?>"></div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Nilai terendah (angka)</label><input type="number" min="0" max="100" step="0.01" class="form-control" name="recap_lowest_value" value="<?php echo e($lowestValueValue); ?>"></div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Kategori hasil</label>
                        <select class="form-select" name="recap_result_category">
                            <option value="">-- Pilih kategori --</option>
                            <?php foreach ($recapResultCategories as $category): ?>
                                <option value="<?php echo e($category); ?>" <?php echo $resultCategoryValue === $category ? 'selected' : ''; ?>><?php echo e($category); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Target internal /100</label><input type="number" min="0" max="100" step="0.01" class="form-control" name="recap_internal_target" value="<?php echo e($internalTargetValue); ?>"></div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Status capaian</label>
                        <select class="form-select" name="recap_achievement_status">
                            <option value="">-- Pilih status --</option>
                            <?php foreach ($recapAchievementStatuses as $status): ?>
                                <option value="<?php echo e($status); ?>" <?php echo $achievementStatusValue === $status ? 'selected' : ''; ?>><?php echo e($status); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8"><label class="form-label fw-semibold">Kesimpulan umum</label><textarea class="form-control" name="recap_general_conclusion" rows="3"><?php echo e($generalConclusionValue); ?></textarea></div>
                </div>
                <h6 class="fw-semibold mb-2">Pencapaian Indikator Kegiatan</h6>
                <div class="table-responsive mb-3">
                    <table class="table table-bordered table-sm mb-0">
                        <thead><tr><th style="width:56px;">No.</th><th>Indikator</th><th>Target</th><th>Realisasi</th><th>Status/Keterangan</th></tr></thead>
                        <tbody>
                        <?php foreach ($indicatorLabelMap as $idx => $indicatorLabel): ?>
                            <?php
                            $postedIndicator = isset($_POST['indicator_achievements'][$idx]) && is_array($_POST['indicator_achievements'][$idx]) ? $_POST['indicator_achievements'][$idx] : [];
                            $existingIndicator = isset($indicatorAchievements[$idx]) && is_array($indicatorAchievements[$idx]) ? $indicatorAchievements[$idx] : [];
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
                <button type="submit" class="btn btn-primary">Simpan Formulir</button>
            </form>

            <h6 class="fw-bold mb-2" id="form-v-admin">Rencana Tindak Lanjut Hasil Evaluasi</h6>
            <form method="POST">
                <input type="hidden" name="action" value="save_form_v">
                <?php
                $priorityLevelValue = (string) ($_POST['priority_level'] ?? ($latestEvaluation['priority_level'] ?? ''));
                $monitoringCoordinatorValue = (string) ($_POST['monitoring_coordinator'] ?? ($latestEvaluation['monitoring_coordinator'] ?? ''));
                $monitoringFrequencyValue = (string) ($_POST['monitoring_frequency'] ?? ($latestEvaluation['monitoring_frequency'] ?? ''));
                $monitoringMediaOtherValue = (string) ($_POST['monitoring_media_other'] ?? ($latestEvaluation['monitoring_media_other'] ?? ''));
                $firstReviewDateValue = (string) ($_POST['first_review_date'] ?? ($latestEvaluation['first_review_date'] ?? ''));
                $evidenceDocumentsValue = (string) ($_POST['evidence_documents'] ?? ($latestEvaluation['evidence_documents'] ?? ''));
                $leaderNotesValue = (string) ($_POST['leader_notes'] ?? ($latestEvaluation['leader_notes'] ?? ''));
                $selectedMonitoringMedia = $_POST['monitoring_media'] ?? $monitoringMedia;
                if (!is_array($selectedMonitoringMedia)) { $selectedMonitoringMedia = []; }
                ?>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Penetapan prioritas</label>
                        <select class="form-select" name="priority_level">
                            <option value="">-- Pilih prioritas --</option>
                            <?php foreach ($priorityOptions as $priority): ?>
                                <option value="<?php echo e($priority); ?>" <?php echo $priorityLevelValue === $priority ? 'selected' : ''; ?>><?php echo e($priority); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="table-responsive mb-3">
                    <table class="table table-bordered table-sm mb-0">
                        <thead class="table-light"><tr><th style="width:56px;">No.</th><th>Temuan/Isu</th><th>Tindak Lanjut</th><th>Penanggung Jawab</th><th>Target Waktu</th><th>Indikator Selesai</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php for ($i = 1; $i <= 7; $i++): ?>
                            <?php
                            $postedRtl = isset($_POST['rtl_items'][$i - 1]) && is_array($_POST['rtl_items'][$i - 1]) ? $_POST['rtl_items'][$i - 1] : [];
                            $existingRtl = $rtlByOrder[$i] ?? [];
                            $issueValue = (string) ($postedRtl['issue'] ?? ($existingRtl['issue'] ?? ''));
                            $followUpValue = (string) ($postedRtl['follow_up'] ?? ($existingRtl['follow_up'] ?? ''));
                            $responsiblePersonValue = (string) ($postedRtl['responsible_person'] ?? ($existingRtl['responsible_person'] ?? ''));
                            $targetDateValue = (string) ($postedRtl['target_date'] ?? ($existingRtl['target_date'] ?? ''));
                            $completionIndicatorValue = (string) ($postedRtl['completion_indicator'] ?? ($existingRtl['completion_indicator'] ?? ''));
                            $statusValue = (string) ($postedRtl['status'] ?? ($existingRtl['status'] ?? ''));
                            ?>
                            <tr>
                                <td><?php echo $i; ?></td>
                                <td><textarea class="form-control" name="rtl_items[<?php echo $i - 1; ?>][issue]" rows="2"><?php echo e($issueValue); ?></textarea></td>
                                <td><textarea class="form-control" name="rtl_items[<?php echo $i - 1; ?>][follow_up]" rows="2"><?php echo e($followUpValue); ?></textarea></td>
                                <td><input type="text" class="form-control" name="rtl_items[<?php echo $i - 1; ?>][responsible_person]" value="<?php echo e($responsiblePersonValue); ?>"></td>
                                <td><input type="date" class="form-control" name="rtl_items[<?php echo $i - 1; ?>][target_date]" value="<?php echo e($targetDateValue); ?>"></td>
                                <td><textarea class="form-control" name="rtl_items[<?php echo $i - 1; ?>][completion_indicator]" rows="2"><?php echo e($completionIndicatorValue); ?></textarea></td>
                                <td><input type="text" class="form-control" name="rtl_items[<?php echo $i - 1; ?>][status]" value="<?php echo e($statusValue); ?>"></td>
                            </tr>
                        <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><label class="form-label fw-semibold">Koordinator pemantauan</label><input type="text" class="form-control" name="monitoring_coordinator" value="<?php echo e($monitoringCoordinatorValue); ?>"></div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Frekuensi pemantauan</label>
                        <select class="form-select" name="monitoring_frequency">
                            <option value="">-- Pilih frekuensi --</option>
                            <?php foreach ($monitoringFrequencies as $frequency): ?>
                                <option value="<?php echo e($frequency); ?>" <?php echo $monitoringFrequencyValue === $frequency ? 'selected' : ''; ?>><?php echo e($frequency); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label fw-semibold">Media lainnya</label><input type="text" class="form-control" name="monitoring_media_other" value="<?php echo e($monitoringMediaOtherValue); ?>"></div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Media pemantauan</label>
                        <div class="d-flex flex-wrap gap-3 mt-1">
                            <?php foreach ($monitoringMediaOptions as $mediaOption): ?>
                                <label class="form-check-label">
                                    <input class="form-check-input me-1" type="checkbox" name="monitoring_media[]" value="<?php echo e($mediaOption); ?>" <?php echo in_array($mediaOption, $selectedMonitoringMedia, true) ? 'checked' : ''; ?>>
                                    <?php echo e($mediaOption); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-3"><label class="form-label fw-semibold">Tanggal reviu pertama</label><input type="date" class="form-control" name="first_review_date" value="<?php echo e($firstReviewDateValue); ?>"></div>
                    <div class="col-md-6"><label class="form-label fw-semibold">Dokumen bukti</label><textarea class="form-control" name="evidence_documents" rows="2"><?php echo e($evidenceDocumentsValue); ?></textarea></div>
                    <div class="col-md-6"><label class="form-label fw-semibold">Catatan pimpinan/arahan tambahan</label><textarea class="form-control" name="leader_notes" rows="2"><?php echo e($leaderNotesValue); ?></textarea></div>
                </div>
                <button type="submit" class="btn btn-primary">Simpan Formulir</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>
