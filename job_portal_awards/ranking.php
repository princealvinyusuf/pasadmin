<?php
require_once __DIR__ . '/bootstrap.php';
jpa_require_any(['job_portal_award_approve_winners', 'job_portal_award_view_scores']);

$period = jpa_selected_period($conn);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    jpa_require_any(['job_portal_award_approve_winners']);
    jpa_verify_csrf();
    $periodId = intval($_POST['period_id'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? ''));
    if ($reason === '' || strlen($reason) > 500) {
        http_response_code(409);
        exit('Finalisasi hanya dapat dilakukan untuk periode locked dengan dasar keputusan komite.');
    }
    $userId = intval($_SESSION['user_id'] ?? 0);
    $conn->begin_transaction();
    try {
        $period = jpa_lock_period($conn, $periodId);
        if (!$period || $period['status'] !== 'locked') {
            throw new RuntimeException('Periode bukan locked atau telah diproses oleh pengguna lain.');
        }
        $stmt = $conn->prepare("SELECT COUNT(*) total FROM job_portal_award_red_flags rf
            JOIN job_portal_award_participants p ON p.id=rf.participant_id
            WHERE p.period_id=? AND rf.status='pending'");
        $stmt->bind_param('i', $periodId);
        $stmt->execute();
        $pendingFlags = intval($stmt->get_result()->fetch_assoc()['total'] ?? 0);
        $stmt->close();
        if ($pendingFlags > 0) {
            throw new RuntimeException('Semua red flag pending harus diputuskan sebelum finalisasi.');
        }
        $ranked = jpa_recalculate_period($conn, $periodId, false);
        if (count($ranked) < 3) {
            throw new RuntimeException('Minimal tiga peserta eligible tanpa blokir diperlukan untuk menetapkan Rank 1–3.');
        }
        $delete = $conn->prepare('DELETE FROM job_portal_award_winners WHERE period_id=?');
        $delete->bind_param('i', $periodId);
        $delete->execute();
        $delete->close();
        $insert = $conn->prepare("INSERT INTO job_portal_award_winners
            (period_id,participant_id,award_rank,partner_id_snapshot,partner_name_snapshot,final_score_snapshot,
             scores_snapshot_json,config_snapshot_json,approved_by) VALUES (?,?,?,?,?,?,?,?,?)");
        $winnerSnapshot = [];
        $configSnapshot = jpa_json($period);
        foreach (array_slice($ranked, 0, 3) as $row) {
            $participantId = intval($row['id']);
            $rank = intval($row['award_rank']);
            $partnerId = (string)$row['partner_id'];
            $partnerName = (string)$row['partner_name'];
            $finalScore = floatval($row['final_score']);
            $scoreSnapshot = jpa_json(array_intersect_key($row, array_flip([
                'score_integration','score_volume','score_consistency','score_completeness','score_kyb',
                'score_duplicate','score_complaint','score_progression','score_placement','final_score',
            ])));
            $insert->bind_param('iiissdssi', $periodId, $participantId, $rank, $partnerId, $partnerName, $finalScore, $scoreSnapshot, $configSnapshot, $userId);
            $insert->execute();
            $winnerSnapshot[] = ['participant_id' => $participantId, 'partner_name' => $partnerName, 'rank' => $rank, 'final_score' => $finalScore];
        }
        $insert->close();
        $update = $conn->prepare("UPDATE job_portal_award_periods SET status='finalized',finalized_by=?,finalized_at=NOW() WHERE id=? AND status='locked'");
        $update->bind_param('ii', $userId, $periodId);
        $update->execute();
        if ($update->affected_rows !== 1) {
            throw new RuntimeException('Periode berubah saat finalisasi.');
        }
        $update->close();
        jpa_audit($conn, $periodId, 'winners.finalized', 'period', $periodId, null, $winnerSnapshot, $reason);
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        jpa_set_flash('danger', $e->getMessage());
        jpa_redirect('ranking?period_id=' . $periodId);
    }
    jpa_set_flash('success', 'Rank 1–3 disahkan dan periode dibekukan.');
    jpa_redirect('ranking?period_id=' . $periodId);
}

$rows = [];
$winners = [];
$pendingFlags = 0;
if ($period) {
    $stmt = $conn->prepare("SELECT p.*,
        EXISTS(SELECT 1 FROM job_portal_award_red_flags rf WHERE rf.participant_id=p.id AND rf.status='pending') pending_flag,
        EXISTS(SELECT 1 FROM job_portal_award_red_flags rf WHERE rf.participant_id=p.id AND rf.status='confirmed' AND rf.consequence='disqualified') disqualified,
        EXISTS(SELECT 1 FROM job_portal_award_red_flags rf WHERE rf.participant_id=p.id AND rf.status='confirmed' AND rf.consequence='score_held') score_held
        FROM job_portal_award_participants p WHERE p.period_id=?
        ORDER BY COALESCE(p.award_rank,999999),p.final_score DESC,p.partner_name");
    $stmt->bind_param('i', $period['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
        if ($row['pending_flag']) {
            $pendingFlags++;
        }
    }
    $stmt->close();
    $stmt = $conn->prepare("SELECT w.*,w.partner_id_snapshot AS partner_id,
        w.partner_name_snapshot AS partner_name,w.final_score_snapshot AS final_score
        FROM job_portal_award_winners w WHERE w.period_id=? ORDER BY w.award_rank");
    $stmt->bind_param('i', $period['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $winners[] = $row;
    }
    $stmt->close();
}

jpa_render_header('Final Ranking & Winners', $period);
?>
<?php jpa_render_page_header('Peringkat & Pemenang', 'Pemecah seri: nilai aduan, kelengkapan, lalu volume tayang untuk selisih di bawah 0,50.', $conn, $period, 'ranking'); ?>
<?php if (!$period): ?>
    <?php jpa_render_no_period(); ?>
<?php else: ?>
    <?php jpa_render_period_banner($period); ?>
    <?php if ($winners): ?>
    <div class="row g-3 mb-4">
        <?php foreach ($winners as $winner): ?><div class="col-md-4"><div class="card jpa-winner-card h-100" data-rank="<?php echo intval($winner['award_rank']); ?>"><div class="card-body text-center"><div class="jpa-winner-rank text-muted"><i class="bi bi-trophy-fill me-1"></i> Peringkat <?php echo intval($winner['award_rank']); ?></div><h2 class="h4 mt-2"><?php echo htmlspecialchars($winner['partner_name']); ?></h2><div class="display-6 jpa-score"><?php echo number_format((float)$winner['final_score'], 2); ?></div><small class="text-muted">Disahkan <?php echo htmlspecialchars($winner['approved_at']); ?></small></div></div></div><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="card mb-4"><div class="table-responsive"><table class="table table-striped align-middle mb-0 jpa-table">
        <thead><tr><th>Peringkat</th><th class="jpa-sticky-column">Partner</th><th>Nilai Akhir</th><th>Aduan</th><th>Kelengkapan</th><th>Volume</th><th>Status Peringkat</th></tr></thead><tbody>
        <?php foreach ($rows as $row): ?><tr>
            <td><?php echo $row['award_rank'] ? '#' . intval($row['award_rank']) : '-'; ?></td>
            <td class="jpa-sticky-column"><?php jpa_render_partner_cell($row); ?></td>
            <td class="jpa-score"><strong><?php echo number_format((float)$row['final_score'], 2); ?></strong></td><td><?php echo number_format((float)$row['score_complaint'], 2); ?></td><td><?php echo number_format((float)$row['score_completeness'], 2); ?></td><td><?php echo number_format((int)$row['published_unique_count']); ?></td>
            <td><?php if ($row['eligibility_status'] !== 'eligible'): ?><span class="badge text-bg-secondary">Tidak Layak</span><?php elseif ($row['pending_flag']): ?><span class="badge text-bg-warning">Menunggu Review</span><?php elseif ($row['disqualified']): ?><span class="badge text-bg-danger">Diskualifikasi</span><?php elseif ($row['score_held']): ?><span class="badge text-bg-warning">Nilai Ditahan</span><?php else: ?><span class="badge text-bg-success">Masuk Peringkat</span><?php endif; ?></td>
        </tr><?php endforeach; ?>
        <?php if (!$rows): ?><?php jpa_render_empty_row(7, 'Belum ada peserta untuk diperingkat.'); ?><?php endif; ?>
        </tbody>
    </table></div></div>
    <?php if ($period['status'] === 'locked' && current_user_can('job_portal_award_approve_winners')): ?>
    <div class="card border-danger no-print"><div class="card-body"><h2 class="h5">Pengesahan Komite</h2>
        <?php if ($pendingFlags): ?><div class="alert alert-warning"><?php echo $pendingFlags; ?> red flag masih pending dan harus diputuskan.</div><?php endif; ?>
        <form method="post" onsubmit="return confirm('Sahkan Rank 1–3 dan bekukan seluruh data periode?');">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(jpa_csrf_token()); ?>"><input type="hidden" name="period_id" value="<?php echo intval($period['id']); ?>">
            <label class="form-label">Dasar keputusan / nomor berita acara</label><input class="form-control mb-3" name="reason" required>
            <button class="btn btn-danger" <?php echo $pendingFlags ? 'disabled' : ''; ?>><i class="bi bi-patch-check"></i> Sahkan Rank 1–3 &amp; Finalisasi</button>
        </form>
    </div></div>
    <?php endif; ?>
<?php endif; ?>
<?php jpa_render_footer(); ?>

