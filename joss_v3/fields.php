<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    jv3_verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save_definition') {
        $definitionId = intval($_POST['definition_id'] ?? 0);
        $scope = in_array($_POST['scope'] ?? '', ['global','company','partner'], true) ? $_POST['scope'] : 'global';
        $owner = trim((string)($_POST['owner_company'] ?? '')) ?: null;
        $source = trim((string)($_POST['source_client_id'] ?? '')) ?: null;
        $key = strtolower(trim((string)($_POST['field_key'] ?? '')));
        $label = trim((string)($_POST['label'] ?? ''));
        $type = (string)($_POST['data_type'] ?? 'string');
        $required = isset($_POST['is_required']) ? 1 : 0;
        $unit = trim((string)($_POST['unit'] ?? '')) ?: null;
        $options = array_values(array_filter(array_map('trim', explode(',', (string)($_POST['options'] ?? '')))));
        $validTypes = ['string','character','integer','decimal','boolean','date','enum','url'];
        if ($definitionId > 0) {
            $existingStmt = $conn->prepare("SELECT scope,owner_company,source_client_id,field_key FROM joss_v3_proto_field_definitions WHERE id=? LIMIT 1");
            $existingStmt->bind_param('i', $definitionId);
            $existingStmt->execute();
            $existingDefinition = $existingStmt->get_result()->fetch_assoc();
            $existingStmt->close();
            if ($existingDefinition) {
                $scope = $existingDefinition['scope'];
                $owner = $existingDefinition['owner_company'];
                $source = $existingDefinition['source_client_id'];
                $key = $existingDefinition['field_key'];
            }
        }
        if (!jv3_valid_key($key) || $label === '' || !in_array($type,$validTypes,true)
            || ($scope === 'company' && !$owner) || ($scope === 'partner' && (!$owner || !$source))
            || ($type === 'enum' && !$options)) {
            jv3_flash('danger', 'Definisi tidak valid. Periksa key, scope, tipe, pemilik, dan opsi enum.');
            jv3_redirect('fields');
        }
        if ($definitionId < 1) {
            $duplicateStmt = $conn->prepare("SELECT id FROM joss_v3_proto_field_definitions
                WHERE scope=? AND owner_company <=> ? AND source_client_id <=> ? AND field_key=? LIMIT 1");
            $duplicateStmt->bind_param('ssss', $scope, $owner, $source, $key);
            $duplicateStmt->execute();
            $duplicate = $duplicateStmt->get_result()->fetch_assoc();
            $duplicateStmt->close();
            if ($duplicate) {
                jv3_flash('danger', 'Key sudah digunakan pada scope, perusahaan, dan klien tersebut.');
                jv3_redirect('fields');
            }
        }
        $optionsJson = $options ? json_encode($options) : null;
        $validationJson = '{}';
        $userId = intval($_SESSION['user_id'] ?? 0);
        $conn->begin_transaction();
        try {
            if ($definitionId < 1) {
                $stmt = $conn->prepare("INSERT INTO joss_v3_proto_field_definitions (scope,owner_company,source_client_id,field_key,created_by) VALUES (?,?,?,?,?)");
                $stmt->bind_param('ssssi', $scope, $owner, $source, $key, $userId);
                $stmt->execute();
                $definitionId = (int)$conn->insert_id;
                $stmt->close();
                $nextVersion = 1;
            } else {
                $stmt = $conn->prepare("SELECT COALESCE(MAX(version_no),0)+1 n FROM joss_v3_proto_field_versions WHERE definition_id=?");
                $stmt->bind_param('i', $definitionId);
                $stmt->execute();
                $nextVersion = (int)$stmt->get_result()->fetch_assoc()['n'];
                $stmt->close();
            }
            $stmt = $conn->prepare("INSERT INTO joss_v3_proto_field_versions (definition_id,version_no,label,data_type,is_required,unit,options_json,validation_json,created_by) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('iississsi', $definitionId, $nextVersion, $label, $type, $required, $unit, $optionsJson, $validationJson, $userId);
            $stmt->execute();
            $stmt->close();
            jv3_audit($conn, $nextVersion === 1 ? 'field.created' : 'field.versioned', 'field_definition', $definitionId, compact('key','label','type','scope','nextVersion'));
            $conn->commit();
            jv3_flash('success', 'Definisi DCF versi ' . $nextVersion . ' berhasil disimpan.');
        } catch (Throwable $e) {
            $conn->rollback();
            jv3_flash('danger', str_contains($e->getMessage(),'Duplicate') ? 'Key sudah digunakan pada scope tersebut.' : $e->getMessage());
        }
        jv3_redirect('fields');
    }

    if ($action === 'toggle_definition') {
        $id = intval($_POST['definition_id'] ?? 0);
        $status = ($_POST['status'] ?? '') === 'active' ? 'active' : 'inactive';
        $stmt = $conn->prepare("UPDATE joss_v3_proto_field_definitions SET status=? WHERE id=?");
        $stmt->bind_param('si', $status, $id);
        $stmt->execute();
        $stmt->close();
        jv3_audit($conn, 'field.status.changed', 'field_definition', $id, ['status'=>$status]);
        jv3_flash('success', 'Status definisi diperbarui; versi historis tetap tersimpan.');
        jv3_redirect('fields');
    }

    if ($action === 'save_vacancy') {
        $vacancyId = intval($_POST['vacancy_id'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $company = trim((string)($_POST['company_name'] ?? ''));
        $source = (string)($_POST['source_client_id'] ?? '');
        $mode = in_array($_POST['custom_fields_mode'] ?? '', ['omit','clear','replace'], true) ? $_POST['custom_fields_mode'] : 'omit';
        $selectedVersions = array_map('intval', (array)($_POST['field_versions'] ?? []));
        $clientStmt = $conn->prepare("SELECT * FROM joss_v3_proto_clients WHERE client_id=? LIMIT 1");
        $clientStmt->bind_param('s', $source);
        $clientStmt->execute();
        $client = $clientStmt->get_result()->fetch_assoc();
        $clientStmt->close();
        if ($title === '' || $company === '' || !$client) {
            jv3_flash('danger', 'Judul, perusahaan, dan klien sumber wajib valid.');
            jv3_redirect('fields');
        }
        if ($mode !== 'omit' && (!$client['allow_dcf_write'] || $client['status'] !== 'active')) {
            jv3_flash('danger', '403 DCF_FORBIDDEN — klien tidak memiliki izin kirim DCF.');
            jv3_redirect('fields');
        }
        if ($mode === 'replace' && (count($selectedVersions) > (int)$client['max_fields'])) {
            jv3_flash('danger', '413 DCF_PAYLOAD_TOO_LARGE — jumlah field melampaui kebijakan klien.');
            jv3_redirect('fields');
        }
        $fieldRows = [];
        $errors = [];
        if ($mode === 'replace') {
            $available = [];
            foreach (jv3_field_rows($conn) as $row) $available[(int)$row['version_id']] = $row;
            $allowedTypes = json_decode($client['allowed_types_json'], true) ?: [];
            foreach ($selectedVersions as $index => $versionId) {
                if (!isset($available[$versionId])) {
                    $errors[] = 'Definisi field tidak tersedia.';
                    continue;
                }
                $row = $available[$versionId];
                $scopeAllowed = $row['scope'] === 'global'
                    || ($row['scope'] === 'company' && $row['owner_company'] === $company)
                    || ($row['scope'] === 'partner' && $row['owner_company'] === $company && $row['source_client_id'] === $source);
                if (!$scopeAllowed) {
                    $errors[] = $row['field_key'] . ': definisi berada di luar scope perusahaan/klien.';
                    continue;
                }
                if (!in_array($row['data_type'], $allowedTypes, true)) {
                    $errors[] = $row['field_key'] . ': tipe tidak diizinkan.';
                    continue;
                }
                $value = trim((string)($_POST['field_value'][$versionId] ?? ''));
                $error = jv3_validate_typed_value($row['data_type'], $value, $row['options_json']);
                if ($error) $errors[] = $row['field_key'] . ': ' . $error;
                $operator = in_array($_POST['field_operator'][$versionId] ?? '', ['eq','gte','lte'], true) ? $_POST['field_operator'][$versionId] : 'eq';
                $fieldRows[] = compact('versionId','value','operator','index');
            }
            if (!$selectedVersions) $errors[] = 'Pilih minimal satu field untuk mode replace.';
        }
        if ($errors) {
            jv3_flash('danger', '422 DCF_INVALID_VALUE — ' . implode(' ', $errors));
            jv3_redirect('fields' . ($vacancyId ? '?vacancy=' . $vacancyId : ''));
        }
        $userId = intval($_SESSION['user_id'] ?? 0);
        $conn->begin_transaction();
        try {
            if ($vacancyId < 1) {
                $code = 'VAC-' . date('ymd-His') . '-' . random_int(10,99);
                $stmt = $conn->prepare("INSERT INTO joss_v3_proto_vacancies (vacancy_code,title,company_name,source_client_id,created_by) VALUES (?,?,?,?,?)");
                $stmt->bind_param('ssssi', $code, $title, $company, $source, $userId);
                $stmt->execute();
                $vacancyId = (int)$conn->insert_id;
                $stmt->close();
                $revision = 1;
            } else {
                $stmt = $conn->prepare("SELECT revision_no FROM joss_v3_proto_vacancies WHERE id=? FOR UPDATE");
                $stmt->bind_param('i', $vacancyId);
                $stmt->execute();
                $current = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$current) throw new RuntimeException('Lowongan tidak ditemukan.');
                $revision = (int)$current['revision_no'] + 1;
                $stmt = $conn->prepare("UPDATE joss_v3_proto_vacancies SET title=?,company_name=?,source_client_id=?,revision_no=?,verification_status='pending',publication_status='draft' WHERE id=?");
                $stmt->bind_param('sssii', $title, $company, $source, $revision, $vacancyId);
                $stmt->execute();
                $stmt->close();
                if ($mode === 'omit') {
                    $copy = $conn->prepare("INSERT INTO joss_v3_proto_vacancy_values (vacancy_id,revision_no,field_version_id,value_text,comparison_operator,display_order)
                        SELECT vacancy_id,?,field_version_id,value_text,comparison_operator,display_order FROM joss_v3_proto_vacancy_values WHERE vacancy_id=? AND revision_no=?");
                    $previous = $revision - 1;
                    $copy->bind_param('iii', $revision, $vacancyId, $previous);
                    $copy->execute();
                    $copy->close();
                }
            }
            if ($mode === 'replace') {
                $stmt = $conn->prepare("INSERT INTO joss_v3_proto_vacancy_values (vacancy_id,revision_no,field_version_id,value_text,comparison_operator,display_order) VALUES (?,?,?,?,?,?)");
                foreach ($fieldRows as $field) {
                    $order = $field['index'] + 1;
                    $stmt->bind_param('iiissi', $vacancyId, $revision, $field['versionId'], $field['value'], $field['operator'], $order);
                    $stmt->execute();
                }
                $stmt->close();
            }
            jv3_audit($conn, 'vacancy.revision.saved', 'vacancy', $vacancyId, ['revision'=>$revision,'custom_fields_mode'=>$mode,'field_count'=>count($fieldRows)]);
            $conn->commit();
            jv3_flash('success', 'Lowongan revisi ' . $revision . ' tersimpan atomik. Mode custom_fields: ' . $mode . '.');
        } catch (Throwable $e) {
            $conn->rollback();
            jv3_flash('danger', $e->getMessage());
        }
        jv3_redirect('fields?vacancy=' . $vacancyId);
    }
}

$definitions = jv3_field_rows($conn, false);
$activeDefinitions = array_values(array_filter($definitions, fn($row) => $row['status'] === 'active'));
$clients = jv3_all($conn, "SELECT * FROM joss_v3_proto_clients ORDER BY client_name");
$vacancies = jv3_all($conn, "SELECT * FROM joss_v3_proto_vacancies ORDER BY updated_at DESC");
$selectedVacancyId = intval($_GET['vacancy'] ?? ($vacancies[0]['id'] ?? 0));
$selectedVacancy = null;
foreach ($vacancies as $vacancy) if ((int)$vacancy['id'] === $selectedVacancyId) $selectedVacancy = $vacancy;
$selectedValues = $selectedVacancy ? jv3_vacancy_values($conn, $selectedVacancyId) : [];
$selectedMap = [];
foreach ($selectedValues as $value) $selectedMap[(int)$value['field_version_id']] = $value;
$payload = $selectedVacancy ? [
    'title'=>$selectedVacancy['title'],
    'qualification'=>'Kualifikasi sesuai kebutuhan pekerjaan',
    'custom_fields'=>jv3_custom_fields_payload($selectedValues),
] : [];

jv3_render_header('DCF & Vacancies', 'fields');
?>
<ul class="nav nav-pills mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#catalog">Katalog Definisi</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#vacancy">Vacancy Builder</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#api">API Preview</button></li>
</ul>
<div class="tab-content">
<div class="tab-pane fade show active" id="catalog"><div class="row g-3">
    <div class="col-xl-8"><section class="jv3-card"><div class="jv3-card-header"><h2 class="h6 mb-0">Definisi & versi aktif</h2></div><div class="table-responsive"><table class="table mb-0 align-middle">
        <thead><tr><th>Key / Label</th><th>Scope</th><th>Tipe</th><th>Versi</th><th>Status</th><th></th></tr></thead>
        <tbody><?php foreach ($definitions as $field): ?><tr>
            <td><strong><?php echo jv3_h($field['field_key']); ?></strong><div class="small text-muted"><?php echo jv3_h($field['label']); ?></div></td>
            <td><?php echo jv3_h($field['scope']); ?><div class="small text-muted"><?php echo jv3_h($field['owner_company'] ?: $field['source_client_id']); ?></div></td>
            <td><?php echo jv3_h($field['data_type']); ?><?php echo $field['unit'] ? ' · '.jv3_h($field['unit']) : ''; ?></td><td>v<?php echo (int)$field['version_no']; ?></td><td><?php echo jv3_status_badge($field['status']); ?></td>
            <td><form method="post"><input type="hidden" name="csrf_token" value="<?php echo jv3_h(jv3_csrf_token()); ?>"><input type="hidden" name="action" value="toggle_definition"><input type="hidden" name="definition_id" value="<?php echo (int)$field['id']; ?>"><input type="hidden" name="status" value="<?php echo $field['status']==='active'?'inactive':'active'; ?>"><button class="btn btn-sm btn-outline-secondary"><?php echo $field['status']==='active'?'Nonaktifkan':'Aktifkan'; ?></button></form></td>
        </tr><?php endforeach; ?></tbody>
    </table></div></section></div>
    <div class="col-xl-4"><section class="jv3-card"><div class="jv3-card-header"><h2 class="h6 mb-0">Tambah definisi / versi</h2></div><form method="post" class="jv3-card-body">
        <input type="hidden" name="csrf_token" value="<?php echo jv3_h(jv3_csrf_token()); ?>"><input type="hidden" name="action" value="save_definition">
        <div class="mb-2"><label class="form-label">Buat versi baru dari (opsional)</label><select class="form-select" name="definition_id"><option value="0">Definisi baru</option><?php foreach ($definitions as $field): ?><option value="<?php echo (int)$field['id']; ?>"><?php echo jv3_h($field['field_key']); ?> v<?php echo (int)$field['version_no']; ?></option><?php endforeach; ?></select></div>
        <div class="row g-2"><div class="col-6"><label class="form-label">Scope</label><select class="form-select" name="scope"><option>global</option><option>company</option><option>partner</option></select></div><div class="col-6"><label class="form-label">Tipe</label><select class="form-select" name="data_type"><?php foreach (['string','character','integer','decimal','boolean','date','enum','url'] as $type): ?><option><?php echo $type; ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><label class="form-label">Key</label><input class="form-control" name="field_key" placeholder="minimum_body_height" required></div><div class="col-12"><label class="form-label">Label</label><input class="form-control" name="label" required></div>
        <div class="col-6"><label class="form-label">Perusahaan pemilik</label><input class="form-control" name="owner_company"></div><div class="col-6"><label class="form-label">Client sumber</label><select class="form-select" name="source_client_id"><option value="">—</option><?php foreach ($clients as $client): ?><option><?php echo jv3_h($client['client_id']); ?></option><?php endforeach; ?></select></div>
        <div class="col-6"><label class="form-label">Satuan</label><input class="form-control" name="unit" placeholder="cm"></div><div class="col-6"><label class="form-label">Opsi enum</label><input class="form-control" name="options" placeholder="A1, A2, B1"></div></div>
        <label class="form-check mt-3"><input class="form-check-input" type="checkbox" name="is_required"> <span class="form-check-label">Wajib bila field dipilih</span></label>
        <button class="btn btn-primary mt-3 w-100">Simpan definisi</button>
    </form></section></div>
</div></div>

<div class="tab-pane fade" id="vacancy"><div class="row g-3">
    <div class="col-xl-4"><section class="jv3-card"><div class="jv3-card-header"><h2 class="h6 mb-0">Lowongan tersimpan</h2></div><div class="list-group list-group-flush"><?php foreach ($vacancies as $row): ?><a class="list-group-item list-group-item-action" href="?vacancy=<?php echo (int)$row['id']; ?>#vacancy"><strong><?php echo jv3_h($row['title']); ?></strong><div class="small text-muted"><?php echo jv3_h($row['vacancy_code']); ?> · revisi <?php echo (int)$row['revision_no']; ?></div></a><?php endforeach; ?></div></section></div>
    <div class="col-xl-8"><section class="jv3-card"><div class="jv3-card-header"><h2 class="h6 mb-0"><?php echo $selectedVacancy ? 'Ubah lowongan' : 'Tambah lowongan'; ?></h2></div><form method="post" class="jv3-card-body" id="vacancyForm">
        <input type="hidden" name="csrf_token" value="<?php echo jv3_h(jv3_csrf_token()); ?>"><input type="hidden" name="action" value="save_vacancy"><input type="hidden" name="vacancy_id" value="<?php echo (int)($selectedVacancy['id'] ?? 0); ?>">
        <div class="row g-2 mb-3"><div class="col-md-5"><label class="form-label">Judul</label><input class="form-control" name="title" value="<?php echo jv3_h($selectedVacancy['title'] ?? ''); ?>" required></div><div class="col-md-4"><label class="form-label">Perusahaan</label><input class="form-control" name="company_name" value="<?php echo jv3_h($selectedVacancy['company_name'] ?? ''); ?>" required></div><div class="col-md-3"><label class="form-label">Client sumber</label><select class="form-select" name="source_client_id"><?php foreach ($clients as $client): ?><option <?php echo ($selectedVacancy['source_client_id']??'')===$client['client_id']?'selected':''; ?>><?php echo jv3_h($client['client_id']); ?></option><?php endforeach; ?></select></div></div>
        <div class="alert alert-light border"><label class="form-label d-block">Semantik PUT custom_fields</label><?php foreach (['omit'=>'Tidak dikirim — pertahankan DCF lama','clear'=>'Array [] — hapus daftar aktif','replace'=>'Array berisi — ganti seluruh daftar atomik'] as $value=>$label): ?><label class="form-check form-check-inline"><input class="form-check-input" type="radio" name="custom_fields_mode" value="<?php echo $value; ?>" <?php echo $value==='replace'?'checked':''; ?>> <span class="form-check-label"><?php echo jv3_h($label); ?></span></label><?php endforeach; ?></div>
        <h3 class="h6">Field tambahan</h3>
        <?php foreach ($activeDefinitions as $field): $saved = $selectedMap[(int)$field['version_id']] ?? null; ?><div class="row g-2 align-items-end border-top py-2">
            <div class="col-md-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="field_versions[]" value="<?php echo (int)$field['version_id']; ?>" <?php echo $saved?'checked':''; ?>> <span class="form-check-label fw-semibold"><?php echo jv3_h($field['label']); ?><?php echo $field['is_required']?' *':''; ?></span></label><div class="small text-muted"><?php echo jv3_h($field['field_key'].' · '.$field['data_type'].' · '.$field['scope']); ?></div></div>
            <div class="col-md-2"><label class="form-label">Operator</label><select class="form-select" name="field_operator[<?php echo (int)$field['version_id']; ?>]"><?php foreach (['eq','gte','lte'] as $op): ?><option <?php echo ($saved['comparison_operator']??'eq')===$op?'selected':''; ?>><?php echo $op; ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label class="form-label">Nilai plain text <?php echo $field['unit']?'('.jv3_h($field['unit']).')':''; ?></label><?php if ($field['data_type']==='enum'): ?><select class="form-select" name="field_value[<?php echo (int)$field['version_id']; ?>]"><?php foreach (json_decode($field['options_json'],true)?:[] as $option): ?><option <?php echo ($saved['value_text']??'')===$option?'selected':''; ?>><?php echo jv3_h($option); ?></option><?php endforeach; ?></select><?php else: ?><input class="form-control" name="field_value[<?php echo (int)$field['version_id']; ?>]" value="<?php echo jv3_h($saved['value_text'] ?? ''); ?>" placeholder="<?php echo $field['data_type']==='boolean'?'true / false':'Nilai sebagai string'; ?>"><?php endif; ?></div>
        </div><?php endforeach; ?>
        <button class="btn btn-primary mt-3"><i class="bi bi-database-check me-1"></i>Simpan revisi atomik</button>
    </form></section></div>
</div></div>

<div class="tab-pane fade" id="api"><div class="row g-3">
    <div class="col-md-6"><section class="jv3-card"><div class="jv3-card-header"><h2 class="h6 mb-0">PUT /api/v1/jobs/{id}/industrial-vacancy</h2></div><div class="jv3-card-body"><pre class="jv3-code"><?php echo jv3_h(jv3_json($payload)); ?></pre></div></section></div>
    <div class="col-md-6"><section class="jv3-card"><div class="jv3-card-header"><h2 class="h6 mb-0">GET response</h2></div><div class="jv3-card-body"><pre class="jv3-code"><?php echo jv3_h(jv3_json(['data'=>['vacancy'=>['id'=>$selectedVacancy['vacancy_code']??null,'custom_fields'=>$payload['custom_fields']??[]]]])); ?></pre></div></section></div>
</div></div>
</div>
<?php jv3_render_footer(); ?>
