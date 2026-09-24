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
<?php jpa_render_page_header('Penilaian & Rincian Skor', 'Seluruh indikator menggunakan skala 0–100 dan bobot periode aktif.', $conn, $period, 'scoring'); ?>
<?php if (!$period): ?>
    <?php jpa_render_no_period(); ?>
<?php else: ?>
    <?php jpa_render_period_banner($period); ?>
    <div class="d-flex align-items-center gap-3 mb-3 jpa-toolbar">
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
    <div class="card"><div class="table-responsive"><table class="table table-sm table-striped align-middle mb-0 jpa-table">
        <thead><tr><th>Peringkat</th><th class="jpa-sticky-column">Partner</th><?php foreach (jpa_score_fields() as $label): ?><th><?php echo htmlspecialchars($label); ?></th><?php endforeach; ?><th>Nilai Akhir</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?><tr>
            <td><?php echo $row['award_rank'] ? '#' . intval($row['award_rank']) : '-'; ?></td>
            <td class="jpa-sticky-column"><?php jpa_render_partner_cell($row); ?><br><?php echo jpa_status_badge('eligibility', $row['eligibility_status']); ?></td>
            <?php foreach (jpa_score_fields() as $field => $label): ?><td class="jpa-score"><?php echo number_format((float)$row[$field], 2); ?></td><?php endforeach; ?>
            <td class="jpa-score"><strong><?php echo number_format((float)$row['final_score'], 2); ?></strong></td>
        </tr><?php endforeach; ?>
        <?php if (!$rows): ?><?php jpa_render_empty_row(12, 'Belum ada peserta untuk dinilai.'); ?><?php endif; ?>
        </tbody>
    </table></div></div>
<?php endif; ?>
<?php jpa_render_footer(); ?>

