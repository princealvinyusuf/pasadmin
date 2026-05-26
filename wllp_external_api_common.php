<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/wllp_external_storage.php';

if (!function_exists('wllp_api_json_response')) {
    function wllp_api_json_response(int $statusCode, array $payload): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }
}

if (!function_exists('wllp_api_client_error')) {
    function wllp_api_client_error(int $statusCode, string $errorCode, string $message, array $fields = []): void
    {
        $payload = [
            'success' => false,
            'error_code' => $errorCode,
            'message' => $message,
        ];
        if (!empty($fields)) {
            $payload['fields'] = $fields;
        }
        wllp_api_json_response($statusCode, $payload);
    }
}

if (!function_exists('wllp_api_get_path')) {
    function wllp_api_get_path(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string)parse_url($uri, PHP_URL_PATH);
        return '/' . trim($path, '/');
    }
}

if (!function_exists('wllp_api_get_headers')) {
    function wllp_api_get_headers(): array
    {
        return [
            'client_id' => trim((string)($_SERVER['HTTP_CLIENT_ID'] ?? '')),
            'request_id' => trim((string)($_SERVER['HTTP_REQUEST_ID'] ?? '')),
            'request_timestamp' => trim((string)($_SERVER['HTTP_REQUEST_TIMESTAMP'] ?? '')),
            'signature' => trim((string)($_SERVER['HTTP_SIGNATURE'] ?? '')),
        ];
    }
}

if (!function_exists('wllp_api_decode_json_body')) {
    function wllp_api_decode_json_body(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            wllp_api_client_error(400, 'BAD_REQUEST', 'Invalid JSON body.');
        }
        return $decoded;
    }
}

if (!function_exists('wllp_api_get_query_int')) {
    function wllp_api_get_query_int(string $key, int $default = 0): int
    {
        if (!isset($_GET[$key])) {
            return $default;
        }
        return max(0, (int)$_GET[$key]);
    }
}

if (!function_exists('wllp_api_get_json_raw')) {
    function wllp_api_get_json_raw(): string
    {
        $raw = file_get_contents('php://input');
        return $raw === false ? '' : $raw;
    }
}

