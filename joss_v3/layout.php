<?php

function jv3_render_header(string $title, string $active): void
{
    $items = [
        'dashboard' => ['Overview','bi-grid-1x2'],
        'clients' => ['Client Permissions','bi-shield-lock'],
        'fields' => ['DCF & Vacancies','bi-ui-checks-grid'],
        'verification' => ['Verification & Routing','bi-patch-check'],
        'application_schema' => ['Application Schema','bi-card-checklist'],
        'apply' => ['Apply Simulation','bi-person-check'],
        'delivery' => ['Delivery Monitor','bi-send-check'],
    ];
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo jv3_h($title); ?> - JOSS V3.0 Prototype</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --jv3-navy:#123b5d; --jv3-teal:#078b88; --jv3-bg:#f3f7fb; }
        body { background:var(--jv3-bg); color:#263f57; }
        .jv3-hero { background:linear-gradient(120deg,#103c60,#0b7478); color:#fff; padding:24px 0 52px; }
        .jv3-eyebrow { text-transform:uppercase; letter-spacing:.12em; font-size:11px; opacity:.75; font-weight:700; }
        .jv3-shell { margin-top:-32px; margin-bottom:40px; }
        .jv3-nav { background:#fff; border:1px solid #dce6ef; border-radius:14px; padding:8px; box-shadow:0 10px 28px rgba(23,57,83,.08); overflow-x:auto; display:flex; gap:4px; }
        .jv3-nav a { display:flex; align-items:center; gap:7px; color:#60778d; text-decoration:none; padding:9px 12px; border-radius:9px; white-space:nowrap; font-size:13px; font-weight:600; }
        .jv3-nav a.active,.jv3-nav a:hover { color:#057d7a; background:#eaf8f7; }
        .jv3-card { background:#fff; border:1px solid #dce6ef; border-radius:13px; box-shadow:0 3px 14px rgba(23,57,83,.04); }
        .jv3-card-header { padding:16px 18px; border-bottom:1px solid #e7eef5; }
        .jv3-card-body { padding:18px; }
        .jv3-kpi { height:100%; padding:17px; }
        .jv3-kpi-label { color:#75899c; font-size:12px; font-weight:700; text-transform:uppercase; }
        .jv3-kpi-value { font-size:28px; font-weight:750; color:#193c59; margin-top:8px; }
        .jv3-badge { border-radius:999px; padding:4px 9px; font-size:11px; font-weight:700; }
        .jv3-code { background:#102638; color:#d8ecf4; padding:14px; border-radius:9px; font:12px/1.55 Consolas,monospace; white-space:pre-wrap; max-height:420px; overflow:auto; }
        .jv3-step { border-left:3px solid #1a9993; padding:4px 0 18px 16px; }
        .jv3-step:last-child { padding-bottom:0; }
        .jv3-muted { color:#74899d; font-size:13px; }
        .table > :not(caption) > * > * { padding:.75rem; }
        .table thead th { background:#f5f9fc; color:#526b81; font-size:12px; white-space:nowrap; }
        .form-label { color:#526b81; font-size:12px; font-weight:700; }
        .btn-primary { background:#087f7c; border-color:#087f7c; }
        .btn-primary:hover { background:#066e6b; border-color:#066e6b; }
    </style>
</head>
<body>
<?php include dirname(__DIR__) . '/navbar.php'; ?>
<section class="jv3-hero">
    <div class="container-fluid px-3 px-lg-4">
        <div class="jv3-eyebrow">Karirhub Prototype / JOSS V3.0</div>
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mt-2">
            <div><h1 class="h3 mb-1"><?php echo jv3_h($title); ?></h1><div class="opacity-75">Dynamic Custom Fields &amp; Dynamic Application Data</div></div>
            <span class="badge rounded-pill text-bg-light">FSD v0.2 · Database-backed prototype</span>
        </div>
    </div>
</section>
<main class="container-fluid px-3 px-lg-4 jv3-shell">
    <nav class="jv3-nav mb-3">
        <?php foreach ($items as $key => [$label,$icon]): ?>
            <a class="<?php echo $active === $key ? 'active' : ''; ?>" href="<?php echo jv3_h($key); ?>"><i class="bi <?php echo jv3_h($icon); ?>"></i><?php echo jv3_h($label); ?></a>
        <?php endforeach; ?>
    </nav>
    <?php if (!empty($_SESSION['jv3_flash'])): $flash = $_SESSION['jv3_flash']; unset($_SESSION['jv3_flash']); ?>
        <div class="alert alert-<?php echo jv3_h($flash['tone']); ?> alert-dismissible fade show"><i class="bi bi-info-circle me-1"></i><?php echo jv3_h($flash['message']); ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php
}

function jv3_render_footer(): void
{
    ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
    <?php
}

function jv3_status_badge(string $status): string
{
    $tone = match ($status) {
        'active','approved','published','success','sent','ready','delivered' => 'success',
        'pending','draft','revision','standard_only' => 'warning',
        'failed','rejected','blocked' => 'danger',
        'held','inactive','retired' => 'secondary',
        default => 'light',
    };
    return '<span class="badge text-bg-' . $tone . ' jv3-badge">' . jv3_h(strtoupper(str_replace('_',' ', $status))) . '</span>';
}
