<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

$stats = [
    ['Klien aktif', (int)(jv3_one($conn, "SELECT COUNT(*) n FROM joss_v3_proto_clients WHERE status='active'")['n'] ?? 0), 'bi-diagram-3'],
    ['Definisi DCF aktif', (int)(jv3_one($conn, "SELECT COUNT(*) n FROM joss_v3_proto_field_definitions WHERE status='active'")['n'] ?? 0), 'bi-ui-checks-grid'],
    ['Menunggu verifikasi', (int)(jv3_one($conn, "SELECT COUNT(*) n FROM joss_v3_proto_vacancies WHERE verification_status='pending'")['n'] ?? 0), 'bi-hourglass-split'],
    ['Skema API 2 terbit', (int)(jv3_one($conn, "SELECT COUNT(*) n FROM joss_v3_proto_schemas WHERE status='published'")['n'] ?? 0), 'bi-card-checklist'],
    ['Lamaran tersimpan', (int)(jv3_one($conn, "SELECT COUNT(*) n FROM joss_v3_proto_applications")['n'] ?? 0), 'bi-person-check'],
    ['Delivery perlu aksi', (int)(jv3_one($conn, "SELECT COUNT(*) n FROM joss_v3_proto_deliveries WHERE status IN ('pending','failed','held')")['n'] ?? 0), 'bi-send-exclamation'],
];
$clients = jv3_all($conn, "SELECT * FROM joss_v3_proto_clients ORDER BY client_name");
$activity = jv3_all($conn, "SELECT * FROM joss_v3_proto_audit ORDER BY id DESC LIMIT 6");

jv3_render_header('Overview', 'dashboard');
?>
<div class="row g-3 mb-3">
    <?php foreach ($stats as [$label,$value,$icon]): ?>
    <div class="col-6 col-lg-4 col-xl-2"><div class="jv3-card jv3-kpi">
        <div class="d-flex justify-content-between"><span class="jv3-kpi-label"><?php echo jv3_h($label); ?></span><i class="bi <?php echo jv3_h($icon); ?> text-success"></i></div>
        <div class="jv3-kpi-value"><?php echo $value; ?></div>
    </div></div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-xl-7"><section class="jv3-card h-100">
        <div class="jv3-card-header"><h2 class="h6 mb-1">End-to-end JOSS V3.0</h2><div class="jv3-muted">Alur ini memisahkan persyaratan lowongan API 1 dari data pribadi lamaran API 2.</div></div>
        <div class="jv3-card-body">
            <?php
            $steps = [
                ['1. Otorisasi klien','Aktifkan izin kirim DCF, terima DCF, dan data lamaran dinamis secara independen.','clients'],
                ['2. Definisi dan nilai DCF','Buat versi field, pilih untuk lowongan, lalu simpan nilai plain text secara atomik.','fields'],
                ['3. Verifikasi dan routing','Nilai relevansi persyaratan dan periksa capability setiap tujuan.','verification'],
                ['4. Konfigurasi API 2','Publikasikan skema variabel profil dan input manual per Client-Id.','application_schema'],
                ['5. Persetujuan pencari kerja','Render form dari skema aktif dan simpan snapshot data, penerima, serta consent.','apply'],
                ['6. Webhook dan retry','Kirim snapshot tetap dengan event idempotent; tahan jika izin dicabut.','delivery'],
            ];
            foreach ($steps as [$title,$text,$href]): ?>
                <div class="jv3-step"><a class="fw-bold text-decoration-none" href="<?php echo $href; ?>"><?php echo jv3_h($title); ?></a><div class="jv3-muted mt-1"><?php echo jv3_h($text); ?></div></div>
            <?php endforeach; ?>
        </div>
    </section></div>
    <div class="col-xl-5">
        <section class="jv3-card mb-3">
            <div class="jv3-card-header"><h2 class="h6 mb-0">Capability matrix</h2></div>
            <div class="table-responsive"><table class="table mb-0 align-middle">
                <thead><tr><th>Client</th><th>Kirim</th><th>Terima</th><th>API 2</th><th>Status</th></tr></thead>
                <tbody><?php foreach ($clients as $client): ?><tr>
                    <td><strong><?php echo jv3_h($client['client_name']); ?></strong><div class="small text-muted"><?php echo jv3_h($client['client_id']); ?></div></td>
                    <td><?php echo $client['allow_dcf_write'] ? '✓' : '—'; ?></td><td><?php echo $client['allow_dcf_read'] ? '✓' : '—'; ?></td><td><?php echo $client['allow_dynamic_apply'] ? '✓' : '—'; ?></td>
                    <td><?php echo jv3_status_badge($client['status']); ?></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
        </section>
        <section class="jv3-card">
            <div class="jv3-card-header"><h2 class="h6 mb-0">Audit terbaru</h2></div>
            <div class="list-group list-group-flush">
                <?php if (!$activity): ?><div class="p-3 jv3-muted">Interaksi prototype akan muncul di sini.</div><?php endif; ?>
                <?php foreach ($activity as $event): ?><div class="list-group-item py-3">
                    <div class="fw-semibold"><?php echo jv3_h($event['action']); ?></div>
                    <div class="small text-muted"><?php echo jv3_h($event['entity_type'] . ' #' . $event['entity_id']); ?> · <?php echo jv3_h($event['created_at']); ?></div>
                </div><?php endforeach; ?>
            </div>
        </section>
    </div>
</div>
<?php jv3_render_footer(); ?>
