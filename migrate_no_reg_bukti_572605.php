<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * One-time safe migration for No. Reg Bukti format:
 * - Old values -> WLLP-572605-XXXXXXXX
 * - Keeps relations consistent across:
 *   1) karirhub_proto_wllp_pelaporan
 *   2) karirhub_proto_wllp_status
 *   3) karirhub_proto_wllp_penempatan
 *
 * Usage:
 *   php migrate_no_reg_bukti_572605.php          (dry-run, default)
 *   php migrate_no_reg_bukti_572605.php --apply  (execute updates)
 */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$apply = in_array('--apply', $argv, true);
$targetPrefix = 'WLLP-572605-';
$targetRegex = '/^WLLP-572605-\d{8}$/';

/** @var mysqli $conn */
$conn->set_charset('utf8mb4');

function fetchNoRegValues(mysqli $conn, string $table): array
{
    $rows = [];
    $sql = "SELECT no_reg_bukti FROM `{$table}` WHERE no_reg_bukti IS NOT NULL AND no_reg_bukti <> ''";
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        $rows[] = (string)$row['no_reg_bukti'];
    }
    return $rows;
}

function nextSequenceFromExisting(array $allRegs, string $prefix): int
{
    $maxSeq = 0;
    foreach ($allRegs as $reg) {
        if (!preg_match('/^' . preg_quote($prefix, '/') . '(\d{8})$/', $reg, $m)) {
            continue;
        }
        $seq = (int)$m[1];
        if ($seq > $maxSeq) {
            $maxSeq = $seq;
        }
    }
    return $maxSeq + 1;
}

$tables = [
    'karirhub_proto_wllp_pelaporan',
    'karirhub_proto_wllp_status',
    'karirhub_proto_wllp_penempatan',
];

// Build universe of existing no_reg values from all related tables.
$allExisting = [];
foreach ($tables as $table) {
    foreach (fetchNoRegValues($conn, $table) as $reg) {
        $allExisting[$reg] = true;
    }
}
$allRegs = array_keys($allExisting);

// Determine old regs that need migration from the authoritative pelaporan table.
$pelaporanRegs = fetchNoRegValues($conn, 'karirhub_proto_wllp_pelaporan');
$oldRegs = [];
foreach ($pelaporanRegs as $reg) {
    if (!preg_match($targetRegex, $reg)) {
        $oldRegs[$reg] = true;
    }
}
$oldRegs = array_keys($oldRegs);
sort($oldRegs, SORT_STRING);

if (empty($oldRegs)) {
    echo "No migration needed. All pelaporan no_reg_bukti already match {$targetPrefix}XXXXXXXX.\n";
    exit(0);
}

// Build deterministic old -> new mapping.
$nextSeq = nextSequenceFromExisting($allRegs, $targetPrefix);
$mapping = [];
foreach ($oldRegs as $oldReg) {
    do {
        $candidate = $targetPrefix . str_pad((string)$nextSeq, 8, '0', STR_PAD_LEFT);
        $nextSeq++;
    } while (isset($allExisting[$candidate]));
    $mapping[$oldReg] = $candidate;
    $allExisting[$candidate] = true;
}

echo "Migration mode: " . ($apply ? "APPLY" : "DRY-RUN") . "\n";
echo "Target format : {$targetPrefix}XXXXXXXX\n";
echo "Rows to migrate (unique by pelaporan): " . count($mapping) . "\n\n";

$previewCount = 0;
foreach ($mapping as $old => $new) {
    echo "{$old}  =>  {$new}\n";
    $previewCount++;
    if ($previewCount >= 20 && count($mapping) > 20) {
        echo "... (" . (count($mapping) - 20) . " more)\n";
        break;
    }
}
echo "\n";

if (!$apply) {
    echo "Dry-run only. Re-run with --apply to execute updates.\n";
    exit(0);
}

try {
    $conn->begin_transaction();

    // Update every related table per old->new mapping.
    foreach ($mapping as $old => $new) {
        foreach ($tables as $table) {
            $stmt = $conn->prepare("UPDATE `{$table}` SET no_reg_bukti = ? WHERE no_reg_bukti = ?");
            $stmt->bind_param('ss', $new, $old);
            $stmt->execute();
            $stmt->close();
        }
    }

    $conn->commit();
    echo "Migration committed successfully.\n";

    // Post-check summary.
    foreach ($tables as $table) {
        $sql = "SELECT COUNT(*) AS c
                FROM `{$table}`
                WHERE no_reg_bukti NOT REGEXP '^WLLP-572605-[0-9]{8}$'";
        $res = $conn->query($sql);
        $count = (int)$res->fetch_assoc()['c'];
        echo "{$table}: {$count} row(s) still outside target format.\n";
    }
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, "Migration failed, rolled back: " . $e->getMessage() . "\n");
    exit(1);
}

