<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    jv3_verify_csrf();
    $id = intval($_POST['client_pk'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? ''));
    $maxFields = max(0, min(50, intval($_POST['max_fields'] ?? 10)));
    $types = array_values(array_intersect(
        ['string','character','integer','decimal','boolean','date','enum','url'],
        (array)($_POST['allowed_types'] ?? [])
    ));
    if ($id < 1 || $reason === '' || mb_strlen($reason) > 500 || !$types) {
        jv3_flash('danger', 'Alasan perubahan dan minimal satu tipe yang diizinkan wajib diisi.');
        jv3_redirect('clients');
    }
    $status = ($_POST['status'] ?? '') === 'active' ? 'active' : 'inactive';
    $write = isset($_POST['allow_dcf_write']) ? 1 : 0;
    $read = isset($_POST['allow_dcf_read']) ? 1 : 0;
    $apply = isset($_POST['allow_dynamic_apply']) ? 1 : 0;
    $typesJson = json_encode($types);
    $userId = intval($_SESSION['user_id'] ?? 0);
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE joss_v3_proto_clients SET status=?,allow_dcf_write=?,allow_dcf_read=?,allow_dynamic_apply=?,max_fields=?,allowed_types_json=?,change_reason=?,updated_by=? WHERE id=?");
        $stmt->bind_param('siiiissii', $status, $write, $read, $apply, $maxFields, $typesJson, $reason, $userId, $id);
        $stmt->execute();
        $stmt->close();
        if (!$apply || $status !== 'active') {
            $stmt = $conn->prepare("UPDATE joss_v3_proto_deliveries d
                JOIN joss_v3_proto_applications a ON a.id=d.application_id
                JOIN joss_v3_proto_clients c ON c.client_id=a.client_id
                SET d.status='held',d.last_error_code='APPLY_CLIENT_FORBIDDEN',a.status='held'
                WHERE c.id=? AND d.status IN ('pending','failed')");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
        jv3_audit($conn, 'client.permissions.updated', 'client', $id, compact('status','write','read','apply','maxFields','types','reason'));
        $conn->commit();
        jv3_flash('success', 'Kewenangan klien berhasil diperbarui dan event yang tidak lagi sah telah ditahan.');
    } catch (Throwable $e) {
        $conn->rollback();
        jv3_flash('danger', $e->getMessage());
    }
    jv3_redirect('clients?client=' . $id);
}

$clients = jv3_all($conn, "SELECT * FROM joss_v3_proto_clients ORDER BY client_name");
$selectedId = intval($_GET['client'] ?? ($clients[0]['id'] ?? 0));
$selected = null;
foreach ($clients as $client) {
    if ((int)$client['id'] === $selectedId) $selected = $client;
}
$selected ??= $clients[0] ?? null;
$allTypes = ['string','character','integer','decimal','boolean','date','enum','url'];

jv3_render_header('Client Permissions', 'clients');
?>
<div class="alert alert-info"><i class="bi bi-shield-check me-1"></i>Tiga kewenangan berdiri sendiri. Izin API 1 tidak otomatis mengizinkan permintaan atau penerimaan data pribadi API 2.</div>
<div class="row g-3">
    <div class="col-lg-5"><section class="jv3-card">
        <div class="jv3-card-header"><h2 class="h6 mb-1">Klien integrasi</h2><div class="jv3-muted">Pilih klien untuk melihat kebijakan efektif.</div></div>
        <div class="table-responsive"><table class="table mb-0 align-middle">
            <thead><tr><th>Client</th><th>Capability</th><th></th></tr></thead>
            <tbody><?php foreach ($clients as $client): ?><tr>
                <td><strong><?php echo jv3_h($client['client_name']); ?></strong><div class="small text-muted"><?php echo jv3_h($client['client_id']); ?></div></td>
                <td><span title="Write / Read / Apply"><?php echo $client['allow_dcf_write'] ? 'W' : '—'; ?> · <?php echo $client['allow_dcf_read'] ? 'R' : '—'; ?> · <?php echo $client['allow_dynamic_apply'] ? 'A' : '—'; ?></span></td>
                <td><a class="btn btn-sm btn-outline-primary" href="?client=<?php echo (int)$client['id']; ?>">Atur</a></td>
            </tr><?php endforeach; ?></tbody>
        </table></div>
    </section></div>
    <div class="col-lg-7"><?php if ($selected): ?><section class="jv3-card">
        <div class="jv3-card-header d-flex justify-content-between"><div><h2 class="h6 mb-1"><?php echo jv3_h($selected['client_name']); ?></h2><div class="jv3-muted"><?php echo jv3_h($selected['client_id']); ?></div></div><?php echo jv3_status_badge($selected['status']); ?></div>
        <form method="post" class="jv3-card-body">
            <input type="hidden" name="csrf_token" value="<?php echo jv3_h(jv3_csrf_token()); ?>"><input type="hidden" name="client_pk" value="<?php echo (int)$selected['id']; ?>">
            <div class="row g-3">
                <div class="col-md-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="allow_dcf_write" id="write" <?php echo $selected['allow_dcf_write'] ? 'checked' : ''; ?>><label class="form-check-label fw-semibold" for="write">Kirim DCF</label><div class="small text-muted">Definisi inline & nilai API 1</div></div></div>
                <div class="col-md-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="allow_dcf_read" id="read" <?php echo $selected['allow_dcf_read'] ? 'checked' : ''; ?>><label class="form-check-label fw-semibold" for="read">Terima DCF</label><div class="small text-muted">GET dan distribusi advanced</div></div></div>
                <div class="col-md-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="allow_dynamic_apply" id="apply" <?php echo $selected['allow_dynamic_apply'] ? 'checked' : ''; ?>><label class="form-check-label fw-semibold" for="apply">API 2 Dinamis</label><div class="small text-muted">Skema & webhook tambahan</div></div></div>
                <div class="col-md-6"><label class="form-label">Status integrasi</label><select name="status" class="form-select"><option value="active" <?php echo $selected['status']==='active'?'selected':''; ?>>Aktif</option><option value="inactive" <?php echo $selected['status']==='inactive'?'selected':''; ?>>Nonaktif</option></select></div>
                <div class="col-md-6"><label class="form-label">Batas field per lowongan</label><input class="form-control" type="number" min="0" max="50" name="max_fields" value="<?php echo (int)$selected['max_fields']; ?>"></div>
                <div class="col-12"><label class="form-label">Tipe logis yang diizinkan</label><div class="d-flex flex-wrap gap-3">
                    <?php $allowed = json_decode($selected['allowed_types_json'], true) ?: []; foreach ($allTypes as $type): ?><label class="form-check"><input class="form-check-input" type="checkbox" name="allowed_types[]" value="<?php echo $type; ?>" <?php echo in_array($type,$allowed,true)?'checked':''; ?>> <span class="form-check-label"><?php echo $type; ?></span></label><?php endforeach; ?>
                </div></div>
                <div class="col-12"><label class="form-label">Alasan perubahan *</label><textarea class="form-control" name="reason" rows="2" maxlength="500" required placeholder="Dasar onboarding, perubahan capability, atau pencabutan izin"></textarea></div>
            </div>
            <button class="btn btn-primary mt-3"><i class="bi bi-save me-1"></i>Simpan kebijakan</button>
        </form>
    </section><?php endif; ?></div>
</div>
<?php jv3_render_footer(); ?>
