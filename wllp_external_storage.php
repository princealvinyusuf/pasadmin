<?php

if (!function_exists('wllp_external_ensure_schema')) {
    function wllp_external_ensure_schema(mysqli $conn): void
    {
        $conn->query("
            CREATE TABLE IF NOT EXISTS wllp_seq_counters (
                prefix VARCHAR(40) PRIMARY KEY,
                last_seq BIGINT UNSIGNED NOT NULL DEFAULT 0,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS wllp_api_clients (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                client_id VARCHAR(120) NOT NULL UNIQUE,
                client_name VARCHAR(180) NOT NULL,
                client_secret VARCHAR(255) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS wllp_api_request_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                client_id VARCHAR(120) NOT NULL,
                request_id VARCHAR(120) NOT NULL,
                request_timestamp DATETIME NOT NULL,
                request_method VARCHAR(10) NOT NULL,
                request_path VARCHAR(255) NOT NULL,
                request_body_hash CHAR(64) NOT NULL,
                status_code INT NOT NULL DEFAULT 200,
                error_code VARCHAR(80) DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_wllp_api_req_replay (client_id, request_id),
                KEY idx_wllp_api_req_path (request_path),
                KEY idx_wllp_api_req_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS wllp_reports (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                no_reg_bukti VARCHAR(60) NOT NULL UNIQUE,
                employer_id BIGINT UNSIGNED NOT NULL,
                employer_code VARCHAR(60) NOT NULL,
                employer_name VARCHAR(255) NOT NULL,
                unit_id BIGINT UNSIGNED NOT NULL,
                unit_code VARCHAR(60) NOT NULL,
                unit_name VARCHAR(255) NOT NULL,
                period_type ENUM('weekly','monthly') NOT NULL,
                period_anchor DATE NOT NULL,
                period_start DATE NOT NULL,
                period_end DATE NOT NULL,
                verification_status VARCHAR(40) NOT NULL DEFAULT 'submitted',
                notes TEXT DEFAULT NULL,
                created_by_client_id VARCHAR(120) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_wllp_reports_employer_period (employer_id, period_type, period_start, period_end),
                KEY idx_wllp_reports_unit (unit_id),
                KEY idx_wllp_reports_status (verification_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS wllp_report_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                report_id BIGINT UNSIGNED NOT NULL,
                id_lowongan VARCHAR(40) NOT NULL UNIQUE,
                karirhub_job_id VARCHAR(120) DEFAULT NULL,
                title VARCHAR(255) NOT NULL,
                headcount_needed INT UNSIGNED NOT NULL,
                gender_requirement VARCHAR(50) NOT NULL DEFAULT 'Semua',
                age_min INT UNSIGNED DEFAULT NULL,
                age_max INT UNSIGNED DEFAULT NULL,
                education_min_id INT UNSIGNED DEFAULT NULL,
                job_description TEXT NOT NULL,
                skills TEXT DEFAULT NULL,
                experience_min_years INT UNSIGNED DEFAULT NULL,
                salary_min BIGINT UNSIGNED DEFAULT NULL,
                salary_max BIGINT UNSIGNED DEFAULT NULL,
                kbji_code VARCHAR(40) DEFAULT NULL,
                province_id BIGINT UNSIGNED DEFAULT NULL,
                city_id BIGINT UNSIGNED DEFAULT NULL,
                district_id BIGINT UNSIGNED DEFAULT NULL,
                village_id BIGINT UNSIGNED DEFAULT NULL,
                job_field_id BIGINT UNSIGNED DEFAULT NULL,
                industry_id BIGINT UNSIGNED DEFAULT NULL,
                marital_status_requirement VARCHAR(50) DEFAULT NULL,
                work_type VARCHAR(50) DEFAULT NULL,
                valid_from DATE DEFAULT NULL,
                valid_until DATE DEFAULT NULL,
                posting_url VARCHAR(500) DEFAULT NULL,
                verification_status VARCHAR(40) NOT NULL DEFAULT 'submitted',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_wllp_item_report FOREIGN KEY (report_id) REFERENCES wllp_reports(id) ON DELETE CASCADE,
                KEY idx_wllp_items_report (report_id),
                KEY idx_wllp_items_job (karirhub_job_id),
                KEY idx_wllp_items_title (title)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS wllp_item_statuses (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                item_id BIGINT UNSIGNED NOT NULL UNIQUE,
                status VARCHAR(60) NOT NULL DEFAULT 'Belum Terisi',
                note TEXT DEFAULT NULL,
                filled_count INT UNSIGNED NOT NULL DEFAULT 0,
                last_reported_at DATETIME DEFAULT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_wllp_status_item FOREIGN KEY (item_id) REFERENCES wllp_report_items(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS wllp_status_histories (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                item_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(60) NOT NULL,
                note TEXT DEFAULT NULL,
                actor_client_id VARCHAR(120) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_wllp_status_hist_item FOREIGN KEY (item_id) REFERENCES wllp_report_items(id) ON DELETE CASCADE,
                KEY idx_wllp_status_hist_item (item_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS wllp_placements (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                item_id BIGINT UNSIGNED NOT NULL,
                nik_hash CHAR(64) NOT NULL,
                nik_masked VARCHAR(30) NOT NULL,
                full_name VARCHAR(180) NOT NULL,
                education_id INT UNSIGNED DEFAULT NULL,
                gender VARCHAR(30) DEFAULT NULL,
                birth_place VARCHAR(120) DEFAULT NULL,
                birth_date DATE DEFAULT NULL,
                address TEXT DEFAULT NULL,
                disability_status TINYINT(1) NOT NULL DEFAULT 0,
                start_date DATE NOT NULL,
                email VARCHAR(180) DEFAULT NULL,
                phone VARCHAR(40) DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_wllp_placement_item FOREIGN KEY (item_id) REFERENCES wllp_report_items(id) ON DELETE CASCADE,
                KEY idx_wllp_placements_item (item_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS wllp_terms_consents (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                report_id BIGINT UNSIGNED NOT NULL,
                agreed TINYINT(1) NOT NULL DEFAULT 0,
                version VARCHAR(80) NOT NULL,
                ip_address VARCHAR(64) DEFAULT NULL,
                user_agent VARCHAR(255) DEFAULT NULL,
                consented_at DATETIME NOT NULL,
                CONSTRAINT fk_wllp_terms_report FOREIGN KEY (report_id) REFERENCES wllp_reports(id) ON DELETE CASCADE,
                KEY idx_wllp_terms_report (report_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS wllp_verification_logs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                report_id BIGINT UNSIGNED NOT NULL,
                actor_user_id BIGINT UNSIGNED DEFAULT NULL,
                action VARCHAR(50) NOT NULL,
                note TEXT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_wllp_verify_report FOREIGN KEY (report_id) REFERENCES wllp_reports(id) ON DELETE CASCADE,
                KEY idx_wllp_verify_report (report_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS wllp_bulk_batches (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                batch_id VARCHAR(80) NOT NULL UNIQUE,
                client_id VARCHAR(120) NOT NULL,
                employer_id BIGINT UNSIGNED NOT NULL,
                payload_json LONGTEXT NOT NULL,
                total_rows INT UNSIGNED NOT NULL DEFAULT 0,
                valid_rows INT UNSIGNED NOT NULL DEFAULT 0,
                invalid_rows INT UNSIGNED NOT NULL DEFAULT 0,
                template_version VARCHAR(50) NOT NULL DEFAULT 'WLLP-BULK-1.0',
                committed_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_wllp_bulk_client (client_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $stmt = $conn->prepare("
            INSERT INTO wllp_api_clients (client_id, client_name, client_secret, is_active)
            VALUES ('demo-client', 'Demo External Stakeholder', 'demo-secret-change-me', 1)
            ON DUPLICATE KEY UPDATE
                client_name = VALUES(client_name)
        ");
        if ($stmt) {
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('wllp_external_derive_period')) {
    function wllp_external_derive_period(string $periodType, string $anchorDate): array
    {
        $anchorTs = strtotime($anchorDate);
        if ($anchorTs === false) {
            throw new InvalidArgumentException('period_anchor is invalid');
        }
        $type = strtolower(trim($periodType));
        if ($type !== 'weekly' && $type !== 'monthly') {
            throw new InvalidArgumentException('period_type must be weekly or monthly');
        }
        if ($type === 'weekly') {
            $day = (int)date('N', $anchorTs);
            $startTs = strtotime('-' . ($day - 1) . ' days', $anchorTs);
            $endTs = strtotime('+' . (7 - $day) . ' days', $anchorTs);
            return [
                'period_type' => 'weekly',
                'period_anchor' => date('Y-m-d', $anchorTs),
                'period_start' => date('Y-m-d', $startTs),
                'period_end' => date('Y-m-d', $endTs),
            ];
        }
        return [
            'period_type' => 'monthly',
            'period_anchor' => date('Y-m-d', $anchorTs),
            'period_start' => date('Y-m-01', $anchorTs),
            'period_end' => date('Y-m-t', $anchorTs),
        ];
    }
}

if (!function_exists('wllp_external_next_sequence')) {
    function wllp_external_next_sequence(mysqli $conn, string $prefix): int
    {
        $stmt = $conn->prepare("
            INSERT INTO wllp_seq_counters(prefix, last_seq)
            VALUES (?, 0)
            ON DUPLICATE KEY UPDATE last_seq = last_seq
        ");
        $stmt->bind_param('s', $prefix);
        $stmt->execute();
        $stmt->close();

        $sel = $conn->prepare("SELECT last_seq FROM wllp_seq_counters WHERE prefix = ? FOR UPDATE");
        $sel->bind_param('s', $prefix);
        $sel->execute();
        $res = $sel->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $sel->close();
        $next = ((int)($row['last_seq'] ?? 0)) + 1;

        $upd = $conn->prepare("UPDATE wllp_seq_counters SET last_seq = ? WHERE prefix = ?");
        $upd->bind_param('is', $next, $prefix);
        $upd->execute();
        $upd->close();
        return $next;
    }
}

if (!function_exists('wllp_external_generate_no_reg_bukti')) {
    function wllp_external_generate_no_reg_bukti(mysqli $conn, string $periodAnchor): string
    {
        $anchorTs = strtotime($periodAnchor);
        if ($anchorTs === false) {
            throw new InvalidArgumentException('period_anchor is invalid');
        }
        $prefix = 'WLLP-57' . date('ym', $anchorTs) . '-';
        $seq = wllp_external_next_sequence($conn, 'NO_REG_' . date('ym', $anchorTs));
        return $prefix . str_pad((string)$seq, 8, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('wllp_external_generate_id_lowongan')) {
    function wllp_external_generate_id_lowongan(mysqli $conn): string
    {
        $seq = wllp_external_next_sequence($conn, 'ID_LOWONGAN');
        return 'LK-' . str_pad((string)$seq, 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('wllp_external_mask_nik')) {
    function wllp_external_mask_nik(string $nik): string
    {
        $digits = preg_replace('/\D+/', '', $nik);
        if ($digits === null || $digits === '') {
            return '****';
        }
        if (strlen($digits) <= 6) {
            return str_repeat('*', strlen($digits));
        }
        return substr($digits, 0, 4) . str_repeat('*', strlen($digits) - 6) . substr($digits, -2);
    }
}

