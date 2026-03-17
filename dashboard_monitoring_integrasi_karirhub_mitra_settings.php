<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';

if (!(current_user_can('settings_integrasi_karirhub_mitra_manage') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
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
        notes TEXT DEFAULT NULL,
        display_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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
        ['hired_today', 'HiredToday', 'PT. Indo HR (Hired Today)', '', "Kesepahaman Bersama (KB)\nPerjanjian Kerjasama (PKS)\nNon-Disclosure Agreement (NDA)", 'Hired Today dan Karirhub sudah terintegrasi.', 1, 1, 1, 1, 1, "Adendum NDA proses TTD Job Portal\nLive in production", 1],
        ['glints', 'Glints', 'Glints Indonesia (Glints)', '', "Kesepahaman Bersama (KB)\nPerjanjian Kerjasama (PKS)\nNon-Disclosure Agreement (NDA)", 'Proses testing di staging area sandbox dan progres migrasi ke production.', 0, 1, 1, 0, 0, "NDA proses biro hukum\nProses migrasi ke production\nPerizinan masih dalam proses", 2],
        ['toploker', 'Toploker', 'PT Bisnis Digital Ekonomi (Top Loker)', '', "Kesepahaman Bersama (KB)\nPerjanjian Kerjasama (PKS)\nNon-Disclosure Agreement (NDA)", 'Integrasi Top Loker-Karirhub telah berjalan.', 1, 1, 1, 0, 1, "NDA proses TTD Job Portal\nLive in production", 3],
        ['redy', 'Redy', 'PT Rekrutmen Indonesia (getredy.id)', '', "Kesepahaman Bersama (KB)\nPerjanjian Kerjasama (PKS)\nNon-Disclosure Agreement (NDA)", 'Proses testing di staging area sandbox dan progres migrasi ke production.', 1, 1, 1, 0, 0, "NDA proses TTD Pasker\nProses migrasi ke production", 4],
        ['kitalulus', 'KitaLulus', 'KitaLulus Internasional', '', "Kesepahaman Bersama (KB)", 'Pihak KitaLulus sudah setuju untuk melakukan integrasi menggunakan sistem API.', 1, 1, 0, 0, 0, "Dijadwalkan pembahasan PKS dan NDA\nBelum integrasi", 5],
        ['kalibrr', 'Kalibrr', 'PT Kalibrr Technology Access (Kalibrr)', '', "Kesepahaman Bersama (KB)", 'Draft PKS dan NDA sedang proses penelaahan oleh tim Legal Kalibrr.', 0, 1, 0, 0, 0, "PKS dan NDA proses legal Kalibrr\nPerizinan masih dalam proses\nBelum integrasi", 6],
        ['dki', 'DKI', 'PT Disabilitas Kerja Indonesia (disabilitaskerja.co.id)', '', "Kesepahaman Bersama (KB)", "Belum menyelesaikan perizinan Aktivitas Penempatan Tenaga Kerja Daring (Job Portal), KBLI 78104.\nBelum memasuki pembahasan mengenai draft PKS dan NDA.", 0, 1, 0, 0, 0, "Dijadwalkan pembahasan PKS dan NDA\nPerizinan masih dalam proses\nBelum integrasi", 7],
        ['diploy', 'Diploy', 'Diploy Komdigi', '', "Kesepahaman Bersama (KB)\n(Kemnaker dengan Komdigi)", 'Draft PKS dan NDA sudah dikirimkan ke pihak Diploy.', 1, 1, 0, 0, 0, "PKS dan NDA menunggu feedback\nProses migrasi ke production", 8],
        ['jobstreet', 'Jobstreet', 'Jobstreet', '', '', 'Dijadwalkan penjajakan awal.', 1, 0, 0, 0, 0, "Dijadwalkan penjajakan\nBelum integrasi", 9],
    ];

    $insMain = $conn->prepare("INSERT INTO karirhub_mitra_monitoring (
        portal_code, portal_name, company_name, logo_url, cooperation_types, progress_summary,
        perizinan_done, kb_done, pks_done, nda_done, integrasi_done, notes, display_order
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
    foreach ($seedRows as $row) {
        $insMain->bind_param(
            'ssssssiiiiisi',
            $row[0], $row[1], $row[2], $row[3], $row[4], $row[5],
            $row[6], $row[7], $row[8], $row[9], $row[10], $row[11], $row[12]
        );
        $insMain->execute();
    }
    $insMain->close();

    $itemsMap = [
        'hired_today' => [
            ['Integrasi Lowongan', 'Selesai', 'Live in Production'],
            ['Kirim Lamaran', 'Selesai', '-'],
            ['Status Lamaran', 'Selesai', '-'],
        ],
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

function parse_detail_lines(string $text): array {
    $result = [];
    $lines = preg_split('/\r\n|\r|\n/', $text);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        $scope = $parts[0] ?? 'Integrasi Lowongan';
        $status = $parts[1] ?? 'On Progress';
        $detail = $parts[2] ?? '-';
        $result[] = ['scope' => $scope, 'status' => $status, 'detail' => $detail];
    }
    return $result;
}

function details_to_text(array $rows): string {
    $lines = [];
    foreach ($rows as $r) {
        $lines[] = trim($r['integration_scope']) . ' | ' . trim($r['status_progress']) . ' | ' . trim($r['latest_progress_detail']);
    }
    return implode("\n", $lines);
}

ensure_karirhub_mitra_monitoring_tables($conn);
seed_karirhub_mitra_monitoring($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = intval($_POST['id'] ?? 0);
        $portalCode = trim($_POST['portal_code'] ?? '');
        $portalName = trim($_POST['portal_name'] ?? '');
        $companyName = trim($_POST['company_name'] ?? '');
        $logoUrl = trim($_POST['logo_url'] ?? '');
        $cooperationTypes = trim($_POST['cooperation_types'] ?? '');
        $progressSummary = trim($_POST['progress_summary'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $displayOrder = intval($_POST['display_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $perizinanDone = isset($_POST['perizinan_done']) ? 1 : 0;
        $kbDone = isset($_POST['kb_done']) ? 1 : 0;
        $pksDone = isset($_POST['pks_done']) ? 1 : 0;
        $ndaDone = isset($_POST['nda_done']) ? 1 : 0;
        $integrasiDone = isset($_POST['integrasi_done']) ? 1 : 0;

        if ($portalCode === '' || $portalName === '' || $companyName === '') {
            $_SESSION['error'] = 'Portal code, portal name, and company name are required.';
            header('Location: dashboard_monitoring_integrasi_karirhub_mitra_settings.php');
            exit;
        }

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE karirhub_mitra_monitoring
                SET portal_code=?, portal_name=?, company_name=?, logo_url=?, cooperation_types=?, progress_summary=?,
                    perizinan_done=?, kb_done=?, pks_done=?, nda_done=?, integrasi_done=?, notes=?, display_order=?, is_active=?
                WHERE id=?");
            $stmt->bind_param(
                'ssssssiiiiisiii',
                $portalCode, $portalName, $companyName, $logoUrl, $cooperationTypes, $progressSummary,
                $perizinanDone, $kbDone, $pksDone, $ndaDone, $integrasiDone, $notes, $displayOrder, $isActive, $id
            );
            $stmt->execute();
            $stmt->close();
            $monitoringId = $id;
        } else {
            $stmt = $conn->prepare("INSERT INTO karirhub_mitra_monitoring (
                portal_code, portal_name, company_name, logo_url, cooperation_types, progress_summary,
                perizinan_done, kb_done, pks_done, nda_done, integrasi_done, notes, display_order, is_active
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param(
                'ssssssiiiiisii',
                $portalCode, $portalName, $companyName, $logoUrl, $cooperationTypes, $progressSummary,
                $perizinanDone, $kbDone, $pksDone, $ndaDone, $integrasiDone, $notes, $displayOrder, $isActive
            );
            $stmt->execute();
            $monitoringId = intval($stmt->insert_id);
            $stmt->close();
        }

        $conn->query('DELETE FROM karirhub_mitra_monitoring_items WHERE monitoring_id=' . $monitoringId);
        $itemRows = parse_detail_lines($_POST['detail_rows'] ?? '');
        if (!empty($itemRows)) {
            $ins = $conn->prepare("INSERT INTO karirhub_mitra_monitoring_items (monitoring_id, integration_scope, status_progress, latest_progress_detail, display_order) VALUES (?,?,?,?,?)");
            foreach ($itemRows as $idx => $item) {
                $sort = $idx + 1;
                $ins->bind_param('isssi', $monitoringId, $item['scope'], $item['status'], $item['detail'], $sort);
                $ins->execute();
            }
            $ins->close();
        }

        $_SESSION['success'] = 'Data saved successfully.';
        header('Location: dashboard_monitoring_integrasi_karirhub_mitra_settings.php');
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare('DELETE FROM karirhub_mitra_monitoring WHERE id=?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Data deleted.';
        }
        header('Location: dashboard_monitoring_integrasi_karirhub_mitra_settings.php');
        exit;
    }
}

$editRow = null;
$detailText = "Integrasi Lowongan | On Progress | -";
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $stmt = $conn->prepare('SELECT * FROM karirhub_mitra_monitoring WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $result = $stmt->get_result();
    $editRow = $result->fetch_assoc();
    $stmt->close();

    if ($editRow) {
        $detailRows = [];
        $resItems = $conn->query('SELECT integration_scope, status_progress, latest_progress_detail FROM karirhub_mitra_monitoring_items WHERE monitoring_id=' . intval($editRow['id']) . ' ORDER BY display_order ASC, id ASC');
        while ($r = $resItems->fetch_assoc()) {
            $detailRows[] = $r;
        }
        if (!empty($detailRows)) {
            $detailText = details_to_text($detailRows);
        }
    }
}

$allRows = [];
$resMain = $conn->query("SELECT * FROM karirhub_mitra_monitoring ORDER BY display_order ASC, id ASC");
while ($r = $resMain->fetch_assoc()) {
    $allRows[] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Integrasi Karirhub x Mitra Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Monitoring Integrasi Karirhub x Mitra Settings</h3>
        <a href="dashboard_monitoring_integrasi_karirhub_mitra.php" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-eye me-1"></i>Lihat Dashboard
        </a>
    </div>

    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="mb-3"><?php echo $editRow ? 'Edit Data Portal' : 'Tambah Data Portal'; ?></h5>
            <form method="post">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?php echo intval($editRow['id'] ?? 0); ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Portal Code (unique)</label>
                        <input class="form-control" type="text" name="portal_code" required value="<?php echo htmlspecialchars($editRow['portal_code'] ?? ''); ?>" placeholder="contoh: glints">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Nama Job Portal</label>
                        <input class="form-control" type="text" name="portal_name" required value="<?php echo htmlspecialchars($editRow['portal_name'] ?? ''); ?>" placeholder="contoh: Glints">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Display Order</label>
                        <input class="form-control" type="number" name="display_order" value="<?php echo intval($editRow['display_order'] ?? 0); ?>">
                    </div>

                    <div class="col-md-8">
                        <label class="form-label">Nama Perusahaan / Judul Kartu</label>
                        <input class="form-control" type="text" name="company_name" required value="<?php echo htmlspecialchars($editRow['company_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Logo URL</label>
                        <input class="form-control" type="text" name="logo_url" value="<?php echo htmlspecialchars($editRow['logo_url'] ?? ''); ?>" placeholder="/images/... atau https://...">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Jenis Kerjasama (1 baris = 1 item)</label>
                        <textarea class="form-control" name="cooperation_types" rows="4"><?php echo htmlspecialchars($editRow['cooperation_types'] ?? "Kesepahaman Bersama (KB)\nPerjanjian Kerjasama (PKS)\nNon-Disclosure Agreement (NDA)"); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Progress Ringkas</label>
                        <textarea class="form-control" name="progress_summary" rows="4"><?php echo htmlspecialchars($editRow['progress_summary'] ?? ''); ?></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Detail Tabel Progress</label>
                        <textarea class="form-control" name="detail_rows" rows="5"><?php echo htmlspecialchars($detailText); ?></textarea>
                        <div class="form-text">Format tiap baris: <code>Ruang Lingkup | Status Progress | Detail Progress Terakhir</code></div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Keterangan Ringkasan (1 baris = 1 bullet)</label>
                        <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($editRow['notes'] ?? ''); ?></textarea>
                    </div>

                    <div class="col-12">
                        <div class="row g-2">
                            <div class="col-md-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="perizinan_done" id="perizinan_done" <?php echo !empty($editRow['perizinan_done']) ? 'checked' : ''; ?>><label class="form-check-label" for="perizinan_done">Perizinan</label></div></div>
                            <div class="col-md-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="kb_done" id="kb_done" <?php echo !empty($editRow['kb_done']) ? 'checked' : ''; ?>><label class="form-check-label" for="kb_done">KB</label></div></div>
                            <div class="col-md-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="pks_done" id="pks_done" <?php echo !empty($editRow['pks_done']) ? 'checked' : ''; ?>><label class="form-check-label" for="pks_done">PKS</label></div></div>
                            <div class="col-md-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="nda_done" id="nda_done" <?php echo !empty($editRow['nda_done']) ? 'checked' : ''; ?>><label class="form-check-label" for="nda_done">NDA</label></div></div>
                            <div class="col-md-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="integrasi_done" id="integrasi_done" <?php echo !empty($editRow['integrasi_done']) ? 'checked' : ''; ?>><label class="form-check-label" for="integrasi_done">Integrasi</label></div></div>
                            <div class="col-md-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?php echo !array_key_exists('is_active', (array)$editRow) || !empty($editRow['is_active']) ? 'checked' : ''; ?>><label class="form-check-label" for="is_active">Aktif</label></div></div>
                        </div>
                    </div>

                    <div class="col-12">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan</button>
                        <a class="btn btn-secondary" href="dashboard_monitoring_integrasi_karirhub_mitra_settings.php">Reset</a>
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
                            <th>Portal</th>
                            <th>Company</th>
                            <th>Order</th>
                            <th>Aktif</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allRows)): ?>
                            <tr><td colspan="6" class="text-center">No data</td></tr>
                        <?php else: ?>
                            <?php foreach ($allRows as $row): ?>
                                <tr>
                                    <td><?php echo intval($row['id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['portal_name']); ?><br><span class="text-muted small"><?php echo htmlspecialchars($row['portal_code']); ?></span></td>
                                    <td><?php echo htmlspecialchars($row['company_name']); ?></td>
                                    <td><?php echo intval($row['display_order']); ?></td>
                                    <td><?php echo intval($row['is_active']) === 1 ? 'Ya' : 'Tidak'; ?></td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary" href="?edit=<?php echo intval($row['id']); ?>"><i class="bi bi-pencil-square"></i></a>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this item?');">
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
