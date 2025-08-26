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

// Try to kick off background PHP process
$php = PHP_BINARY;
$script = __DIR__ . '/../scripts/jobstreet_worker.php';
$runIdStr = (string)$runId;

// Windows-specific execution
if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
    // Method 1: Try using start command
    $cmd = "start /B \"Jobstreet Scraper\" \"{$php}\" \"{$script}\" \"{$runIdStr}\"";
    $output = [];
    $returnVar = 0;
    exec($cmd, $output, $returnVar);
    
    // Method 2: If start fails, try direct execution
    if ($returnVar !== 0) {
        $cmd = "\"{$php}\" \"{$script}\" \"{$runIdStr}\" > NUL 2>&1";
        exec($cmd, $output, $returnVar);
    }
    
    // Method 3: Try using wscript for background execution
    if ($returnVar !== 0) {
        $vbsScript = __DIR__ . '/../scripts/run_worker.vbs';
        $vbsContent = "Set WshShell = CreateObject(\"WScript.Shell\")\n";
        $vbsContent .= "WshShell.Run \"\\\"{$php}\\\" \\\"{$script}\\\" \\\"{$runIdStr}\\\"\", 0, false\n";
        file_put_contents($vbsScript, $vbsContent);
        
        $cmd = "wscript \"{$vbsScript}\"";
        exec($cmd, $output, $returnVar);
        
        // Clean up VBS file
        @unlink($vbsScript);
    }
} else {
    // Unix/Linux execution
    $cmd = "{$php} {$script} {$runIdStr} > /dev/null 2>&1 &";
    exec($cmd);
}

// For immediate testing, let's also run the worker directly in a separate thread
// This ensures the scraping actually happens
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// Run worker in background thread
register_shutdown_function(function() use ($php, $script, $runIdStr) {
    if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
        pclose(popen("\"{$php}\" \"{$script}\" \"{$runIdStr}\"", 'r'));
    } else {
        exec("{$php} {$script} {$runIdStr} > /dev/null 2>&1 &");
    }
});

echo json_encode(['ok' => true, 'run_id' => $runId, 'message' => 'Scraping started']);
?>

