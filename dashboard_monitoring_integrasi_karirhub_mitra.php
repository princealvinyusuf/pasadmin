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
    if (!function_exists('current_user_is_super_admin')) {
        function current_user_is_super_admin(): bool { return false; }
    }
}

function column_exists(mysqli $conn, string $table, string $column): bool {
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($column);
    $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function ensure_karirhub_mitra_monitoring_tables(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS karirhub_mitra_monitoring (
        id INT AUTO_INCREMENT PRIMARY KEY,
        portal_code VARCHAR(64) NOT NULL UNIQUE,
        portal_name VARCHAR(120) NOT NULL,
        company_name VARCHAR(180) NOT NULL,
        logo_url VARCHAR(500) DEFAULT '',
        cooperation_types TEXT DEFAULT NULL,
        progress_summary TEXT DEFAULT NULL,
        perizinan_done TINYINT(1) NOT NULL DEFAULT 0,
        kb_done TINYINT(1) NOT NULL DEFAULT 0,
        pks_done TINYINT(1) NOT NULL DEFAULT 0,
        nda_done TINYINT(1) NOT NULL DEFAULT 0,
        integrasi_done TINYINT(1) NOT NULL DEFAULT 0,
        progress_indicator VARCHAR(10) NOT NULL DEFAULT 'yellow',
        notes TEXT DEFAULT NULL,
        display_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (!column_exists($conn, 'karirhub_mitra_monitoring', 'progress_indicator')) {
        $conn->query("ALTER TABLE karirhub_mitra_monitoring ADD COLUMN progress_indicator VARCHAR(10) NOT NULL DEFAULT 'yellow' AFTER integrasi_done");
    }

    $conn->query("CREATE TABLE IF NOT EXISTS karirhub_mitra_monitoring_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        monitoring_id INT NOT NULL,
        integration_scope VARCHAR(150) NOT NULL DEFAULT 'Integrasi Lowongan',
        status_progress VARCHAR(60) NOT NULL DEFAULT 'On Progress',
        latest_progress_detail VARCHAR(255) NOT NULL DEFAULT '-',
        display_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_karirhub_monitoring_items_parent
            FOREIGN KEY (monitoring_id) REFERENCES karirhub_mitra_monitoring(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function seed_karirhub_mitra_monitoring(mysqli $conn): void {
    $res = $conn->query('SELECT COUNT(*) AS c FROM karirhub_mitra_monitoring');
    $count = $res ? intval(($res->fetch_assoc()['c'] ?? 0)) : 0;
    if ($count > 0) {
        return;
    }

    $seedRows = [
        ['hired_today', 'HiredToday', 'PT. Indo HR (Hired Today)', '', "Kesepahaman Bersama (KB)\nPerjanjian Kerjasama (PKS)\nNon-Disclosure Agreement (NDA)", 'Hired Today dan Karirhub sudah terintegrasi.', 1, 1, 1, 1, 1, 'green', "Adendum NDA proses TTD Job Portal\nLive in production", 1],
        ['glints', 'Glints', 'Glints Indonesia (Glints)', '', "Kesepahaman Bersama (KB)\nPerjanjian Kerjasama (PKS)\nNon-Disclosure Agreement (NDA)", 'Proses testing di staging area sandbox dan progres migrasi ke production.', 0, 1, 1, 0, 0, 'yellow', "NDA proses biro hukum\nProses migrasi ke production\nPerizinan masih dalam proses", 2],
        ['toploker', 'Toploker', 'PT Bisnis Digital Ekonomi (Top Loker)', '', "Kesepahaman Bersama (KB)\nPerjanjian Kerjasama (PKS)\nNon-Disclosure Agreement (NDA)", 'Integrasi Top Loker-Karirhub telah berjalan.', 1, 1, 1, 0, 1, 'green', "NDA proses TTD Job Portal\nLive in production", 3],
        ['redy', 'Redy', 'PT Rekrutmen Indonesia (getredy.id)', '', "Kesepahaman Bersama (KB)\nPerjanjian Kerjasama (PKS)\nNon-Disclosure Agreement (NDA)", 'Proses testing di staging area sandbox dan progres migrasi ke production.', 1, 1, 1, 0, 0, 'yellow', "NDA proses TTD Pasker\nProses migrasi ke production", 4],
        ['kitalulus', 'KitaLulus', 'KitaLulus Internasional', '', "Kesepahaman Bersama (KB)", 'Pihak KitaLulus sudah setuju untuk melakukan integrasi menggunakan sistem API.', 1, 1, 0, 0, 0, 'yellow', "Dijadwalkan pembahasan PKS dan NDA\nBelum integrasi", 5],
        ['kalibrr', 'Kalibrr', 'PT Kalibrr Technology Access (Kalibrr)', '', "Kesepahaman Bersama (KB)", 'Draft PKS dan NDA sedang proses penelaahan oleh tim Legal Kalibrr.', 0, 1, 0, 0, 0, 'yellow', "PKS dan NDA proses legal Kalibrr\nPerizinan masih dalam proses\nBelum integrasi", 6],
        ['dki', 'DKI', 'PT Disabilitas Kerja Indonesia (disabilitaskerja.co.id)', '', "Kesepahaman Bersama (KB)", "Belum menyelesaikan perizinan Aktivitas Penempatan Tenaga Kerja Daring (Job Portal), KBLI 78104.\nBelum memasuki pembahasan mengenai draft PKS dan NDA.", 0, 1, 0, 0, 0, 'red', "Dijadwalkan pembahasan PKS dan NDA\nPerizinan masih dalam proses\nBelum integrasi", 7],
        ['diploy', 'Diploy', 'Diploy Komdigi', '', "Kesepahaman Bersama (KB)\n(Kemnaker dengan Komdigi)", 'Draft PKS dan NDA sudah dikirimkan ke pihak Diploy.', 1, 1, 0, 0, 0, 'yellow', "PKS dan NDA menunggu feedback\nProses migrasi ke production", 8],
        ['jobstreet', 'Jobstreet', 'Jobstreet', '', '', 'Dijadwalkan penjajakan awal.', 1, 0, 0, 0, 0, 'red', "Dijadwalkan penjajakan\nBelum integrasi", 9],
    ];

    $insMain = $conn->prepare("INSERT INTO karirhub_mitra_monitoring (
        portal_code, portal_name, company_name, logo_url, cooperation_types, progress_summary,
        perizinan_done, kb_done, pks_done, nda_done, integrasi_done, progress_indicator, notes, display_order
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    foreach ($seedRows as $row) {
        $insMain->bind_param(
            'ssssssiiiiissi',
            $row[0], $row[1], $row[2], $row[3], $row[4], $row[5],
            $row[6], $row[7], $row[8], $row[9], $row[10], $row[11], $row[12], $row[13]
        );
        $insMain->execute();
    }
    $insMain->close();

    $itemsMap = [
        'hired_today' => [['Integrasi Lowongan', 'Selesai', 'Live in Production'], ['Kirim Lamaran', 'Selesai', '-'], ['Status Lamaran', 'Selesai', '-']],
        'glints' => [['Integrasi Lowongan', 'On Progress', 'Migrasi ke Production']],
        'toploker' => [['Integrasi Lowongan', 'Selesai', 'Live in Production']],
        'redy' => [['Integrasi Lowongan', 'On Progress', 'Migrasi ke Production']],
        'kitalulus' => [['Integrasi Lowongan', 'On Progress', '-']],
        'kalibrr' => [['Integrasi Lowongan', 'On Progress', 'Testing API di Sandbox']],
        'dki' => [['Integrasi Lowongan', 'Belum Mulai', '-']],
        'diploy' => [['Integrasi Lowongan', 'On Progress', 'Migrasi ke Production']],
        'jobstreet' => [['Integrasi Lowongan', 'Belum Mulai', '-']],
    ];

    $resIds = $conn->query("SELECT id, portal_code FROM karirhub_mitra_monitoring");
    $portalIdMap = [];
    while ($r = $resIds->fetch_assoc()) {
        $portalIdMap[$r['portal_code']] = intval($r['id']);
    }
    $insItem = $conn->prepare("INSERT INTO karirhub_mitra_monitoring_items (monitoring_id, integration_scope, status_progress, latest_progress_detail, display_order) VALUES (?,?,?,?,?)");
    foreach ($itemsMap as $portalCode => $items) {
        if (!isset($portalIdMap[$portalCode])) {
            continue;
        }
        $monitoringId = $portalIdMap[$portalCode];
        foreach ($items as $idx => $item) {
            $sort = $idx + 1;
            $insItem->bind_param('isssi', $monitoringId, $item[0], $item[1], $item[2], $sort);
            $insItem->execute();
        }
    }
    $insItem->close();
}

function split_lines(?string $text): array {
    if ($text === null || trim($text) === '') {
        return [];
    }
    $parts = preg_split('/\r\n|\r|\n/', $text);
    $clean = [];
    foreach ($parts as $p) {
        $line = trim($p);
        if ($line !== '') {
            $clean[] = $line;
        }
    }
    return $clean;
}

function status_mark(int $value): string {
    return $value === 1 ? '√' : '';
}

function normalize_indicator(?string $value, array $row): string {
    $v = strtolower(trim((string) $value));
    if (in_array($v, ['red', 'yellow', 'green'], true)) {
        return $v;
    }
    if (intval($row['integrasi_done'] ?? 0) === 1) {
        return 'green';
    }
    if (
        intval($row['perizinan_done'] ?? 0) === 1 ||
        intval($row['kb_done'] ?? 0) === 1 ||
        intval($row['pks_done'] ?? 0) === 1 ||
        intval($row['nda_done'] ?? 0) === 1
    ) {
        return 'yellow';
    }
    return 'red';
}

function indicator_label(string $indicator): string {
    if ($indicator === 'green') { return 'Selesai'; }
    if ($indicator === 'red') { return 'Belum Mulai'; }
    return 'On Progress';
}

ensure_karirhub_mitra_monitoring_tables($conn);
seed_karirhub_mitra_monitoring($conn);

$rows = [];
$qMain = $conn->query("SELECT * FROM karirhub_mitra_monitoring WHERE is_active=1 ORDER BY display_order ASC, id ASC");
while ($r = $qMain->fetch_assoc()) {
    $r['progress_indicator'] = normalize_indicator($r['progress_indicator'] ?? '', $r);
    $rows[] = $r;
}

$itemsByMonitoring = [];
$qItems = $conn->query("SELECT monitoring_id, integration_scope, status_progress, latest_progress_detail FROM karirhub_mitra_monitoring_items ORDER BY display_order ASC, id ASC");
while ($item = $qItems->fetch_assoc()) {
    $mid = intval($item['monitoring_id']);
    if (!isset($itemsByMonitoring[$mid])) {
        $itemsByMonitoring[$mid] = [];
    }
    $itemsByMonitoring[$mid][] = $item;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Monitoring Integrasi Karirhub x Mitra</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #edf2f4; }
        .monitor-card { border: 0; border-radius: 14px; overflow: hidden; box-shadow: 0 6px 22px rgba(0, 0, 0, 0.08); }
        .monitor-card-header { background: linear-gradient(90deg, #0f8f92, #0a7c81); color: #fff; padding: 20px 24px; }
        .logo-box {
            width: 110px; height: 72px; border-radius: 10px; background: #fff; color: #0e4953;
            border: 1px solid rgba(0, 0, 0, 0.08); display: flex; align-items: center; justify-content: center; font-weight: 700;
            overflow: hidden;
        }
        .logo-box img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .cooperation-card { background: #f8fbfc; border: 1px solid #deecef; border-radius: 12px; }
        .progress-card { background: #0f8f92; color: #fff; border-radius: 12px; }
        .small-table th, .small-table td { font-size: 0.88rem; padding: 0.4rem 0.5rem; }
        .summary-table th, .summary-table td { vertical-align: top; font-size: 0.88rem; }
        .tick { font-weight: 700; color: #0e4f5f; text-align: center; }
        .indicator-wrap { display: flex; align-items: center; gap: 8px; }
        .indicator-lamp {
            width: 18px; height: 18px; border-radius: 50%; border: 1px solid rgba(255, 255, 255, 0.6);
            box-shadow: inset 0 -3px 6px rgba(0, 0, 0, 0.18), 0 0 8px rgba(255, 255, 255, 0.1);
        }
        .lamp-red { background: radial-gradient(circle at 35% 35%, #ff8f8f, #e81f1f 60%, #9f0c0c); }
        .lamp-yellow { background: radial-gradient(circle at 35% 35%, #fff3a4, #ffd21f 60%, #b18300); }
        .lamp-green { background: radial-gradient(circle at 35% 35%, #9ff6a6, #15b429 60%, #0d7d1f); }
        .indicator-badge {
            font-size: 0.76rem; letter-spacing: 0.2px; text-transform: uppercase;
            border: 1px solid rgba(255, 255, 255, 0.5); border-radius: 999px; padding: 2px 8px; font-weight: 600;
        }
        .legend { font-size: 0.86rem; color: #335; }
        .legend .indicator-lamp { width: 14px; height: 14px; border-color: rgba(0, 0, 0, 0.2); box-shadow: none; }
    </style>
</head>
<body class="bg-light">
<?php
if ($userIsLoggedIn) {
    include 'navbar.php';
} else {
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="dashboard_monitoring_integrasi_karirhub_mitra.php">
            <img src="https://paskerid.kemnaker.go.id/images/services/logo.png" alt="Logo" style="height:24px; width:auto;" class="me-2">
            Dashboard Monitoring Integrasi
        </a>
    </div>
</nav>
<?php } ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Dashboard Monitoring Integrasi Karirhub x Mitra</h3>
        <?php if (current_user_can('settings_integrasi_karirhub_mitra_manage') || current_user_can('manage_settings')): ?>
            <a class="btn btn-outline-primary btn-sm" href="dashboard_monitoring_integrasi_karirhub_mitra_settings.php">
                <i class="bi bi-gear me-1"></i>Kelola Data
            </a>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-3 align-items-center mb-3 legend">
        <span class="d-inline-flex align-items-center gap-1"><span class="indicator-lamp lamp-red"></span>Belum Mulai</span>
        <span class="d-inline-flex align-items-center gap-1"><span class="indicator-lamp lamp-yellow"></span>On Progress</span>
        <span class="d-inline-flex align-items-center gap-1"><span class="indicator-lamp lamp-green"></span>Selesai</span>
    </div>

    <?php if (empty($rows)): ?>
        <div class="alert alert-warning">Belum ada data monitoring. Tambahkan data melalui halaman settings.</div>
    <?php endif; ?>

    <?php foreach ($rows as $row): ?>
        <?php
            $mid = intval($row['id']);
            $cooperationTypes = split_lines($row['cooperation_types'] ?? '');
            $details = $itemsByMonitoring[$mid] ?? [];
            $notes = split_lines($row['notes'] ?? '');
            $indicator = $row['progress_indicator'] ?? 'yellow';
            $lampClass = $indicator === 'green' ? 'lamp-green' : ($indicator === 'red' ? 'lamp-red' : 'lamp-yellow');
        ?>
        <div class="card monitor-card mb-4">
            <div class="monitor-card-header">
                <div class="d-flex align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="logo-box">
                            <?php if (!empty($row['logo_url'])): ?>
                                <img src="<?php echo htmlspecialchars($row['logo_url']); ?>" alt="logo <?php echo htmlspecialchars($row['portal_name']); ?>">
                            <?php else: ?>
                                <?php echo htmlspecialchars(substr($row['portal_name'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h4 class="mb-0"><?php echo htmlspecialchars($row['company_name']); ?></h4>
                            <div class="opacity-75 small"><?php echo htmlspecialchars($row['portal_name']); ?></div>
                        </div>
                    </div>
                    <div class="indicator-wrap">
                        <span class="indicator-lamp <?php echo $lampClass; ?>"></span>
                        <span class="indicator-badge"><?php echo htmlspecialchars(indicator_label($indicator)); ?></span>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="cooperation-card p-3 h-100">
                            <h5 class="mb-2">Jenis Kerjasama</h5>
                            <?php if (empty($cooperationTypes)): ?>
                                <div class="text-muted">-</div>
                            <?php else: ?>
                                <?php foreach ($cooperationTypes as $t): ?>
                                    <div><?php echo htmlspecialchars($t); ?></div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="progress-card p-3 h-100">
                            <h5 class="mb-2">Progress</h5>
                            <div><?php echo nl2br(htmlspecialchars($row['progress_summary'] ?? '-')); ?></div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered small-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nama Job Portal</th>
                                <th>Ruang Lingkup Integrasi</th>
                                <th>Status Progress</th>
                                <th>Detail Progress Terakhir</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($details)): ?>
                                <tr><td><?php echo htmlspecialchars($row['portal_name']); ?></td><td>Integrasi Lowongan</td><td>-</td><td>-</td></tr>
                            <?php else: ?>
                                <?php foreach ($details as $idx => $d): ?>
                                    <tr>
                                        <?php if ($idx === 0): ?>
                                            <td rowspan="<?php echo count($details); ?>"><?php echo htmlspecialchars($row['portal_name']); ?></td>
                                        <?php endif; ?>
                                        <td><?php echo htmlspecialchars($d['integration_scope']); ?></td>
                                        <td><?php echo htmlspecialchars($d['status_progress']); ?></td>
                                        <td><?php echo htmlspecialchars($d['latest_progress_detail']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (!empty($rows)): ?>
        <div class="card monitor-card">
            <div class="card-body">
                <h5 class="mb-3">Ringkasan Monitoring</h5>
                <div class="table-responsive">
                    <table class="table table-bordered summary-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Job Portal</th>
                                <th class="text-center">Indikator</th>
                                <th class="text-center">Perizinan</th>
                                <th class="text-center">KB</th>
                                <th class="text-center">PKS</th>
                                <th class="text-center">NDA</th>
                                <th class="text-center">Integrasi</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                    $indicator = $row['progress_indicator'] ?? 'yellow';
                                    $lampClass = $indicator === 'green' ? 'lamp-green' : ($indicator === 'red' ? 'lamp-red' : 'lamp-yellow');
                                ?>
                                <tr>
                                    <td><strong><?php echo strtoupper(htmlspecialchars($row['portal_name'])); ?></strong></td>
                                    <td class="text-center">
                                        <span class="d-inline-flex align-items-center gap-1">
                                            <span class="indicator-lamp <?php echo $lampClass; ?>"></span>
                                            <small><?php echo htmlspecialchars(indicator_label($indicator)); ?></small>
                                        </span>
                                    </td>
                                    <td class="tick"><?php echo status_mark(intval($row['perizinan_done'])); ?></td>
                                    <td class="tick"><?php echo status_mark(intval($row['kb_done'])); ?></td>
                                    <td class="tick"><?php echo status_mark(intval($row['pks_done'])); ?></td>
                                    <td class="tick"><?php echo status_mark(intval($row['nda_done'])); ?></td>
                                    <td class="tick"><?php echo status_mark(intval($row['integrasi_done'])); ?></td>
                                    <td>
                                        <?php foreach (split_lines($row['notes'] ?? '') as $note): ?>
                                            <div>- <?php echo htmlspecialchars($note); ?></div>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
