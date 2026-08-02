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

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function table_exists(mysqli $conn, string $table): bool
{
    $t = $conn->real_escape_string($table);
    $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

$activityTableReady = table_exists($conn, 'program_kemitraan_evaluation_activities');
$evaluationTableReady = table_exists($conn, 'program_kemitraan_evaluations');
$answerTableReady = table_exists($conn, 'program_kemitraan_evaluation_answers');

$rows = [];
if ($activityTableReady && $evaluationTableReady) {
    $query = "
        SELECT
            a.id AS activity_master_id,
            a.activity_name,
            a.activity_theme,
            a.activity_date,
            a.activity_organizer,
            COUNT(DISTINCT e.id) AS total_evaluasi,
            MAX(e.updated_at) AS last_update,
            ROUND(AVG(ans.score), 2) AS avg_score
        FROM program_kemitraan_evaluation_activities a
        LEFT JOIN program_kemitraan_evaluations e ON e.activity_master_id = a.id
        LEFT JOIN program_kemitraan_evaluation_answers ans ON ans.evaluation_id = e.id AND ans.score IS NOT NULL
        WHERE a.is_active = 1
        GROUP BY
            a.id, a.activity_name, a.activity_theme, a.activity_date, a.activity_organizer
        ORDER BY a.activity_date DESC, a.activity_name ASC
    ";

    if (!$answerTableReady) {
        $query = "
            SELECT
                a.id AS activity_master_id,
                a.activity_name,
                a.activity_theme,
                a.activity_date,
                a.activity_organizer,
                COUNT(DISTINCT e.id) AS total_evaluasi,
                MAX(e.updated_at) AS last_update,
                NULL AS avg_score
            FROM program_kemitraan_evaluation_activities a
            LEFT JOIN program_kemitraan_evaluations e ON e.activity_master_id = a.id
            WHERE a.is_active = 1
            GROUP BY
                a.id, a.activity_name, a.activity_theme, a.activity_date, a.activity_organizer
            ORDER BY a.activity_date DESC, a.activity_name ASC
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
    <title>Dashboard Analisis Hasil Kegiatan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background:
                radial-gradient(1200px 520px at -8% -10%, rgba(37, 99, 235, 0.11), transparent 55%),
                radial-gradient(900px 500px at 110% -8%, rgba(16, 185, 129, 0.11), transparent 56%),
                #f4f7fb;
        }
        .shell {
            border: 1px solid rgba(148, 163, 184, 0.25);
            border-radius: 18px;
            box-shadow: 0 22px 45px rgba(15, 23, 42, 0.09);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(6px);
            overflow: hidden;
        }
        .shell-header {
            padding: 1.15rem 1.35rem;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.10), rgba(16, 185, 129, 0.10));
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
        }
        .shell-content {
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
    </style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>

<div class="container mt-4 mb-5">
    <div class="shell">
        <div class="shell-header">
            <h2 class="h5 mb-1 fw-bold">Dashboard Analisis Hasil Kegiatan dan Rencana Tindak Lanjut</h2>
            <p class="text-muted mb-0">Data dikelompokkan berdasarkan <strong>Nama Kegiatan</strong> untuk pengisian Formulir C dan V oleh admin.</p>
        </div>
        <div class="shell-content">
            <?php if (!$activityTableReady || !$evaluationTableReady): ?>
                <div class="alert alert-warning mb-0">
                    Tabel inti evaluasi belum lengkap. Pastikan tabel <code>program_kemitraan_evaluation_activities</code> dan <code>program_kemitraan_evaluations</code> tersedia.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped bg-white mb-0">
                        <thead>
                            <tr>
                                <th>Aksi</th>
                                <th>Nama Kegiatan</th>
                                <th>Tanggal</th>
                                <th>Penyelenggara</th>
                                <th>Total Evaluasi</th>
                                <th>Rata-rata Skor</th>
                                <th>Pembaruan Terakhir</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="7" class="text-center text-muted">Belum ada data kegiatan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <?php $aid = (int) ($row['activity_master_id'] ?? 0); ?>
                                <tr>
                                    <td>
                                        <a href="program_kemitraan_dashboard_analisis_hasil_detail?activity_master_id=<?php echo $aid; ?>" class="btn btn-sm btn-primary">
                                            Kelola Form
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?php echo e((string) ($row['activity_name'] ?? '-')); ?></div>
                                        <div class="small text-muted"><?php echo e((string) ($row['activity_theme'] ?? '-')); ?></div>
                                    </td>
                                    <td><?php echo e((string) ($row['activity_date'] ?? '-')); ?></td>
                                    <td><?php echo e((string) ($row['activity_organizer'] ?? '-')); ?></td>
                                    <td><?php echo (int) ($row['total_evaluasi'] ?? 0); ?></td>
                                    <td><?php echo $row['avg_score'] !== null ? e(number_format((float) $row['avg_score'], 2)) : '-'; ?></td>
                                    <td><?php echo e((string) ($row['last_update'] ?? '-')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
