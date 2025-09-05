<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

if (!(current_user_can('asmen_use_qr') || current_user_can('asmen_manage_assets'))) { http_response_code(403); echo 'Forbidden'; exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>AsMen QR Scanner</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
	<style>
		#reader { width: 100%; max-width: 520px; margin: 0 auto; }
	</style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
	<div class="d-flex justify-content-between align-items-center mb-3">
		<h2 class="mb-0">AsMen - QR Scanner</h2>
		<div class="form-check form-switch">
			<input class="form-check-input" type="checkbox" id="bmnToggle">
			<label class="form-check-label" for="bmnToggle">Scan from BMN QR</label>
		</div>
	</div>
	<div class="card">
		<div class="card-body">
			<div id="reader"></div>
			<div class="mt-3 text-muted small">Allow camera permissions and point to the asset QR.</div>
		</div>
	</div>
</div>
<script>
function onScanSuccess(decodedText, decodedResult) {
	try {
		// If BMN mode enabled and content starts with '#', strip it
		var bmnMode = false;
		try { bmnMode = document.getElementById('bmnToggle')?.checked === true; } catch(e) {}
		if (bmnMode && typeof decodedText === 'string' && decodedText.startsWith('#')) {
			decodedText = decodedText.substring(1);
		}
		if (decodedText.includes('asmen_qr.php')) {
			window.location.href = decodedText;
			return;
		}
		// Fallback: treat as kode_register (new format) or legacy secret
		// First try kode_register: allow broad character set commonly used in registers
		if (/^[\w\-\.\/]+$/i.test(decodedText)) {
			window.location.href = 'asmen_qr.php?s=' + encodeURIComponent(decodedText);
			return;
		}
		// Legacy secret fallback (hex)
		if (/^[a-f0-9]{16,64}$/i.test(decodedText)) {
			window.location.href = 'asmen_qr.php?s=' + encodeURIComponent(decodedText);
			return;
		}
		alert('QR not recognized for AsMen');
	} catch (e) { console.error(e); }
}

function onScanFailure(error) { /* ignore continuous errors */ }

document.addEventListener('DOMContentLoaded', function() {
	const html5QrCode = new Html5Qrcode('reader');
	const config = { fps: 10, qrbox: 280 };
	html5QrCode
		.start({ facingMode: { exact: 'environment' } }, config, onScanSuccess, onScanFailure)
		.catch(err => {
			return html5QrCode.start({ facingMode: 'environment' }, config, onScanSuccess, onScanFailure);
		})
		.catch(err => {
			Html5Qrcode.getCameras().then(cameras => {
				let backCam = null;
				if (cameras && cameras.length) {
					backCam = cameras.find(c => /back|rear|environment|world/i.test(c.label)) || cameras[cameras.length - 1];
				}
				const camId = backCam ? backCam.id : null;
				if (!camId) { alert('No camera found'); return; }
				html5QrCode.start(camId, config, onScanSuccess, onScanFailure).catch(e => { console.error(e); alert('Unable to access camera'); });
			}).catch(e => { console.error(e); alert('Unable to access camera'); });
		});
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


