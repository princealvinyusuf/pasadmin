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

function build_query_url(array $params): string
{
    return http_build_query($params);
}

function pdf_escape(string $text): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

function pdf_fit(string $text, int $maxLen = 82): string
{
    $text = trim($text);
    if (strlen($text) <= $maxLen) {
        return $text;
    }
    return substr($text, 0, $maxLen - 3) . '...';
}

function pdf_logo_jpeg_data(string $logoPath): ?array
{
    if (!is_file($logoPath)) {
        return null;
    }
    if (!function_exists('imagecreatefrompng') || !function_exists('imagejpeg')) {
        return null;
    }

    $png = @imagecreatefrompng($logoPath);
    if ($png === false) {
        return null;
    }

    $width = imagesx($png);
    $height = imagesy($png);
    if ($width <= 0 || $height <= 0) {
        imagedestroy($png);
        return null;
    }

    // Render PNG on white background so transparent parts stay readable in PDF.
    $canvas = imagecreatetruecolor($width, $height);
    if ($canvas === false) {
        imagedestroy($png);
        return null;
    }
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
    imagecopy($canvas, $png, 0, 0, 0, 0, $width, $height);
    imagedestroy($png);

    ob_start();
    imagejpeg($canvas, null, 90);
    $jpegData = (string)ob_get_clean();
    imagedestroy($canvas);

    if ($jpegData === '') {
        return null;
    }

    return [
        'data' => $jpegData,
        'width' => $width,
        'height' => $height,
    ];
}

