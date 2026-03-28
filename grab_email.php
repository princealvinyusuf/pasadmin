<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

if (!(current_user_can('grab_email_manage') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function db_connect(string $dbName): ?mysqli {
    $conn = @new mysqli('localhost', 'root', '', $dbName);
    if ($conn->connect_error) {
        return null;
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

function table_exists(mysqli $conn, string $table): bool {
    $t = $conn->real_escape_string($table);
    $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$t}' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function column_exists(mysqli $conn, string $table, string $column): bool {
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($column);
    $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$t}' AND COLUMN_NAME = '{$c}' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function ensure_grab_email_table(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS grab_email_contacts (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(191) NOT NULL,
        from_walkin_survey TINYINT(1) NOT NULL DEFAULT 0,
        from_tes_minat_karir TINYINT(1) NOT NULL DEFAULT 0,
        from_career_boost_day TINYINT(1) NOT NULL DEFAULT 0,
        last_regrab_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_email (email),
        KEY idx_flags (from_walkin_survey, from_tes_minat_karir, from_career_boost_day),
        KEY idx_last_regrab (last_regrab_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function sanitize_identifier(string $name): ?string {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        return null;
    }
    return $name;
}

function normalize_email(string $email): ?string {
    $email = strtolower(trim($email));
    if ($email === '') {
        return null;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    return $email;
}

function collect_emails_from_column(mysqli $conn, string $table, string $column): array {
    $safeTable = sanitize_identifier($table);
    $safeColumn = sanitize_identifier($column);
    if ($safeTable === null || $safeColumn === null) {
        return [];
    }

    $rows = [];
    $sql = "SELECT DISTINCT {$safeColumn} AS email_value FROM {$safeTable} WHERE {$safeColumn} IS NOT NULL AND TRIM({$safeColumn}) <> ''";
    $res = $conn->query($sql);
    if (!$res) {
        return [];
    }

    while ($r = $res->fetch_assoc()) {
        $normalized = normalize_email((string)($r['email_value'] ?? ''));
        if ($normalized !== null) {
            $rows[$normalized] = true;
        }
    }

    return array_keys($rows);
}

function discover_tes_minat_karir_sources(mysqli $conn): array {
    $sources = [];
    $seen = [];
    $emailColumns = ['email', 'email_address', 'user_email', 'participant_email'];

    $knownTables = [
        'tes_minat_karir',
        'tes_minat_dan_karir',
        'test_minat_karir',
        'test_minat_dan_karir',
        'minat_karir',
        'minat_dan_karir',
        'tesminatkarir',
    ];

    foreach ($knownTables as $table) {
        if (!table_exists($conn, $table)) {
            continue;
        }
        foreach ($emailColumns as $col) {
            if (column_exists($conn, $table, $col)) {
                $key = $table . ':' . $col;
                if (!isset($seen[$key])) {
                    $sources[] = ['table' => $table, 'column' => $col];
                    $seen[$key] = true;
                }
                break;
            }
        }
    }

    $sql = "SELECT TABLE_NAME, COLUMN_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND COLUMN_NAME IN ('email', 'email_address', 'user_email', 'participant_email')
              AND TABLE_NAME LIKE '%minat%'
              AND TABLE_NAME LIKE '%karir%'";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $table = (string)($row['TABLE_NAME'] ?? '');
            $column = (string)($row['COLUMN_NAME'] ?? '');
            if ($table === '' || $column === '') {
                continue;
            }
            $key = $table . ':' . $column;
            if (isset($seen[$key])) {
                continue;
            }
            $sources[] = ['table' => $table, 'column' => $column];
            $seen[$key] = true;
        }
    }

    return $sources;
}

$targetConn = db_connect('job_admin_prod');
if (!$targetConn) {
    http_response_code(500);
    echo 'Failed to connect to target database: job_admin_prod';
    exit;
}
ensure_grab_email_table($targetConn);

if (isset($_GET['export']) && $_GET['export'] === 'json') {
    $rows = [];
    $res = $targetConn->query("SELECT email, from_walkin_survey, from_tes_minat_karir, from_career_boost_day, last_regrab_at
        FROM grab_email_contacts
        ORDER BY email ASC");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['rows' => $rows], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string)($_POST['action'] ?? '') === 'regrab_all') {
    $emailMap = [];
    $featureCounts = [
        'walkin' => 0,
        'tes' => 0,
        'career' => 0,
    ];
    $tesSourceUsed = [];

    $sourceDbs = ['job_admin_prod', 'paskerid_db_prod'];
    foreach ($sourceDbs as $dbName) {
        $conn = ($dbName === 'job_admin_prod') ? $targetConn : db_connect($dbName);
        if (!$conn) {
            continue;
        }

        if (table_exists($conn, 'walk_in_survey_responses') && column_exists($conn, 'walk_in_survey_responses', 'email')) {
            $emails = collect_emails_from_column($conn, 'walk_in_survey_responses', 'email');
            foreach ($emails as $email) {
                if (!isset($emailMap[$email])) {
                    $emailMap[$email] = ['walkin' => 0, 'tes' => 0, 'career' => 0];
                }
                if ($emailMap[$email]['walkin'] === 0) {
                    $emailMap[$email]['walkin'] = 1;
                    $featureCounts['walkin']++;
                }
            }
        }

        if (table_exists($conn, 'career_boostday_consultations') && column_exists($conn, 'career_boostday_consultations', 'email')) {
            $emails = collect_emails_from_column($conn, 'career_boostday_consultations', 'email');
            foreach ($emails as $email) {
                if (!isset($emailMap[$email])) {
                    $emailMap[$email] = ['walkin' => 0, 'tes' => 0, 'career' => 0];
                }
                if ($emailMap[$email]['career'] === 0) {
                    $emailMap[$email]['career'] = 1;
                    $featureCounts['career']++;
                }
            }
        }

        $tesSources = discover_tes_minat_karir_sources($conn);
        foreach ($tesSources as $src) {
            $table = (string)$src['table'];
            $column = (string)$src['column'];
            $emails = collect_emails_from_column($conn, $table, $column);
            if (!empty($emails)) {
                $tesSourceUsed[] = $dbName . '.' . $table . '(' . $column . ')';
            }
            foreach ($emails as $email) {
                if (!isset($emailMap[$email])) {
                    $emailMap[$email] = ['walkin' => 0, 'tes' => 0, 'career' => 0];
                }
                if ($emailMap[$email]['tes'] === 0) {
                    $emailMap[$email]['tes'] = 1;
                    $featureCounts['tes']++;
                }
            }
        }

        if ($dbName !== 'job_admin_prod') {
            $conn->close();
        }
    }

    $targetConn->begin_transaction();
    try {
        $targetConn->query("DELETE FROM grab_email_contacts");
        $ins = $targetConn->prepare("INSERT INTO grab_email_contacts
            (email, from_walkin_survey, from_tes_minat_karir, from_career_boost_day, last_regrab_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW(), NOW())");
        if (!$ins) {
            throw new RuntimeException('Failed to prepare insert statement.');
        }

        $inserted = 0;
        foreach ($emailMap as $email => $flags) {
            $walkin = (int)$flags['walkin'];
            $tes = (int)$flags['tes'];
            $career = (int)$flags['career'];
            $ins->bind_param('siii', $email, $walkin, $tes, $career);
            $ins->execute();
            $inserted++;
        }
        $ins->close();
        $targetConn->commit();

        $tesSourceText = empty($tesSourceUsed) ? 'Tidak ditemukan table Tes Minat & Karir yang punya kolom email.' : ('Sumber Tes Minat & Karir: ' . implode(', ', array_unique($tesSourceUsed)));
        $_SESSION['grab_email_success'] = "Grab/Regrab selesai. Total email unik: {$inserted}. Walk In Interview Survei Evaluasi: {$featureCounts['walkin']}, Tes Minat & Karir: {$featureCounts['tes']}, Career Boost Day: {$featureCounts['career']}. {$tesSourceText}";
    } catch (Throwable $e) {
        $targetConn->rollback();
        $_SESSION['grab_email_error'] = 'Grab/Regrab gagal: ' . $e->getMessage();
    }

    header('Location: grab_email');
    exit;
}

$stats = [
    'total' => 0,
    'walkin' => 0,
    'tes' => 0,
    'career' => 0,
];

$resStats = $targetConn->query("SELECT
    COUNT(*) AS total_count,
    SUM(CASE WHEN from_walkin_survey = 1 THEN 1 ELSE 0 END) AS walkin_count,
    SUM(CASE WHEN from_tes_minat_karir = 1 THEN 1 ELSE 0 END) AS tes_count,
    SUM(CASE WHEN from_career_boost_day = 1 THEN 1 ELSE 0 END) AS career_count
    FROM grab_email_contacts");
if ($resStats) {
    $r = $resStats->fetch_assoc();
    $stats['total'] = (int)($r['total_count'] ?? 0);
    $stats['walkin'] = (int)($r['walkin_count'] ?? 0);
    $stats['tes'] = (int)($r['tes_count'] ?? 0);
    $stats['career'] = (int)($r['career_count'] ?? 0);
}

$rows = [];
$resRows = $targetConn->query("SELECT email, from_walkin_survey, from_tes_minat_karir, from_career_boost_day, last_regrab_at
    FROM grab_email_contacts
    ORDER BY email ASC
    LIMIT 5000");
if ($resRows) {
    while ($row = $resRows->fetch_assoc()) {
        $rows[] = $row;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Grab Email | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="/xlsx.full.min.js"></script>
    <script src="node_modules/xlsx/dist/xlsx.full.min.js"></script>
</head>
<body class="bg-light">
<?php include __DIR__ . '/navbar.php'; ?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div>
            <h1 class="h4 mb-1"><i class="bi bi-envelope-paper me-2"></i>Grab Email</h1>
            <div class="text-muted small">Menu ini mengambil email untuk kebutuhan digital marketing tanpa mengubah table sumber.</div>
        </div>
        <div class="d-flex gap-2">
            <form method="post">
                <input type="hidden" name="action" value="regrab_all">
                <button type="submit" class="btn btn-primary" onclick="return confirm('Proses ini akan re-import semua email ke table baru grab_email_contacts. Lanjutkan?');">
                    <i class="bi bi-arrow-repeat me-1"></i>Grab/Regrab All Email
                </button>
            </form>
            <button type="button" class="btn btn-success" id="btnExportExcel">
                <i class="bi bi-file-earmark-excel me-1"></i>Export to Excel
            </button>
        </div>
    </div>

    <?php if (!empty($_SESSION['grab_email_success'])): ?>
        <div class="alert alert-success"><?php echo h($_SESSION['grab_email_success']); unset($_SESSION['grab_email_success']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['grab_email_error'])): ?>
        <div class="alert alert-danger"><?php echo h($_SESSION['grab_email_error']); unset($_SESSION['grab_email_error']); ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="text-muted small">Total Email Unik</div><div class="fs-4 fw-bold"><?php echo number_format($stats['total']); ?></div></div></div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="text-muted small">Walk In Interview Survei Evaluasi</div><div class="fs-4 fw-bold"><?php echo number_format($stats['walkin']); ?></div></div></div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="text-muted small">Tes Minat &amp; Karir</div><div class="fs-4 fw-bold"><?php echo number_format($stats['tes']); ?></div></div></div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card shadow-sm border-0"><div class="card-body"><div class="text-muted small">Career Boost Day</div><div class="fs-4 fw-bold"><?php echo number_format($stats['career']); ?></div></div></div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body border-bottom">
            <div class="small text-muted">Menampilkan maksimal 5,000 baris pada layar. Export to Excel akan mengambil seluruh data.</div>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0" id="grabEmailTable">
                <thead class="table-primary">
                    <tr>
                        <th style="width:70px;">No</th>
                        <th>Email</th>
                        <th style="width:130px;">Walk In</th>
                        <th style="width:130px;">Tes Minat</th>
                        <th style="width:130px;">Career Boost</th>
                        <th style="width:190px;">Last Regrab</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada data. Klik "Grab/Regrab All Email" untuk mulai import.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $idx => $r): ?>
                        <tr>
                            <td><?php echo (int)$idx + 1; ?></td>
                            <td><?php echo h($r['email']); ?></td>
                            <td><?php echo ((int)$r['from_walkin_survey'] === 1) ? '<span class="badge text-bg-success">Ya</span>' : '<span class="badge text-bg-secondary">Tidak</span>'; ?></td>
                            <td><?php echo ((int)$r['from_tes_minat_karir'] === 1) ? '<span class="badge text-bg-success">Ya</span>' : '<span class="badge text-bg-secondary">Tidak</span>'; ?></td>
                            <td><?php echo ((int)$r['from_career_boost_day'] === 1) ? '<span class="badge text-bg-success">Ya</span>' : '<span class="badge text-bg-secondary">Tidak</span>'; ?></td>
                            <td><?php echo h($r['last_regrab_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    var btn = document.getElementById('btnExportExcel');
    if (!btn) return;

    btn.addEventListener('click', async function () {
        if (typeof XLSX === 'undefined') {
            alert('Library xlsx.full.min.js tidak ditemukan.');
            return;
        }

        var oldHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Exporting...';
        try {
            var res = await fetch('grab_email?export=json', { credentials: 'same-origin' });
            if (!res.ok) throw new Error('Gagal mengambil data export.');
            var payload = await res.json();
            var rows = Array.isArray(payload.rows) ? payload.rows : [];

            var exportRows = rows.map(function (r, idx) {
                return {
                    'No': idx + 1,
                    'Email': r.email || '',
                    'Walk In Interview Survei Evaluasi': Number(r.from_walkin_survey) === 1 ? 'Ya' : 'Tidak',
                    'Tes Minat & Karir': Number(r.from_tes_minat_karir) === 1 ? 'Ya' : 'Tidak',
                    'Career Boost Day': Number(r.from_career_boost_day) === 1 ? 'Ya' : 'Tidak',
                    'Last Regrab': r.last_regrab_at || ''
                };
            });

            var ws = XLSX.utils.json_to_sheet(exportRows);
            var wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Grab Email');

            var d = new Date();
            var pad = function (n) { return String(n).padStart(2, '0'); };
            var fileName = 'grab_email_' + d.getFullYear() + pad(d.getMonth() + 1) + pad(d.getDate()) + '_' + pad(d.getHours()) + pad(d.getMinutes()) + pad(d.getSeconds()) + '.xlsx';
            XLSX.writeFile(wb, fileName);
        } catch (err) {
            alert((err && err.message) ? err.message : 'Export gagal.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
        }
    });
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
