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

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
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
$records = blk_get_item_records($itemId);
$selectedRecordIndex = isset($_GET['record']) ? (int)$_GET['record'] : null;
$selectedIndividualIndex = isset($_GET['individual']) ? (int)$_GET['individual'] : null;
$selectedRecord = null;
if ($selectedRecordIndex !== null && isset($records[$selectedRecordIndex])) {
    $selectedRecord = $records[$selectedRecordIndex];
}
$selectedIndividual = null;
if ($selectedIndividualIndex !== null && isset($records[$selectedIndividualIndex])) {
    $selectedIndividual = $records[$selectedIndividualIndex];
}

$backQuery = http_build_query([
    'period' => $selectedPeriod,
    'location' => $selectedLocation,
    'major' => $selectedMajor,
    'source' => $selectedSource,
]);

function build_detail_link(string $itemId, int $recordIndex, string $selectedPeriod, string $selectedLocation, string $selectedMajor, string $selectedSource): string
{
    return 'dashboard_blk_detail.php?' . http_build_query([
        'item' => $itemId,
        'record' => $recordIndex,
        'period' => $selectedPeriod,
        'location' => $selectedLocation,
        'major' => $selectedMajor,
        'source' => $selectedSource,
    ]);
}

function build_individual_link(string $itemId, int $recordIndex, int $individualIndex, string $selectedPeriod, string $selectedLocation, string $selectedMajor, string $selectedSource): string
{
    return 'dashboard_blk_detail.php?' . http_build_query([
        'item' => $itemId,
        'record' => $recordIndex,
        'individual' => $individualIndex,
        'period' => $selectedPeriod,
        'location' => $selectedLocation,
        'major' => $selectedMajor,
        'source' => $selectedSource,
    ]);
}
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
            <?php if ($selectedRecord !== null): ?>
                <div class="alert alert-primary d-flex align-items-center justify-content-between mb-3" role="alert">
                    <div>
                        <strong>Ringkasan KPI Terpilih:</strong>
                        <?php echo e(($item['columns'][0] ?? 'Data') . ' - ' . ($item['rows'][$selectedRecordIndex][0] ?? '')); ?>
                    </div>
                    <a href="dashboard_blk_detail.php?item=<?php echo e($itemId); ?>&<?php echo e($backQuery); ?>" class="btn btn-sm btn-outline-primary">
                        Reset Detail Record
                    </a>
                </div>
            <?php endif; ?>

            <h5 class="mb-2">Ringkasan KPI</h5>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                    <tr>
                        <?php foreach (($item['columns'] ?? []) as $column): ?>
                            <th><?php echo e((string)$column); ?></th>
                        <?php endforeach; ?>
                        <th style="width: 120px;">Aksi</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($item['rows'])): ?>
                        <?php foreach ($item['rows'] as $rowIndex => $row): ?>
                            <tr>
                                <?php foreach ($row as $cell): ?>
                                    <td><?php echo e((string)$cell); ?></td>
                                <?php endforeach; ?>
                                <td>
                                    <a href="<?php echo e(build_detail_link($itemId, $rowIndex, $selectedPeriod, $selectedLocation, $selectedMajor, $selectedSource)); ?>" class="btn btn-sm btn-outline-primary">
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo count($item['columns'] ?? []) + 1; ?>" class="text-center text-muted">Belum ada data detail.</td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($selectedRecord !== null): ?>
                <hr>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="mb-0">Data Individu (Variable Level)</h5>
                    <small class="text-muted">Menampilkan 4 data individu. Klik Detail untuk data lengkap.</small>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle">
                        <thead>
                        <tr>
                            <?php foreach (array_keys($records[0]) as $col): ?>
                                <th><?php echo e($col); ?></th>
                            <?php endforeach; ?>
                            <th style="width: 120px;">Aksi</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($records as $individualIndex => $record): ?>
                            <tr>
                                <?php foreach ($record as $value): ?>
                                    <td><?php echo e($value); ?></td>
                                <?php endforeach; ?>
                                <td>
                                    <a href="<?php echo e(build_individual_link($itemId, (int)$selectedRecordIndex, $individualIndex, $selectedPeriod, $selectedLocation, $selectedMajor, $selectedSource)); ?>" class="btn btn-sm btn-primary">
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($selectedIndividual !== null): ?>
                    <div class="card border-0 mt-3" style="background:#f8fafc;">
                        <div class="card-body">
                            <h6 class="mb-3">Detail Lengkap Individu</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <tbody>
                                    <?php foreach ($selectedIndividual as $field => $value): ?>
                                        <tr>
                                            <th style="width: 260px;"><?php echo e($field); ?></th>
                                            <td><?php echo e($value); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
