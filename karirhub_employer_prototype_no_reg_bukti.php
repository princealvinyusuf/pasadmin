<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_data.php';
require_once __DIR__ . '/karirhub_employer_prototype_storage.php';
require_once __DIR__ . '/karirhub_employer_prototype_ui.php';
require_once __DIR__ . '/db.php';

if (!kh_proto_can_access('karirhub_employer_prototype_no_reg_bukti_view')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$query = strtolower(trim((string)($_GET['q'] ?? '')));
$verifikasiFilter = trim((string)($_GET['verifikasi'] ?? 'all'));
$allowedFilters = ['all', 'terverifikasi', 'perlu update'];
if (!in_array($verifikasiFilter, $allowedFilters, true)) {
    $verifikasiFilter = 'all';
}

$dataset = karirhub_proto_dataset();
$units = $dataset['units'];
kh_proto_ensure_multi_tables($conn);
kh_proto_seed_multi_from_dataset($conn, $dataset, $units);

$allRows = [];
$res = $conn->query("
    SELECT
        h.no_reg_bukti,
        h.unit_kode,
        h.unit_nama,
        h.periode_tipe,
        CAST(h.periode_mulai AS CHAR) AS periode_mulai,
        CAST(h.periode_selesai AS CHAR) AS periode_selesai,
        h.status_verifikasi,
        CAST(MAX(d.created_at) AS CHAR) AS tanggal_lapor,
        SUM(d.jumlah_kebutuhan) AS total_kebutuhan,
        COUNT(d.id_lowongan) AS total_lowongan,
        GROUP_CONCAT(d.id_lowongan ORDER BY d.id_lowongan SEPARATOR ', ') AS daftar_id_lowongan,
        GROUP_CONCAT(DISTINCT d.jabatan ORDER BY d.jabatan SEPARATOR ', ') AS daftar_jabatan
    FROM karirhub_proto_wllp_laporan h
    JOIN karirhub_proto_wllp_pelaporan d ON d.no_reg_bukti = h.no_reg_bukti
    GROUP BY h.no_reg_bukti, h.unit_kode, h.unit_nama, h.periode_tipe, h.periode_mulai, h.periode_selesai, h.status_verifikasi
    ORDER BY h.created_at DESC, h.no_reg_bukti DESC
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $allRows[] = $row;
    }
}

$filteredRows = array_values(array_filter($allRows, static function (array $row) use ($query, $verifikasiFilter): bool {
    if ($verifikasiFilter !== 'all' && strtolower((string)$row['status_verifikasi']) !== $verifikasiFilter) {
        return false;
    }
    if ($query === '') {
        return true;
    }
    $haystack = strtolower(implode(' ', [
        (string)$row['no_reg_bukti'],
        (string)$row['daftar_id_lowongan'],
        (string)$row['daftar_jabatan'],
        (string)$row['unit_kode'],
        (string)$row['unit_nama'],
        (string)$row['periode_tipe'],
    ]));
    return strpos($haystack, $query) !== false;
}));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karirhub Employer Prototype - No. Reg Bukti</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php kh_proto_render_styles(); ?>
</head>
<body class="kh-proto-page">
<?php include 'navbar.php'; ?>
<?php kh_proto_render_hero('Daftar Lowongan Kerja', 'Pencarian nomor registrasi bukti pada prototipe employer.', 'Lowongan Kerja', 'karirhub_employer_prototype_pelaporan_lowongan', 'Proyek', 'karirhub_employer_prototype_dashboard_wllp'); ?>

<div class="kh-content-wrap">
<div class="container py-4">
    <div class="kh-proto-shell">
    <?php kh_proto_render_sidebar('wllp_no_reg_bukti'); ?>
    <main class="kh-proto-main">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-0">No. Reg Bukti</h3>
            <div class="text-muted small">Karirhub Employer Prototype (reference only)</div>
        </div>
        <a class="btn btn-outline-primary btn-sm" href="karirhub_employer_prototype_dashboard_wllp">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard WLLP
        </a>
    </div>

    <form method="GET" class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-6">
                    <label for="q" class="form-label mb-1">Cari No. Reg / ID Lowongan / Jabatan</label>
                    <input
                        id="q"
                        name="q"
                        class="form-control form-control-sm"
                        value="<?php echo h($query); ?>"
                        placeholder="Contoh: WLLP-572605-00001278 atau LK-000123"
                    >
                </div>
                <div class="col-12 col-md-3">
                    <label for="verifikasi" class="form-label mb-1">Status Verifikasi</label>
                    <select id="verifikasi" name="verifikasi" class="form-select form-select-sm">
                        <option value="all"<?php echo $verifikasiFilter === 'all' ? ' selected' : ''; ?>>Semua</option>
                        <option value="terverifikasi"<?php echo $verifikasiFilter === 'terverifikasi' ? ' selected' : ''; ?>>Terverifikasi</option>
                        <option value="perlu update"<?php echo $verifikasiFilter === 'perlu update' ? ' selected' : ''; ?>>Perlu Update</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search me-1"></i>Cari
                    </button>
                </div>
                <div class="col-12 col-md-2 d-grid">
                    <a class="btn btn-outline-secondary btn-sm" href="karirhub_employer_prototype_no_reg_bukti">
                        Reset
                    </a>
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
                            <th>Daftar ID Lowongan</th>
                            <th>Jabatan</th>
                            <th>Total Kebutuhan</th>
                            <th>Unit</th>
                            <th>Tanggal Lapor</th>
                            <th>Status Verifikasi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($filteredRows)): ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">Data tidak ditemukan.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($filteredRows as $row): ?>
                            <?php $badgeClass = karirhub_proto_status_badge_class($row['status_verifikasi']); ?>
                            <tr>
                                <td class="fw-semibold"><?php echo h($row['no_reg_bukti']); ?></td>
                                <td><?php echo h(strtoupper((string)$row['periode_tipe']) . ' (' . (string)$row['periode_mulai'] . ' s.d. ' . (string)$row['periode_selesai'] . ')'); ?></td>
                                <td><?php echo h((string)$row['total_lowongan']); ?></td>
                                <td class="small"><?php echo h((string)$row['daftar_id_lowongan']); ?></td>
                                <td><?php echo h((string)$row['daftar_jabatan']); ?></td>
                                <td><?php echo h((string)$row['total_kebutuhan']); ?></td>
                                <td><?php echo h(($units[$row['unit_kode']]['nama'] ?? $row['unit_nama'])); ?></td>
                                <td><?php echo h($row['tanggal_lapor']); ?></td>
                                <td>
                                    <span class="badge text-bg-<?php echo h($badgeClass); ?>">
                                        <?php echo h($row['status_verifikasi']); ?>
                                    </span>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php kh_proto_render_sidebar_script(); ?>
</body>
</html>
