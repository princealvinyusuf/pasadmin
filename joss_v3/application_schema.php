<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    jv3_verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'add_variable') {
        $key = strtolower(trim((string)($_POST['variable_key'] ?? '')));
        $label = trim((string)($_POST['label'] ?? ''));
        $sourceType = ($_POST['source_type'] ?? '') === 'PROFILE' ? 'PROFILE' : 'INPUT';
        $sourceKey = $sourceType === 'PROFILE' ? trim((string)($_POST['source_key'] ?? '')) : null;
        $type = (string)($_POST['data_type'] ?? 'string');
        $options = array_values(array_filter(array_map('trim', explode(',', (string)($_POST['options'] ?? '')))));
        $optionsJson = $options ? json_encode($options) : null;
        if (!jv3_valid_key($key) || $label === '' || ($sourceType === 'PROFILE' && !$sourceKey)
            || ($type === 'enum' && !$options)) {
            jv3_flash('danger', 'Variabel katalog tidak valid. PROFILE memerlukan source key resmi; enum memerlukan opsi.');
            jv3_redirect('application_schema');
        }
        try {
            $stmt = $conn->prepare("INSERT INTO joss_v3_proto_variables (variable_key,label,source_type,source_key,data_type,options_json) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param('ssssss', $key, $label, $sourceType, $sourceKey, $type, $optionsJson);
            $stmt->execute();
            $id = (int)$conn->insert_id;
            $stmt->close();
            jv3_audit($conn, 'application_variable.created', 'variable', $id, compact('key','label','sourceType','sourceKey','type'));
            jv3_flash('success', 'Variabel ditambahkan ke katalog terkontrol.');
        } catch (Throwable $e) {
            jv3_flash('danger', 'Variable key sudah digunakan.');
        }
        jv3_redirect('application_schema');
    }
    if ($action === 'save_schema') {
        $clientId = trim((string)($_POST['client_id'] ?? ''));
        $recipient = trim((string)($_POST['recipient_label'] ?? ''));
        $purpose = trim((string)($_POST['purpose'] ?? ''));
        $variableIds = array_values(array_unique(array_map('intval', (array)($_POST['variables'] ?? []))));
        if ($clientId === '' || $recipient === '' || $purpose === '' || !$variableIds) {
            jv3_flash('danger', 'Klien, penerima, tujuan, dan minimal satu variabel wajib dipilih.');
            jv3_redirect('application_schema');
        }
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT COALESCE(MAX(version_no),0)+1 n FROM joss_v3_proto_schemas WHERE client_id=? FOR UPDATE");
            $stmt->bind_param('s', $clientId);
            $stmt->execute();
            $version = (int)$stmt->get_result()->fetch_assoc()['n'];
            $stmt->close();
            $stmt = $conn->prepare("INSERT INTO joss_v3_proto_schemas (client_id,version_no,status,recipient_label,purpose) VALUES (?,?,'draft',?,?)");
            $stmt->bind_param('siss', $clientId, $version, $recipient, $purpose);
            $stmt->execute();
            $schemaId = (int)$conn->insert_id;
            $stmt->close();
            $fieldStmt = $conn->prepare("INSERT INTO joss_v3_proto_schema_fields (schema_id,variable_id,is_required,display_order,purpose) VALUES (?,?,?,?,?)");
            foreach ($variableIds as $index => $variableId) {
                $required = isset($_POST['required'][$variableId]) ? 1 : 0;
                $order = max(1, intval($_POST['order'][$variableId] ?? ($index + 1)));
                $fieldPurpose = trim((string)($_POST['field_purpose'][$variableId] ?? '')) ?: $purpose;
                $fieldStmt->bind_param('iiiis', $schemaId, $variableId, $required, $order, $fieldPurpose);
                $fieldStmt->execute();
            }
            $fieldStmt->close();
            jv3_audit($conn, 'application_schema.draft.created', 'schema', $schemaId, ['client_id'=>$clientId,'version'=>$version,'field_count'=>count($variableIds)]);
            $conn->commit();
            jv3_flash('success', 'Draft skema v' . $version . ' tersimpan.');
            jv3_redirect('application_schema?schema=' . $schemaId);
        } catch (Throwable $e) {
            $conn->rollback();
            jv3_flash('danger', $e->getMessage());
            jv3_redirect('application_schema');
        }
    }
    if ($action === 'publish_schema') {
        $schemaId = intval($_POST['schema_id'] ?? 0);
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("SELECT s.*,c.allow_dynamic_apply,c.status client_status FROM joss_v3_proto_schemas s JOIN joss_v3_proto_clients c ON c.client_id=s.client_id WHERE s.id=? FOR UPDATE");
            $stmt->bind_param('i', $schemaId);
            $stmt->execute();
            $schema = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$schema || $schema['status'] !== 'draft') throw new RuntimeException('Hanya draft yang dapat dipublikasikan.');
            if (!$schema['allow_dynamic_apply'] || $schema['client_status'] !== 'active') throw new RuntimeException('APPLY_CLIENT_FORBIDDEN — izin API 2 klien belum aktif.');
            $retire = $conn->prepare("UPDATE joss_v3_proto_schemas SET status='retired' WHERE client_id=? AND status='published'");
            $retire->bind_param('s', $schema['client_id']);
            $retire->execute();
            $retire->close();
            $userId = intval($_SESSION['user_id'] ?? 0);
            $publish = $conn->prepare("UPDATE joss_v3_proto_schemas SET status='published',published_by=?,published_at=NOW() WHERE id=?");
            $publish->bind_param('ii', $userId, $schemaId);
            $publish->execute();
            $publish->close();
            jv3_audit($conn, 'application_schema.published', 'schema', $schemaId, ['client_id'=>$schema['client_id'],'version'=>$schema['version_no']]);
            $conn->commit();
            jv3_flash('success', 'Skema dipublikasikan atomik; versi aktif sebelumnya dipensiunkan.');
        } catch (Throwable $e) {
            $conn->rollback();
            jv3_flash('danger', $e->getMessage());
        }
        jv3_redirect('application_schema?schema=' . $schemaId);
    }
}

