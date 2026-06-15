<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_data.php';
require_once __DIR__ . '/karirhub_employer_prototype_storage.php';
require_once __DIR__ . '/karirhub_employer_prototype_ui.php';
require_once __DIR__ . '/db.php';

if (!kh_proto_can_access('karirhub_employer_prototype_bukti_lapor_view')) {
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

function pdf_logo_jpeg_data(string $logoPath, array $backgroundRgb = [255, 255, 255]): ?array
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

    // Render PNG on a solid background so transparent parts stay readable in PDF.
    $canvas = imagecreatetruecolor($width, $height);
    if ($canvas === false) {
        imagedestroy($png);
        return null;
    }
    $bgR = max(0, min(255, (int)($backgroundRgb[0] ?? 255)));
    $bgG = max(0, min(255, (int)($backgroundRgb[1] ?? 255)));
    $bgB = max(0, min(255, (int)($backgroundRgb[2] ?? 255)));
    $bgColor = imagecolorallocate($canvas, $bgR, $bgG, $bgB);
    imagefilledrectangle($canvas, 0, 0, $width, $height, $bgColor);
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
        ['Periode Pelaporan', strtoupper((string)($row['periode_tipe'] ?? '-')) . ' (' . (string)($row['periode_mulai'] ?? '-') . ' s.d. ' . (string)($row['periode_selesai'] ?? '-') . ')'],
        ['Total ID Lowongan', (string)($row['total_lowongan'] ?? 1)],
        ['Daftar ID Lowongan', (string)($row['daftar_id_lowongan'] ?? (string)($row['id_lowongan'] ?? '-'))],
        ['Jabatan', (string)($row['daftar_jabatan'] ?? (string)($row['jabatan'] ?? '-'))],
        ['Jumlah Kebutuhan', (string)($row['jumlah_kebutuhan'] ?? 0)],
        ['Jumlah Penempatan', (string)($row['jumlah_penempatan'] ?? 0)],
        ['Unit/Perusahaan', $unitName],
        ['Masa Berlaku', (string)($row['masa_berlaku_mulai'] ?? '-') . ' s.d. ' . (string)($row['masa_berlaku_sampai'] ?? '-')],
        ['Tipe Kerja', (string)($row['tipe_kerja'] ?? '-')],
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
    ];

    $streamParts = [];
    $left = 40;
    $right = 555;
    $width = $right - $left;
    $headerY = 775;
    $headerH = 58;
    $footerLogoPath = __DIR__ . '/images/karirhub.png';
    $footerLogoInfo = pdf_logo_jpeg_data($footerLogoPath);
    $siapKerjaLogoPath = __DIR__ . '/images/logo-siapkerja.png';
    $siapKerjaLogoInfo = pdf_logo_jpeg_data($siapKerjaLogoPath);
    $headerLogoPath = __DIR__ . '/images/logo-white.png';
    $headerLogoInfo = pdf_logo_jpeg_data($headerLogoPath, [20, 74, 140]);

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
    $headerTextX = 52.0;
    if ($headerLogoInfo !== null) {
        $headerLogoTargetH = 38.0;
        $headerLogoTargetW = ($headerLogoInfo['width'] / max(1, $headerLogoInfo['height'])) * $headerLogoTargetH;
        if ($headerLogoTargetW > 170.0) {
            $headerLogoTargetW = 170.0;
            $headerLogoTargetH = ($headerLogoInfo['height'] / max(1, $headerLogoInfo['width'])) * $headerLogoTargetW;
        }
        $headerLogoX = 48.0;
        $headerLogoY = $headerY + (($headerH - $headerLogoTargetH) / 2.0);
        $streamParts[] = 'q';
        $streamParts[] = sprintf('%.2F 0 0 %.2F %.2F %.2F cm', $headerLogoTargetW, $headerLogoTargetH, $headerLogoX, $headerLogoY);
        $streamParts[] = '/Im2 Do';
        $streamParts[] = 'Q';
        $headerTextX = $headerLogoX + $headerLogoTargetW + 10.0;
    }
    $streamParts[] = '1 1 1 rg';
    $streamParts[] = 'BT /F2 13 Tf ' . sprintf('%.2F', $headerTextX) . ' 813 Td (' . pdf_escape('KEMENTERIAN KETENAGAKERJAAN REPUBLIK INDONESIA') . ') Tj ET';
    $streamParts[] = 'BT /F1 7 Tf ' . sprintf('%.2F', $headerTextX) . ' 800 Td (' . pdf_escape('Jl. Gatot Subroto No.Kav 51, RT.5/RW.4, Kuningan Tim., Kecamatan Setiabudi,') . ') Tj ET';
    $streamParts[] = 'BT /F1 7 Tf ' . sprintf('%.2F', $headerTextX) . ' 791 Td (' . pdf_escape('Kota Jakarta Selatan, Daerah Khusus Ibukota Jakarta 12950') . ') Tj ET';

    // Main title below the blue header band.
    $streamParts[] = '0.08 0.2 0.4 rg';
    $streamParts[] = 'BT /F2 12 Tf 148 752 Td (' . pdf_escape('BUKTI LAPOR LOWONGAN PEKERJAAN (WLLP)') . ') Tj ET';

    // Section title.
    $sectionTop = 712;
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

    // Footer logos: SiapKerja then Karirhub.
    if ($siapKerjaLogoInfo !== null || $footerLogoInfo !== null) {
        $logoGap = 8.0;
        $logoBaseY = $footerY + 2.0;
        $totalWidth = 0.0;
        $siapKerjaTargetW = 0.0;
        $siapKerjaTargetH = 0.0;
        $karirhubTargetW = 0.0;
        $karirhubTargetH = 0.0;

        if ($siapKerjaLogoInfo !== null) {
            $siapKerjaTargetH = 28.0;
            $siapKerjaTargetW = ($siapKerjaLogoInfo['width'] / max(1, $siapKerjaLogoInfo['height'])) * $siapKerjaTargetH;
            if ($siapKerjaTargetW > 145.0) {
                $siapKerjaTargetW = 145.0;
                $siapKerjaTargetH = ($siapKerjaLogoInfo['height'] / max(1, $siapKerjaLogoInfo['width'])) * $siapKerjaTargetW;
            }
            $totalWidth += $siapKerjaTargetW;
        }
        if ($footerLogoInfo !== null) {
            $karirhubTargetH = 28.0;
            $karirhubTargetW = ($footerLogoInfo['width'] / max(1, $footerLogoInfo['height'])) * $karirhubTargetH;
            if ($karirhubTargetW > 130.0) {
                $karirhubTargetW = 130.0;
                $karirhubTargetH = ($footerLogoInfo['height'] / max(1, $footerLogoInfo['width'])) * $karirhubTargetW;
            }
            if ($totalWidth > 0.0) {
                $totalWidth += $logoGap;
            }
            $totalWidth += $karirhubTargetW;
        }

        $startX = $right - $totalWidth - 6.0;
        $cursorX = $startX;
        if ($siapKerjaLogoInfo !== null) {
            $streamParts[] = 'q';
            $streamParts[] = sprintf('%.2F 0 0 %.2F %.2F %.2F cm', $siapKerjaTargetW, $siapKerjaTargetH, $cursorX, $logoBaseY);
            $streamParts[] = '/Im3 Do';
            $streamParts[] = 'Q';
            $cursorX += $siapKerjaTargetW + $logoGap;
        }
        if ($footerLogoInfo !== null) {
            $streamParts[] = 'q';
            $streamParts[] = sprintf('%.2F 0 0 %.2F %.2F %.2F cm', $karirhubTargetW, $karirhubTargetH, $cursorX, $logoBaseY);
            $streamParts[] = '/Im1 Do';
            $streamParts[] = 'Q';
        }
    }

    $contentStream = implode("\n", $streamParts) . "\n";

    $objects = [];
    $objects[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $objects[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    $footerLogoObjectNumber = null;
    $headerLogoObjectNumber = null;
    $siapKerjaLogoObjectNumber = null;
    $nextObjectNumber = 7;
    if ($footerLogoInfo !== null) {
        $footerLogoObjectNumber = $nextObjectNumber;
        $nextObjectNumber++;
    }
    if ($headerLogoInfo !== null) {
        $headerLogoObjectNumber = $nextObjectNumber;
        $nextObjectNumber++;
    }
    if ($siapKerjaLogoInfo !== null) {
        $siapKerjaLogoObjectNumber = $nextObjectNumber;
        $nextObjectNumber++;
    }
    $resources = '/Font << /F1 5 0 R /F2 6 0 R >>';
    if ($footerLogoObjectNumber !== null || $headerLogoObjectNumber !== null || $siapKerjaLogoObjectNumber !== null) {
        $resources .= ' /XObject <<';
        if ($footerLogoObjectNumber !== null) {
            $resources .= ' /Im1 ' . $footerLogoObjectNumber . ' 0 R';
        }
        if ($headerLogoObjectNumber !== null) {
            $resources .= ' /Im2 ' . $headerLogoObjectNumber . ' 0 R';
        }
        if ($siapKerjaLogoObjectNumber !== null) {
            $resources .= ' /Im3 ' . $siapKerjaLogoObjectNumber . ' 0 R';
        }
        $resources .= ' >>';
    }
    $objects[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << " . $resources . " >> /Contents 4 0 R >>\nendobj\n";
    $objects[] = "4 0 obj\n<< /Length " . strlen($contentStream) . " >>\nstream\n" . $contentStream . "endstream\nendobj\n";
    $objects[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    $objects[] = "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n";
    if ($footerLogoInfo !== null && $footerLogoObjectNumber !== null) {
        $jpegData = $footerLogoInfo['data'];
        $objects[] = $footerLogoObjectNumber . " 0 obj\n<< /Type /XObject /Subtype /Image /Width " . (int)$footerLogoInfo['width'] . " /Height " . (int)$footerLogoInfo['height'] . " /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpegData) . " >>\nstream\n" . $jpegData . "\nendstream\nendobj\n";
    }
    if ($headerLogoInfo !== null && $headerLogoObjectNumber !== null) {
        $jpegData = $headerLogoInfo['data'];
        $objects[] = $headerLogoObjectNumber . " 0 obj\n<< /Type /XObject /Subtype /Image /Width " . (int)$headerLogoInfo['width'] . " /Height " . (int)$headerLogoInfo['height'] . " /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpegData) . " >>\nstream\n" . $jpegData . "\nendstream\nendobj\n";
    }
    if ($siapKerjaLogoInfo !== null && $siapKerjaLogoObjectNumber !== null) {
        $jpegData = $siapKerjaLogoInfo['data'];
        $objects[] = $siapKerjaLogoObjectNumber . " 0 obj\n<< /Type /XObject /Subtype /Image /Width " . (int)$siapKerjaLogoInfo['width'] . " /Height " . (int)$siapKerjaLogoInfo['height'] . " /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpegData) . " >>\nstream\n" . $jpegData . "\nendstream\nendobj\n";
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
$allowedStatuses = ['all', 'belum terisi', 'proses seleksi', 'terisi'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}

$unitFilter = trim((string)($_GET['unit'] ?? 'all'));
$query = strtolower(trim((string)($_GET['q'] ?? '')));

$dataset = karirhub_proto_dataset();
$units = $dataset['units'];
kh_proto_ensure_multi_tables($conn);
kh_proto_seed_multi_from_dataset($conn, $dataset, $units);

$unitOptions = [];
foreach ($units as $unitCode => $unitInfo) {
    $unitOptions[$unitCode] = $unitInfo['nama'];
}

$headerRows = [];
$detailByNoReg = [];
$resHeaders = $conn->query("
    SELECT
        h.no_reg_bukti,
        h.unit_kode,
        h.unit_nama,
        h.periode_tipe,
        CAST(h.periode_mulai AS CHAR) AS periode_mulai,
        CAST(h.periode_selesai AS CHAR) AS periode_selesai,
        h.status_verifikasi,
        h.catatan,
        SUM(d.jumlah_kebutuhan) AS jumlah_kebutuhan_total,
        COUNT(d.id_lowongan) AS total_lowongan,
        GROUP_CONCAT(d.id_lowongan ORDER BY d.id_lowongan SEPARATOR ', ') AS daftar_id_lowongan,
        GROUP_CONCAT(DISTINCT d.jabatan ORDER BY d.jabatan SEPARATOR ', ') AS daftar_jabatan
    FROM karirhub_proto_wllp_laporan h
    JOIN karirhub_proto_wllp_pelaporan d ON d.no_reg_bukti = h.no_reg_bukti
    GROUP BY h.no_reg_bukti, h.unit_kode, h.unit_nama, h.periode_tipe, h.periode_mulai, h.periode_selesai, h.status_verifikasi, h.catatan
    ORDER BY h.created_at DESC, h.no_reg_bukti DESC
");
if ($resHeaders) {
    while ($r = $resHeaders->fetch_assoc()) {
        $headerRows[] = $r;
    }
}

$resDetails = $conn->query("
    SELECT
        d.*,
        COALESCE(s.status_saat_ini, 'Belum Terisi') AS status_keterisian,
        COALESCE(CAST(s.tanggal_terisi AS CHAR), '') AS tanggal_terisi,
        COALESCE(p.nik, '') AS nik,
        COALESCE(p.nama_lengkap, '') AS nama_lengkap,
        COALESCE(p.pendidikan, '') AS pendidikan,
        COALESCE(p.jenis_kelamin, '') AS jenis_kelamin_penempatan,
        COALESCE(p.tempat_lahir, '') AS tempat_lahir,
        COALESCE(CAST(p.tanggal_lahir AS CHAR), '') AS tanggal_lahir,
        COALESCE(p.alamat, '') AS alamat,
        COALESCE(p.status_disabilitas, '') AS status_disabilitas,
        COALESCE(CAST(p.tmt AS CHAR), '') AS tmt,
        COALESCE(p.email, '') AS email,
        COALESCE(p.nomor_hp, '') AS nomor_hp
    FROM karirhub_proto_wllp_pelaporan d
    LEFT JOIN karirhub_proto_wllp_status s ON s.no_reg_bukti = d.no_reg_bukti AND s.id_lowongan = d.id_lowongan
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
    ) p ON p.no_reg_bukti = d.no_reg_bukti AND p.id_lowongan = d.id_lowongan
    ORDER BY d.no_reg_bukti DESC, d.id_lowongan ASC
");
if ($resDetails) {
    while ($r = $resDetails->fetch_assoc()) {
        $nr = (string)$r['no_reg_bukti'];
        if (!isset($detailByNoReg[$nr])) {
            $detailByNoReg[$nr] = [];
        }
        $detailByNoReg[$nr][] = $r;
    }
}

if ($unitFilter !== 'all' && !isset($unitOptions[$unitFilter])) {
    $unitFilter = 'all';
}

$rows = array_values(array_filter($headerRows, static function (array $row) use ($statusFilter, $unitFilter, $query, $detailByNoReg): bool {
    if ($statusFilter !== 'all') {
        $noReg = (string)($row['no_reg_bukti'] ?? '');
        $detailRows = $detailByNoReg[$noReg] ?? [];
        $hasMatchingStatus = false;
        foreach ($detailRows as $detailRow) {
            if (strtolower(trim((string)($detailRow['status_keterisian'] ?? ''))) === $statusFilter) {
                $hasMatchingStatus = true;
                break;
            }
        }
        if (!$hasMatchingStatus) {
            return false;
        }
    }
    if ($unitFilter !== 'all' && (string)$row['unit_kode'] !== $unitFilter) {
        return false;
    }
    if ($query !== '') {
        $haystack = strtolower(implode(' ', [
            (string)$row['no_reg_bukti'],
            (string)$row['daftar_id_lowongan'],
            (string)$row['daftar_jabatan'],
            (string)$row['catatan'],
            (string)$row['periode_tipe'],
        ]));
        if (strpos($haystack, $query) === false) {
            return false;
        }
    }
    return true;
}));
$filteredRows = $rows;

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
$actionDetailRows = [];
if ($actionRow !== null) {
    $actionDetailRows = $detailByNoReg[$actionRow['no_reg_bukti']] ?? [];
    $firstDetail = $actionDetailRows[0] ?? [];
    $jumlahPenempatan = 0;
    foreach ($actionDetailRows as $item) {
        if (strtolower(trim((string)($item['status_keterisian'] ?? ''))) === 'terisi') {
            $jumlahPenempatan++;
        }
    }
    $actionRow = array_merge($firstDetail, $actionRow, [
        'jumlah_kebutuhan' => (int)($actionRow['jumlah_kebutuhan_total'] ?? 0),
        'jumlah_penempatan' => $jumlahPenempatan,
        'status_keterisian' => $jumlahPenempatan > 0 ? 'Terisi Sebagian' : 'Belum Terisi',
        'tanggal_lapor' => (string)($firstDetail['masa_berlaku_mulai'] ?? ($actionRow['periode_mulai'] ?? '')),
        'masa_berlaku_mulai' => (string)($actionRow['periode_mulai'] ?? ''),
        'masa_berlaku_sampai' => (string)($actionRow['periode_selesai'] ?? ''),
        'id_lowongan' => (string)($actionRow['daftar_id_lowongan'] ?? ''),
        'jabatan' => (string)($actionRow['daftar_jabatan'] ?? ''),
    ]);
}
$actionError = null;
if ($action !== '' && !in_array($action, ['lihat', 'cetak', 'unduh'], true)) {
    $actionError = 'Aksi tidak dikenali.';
}
if ($action !== '' && $actionRow === null && $actionError === null) {
    $actionError = 'Data bukti lapor tidak ditemukan.';
}

if ($action === 'unduh' && $actionRow !== null) {
    $unitName = $unitOptions[$actionRow['unit_kode']] ?? ($actionRow['unit_nama'] ?? $actionRow['unit_kode']);
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
                        <option value="belum terisi"<?php echo $statusFilter === 'belum terisi' ? ' selected' : ''; ?>>Belum Terisi</option>
                        <option value="proses seleksi"<?php echo $statusFilter === 'proses seleksi' ? ' selected' : ''; ?>>Proses Seleksi</option>
                        <option value="terisi"<?php echo $statusFilter === 'terisi' ? ' selected' : ''; ?>>Terisi</option>
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
                    <input id="q" name="q" class="form-control form-control-sm" value="<?php echo h($query); ?>" placeholder="No Reg, daftar ID Lowongan, Jabatan">
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
                            <th>Periode</th>
                            <th>Total ID Lowongan</th>
                            <th>Daftar ID</th>
                            <th>Jabatan</th>
                            <th>Total Kebutuhan</th>
                            <th>Unit/Perusahaan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($filteredRows)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">Tidak ada data sesuai filter.</td>
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
                                <td class="small"><?php echo h(strtoupper((string)$row['periode_tipe']) . ' (' . (string)$row['periode_mulai'] . ' s.d. ' . (string)$row['periode_selesai'] . ')'); ?></td>
                                <td><?php echo h((string)$row['total_lowongan']); ?></td>
                                <td class="small"><?php echo h((string)$row['daftar_id_lowongan']); ?></td>
                                <td><?php echo h((string)$row['daftar_jabatan']); ?></td>
                                <td><?php echo h((string)$row['jumlah_kebutuhan_total']); ?></td>
                                <td><?php echo h($unitOptions[$row['unit_kode']] ?? $row['unit_nama']); ?></td>
                                <td><span class="badge text-bg-<?php echo h($badgeClass); ?>"><?php echo h($row['status_verifikasi']); ?></span></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a class="btn btn-outline-primary" href="<?php echo h($urlLihat); ?>">Lihat Detail</a>
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
                    <div class="col-md-6"><strong>Total ID Lowongan:</strong><br><?php echo h((string)($actionRow['total_lowongan'] ?? 0)); ?></div>
                    <div class="col-md-6"><strong>Daftar ID Lowongan:</strong><br><?php echo h((string)($actionRow['daftar_id_lowongan'] ?? '-')); ?></div>
                    <div class="col-md-6"><strong>Jabatan:</strong><br><?php echo h((string)($actionRow['daftar_jabatan'] ?? $actionRow['jabatan'])); ?></div>
                    <div class="col-md-6"><strong>Tanggal Lapor:</strong><br><?php echo h($actionRow['tanggal_lapor']); ?></div>
                    <div class="col-md-6"><strong>Jumlah Kebutuhan:</strong><br><?php echo h((string)$actionRow['jumlah_kebutuhan']); ?></div>
                    <div class="col-md-6"><strong>Jumlah Penempatan:</strong><br><?php echo h((string)($actionRow['jumlah_penempatan'] ?? 0)); ?></div>
                    <div class="col-md-6"><strong>Unit/Perusahaan:</strong><br><?php echo h($unitOptions[$actionRow['unit_kode']] ?? ($actionRow['unit_nama'] ?? $actionRow['unit_kode'])); ?></div>
                    <div class="col-md-6"><strong>Periode Pelaporan:</strong><br><?php echo h(strtoupper((string)$actionRow['periode_tipe']) . ' (' . (string)$actionRow['periode_mulai'] . ' s.d. ' . (string)$actionRow['periode_selesai'] . ')'); ?></div>
                    <div class="col-md-6"><strong>Masa Berlaku:</strong><br><?php echo h($actionRow['masa_berlaku_mulai']); ?> s.d. <?php echo h($actionRow['masa_berlaku_sampai']); ?></div>
                    <div class="col-md-6"><strong>Kode KBJI:</strong><br><?php echo h((string)($actionRow['kode_kbji'] ?? '-')); ?></div>
                    <div class="col-md-6"><strong>Provinsi:</strong><br><?php echo h((string)($actionRow['provinsi'] ?? '-')); ?></div>
                    <div class="col-md-6"><strong>Kota:</strong><br><?php echo h((string)($actionRow['kota'] ?? '-')); ?></div>
                    <div class="col-md-6"><strong>Kecamatan:</strong><br><?php echo h((string)($actionRow['kecamatan'] ?? '-')); ?></div>
                    <div class="col-md-6"><strong>Kelurahan:</strong><br><?php echo h((string)($actionRow['kelurahan'] ?? '-')); ?></div>
                    <div class="col-md-6"><strong>Bidang Pekerjaan:</strong><br><?php echo h((string)($actionRow['bidang_pekerjaan'] ?? '-')); ?></div>
                    <div class="col-md-6"><strong>Industri / Sektor:</strong><br><?php echo h((string)($actionRow['industri_sektor'] ?? '-')); ?></div>
                    <div class="col-md-6"><strong>Status Keterisian:</strong><br><?php echo h($actionRow['status_keterisian']); ?></div>
                    <?php if (!empty($actionDetailRows)): ?>
                        <div class="col-12">
                            <strong>Rincian ID Lowongan:</strong>
                            <div class="table-responsive mt-2">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="table-light">
                                    <tr>
                                        <th>ID Lowongan</th>
                                        <th>Jabatan</th>
                                        <th>Jumlah Kebutuhan</th>
                                        <th>Status Keterisian</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($actionDetailRows as $item): ?>
                                        <tr>
                                            <td><?php echo h((string)$item['id_lowongan']); ?></td>
                                            <td><?php echo h((string)$item['jabatan']); ?></td>
                                            <td><?php echo h((string)$item['jumlah_kebutuhan']); ?></td>
                                            <td><?php echo h((string)$item['status_keterisian']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
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
                    <tr><th>Total ID Lowongan</th><td>${<?php echo json_encode((string)($actionRow['total_lowongan'] ?? 0)); ?>}</td></tr>
                    <tr><th>Daftar ID Lowongan</th><td>${<?php echo json_encode((string)($actionRow['daftar_id_lowongan'] ?? '-')); ?>}</td></tr>
                    <tr><th>Jabatan</th><td>${<?php echo json_encode((string)($actionRow['daftar_jabatan'] ?? $actionRow['jabatan'])); ?>}</td></tr>
                    <tr><th>Tanggal Lapor</th><td>${<?php echo json_encode($actionRow['tanggal_lapor']); ?>}</td></tr>
                    <tr><th>Jumlah Kebutuhan</th><td>${<?php echo json_encode((string)$actionRow['jumlah_kebutuhan']); ?>}</td></tr>
                    <tr><th>Jumlah Penempatan</th><td>${<?php echo json_encode((string)($actionRow['jumlah_penempatan'] ?? 0)); ?>}</td></tr>
                    <tr><th>Unit/Perusahaan</th><td>${<?php echo json_encode($unitOptions[$actionRow['unit_kode']] ?? ($actionRow['unit_nama'] ?? $actionRow['unit_kode'])); ?>}</td></tr>
                    <tr><th>Periode Pelaporan</th><td>${<?php echo json_encode(strtoupper((string)$actionRow['periode_tipe']) . ' (' . (string)$actionRow['periode_mulai'] . ' s.d. ' . (string)$actionRow['periode_selesai'] . ')'); ?>}</td></tr>
                    <tr><th>Masa Berlaku</th><td>${<?php echo json_encode($actionRow['masa_berlaku_mulai'] . ' s.d. ' . $actionRow['masa_berlaku_sampai']); ?>}</td></tr>
                    <tr><th>Kode KBJI</th><td>${<?php echo json_encode((string)($actionRow['kode_kbji'] ?? '-')); ?>}</td></tr>
                    <tr><th>Provinsi</th><td>${<?php echo json_encode((string)($actionRow['provinsi'] ?? '-')); ?>}</td></tr>
                    <tr><th>Kota</th><td>${<?php echo json_encode((string)($actionRow['kota'] ?? '-')); ?>}</td></tr>
                    <tr><th>Kecamatan</th><td>${<?php echo json_encode((string)($actionRow['kecamatan'] ?? '-')); ?>}</td></tr>
                    <tr><th>Kelurahan</th><td>${<?php echo json_encode((string)($actionRow['kelurahan'] ?? '-')); ?>}</td></tr>
                    <tr><th>Bidang Pekerjaan</th><td>${<?php echo json_encode((string)($actionRow['bidang_pekerjaan'] ?? '-')); ?>}</td></tr>
                    <tr><th>Industri / Sektor</th><td>${<?php echo json_encode((string)($actionRow['industri_sektor'] ?? '-')); ?>}</td></tr>
                    <tr><th>Status Pernikahan</th><td>${<?php echo json_encode((string)($actionRow['status_pernikahan'] ?? '-')); ?>}</td></tr>
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
