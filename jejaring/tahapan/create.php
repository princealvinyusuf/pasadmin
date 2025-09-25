<?php
include "init.php"; 
error_reporting(E_ALL);
ini_set('display_errors', 1);
$errors = [];

function handleUpload($fileKey, $customName, $uploadDir) {
    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] == UPLOAD_ERR_OK) {
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $originalFileName = $_FILES[$fileKey]['name'];
        $fileExtension = pathinfo($originalFileName, PATHINFO_EXTENSION);
        
        $baseName = basename($originalFileName, "." . $fileExtension);

        $finalName = !empty(trim($customName)) ? trim($customName) : $baseName;

        $safeFileName = preg_replace('/[^a-zA-Z0-9-_\.]/', '', str_replace(' ', '_', $finalName));
        
        $newFileName = time() . '_' . $safeFileName . '.' . $fileExtension;
        $targetPath = $uploadDir . $newFileName;

        if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetPath)) {
            return $newFileName;
        }
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty(trim($_POST['nama_mitra']))) { $errors['nama_mitra'] = "Nama Mitra wajib diisi"; }
    if (empty(trim($_POST['jenis_mitra']))) { $errors['jenis_mitra'] = "Jenis Mitra wajib dipilih"; }
    
    if (count($errors) === 0) {
        $uploadDir = __DIR__ . '/uploads/';
        $file1Name = handleUpload('file1', $_POST['file1_nama'], $uploadDir);
        $file2Name = handleUpload('file2', $_POST['file2_nama'], $uploadDir);
        $file3Name = handleUpload('file3', $_POST['file3_nama'], $uploadDir);

        $sql = "INSERT INTO tahapan_kerjasama (
                    nama_mitra, jenis_mitra, sumber_usulan, tandai, 
                    status_kesepahaman, nomor_kesepahaman, tanggal_kesepahaman, ruanglingkup_kesepahaman, status_pelaksanaan_kesepahaman, 
                    rencana_pertemuan_kesepahaman, rencana_kolaborasi_kesepahaman, status_progres_kesepahaman, tindaklanjut_kesepahaman, keterangan_kesepahaman, 
                    status_pks, nomor_pks, tanggal_pks, ruanglingkup_pks, status_pelaksanaan_pks, 
                    rencana_pertemuan_pks, status_progres_pks, tindaklanjut_pks, keterangan_pks,
                    file1, file2, file3
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        
        $stmt = $conn->prepare($sql);
        
        $tandai = isset($_POST['tandai']) ? 1 : 0;
        foreach ($_POST as $key => $value) { if (empty($value)) { $_POST[$key] = null; } }
        
        $stmt->bind_param("sssissssssssssssssssssssss", 
            $_POST['nama_mitra'], $_POST['jenis_mitra'], $_POST['sumber_usulan'], $tandai, 
            $_POST['status_kesepahaman'], $_POST['nomor_kesepahaman'], $_POST['tanggal_kesepahaman'], $_POST['ruanglingkup_kesepahaman'], $_POST['status_pelaksanaan_kesepahaman'], 
            $_POST['rencana_pertemuan_kesepahaman'], $_POST['rencana_kolaborasi_kesepahaman'], $_POST['status_progres_kesepahaman'], $_POST['tindaklanjut_kesepahaman'], $_POST['keterangan_kesepahaman'], 
            $_POST['status_pks'], $_POST['nomor_pks'], $_POST['tanggal_pks'], $_POST['ruanglingkup_pks'], $_POST['status_pelaksanaan_pks'], 
            $_POST['rencana_pertemuan_pks'], $_POST['status_progres_pks'], $_POST['tindaklanjut_pks'], $_POST['keterangan_pks'],
            $file1Name, $file2Name, $file3Name
        );
        
        if ($stmt->execute()) { 
            header("Location: index.php?success=created"); 
            exit(); 
        } else { 
            $errors['db_error'] = "Gagal menyimpan: " . htmlspecialchars($stmt->error); 
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Mitra Baru</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --bs-blue-dark: #0a3d62; --bs-blue-light: #3c6382; --bs-orange: #f39c12; --bs-gray: #f5f7fa; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--bs-gray); }
        .form-card { background-color: white; border-radius: 1rem; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .form-card-header { background: linear-gradient(135deg, var(--bs-blue-dark) 0%, var(--bs-blue-light) 100%); color: white; }
        .form-section-title { font-weight: 600; color: var(--bs-blue-dark); border-bottom: 2px solid var(--bs-blue-light); }
    </style>
</head>
<body>
    <div class="container my-5"><div class="row justify-content-center"><div class="col-lg-11"><div class="card form-card">
        <div class="card-header form-card-header text-center p-4"><h2 class="mb-1"><i class="bi bi-person-plus-fill"></i> Tambah Mitra Baru</h2><p class="mb-0">Lengkapi informasi di bawah ini.</p></div>
        <div class="card-body p-4 p-md-5">
            <form method="POST" action="" enctype="multipart/form-data">
                <h5 class="form-section-title pb-2 mb-4"><i class="bi bi-building"></i> Informasi Dasar Mitra</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-6"><label class="form-label">Nama Mitra <span class="text-danger">*</span></label><input type="text" name="nama_mitra" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Jenis Mitra <span class="text-danger">*</span></label>
                        <select name="jenis_mitra" class="form-select" required>
                            <option value="">-- Pilih Jenis --</option>
                            <option value="Kementerian/Lembaga">Kementerian/Lembaga</option>
                            <option value="Pemerintah Daerah">Pemerintah Daerah</option>
                            <option value="Swasta/Perusahaan">Swasta/Perusahaan</option>
                            <option value="Job Portal">Job Portal</option>
                            <option value="Universitas">Universitas</option>
                            <option value="Asosiasi/Komunitas">Asosiasi/Komunitas</option>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">Sumber Usulan</label><input type="text" name="sumber_usulan" class="form-control"></div>
                    <div class="col-md-6 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="tandai" value="1" id="tandaiCheck"><label class="form-check-label" for="tandaiCheck">Tandai sebagai prioritas</label></div></div>
                </div>
                <h5 class="form-section-title mt-5"><i class="bi bi-handshake"></i> Tahap Kesepahaman (MoU)</h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="mb-3"><label class="form-label">Status Kesepahaman</label><select name="status_kesepahaman" class="form-select"><option value="">-- Pilih Status --</option><option value="Signed">Signed</option><option value="Not Available">Not Available</option><option value="Drafting/In Progress">Drafting/In Progress</option></select></div>
                        <div class="mb-3"><label class="form-label">Nomor</label><input type="text" name="nomor_kesepahaman" class="form-control"></div>
                        <div class="mb-3"><label class="form-label">Tanggal</label><input type="date" name="tanggal_kesepahaman" class="form-control"></div>
                        <div class="mb-3"><label class="form-label">Status Pelaksanaan</label><select name="status_pelaksanaan_kesepahaman" class="form-select"><option value="">-- Pilih Status --</option><option value="Implemented">Implemented</option><option value="In Progress">In Progress</option><option value="Not Yet">Not Yet</option></select></div>
                        <div class="mb-3"><label class="form-label">Rencana Pertemuan</label><input type="date" name="rencana_pertemuan_kesepahaman" class="form-control"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3"><label class="form-label">Ruang Lingkup</label><textarea name="ruanglingkup_kesepahaman" class="form-control" rows="2"></textarea></div>
                        <div class="mb-3"><label class="form-label">Rencana Kolaborasi</label><textarea name="rencana_kolaborasi_kesepahaman" class="form-control" rows="2"></textarea></div>
                        <div class="mb-3"><label class="form-label">Status/Progres</label><textarea name="status_progres_kesepahaman" class="form-control" rows="2"></textarea></div>
                        <div class="mb-3"><label class="form-label">Tindak Lanjut</label><textarea name="tindaklanjut_kesepahaman" class="form-control" rows="2"></textarea></div>
                        <div class="mb-3"><label class="form-label">Keterangan</label><input type="text" name="keterangan_kesepahaman" class="form-control"></div>
                    </div>
                </div>
                <div class="text-center mt-4" id="lanjutPksButtonContainer"><button type="button" class="btn btn-warning" id="btnLanjutPks"><i class="bi bi-arrow-down-circle"></i> Lanjut isi PKS</button></div>
                <div id="pksSection" style="display: none;">
                    <h5 class="form-section-title mt-5"><i class="bi bi-file-earmark-text"></i> Tahap PKS</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3"><label class="form-label">Status PKS</label><select name="status_pks" class="form-select"><option value="">-- Pilih Status --</option><option value="Signed">Signed</option><option value="Not Available">Not Available</option><option value="Drafting/In Progress">Drafting/In Progress</option></select></div>
                            <div class="mb-3"><label class="form-label">Nomor</label><input type="text" name="nomor_pks" class="form-control"></div>
                            <div class="mb-3"><label class="form-label">Tanggal</label><input type="date" name="tanggal_pks" class="form-control"></div>
                            <div class="mb-3"><label class="form-label">Status Pelaksanaan PKS</label><select name="status_pelaksanaan_pks" class="form-select"><option value="">-- Pilih Status --</option><option value="Implemented">Implemented</option><option value="In Progress">In Progress</option><option value="Not Yet">Not Yet</option></select></div>
                            <div class="mb-3"><label class="form-label">Rencana Pertemuan</label><input type="date" name="rencana_pertemuan_pks" class="form-control"></div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3"><label class="form-label">Ruang Lingkup</label><textarea name="ruanglingkup_pks" class="form-control" rows="2"></textarea></div>
                            <div class="mb-3"><label class="form-label">Status/Progres</label><textarea name="status_progres_pks" class="form-control" rows="2"></textarea></div>
                            <div class="mb-3"><label class="form-label">Tindak Lanjut</label><textarea name="tindaklanjut_pks" class="form-control" rows="2"></textarea></div>
                            <div class="mb-3"><label class="form-label">Keterangan</label><input type="text" name="keterangan_pks" class="form-control"></div>
                        </div>
                    </div>
                </div>

                <h5 class="form-section-title mt-5"><i class="bi bi-paperclip"></i> Dokumen Pendukung</h5>
                <div class="row g-4 align-items-end">
                    <div class="col-md-6">
                        <label for="file1_nama" class="form-label">Nama File Kesepahaman (MoU)</label>
                        <input class="form-control" type="text" id="file1_nama" name="file1_nama" placeholder="Contoh: MoU dengan Mitra A">
                    </div>
                    <div class="col-md-6">
                        <label for="file1" class="form-label">Pilih File Kesepahaman</label>
                        <input class="form-control" type="file" id="file1" name="file1">
                    </div>
                    <div class="col-md-6">
                        <label for="file2_nama" class="form-label">Nama File PKS</label>
                        <input class="form-control" type="text" id="file2_nama" name="file2_nama" placeholder="Contoh: PKS Program Magang">
                    </div>
                    <div class="col-md-6">
                         <label for="file2" class="form-label">Pilih File PKS</label>
                        <input class="form-control" type="file" id="file2" name="file2">
                    </div>
                    <div class="col-md-6">
                        <label for="file3_nama" class="form-label">Nama File Notulensi</label>
                        <input class="form-control" type="text" id="file3_nama" name="file3_nama" placeholder="Contoh: Notulensi Rapat Kick-off">
                    </div>
                    <div class="col-md-6">
                         <label for="file3" class="form-label">Pilih File Notulensi</label>
                        <input class="form-control" type="file" id="file3" name="file3">
                    </div>
                </div>

                <hr class="my-5">
                <div class="d-flex justify-content-end gap-2"><a href="index.php" class="btn btn-secondary">Batal</a><button type="submit" class="btn btn-primary"><i class="bi bi-save-fill me-2"></i>Simpan Mitra</button></div>
            </form>
        </div>
    </div></div></div></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('btnLanjutPks').addEventListener('click', function() {
            this.parentElement.style.display = 'none';
            document.getElementById('pksSection').style.display = 'block';
            document.getElementById('pksSection').scrollIntoView({ behavior: 'smooth' });
        });
    </script>
</body>
</html>


