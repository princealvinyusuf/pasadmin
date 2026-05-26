<?php

require_once __DIR__ . '/wllp_external_handlers.php';

$auth = wllp_api_validate_signature($conn);
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$path = wllp_api_get_path();
$base = '/api/wllp';
$relative = trim(substr($path, strlen($base)), '/');
$parts = $relative === '' ? [] : explode('/', $relative);

try {
    if ($method === 'GET' && $relative === 'employer/dashboard') {
        wllp_api_handle_employer_dashboard($conn);
    }
    if ($method === 'GET' && $relative === 'reports') {
        wllp_api_handle_reports_get($conn);
    }
    if ($method === 'POST' && $relative === 'reports') {
        $payload = wllp_api_decode_json_body();
        $result = wllp_api_create_report_from_payload($conn, $payload, $auth);
        wllp_api_json_response(201, $result);
    }
    if ($method === 'POST' && $relative === 'reports/bulk/validate') {
        wllp_api_handle_bulk_validate($conn, $auth);
    }
    if ($method === 'POST' && $relative === 'reports/bulk/commit') {
        wllp_api_handle_bulk_commit($conn, $auth);
    }
    if ($method === 'GET' && count($parts) === 2 && $parts[0] === 'reports' && ctype_digit($parts[1])) {
        wllp_api_handle_report_detail($conn, (int)$parts[1]);
    }
    if ($method === 'GET' && count($parts) === 3 && $parts[0] === 'reports' && ctype_digit($parts[1]) && $parts[2] === 'pdf') {
        wllp_api_handle_report_pdf($conn, (int)$parts[1]);
    }
    if ($method === 'GET' && count($parts) === 3 && $parts[0] === 'items' && ctype_digit($parts[1]) && $parts[2] === 'status') {
        wllp_api_handle_item_status_get($conn, (int)$parts[1]);
    }
    if ($method === 'PUT' && count($parts) === 3 && $parts[0] === 'items' && ctype_digit($parts[1]) && $parts[2] === 'status') {
        wllp_api_handle_item_status_put($conn, (int)$parts[1], $auth);
    }
    if ($method === 'POST' && count($parts) === 3 && $parts[0] === 'items' && ctype_digit($parts[1]) && $parts[2] === 'placements') {
        wllp_api_handle_add_placement($conn, (int)$parts[1], $auth);
    }
} catch (Throwable $e) {
    wllp_api_log_request($conn, $auth, 500, 'INTERNAL_ERROR');
    wllp_api_client_error(500, 'INTERNAL_ERROR', 'Unhandled API error: ' . $e->getMessage());
}

wllp_api_log_request($conn, $auth, 404, 'NOT_FOUND');
wllp_api_client_error(404, 'NOT_FOUND', 'Endpoint not found.');

