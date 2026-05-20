<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_data.php';

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

$form = [
    'unit_kode' => (string)($_POST['unit_kode'] ?? 'UNIT-001'),
    'jabatan' => trim((string)($_POST['jabatan'] ?? '')),
    'jumlah_kebutuhan' => trim((string)($_POST['jumlah_kebutuhan'] ?? '')),
    'jenis_kelamin' => trim((string)($_POST['jenis_kelamin'] ?? 'Semua')),
    'usia_min' => trim((string)($_POST['usia_min'] ?? '')),
    'usia_max' => trim((string)($_POST['usia_max'] ?? '')),
    'pendidikan_minimal' => trim((string)($_POST['pendidikan_minimal'] ?? '')),
    'keterampilan_utama' => trim((string)($_POST['keterampilan_utama'] ?? '')),
    'pengalaman_min_tahun' => trim((string)($_POST['pengalaman_min_tahun'] ?? '')),
    'rentang_gaji' => trim((string)($_POST['rentang_gaji'] ?? '')),
    'domisili_kerja' => trim((string)($_POST['domisili_kerja'] ?? '')),
    'mode_publikasi' => trim((string)($_POST['mode_publikasi'] ?? 'Publik')),
    'masa_berlaku_mulai' => trim((string)($_POST['masa_berlaku_mulai'] ?? date('Y-m-d'))),
    'masa_berlaku_sampai' => trim((string)($_POST['masa_berlaku_sampai'] ?? date('Y-m-d', strtotime('+30 days')))),
    'petugas_input' => trim((string)($_POST['petugas_input'] ?? '')),
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
        'keterampilan_utama' => 'Keterampilan Utama',
        'pengalaman_min_tahun' => 'Pengalaman Minimal (tahun)',
        'rentang_gaji' => 'Rentang Gaji',
        'domisili_kerja' => 'Domisili Kerja',
        'masa_berlaku_mulai' => 'Masa Berlaku Mulai',
        'masa_berlaku_sampai' => 'Masa Berlaku Sampai',
        'petugas_input' => 'Petugas Input',
    ];
    foreach ($requiredFields as $fieldKey => $label) {
        if ($form[$fieldKey] === '') {
            $errors[] = $label . ' wajib diisi.';
        }
    }
    if (!isset($units[$form['unit_kode']])) {
        $errors[] = 'Unit perusahaan tidak valid.';
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
        $generated = [
            'id_lowongan' => 'LK-SIM-' . strtoupper(substr(md5($form['jabatan'] . microtime(true)), 0, 6)),
            'no_reg_bukti' => 'WLLP-' . date('Ymd') . '-SIM-' . substr((string)time(), -4),
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
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-0">Pelaporan Lowongan</h3>
            <div class="text-muted small">Simulasi form WLLP lengkap (dummy data only)</div>
        </div>
        <a class="btn btn-outline-primary btn-sm" href="karirhub_employer_prototype_dashboard_wllp">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard WLLP
        </a>
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
                    <label class="form-label mb-1">Unit Perusahaan</label>
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
                    <label class="form-label mb-1">Keterampilan Utama</label>
                    <textarea name="keterampilan_utama" class="form-control form-control-sm" rows="2"><?php echo h($form['keterampilan_utama']); ?></textarea>
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label mb-1">Rentang Gaji</label>
                    <input type="text" name="rentang_gaji" class="form-control form-control-sm" value="<?php echo h($form['rentang_gaji']); ?>" placeholder="Rp5.000.000 - Rp7.000.000">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label mb-1">Domisili Kerja</label>
                    <input type="text" name="domisili_kerja" class="form-control form-control-sm" value="<?php echo h($form['domisili_kerja']); ?>">
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label mb-1">Mode Publikasi</label>
                    <select name="mode_publikasi" class="form-select form-select-sm">
                        <?php foreach (['Publik', 'Administratif'] as $mode): ?>
                            <option value="<?php echo h($mode); ?>"<?php echo $form['mode_publikasi'] === $mode ? ' selected' : ''; ?>><?php echo h($mode); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label mb-1">Masa Berlaku Mulai</label>
                    <input type="date" name="masa_berlaku_mulai" class="form-control form-control-sm" value="<?php echo h($form['masa_berlaku_mulai']); ?>">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label mb-1">Masa Berlaku Sampai</label>
                    <input type="date" name="masa_berlaku_sampai" class="form-control form-control-sm" value="<?php echo h($form['masa_berlaku_sampai']); ?>">
                </div>

                <div class="col-12 col-md-6">
                    <label class="form-label mb-1">Petugas Input</label>
                    <input type="email" name="petugas_input" class="form-control form-control-sm" value="<?php echo h($form['petugas_input']); ?>">
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
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
