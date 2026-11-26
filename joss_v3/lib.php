<?php

function jv3_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function jv3_json(mixed $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
}

function jv3_csrf_token(): string
{
    if (empty($_SESSION['jv3_csrf'])) {
        $_SESSION['jv3_csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['jv3_csrf'];
}

function jv3_verify_csrf(): void
{
    $provided = (string)($_POST['csrf_token'] ?? '');
    if ($provided === '' || !hash_equals(jv3_csrf_token(), $provided)) {
        http_response_code(403);
        exit('Token keamanan tidak valid.');
    }
}

function jv3_flash(string $tone, string $message): void
{
    $_SESSION['jv3_flash'] = ['tone' => $tone, 'message' => $message];
}

function jv3_redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function jv3_audit(mysqli $conn, string $action, string $entityType, mixed $entityId, array $details = []): void
{
    $actor = intval($_SESSION['user_id'] ?? 0) ?: null;
    $entityId = $entityId === null ? null : (string)$entityId;
    $json = jv3_json($details);
    $stmt = $conn->prepare("INSERT INTO joss_v3_proto_audit (actor_user_id,action,entity_type,entity_id,details_json) VALUES (?,?,?,?,?)");
    $stmt->bind_param('issss', $actor, $action, $entityType, $entityId, $json);
    $stmt->execute();
    $stmt->close();
}

function jv3_all(mysqli $conn, string $sql): array
{
    $rows = [];
    $result = $conn->query($sql);
    while ($result && ($row = $result->fetch_assoc())) {
        $rows[] = $row;
    }
    return $rows;
}

function jv3_one(mysqli $conn, string $sql): ?array
{
    $result = $conn->query($sql);
    $row = $result ? $result->fetch_assoc() : null;
    return $row ?: null;
}

function jv3_valid_key(string $key): bool
{
    return (bool)preg_match('/^[a-z][a-z0-9_]{1,99}$/', $key)
        && !in_array($key, ['id','title','qualification','company','location','salary','custom_fields'], true);
}

function jv3_validate_typed_value(string $type, string $value, ?string $optionsJson = null): ?string
{
    if ($value === '') {
        return 'Nilai tidak boleh kosong.';
    }
    return match ($type) {
        'character' => mb_strlen($value) === 1 ? null : 'Nilai harus tepat satu karakter.',
        'integer' => preg_match('/^-?\d+$/', $value) ? null : 'Nilai integer harus dikirim sebagai teks bilangan bulat.',
        'decimal' => preg_match('/^-?\d+(\.\d+)?$/', $value) ? null : 'Nilai decimal harus menggunakan titik sebagai pemisah.',
        'boolean' => in_array($value, ['true','false'], true) ? null : 'Nilai boolean hanya boleh "true" atau "false".',
        'date' => (($date = DateTimeImmutable::createFromFormat('!Y-m-d', $value)) && $date->format('Y-m-d') === $value) ? null : 'Tanggal harus berformat YYYY-MM-DD.',
        'enum' => in_array($value, json_decode((string)$optionsJson, true) ?: [], true) ? null : 'Nilai tidak terdapat pada opsi enum.',
        'url' => filter_var($value, FILTER_VALIDATE_URL) && in_array(parse_url($value, PHP_URL_SCHEME), ['http','https'], true) ? null : 'URL harus absolut http/https.',
        default => preg_match('/<[^>]+>/', $value) ? 'HTML aktif tidak diizinkan.' : (mb_strlen($value) <= 2000 ? null : 'Nilai terlalu panjang.'),
    };
}

function jv3_field_rows(mysqli $conn, bool $activeOnly = true): array
{
    $where = $activeOnly ? "WHERE d.status='active'" : '';
    return jv3_all($conn, "SELECT d.*,v.id version_id,v.version_no,v.label,v.data_type,v.is_required,v.unit,v.options_json,v.validation_json
        FROM joss_v3_proto_field_definitions d
        JOIN joss_v3_proto_field_versions v ON v.definition_id=d.id
        JOIN (SELECT definition_id,MAX(version_no) latest FROM joss_v3_proto_field_versions GROUP BY definition_id) latest
          ON latest.definition_id=v.definition_id AND latest.latest=v.version_no
        $where ORDER BY d.scope,d.field_key");
}

function jv3_schema_fields(mysqli $conn, int $schemaId): array
{
    $stmt = $conn->prepare("SELECT sf.*,v.variable_key,v.label,v.source_type,v.source_key,v.data_type,v.options_json
        FROM joss_v3_proto_schema_fields sf JOIN joss_v3_proto_variables v ON v.id=sf.variable_id
        WHERE sf.schema_id=? ORDER BY sf.display_order,sf.id");
    $stmt->bind_param('i', $schemaId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function jv3_vacancy_values(mysqli $conn, int $vacancyId, ?int $revision = null): array
{
    if ($revision === null) {
        $stmt = $conn->prepare("SELECT revision_no FROM joss_v3_proto_vacancies WHERE id=?");
        $stmt->bind_param('i', $vacancyId);
        $stmt->execute();
        $revision = intval($stmt->get_result()->fetch_assoc()['revision_no'] ?? 1);
        $stmt->close();
    }
    $stmt = $conn->prepare("SELECT vv.*,d.field_key,d.scope,v.label,v.data_type,v.is_required,v.unit,v.options_json
        FROM joss_v3_proto_vacancy_values vv
        JOIN joss_v3_proto_field_versions v ON v.id=vv.field_version_id
        JOIN joss_v3_proto_field_definitions d ON d.id=v.definition_id
        WHERE vv.vacancy_id=? AND vv.revision_no=? ORDER BY vv.display_order,vv.id");
    $stmt->bind_param('ii', $vacancyId, $revision);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function jv3_custom_fields_payload(array $rows): array
{
    return array_map(static function (array $row): array {
        $field = [
            'key' => $row['field_key'],
            'label' => $row['label'],
            'data_type' => $row['data_type'],
            'required' => (bool)$row['is_required'],
            'operator' => $row['comparison_operator'] ?? 'eq',
            'value' => (string)($row['value_text'] ?? ''),
        ];
        if (!empty($row['unit'])) {
            $field['unit'] = $row['unit'];
        }
        if ($row['data_type'] === 'enum') {
            $field['options'] = json_decode((string)$row['options_json'], true) ?: [];
        }
        return $field;
    }, $rows);
}

function jv3_build_delivery_payload(mysqli $conn, array $application): array
{
    $values = json_decode((string)$application['values_json'], true) ?: [];
    $fields = jv3_schema_fields($conn, (int)$application['schema_id']);
    $standard = [];
    $custom = [];
    foreach ($fields as $field) {
        $key = $field['variable_key'];
        if (!array_key_exists($key, $values) || $values[$key] === '') {
            continue;
        }
        if ($field['source_type'] === 'PROFILE') {
            $standard[$key] = $values[$key];
        } else {
            $custom[] = ['key' => $key, 'data_type' => $field['data_type'], 'value' => (string)$values[$key]];
        }
    }
    return [
        'id' => $application['application_code'],
        'applied_at' => $application['consent_at'],
        'job_seeker' => array_merge(['name' => $application['applicant_name']], $standard),
        'custom_fields' => $custom,
    ];
}
