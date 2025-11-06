<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';

// Get URLs from query parameters or use defaults
$url1 = isset($_GET['url1']) ? $_GET['url1'] : 'https://paskerid.kemnaker.go.id/paske';
$url2 = isset($_GET['url2']) ? $_GET['url2'] : 'https://karirhub.kemnaker.go.id/';
$url3 = isset($_GET['url3']) ? $_GET['url3'] : '';
$url4 = isset($_GET['url4']) ? $_GET['url4'] : '';

// Get split ratios (for vertical and horizontal splits)
$verticalRatio = isset($_GET['vratio']) ? floatval($_GET['vratio']) : 50.0;
$horizontalRatio = isset($_GET['hratio']) ? floatval($_GET['hratio']) : 50.0;

// Ensure ratios are valid (between 0 and 100)
$verticalRatio = max(0, min(100, $verticalRatio));
$horizontalRatio = max(0, min(100, $horizontalRatio));

// Calculate percentages
$leftPercent = $verticalRatio;
$rightPercent = 100 - $verticalRatio;
$topPercent = $horizontalRatio;
$bottomPercent = 100 - $horizontalRatio;
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Split Screen Tool</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<style>
		body {
			background: #f6f8fa;
			overflow: hidden;
		}
		.split-container {
			position: fixed;
			top: 56px;
			left: 0;
			right: 0;
			bottom: 0;
			display: flex;
			flex-direction: column;
		}
		.split-row {
			display: flex;
			width: 100%;
			height: 100%;
		}
		.split-pane {
			border: 2px solid #ddd;
			position: relative;
			overflow: hidden;
			background: #fff;
		}
		.split-pane iframe {
			width: 100%;
			height: 100%;
			border: none;
		}
		.control-panel {
			position: fixed;
			top: 56px;
			left: 0;
			right: 0;
			background: white;
			padding: 15px;
			box-shadow: 0 2px 4px rgba(0,0,0,0.1);
			z-index: 1000;
			display: none;
		}
		.control-panel.show {
			display: block;
		}
		.toggle-controls {
			position: fixed;
			top: 70px;
			right: 20px;
			z-index: 1001;
			background: #0d6efd;
			color: white;
			border: none;
			border-radius: 50%;
			width: 40px;
			height: 40px;
			display: flex;
			align-items: center;
			justify-content: center;
			cursor: pointer;
			box-shadow: 0 2px 4px rgba(0,0,0,0.2);
		}
		.toggle-controls:hover {
			background: #0b5ed7;
		}
		.input-group-url {
			margin-bottom: 10px;
		}
		.split-ratio-input {
			width: 80px;
		}
		.split-icon {
			cursor: pointer;
			padding: 5px;
			border: 2px solid #ddd;
			border-radius: 4px;
			display: inline-block;
			margin-right: 10px;
		}
		.split-icon.active {
			border-color: #0d6efd;
			background-color: #e7f1ff;
		}
		.split-icon svg {
			display: block;
		}
	</style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>

<button class="toggle-controls" onclick="toggleControls()" title="Toggle Controls">
	<i class="bi bi-gear"></i>
</button>

<div class="control-panel" id="controlPanel">
	<div class="container">
		<h5 class="mb-3">Split Screen Tool</h5>
		<p class="text-muted small mb-3">Enter the URLs of four different web sites that you would like to display. After clicking the View button, copy the browser's URL and use that as the URL for your test. (Some websites won't work with this tool as they block being displayed within a frame.)</p>
		
		<form method="get" id="splitForm">
			<div class="row">
				<div class="col-md-6">
					<div class="input-group-url">
						<label class="form-label">URL1:</label>
						<input class="form-control" type="url" name="url1" value="<?php echo htmlspecialchars($url1); ?>" placeholder="https://example.com">
					</div>
				</div>
				<div class="col-md-6">
					<div class="input-group-url">
						<label class="form-label">URL2:</label>
						<input class="form-control" type="url" name="url2" value="<?php echo htmlspecialchars($url2); ?>" placeholder="https://example.com">
					</div>
				</div>
				<div class="col-md-6">
					<div class="input-group-url">
						<label class="form-label">URL3:</label>
						<input class="form-control" type="url" name="url3" value="<?php echo htmlspecialchars($url3); ?>" placeholder="https://example.com">
					</div>
				</div>
				<div class="col-md-6">
					<div class="input-group-url">
						<label class="form-label">URL4:</label>
						<input class="form-control" type="url" name="url4" value="<?php echo htmlspecialchars($url4); ?>" placeholder="https://example.com">
					</div>
				</div>
			</div>
			
			<div class="row mt-3">
				<div class="col-md-6">
					<label class="form-label">Vertical Split:</label>
					<div class="d-flex align-items-center">
						<input class="form-control split-ratio-input" type="number" name="vratio" value="<?php echo $verticalRatio; ?>" min="0" max="100" step="1">
						<span class="mx-2">/</span>
						<input class="form-control split-ratio-input" type="number" value="<?php echo 100 - $verticalRatio; ?>" disabled>
						<span class="ms-2 text-muted">%</span>
					</div>
				</div>
				<div class="col-md-6">
					<label class="form-label">Horizontal Split:</label>
					<div class="d-flex align-items-center">
						<input class="form-control split-ratio-input" type="number" name="hratio" value="<?php echo $horizontalRatio; ?>" min="0" max="100" step="1">
						<span class="mx-2">/</span>
						<input class="form-control split-ratio-input" type="number" value="<?php echo 100 - $horizontalRatio; ?>" disabled>
						<span class="ms-2 text-muted">%</span>
					</div>
				</div>
			</div>
			
			<div class="mt-3">
				<button class="btn btn-primary" type="submit">
					<i class="bi bi-eye me-1"></i>View
				</button>
				<button class="btn btn-secondary ms-2" type="button" onclick="hideControls()">
					Hide Controls
				</button>
			</div>
		</form>
	</div>
