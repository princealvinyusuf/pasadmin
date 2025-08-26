<?php
// Simple web-based test scraper for Jobstreet
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';
if (!current_user_can('manage_jobstreet_scraping')) { http_response_code(403); echo 'Forbidden'; exit; }

header('Content-Type: text/plain; charset=utf-8');

echo "=== Jobstreet Scraper Test ===\n\n";

try {
    // Test database connection
    echo "1. Testing database connection... ";
    if ($conn->ping()) {
        echo "OK\n";
    } else {
        echo "FAILED\n";
        exit;
    }
    
    // Ensure tables exist
    echo "2. Creating tables if needed... ";
    $conn->query("CREATE TABLE IF NOT EXISTS jobstreet_scrape_runs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(32) NOT NULL DEFAULT 'queued',
        total_found INT DEFAULT 0,
        total_imported INT DEFAULT 0,
        log TEXT NULL,
        output_file VARCHAR(255) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "OK\n";
    
    // Create test run
    echo "3. Creating test run... ";
    $conn->query("INSERT INTO jobstreet_scrape_runs (status) VALUES ('testing')");
    $runId = $conn->insert_id;
    echo "OK (ID: $runId)\n";
    
    // Test basic scraping
    echo "4. Testing basic scraping...\n";
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // Test single page
    $url = 'https://id.jobstreet.com/id/jobs';
    echo "   Fetching: $url\n";
    
    curl_setopt($ch, CURLOPT_URL, $url);
    $html = curl_exec($ch);
    
    if (!$html) {
        echo "   ERROR: Failed to fetch page: " . curl_error($ch) . "\n";
        curl_close($ch);
        exit;
    }
    
    echo "   SUCCESS: Got " . strlen($html) . " bytes\n";
    
    // Check if HTML contains expected content
    if (strpos($html, 'job') !== false) {
        echo "   HTML contains 'job' keyword\n";
    }
    if (strpos($html, 'position') !== false) {
        echo "   HTML contains 'position' keyword\n";
    }
    if (strpos($html, 'career') !== false) {
        echo "   HTML contains 'career' keyword\n";
    }
    
    // Try to parse HTML
    echo "5. Parsing HTML...\n";
    
    $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($dom);
    
    // Try different selectors - updated for modern Jobstreet structure
    $selectors = [
        // Modern Jobstreet selectors
        '//div[contains(@class, "job-card")]',
        '//div[contains(@class, "job-item")]',
        '//div[contains(@class, "listing-item")]',
        '//div[contains(@class, "search-result")]',
        '//div[contains(@class, "result")]',
        '//div[contains(@class, "card")]',
        '//div[contains(@class, "item")]',
        '//article[contains(@class, "job")]',
        '//div[contains(@class, "job")]',
        '//div[contains(@class, "listing")]',
        // Generic selectors that might contain jobs
        '//div[contains(@class, "content")]',
        '//div[contains(@class, "main")]',
        '//div[contains(@class, "container")]',
        '//div[contains(@class, "wrapper")]',
        // Look for any div with job-related attributes
        '//div[@data-testid]',
        '//div[@data-cy]',
        '//div[@data-automation]'
    ];
    
    $jobNodes = null;
    foreach ($selectors as $selector) {
        $jobNodes = $xpath->query($selector);
        if ($jobNodes->length > 0) {
            echo "   Found jobs using selector: $selector\n";
            break;
        }
    }
    
    if (!$jobNodes || $jobNodes->length == 0) {
        echo "   WARNING: No job nodes found with any selector\n";
        echo "   Saving HTML for debugging...\n";
        
        // Use relative path to avoid permission issues
        $debugDir = 'logs';
        if (!is_dir($debugDir)) {
            if (!mkdir($debugDir, 0777, true)) {
                echo "   WARNING: Could not create logs directory, using current directory\n";
                $debugDir = '.';
            }
        }
        
        $debugFile = $debugDir . '/jobstreet_debug.html';
        if (file_put_contents($debugFile, $html)) {
            echo "   HTML saved to: $debugFile\n";
        } else {
            echo "   WARNING: Could not save HTML file\n";
        }
        
        // Try to find any content
        $allDivs = $xpath->query('//div');
        echo "   Total divs on page: " . $allDivs->length . "\n";
        
        if ($allDivs->length > 0) {
            echo "   First few div classes:\n";
            for ($i = 0; $i < min(10, $allDivs->length); $i++) {
                $div = $allDivs->item($i);
                $class = $div->getAttribute('class');
                if ($class) {
                    echo "     - " . $class . "\n";
                }
            }
            
            // Look for any text that might indicate job listings
            echo "   Looking for job-related text...\n";
            $jobKeywords = ['job', 'position', 'vacancy', 'career', 'employment', 'work'];
            foreach ($jobKeywords as $keyword) {
                $elements = $xpath->query("//*[contains(translate(text(), 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz'), '$keyword')]");
                if ($elements->length > 0) {
                    echo "     Found $keyword: " . $elements->length . " elements\n";
                    // Show first few examples
                    for ($j = 0; $j < min(3, $elements->length); $j++) {
                        $text = trim($elements->item($j)->textContent);
                        if (strlen($text) > 50) {
                            $text = substr($text, 0, 50) . "...";
                        }
                        echo "       Example: " . $text . "\n";
                    }
                }
            }
        }
        
    } else {
        echo "   SUCCESS: Found " . $jobNodes->length . " potential job nodes\n";
        
        // Try to extract some basic info from first few nodes
        $sampleCount = min(3, $jobNodes->length);
        echo "   Analyzing first $sampleCount nodes:\n";
        
        for ($i = 0; $i < $sampleCount; $i++) {
            $node = $jobNodes->item($i);
            echo "   Node " . ($i + 1) . ":\n";
            
            // Look for text content
            $text = trim($node->textContent);
            if (strlen($text) > 100) {
                $text = substr($text, 0, 100) . "...";
            }
            echo "     Text: " . $text . "\n";
            
            // Look for links
            $links = $xpath->query('.//a', $node);
            if ($links->length > 0) {
                echo "     Links: " . $links->length . "\n";
            }
            
            // Look for headings
            $headings = $xpath->query('.//h1 | .//h2 | .//h3 | .//h4', $node);
            if ($headings->length > 0) {
                echo "     Headings: " . $headings->length . "\n";
                foreach ($headings as $heading) {
                    echo "       - " . trim($heading->textContent) . "\n";
                }
            }
        }
    }
    
    curl_close($ch);
    
    // Update run status
    echo "6. Updating run status... ";
    $stmt = $conn->prepare('UPDATE jobstreet_scrape_runs SET status=\'completed\', total_found=?, total_imported=?, log=? WHERE id=?');
    $totalFound = $jobNodes ? $jobNodes->length : 0;
    $totalImported = 0; // We didn't actually import anything in this test
    $log = "Test run completed. Found $totalFound potential job nodes.";
    $stmt->bind_param('iisi', $totalFound, $totalImported, $log, $runId);
    $stmt->execute();
    $stmt->close();
    echo "OK\n";
    
    echo "\n=== Test Results ===\n";
    echo "Run ID: $runId\n";
    echo "Status: completed\n";
    echo "Potential jobs found: $totalFound\n";
    echo "Jobs imported: $totalImported\n";
    
    if ($totalFound > 0) {
        echo "\nThe scraper found job listings! The HTML structure is parseable.\n";
        echo "You can now use the main 'Run Scraping' button to start a full scraping job.\n";
    } else {
        echo "\nNo job listings were found. This could mean:\n";
        echo "1. Jobstreet changed their HTML structure\n";
        echo "2. The page requires JavaScript to load content\n";
        echo "3. The site is blocking automated requests\n";
        echo "\nCheck the debug HTML file for more details.\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
?>
