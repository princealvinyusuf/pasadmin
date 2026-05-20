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

$query = strtolower(trim((string)($_GET['q'] ?? '')));

$registryRows = [
    ['no_reg' => 'WLLP-2026-0519-001278', 'lowongan_id' => 'LK-000987', 'jabatan' => 'Staff Operasional', 'jumlah' => '4', 'created_by' => 'admin@contoh.co.id', 'created_at' => '20 Mei 2026 08:10', 'verifikasi' => 'Terverifikasi'],
    ['no_reg' => 'WLLP-2026-0518-001249', 'lowongan_id' => 'LK-000984', 'jabatan' => 'Admin HR', 'jumlah' => '2', 'created_by' => 'hr@contoh.co.id', 'created_at' => '18 Mei 2026 09:14', 'verifikasi' => 'Terverifikasi'],
    ['no_reg' => 'WLLP-2026-0514-001180', 'lowongan_id' => 'LK-000971', 'jabatan' => 'Digital Marketing', 'jumlah' => '1', 'created_by' => 'hrd@contoh.co.id', 'created_at' => '14 Mei 2026 10:26', 'verifikasi' => 'Menunggu Update'],
    ['no_reg' => 'WLLP-2026-0510-001032', 'lowongan_id' => 'LK-000954', 'jabatan' => 'Finance Officer', 'jumlah' => '2', 'created_by' => 'finance.hr@contoh.co.id', 'created_at' => '10 Mei 2026 15:40', 'verifikasi' => 'Terverifikasi'],
];

$filteredRows = array_values(array_filter($registryRows, static function (array $row) use ($query): bool {
    if ($query === '') {
        return true;
    }

    $haystack = strtolower(implode(' ', $row));
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
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>

<div class="container py-4">
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
                    <label for="q" class="form-label mb-1">Cari No. Reg / Jabatan / ID Lowongan</label>
                    <input
                        id="q"
                        name="q"
                        class="form-control form-control-sm"
                        value="<?php echo h($query); ?>"
                        placeholder="Contoh: WLLP-2026-0519 atau Staff Operasional"
                    >
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
                            <th>ID Lowongan</th>
                            <th>Jabatan</th>
                            <th>Jumlah</th>
                            <th>Dibuat Oleh</th>
                            <th>Waktu Buat</th>
                            <th>Status Verifikasi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($filteredRows)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">Data tidak ditemukan.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($filteredRows as $row): ?>
                            <?php $isVerified = $row['verifikasi'] === 'Terverifikasi'; ?>
                            <tr>
                                <td class="fw-semibold"><?php echo h($row['no_reg']); ?></td>
                                <td><?php echo h($row['lowongan_id']); ?></td>
                                <td><?php echo h($row['jabatan']); ?></td>
                                <td><?php echo h($row['jumlah']); ?></td>
                                <td><?php echo h($row['created_by']); ?></td>
                                <td><?php echo h($row['created_at']); ?></td>
                                <td>
                                    <span class="badge text-bg-<?php echo h($isVerified ? 'success' : 'warning'); ?>">
                                        <?php echo h($row['verifikasi']); ?>
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
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
