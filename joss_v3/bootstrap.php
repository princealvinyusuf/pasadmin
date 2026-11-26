<?php
require_once dirname(__DIR__) . '/auth_guard.php';
require_once dirname(__DIR__) . '/access_helper.php';
require_once dirname(__DIR__) . '/db.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/lib.php';

if (!(current_user_can('manage_settings')
    || current_user_can('karirhub_employer_prototype_view')
    || current_user_can('joss_v3_prototype_view'))) {
    http_response_code(403);
    exit('Forbidden');
}

$conn->set_charset('utf8mb4');
jv3_ensure_schema($conn);