</div>

<div class="split-container" id="splitContainer">
	<div class="split-row" style="height: <?php echo $topPercent; ?>%;">
		<div class="split-pane" style="width: <?php echo $leftPercent; ?>%;">
			<?php if (!empty($url1)): ?>
				<iframe src="<?php echo htmlspecialchars($url1); ?>" title="URL1"></iframe>
			<?php else: ?>
				<div class="d-flex align-items-center justify-content-center h-100 text-muted">
					<div class="text-center">
						<i class="bi bi-browser display-4"></i>
						<p class="mt-2">No URL specified</p>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<div class="split-pane" style="width: <?php echo $rightPercent; ?>%;">
			<?php if (!empty($url2)): ?>
				<iframe src="<?php echo htmlspecialchars($url2); ?>" title="URL2"></iframe>
			<?php else: ?>
				<div class="d-flex align-items-center justify-content-center h-100 text-muted">
					<div class="text-center">
						<i class="bi bi-browser display-4"></i>
						<p class="mt-2">No URL specified</p>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<div class="split-row" style="height: <?php echo $bottomPercent; ?>%;">
		<div class="split-pane" style="width: <?php echo $leftPercent; ?>%;">
			<?php if (!empty($url3)): ?>
				<iframe src="<?php echo htmlspecialchars($url3); ?>" title="URL3"></iframe>
			<?php else: ?>
				<div class="d-flex align-items-center justify-content-center h-100 text-muted">
					<div class="text-center">
						<i class="bi bi-browser display-4"></i>
						<p class="mt-2">No URL specified</p>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<div class="split-pane" style="width: <?php echo $rightPercent; ?>%;">
			<?php if (!empty($url4)): ?>
				<iframe src="<?php echo htmlspecialchars($url4); ?>" title="URL4"></iframe>
			<?php else: ?>
				<div class="d-flex align-items-center justify-content-center h-100 text-muted">
					<div class="text-center">
						<i class="bi bi-browser display-4"></i>
						<p class="mt-2">No URL specified</p>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
	// Adjust split container position based on control panel visibility
	function updateSplitContainer() {
		const panel = document.getElementById('controlPanel');
		const container = document.getElementById('splitContainer');
		if (panel.classList.contains('show')) {
			const panelHeight = panel.offsetHeight;
			container.style.top = (56 + panelHeight) + 'px';
		} else {
			container.style.top = '56px';
		}
	}
	
	function toggleControls() {
		const panel = document.getElementById('controlPanel');
		panel.classList.toggle('show');
		setTimeout(updateSplitContainer, 100);
	}
	
	function hideControls() {
		const panel = document.getElementById('controlPanel');
		panel.classList.remove('show');
		setTimeout(updateSplitContainer, 100);
	}
	
	// Auto-show controls if no URLs are set
	<?php if (empty($url1) && empty($url2) && empty($url3) && empty($url4)): ?>
		document.getElementById('controlPanel').classList.add('show');
	<?php endif; ?>
	
	// Initial update and setup
	window.addEventListener('load', function() {
		updateSplitContainer();
	});
	window.addEventListener('resize', updateSplitContainer);
	
	// Update disabled ratio inputs when user changes the ratio
	document.querySelector('input[name="vratio"]').addEventListener('input', function(e) {
		const value = parseFloat(e.target.value) || 0;
		const disabledInput = e.target.parentElement.querySelector('input[disabled]');
		if (disabledInput) {
			disabledInput.value = (100 - value).toFixed(0);
		}
	});
	
	document.querySelector('input[name="hratio"]').addEventListener('input', function(e) {
		const value = parseFloat(e.target.value) || 0;
		const disabledInput = e.target.parentElement.querySelector('input[disabled]');
		if (disabledInput) {
			disabledInput.value = (100 - value).toFixed(0);
		}
	});
</script>
</body>
</html>

