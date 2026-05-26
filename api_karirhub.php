<?php

require_once __DIR__ . '/wllp_external_handlers.php';

$auth = wllp_api_validate_signature($conn);
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$path = wllp_api_get_path();
$base = '/api/karirhub';
$relative = trim(substr($path, strlen($base)), '/');
$parts = $relative === '' ? [] : explode('/', $relative);

try {
    if ($method === 'GET' && $relative === 'jobs/posted') {
        wllp_api_handle_karirhub_jobs_posted();
    }
    if ($method === 'POST' && count($parts) === 3 && $parts[0] === 'jobs' && $parts[2] === 'add-to-wllp') {
        wllp_api_handle_add_job_to_wllp($conn, (string)$parts[1], $auth);
    }
} catch (Throwable $e) {
    wllp_api_log_request($conn, $auth, 500, 'INTERNAL_ERROR');
    wllp_api_client_error(500, 'INTERNAL_ERROR', 'Unhandled API error: ' . $e->getMessage());
}

wllp_api_log_request($conn, $auth, 404, 'NOT_FOUND');
wllp_api_client_error(404, 'NOT_FOUND', 'Endpoint not found.');

