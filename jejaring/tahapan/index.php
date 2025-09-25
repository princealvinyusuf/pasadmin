<?php
include "../../views/header.php";
include "db.php";

// Menampilkan pesan notifikasi
$message = '';
$messageType = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $message = 'Data mitra berhasil ditambahkan!';
            $messageType = 'success';
            break;
        case 'updated':
            $message = 'Data mitra berhasil diperbarui!';
            $messageType = 'success';
            break;
        case 'deleted':
            $message = 'Data mitra berhasil dihapus!';
            $messageType = 'success';
            break;
        case 'file_deleted':
            $message = 'File berhasil dihapus!';
            $messageType = 'success';
            break;
    }
}
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'invalid_id':
        case 'invalid_request':
            $message = 'Permintaan tidak valid!';
            $messageType = 'danger';
            break;
        case 'not_found':
            $message = 'Data tidak ditemukan!';
            $messageType = 'danger';
            break;
        case 'delete_failed':
        case 'db_update_failed':
            $message = 'Gagal memproses data!';
            $messageType = 'danger';
            break;
    }
}

// FUNGSI UNTUK MENCOCOKKAN SEMUA JENIS MITRA
function getBadgeClass($type, $value) {
    if ($type === 'jenis') {
        if (empty($value)) return "secondary";
        $value_lower = strtolower($value); 

        if (str_contains($value_lower, 'kementerian') || str_contains($value_lower, 'lembaga')) return 'success';
        if (str_contains($value_lower, 'pemerintah')) return 'primary';
        if (str_contains($value_lower, 'swasta') || str_contains($value_lower, 'perusahaan')) return 'dark';
        if (str_contains($value_lower, 'portal')) return 'secondary';
        if (str_contains($value_lower, 'universitas')) return 'warning';
        if (str_contains($value_lower, 'asosiasi') || str_contains($value_lower, 'komunitas')) return 'info';
        
        return 'secondary';
    }

    if ($type === 'status') {
         if (empty($value)) return "secondary";
         $status_map = [
            "Signed" => "success", "Not Available" => "secondary", "Drafting/In Progress" => "warning", 
            "Implemented" => "success", "In Progress" => "warning", "Not Yet" => "secondary"
         ];
         return $status_map[$value] ?? 'secondary';
    }

    return 'secondary';
}

function formatDate($date) {
    return $date ? date('d M Y', strtotime($date)) : '-';
}

