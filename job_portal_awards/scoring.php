<?php
require_once __DIR__ . '/bootstrap.php';
jpa_require_any(['job_portal_award_view_scores', 'job_portal_award_recalculate']);

$period = jpa_selected_period($conn);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    jpa_require_any(['job_portal_award_recalculate']);
    jpa_verify_csrf();
    $periodId = intval($_POST['period_id'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? ''));
    if ($reason === '' || strlen($reason) > 500) {
        http_response_code(422);
        exit('Alasan kalkulasi ulang wajib diisi.');
    }
    $conn->begin_transaction();
    try {
        $period = jpa_lock_period($conn, $periodId);
        if (!$period || $period['status'] === 'finalized') {
            throw new RuntimeException('Periode tidak tersedia untuk kalkulasi ulang.');
        }
        $ranked = jpa_recalculate_period($conn, $periodId, false);
        jpa_audit($conn, $periodId, 'scores.recalculated', 'period', $periodId, null, ['ranked_count' => count($ranked)], $reason);
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
    jpa_set_flash('success', 'Seluruh sub-score, Nilai Akhir, dan ranking dihitung ulang.');
    jpa_redirect('scoring?period_id=' . $periodId);
}

$rows = [];
if ($period) {
    $stmt = $conn->prepare('SELECT * FROM job_portal_award_participants WHERE period_id=? ORDER BY COALESCE(award_rank,999999),final_score DESC,partner_name');
    $stmt->bind_param('i', $period['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
}

jpa_render_header('Scoring', $period);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h1 class="h3 mb-1">Scoring &amp; Breakdown</h1><p class="text-muted mb-0">Seluruh indikator menggunakan skala 0–100 dan bobot framework final.</p></div>
    <?php jpa_render_period_selector($conn, $period, 'scoring'); ?>
</div>
<?php if (!$period): ?>
    <div class="alert alert-info">Pilih periode penilaian.</div>
<?php else: ?>
    <div class="d-flex align-items-center gap-3 mb-3">
        <span class="badge text-bg-info">Modul Dampak: <?php echo $period['impact_module_enabled'] ? 'Aktif (100%)' : 'Nonaktif (85% dinormalisasi)'; ?></span>
        <?php if ($period['status'] !== 'finalized' && current_user_can('job_portal_award_recalculate')): ?>
        <form method="post" class="d-flex gap-2 ms-auto no-print" onsubmit="return confirm('Hitung ulang seluruh skor?');">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(jpa_csrf_token()); ?>">
            <input type="hidden" name="period_id" value="<?php echo intval($period['id']); ?>">
            <input class="form-control form-control-sm" name="reason" required placeholder="Alasan kalkulasi ulang">
            <button class="btn btn-sm btn-primary">Kalkulasi Ulang</button>
        </form>
        <?php endif; ?>
    </div>
    <div class="card"><div class="table-responsive"><table class="table table-sm table-striped align-middle mb-0">
        <thead><tr><th>Rank</th><th>Partner</th><th>Integrasi</th><th>Volume</th><th>Konsistensi</th><th>Completeness</th><th>KYB</th><th>Duplikasi</th><th>Aduan</th><th>Progression</th><th>Placement</th><th>Nilai Akhir</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?><tr>
            <td><?php echo $row['award_rank'] ? '#' . intval($row['award_rank']) : '-'; ?></td>
            <td><a href="participant?participant_id=<?php echo intval($row['id']); ?>"><?php echo htmlspecialchars($row['partner_name']); ?></a><br><small class="text-muted"><?php echo htmlspecialchars($row['eligibility_status']); ?></small></td>
            <?php foreach (['score_integration','score_volume','score_consistency','score_completeness','score_kyb','score_duplicate','score_complaint','score_progression','score_placement'] as $field): ?><td class="jpa-score"><?php echo number_format((float)$row[$field], 2); ?></td><?php endforeach; ?>
            <td class="jpa-score"><strong><?php echo number_format((float)$row['final_score'], 2); ?></strong></td>
        </tr><?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="12" class="text-center text-muted py-4">Belum ada peserta.</td></tr><?php endif; ?>
        </tbody>
    </table></div></div>
<?php endif; ?>
<?php jpa_render_footer(); ?>

