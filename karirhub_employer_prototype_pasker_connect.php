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

$sandboxAbsolute = 'https://sandbox.wllpconnect.kemnaker.go.id';
$productionBase = 'https://wllpconnect.kemnaker.go.id';
$showEmployerDashboardEndpoint = false;
$showAdminApiEndpoints = false;
$showKarirhubBridgeApiEndpoints = false;
$showEmployerBulkEndpoints = false;

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
                    <p class="mb-2">Pasker Connect menggunakan model request signed header bergaya JOSS, dengan dukungan endpoint employer API. API ini ditujukan untuk sistem eksternal seperti portal kerja mitra, agregator lowongan, dan integrator institusi.</p>
                    <ul class="mb-0">
                        <li>Sandbox Base URL: <span class="pc-mono"><?php echo h($sandboxAbsolute); ?></span></li>
                        <li>Production Base URL: <span class="pc-mono"><?php echo h($productionBase); ?></span></li>
                        <li>Format response: JSON (kecuali endpoint report PDF).</li>
                        <li>Metode autentikasi: Signature berbasis HMAC SHA256.</li>
                    </ul>
                    <div class="pc-hr"></div>
                    <div class="pc-section-title mb-2">Table of Contents</div>
                    <div class="pc-toc d-flex flex-wrap gap-2">
                        <a class="pc-chip" href="#overview">Overview</a>
                        <a class="pc-chip" href="#auth">Authentication</a>
                        <a class="pc-chip" href="#endpoint-summary">Endpoint Summary</a>
                        <a class="pc-chip" href="#endpoint-details">Endpoint Details</a>
                        <a class="pc-chip" href="#errors">Error Catalog</a>
                        <a class="pc-chip" href="#ops">Operational Guidance</a>
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
                            <tr><td class="pc-mono">Request-Id</td><td>Yes</td><td>Harus unik per request.</td></tr>
                            <tr><td class="pc-mono">Request-Timestamp</td><td>Yes</td><td>ISO8601 UTC.</td></tr>
                            <tr><td class="pc-mono">Signature</td><td>Yes</td><td>HMAC SHA256.</td></tr>
                            <tr><td class="pc-mono">Content-Type</td><td>Yes</td><td><span class="pc-mono">application/json</span> untuk request JSON.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card pc-card mb-3">
                <div class="card-body">
                    <div class="pc-section-title mb-2 pc-anchor" id="endpoint-summary">3) Endpoint Summary</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                            <tr><th>Group</th><th>Method</th><th>Path</th><th>Purpose</th><th>Type</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($endpointGroups as $group): ?>
                                <?php if (!$showAdminApiEndpoints && $group['title'] === 'Admin API') { continue; } ?>
                                <?php if (!$showKarirhubBridgeApiEndpoints && $group['title'] === 'Karirhub Bridge API') { continue; } ?>
                                <?php foreach ($group['rows'] as $row): ?>
                                    <?php if (!$showEmployerDashboardEndpoint && $row[1] === '/api/wllp/employer/dashboard') { continue; } ?>
                                    <?php if (!$showEmployerBulkEndpoints && in_array($row[1], ['/api/wllp/reports/bulk/validate', '/api/wllp/reports/bulk/commit'], true)) { continue; } ?>
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
                    <div class="pc-section-title mb-2 pc-anchor" id="endpoint-details">4) Endpoint Details</div>

                    <h6 class="mb-2 text-primary">Employer API</h6>

                    <?php if ($showEmployerDashboardEndpoint): ?>
                    <div class="pc-endpoint-card mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div><span class="badge text-bg-success me-2">GET</span><span class="pc-mono">/api/wllp/employer/dashboard</span></div>
                            <span class="pc-small">Employer dashboard metrics</span>
                        </div>
                        <div class="pc-kv mb-2">
                            <div class="k">Purpose</div><div class="v">Ambil ringkasan metrik WLLP milik employer.</div>
                            <div class="k">Query wajib</div><div class="v"><span class="pc-mono">employer_id</span> (integer &gt; 0)</div>
                            <div class="k">Response utama</div><div class="v"><span class="pc-mono">total_reports, total_items, terisi_items, belum_terisi_items</span></div>
                            <div class="k">Error utama</div><div class="v"><span class="pc-mono">422 VALIDATION_FAILED</span> jika employer_id tidak valid.</div>
                            <div class="k">Narrative reference</div><div class="v">Endpoint ini biasanya menjadi request pertama saat halaman dashboard dibuka. Tujuannya memberi konteks cepat terkait kesehatan pelaporan employer sebelum user melakukan aksi lanjutan. Integrator disarankan menyimpan snapshot metrik ini untuk perbandingan antar periode dan menampilkan warning jika metrik belum terisi terlalu tinggi.</div>
                        </div>
