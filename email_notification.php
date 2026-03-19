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
        $email = trim((string)($parts[0] ?? ''));
        $name = trim((string)($parts[1] ?? ''));
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

function decode_b64_post_field(string $key, string $fallback = ''): string {
    $raw = isset($_POST[$key]) ? (string)$_POST[$key] : '';
    if ($raw === '') { return $fallback; }
    $decoded = base64_decode($raw, true);
    if ($decoded === false) { return $fallback; }
    return $decoded;
}

function ensure_email_log_tables(mysqli $conn): void {
    $conn->query("CREATE TABLE IF NOT EXISTS email_campaign_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        created_by_user_id INT NULL,
        created_by_username VARCHAR(191) NULL,
        smtp_user VARCHAR(191) NOT NULL,
        from_name VARCHAR(191) NOT NULL,
        from_email VARCHAR(191) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message_text MEDIUMTEXT NULL,
        message_html MEDIUMTEXT NULL,
        include_all_users TINYINT(1) NOT NULL DEFAULT 0,
        batch_size INT NOT NULL DEFAULT 20,
        delay_ms INT NOT NULL DEFAULT 1500,
        batch_delay_ms INT NOT NULL DEFAULT 5000,
        total_recipients INT NOT NULL DEFAULT 0,
        sent_count INT NOT NULL DEFAULT 0,
        failed_count INT NOT NULL DEFAULT 0,
        status VARCHAR(50) NOT NULL DEFAULT 'processing',
        raw_output MEDIUMTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        finished_at DATETIME NULL,
        INDEX idx_created_at (created_at),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS email_campaign_log_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campaign_id INT NOT NULL,
        recipient_email VARCHAR(191) NOT NULL,
        recipient_name VARCHAR(191) NULL,
        send_status VARCHAR(20) NOT NULL,
        error_message TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_campaign (campaign_id),
        INDEX idx_status (send_status),
        CONSTRAINT fk_email_campaign_items_campaign
            FOREIGN KEY (campaign_id) REFERENCES email_campaign_logs(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS email_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        template_name VARCHAR(191) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message_text MEDIUMTEXT NULL,
        message_html MEDIUMTEXT NULL,
        created_by_user_id INT NULL,
        created_by_username VARCHAR(191) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_template_name (template_name),
        INDEX idx_updated_at (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$smtpUser = '';
$fromName = 'PaskerID Notification';
$fromEmail = '';
$subject = '';
$messageBodyText = "Halo {name},\n\nIni adalah email pemberitahuan dari sistem kami.\n\nTerima kasih.";
$messageBodyHtml = "<div style=\"font-family:Arial,sans-serif;line-height:1.6;\">\n  <p>Halo <strong>{name}</strong>,</p>\n  <p>Ini adalah email pemberitahuan dari sistem kami.</p>\n  <p>Terima kasih.</p>\n</div>";
$manualRecipients = '';
$includeAllUsers = false;
$batchSize = 20;
$delayMs = 1500;
$batchDelayMs = 5000;
$result = null;
$failedList = [];
$warning = '';
$sentList = [];
$notice = '';
$noticeType = 'success';
$customTemplates = [];

$logConn = new mysqli('localhost', 'root', '', 'job_admin_prod');
if ($logConn->connect_error) {
    $warning = 'Gagal koneksi DB log email: ' . $logConn->connect_error;
} else {
    ensure_email_log_tables($logConn);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = trim((string)($_POST['form_action'] ?? 'send_campaign'));

    // Keep editor fields sticky for all actions.
    $subject = trim((string)($_POST['subject'] ?? $subject));
    $messageBodyText = trim((string)($_POST['message_body_text'] ?? $messageBodyText));
    $messageBodyHtml = trim((string)($_POST['message_body_html'] ?? $messageBodyHtml));

    if (($formAction === 'save_template' || $formAction === 'update_template' || $formAction === 'delete_template') && (!$logConn || $logConn->connect_error)) {
        $warning = 'DB template tidak tersedia.';
    } elseif ($formAction === 'save_template' || $formAction === 'update_template') {
        $subject = decode_b64_post_field('subject_b64', $subject);
        $messageBodyText = decode_b64_post_field('message_body_text_b64', $messageBodyText);
        $messageBodyHtml = decode_b64_post_field('message_body_html_b64', $messageBodyHtml);
        $templateName = trim((string)($_POST['template_name'] ?? ''));
        $templateId = (int)($_POST['template_id'] ?? 0);
        if ($templateName === '') {
            $warning = 'Nama template wajib diisi.';
        } elseif ($subject === '' || ($messageBodyText === '' && $messageBodyHtml === '')) {
            $warning = 'Subject dan minimal salah satu body (text/html) wajib diisi untuk simpan template.';
        } else {
            $createdByUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $createdByUsername = isset($_SESSION['username']) ? (string)$_SESSION['username'] : null;
            if ($formAction === 'update_template' && $templateId > 0) {
                $updTpl = $logConn->prepare('UPDATE email_templates SET template_name=?, subject=?, message_text=?, message_html=?, created_by_user_id=?, created_by_username=? WHERE id=?');
                if ($updTpl) {
                    $updTpl->bind_param('ssssisi', $templateName, $subject, $messageBodyText, $messageBodyHtml, $createdByUserId, $createdByUsername, $templateId);
                    $updTpl->execute();
                    $updTpl->close();
                    $notice = 'Template berhasil diupdate.';
                }
            } else {
                $insTpl = $logConn->prepare('INSERT INTO email_templates (template_name, subject, message_text, message_html, created_by_user_id, created_by_username) VALUES (?, ?, ?, ?, ?, ?)');
                if ($insTpl) {
                    $insTpl->bind_param('ssssis', $templateName, $subject, $messageBodyText, $messageBodyHtml, $createdByUserId, $createdByUsername);
                    $insTpl->execute();
                    $insTpl->close();
                    $notice = 'Template berhasil disimpan.';
                }
            }
        }
    } elseif ($formAction === 'delete_template') {
        $templateId = (int)($_POST['template_id'] ?? 0);
        if ($templateId <= 0) {
            $warning = 'Template yang dipilih tidak valid.';
        } else {
            $delTpl = $logConn->prepare('DELETE FROM email_templates WHERE id=?');
            if ($delTpl) {
                $delTpl->bind_param('i', $templateId);
                $delTpl->execute();
                $delTpl->close();
                $notice = 'Template berhasil dihapus.';
            }
        }
    } else {
        $smtpUser = trim((string)($_POST['smtp_user'] ?? ''));
        $smtpPassRaw = (string)($_POST['smtp_pass'] ?? '');
        // Gmail app password is 16 chars and often copied with spaces/NBSP.
        $smtpPass = preg_replace('/\s+/u', '', $smtpPassRaw);
        $fromName = trim((string)($_POST['from_name'] ?? 'PaskerID Notification'));
        $fromEmail = trim((string)($_POST['from_email'] ?? ''));
        $manualRecipients = trim((string)($_POST['manual_recipients'] ?? ''));
        $includeAllUsers = isset($_POST['include_all_users']) && $_POST['include_all_users'] === '1';
        $batchSize = max(1, min(200, (int)($_POST['batch_size'] ?? 20)));
        $delayMs = max(0, min(60000, (int)($_POST['delay_ms'] ?? 1500)));
        $batchDelayMs = max(0, min(120000, (int)($_POST['batch_delay_ms'] ?? 5000)));

        $allRecipients = parse_manual_recipients($manualRecipients);

        if ($includeAllUsers && $logConn && !$logConn->connect_error) {
            $cols = [];
            $resCols = $logConn->query('SHOW COLUMNS FROM users');
            if ($resCols) {
                while ($c = $resCols->fetch_assoc()) { $cols[] = $c['Field']; }
            }
            $emailField = in_array('email', $cols, true) ? 'email' : null;
            $nameField = in_array('username', $cols, true) ? 'username' : (in_array('name', $cols, true) ? 'name' : null);
            if ($emailField === null) {
                $warning = 'Table users tidak punya kolom email.';
            } else {
                $sql = "SELECT {$emailField} AS email" . ($nameField ? ", {$nameField} AS name" : ", '' AS name") . " FROM users";
                $resUsers = $logConn->query($sql);
                if ($resUsers) {
                    while ($row = $resUsers->fetch_assoc()) {
                        $allRecipients[] = [
                            'email' => (string)($row['email'] ?? ''),
                            'name' => (string)($row['name'] ?? '')
                        ];
                    }
                }
            }
        }

        $recipients = normalize_recipients($allRecipients);

        if ($smtpUser === '' || $smtpPass === '' || $subject === '') {
            $warning = 'SMTP username, SMTP password, dan subject wajib diisi.';
        } elseif ($messageBodyText === '' && $messageBodyHtml === '') {
            $warning = 'Isi minimal salah satu body template: text atau HTML.';
        } elseif ($fromEmail !== '' && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $warning = 'Format From Email tidak valid.';
        } elseif (count($recipients) === 0) {
            $warning = 'Tidak ada email recipient yang valid.';
        } elseif (!$logConn || $logConn->connect_error) {
            $warning = 'DB log email tidak tersedia.';
        } else {
            $campaignId = null;
            $insertCampaign = $logConn->prepare('INSERT INTO email_campaign_logs (created_by_user_id, created_by_username, smtp_user, from_name, from_email, subject, message_text, message_html, include_all_users, batch_size, delay_ms, batch_delay_ms, total_recipients, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            if ($insertCampaign) {
                $createdByUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
                $createdByUsername = isset($_SESSION['username']) ? (string)$_SESSION['username'] : null;
                $smtpUserForLog = $smtpUser;
                $fromEmailForLog = $fromEmail !== '' ? $fromEmail : $smtpUser;
                $totalRecipients = count($recipients);
                $campaignStatus = 'processing';
                $insertCampaign->bind_param(
                    'isssssssiiiiis',
                    $createdByUserId,
                    $createdByUsername,
                    $smtpUserForLog,
                    $fromName,
                    $fromEmailForLog,
                    $subject,
                    $messageBodyText,
                    $messageBodyHtml,
                    $includeAllUsers,
                    $batchSize,
                    $delayMs,
                    $batchDelayMs,
                    $totalRecipients,
                    $campaignStatus
                );
                $insertCampaign->execute();
                $campaignId = (int)$insertCampaign->insert_id;
                $insertCampaign->close();
            }

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
                'textTemplate' => $messageBodyText,
                'htmlTemplate' => $messageBodyHtml,
                'batchSize' => $batchSize,
                'delayMs' => $delayMs,
                'batchDelayMs' => $batchDelayMs,
                'recipients' => $recipients
            ];

            if (!function_exists('proc_open')) {
                $warning = 'Server tidak mengizinkan proc_open(). Aktifkan proc_open atau gunakan worker/background service.';
            } else {
                $descriptorspec = [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w']
                ];
                $nodeCommand = 'env -u LD_LIBRARY_PATH node send_email_broadcast.js';
                $process = proc_open($nodeCommand, $descriptorspec, $pipes, __DIR__);
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
                        if (strpos($line, 'SENT:') === 0) {
                            $parts = explode("\t", $line, 3);
                            $sentList[] = [
                                'email' => $parts[1] ?? '',
                                'name' => $parts[2] ?? ''
                            ];
                        } elseif (strpos($line, 'FAILED:') === 0) {
                            $parts = explode("\t", $line, 4);
                            $failedList[] = [
                                'email' => $parts[1] ?? '',
                                'name' => $parts[2] ?? '',
                                'error' => $parts[3] ?? 'unknown_error'
                            ];
                        }
                    }

                    if ($campaignId) {
                        $insItem = $logConn->prepare('INSERT INTO email_campaign_log_items (campaign_id, recipient_email, recipient_name, send_status, error_message) VALUES (?, ?, ?, ?, ?)');
                        if ($insItem) {
                            foreach ($sentList as $s) {
                                $status = 'sent';
                                $emptyErr = null;
                                $email = (string)$s['email'];
                                $name = (string)$s['name'];
                                $insItem->bind_param('issss', $campaignId, $email, $name, $status, $emptyErr);
                                $insItem->execute();
                            }
                            foreach ($failedList as $f) {
                                $status = 'failed';
                                $email = (string)$f['email'];
                                $name = (string)$f['name'];
                                $error = (string)$f['error'];
                                $insItem->bind_param('issss', $campaignId, $email, $name, $status, $error);
                                $insItem->execute();
                            }
                            $insItem->close();
                        }

                        $sentCount = count($sentList);
                        $failedCount = count($failedList);
                        $finalStatus = $failedCount === 0 ? 'success' : ($sentCount > 0 ? 'partial_failed' : 'failed');
                        if ($exitCode !== 0 && $sentCount === 0 && $failedCount === 0) {
                            $finalStatus = 'error';
                        }
                        $rawOutput = implode("\n", $outputLines);

                        $upd = $logConn->prepare('UPDATE email_campaign_logs SET sent_count=?, failed_count=?, status=?, raw_output=?, finished_at=NOW() WHERE id=?');
                        if ($upd) {
                            $upd->bind_param('iissi', $sentCount, $failedCount, $finalStatus, $rawOutput, $campaignId);
                            $upd->execute();
                            $upd->close();
                        }
                    }

                    $result = [
                        'campaignId' => $campaignId,
                        'exitCode' => $exitCode,
                        'output' => $outputLines,
                        'totalRecipients' => count($recipients),
                        'sentCount' => count($sentList),
                        'failedCount' => count($failedList)
                    ];
                } else {
                    $warning = 'Gagal menjalankan sender process. Pastikan command node tersedia di server.';
                }
            }
        }
    }
}

if ($logConn && !$logConn->connect_error) {
    $resTemplates = $logConn->query('SELECT id, template_name, subject, message_text, message_html, updated_at FROM email_templates ORDER BY updated_at DESC, id DESC LIMIT 200');
    if ($resTemplates) {
        while ($row = $resTemplates->fetch_assoc()) {
            $customTemplates[] = $row;
        }
    }
}

$recentCampaigns = [];
if ($logConn && !$logConn->connect_error) {
    $resCampaigns = $logConn->query('SELECT id, created_by_username, subject, from_email, total_recipients, sent_count, failed_count, status, batch_size, delay_ms, batch_delay_ms, created_at, finished_at FROM email_campaign_logs ORDER BY id DESC LIMIT 30');
    if ($resCampaigns) {
        while ($row = $resCampaigns->fetch_assoc()) {
            $recentCampaigns[] = $row;
        }
    }
}

$selectedCampaign = null;
$selectedCampaignItems = [];
$selectedCampaignId = isset($_GET['campaign_id']) ? (int)$_GET['campaign_id'] : ($result['campaignId'] ?? 0);
if ($selectedCampaignId > 0 && $logConn && !$logConn->connect_error) {
    $sc = $logConn->prepare('SELECT * FROM email_campaign_logs WHERE id=? LIMIT 1');
    if ($sc) {
        $sc->bind_param('i', $selectedCampaignId);
        $sc->execute();
        $selectedCampaign = $sc->get_result()->fetch_assoc();
        $sc->close();
    }

    $si = $logConn->prepare('SELECT recipient_email, recipient_name, send_status, error_message, created_at FROM email_campaign_log_items WHERE campaign_id=? ORDER BY id ASC LIMIT 500');
    if ($si) {
        $si->bind_param('i', $selectedCampaignId);
        $si->execute();
        $resItems = $si->get_result();
        while ($row = $resItems->fetch_assoc()) {
            $selectedCampaignItems[] = $row;
        }
        $si->close();
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
        .html-preview { background: #fff; border: 1px solid #dee2e6; border-radius: 8px; min-height: 220px; padding: 12px; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container my-4">
    <div class="card shadow-sm">
        <div class="card-body">
            <h4 class="mb-3">Email Notification</h4>
            <p class="text-muted mb-3">Gunakan Gmail App Password. Fitur ini sudah mendukung batch + delay, template HTML, dan logging campaign.</p>

            <?php if ($warning !== ''): ?>
                <div class="alert alert-warning"><?php echo htmlspecialchars($warning); ?></div>
            <?php endif; ?>
            <?php if ($notice !== ''): ?>
                <div class="alert alert-<?php echo $noticeType === 'success' ? 'success' : 'info'; ?>"><?php echo htmlspecialchars($notice); ?></div>
            <?php endif; ?>

            <form method="post" id="emailForm">
                <input type="hidden" name="form_action" value="send_campaign">
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
                        <input type="text" class="form-control" id="subject_input" name="subject" value="<?php echo htmlspecialchars($subject); ?>" required>
                        <div class="hint">Variables supported: <code>{name}</code>, <code>{email}</code></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Template Library</label>
                        <div class="d-flex gap-2">
                            <select class="form-select" id="template_selector">
                                <option value="">Select template...</option>
                            </select>
                            <button type="button" class="btn btn-outline-primary" id="applyTemplateBtn">
                                <i class="bi bi-magic me-1"></i>Apply Template
                            </button>
                        </div>
                        <div class="hint">Pilih template, otomatis isi subject + text + HTML, lalu bisa langsung Anda edit.</div>
                        <div class="border rounded p-3 mt-2 bg-light">
                            <label class="form-label mb-1">Manage Custom Templates</label>
                            <div class="d-flex gap-2 mb-2">
                                <input type="text" class="form-control" id="template_name_input" placeholder="Template name (e.g. Promo April)">
                                <button type="button" class="btn btn-success" id="saveTemplateBtn"><i class="bi bi-save me-1"></i>Save New</button>
                                <button type="button" class="btn btn-warning text-white" id="updateTemplateBtn"><i class="bi bi-pencil-square me-1"></i>Update</button>
                                <button type="button" class="btn btn-danger" id="deleteTemplateBtn"><i class="bi bi-trash me-1"></i>Delete</button>
                            </div>
                            <div class="hint">Pilih template custom dari dropdown lalu klik Update/Delete. Save New akan membuat template baru.</div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Text Body (fallback/plain)</label>
                        <textarea class="form-control" id="message_body_text" name="message_body_text" rows="8"><?php echo htmlspecialchars($messageBodyText); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">HTML Body (template editor)</label>
                        <textarea class="form-control" id="message_body_html" name="message_body_html" rows="8"><?php echo htmlspecialchars($messageBodyHtml); ?></textarea>
                        <div class="d-flex gap-2 mt-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="previewHtmlBtn">
                                <i class="bi bi-eye me-1"></i>Preview HTML
                            </button>
                            <span class="hint">Variables: <code>{name}</code>, <code>{email}</code></span>
                        </div>
                    </div>
                    <div class="col-12">
                        <div id="htmlPreview" class="html-preview"></div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Batch Size</label>
                        <input type="number" class="form-control" name="batch_size" min="1" max="200" value="<?php echo (int)$batchSize; ?>" required>
                        <div class="hint">Jumlah email per batch.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Delay per Email (ms)</label>
                        <input type="number" class="form-control" name="delay_ms" min="0" max="60000" value="<?php echo (int)$delayMs; ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Delay antar Batch (ms)</label>
                        <input type="number" class="form-control" name="batch_delay_ms" min="0" max="120000" value="<?php echo (int)$batchDelayMs; ?>" required>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Manual Recipients</label>
                        <textarea class="form-control" id="manual_recipients" name="manual_recipients" rows="6" placeholder="email@contoh.com,Nama User"><?php echo htmlspecialchars($manualRecipients); ?></textarea>
                        <div class="hint">Format per baris: <code>email</code> atau <code>email,nama</code>. Bisa import CSV/XLSX juga.</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Import Recipients (CSV/XLSX)</label>
                        <input type="file" id="importFile" class="form-control" accept=".csv,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv">
                        <div class="hint">Kolom didukung: <code>email</code> dan opsional <code>name</code>.</div>
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
                <h5>Result Campaign #<?php echo (int)$result['campaignId']; ?></h5>
                <p class="mb-2">
                    Processed: <strong><?php echo (int)$result['totalRecipients']; ?></strong> |
                    Sent: <strong class="text-success"><?php echo (int)$result['sentCount']; ?></strong> |
                    Failed: <strong class="text-danger"><?php echo (int)$result['failedCount']; ?></strong>
                </p>
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

    <div class="card shadow-sm mt-3">
        <div class="card-body">
            <h5 class="mb-3">Campaign History</h5>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Created By</th>
                            <th>Subject</th>
                            <th>Total</th>
                            <th>Sent</th>
                            <th>Failed</th>
                            <th>Status</th>
                            <th>Rate Limit</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($recentCampaigns)): ?>
                        <tr><td colspan="10" class="text-center text-muted">Belum ada history campaign.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentCampaigns as $c): ?>
                            <tr>
                                <td><?php echo (int)$c['id']; ?></td>
                                <td><?php echo htmlspecialchars((string)($c['created_by_username'] ?? '-')); ?></td>
                                <td><?php echo htmlspecialchars((string)$c['subject']); ?></td>
                                <td><?php echo (int)$c['total_recipients']; ?></td>
                                <td class="text-success"><?php echo (int)$c['sent_count']; ?></td>
                                <td class="text-danger"><?php echo (int)$c['failed_count']; ?></td>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars((string)$c['status']); ?></span></td>
                                <td>
                                    <?php echo (int)$c['batch_size']; ?>/batch<br>
                                    <?php echo (int)$c['delay_ms']; ?>ms + <?php echo (int)$c['batch_delay_ms']; ?>ms
                                </td>
                                <td><?php echo htmlspecialchars((string)$c['created_at']); ?></td>
                                <td><a class="btn btn-sm btn-outline-primary" href="email_notification?campaign_id=<?php echo (int)$c['id']; ?>">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($selectedCampaign): ?>
        <div class="card shadow-sm mt-3">
            <div class="card-body">
                <h5 class="mb-3">Campaign Detail #<?php echo (int)$selectedCampaign['id']; ?></h5>
                <p class="mb-2">
                    Subject: <strong><?php echo htmlspecialchars((string)$selectedCampaign['subject']); ?></strong><br>
                    Sender: <?php echo htmlspecialchars((string)$selectedCampaign['from_email']); ?> |
                    Status: <span class="badge bg-secondary"><?php echo htmlspecialchars((string)$selectedCampaign['status']); ?></span>
                </p>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Email</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Error</th>
                                <th>Logged At</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($selectedCampaignItems)): ?>
                            <tr><td colspan="5" class="text-center text-muted">Belum ada recipient log.</td></tr>
                        <?php else: ?>
                            <?php foreach ($selectedCampaignItems as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string)$item['recipient_email']); ?></td>
                                    <td><?php echo htmlspecialchars((string)$item['recipient_name']); ?></td>
                                    <td>
                                        <?php if ($item['send_status'] === 'sent'): ?>
                                            <span class="badge bg-success">sent</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">failed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars((string)($item['error_message'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars((string)$item['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<form method="post" id="templateForm" class="d-none">
    <input type="hidden" name="form_action" id="template_form_action" value="">
    <input type="hidden" name="template_id" id="template_form_template_id" value="">
    <input type="hidden" name="template_name" id="template_form_template_name" value="">
    <input type="hidden" name="subject_b64" id="template_form_subject" value="">
    <input type="hidden" name="message_body_text_b64" id="template_form_text" value="">
    <input type="hidden" name="message_body_html_b64" id="template_form_html" value="">
