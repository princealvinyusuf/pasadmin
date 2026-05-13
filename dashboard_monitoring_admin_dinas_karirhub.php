<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/db.php';

$userIsLoggedIn = !empty($_SESSION['user_id']);
if ($userIsLoggedIn) {
    require_once __DIR__ . '/access_helper.php';
} else {
    if (!function_exists('current_user_can')) {
        function current_user_can(string $code): bool { return false; }
    }
}

const KARIRHUB_SHEET_ID = '1ZIjf90UlDyjfCY4AFuBAQDb-AABOHMEzG0Av4rtX3Yo';
const KARIRHUB_SHEET_NAME = 'Form responses 1';

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function normalize_space(string $value): string {
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return trim($value);
}

function fetch_sheet_csv(string $sheetId, string $sheetName): array {
    $url = 'https://docs.google.com/spreadsheets/d/' . rawurlencode($sheetId) . '/gviz/tq?tqx=out:csv&sheet=' . rawurlencode($sheetName);
    $body = null;
    $error = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Accept: text/csv'],
            CURLOPT_USERAGENT => 'pasadmin-dashboard/1.0',
        ]);
        $response = curl_exec($ch);
        $statusCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response !== false && $statusCode >= 200 && $statusCode < 300) {
            $body = $response;
        } else {
            $error = $curlError !== '' ? $curlError : ('HTTP ' . $statusCode);
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
                'header' => "Accept: text/csv\r\nUser-Agent: pasadmin-dashboard/1.0\r\n",
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response !== false) {
            $body = $response;
        } else {
            $error = 'Gagal mengambil data sheet';
        }
    }

    if ($body === null || trim($body) === '') {
        return ['rows' => [], 'error' => $error ?: 'Data sheet kosong'];
    }

    $body = preg_replace('/^\xEF\xBB\xBF/', '', $body) ?? $body;
    $lines = preg_split('/\r\n|\n|\r/', trim($body));
    if (!$lines || count($lines) < 2) {
        return ['rows' => [], 'error' => 'Format CSV tidak valid'];
    }

    $header = str_getcsv((string) array_shift($lines));
    $rows = [];
    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }
        $cells = str_getcsv($line);
        if (count($cells) === 1 && trim((string) $cells[0]) === '') {
            continue;
        }
        $rowAssoc = [];
        foreach ($header as $idx => $col) {
            $colName = trim((string) $col);
            if ($colName === '') {
                $colName = 'Column_' . $idx;
            }
            $rowAssoc[$colName] = trim((string) ($cells[$idx] ?? ''));
        }
        $rows[] = $rowAssoc;
    }

    return ['rows' => $rows, 'error' => null];
}

function parse_sheet_rows(array $sheetRows): array {
    $parsed = [];
    foreach ($sheetRows as $row) {
        $timestamp = normalize_space((string) ($row['Timestamp'] ?? ''));
        $fullName = normalize_space((string) ($row['Nama anda'] ?? ''));
        $nip = normalize_space((string) ($row['NIP'] ?? ''));
        $position = normalize_space((string) ($row['Jabatan'] ?? ''));
        $office = normalize_space((string) ($row['Nomenklatur Asal Instansi'] ?? ''));
        $region = normalize_space((string) ($row['Kabupaten/ Kota'] ?? ''));
        $province = normalize_space((string) ($row['Provinsi'] ?? ''));
        $accessStatus = normalize_space((string) ($row['Apakah anda bisa mengakses DWH'] ?? ''));
        $accessRequest = normalize_space((string) ($row['Apabila anda tidak bisa mengakses, apakah anda ingin mengakses.'] ?? ''));
        $requestNote = normalize_space((string) ($row['Column 1'] ?? ''));
        $uploadUrl = normalize_space((string) ($row['Upload Surat Permohonan dan Surat Tugas'] ?? ''));

        // Skip malformed rows that do not look like response rows.
        if ($fullName === '' && $timestamp === '' && $province === '') {
            continue;
        }

        $parsed[] = [
            'timestamp' => $timestamp,
            'nama' => $fullName,
            'nip' => $nip,
            'jabatan' => $position,
            'instansi' => $office,
            'kabupaten_kota' => $region,
            'provinsi' => $province,
            'akses_dwh' => $accessStatus,
            'permintaan_akses' => $accessRequest,
            'catatan' => $requestNote,
            'surat_url' => $uploadUrl,
        ];
    }
    return $parsed;
}

