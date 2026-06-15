<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_data.php';
require_once __DIR__ . '/karirhub_employer_prototype_storage.php';
require_once __DIR__ . '/karirhub_employer_prototype_ui.php';
require_once __DIR__ . '/db.php';

if (!kh_proto_can_access('karirhub_employer_prototype_pelaporan_lowongan_view')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function kh_proto_generate_no_reg_bukti(mysqli $conn, string $anchorDate): string
{
    return kh_proto_generate_no_reg_from_anchor($conn, $anchorDate);
}

function kh_proto_parse_platform_kanal(string $raw): array
{
    $parts = preg_split('/\s*,\s*/', $raw) ?: [];
    $allowed = ['Job Portal', 'Social Media', 'Lainnya'];
    $result = [];
    foreach ($parts as $part) {
        $value = trim((string)$part);
        if ($value === '' || !in_array($value, $allowed, true) || in_array($value, $result, true)) {
            continue;
        }
        $result[] = $value;
    }
    return $result;
}

function kh_proto_parse_posting_rows(string $raw): array
{
    $rows = [];
    $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
    foreach ($lines as $line) {
        $line = trim((string)$line);
        if ($line === '') {
            continue;
        }
        if (str_contains($line, '||')) {
            $parts = array_map('trim', explode('||', $line, 3));
            $rows[] = [
                'channel' => (string)($parts[0] ?? ''),
                'source' => (string)($parts[1] ?? ''),
                'url' => (string)($parts[2] ?? ''),
            ];
            continue;
        }
        $rows[] = [
            'channel' => '',
            'source' => '',
            'url' => $line,
        ];
    }
    return $rows;
}

function kh_proto_parse_csv_values(string $raw, array $allowed = []): array
{
    $parts = preg_split('/\s*,\s*/', $raw) ?: [];
    $result = [];
    foreach ($parts as $part) {
        $value = trim((string)$part);
        if ($value === '') {
            continue;
        }
        if (!empty($allowed) && !in_array($value, $allowed, true)) {
            continue;
        }
        if (in_array($value, $result, true)) {
            continue;
        }
        $result[] = $value;
    }
    return $result;
}

function kh_proto_serialize_posting_rows(array $rows): string
{
    $lines = [];
    foreach ($rows as $row) {
        $channel = trim((string)($row['channel'] ?? ''));
        $source = trim((string)($row['source'] ?? ''));
        $url = trim((string)($row['url'] ?? ''));
        if ($url === '') {
            continue;
        }
        $lines[] = $channel . '||' . $source . '||' . $url;
    }
    return implode("\n", $lines);
}

$dataset = karirhub_proto_dataset();
$units = $dataset['units'];
kh_proto_ensure_multi_tables($conn);
kh_proto_seed_multi_from_dataset($conn, $dataset, $units);

$form = [
    'unit_kode' => (string)($_POST['unit_kode'] ?? 'UNIT-001'),
    'periode_tipe' => trim((string)($_POST['periode_tipe'] ?? 'monthly')),
    'periode_anchor' => trim((string)($_POST['periode_anchor'] ?? date('Y-m-d'))),
    'jumlah_id_lowongan' => trim((string)($_POST['jumlah_id_lowongan'] ?? '1')),
    'daftar_jabatan' => '',
    'catatan' => trim((string)($_POST['catatan'] ?? '')),
];

$lowonganDefaults = [
    'jabatan' => '',
    'jumlah_kebutuhan' => '',
    'jenis_kelamin' => 'Semua',
    'usia_min' => '',
    'usia_max' => '',
    'pendidikan_minimal' => '',
    'deskripsi_pekerjaan' => '',
    'keterampilan_utama' => '',
    'kondisi_fisik' => '',
    'jenis_disabilitas_tidak_diperbolehkan' => '',
    'pengalaman_min_tahun' => '',
    'rentang_gaji' => '',
    'kode_kbji' => '',
    'provinsi' => '',
    'kota' => '',
    'kecamatan' => '',
    'kelurahan' => '',
    'bidang_pekerjaan' => '',
    'industri_sektor' => '',
    'status_pernikahan' => 'Tidak Dipersyaratkan',
    'metode_publikasi_loker' => 'Online',
    'tipe_kerja' => '',
    'platform_kanal' => '',
    'masa_berlaku_mulai' => date('Y-m-d'),
    'masa_berlaku_sampai' => date('Y-m-d', strtotime('+30 days')),
    'alamat_url_postingan_loker' => '',
    'alamat_url_postingan_loker_main' => '',
    'media_publikasi_offline' => '',
    'alasan_metode_offline' => '',
];
$lowonganFieldKeys = array_keys($lowonganDefaults);

$errors = [];
$generated = null;
$wizardLowonganTabs = [];
$wizardCount = max(1, min(50, (int)$form['jumlah_id_lowongan']));
$termsAgreed = isset($_POST['setuju_syarat']) && (string)$_POST['setuju_syarat'] === '1';
$initialLandingMode = $_SERVER['REQUEST_METHOD'] === 'POST' ? 'form' : '';
$requestedLandingMode = trim((string)($_GET['mode'] ?? ''));
if (in_array($requestedLandingMode, ['form', 'bulk'], true)) {
    $initialLandingMode = $requestedLandingMode;
}
$wizardForceOpen = ($_SERVER['REQUEST_METHOD'] !== 'POST' && $initialLandingMode === 'form') ? '1' : '0';

for ($i = 0; $i < $wizardCount; $i++) {
    $item = $lowonganDefaults;
    foreach ($lowonganFieldKeys as $fieldKey) {
        $raw = $_POST[$fieldKey] ?? null;
        if (is_array($raw) && array_key_exists($i, $raw)) {
            $item[$fieldKey] = trim((string)$raw[$i]);
        } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST' && $i > 0 && !in_array($fieldKey, ['jenis_kelamin', 'masa_berlaku_mulai', 'masa_berlaku_sampai'], true)) {
            $item[$fieldKey] = '';
        }
    }
    $wizardLowonganTabs[] = $item;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requiredHeaderFields = [
        'periode_tipe' => 'Periode Pelaporan',
        'periode_anchor' => 'Tanggal Anchor Periode',
        'jumlah_id_lowongan' => 'Jumlah ID Lowongan',
    ];
    foreach ($requiredHeaderFields as $fieldKey => $label) {
        if ($form[$fieldKey] === '') {
            $errors[] = $label . ' wajib diisi.';
        }
    }
    if (!isset($units[$form['unit_kode']])) {
        $errors[] = 'Unit perusahaan/usaha tidak valid.';
    }
    if (!in_array($form['periode_tipe'], ['weekly', 'monthly'], true)) {
        $errors[] = 'Periode Pelaporan harus Weekly atau Monthly.';
    }
    if (strtotime($form['periode_anchor']) === false) {
        $errors[] = 'Tanggal Anchor Periode tidak valid.';
    }
    if ($form['jumlah_id_lowongan'] !== '' && (!ctype_digit($form['jumlah_id_lowongan']) || (int)$form['jumlah_id_lowongan'] <= 0 || (int)$form['jumlah_id_lowongan'] > 50)) {
        $errors[] = 'Jumlah ID Lowongan harus angka 1 sampai 50.';
    }
    if (!$termsAgreed) {
        $errors[] = 'Anda wajib menyetujui Syarat dan Ketentuan Wajib Lapor Lowongan Pekerjaan.';
    }

    $requiredLowonganFields = [
        'jabatan' => 'Jabatan',
        'jumlah_kebutuhan' => 'Jumlah Kebutuhan',
        'kondisi_fisik' => 'Kondisi Fisik',
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
        'metode_publikasi_loker' => 'Metode publikasi loker',
        'tipe_kerja' => 'Status Pekerjaan',
        'masa_berlaku_mulai' => 'Masa Berlaku Mulai',
        'masa_berlaku_sampai' => 'Masa Berlaku Sampai',
    ];

    foreach ($wizardLowonganTabs as $idx => $item) {
        foreach ($requiredLowonganFields as $fieldKey => $label) {
            if (($item[$fieldKey] ?? '') === '') {
                $errors[] = 'Lowongan ' . ($idx + 1) . ': ' . $label . ' wajib diisi.';
            }
        }
        if (($item['jumlah_kebutuhan'] ?? '') !== '' && (!ctype_digit((string)$item['jumlah_kebutuhan']) || (int)$item['jumlah_kebutuhan'] <= 0)) {
            $errors[] = 'Lowongan ' . ($idx + 1) . ': Jumlah kebutuhan harus angka lebih dari 0.';
        }
        if (($item['usia_min'] ?? '') !== '' && ($item['usia_max'] ?? '') !== '' && (int)$item['usia_min'] > (int)$item['usia_max']) {
            $errors[] = 'Lowongan ' . ($idx + 1) . ': Usia minimal tidak boleh lebih besar dari usia maksimal.';
        }
        if (($item['masa_berlaku_mulai'] ?? '') !== '' && ($item['masa_berlaku_sampai'] ?? '') !== '' && $item['masa_berlaku_mulai'] > $item['masa_berlaku_sampai']) {
            $errors[] = 'Lowongan ' . ($idx + 1) . ': Masa berlaku mulai tidak boleh lebih akhir dari masa berlaku sampai.';
        }

        $kondisiFisik = kh_proto_parse_csv_values((string)($item['kondisi_fisik'] ?? ''), ['Disabilitas', 'Non Disabilitas']);
        $wizardLowonganTabs[$idx]['kondisi_fisik'] = implode(', ', $kondisiFisik);
        $jenisDisabilitas = kh_proto_parse_csv_values(
            (string)($item['jenis_disabilitas_tidak_diperbolehkan'] ?? ''),
            ['Tuna daksa', 'Tuna grahita', 'Tuna wicara', 'Tuna netra', 'Tuna rungu', 'Tuna ganda']
        );
        if (in_array('Disabilitas', $kondisiFisik, true)) {
            $wizardLowonganTabs[$idx]['jenis_disabilitas_tidak_diperbolehkan'] = implode(', ', $jenisDisabilitas);
        } else {
            $wizardLowonganTabs[$idx]['jenis_disabilitas_tidak_diperbolehkan'] = '';
        }

        $metodePublikasi = trim((string)($item['metode_publikasi_loker'] ?? ''));
        if (!in_array($metodePublikasi, ['Online', 'Offline'], true)) {
            $errors[] = 'Lowongan ' . ($idx + 1) . ': Metode publikasi loker harus Online atau Offline.';
            $metodePublikasi = 'Online';
        }

        if ($metodePublikasi === 'Online') {
            $selectedChannels = kh_proto_parse_platform_kanal((string)($item['platform_kanal'] ?? ''));
            if (empty($selectedChannels)) {
                $errors[] = 'Lowongan ' . ($idx + 1) . ': Platform/Kanal wajib dipilih untuk metode Online.';
            }
            $postingRowsRaw = kh_proto_parse_posting_rows((string)($item['alamat_url_postingan_loker'] ?? ''));
            $postingRows = [];
            foreach ($postingRowsRaw as $postingRow) {
                $rowUrl = trim((string)($postingRow['url'] ?? ''));
                $rowSource = trim((string)($postingRow['source'] ?? ''));
                if ($rowUrl === '' && $rowSource === '') {
                    continue;
                }
                $postingRows[] = [
                    'channel' => trim((string)($postingRow['channel'] ?? '')),
                    'source' => $rowSource,
                    'url' => $rowUrl,
                ];
            }
            if (empty($postingRows)) {
                $errors[] = 'Lowongan ' . ($idx + 1) . ': Alamat URL Postingan Loker wajib diisi untuk metode Online.';
            }
            foreach ($postingRows as $postingRow) {
                if ($postingRow['source'] === '') {
                    $errors[] = 'Lowongan ' . ($idx + 1) . ': Sumber URL wajib diisi.';
                    break;
                }
                if ($postingRow['channel'] !== '' && !in_array($postingRow['channel'], $selectedChannels, true)) {
                    $errors[] = 'Lowongan ' . ($idx + 1) . ': Kanal sumber URL tidak sesuai dengan Platform/Kanal terpilih.';
                    break;
                }
            }
            $urlRows = array_values(array_filter(array_map(static fn (array $row): string => (string)($row['url'] ?? ''), $postingRows), static fn ($v) => $v !== ''));
            $mainUrl = trim((string)($item['alamat_url_postingan_loker_main'] ?? ''));
            if (count($urlRows) > 1 && $mainUrl === '') {
                $errors[] = 'Lowongan ' . ($idx + 1) . ': Jika URL postingan lebih dari satu, wajib pilih satu URL Utama.';
            }
            if ($mainUrl !== '' && !in_array($mainUrl, $urlRows, true)) {
                $errors[] = 'Lowongan ' . ($idx + 1) . ': URL Utama harus salah satu dari daftar URL postingan.';
            }
            if (!empty($postingRows)) {
                if ($mainUrl === '' || !in_array($mainUrl, $urlRows, true)) {
                    $mainUrl = (string)($postingRows[0]['url'] ?? '');
                }
                if (count($postingRows) > 1) {
                    usort($postingRows, static function (array $a, array $b) use ($mainUrl): int {
                        $aMain = (string)($a['url'] ?? '') === $mainUrl ? 0 : 1;
                        $bMain = (string)($b['url'] ?? '') === $mainUrl ? 0 : 1;
                        if ($aMain === $bMain) {
                            return 0;
                        }
                        return $aMain <=> $bMain;
                    });
                }
                if (count($selectedChannels) === 1) {
                    foreach ($postingRows as $pIdx => $postingRow) {
                        if (trim((string)($postingRow['channel'] ?? '')) === '') {
                            $postingRows[$pIdx]['channel'] = $selectedChannels[0];
                        }
                    }
                }
                $wizardLowonganTabs[$idx]['platform_kanal'] = implode(', ', $selectedChannels);
                $wizardLowonganTabs[$idx]['alamat_url_postingan_loker'] = kh_proto_serialize_posting_rows($postingRows);
                $wizardLowonganTabs[$idx]['alamat_url_postingan_loker_main'] = $mainUrl;
            }
            $wizardLowonganTabs[$idx]['media_publikasi_offline'] = '';
            $wizardLowonganTabs[$idx]['alasan_metode_offline'] = '';
            $wizardLowonganTabs[$idx]['metode_publikasi_loker'] = 'Online';
        } else {
            if (trim((string)($item['media_publikasi_offline'] ?? '')) === '') {
                $errors[] = 'Lowongan ' . ($idx + 1) . ': Media yang digunakan untuk publikasi wajib diisi untuk metode Offline.';
            }
            if (trim((string)($item['alasan_metode_offline'] ?? '')) === '') {
                $errors[] = 'Lowongan ' . ($idx + 1) . ': Alasan menggunakan metode offline wajib diisi.';
            }
            $wizardLowonganTabs[$idx]['platform_kanal'] = '';
            $wizardLowonganTabs[$idx]['alamat_url_postingan_loker'] = '';
            $wizardLowonganTabs[$idx]['alamat_url_postingan_loker_main'] = '';
            $wizardLowonganTabs[$idx]['metode_publikasi_loker'] = 'Offline';
        }
    }

    $form['daftar_jabatan'] = implode("\n", array_values(array_filter(array_map(static fn ($x) => trim((string)($x['jabatan'] ?? '')), $wizardLowonganTabs), static fn ($x) => $x !== '')));

    if (empty($errors)) {
        $unitNama = (string)($units[$form['unit_kode']]['nama'] ?? $form['unit_kode']);
        $employerKode = (string)($units[$form['unit_kode']]['employer_kode'] ?? 'EMP-001');
        $employerNama = (string)($units[$form['unit_kode']]['employer_nama'] ?? 'PT Contoh Nusantara');
        $period = kh_proto_derive_period($form['periode_tipe'], $form['periode_anchor']);
        $generatedNoReg = kh_proto_generate_no_reg_bukti($conn, $period['anchor']);
        $statusBelumTerisi = 'Belum Terisi';
        $generatedLowongan = [];

        $stmtSaveHeader = $conn->prepare("
            INSERT INTO karirhub_proto_wllp_laporan
                (no_reg_bukti, employer_kode, employer_nama, unit_kode, unit_nama, periode_tipe, periode_anchor, periode_mulai, periode_selesai, status_verifikasi, catatan)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Terverifikasi', ?)
            ON DUPLICATE KEY UPDATE
                employer_kode = VALUES(employer_kode),
                employer_nama = VALUES(employer_nama),
                unit_kode = VALUES(unit_kode),
                unit_nama = VALUES(unit_nama),
                periode_tipe = VALUES(periode_tipe),
                periode_anchor = VALUES(periode_anchor),
                periode_mulai = VALUES(periode_mulai),
                periode_selesai = VALUES(periode_selesai),
                catatan = VALUES(catatan)
        ");
        $stmtSaveDetail = $conn->prepare("
            INSERT INTO karirhub_proto_wllp_pelaporan (
                no_reg_bukti, id_lowongan, employer_kode, employer_nama, unit_kode, unit_nama, jabatan, jumlah_kebutuhan, jenis_kelamin, usia_min, usia_max,
                pendidikan_minimal, deskripsi_pekerjaan, keterampilan_utama, pengalaman_min_tahun, rentang_gaji, kode_kbji, provinsi, kota, kecamatan, kelurahan,
                bidang_pekerjaan, industri_sektor, status_pernikahan, tipe_kerja, platform_kanal, masa_berlaku_mulai, masa_berlaku_sampai, alamat_url_postingan_loker, catatan, status_verifikasi
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Terverifikasi')
        ");
        $stmtSaveStatus = $conn->prepare("
            INSERT INTO karirhub_proto_wllp_status (no_reg_bukti, id_lowongan, employer_kode, employer_nama, jabatan, unit_nama, status_saat_ini, tanggal_lapor, tanggal_terisi)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL)
        ");

        $conn->begin_transaction();
        try {
            $stmtSaveHeader->bind_param(
                'ssssssssss',
                $generatedNoReg,
                $employerKode,
                $employerNama,
                $form['unit_kode'],
                $unitNama,
                $period['tipe'],
                $period['anchor'],
                $period['mulai'],
                $period['selesai'],
                $form['catatan']
            );
            $stmtSaveHeader->execute();

            for ($i = 0; $i < $wizardCount; $i++) {
                $generatedIdLowongan = kh_proto_generate_id_lowongan($conn);
                $generatedLowongan[] = $generatedIdLowongan;
                $item = $wizardLowonganTabs[$i];

                $jumlahKebutuhanInt = (int)$item['jumlah_kebutuhan'];
                $usiaMinInt = (int)$item['usia_min'];
                $usiaMaxInt = (int)$item['usia_max'];
                $pengalamanMinInt = (int)$item['pengalaman_min_tahun'];
                $jabatanItem = (string)$item['jabatan'];

                $stmtSaveDetail->bind_param(
                    str_repeat('s', 30),
                    $generatedNoReg,
                    $generatedIdLowongan,
                    $employerKode,
                    $employerNama,
                    $form['unit_kode'],
                    $unitNama,
                    $jabatanItem,
                    $jumlahKebutuhanInt,
                    $item['jenis_kelamin'],
                    $usiaMinInt,
                    $usiaMaxInt,
                    $item['pendidikan_minimal'],
                    $item['deskripsi_pekerjaan'],
                    $item['keterampilan_utama'],
                    $pengalamanMinInt,
                    $item['rentang_gaji'],
                    $item['kode_kbji'],
                    $item['provinsi'],
                    $item['kota'],
                    $item['kecamatan'],
                    $item['kelurahan'],
                    $item['bidang_pekerjaan'],
                    $item['industri_sektor'],
                    $item['status_pernikahan'],
                    $item['tipe_kerja'],
                    $item['platform_kanal'],
                    $item['masa_berlaku_mulai'],
                    $item['masa_berlaku_sampai'],
                    $item['alamat_url_postingan_loker'],
                    $form['catatan']
                );
                $stmtSaveDetail->execute();

                $stmtSaveStatus->bind_param(
                    'ssssssss',
                    $generatedNoReg,
                    $generatedIdLowongan,
                    $employerKode,
                    $employerNama,
                    $jabatanItem,
                    $unitNama,
                    $statusBelumTerisi,
                    $item['masa_berlaku_mulai']
                );
                $stmtSaveStatus->execute();
            }

            $conn->commit();
            $generated = [
                'id_lowongan_list' => $generatedLowongan,
                'no_reg_bukti' => $generatedNoReg,
                'status_verifikasi' => 'Terverifikasi (Dummy)',
                'status_keterisian' => 'Belum Terisi',
                'created_at' => date('Y-m-d H:i:s'),
                'periode_label' => strtoupper($period['tipe']) . ' (' . $period['mulai'] . ' s.d. ' . $period['selesai'] . ')',
            ];
        } catch (Throwable $e) {
            $conn->rollback();
            $errors[] = 'Gagal menyimpan laporan: ' . $e->getMessage();
        }

        $stmtSaveHeader->close();
        $stmtSaveDetail->close();
        $stmtSaveStatus->close();
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
    <style>
        #wizardSummaryBar {
            border: 1px solid #cfe2ff;
            border-left: 4px solid #0d6efd;
            background: #f8fbff;
        }
        #wizardSummaryBar .wizard-meta-pill {
            display: inline-block;
            padding: 0.2rem 0.55rem;
            border-radius: 999px;
            background: #e7f1ff;
            color: #0a58ca;
            font-weight: 600;
            margin-right: 0.35rem;
        }
        #lowonganTabsNav .nav-link {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-weight: 600;
        }
        #lowonganTabsNav .nav-link.active {
            background: #0d6efd;
            border-color: #0d6efd;
            color: #fff;
        }
        .wizard-tab-badge {
            min-width: 88px;
        }
        #wizardValidationSummary {
            border-left: 4px solid #ffc107;
        }
        .wizard-url-row .btn {
            min-width: 2.25rem;
        }
        .wizard-url-main-wrap {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
            color: #4f647a;
            padding: 0 0.5rem;
            border-left: 0;
            border-right: 0;
            background: #f8fbff;
            border-top: 1px solid #ced4da;
            border-bottom: 1px solid #ced4da;
        }
        .wizard-url-section {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.65rem;
            margin-bottom: 0.5rem;
            background: #fff;
        }
        .wizard-url-section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.4rem;
        }
        .wizard-url-row {
            display: grid;
            grid-template-columns: minmax(180px, 32%) minmax(240px, 1fr) auto auto;
            gap: 0.35rem;
            align-items: center;
            margin-bottom: 0.35rem;
        }
        .wizard-url-source-wrap {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        @media (max-width: 991px) {
            .wizard-url-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="kh-proto-page" data-wizard-force-open="<?php echo $wizardForceOpen; ?>" data-initial-landing-mode="<?php echo h($initialLandingMode); ?>">
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
            <a class="btn btn-outline-primary btn-sm" href="karirhub_employer_prototype_dashboard_wllp">
                <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard WLLP
            </a>
        </div>
    </div>

    <div id="landingChoiceSection" class="card border-0 shadow-sm mb-3" style="display:none;">
        <div class="card-body">
            <h5 class="mb-2">Pilih Metode Pelaporan</h5>
            <div class="text-muted small mb-3">Silakan pilih salah satu alur pelaporan lowongan yang ingin digunakan.</div>
            <div class="row g-2">
                <div class="col-12 col-md-6">
                    <button type="button" class="btn btn-outline-success w-100 py-3" id="btnLandingBulk">
                        <i class="bi bi-file-earmark-spreadsheet me-1"></i>
                        Laporkan Lowongan Kerja dalam Jumlah Banyak sekaligus
                    </button>
                </div>
                <div class="col-12 col-md-6">
                    <button type="button" class="btn btn-primary w-100 py-3" id="btnLandingForm">
                        <i class="bi bi-ui-checks-grid me-1"></i>
                        Laporkan Lowongan Kerja dengan Isian Form
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="bulkToolsSection" class="card border-0 shadow-sm mb-3" style="display:none;">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <div>
                    <h5 class="mb-0">Pelaporan Massal Lowongan</h5>
                    <div class="text-muted small">Gunakan template Excel untuk lapor banyak lowongan sekaligus.</div>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnBulkToChooseMode">
                    <i class="bi bi-arrow-left-right me-1"></i>Ubah Metode
                </button>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-success btn-sm" id="btnDownloadPelaporanTemplate">
                    <i class="bi bi-download me-1"></i>Download Template
                </button>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#bulkImportPelaporanModal">
                    <i class="bi bi-file-earmark-arrow-up me-1"></i>Bulk Import
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnBulkToFormMode">
                    <i class="bi bi-ui-checks-grid me-1"></i>Buka Form Isian
                </button>
            </div>
        </div>
    </div>

    <div id="formPelaporanSection" style="display:none;">
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
            <div><strong>Periode Pelaporan:</strong> <?php echo h($generated['periode_label']); ?></div>
            <div><strong>Total ID Lowongan:</strong> <?php echo h((string)count($generated['id_lowongan_list'])); ?></div>
            <div><strong>ID Lowongan:</strong> <?php echo h(implode(', ', $generated['id_lowongan_list'])); ?></div>
            <div><strong>Status Verifikasi:</strong> <?php echo h($generated['status_verifikasi']); ?></div>
            <div><strong>Waktu Simulasi:</strong> <?php echo h($generated['created_at']); ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-end mb-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnFormToChooseMode">
                    <i class="bi bi-arrow-left-right me-1"></i>Ubah Metode
                </button>
            </div>
            <input type="hidden" name="periode_tipe" id="wizardPeriodeTipe" value="<?php echo h($form['periode_tipe']); ?>">
            <input type="hidden" name="periode_anchor" id="wizardPeriodeAnchor" value="<?php echo h($form['periode_anchor']); ?>">
            <input type="hidden" name="jumlah_id_lowongan" id="wizardJumlahLowongan" value="<?php echo h((string)$wizardCount); ?>">
            <input type="hidden" name="daftar_jabatan" id="wizardDaftarJabatan" value="<?php echo h($form['daftar_jabatan']); ?>">
            <input type="hidden" name="setuju_syarat" id="setujuSyaratValue" value="<?php echo $termsAgreed ? '1' : '0'; ?>">

            <div class="alert alert-primary py-2 d-flex flex-wrap justify-content-between align-items-center gap-2" id="wizardSummaryBar">
                <div class="small">
                    <span class="wizard-meta-pill"><i class="bi bi-123 me-1"></i>Step 3/3</span>
                    <span class="wizard-meta-pill"><i class="bi bi-calendar-week me-1"></i><span id="wizardSummaryPeriode"><?php echo h(strtoupper($form['periode_tipe']) . ' - ' . $form['periode_anchor']); ?></span></span>
                    <span class="wizard-meta-pill"><i class="bi bi-layers me-1"></i><span id="wizardSummaryJumlah"><?php echo h((string)$wizardCount); ?></span> Lowongan</span>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnEditWizardFlow">
                    <i class="bi bi-pencil-square me-1"></i>Edit
                </button>
            </div>

            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label mb-1">Unit Perusahaan/ Usaha</label>
                    <select name="unit_kode" class="form-select form-select-sm">
                        <?php foreach ($units as $unitCode => $unit): ?>
                            <option value="<?php echo h($unitCode); ?>"<?php echo $form['unit_kode'] === $unitCode ? ' selected' : ''; ?>><?php echo h($unit['nama']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <div class="border rounded p-2 bg-light">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="fw-semibold small">Form Pelaporan Lowongan per ID</div>
                            <div class="small text-muted" id="wizardTabProgressText">Lengkapi semua tab lowongan.</div>
                        </div>
                        <ul class="nav nav-tabs" id="lowonganTabsNav" role="tablist">
                            <?php foreach ($wizardLowonganTabs as $index => $tab): ?>
                                <li class="nav-item" role="presentation">
                                    <button
                                        class="nav-link<?php echo $index === 0 ? ' active' : ''; ?>"
                                        id="lowongan-tab-<?php echo $index; ?>"
                                        data-bs-toggle="tab"
                                        data-bs-target="#lowongan-pane-<?php echo $index; ?>"
                                        type="button"
                                        role="tab"
                                        aria-controls="lowongan-pane-<?php echo $index; ?>"
                                        aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                                    >
                                        Lowongan <?php echo $index + 1; ?>
                                        <span class="badge text-bg-secondary ms-1 wizard-tab-badge" id="wizardTabBadge-<?php echo $index; ?>">Belum lengkap</span>
                                    </button>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="alert alert-warning py-2 mt-2 mb-0 small" id="wizardValidationSummary" style="display:none;"></div>
                        <div class="tab-content border border-top-0 bg-white p-3" id="lowonganTabsContent">
                            <?php foreach ($wizardLowonganTabs as $index => $tab): ?>
                                <div class="tab-pane fade<?php echo $index === 0 ? ' show active' : ''; ?>" id="lowongan-pane-<?php echo $index; ?>" role="tabpanel" aria-labelledby="lowongan-tab-<?php echo $index; ?>">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="border rounded p-3 bg-light-subtle">
                                                <div class="fw-semibold mb-2">Informasi Jabatan</div>
                                                <div class="row g-2">
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label mb-1">Jabatan (Lowongan <?php echo $index + 1; ?>)</label>
                                                        <input type="text" class="form-control form-control-sm wizard-lowongan-field" name="jabatan[]" value="<?php echo h((string)$tab['jabatan']); ?>" data-tab-index="<?php echo $index; ?>" data-field="jabatan" data-required="1">
                                                    </div>
                                                    <div class="col-6 col-md-3">
                                                        <label class="form-label mb-1">Jumlah Kebutuhan</label>
                                                        <input type="number" min="1" class="form-control form-control-sm wizard-lowongan-field" name="jumlah_kebutuhan[]" value="<?php echo h((string)$tab['jumlah_kebutuhan']); ?>" data-tab-index="<?php echo $index; ?>" data-field="jumlah_kebutuhan" data-required="1">
                                                    </div>
                                                    <div class="col-6 col-md-3">
                                                        <label class="form-label mb-1">Jenis Kelamin</label>
                                                        <select class="form-select form-select-sm wizard-lowongan-field" name="jenis_kelamin[]" data-tab-index="<?php echo $index; ?>" data-field="jenis_kelamin">
                                                            <?php foreach (['Semua', 'Laki-laki', 'Perempuan'] as $jk): ?>
                                                                <option value="<?php echo h($jk); ?>"<?php echo ($tab['jenis_kelamin'] ?? '') === $jk ? ' selected' : ''; ?>><?php echo h($jk); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label mb-1">Kondisi Fisik</label>
                                                        <?php
                                                        $selectedKondisiFisik = kh_proto_parse_csv_values((string)($tab['kondisi_fisik'] ?? ''), ['Disabilitas', 'Non Disabilitas']);
                                                        $selectedJenisDisabilitas = kh_proto_parse_csv_values(
                                                            (string)($tab['jenis_disabilitas_tidak_diperbolehkan'] ?? ''),
                                                            ['Tuna daksa', 'Tuna grahita', 'Tuna wicara', 'Tuna netra', 'Tuna rungu', 'Tuna ganda']
                                                        );
                                                        ?>
                                                        <div class="wizard-kondisi-group border rounded p-2 bg-light" data-tab-index="<?php echo $index; ?>">
                                                            <div class="d-flex flex-wrap gap-3">
                                                                <?php foreach (['Disabilitas', 'Non Disabilitas'] as $kondisi): ?>
                                                                    <?php $kondisiKey = strtolower(str_replace(' ', '-', $kondisi)); ?>
                                                                    <div class="form-check m-0">
                                                                        <input
                                                                            class="form-check-input wizard-kondisi-check"
                                                                            type="checkbox"
                                                                            value="<?php echo h($kondisi); ?>"
                                                                            id="kondisi-<?php echo $index; ?>-<?php echo h($kondisiKey); ?>"
                                                                            <?php echo in_array($kondisi, $selectedKondisiFisik, true) ? ' checked' : ''; ?>
                                                                        >
                                                                        <label class="form-check-label small" for="kondisi-<?php echo $index; ?>-<?php echo h($kondisiKey); ?>"><?php echo h($kondisi); ?></label>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <input type="hidden" class="wizard-lowongan-field" name="kondisi_fisik[]" value="<?php echo h((string)$tab['kondisi_fisik']); ?>" data-tab-index="<?php echo $index; ?>" data-field="kondisi_fisik" data-required="0">
                                                            <div class="wizard-disabilitas-only mt-2" style="<?php echo in_array('Disabilitas', $selectedKondisiFisik, true) ? '' : 'display:none;'; ?>">
                                                                <label class="form-label mb-1">Pilih jenis disabilitas yang tidak diperbolehkan untuk melamar.</label>
                                                                <div class="d-flex flex-wrap gap-3">
                                                                    <?php foreach (['Tuna daksa', 'Tuna grahita', 'Tuna wicara', 'Tuna netra', 'Tuna rungu', 'Tuna ganda'] as $jenisDisabilitas): ?>
                                                                        <?php $jenisDisabilitasKey = strtolower(str_replace(' ', '-', $jenisDisabilitas)); ?>
                                                                        <div class="form-check m-0">
                                                                            <input
                                                                                class="form-check-input wizard-disabilitas-jenis-check"
                                                                                type="checkbox"
                                                                                value="<?php echo h($jenisDisabilitas); ?>"
                                                                                id="jenis-disabilitas-<?php echo $index; ?>-<?php echo h($jenisDisabilitasKey); ?>"
                                                                                <?php echo in_array($jenisDisabilitas, $selectedJenisDisabilitas, true) ? ' checked' : ''; ?>
                                                                            >
                                                                            <label class="form-check-label small" for="jenis-disabilitas-<?php echo $index; ?>-<?php echo h($jenisDisabilitasKey); ?>"><?php echo h($jenisDisabilitas); ?></label>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                                <input type="hidden" class="wizard-lowongan-field" name="jenis_disabilitas_tidak_diperbolehkan[]" value="<?php echo h((string)($tab['jenis_disabilitas_tidak_diperbolehkan'] ?? '')); ?>" data-tab-index="<?php echo $index; ?>" data-field="jenis_disabilitas_tidak_diperbolehkan" data-required="0">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 col-md-3">
                                                        <label class="form-label mb-1">Usia Minimal</label>
                                                        <input type="number" min="18" class="form-control form-control-sm wizard-lowongan-field" name="usia_min[]" value="<?php echo h((string)$tab['usia_min']); ?>" data-tab-index="<?php echo $index; ?>" data-field="usia_min" data-required="1">
                                                    </div>
                                                    <div class="col-6 col-md-3">
                                                        <label class="form-label mb-1">Usia Maksimal</label>
                                                        <input type="number" min="18" class="form-control form-control-sm wizard-lowongan-field" name="usia_max[]" value="<?php echo h((string)$tab['usia_max']); ?>" data-tab-index="<?php echo $index; ?>" data-field="usia_max" data-required="1">
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label mb-1">Pendidikan Minimal</label>
                                                        <input type="text" class="form-control form-control-sm wizard-lowongan-field" name="pendidikan_minimal[]" value="<?php echo h((string)$tab['pendidikan_minimal']); ?>" data-tab-index="<?php echo $index; ?>" data-field="pendidikan_minimal" data-required="1">
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label mb-1">Pengalaman Minimal (tahun)</label>
                                                        <input type="number" min="0" class="form-control form-control-sm wizard-lowongan-field" name="pengalaman_min_tahun[]" value="<?php echo h((string)$tab['pengalaman_min_tahun']); ?>" data-tab-index="<?php echo $index; ?>" data-field="pengalaman_min_tahun" data-required="1">
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label mb-1">Deskripsi Pekerjaan</label>
                                                        <textarea class="form-control form-control-sm wizard-lowongan-field" name="deskripsi_pekerjaan[]" rows="3" data-tab-index="<?php echo $index; ?>" data-field="deskripsi_pekerjaan" data-required="1"><?php echo h((string)$tab['deskripsi_pekerjaan']); ?></textarea>
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label mb-1">Keterampilan Utama</label>
                                                        <textarea class="form-control form-control-sm wizard-lowongan-field" name="keterampilan_utama[]" rows="2" data-tab-index="<?php echo $index; ?>" data-field="keterampilan_utama" data-required="1"><?php echo h((string)$tab['keterampilan_utama']); ?></textarea>
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label mb-1">Rentang Gaji</label>
                                                        <input type="text" class="form-control form-control-sm wizard-lowongan-field" name="rentang_gaji[]" value="<?php echo h((string)$tab['rentang_gaji']); ?>" placeholder="Rp5.000.000 - Rp7.000.000" data-tab-index="<?php echo $index; ?>" data-field="rentang_gaji" data-required="1">
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label mb-1">Bidang Pekerjaan</label>
                                                        <input type="text" class="form-control form-control-sm wizard-lowongan-field" name="bidang_pekerjaan[]" value="<?php echo h((string)$tab['bidang_pekerjaan']); ?>" data-tab-index="<?php echo $index; ?>" data-field="bidang_pekerjaan" data-required="1">
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label mb-1">Industri / Sektor</label>
                                                        <input type="text" class="form-control form-control-sm wizard-lowongan-field" name="industri_sektor[]" value="<?php echo h((string)$tab['industri_sektor']); ?>" data-tab-index="<?php echo $index; ?>" data-field="industri_sektor" data-required="1">
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label mb-1">Status Pekerjaan</label>
                                                        <select class="form-select form-select-sm wizard-lowongan-field" name="tipe_kerja[]" data-tab-index="<?php echo $index; ?>" data-field="tipe_kerja" data-required="1">
                                                            <option value="">Pilih</option>
                                                            <?php foreach (['Full Time', 'Part Time', 'Contract', 'Internship'] as $tipe): ?>
                                                                <option value="<?php echo h($tipe); ?>"<?php echo ($tab['tipe_kerja'] ?? '') === $tipe ? ' selected' : ''; ?>><?php echo h($tipe); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label mb-1">Masa Berlaku Loker Mulai</label>
                                                        <input type="date" class="form-control form-control-sm wizard-lowongan-field" name="masa_berlaku_mulai[]" value="<?php echo h((string)$tab['masa_berlaku_mulai']); ?>" data-tab-index="<?php echo $index; ?>" data-field="masa_berlaku_mulai" data-required="1">
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label mb-1">Masa Berlaku Loker Sampai</label>
                                                        <input type="date" class="form-control form-control-sm wizard-lowongan-field" name="masa_berlaku_sampai[]" value="<?php echo h((string)$tab['masa_berlaku_sampai']); ?>" data-tab-index="<?php echo $index; ?>" data-field="masa_berlaku_sampai" data-required="1">
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label mb-1">Kode KBJI</label>
                                                        <input type="text" class="form-control form-control-sm wizard-lowongan-field" name="kode_kbji[]" value="<?php echo h((string)$tab['kode_kbji']); ?>" placeholder="Contoh: 24231" data-tab-index="<?php echo $index; ?>" data-field="kode_kbji" data-required="1">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="border rounded p-3 bg-light-subtle">
                                                <div class="fw-semibold mb-2">Lokasi Penempatan</div>
                                                <div class="row g-2">
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label mb-1">Provinsi</label>
                                                        <input type="text" class="form-control form-control-sm wizard-lowongan-field" name="provinsi[]" value="<?php echo h((string)$tab['provinsi']); ?>" data-tab-index="<?php echo $index; ?>" data-field="provinsi" data-required="1">
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label mb-1">Kota</label>
                                                        <input type="text" class="form-control form-control-sm wizard-lowongan-field" name="kota[]" value="<?php echo h((string)$tab['kota']); ?>" data-tab-index="<?php echo $index; ?>" data-field="kota" data-required="1">
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label mb-1">Kecamatan</label>
                                                        <input type="text" class="form-control form-control-sm wizard-lowongan-field" name="kecamatan[]" value="<?php echo h((string)$tab['kecamatan']); ?>" data-tab-index="<?php echo $index; ?>" data-field="kecamatan" data-required="1">
                                                    </div>
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label mb-1">Kelurahan</label>
                                                        <input type="text" class="form-control form-control-sm wizard-lowongan-field" name="kelurahan[]" value="<?php echo h((string)$tab['kelurahan']); ?>" data-tab-index="<?php echo $index; ?>" data-field="kelurahan" data-required="1">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="border rounded p-3 bg-light-subtle">
                                                <div class="fw-semibold mb-2">Sarana Penyebaran Loker</div>
                                                <div class="row g-2">
                                                    <div class="col-12 col-md-6">
                                                        <label class="form-label mb-1">Metode publikasi loker</label>
                                                        <div class="d-flex flex-wrap gap-3 border rounded p-2 bg-light">
                                                            <?php
                                                            $metodePublikasi = in_array((string)($tab['metode_publikasi_loker'] ?? 'Online'), ['Online', 'Offline'], true)
                                                                ? (string)$tab['metode_publikasi_loker']
                                                                : 'Online';
                                                            ?>
                                                            <div class="form-check m-0">
                                                                <input class="form-check-input wizard-lowongan-field wizard-metode-publikasi-radio" type="radio" name="metode_publikasi_loker[<?php echo $index; ?>]" id="metode-online-<?php echo $index; ?>" value="Online" data-tab-index="<?php echo $index; ?>" data-field="metode_publikasi_loker" data-required="0"<?php echo $metodePublikasi === 'Online' ? ' checked' : ''; ?>>
                                                                <label class="form-check-label small" for="metode-online-<?php echo $index; ?>">Online</label>
                                                            </div>
                                                            <div class="form-check m-0">
                                                                <input class="form-check-input wizard-lowongan-field wizard-metode-publikasi-radio" type="radio" name="metode_publikasi_loker[<?php echo $index; ?>]" id="metode-offline-<?php echo $index; ?>" value="Offline" data-tab-index="<?php echo $index; ?>" data-field="metode_publikasi_loker" data-required="0"<?php echo $metodePublikasi === 'Offline' ? ' checked' : ''; ?>>
                                                                <label class="form-check-label small" for="metode-offline-<?php echo $index; ?>">Offline</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-6 wizard-online-only">
                                                        <label class="form-label mb-1">Platform/Kanal</label>
                                                        <?php
                                                        $selectedPlatforms = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', (string)($tab['platform_kanal'] ?? ''))), static fn ($v) => $v !== ''));
                                                        $platformOptions = ['Job Portal', 'Social Media', 'Lainnya'];
                                                        ?>
                                                        <div class="wizard-platform-group border rounded p-2 bg-light" data-tab-index="<?php echo $index; ?>">
                                                            <div class="d-flex flex-wrap gap-3">
                                                                <?php foreach ($platformOptions as $platformOption): ?>
                                                                    <div class="form-check m-0">
                                                                        <input
                                                                            class="form-check-input wizard-platform-check"
                                                                            type="checkbox"
                                                                            value="<?php echo h($platformOption); ?>"
                                                                            id="platform-<?php echo $index; ?>-<?php echo strtolower(str_replace(' ', '-', $platformOption)); ?>"
                                                                            <?php echo in_array($platformOption, $selectedPlatforms, true) ? ' checked' : ''; ?>
                                                                        >
                                                                        <label class="form-check-label small" for="platform-<?php echo $index; ?>-<?php echo strtolower(str_replace(' ', '-', $platformOption)); ?>">
                                                                            <?php echo h($platformOption); ?>
                                                                        </label>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <input type="hidden" class="wizard-lowongan-field" name="platform_kanal[]" value="<?php echo h((string)$tab['platform_kanal']); ?>" data-tab-index="<?php echo $index; ?>" data-field="platform_kanal" data-required="0">
                                                        </div>
                                                    </div>
                                                    <div class="col-12 wizard-online-only">
                                                        <label class="form-label mb-1">Alamat URL Postingan Loker</label>
                                                        <div class="wizard-url-group" data-tab-index="<?php echo $index; ?>">
                                                            <div class="wizard-url-list"></div>
                                                            <div class="small text-muted mt-1">Jika URL lebih dari satu, pilih salah satu sebagai URL Utama.</div>
                                                            <input type="hidden" class="wizard-lowongan-field" name="alamat_url_postingan_loker[]" value="<?php echo h((string)$tab['alamat_url_postingan_loker']); ?>" data-tab-index="<?php echo $index; ?>" data-field="alamat_url_postingan_loker" data-required="0">
                                                            <input type="hidden" class="wizard-lowongan-field" name="alamat_url_postingan_loker_main[]" value="<?php echo h((string)$tab['alamat_url_postingan_loker_main']); ?>" data-tab-index="<?php echo $index; ?>" data-field="alamat_url_postingan_loker_main">
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-6 wizard-offline-only" style="display:none;">
                                                        <label class="form-label mb-1">Media yang digunakan untuk publikasi</label>
                                                        <input type="text" class="form-control form-control-sm wizard-lowongan-field" name="media_publikasi_offline[]" value="<?php echo h((string)($tab['media_publikasi_offline'] ?? '')); ?>" data-tab-index="<?php echo $index; ?>" data-field="media_publikasi_offline" data-required="0">
                                                    </div>
                                                    <div class="col-12 col-md-6 wizard-offline-only" style="display:none;">
                                                        <label class="form-label mb-1">Alasan menggunakan metode offline</label>
                                                        <input type="text" class="form-control form-control-sm wizard-lowongan-field" name="alasan_metode_offline[]" value="<?php echo h((string)($tab['alasan_metode_offline'] ?? '')); ?>" data-tab-index="<?php echo $index; ?>" data-field="alasan_metode_offline" data-required="0">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label mb-1">Catatan</label>
                    <input type="text" name="catatan" class="form-control form-control-sm" value="<?php echo h($form['catatan']); ?>">
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm" id="btnSubmitPelaporan">
                    <i class="bi bi-send-check me-1"></i>Simulasikan Buat Laporan
                </button>
                <a class="btn btn-outline-secondary btn-sm" href="karirhub_employer_prototype_pelaporan_lowongan">
                    Reset Form
                </a>
            </div>
        </div>
    </form>
    </div>
    </main>
    </div>
</div>
</div>

<div class="modal fade" id="syaratKetentuanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">SYARAT DAN KETENTUAN WAJIB LAPOR LOWONGAN PEKERJAAN</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body small">
                <h6>1. Ketentuan Umum</h6>
                <p>Wajib Lapor Lowongan Pekerjaan adalah kewajiban pemberi kerja untuk menyampaikan informasi lowongan pekerjaan kepada Kementerian Ketenagakerjaan melalui sistem yang ditetapkan.</p>
                <p>Pemberi kerja adalah perusahaan, instansi, lembaga, badan usaha, atau perseorangan yang membuka kesempatan kerja dan menyampaikan informasi lowongan pekerjaan melalui layanan Wajib Lapor Lowongan Pekerjaan.</p>
                <p>Pemberi kerja wajib memiliki akun SIAPkerja melalui laman <a href="https://account.kemnaker.go.id" target="_blank" rel="noopener noreferrer">https://account.kemnaker.go.id</a> dan melengkapi seluruh data yang dipersyaratkan secara benar, lengkap, mutakhir, dan dapat dipertanggungjawabkan.</p>

                <h6>2. Kewajiban Pemberi Kerja</h6>
                <p>Pemberi kerja wajib memastikan bahwa seluruh data yang disampaikan dalam Wajib Lapor Lowongan Pekerjaan merupakan data yang valid, akurat, dan sesuai dengan kondisi sebenarnya.</p>
                <p>Data yang wajib disampaikan sekurang-kurangnya meliputi identitas pemberi kerja, informasi jabatan, jumlah kebutuhan tenaga kerja, lokasi penempatan, kualifikasi jabatan, jenis hubungan kerja, rentang upah atau informasi pengupahan sesuai ketentuan yang berlaku, serta periode pembukaan lowongan.</p>
                <p>Pemberi kerja wajib memastikan bahwa lowongan pekerjaan yang dilaporkan bukan lowongan fiktif, palsu, menyesatkan, diskriminatif, atau bertentangan dengan ketentuan peraturan perundang-undangan.</p>
                <p>Pemberi kerja bertanggung jawab penuh atas kebenaran, keabsahan, dan legalitas seluruh data serta dokumen pendukung yang disampaikan.</p>

                <h6>3. Perlindungan Data</h6>
                <p>Pemberi kerja wajib melindungi dan menjaga seluruh data pribadi yang dikelola dalam proses pelaporan lowongan pekerjaan, termasuk data perusahaan, pengelola akun, dan pencari kerja, sesuai dengan ketentuan peraturan perundang-undangan.</p>
                <p>Pemberi kerja dilarang menyalahgunakan data pencari kerja untuk kepentingan di luar proses rekrutmen yang sah.</p>

                <h6>4. Hak Kementerian Ketenagakerjaan</h6>
                <p>Kementerian Ketenagakerjaan berhak melakukan pengelolaan, pengolahan, verifikasi, validasi, analisis, dan pelaporan atas data lowongan pekerjaan yang disampaikan oleh pemberi kerja.</p>
                <p>Kementerian Ketenagakerjaan berhak meminta klarifikasi, perbaikan, atau dokumen pendukung apabila terdapat indikasi data tidak lengkap, tidak sesuai, tidak valid, atau diragukan kebenarannya.</p>
                <p>Kementerian Ketenagakerjaan berhak menolak, menonaktifkan, menghapus, atau memblokir laporan lowongan pekerjaan dan/atau akun pemberi kerja apabila ditemukan indikasi lowongan palsu, penyalahgunaan layanan, pelanggaran hukum, atau ketidaksesuaian data.</p>

                <h6>5. Validasi dan Pernyataan Kebenaran Data</h6>
                <p>Dengan menyampaikan Wajib Lapor Lowongan Pekerjaan, pemberi kerja menyatakan bahwa seluruh data yang disampaikan adalah benar, valid, mutakhir, dan dapat dipertanggungjawabkan.</p>
                <p>Pemberi kerja bersedia menerima konsekuensi administratif sesuai ketentuan yang berlaku apabila di kemudian hari ditemukan data yang tidak benar, tidak valid, atau menyesatkan.</p>

                <h6>6. Perubahan Data dan Pengelola Akun</h6>
                <p>Pemberi kerja wajib memperbarui data lowongan pekerjaan apabila terdapat perubahan informasi, termasuk perubahan jumlah kebutuhan tenaga kerja, lokasi penempatan, masa berlaku lowongan, atau status pemenuhan lowongan.</p>
                <p>Apabila terdapat pergantian pengelola akun, pemberi kerja wajib melaporkan kepada Kementerian Ketenagakerjaan melalui kanal layanan resmi yang ditetapkan.</p>

                <h6>7. Larangan</h6>
                <p>Pemberi kerja dilarang menyampaikan lowongan pekerjaan yang:</p>
                <ol type="a">
                    <li>tidak benar, fiktif, atau menyesatkan;</li>
                    <li>memungut biaya kepada pencari kerja;</li>
                    <li>mengandung unsur diskriminasi yang bertentangan dengan peraturan perundang-undangan;</li>
                    <li>mengarah pada tindak pidana perdagangan orang, penipuan, eksploitasi, atau praktik ketenagakerjaan yang tidak sah;</li>
                    <li>tidak memiliki kejelasan pemberi kerja, jabatan, lokasi kerja, atau mekanisme rekrutmen;</li>
                    <li>menggunakan identitas perusahaan, instansi, atau pihak lain tanpa hak.</li>
                </ol>

                <h6>8. Penutup</h6>
                <p>Syarat dan Ketentuan ini berlaku sejak pemberi kerja menggunakan layanan Wajib Lapor Lowongan Pekerjaan.</p>
                <p>Dengan menggunakan layanan ini, pemberi kerja dianggap telah membaca, memahami, menyetujui, dan bersedia mematuhi seluruh ketentuan yang berlaku.</p>
            </div>
            <div class="modal-footer d-block">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" value="1" id="setujuSyaratCheckModal"<?php echo $termsAgreed ? ' checked' : ''; ?>>
                    <label class="form-check-label small" for="setujuSyaratCheckModal">
                        Saya menyetujui syarat dan ketentuan yang berlaku dan bersedia menerima konsekuensi hukum yang berlaku apabila di kemudian hari ditemukan data yang tidak benar, tidak valid, atau menyesatkan.
                    </label>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnSetujuDanSubmit">Setuju</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="pelaporanWizardModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Panduan Pelaporan Lowongan</h5>
            </div>
            <div class="modal-body">
                <div class="small text-muted mb-2" id="wizardStepIndicator">Step 1/2</div>
                <div id="wizardStep1">
                    <div class="small text-muted mb-2">Lengkapi dasar periode pelaporan terlebih dahulu.</div>
                    <label class="form-label mb-1">Pilih periode pelaporan lowongan kerja yang ingin anda laporkan</label>
                    <select class="form-select form-select-sm mb-2" id="wizardModalPeriodeTipe">
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                    <label class="form-label mb-1">Tanggal Mulai periode</label>
                    <input type="date" class="form-control form-control-sm" id="wizardModalPeriodeAnchor">
                </div>
                <div id="wizardStep2" style="display:none;">
                    <div class="small text-muted mb-2">Jumlah ini akan menentukan jumlah tab form lowongan.</div>
                    <label class="form-label mb-1">Berapa banyak lowongan kerja yang ingin anda Buka?</label>
                    <input type="number" min="1" max="50" class="form-control form-control-sm" id="wizardModalJumlahLowongan">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="wizardPrevBtn" style="display:none;">Kembali</button>
                <button type="button" class="btn btn-primary btn-sm" id="wizardNextBtn">Lanjut</button>
                <button type="button" class="btn btn-success btn-sm" id="wizardFinishBtn" style="display:none;">Mulai Isi Form</button>
            </div>
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
            'Periode Tipe',
            'Periode Anchor',
            'Jumlah ID Lowongan',
            'Daftar Jabatan (Pisahkan |)',
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
            'Status Pekerjaan',
            'Platform/Kanal',
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
        const wizardModalEl = document.getElementById('pelaporanWizardModal');
        const syaratKetentuanModalEl = document.getElementById('syaratKetentuanModal');
        const wizardStep1 = document.getElementById('wizardStep1');
        const wizardStep2 = document.getElementById('wizardStep2');
        const wizardStepIndicator = document.getElementById('wizardStepIndicator');
        const wizardPrevBtn = document.getElementById('wizardPrevBtn');
        const wizardNextBtn = document.getElementById('wizardNextBtn');
        const wizardFinishBtn = document.getElementById('wizardFinishBtn');
        const wizardModalPeriodeTipe = document.getElementById('wizardModalPeriodeTipe');
        const wizardModalPeriodeAnchor = document.getElementById('wizardModalPeriodeAnchor');
        const wizardModalJumlahLowongan = document.getElementById('wizardModalJumlahLowongan');
        const wizardPeriodeTipe = document.getElementById('wizardPeriodeTipe');
        const wizardPeriodeAnchor = document.getElementById('wizardPeriodeAnchor');
        const wizardJumlahLowongan = document.getElementById('wizardJumlahLowongan');
        const wizardDaftarJabatan = document.getElementById('wizardDaftarJabatan');
        const wizardSummaryPeriode = document.getElementById('wizardSummaryPeriode');
        const wizardSummaryJumlah = document.getElementById('wizardSummaryJumlah');
        const wizardTabProgressText = document.getElementById('wizardTabProgressText');
        const wizardValidationSummary = document.getElementById('wizardValidationSummary');
        const btnEditWizardFlow = document.getElementById('btnEditWizardFlow');
        const submitBtn = document.getElementById('btnSubmitPelaporan');
        const setujuSyaratValue = document.getElementById('setujuSyaratValue');
        const setujuSyaratCheckModal = document.getElementById('setujuSyaratCheckModal');
        const btnSetujuDanSubmit = document.getElementById('btnSetujuDanSubmit');
        const landingChoiceSection = document.getElementById('landingChoiceSection');
        const bulkToolsSection = document.getElementById('bulkToolsSection');
        const formPelaporanSection = document.getElementById('formPelaporanSection');
        const btnLandingBulk = document.getElementById('btnLandingBulk');
        const btnLandingForm = document.getElementById('btnLandingForm');
        const btnBulkToFormMode = document.getElementById('btnBulkToFormMode');
        const btnBulkToChooseMode = document.getElementById('btnBulkToChooseMode');
        const btnFormToChooseMode = document.getElementById('btnFormToChooseMode');
        const tabsNav = document.getElementById('lowonganTabsNav');
        const tabsContent = document.getElementById('lowonganTabsContent');
        const pelaporanForm = document.querySelector('form[method="POST"]');
        const initialLandingMode = document.body.getAttribute('data-initial-landing-mode') || '';
        let wizardStep = 1;

        function defaultValueByField(field) {
            if (field === 'jenis_kelamin') return 'Semua';
            if (field === 'masa_berlaku_mulai') return (wizardPeriodeAnchor && wizardPeriodeAnchor.value) ? wizardPeriodeAnchor.value : '';
            if (field === 'masa_berlaku_sampai') {
                const anchor = (wizardPeriodeAnchor && wizardPeriodeAnchor.value) ? new Date(wizardPeriodeAnchor.value) : null;
                if (anchor && !Number.isNaN(anchor.getTime())) {
                    anchor.setDate(anchor.getDate() + 30);
                    return anchor.toISOString().slice(0, 10);
                }
            }
            return '';
        }

        function showLandingMode(mode, options) {
            const opts = options || {};
            if (landingChoiceSection) landingChoiceSection.style.display = mode === 'choose' ? '' : 'none';
            if (bulkToolsSection) bulkToolsSection.style.display = mode === 'bulk' ? '' : 'none';
            if (formPelaporanSection) formPelaporanSection.style.display = mode === 'form' ? '' : 'none';

            if (mode === 'form' && opts.openWizard && wizardModalEl && typeof bootstrap !== 'undefined') {
                const wizardModal = bootstrap.Modal.getOrCreateInstance(wizardModalEl);
                setWizardStep(1);
                wizardModal.show();
            }
            if (mode === 'choose') {
                openTermsIfNeeded();
            }
        }

        function openTermsIfNeeded() {
            const alreadyAgreed = setujuSyaratValue && setujuSyaratValue.value === '1';
            if (alreadyAgreed) return;
            if (wizardValidationSummary) {
                wizardValidationSummary.style.display = 'none';
                wizardValidationSummary.innerHTML = '';
            }
            if (syaratKetentuanModalEl && typeof bootstrap !== 'undefined') {
                const termsModal = bootstrap.Modal.getOrCreateInstance(syaratKetentuanModalEl);
                termsModal.show();
            }
        }

        function collectLowonganValues() {
            if (!tabsContent) return [];
            const panes = Array.from(tabsContent.querySelectorAll('.tab-pane'));
            return panes.map((pane) => {
                const row = {};
                pane.querySelectorAll('.wizard-lowongan-field').forEach((el) => {
                    const field = el.getAttribute('data-field');
                    if (!field) return;
                    if (el.type === 'radio') {
                        if (el.checked) {
                            row[field] = String(el.value || '').trim();
                        } else if (!Object.prototype.hasOwnProperty.call(row, field)) {
                            row[field] = '';
                        }
                        return;
                    }
                    row[field] = (el.value || '').trim();
                });
                return row;
            });
        }

        function splitUrlLines(rawValue) {
            return String(rawValue || '')
                .split(/\r?\n/)
                .map((x) => x.trim())
                .filter((x) => x !== '');
        }

        const allowedPlatformChannels = ['Job Portal', 'Social Media', 'Lainnya'];
        const allowedKondisiFisik = ['Disabilitas', 'Non Disabilitas'];
        const allowedJenisDisabilitas = ['Tuna daksa', 'Tuna grahita', 'Tuna wicara', 'Tuna netra', 'Tuna rungu', 'Tuna ganda'];
        const sourceOptionsByChannel = {
            'Job Portal': ['Jobstreet', 'Glints', 'Kalibrr', 'Kitalulus', 'HiredToday', 'Toploker', 'Redy', 'Tambahkan Isian'],
            'Social Media': ['Instagram', 'Facebook', 'Tiktok', 'Twitter', 'Tambahkan Isian'],
        };

        function parseCsvList(rawValue, allowedValues) {
            const parts = String(rawValue || '')
                .split(',')
                .map((x) => x.trim())
                .filter((x) => x !== '');
            const unique = [];
            parts.forEach((part) => {
                if (allowedValues && allowedValues.length && !allowedValues.includes(part)) {
                    return;
                }
                if (!unique.includes(part)) {
                    unique.push(part);
                }
            });
            return unique;
        }

        function parsePlatformValue(rawValue) {
            return parseCsvList(rawValue, allowedPlatformChannels);
        }

        function parsePostingRows(rawValue) {
            return splitUrlLines(rawValue).map((line) => {
                const parts = line.split('||');
                if (parts.length >= 3) {
                    return {
                        channel: String(parts[0] || '').trim(),
                        source: String(parts[1] || '').trim(),
                        url: String(parts.slice(2).join('||') || '').trim(),
                    };
                }
                return {
                    channel: '',
                    source: '',
                    url: String(line || '').trim(),
                };
            });
        }

        function serializePostingRows(rows) {
            return rows
                .filter((row) => String(row.url || '').trim() !== '')
                .map((row) => [row.channel || '', row.source || '', row.url || ''].join('||'))
                .join('\n');
        }

        function getUrlMainRadioName(group) {
            const tabIndex = String(group.getAttribute('data-tab-index') || '0');
            return 'wizard_url_main_' + tabIndex;
        }

        function getTabPaneForElement(el) {
            return el ? el.closest('.tab-pane') : null;
        }

        function getSelectedPlatformsInPane(tabPane) {
            if (!tabPane) return [];
            const group = tabPane.querySelector('.wizard-platform-group');
            if (!group) return [];
            const checked = Array.from(group.querySelectorAll('.wizard-platform-check:checked'))
                .map((x) => String(x.value || '').trim())
                .filter((x) => allowedPlatformChannels.includes(x));
            return Array.from(new Set(checked));
        }

        function getMetodePublikasiInPane(tabPane) {
            if (!tabPane) return 'Online';
            const checked = tabPane.querySelector('.wizard-metode-publikasi-radio:checked');
            const value = checked ? String(checked.value || '').trim() : 'Online';
            return value === 'Offline' ? 'Offline' : 'Online';
        }

        function applyMetodePublikasiVisibility(tabPane) {
            if (!tabPane) return;
            const metode = getMetodePublikasiInPane(tabPane);
            const isOnline = metode === 'Online';

            tabPane.querySelectorAll('.wizard-online-only').forEach((el) => {
                el.style.display = isOnline ? '' : 'none';
            });
            tabPane.querySelectorAll('.wizard-offline-only').forEach((el) => {
                el.style.display = isOnline ? 'none' : '';
            });

            if (!isOnline) {
                const platformGroup = tabPane.querySelector('.wizard-platform-group');
                if (platformGroup) {
                    platformGroup.querySelectorAll('.wizard-platform-check').forEach((cb) => {
                        cb.checked = false;
                    });
                    syncPlatformGroup(platformGroup);
                }
                const urlGroup = tabPane.querySelector('.wizard-url-group');
                if (urlGroup) {
                    const hiddenUrl = urlGroup.querySelector('.wizard-lowongan-field[data-field="alamat_url_postingan_loker"]');
                    const hiddenMain = urlGroup.querySelector('.wizard-lowongan-field[data-field="alamat_url_postingan_loker_main"]');
                    if (hiddenUrl) hiddenUrl.value = '';
                    if (hiddenMain) hiddenMain.value = '';
                    const list = urlGroup.querySelector('.wizard-url-list');
                    if (list) list.innerHTML = '';
                }
            }
        }

        function syncPlatformGroup(group) {
            if (!group) return;
            const hiddenField = group.querySelector('.wizard-lowongan-field[data-field="platform_kanal"]');
            const checked = Array.from(group.querySelectorAll('.wizard-platform-check:checked'))
                .map((x) => String(x.value || '').trim())
                .filter((x) => allowedPlatformChannels.includes(x));
            if (hiddenField) {
                hiddenField.value = Array.from(new Set(checked)).join(', ');
            }
        }

        function syncKondisiGroup(group) {
            if (!group) return;
            const hiddenKondisi = group.querySelector('.wizard-lowongan-field[data-field="kondisi_fisik"]');
            const hiddenJenis = group.querySelector('.wizard-lowongan-field[data-field="jenis_disabilitas_tidak_diperbolehkan"]');
            const checkedKondisi = Array.from(group.querySelectorAll('.wizard-kondisi-check:checked'))
                .map((x) => String(x.value || '').trim())
                .filter((x) => allowedKondisiFisik.includes(x));
            if (hiddenKondisi) {
                hiddenKondisi.value = Array.from(new Set(checkedKondisi)).join(', ');
            }
            const checkedJenis = Array.from(group.querySelectorAll('.wizard-disabilitas-jenis-check:checked'))
                .map((x) => String(x.value || '').trim())
                .filter((x) => allowedJenisDisabilitas.includes(x));
            const hasDisabilitas = checkedKondisi.includes('Disabilitas');
            if (hiddenJenis) {
                hiddenJenis.value = hasDisabilitas ? Array.from(new Set(checkedJenis)).join(', ') : '';
            }
            if (!hasDisabilitas) {
                group.querySelectorAll('.wizard-disabilitas-jenis-check').forEach((el) => {
                    el.checked = false;
                });
            }
        }

        function applyKondisiFisikVisibility(tabPane) {
            if (!tabPane) return;
            const group = tabPane.querySelector('.wizard-kondisi-group');
            if (!group) return;
            const checkedKondisi = Array.from(group.querySelectorAll('.wizard-kondisi-check:checked'))
                .map((x) => String(x.value || '').trim());
            const showDisabilitas = checkedKondisi.includes('Disabilitas');
            const disabilitasWrap = group.querySelector('.wizard-disabilitas-only');
            if (disabilitasWrap) {
                disabilitasWrap.style.display = showDisabilitas ? '' : 'none';
            }
            syncKondisiGroup(group);
        }

        function buildSourceInput(channel, sourceValue) {
            const sourceWrap = document.createElement('div');
            sourceWrap.className = 'wizard-url-source-wrap';
            if (channel === 'Lainnya') {
                const textInput = document.createElement('input');
                textInput.type = 'text';
                textInput.className = 'form-control form-control-sm wizard-url-source-input';
                textInput.placeholder = 'Isi sumber lainnya';
                textInput.value = sourceValue || '';
                sourceWrap.appendChild(textInput);
                return sourceWrap;
            }

            const select = document.createElement('select');
            select.className = 'form-select form-select-sm wizard-url-source-select';
            const options = ['Pilih Sumber'].concat(sourceOptionsByChannel[channel] || []);
            const knownOptions = sourceOptionsByChannel[channel] || [];
            const hasCustomValue = sourceValue && !knownOptions.includes(sourceValue);
            options.forEach((opt) => {
                const option = document.createElement('option');
                option.value = opt === 'Pilih Sumber' ? '' : opt;
                option.textContent = opt;
                if (opt === sourceValue || (hasCustomValue && opt === 'Tambahkan Isian')) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
            sourceWrap.appendChild(select);

            const customInput = document.createElement('input');
            customInput.type = 'text';
            customInput.className = 'form-control form-control-sm wizard-url-source-custom';
            customInput.placeholder = 'Isi sumber lainnya';
            customInput.value = hasCustomValue ? sourceValue : '';
            customInput.style.display = select.value === 'Tambahkan Isian' ? '' : 'none';
            sourceWrap.appendChild(customInput);
            return sourceWrap;
        }

        function resolveSourceFromRow(row) {
            if (!row) return '';
            const sourceText = row.querySelector('.wizard-url-source-input');
            if (sourceText) {
                return String(sourceText.value || '').trim();
            }
            const sourceSelect = row.querySelector('.wizard-url-source-select');
            const sourceCustom = row.querySelector('.wizard-url-source-custom');
            if (!sourceSelect) return '';
            if (sourceSelect.value === 'Tambahkan Isian') {
                return sourceCustom ? String(sourceCustom.value || '').trim() : '';
            }
            return String(sourceSelect.value || '').trim();
        }

        function buildUrlRow(channel, sourceValue, urlValue, radioName, isMain) {
            const row = document.createElement('div');
            row.className = 'wizard-url-row';
            row.setAttribute('data-channel', channel);

            const sourceCell = document.createElement('div');
            sourceCell.appendChild(buildSourceInput(channel, sourceValue));
            row.appendChild(sourceCell);

            const urlInput = document.createElement('input');
            urlInput.type = 'url';
            urlInput.className = 'form-control form-control-sm wizard-url-input';
            urlInput.placeholder = 'https://karirhub.kemnaker.go.id/...';
            urlInput.value = urlValue || '';
            row.appendChild(urlInput);

            const mainWrap = document.createElement('label');
            mainWrap.className = 'wizard-url-main-wrap';
            const mainRadio = document.createElement('input');
            mainRadio.type = 'radio';
            mainRadio.className = 'form-check-input mt-0 wizard-url-main';
            mainRadio.name = radioName;
            mainRadio.checked = Boolean(isMain);
            mainWrap.appendChild(mainRadio);
            mainWrap.append('URL Utama');
            row.appendChild(mainWrap);

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-outline-danger btn-sm wizard-url-remove';
            removeBtn.title = 'Hapus URL';
            removeBtn.innerHTML = '<i class="bi bi-dash"></i>';
            row.appendChild(removeBtn);

            return row;
        }

        function syncUrlGroup(group) {
            if (!group) return;
            const hiddenField = group.querySelector('.wizard-lowongan-field[data-field="alamat_url_postingan_loker"]');
            const hiddenMainField = group.querySelector('.wizard-lowongan-field[data-field="alamat_url_postingan_loker_main"]');
            const sections = Array.from(group.querySelectorAll('.wizard-url-section'));
            const rows = Array.from(group.querySelectorAll('.wizard-url-row'));

            const collected = [];
            let selectedMain = '';
            rows.forEach((row) => {
                const channel = String(row.getAttribute('data-channel') || '').trim();
                const source = resolveSourceFromRow(row);
                const urlInput = row.querySelector('.wizard-url-input');
                const radio = row.querySelector('.wizard-url-main');
                const url = urlInput ? String(urlInput.value || '').trim() : '';
                if (url !== '' || source !== '') {
                    collected.push({ channel, source, url });
                }
                if (radio && radio.checked && url !== '') {
                    selectedMain = url;
                }
            });
            if (collected.length === 1) {
                selectedMain = collected[0].url;
            }
            if (hiddenField) {
                hiddenField.value = serializePostingRows(collected);
            }
            if (hiddenMainField) {
                hiddenMainField.value = selectedMain;
            }

            sections.forEach((section) => {
                const sectionRows = Array.from(section.querySelectorAll('.wizard-url-row'));
                sectionRows.forEach((row, rowIdx) => {
                    const removeBtn = row.querySelector('.wizard-url-remove');
                    if (removeBtn) {
                        removeBtn.disabled = sectionRows.length <= 1;
                    }
                    const radio = row.querySelector('.wizard-url-main');
                    const urlInput = row.querySelector('.wizard-url-input');
                    const url = urlInput ? String(urlInput.value || '').trim() : '';
                    if (radio) {
                        if (selectedMain !== '' && url !== '') {
                            radio.checked = url === selectedMain;
                        } else if (sectionRows.length === 1 && rowIdx === 0) {
                            radio.checked = true;
                        }
                    }
                });
            });
        }

        function renderUrlGroup(group) {
            if (!group) return;
            const list = group.querySelector('.wizard-url-list');
            const hiddenField = group.querySelector('.wizard-lowongan-field[data-field="alamat_url_postingan_loker"]');
            const hiddenMainField = group.querySelector('.wizard-lowongan-field[data-field="alamat_url_postingan_loker_main"]');
            if (!list || !hiddenField) return;

            const pane = getTabPaneForElement(group);
            const metodePublikasi = getMetodePublikasiInPane(pane);
            const selectedChannels = getSelectedPlatformsInPane(pane);
            const existingRows = parsePostingRows(hiddenField.value || '');
            const mainUrl = hiddenMainField ? String(hiddenMainField.value || '').trim() : '';
            const radioName = getUrlMainRadioName(group);
            list.innerHTML = '';

            if (metodePublikasi !== 'Online') {
                hiddenField.value = '';
                if (hiddenMainField) hiddenMainField.value = '';
                return;
            }

            if (!selectedChannels.length) {
                const emptyHelp = document.createElement('div');
                emptyHelp.className = 'small text-muted';
                emptyHelp.textContent = 'Pilih Platform/Kanal terlebih dahulu untuk menambahkan sumber URL.';
                list.appendChild(emptyHelp);
                hiddenField.value = '';
                if (hiddenMainField) hiddenMainField.value = '';
                return;
            }

            selectedChannels.forEach((channel) => {
                const section = document.createElement('div');
                section.className = 'wizard-url-section';
                section.setAttribute('data-channel', channel);

                const head = document.createElement('div');
                head.className = 'wizard-url-section-head';
                const title = document.createElement('div');
                title.className = 'small fw-semibold text-primary';
                title.textContent = channel;
                const addBtn = document.createElement('button');
                addBtn.type = 'button';
                addBtn.className = 'btn btn-outline-primary btn-sm wizard-url-add';
                addBtn.innerHTML = '<i class="bi bi-plus-lg me-1"></i>Tambah URL';
                head.appendChild(title);
                head.appendChild(addBtn);
                section.appendChild(head);

                const colHead = document.createElement('div');
                colHead.className = 'wizard-url-row small text-muted fw-semibold';
                colHead.innerHTML = '<div>Sumber</div><div>URL</div><div>URL Utama</div><div></div>';
                section.appendChild(colHead);

                const rowsWrap = document.createElement('div');
                rowsWrap.className = 'wizard-url-rows-wrap';
                const channelRows = existingRows.filter((row) => {
                    const rowChannel = String(row.channel || '').trim();
                    if (rowChannel === channel) return true;
                    return rowChannel === '' && selectedChannels.length === 1;
                });
                const useRows = channelRows.length ? channelRows : [{ channel, source: '', url: '' }];
                useRows.forEach((rowData, idx) => {
                    rowsWrap.appendChild(buildUrlRow(
                        channel,
                        String(rowData.source || ''),
                        String(rowData.url || ''),
                        radioName,
                        String(rowData.url || '') !== '' && String(rowData.url || '') === mainUrl && idx === 0
                    ));
                });
                section.appendChild(rowsWrap);
                list.appendChild(section);
            });

            syncUrlGroup(group);
        }

        function setupAllUrlGroups(scope) {
            const root = scope || document;
            Array.from(root.querySelectorAll('.wizard-url-group')).forEach((group) => {
                renderUrlGroup(group);
            });
        }

        function renderLowonganTabs(count, values) {
            if (!tabsNav || !tabsContent) return;
            const safeCount = Math.max(1, Math.min(50, parseInt(String(count), 10) || 1));
            const data = values && values.length ? values : collectLowonganValues();
            const basePane = tabsContent.querySelector('.tab-pane');
            if (!basePane) return;
            tabsNav.innerHTML = '';
            tabsContent.innerHTML = '';
            for (let i = 0; i < safeCount; i += 1) {
                const navItem = document.createElement('li');
                navItem.className = 'nav-item';
                navItem.setAttribute('role', 'presentation');
                const navBtn = document.createElement('button');
                navBtn.className = 'nav-link' + (i === 0 ? ' active' : '');
                navBtn.id = 'lowongan-tab-' + i;
                navBtn.setAttribute('data-bs-toggle', 'tab');
                navBtn.setAttribute('data-bs-target', '#lowongan-pane-' + i);
                navBtn.type = 'button';
                navBtn.setAttribute('role', 'tab');
                navBtn.innerHTML = 'Lowongan ' + (i + 1) + '<span class="badge text-bg-secondary ms-1 wizard-tab-badge" id="wizardTabBadge-' + i + '">Belum lengkap</span>';
                navItem.appendChild(navBtn);
                tabsNav.appendChild(navItem);

                const pane = basePane.cloneNode(true);
                pane.id = 'lowongan-pane-' + i;
                pane.className = 'tab-pane fade' + (i === 0 ? ' show active' : '');
                pane.setAttribute('aria-labelledby', 'lowongan-tab-' + i);
                const labelJabatan = pane.querySelector('label');
                if (labelJabatan) {
                    labelJabatan.textContent = 'Jabatan (Lowongan ' + (i + 1) + ')';
                }
                pane.querySelectorAll('.wizard-metode-publikasi-radio').forEach((radio) => {
                    const value = String(radio.value || '').toLowerCase();
                    const newId = 'metode-' + value + '-' + i;
                    const oldId = radio.id;
                    radio.name = 'metode_publikasi_loker[' + i + ']';
                    radio.id = newId;
                    const radioLabel = oldId ? pane.querySelector('label[for="' + oldId + '"]') : null;
                    if (radioLabel) {
                        radioLabel.setAttribute('for', newId);
                    }
                });

                const rowData = data[i] || {};
                const selectedPlatforms = parsePlatformValue(rowData.platform_kanal || '');
                const selectedKondisi = parseCsvList(rowData.kondisi_fisik || '', allowedKondisiFisik);
                const selectedJenisDisabilitas = parseCsvList(rowData.jenis_disabilitas_tidak_diperbolehkan || '', allowedJenisDisabilitas);
                pane.querySelectorAll('.wizard-platform-check').forEach((checkbox) => {
                    const optionKey = String(checkbox.value || '').toLowerCase().replace(/\s+/g, '-');
                    const newId = 'platform-' + i + '-' + optionKey;
                    checkbox.id = newId;
                    const cbLabel = checkbox.closest('.form-check') ? checkbox.closest('.form-check').querySelector('.form-check-label') : null;
                    if (cbLabel) {
                        cbLabel.setAttribute('for', newId);
                    }
                    checkbox.checked = selectedPlatforms.includes(String(checkbox.value || '').trim());
                });
                pane.querySelectorAll('.wizard-kondisi-check').forEach((checkbox) => {
                    const optionKey = String(checkbox.value || '').toLowerCase().replace(/\s+/g, '-');
                    const newId = 'kondisi-' + i + '-' + optionKey;
                    checkbox.id = newId;
                    const cbLabel = checkbox.closest('.form-check') ? checkbox.closest('.form-check').querySelector('.form-check-label') : null;
                    if (cbLabel) {
                        cbLabel.setAttribute('for', newId);
                    }
                    checkbox.checked = selectedKondisi.includes(String(checkbox.value || '').trim());
                });
                pane.querySelectorAll('.wizard-disabilitas-jenis-check').forEach((checkbox) => {
                    const optionKey = String(checkbox.value || '').toLowerCase().replace(/\s+/g, '-');
                    const newId = 'jenis-disabilitas-' + i + '-' + optionKey;
                    checkbox.id = newId;
                    const cbLabel = checkbox.closest('.form-check') ? checkbox.closest('.form-check').querySelector('.form-check-label') : null;
                    if (cbLabel) {
                        cbLabel.setAttribute('for', newId);
                    }
                    checkbox.checked = selectedJenisDisabilitas.includes(String(checkbox.value || '').trim());
                });
                pane.querySelectorAll('.wizard-lowongan-field').forEach((el) => {
                    const field = el.getAttribute('data-field');
                    const rawValue = Object.prototype.hasOwnProperty.call(rowData, field) ? rowData[field] : defaultValueByField(field);
                    if (el.type === 'radio') {
                        const raw = rawValue == null ? '' : String(rawValue);
                        el.checked = raw !== '' && String(el.value || '') === raw;
                    } else {
                        el.value = rawValue == null ? '' : String(rawValue);
                    }
                    el.setAttribute('data-tab-index', String(i));
                    el.classList.remove('is-invalid');
                });
                applyKondisiFisikVisibility(pane);
                applyMetodePublikasiVisibility(pane);
                const urlGroup = pane.querySelector('.wizard-url-group');
                if (urlGroup) {
                    const hiddenUrlField = urlGroup.querySelector('.wizard-lowongan-field[data-field="alamat_url_postingan_loker"]');
                    if (hiddenUrlField && !Object.prototype.hasOwnProperty.call(rowData, 'alamat_url_postingan_loker')) {
                        hiddenUrlField.value = defaultValueByField('alamat_url_postingan_loker');
                    }
                    const platformGroup = pane.querySelector('.wizard-platform-group');
                    syncPlatformGroup(platformGroup);
                    renderUrlGroup(urlGroup);
                }
                tabsContent.appendChild(pane);
            }
            validateTabs(false);
        }

        function validateTabs(showDetails) {
            const panes = tabsContent ? Array.from(tabsContent.querySelectorAll('.tab-pane')) : [];
            let complete = 0;
            let firstInvalidField = null;
            const issues = [];

            panes.forEach((pane, idx) => {
                const fields = Array.from(pane.querySelectorAll('.wizard-lowongan-field'));
                const row = {};
                fields.forEach((el) => {
                    const field = el.getAttribute('data-field');
                    if (!field) return;
                    if (el.type === 'radio') {
                        if (el.checked) {
                            row[field] = String(el.value || '').trim();
                        } else if (!Object.prototype.hasOwnProperty.call(row, field)) {
                            row[field] = '';
                        }
                        return;
                    }
                    row[field] = (el.value || '').trim();
                });
                const rowIssues = [];
                const handledRadioFields = new Set();
                fields.forEach((el) => {
                    const field = el.getAttribute('data-field');
                    if (!field) return;
                    const required = el.getAttribute('data-required') === '1';
                    let value = (el.value || '').trim();
                    if (el.type === 'radio') {
                        if (handledRadioFields.has(field)) {
                            return;
                        }
                        handledRadioFields.add(field);
                        const checkedRadio = pane.querySelector('.wizard-lowongan-field[data-field="' + field + '"]:checked');
                        value = checkedRadio ? String(checkedRadio.value || '').trim() : '';
                    }
                    const wrapper = el.closest('[class*="col-"]');
                    const labelNode = wrapper ? wrapper.querySelector('label') : null;
                    const label = String((labelNode ? labelNode.textContent : '') || field || 'Field').trim();
                    const empty = required && value === '';
                    let validationTarget = el;
                    if (field === 'alamat_url_postingan_loker') {
                        const urlGroup = el.closest('.wizard-url-group');
                        const firstVisibleInput = urlGroup ? urlGroup.querySelector('.wizard-url-input') : null;
                        if (firstVisibleInput) {
                            validationTarget = firstVisibleInput;
                        }
                    }
                    if (empty) {
                        rowIssues.push(label + ' wajib diisi');
                    }
                    if (showDetails) {
                        validationTarget.classList.toggle('is-invalid', empty);
                        if (empty && !firstInvalidField) {
                            firstInvalidField = validationTarget;
                        }
                    } else {
                        validationTarget.classList.remove('is-invalid');
                    }
                });
                if (row.jumlah_kebutuhan && (!/^\d+$/.test(row.jumlah_kebutuhan) || parseInt(row.jumlah_kebutuhan, 10) <= 0)) {
                    rowIssues.push('Jumlah Kebutuhan harus > 0');
                }
                if (row.usia_min && row.usia_max && parseInt(row.usia_min, 10) > parseInt(row.usia_max, 10)) {
                    rowIssues.push('Usia Min tidak boleh > Usia Max');
                }
                if (row.masa_berlaku_mulai && row.masa_berlaku_sampai && row.masa_berlaku_mulai > row.masa_berlaku_sampai) {
                    rowIssues.push('Masa Berlaku Mulai tidak boleh lebih akhir');
                }
                const kondisiFisik = parseCsvList(row.kondisi_fisik || '', allowedKondisiFisik);
                if (!kondisiFisik.length) {
                    rowIssues.push('Kondisi Fisik wajib dipilih');
                }
                const metodePublikasi = (row.metode_publikasi_loker || '').trim();
                if (!['Online', 'Offline'].includes(metodePublikasi)) {
                    rowIssues.push('Metode publikasi loker wajib dipilih');
                }
                if (metodePublikasi === 'Online') {
                    const urlLines = splitUrlLines(row.alamat_url_postingan_loker || '');
                    const postingRows = parsePostingRows(row.alamat_url_postingan_loker || '');
                    const mainUrl = String(row.alamat_url_postingan_loker_main || '').trim();
                    if (!parsePlatformValue(row.platform_kanal || '').length) {
                        rowIssues.push('Platform/Kanal wajib dipilih');
                    }
                    if (!postingRows.length) {
                        rowIssues.push('Alamat URL Postingan Loker wajib diisi');
                    }
                    if (urlLines.length > 1 && mainUrl === '') {
                        rowIssues.push('Jika URL lebih dari satu, pilih URL Utama');
                    }
                    if (mainUrl !== '' && !urlLines.includes(mainUrl)) {
                        rowIssues.push('URL Utama harus ada di daftar URL');
                    }
                    if (postingRows.some((r) => String(r.url || '').trim() !== '' && String(r.source || '').trim() === '')) {
                        rowIssues.push('Sumber wajib diisi untuk setiap URL');
                    }
                    const selectedPlatforms = parsePlatformValue(row.platform_kanal || '');
                    if (postingRows.some((r) => String(r.channel || '').trim() !== '' && !selectedPlatforms.includes(String(r.channel || '').trim()))) {
                        rowIssues.push('Sumber URL tidak sesuai Platform/Kanal terpilih');
                    }
                }
                if (metodePublikasi === 'Offline') {
                    if (!String(row.media_publikasi_offline || '').trim()) {
                        rowIssues.push('Media yang digunakan untuk publikasi wajib diisi');
                    }
                    if (!String(row.alasan_metode_offline || '').trim()) {
                        rowIssues.push('Alasan menggunakan metode offline wajib diisi');
                    }
                }

                const ok = rowIssues.length === 0;
                const badge = document.getElementById('wizardTabBadge-' + idx);
                if (badge) {
                    badge.className = 'badge ms-1 wizard-tab-badge ' + (ok ? 'text-bg-success' : 'text-bg-secondary');
                    badge.textContent = ok ? 'Lengkap' : 'Belum lengkap';
                }
                if (ok) complete += 1;
                if (!ok) {
                    issues.push('Lowongan ' + (idx + 1) + ': ' + rowIssues[0]);
                }
            });

            if (wizardTabProgressText) {
                wizardTabProgressText.textContent = complete === panes.length
                    ? 'Semua tab lengkap. Siap submit.'
                    : 'Tab lengkap: ' + complete + '/' + panes.length;
            }
            if (submitBtn) {
                submitBtn.disabled = complete !== panes.length;
            }
            if (wizardDaftarJabatan) {
                const jabatanValues = Array.from(document.querySelectorAll('.wizard-lowongan-field[data-field="jabatan"]'))
                    .map((x) => (x.value || '').trim())
                    .filter((x) => x !== '');
                wizardDaftarJabatan.value = jabatanValues.join('\n');
            }
            if (wizardValidationSummary) {
                if (showDetails && issues.length) {
                    wizardValidationSummary.style.display = '';
                    wizardValidationSummary.innerHTML = '<strong>Perbaiki data tab:</strong><br>' + issues.slice(0, 5).join('<br>');
                } else {
                    wizardValidationSummary.style.display = 'none';
                    wizardValidationSummary.innerHTML = '';
                }
            }
            if (showDetails && firstInvalidField) {
                const tabIndex = parseInt(firstInvalidField.getAttribute('data-tab-index') || '0', 10);
                const trigger = document.getElementById('lowongan-tab-' + tabIndex);
                if (trigger) {
                    bootstrap.Tab.getOrCreateInstance(trigger).show();
                }
                firstInvalidField.focus();
            }
            return complete === panes.length && panes.length > 0;
        }

        document.addEventListener('input', function (evt) {
            if (evt.target && evt.target.classList && evt.target.classList.contains('wizard-lowongan-field')) {
                validateTabs(false);
                return;
            }
            if (evt.target && evt.target.classList && evt.target.classList.contains('wizard-url-input')) {
                const group = evt.target.closest('.wizard-url-group');
                syncUrlGroup(group);
                validateTabs(false);
                return;
            }
            if (evt.target && evt.target.classList && (evt.target.classList.contains('wizard-url-source-input') || evt.target.classList.contains('wizard-url-source-custom'))) {
                const group = evt.target.closest('.wizard-url-group');
                syncUrlGroup(group);
                validateTabs(false);
            }
        });
        document.addEventListener('change', function (evt) {
            if (evt.target && evt.target.classList && evt.target.classList.contains('wizard-kondisi-check')) {
                const group = evt.target.closest('.wizard-kondisi-group');
                const tabPane = getTabPaneForElement(group);
                applyKondisiFisikVisibility(tabPane);
                validateTabs(false);
                return;
            }
            if (evt.target && evt.target.classList && evt.target.classList.contains('wizard-disabilitas-jenis-check')) {
                const group = evt.target.closest('.wizard-kondisi-group');
                syncKondisiGroup(group);
                validateTabs(false);
                return;
            }
            if (evt.target && evt.target.classList && evt.target.classList.contains('wizard-metode-publikasi-radio')) {
                const tabPane = getTabPaneForElement(evt.target);
                applyMetodePublikasiVisibility(tabPane);
                const urlGroup = tabPane ? tabPane.querySelector('.wizard-url-group') : null;
                if (urlGroup) {
                    renderUrlGroup(urlGroup);
                }
                validateTabs(false);
                return;
            }
            if (evt.target && evt.target.classList && evt.target.classList.contains('wizard-lowongan-field')) {
                validateTabs(false);
                return;
            }
            if (evt.target && evt.target.classList && evt.target.classList.contains('wizard-platform-check')) {
                const platformGroup = evt.target.closest('.wizard-platform-group');
                syncPlatformGroup(platformGroup);
                const tabPane = getTabPaneForElement(platformGroup);
                const urlGroup = tabPane ? tabPane.querySelector('.wizard-url-group') : null;
                renderUrlGroup(urlGroup);
                validateTabs(false);
                return;
            }
            if (evt.target && evt.target.classList && evt.target.classList.contains('wizard-url-input')) {
                const group = evt.target.closest('.wizard-url-group');
                syncUrlGroup(group);
                validateTabs(false);
                return;
            }
            if (evt.target && evt.target.classList && evt.target.classList.contains('wizard-url-source-select')) {
                const sourceWrap = evt.target.closest('.wizard-url-source-wrap');
                const customInput = sourceWrap ? sourceWrap.querySelector('.wizard-url-source-custom') : null;
                if (customInput) {
                    customInput.style.display = evt.target.value === 'Tambahkan Isian' ? '' : 'none';
                    if (evt.target.value !== 'Tambahkan Isian') {
                        customInput.value = '';
                    } else {
                        customInput.focus();
                    }
                }
                const group = evt.target.closest('.wizard-url-group');
                syncUrlGroup(group);
                validateTabs(false);
                return;
            }
            if (evt.target && evt.target.classList && evt.target.classList.contains('wizard-url-main')) {
                const group = evt.target.closest('.wizard-url-group');
                syncUrlGroup(group);
                validateTabs(false);
            }
        });
        document.addEventListener('click', function (evt) {
            const addBtn = evt.target ? evt.target.closest('.wizard-url-add') : null;
            if (addBtn) {
                const section = addBtn.closest('.wizard-url-section');
                const group = addBtn.closest('.wizard-url-group');
                const rowsWrap = section ? section.querySelector('.wizard-url-rows-wrap') : null;
                if (!group || !section || !rowsWrap) return;
                const channel = String(section.getAttribute('data-channel') || '').trim();
                const row = buildUrlRow(channel, '', '', getUrlMainRadioName(group), false);
                rowsWrap.appendChild(row);
                syncUrlGroup(group);
                const input = row.querySelector('.wizard-url-input');
                if (input) input.focus();
                return;
            }
            const removeBtn = evt.target ? evt.target.closest('.wizard-url-remove') : null;
            if (removeBtn) {
                const group = removeBtn.closest('.wizard-url-group');
                const list = group ? group.querySelector('.wizard-url-list') : null;
                const rows = list ? list.querySelectorAll('.wizard-url-row') : [];
                const row = removeBtn.closest('.wizard-url-row');
                if (!group || !list || !row) return;
                if (rows.length <= 1) {
                    const input = row.querySelector('.wizard-url-input');
                    if (input) input.value = '';
                } else {
                    row.remove();
                }
                syncUrlGroup(group);
                validateTabs(false);
            }
        });

        function applyWizardSummary() {
            if (!wizardSummaryPeriode || !wizardSummaryJumlah) return;
            const tipe = (wizardPeriodeTipe && wizardPeriodeTipe.value ? wizardPeriodeTipe.value : 'monthly').toUpperCase();
            const anchor = wizardPeriodeAnchor && wizardPeriodeAnchor.value ? wizardPeriodeAnchor.value : '';
            wizardSummaryPeriode.textContent = 'Periode ' + tipe + ' - ' + anchor;
            wizardSummaryJumlah.textContent = wizardJumlahLowongan && wizardJumlahLowongan.value ? wizardJumlahLowongan.value : '1';
        }

        function setWizardStep(step) {
            wizardStep = step;
            const isStep1 = step === 1;
            if (wizardStep1) wizardStep1.style.display = isStep1 ? '' : 'none';
            if (wizardStep2) wizardStep2.style.display = isStep1 ? 'none' : '';
            if (wizardPrevBtn) wizardPrevBtn.style.display = isStep1 ? 'none' : '';
            if (wizardNextBtn) wizardNextBtn.style.display = isStep1 ? '' : 'none';
            if (wizardFinishBtn) wizardFinishBtn.style.display = isStep1 ? 'none' : '';
            if (wizardStepIndicator) wizardStepIndicator.textContent = isStep1 ? 'Step 1/3' : 'Step 2/3';
        }

        if (wizardModalEl && typeof bootstrap !== 'undefined') {
            const wizardModal = new bootstrap.Modal(wizardModalEl);
            const forceOpen = document.body.getAttribute('data-wizard-force-open') === '1';
            if (wizardModalPeriodeTipe && wizardPeriodeTipe) wizardModalPeriodeTipe.value = wizardPeriodeTipe.value || 'monthly';
            if (wizardModalPeriodeAnchor && wizardPeriodeAnchor) wizardModalPeriodeAnchor.value = wizardPeriodeAnchor.value || new Date().toISOString().slice(0, 10);
            if (wizardModalJumlahLowongan && wizardJumlahLowongan) wizardModalJumlahLowongan.value = wizardJumlahLowongan.value || '1';

            if (forceOpen) {
                setWizardStep(1);
                wizardModal.show();
            }
            if (btnEditWizardFlow) {
                btnEditWizardFlow.addEventListener('click', function () {
                    if (wizardModalJumlahLowongan && wizardJumlahLowongan) wizardModalJumlahLowongan.value = wizardJumlahLowongan.value || '1';
                    setWizardStep(1);
                    wizardModal.show();
                });
            }
            if (wizardNextBtn) {
                wizardNextBtn.addEventListener('click', function () {
                    if (!wizardModalPeriodeAnchor || !wizardModalPeriodeAnchor.value) {
                        wizardModalPeriodeAnchor && wizardModalPeriodeAnchor.focus();
                        return;
                    }
                    setWizardStep(2);
                });
            }
            if (wizardPrevBtn) {
                wizardPrevBtn.addEventListener('click', function () {
                    setWizardStep(1);
                });
            }
            if (wizardFinishBtn) {
                wizardFinishBtn.addEventListener('click', function () {
                    const count = Math.max(1, Math.min(50, parseInt((wizardModalJumlahLowongan && wizardModalJumlahLowongan.value) || '1', 10) || 1));
                    if (wizardPeriodeTipe && wizardModalPeriodeTipe) wizardPeriodeTipe.value = wizardModalPeriodeTipe.value;
                    if (wizardPeriodeAnchor && wizardModalPeriodeAnchor) wizardPeriodeAnchor.value = wizardModalPeriodeAnchor.value;
                    if (wizardJumlahLowongan) wizardJumlahLowongan.value = String(count);
                    renderLowonganTabs(count, collectLowonganValues());
                    applyWizardSummary();
                    wizardModal.hide();
                });
            }
        }

        renderLowonganTabs(parseInt((wizardJumlahLowongan && wizardJumlahLowongan.value) || '1', 10) || 1, collectLowonganValues());
        setupAllUrlGroups(tabsContent || document);
        applyWizardSummary();
        if (btnLandingBulk) {
            btnLandingBulk.addEventListener('click', function () {
                showLandingMode('bulk');
            });
        }
        if (btnLandingForm) {
            btnLandingForm.addEventListener('click', function () {
                showLandingMode('form', { openWizard: true });
            });
        }
        if (btnBulkToFormMode) {
            btnBulkToFormMode.addEventListener('click', function () {
                showLandingMode('form', { openWizard: true });
            });
        }
        if (btnBulkToChooseMode) {
            btnBulkToChooseMode.addEventListener('click', function () {
                showLandingMode('choose');
            });
        }
        if (btnFormToChooseMode) {
            btnFormToChooseMode.addEventListener('click', function () {
                showLandingMode('choose');
            });
        }
        if (initialLandingMode === 'form') {
            showLandingMode('form');
        } else if (initialLandingMode === 'bulk') {
            showLandingMode('bulk');
        } else {
            showLandingMode('choose');
        }

        if (pelaporanForm) {
            pelaporanForm.addEventListener('submit', function (evt) {
                if (!validateTabs(true)) {
                    evt.preventDefault();
                    return;
                }
                if (!(setujuSyaratValue && setujuSyaratValue.value === '1')) {
                    evt.preventDefault();
                    openTermsIfNeeded();
                }
            });
        }
        if (btnSetujuDanSubmit) {
            btnSetujuDanSubmit.addEventListener('click', function () {
                if (!(setujuSyaratCheckModal && setujuSyaratCheckModal.checked)) {
                    if (wizardValidationSummary) {
                        wizardValidationSummary.style.display = '';
                        wizardValidationSummary.innerHTML = '<strong>Validasi:</strong><br>Anda wajib mencentang persetujuan Syarat dan Ketentuan.';
                    }
                    return;
                }
                if (setujuSyaratValue) {
                    setujuSyaratValue.value = '1';
                }
                if (syaratKetentuanModalEl && typeof bootstrap !== 'undefined') {
                    const termsModal = bootstrap.Modal.getOrCreateInstance(syaratKetentuanModalEl);
                    termsModal.hide();
                }
            });
        }

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
                    'Monthly',
                    '2026-05-21',
                    '2',
                    'Staff Operasional|Admin Operasional',
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
                    'Full Time',
                    'Karirhub',
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
                        const allowedPeriode = ['Weekly', 'Monthly'];
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
                            if (!allowedTipe.includes(map['Status Pekerjaan'])) {
                                issues.push('Baris ' + line + ': Status Pekerjaan harus Full Time / Part Time / Contract / Internship.');
                                return;
                            }
                            if (!allowedPeriode.includes(map['Periode Tipe'])) {
                                issues.push('Baris ' + line + ': Periode Tipe harus Weekly/Monthly.');
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