</form>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
const importInput = document.getElementById('importFile');
const recipientsTextarea = document.getElementById('manual_recipients');
const subjectInput = document.getElementById('subject_input');
const textTextarea = document.getElementById('message_body_text');
const htmlTextarea = document.getElementById('message_body_html');
const previewHtmlBtn = document.getElementById('previewHtmlBtn');
const htmlPreview = document.getElementById('htmlPreview');
const templateSelector = document.getElementById('template_selector');
const applyTemplateBtn = document.getElementById('applyTemplateBtn');
const templateNameInput = document.getElementById('template_name_input');
const saveTemplateBtn = document.getElementById('saveTemplateBtn');
const updateTemplateBtn = document.getElementById('updateTemplateBtn');
const deleteTemplateBtn = document.getElementById('deleteTemplateBtn');
const templateForm = document.getElementById('templateForm');
const templateFormAction = document.getElementById('template_form_action');
const templateFormTemplateId = document.getElementById('template_form_template_id');
const templateFormTemplateName = document.getElementById('template_form_template_name');
const templateFormSubject = document.getElementById('template_form_subject');
const templateFormText = document.getElementById('template_form_text');
const templateFormHtml = document.getElementById('template_form_html');

const builtinTemplates = [
    {
        id: 'new-job-alert',
        label: 'New Job Alert',
        subject: 'Lowongan Baru untuk {name} - Cek Sekarang',
        text: `Halo {name},

Kami punya informasi lowongan terbaru yang sesuai dengan minat Anda.

Silakan cek detail lowongan dan daftar melalui platform kami.

Jika ada pertanyaan, balas email ini kapan saja.

Salam,
Tim PaskerID`,
        html: `<div style="font-family:Arial,sans-serif;line-height:1.6;color:#1f2937;">
  <p>Halo <strong>{name}</strong>,</p>
  <p>Kami punya informasi <strong>lowongan terbaru</strong> yang sesuai dengan minat Anda.</p>
  <p>Silakan cek detail lowongan dan daftar melalui platform kami.</p>
  <p>Jika ada pertanyaan, balas email ini kapan saja.</p>
  <p>Salam,<br><strong>Tim PaskerID</strong></p>
</div>`
    },
    {
        id: 'event-invitation',
        label: 'Event Invitation',
        subject: 'Undangan Event Karier Eksklusif untuk {name}',
        text: `Halo {name},

Anda kami undang ke event karier terbaru dari PaskerID.

Agenda:
- Insight tren rekrutmen
- Sesi networking
- Akses lowongan prioritas

Daftar sekarang agar tidak kehabisan kuota.

Sampai jumpa di event!`,
        html: `<div style="font-family:Arial,sans-serif;line-height:1.6;color:#1f2937;">
  <p>Halo <strong>{name}</strong>,</p>
  <p>Anda kami undang ke <strong>event karier terbaru</strong> dari PaskerID.</p>
  <div style="background:#f9fafb;padding:12px;border-radius:8px;border:1px solid #e5e7eb;">
    <p style="margin:0 0 8px 0;"><strong>Agenda:</strong></p>
    <ul style="margin:0;padding-left:18px;">
      <li>Insight tren rekrutmen</li>
      <li>Sesi networking</li>
      <li>Akses lowongan prioritas</li>
    </ul>
  </div>
  <p>Daftar sekarang agar tidak kehabisan kuota.</p>
  <p>Sampai jumpa di event!</p>
</div>`
    },
    {
        id: 'profile-reminder',
        label: 'Profile Completion Reminder',
        subject: 'Lengkapi Profil Anda, {name} - Peluang Lebih Besar Menunggu',
        text: `Halo {name},

Profil Anda di PaskerID belum lengkap. Profil yang lengkap membantu perusahaan menemukan Anda lebih cepat.

Pastikan data berikut sudah terisi:
1. Riwayat pendidikan
2. Keahlian utama
3. Pengalaman kerja/organisasi

Lengkapi sekarang untuk meningkatkan peluang dipanggil rekruter.`,
        html: `<div style="font-family:Arial,sans-serif;line-height:1.6;color:#1f2937;">
  <p>Halo <strong>{name}</strong>,</p>
  <p>Profil Anda di PaskerID belum lengkap. Profil yang lengkap membantu perusahaan menemukan Anda lebih cepat.</p>
  <p><strong>Pastikan data berikut sudah terisi:</strong></p>
  <ol style="padding-left:18px;">
    <li>Riwayat pendidikan</li>
    <li>Keahlian utama</li>
    <li>Pengalaman kerja/organisasi</li>
  </ol>
  <p>Lengkapi sekarang untuk meningkatkan peluang dipanggil rekruter.</p>
</div>`
    },
    {
        id: 'application-followup',
        label: 'Application Follow-up',
        subject: 'Update Lamaran Anda di PaskerID',
        text: `Halo {name},

Kami ingin mengingatkan Anda untuk memantau status lamaran secara berkala.

Tips cepat:
- Perbarui CV terbaru
- Aktifkan notifikasi
- Cek email secara rutin

Semoga sukses pada proses seleksi Anda.`,
        html: `<div style="font-family:Arial,sans-serif;line-height:1.6;color:#1f2937;">
  <p>Halo <strong>{name}</strong>,</p>
  <p>Kami ingin mengingatkan Anda untuk memantau status lamaran secara berkala.</p>
  <p><strong>Tips cepat:</strong></p>
  <ul style="padding-left:18px;">
    <li>Perbarui CV terbaru</li>
    <li>Aktifkan notifikasi</li>
    <li>Cek email secara rutin</li>
  </ul>
  <p>Semoga sukses pada proses seleksi Anda.</p>
</div>`
    },
    {
        id: 'reactivation',
        label: 'Dormant User Reactivation',
        subject: '{name}, ada peluang baru menunggu Anda di PaskerID',
        text: `Halo {name},

Sudah lama Anda tidak mengakses akun PaskerID.

Saat ini ada berbagai peluang baru yang bisa Anda eksplorasi.
Kembali aktifkan akun Anda untuk mendapatkan rekomendasi lowongan terbaru.

Kami siap membantu perjalanan karier Anda.`,
        html: `<div style="font-family:Arial,sans-serif;line-height:1.6;color:#1f2937;">
  <p>Halo <strong>{name}</strong>,</p>
  <p>Sudah lama Anda tidak mengakses akun PaskerID.</p>
  <p>Saat ini ada berbagai peluang baru yang bisa Anda eksplorasi. Kembali aktifkan akun Anda untuk mendapatkan rekomendasi lowongan terbaru.</p>
  <p>Kami siap membantu perjalanan karier Anda.</p>
</div>`
    },
    {
        id: 'newsletter',
        label: 'Monthly Career Newsletter',
        subject: 'Newsletter Karier Bulanan - Insight & Peluang Terbaru',
        text: `Halo {name},

Berikut rangkuman update bulan ini:
- Tren industri dan kebutuhan skill
- Rekomendasi lowongan terpopuler
- Tips lolos screening HR

Terima kasih sudah menjadi bagian dari komunitas PaskerID.`,
        html: `<div style="font-family:Arial,sans-serif;line-height:1.6;color:#1f2937;">
  <p>Halo <strong>{name}</strong>,</p>
  <p>Berikut rangkuman update bulan ini:</p>
  <ul style="padding-left:18px;">
    <li>Tren industri dan kebutuhan skill</li>
    <li>Rekomendasi lowongan terpopuler</li>
    <li>Tips lolos screening HR</li>
  </ul>
  <p>Terima kasih sudah menjadi bagian dari komunitas PaskerID.</p>
</div>`
    }
];

