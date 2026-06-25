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

$jobTitle = trim((string)($_GET['job'] ?? 'IT Manager'));
$jobs = [
    'IT Manager' => [
        'lokasi' => 'Amban, Manokwari Barat, Kab. Manokwari, Papua Barat, Indonesia',
        'status' => 'Lowongan Ditutup',
        'status_class' => 'danger',
        'kuota' => '0 / 1 kuota telah terisi',
        'metrics' => ['leads' => 120308, 'lamaran' => 0, 'bookmark' => 0, 'ditawarkan' => 0, 'wawancara' => 0, 'diterima' => 0, 'arsip' => 0],
    ],
    'Kasir' => [
        'lokasi' => 'Bojongcae, Cibadak, KAB. LEBAK, BANTEN, Indonesia',
        'status' => 'Lowongan Ditutup',
        'status_class' => 'danger',
        'kuota' => '0 / 2 kuota telah terisi',
        'metrics' => ['leads' => 118244, 'lamaran' => 0, 'bookmark' => 0, 'ditawarkan' => 0, 'wawancara' => 0, 'diterima' => 0, 'arsip' => 0],
    ],
    'Finance Accounting' => [
        'lokasi' => 'Soreang, Soreang, KAB BANDUNG, JAWA BARAT, Indonesia',
        'status' => 'Lowongan Ditutup',
        'status_class' => 'danger',
        'kuota' => '0 / 2 kuota telah terisi',
        'metrics' => ['leads' => 107982, 'lamaran' => 0, 'bookmark' => 0, 'ditawarkan' => 0, 'wawancara' => 0, 'diterima' => 0, 'arsip' => 0],
    ],
    'Customer Relationship Officer' => [
        'lokasi' => 'Cicendo, Kota Bandung, Jawa Barat, Indonesia',
        'status' => 'Lowongan Aktif',
        'status_class' => 'success',
        'kuota' => '1 / 3 kuota telah terisi',
        'metrics' => ['leads' => 98944, 'lamaran' => 12, 'bookmark' => 8, 'ditawarkan' => 2, 'wawancara' => 3, 'diterima' => 1, 'arsip' => 0],
    ],
];
$selectedJob = $jobs[$jobTitle] ?? $jobs['IT Manager'];
$activeTab = trim((string)($_GET['tab'] ?? 'leads'));
$allowedTabs = ['leads', 'lamaran', 'bookmark', 'ditawarkan', 'wawancara', 'diterima', 'arsip'];
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'leads';
}

