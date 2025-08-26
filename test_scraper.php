<?php
// Test script to manually run the Jobstreet scraper
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Check if running from web or command line
$isWeb = !defined('STDIN');

if ($isWeb) {
    // Web execution - set content type
    header('Content-Type: text/plain; charset=utf-8');
}

echo "Testing Jobstreet Scraper...\n";

// Include the worker script functions
require_once __DIR__ . '/scripts/jobstreet_worker.php';

// Test the scraper directly
echo "Starting test scrape...\n";

// Create a test run in the database
require_once __DIR__ . '/db.php';

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

// Create a test run
$conn->query("INSERT INTO jobstreet_scrape_runs (status) VALUES ('testing')");
$runId = $conn->insert_id;

echo "Created test run ID: $runId\n";

// Run the scraper
$success = scrapeJobstreet($conn, $runId);

if ($success) {
    echo "Scraping completed successfully!\n";
    
    // Check results
    $result = $conn->query("SELECT * FROM jobstreet_scrape_runs WHERE id = $runId");
    $run = $result->fetch_assoc();
    
    echo "Run Status: " . $run['status'] . "\n";
    echo "Total Found: " . $run['total_found'] . "\n";
    echo "Total Imported: " . $run['total_imported'] . "\n";
    
    // Check if jobs were imported
    $jobCount = $conn->query("SELECT COUNT(*) as count FROM jobs WHERE platform_lowongan = 'Jobstreet'")->fetch_assoc()['count'];
    echo "Total Jobstreet jobs in database: $jobCount\n";
    
    // Show some sample jobs if any were imported
    if ($jobCount > 0) {
        echo "\nSample jobs imported:\n";
        $sampleJobs = $conn->query("SELECT nama_jabatan, nama_perusahaan, provinsi FROM jobs WHERE platform_lowongan = 'Jobstreet' ORDER BY id DESC LIMIT 5");
        while ($job = $sampleJobs->fetch_assoc()) {
            echo "- " . $job['nama_jabatan'] . " at " . $job['nama_perusahaan'] . " (" . $job['provinsi'] . ")\n";
        }
    }
    
} else {
    echo "Scraping failed!\n";
    
    // Check the run status for error details
    $result = $conn->query("SELECT * FROM jobstreet_scrape_runs WHERE id = $runId");
    $run = $result->fetch_assoc();
    
    if ($run) {
        echo "Run Status: " . $run['status'] . "\n";
        if (!empty($run['log'])) {
            echo "Error Log:\n" . $run['log'] . "\n";
        }
    }
}

echo "\nTest completed.\n";

// For web execution, also check the log file
if ($isWeb) {
    $logFile = __DIR__ . '/logs/jobstreet_worker.log';
    if (file_exists($logFile)) {
        echo "\nRecent log entries:\n";
        $logContent = file_get_contents($logFile);
        $lines = explode("\n", $logContent);
        $recentLines = array_slice($lines, -10); // Last 10 lines
        foreach ($recentLines as $line) {
            if (trim($line)) {
                echo $line . "\n";
            }
        }
    }
}
?>