function generate_official_bukti_lapor_pdf(array $row, string $unitName): string
{
    $issuedAt = date('d-m-Y H:i:s');
    $rows = [
        ['No. Reg Bukti', (string)($row['no_reg_bukti'] ?? '-')],
        ['Tanggal Lapor', (string)($row['tanggal_lapor'] ?? '-')],
        ['ID Lowongan', (string)($row['id_lowongan'] ?? '-')],
        ['Jabatan', (string)($row['jabatan'] ?? '-')],
        ['Jumlah Kebutuhan', (string)($row['jumlah_kebutuhan'] ?? 0)],
        ['Unit/Perusahaan', $unitName],
        ['Lokasi Penempatan', (string)($row['lokasi_penempatan_detail'] ?? '-')],
        ['Masa Berlaku', (string)($row['masa_berlaku_mulai'] ?? '-') . ' s.d. ' . (string)($row['masa_berlaku_sampai'] ?? '-')],
        ['Tipe Kerja', (string)($row['employment_type'] ?? '-') . ' / ' . (string)($row['work_setup'] ?? '-') . ' / ' . (string)($row['shift_type'] ?? '-')],
        ['Kode KBJI', (string)($row['kode_kbji'] ?? '-')],
        ['Provinsi', (string)($row['provinsi'] ?? '-')],
        ['Kota', (string)($row['kota'] ?? '-')],
        ['Kecamatan', (string)($row['kecamatan'] ?? '-')],
        ['Kelurahan', (string)($row['kelurahan'] ?? '-')],
        ['Bidang Pekerjaan', (string)($row['bidang_pekerjaan'] ?? '-')],
        ['Industri / Sektor', (string)($row['industri_sektor'] ?? '-')],
        ['Status Pernikahan', (string)($row['status_pernikahan'] ?? '-')],
        ['Status Verifikasi', (string)($row['status_verifikasi'] ?? '-')],
        ['Status Keterisian', (string)($row['status_keterisian'] ?? '-')],
        ['Approval', (string)($row['approval_state'] ?? '-') . ' by ' . (string)($row['approval_by'] ?? '-') . ' (' . (string)($row['approval_date'] ?? '-') . ')'],
    ];

    $streamParts = [];
    $left = 40;
    $right = 555;
    $width = $right - $left;
    $headerY = 775;
    $headerH = 58;
    $logoPath = __DIR__ . '/images/karirhub.png';
    $logoInfo = pdf_logo_jpeg_data($logoPath);

    // Page frame.
    $streamParts[] = '0.87 0.9 0.95 RG';
    $streamParts[] = '1 w';
    $streamParts[] = '30 30 535 782 re S';

    // Watermark text.
    $streamParts[] = '0.93 0.95 0.98 rg';
    $streamParts[] = 'BT /F2 34 Tf 0.707 0.707 -0.707 0.707 180 360 Tm (' . pdf_escape('DIPEROLEH DARI KARIRHUB') . ') Tj ET';

    // Header band.
    $streamParts[] = '0.08 0.29 0.55 rg';
    $streamParts[] = $left . ' ' . $headerY . ' ' . $width . ' ' . $headerH . ' re f';
    $streamParts[] = '1 1 1 rg';
    $streamParts[] = 'BT /F2 13 Tf 52 817 Td (' . pdf_escape('KEMENTERIAN KETENAGAKERJAAN REPUBLIK INDONESIA') . ') Tj ET';
    $streamParts[] = 'BT /F1 10 Tf 52 802 Td (' . pdf_escape('SIAPKERJA - KARIRHUB') . ') Tj ET';
    $streamParts[] = 'BT /F2 11 Tf 52 788 Td (' . pdf_escape('BUKTI LAPOR LOWONGAN PEKERJAAN (WLLP)') . ') Tj ET';

    // Section title.
    $sectionTop = 746;
    $streamParts[] = '0.95 0.97 1 rg';
    $streamParts[] = $left . ' ' . $sectionTop . ' ' . $width . ' 24 re f';
    $streamParts[] = '0.77 0.82 0.9 RG';
    $streamParts[] = '0.8 w';
    $streamParts[] = $left . ' ' . $sectionTop . ' ' . $width . ' 24 re S';
    $streamParts[] = '0.11 0.2 0.36 rg';
    $streamParts[] = 'BT /F2 10 Tf 48 ' . ($sectionTop + 8) . ' Td (' . pdf_escape('RINGKASAN DOKUMEN') . ') Tj ET';

    // Key/value table.
    $tableTopY = $sectionTop;
    $rowH = 18;
    $splitX = 190;
    $currentTop = $tableTopY;
    $rowIndex = 0;
    foreach ($rows as $item) {
        $yBottom = $currentTop - $rowH;
        if ($rowIndex % 2 === 0) {
            $streamParts[] = '0.985 0.99 1 rg';
        } else {
            $streamParts[] = '1 1 1 rg';
        }
        $streamParts[] = $left . ' ' . $yBottom . ' ' . $width . ' ' . $rowH . ' re f';
        $streamParts[] = '0.82 0.85 0.9 RG';
        $streamParts[] = '0.7 w';
        $streamParts[] = $left . ' ' . $yBottom . ' ' . $width . ' ' . $rowH . ' re S';
        $streamParts[] = $splitX . ' ' . $yBottom . ' m ' . $splitX . ' ' . $currentTop . ' l S';

        $streamParts[] = '0.22 0.24 0.28 rg';
        $streamParts[] = 'BT /F2 9 Tf 48 ' . ($yBottom + 6) . ' Td (' . pdf_escape(pdf_fit((string)$item[0], 34)) . ') Tj ET';
        $streamParts[] = 'BT /F1 9 Tf 198 ' . ($yBottom + 6) . ' Td (' . pdf_escape(pdf_fit((string)$item[1], 78)) . ') Tj ET';

        $currentTop -= $rowH;
        $rowIndex++;
    }

    // Catatan panel.
    $catatanTop = $currentTop - 8;
    $catatanH = 48;
    $streamParts[] = '0.94 0.97 1 rg';
    $streamParts[] = $left . ' ' . ($catatanTop - $catatanH) . ' ' . $width . ' ' . $catatanH . ' re f';
    $streamParts[] = '0.82 0.85 0.9 RG';
    $streamParts[] = '0.8 w';
    $streamParts[] = $left . ' ' . ($catatanTop - $catatanH) . ' ' . $width . ' ' . $catatanH . ' re S';
    $streamParts[] = '0.11 0.2 0.36 rg';
    $streamParts[] = 'BT /F2 9 Tf 48 ' . ($catatanTop - 13) . ' Td (' . pdf_escape('CATATAN') . ') Tj ET';
    $streamParts[] = '0.25 0.26 0.31 rg';
    $streamParts[] = 'BT /F1 9 Tf 48 ' . ($catatanTop - 27) . ' Td (' . pdf_escape(pdf_fit((string)($row['catatan'] ?? '-'), 100)) . ') Tj ET';

    // Footer.
    $footerY = 62;
    $streamParts[] = '0.74 0.78 0.86 RG';
    $streamParts[] = '0.7 w';
    $streamParts[] = $left . ' ' . ($footerY + 18) . ' m ' . $right . ' ' . ($footerY + 18) . ' l S';
    $streamParts[] = '0.35 0.38 0.45 rg';
    $streamParts[] = 'BT /F2 8 Tf 48 ' . ($footerY + 20) . ' Td (' . pdf_escape('Diperoleh dari:') . ') Tj ET';
    $streamParts[] = 'BT /F1 8 Tf 48 ' . ($footerY + 6) . ' Td (' . pdf_escape('Diterbitkan oleh sistem prototype pada: ' . $issuedAt) . ') Tj ET';
    $streamParts[] = 'BT /F1 8 Tf 48 ' . ($footerY - 6) . ' Td (' . pdf_escape('Dokumen ini hanya untuk referensi UI/UX dan bukan dokumen legal resmi.') . ') Tj ET';

    // Footer logo.
    if ($logoInfo !== null) {
        $targetW = 138.0;
        $targetH = ($logoInfo['height'] / max(1, $logoInfo['width'])) * $targetW;
        $logoX = 410.0;
        $logoY = $footerY + 2.0;
        $streamParts[] = 'q';
        $streamParts[] = sprintf('%.2F 0 0 %.2F %.2F %.2F cm', $targetW, $targetH, $logoX, $logoY);
        $streamParts[] = '/Im1 Do';
        $streamParts[] = 'Q';
    }

    $contentStream = implode("\n", $streamParts) . "\n";

    $objects = [];
    $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    $resources = '/Font << /F1 5 0 R /F2 6 0 R >>';
    if ($logoInfo !== null) {
        $resources .= ' /XObject << /Im1 7 0 R >>';
    }
    $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << " . $resources . " >> /Contents 4 0 R >>\nendobj\n";
    $objects[] = "4 0 obj\n<< /Length " . strlen($contentStream) . " >>\nstream\n" . $contentStream . "endstream\nendobj\n";
    $objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    $objects[] = "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n";
    if ($logoInfo !== null) {
        $jpegData = $logoInfo['data'];
        $objects[] = "7 0 obj\n<< /Type /XObject /Subtype /Image /Width " . (int)$logoInfo['width'] . " /Height " . (int)$logoInfo['height'] . " /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpegData) . " >>\nstream\n" . $jpegData . "\nendstream\nendobj\n";
    }

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $obj) {
        $offsets[] = strlen($pdf);
        $pdf .= $obj;
    }

    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
    }

    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n" . $xrefPos . "\n%%EOF";

    return $pdf;
}

