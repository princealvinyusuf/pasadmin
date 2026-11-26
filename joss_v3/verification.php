<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    jv3_verify_csrf();
    $vacancyId = intval($_POST['vacancy_id'] ?? 0);
    $decision = in_array($_POST['decision'] ?? '', ['approved','revision','rejected'], true) ? $_POST['decision'] : 'revision';
    $notes = trim((string)($_POST['notes'] ?? ''));
    if ($vacancyId < 1 || $notes === '') {
        jv3_flash('danger', 'Keputusan dan catatan verifikator wajib diisi.');
        jv3_redirect('verification');
    }
    $publication = $decision === 'approved' ? 'published' : ($decision === 'rejected' ? 'rejected' : 'draft');
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE joss_v3_proto_vacancies SET verification_status=?,publication_status=? WHERE id=?");
        $stmt->bind_param('ssi', $decision, $publication, $vacancyId);
        $stmt->execute();
        $stmt->close();
        $deleteRoutes = $conn->prepare("DELETE FROM joss_v3_proto_routes WHERE vacancy_id=?");
        $deleteRoutes->bind_param('i', $vacancyId);
        $deleteRoutes->execute();
        $deleteRoutes->close();
        if ($decision === 'approved') {
            $values = jv3_vacancy_values($conn, $vacancyId);
            $hasRequired = count(array_filter($values, fn($row) => (int)$row['is_required'] === 1)) > 0;
            $clients = jv3_all($conn, "SELECT * FROM joss_v3_proto_clients WHERE status='active'");
            $route = $conn->prepare("INSERT INTO joss_v3_proto_routes (vacancy_id,destination_client_id,route_status,reason) VALUES (?,?,?,?)");
            foreach ($clients as $client) {
                if ($client['allow_dcf_read']) {
                    $status = 'ready';
                    $reason = 'Tujuan mendukung dan berizin menerima DCF.';
                } elseif ($hasRequired) {
                    $status = 'held';
                    $reason = 'DCF wajib tidak dapat disampaikan; distribusi ditahan.';
                } else {
                    $status = 'standard_only';
                    $reason = 'Kontrak lama menerima parameter standar saja.';
                }
                $route->bind_param('isss', $vacancyId, $client['client_id'], $status, $reason);
                $route->execute();
            }
            $route->close();
        }
        jv3_audit($conn, 'vacancy.verification.' . $decision, 'vacancy', $vacancyId, ['notes'=>$notes,'publication'=>$publication]);
        $conn->commit();
        jv3_flash('success', 'Keputusan verifikasi tersimpan dan matriks routing dihitung ulang.');
    } catch (Throwable $e) {
        $conn->rollback();
        jv3_flash('danger', $e->getMessage());
    }
    jv3_redirect('verification?vacancy=' . $vacancyId);
}

$vacancies = jv3_all($conn, "SELECT * FROM joss_v3_proto_vacancies ORDER BY FIELD(verification_status,'pending','revision','approved','rejected'),updated_at DESC");
$selectedId = intval($_GET['vacancy'] ?? ($vacancies[0]['id'] ?? 0));
$selected = null;
foreach ($vacancies as $vacancy) if ((int)$vacancy['id'] === $selectedId) $selected = $vacancy;
$values = $selected ? jv3_vacancy_values($conn, $selectedId) : [];
$routes = $selected ? jv3_all($conn, "SELECT * FROM joss_v3_proto_routes WHERE vacancy_id=" . $selectedId . " ORDER BY destination_client_id") : [];

