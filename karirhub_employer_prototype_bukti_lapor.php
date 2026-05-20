<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_data.php';

if (!(current_user_can('karirhub_employer_prototype_view') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
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
$unitOptions = [];
foreach ($units as $unitCode => $unitInfo) {
    $unitOptions[$unitCode] = $unitInfo['nama'];
}
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
            $row['petugas_input'],
            $row['catatan'],
        ]));
        if (strpos($haystack, $query) === false) {
            return false;
        }
    }
    return true;
}));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karirhub Employer Prototype - Bukti Lapor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-0">Bukti Lapor</h3>
            <div class="text-muted small">Karirhub Employer Prototype (reference only)</div>
        </div>
        <a class="btn btn-outline-primary btn-sm" href="karirhub_employer_prototype_dashboard_wllp">
            <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard WLLP
        </a>
    </div>

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
                            <th>Mode</th>
                            <th>Petugas</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($filteredRows)): ?>
                        <tr>
                            <td colspan="11" class="text-center text-muted">Tidak ada data sesuai filter.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($filteredRows as $row): ?>
                            <?php $badgeClass = karirhub_proto_status_badge_class($row['status_verifikasi']); ?>
                            <tr>
                                <td class="fw-semibold"><?php echo h($row['no_reg_bukti']); ?></td>
                                <td><?php echo h($row['id_lowongan']); ?></td>
                                <td><?php echo h($row['tanggal_lapor']); ?></td>
                                <td><?php echo h($row['jabatan']); ?></td>
                                <td><?php echo h((string)$row['jumlah_kebutuhan']); ?></td>
                                <td><?php echo h($unitOptions[$row['unit_kode']] ?? $row['unit_kode']); ?></td>
                                <td><?php echo h($row['masa_berlaku_sampai']); ?></td>
                                <td><?php echo h($row['mode_publikasi']); ?></td>
                                <td><?php echo h($row['petugas_input']); ?></td>
                                <td><span class="badge text-bg-<?php echo h($badgeClass); ?>"><?php echo h($row['status_verifikasi']); ?></span></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button class="btn btn-outline-primary" type="button">Lihat</button>
                                        <button class="btn btn-outline-secondary" type="button">Cetak</button>
                                        <button class="btn btn-outline-dark" type="button">Unduh PDF</button>
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
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
