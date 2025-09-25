<?php
include "init.php";

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die("Error: ID tidak valid.");
}

$stmt = $conn->prepare("SELECT * FROM tahapan_kerjasama WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    die("Error: Data tidak ditemukan.");
}

$nama_file = "Detail_Mitra_" . preg_replace('/[^a-zA-Z0-9_-]/', '_', $data['nama_mitra']) . ".doc";
header("Content-Type: application/vnd.ms-word");
header("Content-Disposition: attachment; filename=\"$nama_file\"");
header("Pragma: no-cache");
header("Expires: 0");

function formatValue($value) { return !empty($value) ? htmlspecialchars($value) : '-'; }
function formatDate($date) { return $date ? date('d F Y', strtotime($date)) : '-'; }
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    body { font-family: Arial, sans-serif; font-size: 11pt; margin: 0cm; }
    .container { width: 100%; padding: 1cm; }
    h1 { text-align: center; font-weight: bold; font-size: 14pt; }
    h2 { font-size: 12pt; margin-top: 20px; text-align: left; font-weight: bold; border-bottom: 2px solid #000; padding-bottom: 5px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; text-align: left; }
    td { padding: 5px; border: 1px solid #000; vertical-align: top; }
    td.label { font-weight: bold; width: 35%; background-color: #f2f2f2; }
</style>
</head>
<body>
    <div class="container">
        <h1>DETAIL KERJASAMA MITRA</h1>
        <h2>Informasi Dasar</h2>
        <table>
            <tr><td class="label">Nama Mitra</td><td><?php echo formatValue($data['nama_mitra']); ?></td></tr>
            <tr><td class="label">Jenis Mitra</td><td><?php echo formatValue($data['jenis_mitra']); ?></td></tr>
            <tr><td class="label">Sumber Usulan</td><td><?php echo formatValue($data['sumber_usulan']); ?></td></tr>
            <tr><td class="label">Prioritas</td><td><?php echo $data['tandai'] ? 'Ya' : 'Tidak'; ?></td></tr>
        </table>

        <h2>Tahap Kesepahaman Bersama</h2>
        <table>
            <tr><td class="label">Status</td><td><?php echo formatValue($data['status_kesepahaman']); ?></td></tr>
            <tr><td class="label">Nomor</td><td><?php echo formatValue($data['nomor_kesepahaman']); ?></td></tr>
            <tr><td class="label">Tanggal</td><td><?php echo formatDate($data['tanggal_kesepahaman']); ?></td></tr>
            <tr><td class="label">Ruang Lingkup</td><td><?php echo nl2br(formatValue($data['ruanglingkup_kesepahaman'])); ?></td></tr>
            <tr><td class="label">Status Pelaksanaan</td><td><?php echo formatValue($data['status_pelaksanaan_kesepahaman']); ?></td></tr>
            <tr><td class="label">Rencana Pertemuan</td><td><?php echo formatDate($data['rencana_pertemuan_kesepahaman']); ?></td></tr>
            <tr><td class="label">Progres</td><td><?php echo nl2br(formatValue($data['status_progres_kesepahaman'])); ?></td></tr>
            <tr><td class="label">Tindak Lanjut</td><td><?php echo nl2br(formatValue($data['tindaklanjut_kesepahaman'])); ?></td></tr>
        </table>

        <?php if (!empty($data['status_pks']) || !empty($data['nomor_pks'])): ?>
        <h2>Tahap Perjanjian Kerja Sama (PKS)</h2>
        <table>
            <tr><td class="label">Status</td><td><?php echo formatValue($data['status_pks']); ?></td></tr>
            <tr><td class="label">Nomor</td><td><?php echo formatValue($data['nomor_pks']); ?></td></tr>
            <tr><td class="label">Tanggal</td><td><?php echo formatDate($data['tanggal_pks']); ?></td></tr>
            <tr><td class="label">Ruang Lingkup</td><td><?php echo nl2br(formatValue($data['ruanglingkup_pks'])); ?></td></tr>
            <tr><td class="label">Status Pelaksanaan</td><td><?php echo formatValue($data['status_pelaksanaan_pks']); ?></td></tr>
            <tr><td class="label">Rencana Pertemuan</td><td><?php echo formatDate($data['rencana_pertemuan_pks']); ?></td></tr>
            <tr><td class="label">Progres</td><td><?php echo nl2br(formatValue($data['status_progres_pks'])); ?></td></tr>
            <tr><td class="label">Tindak Lanjut</td><td><?php echo nl2br(formatValue($data['tindaklanjut_pks'])); ?></td></tr>
        </table>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>


