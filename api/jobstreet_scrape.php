<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../access_helper.php';
if (!current_user_can('manage_jobstreet_scraping')) { http_response_code(403); header('Content-Type: application/json'); echo json_encode(['error' => 'Forbidden']); exit; }

header('Content-Type: application/json');

// Ensure runs table exists
$conn->query("CREATE TABLE IF NOT EXISTS jobstreet_scrape_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(32) NOT NULL DEFAULT 'queued',
    total_found INT DEFAULT 0,
    total_imported INT DEFAULT 0,
    log TEXT NULL,
    output_file VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Create a new run entry
$conn->query("INSERT INTO jobstreet_scrape_runs (status) VALUES ('queued')");
$runId = $conn->insert_id;

// Kick off background PHP process (simple, Windows compatible using start /B)
$php = escapeshellarg(PHP_BINARY);
$script = escapeshellarg(__DIR__ . '/../scripts/jobstreet_worker.php');
$cmd = "start /B {$php} {$script} " . escapeshellarg((string)$runId) . "";
if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
    pclose(popen($cmd, 'r'));
} else {
    $cmd = "$php $script " . escapeshellarg((string)$runId) . " > /dev/null 2>&1 &";
    exec($cmd);
}

echo json_encode(['ok' => true, 'run_id' => $runId]);
?>