$addErrors = [];
$addSuccess = null;
$addForm = [
    'periode_tipe' => trim((string)($_POST['periode_tipe'] ?? 'monthly')),
    'periode_anchor' => trim((string)($_POST['periode_anchor'] ?? date('Y-m-d'))),
];
$wllpAddedInfo = null;
$stmtAdded = $conn->prepare("
    SELECT d.no_reg_bukti, d.id_lowongan
    FROM karirhub_proto_wllp_pelaporan d
    WHERE d.jabatan = ?
      AND d.catatan LIKE 'Auto insert dari Job Posted%'
    ORDER BY d.created_at DESC
    LIMIT 1
");
$stmtAdded->bind_param('s', $jobTitle);
$stmtAdded->execute();
$resAdded = $stmtAdded->get_result();
$rowAdded = $resAdded ? $resAdded->fetch_assoc() : null;
$stmtAdded->close();
if ($rowAdded) {
    $wllpAddedInfo = [
        'no_reg_bukti' => (string)($rowAdded['no_reg_bukti'] ?? ''),
        'id_lowongan' => (string)($rowAdded['id_lowongan'] ?? ''),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'add_to_wllp') {
    if ($wllpAddedInfo !== null) {
        $addSuccess = [
            'no_reg_bukti' => $wllpAddedInfo['no_reg_bukti'],
            'id_lowongan' => $wllpAddedInfo['id_lowongan'],
            'periode_label' => 'Sudah pernah ditambahkan ke WLLP',
        ];
    }
    if ($addSuccess === null) {
    if (!in_array($addForm['periode_tipe'], ['weekly', 'monthly'], true)) {
        $addErrors[] = 'Periode Pelaporan wajib Weekly atau Monthly.';
    }
    if (strtotime($addForm['periode_anchor']) === false) {
        $addErrors[] = 'Tanggal anchor periode tidak valid.';
    }

    if (empty($addErrors)) {
        $jobUnitMap = [
            'IT Manager' => 'UNIT-001',
            'Kasir' => 'UNIT-002',
            'Finance Accounting' => 'UNIT-003',
            'Customer Relationship Officer' => 'UNIT-002',
        ];
        $unitKode = (string)($jobUnitMap[$jobTitle] ?? 'UNIT-001');
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

        $provinsi = (string)($units[$unitKode]['provinsi'] ?? 'DKI Jakarta');
        $kota = (string)($units[$unitKode]['kota'] ?? 'Jakarta Selatan');
        $alamatParts = explode(',', (string)$selectedJob['lokasi']);
        $kecamatan = trim((string)($alamatParts[0] ?? 'Kecamatan'));
        $kelurahan = trim((string)($alamatParts[1] ?? 'Kelurahan'));
        $catatan = 'Auto insert dari Job Posted Karirhub Detail (' . $jobTitle . ')';
        $statusBelumTerisi = 'Belum Terisi';

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
            $deskripsiPekerjaan = 'Posisi ' . $jobTitle . ' yang dipublikasikan melalui Job Posted Karirhub.';
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
                $jobTitle,
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
                $jobTitle,
                $unitNama,
                $statusBelumTerisi,
                $masaMulai
            );
            $stmtSaveStatus->execute();
            $conn->commit();

            $addSuccess = [
                'no_reg_bukti' => $generatedNoReg,
                'id_lowongan' => $generatedIdLowongan,
                'periode_label' => strtoupper($period['tipe']) . ' (' . $period['mulai'] . ' s.d. ' . $period['selesai'] . ')',
            ];
            $wllpAddedInfo = [
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
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karirhub Employer Prototype - Detail Job Posted</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php kh_proto_render_styles(); ?>
    <style>
        .jp-head { background: #0b3b66; border: 1px solid rgba(255,255,255,.12); border-radius: 10px; padding: 16px; color: #fff; }
        .jp-badge { border-radius: 999px; font-size: 12px; font-weight: 700; padding: 6px 14px; }
        .jp-location { color: #cfe0f0; font-size: 13px; }
        .jp-actions .btn { border-radius: 6px; font-size: 13px; font-weight: 600; }
        .jp-actions .btn-outline-light { border-color: rgba(255,255,255,.5); }
        .jp-actions .btn-teal { background: #10a3a3; border-color: #10a3a3; color: #fff; }
        .jp-actions .btn-teal:hover { background: #0f9191; border-color: #0f9191; color: #fff; }
        .jp-actions .jp-added-flag { border-radius: 999px; padding: 7px 12px; font-size: 12px; font-weight: 700; background: #0d6efd; color: #fff; }
        .jp-panel { border: 1px solid #e7edf5; border-radius: 10px; background: #fff; overflow: hidden; }
        .jp-tabs { border-bottom: 1px solid #e7edf5; padding: 0 12px; display: flex; gap: 8px; flex-wrap: wrap; }
        .jp-tab { color: #24476a; text-decoration: none; padding: 10px 6px; border-bottom: 3px solid transparent; font-size: 14px; font-weight: 700; }
        .jp-tab.active { border-bottom-color: #0a8f8a; }
        .jp-tab .badge { margin-left: 4px; }
        .jp-search-wrap { padding: 12px; border-bottom: 1px solid #eef3f8; }
        .jp-filter-row { padding: 0 12px 12px; border-bottom: 1px solid #eef3f8; color: #546b84; font-size: 13px; display: flex; gap: 16px; flex-wrap: wrap; }
        .jp-empty { color: #75879a; text-align: center; padding: 58px 16px; }
    </style>
</head>
<body class="kh-proto-page">
<?php include 'navbar.php'; ?>
<?php kh_proto_render_hero('Daftar Pekerjaan', 'Detail performa lowongan yang diposting ke Karirhub.', 'Lowongan Kerja', 'karirhub_employer_prototype_job_posted_karirhub', 'Proyek', 'karirhub_employer_prototype_job_posted_karirhub', false); ?>

<div class="kh-content-wrap">
<div class="container py-4">
    <div class="kh-proto-shell">
    <?php kh_proto_render_sidebar('wllp_job_posted'); ?>
    <main class="kh-proto-main">
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

    <div class="jp-head mb-3">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-lg-8">
                <span class="jp-badge text-bg-<?php echo h((string)$selectedJob['status_class']); ?>"><?php echo h((string)$selectedJob['status']); ?></span>
                <h3 class="mt-2 mb-1"><?php echo h($jobTitle); ?></h3>
                <div class="jp-location"><i class="bi bi-geo-alt-fill me-1"></i><?php echo h((string)$selectedJob['lokasi']); ?></div>
                <div class="jp-actions d-flex flex-wrap gap-2 mt-3">
                    <a class="btn btn-outline-light btn-sm" href="#"><i class="bi bi-eye me-1"></i>Lihat Lowongan</a>
                    <a class="btn btn-outline-light btn-sm" href="#"><i class="bi bi-pencil-square me-1"></i>Edit Lowongan</a>
                    <a class="btn btn-teal btn-sm" href="#"><i class="bi bi-send-fill me-1"></i>Buka Lowongan</a>
                    <a class="btn btn-outline-light btn-sm" href="#"><i class="bi bi-link-45deg me-1"></i>Salin Link Lowongan</a>
                    <?php if ($wllpAddedInfo !== null): ?>
                        <span class="jp-added-flag">Berhasil ditambahkan ke WLLP</span>
                    <?php endif; ?>
                    <button type="button" class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#addToWllpModalDetail"<?php echo $wllpAddedInfo !== null ? ' disabled' : ''; ?>>
                        <i class="bi bi-plus-circle me-1"></i><?php echo $wllpAddedInfo !== null ? 'Sudah ditambahkan ke WLLP' : 'Tambahkan ke dalam WLLP'; ?>
                    </button>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="small text-uppercase text-light opacity-75">Kuota</div>
                <div class="progress my-2" style="height:6px;">
                    <div class="progress-bar" role="progressbar" style="width: 18%;"></div>
                </div>
                <div class="small"><?php echo h((string)$selectedJob['kuota']); ?></div>
            </div>
        </div>
    </div>

    <div class="jp-panel">
        <div class="jp-tabs">
            <?php foreach ($allowedTabs as $tab): ?>
                <a class="jp-tab <?php echo $activeTab === $tab ? 'active' : ''; ?>"
                   href="karirhub_employer_prototype_job_posted_karirhub_detail?<?php echo h(http_build_query(['job' => $jobTitle, 'tab' => $tab])); ?>">
                    <?php echo h(ucfirst($tab)); ?>
                    <span class="badge text-bg-light"><?php echo h((string)($selectedJob['metrics'][$tab] ?? 0)); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="jp-search-wrap">
            <div class="input-group input-group-sm">
                <input type="text" class="form-control" placeholder="Cari berdasarkan nama">
                <button class="btn btn-outline-secondary" type="button"><i class="bi bi-search"></i></button>
            </div>
        </div>
        <div class="jp-filter-row">
            <span>Min Pendidikan (0)</span>
            <span>Lokasi (0)</span>
            <span>Pengalaman (0)</span>
            <span>Jenis kelamin (0)</span>
            <span>Usia (0)</span>
            <span><input class="form-check-input me-1" type="checkbox">Disabilitas</span>
        </div>
        <div class="jp-empty">Tidak ada data ditemukan.</div>
    </div>

    <div class="mt-3">
        <a class="btn btn-outline-primary btn-sm" href="karirhub_employer_prototype_job_posted_karirhub">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke daftar Job Posted Karirhub
        </a>
    </div>
    </main>
    </div>
</div>
</div>

<div class="modal fade" id="addToWllpModalDetail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Tambahkan ke dalam WLLP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_to_wllp">
                    <div class="mb-2 small text-muted">
                        Lowongan: <strong><?php echo h($jobTitle); ?></strong>
                    </div>
                    <div class="mb-2">
                        <label class="form-label mb-1">Periode Pelaporan</label>
                        <select class="form-select form-select-sm" name="periode_tipe">
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
        <?php if (!empty($addErrors)): ?>
        const modalEl = document.getElementById('addToWllpModalDetail');
        if (modalEl && typeof bootstrap !== 'undefined') {
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        }
        <?php endif; ?>
    })();
</script>
<?php kh_proto_render_sidebar_script(); ?>
</body>
</html>
