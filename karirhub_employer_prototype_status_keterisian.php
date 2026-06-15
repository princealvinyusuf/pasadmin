<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_data.php';
require_once __DIR__ . '/karirhub_employer_prototype_storage.php';
require_once __DIR__ . '/karirhub_employer_prototype_ui.php';
require_once __DIR__ . '/db.php';

if (!kh_proto_can_access('karirhub_employer_prototype_status_keterisian_view')) {
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
kh_proto_ensure_multi_tables($conn);
kh_proto_seed_multi_from_dataset($conn, $dataset, $units);

$statusFilter = trim((string)($_REQUEST['status'] ?? 'all'));
$allowedStatus = ['all', 'belum terisi', 'proses seleksi', 'terisi'];
if (!in_array($statusFilter, $allowedStatus, true)) {
    $statusFilter = 'all';
}
$unitFilter = trim((string)($_REQUEST['unit'] ?? 'all'));
if ($unitFilter !== 'all' && !isset($units[$unitFilter])) {
    $unitFilter = 'all';
}

$simulatedNoReg = trim((string)($_GET['simulate_no_reg'] ?? ''));
$simulatedIdLowongan = trim((string)($_GET['simulate_id_lowongan'] ?? ''));
$simulatedStatus = trim((string)($_GET['simulate_status'] ?? ''));
$successMessage = null;
if ($simulatedNoReg !== '' && $simulatedIdLowongan !== '' && in_array($simulatedStatus, ['Belum Terisi', 'Proses Seleksi', 'Terisi', 'Belum Update'], true)) {
    $successMessage = 'Simulasi update status untuk ' . $simulatedNoReg . ' / ' . $simulatedIdLowongan . ' -> ' . $simulatedStatus . ' berhasil (dummy, tidak disimpan permanen).';
}

$rows = [];
$resRows = $conn->query("
    SELECT
        d.no_reg_bukti,
        d.id_lowongan,
        d.jabatan,
        d.jumlah_kebutuhan,
        d.unit_kode,
        d.unit_nama,
        COALESCE(s.status_saat_ini, 'Belum Terisi') AS status_keterisian,
        COALESCE(CAST(s.tanggal_lapor AS CHAR), CAST(d.masa_berlaku_mulai AS CHAR), '') AS tanggal_lapor,
        COALESCE(CAST(s.tanggal_terisi AS CHAR), '') AS tanggal_terisi,
        h.periode_tipe,
        CAST(h.periode_mulai AS CHAR) AS periode_mulai,
        CAST(h.periode_selesai AS CHAR) AS periode_selesai,
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
        COALESCE(p.nomor_hp, '') AS nomor_hp,
        COALESCE(pc.jumlah_penempatan, 0) AS jumlah_penempatan
    FROM karirhub_proto_wllp_pelaporan d
    LEFT JOIN karirhub_proto_wllp_status s
        ON s.no_reg_bukti = d.no_reg_bukti AND s.id_lowongan = d.id_lowongan
    LEFT JOIN (
        SELECT p1.*
        FROM karirhub_proto_wllp_penempatan p1
        INNER JOIN (
            SELECT no_reg_bukti, id_lowongan, MIN(urutan_penempatan) AS urutan_penempatan
            FROM karirhub_proto_wllp_penempatan
            GROUP BY no_reg_bukti, id_lowongan
        ) pmin
            ON pmin.no_reg_bukti = p1.no_reg_bukti
            AND pmin.id_lowongan = p1.id_lowongan
            AND pmin.urutan_penempatan = p1.urutan_penempatan
    ) p
        ON p.no_reg_bukti = d.no_reg_bukti AND p.id_lowongan = d.id_lowongan
    LEFT JOIN (
        SELECT no_reg_bukti, id_lowongan, COUNT(*) AS jumlah_penempatan
        FROM karirhub_proto_wllp_penempatan
        GROUP BY no_reg_bukti, id_lowongan
    ) pc
        ON pc.no_reg_bukti = d.no_reg_bukti AND pc.id_lowongan = d.id_lowongan
    LEFT JOIN karirhub_proto_wllp_laporan h
        ON h.no_reg_bukti = d.no_reg_bukti
    ORDER BY d.created_at DESC, d.no_reg_bukti DESC, d.id_lowongan DESC
");
if ($resRows) {
    while ($row = $resRows->fetch_assoc()) {
        $row['status_saat_ini'] = (string)$row['status_keterisian'];
        $rows[] = $row;
    }
}

$rowMap = [];
foreach ($rows as $row) {
    $key = (string)$row['no_reg_bukti'] . '||' . (string)$row['id_lowongan'];
    $rowMap[$key] = $row;
}

$requiredPegawaiFields = [
    'nik' => 'NIK',
    'nama_lengkap' => 'Nama Lengkap',
    'pendidikan' => 'Pendidikan',
    'alamat' => 'Alamat',
    'status_disabilitas' => 'Status Disabilitas',
    'tmt' => 'Tanggal Mulai Kerja',
    'email' => 'Email',
    'nomor_hp' => 'Nomor Hp',
];
$pegawaiDefaultRow = [
    'nik' => '',
    'nama_lengkap' => '',
    'pendidikan' => '',
    'alamat' => '',
    'status_disabilitas' => '',
    'tmt' => '',
    'email' => '',
    'nomor_hp' => '',
];
$pegawaiFormRows = [];
$pegawaiErrors = [];
$openTerisiNoReg = trim((string)($_GET['open_terisi_for'] ?? ''));
$openTerisiIdLowongan = trim((string)($_GET['open_terisi_id'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['form_action'] ?? '') === 'submit_terisi_data') {
    $openTerisiNoReg = trim((string)($_POST['no_reg_bukti'] ?? ''));
    $openTerisiIdLowongan = trim((string)($_POST['id_lowongan'] ?? ''));
    $openKey = $openTerisiNoReg . '||' . $openTerisiIdLowongan;

    $targetPegawaiCount = max(1, (int)($_POST['jumlah_kebutuhan_target'] ?? 1));
    foreach ($requiredPegawaiFields as $field => $_label) {
        $rawValues = $_POST[$field] ?? [];
        if (!is_array($rawValues)) {
            $rawValues = [$rawValues];
        }
        for ($idx = 0; $idx < $targetPegawaiCount; $idx++) {
            if (!isset($pegawaiFormRows[$idx])) {
                $pegawaiFormRows[$idx] = $pegawaiDefaultRow;
            }
            $pegawaiFormRows[$idx][$field] = trim((string)($rawValues[$idx] ?? ''));
        }
    }

    if ($openTerisiNoReg === '' || $openTerisiIdLowongan === '' || !isset($rowMap[$openKey])) {
        $pegawaiErrors[] = 'Data lowongan untuk status Terisi tidak ditemukan.';
    } else {
        $jumlahKebutuhan = max(1, (int)($rowMap[$openKey]['jumlah_kebutuhan'] ?? 1));
        if ($targetPegawaiCount !== $jumlahKebutuhan) {
            $targetPegawaiCount = $jumlahKebutuhan;
            for ($idx = 0; $idx < $targetPegawaiCount; $idx++) {
                if (!isset($pegawaiFormRows[$idx])) {
                    $pegawaiFormRows[$idx] = $pegawaiDefaultRow;
                }
            }
        }
    }

    $validPegawaiRows = [];
    $requiredFieldCount = count($requiredPegawaiFields);
    for ($idx = 0; $idx < $targetPegawaiCount; $idx++) {
        $rowLabel = 'Pegawai ke-' . ($idx + 1);
        $filledFieldCount = 0;
        foreach ($requiredPegawaiFields as $field => $_label) {
            if (($pegawaiFormRows[$idx][$field] ?? '') !== '') {
                $filledFieldCount++;
            }
        }
        if ($filledFieldCount === 0) {
            continue;
        }
        if ($filledFieldCount < $requiredFieldCount) {
            foreach ($requiredPegawaiFields as $field => $label) {
                if (($pegawaiFormRows[$idx][$field] ?? '') === '') {
                    $pegawaiErrors[] = $rowLabel . ': ' . $label . ' wajib diisi.';
                }
            }
            continue;
        }
        $statusDisabilitas = $pegawaiFormRows[$idx]['status_disabilitas'] ?? '';
        if ($statusDisabilitas !== '' && !in_array($statusDisabilitas, ['Iya', 'Tidak'], true)) {
            $pegawaiErrors[] = $rowLabel . ': Status Disabilitas hanya boleh Iya atau Tidak.';
            continue;
        }
        $validPegawaiRows[] = $pegawaiFormRows[$idx];
    }
    if (empty($validPegawaiRows)) {
        $pegawaiErrors[] = 'Isi minimal 1 Data Pegawai secara lengkap untuk melanjutkan.';
    }

    if (empty($pegawaiErrors)) {
        try {
            $conn->begin_transaction();

            $stmtDeletePegawai = $conn->prepare("DELETE FROM karirhub_proto_wllp_penempatan WHERE no_reg_bukti = ? AND id_lowongan = ?");
            $stmtDeletePegawai->bind_param('ss', $openTerisiNoReg, $openTerisiIdLowongan);
            $stmtDeletePegawai->execute();
            $stmtDeletePegawai->close();

            $stmtSavePegawai = $conn->prepare("
                INSERT INTO karirhub_proto_wllp_penempatan
                    (no_reg_bukti, id_lowongan, urutan_penempatan, nik, nama_lengkap, pendidikan, jenis_kelamin, tempat_lahir, tanggal_lahir, alamat, status_disabilitas, tmt, email, nomor_hp)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($validPegawaiRows as $index => $pegawaiForm) {
                $urutanPenempatan = $index + 1;
                $jenisKelamin = '-';
                $tempatLahir = '-';
                $tanggalLahir = '1970-01-01';
                $stmtSavePegawai->bind_param(
                    'ssisssssssssss',
                    $openTerisiNoReg,
                    $openTerisiIdLowongan,
                    $urutanPenempatan,
                    $pegawaiForm['nik'],
                    $pegawaiForm['nama_lengkap'],
                    $pegawaiForm['pendidikan'],
                    $jenisKelamin,
                    $tempatLahir,
                    $tanggalLahir,
                    $pegawaiForm['alamat'],
                    $pegawaiForm['status_disabilitas'],
                    $pegawaiForm['tmt'],
                    $pegawaiForm['email'],
                    $pegawaiForm['nomor_hp']
                );
                $stmtSavePegawai->execute();
            }
            $stmtSavePegawai->close();

            $statusTerisi = 'Terisi';
            $tanggalTerisiNow = $validPegawaiRows[0]['tmt'] !== '' ? $validPegawaiRows[0]['tmt'] : date('Y-m-d');
            $stmtUpdateStatus = $conn->prepare("UPDATE karirhub_proto_wllp_status SET status_saat_ini = ?, tanggal_terisi = ? WHERE no_reg_bukti = ? AND id_lowongan = ?");
            $stmtUpdateStatus->bind_param('ssss', $statusTerisi, $tanggalTerisiNow, $openTerisiNoReg, $openTerisiIdLowongan);
            $stmtUpdateStatus->execute();
            $stmtUpdateStatus->close();

            $conn->commit();

            $successMessage = 'Simulasi update status untuk ' . $openTerisiNoReg . ' / ' . $openTerisiIdLowongan . ' -> Terisi berhasil. '
                . count($validPegawaiRows) . ' data pegawai telah dilengkapi.';
            $openTerisiNoReg = '';
            $openTerisiIdLowongan = '';
            $pegawaiFormRows = [];
        } catch (Throwable $e) {
            $conn->rollback();
            $pegawaiErrors[] = 'Gagal menyimpan data pegawai. Silakan coba lagi.';
        }
    }
}

$openKey = $openTerisiNoReg . '||' . $openTerisiIdLowongan;
$openTerisiRow = ($openTerisiNoReg !== '' && $openTerisiIdLowongan !== '' && isset($rowMap[$openKey])) ? $rowMap[$openKey] : null;
if ($openTerisiRow !== null) {
    $openTerisiJumlahKebutuhan = max(1, (int)($openTerisiRow['jumlah_kebutuhan'] ?? 1));
    if (empty($pegawaiFormRows)) {
        $resPegawai = $conn->prepare("
            SELECT nik, nama_lengkap, pendidikan, alamat, status_disabilitas, CAST(tmt AS CHAR) AS tmt, email, nomor_hp
            FROM karirhub_proto_wllp_penempatan
            WHERE no_reg_bukti = ? AND id_lowongan = ?
            ORDER BY urutan_penempatan ASC
        ");
        $resPegawai->bind_param('ss', $openTerisiNoReg, $openTerisiIdLowongan);
        $resPegawai->execute();
        $resPegawaiResult = $resPegawai->get_result();
        while ($resPegawaiResult && ($pegawai = $resPegawaiResult->fetch_assoc())) {
            $pegawaiFormRows[] = [
                'nik' => (string)($pegawai['nik'] ?? ''),
                'nama_lengkap' => (string)($pegawai['nama_lengkap'] ?? ''),
                'pendidikan' => (string)($pegawai['pendidikan'] ?? ''),
                'alamat' => (string)($pegawai['alamat'] ?? ''),
                'status_disabilitas' => (string)($pegawai['status_disabilitas'] ?? ''),
                'tmt' => (string)($pegawai['tmt'] ?? ''),
                'email' => (string)($pegawai['email'] ?? ''),
                'nomor_hp' => (string)($pegawai['nomor_hp'] ?? ''),
            ];
        }
        $resPegawai->close();
    }
    for ($idx = count($pegawaiFormRows); $idx < $openTerisiJumlahKebutuhan; $idx++) {
        $pegawaiFormRows[] = $pegawaiDefaultRow;
    }
    if (count($pegawaiFormRows) > $openTerisiJumlahKebutuhan) {
        $pegawaiFormRows = array_slice($pegawaiFormRows, 0, $openTerisiJumlahKebutuhan);
    }
}
$detailNoReg = trim((string)($_GET['detail_no_reg'] ?? ''));
$detailIdLowongan = trim((string)($_GET['detail_id_lowongan'] ?? ''));
$detailKey = $detailNoReg . '||' . $detailIdLowongan;
$detailRow = ($detailNoReg !== '' && $detailIdLowongan !== '' && isset($rowMap[$detailKey])) ? $rowMap[$detailKey] : null;
$detailLowonganInfo = null;
$detailPegawaiRows = [];
if ($detailRow !== null) {
    $stmtDetailLowongan = $conn->prepare("
        SELECT
            no_reg_bukti,
            id_lowongan,
            jabatan,
            unit_nama,
            jumlah_kebutuhan,
            jenis_kelamin,
            usia_min,
            usia_max,
            pendidikan_minimal,
            pengalaman_min_tahun,
            rentang_gaji,
            keterampilan_utama,
            CAST(masa_berlaku_mulai AS CHAR) AS masa_berlaku_mulai,
            CAST(masa_berlaku_sampai AS CHAR) AS masa_berlaku_sampai,
            status_verifikasi
        FROM karirhub_proto_wllp_pelaporan
        WHERE no_reg_bukti = ? AND id_lowongan = ?
        LIMIT 1
    ");
    $stmtDetailLowongan->bind_param('ss', $detailNoReg, $detailIdLowongan);
    $stmtDetailLowongan->execute();
    $detailLowonganResult = $stmtDetailLowongan->get_result();
    if ($detailLowonganResult) {
        $detailLowonganInfo = $detailLowonganResult->fetch_assoc() ?: null;
    }
    $stmtDetailLowongan->close();

    $stmtDetailPegawai = $conn->prepare("
        SELECT
            urutan_penempatan,
            nik,
            nama_lengkap,
            pendidikan,
            alamat,
            status_disabilitas,
            CAST(tmt AS CHAR) AS tmt,
            email,
            nomor_hp
        FROM karirhub_proto_wllp_penempatan
        WHERE no_reg_bukti = ? AND id_lowongan = ?
        ORDER BY urutan_penempatan ASC
    ");
    $stmtDetailPegawai->bind_param('ss', $detailNoReg, $detailIdLowongan);
    $stmtDetailPegawai->execute();
    $detailPegawaiResult = $stmtDetailPegawai->get_result();
    while ($detailPegawaiResult && ($detailPegawai = $detailPegawaiResult->fetch_assoc())) {
        $detailPegawaiRows[] = $detailPegawai;
    }
    $stmtDetailPegawai->close();
}
$templateRows = $rows;

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
                            <th>Periode</th>
                            <th>Jabatan</th>
                            <th>Unit</th>
                            <th>Jumlah Kebutuhan</th>
                            <th>Jumlah Penempatan</th>
                            <th>Status Saat Ini</th>
                            <th>Tanggal Lapor</th>
                            <th>Tanggal Terisi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($filteredRows)): ?>
                        <tr><td colspan="11" class="text-center text-muted">Tidak ada data.</td></tr>
                    <?php else: ?>
                        <?php foreach ($filteredRows as $row): ?>
                            <?php
                                $kebutuhan = (int)($row['jumlah_kebutuhan'] ?? 0);
                                $penempatan = (int)($row['jumlah_penempatan'] ?? 0);
                                $progressClass = 'secondary';
                                if ($kebutuhan > 0 && $penempatan >= $kebutuhan) {
                                    $progressClass = 'success';
                                } elseif ($penempatan > 0) {
                                    $progressClass = 'warning';
                                }
                            ?>
                            <tr>
                                <td class="fw-semibold">
                                    <div><?php echo h($row['no_reg_bukti']); ?></div>
                                    <a class="btn btn-outline-primary btn-sm mt-1" href="?status=<?php echo h(urlencode($statusFilter)); ?>&unit=<?php echo h(urlencode($unitFilter)); ?>&detail_no_reg=<?php echo h(urlencode($row['no_reg_bukti'])); ?>&detail_id_lowongan=<?php echo h(urlencode($row['id_lowongan'])); ?>">Lihat Detail</a>
                                </td>
                                <td><?php echo h($row['id_lowongan']); ?></td>
                                <td class="small"><?php echo h(strtoupper((string)$row['periode_tipe']) . ' (' . (string)$row['periode_mulai'] . ' s.d. ' . (string)$row['periode_selesai'] . ')'); ?></td>
                                <td><?php echo h($row['jabatan']); ?></td>
                                <td><?php echo h($units[$row['unit_kode']]['nama'] ?? $row['unit_kode']); ?></td>
                                <td><?php echo h((string)($row['jumlah_kebutuhan'] ?? 0)); ?></td>
                                <td><span class="badge text-bg-<?php echo h($progressClass); ?>"><?php echo h((string)$penempatan . ' / ' . (string)$kebutuhan); ?></span></td>
                                <td><span class="badge text-bg-<?php echo h(karirhub_proto_status_badge_class($row['status_keterisian'])); ?>"><?php echo h($row['status_keterisian']); ?></span></td>
                                <td><?php echo h($row['tanggal_lapor']); ?></td>
                                <td><?php echo h((string)($row['tanggal_terisi'] ?? '-')); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm flex-wrap" role="group">
                                        <a class="btn btn-outline-secondary" href="?status=<?php echo h(urlencode($statusFilter)); ?>&unit=<?php echo h(urlencode($unitFilter)); ?>&simulate_no_reg=<?php echo h(urlencode($row['no_reg_bukti'])); ?>&simulate_id_lowongan=<?php echo h(urlencode($row['id_lowongan'])); ?>&simulate_status=Belum%20Terisi">Belum</a>
                                        <a class="btn btn-outline-info" href="?status=<?php echo h(urlencode($statusFilter)); ?>&unit=<?php echo h(urlencode($unitFilter)); ?>&simulate_no_reg=<?php echo h(urlencode($row['no_reg_bukti'])); ?>&simulate_id_lowongan=<?php echo h(urlencode($row['id_lowongan'])); ?>&simulate_status=Proses%20Seleksi">Seleksi</a>
                                        <a class="btn btn-outline-success" href="?status=<?php echo h(urlencode($statusFilter)); ?>&unit=<?php echo h(urlencode($unitFilter)); ?>&open_terisi_for=<?php echo h(urlencode($row['no_reg_bukti'])); ?>&open_terisi_id=<?php echo h(urlencode($row['id_lowongan'])); ?>">Terisi</a>
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
                    Gunakan file dari tombol <strong>Download Template</strong> (data lowongan sudah otomatis terisi dari sistem). Saat import, pastikan kolom wajib terisi: NIK, Nama Lengkap, Pendidikan, Alamat, Status Disabilitas (Iya/Tidak), TMT, Email, dan Nomor Hp.
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

<?php if ($detailRow !== null): ?>
<div class="modal fade show" id="detailWllpModal" tabindex="-1" aria-modal="true" role="dialog" style="display:block; background: rgba(0,0,0,0.35); overflow-y:auto; -webkit-overflow-scrolling:touch;">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-height: calc(100vh - 2rem);">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail WLLP</h5>
                <a href="?status=<?php echo h(urlencode($statusFilter)); ?>&unit=<?php echo h(urlencode($unitFilter)); ?>" class="btn-close"></a>
            </div>
            <div class="modal-body" style="max-height: calc(100vh - 220px); overflow-y: auto; -webkit-overflow-scrolling: touch;">
                <div class="alert alert-primary py-2 mb-3">
                    <div class="fw-semibold mb-1">Panduan Singkat Bulk Import</div>
                    <ul class="mb-2 ps-3 small">
                        <li>Langkah 1: Klik <strong>Download Template</strong> untuk mengambil format terbaru.</li>
                        <li>Langkah 2: Isi data pegawai sesuai kolom wajib, lalu simpan file Excel.</li>
                        <li>Langkah 3: Klik <strong>Bulk Import</strong> untuk unggah file dan cek hasil validasi.</li>
                        <li>Catatan: Gunakan file template resmi agar header sesuai.</li>
                    </ul>
                    <div class="d-flex flex-wrap justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-success btn-sm" id="btnDownloadTemplate">
                            <i class="bi bi-download me-1"></i>Download Template
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#bulkImportModal">
                            <i class="bi bi-file-earmark-arrow-up me-1"></i>Bulk Import
                        </button>
                    </div>
                </div>
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-light fw-semibold">Informasi Lowongan Pekerjaan</div>
                    <div class="card-body">
                        <?php if ($detailLowonganInfo === null): ?>
                            <div class="text-muted small">Detail lowongan tidak ditemukan.</div>
                        <?php else: ?>
                            <div class="row g-3 small">
                                <div class="col-12 col-md-4"><span class="text-muted">No. Reg Bukti</span><div class="fw-semibold"><?php echo h((string)$detailLowonganInfo['no_reg_bukti']); ?></div></div>
                                <div class="col-12 col-md-4"><span class="text-muted">ID Lowongan</span><div class="fw-semibold"><?php echo h((string)$detailLowonganInfo['id_lowongan']); ?></div></div>
                                <div class="col-12 col-md-4"><span class="text-muted">Unit</span><div class="fw-semibold"><?php echo h((string)$detailLowonganInfo['unit_nama']); ?></div></div>
                                <div class="col-12 col-md-6"><span class="text-muted">Jabatan</span><div class="fw-semibold"><?php echo h((string)$detailLowonganInfo['jabatan']); ?></div></div>
                                <div class="col-12 col-md-3"><span class="text-muted">Jumlah Kebutuhan</span><div class="fw-semibold"><?php echo h((string)$detailLowonganInfo['jumlah_kebutuhan']); ?></div></div>
                                <div class="col-12 col-md-3"><span class="text-muted">Status Verifikasi</span><div class="fw-semibold"><?php echo h((string)$detailLowonganInfo['status_verifikasi']); ?></div></div>
                                <div class="col-12 col-md-4"><span class="text-muted">Jenis Kelamin</span><div class="fw-semibold"><?php echo h((string)$detailLowonganInfo['jenis_kelamin']); ?></div></div>
                                <div class="col-12 col-md-4"><span class="text-muted">Usia</span><div class="fw-semibold"><?php echo h((string)$detailLowonganInfo['usia_min'] . ' - ' . (string)$detailLowonganInfo['usia_max'] . ' tahun'); ?></div></div>
                                <div class="col-12 col-md-4"><span class="text-muted">Pendidikan Minimal</span><div class="fw-semibold"><?php echo h((string)$detailLowonganInfo['pendidikan_minimal']); ?></div></div>
                                <div class="col-12 col-md-4"><span class="text-muted">Pengalaman Minimal</span><div class="fw-semibold"><?php echo h((string)$detailLowonganInfo['pengalaman_min_tahun']); ?> tahun</div></div>
                                <div class="col-12 col-md-4"><span class="text-muted">Rentang Gaji</span><div class="fw-semibold"><?php echo h((string)$detailLowonganInfo['rentang_gaji']); ?></div></div>
                                <div class="col-12 col-md-4"><span class="text-muted">Masa Berlaku</span><div class="fw-semibold"><?php echo h((string)$detailLowonganInfo['masa_berlaku_mulai'] . ' s.d. ' . (string)$detailLowonganInfo['masa_berlaku_sampai']); ?></div></div>
                                <div class="col-12"><span class="text-muted">Keterampilan Utama</span><div class="fw-semibold"><?php echo h((string)$detailLowonganInfo['keterampilan_utama']); ?></div></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light fw-semibold">Data Pegawai yang Ditempatkan</div>
                    <div class="card-body">
                        <?php if (empty($detailPegawaiRows)): ?>
                            <div class="text-muted small">Belum ada data pegawai yang ditempatkan.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>NIK</th>
                                            <th>Nama Lengkap</th>
                                            <th>Pendidikan</th>
                                            <th>Tanggal Mulai Kerja</th>
                                            <th>Status Disabilitas</th>
                                            <th>Email</th>
                                            <th>Nomor Hp</th>
                                            <th>Alamat</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($detailPegawaiRows as $pegawaiDetail): ?>
                                            <tr>
                                                <td><?php echo h((string)$pegawaiDetail['urutan_penempatan']); ?></td>
                                                <td><?php echo h((string)$pegawaiDetail['nik']); ?></td>
                                                <td><?php echo h((string)$pegawaiDetail['nama_lengkap']); ?></td>
                                                <td><?php echo h((string)$pegawaiDetail['pendidikan']); ?></td>
                                                <td><?php echo h((string)$pegawaiDetail['tmt']); ?></td>
                                                <td><?php echo h((string)$pegawaiDetail['status_disabilitas']); ?></td>
                                                <td><?php echo h((string)$pegawaiDetail['email']); ?></td>
                                                <td><?php echo h((string)$pegawaiDetail['nomor_hp']); ?></td>
                                                <td><?php echo h((string)$pegawaiDetail['alamat']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="?status=<?php echo h(urlencode($statusFilter)); ?>&unit=<?php echo h(urlencode($unitFilter)); ?>" class="btn btn-outline-secondary btn-sm">Tutup</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($openTerisiRow !== null): ?>
<div class="modal fade show" id="terisiPegawaiModal" tabindex="-1" aria-modal="true" role="dialog" style="display:block; background: rgba(0,0,0,0.35); overflow-y:auto; -webkit-overflow-scrolling:touch;">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" style="max-height: calc(100vh - 2rem);">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Lengkapi Data Pegawai yang ditempatkan</h5>
                <a href="?status=<?php echo h(urlencode($statusFilter)); ?>&unit=<?php echo h(urlencode($unitFilter)); ?>" class="btn-close"></a>
            </div>
            <form method="POST">
                <div class="modal-body" style="max-height: calc(100vh - 220px); overflow-y: auto; -webkit-overflow-scrolling: touch;">
                    <div class="small text-muted mb-2">
                        No. Reg Bukti: <strong><?php echo h($openTerisiRow['no_reg_bukti']); ?></strong> &middot;
                        ID Lowongan: <strong><?php echo h($openTerisiRow['id_lowongan']); ?></strong> &middot;
                        Jabatan: <strong><?php echo h($openTerisiRow['jabatan']); ?></strong> &middot;
                        Jumlah Kebutuhan: <strong><?php echo h((string)$openTerisiJumlahKebutuhan); ?></strong>
                    </div>
                    <input type="hidden" name="form_action" value="submit_terisi_data">
                    <input type="hidden" name="no_reg_bukti" value="<?php echo h($openTerisiRow['no_reg_bukti']); ?>">
                    <input type="hidden" name="id_lowongan" value="<?php echo h($openTerisiRow['id_lowongan']); ?>">
                    <input type="hidden" name="jumlah_kebutuhan_target" value="<?php echo h((string)$openTerisiJumlahKebutuhan); ?>">
                    <input type="hidden" name="status" value="<?php echo h($statusFilter); ?>">
                    <input type="hidden" name="unit" value="<?php echo h($unitFilter); ?>">
                    <?php foreach ($pegawaiFormRows as $pegawaiIndex => $pegawaiForm): ?>
                    <div class="border rounded p-3 mb-3">
                        <div class="fw-semibold small mb-2">Data Pegawai <?php echo h((string)($pegawaiIndex + 1)); ?> dari <?php echo h((string)$openTerisiJumlahKebutuhan); ?></div>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label mb-1">NIK</label>
                                <input type="text" name="nik[]" class="form-control form-control-sm" value="<?php echo h($pegawaiForm['nik']); ?>">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label mb-1">Nama Lengkap</label>
                                <input type="text" name="nama_lengkap[]" class="form-control form-control-sm" value="<?php echo h($pegawaiForm['nama_lengkap']); ?>">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label mb-1">Pendidikan</label>
                                <input type="text" name="pendidikan[]" class="form-control form-control-sm" value="<?php echo h($pegawaiForm['pendidikan']); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label mb-1">Alamat</label>
                                <textarea name="alamat[]" class="form-control form-control-sm" rows="2"><?php echo h($pegawaiForm['alamat']); ?></textarea>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label mb-1">Status Disabilitas</label>
                                <select name="status_disabilitas[]" class="form-select form-select-sm">
                                    <option value="">Pilih</option>
                                    <option value="Iya"<?php echo $pegawaiForm['status_disabilitas'] === 'Iya' ? ' selected' : ''; ?>>Iya</option>
                                    <option value="Tidak"<?php echo $pegawaiForm['status_disabilitas'] === 'Tidak' ? ' selected' : ''; ?>>Tidak</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label mb-1">Tanggal Mulai Kerja</label>
                                <input type="date" name="tmt[]" class="form-control form-control-sm" value="<?php echo h($pegawaiForm['tmt']); ?>">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label mb-1">Email</label>
                                <input type="email" name="email[]" class="form-control form-control-sm" value="<?php echo h($pegawaiForm['email']); ?>">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label mb-1">Nomor Hp</label>
                                <input type="text" name="nomor_hp[]" class="form-control form-control-sm" value="<?php echo h($pegawaiForm['nomor_hp']); ?>">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
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
                        row.alamat || '',
                        row.status_disabilitas || '',
                        row.tmt || '',
                        row.email || '',
                        row.nomor_hp || '',
                    ];
                });
                if (!rows.length) {
                    rows.push(['', '', '', '', '', '', '', '', '', '', '', '', '', '', '']);
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
