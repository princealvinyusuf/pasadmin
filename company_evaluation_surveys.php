<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

if (!(current_user_can('company_evaluation_survey_manage') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['company_evaluation_survey_csrf'])) {
    $_SESSION['company_evaluation_survey_csrf'] = bin2hex(random_bytes(32));
}

function ces_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function ces_table_exists(mysqli $conn, string $table): bool
{
    $escaped = $conn->real_escape_string($table);
    $result = $conn->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$escaped' LIMIT 1");
    return $result && $result->num_rows > 0;
}

function ces_query_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $rows = [];
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $rows;
    }
    if ($types !== '') {
        $refs = [];
        $refs[] = &$types;
        foreach ($params as $key => $value) {
            $refs[] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
    }
    $stmt->close();
    return $rows;
}

function ces_sources($value): string
{
    $decoded = json_decode((string) $value, true);
    return is_array($decoded) ? implode(', ', $decoded) : (string) $value;
}

$ratingLabels = [
    'service_requirements_ease' => 'Kemudahan Persyaratan',
    'system_procedure_ease' => 'Sistem, Mekanisme & Prosedur',
    'service_speed' => 'Kecepatan/Kesesuaian Waktu',
    'fee_compliance' => 'Biaya/Tarif di Luar Ketentuan',
    'product_quality' => 'Kualitas Produk/Jasa',
    'officer_competence' => 'Kompetensi Petugas',
    'officer_behavior' => 'Perilaku Petugas',
    'facility_quality' => 'Sarana & Prasarana',
    'complaint_media_completeness' => 'Media Pengaduan/Saran',
    'karirhub_procedure_ease' => 'Prosedur Karirhub',
    'procedure_information_fit' => 'Kesesuaian Informasi Prosedur',
    'admin_service_hours_fit' => 'Waktu Pelayanan Admin',
    'candidate_fit' => 'Kesesuaian Kandidat',
];

$conn = @new mysqli('localhost', 'root', '', 'paskerid_db_prod');
$connectionError = $conn->connect_error ?: '';
if ($connectionError === '') {
    $conn->set_charset('utf8mb4');
}
$hasTable = $connectionError === '' && ces_table_exists($conn, 'company_evaluation_surveys');

$search = trim((string) ($_GET['q'] ?? ''));
$province = trim((string) ($_GET['province'] ?? ''));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ? $dateFrom : '';
$dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) ? $dateTo : '';
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

$filterQuery = array_filter([
    'q' => $search,
    'province' => $province,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
], static fn ($value) => $value !== '');

if ($hasTable && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['company_evaluation_survey_csrf'], $token)) {
        $_SESSION['error'] = 'Token keamanan tidak valid. Silakan coba kembali.';
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $conn->prepare('DELETE FROM company_evaluation_surveys WHERE id = ?');
        if ($id > 0 && $stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $_SESSION['success'] = $stmt->affected_rows > 0 ? 'Respons survei berhasil dihapus.' : 'Respons tidak ditemukan.';
            $stmt->close();
        } else {
            $_SESSION['error'] = 'Respons tidak valid.';
        }
    }
    $redirect = 'company_evaluation_surveys' . (!empty($filterQuery) ? ('?' . http_build_query($filterQuery)) : '');
    header('Location: ' . $redirect);
    exit;
}

$where = ['1=1'];
$types = '';
$params = [];
if ($search !== '') {
    $where[] = '(company_name LIKE ? OR respondent_name LIKE ? OR company_email LIKE ? OR phone LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
    $types .= 'ssss';
}
if ($province !== '') {
    $where[] = 'province = ?';
    $params[] = $province;
    $types .= 's';
}
if ($dateFrom !== '') {
    $where[] = 'DATE(created_at) >= ?';
    $params[] = $dateFrom;
    $types .= 's';
}
if ($dateTo !== '') {
    $where[] = 'DATE(created_at) <= ?';
    $params[] = $dateTo;
    $types .= 's';
}
$whereSql = ' WHERE ' . implode(' AND ', $where);

if ($hasTable && ($_GET['export'] ?? '') === 'csv') {
    $exportRows = ces_query_rows($conn, 'SELECT * FROM company_evaluation_surveys' . $whereSql . ' ORDER BY id DESC', $types, $params);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="survei_evaluasi_perusahaan_' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    if (!empty($exportRows)) {
        fputcsv($output, array_keys($exportRows[0]));
        foreach ($exportRows as $row) {
            $row['information_sources'] = ces_sources($row['information_sources'] ?? '');
            fputcsv($output, $row);
        }
    }
    fclose($output);
    exit;
}

