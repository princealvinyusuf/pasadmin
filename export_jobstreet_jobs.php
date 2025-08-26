<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';
if (!current_user_can('manage_jobs')) { http_response_code(403); echo 'Forbidden'; exit; }

// Get run ID if specified
$runId = isset($_GET['run_id']) ? intval($_GET['run_id']) : null;

// Build query based on whether we want all Jobstreet jobs or from a specific run
if ($runId) {
    // Export jobs from a specific scraping run
    $sql = "SELECT j.* FROM jobs j 
            INNER JOIN jobstreet_scrape_runs r ON j.platform_lowongan = 'Jobstreet' 
            WHERE r.id = ? AND j.created_at >= r.created_at
            ORDER BY j.id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $runId);
    $stmt->execute();
    $result = $stmt->get_result();
    $filename = "jobstreet_run_{$runId}_" . date('Ymd_His') . ".csv";
} else {
    // Export all Jobstreet jobs
    $result = $conn->query("SELECT * FROM jobs WHERE platform_lowongan = 'Jobstreet' ORDER BY id DESC");
    $filename = "jobstreet_all_jobs_" . date('Ymd_His') . ".csv";
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for proper UTF-8 encoding in Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Get column headers
if ($result->num_rows > 0) {
    $firstRow = $result->fetch_assoc();
    $headers = array_keys($firstRow);
    
    // Write headers
    fputcsv($output, $headers);
    
    // Write first row
    fputcsv($output, $firstRow);
    
    // Write remaining rows
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
} else {
    // No data found
    fputcsv($output, ['No Jobstreet jobs found']);
}

fclose($output);
exit;
?>