$clients = jv3_all($conn, "SELECT * FROM joss_v3_proto_clients ORDER BY client_name");
$variables = jv3_all($conn, "SELECT * FROM joss_v3_proto_variables WHERE status='active' ORDER BY source_type,label");
$schemas = jv3_all($conn, "SELECT s.*,c.client_name,(SELECT COUNT(*) FROM joss_v3_proto_schema_fields sf WHERE sf.schema_id=s.id) field_count FROM joss_v3_proto_schemas s JOIN joss_v3_proto_clients c ON c.client_id=s.client_id ORDER BY s.created_at DESC");
$selectedSchemaId = intval($_GET['schema'] ?? ($schemas[0]['id'] ?? 0));
$selectedSchema = null;
foreach ($schemas as $schema) if ((int)$schema['id'] === $selectedSchemaId) $selectedSchema = $schema;
$selectedFields = $selectedSchema ? jv3_schema_fields($conn, $selectedSchemaId) : [];
$preview = $selectedSchema ? [
    'schema_version'=>(int)$selectedSchema['version_no'],
    'recipient_client_id'=>$selectedSchema['client_id'],
    'recipient_label'=>$selectedSchema['recipient_label'],
    'fields'=>array_map(fn($f)=>['key'=>$f['variable_key'],'label'=>$f['label'],'source'=>$f['source_type'],'type'=>$f['data_type'],'required'=>(bool)$f['is_required'],'order'=>(int)$f['display_order']],$selectedFields),
] : [];

jv3_render_header('Application Schema', 'application_schema');
?>
<div class="row g-3 mb-3">
    <div class="col-xl-8"><section class="jv3-card"><div class="jv3-card-header d-flex justify-content-between"><div><h2 class="h6 mb-1">Versi skema per Client-Id</h2><div class="jv3-muted">Satu versi published aktif per klien; versi yang pernah digunakan tidak dimutasi.</div></div><button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#schemaModal"><i class="bi bi-plus-lg"></i> Buat draft</button></div><div class="table-responsive"><table class="table mb-0 align-middle">
        <thead><tr><th>Klien</th><th>Versi</th><th>Field</th><th>Status</th><th>Publikasi</th><th></th></tr></thead><tbody><?php foreach ($schemas as $schema): ?><tr><td><strong><?php echo jv3_h($schema['client_name']); ?></strong><div class="small text-muted"><?php echo jv3_h($schema['recipient_label']); ?></div></td><td>v<?php echo (int)$schema['version_no']; ?></td><td><?php echo (int)$schema['field_count']; ?></td><td><?php echo jv3_status_badge($schema['status']); ?></td><td><?php echo jv3_h($schema['published_at'] ?: '—'); ?></td><td><a class="btn btn-sm btn-outline-primary" href="?schema=<?php echo (int)$schema['id']; ?>">Preview</a></td></tr><?php endforeach; ?></tbody>
    </table></div></section></div>
    <div class="col-xl-4"><section class="jv3-card"><div class="jv3-card-header d-flex justify-content-between"><h2 class="h6 mb-0">Application schema response</h2><?php if ($selectedSchema) echo jv3_status_badge($selectedSchema['status']); ?></div><div class="jv3-card-body"><pre class="jv3-code"><?php echo jv3_h(jv3_json($preview)); ?></pre><?php if ($selectedSchema && $selectedSchema['status']==='draft'): ?><form method="post" class="mt-3"><input type="hidden" name="csrf_token" value="<?php echo jv3_h(jv3_csrf_token()); ?>"><input type="hidden" name="action" value="publish_schema"><input type="hidden" name="schema_id" value="<?php echo (int)$selectedSchema['id']; ?>"><button class="btn btn-primary w-100"><i class="bi bi-cloud-upload me-1"></i>Publikasikan v<?php echo (int)$selectedSchema['version_no']; ?></button></form><?php endif; ?></div></section></div>