function count_if(array $rows, callable $predicate): int {
    $count = 0;
    foreach ($rows as $row) {
        if ($predicate($row)) {
            $count++;
        }
    }
    return $count;
}

function has_admin_account(string $note): bool {
    $normalized = strtolower(normalize_space($note));
    if ($normalized === '') {
        return false;
    }
    return strpos($normalized, 'aktif') !== false || strpos($normalized, 'active') !== false;
}

$selectedProvince = normalize_space((string) ($_GET['provinsi'] ?? 'all'));
$selectedStatus = strtolower(normalize_space((string) ($_GET['status_akses'] ?? 'all')));

$statusOptions = [
    'all' => 'Semua Status',
    'bisa' => 'Bisa',
    'tidak bisa' => 'Tidak Bisa',
];

if (!array_key_exists($selectedStatus, $statusOptions)) {
    $selectedStatus = 'all';
}

$sheetResult = fetch_sheet_csv(KARIRHUB_SHEET_ID, KARIRHUB_SHEET_NAME);
$rows = parse_sheet_rows($sheetResult['rows']);

$provinces = [];
foreach ($rows as $row) {
    $province = $row['provinsi'];
    if ($province !== '' && !in_array($province, $provinces, true)) {
        $provinces[] = $province;
    }
}
sort($provinces, SORT_NATURAL | SORT_FLAG_CASE);

if ($selectedProvince !== 'all' && !in_array($selectedProvince, $provinces, true)) {
    $selectedProvince = 'all';
}

if ($selectedProvince !== 'all' || $selectedStatus !== 'all') {
    $rows = array_values(array_filter($rows, static function (array $row) use ($selectedProvince, $selectedStatus): bool {
        if ($selectedProvince !== 'all' && $row['provinsi'] !== $selectedProvince) {
            return false;
        }
        if ($selectedStatus !== 'all' && strtolower($row['akses_dwh']) !== $selectedStatus) {
            return false;
        }
        return true;
    }));
}