$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$allowedStatuses = ['all', 'terverifikasi', 'perlu update'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}

$unitFilter = trim((string)($_GET['unit'] ?? 'all'));
$query = strtolower(trim((string)($_GET['q'] ?? '')));

$dataset = karirhub_proto_dataset();
$units = $dataset['units'];
$rows = $dataset['vacancies'];
$rowsByNoReg = [];
foreach ($rows as $r) {
    $rowsByNoReg[(string)$r['no_reg_bukti']] = $r;
}
$unitOptions = [];
foreach ($units as $unitCode => $unitInfo) {
    $unitOptions[$unitCode] = $unitInfo['nama'];
}

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

$unitCodeByName = [];
foreach ($units as $code => $unitInfo) {
    $unitCodeByName[(string)$unitInfo['nama']] = (string)$code;
}

$defaultMeta = [
    'requested_by' => 'N/A',
    'requester_divisi' => 'N/A',
    'hiring_manager' => 'N/A',
    'cost_center' => 'CC-NA',
    'employment_type' => 'PKWT',
    'work_setup' => 'Onsite',
    'shift_type' => 'Non-Shift',
    'lokasi_penempatan_detail' => '-',
    'sumber_rekrutmen' => 'Karirhub',
    'target_tgl_join' => date('Y-m-d', strtotime('+30 days')),
    'sla_hiring_hari' => 30,
    'jumlah_lamaran_masuk' => 0,
    'jumlah_shortlist' => 0,
    'jumlah_interview' => 0,
    'jumlah_offer' => 0,
    'approval_state' => 'Pending',
    'approval_by' => '-',
    'approval_date' => '-',
    'budget_status' => 'Pending',
];