<pre class="pc-pre bg-dark text-light rounded p-3">{
  "success": true,
  "data": {
    "total_reports": 10,
    "total_items": 46,
    "terisi_items": 21,
    "belum_terisi_items": 25
  }
}</pre>
                    </div>
                    <?php endif; ?>

                    <div class="pc-endpoint-card mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div><span class="badge text-bg-success me-2">GET</span><span class="pc-mono">/api/wllp/reports</span></div>
                            <span class="pc-small">List Bukti Lapor</span>
                        </div>
                        <div class="pc-kv mb-2">
                            <div class="k">Purpose</div><div class="v">Daftar report WLLP per employer.</div>
                            <div class="k">Query wajib</div><div class="v"><span class="pc-mono">employer_id</span></div>
                            <div class="k">Query opsional</div><div class="v"><span class="pc-mono">limit, offset</span></div>
                            <div class="k">Response utama</div><div class="v"><span class="pc-mono">data[]</span> dan <span class="pc-mono">pagination</span>.</div>
                            <div class="k">Error utama</div><div class="v"><span class="pc-mono">422 VALIDATION_FAILED</span>.</div>
                            <div class="k">Narrative reference</div><div class="v">Gunakan endpoint ini untuk halaman list dan histori bukti lapor.</div>
                        </div>
                    </div>

                    <div class="pc-endpoint-card mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div><span class="badge text-bg-primary me-2">POST</span><span class="pc-mono">/api/wllp/reports</span></div>
                            <span class="pc-small">Create manual WLLP report</span>
                        </div>
                        <div class="pc-kv mb-2">
                            <div class="k">Purpose</div><div class="v">Membuat report WLLP baru.</div>
                            <div class="k">Required body</div><div class="v"><span class="pc-mono">employer_id, unit_id, period_type, period_anchor, terms, items[]</span></div>
                            <div class="k">Validation highlights</div><div class="v"><span class="pc-mono">headcount_needed > 0</span>, <span class="pc-mono">period_type weekly/monthly</span>, <span class="pc-mono">terms.agreed = true</span></div>
                            <div class="k">Narrative reference</div><div class="v">Ini endpoint tulis paling penting pada alur WLLP. Jika request valid, sistem akan membuat nomor bukti dan id lowongan secara otomatis. Saat validasi gagal, object <span class="pc-mono">fields</span> perlu langsung dipetakan ke UI form agar pengguna dapat memperbaiki input tanpa menebak field mana yang salah.</div>
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
      "gender_requirement": "Perempuan",
      "age_min": 21,
      "age_max": 35,
      "education_min_id": "S1",
      "job_description": "Melakukan operasional harian.",
      "skills": "Microsoft Office, komunikasi",
      "experience_min_years": 1,
      "salary_min": 5000000,
      "salary_max": 7000000,
      "kbji_code": "24231",
      "province": "DKI Jakarta",
      "city": "Jakarta",
      "district": "Kebayoran Baru",
      "village": "Senayan",
      "job_field_id": "Akunting",
      "industry_id": "Perbankan",
      "marital_status_requirement": "Single",
      "work_type": "Full Time",
      "valid_from": "2026-06-03",
      "valid_until": "2026-07-03",
      "posting_url": "https://glints.com/id/en/opportunities/jobs/staff-akunting/a252f626-eee7-4874-943a-83963c73e704"
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

                    <?php if ($showEmployerBulkEndpoints): ?>
                    <div class="pc-endpoint-card mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div><span class="badge text-bg-primary me-2">POST</span><span class="pc-mono">/api/wllp/reports/bulk/validate</span></div>
                            <span class="pc-small">Validate bulk payload</span>
                        </div>
                        <div class="pc-kv mb-2">
                            <div class="k">Purpose</div><div class="v">Memvalidasi batch bulk sebelum proses commit.</div>
                            <div class="k">Input</div><div class="v">JSON rows atau metadata upload file <span class="pc-mono">.xlsx</span>.</div>
                            <div class="k">Response utama</div><div class="v"><span class="pc-mono">batch_id, total_rows, valid_rows, invalid_rows, errors[]</span>.</div>
                            <div class="k">Error utama</div><div class="v"><span class="pc-mono">422 BULK_TEMPLATE_INVALID</span>, <span class="pc-mono">422 VALIDATION_FAILED</span>.</div>
                            <div class="k">Narrative reference</div><div class="v">Tahap ini berfungsi sebagai quality gate. Lakukan validate setiap kali file/rows berubah, lalu tampilkan daftar error per baris ke pengguna. Hindari langsung memanggil commit tanpa validasi karena akan menyulitkan tracing jika ada data campuran valid-invalid di batch besar.</div>
                        </div>
