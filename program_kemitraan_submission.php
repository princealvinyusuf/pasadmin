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

if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

function table_exists(mysqli $conn, string $table): bool {
    $t = $conn->real_escape_string($table);
    $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function safe_count(mysqli $conn, string $status): int {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM program_kemitraan_submissions WHERE status = ?");
    if (!$stmt) return 0;
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return (int) ($count ?? 0);
}

$tableReady = table_exists($conn, 'program_kemitraan_submissions');

if ($tableReady && isset($_POST['set_status'], $_POST['submission_id'])) {
    $status = trim((string) $_POST['set_status']);
    $submissionId = (int) $_POST['submission_id'];
    $allowedStatus = ['pending', 'approved', 'rejected'];

    if ($submissionId > 0 && in_array($status, $allowedStatus, true)) {
        $stmt = $conn->prepare("UPDATE program_kemitraan_submissions SET status = ?, updated_at = NOW() WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('si', $status, $submissionId);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Status pengajuan berhasil diperbarui.';
        } else {
            $_SESSION['error'] = 'Gagal memperbarui status: ' . $conn->error;
        }
    } else {
        $_SESSION['error'] = 'Parameter status tidak valid.';
    }

    header('Location: program_kemitraan_submission');
    exit();
}

$pendingCount = $tableReady ? safe_count($conn, 'pending') : 0;
$approvedCount = $tableReady ? safe_count($conn, 'approved') : 0;
$rejectedCount = $tableReady ? safe_count($conn, 'rejected') : 0;

$submissions = null;
if ($tableReady) {
    $submissions = $conn->query("SELECT * FROM program_kemitraan_submissions ORDER BY id DESC");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Program Kemitraan Submission</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card-counter .display-6 { font-weight: 700; }
        .table td, .table th { vertical-align: middle; }
        .actions form { display: inline-block; }
    </style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>

<div class="container mt-4 mb-5">
    <h2 class="mb-3">Program Kemitraan Submission</h2>

    <?php if (!$tableReady): ?>
        <div class="alert alert-warning">
            Tabel <code>program_kemitraan_submissions</code> belum tersedia. Jalankan migrasi Laravel terlebih dahulu.
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card card-counter shadow-sm border-0">
                <div class="card-body text-center">
                    <div class="text-warning">Pending</div>
                    <div class="display-6 text-warning"><?php echo $pendingCount; ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card card-counter shadow-sm border-0">
                <div class="card-body text-center">
                    <div class="text-success">Approved</div>
                    <div class="display-6 text-success"><?php echo $approvedCount; ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card card-counter shadow-sm border-0">
                <div class="card-body text-center">
                    <div class="text-danger">Rejected</div>
                    <div class="display-6 text-danger"><?php echo $rejectedCount; ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-striped bg-white">
            <thead>
                <tr>
                    <th>Actions</th>
                    <th>ID</th>
                    <th>PIC</th>
                    <th>Email</th>
                    <th>WhatsApp</th>
                    <th>Kategori Instansi</th>
                    <th>Nama Instansi/Lembaga</th>
                    <th>Nama Instansi</th>
                    <th>Sektor Usaha</th>
                    <th>Jenis Kegiatan</th>
                    <th>Alamat</th>
                    <th>Surat</th>
                    <th>Status</th>
                    <th>Dibuat</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($tableReady && $submissions && $submissions->num_rows > 0): ?>
                    <?php while ($row = $submissions->fetch_assoc()): ?>
                        <?php
                            $requestLetter = trim((string) ($row['request_letter'] ?? ''));
                            $cleanPath = ltrim(str_replace('\\', '/', $requestLetter), '/');
                            $cleanPath = preg_replace('#^storage/#', '', $cleanPath ?? '') ?? '';
                            $downloadUrl = $cleanPath !== '' ? '/storage/' . $cleanPath : '';
                            $status = trim((string) ($row['status'] ?? 'pending'));
                            $rowJson = htmlspecialchars(json_encode($row, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
                        ?>
                        <tr data-row="<?php echo $rowJson; ?>" data-download-url="<?php echo htmlspecialchars($downloadUrl, ENT_QUOTES); ?>">
                            <td class="actions" style="min-width: 220px;">
                                <button type="button" class="btn btn-sm btn-outline-primary detail-btn mb-1">Detail</button>

                                <?php if ($status !== 'approved'): ?>
                                    <form method="post" class="mb-1">
                                        <input type="hidden" name="submission_id" value="<?php echo (int) $row['id']; ?>">
                                        <input type="hidden" name="set_status" value="approved">
                                        <button type="submit" class="btn btn-sm btn-success">Approve</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($status !== 'rejected'): ?>
                                    <form method="post" class="mb-1">
                                        <input type="hidden" name="submission_id" value="<?php echo (int) $row['id']; ?>">
                                        <input type="hidden" name="set_status" value="rejected">
                                        <button type="submit" class="btn btn-sm btn-warning">Reject</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($status !== 'pending'): ?>
                                    <form method="post" class="mb-1">
                                        <input type="hidden" name="submission_id" value="<?php echo (int) $row['id']; ?>">
                                        <input type="hidden" name="set_status" value="pending">
                                        <button type="submit" class="btn btn-sm btn-secondary">Set Pending</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td><?php echo (int) $row['id']; ?></td>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($row['pic_name'] ?? '')); ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars((string) ($row['pic_position'] ?? '')); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars((string) ($row['pic_email'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['pic_whatsapp'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['institution_category'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['instansi_lembaga_name'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['institution_name'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['business_sector'] ?? '-')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['proposed_activity_type'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['institution_address'] ?? '')); ?></td>
                            <td>
                                <?php if ($downloadUrl !== ''): ?>
                                    <a href="<?php echo htmlspecialchars($downloadUrl, ENT_QUOTES); ?>" target="_blank" class="btn btn-sm btn-outline-success">Download</a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge text-bg-<?php echo $status === 'approved' ? 'success' : ($status === 'rejected' ? 'danger' : 'warning'); ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></span></td>
                            <td><?php echo htmlspecialchars((string) ($row['created_at'] ?? '')); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="14" class="text-center text-muted">Belum ada data Program Kemitraan.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Program Kemitraan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm table-bordered mb-0">
                    <tbody id="detailModalBody"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        var modalElement = document.getElementById('detailModal');
        if (!modalElement) return;
        var modal = new bootstrap.Modal(modalElement);
        var detailBody = document.getElementById('detailModalBody');

        document.querySelectorAll('.detail-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var row = btn.closest('tr');
                if (!row || !detailBody) return;

                var rowDataRaw = row.getAttribute('data-row') || '{}';
                var downloadUrl = row.getAttribute('data-download-url') || '';
                var data = {};
                try { data = JSON.parse(rowDataRaw); } catch (e) { data = {}; }

                var pairs = [
                    ['ID', data.id || '-'],
                    ['Nama PIC', data.pic_name || '-'],
                    ['Jabatan PIC', data.pic_position || '-'],
                    ['Email PIC', data.pic_email || '-'],
                    ['WhatsApp PIC', data.pic_whatsapp || '-'],
                    ['Kategori/Sektor Instansi', data.institution_category || '-'],
                    ['Nama Instansi/Lembaga', data.instansi_lembaga_name || '-'],
                    ['Nama Instansi', data.institution_name || '-'],
                    ['Sektor Lapangan Usaha', data.business_sector || '-'],
                    ['Jenis Kegiatan Diajukan', data.proposed_activity_type || '-'],
                    ['Alamat Instansi/Lembaga/Perusahaan', data.institution_address || '-'],
                    ['Status', data.status || '-'],
                    ['Created At', data.created_at || '-'],
                    ['Updated At', data.updated_at || '-']
                ];

                var html = '';
                pairs.forEach(function (pair) {
                    html += '<tr><th style="width: 280px;">' + escapeHtml(pair[0]) + '</th><td>' + escapeHtml(pair[1]) + '</td></tr>';
                });

                if (downloadUrl) {
                    html += '<tr><th>Surat Permohonan</th><td><a class="btn btn-sm btn-outline-success" href="' + escapeHtml(downloadUrl) + '" target="_blank">Download Surat</a></td></tr>';
                } else {
                    html += '<tr><th>Surat Permohonan</th><td>-</td></tr>';
                }

                detailBody.innerHTML = html;
                modal.show();
            });
        });
    })();
</script>

<?php if (isset($_SESSION['error'])): ?>
<script>alert("<?= addslashes($_SESSION['error']) ?>");</script>
<?php unset($_SESSION['error']); endif; ?>
<?php if (isset($_SESSION['success'])): ?>
<script>alert("<?= addslashes($_SESSION['success']) ?>");</script>
<?php unset($_SESSION['success']); endif; ?>

</body>
</html>
<?php $conn->close(); ?>
