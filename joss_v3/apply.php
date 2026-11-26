<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

$profileValues = [
    'email' => 'pelamar.contoh@example.org',
    'phone' => '0812••••••78',
    'profile_cv' => 'Rani Pratama · Jakarta · 4 tahun pengalaman operasional',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    jv3_verify_csrf();
    $vacancyId = intval($_POST['vacancy_id'] ?? 0);
    $schemaId = intval($_POST['schema_id'] ?? 0);
    $stmt = $conn->prepare("SELECT s.*,v.source_client_id,v.title,c.allow_dynamic_apply,c.status client_status
        FROM joss_v3_proto_schemas s
        JOIN joss_v3_proto_vacancies v ON v.id=?
        JOIN joss_v3_proto_clients c ON c.client_id=s.client_id
        WHERE s.id=? AND s.client_id=v.source_client_id LIMIT 1");
    $stmt->bind_param('ii', $vacancyId, $schemaId);
    $stmt->execute();
    $schema = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$schema || $schema['status'] !== 'published') {
        jv3_flash('danger', '409 APPLY_SCHEMA_CHANGED — konfigurasi telah berubah. Form dimuat ulang dan memerlukan persetujuan baru.');
        jv3_redirect('apply?vacancy=' . $vacancyId);
    }
    if (!$schema['allow_dynamic_apply'] || $schema['client_status'] !== 'active') {
        jv3_flash('danger', '403 APPLY_CLIENT_FORBIDDEN — klien tujuan tidak aktif atau izinnya dicabut.');
        jv3_redirect('apply?vacancy=' . $vacancyId);
    }
    $fields = jv3_schema_fields($conn, $schemaId);
    $values = [];
    $consents = [];
    $errors = [];
    foreach ($fields as $field) {
        $key = $field['variable_key'];
        $consented = isset($_POST['consent'][$key]);
        $consents[$key] = $consented;
        $value = $field['source_type'] === 'PROFILE'
            ? (string)($profileValues[$key] ?? '')
            : trim((string)($_POST['value'][$key] ?? ''));
        if ((int)$field['is_required'] === 1 && (!$consented || $value === '')) {
            $errors[] = $field['label'] . ' wajib lengkap dan disetujui.';
            continue;
        }
        if (!$consented || $value === '') {
            continue;
        }
        $error = jv3_validate_typed_value($field['data_type'], $value, $field['options_json']);
        if ($error) {
            $errors[] = $field['label'] . ': ' . $error;
            continue;
        }
        $values[$key] = $value;
    }
    if ($errors) {
        jv3_flash('danger', '422 APPLY_REQUIRED_MISSING / APPLY_FIELD_INVALID — ' . implode(' ', $errors));
        jv3_redirect('apply?vacancy=' . $vacancyId);
    }
    $code = 'APP-' . date('ymd-His') . '-' . random_int(100,999);
    $valuesJson = jv3_json($values);
    $consentJson = jv3_json($consents);
    $applicant = 'Rani Pratama';
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO joss_v3_proto_applications
            (application_code,vacancy_id,client_id,schema_id,status,applicant_name,values_json,consent_json,recipient_snapshot,purpose_snapshot,consent_at)
            VALUES (?,?,?,?,'submitted',?,?,?,?,?,NOW())");
        $stmt->bind_param('sisisssss', $code, $vacancyId, $schema['client_id'], $schemaId, $applicant, $valuesJson, $consentJson, $schema['recipient_label'], $schema['purpose']);
        $stmt->execute();
        $applicationId = (int)$conn->insert_id;
        $stmt->close();
        $application = jv3_one($conn, "SELECT * FROM joss_v3_proto_applications WHERE id=" . $applicationId);
        $payload = jv3_json(jv3_build_delivery_payload($conn, $application));
        $eventId = 'EVT-' . $code;
        $delivery = $conn->prepare("INSERT INTO joss_v3_proto_deliveries (application_id,event_id,status,payload_json) VALUES (?,?,'pending',?)");
        $delivery->bind_param('iss', $applicationId, $eventId, $payload);
        $delivery->execute();
        $delivery->close();
        jv3_audit($conn, 'application.submitted', 'application', $applicationId, ['application_code'=>$code,'schema_id'=>$schemaId,'recipient'=>$schema['recipient_label'],'fields'=>array_keys($values)]);
        $conn->commit();
        jv3_flash('success', 'Lamaran ' . $code . ' tersimpan dengan snapshot persetujuan. Event webhook dibuat.');
        jv3_redirect('delivery?application=' . $applicationId);
    } catch (Throwable $e) {
        $conn->rollback();
        jv3_flash('danger', $e->getMessage());
        jv3_redirect('apply?vacancy=' . $vacancyId);
    }
}

$vacancies = jv3_all($conn, "SELECT v.*,c.allow_dynamic_apply FROM joss_v3_proto_vacancies v JOIN joss_v3_proto_clients c ON c.client_id=v.source_client_id ORDER BY v.updated_at DESC");
$selectedVacancyId = intval($_GET['vacancy'] ?? ($vacancies[0]['id'] ?? 0));
$vacancy = null;
foreach ($vacancies as $row) if ((int)$row['id'] === $selectedVacancyId) $vacancy = $row;
$schema = null;
$fields = [];
if ($vacancy) {
    $stmt = $conn->prepare("SELECT * FROM joss_v3_proto_schemas WHERE client_id=? AND status='published' ORDER BY version_no DESC LIMIT 1");
    $stmt->bind_param('s', $vacancy['source_client_id']);
    $stmt->execute();
    $schema = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($schema) $fields = jv3_schema_fields($conn, (int)$schema['id']);
}