<pre class="pc-pre bg-dark text-light rounded p-3">{
  "batch_id": "BULK-20260526153012-431",
  "template_version": "WLLP-BULK-1.0",
  "total_rows": 100,
  "valid_rows": 92,
  "invalid_rows": 8,
  "errors": [{"row": 12, "field": "kbji_code", "message": "Kode KBJI tidak ditemukan."}]
}</pre>
                    </div>

                    <div class="pc-endpoint-card mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div><span class="badge text-bg-primary me-2">POST</span><span class="pc-mono">/api/wllp/reports/bulk/commit</span></div>
                            <span class="pc-small">Commit validated batch</span>
                        </div>
                        <div class="pc-kv mb-2">
                            <div class="k">Purpose</div><div class="v">Memproses batch valid menjadi report dan item WLLP.</div>
                            <div class="k">Body wajib</div><div class="v"><span class="pc-mono">batch_id, terms.agreed, terms.version</span></div>
                            <div class="k">Error utama</div><div class="v"><span class="pc-mono">404 BATCH_NOT_FOUND</span>, <span class="pc-mono">409 BATCH_ALREADY_COMMITTED</span>, <span class="pc-mono">422 TERMS_REQUIRED</span>.</div>
                            <div class="k">Narrative reference</div><div class="v">Endpoint commit harus dianggap non-repeatable untuk batch yang sama. Jika muncul <span class="pc-mono">BATCH_ALREADY_COMMITTED</span>, jangan retry request identik; lakukan proses validate ulang dan hasilkan batch baru. Integrator juga sebaiknya mencatat batch_id final ke log audit internal.</div>
                        </div>
