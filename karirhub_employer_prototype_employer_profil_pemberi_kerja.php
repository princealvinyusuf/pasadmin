<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_ui.php';

if (!kh_proto_can_access('karirhub_employer_prototype_employer_profil_pemberi_kerja_view')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$employer = [
    'type_label' => 'PERUSAHAAN',
    'name' => 'PT. Pandu Jaya',
    'email' => 'pandu@gmail.com',
    'phone' => '0216645828',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Employer - Profil Pemberi Kerja</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php kh_proto_render_styles(); ?>
    <style>
        body.kh-proto-page { background: #f7f9fc; }
        .epp-shell { background: #fff; border: 1px solid #e6edf5; border-radius: 14px; padding: 28px 28px 32px; }
        .epp-title { margin: 0 0 20px; font-size: 28px; font-weight: 700; color: #1f2937; line-height: 1.25; }
        .epp-card {
            background: #eaf7ee;
            border-radius: 16px;
            padding: 20px 22px 22px;
        }
        .epp-verified {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 18px;
        }
        .epp-verified-icon {
            width: 28px;
            height: 28px;
            border-radius: 999px;
            border: 2px solid #22a06b;
            color: #22a06b;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 2px;
            font-size: 14px;
            font-weight: 700;
        }
        .epp-verified-title {
            margin: 0 0 4px;
            color: #1f7a4d;
            font-size: 16px;
            font-weight: 700;
            line-height: 1.3;
        }
        .epp-verified-text {
            margin: 0;
            color: #2f6b4f;
            font-size: 14px;
            line-height: 1.45;
        }
        .epp-profile {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .epp-logo {
            width: 72px;
            height: 72px;
            border-radius: 12px;
            background: #fff;
            border: 1px solid #d9e4ef;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: #3b82f6;
            font-size: 30px;
        }
        .epp-type {
            margin: 0 0 4px;
            color: #6b8799;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .epp-name {
            margin: 0 0 10px;
            color: #111827;
            font-size: 22px;
            font-weight: 700;
            line-height: 1.25;
        }
        .epp-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .epp-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #fff;
            border: 1px solid #d7e2ee;
            border-radius: 999px;
            padding: 7px 14px;
            color: #4b5563;
            font-size: 13px;
            line-height: 1.2;
        }
        .epp-pill i { color: #6b7280; font-size: 14px; }
        @media (max-width: 575px) {
            .epp-shell { padding: 20px 16px 24px; }
            .epp-title { font-size: 24px; }
            .epp-profile { align-items: flex-start; }
            .epp-name { font-size: 20px; }
        }
    </style>
</head>
<body class="kh-proto-page">
<?php include 'navbar.php'; ?>

<div class="kh-content-wrap">
    <div class="container py-4">
        <div class="epp-shell">
            <h1 class="epp-title">Profil Pemberi Kerja</h1>

            <div class="epp-card">
                <div class="epp-verified">
                    <span class="epp-verified-icon" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                    <div>
                        <h2 class="epp-verified-title">Pemberi Kerja Terverifikasi</h2>
                        <p class="epp-verified-text">Selamat! Pemberi kerja Anda telah berhasil diverifikasi dan kini dapat menggunakan seluruh fitur yang tersedia.</p>
                    </div>
                </div>

                <div class="epp-profile">
                    <div class="epp-logo" aria-hidden="true">
                        <i class="bi bi-buildings"></i>
                    </div>
                    <div>
                        <p class="epp-type"><?php echo h($employer['type_label']); ?></p>
                        <h3 class="epp-name"><?php echo h($employer['name']); ?></h3>
                        <div class="epp-pills">
                            <span class="epp-pill"><i class="bi bi-envelope"></i><?php echo h($employer['email']); ?></span>
                            <span class="epp-pill"><i class="bi bi-telephone"></i><?php echo h($employer['phone']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
