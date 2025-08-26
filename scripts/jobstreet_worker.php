<?php
// Background worker: Scrape real job data from Jobstreet Indonesia
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$runId = intval($argv[1] ?? 0);
if ($runId <= 0) { fwrite(STDERR, "Missing run id\n"); exit(1); }

require_once __DIR__ . '/../db.php';

// Mark as running
$stmt = $conn->prepare('UPDATE jobstreet_scrape_runs SET status=\'running\' WHERE id=?');
$stmt->bind_param('i', $runId);
$stmt->execute();
$stmt->close();

// Function to scrape Jobstreet
function scrapeJobstreet($conn, $runId) {
    $baseUrl = 'https://id.jobstreet.com/id/jobs';
    $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    $totalFound = 0;
    $totalImported = 0;
    $log = [];
    
    try {
        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
        
        // Scrape multiple pages (Jobstreet shows pagination)
        for ($page = 1; $page <= 5; $page++) { // Limit to 5 pages for now
            $url = $baseUrl;
            if ($page > 1) {
                $url .= "?page=" . $page;
            }
            
            $log[] = "Scraping page $page: $url";
            
            curl_setopt($ch, CURLOPT_URL, $url);
            $html = curl_exec($ch);
            
            if (!$html) {
                $log[] = "Failed to fetch page $page: " . curl_error($ch);
                continue;
            }
            
            // Parse HTML to extract job listings
            $jobs = parseJobstreetHTML($html);
            $log[] = "Found " . count($jobs) . " jobs on page $page";
            
            // Import jobs to database
            foreach ($jobs as $job) {
                if (importJobToDatabase($conn, $job)) {
                    $totalImported++;
                }
                $totalFound++;
            }
            
            // Be respectful - add delay between requests
            sleep(2);
        }
        
        curl_close($ch);
        
        // Update run with results
        $stmt = $conn->prepare('UPDATE jobstreet_scrape_runs SET status=\'completed\', total_found=?, total_imported=?, log=? WHERE id=?');
        $logText = implode("\n", $log);
        $stmt->bind_param('iisi', $totalFound, $totalImported, $logText, $runId);
        $stmt->execute();
        $stmt->close();
        
        return true;
        
    } catch (Exception $e) {
        $log[] = "Error: " . $e->getMessage();
        $stmt = $conn->prepare('UPDATE jobstreet_scrape_runs SET status=\'failed\', log=? WHERE id=?');
        $logText = implode("\n", $log);
        $stmt->bind_param('si', $logText, $runId);
        $stmt->execute();
        $stmt->close();
        return false;
    }
}

// Function to parse Jobstreet HTML and extract job data
function parseJobstreetHTML($html) {
    $jobs = [];
    
    // Use DOMDocument to parse HTML
    $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($dom);
    
    // Find job listing containers
    // Jobstreet job listings are typically in article tags or divs with specific classes
    $jobNodes = $xpath->query('//article[contains(@class, "job")] | //div[contains(@class, "job")] | //div[contains(@class, "listing")]');
    
    if ($jobNodes->length == 0) {
        // Try alternative selectors if the above doesn't work
        $jobNodes = $xpath->query('//div[contains(@class, "job-card")] | //div[contains(@class, "job-item")] | //div[contains(@class, "listing-item")]');
    }
    
    foreach ($jobNodes as $jobNode) {
        $job = [];
        
        // Extract job title
        $titleNodes = $xpath->query('.//h1 | .//h2 | .//h3 | .//div[contains(@class, "title")]', $jobNode);
        if ($titleNodes->length > 0) {
            $job['nama_jabatan'] = trim($titleNodes->item(0)->textContent);
        }
        
        // Extract company name
        $companyNodes = $xpath->query('.//div[contains(@class, "company")] | .//span[contains(@class, "company")] | .//a[contains(@class, "company")]', $jobNode);
        if ($companyNodes->length > 0) {
            $job['nama_perusahaan'] = trim($companyNodes->item(0)->textContent);
        }
        
        // Extract location
        $locationNodes = $xpath->query('.//div[contains(@class, "location")] | .//span[contains(@class, "location")] | .//div[contains(@class, "area")]', $jobNode);
        if ($locationNodes->length > 0) {
            $location = trim($locationNodes->item(0)->textContent);
            $job['provinsi'] = extractProvince($location);
            $job['kab_kota'] = extractCity($location);
        }
        
        // Extract salary
        $salaryNodes = $xpath->query('.//div[contains(@class, "salary")] | .//span[contains(@class, "salary")] | .//div[contains(@class, "gaji")]', $jobNode);
        if ($salaryNodes->length > 0) {
            $salary = trim($salaryNodes->item(0)->textContent);
            $job['gaji_minimum'] = extractMinSalary($salary);
            $job['gaji_maksimum'] = extractMaxSalary($salary);
        }
        
        // Extract job type
        $typeNodes = $xpath->query('.//div[contains(@class, "type")] | .//span[contains(@class, "type")] | .//div[contains(@class, "employment")]', $jobNode);
        if ($typeNodes->length > 0) {
            $job['tipe_pekerjaan'] = trim($typeNodes->item(0)->textContent);
        }
        
        // Extract classification
        $classNodes = $xpath->query('.//div[contains(@class, "classification")] | .//span[contains(@class, "classification")] | .//div[contains(@class, "category")]', $jobNode);
        if ($classNodes->length > 0) {
            $job['bidang_industri'] = trim($classNodes->item(0)->textContent);
        }
        
        // Extract posted date
        $dateNodes = $xpath->query('.//div[contains(@class, "date")] | .//span[contains(@class, "date")] | .//div[contains(@class, "posted")]', $jobNode);
        if ($dateNodes->length > 0) {
            $job['tanggal_posting'] = parsePostedDate(trim($dateNodes->item(0)->textContent));
        }
        
        // Set platform
        $job['platform_lowongan'] = 'Jobstreet';
        
        // Only add job if we have at least a title
        if (!empty($job['nama_jabatan'])) {
            $jobs[] = $job;
        }
    }
    
    return $jobs;
}

