<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';

if (!(current_user_can('settings_integrasi_karirhub_pemda_manage') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const PEMDA_GOOGLE_SHEET_CSV_URL = 'https://docs.google.com/spreadsheets/d/1D09RUuVHYev3eE2Vb8v3AoJz0Jphmw-CxlEPJLetQyw/gviz/tq?tqx=out:csv';

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

function fetch_remote_text(string $url): string {
    $data = @file_get_contents($url);
    if ($data !== false) {
        return (string) $data;
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Tidak bisa mengambil data sheet (file_get_contents dan cURL gagal).');
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $resp = curl_exec($ch);
    $code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
    $err = curl_error($ch);
    curl_close($ch);
    if ($resp === false || $code >= 400) {
        $suffix = $err !== '' ? (' Error: ' . $err) : (' HTTP: ' . $code);
        throw new RuntimeException('Gagal mengambil data sheet.' . $suffix);
    }
    return (string) $resp;
}

function csv_cell(array $row, int $idx): string {
    return trim((string) ($row[$idx] ?? ''));
}

function parse_bool_from_sheet(string $value): int {
    $v = strtolower(trim($value));
    if ($v === '' || $v === '0' || $v === '-' || $v === 'x') {
        return 0;
    }
    if (strpos($v, '%') !== false) {
        return 0;
    }
    return 1;
}

function pick_first_percent(string ...$values): string {
    foreach ($values as $value) {
        $v = trim($value);
        if ($v !== '' && preg_match('/^\d+%$/', $v)) {
            return $v;
        }
    }
    return '';
}

function derive_progress_percent(int $piaDone, int $bearerDone, int $clientDone, int $serviceDone, int $productionDone): string {
    $total = ($piaDone * 5) + ($bearerDone * 10) + ($clientDone * 20) + ($serviceDone * 40) + ($productionDone * 25);
    if ($total <= 0) {
        return '';
    }
    return $total . '%';
}

function parse_sheet_rows_from_csv(string $csv): array {
    $fp = fopen('php://temp', 'r+');
    fwrite($fp, $csv);
    rewind($fp);
    $rows = [];
    while (($data = fgetcsv($fp)) !== false) {
        $rows[] = $data;
    }
    fclose($fp);
    if (count($rows) < 2) {
        return [];
    }

    $parsed = [];
    foreach ($rows as $idx => $row) {
        if ($idx === 0) { // header
            continue;
        }
        $noRaw = csv_cell($row, 0);
        $dinas = csv_cell($row, 1);
        if ($dinas === '' || !ctype_digit($noRaw)) {
            continue;
        }
        $noUrut = intval($noRaw);
        if ($noUrut <= 0) {
            continue;
        }

        $piaDone = parse_bool_from_sheet(csv_cell($row, 8));
        $bearerDone = parse_bool_from_sheet(csv_cell($row, 9));
        $clientDone = parse_bool_from_sheet(csv_cell($row, 10));
        $serviceDone = parse_bool_from_sheet(csv_cell($row, 11));
        $productionDone = parse_bool_from_sheet(csv_cell($row, 12));

        $progress = pick_first_percent(csv_cell($row, 13), csv_cell($row, 14));
        if ($progress === '') {
            $progress = derive_progress_percent($piaDone, $bearerDone, $clientDone, $serviceDone, $productionDone);
        }

        $evaluation = pick_first_percent(csv_cell($row, 14));
        $nextColumns = [];
        for ($i = 18; $i < count($row); $i++) {
            $text = csv_cell($row, $i);
            if ($text !== '') {
                $nextColumns[] = $text;
            }
        }

        $parsed[] = [
            'no_urut' => $noUrut,
            'dinas' => $dinas,
            'logo_url' => csv_cell($row, 2),
            'level_tipe' => csv_cell($row, 3),
            'provinsi' => csv_cell($row, 4),
            'nama_aplikasi' => csv_cell($row, 5),
            'contact_person' => csv_cell($row, 6),
            'kategori_integrasi' => csv_cell($row, 7),
            'pia_done' => $piaDone,
            'bearer_token_done' => $bearerDone,
            'client_secret_done' => $clientDone,
            'service_integration_done' => $serviceDone,
            'production_done' => $productionDone,
            'progress_percent' => $progress,
            'evaluation_percent' => $evaluation,
            'keterangan' => csv_cell($row, 15),
            'pic_name' => csv_cell($row, 16),
            'status_integrasi' => csv_cell($row, 17),
            'next_steps' => implode("\n", $nextColumns),
            'raw_payload' => json_encode($row, JSON_UNESCAPED_UNICODE),
            'display_order' => $noUrut,
            'is_active' => 1,
        ];
    }
    return $parsed;
}

function import_pemda_sheet(mysqli $conn, string $csvUrl): int {
    $csv = fetch_remote_text($csvUrl);
    $parsedRows = parse_sheet_rows_from_csv($csv);
    if (empty($parsedRows)) {
        throw new RuntimeException('Tidak ada data valid yang bisa diimport dari Google Sheet.');
    }
    $stmt = $conn->prepare("INSERT INTO karirhub_pemda_monitoring (
            no_urut, dinas, logo_url, level_tipe, provinsi, nama_aplikasi, contact_person, kategori_integrasi,
            pia_done, bearer_token_done, client_secret_done, service_integration_done, production_done,
            progress_percent, evaluation_percent, keterangan, pic_name, status_integrasi, next_steps, raw_payload, display_order, is_active
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
            dinas=VALUES(dinas),
            logo_url=VALUES(logo_url),
            level_tipe=VALUES(level_tipe),
            provinsi=VALUES(provinsi),
            nama_aplikasi=VALUES(nama_aplikasi),
            contact_person=VALUES(contact_person),
            kategori_integrasi=VALUES(kategori_integrasi),
            pia_done=VALUES(pia_done),
            bearer_token_done=VALUES(bearer_token_done),
            client_secret_done=VALUES(client_secret_done),
            service_integration_done=VALUES(service_integration_done),
            production_done=VALUES(production_done),
            progress_percent=VALUES(progress_percent),
            evaluation_percent=VALUES(evaluation_percent),
            keterangan=VALUES(keterangan),
            pic_name=VALUES(pic_name),
            status_integrasi=VALUES(status_integrasi),
            next_steps=VALUES(next_steps),
            raw_payload=VALUES(raw_payload),
            display_order=VALUES(display_order),
            is_active=VALUES(is_active)");

    if (!$stmt) {
        throw new RuntimeException('Query import tidak bisa dipersiapkan.');
    }

    $affected = 0;
    foreach ($parsedRows as $row) {
        $stmt->bind_param(
            'isssssssiiiiisssssssii',
            $row['no_urut'],
            $row['dinas'],
            $row['logo_url'],
            $row['level_tipe'],
            $row['provinsi'],
            $row['nama_aplikasi'],
            $row['contact_person'],
            $row['kategori_integrasi'],
            $row['pia_done'],
            $row['bearer_token_done'],
            $row['client_secret_done'],
            $row['service_integration_done'],
            $row['production_done'],
            $row['progress_percent'],
            $row['evaluation_percent'],
            $row['keterangan'],
            $row['pic_name'],
            $row['status_integrasi'],
            $row['next_steps'],
            $row['raw_payload'],
            $row['display_order'],
            $row['is_active']
        );
        $stmt->execute();
        $affected++;
    }
    $stmt->close();
    return $affected;
}

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

ensure_karirhub_pemda_monitoring_table($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'import_sheet') {
        try {
            $count = import_pemda_sheet($conn, PEMDA_GOOGLE_SHEET_CSV_URL);
            $_SESSION['success'] = 'Import Google Sheet berhasil. ' . $count . ' baris diproses.';
        } catch (Throwable $e) {
            $_SESSION['error'] = 'Import gagal: ' . $e->getMessage();
        }
        header('Location: dashboard_monitoring_integrasi_karirhub_pemda_settings');
        exit;
    }

    if ($action === 'save') {
        $id = intval($_POST['id'] ?? 0);
        $noUrut = max(1, intval($_POST['no_urut'] ?? 1));
        $dinas = trim((string) ($_POST['dinas'] ?? ''));
        $logoUrl = trim((string) ($_POST['logo_url'] ?? ''));
        $levelTipe = trim((string) ($_POST['level_tipe'] ?? ''));
        $provinsi = trim((string) ($_POST['provinsi'] ?? ''));
        $namaAplikasi = trim((string) ($_POST['nama_aplikasi'] ?? ''));
        $contactPerson = trim((string) ($_POST['contact_person'] ?? ''));
        $kategoriIntegrasi = trim((string) ($_POST['kategori_integrasi'] ?? ''));
        $piaDone = isset($_POST['pia_done']) ? 1 : 0;
        $bearerDone = isset($_POST['bearer_token_done']) ? 1 : 0;
        $clientDone = isset($_POST['client_secret_done']) ? 1 : 0;
        $serviceDone = isset($_POST['service_integration_done']) ? 1 : 0;
        $productionDone = isset($_POST['production_done']) ? 1 : 0;
        $progressPercent = trim((string) ($_POST['progress_percent'] ?? ''));
        $evaluationPercent = trim((string) ($_POST['evaluation_percent'] ?? ''));
        $keterangan = trim((string) ($_POST['keterangan'] ?? ''));
        $picName = trim((string) ($_POST['pic_name'] ?? ''));
        $statusIntegrasi = trim((string) ($_POST['status_integrasi'] ?? ''));
        $nextSteps = trim((string) ($_POST['next_steps'] ?? ''));
        $displayOrder = intval($_POST['display_order'] ?? $noUrut);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($dinas === '') {
            $_SESSION['error'] = 'Nama dinas wajib diisi.';
            header('Location: dashboard_monitoring_integrasi_karirhub_pemda_settings');
            exit;
        }

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE karirhub_pemda_monitoring SET
                no_urut=?, dinas=?, logo_url=?, level_tipe=?, provinsi=?, nama_aplikasi=?, contact_person=?, kategori_integrasi=?,
                pia_done=?, bearer_token_done=?, client_secret_done=?, service_integration_done=?, production_done=?,
                progress_percent=?, evaluation_percent=?, keterangan=?, pic_name=?, status_integrasi=?, next_steps=?, display_order=?, is_active=?
                WHERE id=?");
            $stmt->bind_param(
                'isssssssiiiiissssssiii',
                $noUrut, $dinas, $logoUrl, $levelTipe, $provinsi, $namaAplikasi, $contactPerson, $kategoriIntegrasi,
                $piaDone, $bearerDone, $clientDone, $serviceDone, $productionDone,
                $progressPercent, $evaluationPercent, $keterangan, $picName, $statusIntegrasi, $nextSteps, $displayOrder, $isActive, $id
            );
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Data berhasil diperbarui.';
        } else {
            $stmt = $conn->prepare("INSERT INTO karirhub_pemda_monitoring (
                no_urut, dinas, logo_url, level_tipe, provinsi, nama_aplikasi, contact_person, kategori_integrasi,
                pia_done, bearer_token_done, client_secret_done, service_integration_done, production_done,
                progress_percent, evaluation_percent, keterangan, pic_name, status_integrasi, next_steps, display_order, is_active
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param(
                'isssssssiiiiissssssii',
                $noUrut, $dinas, $logoUrl, $levelTipe, $provinsi, $namaAplikasi, $contactPerson, $kategoriIntegrasi,
                $piaDone, $bearerDone, $clientDone, $serviceDone, $productionDone,
                $progressPercent, $evaluationPercent, $keterangan, $picName, $statusIntegrasi, $nextSteps, $displayOrder, $isActive
            );
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Data berhasil ditambahkan.';
        }

        header('Location: dashboard_monitoring_integrasi_karirhub_pemda_settings');
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare('DELETE FROM karirhub_pemda_monitoring WHERE id=?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Data berhasil dihapus.';
        }
        header('Location: dashboard_monitoring_integrasi_karirhub_pemda_settings');
        exit;
    }
}

$editRow = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    if ($editId > 0) {
        $stmt = $conn->prepare('SELECT * FROM karirhub_pemda_monitoring WHERE id=? LIMIT 1');
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $editRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

$allRows = [];
$resMain = $conn->query('SELECT * FROM karirhub_pemda_monitoring ORDER BY display_order ASC, no_urut ASC, id ASC');
while ($r = $resMain->fetch_assoc()) {
    $allRows[] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Integrasi Karirhub x Pemda Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Monitoring Integrasi Karirhub x Pemda Settings</h3>
        <a href="dashboard_monitoring_integrasi_karirhub_pemda" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-eye me-1"></i>Lihat Dashboard
        </a>
    </div>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo h((string) $_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo h((string) $_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Import Data Google Sheet</h5>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo h(PEMDA_GOOGLE_SHEET_CSV_URL); ?>" target="_blank" rel="noopener">Lihat CSV</a>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="import_sheet">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-cloud-download me-1"></i>Import dari Google Sheet
                </button>
                <div class="form-text mt-2">Sumber: <?php echo h(PEMDA_GOOGLE_SHEET_CSV_URL); ?></div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="mb-3"><?php echo $editRow ? 'Edit Data Pemda' : 'Tambah Data Pemda'; ?></h5>
            <form method="post">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?php echo intval($editRow['id'] ?? 0); ?>">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">No</label>
                        <input type="number" class="form-control" name="no_urut" min="1" required value="<?php echo intval($editRow['no_urut'] ?? 1); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Level</label>
                        <input type="text" class="form-control" name="level_tipe" value="<?php echo h((string) ($editRow['level_tipe'] ?? '')); ?>" placeholder="Kab/Kota / Provinsi">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Provinsi</label>
                        <input type="text" class="form-control" name="provinsi" value="<?php echo h((string) ($editRow['provinsi'] ?? '')); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <input type="text" class="form-control" name="status_integrasi" value="<?php echo h((string) ($editRow['status_integrasi'] ?? 'Process')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nama Dinas</label>
                        <input type="text" class="form-control" name="dinas" required value="<?php echo h((string) ($editRow['dinas'] ?? '')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nama Aplikasi</label>
                        <input type="text" class="form-control" name="nama_aplikasi" value="<?php echo h((string) ($editRow['nama_aplikasi'] ?? '')); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Contact Person</label>
                        <input type="text" class="form-control" name="contact_person" value="<?php echo h((string) ($editRow['contact_person'] ?? '')); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">PIC</label>
                        <input type="text" class="form-control" name="pic_name" value="<?php echo h((string) ($editRow['pic_name'] ?? '')); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Kategori Integrasi</label>
                        <input type="text" class="form-control" name="kategori_integrasi" value="<?php echo h((string) ($editRow['kategori_integrasi'] ?? '')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Progress (%)</label>
                        <input type="text" class="form-control" name="progress_percent" value="<?php echo h((string) ($editRow['progress_percent'] ?? '')); ?>" placeholder="contoh: 55%">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Evaluation (%)</label>
                        <input type="text" class="form-control" name="evaluation_percent" value="<?php echo h((string) ($editRow['evaluation_percent'] ?? '')); ?>" placeholder="contoh: 100%">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Checklist Tahapan</label>
                        <div class="row g-2">
                            <div class="col-md-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="pia_done" id="pia_done" <?php echo !empty($editRow['pia_done']) ? 'checked' : ''; ?>><label class="form-check-label" for="pia_done">PIA</label></div></div>
                            <div class="col-md-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="bearer_token_done" id="bearer_token_done" <?php echo !empty($editRow['bearer_token_done']) ? 'checked' : ''; ?>><label class="form-check-label" for="bearer_token_done">Bearer</label></div></div>
                            <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="client_secret_done" id="client_secret_done" <?php echo !empty($editRow['client_secret_done']) ? 'checked' : ''; ?>><label class="form-check-label" for="client_secret_done">Client Secret</label></div></div>
                            <div class="col-md-3"><div class="form-check"><input class="form-check-input" type="checkbox" name="service_integration_done" id="service_integration_done" <?php echo !empty($editRow['service_integration_done']) ? 'checked' : ''; ?>><label class="form-check-label" for="service_integration_done">Service Integration</label></div></div>
                            <div class="col-md-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="production_done" id="production_done" <?php echo !empty($editRow['production_done']) ? 'checked' : ''; ?>><label class="form-check-label" for="production_done">Production</label></div></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Keterangan</label>
                        <textarea class="form-control" name="keterangan" rows="3"><?php echo h((string) ($editRow['keterangan'] ?? '')); ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Next Step / Catatan Tambahan</label>
                        <textarea class="form-control" name="next_steps" rows="3"><?php echo h((string) ($editRow['next_steps'] ?? '')); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Logo URL</label>
                        <input type="text" class="form-control" name="logo_url" value="<?php echo h((string) ($editRow['logo_url'] ?? '')); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Display Order</label>
                        <input type="number" class="form-control" name="display_order" value="<?php echo intval($editRow['display_order'] ?? ($editRow['no_urut'] ?? 1)); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label d-block">Aktif</label>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?php echo !array_key_exists('is_active', (array) $editRow) || !empty($editRow['is_active']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">Tampilkan di dashboard</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan</button>
                        <a class="btn btn-secondary" href="dashboard_monitoring_integrasi_karirhub_pemda_settings">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5 class="mb-3">Data Existing</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>No</th>
                            <th>Dinas</th>
                            <th>Level</th>
                            <th>Provinsi</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Aktif</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($allRows)): ?>
                        <tr><td colspan="9" class="text-center text-muted">Belum ada data.</td></tr>
                    <?php else: ?>
                        <?php foreach ($allRows as $row): ?>
                            <tr>
                                <td><?php echo intval($row['id']); ?></td>
                                <td><?php echo intval($row['no_urut']); ?></td>
                                <td>
                                    <div class="fw-semibold"><?php echo h((string) ($row['dinas'] ?? '')); ?></div>
                                    <div class="small text-muted"><?php echo h((string) ($row['nama_aplikasi'] ?? '')); ?></div>
                                </td>
                                <td><?php echo h((string) ($row['level_tipe'] ?? '')); ?></td>
                                <td><?php echo h((string) ($row['provinsi'] ?? '')); ?></td>
                                <td><?php echo h((string) ($row['progress_percent'] ?? '-')); ?></td>
                                <td><?php echo h((string) ($row['status_integrasi'] ?? '-')); ?></td>
                                <td><?php echo intval($row['is_active'] ?? 0) === 1 ? 'Ya' : 'Tidak'; ?></td>
                                <td>
                                    <a class="btn btn-sm btn-outline-primary" href="?edit=<?php echo intval($row['id']); ?>"><i class="bi bi-pencil-square"></i></a>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Hapus data ini?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo intval($row['id']); ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
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
