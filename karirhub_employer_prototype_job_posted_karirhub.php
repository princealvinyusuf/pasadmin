<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_data.php';
require_once __DIR__ . '/karirhub_employer_prototype_storage.php';
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
$lokerTerbatas = trim((string)($_GET['loker_terbatas'] ?? '0')) === '1';

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
        $period = kh_proto_derive_period($addForm['periode_tipe'], $addForm['periode_anchor']);
        $generatedNoReg = kh_proto_generate_no_reg_from_anchor($conn, $period['anchor']);
        $generatedIdLowongan = kh_proto_generate_id_lowongan($conn);

        $jabatan = (string)$selectedJob['judul'];
        $provinsi = (string)($units[$unitKode]['provinsi'] ?? 'DKI Jakarta');
        $kota = (string)($units[$unitKode]['kota'] ?? 'Jakarta Selatan');
        $alamatParts = explode(',', (string)$selectedJob['lokasi']);
        $kecamatan = trim((string)($alamatParts[0] ?? 'Kecamatan'));
        $kelurahan = trim((string)($alamatParts[1] ?? 'Kelurahan'));
        $catatan = 'Auto insert dari Job Posted Karirhub (' . $jabatan . ')';
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
        } catch (Throwable $e) {
            $conn->rollback();
            $addErrors[] = 'Gagal menambahkan lowongan ke WLLP: ' . $e->getMessage();
        }

        $stmtSaveHeader->close();
        $stmtSaveDetail->close();
        $stmtSaveStatus->close();
    }
}