// Helper function to extract province from location string
function extractProvince($location) {
    $provinces = [
        'Jakarta Raya', 'Jawa Barat', 'Jawa Tengah', 'Jawa Timur', 'Banten',
        'Sumatera Utara', 'Sumatera Barat', 'Sumatera Selatan', 'Lampung',
        'Kalimantan Barat', 'Kalimantan Tengah', 'Kalimantan Selatan', 'Kalimantan Timur',
        'Sulawesi Utara', 'Sulawesi Tengah', 'Sulawesi Selatan', 'Sulawesi Tenggara',
        'Bali', 'Nusa Tenggara Barat', 'Nusa Tenggara Timur', 'Maluku', 'Papua'
    ];
    
    foreach ($provinces as $province) {
        if (stripos($location, $province) !== false) {
            return $province;
        }
    }
    
    return $location; // Return full location if no province found
}

// Helper function to extract city from location string
function extractCity($location) {
    // Common cities in Indonesia
    $cities = [
        'Jakarta Utara', 'Jakarta Selatan', 'Jakarta Barat', 'Jakarta Timur', 'Jakarta Pusat',
        'Bandung', 'Surabaya', 'Medan', 'Semarang', 'Yogyakarta', 'Malang', 'Palembang',
        'Tangerang', 'Tangerang Selatan', 'Bekasi', 'Depok', 'Bogor', 'Cibitung'
    ];
    
    foreach ($cities as $city) {
        if (stripos($location, $city) !== false) {
            return $city;
        }
    }
    
    return ''; // Return empty if no city found
}

// Helper function to extract minimum salary
function extractMinSalary($salary) {
    if (preg_match('/Rp\s*([0-9,.]+)/', $salary, $matches)) {
        $amount = str_replace(['.', ','], '', $matches[1]);
        return 'Rp ' . number_format(intval($amount), 0, ',', '.');
    }
    return '';
}

// Helper function to extract maximum salary
function extractMaxSalary($salary) {
    if (preg_match('/Rp\s*[0-9,.]+\s*–\s*Rp\s*([0-9,.]+)/', $salary, $matches)) {
        $amount = str_replace(['.', ','], '', $matches[1]);
        return 'Rp ' . number_format(intval($amount), 0, ',', '.');
    }
    return '';
}

// Helper function to parse posted date
function parsePostedDate($dateStr) {
    $dateStr = strtolower(trim($dateStr));
    
    if (strpos($dateStr, 'hari ini') !== false) {
        return date('Y-m-d');
    } elseif (strpos($dateStr, 'kemarin') !== false) {
        return date('Y-m-d', strtotime('-1 day'));
    } elseif (preg_match('/(\d+)\s*hari\s*yang\s*lalu/', $dateStr, $matches)) {
        $days = intval($matches[1]);
        return date('Y-m-d', strtotime("-$days days"));
    } elseif (preg_match('/(\d+)\s*jam\s*yang\s*lalu/', $dateStr, $matches)) {
        $hours = intval($matches[1]);
        return date('Y-m-d', strtotime("-$hours hours"));
    }
    
    return date('Y-m-d'); // Default to today
}

// Function to import job to database
function importJobToDatabase($conn, $job) {
    // Check if job already exists (avoid duplicates)
    $stmt = $conn->prepare('SELECT id FROM jobs WHERE nama_jabatan = ? AND nama_perusahaan = ? AND platform_lowongan = ?');
    $stmt->bind_param('sss', $job['nama_jabatan'], $job['nama_perusahaan'], $job['platform_lowongan']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return false; // Job already exists
    }
    
    // Prepare insert statement
    $fields = array_keys($job);
    $placeholders = implode(', ', array_fill(0, count($fields), '?'));
    $fieldNames = implode(', ', $fields);
    $types = str_repeat('s', count($fields));
    
    $sql = "INSERT INTO jobs ($fieldNames) VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);
    
    $values = array_values($job);
    $stmt->bind_param($types, ...$values);
    
    return $stmt->execute();
}

// Run the scraping
scrapeJobstreet($conn, $runId);

exit(0);
?>

