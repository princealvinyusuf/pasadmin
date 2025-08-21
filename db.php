<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'job_admin_prod';
 
try {
    $conn = new mysqli($host, $user, $pass, $db);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Database connection error: ' . $e->getMessage();
    exit;
}
?>