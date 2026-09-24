<?php
require_once __DIR__ . '/bootstrap.php';
jpa_require_any(['job_portal_award_manage_config']);

function jpa_valid_date_input(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $date->format('Y-m-d') === $value;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    jpa_verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $periodId = intval($_POST['period_id'] ?? 0);

    if ($action === 'save') {
        $name = trim((string)($_POST['name'] ?? ''));
        $start = (string)($_POST['period_start'] ?? '');
        $end = (string)($_POST['period_end'] ?? '');
        $minMonths = max(1, intval($_POST['min_active_months'] ?? 3));
        $targetVolume = max(1, intval($_POST['target_volume'] ?? 1));
        $penalty = max(0, floatval($_POST['complaint_penalty_factor'] ?? 25));
        $progression = max(0.01, floatval($_POST['target_progression_rate'] ?? 1));
        $placement = max(0.01, floatval($_POST['target_placement_rate'] ?? 1));
        $impactEnabled = !empty($_POST['impact_module_enabled']) ? 1 : 0;
        $fields = array_values(array_filter(array_map('trim', explode(',', (string)($_POST['mandatory_vacancy_fields'] ?? '')))));
        $fieldsValid = count($fields) <= 50;
        foreach ($fields as $field) {
            if (strlen($field) > 100) {
                $fieldsValid = false;
                break;
            }
        }
        if ($name === '' || strlen($name) > 180 || !jpa_valid_date_input($start) || !jpa_valid_date_input($end) || $start > $end || empty($fields) || !$fieldsValid) {
            jpa_set_flash('danger', 'Nama, rentang tanggal yang valid, dan atribut wajib harus diisi.');
            jpa_redirect('periods' . ($periodId ? '?period_id=' . $periodId : ''));
        }
        $fieldsJson = jpa_json($fields);
        $weightsJson = jpa_json(JPA_DEFAULT_WEIGHTS);
        $userId = intval($_SESSION['user_id'] ?? 0);
        if ($periodId > 0) {
            $reason = trim((string)($_POST['reason'] ?? ''));
            if ($reason === '' || strlen($reason) > 500) {
                jpa_set_flash('danger', 'Alasan perubahan parameter wajib diisi (maksimal 500 karakter).');
                jpa_redirect('periods?period_id=' . $periodId);
            }
            $conn->begin_transaction();
            try {
                $before = jpa_lock_period($conn, $periodId);
                if (!$before || $before['status'] === 'finalized') {
                    throw new RuntimeException('Periode final harus dibuka kembali sebelum parameternya dapat diubah.');
                }
                $stmt = $conn->prepare("UPDATE job_portal_award_periods SET
                    name=?,period_start=?,period_end=?,min_active_months=?,target_volume=?,
                    mandatory_vacancy_fields=?,complaint_penalty_factor=?,target_progression_rate=?,
                    target_placement_rate=?,impact_module_enabled=?,weights_json=? WHERE id=? AND status<>'finalized'");
                $stmt->bind_param('sssiisdddisi', $name, $start, $end, $minMonths, $targetVolume, $fieldsJson, $penalty, $progression, $placement, $impactEnabled, $weightsJson, $periodId);
                $stmt->execute();
                $stmt->close();
                $after = jpa_get_period($conn, $periodId);
                jpa_recalculate_period($conn, $periodId, false);
                jpa_audit($conn, $periodId, 'period.updated', 'period', $periodId, $before, $after, $reason);
                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO job_portal_award_periods
                (name,period_start,period_end,min_active_months,target_volume,mandatory_vacancy_fields,
                 complaint_penalty_factor,target_progression_rate,target_placement_rate,impact_module_enabled,
                 weights_json,created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('sssiisdddisi', $name, $start, $end, $minMonths, $targetVolume, $fieldsJson, $penalty, $progression, $placement, $impactEnabled, $weightsJson, $userId);
            $stmt->execute();
            $periodId = intval($conn->insert_id);
            $stmt->close();
            jpa_audit($conn, $periodId, 'period.created', 'period', $periodId, null, jpa_get_period($conn, $periodId));
        }
        jpa_set_flash('success', 'Periode dan parameter berhasil disimpan.');
        jpa_redirect('periods?period_id=' . $periodId);
    }

    if ($action === 'lock') {
        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($reason === '' || strlen($reason) > 500) {
            http_response_code(422);
            exit('Alasan penguncian wajib diisi.');
        }
        $userId = intval($_SESSION['user_id'] ?? 0);
        $conn->begin_transaction();
        try {
            $period = jpa_lock_period($conn, $periodId);
            if (!$period || $period['status'] !== 'draft') {
                throw new RuntimeException('Periode bukan draft.');
            }
            $stmt = $conn->prepare("UPDATE job_portal_award_periods SET status='locked',locked_by=?,locked_at=NOW() WHERE id=?");
            $stmt->bind_param('ii', $userId, $periodId);
            $stmt->execute();
            $stmt->close();
            jpa_audit($conn, $periodId, 'period.locked', 'period', $periodId, $period, jpa_get_period($conn, $periodId), $reason);
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
        jpa_set_flash('success', 'Parameter periode dikunci. Data peserta sekarang dapat diimpor.');
        jpa_redirect('periods?period_id=' . $periodId);
    }

    if ($action === 'reopen') {
        $reason = trim((string)($_POST['reason'] ?? ''));
        if (!current_user_can('job_portal_award_approve_winners')) {
            http_response_code(403);
            exit('Pembukaan kembali memerlukan izin persetujuan pemenang.');
        }
        if ($reason === '' || strlen($reason) > 500) {
            http_response_code(409);
            exit('Periode final membutuhkan alasan pembukaan kembali.');
        }
        $conn->begin_transaction();
        try {
            $period = jpa_lock_period($conn, $periodId);
            if (!$period || $period['status'] !== 'finalized') {
                throw new RuntimeException('Periode bukan periode final.');
            }
            $delete = $conn->prepare('DELETE FROM job_portal_award_winners WHERE period_id=?');
            $delete->bind_param('i', $periodId);
            $delete->execute();
            $delete->close();
            $update = $conn->prepare("UPDATE job_portal_award_periods SET status='locked',finalized_by=NULL,finalized_at=NULL WHERE id=?");
            $update->bind_param('i', $periodId);
            $update->execute();
            $update->close();
            jpa_audit($conn, $periodId, 'period.reopened', 'period', $periodId, $period, jpa_get_period($conn, $periodId), $reason);
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
        jpa_set_flash('warning', 'Periode dibuka kembali ke status locked; persetujuan pemenang sebelumnya dibatalkan.');
        jpa_redirect('periods?period_id=' . $periodId);
    }
}

$period = jpa_selected_period($conn);
$periods = [];
$result = $conn->query('SELECT * FROM job_portal_award_periods ORDER BY period_end DESC,id DESC');
while ($result && ($row = $result->fetch_assoc())) {
    $periods[] = $row;
}
$defaultFields = 'salary, location, job_description, minimum_requirements';
$fieldsValue = $period ? implode(', ', json_decode((string)$period['mandatory_vacancy_fields'], true) ?: []) : $defaultFields;

jpa_render_header('Periode & Parameter', $period);
?>
<?php jpa_render_page_header('Periode & Parameter Penilaian', 'Parameter dapat dikoreksi sampai periode difinalisasi; perubahan akan menghitung ulang nilai peserta.', $conn, $period, 'periods'); ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(jpa_csrf_token()); ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="period_id" value="<?php echo intval($period['id'] ?? 0); ?>">
                    <?php $editable = !$period || $period['status'] !== 'finalized'; ?>
                    <fieldset <?php echo $editable ? '' : 'disabled'; ?>>
                        <div class="mb-3">
                            <label class="form-label">Nama periode</label>
                            <input class="form-control" name="name" required value="<?php echo htmlspecialchars($period['name'] ?? 'Job Portal Awards 2026'); ?>">
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6"><label class="form-label">Mulai</label><input type="date" class="form-control" name="period_start" required value="<?php echo htmlspecialchars($period['period_start'] ?? ''); ?>"></div>
                            <div class="col-md-6"><label class="form-label">Selesai</label><input type="date" class="form-control" name="period_end" required value="<?php echo htmlspecialchars($period['period_end'] ?? ''); ?>"></div>
                            <div class="col-md-6"><label class="form-label">Minimum bulan aktif</label><input type="number" min="1" class="form-control" name="min_active_months" value="<?php echo intval($period['min_active_months'] ?? 3); ?>"></div>
                            <div class="col-md-6"><label class="form-label">Target volume lowongan</label><input type="number" min="1" class="form-control" name="target_volume" value="<?php echo intval($period['target_volume'] ?? 1); ?>"></div>
                            <div class="col-md-4"><label class="form-label">Faktor penalti aduan</label><input type="number" min="0" step="0.01" class="form-control" name="complaint_penalty_factor" value="<?php echo htmlspecialchars($period['complaint_penalty_factor'] ?? '25'); ?>"></div>
                            <div class="col-md-4"><label class="form-label">Target progression (%)</label><input type="number" min="0.01" step="0.01" class="form-control" name="target_progression_rate" value="<?php echo htmlspecialchars($period['target_progression_rate'] ?? '1'); ?>"></div>
                            <div class="col-md-4"><label class="form-label">Target placement (%)</label><input type="number" min="0.01" step="0.01" class="form-control" name="target_placement_rate" value="<?php echo htmlspecialchars($period['target_placement_rate'] ?? '1'); ?>"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Atribut lowongan wajib (pisahkan dengan koma)</label>
                            <textarea class="form-control" name="mandatory_vacancy_fields" required><?php echo htmlspecialchars($fieldsValue); ?></textarea>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="impact_module_enabled" value="1" id="impact" <?php echo !empty($period['impact_module_enabled']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="impact">Aktifkan Modul Dampak Terverifikasi 15%</label>
                        </div>
                        <?php if ($period): ?><div class="mb-3"><label class="form-label">Alasan perubahan</label><input class="form-control" name="reason" placeholder="Wajib untuk jejak audit perubahan parameter"></div><?php endif; ?>
                        <button class="btn btn-primary" type="submit">Simpan Parameter</button>
                    </fieldset>
                </form>
                <?php if ($period && $period['status'] === 'draft'): ?>
                    <hr>
                    <form method="post" onsubmit="return confirm('Kunci parameter periode ini? Parameter tidak dapat diubah setelah dikunci.');">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(jpa_csrf_token()); ?>">
                        <input type="hidden" name="action" value="lock">
                        <input type="hidden" name="period_id" value="<?php echo intval($period['id']); ?>">
                        <input class="form-control mb-2" name="reason" required placeholder="Alasan penguncian / nomor berita acara">
                        <button class="btn btn-warning" type="submit"><i class="bi bi-lock"></i> Kunci Parameter</button>
                    </form>
                <?php elseif ($period && $period['status'] === 'finalized'): ?>
                    <hr>
                    <form method="post" onsubmit="return confirm('Buka kembali periode final? Semua persetujuan pemenang akan dibatalkan.');">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(jpa_csrf_token()); ?>">
                        <input type="hidden" name="action" value="reopen">
                        <input type="hidden" name="period_id" value="<?php echo intval($period['id']); ?>">
                        <input class="form-control mb-2" name="reason" required placeholder="Alasan resmi pembukaan kembali">
                        <button class="btn btn-outline-danger" type="submit">Buka Kembali Periode</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Periode Tersedia</div>
            <div class="list-group list-group-flush">
                <?php foreach ($periods as $item): ?>
                    <a class="list-group-item list-group-item-action" href="periods?period_id=<?php echo intval($item['id']); ?>">
                        <strong><?php echo htmlspecialchars($item['name']); ?></strong><br>
                        <small><?php echo htmlspecialchars($item['period_start'] . ' – ' . $item['period_end']); ?> · <?php echo htmlspecialchars(jpa_status_label('period', $item['status'])); ?></small>
                    </a>
                <?php endforeach; ?>
                <?php if (empty($periods)): ?><div class="list-group-item text-muted">Belum ada periode.</div><?php endif; ?>
            </div>
        </div>
        <?php if ($period): ?>
            <a class="btn btn-outline-secondary w-100 mt-3" href="periods">Buat Periode Baru</a>
        <?php endif; ?>
    </div>
</div>
<?php jpa_render_footer(); ?>

