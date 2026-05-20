<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_data.php';
require_once __DIR__ . '/karirhub_employer_prototype_ui.php';

if (!(current_user_can('karirhub_employer_prototype_view') || current_user_can('manage_settings'))) {
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
$registryRows = $dataset['vacancies'];

$filteredRows = array_values(array_filter($registryRows, static function (array $row) use ($query, $verifikasiFilter): bool {
    if ($verifikasiFilter !== 'all' && strtolower($row['status_verifikasi']) !== $verifikasiFilter) {
        return false;
    }
    if ($query === '') {
        return true;
    }

    $haystack = strtolower(implode(' ', [
        $row['no_reg_bukti'],
        $row['job_order_no'] ?? '',
        $row['id_lowongan'],
        $row['jabatan'],
        $row['hiring_manager'] ?? '',
        $row['unit_kode'],
        $row['petugas_input'],
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
<?php kh_proto_render_hero('Daftar Lowongan Kerja', 'Pencarian nomor registrasi bukti dan Job Order pada prototipe employer.', 'Lowongan Kerja', 'karirhub_employer_prototype_pelaporan_lowongan', 'Proyek', 'karirhub_employer_prototype_dashboard_wllp'); ?>

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
                    <label for="q" class="form-label mb-1">Cari No. Reg / Job Order / Jabatan / ID Lowongan</label>
                    <input
                        id="q"
                        name="q"
                        class="form-control form-control-sm"
                        value="<?php echo h($query); ?>"
                        placeholder="Contoh: WLLP-2026-0519, JO-2026-OPS-001, atau Staff Operasional"
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
                            <th>Job Order</th>
                            <th>ID Lowongan</th>
                            <th>Jabatan</th>
                            <th>Hiring Manager</th>
                            <th>Jumlah</th>
                            <th>Unit</th>
                            <th>Mode Publikasi</th>
                            <th>Dibuat Oleh</th>
                            <th>Tanggal Lapor</th>
                            <th>Status Verifikasi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($filteredRows)): ?>
                        <tr>
                            <td colspan="11" class="text-center text-muted">Data tidak ditemukan.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($filteredRows as $row): ?>
                            <?php $badgeClass = karirhub_proto_status_badge_class($row['status_verifikasi']); ?>
                            <tr>
                                <td class="fw-semibold"><?php echo h($row['no_reg_bukti']); ?></td>
                                <td>
                                    <div class="fw-semibold"><?php echo h((string)($row['job_order_no'] ?? '-')); ?></div>
                                    <div class="small text-muted"><?php echo h((string)($row['job_order_status'] ?? '-')); ?></div>
                                </td>
                                <td><?php echo h($row['id_lowongan']); ?></td>
                                <td><?php echo h($row['jabatan']); ?></td>
                                <td><?php echo h((string)($row['hiring_manager'] ?? '-')); ?></td>
                                <td><?php echo h((string)$row['jumlah_kebutuhan']); ?></td>
                                <td><?php echo h(($units[$row['unit_kode']]['nama'] ?? $row['unit_kode'])); ?></td>
                                <td><?php echo h($row['mode_publikasi']); ?></td>
                                <td><?php echo h($row['petugas_input']); ?></td>
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
