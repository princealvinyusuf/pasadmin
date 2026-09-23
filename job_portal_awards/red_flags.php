<?php
require_once __DIR__ . '/bootstrap.php';
jpa_require_any(['job_portal_award_committee']);

$period = jpa_selected_period($conn);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    jpa_verify_csrf();
    $periodId = intval($_POST['period_id'] ?? 0);
    $period = jpa_get_period($conn, $periodId);
    if (!$period) {
        http_response_code(404);
        exit('Periode tidak ditemukan.');
    }
    jpa_assert_not_finalized($period);
    $action = (string)($_POST['action'] ?? '');
    $userId = intval($_SESSION['user_id'] ?? 0);

    if ($action === 'create') {
        $participantId = intval($_POST['participant_id'] ?? 0);
        $code = (string)($_POST['code'] ?? '');
        $description = trim((string)($_POST['description'] ?? ''));
        $evidence = trim((string)($_POST['evidence_reference'] ?? ''));
        $participant = jpa_get_participant($conn, $participantId);
        if (!$participant || intval($participant['period_id']) !== $periodId || !in_array($code, ['RF-1','RF-2','RF-4'], true)
            || $description === '' || strlen($description) > 10000 || $evidence === '' || strlen($evidence) > 1000) {
            jpa_set_flash('danger', 'Peserta, kode, deskripsi, dan referensi bukti wajib valid.');
            jpa_redirect('red_flags?period_id=' . $periodId);
        }
        $conn->begin_transaction();
        try {
            $lockedPeriod = jpa_lock_period($conn, $periodId);
            if (!$lockedPeriod || !in_array($lockedPeriod['status'], ['draft', 'locked'], true)) {
                throw new RuntimeException('Periode tidak tersedia untuk perubahan red flag.');
            }
            $stmt = $conn->prepare("INSERT INTO job_portal_award_red_flags
                (participant_id,code,description,evidence_reference,created_by) VALUES (?,?,?,?,?)");
            $stmt->bind_param('isssi', $participantId, $code, $description, $evidence, $userId);
            $stmt->execute();
            $flagId = intval($conn->insert_id);
            $stmt->close();
            jpa_audit($conn, $periodId, 'red_flag.created', 'red_flag', $flagId, null, ['participant_id' => $participantId, 'code' => $code, 'description' => $description, 'evidence' => $evidence]);
            jpa_recalculate_period($conn, $periodId, false);
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
        jpa_set_flash('success', 'Red flag dibuat dan peserta ditahan dari ranking sampai keputusan komite.');
        jpa_redirect('red_flags?period_id=' . $periodId);
    }

    if ($action === 'decide') {
        $flagId = intval($_POST['flag_id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        $consequence = (string)($_POST['consequence'] ?? 'review');
        $notes = trim((string)($_POST['committee_notes'] ?? ''));
        $stmt = $conn->prepare("SELECT rf.*,p.period_id FROM job_portal_award_red_flags rf
            JOIN job_portal_award_participants p ON p.id=rf.participant_id WHERE rf.id=?");
        $stmt->bind_param('i', $flagId);
        $stmt->execute();
        $before = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();
        if (!$before || intval($before['period_id']) !== $periodId || !in_array($status, ['pending','confirmed','dismissed'], true) || !in_array($consequence, ['review','disqualified','score_held'], true) || $notes === '') {
            jpa_set_flash('danger', 'Keputusan, konsekuensi, dan catatan komite wajib valid.');
            jpa_redirect('red_flags?period_id=' . $periodId);
        }
        if (in_array($status, ['pending', 'dismissed'], true)) {
            $consequence = 'review';
        } elseif ($consequence === 'review') {
            jpa_set_flash('danger', 'Red flag confirmed harus menghasilkan disqualified atau score_held.');
            jpa_redirect('red_flags?period_id=' . $periodId);
        }
        if (strlen($notes) > 500) {
            jpa_set_flash('danger', 'Catatan komite maksimal 500 karakter untuk audit ringkas.');
            jpa_redirect('red_flags?period_id=' . $periodId);
        }
        $conn->begin_transaction();
        try {
            $lockedPeriod = jpa_lock_period($conn, $periodId);
            if (!$lockedPeriod || !in_array($lockedPeriod['status'], ['draft', 'locked'], true)) {
                throw new RuntimeException('Periode tidak tersedia untuk keputusan komite.');
            }
            $stmt = $status === 'pending'
                ? $conn->prepare("UPDATE job_portal_award_red_flags SET
                    status=?,consequence=?,committee_notes=?,decided_by=NULL,decided_at=NULL WHERE id=? AND status=?")
                : $conn->prepare("UPDATE job_portal_award_red_flags SET
                    status=?,consequence=?,committee_notes=?,decided_by=?,decided_at=NOW() WHERE id=? AND status=?");
            if ($status === 'pending') {
                $stmt->bind_param('sssis', $status, $consequence, $notes, $flagId, $before['status']);
            } else {
                $stmt->bind_param('sssiis', $status, $consequence, $notes, $userId, $flagId, $before['status']);
            }
            $stmt->execute();
            if ($stmt->affected_rows !== 1) {
                throw new RuntimeException('Keputusan red flag tidak berubah atau telah diperbarui oleh proses lain.');
            }
            $stmt->close();
            $actionName = $before['status'] === 'pending' ? 'red_flag.decided' : 'red_flag.decision_updated';
            jpa_audit($conn, $periodId, $actionName, 'red_flag', $flagId, $before, ['status' => $status, 'consequence' => $consequence, 'committee_notes' => $notes], $notes);
            jpa_recalculate_period($conn, $periodId, false);
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
        jpa_set_flash('success', 'Keputusan komite disimpan dan ranking dihitung ulang.');
        jpa_redirect('red_flags?period_id=' . $periodId);
    }
}

$participants = [];
$flags = [];
if ($period) {
    $stmt = $conn->prepare('SELECT id,partner_id,partner_name FROM job_portal_award_participants WHERE period_id=? ORDER BY partner_name');
    $stmt->bind_param('i', $period['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $participants[] = $row;
    }
    $stmt->close();
    $stmt = $conn->prepare("SELECT rf.*,p.partner_id,p.partner_name FROM job_portal_award_red_flags rf
        JOIN job_portal_award_participants p ON p.id=rf.participant_id
        WHERE p.period_id=? ORDER BY FIELD(rf.status,'pending','confirmed','dismissed'),rf.created_at DESC");
    $stmt->bind_param('i', $period['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $flags[] = $row;
    }
    $stmt->close();
}

jpa_render_header('Red Flags & Committee Review', $period);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h1 class="h3 mb-1">Red Flags &amp; Committee Review</h1><p class="text-muted mb-0">Red flag pending, disqualified, atau score held tidak dapat masuk ranking final.</p></div>
    <?php jpa_render_period_selector($conn, $period, 'red_flags'); ?>
</div>
<?php if (!$period): ?>
    <div class="alert alert-info">Pilih periode penilaian.</div>
<?php else: ?>
    <?php if ($period['status'] !== 'finalized'): ?>
    <div class="card mb-4"><div class="card-header"><strong>Catat Red Flag</strong></div><div class="card-body">
        <form method="post" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(jpa_csrf_token()); ?>">
            <input type="hidden" name="action" value="create"><input type="hidden" name="period_id" value="<?php echo intval($period['id']); ?>">
            <div class="col-md-4"><label class="form-label">Peserta</label><select class="form-select" name="participant_id" required><option value="">Pilih</option><?php foreach ($participants as $item): ?><option value="<?php echo intval($item['id']); ?>"><?php echo htmlspecialchars($item['partner_name'] . ' (' . $item['partner_id'] . ')'); ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label">Kode</label><select class="form-select" name="code"><option>RF-1</option><option>RF-2</option><option>RF-4</option></select></div>
            <div class="col-md-6"><label class="form-label">Referensi bukti / URL / nomor dokumen</label><input class="form-control" name="evidence_reference" required></div>
            <div class="col-12"><label class="form-label">Deskripsi temuan</label><textarea class="form-control" name="description" required></textarea></div>
            <div class="col-12"><button class="btn btn-danger">Catat Red Flag</button></div>
        </form>
    </div></div>
    <?php endif; ?>
    <div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>Peserta</th><th>Temuan</th><th>Bukti</th><th>Status</th><th>Keputusan Komite</th></tr></thead><tbody>
        <?php foreach ($flags as $flag): ?><tr>
            <td><a href="participant?participant_id=<?php echo intval($flag['participant_id']); ?>"><?php echo htmlspecialchars($flag['partner_name']); ?></a><br><small><?php echo htmlspecialchars($flag['partner_id']); ?></small></td>
            <td><strong><?php echo htmlspecialchars($flag['code']); ?></strong><br><?php echo nl2br(htmlspecialchars($flag['description'])); ?></td>
            <td class="small"><?php echo htmlspecialchars($flag['evidence_reference']); ?></td>
            <td><span class="badge text-bg-<?php echo $flag['status'] === 'pending' ? 'warning' : ($flag['status'] === 'confirmed' ? 'danger' : 'secondary'); ?>"><?php echo htmlspecialchars($flag['status']); ?></span><br><small><?php echo htmlspecialchars($flag['consequence']); ?></small></td>
            <td>
                <?php if ($period['status'] !== 'finalized'): ?>
                <form method="post" class="row g-2 red-flag-decision-form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(jpa_csrf_token()); ?>"><input type="hidden" name="action" value="decide"><input type="hidden" name="period_id" value="<?php echo intval($period['id']); ?>"><input type="hidden" name="flag_id" value="<?php echo intval($flag['id']); ?>">
                    <div class="col-5"><select class="form-select form-select-sm red-flag-status" name="status">
                        <?php foreach (['pending' => 'Pending', 'confirmed' => 'Confirmed', 'dismissed' => 'Dismissed'] as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo $flag['status'] === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select></div>
                    <div class="col-7"><select class="form-select form-select-sm red-flag-consequence" name="consequence">
                        <?php foreach (['disqualified' => 'Disqualified', 'score_held' => 'Score Held', 'review' => 'No Consequence'] as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo $flag['consequence'] === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select></div>
                    <div class="col-12"><textarea class="form-control form-control-sm" name="committee_notes" required placeholder="Catatan/alasan perubahan keputusan"><?php echo htmlspecialchars($flag['committee_notes'] ?? ''); ?></textarea></div>
                    <div class="col-12"><button class="btn btn-sm btn-primary"><?php echo $flag['status'] === 'pending' ? 'Simpan Keputusan' : 'Perbarui Keputusan'; ?></button></div>
                </form>
                <?php else: ?><?php echo nl2br(htmlspecialchars($flag['committee_notes'] ?? '-')); ?><?php endif; ?>
            </td>
        </tr><?php endforeach; ?>
        <?php if (!$flags): ?><tr><td colspan="5" class="text-center text-muted py-4">Belum ada red flag.</td></tr><?php endif; ?>
        </tbody>
    </table></div></div>
<?php endif; ?>
<script>
document.querySelectorAll('.red-flag-decision-form').forEach((form) => {
    const status = form.querySelector('.red-flag-status');
    const consequence = form.querySelector('.red-flag-consequence');
    const syncConsequence = () => {
        const isConfirmed = status.value === 'confirmed';
        consequence.querySelector('option[value="review"]').disabled = isConfirmed;
        consequence.querySelectorAll('option[value="disqualified"], option[value="score_held"]').forEach((option) => {
            option.disabled = !isConfirmed;
        });
        if (isConfirmed && consequence.value === 'review') consequence.value = 'disqualified';
        if (!isConfirmed) consequence.value = 'review';
    };
    status.addEventListener('change', syncConsequence);
    syncConsequence();
});
</script>
<?php jpa_render_footer(); ?>

