<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * One-time safe migration for No. Reg Bukti new dotted format:
 *   WLLP.57.<KELAS>.<KODE_PERUSAHAAN>.<YY>.<MM>.<SEQ>/L
 *
 * Rules:
 * - WLLP.57: fixed value
 * - KELAS: Mi|K|M|B (default B for migrated historical data if unknown)
 * - KODE_PERUSAHAAN: fixed per employer for life (2-digit)
 * - YY.MM: from periode_anchor (fallback created_at)
 * - SEQ: running sequence by prefix WLLP.57.<KELAS>.<KODE_PERUSAHAAN>.<YY>.<MM>.
 * - /L: fixed suffix
 *
 * Usage:
 *   php migrate_no_reg_bukti_new_format.php          (dry-run, default)
 *   php migrate_no_reg_bukti_new_format.php --apply  (execute updates)
 */

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/** @var mysqli $conn */
$conn->set_charset('utf8mb4');
$apply = in_array('--apply', $argv, true);

$targetRegex = '/^WLLP\.57\.(Mi|K|M|B)\.\d{2}\.\d{2}\.\d{2}\.\d{2,}\/L$/';
$targetSqlRegex = '^WLLP\\.57\\.(Mi|K|M|B)\\.[0-9]{2}\\.[0-9]{2}\\.[0-9]{2}\\.[0-9]{2,}/L$';

$tablesToUpdate = [
    'karirhub_proto_wllp_laporan',
    'karirhub_proto_wllp_pelaporan',
    'karirhub_proto_wllp_status',
    'karirhub_proto_wllp_penempatan',
];

function normalizeMsmeClass(string $raw): string
{
    $normalized = strtolower(trim($raw));
    if ($normalized === '') {
        return 'B';
    }
    if (in_array($normalized, ['mi', 'micro', 'mikro'], true)) {
        return 'Mi';
    }
    if (in_array($normalized, ['k', 'small', 'kecil'], true)) {
        return 'K';
    }
    if (in_array($normalized, ['m', 'medium', 'menengah'], true)) {
        return 'M';
    }
    if (in_array($normalized, ['b', 'large', 'besar'], true)) {
        return 'B';
    }
    return 'B';
}

function regPrefixFromParts(string $msmeClass, string $companyCode, string $periodDate): string
{
    $ts = strtotime($periodDate);
    if ($ts === false) {
        $ts = time();
    }
    return 'WLLP.57.' . $msmeClass . '.' . $companyCode . '.' . date('y', $ts) . '.' . date('m', $ts) . '.';
}

function sequenceFromReg(string $noReg): int
{
    $leftPart = explode('/', $noReg, 2)[0] ?? '';
    $parts = explode('.', $leftPart);
    $last = (string)end($parts);
    if ($last === '' || !ctype_digit($last)) {
        return 0;
    }
    return (int)$last;
}

function buildCandidate(string $prefix, int $seq): string
{
    return $prefix . str_pad((string)$seq, 2, '0', STR_PAD_LEFT) . '/L';
}