jv3_render_header('Verification & Routing', 'verification');
?>
<div class="row g-3">
    <div class="col-lg-4"><section class="jv3-card">
        <div class="jv3-card-header"><h2 class="h6 mb-0">Antrean verifikasi</h2></div>
        <div class="list-group list-group-flush"><?php foreach ($vacancies as $vacancy): ?><a class="list-group-item list-group-item-action <?php echo (int)$vacancy['id']===$selectedId?'active':''; ?>" href="?vacancy=<?php echo (int)$vacancy['id']; ?>">
            <div class="d-flex justify-content-between gap-2"><strong><?php echo jv3_h($vacancy['title']); ?></strong><?php echo jv3_status_badge($vacancy['verification_status']); ?></div>
            <div class="small <?php echo (int)$vacancy['id']===$selectedId?'text-white-50':'text-muted'; ?>"><?php echo jv3_h($vacancy['company_name']); ?> · rev <?php echo (int)$vacancy['revision_no']; ?></div>
        </a><?php endforeach; ?></div>
    </section></div>
    <div class="col-lg-8"><?php if ($selected): ?>
        <section class="jv3-card mb-3">
            <div class="jv3-card-header d-flex justify-content-between align-items-start"><div><h2 class="h5 mb-1"><?php echo jv3_h($selected['title']); ?></h2><div class="jv3-muted"><?php echo jv3_h($selected['vacancy_code'].' · '.$selected['company_name'].' · sumber '.$selected['source_client_id']); ?></div></div><?php echo jv3_status_badge($selected['publication_status']); ?></div>
            <div class="jv3-card-body">
                <h3 class="h6">Persyaratan tambahan</h3>
                <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Field</th><th>Nilai</th><th>Scope / versi</th><th>Review</th></tr></thead><tbody>
                    <?php foreach ($values as $value): ?><tr><td><strong><?php echo jv3_h($value['label']); ?></strong><div class="small text-muted"><?php echo jv3_h($value['field_key'].' · '.$value['data_type']); ?></div></td><td><?php echo jv3_h($value['comparison_operator'].' '.$value['value_text'].' '.$value['unit']); ?></td><td><?php echo jv3_h($value['scope']); ?></td><td><?php echo $value['is_required']?'<span class="badge text-bg-warning">Wajib</span>':'<span class="badge text-bg-light">Opsional</span>'; ?></td></tr><?php endforeach; ?>
                    <?php if (!$values): ?><tr><td colspan="4" class="text-muted">Lowongan tidak memiliki DCF pada revisi aktif.</td></tr><?php endif; ?>
                </tbody></table></div>
                <form method="post" class="border-top pt-3"><input type="hidden" name="csrf_token" value="<?php echo jv3_h(jv3_csrf_token()); ?>"><input type="hidden" name="vacancy_id" value="<?php echo (int)$selected['id']; ?>">
                    <div class="row g-2"><div class="col-md-4"><label class="form-label">Keputusan</label><select class="form-select" name="decision"><option value="approved">Setujui & publikasikan</option><option value="revision">Minta perbaikan</option><option value="rejected">Tolak</option></select></div><div class="col-md-8"><label class="form-label">Catatan *</label><input class="form-control" name="notes" required placeholder="Relevansi, dasar keputusan, atau koreksi yang diminta"></div></div>
                    <button class="btn btn-primary mt-3"><i class="bi bi-patch-check me-1"></i>Simpan keputusan</button>
                </form>
            </div>
        </section>
        <section class="jv3-card"><div class="jv3-card-header"><h2 class="h6 mb-1">Routing capability result</h2><div class="jv3-muted">Tujuan ditentukan oleh identitas dan izin, bukan nilai field bebas.</div></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Tujuan</th><th>Hasil</th><th>Alasan</th></tr></thead><tbody>
            <?php foreach ($routes as $route): ?><tr><td><strong><?php echo jv3_h($route['destination_client_id']); ?></strong></td><td><?php echo jv3_status_badge($route['route_status']); ?></td><td><?php echo jv3_h($route['reason']); ?></td></tr><?php endforeach; ?>
            <?php if (!$routes): ?><tr><td colspan="3" class="text-muted">Routing dibentuk setelah lowongan disetujui.</td></tr><?php endif; ?>
        </tbody></table></div></section>
    <?php endif; ?></div>
</div>
<?php jv3_render_footer(); ?>
