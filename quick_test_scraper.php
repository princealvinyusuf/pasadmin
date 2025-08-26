<?php
// Quick test scraper for Jobstreet - focused on speed and reliability
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Set short timeout to prevent hanging
set_time_limit(30);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';
if (!current_user_can('manage_jobstreet_scraping')) { http_response_code(403); echo 'Forbidden'; exit; }

header('Content-Type: text/plain; charset=utf-8');

echo "=== Quick Jobstreet Test ===\n\n";

try {
    // Test database connection
    echo "1. Database connection... ";
    if ($conn->ping()) {
        echo "OK\n";
    } else {
        echo "FAILED\n";
        exit;
    }
    
    // Create test run
    echo "2. Creating test run... ";
    $conn->query("CREATE TABLE IF NOT EXISTS jobstreet_scrape_runs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(32) NOT NULL DEFAULT 'queued',
        total_found INT DEFAULT 0,
        total_imported INT DEFAULT 0,
        log TEXT NULL,
        output_file VARCHAR(255) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $conn->query("INSERT INTO jobstreet_scrape_runs (status) VALUES ('testing')");
    $runId = $conn->insert_id;
    echo "OK (ID: $runId)\n";
    
    // Quick scraping test
    echo "3. Quick scraping test...\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $url = 'https://id.jobstreet.com/id/jobs';
    echo "   Fetching: $url\n";
    
    curl_setopt($ch, CURLOPT_URL, $url);
    $html = curl_exec($ch);
    
    if (!$html) {
        throw new Exception("Failed to fetch page: " . curl_error($ch));
    }
    
    echo "   SUCCESS: Got " . strlen($html) . " bytes\n";
    
    // Quick HTML parsing
    echo "4. Quick HTML parsing...\n";
    
    $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($dom);
    
    // Try the working selector directly
    $selector = '//div[.//*[contains(text(), "pekerjaan")] and .//*[contains(text(), "perusahaan")]]';
    $jobNodes = $xpath->query($selector);
    
    if ($jobNodes && $jobNodes->length > 0) {
        echo "   SUCCESS: Found " . $jobNodes->length . " job nodes using: $selector\n";
        
        // Quick analysis of first 2 nodes
        echo "5. Quick node analysis...\n";
        
        for ($i = 0; $i < min(2, $jobNodes->length); $i++) {
            $node = $jobNodes->item($i);
            echo "   Node " . ($i + 1) . ":\n";
            
            // Show text content
            $text = trim($node->textContent);
            if (strlen($text) > 150) {
                $text = substr($text, 0, 150) . "...";
            }
            echo "     Text: " . $text . "\n";
            
            // Look for job title
            $titleNodes = $xpath->query('.//h1 | .//h2 | .//h3 | .//h4', $node);
            if ($titleNodes->length > 0) {
                echo "     Title: " . trim($titleNodes->item(0)->textContent) . "\n";
            } else {
                echo "     Title: NOT FOUND\n";
            }
            
            // Look for company
            $companyNodes = $xpath->query('.//*[contains(text(), "perusahaan")]', $node);
            if ($companyNodes->length > 0) {
                echo "     Company: " . trim($companyNodes->item(0)->textContent) . "\n";
            } else {
                echo "     Company: NOT FOUND\n";
            }
            
            echo "\n";
        }
        
        $totalFound = $jobNodes->length;
        
    } else {
        echo "   WARNING: No job nodes found with selector: $selector\n";
        $totalFound = 0;
    }
    
    curl_close($ch);
    
    // Update run status
    echo "6. Updating status... ";
    $stmt = $conn->prepare('UPDATE jobstreet_scrape_runs SET status=\'completed\', total_found=?, total_imported=?, log=? WHERE id=?');
    $totalImported = 0;
    $log = "Quick test completed. Found $totalFound job nodes.";
    $stmt->bind_param('iisi', $totalFound, $totalImported, $log, $runId);
    $stmt->execute();
    $stmt->close();
    echo "OK\n";
    
    echo "\n=== Quick Test Results ===\n";
    echo "Run ID: $runId\n";
    echo "Status: completed\n";
    echo "Jobs found: $totalFound\n";
    
    if ($totalFound > 0) {
        echo "\n✅ Job listings found! The scraper is working.\n";
    } else {
        echo "\n❌ No job listings found.\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    
    // Update run status to failed
    if (isset($runId)) {
        try {
            $stmt = $conn->prepare('UPDATE jobstreet_scrape_runs SET status=\'failed\', log=? WHERE id=?');
            $log = "Quick test failed: " . $e->getMessage();
            $stmt->bind_param('si', $log, $runId);
            $stmt->execute();
            $stmt->close();
        } catch (Exception $dbError) {
            echo "Failed to update database status: " . $dbError->getMessage() . "\n";
        }
    }
}

echo "\n=== Quick Test Complete ===\n";
?>