$resPelaporan = $conn->query("SELECT * FROM karirhub_proto_wllp_pelaporan ORDER BY created_at DESC");
if ($resPelaporan) {
    while ($p = $resPelaporan->fetch_assoc()) {
        $nr = (string)$p['no_reg_bukti'];
        if (isset($rowsByNoReg[$nr])) {
            $rowsByNoReg[$nr] = array_merge($rowsByNoReg[$nr], [
                'id_lowongan' => (string)$p['id_lowongan'],
                'unit_kode' => (string)$p['unit_kode'],
                'jabatan' => (string)$p['jabatan'],
                'jumlah_kebutuhan' => (int)$p['jumlah_kebutuhan'],
                'jenis_kelamin' => (string)$p['jenis_kelamin'],
                'usia_min' => (int)$p['usia_min'],
                'usia_max' => (int)$p['usia_max'],
                'pendidikan_minimal' => (string)$p['pendidikan_minimal'],
                'keterampilan_utama' => (string)$p['keterampilan_utama'],
                'pengalaman_min_tahun' => (int)$p['pengalaman_min_tahun'],
                'rentang_gaji' => (string)$p['rentang_gaji'],
                'kode_kbji' => (string)($p['kode_kbji'] ?? ''),
                'provinsi' => (string)($p['provinsi'] ?? ''),
                'kota' => (string)($p['kota'] ?? ''),
                'kecamatan' => (string)($p['kecamatan'] ?? ''),
                'kelurahan' => (string)($p['kelurahan'] ?? ''),
                'bidang_pekerjaan' => (string)($p['bidang_pekerjaan'] ?? ''),
                'industri_sektor' => (string)($p['industri_sektor'] ?? ''),
                'status_pernikahan' => (string)($p['status_pernikahan'] ?? ''),
                'status_verifikasi' => (string)$p['status_verifikasi'],
                'tanggal_lapor' => (string)$p['masa_berlaku_mulai'],
                'masa_berlaku_mulai' => (string)$p['masa_berlaku_mulai'],
                'masa_berlaku_sampai' => (string)$p['masa_berlaku_sampai'],
                'catatan' => (string)$p['catatan'],
                'mode_publikasi' => 'Publik',
                'petugas_input' => '-',
            ]);
        } else {
            $rowsByNoReg[$nr] = array_merge($defaultMeta, [
                'no_reg_bukti' => $nr,
                'id_lowongan' => (string)$p['id_lowongan'],
                'unit_kode' => (string)$p['unit_kode'],
                'jabatan' => (string)$p['jabatan'],
                'jumlah_kebutuhan' => (int)$p['jumlah_kebutuhan'],
                'jenis_kelamin' => (string)$p['jenis_kelamin'],
                'usia_min' => (int)$p['usia_min'],
                'usia_max' => (int)$p['usia_max'],
                'pendidikan_minimal' => (string)$p['pendidikan_minimal'],
                'keterampilan_utama' => (string)$p['keterampilan_utama'],
                'pengalaman_min_tahun' => (int)$p['pengalaman_min_tahun'],
                'rentang_gaji' => (string)$p['rentang_gaji'],
                'kode_kbji' => (string)($p['kode_kbji'] ?? ''),
                'provinsi' => (string)($p['provinsi'] ?? ''),
                'kota' => (string)($p['kota'] ?? ''),
                'kecamatan' => (string)($p['kecamatan'] ?? ''),
                'kelurahan' => (string)($p['kelurahan'] ?? ''),
                'bidang_pekerjaan' => (string)($p['bidang_pekerjaan'] ?? ''),
                'industri_sektor' => (string)($p['industri_sektor'] ?? ''),
                'status_pernikahan' => (string)($p['status_pernikahan'] ?? ''),
                'status_verifikasi' => (string)$p['status_verifikasi'],
                'status_keterisian' => 'Belum Terisi',
                'tanggal_lapor' => (string)$p['masa_berlaku_mulai'],
                'masa_berlaku_mulai' => (string)$p['masa_berlaku_mulai'],
                'masa_berlaku_sampai' => (string)$p['masa_berlaku_sampai'],
                'tanggal_terisi' => null,
                'catatan' => (string)$p['catatan'],
                'mode_publikasi' => 'Publik',
                'petugas_input' => '-',
            ]);
        }
    }
}

$resStatus = $conn->query("SELECT no_reg_bukti, status_saat_ini, tanggal_terisi, unit_nama FROM karirhub_proto_wllp_status");
if ($resStatus) {
    while ($s = $resStatus->fetch_assoc()) {
        $nr = (string)$s['no_reg_bukti'];
        if (isset($rowsByNoReg[$nr])) {
            $rowsByNoReg[$nr]['status_keterisian'] = (string)$s['status_saat_ini'];
            $rowsByNoReg[$nr]['tanggal_terisi'] = (string)$s['tanggal_terisi'];
            if (!empty($s['unit_nama']) && empty($rowsByNoReg[$nr]['unit_kode'])) {
                $rowsByNoReg[$nr]['unit_kode'] = $unitCodeByName[(string)$s['unit_nama']] ?? (string)$s['unit_nama'];
            }
        }
    }
}
$resPenempatan = $conn->query("SELECT * FROM karirhub_proto_wllp_penempatan");
if ($resPenempatan) {
    while ($p = $resPenempatan->fetch_assoc()) {
        $nr = (string)$p['no_reg_bukti'];
        if (!isset($rowsByNoReg[$nr])) {
            continue;
        }
        $rowsByNoReg[$nr] = array_merge($rowsByNoReg[$nr], [
            'nik' => (string)$p['nik'],
            'nama_lengkap' => (string)$p['nama_lengkap'],
            'pendidikan' => (string)$p['pendidikan'],
            'jenis_kelamin' => (string)$p['jenis_kelamin'],
            'tempat_lahir' => (string)$p['tempat_lahir'],
            'tanggal_lahir' => (string)$p['tanggal_lahir'],
            'alamat' => (string)$p['alamat'],
            'status_disabilitas' => (string)$p['status_disabilitas'],
            'tmt' => (string)$p['tmt'],
            'email' => (string)$p['email'],
            'nomor_hp' => (string)$p['nomor_hp'],
        ]);
    }
}