function formatValue($value) {
    return htmlspecialchars($value ?: '-');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Mitra Kerjasama</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --bs-blue-dark: #0a3d62; --bs-blue-light: #3c6382; --bs-gray: #f5f7fa; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--bs-gray); }
        .main-container { background-color: white; border-radius: 1rem; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08); padding: 2rem; margin-top: -4rem; position: relative; z-index: 2; }
        .page-title { color: var(--bs-blue-dark); font-weight: 700; }
        .controls-section { background-color: var(--bs-gray); border-radius: 0.75rem; padding: 1.5rem; }
        .table thead th { background-color: var(--bs-blue-dark); color: white; text-align: center; vertical-align: middle; }
        .table tbody tr:hover { transform: scale(1.015); box-shadow: 0 5px 15px rgba(0,0,0,0.1); background-color: #e9ecef; }
        .modal-header { background: linear-gradient(135deg, var(--bs-blue-dark) 0%, var(--bs-blue-light) 100%); color: white; }
        .detail-section { background-color: var(--bs-gray); border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem; }
        .detail-section h6 { font-weight: 700; color: var(--bs-blue-dark); border-bottom: 1px solid #ccc; padding-bottom: 0.5rem; margin-bottom: 1rem; }
        .detail-label { font-weight: 600; color: #555; }
        .status-cell-info { font-size: 0.75rem; line-height: 1.2; }
    </style>
</head>
<body>
<div class="container my-5">
    <div class="main-container">
        <div class="text-center mb-4">
            <h1 class="page-title"><i class="bi bi-diagram-3-fill"></i> Manajemen Tahapan Kerjasama</h1>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType; ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="controls-section mb-4">
            <div class="row g-3 align-items-center">
                <div class="col-lg-3 col-md-6"><input type="text" id="searchInput" class="form-control" placeholder="Cari nama mitra..."></div>
                <div class="col-lg-3 col-md-6">
                    <select id="filterJenis" class="form-select">
                        <option value="">Semua Jenis Mitra</option>
                        <option value="Kementerian/Lembaga">Kementerian/Lembaga</option>
                        <option value="Pemerintah Daerah">Pemerintah Daerah</option>
                        <option value="Swasta/Perusahaan">Swasta/Perusahaan</option>
                        <option value="Job Portal">Job Portal</option>
                        <option value="Universitas">Universitas</option>
                        <option value="Asosiasi/Komunitas">Asosiasi/Komunitas</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <select id="filterPrioritas" class="form-select">
                        <option value="">Semua Prioritas</option>
                        <option value="1">Mitra Prioritas</option>
                        <option value="0">Bukan Prioritas</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6 text-end"><a href="create.php" class="btn btn-primary w-100"><i class="bi bi-plus-circle"></i> Tambah Mitra Baru</a></div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                 <thead>
                    <tr><th>No</th><th>Nama Mitra</th><th>Jenis</th><th>Status KB</th><th>Status PKS</th><th>Aksi</th></tr>
                </thead>
                <tbody id="mitraTableBody">
                <?php
                $result = $conn->query("SELECT * FROM tahapan_kerjasama ORDER BY tandai DESC, id DESC");
                if ($result && $result->num_rows > 0):
                    $no = 1;
                    while ($row = $result->fetch_assoc()):
                ?>
                    <tr data-bs-toggle="modal" data-bs-target="#detailModal<?= $row['id'] ?>" style="cursor:pointer;" 
                        data-nama="<?= strtolower(htmlspecialchars($row['nama_mitra'])) ?>" 
                        data-jenis="<?= htmlspecialchars($row['jenis_mitra']) ?>"
                        data-prioritas="<?= $row['tandai'] ?>">
                        <td class="text-center fw-bold"><?= $no++ ?></td>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($row['nama_mitra']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($row['sumber_usulan'] ?: 'Sumber tidak diketahui') ?></small>
                            <?php if ($row['tandai']): ?><span class="badge bg-warning text-dark ms-2">Prioritas</span><?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php 
                                $jenis_mitra = $row['jenis_mitra'];
                                $badge_class = getBadgeClass('jenis', $jenis_mitra);
                                $display_text = !empty($jenis_mitra) ? htmlspecialchars($jenis_mitra) : 'Tidak Diketahui';
                            ?>
                            <span class="badge rounded-pill text-bg-<?= $badge_class ?>"><?= $display_text ?></span>
                        </td>
                        <td class="text-center" style="vertical-align: top;">
                            <span class="badge rounded-pill text-bg-<?= getBadgeClass('status', $row['status_kesepahaman']) ?> mb-1"><?= htmlspecialchars($row['status_kesepahaman'] ?: 'N/A') ?></span>
                            <div class="status-cell-info text-muted mt-1">
                                <div class="text-truncate" title="<?= htmlspecialchars($row['nomor_kesepahaman']) ?>"><?= formatValue($row['nomor_kesepahaman']) ?></div>
                                <div><?= formatDate($row['tanggal_kesepahaman']) ?></div>
                            </div>
                        </td>
                        <td class="text-center" style="vertical-align: top;">
                            <span class="badge rounded-pill text-bg-<?= getBadgeClass('status', $row['status_pks']) ?> mb-1"><?= htmlspecialchars($row['status_pks'] ?: 'N/A') ?></span>
                            <div class="status-cell-info text-muted mt-1">
                                <div class="text-truncate" title="<?= htmlspecialchars($row['nomor_pks']) ?>"><?= formatValue($row['nomor_pks']) ?></div>
                                <div><?= formatDate($row['tanggal_pks']) ?></div>
                            </div>
                        </td>
                        <td class="text-center" onclick="event.stopPropagation();">
                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#deleteConfirmModal"
                                    data-delete-url="delete.php?id=<?= $row['id'] ?>">
                                <i class="bi bi-trash-fill"></i>
                            </button>
                        </td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="6" class="text-center p-5">Belum ada data mitra.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
if ($result && $result->num_rows > 0) {
    $result->data_seek(0);
    while($row = $result->fetch_assoc()):
?>
<div class="modal fade" id="detailModal<?= $row['id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-info-circle-fill"></i> Detail Mitra: <?= htmlspecialchars($row['nama_mitra']) ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="detail-section"><h6><i class="bi bi-building"></i> Informasi Dasar</h6><div class="row"><div class="col-md-4"><p><span class="detail-label">Jenis Mitra:</span><br><?= formatValue($row['jenis_mitra']) ?></p></div><div class="col-md-4"><p><span class="detail-label">Sumber Usulan:</span><br><?= formatValue($row['sumber_usulan']) ?></p></div><div class="col-md-4"><p><span class="detail-label">Prioritas:</span><br><?= $row['tandai'] ? 'Ya' : 'Tidak' ?></p></div></div></div>
                <div class="detail-section"><h6><i class="bi bi-handshake"></i> Tahap Kesepahaman Bersama</h6><div class="row"><div class="col-md-4"><p><span class="detail-label">Status:</span><br><?= formatValue($row['status_kesepahaman']) ?></p></div><div class="col-md-4"><p><span class="detail-label">Nomor:</span><br><?= formatValue($row['nomor_kesepahaman']) ?></p></div><div class="col-md-4"><p><span class="detail-label">Tanggal:</span><br><?= formatDate($row['tanggal_kesepahaman']) ?></p></div><div class="col-md-4"><p><span class="detail-label">Status Pelaksanaan:</span><br><?= formatValue($row['status_pelaksanaan_kesepahaman']) ?></p></div><div class="col-md-4"><p><span class="detail-label">Rencana Pertemuan:</span><br><?= formatDate($row['rencana_pertemuan_kesepahaman']) ?></p></div><div class="col-md-12"><p><span class="detail-label">Ruang Lingkup:</span><br><?= nl2br(formatValue($row['ruanglingkup_kesepahaman'])) ?></p></div><div class="col-md-12"><p><span class="detail-label">Rencana Kolaborasi:</span><br><?= nl2br(formatValue($row['rencana_kolaborasi_kesepahaman'])) ?></p></div><div class="col-md-12"><p><span class="detail-label">Status/Progres:</span><br><?= nl2br(formatValue($row['status_progres_kesepahaman'])) ?></p></div><div class="col-md-12"><p><span class="detail-label">Tindak Lanjut:</span><br><?= nl2br(formatValue($row['tindaklanjut_kesepahaman'])) ?></p></div><div class="col-md-12"><p><span class="detail-label">Keterangan:</span><br><?= formatValue($row['keterangan_kesepahaman']) ?></p></div></div></div>
                <div class="detail-section"><h6><i class="bi bi-file-earmark-text"></i> Tahap PKS</h6><div class="row"><div class="col-md-4"><p><span class="detail-label">Status:</span><br><?= formatValue($row['status_pks']) ?></p></div><div class="col-md-4"><p><span class="detail-label">Nomor:</span><br><?= formatValue($row['nomor_pks']) ?></p></div><div class="col-md-4"><p><span class="detail-label">Tanggal:</span><br><?= formatDate($row['tanggal_pks']) ?></p></div><div class="col-md-4"><p><span class="detail-label">Status Pelaksanaan:</span><br><?= formatValue($row['status_pelaksanaan_pks']) ?></p></div><div class="col-md-4"><p><span class="detail-label">Rencana Pertemuan:</span><br><?= formatDate($row['rencana_pertemuan_pks']) ?></p></div><div class="col-md-12"><p><span class="detail-label">Ruang Lingkup:</span><br><?= nl2br(formatValue($row['ruanglingkup_pks'])) ?></p></div><div class="col-md-12"><p><span class="detail-label">Status/Progres:</span><br><?= nl2br(formatValue($row['status_progres_pks'])) ?></p></div><div class="col-md-12"><p><span class="detail-label">Tindak Lanjut:</span><br><?= nl2br(formatValue($row['tindaklanjut_pks'])) ?></p></div><div class="col-md-12"><p><span class="detail-label">Keterangan:</span><br><?= formatValue($row['keterangan_pks']) ?></p></div></div></div>
                
                <div class="detail-section">
                    <h6><i class="bi bi-paperclip"></i> Dokumen Pendukung</h6>
                    <ul class="list-group">
                        <?php
                        $fileLabels = ['File Kesepahaman', 'File PKS', 'File Notulensi'];
                        $fileFound = false;
                        for ($i = 1; $i <= 3; $i++):
                            $fileKey = "file" . $i;
                            if (!empty($row[$fileKey])):
                                $fileFound = true;
                        ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-file-earmark-text-fill"></i>
                                <strong><?= $fileLabels[$i-1] ?>:</strong>
                                <span class="ms-2"><?= htmlspecialchars($row[$fileKey]) ?></span>
                            </div>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="openFileViewer('<?= urlencode($row[$fileKey]) ?>')">
                                    <i class="bi bi-eye"></i> Lihat
                                </button>
                                <a href="uploads/<?= htmlspecialchars($row[$fileKey]) ?>" class="btn btn-sm btn-outline-success" download>
                                    <i class="bi bi-download"></i> Download
                                </a>
                                <a href="delete_file.php?id=<?= $row['id'] ?>&file_key=<?= $fileKey ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Anda yakin ingin menghapus file ini secara permanen?')">
                                    <i class="bi bi-trash"></i> Hapus
                                </a>
                            </div>
                        </li>
                        <?php 
                            endif;
                        endfor;
                        if (!$fileFound):
                        ?>
                        <li class="list-group-item text-muted">Tidak ada dokumen yang diunggah.</li>
                        <?php endif; ?>
                    </ul>
                </div>

            </div>
            <div class="modal-footer">
                <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-warning"><i class="bi bi-pencil-square"></i> Edit</a>
                <a href="export_word.php?id=<?= $row['id'] ?>" class="btn btn-primary"><i class="bi bi-file-earmark-word-fill"></i> Export Word</a>
                <a href="export_excel.php?id=<?= $row['id'] ?>" class="btn btn-success"><i class="bi bi-file-earmark-excel-fill"></i> Export Excel</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
<?php
    endwhile;
}
?>

<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteModalLabel"><i class="bi bi-exclamation-triangle-fill"></i> Konfirmasi Hapus</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Apakah Anda benar-benar yakin ingin menghapus data mitra ini? Proses ini tidak dapat dibatalkan.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
        <a href="#" id="confirmDeleteBtn" class="btn btn-danger"><i class="bi bi-trash-fill"></i> Ya, Hapus</a>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="viewFileModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Pratinjau Dokumen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="height: 80vh;">
                <iframe id="fileViewerFrame" src="about:blank" width="100%" height="100%" frameborder="0"></iframe>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let viewFileModal;

    document.addEventListener('DOMContentLoaded', function() {
        viewFileModal = new bootstrap.Modal(document.getElementById('viewFileModal'));
        
        const searchInput = document.getElementById('searchInput');
        const filterJenis = document.getElementById('filterJenis');
        const filterPrioritas = document.getElementById('filterPrioritas');
        const tableBody = document.getElementById('mitraTableBody');
        const rows = tableBody.getElementsByTagName('tr');
        
        function filterTable() {
            const searchFilter = searchInput.value.toLowerCase();
            const jenisFilter = filterJenis.value;
            const prioritasFilter = filterPrioritas.value;
            
            for (let i = 0; i < rows.length; i++) {
                if(rows[i].children.length < 2) continue;
                
                const nama = rows[i].dataset.nama || '';
                const jenis = rows[i].dataset.jenis || '';
                const prioritas = rows[i].dataset.prioritas || '0';
                
                const showByName = nama.includes(searchFilter);
                const showByJenis = jenisFilter === '' || jenis === jenisFilter;
                const showByPrioritas = prioritasFilter === '' || prioritas === prioritasFilter;
                
                rows[i].style.display = (showByName && showByJenis && showByPrioritas) ? '' : 'none';
            }
        }
        
        searchInput.addEventListener('keyup', filterTable);
        filterJenis.addEventListener('change', filterTable);
        filterPrioritas.addEventListener('change', filterTable);

        const deleteConfirmModal = document.getElementById('deleteConfirmModal');
        if (deleteConfirmModal) {
            deleteConfirmModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const deleteUrl = button.getAttribute('data-delete-url');
                const confirmBtn = deleteConfirmModal.querySelector('#confirmDeleteBtn');
                confirmBtn.setAttribute('href', deleteUrl);
            });
        }
    });

    function openFileViewer(fileName) {
        const frame = document.getElementById('fileViewerFrame');
        frame.src = `view_file.php?file=${fileName}`;
        viewFileModal.show();
    }
</script>
</body>
</html>


