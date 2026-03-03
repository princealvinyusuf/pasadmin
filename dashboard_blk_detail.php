<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/blk_dashboard_data.php';

if (!(current_user_can('view_dashboard_blk') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$dashboardData = blk_get_dashboard_data();
$itemId = $_GET['item'] ?? '';
$item = blk_find_item_by_id($dashboardData, $itemId);

if ($item === null) {
    http_response_code(404);
    echo 'Data detail tidak ditemukan.';
    exit;
}

$selectedPeriod = $_GET['period'] ?? '30 Hari';
$selectedLocation = $_GET['location'] ?? 'Semua Lokasi';
$selectedMajor = $_GET['major'] ?? 'Semua Kejuruan';
$selectedSource = $_GET['source'] ?? 'Semua Sumber';

$backQuery = http_build_query([
    'period' => $selectedPeriod,
    'location' => $selectedLocation,
    'major' => $selectedMajor,
    'source' => $selectedSource,
]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail BLK - <?php echo e($item['title'] ?? 'Data'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f5f7fb; }
        .page-card { border: 0; border-radius: 12px; box-shadow: 0 3px 14px rgba(15, 23, 42, 0.08); }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h3 class="mb-1"><?php echo e($item['table_title'] ?? $item['title'] ?? 'Detail Data'); ?></h3>
            <div class="text-muted small">
                Filter aktif: <?php echo e($selectedPeriod); ?> | <?php echo e($selectedLocation); ?> | <?php echo e($selectedMajor); ?> | <?php echo e($selectedSource); ?>
            </div>
        </div>
        <a href="dashboard_blk.php?<?php echo e($backQuery); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard BLK
        </a>
    </div>

    <div class="card page-card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                    <tr>
                        <?php foreach (($item['columns'] ?? []) as $column): ?>
                            <th><?php echo e((string)$column); ?></th>
                        <?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($item['rows'])): ?>
                        <?php foreach ($item['rows'] as $row): ?>
                            <tr>
                                <?php foreach ($row as $cell): ?>
                                    <td><?php echo e((string)$cell); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo count($item['columns'] ?? []); ?>" class="text-center text-muted">Belum ada data detail.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
