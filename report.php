<?php
require_once __DIR__ . '/auth_guard.php';

// Basic CSRF token using session
session_start();
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf_token'];

$statusMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        $statusMessage = 'Invalid CSRF token';
    } else {
        $url = trim($_POST['url'] ?? '');
        $groupJid = trim($_POST['group_jid'] ?? '');
        if ($url === '' || $groupJid === '') {
            $statusMessage = 'URL and Group JID are required';
        } else {
            $env = [
                'REPORT_URL' => $url,
                'WA_GROUP_JID' => $groupJid
            ];
            $nodePath = 'node';
            $senderDir = __DIR__ . '/whatsapp-sender';

            // Ensure writable config/cache dirs for Puppeteer
            $configDir = $senderDir . '/.config';
            $cacheDir = $senderDir . '/.cache/puppeteer';
            $reportOutDir = $senderDir . '/reports';
            if (!is_dir($configDir)) @mkdir($configDir, 0775, true);
            if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
            if (!is_dir($reportOutDir)) @mkdir($reportOutDir, 0775, true);

            $puppeteerExec = getenv('PUPPETEER_EXECUTABLE_PATH');
            if ($puppeteerExec === false || $puppeteerExec === '') {
                $puppeteerExec = '/usr/bin/chromium';
            }

            $cmd = 'cd ' . escapeshellarg($senderDir) . ' && ' .
                'LD_LIBRARY_PATH= ' .
                'LIBRARY_PATH= ' .
                'LD_PRELOAD= ' .
                'XDG_CONFIG_HOME=' . escapeshellarg($configDir) . ' ' .
                'PUPPETEER_CACHE_DIR=' . escapeshellarg($cacheDir) . ' ' .
                'PUPPETEER_EXECUTABLE_PATH=' . escapeshellarg($puppeteerExec) . ' ' .
                'REPORT_OUTPUT_DIR=' . escapeshellarg($reportOutDir) . ' ' .
                'REPORT_URL=' . escapeshellarg($env['REPORT_URL']) . ' ' .
                'WA_GROUP_JID=' . escapeshellarg($env['WA_GROUP_JID']) . ' ' .
                $nodePath . ' send_report.js 2>&1';
            $output = [];
            $exitCode = 0;
            exec($cmd, $output, $exitCode);
            $statusMessage = $exitCode === 0 ? 'Report sent successfully.' : 'Failed to send report. See output below.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report | Job Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: 12px; }
    </style>
    <script>
        function setDefaultUrl() {
            const inp = document.getElementById('url');
            if (!inp.value) inp.value = 'https://paskerid.kemnaker.go.id/paskerid/public/';
        }
    </script>
    </head>
<body class="bg-light" onload="setDefaultUrl()">
    <?php include 'navbar.php'; ?>
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h1 class="h4 mb-3"><i class="bi bi-graph-up me-2"></i>Report</h1>
                        <p class="text-muted">Capture a web page as an image and send it to a WhatsApp group.</p>
                        <?php if (!empty($statusMessage)): ?>
                            <div class="alert <?php echo ($statusMessage === 'Report sent successfully.') ? 'alert-success' : 'alert-warning'; ?>" role="alert">
                                <?php echo htmlspecialchars($statusMessage); ?>
                            </div>
                        <?php endif; ?>
                        <form method="post" class="row g-3">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                            <div class="col-12">
                                <label for="url" class="form-label">Target URL</label>
                                <input id="url" name="url" type="url" class="form-control" placeholder="https://example.com" value="<?php echo htmlspecialchars($_POST['url'] ?? ''); ?>" required>
                            </div>
                            <div class="col-12">
                                <label for="group_jid" class="form-label">WhatsApp Group JID</label>
                                <input id="group_jid" name="group_jid" type="text" class="form-control" placeholder="1203xxxxxxxxxxxx@g.us" value="<?php echo htmlspecialchars($_POST['group_jid'] ?? ''); ?>" required>
                                <div class="form-text">To find Group JID, run the sender once; it logs group names and JIDs.</div>
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary" type="submit"><i class="bi bi-send me-1"></i>Send Report</button>
                            </div>
                        </form>
                        <?php if (!empty($output)): ?>
                        <hr>
                        <div class="mono">
<pre class="mono mb-0"><?php echo htmlspecialchars(implode("\n", $output)); ?></pre>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


