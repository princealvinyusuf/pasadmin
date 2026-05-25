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

function kh_proto_generate_no_reg_bukti(mysqli $conn): string
{
    $prefix = 'WLLP-57' . date('ym') . '-';
    $regex = '^' . preg_quote($prefix, '/') . '[0-9]{8}$';
    $stmt = $conn->prepare("
        SELECT COALESCE(MAX(CAST(RIGHT(no_reg_bukti, 8) AS UNSIGNED)), 0) AS max_seq
        FROM karirhub_proto_wllp_pelaporan
        WHERE no_reg_bukti LIKE CONCAT(?, '%')
          AND no_reg_bukti REGEXP ?
    ");
    $stmt->bind_param('ss', $prefix, $regex);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    $nextSeq = ((int)($row['max_seq'] ?? 0)) + 1;
    return $prefix . str_pad((string)$nextSeq, 8, '0', STR_PAD_LEFT);
}

$dataset = karirhub_proto_dataset();
$units = $dataset['units'];

$conn->query("CREATE TABLE IF NOT EXISTS karirhub_proto_wllp_pelaporan (
    no_reg_bukti VARCHAR(60) PRIMARY KEY,
    id_lowongan VARCHAR(30) NOT NULL,
    unit_kode VARCHAR(40) NOT NULL,
    unit_nama VARCHAR(255) NOT NULL,
    jabatan VARCHAR(200) NOT NULL,
    jumlah_kebutuhan INT NOT NULL,
    jenis_kelamin VARCHAR(30) NOT NULL,
    usia_min INT NOT NULL,
    usia_max INT NOT NULL,
    pendidikan_minimal VARCHAR(120) NOT NULL,
    deskripsi_pekerjaan TEXT NOT NULL,
    keterampilan_utama TEXT NOT NULL,
    pengalaman_min_tahun INT NOT NULL,
    rentang_gaji VARCHAR(120) NOT NULL,
    kode_kbji VARCHAR(50) NOT NULL,
    provinsi VARCHAR(120) NOT NULL,
    kota VARCHAR(120) NOT NULL,
    kecamatan VARCHAR(120) NOT NULL,
    kelurahan VARCHAR(120) NOT NULL,
    bidang_pekerjaan VARCHAR(180) NOT NULL,
    industri_sektor VARCHAR(180) NOT NULL,
    status_pernikahan VARCHAR(40) NOT NULL,
    masa_berlaku_mulai DATE NOT NULL,
    masa_berlaku_sampai DATE NOT NULL,
    alamat_url_postingan_loker VARCHAR(500) NOT NULL,
    catatan TEXT DEFAULT NULL,
    status_verifikasi VARCHAR(60) NOT NULL DEFAULT 'Terverifikasi',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("ALTER TABLE karirhub_proto_wllp_pelaporan ADD COLUMN IF NOT EXISTS kode_kbji VARCHAR(50) NOT NULL DEFAULT '' AFTER rentang_gaji");
$conn->query("ALTER TABLE karirhub_proto_wllp_pelaporan ADD COLUMN IF NOT EXISTS provinsi VARCHAR(120) NOT NULL DEFAULT '' AFTER kode_kbji");
$conn->query("ALTER TABLE karirhub_proto_wllp_pelaporan ADD COLUMN IF NOT EXISTS kota VARCHAR(120) NOT NULL DEFAULT '' AFTER provinsi");
$conn->query("ALTER TABLE karirhub_proto_wllp_pelaporan ADD COLUMN IF NOT EXISTS kecamatan VARCHAR(120) NOT NULL DEFAULT '' AFTER kota");
$conn->query("ALTER TABLE karirhub_proto_wllp_pelaporan ADD COLUMN IF NOT EXISTS kelurahan VARCHAR(120) NOT NULL DEFAULT '' AFTER kecamatan");
$conn->query("ALTER TABLE karirhub_proto_wllp_pelaporan ADD COLUMN IF NOT EXISTS bidang_pekerjaan VARCHAR(180) NOT NULL DEFAULT '' AFTER kelurahan");
$conn->query("ALTER TABLE karirhub_proto_wllp_pelaporan ADD COLUMN IF NOT EXISTS industri_sektor VARCHAR(180) NOT NULL DEFAULT '' AFTER bidang_pekerjaan");
$conn->query("ALTER TABLE karirhub_proto_wllp_pelaporan ADD COLUMN IF NOT EXISTS status_pernikahan VARCHAR(40) NOT NULL DEFAULT '' AFTER industri_sektor");

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

$form = [
    'unit_kode' => (string)($_POST['unit_kode'] ?? 'UNIT-001'),
    'jabatan' => trim((string)($_POST['jabatan'] ?? '')),
    'jumlah_kebutuhan' => trim((string)($_POST['jumlah_kebutuhan'] ?? '')),
    'jenis_kelamin' => trim((string)($_POST['jenis_kelamin'] ?? 'Semua')),
    'usia_min' => trim((string)($_POST['usia_min'] ?? '')),
    'usia_max' => trim((string)($_POST['usia_max'] ?? '')),
    'pendidikan_minimal' => trim((string)($_POST['pendidikan_minimal'] ?? '')),
    'deskripsi_pekerjaan' => trim((string)($_POST['deskripsi_pekerjaan'] ?? '')),
    'keterampilan_utama' => trim((string)($_POST['keterampilan_utama'] ?? '')),
    'pengalaman_min_tahun' => trim((string)($_POST['pengalaman_min_tahun'] ?? '')),
    'rentang_gaji' => trim((string)($_POST['rentang_gaji'] ?? '')),
    'kode_kbji' => trim((string)($_POST['kode_kbji'] ?? '')),
    'provinsi' => trim((string)($_POST['provinsi'] ?? '')),
    'kota' => trim((string)($_POST['kota'] ?? '')),
    'kecamatan' => trim((string)($_POST['kecamatan'] ?? '')),
    'kelurahan' => trim((string)($_POST['kelurahan'] ?? '')),
    'bidang_pekerjaan' => trim((string)($_POST['bidang_pekerjaan'] ?? '')),
    'industri_sektor' => trim((string)($_POST['industri_sektor'] ?? '')),
    'status_pernikahan' => trim((string)($_POST['status_pernikahan'] ?? '')),
    'tipe_kerja' => trim((string)($_POST['tipe_kerja'] ?? '')),
    'masa_berlaku_mulai' => trim((string)($_POST['masa_berlaku_mulai'] ?? date('Y-m-d'))),
    'masa_berlaku_sampai' => trim((string)($_POST['masa_berlaku_sampai'] ?? date('Y-m-d', strtotime('+30 days')))),
    'alamat_url_postingan_loker' => trim((string)($_POST['alamat_url_postingan_loker'] ?? '')),
    'catatan' => trim((string)($_POST['catatan'] ?? '')),
];

$errors = [];
$generated = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requiredFields = [
        'jabatan' => 'Jabatan',
        'jumlah_kebutuhan' => 'Jumlah Kebutuhan',
        'usia_min' => 'Usia Minimal',
        'usia_max' => 'Usia Maksimal',
        'pendidikan_minimal' => 'Pendidikan Minimal',
        'deskripsi_pekerjaan' => 'Deskripsi Pekerjaan',
        'keterampilan_utama' => 'Keterampilan Utama',
        'pengalaman_min_tahun' => 'Pengalaman Minimal (tahun)',
        'rentang_gaji' => 'Rentang Gaji',
        'kode_kbji' => 'Kode KBJI',
        'provinsi' => 'Provinsi',
        'kota' => 'Kota',
        'kecamatan' => 'Kecamatan',
        'kelurahan' => 'Kelurahan',
        'bidang_pekerjaan' => 'Bidang Pekerjaan',
        'industri_sektor' => 'Industri / Sektor',
        'status_pernikahan' => 'Status Pernikahan',
        'tipe_kerja' => 'Tipe Kerja',
        'masa_berlaku_mulai' => 'Masa Berlaku Mulai',
        'masa_berlaku_sampai' => 'Masa Berlaku Sampai',
        'alamat_url_postingan_loker' => 'Alamat URL Postingan Loker',
    ];
    foreach ($requiredFields as $fieldKey => $label) {
        if ($form[$fieldKey] === '') {
            $errors[] = $label . ' wajib diisi.';
        }
    }
    if (!isset($units[$form['unit_kode']])) {
        $errors[] = 'Unit perusahaan/usaha tidak valid.';
    }
    if ($form['jumlah_kebutuhan'] !== '' && (!ctype_digit($form['jumlah_kebutuhan']) || (int)$form['jumlah_kebutuhan'] <= 0)) {
        $errors[] = 'Jumlah kebutuhan harus angka lebih dari 0.';
    }
    if ($form['usia_min'] !== '' && $form['usia_max'] !== '' && (int)$form['usia_min'] > (int)$form['usia_max']) {
        $errors[] = 'Usia minimal tidak boleh lebih besar dari usia maksimal.';
    }
    if ($form['masa_berlaku_mulai'] !== '' && $form['masa_berlaku_sampai'] !== '' && $form['masa_berlaku_mulai'] > $form['masa_berlaku_sampai']) {
        $errors[] = 'Masa berlaku mulai tidak boleh lebih akhir dari masa berlaku sampai.';
    }

    if (empty($errors)) {
        $generatedIdLowongan = 'LK-SIM-' . strtoupper(substr(md5($form['jabatan'] . microtime(true)), 0, 6));
        $generatedNoReg = kh_proto_generate_no_reg_bukti($conn);
        $unitNama = (string)($units[$form['unit_kode']]['nama'] ?? $form['unit_kode']);

        $stmtSavePelaporan = $conn->prepare("
            INSERT INTO karirhub_proto_wllp_pelaporan (
                no_reg_bukti, id_lowongan, unit_kode, unit_nama, jabatan, jumlah_kebutuhan, jenis_kelamin, usia_min, usia_max,
                pendidikan_minimal, deskripsi_pekerjaan, keterampilan_utama, pengalaman_min_tahun, rentang_gaji, kode_kbji, provinsi, kota, kecamatan, kelurahan,
                bidang_pekerjaan, industri_sektor, status_pernikahan, masa_berlaku_mulai, masa_berlaku_sampai, alamat_url_postingan_loker, catatan, status_verifikasi
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Terverifikasi')
        ");
        $jumlahKebutuhanInt = (int)$form['jumlah_kebutuhan'];
        $usiaMinInt = (int)$form['usia_min'];
        $usiaMaxInt = (int)$form['usia_max'];
        $pengalamanMinInt = (int)$form['pengalaman_min_tahun'];
        $stmtSavePelaporan->bind_param(
            str_repeat('s', 26),
            $generatedNoReg,
            $generatedIdLowongan,
            $form['unit_kode'],
            $unitNama,
            $form['jabatan'],
            $jumlahKebutuhanInt,
            $form['jenis_kelamin'],
            $usiaMinInt,
            $usiaMaxInt,
            $form['pendidikan_minimal'],
            $form['deskripsi_pekerjaan'],
            $form['keterampilan_utama'],
            $pengalamanMinInt,
            $form['rentang_gaji'],
            $form['kode_kbji'],
            $form['provinsi'],
            $form['kota'],
            $form['kecamatan'],
            $form['kelurahan'],
            $form['bidang_pekerjaan'],
            $form['industri_sektor'],
            $form['status_pernikahan'],
            $form['masa_berlaku_mulai'],
            $form['masa_berlaku_sampai'],
            $form['alamat_url_postingan_loker'],
            $form['catatan']
        );
        $stmtSavePelaporan->execute();
        $stmtSavePelaporan->close();

        $statusBelumTerisi = 'Belum Terisi';
        $stmtSaveStatus = $conn->prepare("
            INSERT INTO karirhub_proto_wllp_status (no_reg_bukti, id_lowongan, jabatan, unit_nama, status_saat_ini, tanggal_lapor, tanggal_terisi)
            VALUES (?, ?, ?, ?, ?, ?, NULL)
            ON DUPLICATE KEY UPDATE
                id_lowongan = VALUES(id_lowongan),
                jabatan = VALUES(jabatan),
                unit_nama = VALUES(unit_nama),
                tanggal_lapor = VALUES(tanggal_lapor)
        ");
        $stmtSaveStatus->bind_param(
            'ssssss',
            $generatedNoReg,
            $generatedIdLowongan,
            $form['jabatan'],
            $unitNama,
            $statusBelumTerisi,
            $form['masa_berlaku_mulai']
        );
        $stmtSaveStatus->execute();
        $stmtSaveStatus->close();

        $generated = [
            'id_lowongan' => $generatedIdLowongan,
            'no_reg_bukti' => $generatedNoReg,
            'status_verifikasi' => 'Terverifikasi (Dummy)',
            'status_keterisian' => 'Belum Terisi',
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karirhub Employer Prototype - Pelaporan Lowongan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php kh_proto_render_styles(); ?>
</head>
<body class="kh-proto-page">
<?php include 'navbar.php'; ?>
<?php kh_proto_render_hero('Daftar Lowongan Kerja', 'Buat lowongan kerja melalui alur pelaporan WLLP prototipe.', 'Lowongan Kerja', 'karirhub_employer_prototype_pelaporan_lowongan', 'Proyek', 'karirhub_employer_prototype_dashboard_wllp'); ?>

<div class="kh-content-wrap">
<div class="container py-4">
    <div class="kh-proto-shell">
    <?php kh_proto_render_sidebar('wllp_pelaporan'); ?>
    <main class="kh-proto-main">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-0">Pelaporan Lowongan</h3>
            <div class="text-muted small">Simulasi form WLLP lengkap (dummy data only)</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-success btn-sm" id="btnDownloadPelaporanTemplate">
                <i class="bi bi-download me-1"></i>Download Template
            </button>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#bulkImportPelaporanModal">
                <i class="bi bi-file-earmark-arrow-up me-1"></i>Bulk Import
            </button>
            <a class="btn btn-outline-primary btn-sm" href="karirhub_employer_prototype_dashboard_wllp">
                <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard WLLP
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Validasi gagal:</div>
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo h($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($generated !== null): ?>
        <div class="alert alert-success">
            <div class="fw-semibold mb-1">Pelaporan dummy berhasil dibuat</div>
            <div><strong>No. Reg Bukti:</strong> <?php echo h($generated['no_reg_bukti']); ?></div>
            <div><strong>ID Lowongan:</strong> <?php echo h($generated['id_lowongan']); ?></div>
            <div><strong>Status Verifikasi:</strong> <?php echo h($generated['status_verifikasi']); ?></div>
            <div><strong>Waktu Simulasi:</strong> <?php echo h($generated['created_at']); ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label mb-1">Unit Perusahaan/ Usaha</label>
                    <select name="unit_kode" class="form-select form-select-sm">
                        <?php foreach ($units as $unitCode => $unit): ?>
                            <option value="<?php echo h($unitCode); ?>"<?php echo $form['unit_kode'] === $unitCode ? ' selected' : ''; ?>><?php echo h($unit['nama']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label mb-1">Jabatan</label>
                    <input type="text" name="jabatan" class="form-control form-control-sm" value="<?php echo h($form['jabatan']); ?>">
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label mb-1">Jumlah Kebutuhan</label>
                    <input type="number" min="1" name="jumlah_kebutuhan" class="form-control form-control-sm" value="<?php echo h($form['jumlah_kebutuhan']); ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label mb-1">Jenis Kelamin</label>
                    <select name="jenis_kelamin" class="form-select form-select-sm">
                        <?php foreach (['Semua', 'Laki-laki', 'Perempuan'] as $jk): ?>
                            <option value="<?php echo h($jk); ?>"<?php echo $form['jenis_kelamin'] === $jk ? ' selected' : ''; ?>><?php echo h($jk); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label mb-1">Usia Min</label>
                    <input type="number" min="18" name="usia_min" class="form-control form-control-sm" value="<?php echo h($form['usia_min']); ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label mb-1">Usia Max</label>
                    <input type="number" min="18" name="usia_max" class="form-control form-control-sm" value="<?php echo h($form['usia_max']); ?>">
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label mb-1">Pendidikan Minimal</label>
                    <input type="text" name="pendidikan_minimal" class="form-control form-control-sm" value="<?php echo h($form['pendidikan_minimal']); ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label mb-1">Pengalaman Minimal (tahun)</label>
                    <input type="number" min="0" name="pengalaman_min_tahun" class="form-control form-control-sm" value="<?php echo h($form['pengalaman_min_tahun']); ?>">
                </div>

                <div class="col-12">
                    <label class="form-label mb-1">Deskripsi Pekerjaan</label>
                    <textarea name="deskripsi_pekerjaan" class="form-control form-control-sm" rows="3"><?php echo h($form['deskripsi_pekerjaan']); ?></textarea>
                </div>

                <div class="col-12">
                    <label class="form-label mb-1">Keterampilan Utama</label>
                    <textarea name="keterampilan_utama" class="form-control form-control-sm" rows="2"><?php echo h($form['keterampilan_utama']); ?></textarea>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label mb-1">Rentang Gaji</label>
                    <input type="text" name="rentang_gaji" class="form-control form-control-sm" value="<?php echo h($form['rentang_gaji']); ?>" placeholder="Rp5.000.000 - Rp7.000.000">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label mb-1">Kode KBJI</label>
                    <input type="text" name="kode_kbji" class="form-control form-control-sm" value="<?php echo h($form['kode_kbji']); ?>" placeholder="Contoh: 24231">
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label mb-1">Provinsi</label>
                    <input type="text" name="provinsi" class="form-control form-control-sm" value="<?php echo h($form['provinsi']); ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label mb-1">Kota</label>
                    <input type="text" name="kota" class="form-control form-control-sm" value="<?php echo h($form['kota']); ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label mb-1">Kecamatan</label>
                    <input type="text" name="kecamatan" class="form-control form-control-sm" value="<?php echo h($form['kecamatan']); ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label mb-1">Kelurahan</label>
                    <input type="text" name="kelurahan" class="form-control form-control-sm" value="<?php echo h($form['kelurahan']); ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label mb-1">Bidang Pekerjaan</label>
                    <input type="text" name="bidang_pekerjaan" class="form-control form-control-sm" value="<?php echo h($form['bidang_pekerjaan']); ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label mb-1">Industri / Sektor</label>
                    <input type="text" name="industri_sektor" class="form-control form-control-sm" value="<?php echo h($form['industri_sektor']); ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label mb-1">Status Pernikahan</label>
                    <select name="status_pernikahan" class="form-select form-select-sm">
                        <option value="">Pilih</option>
                        <?php foreach (['Belum Menikah', 'Menikah', 'Cerai Hidup', 'Cerai Mati'] as $statusNikah): ?>
                            <option value="<?php echo h($statusNikah); ?>"<?php echo $form['status_pernikahan'] === $statusNikah ? ' selected' : ''; ?>><?php echo h($statusNikah); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label mb-1">Tipe Kerja</label>
                    <select name="tipe_kerja" class="form-select form-select-sm">
                        <option value="">Pilih</option>
                        <?php foreach (['Full Time', 'Part Time', 'Contract', 'Internship'] as $tipe): ?>
                            <option value="<?php echo h($tipe); ?>"<?php echo $form['tipe_kerja'] === $tipe ? ' selected' : ''; ?>><?php echo h($tipe); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label mb-1">Masa Berlaku Mulai</label>
                    <input type="date" name="masa_berlaku_mulai" class="form-control form-control-sm" value="<?php echo h($form['masa_berlaku_mulai']); ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label mb-1">Masa Berlaku Sampai</label>
                    <input type="date" name="masa_berlaku_sampai" class="form-control form-control-sm" value="<?php echo h($form['masa_berlaku_sampai']); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label mb-1">Alamat URL Postingan Loker</label>
                    <input type="url" name="alamat_url_postingan_loker" class="form-control form-control-sm" placeholder="https://karirhub.kemnaker.go.id/..." value="<?php echo h($form['alamat_url_postingan_loker']); ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label mb-1">Catatan</label>
                    <input type="text" name="catatan" class="form-control form-control-sm" value="<?php echo h($form['catatan']); ?>">
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-send-check me-1"></i>Simulasikan Buat Laporan
                </button>
                <a class="btn btn-outline-secondary btn-sm" href="karirhub_employer_prototype_pelaporan_lowongan">
                    Reset Form
                </a>
            </div>
        </div>
    </form>
    </main>
    </div>
</div>
</div>

<div class="modal fade" id="bulkImportPelaporanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Import Pelaporan Lowongan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2">
                    Gunakan file dari tombol <strong>Download Template</strong>. Isi data tiap lowongan sesuai header template, lalu upload untuk validasi cepat.
                </div>
                <div class="mb-3">
                    <label class="form-label mb-1">Pilih file Excel (.xlsx)</label>
                    <input type="file" id="pelaporanImportFile" class="form-control form-control-sm" accept=".xlsx,.xls">
                </div>
                <div class="d-flex gap-2 mb-3">
                    <button type="button" class="btn btn-primary btn-sm" id="btnProcessPelaporanImport">
                        <i class="bi bi-upload me-1"></i>Proses Import
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnResetPelaporanImport">Reset</button>
                </div>
                <div id="pelaporanImportResult" class="small text-muted">Belum ada proses import.</div>
                <div class="table-responsive mt-2" id="pelaporanImportPreviewWrap" style="display:none;">
                    <table class="table table-sm table-bordered align-middle mb-0" id="pelaporanImportPreviewTable">
                        <thead class="table-light"></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<?php kh_proto_render_sidebar_script(); ?>
<script>
    (function () {
        const headers = [
            'Unit Kode',
            'Jabatan',
            'Jumlah Kebutuhan',
            'Jenis Kelamin',
            'Usia Min',
            'Usia Max',
            'Pendidikan Minimal',
            'Deskripsi Pekerjaan',
            'Keterampilan Utama',
            'Pengalaman Min (Tahun)',
            'Rentang Gaji',
            'Kode KBJI',
            'Provinsi',
            'Kota',
            'Kecamatan',
            'Kelurahan',
            'Bidang Pekerjaan',
            'Industri / Sektor',
            'Status Pernikahan',
            'Tipe Kerja',
            'Masa Berlaku Mulai',
            'Masa Berlaku Sampai',
            'Alamat URL Postingan Loker',
            'Catatan',
        ];

        const btnDownload = document.getElementById('btnDownloadPelaporanTemplate');
        const btnProcess = document.getElementById('btnProcessPelaporanImport');
        const btnReset = document.getElementById('btnResetPelaporanImport');
        const fileInput = document.getElementById('pelaporanImportFile');
        const resultEl = document.getElementById('pelaporanImportResult');
        const previewWrap = document.getElementById('pelaporanImportPreviewWrap');
        const previewTable = document.getElementById('pelaporanImportPreviewTable');

        function setResult(cls, html) {
            if (!resultEl) return;
            resultEl.className = cls;
            resultEl.innerHTML = html;
        }

        function resetImport() {
            if (fileInput) fileInput.value = '';
            setResult('small text-muted', 'Belum ada proses import.');
            if (previewWrap) previewWrap.style.display = 'none';
            if (previewTable) {
                previewTable.querySelector('thead').innerHTML = '';
                previewTable.querySelector('tbody').innerHTML = '';
            }
        }

        if (btnDownload) {
            btnDownload.addEventListener('click', function () {
                const sample = [
                    'UNIT-001',
                    'Staff Operasional',
                    '3',
                    'Semua',
                    '20',
                    '35',
                    'D3',
                    'Menjalankan operasional harian sesuai SOP.',
                    'Administrasi, komunikasi, Microsoft Office',
                    '1',
                    'Rp4.500.000 - Rp6.000.000',
                    '24231',
                    'DKI Jakarta',
                    'Jakarta Selatan',
                    'Pasar Minggu',
                    'Pejaten Timur',
                    'Operasional',
                    'Logistik',
                    'Belum Menikah',
                    'Full Time',
                    '2026-05-21',
                    '2026-06-21',
                    'https://karirhub.kemnaker.go.id/lowongan/contoh',
                    'Prioritas domisili Jabodetabek',
                ];
                const ws = XLSX.utils.aoa_to_sheet([headers, sample]);
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Template Pelaporan');
                XLSX.writeFile(wb, 'template_bulk_import_pelaporan_wllp.xlsx');
            });
        }

        if (btnReset) btnReset.addEventListener('click', resetImport);

        if (btnProcess) {
            btnProcess.addEventListener('click', function () {
                if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                    setResult('alert alert-warning py-2 mb-0', 'Silakan pilih file Excel terlebih dahulu.');
                    return;
                }
                const reader = new FileReader();
                reader.onload = function (evt) {
                    try {
                        const data = new Uint8Array(evt.target.result);
                        const wb = XLSX.read(data, { type: 'array' });
                        const ws = wb.Sheets[wb.SheetNames[0]];
                        const rows = XLSX.utils.sheet_to_json(ws, { header: 1, defval: '' });
                        if (!rows.length) {
                            setResult('alert alert-danger py-2 mb-0', 'File kosong.');
                            return;
                        }
                        const actualHeader = rows[0].map((x) => String(x).trim());
                        const headerOk = headers.every((h, idx) => (actualHeader[idx] || '') === h);
                        if (!headerOk) {
                            setResult('alert alert-danger py-2 mb-0', 'Header file tidak sesuai template. Silakan download template terbaru.');
                            return;
                        }

                        const dataRows = rows.slice(1).filter((r) => r.some((c) => String(c).trim() !== ''));
                        const allowedTipe = ['Full Time', 'Part Time', 'Contract', 'Internship'];
                        let valid = 0;
                        const issues = [];

                        dataRows.forEach((r, index) => {
                            const line = index + 2;
                            const map = {};
                            headers.forEach((h, i) => { map[h] = String(r[i] || '').trim(); });
                            const missing = headers.filter((h) => map[h] === '');
                            if (missing.length) {
                                issues.push('Baris ' + line + ': kolom kosong -> ' + missing.join(', '));
                                return;
                            }
                            if (!allowedTipe.includes(map['Tipe Kerja'])) {
                                issues.push('Baris ' + line + ': Tipe Kerja harus Full Time / Part Time / Contract / Internship.');
                                return;
                            }
                            valid += 1;
                        });

                        if (dataRows.length) {
                            previewWrap.style.display = '';
                            previewTable.querySelector('thead').innerHTML = '<tr>' + headers.map((h) => '<th>' + h + '</th>').join('') + '</tr>';
                            previewTable.querySelector('tbody').innerHTML = dataRows.slice(0, 5).map((r) =>
                                '<tr>' + headers.map((_, i) => '<td>' + String(r[i] || '') + '</td>').join('') + '</tr>'
                            ).join('');
                        } else {
                            previewWrap.style.display = 'none';
                        }

                        if (issues.length) {
                            setResult(
                                'alert alert-warning py-2 mb-0',
                                '<strong>Import selesai dengan catatan.</strong><br>Total baris: ' + dataRows.length +
                                ', valid: ' + valid + ', invalid: ' + issues.length +
                                '<br><small>' + issues.slice(0, 5).join('<br>') + (issues.length > 5 ? '<br>...dan lainnya.' : '') + '</small>'
                            );
                        } else {
                            setResult('alert alert-success py-2 mb-0', 'Import valid untuk ' + valid + ' baris (simulasi prototype, belum disimpan permanen).');
                        }
                    } catch (e) {
                        setResult('alert alert-danger py-2 mb-0', 'Gagal membaca file: ' + (e && e.message ? e.message : String(e)));
                    }
                };
                reader.readAsArrayBuffer(fileInput.files[0]);
            });
        }
    })();
</script>
</body>
</html>
