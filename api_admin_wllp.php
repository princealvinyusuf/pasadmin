<?php

require_once __DIR__ . '/wllp_external_handlers.php';

$auth = wllp_api_validate_signature($conn);
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$path = wllp_api_get_path();
$base = '/api/admin/wllp';
$relative = trim(substr($path, strlen($base)), '/');
$parts = $relative === '' ? [] : explode('/', $relative);

try {
    if ($method === 'GET' && $relative === 'dashboard') {
        wllp_api_handle_admin_dashboard($conn);
    }
    if ($method === 'GET' && $relative === 'reports') {
        wllp_api_handle_admin_reports($conn);
    }
    if ($method === 'GET' && $relative === 'compliance') {
        wllp_api_handle_admin_compliance($conn);
    }
    if ($method === 'PUT' && count($parts) === 3 && $parts[0] === 'reports' && ctype_digit($parts[1]) && $parts[2] === 'verification') {
        wllp_api_handle_admin_verification($conn, (int)$parts[1], $auth);
    }
    if ($method === 'GET' && $relative === 'export') {
        wllp_api_handle_admin_export($conn);
    }
} catch (Throwable $e) {
    wllp_api_log_request($conn, $auth, 500, 'INTERNAL_ERROR');
    wllp_api_client_error(500, 'INTERNAL_ERROR', 'Unhandled API error: ' . $e->getMessage());
}

wllp_api_log_request($conn, $auth, 404, 'NOT_FOUND');
wllp_api_client_error(404, 'NOT_FOUND', 'Endpoint not found.');

