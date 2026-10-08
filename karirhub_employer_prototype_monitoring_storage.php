<?php

require_once __DIR__ . '/db.php';

function kh_monitoring_period_days(int $days): int
{
    return in_array($days, [7, 30, 90], true) ? $days : 30;
}

function kh_monitoring_ensure_tables(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS karirhub_proto_reporters (
            reporter_id VARCHAR(40) PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(40) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_kh_reporter_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS karirhub_proto_monitoring_employers (
            employer_id VARCHAR(40) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            employer_type VARCHAR(80) NOT NULL DEFAULT 'Perusahaan',
            business_field VARCHAR(190) DEFAULT NULL,
            email VARCHAR(190) DEFAULT NULL,
            phone VARCHAR(40) DEFAULT NULL,
            website VARCHAR(255) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            verification_status VARCHAR(40) NOT NULL DEFAULT 'VERIFIED',
            enforcement_status VARCHAR(40) NOT NULL DEFAULT 'ACTIVE',
            blocked_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_kh_employer_enforcement (enforcement_status, blocked_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS karirhub_proto_monitoring_vacancies (
            vacancy_id VARCHAR(40) PRIMARY KEY,
            employer_id VARCHAR(40) NOT NULL,
            title VARCHAR(255) NOT NULL,
            location VARCHAR(255) NOT NULL,
            job_field VARCHAR(190) DEFAULT NULL,
            job_type VARCHAR(80) DEFAULT NULL,
            posted_at DATE DEFAULT NULL,
            deadline DATE DEFAULT NULL,
            publication_status VARCHAR(40) NOT NULL DEFAULT 'ACTIVE',
            enforcement_status VARCHAR(40) NOT NULL DEFAULT 'ACTIVE',
            blocked_at DATETIME DEFAULT NULL,
            description TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_kh_vacancy_employer (employer_id),
            KEY idx_kh_vacancy_enforcement (enforcement_status, blocked_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS karirhub_proto_monitoring_reports (
            report_id VARCHAR(40) PRIMARY KEY,
            object_type ENUM('vacancy','company') NOT NULL,
            reporter_id VARCHAR(40) NOT NULL,
            employer_id VARCHAR(40) NOT NULL,
            vacancy_id VARCHAR(40) DEFAULT NULL,
            subject VARCHAR(255) NOT NULL,
            region VARCHAR(255) NOT NULL,
            reason VARCHAR(255) NOT NULL,
            comment TEXT DEFAULT NULL,
            evidence TEXT DEFAULT NULL,
            severity VARCHAR(40) NOT NULL,
            sla_status VARCHAR(40) NOT NULL,
            verification_status VARCHAR(40) NOT NULL,
            assigned_to VARCHAR(120) DEFAULT NULL,
            snapshot TEXT DEFAULT NULL,
            current_data TEXT DEFAULT NULL,
            submitted_at DATETIME NOT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_kh_report_period (submitted_at),
            KEY idx_kh_report_status (verification_status, submitted_at),
            KEY idx_kh_report_reporter (reporter_id, submitted_at),
            KEY idx_kh_report_vacancy (vacancy_id, submitted_at),
            KEY idx_kh_report_employer (employer_id, submitted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    kh_monitoring_seed($conn);
}

function kh_monitoring_seed(mysqli $conn): void
{
    $reporters = [
        ['USR-73100', 'Rina', 'rina@mail.com', '0812-7788-3100'],
        ['USR-92811', 'Pelapor Finaccel', 'email@example.com', '0812-3456-7811'],
        ['USR-74410', 'Andi', 'andi@mail.com', '0813-9900-1100'],
        ['USR-81221', 'John', 'john@mail.com', '0813-2211-8844'],
        ['USR-76602', 'Sari', 'sari@mail.com', '0812-4488-9933'],
        ['USR-70283', 'Budi', 'budi@mail.com', '0811-2233-7788'],
        ['USR-79514', 'Dewi', 'dewi@mail.com', '0812-5566-8822'],
        ['USR-78642', 'Fajar', 'fajar@mail.com', '0813-6677-9911'],
    ];
    $stmt = $conn->prepare("
        INSERT IGNORE INTO karirhub_proto_reporters (reporter_id, name, email, phone)
        VALUES (?, ?, ?, ?)
    ");
    foreach ($reporters as $row) {
        $stmt->bind_param('ssss', $row[0], $row[1], $row[2], $row[3]);
        $stmt->execute();
    }
    $stmt->close();

    $employers = [
        ['EMP-001', 'PT Finaccel Finance Indonesia', 'Perusahaan', 'Banking & Financial Services', 'hr@finaccel.co.id', '0211500987', 'www.finaccel.co.id', 'Jakarta Pusat - DKI Jakarta', 'VERIFIED', 'ACTIVE', null],
        ['EMP-002', 'CV Maju Sejahtera', 'Perusahaan', 'Retail', 'hr@majusejahtera.co.id', '0215550102', 'www.majusejahtera.co.id', 'Tangerang - Banten', 'VERIFIED', 'ACTIVE', null],
        ['EMP-003', 'PT Maju Karier Nusantara', 'Perusahaan', 'Human Resources & Recruitment', 'info@majukarier.co.id', '0221234567', 'www.maju-karier.co.id', 'Bandung - Jawa Barat', 'VERIFIED', 'BLOCKED', 'DATE_SUB(NOW(), INTERVAL 4 DAY)'],
        ['EMP-004', 'PT Samudra Arta', 'Perusahaan', 'Financial Services', 'contact@samudraarta.co.id', '0411555011', null, 'Makassar - Sulawesi Selatan', 'VERIFIED', 'ACTIVE', null],
        ['EMP-005', 'CV Mitra Giat Sentosa', 'Lembaga', 'Outsourcing', 'info@mitragiat.co.id', '0315550188', null, 'Surabaya - Jawa Timur', 'VERIFIED', 'BLOCKED', 'DATE_SUB(NOW(), INTERVAL 7 DAY)'],
    ];
    $stmt = $conn->prepare("
        INSERT IGNORE INTO karirhub_proto_monitoring_employers
            (employer_id, name, employer_type, business_field, email, phone, website, address, verification_status, enforcement_status, blocked_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CASE WHEN ? IS NULL THEN NULL ELSE DATE_SUB(NOW(), INTERVAL ? DAY) END)
    ");
    foreach ($employers as $row) {
        $blockedDays = $row[10] === null ? null : ($row[0] === 'EMP-003' ? '4' : '7');
        $stmt->bind_param('ssssssssssss', $row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8], $row[9], $blockedDays, $blockedDays);
        $stmt->execute();
    }
    $stmt->close();

    $vacancies = [
        ['VAC-99311', 'EMP-001', 'Sales Executive - DKI Jakarta', 'Jakarta Timur - DKI Jakarta', 'Penjualan dan Pengembangan Bisnis', 'Full time', 'ACTIVE', 'BLOCKED', 3, 'Melakukan riset pasar, mengembangkan peluang penjualan, dan menjaga hubungan dengan pelanggan.'],
        ['VAC-88210', 'EMP-002', 'Kasir', 'Tangerang - Banten', 'Retail', 'Full time', 'ACTIVE', 'ACTIVE', null, 'Melayani transaksi pelanggan dan memastikan administrasi kas berjalan baik.'],
        ['VAC-77104', 'EMP-004', 'Finance Accounting', 'Makassar - Sulawesi Selatan', 'Keuangan', 'Full time', 'ACTIVE', 'BLOCKED', 6, 'Mengelola pencatatan transaksi, rekonsiliasi, dan laporan keuangan perusahaan.'],
    ];
    $stmt = $conn->prepare("
        INSERT IGNORE INTO karirhub_proto_monitoring_vacancies
            (vacancy_id, employer_id, title, location, job_field, job_type, posted_at, deadline, publication_status, enforcement_status, blocked_at, description)
        VALUES (?, ?, ?, ?, ?, ?, DATE_SUB(CURDATE(), INTERVAL 20 DAY), DATE_ADD(CURDATE(), INTERVAL 20 DAY), ?, ?,
            CASE WHEN ? IS NULL THEN NULL ELSE DATE_SUB(NOW(), INTERVAL ? DAY) END, ?)
    ");
    foreach ($vacancies as $row) {
        $blockedDays = $row[8] === null ? null : (string)$row[8];
        $stmt->bind_param('sssssssssss', $row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $blockedDays, $blockedDays, $row[9]);
        $stmt->execute();
    }
    $stmt->close();

    $reports = [
        ['VRP-2026-304511', 'vacancy', 'USR-73100', 'EMP-001', 'VAC-99311', 'Sales Executive - DKI Jakarta', 'Jakarta Timur - DKI Jakarta', 'Meminta biaya / pembayaran', 'Ada biaya pendaftaran dan biaya pelatihan sebelum offering.', 'biaya_pelatihan.png, invoice.pdf', 'High', 'Approaching', 'PENDING_REVIEW', null, 'vacancy_id=VAC-99311, publication_status=ACTIVE, source_flag=NATIVE.', 'Lowongan telah diblokir sambil menunggu hasil akhir pemeriksaan.', 2],
        ['CRP-2026-103421', 'company', 'USR-92811', 'EMP-001', null, 'PT Finaccel Finance Indonesia', 'Jakarta Pusat - DKI Jakarta', 'Meminta biaya / pembayaran', 'Pelamar diminta transfer biaya administrasi sebelum interview.', 'bukti_transfer.jpg, screenshot_chat.pdf', 'Urgent', 'Approaching', 'PENDING_REVIEW', null, 'Profil terverifikasi dengan enforcement status aktif.', 'Perusahaan masih terverifikasi dan memiliki lowongan aktif.', 3],
        ['VRP-2026-304477', 'vacancy', 'USR-74410', 'EMP-002', 'VAC-88210', 'Kasir', 'Tangerang - Banten', 'Informasi menyesatkan', 'Deskripsi pekerjaan berbeda dengan penjelasan saat dihubungi.', 'screenshot_chat.png', 'Medium', 'On Time', 'IN_REVIEW', 'admin.kabkota.tng', 'vacancy_id=VAC-88210, publication_status=ACTIVE.', 'Lowongan masih tayang dan sedang diverifikasi.', 4],
        ['CRP-2026-103109', 'company', 'USR-81221', 'EMP-003', null, 'PT Maju Karier Nusantara', 'Bandung - Jawa Barat', 'Perusahaan palsu / informasi menyesatkan', 'Alamat domain email berbeda dengan profil legal perusahaan.', 'domain_mismatch.png', 'Urgent', 'On Time', 'SELESAI', 'admin.kabkota.bdg', 'company_id=EMP-003, verification_status=VERIFIED.', 'Akun pemberi kerja telah diblokir setelah pemeriksaan.', 5],
        ['VRP-2026-304220', 'vacancy', 'USR-76602', 'EMP-004', 'VAC-77104', 'Finance Accounting', 'Makassar - Sulawesi Selatan', 'Data pribadi / kredensial', 'Pelamar diminta mengirimkan kredensial akun pribadi.', 'permintaan_kredensial.pdf', 'High', 'Overdue', 'IN_REVIEW', 'admin.pusat.layanan', 'vacancy_id=VAC-77104, publication_status=ACTIVE.', 'Lowongan diblokir selama proses verifikasi.', 6],
        ['CRP-2026-102883', 'company', 'USR-70283', 'EMP-005', null, 'CV Mitra Giat Sentosa', 'Surabaya - Jawa Timur', 'Praktik diskriminatif', 'Persyaratan lowongan memuat pembatasan yang tidak relevan.', 'screenshot_lowongan.jpg', 'Medium', 'Overdue', 'SELESAI', 'admin.pusat.layanan', 'company_id=EMP-005, verification_status=VERIFIED.', 'Akun pemberi kerja telah diblokir.', 7],
        ['VRP-2026-304188', 'vacancy', 'USR-79514', 'EMP-001', 'VAC-99311', 'Sales Executive - DKI Jakarta', 'Jakarta Timur - DKI Jakarta', 'Penipuan', 'Pelapor menerima pesan rekrutmen palsu yang mengatasnamakan perusahaan.', 'pesan_rekrutmen_palsu.png', 'Urgent', 'On Time', 'PENDING_REVIEW', null, 'vacancy_id=VAC-99311, publication_status=ACTIVE.', 'Laporan menunggu pemeriksaan awal.', 8],
        ['VRP-2026-304155', 'vacancy', 'USR-78642', 'EMP-002', 'VAC-88210', 'Kasir', 'Tangerang - Banten', 'Lowongan fiktif', 'Alamat tempat kerja dan posisi yang ditawarkan tidak dapat diverifikasi.', 'bukti_alamat.png', 'High', 'On Time', 'IN_REVIEW', 'admin.kabkota.tng', 'vacancy_id=VAC-88210, publication_status=ACTIVE.', 'Lowongan sedang diverifikasi oleh admin wilayah.', 9],
    ];
    $stmt = $conn->prepare("
        INSERT IGNORE INTO karirhub_proto_monitoring_reports
            (report_id, object_type, reporter_id, employer_id, vacancy_id, subject, region, reason, comment, evidence,
             severity, sla_status, verification_status, assigned_to, snapshot, current_data, submitted_at, reviewed_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY),
            CASE WHEN ? = 'SELESAI' THEN DATE_SUB(NOW(), INTERVAL 1 DAY) ELSE NULL END)
    ");
    foreach ($reports as $row) {
        $stmt->bind_param(
            'ssssssssssssssssis',
            $row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8], $row[9],
            $row[10], $row[11], $row[12], $row[13], $row[14], $row[15], $row[16], $row[12]
        );
        $stmt->execute();
    }
    $stmt->close();
}

function kh_monitoring_fetch_all(mysqli_stmt $stmt): array
{
    $stmt->execute();
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function kh_monitoring_summary(mysqli $conn, int $days): array
{
    $days = kh_monitoring_period_days($days);
    $sql = "
        SELECT
            COUNT(*) AS total_reports,
            COUNT(DISTINCT reporter_id) AS total_reporters,
            COUNT(DISTINCT vacancy_id) AS total_vacancies,
            SUM(verification_status = 'PENDING_REVIEW') AS pending_reports,
            SUM(verification_status = 'IN_REVIEW') AS reviewing_reports,
            SUM(verification_status = 'SELESAI') AS completed_reports
        FROM karirhub_proto_monitoring_reports
        WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $days);
    $rows = kh_monitoring_fetch_all($stmt);
    $stmt->close();
    $base = $rows[0] ?? [];

    $stmt = $conn->prepare("
        SELECT
            (SELECT COUNT(*) FROM karirhub_proto_monitoring_vacancies
             WHERE enforcement_status = 'BLOCKED' AND blocked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)) AS blocked_vacancies,
            (SELECT COUNT(*) FROM karirhub_proto_monitoring_employers
             WHERE enforcement_status = 'BLOCKED' AND blocked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)) AS blocked_employers
    ");
    $stmt->bind_param('ii', $days, $days);
    $blockedRows = kh_monitoring_fetch_all($stmt);
    $stmt->close();
    return array_map('intval', array_merge($base, $blockedRows[0] ?? []));
}

function kh_monitoring_reasons(mysqli $conn, int $days): array
{
    $days = kh_monitoring_period_days($days);
    $stmt = $conn->prepare("
        SELECT reason AS label, COUNT(*) AS value
        FROM karirhub_proto_monitoring_reports
        WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
          AND object_type = 'vacancy'
        GROUP BY reason
        ORDER BY value DESC, reason ASC
        LIMIT 5
    ");
    $stmt->bind_param('i', $days);
    $rows = kh_monitoring_fetch_all($stmt);
    $stmt->close();
    $max = (int)($rows[0]['value'] ?? 0);
    foreach ($rows as &$row) {
        $row['value'] = (int)$row['value'];
        $row['percent'] = $max > 0 ? (int)round(($row['value'] / $max) * 100) : 0;
    }
    unset($row);
    return $rows;
}

function kh_monitoring_regions(mysqli $conn, int $days): array
{
    $days = kh_monitoring_period_days($days);
    $stmt = $conn->prepare("
        SELECT region AS label, COUNT(*) AS value
        FROM karirhub_proto_monitoring_reports
        WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY region
        ORDER BY value DESC, region ASC
        LIMIT 5
    ");
    $stmt->bind_param('i', $days);
    $rows = kh_monitoring_fetch_all($stmt);
    $stmt->close();
    foreach ($rows as &$row) {
        $row['value'] = (int)$row['value'];
    }
    unset($row);
    return $rows;
}

function kh_monitoring_recent_reports(mysqli $conn, int $days, int $limit = 20): array
{
    $days = kh_monitoring_period_days($days);
    $limit = max(1, min($limit, 100));
    $stmt = $conn->prepare("
        SELECT r.report_id AS id, r.object_type AS detail_type,
               CASE WHEN r.object_type = 'vacancy' THEN 'Lowongan' ELSE 'Perusahaan' END AS type,
               r.subject, e.name AS company, r.region, r.reason, r.severity, r.sla_status AS sla,
               CASE r.verification_status
                   WHEN 'PENDING_REVIEW' THEN 'Menunggu Verifikasi'
                   WHEN 'IN_REVIEW' THEN 'Dalam Verifikasi'
                   ELSE 'Selesai'
               END AS status,
               COALESCE(r.assigned_to, '-') AS assigned_to
        FROM karirhub_proto_monitoring_reports r
        JOIN karirhub_proto_monitoring_employers e ON e.employer_id = r.employer_id
        WHERE r.submitted_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ORDER BY r.submitted_at DESC
        LIMIT ?
    ");
    $stmt->bind_param('ii', $days, $limit);
    $rows = kh_monitoring_fetch_all($stmt);
    $stmt->close();
    return $rows;
}

function kh_monitoring_list(mysqli $conn, string $card, int $days, string $reason = ''): array
{
    $days = kh_monitoring_period_days($days);
    $reportCondition = '';
    $reportCards = [
        'reports' => '',
        'pending' => " AND r.verification_status = 'PENDING_REVIEW'",
        'reviewing' => " AND r.verification_status = 'IN_REVIEW'",
        'completed' => " AND r.verification_status = 'SELESAI'",
    ];
    if ($card === 'reason' && $reason !== '') {
        $stmt = $conn->prepare("
            SELECT r.report_id AS id, 'report' AS record_type, r.subject AS title,
                   CONCAT(CASE WHEN r.object_type = 'vacancy' THEN 'Lowongan' ELSE 'Perusahaan' END, ' · ', e.name) AS subtitle,
                   CONCAT(r.region, ' · ', r.reason) AS meta,
                   CASE r.verification_status
                       WHEN 'PENDING_REVIEW' THEN 'Menunggu Verifikasi'
                       WHEN 'IN_REVIEW' THEN 'Dalam Verifikasi'
                       ELSE 'Selesai'
                   END AS status,
                   DATE_FORMAT(r.submitted_at, '%d %b %Y %H:%i') AS date_text
            FROM karirhub_proto_monitoring_reports r
            JOIN karirhub_proto_monitoring_employers e ON e.employer_id = r.employer_id
            WHERE r.submitted_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
              AND r.object_type = 'vacancy'
              AND r.reason = ?
            ORDER BY r.submitted_at DESC
        ");
        $stmt->bind_param('is', $days, $reason);
    } elseif (array_key_exists($card, $reportCards)) {
        $reportCondition = $reportCards[$card];
        $stmt = $conn->prepare("
            SELECT r.report_id AS id, 'report' AS record_type, r.subject AS title,
                   CONCAT(CASE WHEN r.object_type = 'vacancy' THEN 'Lowongan' ELSE 'Perusahaan' END, ' · ', e.name) AS subtitle,
                   CONCAT(r.region, ' · ', r.reason) AS meta,
                   CASE r.verification_status
                       WHEN 'PENDING_REVIEW' THEN 'Menunggu Verifikasi'
                       WHEN 'IN_REVIEW' THEN 'Dalam Verifikasi'
                       ELSE 'Selesai'
                   END AS status,
                   DATE_FORMAT(r.submitted_at, '%d %b %Y %H:%i') AS date_text
            FROM karirhub_proto_monitoring_reports r
            JOIN karirhub_proto_monitoring_employers e ON e.employer_id = r.employer_id
            WHERE r.submitted_at >= DATE_SUB(NOW(), INTERVAL ? DAY) {$reportCondition}
            ORDER BY r.submitted_at DESC
        ");
        $stmt->bind_param('i', $days);
    } elseif ($card === 'reporters') {
        $stmt = $conn->prepare("
            SELECT p.reporter_id AS id, 'reporter' AS record_type, p.name AS title, p.email AS subtitle,
                   CONCAT(COUNT(*), ' laporan') AS meta, MAX(r.verification_status) AS status,
                   DATE_FORMAT(MAX(r.submitted_at), '%d %b %Y %H:%i') AS date_text
            FROM karirhub_proto_reporters p
            JOIN karirhub_proto_monitoring_reports r ON r.reporter_id = p.reporter_id
            WHERE r.submitted_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY p.reporter_id, p.name, p.email
            ORDER BY MAX(r.submitted_at) DESC
        ");
        $stmt->bind_param('i', $days);
    } elseif ($card === 'vacancies') {
        $stmt = $conn->prepare("
            SELECT v.vacancy_id AS id, 'vacancy' AS record_type, v.title, e.name AS subtitle,
                   CONCAT(v.location, ' · ', COUNT(r.report_id), ' laporan') AS meta,
                   v.enforcement_status AS status, DATE_FORMAT(MAX(r.submitted_at), '%d %b %Y %H:%i') AS date_text
            FROM karirhub_proto_monitoring_vacancies v
            JOIN karirhub_proto_monitoring_employers e ON e.employer_id = v.employer_id
            JOIN karirhub_proto_monitoring_reports r ON r.vacancy_id = v.vacancy_id
            WHERE r.submitted_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY v.vacancy_id, v.title, e.name, v.location, v.enforcement_status
            ORDER BY MAX(r.submitted_at) DESC
        ");
        $stmt->bind_param('i', $days);
    } elseif ($card === 'blocked-vacancies') {
        $stmt = $conn->prepare("
            SELECT v.vacancy_id AS id, 'vacancy' AS record_type, v.title, e.name AS subtitle,
                   v.location AS meta, 'Diblokir' AS status, DATE_FORMAT(v.blocked_at, '%d %b %Y %H:%i') AS date_text
            FROM karirhub_proto_monitoring_vacancies v
            JOIN karirhub_proto_monitoring_employers e ON e.employer_id = v.employer_id
            WHERE v.enforcement_status = 'BLOCKED' AND v.blocked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ORDER BY v.blocked_at DESC
        ");
        $stmt->bind_param('i', $days);
    } elseif ($card === 'blocked-employers') {
        $stmt = $conn->prepare("
            SELECT e.employer_id AS id, 'employer' AS record_type, e.name AS title, e.employer_type AS subtitle,
                   e.address AS meta, 'Diblokir' AS status, DATE_FORMAT(e.blocked_at, '%d %b %Y %H:%i') AS date_text
            FROM karirhub_proto_monitoring_employers e
            WHERE e.enforcement_status = 'BLOCKED' AND e.blocked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            ORDER BY e.blocked_at DESC
        ");
        $stmt->bind_param('i', $days);
    } else {
        return [];
    }
    $rows = kh_monitoring_fetch_all($stmt);
    $stmt->close();
    return $rows;
}

function kh_monitoring_related_reports(mysqli $conn, string $field, string $id): array
{
    $allowed = ['reporter_id', 'vacancy_id', 'employer_id'];
    if (!in_array($field, $allowed, true)) {
        return [];
    }
    $stmt = $conn->prepare("
        SELECT report_id, subject, reason, severity, sla_status,
               CASE verification_status
                   WHEN 'PENDING_REVIEW' THEN 'Menunggu Verifikasi'
                   WHEN 'IN_REVIEW' THEN 'Dalam Verifikasi'
                   ELSE 'Selesai'
               END AS status,
               DATE_FORMAT(submitted_at, '%d %b %Y %H:%i') AS submitted_at
        FROM karirhub_proto_monitoring_reports
        WHERE {$field} = ?
        ORDER BY submitted_at DESC
    ");
    $stmt->bind_param('s', $id);
    $rows = kh_monitoring_fetch_all($stmt);
    $stmt->close();
    return $rows;
}

function kh_monitoring_detail(mysqli $conn, string $type, string $id): ?array
{
    if ($type === 'report') {
        $stmt = $conn->prepare("
            SELECT r.*, p.name AS reporter_name, p.email AS reporter_email, p.phone AS reporter_phone,
                   e.name AS employer_name, e.employer_type, e.email AS employer_email,
                   v.title AS vacancy_title, v.location AS vacancy_location
            FROM karirhub_proto_monitoring_reports r
            JOIN karirhub_proto_reporters p ON p.reporter_id = r.reporter_id
            JOIN karirhub_proto_monitoring_employers e ON e.employer_id = r.employer_id
            LEFT JOIN karirhub_proto_monitoring_vacancies v ON v.vacancy_id = r.vacancy_id
            WHERE r.report_id = ?
            LIMIT 1
        ");
        $stmt->bind_param('s', $id);
        $rows = kh_monitoring_fetch_all($stmt);
        $stmt->close();
        return $rows[0] ?? null;
    }

    $tables = [
        'reporter' => ['karirhub_proto_reporters', 'reporter_id'],
        'vacancy' => ['karirhub_proto_monitoring_vacancies', 'vacancy_id'],
        'employer' => ['karirhub_proto_monitoring_employers', 'employer_id'],
    ];
    if (!isset($tables[$type])) {
        return null;
    }
    [$table, $key] = $tables[$type];
    if ($type === 'vacancy') {
        $sql = "SELECT v.*, e.name AS employer_name, e.email AS employer_email
                FROM {$table} v JOIN karirhub_proto_monitoring_employers e ON e.employer_id = v.employer_id
                WHERE v.{$key} = ? LIMIT 1";
    } else {
        $sql = "SELECT * FROM {$table} WHERE {$key} = ? LIMIT 1";
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $id);
    $rows = kh_monitoring_fetch_all($stmt);
    $stmt->close();
    if (!$rows) {
        return null;
    }
    $detail = $rows[0];
    $detail['related_reports'] = kh_monitoring_related_reports($conn, $key, $id);
    if ($type === 'employer') {
        $stmt = $conn->prepare("
            SELECT vacancy_id, title, location, publication_status, enforcement_status
            FROM karirhub_proto_monitoring_vacancies WHERE employer_id = ? ORDER BY created_at DESC
        ");
        $stmt->bind_param('s', $id);
        $detail['vacancies'] = kh_monitoring_fetch_all($stmt);
        $stmt->close();
    }
    return $detail;
}

function kh_monitoring_dashboard_data(mysqli $conn, int $days): array
{
    return [
        'period_days' => kh_monitoring_period_days($days),
        'summary' => kh_monitoring_summary($conn, $days),
        'reasons' => kh_monitoring_reasons($conn, $days),
        'regions' => kh_monitoring_regions($conn, $days),
        'recent_reports' => kh_monitoring_recent_reports($conn, $days),
    ];
}

