<?php
// =================================================================
// FILE: edit.php (VERSI PERBAIKAN FINAL - FIXED)
// =================================================================
include "../views/header.php";
include "init.php"; 
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Validasi dan ambil ID dari URL
$id = $_GET['id'] ?? null;
if (!$id || !is_numeric($id)) {
    header("Location: index.php?error=invalid_id");
    exit();
}
$id = intval($id);

// 2. Ambil data mitra yang akan diedit dari database
$stmt = $conn->prepare("SELECT * FROM tahapan_kerjasama WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    header("Location: index.php?error=not_found");
    exit();
}

$errors = [];
// 3. Proses form saat disubmit (method POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi input dasar
    if (empty(trim($_POST['nama_mitra']))) { $errors['nama_mitra'] = "Nama Mitra wajib diisi"; }
    if (empty(trim($_POST['jenis_mitra']))) { $errors['jenis_mitra'] = "Jenis Mitra wajib dipilih"; }

    // Proses upload file
    $uploadDir = $tahapanUploadDir; // from init.php
    if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }
    if (!is_writable($uploadDir)) { @chmod($uploadDir, 0777); }
    $filePaths = ['file1' => $data['file1'], 'file2' => $data['file2'], 'file3' => $data['file3']];
    
    foreach(['file1', 'file2', 'file3'] as $fileKey) {
        $customNameKey = 'file_name_' . substr($fileKey, -1);
        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
            
            // Hapus file lama jika ada yang baru diunggah
            if (!empty($data[$fileKey]) && file_exists($uploadDir . $data[$fileKey])) {
                @unlink($uploadDir . $data[$fileKey]);
            }

            $originalName = $_FILES[$fileKey]['name'];
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $customName = trim($_POST[$customNameKey] ?? '');
            
            $baseName = !empty($customName) ? $customName : basename($originalName, '.' . $extension);
            $newFileName = tahapanGenerateUniqueFileName($uploadDir, $baseName, $extension);

            if (is_uploaded_file($_FILES[$fileKey]['tmp_name']) && move_uploaded_file($_FILES[$fileKey]['tmp_name'], $uploadDir . $newFileName)) {
                $filePaths[$fileKey] = $newFileName;
            } else {
                $errors['file_upload'] = "Gagal mengunggah file: " . htmlspecialchars($originalName);
            }
        }
    }
    
    // Jika tidak ada error, lanjutkan update ke database
    if (count($errors) === 0) {
        $sql = "UPDATE tahapan_kerjasama SET 
                nama_mitra=?, jenis_mitra=?, sumber_usulan=?, tandai=?,
                status_kesepahaman=?, nomor_kesepahaman=?, tanggal_kesepahaman=?, ruanglingkup_kesepahaman=?, status_pelaksanaan_kesepahaman=?,
                rencana_pertemuan_kesepahaman=?, rencana_kolaborasi_kesepahaman=?, status_progres_kesepahaman=?, tindaklanjut_kesepahaman=?, keterangan_kesepahaman=?,
                status_pks=?, nomor_pks=?, tanggal_pks=?, ruanglingkup_pks=?, status_pelaksanaan_pks=?,
                rencana_pertemuan_pks=?, status_progres_pks=?, tindaklanjut_pks=?, keterangan_pks=?,
                file1=?, file2=?, file3=?
                WHERE id=?";

        $stmt_update = $conn->prepare($sql);
        $tandai = isset($_POST['tandai']) ? 1 : 0;
        
        $params = [
            $_POST['nama_mitra'] ?? null,
            $_POST['jenis_mitra'] ?? null,
            $_POST['sumber_usulan'] ?? null,
            $tandai,
            $_POST['status_kesepahaman'] ?? null,
            $_POST['nomor_kesepahaman'] ?? null,
            $_POST['tanggal_kesepahaman'] ?? null,
            $_POST['ruanglingkup_kesepahaman'] ?? null,
            $_POST['status_pelaksanaan_kesepahaman'] ?? null,
            $_POST['rencana_pertemuan_kesepahaman'] ?? null,
            $_POST['rencana_kolaborasi_kesepahaman'] ?? null,
            $_POST['status_progres_kesepahaman'] ?? null,
            $_POST['tindaklanjut_kesepahaman'] ?? null,
            $_POST['keterangan_kesepahaman'] ?? null,
            $_POST['status_pks'] ?? null,
            $_POST['nomor_pks'] ?? null,
            $_POST['tanggal_pks'] ?? null,
            $_POST['ruanglingkup_pks'] ?? null,
            $_POST['status_pelaksanaan_pks'] ?? null,
            $_POST['rencana_pertemuan_pks'] ?? null,
            $_POST['status_progres_pks'] ?? null,
            $_POST['tindaklanjut_pks'] ?? null,
            $_POST['keterangan_pks'] ?? null,
            $filePaths['file1'],
            $filePaths['file2'],
            $filePaths['file3'],
            $id
        ];
        
        foreach ($params as $key => &$value) {
            if ($value === '') {
                $value = null;
            }
        }
        
        // [PERBAIKAN] String tipe data yang benar: 27 parameter dengan tipe yang sesuai
        // s = string, i = integer, d = double/decimal
        $types = "sssissssssssssssssssssssssi";
        $stmt_update->bind_param($types, ...$params);

        if ($stmt_update->execute()) {
            header("Location: index.php?success=updated");
            exit();
        } else {
            $errors['db_error'] = "Gagal memperbarui data: " . htmlspecialchars($stmt_update->error);
        }
    }
}

