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

function build_query_url(array $params): string
{
    return http_build_query($params);
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

$baseParams = [
    'status' => $statusFilter,
    'unit' => $unitFilter,
    'q' => $query,
];
$rowMap = [];
foreach ($rows as $row) {
    $rowMap[$row['no_reg_bukti']] = $row;
}

$action = trim((string)($_GET['action'] ?? ''));
$actionNoReg = trim((string)($_GET['no_reg'] ?? ''));
$actionRow = ($actionNoReg !== '' && isset($rowMap[$actionNoReg])) ? $rowMap[$actionNoReg] : null;
$actionError = null;
if ($action !== '' && !in_array($action, ['lihat', 'cetak', 'unduh'], true)) {
    $actionError = 'Aksi tidak dikenali.';
}
if ($action !== '' && $actionRow === null && $actionError === null) {
    $actionError = 'Data bukti lapor tidak ditemukan.';
}

if ($action === 'unduh' && $actionRow !== null) {
    $unitName = $unitOptions[$actionRow['unit_kode']] ?? $actionRow['unit_kode'];
    $downloadText = "Bukti Lapor WLLP (Dummy)\n";
    $downloadText .= "No. Reg Bukti: " . $actionRow['no_reg_bukti'] . "\n";
    $downloadText .= "ID Lowongan: " . $actionRow['id_lowongan'] . "\n";
    $downloadText .= "Tanggal Lapor: " . $actionRow['tanggal_lapor'] . "\n";
    $downloadText .= "Jabatan: " . $actionRow['jabatan'] . "\n";
    $downloadText .= "Jumlah Kebutuhan: " . (string)$actionRow['jumlah_kebutuhan'] . "\n";
    $downloadText .= "Unit/Perusahaan: " . $unitName . "\n";
    $downloadText .= "Masa Berlaku: " . $actionRow['masa_berlaku_mulai'] . " s.d. " . $actionRow['masa_berlaku_sampai'] . "\n";
    $downloadText .= "Mode Publikasi: " . $actionRow['mode_publikasi'] . "\n";
    $downloadText .= "Petugas Input: " . $actionRow['petugas_input'] . "\n";
    $downloadText .= "Status Verifikasi: " . $actionRow['status_verifikasi'] . "\n";
    $downloadText .= "Status Keterisian: " . $actionRow['status_keterisian'] . "\n";
    $downloadText .= "Catatan: " . $actionRow['catatan'] . "\n";

    $filename = 'bukti-lapor-' . preg_replace('/[^A-Za-z0-9\-]/', '_', $actionRow['no_reg_bukti']) . '.txt';
    header('Content-Type: text/plain; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $downloadText;
    exit;
}
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

    <?php if ($actionError !== null): ?>
        <div class="alert alert-danger py-2"><?php echo h($actionError); ?></div>
    <?php endif; ?>

    <?php if ($action === 'cetak' && $actionRow !== null): ?>
        <div class="alert alert-success py-2">
            Simulasi cetak dijalankan untuk <strong><?php echo h($actionRow['no_reg_bukti']); ?></strong>.
            Jendela print akan otomatis terbuka.
        </div>
    <?php endif; ?>

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
                            <?php
                                $badgeClass = karirhub_proto_status_badge_class($row['status_verifikasi']);
                                $urlLihat = '?' . build_query_url(array_merge($baseParams, ['action' => 'lihat', 'no_reg' => $row['no_reg_bukti']]));
                                $urlCetak = '?' . build_query_url(array_merge($baseParams, ['action' => 'cetak', 'no_reg' => $row['no_reg_bukti']]));
                                $urlUnduh = '?' . build_query_url(array_merge($baseParams, ['action' => 'unduh', 'no_reg' => $row['no_reg_bukti']]));
                            ?>
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
                                        <a class="btn btn-outline-primary" href="<?php echo h($urlLihat); ?>">Lihat</a>
                                        <a class="btn btn-outline-secondary" href="<?php echo h($urlCetak); ?>">Cetak</a>
                                        <a class="btn btn-outline-dark" href="<?php echo h($urlUnduh); ?>">Unduh</a>
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

<?php if ($action === 'lihat' && $actionRow !== null): ?>
<?php $urlCetakFromModal = '?' . build_query_url(array_merge($baseParams, ['action' => 'cetak', 'no_reg' => $actionRow['no_reg_bukti']])); ?>
<div class="modal fade show" id="detailModal" tabindex="-1" aria-modal="true" role="dialog" style="display:block; background: rgba(0,0,0,0.35);">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Bukti Lapor - <?php echo h($actionRow['no_reg_bukti']); ?></h5>
                <a href="?<?php echo h(build_query_url($baseParams)); ?>" class="btn-close"></a>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><strong>ID Lowongan:</strong><br><?php echo h($actionRow['id_lowongan']); ?></div>
                    <div class="col-md-6"><strong>Jabatan:</strong><br><?php echo h($actionRow['jabatan']); ?></div>
                    <div class="col-md-6"><strong>Tanggal Lapor:</strong><br><?php echo h($actionRow['tanggal_lapor']); ?></div>
                    <div class="col-md-6"><strong>Jumlah Kebutuhan:</strong><br><?php echo h((string)$actionRow['jumlah_kebutuhan']); ?></div>
                    <div class="col-md-6"><strong>Unit/Perusahaan:</strong><br><?php echo h($unitOptions[$actionRow['unit_kode']] ?? $actionRow['unit_kode']); ?></div>
                    <div class="col-md-6"><strong>Mode Publikasi:</strong><br><?php echo h($actionRow['mode_publikasi']); ?></div>
                    <div class="col-md-6"><strong>Masa Berlaku:</strong><br><?php echo h($actionRow['masa_berlaku_mulai']); ?> s.d. <?php echo h($actionRow['masa_berlaku_sampai']); ?></div>
                    <div class="col-md-6"><strong>Status Verifikasi:</strong><br><?php echo h($actionRow['status_verifikasi']); ?></div>
                    <div class="col-md-6"><strong>Status Keterisian:</strong><br><?php echo h($actionRow['status_keterisian']); ?></div>
                    <div class="col-md-6"><strong>Petugas Input:</strong><br><?php echo h($actionRow['petugas_input']); ?></div>
                    <div class="col-12"><strong>Keterampilan Utama:</strong><br><?php echo h($actionRow['keterampilan_utama']); ?></div>
                    <div class="col-12"><strong>Catatan:</strong><br><?php echo h($actionRow['catatan']); ?></div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="?<?php echo h(build_query_url($baseParams)); ?>" class="btn btn-outline-secondary btn-sm">Tutup</a>
                <a href="<?php echo h($urlCetakFromModal); ?>" class="btn btn-primary btn-sm">Cetak</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($action === 'cetak' && $actionRow !== null): ?>
<script>
    (function () {
        const printWindow = window.open('', '_blank', 'width=900,height=700');
        if (!printWindow) return;
        const html = `
            <html>
            <head>
                <title>Bukti Lapor ${<?php echo json_encode($actionRow['no_reg_bukti']); ?>}</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 24px; }
                    h2 { margin-bottom: 6px; }
                    table { border-collapse: collapse; width: 100%; margin-top: 16px; }
                    td, th { border: 1px solid #ccc; padding: 8px; font-size: 13px; vertical-align: top; }
                    th { width: 220px; text-align: left; background: #f5f5f5; }
                    .muted { color: #666; font-size: 12px; margin-top: 8px; }
                </style>
            </head>
            <body>
                <h2>Bukti Lapor WLLP (Prototype)</h2>
                <div>No. Reg Bukti: <strong>${<?php echo json_encode($actionRow['no_reg_bukti']); ?>}</strong></div>
                <div class="muted">Dokumen simulasi dari prototype Karirhub Employer.</div>
                <table>
                    <tr><th>ID Lowongan</th><td>${<?php echo json_encode($actionRow['id_lowongan']); ?>}</td></tr>
                    <tr><th>Jabatan</th><td>${<?php echo json_encode($actionRow['jabatan']); ?>}</td></tr>
                    <tr><th>Tanggal Lapor</th><td>${<?php echo json_encode($actionRow['tanggal_lapor']); ?>}</td></tr>
                    <tr><th>Jumlah Kebutuhan</th><td>${<?php echo json_encode((string)$actionRow['jumlah_kebutuhan']); ?>}</td></tr>
                    <tr><th>Unit/Perusahaan</th><td>${<?php echo json_encode($unitOptions[$actionRow['unit_kode']] ?? $actionRow['unit_kode']); ?>}</td></tr>
                    <tr><th>Masa Berlaku</th><td>${<?php echo json_encode($actionRow['masa_berlaku_mulai'] . ' s.d. ' . $actionRow['masa_berlaku_sampai']); ?>}</td></tr>
                    <tr><th>Status Verifikasi</th><td>${<?php echo json_encode($actionRow['status_verifikasi']); ?>}</td></tr>
                    <tr><th>Status Keterisian</th><td>${<?php echo json_encode($actionRow['status_keterisian']); ?>}</td></tr>
                    <tr><th>Petugas Input</th><td>${<?php echo json_encode($actionRow['petugas_input']); ?>}</td></tr>
                    <tr><th>Catatan</th><td>${<?php echo json_encode($actionRow['catatan']); ?>}</td></tr>
                </table>
            </body>
            </html>
        `;
        printWindow.document.open();
        printWindow.document.write(html);
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
    })();
</script>
<?php endif; ?>
</body>
</html>
