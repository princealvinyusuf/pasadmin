<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';
if (!current_user_can('manage_jobstreet_scraping')) { http_response_code(403); echo 'Forbidden'; exit; }

// Ensure table for scrape runs exists (idempotent)
$conn->query("CREATE TABLE IF NOT EXISTS jobstreet_scrape_runs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(32) NOT NULL DEFAULT 'queued',
    total_found INT DEFAULT 0,
    total_imported INT DEFAULT 0,
    log TEXT NULL,
    output_file VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Fetch recent runs
$runs = [];
$res = $conn->query('SELECT * FROM jobstreet_scrape_runs ORDER BY id DESC LIMIT 50');
while ($row = $res->fetch_assoc()) { $runs[] = $row; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jobstreet Scrapping</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f6f8fa; }
        .table thead th { background: #f1f5f9; }
        .status-badge { text-transform: capitalize; }
        .log-text { 
            max-height: 200px; 
            overflow-y: auto; 
            font-family: monospace; 
            font-size: 0.85em; 
            background: #f8f9fa; 
            padding: 10px; 
            border-radius: 4px; 
        }
        .status-queued { color: #6c757d; }
        .status-running { color: #fd7e14; }
        .status-completed { color: #198754; }
        .status-failed { color: #dc3545; }
    </style>
    <script>
        async function runScrape() {
            const btn = document.getElementById('run-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Running...';
            
            try {
                const res = await fetch('api/jobstreet_scrape.php', { method: 'POST' });
                const data = await res.json();
                if (!res.ok) { throw new Error(data.error || 'Failed'); }
                
                // Show success message
                showAlert('Scraping started successfully! Check the table below for progress.', 'success');
                
                // Refresh the page after a short delay to show the new run
                setTimeout(() => window.location.reload(), 2000);
                
            } catch (e) {
                showAlert('Failed to start scraping: ' + e.message, 'danger');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-play-fill"></i> Run Scraping';
            }
        }
        
        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const container = document.querySelector('.container');
            container.insertBefore(alertDiv, container.firstChild);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }
        
        function toggleLog(logId) {
            const logElement = document.getElementById(logId);
            if (logElement.style.display === 'none') {
                logElement.style.display = 'block';
            } else {
                logElement.style.display = 'none';
            }
        }
        
        function downloadScrapedData() {
            // Create a CSV export of all Jobstreet jobs
            window.location.href = 'export_jobstreet_jobs.php';
        }
        
        async function testScraper() {
            const btn = document.querySelector('button[onclick="testScraper()"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Testing...';
            
            try {
                const res = await fetch('test_scraper.php');
                const text = await res.text();
                
                // Show results in an alert
                alert('Test Results:\n\n' + text);
                
                // Refresh the page to show new data
                window.location.reload();
                
            } catch (e) {
                alert('Test failed: ' + e.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-bug"></i> Test Scraper';
            }
        }
        
        async function testScraper() {
            const btn = document.querySelector('button[onclick="testScraper()"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Testing...';
            
            try {
                const res = await fetch('test_scraper.php');
                const text = await res.text();
                
                // Show results in an alert
                alert('Test Results:\n\n' + text);
                
                // Refresh the page to show new data
                window.location.reload();
                
            } catch (e) {
                alert('Test failed: ' + e.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-bug"></i> Test Scraper';
            }
        }
    </script>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Jobstreet Scrapping</h2>
        <div class="d-flex gap-2">
            <button id="run-btn" class="btn btn-primary" onclick="runScrape()">
                <i class="bi bi-play-fill"></i> Run Scraping
            </button>
            <button class="btn btn-warning" onclick="testScraper()">
                <i class="bi bi-bug"></i> Test Scraper
            </button>
            <button class="btn btn-success" onclick="downloadScrapedData()">
                <i class="bi bi-download"></i> Download All Jobstreet Jobs
            </button>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Type</th>
                        <th>Progress</th>
                        <th>Stats</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($runs as $r): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?php echo date('M d, y H:i', strtotime($r['created_at'])); ?></div>
                            <small class="text-muted">ID: <?php echo $r['id']; ?></small>
                        </td>
                        <td>
                            <span class="badge bg-primary">Jobstreet</span>
                        </td>
                        <td>
                            <?php
                                $status = $r['status'];
                                $statusClass = 'status-' . $status;
                                $progress = $status === 'completed' ? '100.0' : ($status === 'running' ? '50.0' : '0.0');
                            ?>
                            <div class="d-flex align-items-center">
                                <div class="progress me-2" style="width: 60px; height: 6px;">
                                    <div class="progress-bar" style="width: <?php echo $progress; ?>%"></div>
                                </div>
                                <span class="<?php echo $statusClass; ?> fw-semibold"><?php echo ucfirst($status); ?></span>
                            </div>
                        </td>
                        <td>
                            <?php if ($r['total_found'] > 0 || $r['total_imported'] > 0): ?>
                                <div class="small">
                                    <div>Found: <strong><?php echo $r['total_found']; ?></strong></div>
                                    <div>Imported: <strong><?php echo $r['total_imported']; ?></strong></div>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <?php if (!empty($r['log'])): ?>
                                    <button class="btn btn-outline-info btn-sm" onclick="toggleLog('log-<?php echo $r['id']; ?>')">
                                        <i class="bi bi-info-circle"></i> Log
                                    </button>
                                <?php endif; ?>
                                <?php if ($r['status'] === 'completed' && $r['total_imported'] > 0): ?>
                                    <a class="btn btn-outline-success btn-sm" href="export_jobstreet_jobs.php?run_id=<?php echo $r['id']; ?>" download>
                                        <i class="bi bi-file-earmark-excel"></i> Export
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php if (!empty($r['log'])): ?>
                    <tr id="log-<?php echo $r['id']; ?>" style="display: none;">
                        <td colspan="5">
                            <div class="log-text"><?php echo nl2br(htmlspecialchars($r['log'])); ?></div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-body text-muted small">
            Shows last 50 runs. Click "Log" to view scraping details.
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

