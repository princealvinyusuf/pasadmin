<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';

// Allow viewing if user can view any dashboard or manage settings
if (!(current_user_can('view_dashboard_kebutuhan_tk') || current_user_can('view_dashboard_persediaan_tk') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// Ensure storage table exists in job_admin_prod
try {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS `tableau_dashboards` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `iframe_code` LONGTEXT NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX `idx_name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
} catch (Throwable $e) {
    // If table creation fails, continue; page will show warnings
}

function fetchIframeByName(mysqli $conn, string $dashboardName): ?string {
    try {
        $sql = "SELECT iframe_code FROM `tableau_dashboards` WHERE name = ? ORDER BY id DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) { return null; }
        $stmt->bind_param('s', $dashboardName);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if ($row && isset($row['iframe_code']) && trim((string)$row['iframe_code']) !== '') {
            return (string)$row['iframe_code'];
        }
    } catch (Throwable $e) {
        return null;
    }
    return null;
}

$iframeKebutuhan = fetchIframeByName($conn, 'Dashboard Kebutuhan Tenaga Kerja');
$iframePersediaan = fetchIframeByName($conn, 'Dashboard Persediaan Tenaga Kerja');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Tenaga Kerja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f6f8fa; }
        .dash-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 18px rgba(0,0,0,0.06); }
        .dash-card .card-header { background: #ffffff; border-bottom: 1px solid #e5e7eb; }
        .iframe-container { position: relative; width: 100%; min-height: 480px; }
        .divider { margin: 24px 0; border-top: 2px dashed #cbd5e1; }
    </style>
    <script type="text/javascript" src="https://public.tableau.com/javascripts/api/tableau.v1.js" defer></script>
    <script>
        // Resize common Tableau placeholder embeds if present in stored HTML
        document.addEventListener('DOMContentLoaded', function() {
            var placeholders = document.querySelectorAll('.tableauPlaceholder');
            placeholders.forEach(function(ph){
                var obj = ph.querySelector('object');
                if (obj) {
                    obj.style.width = '100%';
                    obj.style.height = '720px';
                }
                ph.style.width = '100%';
                ph.style.minHeight = '480px';
            });
        });
    </script>
    <meta http-equiv="Content-Security-Policy" content="frame-ancestors *;">
    <!-- Allow embedding trusted Tableau iframes stored in DB -->
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <h2 class="mb-3">Dashboard Tenaga Kerja</h2>

    <div class="card dash-card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Dashboard Kebutuhan Tenaga Kerja</h5>
        </div>
        <div class="card-body">
            <?php if ($iframeKebutuhan): ?>
                <div class="iframe-container">
                    <?php echo $iframeKebutuhan; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mb-0">Iframe for "Dashboard Kebutuhan Tenaga Kerja" not found in database.</div>
            <?php endif; ?>
        </div>
    </div>

    <hr class="divider">

    <div class="card dash-card mb-4">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Dashboard Persediaan Tenaga Kerja</h5>
        </div>
        <div class="card-body">
            <?php if ($iframePersediaan): ?>
                <div class="iframe-container">
                    <?php echo $iframePersediaan; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mb-0">Iframe for "Dashboard Persediaan Tenaga Kerja" not found in database.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


