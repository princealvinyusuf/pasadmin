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

$baseUrl = '/pasadmin';
$sandboxBase = rtrim($baseUrl, '/') . '/api';
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
        pre.pc-pre { margin: 0; white-space: pre-wrap; font-size: 12px; }
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
                            <div class="text-muted">Referensi integrasi untuk external stakeholder agar dapat mengirim data WLLP.</div>
                        </div>
                        <span class="badge text-bg-primary pc-endpoint-badge">Version v1</span>
                    </div>
                </div>
            </div>

            <div class="card pc-card mb-3">
                <div class="card-body">
                    <div class="pc-section-title mb-2">Overview</div>
                    <p class="mb-2">Pasker Connect menggunakan model request signed header bergaya JOSS, dengan dukungan endpoint employer, admin, dan bridge data Karirhub jobs.</p>
                    <ul class="mb-0">
                        <li>Sandbox Base URL: <span class="pc-mono"><?php echo h($sandboxBase); ?></span></li>
                        <li>Production Base URL: <span class="pc-mono">https://joss.kemnaker.go.id/api</span> (placeholder deployment target)</li>
                        <li>Format response: JSON (kecuali endpoint export CSV dan report PDF).</li>
                    </ul>
                </div>
            </div>

            <div class="card pc-card mb-3">
                <div class="card-body">
                    <div class="pc-section-title mb-2">Authentication</div>
                    <p class="mb-2">Setiap request wajib mengirim header berikut:</p>
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
                    <div class="alert alert-info mb-0">
                        Canonical string: <span class="pc-mono">METHOD + "\\n" + PATH + "\\n" + Client-Id + "\\n" + Request-Id + "\\n" + Request-Timestamp + "\\n" + SHA256(body)</span>
                    </div>
                </div>
            </div>

            <div class="card pc-card mb-3">
                <div class="card-body">
                    <div class="pc-section-title mb-2">Endpoint Groups</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                            <tr><th>Method</th><th>Path</th><th>Purpose</th></tr>
                            </thead>
                            <tbody>
                            <tr><td><span class="badge text-bg-success">GET</span></td><td class="pc-mono">/api/wllp/employer/dashboard?employer_id=1</td><td>Employer dashboard metrics.</td></tr>
                            <tr><td><span class="badge text-bg-success">GET</span></td><td class="pc-mono">/api/wllp/reports?employer_id=1</td><td>List Bukti Lapor by employer.</td></tr>
                            <tr><td><span class="badge text-bg-primary">POST</span></td><td class="pc-mono">/api/wllp/reports</td><td>Create manual WLLP report.</td></tr>
                            <tr><td><span class="badge text-bg-primary">POST</span></td><td class="pc-mono">/api/wllp/reports/bulk/validate</td><td>Validate bulk payload or xlsx metadata.</td></tr>
                            <tr><td><span class="badge text-bg-primary">POST</span></td><td class="pc-mono">/api/wllp/reports/bulk/commit</td><td>Commit validated batch.</td></tr>
                            <tr><td><span class="badge text-bg-success">GET</span></td><td class="pc-mono">/api/wllp/reports/{id}</td><td>Report detail.</td></tr>
                            <tr><td><span class="badge text-bg-success">GET</span></td><td class="pc-mono">/api/wllp/reports/{id}/pdf</td><td>Download Bukti Lapor PDF.</td></tr>
                            <tr><td><span class="badge text-bg-success">GET</span></td><td class="pc-mono">/api/wllp/items/{itemId}/status</td><td>Get status keterisian.</td></tr>
                            <tr><td><span class="badge text-bg-warning">PUT</span></td><td class="pc-mono">/api/wllp/items/{itemId}/status</td><td>Update status keterisian.</td></tr>
                            <tr><td><span class="badge text-bg-primary">POST</span></td><td class="pc-mono">/api/wllp/items/{itemId}/placements</td><td>Add placement data.</td></tr>
                            <tr><td><span class="badge text-bg-success">GET</span></td><td class="pc-mono">/api/karirhub/jobs/posted</td><td>List posted jobs from Karirhub bridge.</td></tr>
                            <tr><td><span class="badge text-bg-primary">POST</span></td><td class="pc-mono">/api/karirhub/jobs/{jobId}/add-to-wllp</td><td>Add posted job to WLLP report.</td></tr>
                            <tr><td><span class="badge text-bg-success">GET</span></td><td class="pc-mono">/api/admin/wllp/dashboard</td><td>Admin analytics summary.</td></tr>
                            <tr><td><span class="badge text-bg-success">GET</span></td><td class="pc-mono">/api/admin/wllp/reports</td><td>Cross-employer report list.</td></tr>
                            <tr><td><span class="badge text-bg-success">GET</span></td><td class="pc-mono">/api/admin/wllp/compliance</td><td>Compliance overview by employer.</td></tr>
                            <tr><td><span class="badge text-bg-warning">PUT</span></td><td class="pc-mono">/api/admin/wllp/reports/{id}/verification</td><td>Verify/reject/needs_update report.</td></tr>
                            <tr><td><span class="badge text-bg-success">GET</span></td><td class="pc-mono">/api/admin/wllp/export</td><td>CSV export for admin.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card pc-card mb-3">
                <div class="card-body">
                    <div class="pc-section-title mb-2">Sample cURL</div>
<pre class="pc-pre bg-dark text-light rounded p-3">curl --request POST "<?php echo h($sandboxBase); ?>/wllp/reports" \
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
  }'</pre>
                </div>
            </div>

            <div class="card pc-card mb-3">
                <div class="card-body">
                    <div class="pc-section-title mb-2">Error Codes</div>
                    <div class="row g-2">
                        <div class="col-12 col-md-6"><span class="badge text-bg-danger">UNAUTHORIZED</span> Header auth tidak valid/expired.</div>
                        <div class="col-12 col-md-6"><span class="badge text-bg-danger">DUPLICATE_REQUEST_ID</span> Request-Id sudah pernah dipakai.</div>
                        <div class="col-12 col-md-6"><span class="badge text-bg-warning">VALIDATION_FAILED</span> Validasi payload gagal.</div>
                        <div class="col-12 col-md-6"><span class="badge text-bg-warning">TERMS_REQUIRED</span> Persetujuan terms wajib.</div>
                        <div class="col-12 col-md-6"><span class="badge text-bg-warning">PLACEMENT_LIMIT_EXCEEDED</span> Placement melebihi headcount.</div>
                        <div class="col-12 col-md-6"><span class="badge text-bg-secondary">NOT_FOUND</span> Path atau resource tidak ditemukan.</div>
                    </div>
                </div>
            </div>

            <div class="card pc-card">
                <div class="card-body">
                    <div class="pc-section-title mb-2">Integration Checklist</div>
                    <ol class="mb-0">
                        <li>Dapatkan <span class="pc-mono">Client-Id</span> dan <span class="pc-mono">Client-Secret</span> dari admin Kemnaker.</li>
                        <li>Sinkronkan server time dengan UTC untuk validasi timestamp.</li>
                        <li>Implementasikan HMAC SHA256 signer di backend integrator.</li>
                        <li>Gunakan <span class="pc-mono">Request-Id</span> unik per request.</li>
                        <li>Uji endpoint employer sebelum memanggil endpoint admin.</li>
                    </ol>
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