$filteredJobs = array_values(array_filter($jobs, static function (array $job) use ($statusFilter): bool {
    if ($statusFilter === 'all') {
        return true;
    }
    return (string)$job['status'] === $statusFilter;
}));
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
        .job-posted-note { background: #ffffff; border: 1px solid #e8eef5; border-radius: 6px; padding: 10px 12px; color: #61758b; font-size: 13px; }
        .job-posted-card { border: 1px solid #edf2f8; border-radius: 10px; background: #fff; overflow: hidden; margin-bottom: 12px; }
        .job-posted-card-head { padding: 14px 16px 10px; display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
        .job-posted-title { font-size: 22px; font-weight: 700; color: #23415f; margin-bottom: 2px; }
        .job-posted-title-link { color: #23415f; text-decoration: none; }
        .job-posted-title-link:hover { color: #0a8f8a; text-decoration: underline; }
        .job-posted-loc { color: #8596a8; font-size: 13px; }
        .job-posted-status { border-radius: 999px; padding: 6px 14px; font-size: 12px; font-weight: 700; color: #fff; white-space: nowrap; }
        .job-posted-status.ditutup { background: #ea3f51; }
        .job-posted-status.aktif { background: #18a365; }
        .job-posted-side-actions { display: flex; flex-direction: column; gap: 8px; align-items: flex-end; }
        .job-posted-metrics { border-top: 1px solid #edf2f8; background: #fbfcfe; display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); }
        .job-metric { padding: 10px 8px; text-align: center; border-right: 1px solid #edf2f8; }
        .job-metric:last-child { border-right: none; }
        .job-metric-value { font-weight: 800; color: #24476a; font-size: 19px; line-height: 1.1; }
        .job-metric-label { color: #8a99aa; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }
        @media (max-width: 991px) {
            .job-posted-title { font-size: 18px; }
            .job-posted-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>
</head>
<body class="kh-proto-page">
<?php include 'navbar.php'; ?>
<?php kh_proto_render_hero('Daftar Pekerjaan', 'Pantau lowongan yang sudah diposting ke Karirhub.', 'Lowongan Kerja', 'karirhub_employer_prototype_pelaporan_lowongan', 'Proyek', 'karirhub_employer_prototype_job_posted_karirhub'); ?>

<div class="kh-content-wrap">
<div class="container py-4">
    <div class="kh-proto-shell">
    <?php kh_proto_render_sidebar('wllp_job_posted'); ?>
    <main class="kh-proto-main">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-0">Job Posted Karirhub</h3>
            <div class="text-muted small">Tampilan daftar lowongan posted seperti Karirhub Employer.</div>
        </div>
        <a class="btn btn-outline-primary btn-sm" href="karirhub_employer_prototype_dashboard_wllp">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard WLLP
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

    <form method="GET" class="card border-0 shadow-sm mb-3">
        <div class="card-body py-2 d-flex flex-wrap align-items-center gap-3">
            <div class="small fw-semibold text-muted">Status Lowongan:</div>
            <select name="status" class="form-select form-select-sm" style="width:auto;">
                <option value="all"<?php echo $statusFilter === 'all' ? ' selected' : ''; ?>>Semua</option>
                <option value="aktif"<?php echo $statusFilter === 'aktif' ? ' selected' : ''; ?>>Lowongan Aktif</option>
                <option value="ditutup"<?php echo $statusFilter === 'ditutup' ? ' selected' : ''; ?>>Lowongan Ditutup</option>
            </select>
            <div class="form-check m-0">
                <input class="form-check-input" type="checkbox" value="1" id="lokerTerbatas" name="loker_terbatas"<?php echo $lokerTerbatas ? ' checked' : ''; ?>>
                <label class="form-check-label small" for="lokerTerbatas">Loker Terbatas</label>
            </div>
            <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-funnel me-1"></i>Terapkan</button>
        </div>
    </form>

    <div class="job-posted-note mb-3">
        Perhatian: Data lowongan pekerjaan yang kamu input, akan masuk ke rencana penggunaan tenaga kerja pada layanan <strong>Wajib Lapor Ketenagakerjaan</strong>
    </div>

    <?php if (empty($filteredJobs)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body text-muted text-center py-4">Tidak ada lowongan sesuai filter saat ini.</div>
        </div>
    <?php else: ?>
        <?php foreach ($filteredJobs as $job): ?>
            <div class="job-posted-card">
                <div class="job-posted-card-head">
                    <div>
                        <div class="job-posted-title">
                            <a class="job-posted-title-link" href="karirhub_employer_prototype_job_posted_karirhub_detail?job=<?php echo rawurlencode((string)$job['judul']); ?>">
                                <?php echo h((string)$job['judul']); ?>
                            </a>
                        </div>
                        <div class="job-posted-loc"><i class="bi bi-geo-alt-fill me-1"></i><?php echo h((string)$job['lokasi']); ?></div>
                    </div>
                    <div class="job-posted-side-actions">
                        <span class="job-posted-status <?php echo h((string)$job['status']); ?>">
                            <?php echo (string)$job['status'] === 'ditutup' ? 'Lowongan Ditutup' : 'Lowongan Aktif'; ?>
                        </span>
                        <button
                            type="button"
                            class="btn btn-outline-primary btn-sm js-add-to-wllp-btn"
                            data-job-title="<?php echo h((string)$job['judul']); ?>"
                            data-bs-toggle="modal"
                            data-bs-target="#addToWllpModal"
                        >
                            <i class="bi bi-plus-circle me-1"></i>Tambahkan ke dalam WLLP
                        </button>
                    </div>
                </div>
                <div class="job-posted-metrics">
                    <div class="job-metric">
                        <div class="job-metric-value"><?php echo h((string)$job['metrics']['leads']); ?></div>
                        <div class="job-metric-label">Leads</div>
                    </div>
                    <div class="job-metric">
                        <div class="job-metric-value"><?php echo h((string)$job['metrics']['lamaran']); ?></div>
                        <div class="job-metric-label">Lamaran</div>
                    </div>
                    <div class="job-metric">
                        <div class="job-metric-value"><?php echo h((string)$job['metrics']['bookmark']); ?></div>
                        <div class="job-metric-label">Bookmark</div>
                    </div>
                    <div class="job-metric">
                        <div class="job-metric-value"><?php echo h((string)$job['metrics']['ditawarkan']); ?></div>
                        <div class="job-metric-label">Ditawarkan</div>
                    </div>
                    <div class="job-metric">
                        <div class="job-metric-value"><?php echo h((string)$job['metrics']['wawancara']); ?></div>
                        <div class="job-metric-label">Wawancara</div>
                    </div>
                    <div class="job-metric">
                        <div class="job-metric-value"><?php echo h((string)$job['metrics']['diterima']); ?></div>
                        <div class="job-metric-label">Diterima</div>
                    </div>
                    <div class="job-metric">
                        <div class="job-metric-value"><?php echo h((string)$job['metrics']['arsip']); ?></div>
                        <div class="job-metric-label">Arsip</div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
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

        <?php if (!empty($addErrors)): ?>
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
        <?php endif; ?>
    })();
</script>
<?php kh_proto_render_sidebar_script(); ?>
</body>
</html>
