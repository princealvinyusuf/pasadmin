<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_data.php';
require_once __DIR__ . '/karirhub_employer_prototype_ui.php';
require_once __DIR__ . '/db.php';

if (!(current_user_can('karirhub_employer_prototype_view') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$dataset = karirhub_proto_dataset();
$units = $dataset['units'];
$rows = $dataset['vacancies'];

$conn->query("CREATE TABLE IF NOT EXISTS karirhub_proto_wllp_status (
    no_reg_bukti VARCHAR(60) PRIMARY KEY,
    id_lowongan VARCHAR(30) NOT NULL,
    jabatan VARCHAR(200) NOT NULL,
    unit_nama VARCHAR(255) NOT NULL,
    status_saat_ini VARCHAR(50) NOT NULL,
    tanggal_lapor DATE NOT NULL,
    tanggal_terisi DATE DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS karirhub_proto_wllp_penempatan (
    no_reg_bukti VARCHAR(60) PRIMARY KEY,
    nik VARCHAR(30) NOT NULL,
    nama_lengkap VARCHAR(180) NOT NULL,
    pendidikan VARCHAR(120) NOT NULL,
    jenis_kelamin VARCHAR(30) NOT NULL,
    tempat_lahir VARCHAR(120) NOT NULL,
    tanggal_lahir DATE NOT NULL,
    alamat TEXT NOT NULL,
    status_disabilitas VARCHAR(10) NOT NULL,
    tmt DATE NOT NULL,
    email VARCHAR(180) NOT NULL,
    nomor_hp VARCHAR(40) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$stmtSeedStatus = $conn->prepare("
    INSERT INTO karirhub_proto_wllp_status
        (no_reg_bukti, id_lowongan, jabatan, unit_nama, status_saat_ini, tanggal_lapor, tanggal_terisi)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        id_lowongan = VALUES(id_lowongan),
        jabatan = VALUES(jabatan),
        unit_nama = VALUES(unit_nama),
        tanggal_lapor = VALUES(tanggal_lapor)
");
foreach ($rows as $seedRow) {
    $noReg = (string)$seedRow['no_reg_bukti'];
    $idLowongan = (string)$seedRow['id_lowongan'];
    $jabatan = (string)$seedRow['jabatan'];
    $unitNama = (string)($units[$seedRow['unit_kode']]['nama'] ?? $seedRow['unit_kode']);
    $statusSaatIni = (string)$seedRow['status_keterisian'];
    $tanggalLapor = (string)$seedRow['tanggal_lapor'];
    $tanggalTerisi = $seedRow['tanggal_terisi'] !== null && $seedRow['tanggal_terisi'] !== '' ? (string)$seedRow['tanggal_terisi'] : null;
    $stmtSeedStatus->bind_param('sssssss', $noReg, $idLowongan, $jabatan, $unitNama, $statusSaatIni, $tanggalLapor, $tanggalTerisi);
    $stmtSeedStatus->execute();
}
$stmtSeedStatus->close();

$statusFilter = trim((string)($_REQUEST['status'] ?? 'all'));
$allowedStatus = ['all', 'belum terisi', 'proses seleksi', 'terisi', 'belum update'];
if (!in_array($statusFilter, $allowedStatus, true)) {
    $statusFilter = 'all';
}
$unitFilter = trim((string)($_REQUEST['unit'] ?? 'all'));
if ($unitFilter !== 'all' && !isset($units[$unitFilter])) {
    $unitFilter = 'all';
}

$simulatedNoReg = trim((string)($_GET['simulate_no_reg'] ?? ''));
$simulatedStatus = trim((string)($_GET['simulate_status'] ?? ''));
$successMessage = null;
if ($simulatedNoReg !== '' && in_array($simulatedStatus, ['Belum Terisi', 'Proses Seleksi', 'Terisi', 'Belum Update'], true)) {
    $successMessage = 'Simulasi update status untuk ' . $simulatedNoReg . ' -> ' . $simulatedStatus . ' berhasil (dummy, tidak disimpan permanen).';
}

$rowMap = [];
foreach ($rows as $row) {
    $rowMap[$row['no_reg_bukti']] = $row;
}

$pegawaiForm = [
    'nik' => trim((string)($_POST['nik'] ?? '')),
    'nama_lengkap' => trim((string)($_POST['nama_lengkap'] ?? '')),
    'pendidikan' => trim((string)($_POST['pendidikan'] ?? '')),
    'jenis_kelamin' => trim((string)($_POST['jenis_kelamin'] ?? '')),
    'tempat_lahir' => trim((string)($_POST['tempat_lahir'] ?? '')),
    'tanggal_lahir' => trim((string)($_POST['tanggal_lahir'] ?? '')),
    'alamat' => trim((string)($_POST['alamat'] ?? '')),
    'status_disabilitas' => trim((string)($_POST['status_disabilitas'] ?? '')),
    'tmt' => trim((string)($_POST['tmt'] ?? '')),
    'email' => trim((string)($_POST['email'] ?? '')),
    'nomor_hp' => trim((string)($_POST['nomor_hp'] ?? '')),
];

$pegawaiErrors = [];
$openTerisiNoReg = trim((string)($_GET['open_terisi_for'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['form_action'] ?? '') === 'submit_terisi_data') {
    $openTerisiNoReg = trim((string)($_POST['no_reg_bukti'] ?? ''));
    $requiredPegawaiFields = [
        'nik' => 'NIK',
        'nama_lengkap' => 'Nama Lengkap',
        'pendidikan' => 'Pendidikan',
        'jenis_kelamin' => 'Jenis Kelamin',
        'tempat_lahir' => 'Tempat Lahir',
        'tanggal_lahir' => 'Tanggal Lahir',
        'alamat' => 'Alamat',
        'status_disabilitas' => 'Status Disabilitas',
        'tmt' => 'TMT',
        'email' => 'Email',
        'nomor_hp' => 'Nomor Hp',
    ];
    foreach ($requiredPegawaiFields as $field => $label) {
        if ($pegawaiForm[$field] === '') {
            $pegawaiErrors[] = $label . ' wajib diisi.';
        }
    }
    if ($pegawaiForm['status_disabilitas'] !== '' && !in_array($pegawaiForm['status_disabilitas'], ['Iya', 'Tidak'], true)) {
        $pegawaiErrors[] = 'Status Disabilitas hanya boleh Iya atau Tidak.';
    }
    if ($openTerisiNoReg === '' || !isset($rowMap[$openTerisiNoReg])) {
        $pegawaiErrors[] = 'Data lowongan untuk status Terisi tidak ditemukan.';
    }

    if (empty($pegawaiErrors)) {
        $stmtSavePegawai = $conn->prepare("
            INSERT INTO karirhub_proto_wllp_penempatan
                (no_reg_bukti, nik, nama_lengkap, pendidikan, jenis_kelamin, tempat_lahir, tanggal_lahir, alamat, status_disabilitas, tmt, email, nomor_hp)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                nik = VALUES(nik),
                nama_lengkap = VALUES(nama_lengkap),
                pendidikan = VALUES(pendidikan),
                jenis_kelamin = VALUES(jenis_kelamin),
                tempat_lahir = VALUES(tempat_lahir),
                tanggal_lahir = VALUES(tanggal_lahir),
                alamat = VALUES(alamat),
                status_disabilitas = VALUES(status_disabilitas),
                tmt = VALUES(tmt),
                email = VALUES(email),
                nomor_hp = VALUES(nomor_hp)
        ");
        $stmtSavePegawai->bind_param(
            'ssssssssssss',
            $openTerisiNoReg,
            $pegawaiForm['nik'],
            $pegawaiForm['nama_lengkap'],
            $pegawaiForm['pendidikan'],
            $pegawaiForm['jenis_kelamin'],
            $pegawaiForm['tempat_lahir'],
            $pegawaiForm['tanggal_lahir'],
            $pegawaiForm['alamat'],
            $pegawaiForm['status_disabilitas'],
            $pegawaiForm['tmt'],
            $pegawaiForm['email'],
            $pegawaiForm['nomor_hp']
        );
        $stmtSavePegawai->execute();
        $stmtSavePegawai->close();

        $statusTerisi = 'Terisi';
        $tanggalTerisiNow = $pegawaiForm['tmt'] !== '' ? $pegawaiForm['tmt'] : date('Y-m-d');
        $stmtUpdateStatus = $conn->prepare("UPDATE karirhub_proto_wllp_status SET status_saat_ini = ?, tanggal_terisi = ? WHERE no_reg_bukti = ?");
        $stmtUpdateStatus->bind_param('sss', $statusTerisi, $tanggalTerisiNow, $openTerisiNoReg);
        $stmtUpdateStatus->execute();
        $stmtUpdateStatus->close();

        $successMessage = 'Simulasi update status untuk ' . $openTerisiNoReg . ' -> Terisi berhasil. Data pegawai ditempatkan atas nama '
            . $pegawaiForm['nama_lengkap'] . ' telah dilengkapi.';
        $openTerisiNoReg = '';
        foreach ($pegawaiForm as $key => $_) {
            $pegawaiForm[$key] = '';
        }
    }
}

$openTerisiRow = ($openTerisiNoReg !== '' && isset($rowMap[$openTerisiNoReg])) ? $rowMap[$openTerisiNoReg] : null;

$templateRows = [];
$resTemplate = $conn->query("
    SELECT
        s.no_reg_bukti,
        s.id_lowongan,
        s.jabatan,
        s.unit_nama,
        s.status_saat_ini,
        s.tanggal_lapor,
        s.tanggal_terisi,
        COALESCE(p.nik, '') AS nik,
        COALESCE(p.nama_lengkap, '') AS nama_lengkap,
        COALESCE(p.pendidikan, '') AS pendidikan,
        COALESCE(p.jenis_kelamin, '') AS jenis_kelamin,
        COALESCE(p.tempat_lahir, '') AS tempat_lahir,
        COALESCE(CAST(p.tanggal_lahir AS CHAR), '') AS tanggal_lahir,
        COALESCE(p.alamat, '') AS alamat,
        COALESCE(p.status_disabilitas, '') AS status_disabilitas,
        COALESCE(CAST(p.tmt AS CHAR), '') AS tmt,
        COALESCE(p.email, '') AS email,
        COALESCE(p.nomor_hp, '') AS nomor_hp
    FROM karirhub_proto_wllp_status s
    LEFT JOIN karirhub_proto_wllp_penempatan p ON p.no_reg_bukti = s.no_reg_bukti
    ORDER BY s.tanggal_lapor DESC, s.no_reg_bukti DESC
");
if ($resTemplate) {
    while ($r = $resTemplate->fetch_assoc()) {
        $templateRows[] = $r;
    }
}

$statusMap = [];
foreach ($templateRows as $tRow) {
    $statusMap[(string)$tRow['no_reg_bukti']] = [
        'status_saat_ini' => (string)$tRow['status_saat_ini'],
        'tanggal_terisi' => (string)$tRow['tanggal_terisi'],
    ];
}
foreach ($rows as $idx => $baseRow) {
    $nr = (string)$baseRow['no_reg_bukti'];
    if (isset($statusMap[$nr])) {
        $rows[$idx]['status_keterisian'] = $statusMap[$nr]['status_saat_ini'] !== '' ? $statusMap[$nr]['status_saat_ini'] : $baseRow['status_keterisian'];
        $rows[$idx]['tanggal_terisi'] = $statusMap[$nr]['tanggal_terisi'] !== '' ? $statusMap[$nr]['tanggal_terisi'] : $baseRow['tanggal_terisi'];
    }
}
$unitCodeByName = [];
foreach ($units as $code => $unitInfo) {
    $unitCodeByName[(string)$unitInfo['nama']] = (string)$code;
}
foreach ($templateRows as $dbRow) {
    $nr = (string)$dbRow['no_reg_bukti'];
    if (isset($rowMap[$nr])) {
        continue;
    }
    $unitCode = $unitCodeByName[(string)$dbRow['unit_nama']] ?? (string)$dbRow['unit_nama'];
    $rows[] = [
        'no_reg_bukti' => $nr,
        'id_lowongan' => (string)$dbRow['id_lowongan'],
        'jabatan' => (string)$dbRow['jabatan'],
        'unit_kode' => $unitCode,
        'status_keterisian' => (string)$dbRow['status_saat_ini'],
        'tanggal_lapor' => (string)$dbRow['tanggal_lapor'],
        'tanggal_terisi' => (string)$dbRow['tanggal_terisi'],
    ];
}
// Remap row index after DB merge so modals can resolve newly inserted records.
$rowMap = [];
foreach ($rows as $row) {
    $rowMap[$row['no_reg_bukti']] = $row;
}
foreach ($rows as $row) {
    $rowMap[$row['no_reg_bukti']] = $row;
}

$filteredRows = array_values(array_filter($rows, static function (array $row) use ($statusFilter, $unitFilter): bool {
    if ($statusFilter !== 'all' && strtolower($row['status_keterisian']) !== $statusFilter) {
        return false;
    }
    if ($unitFilter !== 'all' && $row['unit_kode'] !== $unitFilter) {
        return false;
    }
    return true;
}));

$countByStatus = ['Belum Terisi' => 0, 'Proses Seleksi' => 0, 'Terisi' => 0, 'Belum Update' => 0];
foreach ($rows as $row) {
    if (isset($countByStatus[$row['status_keterisian']])) {
        $countByStatus[$row['status_keterisian']]++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karirhub Employer Prototype - Status Keterisian</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php kh_proto_render_styles(); ?>
</head>
<body class="kh-proto-page">
<?php include 'navbar.php'; ?>
<?php kh_proto_render_hero('Daftar Lowongan Kerja', 'Pantau dan simulasikan status keterisian lowongan seperti dashboard employer.', 'Lowongan Kerja', 'karirhub_employer_prototype_pelaporan_lowongan', 'Proyek', 'karirhub_employer_prototype_dashboard_wllp'); ?>

<div class="kh-content-wrap">
<div class="container py-4">
    <div class="kh-proto-shell">
    <?php kh_proto_render_sidebar('wllp_status_keterisian'); ?>
    <main class="kh-proto-main">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-0">Status Keterisian</h3>
            <div class="text-muted small">Simulasi update status lowongan WLLP</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-success btn-sm" id="btnDownloadTemplate">
                <i class="bi bi-download me-1"></i>Download Template
            </button>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#bulkImportModal">
                <i class="bi bi-file-earmark-arrow-up me-1"></i>Bulk Import
            </button>
            <a class="btn btn-outline-primary btn-sm" href="karirhub_employer_prototype_dashboard_wllp">
                <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard WLLP
            </a>
        </div>
    </div>

    <?php if ($successMessage !== null): ?>
        <div class="alert alert-success py-2"><?php echo h($successMessage); ?></div>
    <?php endif; ?>
    <?php if (!empty($pegawaiErrors)): ?>
        <div class="alert alert-danger py-2">
            <div class="fw-semibold mb-1">Lengkapi Data Pegawai yang ditempatkan:</div>
            <ul class="mb-0">
                <?php foreach ($pegawaiErrors as $err): ?>
                    <li><?php echo h($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <?php foreach ($countByStatus as $statusName => $statusCount): ?>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small"><?php echo h($statusName); ?></div>
                        <div class="fs-4 fw-semibold text-<?php echo h(karirhub_proto_status_badge_class($statusName)); ?>"><?php echo h((string)$statusCount); ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <form method="GET" class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label mb-1">Status Keterisian</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="all"<?php echo $statusFilter === 'all' ? ' selected' : ''; ?>>Semua Status</option>
                        <option value="belum terisi"<?php echo $statusFilter === 'belum terisi' ? ' selected' : ''; ?>>Belum Terisi</option>
                        <option value="proses seleksi"<?php echo $statusFilter === 'proses seleksi' ? ' selected' : ''; ?>>Proses Seleksi</option>
                        <option value="terisi"<?php echo $statusFilter === 'terisi' ? ' selected' : ''; ?>>Terisi</option>
                        <option value="belum update"<?php echo $statusFilter === 'belum update' ? ' selected' : ''; ?>>Belum Update</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label mb-1">Unit Perusahaan</label>
                    <select name="unit" class="form-select form-select-sm">
                        <option value="all"<?php echo $unitFilter === 'all' ? ' selected' : ''; ?>>Semua Unit</option>
                        <?php foreach ($units as $unitCode => $unit): ?>
                            <option value="<?php echo h($unitCode); ?>"<?php echo $unitFilter === $unitCode ? ' selected' : ''; ?>><?php echo h($unit['nama']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
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
                            <th>No. Reg Bukti</th>
                            <th>ID Lowongan</th>
                            <th>Jabatan</th>
                            <th>Unit</th>
                            <th>Status Saat Ini</th>
                            <th>Tanggal Lapor</th>
                            <th>Tanggal Terisi</th>
                            <th>Simulasi Update</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($filteredRows)): ?>
                        <tr><td colspan="8" class="text-center text-muted">Tidak ada data.</td></tr>
                    <?php else: ?>
                        <?php foreach ($filteredRows as $row): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo h($row['no_reg_bukti']); ?></td>
                                <td><?php echo h($row['id_lowongan']); ?></td>
                                <td><?php echo h($row['jabatan']); ?></td>
                                <td><?php echo h($units[$row['unit_kode']]['nama'] ?? $row['unit_kode']); ?></td>
                                <td><span class="badge text-bg-<?php echo h(karirhub_proto_status_badge_class($row['status_keterisian'])); ?>"><?php echo h($row['status_keterisian']); ?></span></td>
                                <td><?php echo h($row['tanggal_lapor']); ?></td>
                                <td><?php echo h((string)($row['tanggal_terisi'] ?? '-')); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a class="btn btn-outline-secondary" href="?status=<?php echo h(urlencode($statusFilter)); ?>&unit=<?php echo h(urlencode($unitFilter)); ?>&simulate_no_reg=<?php echo h(urlencode($row['no_reg_bukti'])); ?>&simulate_status=Belum%20Terisi">Belum</a>
                                        <a class="btn btn-outline-info" href="?status=<?php echo h(urlencode($statusFilter)); ?>&unit=<?php echo h(urlencode($unitFilter)); ?>&simulate_no_reg=<?php echo h(urlencode($row['no_reg_bukti'])); ?>&simulate_status=Proses%20Seleksi">Seleksi</a>
                                        <a class="btn btn-outline-success" href="?status=<?php echo h(urlencode($statusFilter)); ?>&unit=<?php echo h(urlencode($unitFilter)); ?>&open_terisi_for=<?php echo h(urlencode($row['no_reg_bukti'])); ?>">Terisi</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </main>
    </div>
</div>
</div>

<div class="modal fade" id="bulkImportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Import Data Pegawai Ditempatkan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2">
                    Gunakan file dari tombol <strong>Download Template</strong> (data lowongan sudah otomatis terisi dari sistem). Saat import, pastikan kolom wajib terisi: NIK, Nama Lengkap, Pendidikan, Jenis Kelamin, Tempat Lahir, Tanggal Lahir, Alamat, Status Disabilitas (Iya/Tidak), TMT, Email, dan Nomor Hp.
                </div>
                <div class="mb-3">
                    <label class="form-label mb-1">Pilih file Excel (.xlsx)</label>
                    <input type="file" id="bulkImportFile" class="form-control form-control-sm" accept=".xlsx,.xls">
                </div>
                <div class="d-flex gap-2 mb-3">
                    <button type="button" class="btn btn-primary btn-sm" id="btnProcessBulkImport">
                        <i class="bi bi-upload me-1"></i>Proses Import
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnResetBulkImport">
                        Reset
                    </button>
                </div>
                <div id="bulkImportResult" class="small text-muted">Belum ada proses import.</div>
                <div class="table-responsive mt-2" id="bulkImportPreviewWrap" style="display:none;">
                    <table class="table table-sm table-bordered align-middle mb-0" id="bulkImportPreviewTable">
                        <thead class="table-light"></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($openTerisiRow !== null): ?>
<div class="modal fade show" id="terisiPegawaiModal" tabindex="-1" aria-modal="true" role="dialog" style="display:block; background: rgba(0,0,0,0.35);">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Lengkapi Data Pegawai yang ditempatkan</h5>
                <a href="?status=<?php echo h(urlencode($statusFilter)); ?>&unit=<?php echo h(urlencode($unitFilter)); ?>" class="btn-close"></a>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="small text-muted mb-2">
                        No. Reg Bukti: <strong><?php echo h($openTerisiRow['no_reg_bukti']); ?></strong> &middot;
                        Jabatan: <strong><?php echo h($openTerisiRow['jabatan']); ?></strong>
                    </div>
                    <input type="hidden" name="form_action" value="submit_terisi_data">
                    <input type="hidden" name="no_reg_bukti" value="<?php echo h($openTerisiRow['no_reg_bukti']); ?>">
                    <input type="hidden" name="status" value="<?php echo h($statusFilter); ?>">
                    <input type="hidden" name="unit" value="<?php echo h($unitFilter); ?>">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label mb-1">NIK</label>
                            <input type="text" name="nik" class="form-control form-control-sm" value="<?php echo h($pegawaiForm['nik']); ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label mb-1">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" class="form-control form-control-sm" value="<?php echo h($pegawaiForm['nama_lengkap']); ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label mb-1">Pendidikan</label>
                            <input type="text" name="pendidikan" class="form-control form-control-sm" value="<?php echo h($pegawaiForm['pendidikan']); ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label mb-1">Jenis Kelamin</label>
                            <select name="jenis_kelamin" class="form-select form-select-sm">
                                <option value="">Pilih</option>
                                <option value="Laki-laki"<?php echo $pegawaiForm['jenis_kelamin'] === 'Laki-laki' ? ' selected' : ''; ?>>Laki-laki</option>
                                <option value="Perempuan"<?php echo $pegawaiForm['jenis_kelamin'] === 'Perempuan' ? ' selected' : ''; ?>>Perempuan</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label mb-1">Tempat Lahir</label>
                            <input type="text" name="tempat_lahir" class="form-control form-control-sm" value="<?php echo h($pegawaiForm['tempat_lahir']); ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label mb-1">Tanggal Lahir</label>
                            <input type="date" name="tanggal_lahir" class="form-control form-control-sm" value="<?php echo h($pegawaiForm['tanggal_lahir']); ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label mb-1">Alamat</label>
                            <textarea name="alamat" class="form-control form-control-sm" rows="2"><?php echo h($pegawaiForm['alamat']); ?></textarea>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label mb-1">Status Disabilitas</label>
                            <select name="status_disabilitas" class="form-select form-select-sm">
                                <option value="">Pilih</option>
                                <option value="Iya"<?php echo $pegawaiForm['status_disabilitas'] === 'Iya' ? ' selected' : ''; ?>>Iya</option>
                                <option value="Tidak"<?php echo $pegawaiForm['status_disabilitas'] === 'Tidak' ? ' selected' : ''; ?>>Tidak</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label mb-1">TMT</label>
                            <input type="date" name="tmt" class="form-control form-control-sm" value="<?php echo h($pegawaiForm['tmt']); ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label mb-1">Email</label>
                            <input type="email" name="email" class="form-control form-control-sm" value="<?php echo h($pegawaiForm['email']); ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label mb-1">Nomor Hp</label>
                            <input type="text" name="nomor_hp" class="form-control form-control-sm" value="<?php echo h($pegawaiForm['nomor_hp']); ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="?status=<?php echo h(urlencode($statusFilter)); ?>&unit=<?php echo h(urlencode($unitFilter)); ?>" class="btn btn-outline-secondary btn-sm">Batal</a>
                    <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check2-circle me-1"></i>Simpan Data & Set Terisi</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<?php kh_proto_render_sidebar_script(); ?>
<script>
    (function () {
        const headers = [
            'No Reg Bukti',
            'ID Lowongan',
            'Jabatan',
            'Unit',
            'Status Saat Ini',
            'Tanggal Lapor',
            'Tanggal Terisi',
            'NIK',
            'Nama Lengkap',
            'Pendidikan',
            'Jenis Kelamin',
            'Tempat Lahir',
            'Tanggal Lahir',
            'Alamat',
            'Status Disabilitas',
            'TMT',
            'Email',
            'Nomor Hp',
        ];
        const templateRowsFromDb = <?php echo json_encode($templateRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        const btnDownload = document.getElementById('btnDownloadTemplate');
        const btnProcess = document.getElementById('btnProcessBulkImport');
        const btnReset = document.getElementById('btnResetBulkImport');
        const fileInput = document.getElementById('bulkImportFile');
        const resultEl = document.getElementById('bulkImportResult');
        const previewWrap = document.getElementById('bulkImportPreviewWrap');
        const previewTable = document.getElementById('bulkImportPreviewTable');

        if (btnDownload) {
            btnDownload.addEventListener('click', function () {
                const rows = templateRowsFromDb.map(function (row) {
                    return [
                        row.no_reg_bukti || '',
                        row.id_lowongan || '',
                        row.jabatan || '',
                        row.unit_nama || '',
                        row.status_saat_ini || '',
                        row.tanggal_lapor || '',
                        row.tanggal_terisi || '',
                        row.nik || '',
                        row.nama_lengkap || '',
                        row.pendidikan || '',
                        row.jenis_kelamin || '',
                        row.tempat_lahir || '',
                        row.tanggal_lahir || '',
                        row.alamat || '',
                        row.status_disabilitas || '',
                        row.tmt || '',
                        row.email || '',
                        row.nomor_hp || '',
                    ];
                });
                if (!rows.length) {
                    rows.push(['', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '']);
                }
                const ws = XLSX.utils.aoa_to_sheet([headers].concat(rows));
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Template Import');
                XLSX.writeFile(wb, 'template_bulk_import_pegawai_wllp.xlsx');
            });
        }

        function resetImportState() {
            if (fileInput) fileInput.value = '';
            if (resultEl) {
                resultEl.className = 'small text-muted';
                resultEl.textContent = 'Belum ada proses import.';
            }
            if (previewWrap) previewWrap.style.display = 'none';
            if (previewTable) {
                previewTable.querySelector('thead').innerHTML = '';
                previewTable.querySelector('tbody').innerHTML = '';
            }
        }

        if (btnReset) {
            btnReset.addEventListener('click', resetImportState);
        }

        function validateHeaders(actualHeaders) {
            if (actualHeaders.length < headers.length) return false;
            for (let i = 0; i < headers.length; i += 1) {
                if ((actualHeaders[i] || '').trim() !== headers[i]) return false;
            }
            return true;
        }

        if (btnProcess) {
            btnProcess.addEventListener('click', function () {
                if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                    resultEl.className = 'alert alert-warning py-2 mb-0';
                    resultEl.textContent = 'Silakan pilih file Excel terlebih dahulu.';
                    return;
                }

                const file = fileInput.files[0];
                const reader = new FileReader();
                reader.onload = function (evt) {
                    try {
                        const data = new Uint8Array(evt.target.result);
                        const workbook = XLSX.read(data, { type: 'array' });
                        const firstSheetName = workbook.SheetNames[0];
                        const sheet = workbook.Sheets[firstSheetName];
                        const rows = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });

                        if (!rows.length) {
                            resultEl.className = 'alert alert-danger py-2 mb-0';
                            resultEl.textContent = 'File kosong. Gunakan template yang sudah disediakan.';
                            return;
                        }

                        const headerRow = rows[0].map((cell) => String(cell).trim());
                        if (!validateHeaders(headerRow)) {
                            resultEl.className = 'alert alert-danger py-2 mb-0';
                            resultEl.textContent = 'Header tidak sesuai template. Silakan download ulang template.';
                            return;
                        }

                        const dataRows = rows.slice(1).filter((r) => r.some((cell) => String(cell).trim() !== ''));
                        let validCount = 0;
                        const errors = [];

                        dataRows.forEach((r, idx) => {
                            const rowNumber = idx + 2;
                            const map = {};
                            headers.forEach((h, i) => { map[h] = String(r[i] || '').trim(); });

                            const missing = headers.filter((h) => map[h] === '');
                            if (missing.length) {
                                errors.push('Baris ' + rowNumber + ': kolom kosong -> ' + missing.join(', '));
                                return;
                            }
                            if (!['Iya', 'Tidak'].includes(map['Status Disabilitas'])) {
                                errors.push('Baris ' + rowNumber + ': Status Disabilitas harus Iya/Tidak.');
                                return;
                            }
                            validCount += 1;
                        });

                        const previewRows = dataRows.slice(0, 5);
                        if (previewRows.length) {
                            previewWrap.style.display = '';
                            previewTable.querySelector('thead').innerHTML = '<tr>' + headers.map((h) => '<th>' + h + '</th>').join('') + '</tr>';
                            previewTable.querySelector('tbody').innerHTML = previewRows.map((r) => '<tr>' + headers.map((_, i) => '<td>' + String(r[i] || '') + '</td>').join('') + '</tr>').join('');
                        } else {
                            previewWrap.style.display = 'none';
                        }

                        if (errors.length) {
                            resultEl.className = 'alert alert-warning py-2 mb-0';
                            resultEl.innerHTML =
                                '<strong>Import selesai dengan catatan.</strong><br>' +
                                'Total baris: ' + dataRows.length + ', valid: ' + validCount + ', invalid: ' + errors.length +
                                '<br><small>' + errors.slice(0, 5).join('<br>') + (errors.length > 5 ? '<br>...dan lainnya.' : '') + '</small>';
                        } else {
                            resultEl.className = 'alert alert-success py-2 mb-0';
                            resultEl.textContent = 'Import berhasil. Total baris valid: ' + validCount + ' (simulasi prototype, belum disimpan permanen).';
                        }
                    } catch (err) {
                        resultEl.className = 'alert alert-danger py-2 mb-0';
                        resultEl.textContent = 'Gagal membaca file Excel: ' + (err && err.message ? err.message : String(err));
                    }
                };
                reader.readAsArrayBuffer(file);
            });
        }
    })();
</script>
</body>
</html>
