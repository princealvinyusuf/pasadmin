<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/karirhub_employer_prototype_data.php';
require_once __DIR__ . '/karirhub_employer_prototype_storage.php';

/**
 * Safe reset + schema rebuild for multi-lowongan prototype.
 *
 * Usage:
 *   php reset_karirhub_proto_multi_lowongan.php           # dry-run
 *   php reset_karirhub_proto_multi_lowongan.php --apply   # execute reset
 *   php reset_karirhub_proto_multi_lowongan.php --apply --seed
 */

$apply = in_array('--apply', $argv, true);
$seed = in_array('--seed', $argv, true);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

$tables = [
    'karirhub_proto_wllp_penempatan',
    'karirhub_proto_wllp_status',
    'karirhub_proto_wllp_pelaporan',
    'karirhub_proto_wllp_laporan',
];

echo "Mode: " . ($apply ? "APPLY" : "DRY-RUN") . PHP_EOL;
echo "Seed after reset: " . ($seed ? "YES" : "NO") . PHP_EOL . PHP_EOL;

echo "Current row counts:" . PHP_EOL;
foreach ($tables as $table) {
    $existsRes = $conn->query("SHOW TABLES LIKE '{$table}'");
    if ($existsRes->num_rows === 0) {
        echo "- {$table}: [missing]" . PHP_EOL;
        continue;
    }
    $countRes = $conn->query("SELECT COUNT(*) AS c FROM `{$table}`");
    $count = (int)($countRes->fetch_assoc()['c'] ?? 0);
    echo "- {$table}: {$count}" . PHP_EOL;
}
echo PHP_EOL;

if (!$apply) {
    echo "Dry-run complete. Re-run with --apply to reset tables." . PHP_EOL;
    exit(0);
}

try {
    $conn->begin_transaction();
    foreach ($tables as $table) {
        $conn->query("DROP TABLE IF EXISTS `{$table}`");
    }
    kh_proto_ensure_multi_tables($conn);

    if ($seed) {
        $dataset = karirhub_proto_dataset();
        $units = $dataset['units'] ?? [];
        kh_proto_seed_multi_from_dataset($conn, $dataset, $units);
    }

    $conn->commit();
    echo "Reset completed successfully." . PHP_EOL;
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, "Reset failed, rolled back: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo PHP_EOL . "Post-reset row counts:" . PHP_EOL;
foreach ($tables as $table) {
    $countRes = $conn->query("SELECT COUNT(*) AS c FROM `{$table}`");
    $count = (int)($countRes->fetch_assoc()['c'] ?? 0);
    echo "- {$table}: {$count}" . PHP_EOL;
}

