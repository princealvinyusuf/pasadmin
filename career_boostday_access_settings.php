<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

if (!(current_user_can('career_boost_day_manage') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

function try_connect_db(string $dbName): ?mysqli
{
    $conn = @new mysqli('localhost', 'root', '', $dbName);
    if ($conn->connect_error) {
        return null;
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

function table_exists(mysqli $conn, string $table): bool
{
    $t = $conn->real_escape_string($table);
    $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function app_base_url(): string
{
    $default = '/pasadmin/';
    $candidates = [
        $_SERVER['REQUEST_URI'] ?? '',
        $_SERVER['PHP_SELF'] ?? '',
        $_SERVER['SCRIPT_NAME'] ?? '',
    ];
    foreach ($candidates as $candidate) {
        $path = parse_url((string) $candidate, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            continue;
        }
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        foreach ($segments as $segment) {
            if (strcasecmp($segment, 'pasadmin') === 0) {
                return '/' . $segment . '/';
            }
        }
    }
    return $default;
}

function ensure_settings_schema(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS career_boostday_settings (
        id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
        is_registration_open TINYINT(1) NOT NULL DEFAULT 1,
        closed_message TEXT NULL,
        created_at DATETIME NULL,
        updated_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $res = $conn->query("SELECT id FROM career_boostday_settings WHERE id = 1 LIMIT 1");
    if (!$res || $res->num_rows === 0) {
        $defaultMessage = 'Mohon maaf, pendaftaran Career Boost Day sedang ditutup sementara karena kuota telah terpenuhi.';
        $stmt = $conn->prepare("INSERT INTO career_boostday_settings (id, is_registration_open, closed_message, created_at, updated_at)
            VALUES (1, 1, ?, NOW(), NOW())");
        if ($stmt) {
            $stmt->bind_param('s', $defaultMessage);
            $stmt->execute();
            $stmt->close();
        }
    }
}

$candidates = ['job_admin_prod', 'paskerid_db_prod'];
$conn = null;
$activeDb = null;
foreach ($candidates as $dbName) {
    $tmp = try_connect_db($dbName);
    if (!$tmp) {
        continue;
    }

    if (table_exists($tmp, 'career_boostday_consultations') || table_exists($tmp, 'career_boostday_settings')) {
        $conn = $tmp;
        $activeDb = $dbName;
        break;
    }

    $tmp->close();
}

if (!$conn) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Cannot determine DB for Career Boost Day settings.\n";
    exit;
}

ensure_settings_schema($conn);
$appBaseUrl = app_base_url();
$selfPath = $appBaseUrl . 'career_boostday_access_settings';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $isRegistrationOpen = isset($_POST['is_registration_open']) ? 1 : 0;
    $closedMessage = trim((string) ($_POST['closed_message'] ?? ''));
    if ($closedMessage === '') {
        $closedMessage = 'Mohon maaf, pendaftaran Career Boost Day sedang ditutup sementara karena kuota telah terpenuhi.';
    }

    $stmt = $conn->prepare("UPDATE career_boostday_settings
        SET is_registration_open = ?, closed_message = ?, updated_at = NOW()
        WHERE id = 1");
    if ($stmt) {
        $stmt->bind_param('is', $isRegistrationOpen, $closedMessage);
        $ok = $stmt->execute();
        $stmt->close();
        $_SESSION[$ok ? 'success' : 'error'] = $ok
            ? 'Pengaturan akses Career Boost Day berhasil disimpan.'
            : 'Gagal menyimpan pengaturan akses.';
    } else {
        $_SESSION['error'] = 'Gagal menyimpan pengaturan akses.';
    }

    header('Location: ' . $selfPath);
    exit;
}

$settings = [
    'is_registration_open' => 1,
    'closed_message' => 'Mohon maaf, pendaftaran Career Boost Day sedang ditutup sementara karena kuota telah terpenuhi.',
    'updated_at' => null,
];
$res = $conn->query("SELECT is_registration_open, closed_message, updated_at FROM career_boostday_settings WHERE id = 1 LIMIT 1");
if ($res && ($row = $res->fetch_assoc())) {
    $settings = [
        'is_registration_open' => (int) ($row['is_registration_open'] ?? 1),
        'closed_message' => (string) ($row['closed_message'] ?? ''),
        'updated_at' => $row['updated_at'] ?? null,
    ];
    if (trim($settings['closed_message']) === '') {
        $settings['closed_message'] = 'Mohon maaf, pendaftaran Career Boost Day sedang ditutup sementara karena kuota telah terpenuhi.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Boost Day Access Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include __DIR__ . '/navbar.php'; ?>

<div class="container mt-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h3 class="mb-0">Career Boost Day Access Settings</h3>
            <div class="text-muted small">Database: <code><?php echo h($activeDb); ?></code></div>
        </div>
        <a href="<?php echo h($appBaseUrl . 'career_boostday'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Career Boost Day
        </a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo h($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo h($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-12">
                    <div class="form-check">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="is_registration_open"
                            name="is_registration_open"
                            <?php echo ((int) $settings['is_registration_open'] === 1) ? 'checked' : ''; ?>
                        >
                        <label class="form-check-label" for="is_registration_open">
                            Buka pendaftaran <strong>Form Curhat Peluang Kerja</strong> untuk user publik
                        </label>
                    </div>
                    <div class="form-text">
                        Jika dinonaktifkan, user tidak bisa akses tab form dan tidak bisa submit pendaftaran baru.
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label" for="closed_message">Pesan saat ditutup</label>
                    <textarea
                        class="form-control"
                        id="closed_message"
                        name="closed_message"
                        rows="3"
                        placeholder="Pesan ini ditampilkan saat akses form ditutup."
                    ><?php echo h($settings['closed_message']); ?></textarea>
                    <div class="form-text">Pesan tampil sebagai notifikasi di halaman Career Boost Day publik.</div>
                </div>

                <div class="col-12">
                    <div class="small text-muted">Last updated: <?php echo h((string) ($settings['updated_at'] ?? '-')); ?></div>
                </div>

                <div class="col-12">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
