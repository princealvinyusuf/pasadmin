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
$priorityOptions = [
    'Prioritas 1 - Mendesak dan berdampak besar',
    'Prioritas 2 - Penting dan perlu dijadwalkan',
    'Prioritas 3 - Penyempurnaan bertahap',
    'Dapat dipantau tanpa tindakan segera',
];
$monitoringFrequencies = ['Mingguan', 'Dua mingguan', 'Bulanan', 'Sesuai tenggat'];
$monitoringMediaOptions = ['Rapat', 'Lembar kendali', 'Sistem/aplikasi', 'Lainnya'];

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
$formVSuccess = isset($_GET['form_v_saved']) && $_GET['form_v_saved'] === '1';
$formVError = '';

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

$rtlByOrder = [];
foreach ($rtlItems as $rtlItemRow) {
    $rowOrder = (int) ($rtlItemRow['row_order'] ?? 0);
    if ($rowOrder > 0) {
        $rtlByOrder[$rowOrder] = $rtlItemRow;
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
                $updateStmt = $conn->prepare("
                    UPDATE program_kemitraan_evaluations
                    SET
                        priority_level = ?,
                        monitoring_coordinator = ?,
                        monitoring_frequency = ?,
                        monitoring_media = ?,
                        monitoring_media_other = ?,
                        first_review_date = ?,
                        evidence_documents = ?,
                        leader_notes = ?
                    WHERE id = ?
                    LIMIT 1
                ");
                if (!$updateStmt) {
                    throw new RuntimeException('Gagal menyiapkan update Form V: ' . $conn->error);
                }
                $updateStmt->bind_param(
                    'ssssssssi',
                    $priorityLevel,
                    $monitoringCoordinator,
                    $monitoringFrequency,
                    $monitoringMediaJson,
                    $monitoringMediaOther,
                    $firstReviewDate,
                    $evidenceDocuments,
                    $leaderNotes,
                    $id
                );
                if (!$updateStmt->execute()) {
                    throw new RuntimeException('Gagal menyimpan data Form V: ' . $updateStmt->error);
                }
                $updateStmt->close();

                $deleteStmt = $conn->prepare("DELETE FROM program_kemitraan_evaluation_rtl_items WHERE evaluation_id = ?");
                if (!$deleteStmt) {
                    throw new RuntimeException('Gagal menyiapkan reset RTL: ' . $conn->error);
                }
                $deleteStmt->bind_param('i', $id);
                if (!$deleteStmt->execute()) {
                    throw new RuntimeException('Gagal menghapus RTL lama: ' . $deleteStmt->error);
                }
                $deleteStmt->close();

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
                            $id,
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

                $conn->commit();
                header('Location: program_kemitraan_evaluasi_detail?id=' . $id . '&form_v_saved=1#form-v-admin');
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
            <?php if ($formVSuccess): ?>
                <div class="alert alert-success">Formulir V berhasil disimpan.</div>
            <?php endif; ?>
            <?php if ($formVError !== ''): ?>
                <div class="alert alert-danger"><?php echo e($formVError); ?></div>
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

            <h6 class="fw-bold mb-2" id="form-v-admin">V Rencana Tindak Lanjut Hasil Evaluasi (Admin)</h6>
            <form method="POST" class="mb-2">
                <input type="hidden" name="action" value="save_form_v">
                <?php
                $priorityLevelValue = (string) ($_POST['priority_level'] ?? ($evaluation['priority_level'] ?? ''));
                $monitoringCoordinatorValue = (string) ($_POST['monitoring_coordinator'] ?? ($evaluation['monitoring_coordinator'] ?? ''));
                $monitoringFrequencyValue = (string) ($_POST['monitoring_frequency'] ?? ($evaluation['monitoring_frequency'] ?? ''));
                $monitoringMediaOtherValue = (string) ($_POST['monitoring_media_other'] ?? ($evaluation['monitoring_media_other'] ?? ''));
                $firstReviewDateValue = (string) ($_POST['first_review_date'] ?? ($evaluation['first_review_date'] ?? ''));
                $evidenceDocumentsValue = (string) ($_POST['evidence_documents'] ?? ($evaluation['evidence_documents'] ?? ''));
                $leaderNotesValue = (string) ($_POST['leader_notes'] ?? ($evaluation['leader_notes'] ?? ''));
                $selectedMonitoringMedia = $_POST['monitoring_media'] ?? $monitoringMedia;
                if (!is_array($selectedMonitoringMedia)) {
                    $selectedMonitoringMedia = [];
                }
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
                        <thead class="table-light">
                            <tr>
                                <th style="width:56px;">No.</th>
                                <th>Temuan/Isu</th>
                                <th>Tindak Lanjut</th>
                                <th>Penanggung Jawab</th>
                                <th>Target Waktu</th>
                                <th>Indikator Selesai</th>
                                <th>Status</th>
                            </tr>
                        </thead>
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
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Koordinator pemantauan</label>
                        <input type="text" class="form-control" name="monitoring_coordinator" value="<?php echo e($monitoringCoordinatorValue); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Frekuensi pemantauan</label>
                        <select class="form-select" name="monitoring_frequency">
                            <option value="">-- Pilih frekuensi --</option>
                            <?php foreach ($monitoringFrequencies as $frequency): ?>
                                <option value="<?php echo e($frequency); ?>" <?php echo $monitoringFrequencyValue === $frequency ? 'selected' : ''; ?>><?php echo e($frequency); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Media lainnya</label>
                        <input type="text" class="form-control" name="monitoring_media_other" value="<?php echo e($monitoringMediaOtherValue); ?>">
                    </div>
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
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Tanggal reviu pertama</label>
                        <input type="date" class="form-control" name="first_review_date" value="<?php echo e($firstReviewDateValue); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Dokumen bukti</label>
                        <textarea class="form-control" name="evidence_documents" rows="2"><?php echo e($evidenceDocumentsValue); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Catatan pimpinan/arahan tambahan</label>
                        <textarea class="form-control" name="leader_notes" rows="2"><?php echo e($leaderNotesValue); ?></textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Simpan Formulir V</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
<?php $conn->close(); ?>
