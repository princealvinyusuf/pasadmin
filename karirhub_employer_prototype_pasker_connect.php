<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_ui.php';

if (!kh_proto_can_access('karirhub_employer_prototype_pasker_connect_view')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
$baseUrl = '/pasadmin';
$sandboxBase = rtrim($baseUrl, '/') . '/api';
$sandboxAbsolute = $scheme . '://' . $host . $sandboxBase;
$productionBase = 'https://joss.kemnaker.go.id/api';

$endpointGroups = [
    [
        'title' => 'Employer API',
        'rows' => [
            ['GET', '/api/wllp/employer/dashboard', 'Employer dashboard metrics', 'Read'],
            ['GET', '/api/wllp/reports', 'List Bukti Lapor by employer', 'Read'],
            ['POST', '/api/wllp/reports', 'Create manual WLLP report', 'Write'],
            ['POST', '/api/wllp/reports/bulk/validate', 'Validate bulk payload', 'Write'],
            ['POST', '/api/wllp/reports/bulk/commit', 'Commit validated batch', 'Write'],
            ['GET', '/api/wllp/reports/{id}', 'Report detail', 'Read'],
            ['GET', '/api/wllp/reports/{id}/pdf', 'Download Bukti Lapor PDF', 'Read'],
            ['GET', '/api/wllp/items/{itemId}/status', 'Get item status', 'Read'],
            ['PUT', '/api/wllp/items/{itemId}/status', 'Update item status', 'Write'],
            ['POST', '/api/wllp/items/{itemId}/placements', 'Add placement', 'Write'],
        ],
    ],
    [
        'title' => 'Karirhub Bridge API',
        'rows' => [
            ['GET', '/api/karirhub/jobs/posted', 'List posted jobs', 'Read'],
            ['POST', '/api/karirhub/jobs/{jobId}/add-to-wllp', 'Add posted job to WLLP', 'Write'],
        ],
    ],
    [
        'title' => 'Admin API',
        'rows' => [
            ['GET', '/api/admin/wllp/dashboard', 'Admin analytics summary', 'Read'],
            ['GET', '/api/admin/wllp/reports', 'Cross-employer report list', 'Read'],
            ['GET', '/api/admin/wllp/compliance', 'Compliance overview', 'Read'],
            ['PUT', '/api/admin/wllp/reports/{id}/verification', 'Verify/reject/needs update', 'Write'],
            ['GET', '/api/admin/wllp/export', 'Export CSV', 'Read'],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karirhub Employer Prototype - Pasker Connect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php kh_proto_render_styles(); ?>
    <style>
        .pc-section-title { font-size: 1.2rem; font-weight: 700; color: #0b3b66; }
        .pc-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
        .pc-endpoint-badge { font-size: 11px; font-weight: 700; letter-spacing: 0.04em; }
        .pc-card { border: 1px solid #d7e2ee; border-radius: 8px; }
        .pc-table th { white-space: nowrap; }
        pre.pc-pre { margin: 0; white-space: pre-wrap; font-size: 12px; max-height: 340px; overflow: auto; }
        .pc-toc a { text-decoration: none; }
        .pc-kv { display: grid; grid-template-columns: 180px 1fr; gap: 8px; font-size: 13px; }
        .pc-kv .k { color: #52667a; font-weight: 600; }
        .pc-kv .v { color: #1f2f42; }
        .pc-code-block { background: #0f1720; color: #d7e3f5; border-radius: 8px; padding: 12px; }
        .pc-chip { border: 1px solid #d7e2ee; border-radius: 999px; padding: 3px 10px; font-size: 12px; background: #f8fbff; display: inline-block; margin: 2px 4px 2px 0; }
        .pc-hr { border-top: 1px dashed #d7e2ee; margin: 14px 0; }
        .pc-small { font-size: 12px; color: #637a92; }
        .pc-anchor { scroll-margin-top: 120px; }
        .pc-endpoint-card { border: 1px solid #d7e2ee; border-radius: 8px; padding: 14px; background: #ffffff; }
    </style>
</head>
<body class="kh-proto-page">
<?php include 'navbar.php'; ?>
<?php kh_proto_render_hero('Pasker Connect', 'Dokumentasi API eksternal WLLP untuk integrasi stakeholder portal kerja.', 'Lowongan Kerja', 'karirhub_employer_prototype_pelaporan_lowongan', 'Proyek', 'karirhub_employer_prototype_dashboard_wllp'); ?>

<div class="kh-content-wrap">
<div class="container py-4">
    <div class="kh-proto-shell">
        <?php kh_proto_render_sidebar('wllp_pasker_connect'); ?>
        <main class="kh-proto-main">
            <div class="card pc-card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                        <div>
                            <h3 class="mb-1">Pasker Connect API Documentation</h3>
                            <div class="text-muted">Panduan integrasi end-to-end untuk external stakeholder agar dapat mengirim, memantau, dan memvalidasi data WLLP secara aman.</div>
                        </div>
                        <div class="text-end">
                            <span class="badge text-bg-primary pc-endpoint-badge">Version v1</span><br>
                            <span class="pc-small">Last update: <?php echo h(date('Y-m-d')); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card pc-card mb-3">
                <div class="card-body">
                    <div class="pc-section-title mb-2 pc-anchor" id="overview">1) Gambaran Umum</div>
                    <p class="mb-2">Pasker Connect menggunakan model request signed header bergaya JOSS, dengan dukungan endpoint employer, admin, dan bridge data Karirhub jobs. API ini ditujukan untuk sistem eksternal seperti portal kerja mitra, agregator lowongan, dan integrator institusi.</p>
                    <ul class="mb-0">
                        <li>Sandbox Base URL: <span class="pc-mono"><?php echo h($sandboxAbsolute); ?></span></li>
                        <li>Production Base URL: <span class="pc-mono"><?php echo h($productionBase); ?></span></li>
                        <li>Format response: JSON (kecuali endpoint export CSV dan report PDF).</li>
                        <li>Metode autentikasi: Signature berbasis HMAC SHA256.</li>
                    </ul>
                    <div class="pc-hr"></div>
                    <div class="pc-section-title mb-2">Table of Contents</div>
                    <div class="pc-toc d-flex flex-wrap gap-2">
                        <a class="pc-chip" href="#overview">Overview</a>
                        <a class="pc-chip" href="#auth">Authentication</a>
                        <a class="pc-chip" href="#conventions">Request Conventions</a>
                        <a class="pc-chip" href="#endpoint-summary">Endpoint Summary</a>
                        <a class="pc-chip" href="#endpoint-details">Endpoint Details</a>
                        <a class="pc-chip" href="#errors">Error Catalog</a>
                        <a class="pc-chip" href="#ops">Operational Guidance</a>
                        <a class="pc-chip" href="#checklist">Go-live Checklist</a>
                        <a class="pc-chip" href="#changelog">Changelog</a>
                    </div>
                </div>
            </div>

            <div class="card pc-card mb-3">
                <div class="card-body">
                    <div class="pc-section-title mb-2 pc-anchor" id="auth">2) Authentication</div>
                    <p class="mb-2">Setiap request wajib mengirim header berikut. Validasi signature dilakukan sebelum proses business logic endpoint.</p>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered pc-table mb-3">
                            <thead class="table-light">
                            <tr><th>Header</th><th>Required</th><th>Notes</th></tr>
                            </thead>
                            <tbody>
                            <tr><td class="pc-mono">Client-Id</td><td>Yes</td><td>Identifier client dari Kemnaker.</td></tr>
                            <tr><td class="pc-mono">Request-Id</td><td>Yes</td><td>Harus unik per request (anti replay).</td></tr>
                            <tr><td class="pc-mono">Request-Timestamp</td><td>Yes</td><td>ISO8601 UTC, toleransi 5 menit.</td></tr>
                            <tr><td class="pc-mono">Signature</td><td>Yes</td><td>HMAC SHA256 canonical request.</td></tr>
                            <tr><td class="pc-mono">Content-Type</td><td>Yes</td><td><span class="pc-mono">application/json</span> untuk request JSON.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="alert alert-info mb-3">
                        Canonical string:<br>
                        <span class="pc-mono">METHOD + "\\n" + PATH + "\\n" + Client-Id + "\\n" + Request-Id + "\\n" + Request-Timestamp + "\\n" + SHA256(raw_body)</span>
                    </div>
                    <div class="pc-code-block">
<pre class="pc-pre"># Signature formula
signature = HEX( HMAC_SHA256(canonical_string, client_secret) )

# Example canonical string
POST
/api/wllp/reports
demo-client
req-20260526-0001
2026-05-26T05:00:00Z
2f3d7d3f18f8f5d0ab63b0bb1f9e4f0e5f6cc57bf4d0bb1f2f92f2a1d06322a1</pre>
                    </div>
                    <div class="pc-small mt-2">
                        Notes: Untuk request GET tanpa body, gunakan SHA256 dari string kosong. Signature selalu dihitung dari raw JSON body persis seperti yang dikirim.
                    </div>
                </div>
            </div>

            <div class="card pc-card mb-3">
                <div class="card-body">
                    <div class="pc-section-title mb-2 pc-anchor" id="conventions">3) Request & Response Conventions</div>
                    <div class="pc-kv mb-3">
                        <div class="k">Protocol</div><div class="v">HTTPS only</div>
                        <div class="k">Request timestamp</div><div class="v">ISO8601 UTC (contoh: <span class="pc-mono">2026-05-26T05:00:00Z</span>)</div>
                        <div class="k">Pagination</div><div class="v"><span class="pc-mono">limit</span> dan <span class="pc-mono">offset</span> pada endpoint list</div>
                        <div class="k">Success envelope</div><div class="v"><span class="pc-mono">{"success":true,...}</span></div>
                        <div class="k">Error envelope</div><div class="v"><span class="pc-mono">{"success":false,"error_code":"..."}</span></div>
                    </div>
                    <div class="pc-code-block">
<pre class="pc-pre">{
  "success": false,
  "error_code": "VALIDATION_FAILED",
  "message": "Data belum lengkap.",
  "fields": {
    "items.0.title": "Required field."
  }
}</pre>
                    </div>
                </div>
            </div>

            <div class="card pc-card mb-3">
                <div class="card-body">
                    <div class="pc-section-title mb-2 pc-anchor" id="endpoint-summary">4) Endpoint Summary</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                            <tr><th>Group</th><th>Method</th><th>Path</th><th>Purpose</th><th>Type</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($endpointGroups as $group): ?>
                                <?php foreach ($group['rows'] as $row): ?>
                                    <tr>
                                        <td><?php echo h($group['title']); ?></td>
                                        <td><span class="badge text-bg-<?php echo $row[0] === 'GET' ? 'success' : ($row[0] === 'PUT' ? 'warning' : 'primary'); ?>"><?php echo h($row[0]); ?></span></td>
                                        <td class="pc-mono"><?php echo h($row[1]); ?></td>
                                        <td><?php echo h($row[2]); ?></td>
                                        <td><?php echo h($row[3]); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card pc-card mb-3">
                <div class="card-body">
                    <div class="pc-section-title mb-2 pc-anchor" id="endpoint-details">5) Endpoint Details</div>

                    <div class="pc-endpoint-card mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div><span class="badge text-bg-primary me-2">POST</span><span class="pc-mono">/api/wllp/reports</span></div>
                            <span class="pc-small">Create manual WLLP report</span>
                        </div>
                        <div class="pc-kv mb-2">
                            <div class="k">Purpose</div><div class="v">Membuat report WLLP baru atau reuse report pada periode yang sama untuk employer terkait.</div>
                            <div class="k">Required body</div><div class="v"><span class="pc-mono">employer_id, unit_id, period_type, period_anchor, terms, items[]</span></div>
                            <div class="k">Validation highlights</div><div class="v"><span class="pc-mono">headcount_needed > 0</span>, <span class="pc-mono">period_type weekly/monthly</span>, <span class="pc-mono">terms.agreed = true</span></div>
                        </div>
<pre class="pc-pre bg-dark text-light rounded p-3">curl --request POST "<?php echo h($sandboxAbsolute); ?>/wllp/reports" \
  --header "Client-Id: demo-client" \
  --header "Request-Id: req-20260526-0001" \
  --header "Request-Timestamp: 2026-05-26T05:00:00Z" \
  --header "Signature: &lt;hmac_sha256_signature&gt;" \
  --header "Content-Type: application/json" \
  --data '{
    "employer_id": 1,
    "unit_id": 1001,
    "period_type": "weekly",
    "period_anchor": "2026-06-03",
    "notes": "Pelaporan minggu pertama Juni",
    "terms": {"agreed": true, "version": "WLLP-TC-2026-01"},
    "items": [{
      "title": "Staff Operasional",
      "headcount_needed": 3,
      "job_description": "Melakukan operasional harian.",
      "skills": "Microsoft Office, komunikasi"
    }]
  }'

# Success example
{
  "success": true,
  "report": {
    "id": 501,
    "no_reg_bukti": "WLLP-572606-00000001",
    "period_start": "2026-06-02",
    "period_end": "2026-06-08",
    "verification_status": "submitted"
  },
  "items": [
    {"id": 9001, "id_lowongan": "LK-000001", "status": "Belum Terisi"}
  ]
}</pre>
                    </div>

                    <div class="pc-endpoint-card mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div><span class="badge text-bg-warning me-2">PUT</span><span class="pc-mono">/api/wllp/items/{itemId}/status</span></div>
                            <span class="pc-small">Update status keterisian</span>
                        </div>
                        <div class="pc-kv mb-2">
                            <div class="k">Purpose</div><div class="v">Update status proses rekrutmen untuk item lowongan.</div>
                            <div class="k">Required body</div><div class="v"><span class="pc-mono">status</span> (note opsional)</div>
                            <div class="k">Status sample</div><div class="v">Belum Terisi, Proses Seleksi, Terisi</div>
                        </div>
<pre class="pc-pre bg-dark text-light rounded p-3">{
  "status": "Proses Seleksi",
  "note": "Kandidat sedang tahap interview"
}</pre>
                    </div>

                    <div class="pc-endpoint-card mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div><span class="badge text-bg-primary me-2">POST</span><span class="pc-mono">/api/wllp/items/{itemId}/placements</span></div>
                            <span class="pc-small">Add placement data</span>
                        </div>
                        <div class="pc-kv mb-2">
                            <div class="k">Purpose</div><div class="v">Mencatat data penempatan pekerja atas item lowongan.</div>
                            <div class="k">Required body</div><div class="v"><span class="pc-mono">nik, full_name, start_date</span></div>
                            <div class="k">Business rule</div><div class="v">Jumlah placement tidak boleh melebihi <span class="pc-mono">headcount_needed</span>.</div>
                        </div>
<pre class="pc-pre bg-dark text-light rounded p-3">{
  "nik": "3171xxxxxxxxxxxx",
  "full_name": "Budi Santoso",
  "education_id": 5,
  "gender": "Laki-laki",
  "birth_place": "Jakarta",
  "birth_date": "1998-01-20",
  "address": "Jakarta Selatan",
  "disability_status": false,
  "start_date": "2026-06-10",
  "email": "budi@example.com",
  "phone": "081234567890"
}</pre>
                    </div>

                    <div class="pc-endpoint-card mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div><span class="badge text-bg-primary me-2">POST</span><span class="pc-mono">/api/karirhub/jobs/{jobId}/add-to-wllp</span></div>
                            <span class="pc-small">Karirhub bridge</span>
                        </div>
                        <div class="pc-kv mb-2">
                            <div class="k">Purpose</div><div class="v">Menambahkan lowongan dari data Karirhub jobs posted ke report WLLP.</div>
                            <div class="k">Required body</div><div class="v"><span class="pc-mono">employer_id, unit_id, period_type, period_anchor, terms</span></div>
                        </div>
<pre class="pc-pre bg-dark text-light rounded p-3">{
  "success": true,
  "reused_report": true,
  "no_reg_bukti": "WLLP-572606-00000001",
  "id_lowongan": "LK-000002",
  "status_label": "Berhasil ditambahkan ke WLLP"
}</pre>
                    </div>

                    <div class="pc-endpoint-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div><span class="badge text-bg-warning me-2">PUT</span><span class="pc-mono">/api/admin/wllp/reports/{id}/verification</span></div>
                            <span class="pc-small">Admin verification flow</span>
                        </div>
                        <div class="pc-kv mb-2">
                            <div class="k">Allowed status</div><div class="v"><span class="pc-mono">verified</span>, <span class="pc-mono">rejected</span>, <span class="pc-mono">needs_update</span></div>
                            <div class="k">Audit</div><div class="v">Perubahan status tercatat pada verification log dan audit log.</div>
                        </div>
<pre class="pc-pre bg-dark text-light rounded p-3">{
  "status": "verified",
  "note": "Dokumen valid dan lengkap"
}</pre>
                    </div>
                </div>
            </div>

            <div class="card pc-card mb-3">
                <div class="card-body">
                    <div class="pc-section-title mb-2 pc-anchor" id="errors">6) Error Catalog</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr><th>Error Code</th><th>HTTP</th><th>Meaning</th><th>Recommended Action</th></tr>
                            </thead>
                            <tbody>
                                <tr><td><span class="badge text-bg-danger">UNAUTHORIZED</span></td><td>401</td><td>Header auth invalid/expired/signature mismatch.</td><td>Sync clock, re-sign request, verify client secret.</td></tr>
                                <tr><td><span class="badge text-bg-danger">DUPLICATE_REQUEST_ID</span></td><td>409</td><td>Request-Id sudah pernah dipakai.</td><td>Generate Request-Id baru untuk retry.</td></tr>
                                <tr><td><span class="badge text-bg-warning">VALIDATION_FAILED</span></td><td>422</td><td>Payload gagal validasi field/rules.</td><td>Perbaiki fields sesuai detail pada object <span class="pc-mono">fields</span>.</td></tr>
                                <tr><td><span class="badge text-bg-warning">TERMS_REQUIRED</span></td><td>422</td><td>Persetujuan terms tidak valid.</td><td>Pastikan <span class="pc-mono">terms.agreed=true</span> dan version terisi.</td></tr>
                                <tr><td><span class="badge text-bg-warning">PLACEMENT_LIMIT_EXCEEDED</span></td><td>409</td><td>Placement melebihi kebutuhan lowongan.</td><td>Kurangi input placement atau revisi headcount item.</td></tr>
                                <tr><td><span class="badge text-bg-secondary">REPORT_NOT_FOUND</span></td><td>404</td><td>Report ID tidak ditemukan.</td><td>Pastikan report_id valid dan masih dalam scope akses.</td></tr>
                                <tr><td><span class="badge text-bg-secondary">ITEM_NOT_FOUND</span></td><td>404</td><td>Item ID tidak ditemukan.</td><td>Pastikan itemId berasal dari report valid.</td></tr>
                                <tr><td><span class="badge text-bg-secondary">NOT_FOUND</span></td><td>404</td><td>Endpoint path tidak dikenali.</td><td>Periksa method/path sesuai dokumentasi.</td></tr>
                                <tr><td><span class="badge text-bg-dark">INTERNAL_ERROR</span></td><td>500</td><td>Kesalahan server internal.</td><td>Retry dengan Request-Id baru, lalu hubungi support jika berulang.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card pc-card mb-3">
                <div class="card-body">
                    <div class="pc-section-title mb-2 pc-anchor" id="ops">7) Operational Guidance</div>
                    <ul class="mb-0">
                        <li><strong>Retry policy:</strong> retry hanya untuk 5xx/timeout, tidak untuk 4xx validasi.</li>
                        <li><strong>Idempotency:</strong> setiap retry wajib memakai <span class="pc-mono">Request-Id</span> baru.</li>
                        <li><strong>Backoff:</strong> disarankan exponential backoff (1s, 2s, 4s, 8s; max 30s).</li>
                        <li><strong>Correlation:</strong> simpan <span class="pc-mono">Request-Id</span> di log client untuk troubleshooting.</li>
                        <li><strong>Time sync:</strong> gunakan NTP sinkron UTC untuk mencegah auth failure karena skew timestamp.</li>
                    </ul>
                </div>
            </div>

            <div class="card pc-card mb-3">
                <div class="card-body">
                    <div class="pc-section-title mb-2 pc-anchor" id="checklist">8) Go-live Checklist</div>
                    <ol class="mb-0">
                        <li>Dapatkan <span class="pc-mono">Client-Id</span> dan <span class="pc-mono">Client-Secret</span> dari admin Kemnaker.</li>
                        <li>Sinkronkan server time dengan UTC (NTP).</li>
                        <li>Implementasikan HMAC SHA256 signer dan validasi canonical string.</li>
                        <li>Uji positive scenario untuk semua endpoint utama.</li>
                        <li>Uji negative scenario: signature invalid, timestamp expired, duplicate Request-Id, payload invalid.</li>
                        <li>Verifikasi log monitoring request di dashboard API clients.</li>
                        <li>Siapkan proses rotasi secret dan incident response kontak teknis.</li>
                    </ol>
                </div>
            </div>

            <div class="card pc-card">
                <div class="card-body">
                    <div class="pc-section-title mb-2 pc-anchor" id="changelog">9) Changelog & Versioning</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr><th>Version</th><th>Date</th><th>Changes</th><th>Compatibility</th></tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="badge text-bg-primary">v1.0</span></td>
                                    <td><?php echo h(date('Y-m-d')); ?></td>
                                    <td>Initial release: employer/admin/karirhub bridge endpoints, signature auth, replay protection, audit logging.</td>
                                    <td>Backward compatible baseline</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="pc-small mt-2">
                        Referensi pola dokumentasi dan autentikasi: <a href="https://joss.docs.kemnaker.go.id/" target="_blank" rel="noopener">https://joss.docs.kemnaker.go.id/</a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php kh_proto_render_sidebar_script(); ?>
</body>
</html>

