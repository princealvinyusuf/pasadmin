<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_data.php';
require_once __DIR__ . '/karirhub_employer_prototype_storage.php';
require_once __DIR__ . '/karirhub_employer_prototype_ui.php';
require_once __DIR__ . '/db.php';

if (!kh_proto_can_access('karirhub_employer_prototype_dashboard_wllp_view')) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$dataset = karirhub_proto_dataset();
$units = $dataset['units'];
kh_proto_ensure_multi_tables($conn);
kh_proto_seed_multi_from_dataset($conn, $dataset, $units);

$vacancies = [];
$recentActivities = [];
$resItems = $conn->query("
    SELECT
        d.no_reg_bukti,
        d.id_lowongan,
        d.unit_kode,
        d.jabatan,
        d.masa_berlaku_mulai AS tanggal_lapor,
        d.masa_berlaku_sampai,
        d.status_verifikasi,
        COALESCE(s.status_saat_ini, 'Belum Terisi') AS status_keterisian
    FROM karirhub_proto_wllp_pelaporan d
    LEFT JOIN karirhub_proto_wllp_status s ON s.no_reg_bukti = d.no_reg_bukti AND s.id_lowongan = d.id_lowongan
    ORDER BY d.created_at DESC, d.no_reg_bukti DESC
");
if ($resItems) {
    while ($row = $resItems->fetch_assoc()) {
        $vacancies[] = $row;
    }
}

$resActivities = $conn->query("
    SELECT
        CONCAT(DATE_FORMAT(h.created_at, '%d %M %Y'), ' ', DATE_FORMAT(h.created_at, '%H:%i')) AS waktu,
        'Buat Laporan Lowongan' AS aksi,
        h.no_reg_bukti,
        h.status_verifikasi AS status
    FROM karirhub_proto_wllp_laporan h
    ORDER BY h.created_at DESC
    LIMIT 10
");
if ($resActivities) {
    while ($row = $resActivities->fetch_assoc()) {
        $recentActivities[] = $row;
    }
}

$metrics = karirhub_proto_dashboard_metrics($vacancies);

$summaryCards = [
    [
        'label' => 'Lowongan Dilaporkan',
        'value' => (string)$metrics['total_dilaporkan'],
        'tone' => 'blue',
        'icon' => 'bi-briefcase-fill',
        'copy' => 'Total lowongan dalam WLLP',
    ],
    [
        'label' => 'Lowongan Aktif',
        'value' => (string)$metrics['lowongan_aktif'],
        'tone' => 'cyan',
        'icon' => 'bi-broadcast-pin',
        'copy' => 'Masih dalam masa berlaku',
    ],
    [
        'label' => 'Sudah Terisi',
        'value' => (string)$metrics['sudah_terisi'],
        'tone' => 'green',
        'icon' => 'bi-person-check-fill',
        'copy' => 'Kebutuhan telah terpenuhi',
    ],
    [
        'label' => 'Belum Terisi',
        'value' => (string)$metrics['perlu_update'],
        'tone' => 'orange',
        'icon' => 'bi-hourglass-split',
        'copy' => 'Perlu pemantauan lanjutan',
    ],
];

$totalReported = max(0, (int)$metrics['total_dilaporkan']);
$filledPercentage = $totalReported > 0 ? min(100, (int)round(((int)$metrics['sudah_terisi'] / $totalReported) * 100)) : 0;
$activePercentage = $totalReported > 0 ? min(100, (int)round(((int)$metrics['lowongan_aktif'] / $totalReported) * 100)) : 0;
$unfilledPercentage = $totalReported > 0 ? min(100, (int)round(((int)$metrics['perlu_update'] / $totalReported) * 100)) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karirhub Employer Prototype - Dashboard WLLP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php kh_proto_render_styles(); ?>
    <style>
        .kh-wllp-dashboard {
            --wllp-ink: #172b4d;
            --wllp-muted: #667b91;
            --wllp-line: #dfe8f2;
            --wllp-blue: #155eef;
            --wllp-green: #0b8f69;
        }
        .kh-wllp-dashboard .kh-proto-main {
            color: var(--wllp-ink);
        }
        .wllp-welcome {
            position: relative;
            overflow: hidden;
            padding: clamp(1.2rem, 3vw, 2rem);
            border-radius: 1rem;
            color: #fff;
            background:
                radial-gradient(circle at 88% 15%, rgba(255,255,255,.18), transparent 25%),
                linear-gradient(125deg, #0d43ad 0%, #155eef 54%, #3484f5 100%);
            box-shadow: 0 16px 34px rgba(21, 94, 239, 0.22);
        }
        .wllp-welcome::after {
            position: absolute;
            right: -65px;
            bottom: -95px;
            width: 230px;
            height: 230px;
            border: 38px solid rgba(255,255,255,.08);
            border-radius: 50%;
            content: "";
        }
        .wllp-welcome-content {
            position: relative;
            z-index: 1;
        }
        .wllp-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            margin-bottom: .45rem;
            padding: .25rem .6rem;
            border-radius: 999px;
            color: #dce9ff;
            font-size: .7rem;
            font-weight: 750;
            letter-spacing: .07em;
            text-transform: uppercase;
            background: rgba(255,255,255,.12);
        }
        .wllp-welcome h3 {
            font-size: clamp(1.35rem, 3vw, 1.9rem);
            font-weight: 760;
        }
        .wllp-welcome-copy {
            max-width: 610px;
            color: #dce9ff;
            font-size: .85rem;
            line-height: 1.6;
        }
        .wllp-primary-action {
            padding: .6rem 1rem;
            border: 0;
            border-radius: .65rem;
            color: #124dbf;
            font-weight: 700;
            background: #fff;
            box-shadow: 0 8px 18px rgba(7, 40, 100, .2);
        }
        .wllp-primary-action:hover {
            color: #0b3c9c;
            background: #f3f7ff;
            transform: translateY(-1px);
        }
        .wllp-info-strip {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .75rem .9rem;
            border: 1px solid #cde4f7;
            border-radius: .75rem;
            color: #3f5f7c;
            font-size: .76rem;
            background: #f3faff;
        }
        .wllp-info-icon {
            display: grid;
            flex: 0 0 30px;
            width: 30px;
            height: 30px;
            place-items: center;
            border-radius: .55rem;
            color: #1687bc;
            background: #dff3fc;
        }
        .wllp-summary-card {
            position: relative;
            height: 100%;
            overflow: hidden;
            padding: 1rem;
            border: 1px solid var(--wllp-line);
            border-radius: .9rem;
            background: #fff;
            box-shadow: 0 8px 22px rgba(36, 67, 104, .06);
            transition: transform 180ms ease, box-shadow 180ms ease;
        }
        .wllp-summary-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 13px 28px rgba(36, 67, 104, .11);
        }
        .wllp-summary-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: .75rem;
        }
        .wllp-summary-label {
            color: #61758a;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .03em;
            text-transform: uppercase;
        }
        .wllp-summary-value {
            margin-top: .3rem;
            color: #142b4b;
            font-size: 1.65rem;
            font-weight: 780;
            line-height: 1;
        }
        .wllp-summary-copy {
            margin-top: .65rem;
            color: #7b8ca0;
            font-size: .7rem;
        }
        .wllp-summary-icon {
            display: grid;
            width: 40px;
            height: 40px;
            place-items: center;
            border-radius: .7rem;
            font-size: 1rem;
        }
        .wllp-summary-icon.blue { color: #155eef; background: #eaf1ff; }
        .wllp-summary-icon.cyan { color: #1689a8; background: #e9f9fc; }
        .wllp-summary-icon.green { color: #087e5b; background: #eaf9f4; }
        .wllp-summary-icon.orange { color: #c46a16; background: #fff4e8; }
        .wllp-panel {
            height: 100%;
            overflow: hidden;
            border: 1px solid var(--wllp-line);
            border-radius: .95rem;
            background: #fff;
            box-shadow: 0 8px 22px rgba(36, 67, 104, .05);
        }
        .wllp-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.1rem;
            border-bottom: 1px solid #e8eef5;
        }
        .wllp-panel-title {
            margin: 0;
            color: #173658;
            font-size: .95rem;
            font-weight: 750;
        }
        .wllp-panel-subtitle {
            margin-top: .15rem;
            color: #8190a2;
            font-size: .68rem;
        }
        .wllp-distribution {
            padding: 1rem 1.1rem 1.15rem;
        }
        .wllp-progress-row + .wllp-progress-row {
            margin-top: 1rem;
        }
        .wllp-progress-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: .4rem;
            color: #405a74;
            font-size: .73rem;
            font-weight: 650;
        }
        .wllp-progress-track {
            height: 8px;
            overflow: hidden;
            border-radius: 999px;
            background: #edf1f6;
        }
        .wllp-progress-fill {
            height: 100%;
            min-width: 0;
            border-radius: inherit;
        }
        .wllp-progress-fill.active { background: linear-gradient(90deg, #24a5c5, #45c1da); }
        .wllp-progress-fill.filled { background: linear-gradient(90deg, #0b8f69, #35b98f); }
        .wllp-progress-fill.unfilled { background: linear-gradient(90deg, #e88724, #f3ae57); }
        .wllp-quick-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .7rem;
            padding: 1rem;
        }
        .wllp-quick-link {
            display: flex;
            align-items: center;
            gap: .7rem;
            min-height: 70px;
            padding: .75rem;
            border: 1px solid #e1e9f2;
            border-radius: .75rem;
            color: #294766;
            text-decoration: none;
            background: #fbfdff;
            transition: border-color 160ms ease, background 160ms ease, transform 160ms ease;
        }
        .wllp-quick-link:hover {
            transform: translateY(-2px);
            border-color: #9fc0f7;
            color: #155eef;
            background: #f4f8ff;
        }
        .wllp-quick-icon {
            display: grid;
            flex: 0 0 36px;
            width: 36px;
            height: 36px;
            place-items: center;
            border-radius: .65rem;
            color: #155eef;
            background: #eaf2ff;
        }
        .wllp-quick-title {
            display: block;
            font-size: .75rem;
            font-weight: 700;
        }
        .wllp-quick-copy {
            display: block;
            margin-top: .1rem;
            color: #8290a0;
            font-size: .62rem;
        }
        .wllp-activity-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .wllp-activity-item {
            display: grid;
            grid-template-columns: auto minmax(180px, 1fr) minmax(150px, auto) auto;
            align-items: center;
            gap: .8rem;
            padding: .85rem 1.1rem;
            border-bottom: 1px solid #edf1f5;
        }
        .wllp-activity-item:last-child {
            border-bottom: 0;
        }
        .wllp-activity-item:hover {
            background: #fbfdff;
        }
        .wllp-activity-icon {
            display: grid;
            width: 34px;
            height: 34px;
            place-items: center;
            border-radius: 50%;
            color: #155eef;
            background: #eaf2ff;
        }
        .wllp-activity-action {
            color: #294663;
            font-size: .76rem;
            font-weight: 700;
        }
        .wllp-activity-time,
        .wllp-activity-reg {
            color: #8290a0;
            font-size: .67rem;
        }
        .wllp-activity-reg {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        }
        .wllp-status-pill {
            display: inline-flex;
            padding: .25rem .55rem;
            border-radius: 999px;
            color: #087e5b;
            font-size: .64rem;
            font-weight: 700;
            background: #eaf9f4;
            white-space: nowrap;
        }
        .wllp-empty {
            padding: 2.5rem 1rem;
            color: #7c8da1;
            text-align: center;
        }
        @media (max-width: 767px) {
            .wllp-welcome-actions {
                width: 100%;
            }
            .wllp-welcome-actions .btn {
                width: 100%;
            }
            .wllp-quick-grid {
                grid-template-columns: 1fr;
            }
            .wllp-activity-item {
                grid-template-columns: auto 1fr auto;
            }
            .wllp-activity-reg {
                display: none;
            }
        }
    </style>
</head>
<body class="kh-proto-page kh-wllp-dashboard">
<?php include 'navbar.php'; ?>
<?php kh_proto_render_hero('Daftar Lowongan Kerja', 'Kelola prototipe WLLP dengan tampilan bergaya Karirhub Employer.', 'Lowongan Kerja', 'karirhub_employer_prototype_pelaporan_lowongan', 'Proyek', 'karirhub_employer_prototype_dashboard_wllp', false); ?>

<div class="kh-content-wrap">
<div class="container py-4">
    <div class="kh-proto-shell">
    <?php kh_proto_render_sidebar('dashboard_wllp'); ?>
    <main class="kh-proto-main">
    <section class="wllp-welcome mb-3">
        <div class="wllp-welcome-content d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="wllp-eyebrow"><i class="bi bi-shield-check"></i> Wajib Lapor Lowongan Pekerjaan</div>
                <h3 class="mb-2">Ringkasan WLLP</h3>
                <div class="wllp-welcome-copy">
                    Pantau pelaporan, status keterisian, dan bukti lapor lowongan perusahaan dalam satu dashboard.
                </div>
            </div>
            <div class="wllp-welcome-actions d-flex flex-wrap gap-2">
                <a class="btn btn-sm btn-outline-light" href="karirhub_employer_prototype_dashboard_pengembangan">
                    <i class="bi bi-kanban me-1"></i>Dashboard Pengembangan
                </a>
                <a class="btn btn-sm wllp-primary-action" href="karirhub_employer_prototype_pelaporan_lowongan">
                    <i class="bi bi-plus-circle me-1"></i>Buat Laporan Lowongan
                </a>
            </div>
        </div>
    </section>

    <div class="wllp-info-strip mb-3">
        <span class="wllp-info-icon"><i class="bi bi-lightbulb-fill"></i></span>
        <span>Halaman ini merupakan prototipe alur WLLP dan belum terhubung ke API produksi.</span>
    </div>

    <div class="row g-3 mb-3">
        <?php foreach ($summaryCards as $card): ?>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="wllp-summary-card">
                    <div class="wllp-summary-head">
                        <div>
                            <div class="wllp-summary-label"><?php echo h($card['label']); ?></div>
                            <div class="wllp-summary-value"><?php echo h($card['value']); ?></div>
                        </div>
                        <span class="wllp-summary-icon <?php echo h($card['tone']); ?>">
                            <i class="bi <?php echo h($card['icon']); ?>"></i>
                        </span>
                    </div>
                    <div class="wllp-summary-copy"><?php echo h($card['copy']); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-5">
            <section class="wllp-panel">
                <div class="wllp-panel-head">
                    <div>
                        <h5 class="wllp-panel-title">Distribusi Status Lowongan</h5>
                        <div class="wllp-panel-subtitle">Perbandingan terhadap total lowongan dilaporkan</div>
                    </div>
                    <i class="bi bi-bar-chart-fill text-primary"></i>
                </div>
                <div class="wllp-distribution">
                    <div class="wllp-progress-row">
                        <div class="wllp-progress-meta">
                            <span>Lowongan Aktif</span>
                            <span><?php echo h((string)$metrics['lowongan_aktif']); ?> · <?php echo h((string)$activePercentage); ?>%</span>
                        </div>
                        <div class="wllp-progress-track">
                            <div class="wllp-progress-fill active" style="width: <?php echo h((string)$activePercentage); ?>%;"></div>
                        </div>
                    </div>
                    <div class="wllp-progress-row">
                        <div class="wllp-progress-meta">
                            <span>Sudah Terisi</span>
                            <span><?php echo h((string)$metrics['sudah_terisi']); ?> · <?php echo h((string)$filledPercentage); ?>%</span>
                        </div>
                        <div class="wllp-progress-track">
                            <div class="wllp-progress-fill filled" style="width: <?php echo h((string)$filledPercentage); ?>%;"></div>
                        </div>
                    </div>
                    <div class="wllp-progress-row">
                        <div class="wllp-progress-meta">
                            <span>Belum Terisi</span>
                            <span><?php echo h((string)$metrics['perlu_update']); ?> · <?php echo h((string)$unfilledPercentage); ?>%</span>
                        </div>
                        <div class="wllp-progress-track">
                            <div class="wllp-progress-fill unfilled" style="width: <?php echo h((string)$unfilledPercentage); ?>%;"></div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-12 col-xl-7">
            <section class="wllp-panel">
                <div class="wllp-panel-head">
                    <div>
                        <h5 class="wllp-panel-title">Akses Cepat</h5>
                        <div class="wllp-panel-subtitle">Buka layanan WLLP yang paling sering digunakan</div>
                    </div>
                    <i class="bi bi-grid-fill text-primary"></i>
                </div>
                <div class="wllp-quick-grid">
                    <a class="wllp-quick-link" href="karirhub_employer_prototype_pelaporan_lowongan">
                        <span class="wllp-quick-icon"><i class="bi bi-journal-plus"></i></span>
                        <span>
                            <span class="wllp-quick-title">Pelaporan Lowongan</span>
                            <span class="wllp-quick-copy">Buat laporan baru</span>
                        </span>
                    </a>
                    <a class="wllp-quick-link" href="karirhub_employer_prototype_status_keterisian">
                        <span class="wllp-quick-icon"><i class="bi bi-person-check"></i></span>
                        <span>
                            <span class="wllp-quick-title">Status Keterisian</span>
                            <span class="wllp-quick-copy">Perbarui status lowongan</span>
                        </span>
                    </a>
                    <a class="wllp-quick-link" href="karirhub_employer_prototype_bukti_lapor">
                        <span class="wllp-quick-icon"><i class="bi bi-file-earmark-check"></i></span>
                        <span>
                            <span class="wllp-quick-title">Bukti Lapor</span>
                            <span class="wllp-quick-copy">Lihat dokumen pelaporan</span>
                        </span>
                    </a>
                    <a class="wllp-quick-link" href="karirhub_employer_prototype_job_posted_karirhub">
                        <span class="wllp-quick-icon"><i class="bi bi-briefcase"></i></span>
                        <span>
                            <span class="wllp-quick-title">Lowongan Karirhub</span>
                            <span class="wllp-quick-copy">Kelola lowongan terposting</span>
                        </span>
                    </a>
                </div>
            </section>
        </div>
    </div>

    <section class="wllp-panel">
        <div class="wllp-panel-head">
            <div>
                <h5 class="wllp-panel-title">Aktivitas Terbaru WLLP</h5>
                <div class="wllp-panel-subtitle">Riwayat pelaporan terbaru perusahaan</div>
            </div>
            <a class="btn btn-outline-primary btn-sm" href="karirhub_employer_prototype_bukti_lapor">
                Lihat Semua <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
        <?php if (empty($recentActivities)): ?>
            <div class="wllp-empty">
                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                Belum ada aktivitas WLLP.
            </div>
        <?php else: ?>
            <ul class="wllp-activity-list">
                <?php foreach ($recentActivities as $row): ?>
                    <li class="wllp-activity-item">
                        <span class="wllp-activity-icon"><i class="bi bi-file-earmark-check"></i></span>
                        <span>
                            <span class="wllp-activity-action d-block"><?php echo h($row['aksi']); ?></span>
                            <span class="wllp-activity-time"><?php echo h($row['waktu']); ?></span>
                        </span>
                        <span class="wllp-activity-reg"><?php echo h($row['no_reg_bukti']); ?></span>
                        <span class="wllp-status-pill"><?php echo h($row['status']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    </main>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php kh_proto_render_sidebar_script(); ?>
</body>
</html>