const customTemplates = <?php echo json_encode($customTemplates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
const allTemplateOptions = [
    ...builtinTemplates.map((t) => ({
        key: `builtin:${t.id}`,
        id: t.id,
        kind: 'builtin',
        label: t.label,
        subject: t.subject,
        text: t.text,
        html: t.html
    })),
    ...customTemplates.map((t) => ({
        key: `custom:${t.id}`,
        id: Number(t.id),
        kind: 'custom',
        label: t.template_name,
        subject: t.subject || '',
        text: t.message_text || '',
        html: t.message_html || ''
    }))
];

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

function renderHtmlPreview() {
    const source = (htmlTextarea.value || '').trim();
    if (!source) {
        htmlPreview.innerHTML = '<span class="text-muted">Preview kosong. Isi template HTML lalu klik preview.</span>';
        return;
    }
    const sampleName = 'Sample User';
    const sampleEmail = 'sample@domain.com';
    const rendered = source.replace(/\{name\}/g, sampleName).replace(/\{email\}/g, sampleEmail);
    htmlPreview.innerHTML = rendered;
}

function initTemplateLibrary() {
    const builtins = allTemplateOptions.filter((t) => t.kind === 'builtin');
    const customs = allTemplateOptions.filter((t) => t.kind === 'custom');

    if (builtins.length > 0) {
        const ogBuiltin = document.createElement('optgroup');
        ogBuiltin.label = 'Built-in Templates';
        builtins.forEach((tpl) => {
            const opt = document.createElement('option');
            opt.value = tpl.key;
            opt.textContent = tpl.label;
            ogBuiltin.appendChild(opt);
        });
        templateSelector.appendChild(ogBuiltin);
    }

    if (customs.length > 0) {
        const ogCustom = document.createElement('optgroup');
        ogCustom.label = 'Saved Templates';
        customs.forEach((tpl) => {
            const opt = document.createElement('option');
            opt.value = tpl.key;
            opt.textContent = tpl.label;
            ogCustom.appendChild(opt);
        });
        templateSelector.appendChild(ogCustom);
    }
}

function getSelectedTemplate() {
    const selectedKey = templateSelector.value;
    return allTemplateOptions.find((tpl) => tpl.key === selectedKey) || null;
}

function populateTemplateForm(action, templateId, templateName) {
    const toBase64 = (value) => {
        const str = String(value || '');
        return window.btoa(unescape(encodeURIComponent(str)));
    };
    templateFormAction.value = action;
    templateFormTemplateId.value = templateId ? String(templateId) : '';
    templateFormTemplateName.value = templateName || '';
    templateFormSubject.value = toBase64(subjectInput.value || '');
    templateFormText.value = toBase64(textTextarea.value || '');
    templateFormHtml.value = toBase64(htmlTextarea.value || '');
}

function applySelectedTemplate() {
    const tpl = getSelectedTemplate();
    if (!tpl) return;

    const hasExisting = (subjectInput.value || '').trim() || (textTextarea.value || '').trim() || (htmlTextarea.value || '').trim();
    if (hasExisting) {
        const ok = window.confirm('Current subject/body will be replaced by selected template. Continue?');
        if (!ok) return;
    }
    subjectInput.value = tpl.subject;
    textTextarea.value = tpl.text;
    htmlTextarea.value = tpl.html;
    if (tpl.kind === 'custom') {
        templateNameInput.value = tpl.label;
    }
    renderHtmlPreview();
}

previewHtmlBtn.addEventListener('click', renderHtmlPreview);
applyTemplateBtn.addEventListener('click', applySelectedTemplate);
saveTemplateBtn.addEventListener('click', () => {
    const name = (templateNameInput.value || '').trim();
    if (!name) {
        window.alert('Isi nama template terlebih dulu.');
        return;
    }
    populateTemplateForm('save_template', 0, name);
    templateForm.submit();
});
updateTemplateBtn.addEventListener('click', () => {
    const tpl = getSelectedTemplate();
    if (!tpl || tpl.kind !== 'custom') {
        window.alert('Pilih template custom dari dropdown untuk diupdate.');
        return;
    }
    const name = (templateNameInput.value || tpl.label || '').trim();
    if (!name) {
        window.alert('Nama template tidak boleh kosong.');
        return;
    }
    populateTemplateForm('update_template', tpl.id, name);
    templateForm.submit();
});
deleteTemplateBtn.addEventListener('click', () => {
    const tpl = getSelectedTemplate();
    if (!tpl || tpl.kind !== 'custom') {
        window.alert('Pilih template custom dari dropdown untuk dihapus.');
        return;
    }
    if (!window.confirm(`Delete template "${tpl.label}"?`)) return;
    populateTemplateForm('delete_template', tpl.id, tpl.label);
    templateForm.submit();
});
templateSelector.addEventListener('change', () => {
    const tpl = getSelectedTemplate();
    if (tpl && tpl.kind === 'custom') {
        templateNameInput.value = tpl.label;
    }
});
document.addEventListener('DOMContentLoaded', () => {
    initTemplateLibrary();
    renderHtmlPreview();
});

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
