<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
if (!(current_user_can('use_email_notification') || current_user_can('manage_settings'))) { http_response_code(403); echo 'Forbidden'; exit; }

function parse_manual_recipients(string $text): array {
    $lines = preg_split('/\r\n|\r|\n/', $text);
    $items = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') { continue; }
        $parts = preg_split('/[,\t;]/', $line);
        $email = trim($parts[0] ?? '');
        $name = trim($parts[1] ?? '');
        if ($email === '') { continue; }
        $items[] = ['email' => $email, 'name' => $name];
    }
    return $items;
}

function normalize_recipients(array $rows): array {
    $seen = [];
    $valid = [];
    foreach ($rows as $row) {
        $email = trim((string)($row['email'] ?? ''));
        $name = trim((string)($row['name'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { continue; }
        $key = strtolower($email);
        if (isset($seen[$key])) { continue; }
        $seen[$key] = true;
        $valid[] = ['email' => $email, 'name' => $name];
    }
    return $valid;
}

$smtpUser = '';
$smtpPass = '';
$fromName = 'PaskerID Notification';
$fromEmail = '';
$subject = '';
$messageBody = "Halo {name},\n\nIni adalah email pemberitahuan dari sistem kami.\n\nTerima kasih.";
$manualRecipients = '';
$includeAllUsers = false;
$result = null;
$failedList = [];
$warning = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $smtpUser = trim((string)($_POST['smtp_user'] ?? ''));
    $smtpPass = (string)($_POST['smtp_pass'] ?? '');
    $fromName = trim((string)($_POST['from_name'] ?? 'PaskerID Notification'));
    $fromEmail = trim((string)($_POST['from_email'] ?? ''));
    $subject = trim((string)($_POST['subject'] ?? ''));
    $messageBody = trim((string)($_POST['message_body'] ?? ''));
    $manualRecipients = trim((string)($_POST['manual_recipients'] ?? ''));
    $includeAllUsers = isset($_POST['include_all_users']) && $_POST['include_all_users'] === '1';

    $allRecipients = parse_manual_recipients($manualRecipients);

    if ($includeAllUsers) {
        $conn = new mysqli('localhost', 'root', '', 'job_admin_prod');
        if ($conn->connect_error) {
            $warning = 'Gagal koneksi database users: ' . $conn->connect_error;
        } else {
            $cols = [];
            $resCols = $conn->query('SHOW COLUMNS FROM users');
            if ($resCols) {
                while ($c = $resCols->fetch_assoc()) { $cols[] = $c['Field']; }
            }
            $emailField = in_array('email', $cols, true) ? 'email' : null;
            $nameField = in_array('username', $cols, true) ? 'username' : (in_array('name', $cols, true) ? 'name' : null);
            if ($emailField === null) {
                $warning = 'Table users tidak punya kolom email.';
            } else {
                $sql = "SELECT {$emailField} AS email" . ($nameField ? ", {$nameField} AS name" : ", '' AS name") . " FROM users";
                $resUsers = $conn->query($sql);
                if ($resUsers) {
                    while ($row = $resUsers->fetch_assoc()) {
                        $allRecipients[] = [
                            'email' => (string)($row['email'] ?? ''),
                            'name' => (string)($row['name'] ?? '')
                        ];
                    }
                }
            }
            $conn->close();
        }
    }

    $recipients = normalize_recipients($allRecipients);

    if ($smtpUser === '' || $smtpPass === '' || $subject === '' || $messageBody === '') {
        $warning = 'SMTP username, SMTP password, subject, dan message wajib diisi.';
    } elseif ($fromEmail !== '' && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $warning = 'Format From Email tidak valid.';
    } elseif (count($recipients) === 0) {
        $warning = 'Tidak ada email recipient yang valid.';
    } else {
        $payload = [
            'smtp' => [
                'host' => 'smtp.gmail.com',
                'port' => 465,
                'user' => $smtpUser,
                'pass' => $smtpPass,
                'fromName' => $fromName,
                'fromEmail' => $fromEmail !== '' ? $fromEmail : $smtpUser
            ],
            'subject' => $subject,
            'message' => $messageBody,
            'recipients' => $recipients
        ];

        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        $process = proc_open('node send_email_broadcast.js', $descriptorspec, $pipes, __DIR__);
        if (is_resource($process)) {
            fwrite($pipes[0], json_encode($payload, JSON_UNESCAPED_UNICODE));
            fclose($pipes[0]);
            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            $outputLines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $stdout . "\n" . $stderr)));
            foreach ($outputLines as $line) {
                if (strpos($line, 'FAILED:') === 0) {
                    $parts = explode("\t", $line, 4);
                    $failedList[] = [
                        'email' => $parts[1] ?? '',
                        'name' => $parts[2] ?? '',
                        'error' => $parts[3] ?? 'unknown_error'
                    ];
                }
            }
            $result = [
                'exitCode' => $exitCode,
                'output' => $outputLines,
                'totalRecipients' => count($recipients)
            ];
        } else {
            $warning = 'Gagal menjalankan sender process.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Notification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f4f6fb; }
        textarea { min-height: 120px; }
        .hint { font-size: 0.9rem; color: #6b7280; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container my-4">
    <div class="card shadow-sm">
        <div class="card-body">
            <h4 class="mb-3">Email Notification</h4>
            <p class="text-muted mb-3">Untuk Google SMTP, gunakan akun Gmail + App Password (bukan password login biasa).</p>

            <?php if ($warning !== ''): ?>
                <div class="alert alert-warning"><?php echo htmlspecialchars($warning); ?></div>
            <?php endif; ?>

            <form method="post" id="emailForm">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">SMTP Username (Gmail)</label>
                        <input type="text" class="form-control" name="smtp_user" value="<?php echo htmlspecialchars($smtpUser); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">SMTP Password (App Password)</label>
                        <input type="password" class="form-control" name="smtp_pass" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">From Name</label>
                        <input type="text" class="form-control" name="from_name" value="<?php echo htmlspecialchars($fromName); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">From Email (optional)</label>
                        <input type="email" class="form-control" name="from_email" value="<?php echo htmlspecialchars($fromEmail); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Subject</label>
                        <input type="text" class="form-control" name="subject" value="<?php echo htmlspecialchars($subject); ?>" required>
                        <div class="hint">Variables supported: <code>{name}</code>, <code>{email}</code></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Message Body</label>
                        <textarea class="form-control" name="message_body" rows="8" required><?php echo htmlspecialchars($messageBody); ?></textarea>
                        <div class="hint">Variables supported: <code>{name}</code>, <code>{email}</code></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Manual Recipients</label>
                        <textarea class="form-control" id="manual_recipients" name="manual_recipients" rows="6" placeholder="email@contoh.com,Nama User"><?php echo htmlspecialchars($manualRecipients); ?></textarea>
                        <div class="hint">Format per baris: <code>email</code> atau <code>email,nama</code>. Bisa import CSV/XLSX juga.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Import Recipients (CSV/XLSX)</label>
                        <input type="file" id="importFile" class="form-control" accept=".csv,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv">
                        <div class="hint">Kolom yang didukung: <code>email</code> dan opsional <code>name</code>.</div>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="include_all_users" name="include_all_users" <?php echo $includeAllUsers ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="include_all_users">
                                Include semua email dari table <code>users</code>
                            </label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3">
                    <i class="bi bi-send me-1"></i>Send Email Campaign
                </button>
            </form>
        </div>
    </div>

    <?php if ($result): ?>
        <div class="card shadow-sm mt-3">
            <div class="card-body">
                <h5>Result</h5>
                <p class="mb-2">Recipients processed: <strong><?php echo (int)$result['totalRecipients']; ?></strong></p>
                <pre class="bg-light p-3 rounded border" style="white-space: pre-wrap;"><?php echo htmlspecialchars(implode("\n", $result['output'])); ?></pre>
                <?php if (!empty($failedList)): ?>
                    <div class="alert alert-danger mt-3 mb-0">
                        <strong>Failed Recipients:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($failedList as $f): ?>
                                <li><?php echo htmlspecialchars($f['email']); ?> <?php echo $f['name'] !== '' ? '(' . htmlspecialchars($f['name']) . ')' : ''; ?> - <?php echo htmlspecialchars($f['error']); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
const importInput = document.getElementById('importFile');
const recipientsTextarea = document.getElementById('manual_recipients');

function pushRowsToTextarea(rows) {
    const lines = rows
        .map((r) => `${(r.email || '').trim()}${r.name ? ',' + r.name.trim() : ''}`)
        .filter((line) => line && line !== ',');
    if (!lines.length) return;
    const current = recipientsTextarea.value.trim();
    recipientsTextarea.value = current ? `${current}\n${lines.join('\n')}` : lines.join('\n');
}

function parseCsv(text) {
    const lines = text.split(/\r?\n/).map((l) => l.trim()).filter(Boolean);
    if (!lines.length) return [];
    let start = 0;
    const firstLine = lines[0].toLowerCase();
    const hasHeader = firstLine.includes('email');
    if (hasHeader) start = 1;
    const rows = [];
    for (let i = start; i < lines.length; i++) {
        const cols = lines[i].split(',');
        const email = (cols[0] || '').trim();
        const name = (cols[1] || '').trim();
        rows.push({ email, name });
    }
    return rows;
}

importInput.addEventListener('change', (e) => {
    const file = e.target.files && e.target.files[0];
    if (!file) return;
    const ext = file.name.split('.').pop().toLowerCase();

    if (ext === 'xlsx') {
        const reader = new FileReader();
        reader.onload = (evt) => {
            const data = new Uint8Array(evt.target.result);
            const wb = XLSX.read(data, { type: 'array' });
            const ws = wb.Sheets[wb.SheetNames[0]];
            const table = XLSX.utils.sheet_to_json(ws, { header: 1 });
            if (!table.length) return;
            let emailIdx = 0;
            let nameIdx = 1;
            const header = (table[0] || []).map((h) => String(h).toLowerCase());
            if (header.some((h) => h.includes('email'))) {
                table[0].forEach((h, idx) => {
                    const key = String(h).toLowerCase();
                    if (key.includes('email')) emailIdx = idx;
                    if (key.includes('name')) nameIdx = idx;
                });
                table.shift();
            }
            const rows = table.map((r) => ({
                email: String(r[emailIdx] || '').trim(),
                name: String(r[nameIdx] || '').trim()
            }));
            pushRowsToTextarea(rows);
        };
        reader.readAsArrayBuffer(file);
        return;
    }

    const reader = new FileReader();
    reader.onload = (evt) => {
        const text = String(evt.target.result || '');
        pushRowsToTextarea(parseCsv(text));
    };
    reader.readAsText(file);
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
