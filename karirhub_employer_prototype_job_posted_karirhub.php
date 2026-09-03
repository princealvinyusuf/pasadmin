<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_data.php';
require_once __DIR__ . '/karirhub_employer_prototype_storage.php';
require_once __DIR__ . '/karirhub_employer_prototype_ui.php';
require_once __DIR__ . '/db.php';

if (!kh_proto_can_access('karirhub_employer_prototype_job_posted_view')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$dataset = karirhub_proto_dataset();
$units = $dataset['units'] ?? [];
kh_proto_ensure_multi_tables($conn);
kh_proto_seed_multi_from_dataset($conn, $dataset, $units);

$addErrors = [];
$addSuccess = null;
$addForm = [
    'job_key' => trim((string)($_POST['job_key'] ?? '')),
    'periode_tipe' => trim((string)($_POST['periode_tipe'] ?? 'monthly')),
    'periode_anchor' => trim((string)($_POST['periode_anchor'] ?? date('Y-m-d'))),
];

$statusFilter = trim((string)($_GET['status'] ?? 'all'));
if (!in_array($statusFilter, ['all', 'aktif', 'ditutup'], true)) {
    $statusFilter = 'all';
}
$searchQuery = trim((string)($_GET['q'] ?? ''));
$sortOrder = trim((string)($_GET['sort'] ?? 'newest'));
if (!in_array($sortOrder, ['newest', 'oldest', 'title'], true)) {
    $sortOrder = 'newest';
}

$jobs = [
    [
        'unit_kode' => 'UNIT-001',
        'judul' => 'IT Manager',
        'lokasi' => 'Amban, Manokwari Barat, Kab. Manokwari, Papua Barat, Indonesia',
        'status' => 'ditutup',
        'metrics' => ['leads' => 120308, 'lamaran' => 0, 'bookmark' => 0, 'ditawarkan' => 0, 'wawancara' => 0, 'diterima' => 0, 'arsip' => 0],
    ],
    [
        'unit_kode' => 'UNIT-002',
        'judul' => 'Kasir',
        'lokasi' => 'Bojongcae, Cibadak, KAB. LEBAK, BANTEN, Indonesia',
        'status' => 'ditutup',
        'metrics' => ['leads' => 118244, 'lamaran' => 0, 'bookmark' => 0, 'ditawarkan' => 0, 'wawancara' => 0, 'diterima' => 0, 'arsip' => 0],
    ],
    [
        'unit_kode' => 'UNIT-003',
        'judul' => 'Finance Accounting',
        'lokasi' => 'Soreang, Soreang, KAB BANDUNG, JAWA BARAT, Indonesia',
        'status' => 'ditutup',
        'metrics' => ['leads' => 107982, 'lamaran' => 0, 'bookmark' => 0, 'ditawarkan' => 0, 'wawancara' => 0, 'diterima' => 0, 'arsip' => 0],
    ],
    [
        'unit_kode' => 'UNIT-002',
        'judul' => 'Customer Relationship Officer',
        'lokasi' => 'Cicendo, Kota Bandung, Jawa Barat, Indonesia',
        'status' => 'aktif',
        'metrics' => ['leads' => 98944, 'lamaran' => 12, 'bookmark' => 8, 'ditawarkan' => 2, 'wawancara' => 3, 'diterima' => 1, 'arsip' => 0],
    ],
];
$jobMap = [];
foreach ($jobs as $jobRow) {
    $jobMap[(string)$jobRow['judul']] = $jobRow;
}
$wllpAddedByJob = [];
$jobTitles = array_keys($jobMap);
if (!empty($jobTitles)) {
    $escapedTitles = array_map(static fn (string $v): string => "'" . $conn->real_escape_string($v) . "'", $jobTitles);
    $resAdded = $conn->query("
        SELECT
            d.jabatan,
            d.no_reg_bukti,
            d.id_lowongan,
            d.jumlah_kebutuhan,
            d.created_at,
            COALESCE(s.status_saat_ini, 'Belum Terisi') AS status_keterisian,
            COALESCE(pc.jumlah_penempatan, 0) AS jumlah_penempatan
        FROM karirhub_proto_wllp_pelaporan d
        LEFT JOIN karirhub_proto_wllp_status s
            ON s.no_reg_bukti = d.no_reg_bukti AND s.id_lowongan = d.id_lowongan
        LEFT JOIN (
            SELECT no_reg_bukti, id_lowongan, COUNT(*) AS jumlah_penempatan
            FROM karirhub_proto_wllp_penempatan
            GROUP BY no_reg_bukti, id_lowongan
        ) pc
            ON pc.no_reg_bukti = d.no_reg_bukti AND pc.id_lowongan = d.id_lowongan
        WHERE d.jabatan IN (" . implode(',', $escapedTitles) . ")
          AND d.catatan LIKE 'Auto insert dari Job Posted%'
        ORDER BY d.created_at DESC
    ");
    if ($resAdded) {
        while ($r = $resAdded->fetch_assoc()) {
            $jabatanKey = (string)($r['jabatan'] ?? '');
            if ($jabatanKey === '' || isset($wllpAddedByJob[$jabatanKey])) {
                continue;
            }
            $wllpAddedByJob[$jabatanKey] = [
                'no_reg_bukti' => (string)($r['no_reg_bukti'] ?? ''),
                'id_lowongan' => (string)($r['id_lowongan'] ?? ''),
                'status_keterisian' => (string)($r['status_keterisian'] ?? 'Belum Terisi'),
                'jumlah_kebutuhan' => (int)($r['jumlah_kebutuhan'] ?? 0),
                'jumlah_penempatan' => (int)($r['jumlah_penempatan'] ?? 0),
            ];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'add_to_wllp') {
    $selectedJob = $jobMap[$addForm['job_key']] ?? null;
    if ($selectedJob === null) {
        $addErrors[] = 'Lowongan tidak ditemukan.';
    }
    if (!in_array($addForm['periode_tipe'], ['weekly', 'monthly'], true)) {
        $addErrors[] = 'Periode Pelaporan wajib Weekly atau Monthly.';
    }
    if (strtotime($addForm['periode_anchor']) === false) {
        $addErrors[] = 'Tanggal anchor periode tidak valid.';
    }

    if (empty($addErrors) && $selectedJob !== null) {
        $unitKode = (string)($selectedJob['unit_kode'] ?? 'UNIT-001');
        $unitNama = (string)($units[$unitKode]['nama'] ?? $unitKode);
        $employerKode = (string)($units[$unitKode]['employer_kode'] ?? 'EMP-001');
        $employerNama = (string)($units[$unitKode]['employer_nama'] ?? 'PT Contoh Nusantara');
        $msmeClass = (string)($units[$unitKode]['kelas_umkm'] ?? 'B');
        $period = kh_proto_derive_period($addForm['periode_tipe'], $addForm['periode_anchor']);
        $generatedNoReg = '';
        $stmtFindHeader = $conn->prepare("
            SELECT no_reg_bukti
            FROM karirhub_proto_wllp_laporan
            WHERE employer_kode = ?
              AND periode_tipe = ?
              AND ? BETWEEN periode_mulai AND periode_selesai
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmtFindHeader->bind_param('sss', $employerKode, $period['tipe'], $period['anchor']);
        $stmtFindHeader->execute();
        $resFoundHeader = $stmtFindHeader->get_result();
        $foundHeader = $resFoundHeader ? $resFoundHeader->fetch_assoc() : null;
        $stmtFindHeader->close();
        if ($foundHeader && (string)($foundHeader['no_reg_bukti'] ?? '') !== '') {
            $generatedNoReg = (string)$foundHeader['no_reg_bukti'];
        } else {
            $generatedNoReg = kh_proto_generate_no_reg_from_anchor($conn, $period['anchor'], $employerKode, $employerNama, $msmeClass);
        }
        $generatedIdLowongan = kh_proto_generate_id_lowongan($conn);

        $jabatan = (string)$selectedJob['judul'];
        $provinsi = (string)($units[$unitKode]['provinsi'] ?? 'DKI Jakarta');
        $kota = (string)($units[$unitKode]['kota'] ?? 'Jakarta Selatan');
        $alamatParts = explode(',', (string)$selectedJob['lokasi']);
        $kecamatan = trim((string)($alamatParts[0] ?? 'Kecamatan'));
        $kelurahan = trim((string)($alamatParts[1] ?? 'Kelurahan'));
        $catatan = 'Auto insert dari Job Posted Karirhub (' . $jabatan . ')';
        $statusBelumTerisi = 'Belum Terisi';
        if (isset($wllpAddedByJob[$jabatan])) {
            $existing = $wllpAddedByJob[$jabatan];
            $addSuccess = [
                'job' => $jabatan,
                'no_reg_bukti' => (string)$existing['no_reg_bukti'],
                'id_lowongan' => (string)$existing['id_lowongan'],
                'periode_label' => 'Sudah pernah ditambahkan ke WLLP',
            ];
            goto finalize_add_to_wllp;
        }

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
                bidang_pekerjaan, industri_sektor, status_pernikahan, tipe_kerja, masa_berlaku_mulai, masa_berlaku_sampai, alamat_url_postingan_loker, catatan, status_verifikasi
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Terverifikasi')
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
                $unitKode,
                $unitNama,
                $period['tipe'],
                $period['anchor'],
                $period['mulai'],
                $period['selesai'],
                $catatan
            );
            $stmtSaveHeader->execute();

            $jumlahKebutuhan = 1;
            $jenisKelamin = 'Semua';
            $usiaMin = 21;
            $usiaMax = 45;
            $pendidikanMinimal = 'S1';
            $deskripsiPekerjaan = 'Posisi ' . $jabatan . ' yang dipublikasikan melalui Job Posted Karirhub.';
            $keterampilanUtama = 'Komunikasi, koordinasi, dan problem solving';
            $pengalamanMin = 1;
            $rentangGaji = 'Menyesuaikan';
            $kodeKbji = '00000';
            $bidangPekerjaan = 'General';
            $industriSektor = 'Beragam';
            $statusPernikahan = 'Tidak Dipersyaratkan';
            $tipeKerja = 'Full Time';
            $masaMulai = $period['anchor'];
            $masaSampai = $period['selesai'];
            $urlPosting = 'https://karirhub.kemnaker.go.id/';

            $stmtSaveDetail->bind_param(
                str_repeat('s', 29),
                $generatedNoReg,
                $generatedIdLowongan,
                $employerKode,
                $employerNama,
                $unitKode,
                $unitNama,
                $jabatan,
                $jumlahKebutuhan,
                $jenisKelamin,
                $usiaMin,
                $usiaMax,
                $pendidikanMinimal,
                $deskripsiPekerjaan,
                $keterampilanUtama,
                $pengalamanMin,
                $rentangGaji,
                $kodeKbji,
                $provinsi,
                $kota,
                $kecamatan,
                $kelurahan,
                $bidangPekerjaan,
                $industriSektor,
                $statusPernikahan,
                $tipeKerja,
                $masaMulai,
                $masaSampai,
                $urlPosting,
                $catatan
            );
            $stmtSaveDetail->execute();

            $stmtSaveStatus->bind_param(
                'ssssssss',
                $generatedNoReg,
                $generatedIdLowongan,
                $employerKode,
                $employerNama,
                $jabatan,
                $unitNama,
                $statusBelumTerisi,
                $masaMulai
            );
            $stmtSaveStatus->execute();
            $conn->commit();

            $addSuccess = [
                'job' => $jabatan,
                'no_reg_bukti' => $generatedNoReg,
                'id_lowongan' => $generatedIdLowongan,
                'periode_label' => strtoupper($period['tipe']) . ' (' . $period['mulai'] . ' s.d. ' . $period['selesai'] . ')',
            ];
            $wllpAddedByJob[$jabatan] = [
                'no_reg_bukti' => $generatedNoReg,
                'id_lowongan' => $generatedIdLowongan,
            ];
        } catch (Throwable $e) {
            $conn->rollback();
            $addErrors[] = 'Gagal menambahkan lowongan ke WLLP: ' . $e->getMessage();
        }

        $stmtSaveHeader->close();
        $stmtSaveDetail->close();
        $stmtSaveStatus->close();
    }
}
finalize_add_to_wllp:

$filteredJobs = array_values(array_filter($jobs, static function (array $job) use ($statusFilter, $searchQuery): bool {
    if ($statusFilter !== 'all' && (string)$job['status'] !== $statusFilter) {
        return false;
    }
    if ($searchQuery !== '') {
        $haystack = strtolower((string)$job['judul'] . ' ' . (string)$job['lokasi']);
        if (strpos($haystack, strtolower($searchQuery)) === false) {
            return false;
        }
    }
    return true;
}));
if ($sortOrder === 'oldest') {
    $filteredJobs = array_reverse($filteredJobs);
} elseif ($sortOrder === 'title') {
    usort($filteredJobs, static fn (array $a, array $b): int => strcasecmp((string)$a['judul'], (string)$b['judul']));
}

$activeJobCount = count(array_filter($jobs, static fn (array $job): bool => (string)$job['status'] === 'aktif'));
$wllpJobCount = count($wllpAddedByJob);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karirhub Employer Prototype - Job Posted Karirhub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php kh_proto_render_styles(); ?>
    <style>
        .kh-jobs-page {
            --jobs-ink: #17233c;
            --jobs-muted: #728096;
            --jobs-line: #e3e8ef;
            --jobs-blue: #22aeca;
            --jobs-blue-dark: #138ca8;
        }
        .kh-jobs-page .kh-proto-main {
            color: var(--jobs-ink);
        }
        .jobs-page-head h3 {
            color: #111d32;
            font-size: 1.55rem;
            font-weight: 750;
        }
        .jobs-add-btn {
            padding: 0.55rem 1rem;
            border: 0;
            border-radius: 0.65rem;
            color: #fff;
            background: linear-gradient(135deg, #35bdd7, #1ca4c1);
            box-shadow: 0 7px 16px rgba(28, 164, 193, 0.22);
        }
        .jobs-add-btn:hover {
            color: #fff;
            background: linear-gradient(135deg, #24aec9, #138ca8);
        }
        .jobs-summary-card {
            height: 100%;
            padding: 1rem;
            border: 1px solid var(--jobs-line);
            border-radius: 0.85rem;
            background: #fff;
            box-shadow: 0 4px 14px rgba(38, 55, 82, 0.04);
        }
        .jobs-summary-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
        }
        .jobs-summary-label {
            color: #4f596a;
            font-size: 0.67rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }
        .jobs-summary-value {
            margin-top: 0.3rem;
            color: #111827;
            font-size: 1.45rem;
            font-weight: 750;
            line-height: 1;
        }
        .jobs-summary-copy {
            margin-top: 0.55rem;
            color: var(--jobs-muted);
            font-size: 0.7rem;
        }
        .jobs-summary-icon {
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 0.65rem;
            color: #667085;
            background: #f2f4f7;
        }
        .jobs-summary-icon.sent { color: #c68c16; background: #fff8e8; }
        .jobs-summary-icon.revision { color: #d95063; background: #fff0f2; }
        .jobs-summary-icon.active { color: #13956c; background: #eaf9f4; }
        .jobs-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .jobs-search {
            position: relative;
            width: min(320px, 100%);
        }
        .jobs-search i {
            position: absolute;
            top: 50%;
            left: 0.8rem;
            z-index: 2;
            color: #7f8b9d;
            transform: translateY(-50%);
        }
        .jobs-search .form-control {
            min-height: 40px;
            padding-left: 2.25rem;
            border-color: var(--jobs-line);
            border-radius: 0.65rem;
            font-size: 0.82rem;
        }
        .jobs-filter {
            min-height: 40px;
            border-color: var(--jobs-line);
            border-radius: 0.65rem;
            color: #344054;
            font-size: 0.78rem;
            background-color: #fff;
        }
        .jobs-count {
            color: var(--jobs-muted);
            font-size: 0.75rem;
        }
        .jobs-table-card {
            overflow: hidden;
            border: 1px solid var(--jobs-line);
            border-radius: 0.85rem;
            background: #fff;
        }
        .jobs-table {
            min-width: 1000px;
            margin: 0;
        }
        .jobs-table thead th {
            padding: 0.8rem 0.75rem;
            border-bottom: 1px solid var(--jobs-line);
            color: #667085;
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            background: #fbfcfd !important;
        }
        .jobs-table tbody td {
            padding: 0.8rem 0.75rem;
            border-color: #edf0f4;
            color: #344054;
            font-size: 0.76rem;
            vertical-align: middle;
        }
        .jobs-table tbody tr {
            transition: background 150ms ease;
        }
        .jobs-table tbody tr:hover {
            background: #fbfdff;
        }
        .job-identity {
            display: flex;
            align-items: center;
            gap: 0.7rem;
            min-width: 230px;
        }
        .job-avatar {
            display: grid;
            flex: 0 0 34px;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 50%;
            color: #159bb7;
            font-size: 0.68rem;
            font-weight: 750;
            background: #edfafd;
        }
        .job-title-link {
            display: inline-block;
            margin-bottom: 0.12rem;
            color: #26364f;
            font-size: 0.8rem;
            font-weight: 700;
            text-decoration: none;
        }
        .job-title-link:hover {
            color: var(--jobs-blue-dark);
        }
        .job-meta,
        .job-subvalue {
            color: #8b95a5;
            font-size: 0.66rem;
        }
        .job-location {
            display: block;
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .job-status {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.55rem;
            border: 1px solid #d9dee6;
            border-radius: 999px;
            color: #667085;
            font-size: 0.67rem;
            font-weight: 650;
            background: #f7f8fa;
            white-space: nowrap;
        }
        .job-status.aktif {
            border-color: #b9ead9;
            color: #087e5b;
            background: #effbf7;
        }
        .job-wllp-status {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.28rem 0.55rem;
            border-radius: 999px;
            color: #157c63;
            font-size: 0.66rem;
            font-weight: 700;
            background: #e9f9f3;
            white-space: nowrap;
        }
        .job-wllp-status.pending {
            color: #8a6513;
            background: #fff7e5;
        }
        .job-placement-warning {
            display: block;
            margin-top: 0.3rem;
            color: #c66117;
            font-size: 0.63rem;
        }
        .job-action-btn {
            display: inline-grid;
            width: 30px;
            height: 30px;
            place-items: center;
            border: 0;
            border-radius: 0.45rem;
            color: #7d8797;
            text-decoration: none;
            background: transparent;
        }
        .job-action-btn:hover {
            color: var(--jobs-blue-dark);
            background: #eaf8fb;
        }
        .jobs-empty {
            padding: 3.5rem 1rem;
            color: var(--jobs-muted);
            text-align: center;
        }
        @media (max-width: 767px) {
            .jobs-toolbar {
                align-items: stretch;
                flex-direction: column;
            }
            .jobs-search {
                width: 100%;
            }
            .jobs-toolbar-filters {
                display: grid !important;
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body class="kh-proto-page kh-jobs-page">
<?php include 'navbar.php'; ?>
<?php kh_proto_render_hero('Daftar Pekerjaan', 'Pantau lowongan yang sudah diposting ke Karirhub.', 'Lowongan Kerja', 'karirhub_employer_prototype_pelaporan_lowongan', 'Proyek', 'karirhub_employer_prototype_job_posted_karirhub', false); ?>

<div class="kh-content-wrap">
<div class="container py-4">
    <div class="kh-proto-shell">
    <?php kh_proto_render_sidebar('wllp_job_posted'); ?>
    <main class="kh-proto-main">
    <div class="jobs-page-head d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-1">Lowongan</h3>
            <div class="text-muted small">Kelola lowongan dan pantau status integrasinya dengan WLLP.</div>
        </div>
        <a class="btn btn-sm jobs-add-btn" href="karirhub_employer_prototype_pelaporan_lowongan?mode=form">
            <i class="bi bi-plus-circle me-1"></i>Tambah
        </a>
    </div>

    <?php if (!empty($addErrors)): ?>
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Gagal menambahkan ke WLLP:</div>
            <ul class="mb-0">
                <?php foreach ($addErrors as $err): ?>
                    <li><?php echo h($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($addSuccess !== null): ?>
        <div class="alert alert-success">
            <div class="fw-semibold mb-1">Lowongan berhasil ditambahkan ke WLLP</div>
            <div><strong>Jabatan:</strong> <?php echo h((string)$addSuccess['job']); ?></div>
            <div><strong>No. Reg Bukti:</strong> <?php echo h((string)$addSuccess['no_reg_bukti']); ?></div>
            <div><strong>ID Lowongan:</strong> <?php echo h((string)$addSuccess['id_lowongan']); ?></div>
            <div><strong>Periode:</strong> <?php echo h((string)$addSuccess['periode_label']); ?></div>
            <div class="mt-1">
                <a class="btn btn-outline-primary btn-sm" href="karirhub_employer_prototype_bukti_lapor?action=lihat&no_reg=<?php echo rawurlencode((string)$addSuccess['no_reg_bukti']); ?>">
                    <i class="bi bi-eye me-1"></i>Lihat di Bukti Lapor
                </a>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <div class="jobs-summary-card">
                <div class="jobs-summary-head">
                    <div>
                        <div class="jobs-summary-label">Draft</div>
                        <div class="jobs-summary-value">0</div>
                    </div>
                    <span class="jobs-summary-icon"><i class="bi bi-file-earmark-text"></i></span>
                </div>
                <div class="jobs-summary-copy">Belum diajukan</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="jobs-summary-card">
                <div class="jobs-summary-head">
                    <div>
                        <div class="jobs-summary-label">Dikirim</div>
                        <div class="jobs-summary-value"><?php echo h((string)$wllpJobCount); ?></div>
                    </div>
                    <span class="jobs-summary-icon sent"><i class="bi bi-clock-history"></i></span>
                </div>
                <div class="jobs-summary-copy">Berhasil dikirim ke WLLP</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="jobs-summary-card">
                <div class="jobs-summary-head">
                    <div>
                        <div class="jobs-summary-label">Perlu Direvisi</div>
                        <div class="jobs-summary-value">0</div>
                    </div>
                    <span class="jobs-summary-icon revision"><i class="bi bi-archive"></i></span>
                </div>
                <div class="jobs-summary-copy">Perlu perbaikan</div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="jobs-summary-card">
                <div class="jobs-summary-head">
                    <div>
                        <div class="jobs-summary-label">Lowongan Aktif</div>
                        <div class="jobs-summary-value"><?php echo h((string)$activeJobCount); ?></div>
                    </div>
                    <span class="jobs-summary-icon active"><i class="bi bi-check-circle"></i></span>
                </div>
                <div class="jobs-summary-copy">Sedang tayang</div>
            </div>
        </div>
    </div>

    <form method="GET" class="mb-2">
        <div class="jobs-toolbar">
            <div class="jobs-search">
                <i class="bi bi-search"></i>
                <input
                    type="search"
                    name="q"
                    class="form-control form-control-sm"
                    value="<?php echo h($searchQuery); ?>"
                    placeholder="Cari berdasarkan judul atau lokasi"
                    aria-label="Cari lowongan"
                >
            </div>
            <div class="jobs-toolbar-filters d-flex gap-2">
                <select name="status" class="form-select form-select-sm jobs-filter" aria-label="Filter status" onchange="this.form.submit()">
                    <option value="all"<?php echo $statusFilter === 'all' ? ' selected' : ''; ?>>Semua Status</option>
                    <option value="aktif"<?php echo $statusFilter === 'aktif' ? ' selected' : ''; ?>>Lowongan Aktif</option>
                    <option value="ditutup"<?php echo $statusFilter === 'ditutup' ? ' selected' : ''; ?>>Lowongan Ditutup</option>
                </select>
                <select name="sort" class="form-select form-select-sm jobs-filter" aria-label="Urutkan lowongan" onchange="this.form.submit()">
                    <option value="newest"<?php echo $sortOrder === 'newest' ? ' selected' : ''; ?>>↕ Terbaru</option>
                    <option value="oldest"<?php echo $sortOrder === 'oldest' ? ' selected' : ''; ?>>↕ Terlama</option>
                    <option value="title"<?php echo $sortOrder === 'title' ? ' selected' : ''; ?>>A–Z Judul</option>
                </select>
            </div>
        </div>
    </form>

    <div class="jobs-count mb-2">
        Menampilkan <strong><?php echo h((string)count($filteredJobs)); ?></strong> dari <strong><?php echo h((string)count($jobs)); ?></strong> lowongan
    </div>

    <div class="jobs-table-card">
        <?php if (empty($filteredJobs)): ?>
            <div class="jobs-empty">
                <i class="bi bi-search fs-3 d-block mb-2"></i>
                Tidak ada lowongan yang sesuai dengan pencarian atau filter.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table jobs-table align-middle">
                    <thead>
                        <tr>
                            <th>Lowongan</th>
                            <th>Penempatan</th>
                            <th>Kuota Tersedia</th>
                            <th>Pelamar</th>
                            <th>Status</th>
                            <th>Status WLLP</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filteredJobs as $jobIndex => $job): ?>
                            <?php
                                $addedInfo = $wllpAddedByJob[(string)$job['judul']] ?? null;
                                $jumlahKebutuhan = max(1, (int)($addedInfo['jumlah_kebutuhan'] ?? 1));
                                $jumlahDiterima = (int)($job['metrics']['diterima'] ?? 0);
                                $kuotaTersedia = max(0, $jumlahKebutuhan - $jumlahDiterima);
                                $initials = implode('', array_map(
                                    static fn (string $part): string => strtoupper(substr($part, 0, 1)),
                                    array_slice(array_values(array_filter(explode(' ', (string)$job['judul']))), 0, 2)
                                ));
                                $isPenempatanTidakLengkap = $addedInfo !== null
                                    && (string)($addedInfo['status_keterisian'] ?? '') === 'Terisi'
                                    && (int)($addedInfo['jumlah_penempatan'] ?? 0) < (int)($addedInfo['jumlah_kebutuhan'] ?? 0);
                            ?>
                            <tr>
                                <td>
                                    <div class="job-identity">
                                        <span class="job-avatar"><?php echo h($initials !== '' ? $initials : 'L'); ?></span>
                                        <span>
                                            <a class="job-title-link" href="karirhub_employer_prototype_job_posted_karirhub_detail?job=<?php echo rawurlencode((string)$job['judul']); ?>">
                                                <?php echo h((string)$job['judul']); ?>
                                            </a>
                                            <span class="job-meta d-block">Diposting dari Karirhub</span>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <span class="job-location" title="<?php echo h((string)$job['lokasi']); ?>">
                                        <?php echo h((string)$job['lokasi']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div><strong><?php echo h((string)$kuotaTersedia); ?></strong>/<?php echo h((string)$jumlahKebutuhan); ?> tersedia</div>
                                    <div class="job-subvalue"><?php echo h((string)$jumlahDiterima); ?> diterima</div>
                                </td>
                                <td>
                                    <div><strong><?php echo h((string)$job['metrics']['lamaran']); ?></strong> pelamar</div>
                                    <div class="job-subvalue"><?php echo h((string)$jumlahDiterima); ?> diterima</div>
                                </td>
                                <td>
                                    <span class="job-status <?php echo h((string)$job['status']); ?>">
                                        <?php echo (string)$job['status'] === 'ditutup' ? 'Ditutup' : 'Aktif'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($addedInfo !== null): ?>
                                        <span
                                            class="job-wllp-status"
                                            data-bs-toggle="tooltip"
                                            title="Lowongan pekerjaan ini telah ditambahkan ke Wajib Lapor Lowongan Pekerjaan"
                                        >
                                            <i class="bi bi-check-circle-fill"></i>Berhasil ditambahkan ke WLLP
                                        </span>
                                        <?php if ($isPenempatanTidakLengkap): ?>
                                            <span class="job-placement-warning"><i class="bi bi-exclamation-triangle me-1"></i>Data penempatan belum lengkap</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button
                                            type="button"
                                            class="job-wllp-status pending border-0 js-add-to-wllp-btn"
                                            data-job-title="<?php echo h((string)$job['judul']); ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#addToWllpModal"
                                        >
                                            <i class="bi bi-plus-circle"></i>Belum ditambahkan
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a
                                        class="job-action-btn"
                                        href="karirhub_employer_prototype_job_posted_karirhub_detail?job=<?php echo rawurlencode((string)$job['judul']); ?>"
                                        title="Lihat detail"
                                        aria-label="Lihat detail <?php echo h((string)$job['judul']); ?>"
                                    >
                                        <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    </main>
    </div>
</div>
</div>

<div class="modal fade" id="addToWllpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Tambahkan ke dalam WLLP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_to_wllp">
                    <input type="hidden" name="job_key" id="addToWllpJobKey" value="<?php echo h($addForm['job_key']); ?>">
                    <div class="mb-2 small text-muted">
                        Lowongan: <strong id="addToWllpJobTitle"><?php echo h($addForm['job_key'] !== '' ? $addForm['job_key'] : '-'); ?></strong>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Periode Pelaporan</label>
                        <select class="form-select form-select-sm" name="periode_tipe" id="addToWllpPeriodeTipe">
                            <option value="weekly"<?php echo $addForm['periode_tipe'] === 'weekly' ? ' selected' : ''; ?>>Weekly</option>
                            <option value="monthly"<?php echo $addForm['periode_tipe'] === 'monthly' ? ' selected' : ''; ?>>Monthly</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label mb-1">Tanggal Anchor Periode</label>
                        <input type="date" class="form-control form-control-sm" name="periode_anchor" value="<?php echo h($addForm['periode_anchor']); ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check2-circle me-1"></i>Tambahkan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        const modalEl = document.getElementById('addToWllpModal');
        const jobKeyInput = document.getElementById('addToWllpJobKey');
        const jobTitleText = document.getElementById('addToWllpJobTitle');
        if (!modalEl || !jobKeyInput || !jobTitleText) return;

        document.addEventListener('click', function (evt) {
            const btn = evt.target && evt.target.closest('.js-add-to-wllp-btn');
            if (!btn) return;
            const title = btn.getAttribute('data-job-title') || '';
            jobKeyInput.value = title;
            jobTitleText.textContent = title || '-';
        });

        const tooltipTriggers = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipTriggers.forEach(function (el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });

        <?php if (!empty($addErrors)): ?>
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
        <?php endif; ?>
    })();
</script>
<?php kh_proto_render_sidebar_script(); ?>
</body>
</html>