<pre class="pc-pre bg-dark text-light rounded p-3">{
  "batch_id": "BULK-20260526153012-431",
  "terms": {"agreed": true, "version": "WLLP-TC-2026-01"}
}</pre>
                    </div>
                    <?php endif; ?>

                    <div class="pc-endpoint-card mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div><span class="badge text-bg-success me-2">GET</span><span class="pc-mono">/api/wllp/reports/{id}</span></div>
                            <span class="pc-small">Report detail</span>
                        </div>
                        <div class="pc-kv mb-2">
                            <div class="k">Purpose</div><div class="v">Mengambil detail satu report berikut seluruh item di dalamnya.</div>
                            <div class="k">Path wajib</div><div class="v"><span class="pc-mono">id</span> (report id numerik)</div>
                            <div class="k">Response utama</div><div class="v"><span class="pc-mono">report</span> + <span class="pc-mono">items[]</span>.</div>
                            <div class="k">Error utama</div><div class="v"><span class="pc-mono">404 REPORT_NOT_FOUND</span>.</div>
                            <div class="k">Narrative reference</div><div class="v">Endpoint ini cocok untuk halaman detail tunggal karena menyediakan konteks report dan item dalam satu respons. Gunakan ketika user klik nomor bukti dari list. Bila report tidak ditemukan, arahkan user kembali ke list terbaru dan lakukan refresh sinkronisasi data.</div>
                        </div>
                    </div>

                    <div class="pc-endpoint-card mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div><span class="badge text-bg-success me-2">GET</span><span class="pc-mono">/api/wllp/reports/{id}/pdf</span></div>
                            <span class="pc-small">Download Bukti Lapor PDF</span>
                        </div>
                        <div class="pc-kv mb-2">
                            <div class="k">Purpose</div><div class="v">Mengunduh dokumen bukti lapor dalam format PDF.</div>
                            <div class="k">Output</div><div class="v"><span class="pc-mono">Content-Type: application/pdf</span></div>
                            <div class="k">Error utama</div><div class="v"><span class="pc-mono">404 REPORT_NOT_FOUND</span>.</div>
                            <div class="k">Narrative reference</div><div class="v">Karena output berupa file biner, endpoint ini tidak diproses sebagai JSON. Pastikan client mengeksekusi download stream dan menangani nama file dengan benar. Untuk kebutuhan arsip, integrator bisa menyimpan hash dokumen yang diunduh sebagai bukti integritas.</div>
                        </div>
                    </div>

                    <div class="pc-endpoint-card mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div><span class="badge text-bg-success me-2">GET</span><span class="pc-mono">/api/wllp/items/{itemId}/status</span></div>
                            <span class="pc-small">Get item status</span>
                        </div>
                        <div class="pc-kv mb-2">
                            <div class="k">Purpose</div><div class="v">Mengambil status keterisian item lowongan.</div>
                            <div class="k">Path wajib</div><div class="v"><span class="pc-mono">itemId</span></div>
                            <div class="k">Response utama</div><div class="v">Status, note, filled_count, last_reported_at.</div>
                            <div class="k">Error utama</div><div class="v"><span class="pc-mono">404 ITEM_NOT_FOUND</span>.</div>
                            <div class="k">Narrative reference</div><div class="v">Gunakan endpoint ini untuk polling status item tertentu tanpa memuat ulang seluruh report. Cocok pada UI modal atau panel detail. Jika data sangat dinamis, gunakan interval polling moderat agar tidak membebani API.</div>
                        </div>
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
                            <div class="k">Narrative reference</div><div class="v">Batasi nilai status pada dropdown terstruktur agar konsisten antar integrator. Setiap update sebaiknya menyertakan note singkat untuk memudahkan jejak audit dan analisis perubahan status dari waktu ke waktu.</div>
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
                            <div class="k">Narrative reference</div><div class="v">Endpoint ini menandai outcome aktual dari lowongan. Pastikan data personal dimasking di sisi tampilan, dan kirim hanya field yang diperlukan. Jika menerima <span class="pc-mono">PLACEMENT_LIMIT_EXCEEDED</span>, lakukan sinkronisasi ulang item sebelum user menambah placement baru.</div>
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

                    <?php if ($showKarirhubBridgeApiEndpoints): ?>
                    <h6 class="mb-2 text-primary">Karirhub Bridge API</h6>

                    <div class="pc-endpoint-card mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div><span class="badge text-bg-success me-2">GET</span><span class="pc-mono">/api/karirhub/jobs/posted</span></div>
                            <span class="pc-small">List posted jobs</span>
                        </div>
                        <div class="pc-kv mb-2">
                            <div class="k">Purpose</div><div class="v">Mengambil daftar lowongan posted dari bridge Karirhub.</div>
                            <div class="k">Response utama</div><div class="v"><span class="pc-mono">job_id, title, location, status, headcount, posting_url</span></div>
                            <div class="k">Error utama</div><div class="v">Error auth standar jika signature tidak valid.</div>
                            <div class="k">Narrative reference</div><div class="v">Endpoint ini cocok untuk layar pemilihan lowongan sebelum proses add-to-wllp. Integrator dapat menambahkan cache singkat agar pencarian daftar tidak memicu request berulang saat user melakukan navigasi bolak-balik.</div>
                        </div>
                    </div>

                    <div class="pc-endpoint-card mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div><span class="badge text-bg-primary me-2">POST</span><span class="pc-mono">/api/karirhub/jobs/{jobId}/add-to-wllp</span></div>
                            <span class="pc-small">Karirhub bridge</span>
                        </div>
                        <div class="pc-kv mb-2">
                            <div class="k">Purpose</div><div class="v">Menambahkan lowongan dari data Karirhub jobs posted ke report WLLP.</div>
                            <div class="k">Required body</div><div class="v"><span class="pc-mono">employer_id, unit_id, period_type, period_anchor, terms</span></div>
                            <div class="k">Error utama</div><div class="v"><span class="pc-mono">404 JOB_NOT_FOUND</span>, <span class="pc-mono">422 TERMS_REQUIRED</span>.</div>
                            <div class="k">Narrative reference</div><div class="v">Dipakai saat user memilih lowongan existing agar tidak input manual dari awal. Endpoint ini mempercepat onboarding data, tetapi tetap tunduk pada validasi periode dan terms. Simpan pasangan <span class="pc-mono">jobId - id_lowongan</span> untuk kebutuhan rekonsiliasi.</div>
                        </div>
