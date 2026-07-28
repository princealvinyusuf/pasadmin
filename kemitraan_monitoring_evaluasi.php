<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db_prod';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

function table_exists(mysqli $conn, string $table): bool {
    $t = $conn->real_escape_string($table);
    $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function column_exists(mysqli $conn, string $table, string $column): bool {
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($column);
    $sql = "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
    $res = $conn->query($sql);
    return $res && $res->num_rows > 0;
}

function can_access_kemitraan_record(
    mysqli $conn,
    int $kemitraanId,
    bool $isSuperAdmin,
    ?int $scopedLocationId,
    bool $hasWalkinLocationColumn
): bool {
    if ($isSuperAdmin) {
        return true;
    }
    // Fallback: if location scope is not configured, allow access by permission.
    if (!$hasWalkinLocationColumn || $scopedLocationId === null) {
        return true;
    }
    $stmt = $conn->prepare("SELECT 1 FROM kemitraan WHERE id=? AND walkin_location_id=? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ii', $kemitraanId, $scopedLocationId);
    $stmt->execute();
    $stmt->store_result();
    $ok = $stmt->num_rows > 0;
    $stmt->close();
    return $ok;
}

function format_schedule_time(?string $start, ?string $finish): string {
    $ts = $start ? substr($start, 0, 5) : '';
    $tf = $finish ? substr($finish, 0, 5) : '';
    if ($ts !== '' && $tf !== '') {
        return $ts . ' - ' . $tf;
    }
    if ($ts !== '') {
        return $ts;
    }
    if ($tf !== '') {
        return $tf;
    }
    return '-';
}

$isSuperAdmin = true;
$scopedLocationId = null;
$hasWalkinLocationColumn = column_exists($conn, 'kemitraan', 'walkin_location_id');
$isLocationScopeActive = $hasWalkinLocationColumn && $scopedLocationId !== null;
$kemitraanLocationWhere = ($isSuperAdmin || !$isLocationScopeActive)
    ? '1=1'
    : ('k.walkin_location_id=' . intval($scopedLocationId));

$monitoringTableExists = table_exists($conn, 'kemitraan_monitoring_evaluasi');

if (isset($_POST['save_monitoring'])) {
    $kemitraanId = intval($_POST['kemitraan_id'] ?? 0);
    $passwordInput = trim($_POST['monitoring_password'] ?? '');
    $fieldKey = trim($_POST['field_key'] ?? '');
    $fieldValue = trim($_POST['field_value'] ?? '');
    $requiredPassword = 'Pusatpasarkerj4';
    $allowedFields = [
        'tim_kerja_pelaksana' => 'tim_kerja_pelaksana',
        'pic_pusat_pasar_kerja' => 'pic_pusat_pasar_kerja',
        'masalah_hambatan' => 'masalah_hambatan',
        'tindak_lanjut' => 'tindak_lanjut',
        'dokumentasi_link' => 'dokumentasi_link',
    ];

    if ($kemitraanId <= 0) {
        $_SESSION['error'] = 'Data kemitraan tidak valid.';
        header('Location: kemitraan_monitoring_evaluasi');
        exit;
    }

    if (!can_access_kemitraan_record($conn, $kemitraanId, $isSuperAdmin, $scopedLocationId, $hasWalkinLocationColumn)) {
        $_SESSION['error'] = 'Anda tidak memiliki akses ke data kemitraan ini.';
        header('Location: kemitraan_monitoring_evaluasi');
        exit;
    }

    if ($passwordInput !== $requiredPassword) {
        $_SESSION['error'] = 'Password salah. Monitoring tidak disimpan.';
        header('Location: kemitraan_monitoring_evaluasi');
        exit;
    }

    if (!isset($allowedFields[$fieldKey])) {
        $_SESSION['error'] = 'Field monitoring tidak valid.';
        header('Location: kemitraan_monitoring_evaluasi');
        exit;
    }

    if (!$monitoringTableExists) {
        $_SESSION['error'] = 'Tabel monitoring belum tersedia. Jalankan migration terlebih dahulu.';
        header('Location: kemitraan_monitoring_evaluasi');
        exit;
    }

    $columnName = $allowedFields[$fieldKey];

    $stmt = $conn->prepare(
        "INSERT INTO kemitraan_monitoring_evaluasi
        (kemitraan_id, {$columnName}, created_at, updated_at)
        VALUES (?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            {$columnName} = VALUES({$columnName}),
            updated_at = NOW()"
    );

    if (!$stmt) {
        $_SESSION['error'] = 'Gagal menyiapkan query monitoring: ' . $conn->error;
        header('Location: kemitraan_monitoring_evaluasi');
        exit;
    }

    $stmt->bind_param('is', $kemitraanId, $fieldValue);

    if ($stmt->execute()) {
        $_SESSION['success'] = 'Data monitoring berhasil disimpan.';
    } else {
        $_SESSION['error'] = 'Gagal menyimpan monitoring: ' . $stmt->error;
    }
    $stmt->close();

    header('Location: kemitraan_monitoring_evaluasi');
    exit;
}

$monitoringSelect = $monitoringTableExists
    ? "kme.tim_kerja_pelaksana, kme.pic_pusat_pasar_kerja, kme.masalah_hambatan, kme.tindak_lanjut, kme.dokumentasi_link"
    : "'' AS tim_kerja_pelaksana, '' AS pic_pusat_pasar_kerja, '' AS masalah_hambatan, '' AS tindak_lanjut, '' AS dokumentasi_link";
$monitoringJoin = $monitoringTableExists
    ? "LEFT JOIN kemitraan_monitoring_evaluasi kme ON kme.kemitraan_id = k.id"
    : "";

$approvedRows = [];
$approvedQuery = $conn->query(
    "SELECT
        k.id,
        k.institution_name,
        k.pic_name,
        k.schedule,
        k.scheduletimestart,
        k.scheduletimefinish,
        top.name AS partnership_type_name,
        {$monitoringSelect}
    FROM kemitraan k
    LEFT JOIN type_of_partnership top ON top.id = k.type_of_partnership_id
    {$monitoringJoin}
    WHERE k.status='approved' AND {$kemitraanLocationWhere}
    ORDER BY k.id DESC"
);

if ($approvedQuery) {
    while ($row = $approvedQuery->fetch_assoc()) {
        $approvedRows[] = $row;
    }
    $approvedQuery->free();
}

$approvedIds = [];
foreach ($approvedRows as $row) {
    $approvedIds[] = intval($row['id']);
}

$lowonganByKemitraan = [];
if (!empty($approvedIds) && table_exists($conn, 'kemitraan_detail_lowongan')) {
    $hasJumlahPenempatan = column_exists($conn, 'kemitraan_detail_lowongan', 'jumlah_penempatan');
    $jumlahPenempatanSelect = $hasJumlahPenempatan ? ", jumlah_penempatan" : "";
    $idList = implode(',', array_map('intval', $approvedIds));

    $lowonganRes = $conn->query(
        "SELECT kemitraan_id, jabatan_yang_dibuka, jumlah_kebutuhan{$jumlahPenempatanSelect}
        FROM kemitraan_detail_lowongan
        WHERE kemitraan_id IN ({$idList})
        ORDER BY kemitraan_id ASC, id ASC"
    );

    if ($lowonganRes) {
        while ($lowongan = $lowonganRes->fetch_assoc()) {
            $kemitraanId = intval($lowongan['kemitraan_id']);
            if (!isset($lowonganByKemitraan[$kemitraanId])) {
                $lowonganByKemitraan[$kemitraanId] = [];
            }

            $realisasiItems = [
                'Jabatan Yang Dibuka: ' . trim((string)($lowongan['jabatan_yang_dibuka'] ?? '-')),
                'Jumlah Kebutuhan: ' . trim((string)($lowongan['jumlah_kebutuhan'] ?? '-')),
                'Jumlah Penempatan: ' . ($hasJumlahPenempatan ? trim((string)($lowongan['jumlah_penempatan'] ?? '-')) : '-'),
            ];
            $lowonganByKemitraan[$kemitraanId][] = $realisasiItems;
        }
        $lowonganRes->free();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Monitoring & Evaluasi Kemitraan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .table td, .table th {
            vertical-align: top;
        }
        .cell-line {
            display: block;
            margin-bottom: 4px;
        }
        .cell-line:last-child {
            margin-bottom: 0;
        }
        .label-muted {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .realisasi-box {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 8px;
            margin-bottom: 8px;
        }
        .realisasi-box:last-child {
            margin-bottom: 0;
        }
        .inline-edit-btn {
            margin-top: 6px;
        }
    </style>
</head>
<body class="bg-light">
<?php include __DIR__ . '/navbar.php'; ?>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Dashboard Monitoring & Evaluasi Kemitraan Pusat Pasar Kerja</h2>
        <span class="badge bg-success">Approved Data</span>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
    <?php unset($_SESSION['error']); endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
    <?php unset($_SESSION['success']); endif; ?>

    <?php if (!$monitoringTableExists): ?>
        <div class="alert alert-warning">
            Tabel monitoring belum tersedia di database. Jalankan migration terlebih dahulu agar input monitoring bisa disimpan.
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered align-middle" style="min-width: 1400px;">
                    <thead class="table-light">
                        <tr>
                            <th>Identitas Kemitraan</th>
                            <th>Kegiatan</th>
                            <th>Waktu Pelaksanaan</th>
                            <th>Realisasi / Output</th>
                            <th>Masalah / Hambatan</th>
                            <th>Tindak Lanjut</th>
                            <th>Dokumentasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($approvedRows)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">Belum ada data kemitraan approved.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($approvedRows as $row): ?>
                                <?php
                                    $kemitraanId = intval($row['id']);
                                    $monitoringPayload = [
                                        'tim_kerja_pelaksana' => $row['tim_kerja_pelaksana'] ?? '',
                                        'pic_pusat_pasar_kerja' => $row['pic_pusat_pasar_kerja'] ?? '',
                                        'masalah_hambatan' => $row['masalah_hambatan'] ?? '',
                                        'tindak_lanjut' => $row['tindak_lanjut'] ?? '',
                                        'dokumentasi_link' => $row['dokumentasi_link'] ?? '',
                                    ];
                                    $scheduleLabel = trim((string)($row['schedule'] ?? ''));
                                    $timeLabel = format_schedule_time($row['scheduletimestart'] ?? null, $row['scheduletimefinish'] ?? null);
                                    $institutionNameAttr = htmlspecialchars((string)($row['institution_name'] ?? ''), ENT_QUOTES);
                                    $monitoringDataAttr = htmlspecialchars(json_encode($monitoringPayload, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
                                ?>
                                <tr>
                                    <td>
                                        <span class="cell-line"><span class="label-muted">Nama Mitra:</span> <?php echo htmlspecialchars((string)($row['institution_name'] ?? '-')); ?></span>
                                        <span class="cell-line">
                                            <span class="label-muted">Tim Kerja Pelaksana:</span> <?php echo htmlspecialchars((string)($row['tim_kerja_pelaksana'] ?? '-')); ?>
                                            <button
                                                type="button"
                                                class="btn btn-outline-primary btn-sm inline-edit-btn btn-open-monitoring"
                                                data-id="<?php echo $kemitraanId; ?>"
                                                data-field-key="tim_kerja_pelaksana"
                                                data-institution="<?php echo $institutionNameAttr; ?>"
                                                data-monitoring="<?php echo $monitoringDataAttr; ?>"
                                                <?php echo $monitoringTableExists ? '' : 'disabled'; ?>
                                            >
                                                Isi / Edit
                                            </button>
                                        </span>
                                        <span class="cell-line">
                                            <span class="label-muted">PIC Pusat Pasar Kerja:</span> <?php echo htmlspecialchars((string)($row['pic_pusat_pasar_kerja'] ?? '-')); ?>
                                            <button
                                                type="button"
                                                class="btn btn-outline-primary btn-sm inline-edit-btn btn-open-monitoring"
                                                data-id="<?php echo $kemitraanId; ?>"
                                                data-field-key="pic_pusat_pasar_kerja"
                                                data-institution="<?php echo $institutionNameAttr; ?>"
                                                data-monitoring="<?php echo $monitoringDataAttr; ?>"
                                                <?php echo $monitoringTableExists ? '' : 'disabled'; ?>
                                            >
                                                Isi / Edit
                                            </button>
                                        </span>
                                        <span class="cell-line"><span class="label-muted">PIC Mitra:</span> <?php echo htmlspecialchars((string)($row['pic_name'] ?? '-')); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars((string)($row['partnership_type_name'] ?? '-')); ?></td>
                                    <td>
                                        <span class="cell-line"><?php echo htmlspecialchars($scheduleLabel !== '' ? $scheduleLabel : '-'); ?></span>
                                        <span class="label-muted">Jam: <?php echo htmlspecialchars($timeLabel); ?></span>
                                    </td>
                                    <td>
                                        <?php $realisasiRows = $lowonganByKemitraan[$kemitraanId] ?? []; ?>
                                        <?php if (empty($realisasiRows)): ?>
                                            <span class="text-muted">-</span>
                                        <?php else: ?>
                                            <?php foreach ($realisasiRows as $idx => $realisasi): ?>
                                                <div class="realisasi-box">
                                                    <div class="fw-semibold mb-1">Lowongan <?php echo $idx + 1; ?></div>
                                                    <?php foreach ($realisasi as $item): ?>
                                                        <span class="cell-line"><?php echo htmlspecialchars($item); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo nl2br(htmlspecialchars((string)($row['masalah_hambatan'] ?? '-'))); ?>
                                        <div>
                                            <button
                                                type="button"
                                                class="btn btn-outline-primary btn-sm inline-edit-btn btn-open-monitoring"
                                                data-id="<?php echo $kemitraanId; ?>"
                                                data-field-key="masalah_hambatan"
                                                data-institution="<?php echo $institutionNameAttr; ?>"
                                                data-monitoring="<?php echo $monitoringDataAttr; ?>"
                                                <?php echo $monitoringTableExists ? '' : 'disabled'; ?>
                                            >
                                                Isi / Edit
                                            </button>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo nl2br(htmlspecialchars((string)($row['tindak_lanjut'] ?? '-'))); ?>
                                        <div>
                                            <button
                                                type="button"
                                                class="btn btn-outline-primary btn-sm inline-edit-btn btn-open-monitoring"
                                                data-id="<?php echo $kemitraanId; ?>"
                                                data-field-key="tindak_lanjut"
                                                data-institution="<?php echo $institutionNameAttr; ?>"
                                                data-monitoring="<?php echo $monitoringDataAttr; ?>"
                                                <?php echo $monitoringTableExists ? '' : 'disabled'; ?>
                                            >
                                                Isi / Edit
                                            </button>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                            $dokumentasiLink = trim((string)($row['dokumentasi_link'] ?? ''));
                                            $isValidUrl = $dokumentasiLink !== '' && filter_var($dokumentasiLink, FILTER_VALIDATE_URL);
                                        ?>
                                        <?php if ($isValidUrl): ?>
                                            <a href="<?php echo htmlspecialchars($dokumentasiLink); ?>" target="_blank" rel="noopener noreferrer">Lihat Dokumentasi</a>
                                        <?php elseif ($dokumentasiLink !== ''): ?>
                                            <?php echo htmlspecialchars($dokumentasiLink); ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                        <div>
                                            <button
                                                type="button"
                                                class="btn btn-outline-primary btn-sm inline-edit-btn btn-open-monitoring"
                                                data-id="<?php echo $kemitraanId; ?>"
                                                data-field-key="dokumentasi_link"
                                                data-institution="<?php echo $institutionNameAttr; ?>"
                                                data-monitoring="<?php echo $monitoringDataAttr; ?>"
                                                <?php echo $monitoringTableExists ? '' : 'disabled'; ?>
                                            >
                                                Isi / Edit
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="monitoringModal" tabindex="-1" aria-labelledby="monitoringModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="monitoringModalLabel">Isi / Edit Field Monitoring</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="save_monitoring" value="1">
                    <input type="hidden" name="kemitraan_id" id="monitoring_kemitraan_id">
                    <input type="hidden" name="field_key" id="monitoring_field_key">

                    <div class="mb-2">
                        <label class="form-label">Nama Mitra</label>
                        <input type="text" id="monitoring_institution_name" class="form-control" readonly>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" id="monitoring_field_label">Field</label>
                        <textarea id="monitoring_field_value_textarea" class="form-control" rows="4"></textarea>
                        <input type="text" id="monitoring_field_value_input" class="form-control d-none" placeholder="Masukkan nilai">
                        <input type="hidden" name="field_value" id="monitoring_field_value_hidden">
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Password Validasi (wajib setiap simpan)</label>
                        <input type="password" name="monitoring_password" id="monitoring_password" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Monitoring</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalElement = document.getElementById('monitoringModal');
    const modal = new bootstrap.Modal(modalElement);
    const form = modalElement.querySelector('form');
    const titleEl = document.getElementById('monitoringModalLabel');
    const fieldLabelEl = document.getElementById('monitoring_field_label');
    const fieldKeyEl = document.getElementById('monitoring_field_key');
    const fieldTextareaEl = document.getElementById('monitoring_field_value_textarea');
    const fieldInputEl = document.getElementById('monitoring_field_value_input');
    const fieldHiddenEl = document.getElementById('monitoring_field_value_hidden');
    const passwordEl = document.getElementById('monitoring_password');

    const fieldConfig = {
        tim_kerja_pelaksana: { label: 'Tim Kerja Pelaksana', type: 'textarea' },
        pic_pusat_pasar_kerja: { label: 'PIC Pusat Pasar Kerja', type: 'textarea' },
        masalah_hambatan: { label: 'Masalah / Hambatan', type: 'textarea' },
        tindak_lanjut: { label: 'Tindak Lanjut', type: 'textarea' },
        dokumentasi_link: { label: 'Dokumentasi (Input Source Link)', type: 'text' }
    };

    form.addEventListener('submit', function() {
        if (fieldInputEl.classList.contains('d-none')) {
            fieldHiddenEl.value = fieldTextareaEl.value;
        } else {
            fieldHiddenEl.value = fieldInputEl.value;
        }
    });

    document.querySelectorAll('.btn-open-monitoring').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const kemitraanId = btn.getAttribute('data-id') || '';
            const fieldKey = btn.getAttribute('data-field-key') || '';
            const institutionName = btn.getAttribute('data-institution') || '';
            const monitoringRaw = btn.getAttribute('data-monitoring') || '{}';

            let monitoring = {};
            try {
                monitoring = JSON.parse(monitoringRaw);
            } catch (err) {
                monitoring = {};
            }

            const config = fieldConfig[fieldKey];
            if (!config) {
                return;
            }

            const currentValue = monitoring[fieldKey] || '';
            document.getElementById('monitoring_kemitraan_id').value = kemitraanId;
            fieldKeyEl.value = fieldKey;
            document.getElementById('monitoring_institution_name').value = institutionName;

            titleEl.textContent = 'Isi / Edit ' + config.label;
            fieldLabelEl.textContent = config.label;
            fieldHiddenEl.value = '';
            passwordEl.value = '';

            if (config.type === 'text') {
                fieldTextareaEl.classList.add('d-none');
                fieldInputEl.classList.remove('d-none');
                fieldInputEl.value = currentValue;
            } else {
                fieldInputEl.classList.add('d-none');
                fieldTextareaEl.classList.remove('d-none');
                fieldTextareaEl.value = currentValue;
            }

            modal.show();
        });
    });
});
</script>
</body>
</html>
<?php $conn->close(); ?>
