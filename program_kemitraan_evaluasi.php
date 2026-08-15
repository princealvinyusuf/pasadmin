<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

if (!(current_user_can('program_kemitraan_manage') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db_prod';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int) $_POST['delete_id'];
    if ($deleteId > 0) {
        $conn->query("DELETE FROM program_kemitraan_evaluation_answers WHERE evaluation_id = {$deleteId}");
        $conn->query("DELETE FROM program_kemitraan_evaluation_rtl_items WHERE evaluation_id = {$deleteId}");
        $conn->query("DELETE FROM program_kemitraan_evaluations WHERE id = {$deleteId}");
        header('Location: program_kemitraan_evaluasi.php?msg=deleted');
        exit;
    }
}

function table_exists(mysqli $conn, string $table): bool
{
    $t = $conn->real_escape_string($table);
    $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$headerTableReady = table_exists($conn, 'program_kemitraan_evaluations');
$answerTableReady = table_exists($conn, 'program_kemitraan_evaluation_answers');
$rtlTableReady = table_exists($conn, 'program_kemitraan_evaluation_rtl_items');

$totalCount = 0;
$thisMonthCount = 0;
$avgScore = null;
$rows = [];

if ($headerTableReady) {
    $countRes = $conn->query("SELECT COUNT(*) AS c FROM program_kemitraan_evaluations");
    if ($countRes && ($r = $countRes->fetch_assoc())) {
        $totalCount = (int) ($r['c'] ?? 0);
    }
    if ($countRes) {
        $countRes->free();
    }

    $monthRes = $conn->query("SELECT COUNT(*) AS c FROM program_kemitraan_evaluations WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");
    if ($monthRes && ($r = $monthRes->fetch_assoc())) {
        $thisMonthCount = (int) ($r['c'] ?? 0);
    }
    if ($monthRes) {
        $monthRes->free();
    }

    if ($answerTableReady) {
        $avgRes = $conn->query("SELECT ROUND(AVG(score), 2) AS avg_score FROM program_kemitraan_evaluation_answers WHERE score IS NOT NULL");
        if ($avgRes && ($r = $avgRes->fetch_assoc())) {
            $avgScore = $r['avg_score'] !== null ? (float) $r['avg_score'] : null;
        }
        if ($avgRes) {
            $avgRes->free();
        }
    }

    $query = "
        SELECT
            e.id,
            e.activity_name,
            e.activity_theme,
            e.activity_date,
            e.activity_organizer,
            e.respondent_category,
            e.participation_mode,
            e.evaluator_name,
            e.recap_result_category,
            e.recap_overall_score,
            e.created_at,
            COALESCE(a.answer_count, 0) AS answer_count,
            COALESCE(r.rtl_count, 0) AS rtl_count
        FROM program_kemitraan_evaluations e
        LEFT JOIN (
            SELECT evaluation_id, COUNT(*) AS answer_count
            FROM program_kemitraan_evaluation_answers
            GROUP BY evaluation_id
        ) a ON a.evaluation_id = e.id
        LEFT JOIN (
            SELECT evaluation_id, COUNT(*) AS rtl_count
            FROM program_kemitraan_evaluation_rtl_items
            GROUP BY evaluation_id
        ) r ON r.evaluation_id = e.id
        ORDER BY e.id DESC
    ";

    if (!$answerTableReady || !$rtlTableReady) {
        $query = "
            SELECT
                e.id,
                e.activity_name,
                e.activity_theme,
                e.activity_date,
                e.activity_organizer,
                e.respondent_category,
                e.participation_mode,
                e.evaluator_name,
                e.recap_result_category,
                e.recap_overall_score,
                e.created_at,
                0 AS answer_count,
                0 AS rtl_count
            FROM program_kemitraan_evaluations e
            ORDER BY e.id DESC
        ";
    }

    $res = $conn->query($query);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        $res->free();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Program Kemitraan Evaluasi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background:
                radial-gradient(1200px 520px at -8% -10%, rgba(37, 99, 235, 0.11), transparent 55%),
                radial-gradient(900px 500px at 110% -8%, rgba(16, 185, 129, 0.11), transparent 56%),
                #f4f7fb;
        }
        .pk-admin-shell {
            border: 1px solid rgba(148, 163, 184, 0.25);
            border-radius: 18px;
            box-shadow: 0 22px 45px rgba(15, 23, 42, 0.09);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(6px);
            overflow: hidden;
        }
        .pk-admin-header {
            padding: 1.15rem 1.35rem;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.10), rgba(16, 185, 129, 0.10));
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
        }
        .pk-admin-title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
        }
        .pk-admin-subtitle {
            margin: 0.15rem 0 0;
            color: #475569;
            font-size: 0.93rem;
        }
        .pk-admin-content {
            padding: 1.2rem 1.25rem 1.3rem;
        }
        .table-responsive {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: auto;
        }
        .table thead th {
            background: #f8fafc;
            font-size: 0.84rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #334155;
            white-space: nowrap;
        }
        .metric-card {
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 14px;
            background: #fff;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>

<div class="container mt-4 mb-5">
    <div class="pk-admin-shell">
        <div class="pk-admin-header">
            <h2 class="pk-admin-title">Program Kemitraan - Form Evaluasi</h2>
            <p class="pk-admin-subtitle">Monitoring data evaluasi publik yang dikirim dari tab Form Evaluasi.</p>
        </div>
        <div class="pk-admin-content">
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    Data evaluasi berhasil dihapus.
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!$headerTableReady): ?>
                <div class="alert alert-warning mb-3">
                    Tabel <code>program_kemitraan_evaluations</code> belum tersedia. Jalankan migrasi Laravel terlebih dahulu.
                </div>
            <?php endif; ?>

            <?php if ($headerTableReady && (!$answerTableReady || !$rtlTableReady)): ?>
                <div class="alert alert-warning mb-3">
                    Tabel detail evaluasi belum lengkap.
                    <?php if (!$answerTableReady): ?><code>program_kemitraan_evaluation_answers</code><?php endif; ?>
                    <?php if (!$answerTableReady && !$rtlTableReady): ?> dan <?php endif; ?>
                    <?php if (!$rtlTableReady): ?><code>program_kemitraan_evaluation_rtl_items</code><?php endif; ?>
                    tidak ditemukan.
                </div>
            <?php endif; ?>

            <div class="row g-3 mb-4">
                <div class="col-12 col-md-4">
                    <div class="metric-card p-3 text-center">
                        <div class="text-muted small">Total Evaluasi</div>
                        <div class="display-6 fw-bold text-primary"><?php echo (int) $totalCount; ?></div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="metric-card p-3 text-center">
                        <div class="text-muted small">Evaluasi Bulan Ini</div>
                        <div class="display-6 fw-bold text-success"><?php echo (int) $thisMonthCount; ?></div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="metric-card p-3 text-center">
                        <div class="text-muted small">Rata-rata Skor Global</div>
                        <div class="display-6 fw-bold text-dark"><?php echo $avgScore !== null ? e(number_format($avgScore, 2)) : '-'; ?></div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped bg-white mb-0">
                    <thead>
                        <tr>
                            <th>Actions</th>
                            <th>ID</th>
                            <th>Nama Kegiatan</th>
                            <th>Tanggal</th>
                            <th>Penyelenggara</th>
                            <th>Kategori Responden</th>
                            <th>Moda</th>
                            <th>Evaluator</th>
                            <th>Skor Rekap</th>
                            <th>Kategori Hasil</th>
                            <th>Jawaban Rubrik</th>
                            <th>Item RTL</th>
                            <th>Dibuat</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="13" class="text-center text-muted">Belum ada data Form Evaluasi.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="program_kemitraan_evaluasi_detail?id=<?php echo (int) ($row['id'] ?? 0); ?>" class="btn btn-sm btn-outline-primary">Detail</a>
                                        <form method="POST" class="d-inline m-0" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data evaluasi ini? Semua jawaban terkait juga akan terhapus.');">
                                            <input type="hidden" name="delete_id" value="<?php echo (int) ($row['id'] ?? 0); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                                <td><?php echo (int) ($row['id'] ?? 0); ?></td>
                                <td>
                                    <div class="fw-semibold"><?php echo e((string) ($row['activity_name'] ?? '-')); ?></div>
                                    <div class="small text-muted"><?php echo e((string) ($row['activity_theme'] ?? '-')); ?></div>
                                </td>
                                <td><?php echo e((string) ($row['activity_date'] ?? '-')); ?></td>
                                <td><?php echo e((string) ($row['activity_organizer'] ?? '-')); ?></td>
                                <td><?php echo e((string) ($row['respondent_category'] ?? '-')); ?></td>
                                <td><?php echo e((string) ($row['participation_mode'] ?? '-')); ?></td>
                                <td><?php echo e((string) ($row['evaluator_name'] ?? '-')); ?></td>
                                <td><?php echo e((string) ($row['recap_overall_score'] ?? '-')); ?></td>
                                <td><?php echo e((string) ($row['recap_result_category'] ?? '-')); ?></td>
                                <td><?php echo (int) ($row['answer_count'] ?? 0); ?></td>
                                <td><?php echo (int) ($row['rtl_count'] ?? 0); ?></td>
                                <td><?php echo e((string) ($row['created_at'] ?? '-')); ?></td>
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
<?php $conn->close(); ?>