$rows = array_values($rowsByNoReg);
if ($unitFilter !== 'all' && !isset($unitOptions[$unitFilter])) {
    $unitFilter = 'all';
}

$filteredRows = array_values(array_filter($rows, static function (array $row) use ($statusFilter, $unitFilter, $query): bool {
    if ($statusFilter === 'all') {
        $statusMatch = true;
    } else {
        $statusMatch = strtolower($row['status_verifikasi']) === $statusFilter;
    }
    if (!$statusMatch) {
        return false;
    }
    if ($unitFilter !== 'all' && $row['unit_kode'] !== $unitFilter) {
        return false;
    }
    if ($query !== '') {
        $haystack = strtolower(implode(' ', [
            $row['no_reg_bukti'],
            $row['id_lowongan'],
            $row['jabatan'],
            $row['hiring_manager'] ?? '',
            $row['requester_divisi'] ?? '',
            $row['petugas_input'],
            $row['catatan'],
        ]));
        if (strpos($haystack, $query) === false) {
            return false;
        }
    }
    return true;
}));

$baseParams = [
    'status' => $statusFilter,
    'unit' => $unitFilter,
    'q' => $query,
];
$rowMap = [];
foreach ($rows as $row) {
    $rowMap[$row['no_reg_bukti']] = $row;
}

$action = trim((string)($_GET['action'] ?? ''));
$actionNoReg = trim((string)($_GET['no_reg'] ?? ''));
$actionRow = ($actionNoReg !== '' && isset($rowMap[$actionNoReg])) ? $rowMap[$actionNoReg] : null;
$actionError = null;
if ($action !== '' && !in_array($action, ['lihat', 'cetak', 'unduh'], true)) {
    $actionError = 'Aksi tidak dikenali.';
}
if ($action !== '' && $actionRow === null && $actionError === null) {
    $actionError = 'Data bukti lapor tidak ditemukan.';
}

if ($action === 'unduh' && $actionRow !== null) {
    $unitName = $unitOptions[$actionRow['unit_kode']] ?? $actionRow['unit_kode'];
    $pdfBinary = generate_official_bukti_lapor_pdf($actionRow, $unitName);
    $filename = 'bukti-lapor-' . preg_replace('/[^A-Za-z0-9\-]/', '_', $actionRow['no_reg_bukti']) . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdfBinary));
    echo $pdfBinary;
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karirhub Employer Prototype - Bukti Lapor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php kh_proto_render_styles(); ?>
</head>
<body class="kh-proto-page">
<?php include 'navbar.php'; ?>
<?php kh_proto_render_hero('Daftar Lowongan Kerja', 'Kelola bukti lapor dan dokumen WLLP dengan tampilan employer prototype.', 'Lowongan Kerja', 'karirhub_employer_prototype_pelaporan_lowongan', 'Proyek', 'karirhub_employer_prototype_dashboard_wllp'); ?>