if (!function_exists('wllp_api_log_request')) {
    function wllp_api_log_request(mysqli $conn, array $auth, int $statusCode = 200, ?string $errorCode = null): void
    {
        $requestTs = date('Y-m-d H:i:s', strtotime($auth['request_timestamp']));
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $path = wllp_api_get_path();
        $bodyHash = hash('sha256', wllp_api_get_json_raw());
        $stmt = $conn->prepare("
            INSERT INTO wllp_api_request_logs
            (client_id, request_id, request_timestamp, request_method, request_path, request_body_hash, status_code, error_code)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                status_code = VALUES(status_code),
                error_code = VALUES(error_code)
        ");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param(
            'ssssssis',
            $auth['client_id'],
            $auth['request_id'],
            $requestTs,
            $method,
            $path,
            $bodyHash,
            $statusCode,
            $errorCode
        );
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('wllp_api_write_audit_log')) {
    function wllp_api_write_audit_log(mysqli $conn, string $entityType, string $entityId, string $action, array $before, array $after, array $auth): void
    {
        $conn->query("
            CREATE TABLE IF NOT EXISTS wllp_api_audit_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                actor_client_id VARCHAR(120) NOT NULL,
                entity_type VARCHAR(80) NOT NULL,
                entity_id VARCHAR(120) NOT NULL,
                action VARCHAR(80) NOT NULL,
                before_json LONGTEXT DEFAULT NULL,
                after_json LONGTEXT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_wllp_audit_entity (entity_type, entity_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $beforeJson = empty($before) ? null : json_encode($before);
        $afterJson = empty($after) ? null : json_encode($after);
        $stmt = $conn->prepare("
            INSERT INTO wllp_api_audit_logs
            (actor_client_id, entity_type, entity_id, action, before_json, after_json)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('ssssss', $auth['client_id'], $entityType, $entityId, $action, $beforeJson, $afterJson);
        $stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('wllp_api_validate_signature')) {
    function wllp_api_validate_signature(mysqli $conn): array
    {
        wllp_external_ensure_schema($conn);
        $h = wllp_api_get_headers();
        if ($h['client_id'] === '' || $h['request_id'] === '' || $h['request_timestamp'] === '' || $h['signature'] === '') {
            wllp_api_client_error(401, 'UNAUTHORIZED', 'Missing required authentication headers.');
        }

        $ts = strtotime($h['request_timestamp']);
        if ($ts === false) {
            wllp_api_client_error(401, 'UNAUTHORIZED', 'Request-Timestamp format invalid.');
        }
        $now = time();
        if (abs($now - $ts) > 300) {
            wllp_api_client_error(401, 'UNAUTHORIZED', 'Request-Timestamp is expired.');
        }

        $stmt = $conn->prepare("
            SELECT client_id, client_secret
            FROM wllp_api_clients
            WHERE client_id = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->bind_param('s', $h['client_id']);
        $stmt->execute();
        $res = $stmt->get_result();
        $client = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$client) {
            wllp_api_client_error(401, 'UNAUTHORIZED', 'Client-Id is not active.');
        }

        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $path = wllp_api_get_path();
        $rawBody = wllp_api_get_json_raw();
        $bodyHash = hash('sha256', $rawBody);
        $canonical = $method . "\n" . $path . "\n" . $h['client_id'] . "\n" . $h['request_id'] . "\n" . $h['request_timestamp'] . "\n" . $bodyHash;
        $expected = hash_hmac('sha256', $canonical, (string)$client['client_secret']);

        if (!hash_equals($expected, $h['signature'])) {
            wllp_api_client_error(401, 'UNAUTHORIZED', 'Signature invalid.');
        }

        $reqTs = date('Y-m-d H:i:s', $ts);
        $insertReplay = $conn->prepare("
            INSERT INTO wllp_api_request_logs
            (client_id, request_id, request_timestamp, request_method, request_path, request_body_hash, status_code)
            VALUES (?, ?, ?, ?, ?, ?, 200)
        ");
        $insertReplay->bind_param('ssssss', $h['client_id'], $h['request_id'], $reqTs, $method, $path, $bodyHash);
        try {
            $insertReplay->execute();
        } catch (Throwable $e) {
            $insertReplay->close();
            wllp_api_client_error(409, 'DUPLICATE_REQUEST_ID', 'Request-Id has already been used.');
        }
        $insertReplay->close();

        return $h;
    }
}

if (!function_exists('wllp_api_require_fields')) {
    function wllp_api_require_fields(array $body, array $required): void
    {
        $missing = [];
        foreach ($required as $key) {
            if (!array_key_exists($key, $body) || $body[$key] === null || $body[$key] === '') {
                $missing[$key] = 'Required field.';
            }
        }
        if (!empty($missing)) {
            wllp_api_client_error(422, 'VALIDATION_FAILED', 'Data belum lengkap.', $missing);
        }
    }
}

if (!function_exists('wllp_api_validate_terms')) {
    function wllp_api_validate_terms(array $body): array
    {
        $terms = $body['terms'] ?? null;
        if (!is_array($terms)) {
            wllp_api_client_error(422, 'TERMS_REQUIRED', 'Terms agreement is required.');
        }
        $agreed = (bool)($terms['agreed'] ?? false);
        $version = trim((string)($terms['version'] ?? ''));
        if (!$agreed || $version === '') {
            wllp_api_client_error(422, 'TERMS_REQUIRED', 'Terms must be agreed with valid version.');
        }
        return ['agreed' => 1, 'version' => $version];
    }
}

if (!function_exists('wllp_api_upsert_item_status')) {
    function wllp_api_upsert_item_status(mysqli $conn, int $itemId, string $status, ?string $note, string $clientId): void
    {
        $filledCount = 0;
        $countStmt = $conn->prepare("SELECT COUNT(*) AS c FROM wllp_placements WHERE item_id = ?");
        $countStmt->bind_param('i', $itemId);
        $countStmt->execute();
        $res = $countStmt->get_result();
        $row = $res ? $res->fetch_assoc() : ['c' => 0];
        $countStmt->close();
        $filledCount = (int)($row['c'] ?? 0);

        $stmt = $conn->prepare("
            INSERT INTO wllp_item_statuses(item_id, status, note, filled_count, last_reported_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                note = VALUES(note),
                filled_count = VALUES(filled_count),
                last_reported_at = VALUES(last_reported_at)
        ");
        $stmt->bind_param('issi', $itemId, $status, $note, $filledCount);
        $stmt->execute();
        $stmt->close();

        $hist = $conn->prepare("
            INSERT INTO wllp_status_histories(item_id, status, note, actor_client_id)
            VALUES (?, ?, ?, ?)
        ");
        $hist->bind_param('isss', $itemId, $status, $note, $clientId);
        $hist->execute();
        $hist->close();
    }
}

if (!function_exists('wllp_api_get_report_scope')) {
    function wllp_api_get_report_scope(array $bodyOrQuery): array
    {
        $employerId = (int)($bodyOrQuery['employer_id'] ?? 0);
        $employerCode = trim((string)($bodyOrQuery['employer_code'] ?? ('EMP-' . str_pad((string)$employerId, 3, '0', STR_PAD_LEFT))));
        $employerName = trim((string)($bodyOrQuery['employer_name'] ?? 'Employer External'));
        $unitId = (int)($bodyOrQuery['unit_id'] ?? 0);
        $unitCode = trim((string)($bodyOrQuery['unit_code'] ?? ('UNIT-' . str_pad((string)$unitId, 3, '0', STR_PAD_LEFT))));
        $unitName = trim((string)($bodyOrQuery['unit_name'] ?? 'Unit External'));
        if ($employerId <= 0 || $unitId <= 0) {
            wllp_api_client_error(422, 'VALIDATION_FAILED', 'employer_id and unit_id are required.', [
                'employer_id' => 'Must be > 0.',
                'unit_id' => 'Must be > 0.',
            ]);
        }
        return [
            'employer_id' => $employerId,
            'employer_code' => $employerCode,
            'employer_name' => $employerName,
            'unit_id' => $unitId,
            'unit_code' => $unitCode,
            'unit_name' => $unitName,
        ];
    }
}

if (!function_exists('wllp_api_find_or_create_report')) {
    function wllp_api_find_or_create_report(mysqli $conn, array $scope, array $period, string $notes, string $clientId): array
    {
        $sel = $conn->prepare("
            SELECT id, no_reg_bukti
            FROM wllp_reports
            WHERE employer_id = ?
              AND period_type = ?
              AND ? BETWEEN period_start AND period_end
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $sel->bind_param('iss', $scope['employer_id'], $period['period_type'], $period['period_anchor']);
        $sel->execute();
        $res = $sel->get_result();
        $existing = $res ? $res->fetch_assoc() : null;
        $sel->close();

        if ($existing) {
            return [
                'id' => (int)$existing['id'],
                'no_reg_bukti' => (string)$existing['no_reg_bukti'],
                'reused' => true,
            ];
        }

        $noReg = wllp_external_generate_no_reg_bukti($conn, $period['period_anchor']);
        $ins = $conn->prepare("
            INSERT INTO wllp_reports
            (no_reg_bukti, employer_id, employer_code, employer_name, unit_id, unit_code, unit_name, period_type, period_anchor, period_start, period_end, verification_status, notes, created_by_client_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted', ?, ?)
        ");
        $ins->bind_param(
            'sississssssss',
            $noReg,
            $scope['employer_id'],
            $scope['employer_code'],
            $scope['employer_name'],
            $scope['unit_id'],
            $scope['unit_code'],
            $scope['unit_name'],
            $period['period_type'],
            $period['period_anchor'],
            $period['period_start'],
            $period['period_end'],
            $notes,
            $clientId
        );
        $ins->execute();
        $id = (int)$ins->insert_id;
        $ins->close();

        return [
            'id' => $id,
            'no_reg_bukti' => $noReg,
            'reused' => false,
        ];
    }
}

