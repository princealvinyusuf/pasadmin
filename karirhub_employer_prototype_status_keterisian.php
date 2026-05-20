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

$dataset = karirhub_proto_dataset();
$units = $dataset['units'];
$rows = $dataset['vacancies'];

$statusFilter = trim((string)($_GET['status'] ?? 'all'));
$allowedStatus = ['all', 'belum terisi', 'proses seleksi', 'terisi', 'belum update'];
if (!in_array($statusFilter, $allowedStatus, true)) {
    $statusFilter = 'all';
}
$unitFilter = trim((string)($_GET['unit'] ?? 'all'));
if ($unitFilter !== 'all' && !isset($units[$unitFilter])) {
    $unitFilter = 'all';
}

$simulatedNoReg = trim((string)($_GET['simulate_no_reg'] ?? ''));
$simulatedStatus = trim((string)($_GET['simulate_status'] ?? ''));
$successMessage = null;
if ($simulatedNoReg !== '' && in_array($simulatedStatus, ['Belum Terisi', 'Proses Seleksi', 'Terisi', 'Belum Update'], true)) {
    $successMessage = 'Simulasi update status untuk ' . $simulatedNoReg . ' -> ' . $simulatedStatus . ' berhasil (dummy, tidak disimpan permanen).';
}

$filteredRows = array_values(array_filter($rows, static function (array $row) use ($statusFilter, $unitFilter): bool {
    if ($statusFilter !== 'all' && strtolower($row['status_keterisian']) !== $statusFilter) {
        return false;
    }
    if ($unitFilter !== 'all' && $row['unit_kode'] !== $unitFilter) {
        return false;
    }
    return true;
}));

$countByStatus = ['Belum Terisi' => 0, 'Proses Seleksi' => 0, 'Terisi' => 0, 'Belum Update' => 0];
foreach ($rows as $row) {
    if (isset($countByStatus[$row['status_keterisian']])) {
        $countByStatus[$row['status_keterisian']]++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karirhub Employer Prototype - Status Keterisian</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php kh_proto_render_styles(); ?>
</head>
<body class="kh-proto-page">
<?php include 'navbar.php'; ?>
<?php kh_proto_render_hero('Daftar Lowongan Kerja', 'Pantau dan simulasikan status keterisian lowongan seperti dashboard employer.', 'Lowongan Kerja', 'karirhub_employer_prototype_pelaporan_lowongan', 'Proyek', 'karirhub_employer_prototype_dashboard_wllp'); ?>

<div class="kh-content-wrap">
<div class="container py-4">
    <div class="kh-proto-shell">
    <?php kh_proto_render_sidebar('wllp_status_keterisian'); ?>
    <main class="kh-proto-main">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-0">Status Keterisian</h3>
            <div class="text-muted small">Simulasi update status lowongan WLLP</div>
        </div>
        <a class="btn btn-outline-primary btn-sm" href="karirhub_employer_prototype_dashboard_wllp">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard WLLP
        </a>
    </div>

    <?php if ($successMessage !== null): ?>
        <div class="alert alert-success py-2"><?php echo h($successMessage); ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <?php foreach ($countByStatus as $statusName => $statusCount): ?>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small"><?php echo h($statusName); ?></div>
                        <div class="fs-4 fw-semibold text-<?php echo h(karirhub_proto_status_badge_class($statusName)); ?>"><?php echo h((string)$statusCount); ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <form method="GET" class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label mb-1">Status Keterisian</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="all"<?php echo $statusFilter === 'all' ? ' selected' : ''; ?>>Semua Status</option>
                        <option value="belum terisi"<?php echo $statusFilter === 'belum terisi' ? ' selected' : ''; ?>>Belum Terisi</option>
                        <option value="proses seleksi"<?php echo $statusFilter === 'proses seleksi' ? ' selected' : ''; ?>>Proses Seleksi</option>
                        <option value="terisi"<?php echo $statusFilter === 'terisi' ? ' selected' : ''; ?>>Terisi</option>
                        <option value="belum update"<?php echo $statusFilter === 'belum update' ? ' selected' : ''; ?>>Belum Update</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label mb-1">Unit Perusahaan</label>
                    <select name="unit" class="form-select form-select-sm">
                        <option value="all"<?php echo $unitFilter === 'all' ? ' selected' : ''; ?>>Semua Unit</option>
                        <?php foreach ($units as $unitCode => $unit): ?>
                            <option value="<?php echo h($unitCode); ?>"<?php echo $unitFilter === $unitCode ? ' selected' : ''; ?>><?php echo h($unit['nama']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
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
                            <th>Jabatan</th>
                            <th>Unit</th>
                            <th>Status Saat Ini</th>
                            <th>Tanggal Lapor</th>
                            <th>Tanggal Terisi</th>
                            <th>Simulasi Update</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($filteredRows)): ?>
                        <tr><td colspan="8" class="text-center text-muted">Tidak ada data.</td></tr>
                    <?php else: ?>
                        <?php foreach ($filteredRows as $row): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo h($row['no_reg_bukti']); ?></td>
                                <td><?php echo h($row['id_lowongan']); ?></td>
                                <td><?php echo h($row['jabatan']); ?></td>
                                <td><?php echo h($units[$row['unit_kode']]['nama'] ?? $row['unit_kode']); ?></td>
                                <td><span class="badge text-bg-<?php echo h(karirhub_proto_status_badge_class($row['status_keterisian'])); ?>"><?php echo h($row['status_keterisian']); ?></span></td>
                                <td><?php echo h($row['tanggal_lapor']); ?></td>
                                <td><?php echo h((string)($row['tanggal_terisi'] ?? '-')); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a class="btn btn-outline-secondary" href="?status=<?php echo h(urlencode($statusFilter)); ?>&unit=<?php echo h(urlencode($unitFilter)); ?>&simulate_no_reg=<?php echo h(urlencode($row['no_reg_bukti'])); ?>&simulate_status=Belum%20Terisi">Belum</a>
                                        <a class="btn btn-outline-info" href="?status=<?php echo h(urlencode($statusFilter)); ?>&unit=<?php echo h(urlencode($unitFilter)); ?>&simulate_no_reg=<?php echo h(urlencode($row['no_reg_bukti'])); ?>&simulate_status=Proses%20Seleksi">Seleksi</a>
                                        <a class="btn btn-outline-success" href="?status=<?php echo h(urlencode($statusFilter)); ?>&unit=<?php echo h(urlencode($unitFilter)); ?>&simulate_no_reg=<?php echo h(urlencode($row['no_reg_bukti'])); ?>&simulate_status=Terisi">Terisi</a>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php kh_proto_render_sidebar_script(); ?>
</body>
</html>