</div>
<section class="jv3-card"><div class="jv3-card-header d-flex justify-content-between"><div><h2 class="h6 mb-1">Katalog variabel terkontrol</h2><div class="jv3-muted">PROFILE hanya memakai mapping backend yang disetujui; INPUT tidak mengubah profil SIAPKerja.</div></div><button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#variableModal"><i class="bi bi-plus-lg"></i> Tambah variabel</button></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Key / Label</th><th>Sumber</th><th>Mapping</th><th>Tipe</th><th>Status</th></tr></thead><tbody><?php foreach ($variables as $variable): ?><tr><td><strong><?php echo jv3_h($variable['variable_key']); ?></strong><div class="small text-muted"><?php echo jv3_h($variable['label']); ?></div></td><td><?php echo jv3_h($variable['source_type']); ?></td><td><?php echo jv3_h($variable['source_key'] ?: 'Input lamaran'); ?></td><td><?php echo jv3_h($variable['data_type']); ?></td><td><?php echo jv3_status_badge($variable['status']); ?></td></tr><?php endforeach; ?></tbody></table></div></section>

<div class="modal fade" id="schemaModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><form method="post" class="modal-content"><div class="modal-header"><h2 class="modal-title h5">Susun draft skema API 2</h2><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
    <input type="hidden" name="csrf_token" value="<?php echo jv3_h(jv3_csrf_token()); ?>"><input type="hidden" name="action" value="save_schema">
    <div class="row g-2 mb-3"><div class="col-md-4"><label class="form-label">Client-Id</label><select class="form-select" name="client_id"><?php foreach ($clients as $client): ?><option><?php echo jv3_h($client['client_id']); ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Penerima</label><input class="form-control" name="recipient_label" required placeholder="Portal dan perusahaan tujuan"></div><div class="col-md-4"><label class="form-label">Tujuan penggunaan</label><input class="form-control" name="purpose" required placeholder="Proses seleksi kandidat"></div></div>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Aktif</th><th>Variabel</th><th>Sumber / tipe</th><th>Wajib</th><th>Urutan</th><th>Tujuan field</th></tr></thead><tbody><?php foreach ($variables as $variable): $id=(int)$variable['id']; ?><tr><td><input class="form-check-input" type="checkbox" name="variables[]" value="<?php echo $id; ?>"></td><td><strong><?php echo jv3_h($variable['label']); ?></strong><div class="small text-muted"><?php echo jv3_h($variable['variable_key']); ?></div></td><td><?php echo jv3_h($variable['source_type'].' / '.$variable['data_type']); ?></td><td><input class="form-check-input" type="checkbox" name="required[<?php echo $id; ?>]"></td><td><input class="form-control form-control-sm" type="number" min="1" name="order[<?php echo $id; ?>]" value="<?php echo $id; ?>" style="width:80px"></td><td><input class="form-control form-control-sm" name="field_purpose[<?php echo $id; ?>]" placeholder="Default: tujuan skema"></td></tr><?php endforeach; ?></tbody></table></div>
</div><div class="modal-footer"><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan Draft</button></div></form></div></div>

<div class="modal fade" id="variableModal" tabindex="-1"><div class="modal-dialog"><form method="post" class="modal-content"><div class="modal-header"><h2 class="modal-title h5">Tambah variabel katalog</h2><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">
    <input type="hidden" name="csrf_token" value="<?php echo jv3_h(jv3_csrf_token()); ?>"><input type="hidden" name="action" value="add_variable">
    <div class="mb-2"><label class="form-label">Variable key</label><input class="form-control" name="variable_key" required></div><div class="mb-2"><label class="form-label">Label</label><input class="form-control" name="label" required></div>
    <div class="row g-2"><div class="col-6"><label class="form-label">Sumber</label><select class="form-select" name="source_type"><option>INPUT</option><option>PROFILE</option></select></div><div class="col-6"><label class="form-label">Tipe</label><select class="form-select" name="data_type"><?php foreach (['string','character','integer','decimal','boolean','date','enum','url'] as $type): ?><option><?php echo $type; ?></option><?php endforeach; ?></select></div></div>
    <div class="mt-2"><label class="form-label">Source key resmi (PROFILE)</label><input class="form-control" name="source_key" placeholder="job_seeker.property"></div><div class="mt-2"><label class="form-label">Opsi enum</label><input class="form-control" name="options" placeholder="Ya, Tidak"></div>
</div><div class="modal-footer"><button class="btn btn-primary">Tambah ke katalog</button></div></form></div></div>
<?php jv3_render_footer(); ?>