jv3_render_header('Apply Simulation', 'apply');
?>
<div class="alert alert-info"><i class="bi bi-info-circle me-1"></i>API 1 DCF tidak otomatis menjadi pertanyaan pelamar. Form ini hanya memuat variabel dari skema API 2 yang dipublikasikan Admin JOSS.</div>
<div class="row g-3">
    <div class="col-lg-4"><section class="jv3-card"><div class="jv3-card-header"><h2 class="h6 mb-0">Pilih lowongan</h2></div><div class="list-group list-group-flush"><?php foreach ($vacancies as $row): ?><a href="?vacancy=<?php echo (int)$row['id']; ?>" class="list-group-item list-group-item-action <?php echo (int)$row['id']===$selectedVacancyId?'active':''; ?>"><strong><?php echo jv3_h($row['title']); ?></strong><div class="small <?php echo (int)$row['id']===$selectedVacancyId?'text-white-50':'text-muted'; ?>"><?php echo jv3_h($row['company_name'].' · '.$row['source_client_id']); ?></div></a><?php endforeach; ?></div></section></div>
    <div class="col-lg-8"><?php if ($vacancy && $schema): ?><section class="jv3-card">
        <div class="jv3-card-header"><div class="jv3-eyebrow text-success">Konfirmasi Melamar Lowongan</div><h2 class="h4 mb-1"><?php echo jv3_h($vacancy['title']); ?></h2><div class="jv3-muted"><?php echo jv3_h($vacancy['company_name']); ?> · schema v<?php echo (int)$schema['version_no']; ?></div></div>
        <form method="post" class="jv3-card-body">
            <input type="hidden" name="csrf_token" value="<?php echo jv3_h(jv3_csrf_token()); ?>"><input type="hidden" name="vacancy_id" value="<?php echo (int)$vacancy['id']; ?>"><input type="hidden" name="schema_id" value="<?php echo (int)$schema['id']; ?>">
            <div class="alert alert-light border"><strong>Data dibagikan kepada:</strong> <?php echo jv3_h($schema['recipient_label']); ?><br><span class="jv3-muted"><strong>Tujuan:</strong> <?php echo jv3_h($schema['purpose']); ?></span></div>
            <?php foreach ($fields as $field): $key=$field['variable_key']; ?><div class="border rounded-3 p-3 mb-2">
                <div class="d-flex justify-content-between align-items-start gap-3"><div><label class="form-check fw-semibold"><input class="form-check-input" type="checkbox" name="consent[<?php echo jv3_h($key); ?>]" <?php echo $field['is_required']?'checked':''; ?>> <span class="form-check-label"><?php echo jv3_h($field['label']); ?><?php echo $field['is_required']?' *':''; ?></span></label><div class="small text-muted ms-4"><?php echo jv3_h($field['source_type'].' · '.$field['data_type'].' · '.$field['purpose']); ?></div></div><span class="badge text-bg-light"><?php echo $field['is_required']?'Wajib':'Opsional'; ?></span></div>
                <div class="mt-2 ms-md-4"><?php if ($field['source_type']==='PROFILE'): ?><div class="form-control bg-light"><?php echo jv3_h($profileValues[$key] ?? 'Data profil belum tersedia'); ?></div><div class="small text-muted mt-1"><i class="bi bi-person-badge me-1"></i>Diambil server-side dari profil resmi.</div>
                    <?php elseif ($field['data_type']==='boolean'): ?><select class="form-select" name="value[<?php echo jv3_h($key); ?>]"><option value="">Pilih</option><option value="true">Ya</option><option value="false">Tidak</option></select>
                    <?php elseif ($field['data_type']==='enum'): ?><select class="form-select" name="value[<?php echo jv3_h($key); ?>]"><option value="">Pilih</option><?php foreach (json_decode($field['options_json'],true)?:[] as $option): ?><option><?php echo jv3_h($option); ?></option><?php endforeach; ?></select>
                    <?php else: ?><input class="form-control" name="value[<?php echo jv3_h($key); ?>]" type="<?php echo $field['data_type']==='date'?'date':'text'; ?>" placeholder="Masukkan <?php echo jv3_h(strtolower($field['label'])); ?>"><?php endif; ?></div>
            </div><?php endforeach; ?>
            <div class="d-flex justify-content-end gap-2 mt-3"><button type="button" class="btn btn-outline-secondary">Batal</button><button class="btn btn-primary"><i class="bi bi-send me-1"></i>Lamar Lowongan</button></div>
        </form>
    </section><?php else: ?><div class="jv3-card jv3-card-body"><h2 class="h5">503 APPLY_SCHEMA_UNAVAILABLE</h2><p class="mb-0 text-muted">Klien tujuan belum memiliki skema API 2 yang aktif. Pengajuan dihentikan tanpa fallback ke payload lama.</p></div><?php endif; ?></div>
</div>
<?php jv3_render_footer(); ?>