// Digunakan untuk mengecek apakah form PKS harus ditampilkan atau disembunyikan
$pksDataExists = !empty($data['status_pks']) || !empty($data['nomor_pks']) || !empty($data['tanggal_pks']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Mitra - <?= htmlspecialchars($data['nama_mitra'] ?? '') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bs-blue-dark: #0a3d62; --bs-blue-light: #3c6382; --bs-orange: #f39c12; --bs-gray: #f5f7fa; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--bs-gray); }
        .form-card { background-color: white; border-radius: 1rem; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .form-card-header { background: linear-gradient(135deg, var(--bs-blue-dark) 0%, var(--bs-blue-light) 100%); color: white; }
        .form-section-title { font-weight: 600; color: var(--bs-blue-dark); border-bottom: 2px solid var(--bs-blue-light); }
        .file-upload-section .form-label { font-weight: 500; margin-bottom: 0.25rem; }
        .error-alert { background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
<?php include "../../navbar.php"; ?>
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-11">
                <div class="card form-card">
                    <div class="card-header form-card-header text-center p-4">
                        <h2 class="mb-1"><i class="bi bi-pencil-square"></i> Edit Mitra Kerjasama</h2>
                        <p class="mb-0">Perbarui informasi untuk: <strong><?= htmlspecialchars($data['nama_mitra'] ?? '') ?></strong></p>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <?php if (!empty($errors)): ?>
                            <div class="error-alert">
                                <strong>Terjadi kesalahan:</strong>
                                <ul class="mb-0 mt-2">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" enctype="multipart/form-data">
                            <h5 class="form-section-title pb-2 mb-4"><i class="bi bi-building"></i> Informasi Dasar Mitra</h5>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Nama Mitra <span class="text-danger">*</span></label>
                                    <input type="text" name="nama_mitra" class="form-control <?= isset($errors['nama_mitra']) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['nama_mitra'] ?? $data['nama_mitra'] ?? '') ?>" required>
                                    <?php if (isset($errors['nama_mitra'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($errors['nama_mitra']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Jenis Mitra <span class="text-danger">*</span></label>
                                    <select name="jenis_mitra" class="form-select <?= isset($errors['jenis_mitra']) ? 'is-invalid' : '' ?>" required>
                                        <option value="">-- Pilih Jenis --</option>
                                        <?php 
                                        $current_jenis = $_POST['jenis_mitra'] ?? $data['jenis_mitra'] ?? '';
                                        $jenis_options = [
                                            'Kementerian/Lembaga' => 'Kementerian/Lembaga',
                                            'Pemerintah Daerah' => 'Pemerintah Daerah',
                                            'Swasta/Perusahaan' => 'Swasta/Perusahaan',
                                            'Job Portal' => 'Job Portal',
                                            'Universitas' => 'Universitas',
                                            'Asosiasi/Komunitas' => 'Asosiasi/Komunitas'
                                        ];
                                        foreach ($jenis_options as $value => $label): ?>
                                            <option value="<?= htmlspecialchars($value) ?>" <?= $current_jenis == $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['jenis_mitra'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($errors['jenis_mitra']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Sumber Usulan</label>
                                    <input type="text" name="sumber_usulan" class="form-control" value="<?= htmlspecialchars($_POST['sumber_usulan'] ?? $data['sumber_usulan'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 d-flex align-items-end">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="tandai" value="1" id="tandaiCheck" <?= (($_POST['tandai'] ?? $data['tandai']) ?? 0) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="tandaiCheck">Tandai sebagai prioritas</label>
                                    </div>
                                </div>
                            </div>
                            
                            <h5 class="form-section-title mt-5 pb-2 mb-4"><i class="bi bi-handshake"></i> Tahap Kesepahaman (MoU)</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Status Kesepahaman</label>
                                        <select name="status_kesepahaman" class="form-select">
                                            <option value="">-- Pilih Status --</option>
                                            <?php 
                                            $current_status = $_POST['status_kesepahaman'] ?? $data['status_kesepahaman'] ?? '';
                                            $status_options = ['Signed', 'Not Available', 'Drafting/In Progress'];
                                            foreach ($status_options as $status): ?>
                                                <option value="<?= htmlspecialchars($status) ?>" <?= $current_status == $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Nomor</label>
                                        <input type="text" name="nomor_kesepahaman" class="form-control" value="<?= htmlspecialchars($_POST['nomor_kesepahaman'] ?? $data['nomor_kesepahaman'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Tanggal</label>
                                        <input type="date" name="tanggal_kesepahaman" class="form-control" value="<?= htmlspecialchars($_POST['tanggal_kesepahaman'] ?? $data['tanggal_kesepahaman'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Status Pelaksanaan</label>
                                        <select name="status_pelaksanaan_kesepahaman" class="form-select">
                                            <option value="">-- Pilih Status --</option>
                                            <?php 
                                            $current_pelaksanaan = $_POST['status_pelaksanaan_kesepahaman'] ?? $data['status_pelaksanaan_kesepahaman'] ?? '';
                                            $pelaksanaan_options = ['Implemented', 'In Progress', 'Not Yet'];
                                            foreach ($pelaksanaan_options as $pelaksanaan): ?>
                                                <option value="<?= htmlspecialchars($pelaksanaan) ?>" <?= $current_pelaksanaan == $pelaksanaan ? 'selected' : '' ?>><?= htmlspecialchars($pelaksanaan) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Rencana Pertemuan</label>
                                        <input type="date" name="rencana_pertemuan_kesepahaman" class="form-control" value="<?= htmlspecialchars($_POST['rencana_pertemuan_kesepahaman'] ?? $data['rencana_pertemuan_kesepahaman'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Ruang Lingkup</label>
                                        <textarea name="ruanglingkup_kesepahaman" class="form-control" rows="2"><?= htmlspecialchars($_POST['ruanglingkup_kesepahaman'] ?? $data['ruanglingkup_kesepahaman'] ?? '') ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Rencana Kolaborasi</label>
                                        <textarea name="rencana_kolaborasi_kesepahaman" class="form-control" rows="2"><?= htmlspecialchars($_POST['rencana_kolaborasi_kesepahaman'] ?? $data['rencana_kolaborasi_kesepahaman'] ?? '') ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Status/Progres</label>
                                        <textarea name="status_progres_kesepahaman" class="form-control" rows="2"><?= htmlspecialchars($_POST['status_progres_kesepahaman'] ?? $data['status_progres_kesepahaman'] ?? '') ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Tindak Lanjut</label>
                                        <textarea name="tindaklanjut_kesepahaman" class="form-control" rows="2"><?= htmlspecialchars($_POST['tindaklanjut_kesepahaman'] ?? $data['tindaklanjut_kesepahaman'] ?? '') ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Keterangan</label>
                                        <input type="text" name="keterangan_kesepahaman" class="form-control" value="<?= htmlspecialchars($_POST['keterangan_kesepahaman'] ?? $data['keterangan_kesepahaman'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4" id="lanjutPksButtonContainer" <?= $pksDataExists ? 'style="display: none;"' : '' ?>>
                                <button type="button" class="btn btn-warning" id="btnLanjutPks">
                                    <i class="bi bi-arrow-down-circle"></i> Lanjut isi PKS
                                </button>
                            </div>
                            
                            <div id="pksSection" <?= !$pksDataExists ? 'style="display: none;"' : '' ?>>
                                <h5 class="form-section-title mt-5 pb-2 mb-4"><i class="bi bi-file-earmark-text"></i> Tahap PKS</h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Status PKS</label>
                                            <select name="status_pks" class="form-select">
                                                <option value="">-- Pilih Status --</option>
                                                <?php 
                                                $current_status_pks = $_POST['status_pks'] ?? $data['status_pks'] ?? '';
                                                foreach ($status_options as $status): ?>
                                                    <option value="<?= htmlspecialchars($status) ?>" <?= $current_status_pks == $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Nomor</label>
                                            <input type="text" name="nomor_pks" class="form-control" value="<?= htmlspecialchars($_POST['nomor_pks'] ?? $data['nomor_pks'] ?? '') ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Tanggal</label>
                                            <input type="date" name="tanggal_pks" class="form-control" value="<?= htmlspecialchars($_POST['tanggal_pks'] ?? $data['tanggal_pks'] ?? '') ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Status Pelaksanaan PKS</label>
                                            <select name="status_pelaksanaan_pks" class="form-select">
                                                <option value="">-- Pilih Status --</option>
                                                <?php 
                                                $current_pelaksanaan_pks = $_POST['status_pelaksanaan_pks'] ?? $data['status_pelaksanaan_pks'] ?? '';
                                                foreach ($pelaksanaan_options as $pelaksanaan): ?>
                                                    <option value="<?= htmlspecialchars($pelaksanaan) ?>" <?= $current_pelaksanaan_pks == $pelaksanaan ? 'selected' : '' ?>><?= htmlspecialchars($pelaksanaan) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Rencana Pertemuan</label>
                                            <input type="date" name="rencana_pertemuan_pks" class="form-control" value="<?= htmlspecialchars($_POST['rencana_pertemuan_pks'] ?? $data['rencana_pertemuan_pks'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Ruang Lingkup</label>
                                            <textarea name="ruanglingkup_pks" class="form-control" rows="2"><?= htmlspecialchars($_POST['ruanglingkup_pks'] ?? $data['ruanglingkup_pks'] ?? '') ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Status/Progres</label>
                                            <textarea name="status_progres_pks" class="form-control" rows="2"><?= htmlspecialchars($_POST['status_progres_pks'] ?? $data['status_progres_pks'] ?? '') ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Tindak Lanjut</label>
                                            <textarea name="tindaklanjut_pks" class="form-control" rows="2"><?= htmlspecialchars($_POST['tindaklanjut_pks'] ?? $data['tindaklanjut_pks'] ?? '') ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Keterangan</label>
                                            <input type="text" name="keterangan_pks" class="form-control" value="<?= htmlspecialchars($_POST['keterangan_pks'] ?? $data['keterangan_pks'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <h5 class="form-section-title mt-5 pb-2 mb-4"><i class="bi bi-paperclip"></i> Dokumen Pendukung</h5>
                            <div class="row g-4 file-upload-section">
                                <?php
                                $fileLabels = ['File Kesepahaman (MoU)', 'File PKS', 'File Notulensi'];
                                for ($i = 1; $i <= 3; $i++):
                                    $fileKey = "file" . $i;
                                    $fileNameKey = "file_name_" . $i;
                                ?>
                                <div class="col-md-12">
                                    <label class="form-label"><?= htmlspecialchars($fileLabels[$i-1]) ?></label>
                                    <?php if (!empty($data[$fileKey])): ?>
                                        <div class="mb-2">
                                            <small>File saat ini: 
                                                <a href="uploads/<?= htmlspecialchars($data[$fileKey]) ?>" target="_blank" class="text-decoration-none">
                                                    <i class="bi bi-file-earmark-text"></i> <?= htmlspecialchars($data[$fileKey]) ?>
                                                </a>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    <div class="input-group">
                                        <input type="text" name="<?= htmlspecialchars($fileNameKey) ?>" class="form-control" placeholder="Nama kustom (jika ganti file)" value="<?= htmlspecialchars($_POST[$fileNameKey] ?? '') ?>">
                                        <input type="file" name="<?= htmlspecialchars($fileKey) ?>" class="form-control">
                                    </div>
                                    <small class="form-text text-muted">Isi nama kustom dan pilih file baru jika ingin mengganti.</small>
                                </div>
                                <?php endfor; ?>
                            </div>
                            
                            <hr class="my-5">
                            <div class="d-flex justify-content-end gap-2">
                                <a href="index.php" class="btn btn-secondary">Batal</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save-fill me-2"></i>Update Data
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btnLanjutPks = document.getElementById('btnLanjutPks');
            const pksSection = document.getElementById('pksSection');
            const lanjutPksButtonContainer = document.getElementById('lanjutPksButtonContainer');
            
            if (btnLanjutPks) {
                btnLanjutPks.addEventListener('click', function() {
                    lanjutPksButtonContainer.style.display = 'none';
                    pksSection.style.display = 'block';
                    pksSection.scrollIntoView({ behavior: 'smooth' });
                });
            }
        });
    </script>
</body>
</html>


