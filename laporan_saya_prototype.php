<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

if (!current_user_can('karirhub_employer_prototype_lapor_loker_view') && !current_user_can('manage_settings')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$myReports = [
    [
        'report_id' => 'VRP-2026-304511',
        'objek' => 'Loker',
        'nama_objek' => 'Sales Executive - DKI Jakarta',
        'tanggal' => '13 Aug 2026 10:20',
        'status_ringkas' => 'Sedang Ditinjau',
        'status_key' => 'review',
        'perlu_respons' => false,
        'pertanyaan' => '',
    ],
    [
        'report_id' => 'CRP-2026-103421',
        'objek' => 'Perusahaan',
        'nama_objek' => 'PT Finaccel Finance Indonesia',
        'tanggal' => '13 Aug 2026 09:14',
        'status_ringkas' => 'Menunggu Informasi dari Anda',
        'status_key' => 'waiting',
        'perlu_respons' => true,
        'pertanyaan' => 'Mohon kirim bukti tambahan terkait permintaan biaya yang Anda sebutkan (jika ada percakapan/email pendukung).',
    ],
    [
        'report_id' => 'VRP-2026-301992',
        'objek' => 'Loker',
        'nama_objek' => 'Kasir',
        'tanggal' => '10 Aug 2026 11:03',
        'status_ringkas' => 'Selesai',
        'status_key' => 'done',
        'perlu_respons' => false,
        'pertanyaan' => '',
    ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Saya Prototype</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f4f7fb; }
        .lsp-shell { background: #fff; border: 1px solid #dce6f1; border-radius: 12px; padding: 20px; }
        .lsp-title { font-size: 25px; font-weight: 700; color: #1f3550; margin-bottom: 2px; }
        .lsp-sub { color: #60778f; font-size: 14px; }
        .lsp-badge { border-radius: 999px; padding: 4px 10px; font-size: 12px; font-weight: 600; }
        .lsp-badge.review { background: #e8f8ff; color: #1f6f95; }
        .lsp-badge.waiting { background: #fff4dd; color: #8f6319; }
        .lsp-badge.done { background: #eaf8ed; color: #1d763c; }
        .lsp-table thead th { background: #f5f9fd; color: #324a63; font-weight: 600; white-space: nowrap; }
        .lsp-table td { vertical-align: middle; color: #2b455f; font-size: 13px; }
        .lsp-response-card { border: 1px solid #dce8f7; background: #f6f9ff; border-radius: 10px; padding: 12px; margin-top: 8px; }
        .lsp-response-title { font-weight: 700; color: #2b455f; margin-bottom: 6px; font-size: 14px; }
        .lsp-response-text { color: #355271; font-size: 13px; margin-bottom: 10px; }
        .lsp-feedback { font-size: 12px; margin-top: 6px; display: none; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container py-4">
    <div class="lsp-shell">
        <div class="d-flex justify-content-between align-items-start mb-3 gap-2 flex-wrap">
            <div>
                <div class="lsp-title">Laporan Saya Prototype</div>
                <div class="lsp-sub">Status ringkas laporan sesuai konsep FSD: Diterima, Sedang Ditinjau, Menunggu Informasi dari Anda, Selesai.</div>
            </div>
            <a href="admin_review_laporan_prototype" class="btn btn-outline-primary btn-sm"><i class="bi bi-shield-check me-1"></i>Buka Admin Review Prototype</a>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-hover lsp-table align-middle">
                <thead>
                    <tr>
                        <th>Report ID</th>
                        <th>Objek</th>
                        <th>Nama Objek</th>
                        <th>Tanggal Laporan</th>
                        <th>Status Ringkas</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($myReports as $index => $row): ?>
                    <tr>
                        <td><?php echo h($row['report_id']); ?></td>
                        <td><?php echo h($row['objek']); ?></td>
                        <td><?php echo h($row['nama_objek']); ?></td>
                        <td><?php echo h($row['tanggal']); ?></td>
                        <td><span class="lsp-badge <?php echo h($row['status_key']); ?>"><?php echo h($row['status_ringkas']); ?></span></td>
                        <td>
                            <?php if ($row['perlu_respons']): ?>
                                <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#responseRow<?php echo $index; ?>">
                                    Respon Permintaan Info
                                </button>
                            <?php else: ?>
                                <span class="text-muted small">Tidak ada aksi</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($row['perlu_respons']): ?>
                        <tr class="collapse" id="responseRow<?php echo $index; ?>">
                            <td colspan="6">
                                <div class="lsp-response-card">
                                    <div class="lsp-response-title">Permintaan Informasi dari Admin</div>
                                    <div class="lsp-response-text"><?php echo h($row['pertanyaan']); ?></div>
                                    <div class="row g-2">
                                        <div class="col-12 col-lg-7">
                                            <textarea class="form-control form-control-sm js-response-text" rows="3" placeholder="Tulis jawaban atau klarifikasi Anda..."></textarea>
                                        </div>
                                        <div class="col-12 col-lg-3">
                                            <input type="file" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-12 col-lg-2 d-grid">
                                            <button type="button" class="btn btn-sm btn-success js-send-response">Kirim Respons</button>
                                        </div>
                                    </div>
                                    <div class="lsp-feedback text-success">Respons prototype berhasil dikirim.</div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function () {
        const sendButtons = document.querySelectorAll('.js-send-response');
        sendButtons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const card = btn.closest('.lsp-response-card');
                if (!card) return;
                const text = card.querySelector('.js-response-text');
                const feedback = card.querySelector('.lsp-feedback');
                if (!text || !feedback) return;
                if (text.value.trim() === '') {
                    feedback.textContent = 'Isi jawaban terlebih dahulu sebelum kirim.';
                    feedback.classList.remove('text-success');
                    feedback.classList.add('text-danger');
                    feedback.style.display = 'block';
                    return;
                }
                feedback.textContent = 'Respons prototype berhasil dikirim.';
                feedback.classList.remove('text-danger');
                feedback.classList.add('text-success');
                feedback.style.display = 'block';
            });
        });
    })();
</script>
</body>
</html>