$provinces = [];
$rows = [];
$selected = null;
$total = 0;
$averageScore = 0.0;
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;
$totalPages = 1;

if ($hasTable) {
    $provinceRows = ces_query_rows($conn, "SELECT DISTINCT province FROM company_evaluation_surveys WHERE province <> '' ORDER BY province");
    $provinces = array_column($provinceRows, 'province');

    $countRows = ces_query_rows($conn, 'SELECT COUNT(*) AS total FROM company_evaluation_surveys' . $whereSql, $types, $params);
    $total = (int) ($countRows[0]['total'] ?? 0);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $ratingSum = implode(' + ', array_keys($ratingLabels));
    $averageRows = ces_query_rows(
        $conn,
        "SELECT ROUND(AVG(($ratingSum) / " . count($ratingLabels) . '), 2) AS average_score FROM company_evaluation_surveys' . $whereSql,
        $types,
        $params
    );
    $averageScore = (float) ($averageRows[0]['average_score'] ?? 0);

    $listSql = 'SELECT id, company_name, respondent_name, position, company_email, province, obtained_worker, created_at, '
        . "ROUND(($ratingSum) / " . count($ratingLabels) . ', 2) AS average_score '
        . 'FROM company_evaluation_surveys' . $whereSql . " ORDER BY id DESC LIMIT $perPage OFFSET $offset";
    $rows = ces_query_rows($conn, $listSql, $types, $params);

    $viewId = (int) ($_GET['view'] ?? 0);
    if ($viewId > 0) {
        $detailRows = ces_query_rows($conn, 'SELECT * FROM company_evaluation_surveys WHERE id = ? LIMIT 1', 'i', [$viewId]);
        $selected = $detailRows[0] ?? null;
    }
}

