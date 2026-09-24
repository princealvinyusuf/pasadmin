<?php
require_once __DIR__ . '/bootstrap.php';
jpa_require_any(['job_portal_award_review_eligibility']);

$period = jpa_selected_period($conn);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        if (!$period) {
            throw new RuntimeException('Periode tidak ditemukan.');
        }
        if ($period['status'] === 'finalized') {
            throw new RuntimeException('Periode sudah difinalisasi.');
        }
        $before = ['requested_by' => intval($_SESSION['user_id'] ?? 0)];
        $ranked = jpa_recalculate_period($conn, $periodId, false);
        jpa_audit($conn, $periodId, 'eligibility.recalculated', 'period', $periodId, $before, ['ranked_count' => count($ranked)], $reason);
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
    jpa_set_flash('success', 'Eligibility seluruh peserta dihitung ulang dari E1 sampai E4.');
    jpa_redirect('eligibility?period_id=' . $periodId);
}

$rows = [];
if ($period) {
    $status = (string)($_GET['status'] ?? '');
    $sql = 'SELECT * FROM job_portal_award_participants WHERE period_id=?';
    if (in_array($status, ['eligible','ineligible','pending'], true)) {
        $sql .= " AND eligibility_status='" . $status . "'";
    }
    $sql .= ' ORDER BY eligibility_status,partner_name';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $period['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
}

jpa_render_header('Eligibility Review', $period);
?>
<?php jpa_render_page_header('Review Kelayakan', 'Gate objektif E1 sampai E4 harus dipenuhi sebelum peserta masuk peringkat.', $conn, $period, 'eligibility'); ?>
<?php if (!$period): ?>
    <?php jpa_render_no_period(); ?>
<?php else: ?>
    <?php jpa_render_period_banner($period); ?>
    <div class="d-flex flex-wrap gap-2 mb-3 no-print jpa-toolbar">
        <div class="btn-group" role="group" aria-label="Filter status kelayakan">
            <a class="btn btn-sm <?php echo $status === '' ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="eligibility?period_id=<?php echo intval($period['id']); ?>">Semua</a>
            <?php foreach (['eligible','ineligible','pending'] as $filterStatus): ?><a class="btn btn-sm <?php echo $status === $filterStatus ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="eligibility?period_id=<?php echo intval($period['id']); ?>&status=<?php echo $filterStatus; ?>"><?php echo htmlspecialchars(jpa_status_label('eligibility', $filterStatus)); ?></a><?php endforeach; ?>
        </div>
        <?php if ($period['status'] !== 'finalized'): ?>
        <form method="post" class="d-flex gap-2 ms-auto" onsubmit="return confirm('Hitung ulang eligibility dan ranking?');">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(jpa_csrf_token()); ?>">
            <input type="hidden" name="period_id" value="<?php echo intval($period['id']); ?>">
            <input class="form-control form-control-sm" name="reason" required placeholder="Alasan kalkulasi ulang">
            <button class="btn btn-sm btn-primary">Hitung Ulang</button>
        </form>
        <?php endif; ?>
    </div>
    <div class="card"><div class="table-responsive"><table class="table table-striped align-middle mb-0 jpa-table">
        <thead><tr>
            <th>Partner</th>
            <th><?php echo JPA_ELIGIBILITY_GATES['partnership_active']; ?> Mitra Aktif</th>
            <th><?php echo JPA_ELIGIBILITY_GATES['active_months']; ?> Bulan Aktif</th>
            <th><?php echo JPA_ELIGIBILITY_GATES['critical_violation_resolved']; ?> Pelanggaran</th>
            <th><?php echo JPA_ELIGIBILITY_GATES['data_traceable']; ?> Data Terlacak</th>
            <th>Status / Alasan</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td class="jpa-sticky-column"><?php jpa_render_partner_cell($row); ?></td>
                <td><?php echo $row['partnership_active'] ? '<span class="text-success"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Lolos</span>' : '<span class="text-danger"><i class="bi bi-x-circle-fill" aria-hidden="true"></i> Gagal</span>'; ?></td>
                <td><?php echo intval($row['active_months']); ?> / min <?php echo intval($period['min_active_months']); ?></td>
                <td><?php echo $row['critical_violation_resolved'] ? '<span class="text-success"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Selesai</span>' : '<span class="text-danger"><i class="bi bi-x-circle-fill" aria-hidden="true"></i> Belum</span>'; ?></td>
                <td><?php echo $row['data_traceable'] ? '<span class="text-success"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Ya</span>' : '<span class="text-danger"><i class="bi bi-x-circle-fill" aria-hidden="true"></i> Tidak</span>'; ?></td>
                <td><?php echo jpa_status_badge('eligibility', $row['eligibility_status']); ?><?php if ($row['eligibility_reasons']): ?><div class="small text-danger mt-1"><?php echo nl2br(htmlspecialchars($row['eligibility_reasons'])); ?></div><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><?php jpa_render_empty_row(6, 'Tidak ada peserta untuk filter ini.'); ?><?php endif; ?>
        </tbody>
    </table></div></div>
<?php endif; ?>
<?php jpa_render_footer(); ?>

