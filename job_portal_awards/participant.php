<?php
require_once __DIR__ . '/bootstrap.php';
jpa_require_any([
    'job_portal_award_manage_data',
    'job_portal_award_review_eligibility',
    'job_portal_award_view_scores',
    'job_portal_award_recalculate',
    'job_portal_award_committee',
    'job_portal_award_approve_winners',
]);

$participant = jpa_get_participant($conn, intval($_GET['participant_id'] ?? 0));
if (!$participant) {
    http_response_code(404);
    exit('Peserta tidak ditemukan.');
}
$period = jpa_get_period($conn, intval($participant['period_id']));
$calculation = jpa_compute_scores($participant, jpa_period_config($period));
$weights = jpa_period_config($period)['weights'];
$flags = [];
$stmt = $conn->prepare('SELECT * FROM job_portal_award_red_flags WHERE participant_id=? ORDER BY created_at DESC');
$stmt->bind_param('i', $participant['id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $flags[] = $row;
}
$stmt->close();

$indicators = [
    'integration' => ['1.1 Skema Integrasi', 'integration_type'],
    'volume' => ['2.1 Volume Lowongan Unik Published', 'published_unique_count / target_volume'],
    'consistency' => ['2.2 Konsistensi Pasokan', 'active_months / months_in_period'],
    'completeness' => ['3.1 Kelengkapan Atribut Wajib', 'complete_vacancy_count / published_unique_count'],
    'kyb' => ['3.2 Validitas Legal / KYB', 'employer_valid_legal_count / employer_unique_count'],
    'duplicate' => ['3.3 Tingkat Duplikasi', '100 - duplicate_vacancy_count / records_sent_unique'],
    'complaint' => ['3.4 Tingkat Aduan Valid', '100 - complaint_rate_per_1000 × penalty_factor'],
    'progression' => ['4.1 Kandidat Lolos Kurasi', 'progression_rate / target_progression_rate'],
    'placement' => ['4.2 Placement Rate', 'placement_rate / target_placement_rate'],
];
$rawFields = [
    'integration_type','records_sent_unique','published_unique_count','active_months','complete_vacancy_count',
    'employer_unique_count','employer_valid_legal_count','duplicate_vacancy_count','valid_complaint_count',
    'severe_complaint_count','applications_from_karirhub','progressed_candidate_count','hired_candidate_count',
];

jpa_render_header('Detail Peserta', $period);
?>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div><h1 class="h3 mb-1"><?php echo htmlspecialchars($participant['partner_name']); ?></h1><div class="text-muted"><?php echo htmlspecialchars($participant['partner_id']); ?> · <?php echo htmlspecialchars($period['name']); ?></div></div>
    <div class="text-end">
        <div class="display-6 jpa-score"><?php echo number_format((float)$participant['final_score'], 2); ?></div>
        <span class="badge text-bg-<?php echo $participant['eligibility_status'] === 'eligible' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($participant['eligibility_status']); ?></span>
        <?php if (current_user_can('job_portal_award_manage_data') && in_array($period['status'], ['draft', 'locked'], true)): ?>
            <div class="mt-2"><a class="btn btn-sm btn-outline-primary" href="participants?period_id=<?php echo intval($period['id']); ?>&edit_id=<?php echo intval($participant['id']); ?>"><i class="bi bi-pencil"></i> Edit Data</a></div>
        <?php endif; ?>
    </div>
</div>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4"><div class="card-header"><strong>Breakdown Formula dan Kontribusi</strong></div><div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>Indikator</th><th>Formula</th><th>Skor</th><th>Bobot</th><th>Kontribusi</th></tr></thead><tbody>
            <?php foreach ($indicators as $key => [$label,$formula]): ?><tr class="<?php echo (!$period['impact_module_enabled'] && in_array($key, ['progression','placement'], true)) ? 'table-secondary' : ''; ?>">
                <td><?php echo htmlspecialchars($label); ?></td><td><code><?php echo htmlspecialchars($formula); ?></code></td>
                <td class="jpa-score"><?php echo number_format((float)$calculation['scores'][$key], 2); ?></td>
                <td><?php echo number_format((float)$weights[$key], 0); ?>%</td><td class="jpa-score"><?php echo number_format((float)$calculation['weighted'][$key], 2); ?></td>
            </tr><?php endforeach; ?>
            </tbody><tfoot><tr><th colspan="4">Nilai Akhir<?php echo $period['impact_module_enabled'] ? '' : ' (core dinormalisasi)'; ?></th><th class="jpa-score"><?php echo number_format((float)$calculation['final_score'], 2); ?></th></tr></tfoot>
        </table></div></div>
        <div class="card"><div class="card-header"><strong>Red Flags</strong></div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Kode</th><th>Deskripsi</th><th>Status</th><th>Konsekuensi</th><th>Keputusan</th></tr></thead><tbody>
            <?php foreach ($flags as $flag): ?><tr><td><?php echo htmlspecialchars($flag['code']); ?></td><td><?php echo htmlspecialchars($flag['description']); ?></td><td><?php echo htmlspecialchars($flag['status']); ?></td><td><?php echo htmlspecialchars($flag['consequence']); ?></td><td><?php echo nl2br(htmlspecialchars($flag['committee_notes'] ?? '')); ?></td></tr><?php endforeach; ?>
            <?php if (!$flags): ?><tr><td colspan="5" class="text-center text-muted">Tidak ada red flag.</td></tr><?php endif; ?>
        </tbody></table></div></div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-4"><div class="card-header"><strong>Data Dasar Penilaian</strong></div><div class="list-group list-group-flush">
            <?php foreach ($rawFields as $field): ?>
                <div class="list-group-item d-flex justify-content-between align-items-center gap-3">
                    <span class="small"><?php echo jpa_field_help_html($field, true); ?></span>
                    <strong class="text-end"><?php echo htmlspecialchars(
                        $field === 'integration_type'
                            ? (['full' => 'Penuh', 'semi' => 'Sebagian', 'inactive' => 'Tidak aktif'][$participant[$field]] ?? $participant[$field])
                            : number_format((int)$participant[$field], 0, ',', '.')
                    ); ?></strong>
                </div>
            <?php endforeach; ?>
        </div></div>
        <div class="card"><div class="card-header"><strong>Kelayakan Peserta</strong></div><div class="card-body">
            <div class="mb-2"><?php echo JPA_ELIGIBILITY_GATES['partnership_active']; ?> <?php echo jpa_field_help_html('partnership_active'); ?>: <strong><?php echo $participant['partnership_active'] ? 'Ya' : 'Tidak'; ?></strong></div>
            <div class="mb-2"><?php echo JPA_ELIGIBILITY_GATES['active_months']; ?> <?php echo jpa_field_help_html('active_months'); ?>: <strong><?php echo intval($participant['active_months']); ?> / <?php echo intval($period['min_active_months']); ?></strong></div>
            <div class="mb-2"><?php echo JPA_ELIGIBILITY_GATES['critical_violation_resolved']; ?> <?php echo jpa_field_help_html('critical_violation_resolved'); ?>: <strong><?php echo $participant['critical_violation_resolved'] ? 'Ya' : 'Tidak'; ?></strong></div>
            <div class="mb-2"><?php echo JPA_ELIGIBILITY_GATES['data_traceable']; ?> <?php echo jpa_field_help_html('data_traceable'); ?>: <strong><?php echo $participant['data_traceable'] ? 'Ya' : 'Tidak'; ?></strong></div>
            <?php if ($participant['eligibility_reasons']): ?><div class="alert alert-warning mb-0"><?php echo nl2br(htmlspecialchars($participant['eligibility_reasons'])); ?></div><?php endif; ?>
        </div></div>
    </div>
</div>
<?php jpa_render_footer(); ?>