<div class="kh-content-wrap">
<div class="container py-4">
    <div class="kh-proto-shell">
    <?php kh_proto_render_sidebar('wllp_bukti_lapor'); ?>
    <main class="kh-proto-main">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-0">Bukti Lapor</h3>
            <div class="text-muted small">Karirhub Employer Prototype (reference only)</div>
        </div>
        <a class="btn btn-outline-primary btn-sm" href="karirhub_employer_prototype_dashboard_wllp">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard WLLP
        </a>
    </div>

    <?php if ($actionError !== null): ?>
        <div class="alert alert-danger py-2"><?php echo h($actionError); ?></div>
    <?php endif; ?>

    <?php if ($action === 'cetak' && $actionRow !== null): ?>
        <div class="alert alert-success py-2">
            Simulasi cetak dijalankan untuk <strong><?php echo h($actionRow['no_reg_bukti']); ?></strong>.
            Jendela print akan otomatis terbuka.
        </div>
    <?php endif; ?>

    <form method="GET" class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label for="status" class="form-label mb-1">Status Bukti</label>
                    <select id="status" name="status" class="form-select form-select-sm">
                        <option value="all"<?php echo $statusFilter === 'all' ? ' selected' : ''; ?>>Semua Status</option>
                        <option value="terverifikasi"<?php echo $statusFilter === 'terverifikasi' ? ' selected' : ''; ?>>Terverifikasi</option>
                        <option value="perlu update"<?php echo $statusFilter === 'perlu update' ? ' selected' : ''; ?>>Perlu Update</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label for="unit" class="form-label mb-1">Unit Perusahaan</label>
                    <select id="unit" name="unit" class="form-select form-select-sm">
                        <option value="all"<?php echo $unitFilter === 'all' ? ' selected' : ''; ?>>Semua Unit</option>
                        <?php foreach ($unitOptions as $unitCode => $unitName): ?>
                            <option value="<?php echo h($unitCode); ?>"<?php echo $unitFilter === $unitCode ? ' selected' : ''; ?>><?php echo h($unitName); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label for="q" class="form-label mb-1">Cari</label>
                    <input id="q" name="q" class="form-control form-control-sm" value="<?php echo h($query); ?>" placeholder="No Reg, ID Lowongan, Jabatan">
                </div>
                <div class="col-12 col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-funnel me-1"></i>Filter
                    </button>
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
                            <th>Tanggal Lapor</th>
                            <th>Jabatan</th>
                            <th>Jumlah</th>
                            <th>Unit/Perusahaan</th>
                            <th>Masa Berlaku</th>
                            <th>Tipe Kerja</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($filteredRows)): ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted">Tidak ada data sesuai filter.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($filteredRows as $row): ?>
                            <?php
                                $badgeClass = karirhub_proto_status_badge_class($row['status_verifikasi']);
                                $urlLihat = '?' . build_query_url(array_merge($baseParams, ['action' => 'lihat', 'no_reg' => $row['no_reg_bukti']]));
                                $urlCetak = '?' . build_query_url(array_merge($baseParams, ['action' => 'cetak', 'no_reg' => $row['no_reg_bukti']]));
                                $urlUnduh = '?' . build_query_url(array_merge($baseParams, ['action' => 'unduh', 'no_reg' => $row['no_reg_bukti']]));
                            ?>
                            <tr>
                                <td class="fw-semibold"><?php echo h($row['no_reg_bukti']); ?></td>
                                <td><?php echo h($row['id_lowongan']); ?></td>
                                <td><?php echo h($row['tanggal_lapor']); ?></td>
                                <td><?php echo h($row['jabatan']); ?></td>
                                <td><?php echo h((string)$row['jumlah_kebutuhan']); ?></td>
                                <td><?php echo h($unitOptions[$row['unit_kode']] ?? $row['unit_kode']); ?></td>
                                <td><?php echo h($row['masa_berlaku_sampai']); ?></td>
                                <td><?php echo h((string)($row['employment_type'] ?? '-')); ?></td>
                                <td><span class="badge text-bg-<?php echo h($badgeClass); ?>"><?php echo h($row['status_verifikasi']); ?></span></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a class="btn btn-outline-primary" href="<?php echo h($urlLihat); ?>">Lihat</a>
                                        <a class="btn btn-outline-secondary" href="<?php echo h($urlCetak); ?>">Cetak</a>
                                        <a class="btn btn-outline-dark" href="<?php echo h($urlUnduh); ?>">Unduh PDF</a>
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

