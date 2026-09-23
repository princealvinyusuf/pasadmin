<?php
require_once __DIR__ . '/bootstrap.php';
jpa_require_any(['job_portal_award_view']);

$period = jpa_selected_period($conn);
$summary = ['participants' => 0, 'eligible' => 0, 'ineligible' => 0, 'pending_flags' => 0, 'finalized_winners' => 0];
$topRows = [];
$recentAudit = [];
if ($period) {
    $stmt = $conn->prepare("SELECT COUNT(*) participants,
        SUM(eligibility_status='eligible') eligible,
        SUM(eligibility_status='ineligible') ineligible
        FROM job_portal_award_participants WHERE period_id=?");
    $stmt->bind_param('i', $period['id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $summary = array_merge($summary, $row ?: []);

    $stmt = $conn->prepare("SELECT COUNT(*) total FROM job_portal_award_red_flags rf
        JOIN job_portal_award_participants p ON p.id=rf.participant_id
        WHERE p.period_id=? AND rf.status='pending'");
    $stmt->bind_param('i', $period['id']);
    $stmt->execute();
    $summary['pending_flags'] = intval($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    $stmt = $conn->prepare('SELECT COUNT(*) total FROM job_portal_award_winners WHERE period_id=?');
    $stmt->bind_param('i', $period['id']);
    $stmt->execute();
    $summary['finalized_winners'] = intval($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    $stmt = $conn->prepare("SELECT id,partner_id,partner_name,final_score,award_rank,eligibility_status
        FROM job_portal_award_participants WHERE period_id=? AND award_rank IS NOT NULL ORDER BY award_rank LIMIT 10");
    $stmt->bind_param('i', $period['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $topRows[] = $row;
    }
    $stmt->close();

    $stmt = $conn->prepare('SELECT * FROM job_portal_award_audit WHERE period_id=? ORDER BY created_at DESC,id DESC LIMIT 8');
    $stmt->bind_param('i', $period['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recentAudit[] = $row;
    }
    $stmt->close();
}

jpa_render_header('Dashboard', $period);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">Dashboard Job Portal Awards</h1><p class="text-muted mb-0">Ringkasan kelayakan, penilaian, review komite, dan pemenang.</p></div>
    <?php jpa_render_period_selector($conn, $period, 'dashboard'); ?>
</div>
<?php if (!$period): ?>
    <div class="alert alert-info">Belum ada periode penilaian yang dapat ditampilkan.</div>
<?php else: ?>
    <div class="card mb-4"><div class="card-body d-flex justify-content-between align-items-center">
        <div><h2 class="h5 mb-1"><?php echo htmlspecialchars($period['name']); ?></h2><span class="text-muted"><?php echo htmlspecialchars($period['period_start'] . ' – ' . $period['period_end']); ?></span></div>
        <span class="badge text-bg-<?php echo $period['status'] === 'finalized' ? 'success' : ($period['status'] === 'locked' ? 'warning' : 'secondary'); ?> fs-6"><?php echo htmlspecialchars($period['status']); ?></span>
    </div></div>
    <div class="row g-3 mb-4">
        <?php foreach ([
            ['Peserta', $summary['participants'], 'primary'],
            ['Eligible', $summary['eligible'], 'success'],
            ['Ineligible', $summary['ineligible'], 'secondary'],
            ['Red Flag Pending', $summary['pending_flags'], 'danger'],
            ['Pemenang Disahkan', $summary['finalized_winners'], 'info'],
        ] as [$label,$value,$color]): ?>
            <div class="col-6 col-lg"><div class="card h-100 border-<?php echo $color; ?>"><div class="card-body"><div class="text-muted small"><?php echo $label; ?></div><div class="display-6"><?php echo intval($value); ?></div></div></div></div>
        <?php endforeach; ?>
    </div>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card h-100"><div class="card-header"><strong>Ranking Sementara / Final</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Rank</th><th>Partner</th><th>Nilai Akhir</th><th></th></tr></thead><tbody>
                <?php foreach ($topRows as $row): ?><tr><td>#<?php echo intval($row['award_rank']); ?></td><td><?php echo htmlspecialchars($row['partner_name']); ?><br><small class="text-muted"><?php echo htmlspecialchars($row['partner_id']); ?></small></td><td class="jpa-score"><strong><?php echo number_format((float)$row['final_score'], 2); ?></strong></td><td><a class="btn btn-sm btn-outline-secondary" href="participant?participant_id=<?php echo intval($row['id']); ?>">Detail</a></td></tr><?php endforeach; ?>
                <?php if (!$topRows): ?><tr><td colspan="4" class="text-center text-muted py-4">Belum ada ranking. Import data dan jalankan kalkulasi.</td></tr><?php endif; ?>
            </tbody></table></div></div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100"><div class="card-header"><strong>Aktivitas Terbaru</strong></div><div class="list-group list-group-flush">
                <?php foreach ($recentAudit as $item): ?><div class="list-group-item"><strong><?php echo htmlspecialchars($item['action']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($item['entity_type'] . ' #' . $item['entity_id'] . ' · ' . $item['created_at']); ?></small></div><?php endforeach; ?>
                <?php if (!$recentAudit): ?><div class="list-group-item text-muted">Belum ada aktivitas.</div><?php endif; ?>
            </div></div>
        </div>
    </div>
<?php endif; ?>
<?php jpa_render_footer(); ?>

