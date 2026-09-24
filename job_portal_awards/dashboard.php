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
<?php jpa_render_page_header('Dashboard Job Portal Awards', 'Ringkasan kelayakan, penilaian, review komite, dan pemenang.', $conn, $period, 'dashboard'); ?>
<?php if (!$period): ?>
    <?php jpa_render_no_period('Belum ada periode penilaian yang dapat ditampilkan.'); ?>
<?php else: ?>
    <?php jpa_render_period_banner($period); ?>
    <div class="row g-3 mb-4">
        <?php foreach ([
            ['Peserta', $summary['participants'], 'primary', 'people', 'participants?period_id=' . intval($period['id'])],
            ['Layak', $summary['eligible'], 'success', 'check-circle', 'eligibility?period_id=' . intval($period['id']) . '&status=eligible'],
            ['Tidak Layak', $summary['ineligible'], 'secondary', 'x-circle', 'eligibility?period_id=' . intval($period['id']) . '&status=ineligible'],
            ['Red Flag Menunggu', $summary['pending_flags'], 'danger', 'flag', 'red_flags?period_id=' . intval($period['id']) . '&status=pending'],
            ['Pemenang Disahkan', $summary['finalized_winners'], 'info', 'trophy', 'ranking?period_id=' . intval($period['id'])],
        ] as [$label,$value,$color,$icon,$href]): ?>
            <div class="col-6 col-lg-4 col-xl"><a class="card h-100 jpa-stat-card border-<?php echo $color; ?>" href="<?php echo htmlspecialchars($href); ?>"><div class="card-body d-flex justify-content-between gap-2"><div><div class="text-muted small"><?php echo $label; ?></div><div class="display-6"><?php echo intval($value); ?></div></div><span class="jpa-stat-icon bg-<?php echo $color; ?>-subtle text-<?php echo $color; ?>"><i class="bi bi-<?php echo $icon; ?>"></i></span></div></a></div>
        <?php endforeach; ?>
    </div>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card h-100"><div class="card-header jpa-section-heading"><strong>Peringkat Sementara / Final</strong><a class="btn btn-sm btn-outline-primary" href="ranking?period_id=<?php echo intval($period['id']); ?>">Lihat Semua</a></div><div class="table-responsive"><table class="table mb-0 jpa-table"><thead><tr><th>Peringkat</th><th>Partner</th><th>Nilai Akhir</th><th></th></tr></thead><tbody>
                <?php foreach ($topRows as $row): ?><tr><td>#<?php echo intval($row['award_rank']); ?></td><td><?php jpa_render_partner_cell($row); ?></td><td class="jpa-score"><strong><?php echo number_format((float)$row['final_score'], 2); ?></strong></td><td><a class="btn btn-sm btn-outline-secondary" href="participant?participant_id=<?php echo intval($row['id']); ?>">Detail</a></td></tr><?php endforeach; ?>
                <?php if (!$topRows): ?><?php jpa_render_empty_row(4, 'Belum ada peringkat. Import data dan jalankan kalkulasi.'); ?><?php endif; ?>
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