<?php if ($action === 'lihat' && $actionRow !== null): ?>
<?php $urlCetakFromModal = '?' . build_query_url(array_merge($baseParams, ['action' => 'cetak', 'no_reg' => $actionRow['no_reg_bukti']])); ?>
<div class="modal fade show" id="detailModal" tabindex="-1" aria-modal="true" role="dialog" style="display:block; background: rgba(0,0,0,0.35);">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Bukti Lapor - <?php echo h($actionRow['no_reg_bukti']); ?></h5>
                <a href="?<?php echo h(build_query_url($baseParams)); ?>" class="btn-close"></a>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><strong>Approval State:</strong><br><?php echo h((string)($actionRow['approval_state'] ?? '-')); ?></div>
                    <div class="col-md-6"><strong>Approval By:</strong><br><?php echo h((string)($actionRow['approval_by'] ?? '-')); ?></div>
                    <div class="col-md-6"><strong>Approval Date:</strong><br><?php echo h((string)($actionRow['approval_date'] ?? '-')); ?></div>
                    <div class="col-md-6"><strong>ID Lowongan:</strong><br><?php echo h($actionRow['id_lowongan']); ?></div>
                    <div class="col-md-6"><strong>Jabatan:</strong><br><?php echo h($actionRow['jabatan']); ?></div>
                    <div class="col-md-6"><strong>Tanggal Lapor:</strong><br><?php echo h($actionRow['tanggal_lapor']); ?></div>
                    <div class="col-md-6"><strong>Jumlah Kebutuhan:</strong><br><?php echo h((string)$actionRow['jumlah_kebutuhan']); ?></div>
                    <div class="col-md-6"><strong>Unit/Perusahaan:</strong><br><?php echo h($unitOptions[$actionRow['unit_kode']] ?? $actionRow['unit_kode']); ?></div>
                    <div class="col-md-6"><strong>Mode Publikasi:</strong><br><?php echo h($actionRow['mode_publikasi']); ?></div>
                    <div class="col-md-6"><strong>Masa Berlaku:</strong><br><?php echo h($actionRow['masa_berlaku_mulai']); ?> s.d. <?php echo h($actionRow['masa_berlaku_sampai']); ?></div>
                    <div class="col-md-6"><strong>SLA Hiring:</strong><br><?php echo h((string)($actionRow['sla_hiring_hari'] ?? 0)); ?> hari</div>
                    <div class="col-md-6"><strong>Employment Type:</strong><br><?php echo h((string)($actionRow['employment_type'] ?? '-')); ?></div>
                    <div class="col-md-6"><strong>Work Setup:</strong><br><?php echo h((string)($actionRow['work_setup'] ?? '-')); ?></div>
                    <div class="col-md-6"><strong>Shift Type:</strong><br><?php echo h((string)($actionRow['shift_type'] ?? '-')); ?></div>
                    <div class="col-md-6"><strong>Lokasi Penempatan:</strong><br><?php echo h((string)($actionRow['lokasi_penempatan_detail'] ?? '-')); ?></div>
                    <div class="col-md-6"><strong>Requester Divisi:</strong><br><?php echo h((string)($actionRow['requester_divisi'] ?? '-')); ?></div>
                    <div class="col-md-6"><strong>Sumber Rekrutmen:</strong><br><?php echo h((string)($actionRow['sumber_rekrutmen'] ?? '-')); ?></div>
                    <div class="col-md-6"><strong>Kode KBJI:</strong><br><?php echo h((string)($actionRow['kode_kbji'] ?? '-')); ?></div>
                    <div class="col-md-6"><strong>Provinsi:</strong><br><?php echo h((string)($actionRow['provinsi'] ?? '-')); ?></div>
                    <div class="col-md-6"><strong>Kota:</strong><br><?php echo h((string)($actionRow['kota'] ?? '-')); ?></div>
                    <div class="col-md-6"><strong>Kecamatan:</strong><br><?php echo h((string)($actionRow['kecamatan'] ?? '-')); ?></div>
                    <div class="col-md-6"><strong>Kelurahan:</strong><br><?php echo h((string)($actionRow['kelurahan'] ?? '-')); ?></div>
                    <div class="col-md-6"><strong>Bidang Pekerjaan:</strong><br><?php echo h((string)($actionRow['bidang_pekerjaan'] ?? '-')); ?></div>
                    <div class="col-md-6"><strong>Industri / Sektor:</strong><br><?php echo h((string)($actionRow['industri_sektor'] ?? '-')); ?></div>
                    <div class="col-md-6"><strong>Status Pernikahan:</strong><br><?php echo h((string)($actionRow['status_pernikahan'] ?? '-')); ?></div>
                    <div class="col-md-6"><strong>Status Verifikasi:</strong><br><?php echo h($actionRow['status_verifikasi']); ?></div>
                    <div class="col-md-6"><strong>Status Keterisian:</strong><br><?php echo h($actionRow['status_keterisian']); ?></div>
                    <div class="col-12"><strong>Keterampilan Utama:</strong><br><?php echo h($actionRow['keterampilan_utama']); ?></div>
                    <div class="col-12"><strong>Catatan:</strong><br><?php echo h($actionRow['catatan']); ?></div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="?<?php echo h(build_query_url($baseParams)); ?>" class="btn btn-outline-secondary btn-sm">Tutup</a>
                <a href="<?php echo h($urlCetakFromModal); ?>" class="btn btn-primary btn-sm">Cetak</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php kh_proto_render_sidebar_script(); ?>