<pre class="pc-pre bg-dark text-light rounded p-3">{
  "success": true,
  "reused_report": true,
  "no_reg_bukti": "WLLP-572606-00000001",
  "id_lowongan": "LK-000002",
  "status_label": "Berhasil ditambahkan ke WLLP"
}</pre>
                    </div>
                    <?php endif; ?>

                    <?php if ($showAdminApiEndpoints): ?>
                    <h6 class="mb-2 text-primary">Admin API</h6>

                    <div class="pc-endpoint-card mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div><span class="badge text-bg-success me-2">GET</span><span class="pc-mono">/api/admin/wllp/dashboard</span></div>
                            <span class="pc-small">Admin analytics summary</span>
                        </div>
                        <div class="pc-kv mb-2">
                            <div class="k">Purpose</div><div class="v">Ringkasan analitik global lintas employer.</div>
                            <div class="k">Response utama</div><div class="v"><span class="pc-mono">total_employers, total_reports, total_items, submitted_reports, verified_reports</span>.</div>
                            <div class="k">Narrative reference</div><div class="v">Endpoint ini mendukung monitoring operasional harian tim admin. Umumnya dipakai pada kartu KPI di awal dashboard. Untuk akurasi pelaporan berkala, bandingkan hasilnya dengan export CSV pada periode yang sama.</div>
                        </div>
                    </div>

                    <div class="pc-endpoint-card mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div><span class="badge text-bg-success me-2">GET</span><span class="pc-mono">/api/admin/wllp/reports</span></div>
                            <span class="pc-small">Cross-employer report list</span>
                        </div>
                        <div class="pc-kv mb-2">
                            <div class="k">Purpose</div><div class="v">Daftar seluruh report lintas employer untuk admin.</div>
                            <div class="k">Query opsional</div><div class="v"><span class="pc-mono">limit, offset</span></div>
                            <div class="k">Response utama</div><div class="v"><span class="pc-mono">data[]</span> berisi metadata report.</div>
                            <div class="k">Narrative reference</div><div class="v">Gunakan endpoint ini untuk inspeksi lintas tenant dan tindak lanjut manual. Saat volume tinggi, kombinasikan pagination dan filter UI agar query ringan. Integrator sebaiknya menambahkan fitur search lokal setelah data halaman diterima.</div>
                        </div>
                    </div>

                    <div class="pc-endpoint-card mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div><span class="badge text-bg-success me-2">GET</span><span class="pc-mono">/api/admin/wllp/compliance</span></div>
                            <span class="pc-small">Compliance overview</span>
                        </div>
                        <div class="pc-kv mb-2">
                            <div class="k">Purpose</div><div class="v">Analisis kepatuhan report per employer.</div>
                            <div class="k">Response utama</div><div class="v"><span class="pc-mono">reports, total_items, terisi_items, compliance_pct</span>.</div>
                            <div class="k">Narrative reference</div><div class="v">Endpoint ini ideal sebagai sumber ranking atau heatmap kepatuhan. Integrator dapat menambahkan threshold visual (hijau/kuning/merah) untuk mempercepat identifikasi employer yang memerlukan intervensi.</div>
                        </div>
                    </div>

                    <div class="pc-endpoint-card mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div><span class="badge text-bg-warning me-2">PUT</span><span class="pc-mono">/api/admin/wllp/reports/{id}/verification</span></div>
                            <span class="pc-small">Admin verification flow</span>
                        </div>
                        <div class="pc-kv mb-2">
                            <div class="k">Allowed status</div><div class="v"><span class="pc-mono">verified</span>, <span class="pc-mono">rejected</span>, <span class="pc-mono">needs_update</span></div>
                            <div class="k">Audit</div><div class="v">Perubahan status tercatat pada verification log dan audit log.</div>
                            <div class="k">Error utama</div><div class="v"><span class="pc-mono">404 REPORT_NOT_FOUND</span>, <span class="pc-mono">422 VALIDATION_FAILED</span>.</div>
                            <div class="k">Narrative reference</div><div class="v">Endpoint governance ini menentukan keputusan final report. Wajibkan pengisian note ketika status berubah untuk menjaga transparansi keputusan. Jika terjadi dispute, note verifikasi menjadi referensi utama proses klarifikasi.</div>
                        </div>
