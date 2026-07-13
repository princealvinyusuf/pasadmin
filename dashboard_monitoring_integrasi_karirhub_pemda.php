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
    if (!(current_user_can('view_dashboard_integrasi_karirhub_pemda') || current_user_can('manage_settings'))) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
} else {
    if (!function_exists('current_user_can')) {
        function current_user_can(string $code): bool { return false; }
    }
}

function column_exists(mysqli $conn, string $table, string $column): bool {
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($column);
    $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function ensure_karirhub_pemda_monitoring_table(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS karirhub_pemda_monitoring (
        id INT AUTO_INCREMENT PRIMARY KEY,
        no_urut INT NOT NULL DEFAULT 0,
        dinas VARCHAR(220) NOT NULL,
        logo_url VARCHAR(500) DEFAULT '',
        level_tipe VARCHAR(80) DEFAULT '',
        provinsi VARCHAR(120) DEFAULT '',
        nama_aplikasi VARCHAR(255) DEFAULT '',
        contact_person VARCHAR(180) DEFAULT '',
        kategori_integrasi VARCHAR(120) DEFAULT '',
        pia_done TINYINT(1) NOT NULL DEFAULT 0,
        bearer_token_done TINYINT(1) NOT NULL DEFAULT 0,
        client_secret_done TINYINT(1) NOT NULL DEFAULT 0,
        service_integration_done TINYINT(1) NOT NULL DEFAULT 0,
        production_done TINYINT(1) NOT NULL DEFAULT 0,
        progress_percent VARCHAR(20) DEFAULT '',
        evaluation_percent VARCHAR(20) DEFAULT '',
        keterangan TEXT DEFAULT NULL,
        pic_name VARCHAR(120) DEFAULT '',
        status_integrasi VARCHAR(60) DEFAULT '',
        next_steps TEXT DEFAULT NULL,
        raw_payload LONGTEXT DEFAULT NULL,
        display_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_pemda_no_urut (no_urut),
        KEY idx_pemda_status (status_integrasi),
        KEY idx_pemda_level (level_tipe),
        KEY idx_pemda_provinsi (provinsi)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (!column_exists($conn, 'karirhub_pemda_monitoring', 'evaluation_percent')) {
        $conn->query("ALTER TABLE karirhub_pemda_monitoring ADD COLUMN evaluation_percent VARCHAR(20) DEFAULT '' AFTER progress_percent");
    }
    if (!column_exists($conn, 'karirhub_pemda_monitoring', 'next_steps')) {
        $conn->query("ALTER TABLE karirhub_pemda_monitoring ADD COLUMN next_steps TEXT DEFAULT NULL AFTER status_integrasi");
    }
}

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function as_bool_mark(int $value): string {
    return $value === 1 ? 'Ya' : '-';
}

function status_badge_class(string $status): string {
    $normalized = strtolower(trim($status));
    if ($normalized === 'done' || $normalized === 'evaluasi') {
        return 'success';
    }
    if ($normalized === 'process') {
        return 'warning text-dark';
    }
    return 'secondary';
}

ensure_karirhub_pemda_monitoring_table($conn);

$rows = [];
$resMain = $conn->query("SELECT * FROM karirhub_pemda_monitoring WHERE is_active=1 ORDER BY display_order ASC, no_urut ASC, id ASC");
while ($r = $resMain->fetch_assoc()) {
    $rows[] = $r;
}

$statusOptions = ['all' => 'Semua Status'];
$levelOptions = ['all' => 'Semua Level'];
$provinsiOptions = ['all' => 'Semua Provinsi'];
foreach ($rows as $row) {
    $status = trim((string) ($row['status_integrasi'] ?? ''));
    $level = trim((string) ($row['level_tipe'] ?? ''));
    $provinsi = trim((string) ($row['provinsi'] ?? ''));
    if ($status !== '') { $statusOptions[$status] = $status; }
    if ($level !== '') { $levelOptions[$level] = $level; }
    if ($provinsi !== '') { $provinsiOptions[$provinsi] = $provinsi; }
}
ksort($statusOptions, SORT_NATURAL | SORT_FLAG_CASE);
ksort($levelOptions, SORT_NATURAL | SORT_FLAG_CASE);
ksort($provinsiOptions, SORT_NATURAL | SORT_FLAG_CASE);
$statusOptions = ['all' => 'Semua Status'] + array_diff_key($statusOptions, ['all' => true]);
$levelOptions = ['all' => 'Semua Level'] + array_diff_key($levelOptions, ['all' => true]);
$provinsiOptions = ['all' => 'Semua Provinsi'] + array_diff_key($provinsiOptions, ['all' => true]);

$selectedStatus = trim((string) ($_GET['status_integrasi'] ?? 'all'));
$selectedLevel = trim((string) ($_GET['level_tipe'] ?? 'all'));
$selectedProvinsi = trim((string) ($_GET['provinsi'] ?? 'all'));
if (!array_key_exists($selectedStatus, $statusOptions)) { $selectedStatus = 'all'; }
if (!array_key_exists($selectedLevel, $levelOptions)) { $selectedLevel = 'all'; }
if (!array_key_exists($selectedProvinsi, $provinsiOptions)) { $selectedProvinsi = 'all'; }

if ($selectedStatus !== 'all' || $selectedLevel !== 'all' || $selectedProvinsi !== 'all') {
    $rows = array_values(array_filter($rows, static function (array $row) use ($selectedStatus, $selectedLevel, $selectedProvinsi): bool {
        if ($selectedStatus !== 'all' && trim((string) ($row['status_integrasi'] ?? '')) !== $selectedStatus) { return false; }
        if ($selectedLevel !== 'all' && trim((string) ($row['level_tipe'] ?? '')) !== $selectedLevel) { return false; }
        if ($selectedProvinsi !== 'all' && trim((string) ($row['provinsi'] ?? '')) !== $selectedProvinsi) { return false; }
        return true;
    }));
}

$totalRows = count($rows);
$totalDone = 0;
$totalProcess = 0;
foreach ($rows as $row) {
    $status = strtolower(trim((string) ($row['status_integrasi'] ?? '')));
    if ($status === 'done' || $status === 'evaluasi') {
        $totalDone++;
    } elseif ($status === 'process') {
        $totalProcess++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Monitoring Integrasi Karirhub x Pemda</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php if ($userIsLoggedIn) { include 'navbar.php'; } ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Dashboard Monitoring Integrasi Karirhub x Pemda</h3>
        <?php if (current_user_can('settings_integrasi_karirhub_pemda_manage') || current_user_can('manage_settings')): ?>
            <a class="btn btn-outline-primary btn-sm" href="dashboard_monitoring_integrasi_karirhub_pemda_settings">
                <i class="bi bi-gear me-1"></i>Kelola Data
            </a>
        <?php endif; ?>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Total Pemda</div>
                    <div class="h4 mb-0"><?php echo number_format($totalRows); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Done / Evaluasi</div>
                    <div class="h4 mb-0 text-success"><?php echo number_format($totalDone); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Process</div>
                    <div class="h4 mb-0 text-warning"><?php echo number_format($totalProcess); ?></div>
                </div>
            </div>
        </div>
    </div>

    <form method="GET" class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label mb-1">Status</label>
                    <select class="form-select form-select-sm" name="status_integrasi">
                        <?php foreach ($statusOptions as $value => $label): ?>
                            <option value="<?php echo h($value); ?>"<?php echo $selectedStatus === $value ? ' selected' : ''; ?>><?php echo h($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1">Level</label>
                    <select class="form-select form-select-sm" name="level_tipe">
                        <?php foreach ($levelOptions as $value => $label): ?>
                            <option value="<?php echo h($value); ?>"<?php echo $selectedLevel === $value ? ' selected' : ''; ?>><?php echo h($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Provinsi</label>
                    <select class="form-select form-select-sm" name="provinsi">
                        <?php foreach ($provinsiOptions as $value => $label): ?>
                            <option value="<?php echo h($value); ?>"<?php echo $selectedProvinsi === $value ? ' selected' : ''; ?>><?php echo h($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 d-grid">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i></button>
                </div>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Dinas</th>
                            <th>Level</th>
                            <th>Provinsi</th>
                            <th>Nama Aplikasi</th>
                            <th>Tahapan</th>
                            <th>Progress</th>
                            <th>Keterangan</th>
                            <th>PIC</th>
                            <th>Status</th>
                            <th>Next</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="11" class="text-center text-muted">Belum ada data.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php $status = trim((string) ($row['status_integrasi'] ?? '')); ?>
                            <tr>
                                <td><?php echo intval($row['no_urut']); ?></td>
                                <td>
                                    <div class="fw-semibold"><?php echo h((string) ($row['dinas'] ?? '')); ?></div>
                                    <div class="text-muted small"><?php echo h((string) ($row['contact_person'] ?? '-')); ?></div>
                                </td>
                                <td><?php echo h((string) ($row['level_tipe'] ?? '-')); ?></td>
                                <td><?php echo h((string) ($row['provinsi'] ?? '-')); ?></td>
                                <td><?php echo h((string) ($row['nama_aplikasi'] ?? '-')); ?></td>
                                <td class="small">
                                    <div>PIA: <?php echo as_bool_mark(intval($row['pia_done'] ?? 0)); ?></div>
                                    <div>Bearer: <?php echo as_bool_mark(intval($row['bearer_token_done'] ?? 0)); ?></div>
                                    <div>Client: <?php echo as_bool_mark(intval($row['client_secret_done'] ?? 0)); ?></div>
                                    <div>Service: <?php echo as_bool_mark(intval($row['service_integration_done'] ?? 0)); ?></div>
                                    <div>Prod: <?php echo as_bool_mark(intval($row['production_done'] ?? 0)); ?></div>
                                </td>
                                <td>
                                    <div><?php echo h((string) ($row['progress_percent'] ?? '-')); ?></div>
                                    <?php if (!empty($row['evaluation_percent'])): ?>
                                        <div class="small text-muted">Evaluasi: <?php echo h((string) $row['evaluation_percent']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?php echo nl2br(h((string) ($row['keterangan'] ?? '-'))); ?></td>
                                <td><?php echo h((string) ($row['pic_name'] ?? '-')); ?></td>
                                <td><span class="badge bg-<?php echo status_badge_class($status); ?>"><?php echo h($status !== '' ? $status : '-'); ?></span></td>
                                <td class="small"><?php echo nl2br(h((string) ($row['next_steps'] ?? '-'))); ?></td>
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
