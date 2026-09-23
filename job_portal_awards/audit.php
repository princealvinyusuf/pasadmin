<?php
require_once __DIR__ . '/bootstrap.php';
jpa_require_any(['job_portal_award_view_audit', 'job_portal_award_export']);

$period = jpa_selected_period($conn);
$audits = [];
$exportRows = [];
$winnerRows = [];
$redFlagRows = [];
if ($period) {
    if (current_user_can('job_portal_award_view_audit')) {
        $stmt = $conn->prepare('SELECT * FROM job_portal_award_audit WHERE period_id=? ORDER BY created_at DESC,id DESC LIMIT 250');
        $stmt->bind_param('i', $period['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $audits[] = $row;
        }
        $stmt->close();
    }
    if (current_user_can('job_portal_award_export')) {
        $stmt = $conn->prepare("SELECT partner_id,partner_name,integration_type,eligibility_status,eligibility_reasons,
            records_sent_unique,published_unique_count,active_months,complete_vacancy_count,employer_unique_count,
            employer_valid_legal_count,duplicate_vacancy_count,valid_complaint_count,severe_complaint_count,
            applications_from_karirhub,progressed_candidate_count,hired_candidate_count,
            score_integration,score_volume,score_consistency,score_completeness,score_kyb,score_duplicate,
            score_complaint,score_progression,score_placement,final_score,award_rank
            FROM job_portal_award_participants WHERE period_id=? ORDER BY COALESCE(award_rank,999999),partner_name");
        $stmt->bind_param('i', $period['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $exportRows[] = $row;
        }
        $stmt->close();
        $stmt = $conn->prepare("SELECT award_rank,partner_id_snapshot AS partner_id,
            partner_name_snapshot AS partner_name,final_score_snapshot AS final_score,approved_at
            FROM job_portal_award_winners WHERE period_id=? ORDER BY award_rank");
        $stmt->bind_param('i', $period['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $winnerRows[] = $row;
        }
        $stmt->close();
        $stmt = $conn->prepare("SELECT p.partner_id,p.partner_name,rf.code,rf.description,rf.evidence_reference,
            rf.status,rf.consequence,rf.committee_notes,rf.decided_by,rf.decided_at
            FROM job_portal_award_red_flags rf JOIN job_portal_award_participants p ON p.id=rf.participant_id
            WHERE p.period_id=? ORDER BY p.partner_name,rf.created_at");
        $stmt->bind_param('i', $period['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $redFlagRows[] = $row;
        }
        $stmt->close();
    }
}

jpa_render_header('Audit Trail & Exports', $period);
?>
<?php if (current_user_can('job_portal_award_export')): ?><script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script><?php endif; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h1 class="h3 mb-1">Audit Trail &amp; Exports</h1><p class="text-muted mb-0">Jejak perubahan domain terpisah dari access log umum.</p></div>
    <?php jpa_render_period_selector($conn, $period, 'audit'); ?>
</div>
<?php if (!$period): ?>
    <div class="alert alert-info">Pilih periode penilaian.</div>
<?php else: ?>
    <?php if (current_user_can('job_portal_award_export')): ?>
    <div class="card mb-4 no-print"><div class="card-body d-flex flex-wrap gap-2 align-items-center"><strong class="me-auto">Bahan Rapat Komite</strong>
        <button class="btn btn-success" id="exportExcel"><i class="bi bi-file-earmark-excel"></i> Export Excel</button>
        <a class="btn btn-outline-primary" href="report?period_id=<?php echo intval($period['id']); ?>" target="_blank"><i class="bi bi-printer"></i> Laporan / Save as PDF</a>
    </div></div>
    <?php endif; ?>
    <?php if (current_user_can('job_portal_award_view_audit')): ?>
    <div class="card"><div class="card-header"><strong>250 Aktivitas Terbaru</strong></div><div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Waktu</th><th>Aktor</th><th>Aksi</th><th>Entitas</th><th>Alasan</th><th>Snapshot</th></tr></thead><tbody>
        <?php foreach ($audits as $item): ?><tr>
            <td><?php echo htmlspecialchars($item['created_at']); ?></td><td><?php echo intval($item['actor_user_id']); ?></td><td><code><?php echo htmlspecialchars($item['action']); ?></code></td><td><?php echo htmlspecialchars($item['entity_type'] . ' #' . $item['entity_id']); ?></td><td><?php echo htmlspecialchars($item['reason'] ?? ''); ?></td>
            <td><details><summary>Before / After</summary><div class="row g-2"><div class="col-md-6"><strong>Before</strong><pre class="small text-wrap"><?php echo htmlspecialchars($item['before_json'] ?: 'null'); ?></pre></div><div class="col-md-6"><strong>After</strong><pre class="small text-wrap"><?php echo htmlspecialchars($item['after_json'] ?: 'null'); ?></pre></div></div></details></td>
        </tr><?php endforeach; ?>
        <?php if (!$audits): ?><tr><td colspan="6" class="text-center text-muted py-4">Belum ada aktivitas audit.</td></tr><?php endif; ?>
        </tbody>
    </table></div></div>
    <?php endif; ?>
<?php endif; ?>
<?php if ($period && current_user_can('job_portal_award_export')): ?>
<script>
document.getElementById('exportExcel').addEventListener('click', () => {
    const participants = <?php echo jpa_json($exportRows); ?>;
    const winners = <?php echo jpa_json($winnerRows); ?>;
    const redFlags = <?php echo jpa_json($redFlagRows); ?>;
    const config = [<?php echo jpa_json([
        'period' => $period['name'],
        'period_start' => $period['period_start'],
        'period_end' => $period['period_end'],
        'status' => $period['status'],
        'min_active_months' => $period['min_active_months'],
        'target_volume' => $period['target_volume'],
        'mandatory_vacancy_fields' => $period['mandatory_vacancy_fields'],
        'complaint_penalty_factor' => $period['complaint_penalty_factor'],
        'target_progression_rate' => $period['target_progression_rate'],
        'target_placement_rate' => $period['target_placement_rate'],
        'impact_module_enabled' => $period['impact_module_enabled'],
        'weights_json' => $period['weights_json'],
    ]); ?>];
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.json_to_sheet(config), 'Configuration');
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.json_to_sheet(participants), 'Assessment');
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.json_to_sheet(redFlags), 'Red Flags');
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.json_to_sheet(winners), 'Winners');
    XLSX.writeFile(workbook, 'job_portal_awards_<?php echo intval($period['id']); ?>.xlsx');
});
</script>
<?php endif; ?>
<?php jpa_render_footer(); ?>

