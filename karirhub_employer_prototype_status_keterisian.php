<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_data.php';
require_once __DIR__ . '/karirhub_employer_prototype_storage.php';
require_once __DIR__ . '/karirhub_employer_prototype_ui.php';
require_once __DIR__ . '/db.php';

if (!kh_proto_can_access('karirhub_employer_prototype_status_keterisian_view')) {
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

$statusFilter = trim((string)($_REQUEST['status'] ?? 'all'));
$allowedStatus = ['all', 'belum terisi', 'proses seleksi', 'terisi'];
if (!in_array($statusFilter, $allowedStatus, true)) {
    $statusFilter = 'all';
}
$unitFilter = trim((string)($_REQUEST['unit'] ?? 'all'));
if ($unitFilter !== 'all' && !isset($units[$unitFilter])) {
    $unitFilter = 'all';
}
$sumberFilter = trim((string)($_REQUEST['sumber'] ?? 'all'));
$allowedSumber = ['all', 'karirhub', 'lainnya'];
if (!in_array($sumberFilter, $allowedSumber, true)) {
    $sumberFilter = 'all';
}

$simulatedNoReg = trim((string)($_GET['simulate_no_reg'] ?? ''));
$simulatedIdLowongan = trim((string)($_GET['simulate_id_lowongan'] ?? ''));
$simulatedStatus = trim((string)($_GET['simulate_status'] ?? ''));
$successMessage = null;
if ($simulatedNoReg !== '' && $simulatedIdLowongan !== '' && in_array($simulatedStatus, ['Belum Terisi', 'Proses Seleksi', 'Terisi', 'Belum Update'], true)) {
    $successMessage = 'Simulasi update status untuk ' . $simulatedNoReg . ' / ' . $simulatedIdLowongan . ' -> ' . $simulatedStatus . ' berhasil (dummy, tidak disimpan permanen).';
}

$rows = [];
$resRows = $conn->query("
    SELECT
        d.no_reg_bukti,
        d.id_lowongan,
        d.jabatan,
        d.jumlah_kebutuhan,
        d.unit_kode,
        d.unit_nama,
        CASE
            WHEN d.catatan LIKE 'Auto insert dari Job Posted%' THEN 'Karirhub'
            ELSE 'Lainnya'
        END AS sumber,
        COALESCE(s.status_saat_ini, 'Belum Terisi') AS status_keterisian,
        COALESCE(CAST(s.tanggal_lapor AS CHAR), CAST(d.masa_berlaku_mulai AS CHAR), '') AS tanggal_lapor,
        COALESCE(CAST(s.tanggal_terisi AS CHAR), '') AS tanggal_terisi,
        h.periode_tipe,
        CAST(h.periode_mulai AS CHAR) AS periode_mulai,
        CAST(h.periode_selesai AS CHAR) AS periode_selesai,
        COALESCE(p.nik, '') AS nik,
        COALESCE(p.nama_lengkap, '') AS nama_lengkap,
        COALESCE(p.pendidikan, '') AS pendidikan,
        COALESCE(p.jenis_kelamin, '') AS jenis_kelamin,
        COALESCE(p.tempat_lahir, '') AS tempat_lahir,
        COALESCE(CAST(p.tanggal_lahir AS CHAR), '') AS tanggal_lahir,
        COALESCE(p.alamat, '') AS alamat,
        COALESCE(p.status_disabilitas, '') AS status_disabilitas,
        COALESCE(CAST(p.tmt AS CHAR), '') AS tmt,
        COALESCE(p.email, '') AS email,
        COALESCE(p.nomor_hp, '') AS nomor_hp,
        COALESCE(pc.jumlah_penempatan, 0) AS jumlah_penempatan
    FROM karirhub_proto_wllp_pelaporan d
    LEFT JOIN karirhub_proto_wllp_status s
        ON s.no_reg_bukti = d.no_reg_bukti AND s.id_lowongan = d.id_lowongan
    LEFT JOIN (
        SELECT p1.*
        FROM karirhub_proto_wllp_penempatan p1
        INNER JOIN (
            SELECT no_reg_bukti, id_lowongan, MIN(urutan_penempatan) AS urutan_penempatan
            FROM karirhub_proto_wllp_penempatan
            GROUP BY no_reg_bukti, id_lowongan
        ) pmin
            ON pmin.no_reg_bukti = p1.no_reg_bukti
            AND pmin.id_lowongan = p1.id_lowongan
            AND pmin.urutan_penempatan = p1.urutan_penempatan
    ) p
        ON p.no_reg_bukti = d.no_reg_bukti AND p.id_lowongan = d.id_lowongan
    LEFT JOIN (
        SELECT no_reg_bukti, id_lowongan, COUNT(*) AS jumlah_penempatan
        FROM karirhub_proto_wllp_penempatan
        GROUP BY no_reg_bukti, id_lowongan
    ) pc
        ON pc.no_reg_bukti = d.no_reg_bukti AND pc.id_lowongan = d.id_lowongan
    LEFT JOIN karirhub_proto_wllp_laporan h
        ON h.no_reg_bukti = d.no_reg_bukti
    ORDER BY d.created_at DESC, d.no_reg_bukti DESC, d.id_lowongan DESC
");
if ($resRows) {
    while ($row = $resRows->fetch_assoc()) {
        $row['status_saat_ini'] = (string)$row['status_keterisian'];
        $rows[] = $row;
    }
}

$rowMap = [];
foreach ($rows as $row) {
    $key = (string)$row['no_reg_bukti'] . '||' . (string)$row['id_lowongan'];
    $rowMap[$key] = $row;
}

$requiredPegawaiFields = [
    'nik' => 'NIK',
    'nama_lengkap' => 'Nama Lengkap',
    'pendidikan' => 'Pendidikan',
    'alamat' => 'Alamat',
    'status_disabilitas' => 'Status Disabilitas',
    'tmt' => 'Tanggal Mulai Kerja',
    'email' => 'Email',
    'nomor_hp' => 'Nomor Hp',
];
$pegawaiDefaultRow = [
    'nik' => '',
    'nama_lengkap' => '',
    'pendidikan' => '',
    'alamat' => '',
    'status_disabilitas' => '',
    'tmt' => '',
    'email' => '',
    'nomor_hp' => '',
];
$pegawaiFormRows = [];
$pegawaiErrors = [];
$openTerisiNoReg = trim((string)($_GET['open_terisi_for'] ?? ''));
$openTerisiIdLowongan = trim((string)($_GET['open_terisi_id'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['form_action'] ?? '') === 'submit_terisi_data') {
    $openTerisiNoReg = trim((string)($_POST['no_reg_bukti'] ?? ''));
    $openTerisiIdLowongan = trim((string)($_POST['id_lowongan'] ?? ''));
    $openKey = $openTerisiNoReg . '||' . $openTerisiIdLowongan;

    $targetPegawaiCount = max(1, (int)($_POST['jumlah_kebutuhan_target'] ?? 1));
    foreach ($requiredPegawaiFields as $field => $_label) {
        $rawValues = $_POST[$field] ?? [];
        if (!is_array($rawValues)) {
            $rawValues = [$rawValues];
        }
        for ($idx = 0; $idx < $targetPegawaiCount; $idx++) {
            if (!isset($pegawaiFormRows[$idx])) {
                $pegawaiFormRows[$idx] = $pegawaiDefaultRow;
            }
            $pegawaiFormRows[$idx][$field] = trim((string)($rawValues[$idx] ?? ''));
        }
    }

    if ($openTerisiNoReg === '' || $openTerisiIdLowongan === '' || !isset($rowMap[$openKey])) {
        $pegawaiErrors[] = 'Data lowongan untuk status Terisi tidak ditemukan.';
    } else {
        $jumlahKebutuhan = max(1, (int)($rowMap[$openKey]['jumlah_kebutuhan'] ?? 1));
        if ($targetPegawaiCount !== $jumlahKebutuhan) {
            $targetPegawaiCount = $jumlahKebutuhan;
            for ($idx = 0; $idx < $targetPegawaiCount; $idx++) {
                if (!isset($pegawaiFormRows[$idx])) {
                    $pegawaiFormRows[$idx] = $pegawaiDefaultRow;
                }
            }
        }
    }

    $validPegawaiRows = [];
    $requiredFieldCount = count($requiredPegawaiFields);
    for ($idx = 0; $idx < $targetPegawaiCount; $idx++) {
        $rowLabel = 'Pegawai ke-' . ($idx + 1);
        $filledFieldCount = 0;
        foreach ($requiredPegawaiFields as $field => $_label) {
            if (($pegawaiFormRows[$idx][$field] ?? '') !== '') {
                $filledFieldCount++;
            }
        }
        if ($filledFieldCount === 0) {
            continue;
        }
        if ($filledFieldCount < $requiredFieldCount) {
            foreach ($requiredPegawaiFields as $field => $label) {
                if (($pegawaiFormRows[$idx][$field] ?? '') === '') {
                    $pegawaiErrors[] = $rowLabel . ': ' . $label . ' wajib diisi.';
                }
            }
            continue;
        }
        $statusDisabilitas = $pegawaiFormRows[$idx]['status_disabilitas'] ?? '';
        if ($statusDisabilitas !== '' && !in_array($statusDisabilitas, ['Iya', 'Tidak'], true)) {
            $pegawaiErrors[] = $rowLabel . ': Status Disabilitas hanya boleh Iya atau Tidak.';
            continue;
        }
        $validPegawaiRows[] = $pegawaiFormRows[$idx];
    }
    if (empty($validPegawaiRows)) {
        $pegawaiErrors[] = 'Isi minimal 1 Data Pegawai secara lengkap untuk melanjutkan.';
    }

    if (empty($pegawaiErrors)) {
        try {
            $conn->begin_transaction();

            $stmtDeletePegawai = $conn->prepare("DELETE FROM karirhub_proto_wllp_penempatan WHERE no_reg_bukti = ? AND id_lowongan = ?");
            $stmtDeletePegawai->bind_param('ss', $openTerisiNoReg, $openTerisiIdLowongan);
            $stmtDeletePegawai->execute();
            $stmtDeletePegawai->close();

            $stmtSavePegawai = $conn->prepare("
                INSERT INTO karirhub_proto_wllp_penempatan
                    (no_reg_bukti, id_lowongan, urutan_penempatan, nik, nama_lengkap, pendidikan, jenis_kelamin, tempat_lahir, tanggal_lahir, alamat, status_disabilitas, tmt, email, nomor_hp)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($validPegawaiRows as $index => $pegawaiForm) {
                $urutanPenempatan = $index + 1;
                $jenisKelamin = '-';
                $tempatLahir = '-';
                $tanggalLahir = '1970-01-01';
                $stmtSavePegawai->bind_param(
                    'ssisssssssssss',
                    $openTerisiNoReg,
                    $openTerisiIdLowongan,
                    $urutanPenempatan,
                    $pegawaiForm['nik'],
                    $pegawaiForm['nama_lengkap'],
                    $pegawaiForm['pendidikan'],
                    $jenisKelamin,
                    $tempatLahir,
                    $tanggalLahir,
                    $pegawaiForm['alamat'],
                    $pegawaiForm['status_disabilitas'],
                    $pegawaiForm['tmt'],
                    $pegawaiForm['email'],
                    $pegawaiForm['nomor_hp']
                );
                $stmtSavePegawai->execute();
            }
            $stmtSavePegawai->close();

            $statusTerisi = 'Terisi';
            $tanggalTerisiNow = $validPegawaiRows[0]['tmt'] !== '' ? $validPegawaiRows[0]['tmt'] : date('Y-m-d');
            $stmtUpdateStatus = $conn->prepare("UPDATE karirhub_proto_wllp_status SET status_saat_ini = ?, tanggal_terisi = ? WHERE no_reg_bukti = ? AND id_lowongan = ?");
            $stmtUpdateStatus->bind_param('ssss', $statusTerisi, $tanggalTerisiNow, $openTerisiNoReg, $openTerisiIdLowongan);
            $stmtUpdateStatus->execute();
            $stmtUpdateStatus->close();

            $conn->commit();

            $successMessage = 'Simulasi update status untuk ' . $openTerisiNoReg . ' / ' . $openTerisiIdLowongan . ' -> Terisi berhasil. '
                . count($validPegawaiRows) . ' data pegawai telah dilengkapi.';
            $openTerisiNoReg = '';
            $openTerisiIdLowongan = '';
            $pegawaiFormRows = [];
        } catch (Throwable $e) {
            $conn->rollback();
            $pegawaiErrors[] = 'Gagal menyimpan data pegawai. Silakan coba lagi.';
        }
    }
}

$openKey = $openTerisiNoReg . '||' . $openTerisiIdLowongan;
$openTerisiRow = ($openTerisiNoReg !== '' && $openTerisiIdLowongan !== '' && isset($rowMap[$openKey])) ? $rowMap[$openKey] : null;
if ($openTerisiRow !== null) {
    $openTerisiJumlahKebutuhan = max(1, (int)($openTerisiRow['jumlah_kebutuhan'] ?? 1));
    if (empty($pegawaiFormRows)) {
        $resPegawai = $conn->prepare("
            SELECT nik, nama_lengkap, pendidikan, alamat, status_disabilitas, CAST(tmt AS CHAR) AS tmt, email, nomor_hp
            FROM karirhub_proto_wllp_penempatan
            WHERE no_reg_bukti = ? AND id_lowongan = ?
            ORDER BY urutan_penempatan ASC
        ");
        $resPegawai->bind_param('ss', $openTerisiNoReg, $openTerisiIdLowongan);
        $resPegawai->execute();
        $resPegawaiResult = $resPegawai->get_result();
        while ($resPegawaiResult && ($pegawai = $resPegawaiResult->fetch_assoc())) {
            $pegawaiFormRows[] = [
                'nik' => (string)($pegawai['nik'] ?? ''),
                'nama_lengkap' => (string)($pegawai['nama_lengkap'] ?? ''),
                'pendidikan' => (string)($pegawai['pendidikan'] ?? ''),
                'alamat' => (string)($pegawai['alamat'] ?? ''),
                'status_disabilitas' => (string)($pegawai['status_disabilitas'] ?? ''),
                'tmt' => (string)($pegawai['tmt'] ?? ''),
                'email' => (string)($pegawai['email'] ?? ''),
                'nomor_hp' => (string)($pegawai['nomor_hp'] ?? ''),
            ];
        }
        $resPegawai->close();
    }
    for ($idx = count($pegawaiFormRows); $idx < $openTerisiJumlahKebutuhan; $idx++) {
        $pegawaiFormRows[] = $pegawaiDefaultRow;
    }
    if (count($pegawaiFormRows) > $openTerisiJumlahKebutuhan) {
        $pegawaiFormRows = array_slice($pegawaiFormRows, 0, $openTerisiJumlahKebutuhan);
    }
}
$detailNoReg = trim((string)($_GET['detail_no_reg'] ?? ''));
$detailIdLowongan = trim((string)($_GET['detail_id_lowongan'] ?? ''));
$detailKey = $detailNoReg . '||' . $detailIdLowongan;
$detailRow = ($detailNoReg !== '' && $detailIdLowongan !== '' && isset($rowMap[$detailKey])) ? $rowMap[$detailKey] : null;
$detailLowonganInfo = null;
$detailPegawaiRows = [];
if ($detailRow !== null) {
    $stmtDetailLowongan = $conn->prepare("
        SELECT
            no_reg_bukti,
            id_lowongan,
            jabatan,
            unit_nama,
            jumlah_kebutuhan,
            jenis_kelamin,
            usia_min,
            usia_max,
            pendidikan_minimal,
            pengalaman_min_tahun,
            rentang_gaji,
            keterampilan_utama,
            CAST(masa_berlaku_mulai AS CHAR) AS masa_berlaku_mulai,
            CAST(masa_berlaku_sampai AS CHAR) AS masa_berlaku_sampai
        FROM karirhub_proto_wllp_pelaporan
        WHERE no_reg_bukti = ? AND id_lowongan = ?
        LIMIT 1
    ");
    $stmtDetailLowongan->bind_param('ss', $detailNoReg, $detailIdLowongan);
    $stmtDetailLowongan->execute();
    $detailLowonganResult = $stmtDetailLowongan->get_result();
    if ($detailLowonganResult) {
        $detailLowonganInfo = $detailLowonganResult->fetch_assoc() ?: null;
    }
    $stmtDetailLowongan->close();

    $stmtDetailPegawai = $conn->prepare("
        SELECT
            urutan_penempatan,
            nik,
            nama_lengkap,
            pendidikan,
            alamat,
            status_disabilitas,
            CAST(tmt AS CHAR) AS tmt,
            email,
            nomor_hp
        FROM karirhub_proto_wllp_penempatan
        WHERE no_reg_bukti = ? AND id_lowongan = ?
        ORDER BY urutan_penempatan ASC
    ");
    $stmtDetailPegawai->bind_param('ss', $detailNoReg, $detailIdLowongan);
    $stmtDetailPegawai->execute();
    $detailPegawaiResult = $stmtDetailPegawai->get_result();
    while ($detailPegawaiResult && ($detailPegawai = $detailPegawaiResult->fetch_assoc())) {
        $detailPegawaiRows[] = $detailPegawai;
    }
    $stmtDetailPegawai->close();
}
$templateRows = $rows;

$filteredRows = array_values(array_filter($rows, static function (array $row) use ($statusFilter, $unitFilter, $sumberFilter): bool {
    if ($statusFilter !== 'all' && strtolower($row['status_keterisian']) !== $statusFilter) {
        return false;
    }
    if ($unitFilter !== 'all' && $row['unit_kode'] !== $unitFilter) {
        return false;
    }
    if ($sumberFilter !== 'all' && strtolower((string)($row['sumber'] ?? 'lainnya')) !== $sumberFilter) {
        return false;
    }
    return true;
}));

$countByStatus = ['Belum Terisi' => 0, 'Proses Seleksi' => 0, 'Terisi' => 0, 'Belum Update' => 0];
foreach ($rows as $row) {
    if (isset($countByStatus[$row['status_keterisian']])) {
        $countByStatus[$row['status_keterisian']]++;
    }
}
$statusCardMeta = [
    'Belum Terisi' => ['tone' => 'orange', 'icon' => 'bi-hourglass-split', 'copy' => 'Belum ada kandidat ditempatkan'],
    'Proses Seleksi' => ['tone' => 'cyan', 'icon' => 'bi-people-fill', 'copy' => 'Kandidat sedang diseleksi'],
    'Terisi' => ['tone' => 'green', 'icon' => 'bi-person-check-fill', 'copy' => 'Kebutuhan telah terpenuhi'],
    'Belum Update' => ['tone' => 'red', 'icon' => 'bi-exclamation-triangle-fill', 'copy' => 'Memerlukan pembaruan status'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Karirhub Employer Prototype - Status Keterisian</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php kh_proto_render_styles(); ?>
    <style>
        .kh-fill-page {
            --fill-ink: #172b4d;
            --fill-muted: #6e8196;
            --fill-line: #dfe8f2;
            --fill-blue: #155eef;
        }
        .kh-fill-page .kh-proto-main { color: var(--fill-ink); }
        .fill-hero {
            position: relative;
            overflow: hidden;
            padding: clamp(1.2rem, 3vw, 1.8rem);
            border-radius: 1rem;
            color: #fff;
            background:
                radial-gradient(circle at 88% 12%, rgba(255,255,255,.17), transparent 27%),
                linear-gradient(125deg, #0e4479 0%, #107f9e 54%, #24a9c4 100%);
            box-shadow: 0 15px 34px rgba(16, 127, 158, .2);
        }
        .fill-hero::after {
            position: absolute;
            right: -58px;
            bottom: -96px;
            width: 220px;
            height: 220px;
            border: 35px solid rgba(255,255,255,.07);
            border-radius: 50%;
            content: "";
        }
        .fill-hero-content { position: relative; z-index: 1; }
        .fill-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            margin-bottom: .4rem;
            padding: .25rem .6rem;
            border-radius: 999px;
            color: #dcf7ff;
            font-size: .68rem;
            font-weight: 750;
            letter-spacing: .07em;
            text-transform: uppercase;
            background: rgba(255,255,255,.12);
        }
        .fill-hero h3 { font-size: clamp(1.35rem, 3vw, 1.85rem); font-weight: 760; }
        .fill-hero-copy { color: #dcf7ff; font-size: .82rem; }
        .fill-hero .btn { border-color: rgba(255,255,255,.7); color: #fff; }
        .fill-hero .btn:hover { border-color: #fff; color: #107f9e; background: #fff; }
        .fill-summary-card {
            height: 100%;
            padding: 1rem;
            border: 1px solid var(--fill-line);
            border-radius: .9rem;
            background: #fff;
            box-shadow: 0 8px 22px rgba(36,67,104,.06);
            transition: transform 180ms ease, box-shadow 180ms ease;
        }
        .fill-summary-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 13px 28px rgba(36,67,104,.11);
        }
        .fill-summary-head { display: flex; justify-content: space-between; align-items: flex-start; gap: .75rem; }
        .fill-summary-label {
            color: #64778b;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
        .fill-summary-value { margin-top: .3rem; color: #142b4b; font-size: 1.6rem; font-weight: 780; line-height: 1; }
        .fill-summary-copy { margin-top: .6rem; color: #7b8ca0; font-size: .68rem; }
        .fill-summary-icon { display: grid; width: 40px; height: 40px; place-items: center; border-radius: .72rem; }
        .fill-summary-icon.orange { color: #c46a16; background: #fff4e8; }
        .fill-summary-icon.cyan { color: #1689a8; background: #e9f9fc; }
        .fill-summary-icon.green { color: #087e5b; background: #eaf9f4; }
        .fill-summary-icon.red { color: #c94c5b; background: #fff0f2; }
        .fill-filter-card,
        .fill-table-card {
            overflow: hidden;
            border: 1px solid var(--fill-line) !important;
            border-radius: .95rem !important;
            box-shadow: 0 8px 24px rgba(36,67,104,.06) !important;
        }
        .fill-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .9rem 1.1rem;
            border-bottom: 1px solid #e7edf4;
            background: linear-gradient(135deg, #f8fbff, #f1fbfd);
        }
        .fill-panel-title { color: #1d3c5d; font-size: .87rem; font-weight: 750; }
        .fill-panel-copy { margin-top: .12rem; color: #8090a2; font-size: .65rem; }
        .fill-count {
            display: inline-flex;
            padding: .22rem .55rem;
            border-radius: 999px;
            color: #107f9e;
            font-size: .65rem;
            font-weight: 700;
            background: #e4f7fb;
        }
        .fill-filter-card .form-label { color: #526a82; font-size: .7rem; font-weight: 650; }
        .fill-filter-card .form-control,
        .fill-filter-card .form-select,
        .kh-fill-page .modal .form-control,
        .kh-fill-page .modal .form-select {
            min-height: 40px;
            border-color: #cedae7;
            border-radius: .55rem;
        }
        .fill-table { min-width: 1320px; margin: 0; }
        .fill-table thead th {
            padding: .75rem .65rem;
            border: 0;
            border-bottom: 1px solid #dfe7f0;
            color: #66788c;
            font-size: .6rem;
            font-weight: 750;
            letter-spacing: .04em;
            text-transform: uppercase;
            background: #f8fafd !important;
            white-space: nowrap;
        }
        .fill-table tbody td {
            padding: .78rem .65rem;
            border-color: #ebf0f5;
            color: #354f69;
            font-size: .7rem;
            vertical-align: middle;
        }
        .fill-table tbody tr:hover { background: #fbfdff; }
        .fill-reg { color: #173b61; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-weight: 700; }
        .fill-detail-link { display: inline-block; margin-top: .25rem; color: #155eef; font-size: .62rem; font-weight: 700; text-decoration: none; }
        .fill-detail-link:hover { text-decoration: underline; }
        .fill-source,
        .fill-status {
            display: inline-flex;
            align-items: center;
            gap: .25rem;
            padding: .24rem .5rem;
            border-radius: 999px;
            font-size: .62rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .fill-source.karirhub { color: #137f9a; background: #e8f8fc; }
        .fill-source.other { color: #657286; background: #f0f2f5; }
        .fill-status { color: #526579; background: #f0f3f6; }
        .fill-status.terisi { color: #087e5b; background: #eaf9f4; }
        .fill-status.seleksi { color: #137f9a; background: #e8f8fc; }
        .fill-progress { min-width: 95px; }
        .fill-progress-meta { display: flex; justify-content: space-between; margin-bottom: .25rem; font-size: .62rem; font-weight: 700; }
        .fill-progress-track { height: 5px; overflow: hidden; border-radius: 999px; background: #e9eef4; }
        .fill-progress-bar { height: 100%; border-radius: inherit; background: linear-gradient(90deg, #efa13c, #f1bf72); }
        .fill-progress-bar.complete { background: linear-gradient(90deg, #0b8f69, #3fc39a); }
        .fill-update-actions { display: inline-flex; gap: .28rem; }
        .fill-update-action {
            display: inline-grid;
            width: 30px;
            height: 30px;
            place-items: center;
            border: 1px solid #d8e2ed;
            border-radius: .46rem;
            color: #60758b;
            background: #fff;
            text-decoration: none;
        }
        .fill-update-action:hover { border-color: #8eb9c8; color: #107f9e; background: #f0fafc; }
        .fill-update-action.success { color: #087e5b; }
        .fill-empty { padding: 3rem 1rem !important; color: #7d8ea1 !important; }
        .kh-fill-page .modal-content {
            overflow: hidden;
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 25px 70px rgba(16,42,76,.25);
        }
        .kh-fill-page .modal-header {
            color: #fff;
            border: 0;
            background: linear-gradient(125deg, #0e4479, #107f9e);
        }
        .kh-fill-page .modal-header .btn-close { filter: invert(1); }
        .kh-fill-page .modal-footer { border-top-color: #e6edf5; background: #f8fafd; }
        .fill-modal-card { border: 1px solid #e0e9f2 !important; border-radius: .8rem !important; box-shadow: none !important; }
        .fill-modal-card .card-header { border-bottom-color: #e4ebf3; color: #294966; background: #f6f9fd !important; }
        .fill-employee-card { border: 1px solid #dfe8f2 !important; border-radius: .8rem !important; background: #fbfdff; }
        @media (max-width: 767px) {
            .fill-hero-actions { width: 100%; }
            .fill-hero-actions .btn { width: 100%; }
        }
    </style>
</head>
<body class="kh-proto-page kh-fill-page">
<?php include 'navbar.php'; ?>
<?php kh_proto_render_hero('Daftar Lowongan Kerja', 'Pantau dan simulasikan status keterisian lowongan seperti dashboard employer.', 'Lowongan Kerja', 'karirhub_employer_prototype_pelaporan_lowongan', 'Proyek', 'karirhub_employer_prototype_dashboard_wllp', false); ?>

<div class="kh-content-wrap">
<div class="container py-4">
    <div class="kh-proto-shell">
    <?php kh_proto_render_sidebar('wllp_status_keterisian'); ?>
    <main class="kh-proto-main">
    <section class="fill-hero mb-3">
        <div class="fill-hero-content d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="fill-eyebrow"><i class="bi bi-people-fill"></i> Pemantauan Penempatan</div>
                <h3 class="mb-1">Status Keterisian</h3>
                <div class="fill-hero-copy">Pantau progres seleksi dan perbarui data penempatan tenaga kerja untuk setiap lowongan.</div>
            </div>
            <div class="fill-hero-actions">
                <a class="btn btn-outline-light btn-sm" href="karirhub_employer_prototype_dashboard_wllp">
                    <i class="bi bi-arrow-left me-1"></i>Kembali ke Dashboard WLLP
                </a>
            </div>
        </div>
    </section>

    <?php if ($successMessage !== null): ?>
        <div class="alert alert-success py-2"><?php echo h($successMessage); ?></div>
    <?php endif; ?>
    <?php if (!empty($pegawaiErrors)): ?>
        <div class="alert alert-danger py-2">
            <div class="fw-semibold mb-1">Lengkapi Data Pegawai yang ditempatkan:</div>
            <ul class="mb-0">
                <?php foreach ($pegawaiErrors as $err): ?>
                    <li><?php echo h($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <?php foreach ($countByStatus as $statusName => $statusCount): ?>
            <?php $cardMeta = $statusCardMeta[$statusName]; ?>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="fill-summary-card">
                    <div class="fill-summary-head">
                        <div>
                            <div class="fill-summary-label"><?php echo h($statusName); ?></div>
                            <div class="fill-summary-value"><?php echo h((string)$statusCount); ?></div>
                        </div>
                        <span class="fill-summary-icon <?php echo h($cardMeta['tone']); ?>"><i class="bi <?php echo h($cardMeta['icon']); ?>"></i></span>
                    </div>
                    <div class="fill-summary-copy"><?php echo h($cardMeta['copy']); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <form method="GET" class="card fill-filter-card border-0 shadow-sm mb-3">
        <div class="fill-panel-head">
            <div>
                <div class="fill-panel-title"><i class="bi bi-sliders me-1"></i>Filter Lowongan</div>
                <div class="fill-panel-copy">Saring data berdasarkan status, unit perusahaan, atau sumber</div>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="karirhub_employer_prototype_status_keterisian"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
        </div>
        <div class="card-body p-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label mb-1">Status Keterisian</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="all"<?php echo $statusFilter === 'all' ? ' selected' : ''; ?>>Semua Status</option>
                        <option value="belum terisi"<?php echo $statusFilter === 'belum terisi' ? ' selected' : ''; ?>>Belum Terisi</option>
                        <option value="proses seleksi"<?php echo $statusFilter === 'proses seleksi' ? ' selected' : ''; ?>>Proses Seleksi</option>
                        <option value="terisi"<?php echo $statusFilter === 'terisi' ? ' selected' : ''; ?>>Terisi</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label mb-1">Unit Perusahaan</label>
                    <select name="unit" class="form-select form-select-sm">
                        <option value="all"<?php echo $unitFilter === 'all' ? ' selected' : ''; ?>>Semua Unit</option>
                        <?php foreach ($units as $unitCode => $unit): ?>
                            <option value="<?php echo h($unitCode); ?>"<?php echo $unitFilter === $unitCode ? ' selected' : ''; ?>><?php echo h($unit['nama']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Sumber</label>
                    <select name="sumber" class="form-select form-select-sm">
                        <option value="all"<?php echo $sumberFilter === 'all' ? ' selected' : ''; ?>>Semua Sumber</option>
                        <option value="karirhub"<?php echo $sumberFilter === 'karirhub' ? ' selected' : ''; ?>>Karirhub</option>
                        <option value="lainnya"<?php echo $sumberFilter === 'lainnya' ? ' selected' : ''; ?>>Lainnya</option>
                    </select>
                </div>
                <div class="col-12 col-md-1 d-grid">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Filter</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card fill-table-card border-0 shadow-sm">
        <div class="fill-panel-head">
            <div>
                <div class="fill-panel-title">Daftar Status Lowongan</div>
                <div class="fill-panel-copy">Perbarui tahapan dan data pegawai yang ditempatkan</div>
            </div>
            <span class="fill-count"><?php echo h((string)count($filteredRows)); ?> lowongan</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm fill-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>No. Reg Bukti</th>
                            <th>ID Lowongan</th>
                            <th>Periode</th>
                            <th>Jabatan</th>
                            <th>Unit</th>
                            <th>Sumber</th>
                            <th>Jumlah Kebutuhan</th>
                            <th>Jumlah Penempatan</th>
                            <th>Status Saat Ini</th>
                            <th>Tanggal Lapor</th>
                            <th>Tanggal Terisi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($filteredRows)): ?>
                        <tr><td colspan="12" class="fill-empty"><i class="bi bi-inbox fs-3 d-block mb-2"></i>Tidak ada data.</td></tr>
                    <?php else: ?>
                        <?php foreach ($filteredRows as $row): ?>
                            <?php
                                $kebutuhan = (int)($row['jumlah_kebutuhan'] ?? 0);
                                $penempatan = (int)($row['jumlah_penempatan'] ?? 0);
                                $progressClass = 'secondary';
                                if ($kebutuhan > 0 && $penempatan >= $kebutuhan) {
                                    $progressClass = 'success';
                                } elseif ($penempatan > 0) {
                                    $progressClass = 'warning';
                                }
                                $placementPercentage = $kebutuhan > 0 ? min(100, (int)round(($penempatan / $kebutuhan) * 100)) : 0;
                                $statusCssClass = strtolower((string)$row['status_keterisian']) === 'terisi'
                                    ? 'terisi'
                                    : (strtolower((string)$row['status_keterisian']) === 'proses seleksi' ? 'seleksi' : '');
                            ?>
                            <tr>
                                <td>
                                    <div class="fill-reg"><?php echo h($row['no_reg_bukti']); ?></div>
                                    <a class="fill-detail-link" href="?status=<?php echo h(urlencode($statusFilter)); ?>&unit=<?php echo h(urlencode($unitFilter)); ?>&sumber=<?php echo h(urlencode($sumberFilter)); ?>&detail_no_reg=<?php echo h(urlencode($row['no_reg_bukti'])); ?>&detail_id_lowongan=<?php echo h(urlencode($row['id_lowongan'])); ?>"><i class="bi bi-eye me-1"></i>Lihat Detail</a>
                                </td>
                                <td><?php echo h($row['id_lowongan']); ?></td>
                                <td class="small"><?php echo h(strtoupper((string)$row['periode_tipe']) . ' (' . (string)$row['periode_mulai'] . ' s.d. ' . (string)$row['periode_selesai'] . ')'); ?></td>
                                <td><?php echo h($row['jabatan']); ?></td>
                                <td><?php echo h($units[$row['unit_kode']]['nama'] ?? $row['unit_kode']); ?></td>
                                <td>
                                    <?php $isKarirhubSource = strtolower((string)$row['sumber']) === 'karirhub'; ?>
                                    <span class="fill-source <?php echo $isKarirhubSource ? 'karirhub' : 'other'; ?>">
                                        <i class="bi <?php echo $isKarirhubSource ? 'bi-link-45deg' : 'bi-file-earmark'; ?>"></i><?php echo h((string)$row['sumber']); ?>
                                    </span>
                                </td>
                                <td><?php echo h((string)($row['jumlah_kebutuhan'] ?? 0)); ?></td>
                                <td>
                                    <div class="fill-progress">
                                        <div class="fill-progress-meta"><span><?php echo h((string)$penempatan . ' / ' . (string)$kebutuhan); ?></span><span><?php echo h((string)$placementPercentage); ?>%</span></div>
                                        <div class="fill-progress-track"><div class="fill-progress-bar<?php echo $progressClass === 'success' ? ' complete' : ''; ?>" style="width: <?php echo h((string)$placementPercentage); ?>%;"></div></div>
                                    </div>
                                </td>
                                <td><span class="fill-status <?php echo h($statusCssClass); ?>"><?php echo h($row['status_keterisian']); ?></span></td>
                                <td><?php echo h($row['tanggal_lapor']); ?></td>
                                <td><?php echo h((string)($row['tanggal_terisi'] ?? '-')); ?></td>
                                <td>
                                    <div class="fill-update-actions">
                                        <a class="fill-update-action" title="Set Belum Terisi" aria-label="Set Belum Terisi" href="?status=<?php echo h(urlencode($statusFilter)); ?>&unit=<?php echo h(urlencode($unitFilter)); ?>&sumber=<?php echo h(urlencode($sumberFilter)); ?>&simulate_no_reg=<?php echo h(urlencode($row['no_reg_bukti'])); ?>&simulate_id_lowongan=<?php echo h(urlencode($row['id_lowongan'])); ?>&simulate_status=Belum%20Terisi"><i class="bi bi-hourglass"></i></a>
                                        <a class="fill-update-action" title="Set Proses Seleksi" aria-label="Set Proses Seleksi" href="?status=<?php echo h(urlencode($statusFilter)); ?>&unit=<?php echo h(urlencode($unitFilter)); ?>&sumber=<?php echo h(urlencode($sumberFilter)); ?>&simulate_no_reg=<?php echo h(urlencode($row['no_reg_bukti'])); ?>&simulate_id_lowongan=<?php echo h(urlencode($row['id_lowongan'])); ?>&simulate_status=Proses%20Seleksi"><i class="bi bi-people"></i></a>
                                        <a class="fill-update-action success" title="Lengkapi dan Set Terisi" aria-label="Lengkapi dan Set Terisi" href="?status=<?php echo h(urlencode($statusFilter)); ?>&unit=<?php echo h(urlencode($unitFilter)); ?>&sumber=<?php echo h(urlencode($sumberFilter)); ?>&open_terisi_for=<?php echo h(urlencode($row['no_reg_bukti'])); ?>&open_terisi_id=<?php echo h(urlencode($row['id_lowongan'])); ?>"><i class="bi bi-person-check"></i></a>
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
    </main>
    </div>
</div>
</div>

<div class="modal fade" id="bulkImportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Bulk Import Data Pegawai Ditempatkan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2">
                    Gunakan file dari tombol <strong>Download Template</strong> (data lowongan sudah otomatis terisi dari sistem). Saat import, pastikan kolom wajib terisi: NIK, Nama Lengkap, Pendidikan, Alamat, Status Disabilitas (Iya/Tidak), TMT, Email, dan Nomor Hp.
                </div>
                <div class="mb-3">
                    <label class="form-label mb-1">Pilih file Excel (.xlsx)</label>
                    <input type="file" id="bulkImportFile" class="form-control form-control-sm" accept=".xlsx,.xls">
                </div>
                <div class="d-flex gap-2 mb-3">
                    <button type="button" class="btn btn-primary btn-sm" id="btnProcessBulkImport">
                        <i class="bi bi-upload me-1"></i>Proses Import
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnResetBulkImport">
                        Reset
                    </button>
                </div>
                <div id="bulkImportResult" class="small text-muted">Belum ada proses import.</div>
                <div class="table-responsive mt-2" id="bulkImportPreviewWrap" style="display:none;">
                    <table class="table table-sm table-bordered align-middle mb-0" id="bulkImportPreviewTable">
                        <thead class="table-light"></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($detailRow !== null): ?>
<div class="modal fade show" id="detailWllpModal" tabindex="-1" aria-modal="true" role="dialog" style="display:block; background: rgba(0,0,0,0.35); overflow-y:auto; -webkit-overflow-scrolling:touch;">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-height: calc(100vh - 2rem);">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-briefcase-fill me-2"></i>Detail WLLP</h5>
                <a href="?status=<?php echo h(urlencode($statusFilter)); ?>&unit=<?php echo h(urlencode($unitFilter)); ?>&sumber=<?php echo h(urlencode($sumberFilter)); ?>" class="btn-close"></a>
            </div>
            <div class="modal-body" style="max-height: calc(100vh - 220px); overflow-y: auto; -webkit-overflow-scrolling: touch;">
                <div class="alert alert-primary py-2 mb-3">
                    <div class="fw-semibold mb-1">Panduan Singkat Bulk Import</div>
                    <ul class="mb-2 ps-3 small">
                        <li>Langkah 1: Klik <strong>Download Template</strong> untuk mengambil format terbaru.</li>
                        <li>Langkah 2: Isi data pegawai sesuai kolom wajib, lalu simpan file Excel.</li>
                        <li>Langkah 3: Klik <strong>Bulk Import</strong> untuk unggah file dan cek hasil validasi.</li>
                        <li>Catatan: Gunakan file template resmi agar header sesuai.</li>
                    </ul>
                    <div class="d-flex flex-wrap justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-success btn-sm" id="btnDownloadTemplate">
                            <i class="bi bi-download me-1"></i>Download Template
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#bulkImportModal">
                            <i class="bi bi-file-earmark-arrow-up me-1"></i>Bulk Import
                        </button>
                    </div>
                </div>
                <div class="card fill-modal-card border-0 shadow-sm mb-3">
                    <div class="card-header bg-light fw-semibold">Informasi Lowongan Pekerjaan</div>
                    <div class="card-body">
                        <?php if ($detailLowonganInfo === null): ?>
                            <div class="text-muted small">Detail lowongan tidak ditemukan.</div>
                        <?php else: ?>
                            <div class="row g-3 small">
                                <div class="col-12 col-md-4"><span class="text-muted">No. Reg Bukti</span><div class="fw-semibold"><?php echo h((string)$detailLowonganInfo['no_reg_bukti']); ?></div></div>
                                <div class="col-12 col-md-4"><span class="text-muted">ID Lowongan</span><div class="fw-semibold"><?php echo h((string)$detailLowonganInfo['id_lowongan']); ?></div></div>
                                <div class="col-12 col-md-4"><span class="text-muted">Unit</span><div class="fw-semibold"><?php echo h((string)$detailLowonganInfo['unit_nama']); ?></div></div>
                                <div class="col-12 col-md-6"><span class="text-muted">Jabatan</span><div class="fw-semibold"><?php echo h((string)$detailLowonganInfo['jabatan']); ?></div></div>
                                <div class="col-12 col-md-3"><span class="text-muted">Jumlah Kebutuhan</span><div class="fw-semibold"><?php echo h((string)$detailLowonganInfo['jumlah_kebutuhan']); ?></div></div>
                                <div class="col-12 col-md-4"><span class="text-muted">Jenis Kelamin</span><div class="fw-semibold"><?php echo h((string)$detailLowonganInfo['jenis_kelamin']); ?></div></div>
                                <div class="col-12 col-md-4"><span class="text-muted">Usia</span><div class="fw-semibold"><?php echo h((string)$detailLowonganInfo['usia_min'] . ' - ' . (string)$detailLowonganInfo['usia_max'] . ' tahun'); ?></div></div>
                                <div class="col-12 col-md-4"><span class="text-muted">Pendidikan Minimal</span><div class="fw-semibold"><?php echo h((string)$detailLowonganInfo['pendidikan_minimal']); ?></div></div>
                                <div class="col-12 col-md-4"><span class="text-muted">Pengalaman Minimal</span><div class="fw-semibold"><?php echo h((string)$detailLowonganInfo['pengalaman_min_tahun']); ?> tahun</div></div>
                                <div class="col-12 col-md-4"><span class="text-muted">Rentang Gaji</span><div class="fw-semibold"><?php echo h((string)$detailLowonganInfo['rentang_gaji']); ?></div></div>
                                <div class="col-12 col-md-4"><span class="text-muted">Masa Berlaku</span><div class="fw-semibold"><?php echo h((string)$detailLowonganInfo['masa_berlaku_mulai'] . ' s.d. ' . (string)$detailLowonganInfo['masa_berlaku_sampai']); ?></div></div>
                                <div class="col-12"><span class="text-muted">Keterampilan Utama</span><div class="fw-semibold"><?php echo h((string)$detailLowonganInfo['keterampilan_utama']); ?></div></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card fill-modal-card border-0 shadow-sm">
                    <div class="card-header bg-light fw-semibold">Data Pegawai yang Ditempatkan</div>
                    <div class="card-body">
                        <?php if (empty($detailPegawaiRows)): ?>
                            <div class="text-muted small">Belum ada data pegawai yang ditempatkan.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>NIK</th>
                                            <th>Nama Lengkap</th>
                                            <th>Pendidikan</th>
                                            <th>Tanggal Mulai Kerja</th>
                                            <th>Status Disabilitas</th>
                                            <th>Email</th>
                                            <th>Nomor Hp</th>
                                            <th>Alamat</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($detailPegawaiRows as $pegawaiDetail): ?>
                                            <tr>
                                                <td><?php echo h((string)$pegawaiDetail['urutan_penempatan']); ?></td>
                                                <td><?php echo h((string)$pegawaiDetail['nik']); ?></td>
                                                <td><?php echo h((string)$pegawaiDetail['nama_lengkap']); ?></td>
                                                <td><?php echo h((string)$pegawaiDetail['pendidikan']); ?></td>
                                                <td><?php echo h((string)$pegawaiDetail['tmt']); ?></td>
                                                <td><?php echo h((string)$pegawaiDetail['status_disabilitas']); ?></td>
                                                <td><?php echo h((string)$pegawaiDetail['email']); ?></td>
                                                <td><?php echo h((string)$pegawaiDetail['nomor_hp']); ?></td>
                                                <td><?php echo h((string)$pegawaiDetail['alamat']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="?status=<?php echo h(urlencode($statusFilter)); ?>&unit=<?php echo h(urlencode($unitFilter)); ?>&sumber=<?php echo h(urlencode($sumberFilter)); ?>" class="btn btn-outline-secondary btn-sm">Tutup</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($openTerisiRow !== null): ?>
<div class="modal fade show" id="terisiPegawaiModal" tabindex="-1" aria-modal="true" role="dialog" style="display:block; background: rgba(0,0,0,0.35); overflow-y:auto; -webkit-overflow-scrolling:touch;">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" style="max-height: calc(100vh - 2rem);">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-check-fill me-2"></i>Lengkapi Data Pegawai yang Ditempatkan</h5>
                <a href="?status=<?php echo h(urlencode($statusFilter)); ?>&unit=<?php echo h(urlencode($unitFilter)); ?>&sumber=<?php echo h(urlencode($sumberFilter)); ?>" class="btn-close"></a>
            </div>
            <form method="POST">
                <div class="modal-body" style="max-height: calc(100vh - 220px); overflow-y: auto; -webkit-overflow-scrolling: touch;">
                    <div class="small text-muted mb-2">
                        No. Reg Bukti: <strong><?php echo h($openTerisiRow['no_reg_bukti']); ?></strong> &middot;
                        ID Lowongan: <strong><?php echo h($openTerisiRow['id_lowongan']); ?></strong> &middot;
                        Jabatan: <strong><?php echo h($openTerisiRow['jabatan']); ?></strong> &middot;
                        Jumlah Kebutuhan: <strong><?php echo h((string)$openTerisiJumlahKebutuhan); ?></strong>
                    </div>
                    <input type="hidden" name="form_action" value="submit_terisi_data">
                    <input type="hidden" name="no_reg_bukti" value="<?php echo h($openTerisiRow['no_reg_bukti']); ?>">
                    <input type="hidden" name="id_lowongan" value="<?php echo h($openTerisiRow['id_lowongan']); ?>">
                    <input type="hidden" name="jumlah_kebutuhan_target" value="<?php echo h((string)$openTerisiJumlahKebutuhan); ?>">
                    <input type="hidden" name="status" value="<?php echo h($statusFilter); ?>">
                    <input type="hidden" name="unit" value="<?php echo h($unitFilter); ?>">
                    <input type="hidden" name="sumber" value="<?php echo h($sumberFilter); ?>">
                    <?php foreach ($pegawaiFormRows as $pegawaiIndex => $pegawaiForm): ?>
                    <div class="fill-employee-card border rounded p-3 mb-3">
                        <div class="fw-semibold small mb-2"><i class="bi bi-person-badge text-primary me-1"></i>Data Pegawai <?php echo h((string)($pegawaiIndex + 1)); ?> dari <?php echo h((string)$openTerisiJumlahKebutuhan); ?></div>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label mb-1">NIK</label>
                                <input type="text" name="nik[]" class="form-control form-control-sm" value="<?php echo h($pegawaiForm['nik']); ?>">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label mb-1">Nama Lengkap</label>
                                <input type="text" name="nama_lengkap[]" class="form-control form-control-sm" value="<?php echo h($pegawaiForm['nama_lengkap']); ?>">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label mb-1">Pendidikan</label>
                                <input type="text" name="pendidikan[]" class="form-control form-control-sm" value="<?php echo h($pegawaiForm['pendidikan']); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label mb-1">Alamat</label>
                                <textarea name="alamat[]" class="form-control form-control-sm" rows="2"><?php echo h($pegawaiForm['alamat']); ?></textarea>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label mb-1">Status Disabilitas</label>
                                <select name="status_disabilitas[]" class="form-select form-select-sm">
                                    <option value="">Pilih</option>
                                    <option value="Iya"<?php echo $pegawaiForm['status_disabilitas'] === 'Iya' ? ' selected' : ''; ?>>Iya</option>
                                    <option value="Tidak"<?php echo $pegawaiForm['status_disabilitas'] === 'Tidak' ? ' selected' : ''; ?>>Tidak</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label mb-1">Tanggal Mulai Kerja</label>
                                <input type="date" name="tmt[]" class="form-control form-control-sm" value="<?php echo h($pegawaiForm['tmt']); ?>">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label mb-1">Email</label>
                                <input type="email" name="email[]" class="form-control form-control-sm" value="<?php echo h($pegawaiForm['email']); ?>">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label mb-1">Nomor Hp</label>
                                <input type="text" name="nomor_hp[]" class="form-control form-control-sm" value="<?php echo h($pegawaiForm['nomor_hp']); ?>">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="modal-footer">
                    <a href="?status=<?php echo h(urlencode($statusFilter)); ?>&unit=<?php echo h(urlencode($unitFilter)); ?>&sumber=<?php echo h(urlencode($sumberFilter)); ?>" class="btn btn-outline-secondary btn-sm">Batal</a>
                    <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check2-circle me-1"></i>Simpan Data & Set Terisi</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<?php kh_proto_render_sidebar_script(); ?>
<script>
    (function () {
        const headers = [
            'No Reg Bukti',
            'ID Lowongan',
            'Jabatan',
            'Unit',
            'Status Saat Ini',
            'Tanggal Lapor',
            'Tanggal Terisi',
            'NIK',
            'Nama Lengkap',
            'Pendidikan',
            'Alamat',
            'Status Disabilitas',
            'Tanggal Mulai Kerja',
            'Email',
            'Nomor Hp',
        ];
        const templateRowsFromDb = <?php echo json_encode($templateRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        const btnDownload = document.getElementById('btnDownloadTemplate');
        const btnProcess = document.getElementById('btnProcessBulkImport');
        const btnReset = document.getElementById('btnResetBulkImport');
        const fileInput = document.getElementById('bulkImportFile');
        const resultEl = document.getElementById('bulkImportResult');
        const previewWrap = document.getElementById('bulkImportPreviewWrap');
        const previewTable = document.getElementById('bulkImportPreviewTable');

        if (btnDownload) {
            btnDownload.addEventListener('click', function () {
                const rows = templateRowsFromDb.map(function (row) {
                    return [
                        row.no_reg_bukti || '',
                        row.id_lowongan || '',
                        row.jabatan || '',
                        row.unit_nama || '',
                        row.status_saat_ini || '',
                        row.tanggal_lapor || '',
                        row.tanggal_terisi || '',
                        row.nik || '',
                        row.nama_lengkap || '',
                        row.pendidikan || '',
                        row.alamat || '',
                        row.status_disabilitas || '',
                        row.tmt || '',
                        row.email || '',
                        row.nomor_hp || '',
                    ];
                });
                if (!rows.length) {
                    rows.push(['', '', '', '', '', '', '', '', '', '', '', '', '', '', '']);
                }
                const ws = XLSX.utils.aoa_to_sheet([headers].concat(rows));
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Template Import');
                XLSX.writeFile(wb, 'template_bulk_import_pegawai_wllp.xlsx');
            });
        }

        function resetImportState() {
            if (fileInput) fileInput.value = '';
            if (resultEl) {
                resultEl.className = 'small text-muted';
                resultEl.textContent = 'Belum ada proses import.';
            }
            if (previewWrap) previewWrap.style.display = 'none';
            if (previewTable) {
                previewTable.querySelector('thead').innerHTML = '';
                previewTable.querySelector('tbody').innerHTML = '';
            }
        }

        if (btnReset) {
            btnReset.addEventListener('click', resetImportState);
        }

        function validateHeaders(actualHeaders) {
            if (actualHeaders.length < headers.length) return false;
            for (let i = 0; i < headers.length; i += 1) {
                if ((actualHeaders[i] || '').trim() !== headers[i]) return false;
            }
            return true;
        }

        if (btnProcess) {
            btnProcess.addEventListener('click', function () {
                if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                    resultEl.className = 'alert alert-warning py-2 mb-0';
                    resultEl.textContent = 'Silakan pilih file Excel terlebih dahulu.';
                    return;
                }

                const file = fileInput.files[0];
                const reader = new FileReader();
                reader.onload = function (evt) {
                    try {
                        const data = new Uint8Array(evt.target.result);
                        const workbook = XLSX.read(data, { type: 'array' });
                        const firstSheetName = workbook.SheetNames[0];
                        const sheet = workbook.Sheets[firstSheetName];
                        const rows = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });

                        if (!rows.length) {
                            resultEl.className = 'alert alert-danger py-2 mb-0';
                            resultEl.textContent = 'File kosong. Gunakan template yang sudah disediakan.';
                            return;
                        }

                        const headerRow = rows[0].map((cell) => String(cell).trim());
                        if (!validateHeaders(headerRow)) {
                            resultEl.className = 'alert alert-danger py-2 mb-0';
                            resultEl.textContent = 'Header tidak sesuai template. Silakan download ulang template.';
                            return;
                        }

                        const dataRows = rows.slice(1).filter((r) => r.some((cell) => String(cell).trim() !== ''));
                        let validCount = 0;
                        const errors = [];

                        dataRows.forEach((r, idx) => {
                            const rowNumber = idx + 2;
                            const map = {};
                            headers.forEach((h, i) => { map[h] = String(r[i] || '').trim(); });

                            const missing = headers.filter((h) => map[h] === '');
                            if (missing.length) {
                                errors.push('Baris ' + rowNumber + ': kolom kosong -> ' + missing.join(', '));
                                return;
                            }
                            if (!['Iya', 'Tidak'].includes(map['Status Disabilitas'])) {
                                errors.push('Baris ' + rowNumber + ': Status Disabilitas harus Iya/Tidak.');
                                return;
                            }
                            validCount += 1;
                        });

                        const previewRows = dataRows.slice(0, 5);
                        if (previewRows.length) {
                            previewWrap.style.display = '';
                            previewTable.querySelector('thead').innerHTML = '<tr>' + headers.map((h) => '<th>' + h + '</th>').join('') + '</tr>';
                            previewTable.querySelector('tbody').innerHTML = previewRows.map((r) => '<tr>' + headers.map((_, i) => '<td>' + String(r[i] || '') + '</td>').join('') + '</tr>').join('');
                        } else {
                            previewWrap.style.display = 'none';
                        }

                        if (errors.length) {
                            resultEl.className = 'alert alert-warning py-2 mb-0';
                            resultEl.innerHTML =
                                '<strong>Import selesai dengan catatan.</strong><br>' +
                                'Total baris: ' + dataRows.length + ', valid: ' + validCount + ', invalid: ' + errors.length +
                                '<br><small>' + errors.slice(0, 5).join('<br>') + (errors.length > 5 ? '<br>...dan lainnya.' : '') + '</small>';
                        } else {
                            resultEl.className = 'alert alert-success py-2 mb-0';
                            resultEl.textContent = 'Import berhasil. Total baris valid: ' + validCount + ' (simulasi prototype, belum disimpan permanen).';
                        }
                    } catch (err) {
                        resultEl.className = 'alert alert-danger py-2 mb-0';
                        resultEl.textContent = 'Gagal membaca file Excel: ' + (err && err.message ? err.message : String(err));
                    }
                };
                reader.readAsArrayBuffer(file);
            });
        }
    })();
</script>
</body>
</html>
