<?php
include "db.php";

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    die("Error: ID tidak valid.");
}

$stmt = $conn->prepare("SELECT * FROM tahapan_kerjasama WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    die("Error: Data tidak ditemukan.");
}

$pksDataExists = !empty(trim($row['status_pks'] ?? '')) || !empty(trim($row['nomor_pks'] ?? '')) || !empty(trim($row['tanggal_pks'] ?? ''));

$infoDasarMappings = [
    'nama_mitra' => 'Nama Mitra',
    'jenis_mitra' => 'Jenis Mitra',
    'sumber_usulan' => 'Sumber Usulan',
];

$kesepahamanMappings = [
    'status_kesepahaman' => 'Status KB',
    'nomor_kesepahaman' => 'Nomor KB',
    'tanggal_kesepahaman' => 'Tanggal KB',
    'ruanglingkup_kesepahaman' => 'Ruang Lingkup',
    'status_pelaksanaan_kesepahaman' => 'Status Pelaksanaan',
    'rencana_pertemuan_kesepahaman' => 'Rencana Pertemuan',
    'rencana_kolaborasi_kesepahaman' => 'Rencana Kolaborasi',
    'status_progres_kesepahaman' => 'Status/Progres',
    'tindaklanjut_kesepahaman' => 'Tindak Lanjut',
    'keterangan_kesepahaman' => 'Keterangan',
];

$pksMappings = [
    'status_pks' => 'Status PKS',
    'nomor_pks' => 'Nomor PKS',
    'tanggal_pks' => 'Tanggal PKS',
    'ruanglingkup_pks' => 'Ruang Lingkup',
    'status_pelaksanaan_pks' => 'Status Pelaksanaan',
    'rencana_pertemuan_pks' => 'Rencana Pertemuan',
    'status_progres_pks' => 'Status/Progres',
    'tindaklanjut_pks' => 'Tindak Lanjut',
    'keterangan_pks' => 'Keterangan'
];

$sanitized_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $row['nama_mitra']);
$filename = "mitra_" . $sanitized_name . "_" . date('Ymd') . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

function formatCellValue($value, $field) {
    if ($value === null || $value === '') return '-';
    
    $multiline_fields = [
        'ruanglingkup_kesepahaman', 'rencana_kolaborasi_kesepahaman', 
        'status_progres_kesepahaman', 'tindaklanjut_kesepahaman',
        'ruanglingkup_pks', 'status_progres_pks', 'tindaklanjut_pks'
    ];

    switch (true) {
        case $field === 'tandai':
            return $value == 1 ? 'Ya' : 'Tidak';
        case in_array($field, ['tanggal_kesepahaman', 'tanggal_pks', 'rencana_pertemuan_kesepahaman', 'rencana_pertemuan_pks']):
            return $value ? date('d-m-Y', strtotime($value)) : '-';
        case in_array($field, $multiline_fields):
            return nl2br(htmlspecialchars($value));
        default:
            return htmlspecialchars($value);
    }
}

echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8">';
echo '<style>
    .header { background-color: #0a3d62; color: white; font-weight: bold; text-align: center; } 
    .title { background-color: #0a3d62; color: white; font-size: 22px; font-weight: bold; text-align: center; }
    .subtitle { font-size: 20px; font-weight: bold; background-color: #e9ecef; text-align: center; }
    td { padding: 5px; border: 1px solid #ccc; vertical-align: top; }
    th { padding: 8px; border: 1px solid #333; font-weight: bold; text-align: center; }
</style>';
echo '</head><body>';
echo '<table border="1" style="border-collapse: collapse; width: 100%;">';

echo '<tr><td colspan="10" class="title">' . strtoupper(htmlspecialchars($row['nama_mitra'])) . '</td></tr>';
echo '<tr><td colspan="10">&nbsp;</td></tr>';

echo '<tr><td colspan="10" class="subtitle">Informasi Dasar</td></tr>';
echo '<tr>';
foreach ($infoDasarMappings as $label) { echo '<th class="header">' . htmlspecialchars($label) . '</th>'; }
echo '</tr>';
echo '<tr>';
foreach ($infoDasarMappings as $field => $label) {
    $value = formatCellValue($row[$field] ?? '', $field);
    echo "<td>" . $value . "</td>";
}
echo '</tr>';
echo '<tr><td colspan="10">&nbsp;</td></tr>';

echo '<tr><td colspan="10" class="subtitle">Tahap Kesepahaman Bersama</td></tr>';
echo '<tr>';
foreach ($kesepahamanMappings as $label) { echo '<th class="header">' . htmlspecialchars($label) . '</th>'; }
echo '</tr>';
echo '<tr>';
foreach ($kesepahamanMappings as $field => $label) {
    $value = formatCellValue($row[$field] ?? '', $field);
    $style = in_array($field, ['ruanglingkup_kesepahaman', 'rencana_kolaborasi_kesepahaman', 'status_progres_kesepahaman', 'tindaklanjut_kesepahaman']) ? 'style="white-space: pre-wrap;"' : '';
    echo "<td {$style}>" . $value . "</td>";
}
echo '</tr>';

if ($pksDataExists) {
    echo '<tr><td colspan="10">&nbsp;</td></tr>';
    echo '<tr><td colspan="10" class="subtitle">Tahap Perjanjian Kerja Sama (PKS)</td></tr>';
    echo '<tr>';
    foreach ($pksMappings as $label) { echo '<th class="header">' . htmlspecialchars($label) . '</th>'; }
    echo '</tr>';
    echo '<tr>';
    foreach ($pksMappings as $field => $label) {
        $value = formatCellValue($row[$field] ?? '', $field);
        $style = in_array($field, ['ruanglingkup_pks', 'status_progres_pks', 'tindaklanjut_pks']) ? 'style="white-space: pre-wrap;"' : '';
        echo "<td {$style}>" . $value . "</td>";
    }
    echo '</tr>';
}

echo '</table></body></html>';

$stmt->close();
$conn->close();
?>