<pre class="pc-pre bg-dark text-light rounded p-3">{
  "status": "verified",
  "note": "Dokumen valid dan lengkap"
}</pre>
                    </div>

                    <div class="pc-endpoint-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div><span class="badge text-bg-success me-2">GET</span><span class="pc-mono">/api/admin/wllp/export</span></div>
                            <span class="pc-small">CSV export</span>
                        </div>
                        <div class="pc-kv mb-2">
                            <div class="k">Purpose</div><div class="v">Ekspor data report untuk analitik lanjutan admin.</div>
                            <div class="k">Output</div><div class="v"><span class="pc-mono">Content-Type: text/csv</span>, file <span class="pc-mono">wllp-admin-export.csv</span>.</div>
                            <div class="k">Catatan</div><div class="v">Gunakan endpoint ini untuk kebutuhan rekonsiliasi data offline atau pelaporan periodik.</div>
                            <div class="k">Narrative reference</div><div class="v">Disarankan dijalankan pada jam non-peak karena ukuran file bisa besar. Untuk laporan periodik, simpan hasil export dengan penamaan timestamp agar histori data dapat dilacak. Jika download terputus, ulangi dengan Request-Id baru.</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card pc-card mb-3">
                <div class="card-body">
                    <div class="pc-section-title mb-2 pc-anchor" id="errors">5) Error Catalog</div>
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
                    <div class="pc-section-title mb-2 pc-anchor" id="ops">6) Operational Guidance</div>
                    <ul class="mb-0">
                        <li><strong>Retry policy:</strong> retry hanya untuk 5xx/timeout, tidak untuk 4xx validasi.</li>
                        <li><strong>Backoff:</strong> disarankan exponential backoff (1s, 2s, 4s, 8s; max 30s).</li>
                    </ul>
                </div>
            </div>

            <div class="card pc-card">
                <div class="card-body">
                    <div class="pc-section-title mb-2 pc-anchor" id="changelog">7) Changelog & Versioning</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr><th>Version</th><th>Date</th><th>Changes</th><th>Compatibility</th></tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="badge text-bg-primary">v1.0</span></td>
                                    <td><?php echo h(date('Y-m-d')); ?></td>
                                    <td>Initial release: employer API endpoints, signature auth, logging.</td>
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