function ensureEmployerMapTable(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS karirhub_proto_wllp_employer_no_reg_map (
            employer_kode VARCHAR(40) PRIMARY KEY,
            employer_nama VARCHAR(255) NOT NULL,
            company_code VARCHAR(2) NOT NULL UNIQUE,
            msme_class VARCHAR(2) NOT NULL DEFAULT 'B',
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function loadEmployerMap(mysqli $conn): array
{
    $map = [];
    $res = $conn->query("
        SELECT employer_kode, employer_nama, company_code, msme_class
        FROM karirhub_proto_wllp_employer_no_reg_map
    ");
    while ($row = $res->fetch_assoc()) {
        $kode = trim((string)($row['employer_kode'] ?? ''));
        if ($kode === '') {
            continue;
        }
        $map[$kode] = [
            'employer_nama' => (string)($row['employer_nama'] ?? ''),
            'company_code' => str_pad((string)($row['company_code'] ?? '01'), 2, '0', STR_PAD_LEFT),
            'msme_class' => normalizeMsmeClass((string)($row['msme_class'] ?? 'B')),
        ];
    }
    return $map;
}

function loadEmployerFirstSeen(mysqli $conn): array
{
    $rows = [];
    $res = $conn->query("
        SELECT
            employer_kode,
            employer_nama,
            MIN(COALESCE(periode_anchor, DATE(created_at), CURDATE())) AS first_seen_date
        FROM karirhub_proto_wllp_laporan
        WHERE employer_kode IS NOT NULL AND employer_kode <> ''
        GROUP BY employer_kode, employer_nama
        ORDER BY first_seen_date ASC, employer_kode ASC
    ");
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'employer_kode' => trim((string)($row['employer_kode'] ?? '')),
            'employer_nama' => trim((string)($row['employer_nama'] ?? '')),
            'first_seen_date' => (string)($row['first_seen_date'] ?? date('Y-m-d')),
        ];
    }
    return $rows;
}

function loadLaporanRows(mysqli $conn): array
{
    $rows = [];
    $res = $conn->query("
        SELECT
            no_reg_bukti,
            employer_kode,
            employer_nama,
            COALESCE(periode_anchor, DATE(created_at), CURDATE()) AS period_date
        FROM karirhub_proto_wllp_laporan
        WHERE no_reg_bukti IS NOT NULL AND no_reg_bukti <> ''
    ");
    while ($row = $res->fetch_assoc()) {
        $rows[] = [
            'no_reg_bukti' => (string)$row['no_reg_bukti'],
            'employer_kode' => trim((string)($row['employer_kode'] ?? '')),
            'employer_nama' => trim((string)($row['employer_nama'] ?? '')),
            'period_date' => (string)($row['period_date'] ?? date('Y-m-d')),
        ];
    }
    return $rows;
}

ensureEmployerMapTable($conn);

$existingEmployerMap = loadEmployerMap($conn);
$employerFirstSeen = loadEmployerFirstSeen($conn);

$usedCompanyCodes = [];
foreach ($existingEmployerMap as $item) {
    $code = (string)($item['company_code'] ?? '');
    if ($code !== '' && ctype_digit($code)) {
        $usedCompanyCodes[$code] = true;
    }
}
$maxCompanyCode = 0;
foreach (array_keys($usedCompanyCodes) as $code) {
    $maxCompanyCode = max($maxCompanyCode, (int)$code);
}

$mapUpserts = [];
foreach ($employerFirstSeen as $item) {
    $employerKode = (string)$item['employer_kode'];
    if ($employerKode === '') {
        continue;
    }
    if (isset($existingEmployerMap[$employerKode])) {
        continue;
    }

    do {
        $maxCompanyCode++;
        $companyCode = str_pad((string)$maxCompanyCode, 2, '0', STR_PAD_LEFT);
    } while (isset($usedCompanyCodes[$companyCode]));

    $usedCompanyCodes[$companyCode] = true;
    $mapUpserts[$employerKode] = [
        'employer_kode' => $employerKode,
        'employer_nama' => (string)($item['employer_nama'] !== '' ? $item['employer_nama'] : 'PT Contoh Nusantara'),
        'company_code' => $companyCode,
        'msme_class' => 'B',
    ];
    $existingEmployerMap[$employerKode] = [
        'employer_nama' => (string)$mapUpserts[$employerKode]['employer_nama'],
        'company_code' => $companyCode,
        'msme_class' => 'B',
    ];
}

$laporanRows = loadLaporanRows($conn);
if (empty($laporanRows)) {
    echo "No rows found in karirhub_proto_wllp_laporan.\n";
    exit(0);
}

$occupiedRegs = [];
$maxSeqByPrefix = [];
foreach ($laporanRows as $row) {
    $reg = $row['no_reg_bukti'];
    $occupiedRegs[$reg] = true;
    if (!preg_match($targetRegex, $reg)) {
        continue;
    }
    $prefix = substr($reg, 0, strrpos($reg, '.') + 1);
    $seq = sequenceFromReg($reg);
    if (!isset($maxSeqByPrefix[$prefix]) || $seq > $maxSeqByPrefix[$prefix]) {
        $maxSeqByPrefix[$prefix] = $seq;
    }
}

$rowsToMigrate = [];
foreach ($laporanRows as $row) {
    $oldReg = $row['no_reg_bukti'];
    if (preg_match($targetRegex, $oldReg)) {
        continue;
    }
    $rowsToMigrate[] = $row;
}

if (empty($rowsToMigrate)) {
    echo "No migration needed. All laporan no_reg_bukti already match target format.\n";
    exit(0);
}

usort($rowsToMigrate, static function (array $a, array $b): int {
    $keyA = ($a['employer_kode'] ?: '~') . '|' . $a['period_date'] . '|' . $a['no_reg_bukti'];
    $keyB = ($b['employer_kode'] ?: '~') . '|' . $b['period_date'] . '|' . $b['no_reg_bukti'];
    return strcmp($keyA, $keyB);
});

$mapping = [];
foreach ($rowsToMigrate as $row) {
    $oldReg = $row['no_reg_bukti'];
    $employerKode = $row['employer_kode'] !== '' ? $row['employer_kode'] : 'EMP-001';
    $employerNama = $row['employer_nama'] !== '' ? $row['employer_nama'] : 'PT Contoh Nusantara';

    if (!isset($existingEmployerMap[$employerKode])) {
        do {
            $maxCompanyCode++;
            $companyCode = str_pad((string)$maxCompanyCode, 2, '0', STR_PAD_LEFT);
        } while (isset($usedCompanyCodes[$companyCode]));
        $usedCompanyCodes[$companyCode] = true;
        $existingEmployerMap[$employerKode] = [
            'employer_nama' => $employerNama,
            'company_code' => $companyCode,
            'msme_class' => 'B',
        ];
        $mapUpserts[$employerKode] = [
            'employer_kode' => $employerKode,
            'employer_nama' => $employerNama,
            'company_code' => $companyCode,
            'msme_class' => 'B',
        ];
    }

    $companyCode = str_pad((string)$existingEmployerMap[$employerKode]['company_code'], 2, '0', STR_PAD_LEFT);
    $msmeClass = normalizeMsmeClass((string)$existingEmployerMap[$employerKode]['msme_class']);
    $prefix = regPrefixFromParts($msmeClass, $companyCode, $row['period_date']);
    $nextSeq = ((int)($maxSeqByPrefix[$prefix] ?? 0)) + 1;
    do {
        $candidate = buildCandidate($prefix, $nextSeq);
        $nextSeq++;
    } while (isset($occupiedRegs[$candidate]));

    $mapping[$oldReg] = $candidate;
    $occupiedRegs[$candidate] = true;
    $maxSeqByPrefix[$prefix] = sequenceFromReg($candidate);
}

echo "Migration mode : " . ($apply ? 'APPLY' : 'DRY-RUN') . "\n";
echo "Target format  : WLLP.57.<KELAS>.<KODE_PERUSAHAAN>.<YY>.<MM>.<SEQ>/L\n";
echo "Rows to migrate: " . count($mapping) . " unique no_reg_bukti\n";
echo "Employer map upserts: " . count($mapUpserts) . "\n\n";

$previewCount = 0;
foreach ($mapping as $old => $new) {
    echo $old . "  =>  " . $new . "\n";
    $previewCount++;
    if ($previewCount >= 25 && count($mapping) > 25) {
        echo "... (" . (count($mapping) - 25) . " more)\n";
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

    if (!empty($mapUpserts)) {
        $stmtUpsertMap = $conn->prepare("
            INSERT INTO karirhub_proto_wllp_employer_no_reg_map
                (employer_kode, employer_nama, company_code, msme_class)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                employer_nama = VALUES(employer_nama),
                msme_class = VALUES(msme_class)
        ");
        foreach ($mapUpserts as $row) {
            $kode = (string)$row['employer_kode'];
            $nama = (string)$row['employer_nama'];
            $code = (string)$row['company_code'];
            $kelas = (string)$row['msme_class'];
            $stmtUpsertMap->bind_param('ssss', $kode, $nama, $code, $kelas);
            $stmtUpsertMap->execute();
        }
        $stmtUpsertMap->close();
    }

    foreach ($mapping as $old => $new) {
        foreach ($tablesToUpdate as $table) {
            $stmt = $conn->prepare("UPDATE `{$table}` SET no_reg_bukti = ? WHERE no_reg_bukti = ?");
            $stmt->bind_param('ss', $new, $old);
            $stmt->execute();
            $stmt->close();
        }
    }

    $conn->commit();
    echo "Migration committed successfully.\n";

    foreach ($tablesToUpdate as $table) {
        $res = $conn->query("
            SELECT COUNT(*) AS c
            FROM `{$table}`
            WHERE no_reg_bukti IS NOT NULL
              AND no_reg_bukti <> ''
              AND no_reg_bukti NOT REGEXP '{$targetSqlRegex}'
        ");
        $outside = (int)($res->fetch_assoc()['c'] ?? 0);
        echo $table . ': ' . $outside . " row(s) still outside target format.\n";
    }
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, "Migration failed, rolled back: " . $e->getMessage() . "\n");
    exit(1);
}

