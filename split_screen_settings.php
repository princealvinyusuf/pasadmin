<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';

if (!(current_user_can('manage_settings'))) { 
    http_response_code(403); 
    echo 'Forbidden'; 
    exit; 
}

// Ensure split_screen_settings table exists
$conn->query("CREATE TABLE IF NOT EXISTS split_screen_settings (
    id INT PRIMARY KEY DEFAULT 1,
    url1 VARCHAR(500) NOT NULL DEFAULT 'https://paskerid.kemnaker.go.id/paske',
    url2 VARCHAR(500) NOT NULL DEFAULT 'https://karirhub.kemnaker.go.id/',
    url3 VARCHAR(500) NOT NULL DEFAULT '',
    url4 VARCHAR(500) NOT NULL DEFAULT '',
    vertical_ratio DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    horizontal_ratio DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Initialize default row if not exists
$res = $conn->query('SELECT COUNT(*) AS c FROM split_screen_settings WHERE id=1');
$row = $res ? $res->fetch_assoc() : ['c' => 0];
if (intval($row['c'] ?? 0) === 0) {
    $conn->query("INSERT INTO split_screen_settings (id) VALUES (1)");
}

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $url1 = trim($_POST['url1'] ?? '');
    $url2 = trim($_POST['url2'] ?? '');
    $url3 = trim($_POST['url3'] ?? '');
    $url4 = trim($_POST['url4'] ?? '');
    $verticalRatio = max(0, min(100, floatval($_POST['vertical_ratio'] ?? 50.0)));
    $horizontalRatio = max(0, min(100, floatval($_POST['horizontal_ratio'] ?? 50.0)));
    
    // Validate URLs (basic validation)
    // Note: YouTube URLs are valid even if they don't pass filter_var check
    $urls = [$url1, $url2, $url3, $url4];
    $invalidUrls = [];
    foreach ($urls as $index => $url) {
        if ($url !== '') {
            // Check if it's a YouTube URL (more lenient validation)
            $isYoutube = (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false);
            // If not YouTube and fails standard URL validation, mark as invalid
            if (!$isYoutube && !filter_var($url, FILTER_VALIDATE_URL)) {
                $invalidUrls[] = 'URL' . ($index + 1);
            }
        }
    }
    
    if (!empty($invalidUrls)) {
        $error = 'Invalid URLs: ' . implode(', ', $invalidUrls) . '. Please enter valid URLs (starting with http:// or https://) or leave empty.';
    } else {
        $stmt = $conn->prepare('UPDATE split_screen_settings SET url1=?, url2=?, url3=?, url4=?, vertical_ratio=?, horizontal_ratio=? WHERE id=1');
        $stmt->bind_param('ssssdd', $url1, $url2, $url3, $url4, $verticalRatio, $horizontalRatio);
        $stmt->execute();
        $stmt->close();
        $message = 'Split screen settings updated successfully.';
    }
}

// Load current settings
$settingsQuery = $conn->query('SELECT url1, url2, url3, url4, vertical_ratio, horizontal_ratio FROM split_screen_settings WHERE id=1');
$settings = $settingsQuery ? $settingsQuery->fetch_assoc() : null;

if (!$settings) {
    // Initialize with defaults if no settings found
    $settings = [
        'url1' => 'https://paskerid.kemnaker.go.id/paske',
        'url2' => 'https://karirhub.kemnaker.go.id/',
        'url3' => '',
        'url4' => '',
        'vertical_ratio' => 50.00,
        'horizontal_ratio' => 50.00
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Split Screen Settings</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<style>
		body { background: #f6f8fa; }
	</style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
	<div class="d-flex justify-content-between align-items-center mb-3">
		<h2 class="mb-0">Split Screen Settings</h2>
		<a href="split_screen.php" class="btn btn-primary">
			<i class="bi bi-eye me-1"></i>View Split Screen
		</a>
	</div>

	<?php if ($message): ?>
		<div class="alert alert-success alert-dismissible fade show" role="alert">
			<?php echo htmlspecialchars($message); ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
	<?php endif; ?>

	<?php if ($error): ?>
		<div class="alert alert-danger alert-dismissible fade show" role="alert">
			<?php echo htmlspecialchars($error); ?>
			<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
		</div>
	<?php endif; ?>

	<div class="card">
		<div class="card-body">
			<h5 class="mb-3">Configure Split Screen URLs</h5>
			<p class="text-muted small mb-4">Configure the default URLs that will be displayed in the split screen tool. These URLs can be overridden using query parameters in the split screen page.</p>
			
			<form method="post">
				<input type="hidden" name="update" value="1">
				
				<div class="row">
					<div class="col-md-6 mb-3">
						<label class="form-label">URL 1 (Top Left)</label>
						<input class="form-control" type="url" name="url1" value="<?php echo htmlspecialchars($settings['url1']); ?>" placeholder="https://example.com">
						<div class="form-text">URL displayed in the top-left quadrant</div>
					</div>
					<div class="col-md-6 mb-3">
						<label class="form-label">URL 2 (Top Right)</label>
						<input class="form-control" type="url" name="url2" value="<?php echo htmlspecialchars($settings['url2']); ?>" placeholder="https://example.com">
						<div class="form-text">URL displayed in the top-right quadrant</div>
					</div>
					<div class="col-md-6 mb-3">
						<label class="form-label">URL 3 (Bottom Left)</label>
						<input class="form-control" type="url" name="url3" value="<?php echo htmlspecialchars($settings['url3']); ?>" placeholder="https://example.com">
						<div class="form-text">URL displayed in the bottom-left quadrant</div>
					</div>
					<div class="col-md-6 mb-3">
						<label class="form-label">URL 4 (Bottom Right)</label>
						<input class="form-control" type="url" name="url4" value="<?php echo htmlspecialchars($settings['url4']); ?>" placeholder="https://example.com or https://youtube.com/watch?v=...">
						<div class="form-text">URL displayed in the bottom-right quadrant. Supports YouTube URLs (will be auto-converted to embed format).</div>
					</div>
				</div>

				<hr class="my-4">

				<div class="row">
					<div class="col-md-6 mb-3">
						<label class="form-label">Vertical Split Ratio (%)</label>
						<input class="form-control" type="number" name="vertical_ratio" value="<?php echo htmlspecialchars($settings['vertical_ratio']); ?>" min="0" max="100" step="0.01">
						<div class="form-text">Percentage for left side (0-100). Right side will be automatically calculated.</div>
					</div>
					<div class="col-md-6 mb-3">
						<label class="form-label">Horizontal Split Ratio (%)</label>
						<input class="form-control" type="number" name="horizontal_ratio" value="<?php echo htmlspecialchars($settings['horizontal_ratio']); ?>" min="0" max="100" step="0.01">
						<div class="form-text">Percentage for top section (0-100). Bottom section will be automatically calculated.</div>
					</div>
				</div>

				<div class="mt-4">
					<button class="btn btn-primary" type="submit">
						<i class="bi bi-save me-1"></i>Save Settings
					</button>
					<a href="split_screen.php" class="btn btn-secondary ms-2">
						<i class="bi bi-x me-1"></i>Cancel
					</a>
				</div>
			</form>
		</div>
	</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