$exportUrl = 'company_evaluation_surveys?' . http_build_query(array_merge($filterQuery, ['export' => 'csv']));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Survei Evaluasi Perusahaan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include __DIR__ . '/navbar.php'; ?>
<main class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="mb-1">Survei Evaluasi Perusahaan</h3>
            <div class="text-muted">Respons kuesioner pemberi kerja Karirhub</div>
        </div>
        <?php if ($hasTable): ?>
            <a class="btn btn-success" href="<?php echo ces_h($exportUrl); ?>"><i class="bi bi-download me-1"></i>Export CSV</a>
        <?php endif; ?>
    </div>

    <?php if ($connectionError !== ''): ?>
        <div class="alert alert-danger">Tidak dapat terhubung ke database survei.</div>
    <?php elseif (!$hasTable): ?>
        <div class="alert alert-warning">Tabel <code>company_evaluation_surveys</code> belum tersedia. Jalankan migrasi Laravel terlebih dahulu.</div>
    <?php else: ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo ces_h($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo ces_h($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="text-muted small">Total Respons</div>
                    <div class="fs-3 fw-bold"><?php echo number_format($total); ?></div>
                </div></div>
            </div>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="text-muted small">Rata-rata Penilaian</div>
                    <div class="fs-3 fw-bold"><?php echo number_format($averageScore, 2); ?><span class="fs-6 text-muted"> / 4</span></div>
                </div></div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <form method="get" class="row g-2">
                    <div class="col-lg-4">
                        <label class="form-label">Pencarian</label>
                        <input class="form-control" name="q" value="<?php echo ces_h($search); ?>" placeholder="Perusahaan, responden, email, atau telepon">
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <label class="form-label">Provinsi</label>
                        <select class="form-select" name="province">
                            <option value="">Semua provinsi</option>
                            <?php foreach ($provinces as $option): ?>
                                <option value="<?php echo ces_h($option); ?>" <?php echo $province === $option ? 'selected' : ''; ?>><?php echo ces_h($option); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 col-lg-2">
                        <label class="form-label">Dari tanggal</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo ces_h($dateFrom); ?>">
                    </div>
                    <div class="col-md-4 col-lg-2">
                        <label class="form-label">Sampai tanggal</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo ces_h($dateTo); ?>">
                    </div>
                    <div class="col-lg-1 d-flex align-items-end gap-2">
                        <button class="btn btn-primary" type="submit" title="Filter"><i class="bi bi-search"></i></button>
                        <a class="btn btn-outline-secondary" href="company_evaluation_surveys" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selected): ?>
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>Detail Respons #<?php echo (int) $selected['id']; ?></strong>
                    <a class="btn btn-sm btn-outline-secondary" href="company_evaluation_surveys?<?php echo ces_h(http_build_query($filterQuery)); ?>">Tutup</a>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4"><strong>Perusahaan:</strong><br><?php echo ces_h($selected['company_name']); ?></div>
                        <div class="col-md-4"><strong>Responden:</strong><br><?php echo ces_h($selected['respondent_name']); ?></div>
                        <div class="col-md-4"><strong>Jabatan:</strong><br><?php echo ces_h($selected['position'] === 'Lainnya' ? $selected['position_other'] : $selected['position']); ?></div>
                        <div class="col-md-4"><strong>Email:</strong><br><?php echo ces_h($selected['company_email']); ?></div>
                        <div class="col-md-4"><strong>No. Handphone:</strong><br><?php echo ces_h($selected['phone']); ?></div>
                        <div class="col-md-4"><strong>Provinsi:</strong><br><?php echo ces_h($selected['province']); ?></div>
                        <div class="col-md-4"><strong>Pendampingan input:</strong><br><?php echo ces_h($selected['input_assistance']); ?></div>
                        <div class="col-md-4"><strong>Memperoleh tenaga kerja:</strong><br><?php echo ces_h($selected['obtained_worker']); ?></div>
                        <div class="col-md-4"><strong>Waktu tunggu:</strong><br><?php echo ces_h($selected['waiting_time'] ?: '-'); ?></div>
                        <div class="col-md-8"><strong>Sumber informasi:</strong><br><?php echo ces_h(ces_sources($selected['information_sources'])); ?><?php echo $selected['information_source_other'] ? ' — ' . ces_h($selected['information_source_other']) : ''; ?></div>
                        <div class="col-md-4"><strong>Submitted:</strong><br><?php echo ces_h($selected['created_at']); ?></div>
                    </div>
                    <h5>Penilaian</h5>
                    <div class="row g-2">
                        <?php foreach ($ratingLabels as $field => $label): ?>
                            <div class="col-md-6 col-xl-4">
                                <div class="border rounded p-2 h-100">
                                    <div class="small text-muted"><?php echo ces_h($label); ?></div>
                                    <strong><?php echo (int) $selected[$field]; ?> / 4</strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Perusahaan</th>
                            <th>Responden</th>
                            <th>Provinsi</th>
                            <th>Tenaga Kerja</th>
                            <th>Nilai Rata-rata</th>
                            <th>Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Belum ada respons survei.</td></tr>
                        <?php else: foreach ($rows as $row): ?>
                            <tr>
                                <td><?php echo (int) $row['id']; ?></td>
                                <td><strong><?php echo ces_h($row['company_name']); ?></strong><br><span class="small text-muted"><?php echo ces_h($row['company_email']); ?></span></td>
                                <td><?php echo ces_h($row['respondent_name']); ?><br><span class="small text-muted"><?php echo ces_h($row['position']); ?></span></td>
                                <td><?php echo ces_h($row['province']); ?></td>
                                <td><?php echo ces_h($row['obtained_worker']); ?></td>
                                <td><?php echo number_format((float) $row['average_score'], 2); ?> / 4</td>
                                <td><?php echo ces_h($row['created_at']); ?></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a class="btn btn-sm btn-outline-primary" href="?<?php echo ces_h(http_build_query(array_merge($filterQuery, ['page' => $page, 'view' => (int) $row['id']]))); ?>" title="Detail"><i class="bi bi-eye"></i></a>
                                        <form method="post" onsubmit="return confirm('Hapus respons survei ini?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo ces_h($_SESSION['company_evaluation_survey_csrf']); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo (int) $row['id']; ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Hapus"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPages > 1): ?>
                <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Halaman <?php echo $page; ?> dari <?php echo $totalPages; ?></span>
                    <div class="btn-group">
                        <a class="btn btn-sm btn-outline-primary <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="?<?php echo ces_h(http_build_query(array_merge($filterQuery, ['page' => max(1, $page - 1)]))); ?>">Sebelumnya</a>
                        <a class="btn btn-sm btn-outline-primary <?php echo $page >= $totalPages ? 'disabled' : ''; ?>" href="?<?php echo ces_h(http_build_query(array_merge($filterQuery, ['page' => min($totalPages, $page + 1)]))); ?>">Berikutnya</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php if ($connectionError === '') { $conn->close(); } ?>