$totalResponses = count($rows);
$totalBisa = count_if($rows, static fn(array $row): bool => strtolower($row['akses_dwh']) === 'bisa');
$totalTidakBisa = count_if($rows, static fn(array $row): bool => strtolower($row['akses_dwh']) === 'tidak bisa');
$totalMintaAkses = count_if($rows, static fn(array $row): bool => strtolower($row['permintaan_akses']) === 'ya, mau');
$kabKotaAdminMap = [];
foreach ($rows as $row) {
    $kabKota = normalize_space((string) ($row['kabupaten_kota'] ?? ''));
    if ($kabKota === '') {
        continue;
    }
    if (!isset($kabKotaAdminMap[$kabKota])) {
        $kabKotaAdminMap[$kabKota] = false;
    }
    if (has_admin_account((string) ($row['catatan'] ?? ''))) {
        $kabKotaAdminMap[$kabKota] = true;
    }
}
$totalKabKotaBelumPunyaAkun = 0;
foreach ($kabKotaAdminMap as $hasAdmin) {
    if ($hasAdmin === false) {
        $totalKabKotaBelumPunyaAkun++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Monitoring Admin Dinas Karirhub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php
if ($userIsLoggedIn) {
    include 'navbar.php';
} else {
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="dashboard_monitoring_admin_dinas_karirhub">
            <img src="https://paskerid.kemnaker.go.id/images/services/logo.png" alt="Logo" style="height:24px; width:auto;" class="me-2">
            Dashboard Monitoring Admin Dinas Karirhub
        </a>
    </div>
</nav>
<?php } ?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h3 class="mb-0">Dashboard Monitoring Admin Dinas Karirhub</h3>
        <a class="btn btn-outline-primary btn-sm" href="https://docs.google.com/spreadsheets/d/1ZIjf90UlDyjfCY4AFuBAQDb-AABOHMEzG0Av4rtX3Yo/edit?usp=sharing" target="_blank" rel="noopener noreferrer">
            <i class="bi bi-box-arrow-up-right me-1"></i>Buka Google Sheet
        </a>
    </div>

    <?php if (!empty($sheetResult['error'])): ?>
        <div class="alert alert-warning">
            Data tidak dapat diambil dari Google Sheet saat ini. Detail: <?php echo h((string) $sheetResult['error']); ?>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Respon</div>
                    <div class="fs-4 fw-semibold"><?php echo number_format($totalResponses); ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Bisa Akses DWH</div>
                    <div class="fs-4 fw-semibold text-success"><?php echo number_format($totalBisa); ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Tidak Bisa Akses DWH</div>
                    <div class="fs-4 fw-semibold text-danger"><?php echo number_format($totalTidakBisa); ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Permintaan Akses ("ya, mau")</div>
                    <div class="fs-4 fw-semibold text-primary"><?php echo number_format($totalMintaAkses); ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Jumlah Kabupaten/Kota yang Belum Punya Akun Admin</div>
                    <div class="fs-4 fw-semibold text-warning"><?php echo number_format($totalKabKotaBelumPunyaAkun); ?></div>
                </div>
            </div>
        </div>
    </div>

    <form method="GET" class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <label for="provinsi" class="form-label mb-1">Provinsi</label>
                    <select id="provinsi" name="provinsi" class="form-select form-select-sm">
                        <option value="all"<?php echo $selectedProvince === 'all' ? ' selected' : ''; ?>>Semua Provinsi</option>
                        <?php foreach ($provinces as $province): ?>
                            <option value="<?php echo h($province); ?>"<?php echo $selectedProvince === $province ? ' selected' : ''; ?>>
                                <?php echo h($province); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-5">
                    <label for="status_akses" class="form-label mb-1">Status Akses DWH</label>
                    <select id="status_akses" name="status_akses" class="form-select form-select-sm">
                        <?php foreach ($statusOptions as $statusKey => $statusLabel): ?>
                            <option value="<?php echo h($statusKey); ?>"<?php echo $selectedStatus === $statusKey ? ' selected' : ''; ?>>
                                <?php echo h($statusLabel); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Timestamp</th>
                            <th>Nama</th>
                            <th>NIP</th>
                            <th>Jabatan</th>
                            <th>Instansi</th>
                            <th>Kab/Kota</th>
                            <th>Provinsi</th>
                            <th>Akses DWH</th>
                            <th>Permintaan Akses</th>
                            <th>Surat</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="11" class="text-center text-muted">Tidak ada data yang sesuai filter.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                                $statusAkses = strtolower($row['akses_dwh']);
                                $statusClass = $statusAkses === 'bisa' ? 'success' : ($statusAkses === 'tidak bisa' ? 'danger' : 'secondary');
                            ?>
                            <tr>
                                <td><?php echo h($row['timestamp']); ?></td>
                                <td><?php echo h($row['nama']); ?></td>
                                <td><?php echo h($row['nip']); ?></td>
                                <td><?php echo h($row['jabatan']); ?></td>
                                <td><?php echo h($row['instansi']); ?></td>
                                <td><?php echo h($row['kabupaten_kota']); ?></td>
                                <td><?php echo h($row['provinsi']); ?></td>
                                <td><span class="badge text-bg-<?php echo h($statusClass); ?>"><?php echo h($row['akses_dwh']); ?></span></td>
                                <td><?php echo h($row['permintaan_akses']); ?></td>
                                <td>
                                    <?php if ($row['surat_url'] !== ''): ?>
                                        <a href="<?php echo h($row['surat_url']); ?>" target="_blank" rel="noopener noreferrer">Lihat</a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo h($row['catatan']); ?></td>
                            </tr>
                        <?php endforeach; ?>
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
