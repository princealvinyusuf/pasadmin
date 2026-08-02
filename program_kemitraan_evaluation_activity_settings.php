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

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$conn = new mysqli('localhost', 'root', '', 'paskerid_db_prod');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

function table_exists(mysqli $conn, string $table): bool
{
    $t = $conn->real_escape_string($table);
    $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$tableReady = table_exists($conn, 'program_kemitraan_evaluation_activities');
if (!$tableReady) {
    $_SESSION['error'] = 'Table program_kemitraan_evaluation_activities belum ada. Jalankan migration Laravel terlebih dahulu.';
}

if ($tableReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $activityName = trim((string) ($_POST['activity_name'] ?? ''));
    $activityTheme = trim((string) ($_POST['activity_theme'] ?? ''));
    $activityDate = trim((string) ($_POST['activity_date'] ?? ''));
    $activityStartTime = trim((string) ($_POST['activity_start_time'] ?? ''));
    $activityEndTime = trim((string) ($_POST['activity_end_time'] ?? ''));
    $activityTimezone = trim((string) ($_POST['activity_timezone'] ?? 'WIB'));
    $activityLocation = trim((string) ($_POST['activity_location'] ?? ''));
    $activityOrganizer = trim((string) ($_POST['activity_organizer'] ?? ''));
    $participantsInvited = ($_POST['participants_invited'] ?? '') !== '' ? max(0, (int) $_POST['participants_invited']) : null;
    $participantsAttended = ($_POST['participants_attended'] ?? '') !== '' ? max(0, (int) $_POST['participants_attended']) : null;
    $respondentCount = ($_POST['respondent_count'] ?? '') !== '' ? max(0, (int) $_POST['respondent_count']) : null;
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if (
        $activityName === '' || $activityTheme === '' || $activityDate === '' ||
        $activityStartTime === '' || $activityEndTime === '' || $activityLocation === '' || $activityOrganizer === ''
    ) {
        $_SESSION['error'] = 'Semua field wajib (kecuali jumlah) harus diisi.';
        header('Location: program_kemitraan_evaluation_activity_settings');
        exit();
    }

    if (!in_array($activityTimezone, ['WIB', 'WITA', 'WIT'], true)) {
        $_SESSION['error'] = 'Zona waktu tidak valid.';
        header('Location: program_kemitraan_evaluation_activity_settings');
        exit();
    }

    if ($id > 0) {
        $stmt = $conn->prepare("
            UPDATE program_kemitraan_evaluation_activities
            SET activity_name = ?, activity_theme = ?, activity_date = ?, activity_start_time = ?, activity_end_time = ?,
                activity_timezone = ?, activity_location = ?, activity_organizer = ?, participants_invited = ?,
                participants_attended = ?, respondent_count = ?, is_active = ?, updated_at = NOW()
            WHERE id = ?
        ");
        if ($stmt) {
            $stmt->bind_param(
                'ssssssssiiiii',
                $activityName,
                $activityTheme,
                $activityDate,
                $activityStartTime,
                $activityEndTime,
                $activityTimezone,
                $activityLocation,
                $activityOrganizer,
                $participantsInvited,
                $participantsAttended,
                $respondentCount,
                $isActive,
                $id
            );
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Data kegiatan evaluasi berhasil diperbarui.';
            } else {
                $_SESSION['error'] = 'Gagal update data: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = 'Gagal menyiapkan query update: ' . $conn->error;
        }
    } else {
        $stmt = $conn->prepare("
            INSERT INTO program_kemitraan_evaluation_activities
            (activity_name, activity_theme, activity_date, activity_start_time, activity_end_time, activity_timezone, activity_location, activity_organizer, participants_invited, participants_attended, respondent_count, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        if ($stmt) {
            $stmt->bind_param(
                'ssssssssiiii',
                $activityName,
                $activityTheme,
                $activityDate,
                $activityStartTime,
                $activityEndTime,
                $activityTimezone,
                $activityLocation,
                $activityOrganizer,
                $participantsInvited,
                $participantsAttended,
                $respondentCount,
                $isActive
            );
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Data kegiatan evaluasi berhasil ditambahkan.';
            } else {
                $_SESSION['error'] = 'Gagal simpan data: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = 'Gagal menyiapkan query insert: ' . $conn->error;
        }
    }

    header('Location: program_kemitraan_evaluation_activity_settings');
    exit();
}

if ($tableReady && isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM program_kemitraan_evaluation_activities WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Data kegiatan evaluasi berhasil dihapus.';
        } else {
            $_SESSION['error'] = 'Gagal hapus data: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = 'Gagal menyiapkan query hapus: ' . $conn->error;
    }
    header('Location: program_kemitraan_evaluation_activity_settings');
    exit();
}

$rows = [];
if ($tableReady) {
    $res = $conn->query("
        SELECT a.*, COALESCE(usage_data.total_usage, 0) AS total_usage
        FROM program_kemitraan_evaluation_activities a
        LEFT JOIN (
            SELECT activity_master_id, COUNT(*) AS total_usage
            FROM program_kemitraan_evaluations
            GROUP BY activity_master_id
        ) usage_data ON usage_data.activity_master_id = a.id
        ORDER BY a.activity_date DESC, a.activity_name ASC
    ");
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
    <title>Program Kemitraan Evaluasi Kegiatan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container mt-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h3 class="mb-0">Program Kemitraan - Master Nama Kegiatan Evaluasi</h3>
        <div class="text-muted small">Data di sini digunakan sebagai dropdown `Nama Kegiatan` pada Form Evaluasi.</div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo esc((string) $_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo esc((string) $_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <form method="post" class="row g-3">
                <input type="hidden" name="id" id="form_id" value="">
                <div class="col-md-6">
                    <label class="form-label">Nama Kegiatan</label>
                    <input type="text" class="form-control" name="activity_name" id="form_activity_name" required maxlength="255">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tema/Topik</label>
                    <input type="text" class="form-control" name="activity_theme" id="form_activity_theme" required maxlength="255">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Hari/Tanggal</label>
                    <input type="date" class="form-control" name="activity_date" id="form_activity_date" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Waktu Mulai</label>
                    <input type="time" class="form-control" name="activity_start_time" id="form_activity_start_time" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Waktu Selesai</label>
                    <input type="time" class="form-control" name="activity_end_time" id="form_activity_end_time" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Zona Waktu</label>
                    <select class="form-select" name="activity_timezone" id="form_activity_timezone" required>
                        <option value="WIB">WIB</option>
                        <option value="WITA">WITA</option>
                        <option value="WIT">WIT</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tempat/Media</label>
                    <input type="text" class="form-control" name="activity_location" id="form_activity_location" required maxlength="255">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Penyelenggara</label>
                    <input type="text" class="form-control" name="activity_organizer" id="form_activity_organizer" required maxlength="255">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Jumlah Undangan</label>
                    <input type="number" min="0" class="form-control" name="participants_invited" id="form_participants_invited">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Jumlah Hadir</label>
                    <input type="number" min="0" class="form-control" name="participants_attended" id="form_participants_attended">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Jumlah Responden</label>
                    <input type="number" min="0" class="form-control" name="respondent_count" id="form_respondent_count">
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="form_is_active" checked>
                        <label class="form-check-label" for="form_is_active">Aktif (muncul di dropdown user)</label>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary me-2">Simpan</button>
                    <button type="button" class="btn btn-secondary" onclick="resetForm()">Clear</button>
                </div>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead>
                <tr>
                    <th style="width: 70px;">ID</th>
                    <th>Nama Kegiatan</th>
                    <th>Tema/Topik</th>
                    <th style="width: 140px;">Tanggal</th>
                    <th style="width: 170px;">Waktu</th>
                    <th>Tempat/Media</th>
                    <th>Penyelenggara</th>
                    <th style="width: 140px;">Undangan/Hadir/Responden</th>
                    <th style="width: 90px;">Status</th>
                    <th style="width: 90px;">Dipakai</th>
                    <th style="width: 130px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="11" class="text-center text-muted">Belum ada data kegiatan evaluasi.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo (int) $r['id']; ?></td>
                        <td><?php echo esc((string) ($r['activity_name'] ?? '')); ?></td>
                        <td><?php echo esc((string) ($r['activity_theme'] ?? '')); ?></td>
                        <td><?php echo esc((string) ($r['activity_date'] ?? '')); ?></td>
                        <td><?php echo esc((string) ($r['activity_start_time'] ?? '')); ?> - <?php echo esc((string) ($r['activity_end_time'] ?? '')); ?> <?php echo esc((string) ($r['activity_timezone'] ?? '')); ?></td>
                        <td><?php echo esc((string) ($r['activity_location'] ?? '')); ?></td>
                        <td><?php echo esc((string) ($r['activity_organizer'] ?? '')); ?></td>
                        <td><?php echo esc((string) ($r['participants_invited'] ?? '-')); ?>/<?php echo esc((string) ($r['participants_attended'] ?? '-')); ?>/<?php echo esc((string) ($r['respondent_count'] ?? '-')); ?></td>
                        <td>
                            <?php if ((int) ($r['is_active'] ?? 0) === 1): ?>
                                <span class="badge text-bg-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo (int) ($r['total_usage'] ?? 0); ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick='editRow(<?php echo json_encode($r); ?>)'>Edit</button>
                            <a class="btn btn-sm btn-outline-danger" href="?delete=<?php echo (int) $r['id']; ?>" onclick="return confirm('Hapus data ini?');">Hapus</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function editRow(row) {
    document.getElementById('form_id').value = row.id || '';
    document.getElementById('form_activity_name').value = row.activity_name || '';
    document.getElementById('form_activity_theme').value = row.activity_theme || '';
    document.getElementById('form_activity_date').value = row.activity_date || '';
    document.getElementById('form_activity_start_time').value = row.activity_start_time || '';
    document.getElementById('form_activity_end_time').value = row.activity_end_time || '';
    document.getElementById('form_activity_timezone').value = row.activity_timezone || 'WIB';
    document.getElementById('form_activity_location').value = row.activity_location || '';
    document.getElementById('form_activity_organizer').value = row.activity_organizer || '';
    document.getElementById('form_participants_invited').value = row.participants_invited || '';
    document.getElementById('form_participants_attended').value = row.participants_attended || '';
    document.getElementById('form_respondent_count').value = row.respondent_count || '';
    document.getElementById('form_is_active').checked = Number(row.is_active) === 1;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetForm() {
    document.getElementById('form_id').value = '';
    document.getElementById('form_activity_name').value = '';
    document.getElementById('form_activity_theme').value = '';
    document.getElementById('form_activity_date').value = '';
    document.getElementById('form_activity_start_time').value = '';
    document.getElementById('form_activity_end_time').value = '';
    document.getElementById('form_activity_timezone').value = 'WIB';
    document.getElementById('form_activity_location').value = '';
    document.getElementById('form_activity_organizer').value = '';
    document.getElementById('form_participants_invited').value = '';
    document.getElementById('form_participants_attended').value = '';
    document.getElementById('form_respondent_count').value = '';
    document.getElementById('form_is_active').checked = true;
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