<?php if ($action === 'cetak' && $actionRow !== null): ?>
<script>
    (function () {
        const printWindow = window.open('', '_blank', 'width=900,height=700');
        if (!printWindow) return;
        const html = `
            <html>
            <head>
                <title>Bukti Lapor ${<?php echo json_encode($actionRow['no_reg_bukti']); ?>}</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 24px; }
                    h2 { margin-bottom: 6px; }
                    table { border-collapse: collapse; width: 100%; margin-top: 16px; }
                    td, th { border: 1px solid #ccc; padding: 8px; font-size: 13px; vertical-align: top; }
                    th { width: 220px; text-align: left; background: #f5f5f5; }
                    .muted { color: #666; font-size: 12px; margin-top: 8px; }
                </style>
            </head>
            <body>
                <h2>Bukti Lapor WLLP (Prototype)</h2>
                <div>No. Reg Bukti: <strong>${<?php echo json_encode($actionRow['no_reg_bukti']); ?>}</strong></div>
                <div class="muted">Dokumen simulasi dari prototype Karirhub Employer.</div>
                <table>
                    <tr><th>ID Lowongan</th><td>${<?php echo json_encode($actionRow['id_lowongan']); ?>}</td></tr>
                    <tr><th>Jabatan</th><td>${<?php echo json_encode($actionRow['jabatan']); ?>}</td></tr>
                    <tr><th>Tanggal Lapor</th><td>${<?php echo json_encode($actionRow['tanggal_lapor']); ?>}</td></tr>
                    <tr><th>Jumlah Kebutuhan</th><td>${<?php echo json_encode((string)$actionRow['jumlah_kebutuhan']); ?>}</td></tr>
                    <tr><th>Unit/Perusahaan</th><td>${<?php echo json_encode($unitOptions[$actionRow['unit_kode']] ?? $actionRow['unit_kode']); ?>}</td></tr>
                    <tr><th>Masa Berlaku</th><td>${<?php echo json_encode($actionRow['masa_berlaku_mulai'] . ' s.d. ' . $actionRow['masa_berlaku_sampai']); ?>}</td></tr>
                    <tr><th>Employment Type</th><td>${<?php echo json_encode((string)($actionRow['employment_type'] ?? '-')); ?>}</td></tr>
                    <tr><th>Work Setup</th><td>${<?php echo json_encode((string)($actionRow['work_setup'] ?? '-')); ?>}</td></tr>
                    <tr><th>Shift Type</th><td>${<?php echo json_encode((string)($actionRow['shift_type'] ?? '-')); ?>}</td></tr>
                    <tr><th>Kode KBJI</th><td>${<?php echo json_encode((string)($actionRow['kode_kbji'] ?? '-')); ?>}</td></tr>
                    <tr><th>Provinsi</th><td>${<?php echo json_encode((string)($actionRow['provinsi'] ?? '-')); ?>}</td></tr>
                    <tr><th>Kota</th><td>${<?php echo json_encode((string)($actionRow['kota'] ?? '-')); ?>}</td></tr>
                    <tr><th>Kecamatan</th><td>${<?php echo json_encode((string)($actionRow['kecamatan'] ?? '-')); ?>}</td></tr>
                    <tr><th>Kelurahan</th><td>${<?php echo json_encode((string)($actionRow['kelurahan'] ?? '-')); ?>}</td></tr>
                    <tr><th>Bidang Pekerjaan</th><td>${<?php echo json_encode((string)($actionRow['bidang_pekerjaan'] ?? '-')); ?>}</td></tr>
                    <tr><th>Industri / Sektor</th><td>${<?php echo json_encode((string)($actionRow['industri_sektor'] ?? '-')); ?>}</td></tr>
                    <tr><th>Status Pernikahan</th><td>${<?php echo json_encode((string)($actionRow['status_pernikahan'] ?? '-')); ?>}</td></tr>
                    <tr><th>Requester Divisi</th><td>${<?php echo json_encode((string)($actionRow['requester_divisi'] ?? '-')); ?>}</td></tr>
                    <tr><th>SLA Hiring (hari)</th><td>${<?php echo json_encode((string)($actionRow['sla_hiring_hari'] ?? 0)); ?>}</td></tr>
                    <tr><th>Status Verifikasi</th><td>${<?php echo json_encode($actionRow['status_verifikasi']); ?>}</td></tr>
                    <tr><th>Status Keterisian</th><td>${<?php echo json_encode($actionRow['status_keterisian']); ?>}</td></tr>
                    <tr><th>Catatan</th><td>${<?php echo json_encode($actionRow['catatan']); ?>}</td></tr>
                </table>
            </body>
            </html>
        `;
        printWindow.document.open();
        printWindow.document.write(html);
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
    })();
</script>
<?php endif; ?>
</body>
</html>
