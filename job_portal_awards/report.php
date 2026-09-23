<?php
require_once __DIR__ . '/bootstrap.php';
jpa_require_any(['job_portal_award_export']);

$period = jpa_get_period($conn, intval($_GET['period_id'] ?? 0));
if (!$period) {
    http_response_code(404);
    exit('Periode tidak ditemukan.');
}
$participants = [];
$flags = [];
$winners = [];
$stmt = $conn->prepare('SELECT * FROM job_portal_award_participants WHERE period_id=? ORDER BY COALESCE(award_rank,999999),partner_name');
$stmt->bind_param('i', $period['id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $participants[] = $row;
}
$stmt->close();
$stmt = $conn->prepare("SELECT rf.*,p.partner_name FROM job_portal_award_red_flags rf
    JOIN job_portal_award_participants p ON p.id=rf.participant_id WHERE p.period_id=? ORDER BY rf.created_at");
$stmt->bind_param('i', $period['id']);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $flags[] = $row;
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
$mandatoryFields = json_decode((string)$period['mandatory_vacancy_fields'], true) ?: [];
$weights = json_decode((string)$period['weights_json'], true) ?: JPA_DEFAULT_WEIGHTS;

jpa_render_header('Laporan Komite', $period);
?>
<div class="d-flex justify-content-between align-items-start mb-4">
    <div><h1 class="h2 mb-1">Laporan Penilaian Job Portal Awards</h1><h2 class="h5 text-muted"><?php echo htmlspecialchars($period['name']); ?></h2></div>
    <button class="btn btn-primary no-print" onclick="window.print()"><i class="bi bi-printer"></i> Print / Save as PDF</button>
</div>
<section class="mb-5">
    <h3 class="h5">Konfigurasi Terkunci</h3>
    <div class="row g-2">
        <div class="col-6 col-md-3"><strong>Periode</strong><br><?php echo htmlspecialchars($period['period_start'] . ' – ' . $period['period_end']); ?></div>
        <div class="col-6 col-md-3"><strong>Status</strong><br><?php echo htmlspecialchars($period['status']); ?></div>
        <div class="col-6 col-md-3"><strong>Minimum bulan aktif</strong><br><?php echo intval($period['min_active_months']); ?></div>
        <div class="col-6 col-md-3"><strong>Target volume</strong><br><?php echo number_format((int)$period['target_volume']); ?></div>
        <div class="col-6 col-md-3"><strong>Faktor penalti</strong><br><?php echo htmlspecialchars($period['complaint_penalty_factor']); ?></div>
        <div class="col-6 col-md-3"><strong>Target progression</strong><br><?php echo htmlspecialchars($period['target_progression_rate']); ?>%</div>
        <div class="col-6 col-md-3"><strong>Target placement</strong><br><?php echo htmlspecialchars($period['target_placement_rate']); ?>%</div>
        <div class="col-6 col-md-3"><strong>Modul dampak</strong><br><?php echo $period['impact_module_enabled'] ? 'Aktif' : 'Nonaktif; core dinormalisasi'; ?></div>
    </div>
    <p class="mt-3 mb-1"><strong>Atribut wajib:</strong> <?php echo htmlspecialchars(implode(', ', $mandatoryFields)); ?></p>
    <p><strong>Bobot:</strong> <?php echo htmlspecialchars(implode(', ', array_map(static fn($key, $value) => $key . ' ' . $value . '%', array_keys($weights), $weights))); ?></p>
</section>
<?php if ($winners): ?>
<section class="mb-5">
    <h3 class="h5">Pemenang Disahkan</h3>
    <div class="row g-3"><?php foreach ($winners as $winner): ?><div class="col-4"><div class="border rounded p-3 text-center"><div>Rank <?php echo intval($winner['award_rank']); ?></div><strong><?php echo htmlspecialchars($winner['partner_name']); ?></strong><div class="h3"><?php echo number_format((float)$winner['final_score'], 2); ?></div><small><?php echo htmlspecialchars($winner['approved_at']); ?></small></div></div><?php endforeach; ?></div>
</section>
<?php endif; ?>
<section class="mb-5">
    <h3 class="h5">Rekap Penilaian dan Ranking</h3>
    <div class="table-responsive"><table class="table table-sm table-bordered">
        <thead><tr><th>Rank</th><th>Partner</th><th>Eligibility</th><th>Integrasi</th><th>Volume</th><th>Konsistensi</th><th>Completeness</th><th>KYB</th><th>Duplikasi</th><th>Aduan</th><th>Progression</th><th>Placement</th><th>Nilai Akhir</th></tr></thead><tbody>
        <?php foreach ($participants as $row): ?><tr><td><?php echo $row['award_rank'] ? intval($row['award_rank']) : '-'; ?></td><td><?php echo htmlspecialchars($row['partner_name']); ?><br><small><?php echo htmlspecialchars($row['partner_id']); ?></small></td><td><?php echo htmlspecialchars($row['eligibility_status']); ?></td>
            <?php foreach (['score_integration','score_volume','score_consistency','score_completeness','score_kyb','score_duplicate','score_complaint','score_progression','score_placement'] as $field): ?><td><?php echo number_format((float)$row[$field], 2); ?></td><?php endforeach; ?>
            <td><strong><?php echo number_format((float)$row['final_score'], 2); ?></strong></td></tr><?php endforeach; ?>
        </tbody>
    </table></div>
</section>
<section>
    <h3 class="h5">Keputusan Red Flag</h3>
    <table class="table table-sm table-bordered"><thead><tr><th>Partner</th><th>Kode</th><th>Temuan / Bukti</th><th>Status</th><th>Konsekuensi</th><th>Catatan Komite</th></tr></thead><tbody>
        <?php foreach ($flags as $flag): ?><tr><td><?php echo htmlspecialchars($flag['partner_name']); ?></td><td><?php echo htmlspecialchars($flag['code']); ?></td><td><?php echo nl2br(htmlspecialchars($flag['description'])); ?><br><small><?php echo htmlspecialchars($flag['evidence_reference']); ?></small></td><td><?php echo htmlspecialchars($flag['status']); ?></td><td><?php echo htmlspecialchars($flag['consequence']); ?></td><td><?php echo nl2br(htmlspecialchars($flag['committee_notes'] ?? '')); ?></td></tr><?php endforeach; ?>
        <?php if (!$flags): ?><tr><td colspan="6" class="text-center">Tidak ada red flag.</td></tr><?php endif; ?>
    </tbody></table>
</section>
<?php jpa_render_footer(); ?>

