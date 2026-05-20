<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

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
$allowedStatuses = ['all', 'valid', 'need-update'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}

$rows = [
    ['no_reg' => 'WLLP-2026-0519-001278', 'tanggal' => '20 Mei 2026', 'jabatan' => 'Staff Operasional', 'jumlah' => '4', 'unit' => 'PT Contoh Nusantara', 'masa_berlaku' => '20 Jun 2026', 'status' => 'valid'],
    ['no_reg' => 'WLLP-2026-0518-001249', 'tanggal' => '18 Mei 2026', 'jabatan' => 'Admin HR', 'jumlah' => '2', 'unit' => 'PT Contoh Nusantara', 'masa_berlaku' => '18 Jun 2026', 'status' => 'valid'],
    ['no_reg' => 'WLLP-2026-0514-001180', 'tanggal' => '14 Mei 2026', 'jabatan' => 'Digital Marketing', 'jumlah' => '1', 'unit' => 'PT Contoh Nusantara', 'masa_berlaku' => '14 Jun 2026', 'status' => 'need-update'],
    ['no_reg' => 'WLLP-2026-0510-001032', 'tanggal' => '10 Mei 2026', 'jabatan' => 'Finance Officer', 'jumlah' => '2', 'unit' => 'PT Contoh Nusantara', 'masa_berlaku' => '10 Jun 2026', 'status' => 'valid'],
];

$filteredRows = array_values(array_filter($rows, static function (array $row) use ($statusFilter): bool {
    if ($statusFilter === 'all') {
        return true;
    }
    return $row['status'] === $statusFilter;
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
                        <option value="valid"<?php echo $statusFilter === 'valid' ? ' selected' : ''; ?>>Valid</option>
                        <option value="need-update"<?php echo $statusFilter === 'need-update' ? ' selected' : ''; ?>>Perlu Update</option>
                    </select>
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
                            <th>Tanggal Lapor</th>
                            <th>Jabatan</th>
                            <th>Jumlah</th>
                            <th>Unit/Perusahaan</th>
                            <th>Masa Berlaku</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($filteredRows)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">Tidak ada data sesuai filter.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($filteredRows as $row): ?>
                            <?php $badgeClass = $row['status'] === 'valid' ? 'success' : 'warning'; ?>
                            <tr>
                                <td class="fw-semibold"><?php echo h($row['no_reg']); ?></td>
                                <td><?php echo h($row['tanggal']); ?></td>
                                <td><?php echo h($row['jabatan']); ?></td>
                                <td><?php echo h($row['jumlah']); ?></td>
                                <td><?php echo h($row['unit']); ?></td>
                                <td><?php echo h($row['masa_berlaku']); ?></td>
                                <td><span class="badge text-bg-<?php echo h($badgeClass); ?>"><?php echo h($row['status'] === 'valid' ? 'Valid' : 'Perlu Update'); ?></span></td>
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
