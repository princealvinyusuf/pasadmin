<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    jv3_verify_csrf();
    $deliveryId = intval($_POST['delivery_id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    $stmt = $conn->prepare("SELECT d.*,a.client_id,a.id application_pk,c.allow_dynamic_apply,c.status client_status
        FROM joss_v3_proto_deliveries d
        JOIN joss_v3_proto_applications a ON a.id=d.application_id
        JOIN joss_v3_proto_clients c ON c.client_id=a.client_id
        WHERE d.id=? LIMIT 1");
    $stmt->bind_param('i', $deliveryId);
    $stmt->execute();
    $delivery = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$delivery) {
        jv3_flash('danger', 'Event delivery tidak ditemukan.');
        jv3_redirect('delivery');
    }
    $conn->begin_transaction();
    try {
        if (!$delivery['allow_dynamic_apply'] || $delivery['client_status'] !== 'active') {
            $status = 'held';
            $error = 'APPLY_CLIENT_FORBIDDEN';
            $message = 'Izin penerima dicabut; event ditahan dan tidak dialihkan.';
        } elseif ($action === 'simulate_success') {
            $status = 'success';
            $error = null;
            $message = 'Webhook berhasil (simulasi HTTP 200).';
        } elseif ($action === 'simulate_failure') {
            $status = 'failed';
            $error = 'UPSTREAM_TIMEOUT';
            $message = 'Webhook gagal sementara; snapshot tetap tersedia untuk retry.';
        } else {
            $status = 'pending';
            $error = null;
            $message = 'Retry dijadwalkan dengan event ID, penerima, dan payload yang sama.';
        }
        $stmt = $conn->prepare("UPDATE joss_v3_proto_deliveries SET status=?,attempt_count=attempt_count+1,last_error_code=? WHERE id=?");
        $stmt->bind_param('ssi', $status, $error, $deliveryId);
        $stmt->execute();
        $stmt->close();
        if ($status === 'success') {
            $stmt = $conn->prepare("UPDATE joss_v3_proto_applications SET status='delivered' WHERE id=?");
            $stmt->bind_param('i', $delivery['application_pk']);
            $stmt->execute();
            $stmt->close();
        } elseif ($status === 'held') {
            $stmt = $conn->prepare("UPDATE joss_v3_proto_applications SET status='held' WHERE id=?");
            $stmt->bind_param('i', $delivery['application_pk']);
            $stmt->execute();
            $stmt->close();
        }
        jv3_audit($conn, 'delivery.' . $status, 'delivery_event', $deliveryId, ['event_id'=>$delivery['event_id'],'error'=>$error]);
        $conn->commit();
        jv3_flash($status === 'failed' ? 'warning' : ($status === 'held' ? 'danger' : 'success'), $message);
    } catch (Throwable $e) {
        $conn->rollback();
        jv3_flash('danger', $e->getMessage());
    }
    jv3_redirect('delivery?application=' . $delivery['application_id']);
}

$applications = jv3_all($conn, "SELECT a.*,v.title,d.id delivery_id,d.event_id,d.status delivery_status,d.attempt_count,d.last_error_code
    FROM joss_v3_proto_applications a
    JOIN joss_v3_proto_vacancies v ON v.id=a.vacancy_id
    JOIN joss_v3_proto_deliveries d ON d.application_id=a.id
    ORDER BY a.created_at DESC");
$selectedApplicationId = intval($_GET['application'] ?? ($applications[0]['id'] ?? 0));
$selected = null;
foreach ($applications as $application) if ((int)$application['id'] === $selectedApplicationId) $selected = $application;
$audit = jv3_all($conn, "SELECT action,entity_type,entity_id,created_at FROM joss_v3_proto_audit ORDER BY id DESC LIMIT 12");

jv3_render_header('Delivery Monitor', 'delivery');
?>
<div class="row g-3 mb-3">
    <div class="col-lg-5"><section class="jv3-card h-100"><div class="jv3-card-header"><h2 class="h6 mb-1">Application outbox</h2><div class="jv3-muted">Event idempotent memakai snapshot persetujuan yang sama pada setiap retry.</div></div><div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><th>Lamaran</th><th>Status</th><th>Attempt</th><th></th></tr></thead><tbody>
        <?php foreach ($applications as $application): ?><tr><td><strong><?php echo jv3_h($application['application_code']); ?></strong><div class="small text-muted"><?php echo jv3_h($application['title'].' · '.$application['client_id']); ?></div></td><td><?php echo jv3_status_badge($application['delivery_status']); ?></td><td><?php echo (int)$application['attempt_count']; ?></td><td><a class="btn btn-sm btn-outline-primary" href="?application=<?php echo (int)$application['id']; ?>">Detail</a></td></tr><?php endforeach; ?>
        <?php if (!$applications): ?><tr><td colspan="4" class="text-muted">Belum ada lamaran. Gunakan Apply Simulation terlebih dahulu.</td></tr><?php endif; ?>
    </tbody></table></div></section></div>
    <div class="col-lg-7"><?php if ($selected): ?><section class="jv3-card h-100">
        <div class="jv3-card-header d-flex justify-content-between"><div><h2 class="h6 mb-1"><?php echo jv3_h($selected['event_id']); ?></h2><div class="jv3-muted">Recipient snapshot: <?php echo jv3_h($selected['recipient_snapshot']); ?></div></div><?php echo jv3_status_badge($selected['delivery_status']); ?></div>
        <div class="jv3-card-body"><div class="d-flex gap-2 flex-wrap mb-3">
            <?php foreach (['simulate_success'=>['success','Kirim: HTTP 200'],'simulate_failure'=>['outline-danger','Simulasi timeout'],'retry'=>['outline-primary','Retry snapshot']] as $action=>$meta): ?><form method="post"><input type="hidden" name="csrf_token" value="<?php echo jv3_h(jv3_csrf_token()); ?>"><input type="hidden" name="delivery_id" value="<?php echo (int)$selected['delivery_id']; ?>"><input type="hidden" name="action" value="<?php echo $action; ?>"><button class="btn btn-<?php echo $meta[0]; ?>"><?php echo $meta[1]; ?></button></form><?php endforeach; ?>
        </div><div class="small text-muted mb-2">POST new-job-application · Signature dibuat dari body final di backend</div><pre class="jv3-code"><?php echo jv3_h($selected['payload_json']); ?></pre><?php if ($selected['last_error_code']): ?><div class="alert alert-warning mt-3 mb-0"><strong>Last error:</strong> <?php echo jv3_h($selected['last_error_code']); ?></div><?php endif; ?></div>
    </section><?php else: ?><section class="jv3-card jv3-card-body h-100"><h2 class="h5">Outbox kosong</h2><p class="text-muted mb-0">Submit lamaran dari Apply Simulation untuk membentuk snapshot dan webhook.</p></section><?php endif; ?></div>
</div>
<section class="jv3-card"><div class="jv3-card-header"><h2 class="h6 mb-1">Redacted audit trail</h2><div class="jv3-muted">Audit operasional tidak menampilkan CV, email, telepon, nilai consent, atau secret.</div></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Waktu</th><th>Aksi</th><th>Entitas</th><th>ID</th></tr></thead><tbody><?php foreach ($audit as $event): ?><tr><td><?php echo jv3_h($event['created_at']); ?></td><td><code><?php echo jv3_h($event['action']); ?></code></td><td><?php echo jv3_h($event['entity_type']); ?></td><td><?php echo jv3_h($event['entity_id']); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php jv3_render_footer(); ?>
