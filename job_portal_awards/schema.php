<?php

function jpa_schema_column_exists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare("SELECT 1 FROM information_schema.columns
        WHERE table_schema=DATABASE() AND table_name=? AND column_name=? LIMIT 1");
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $exists;
}

function jpa_schema_index_exists(mysqli $conn, string $table, string $index): bool
{
    $stmt = $conn->prepare("SELECT 1 FROM information_schema.statistics
        WHERE table_schema=DATABASE() AND table_name=? AND index_name=? LIMIT 1");
    $stmt->bind_param('ss', $table, $index);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $exists;
}

function jpa_schema_column_nullable(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare("SELECT is_nullable FROM information_schema.columns
        WHERE table_schema=DATABASE() AND table_name=? AND column_name=? LIMIT 1");
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ($row['is_nullable'] ?? 'NO') === 'YES';
}

function jpa_schema_column_type(mysqli $conn, string $table, string $column): string
{
    $stmt = $conn->prepare("SELECT data_type FROM information_schema.columns
        WHERE table_schema=DATABASE() AND table_name=? AND column_name=? LIMIT 1");
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return strtolower((string)($row['data_type'] ?? ''));
}

function jpa_schema_constraint_exists(mysqli $conn, string $table, string $constraint): bool
{
    $stmt = $conn->prepare("SELECT 1 FROM information_schema.table_constraints
        WHERE constraint_schema=DATABASE() AND table_name=? AND constraint_name=? LIMIT 1");
    $stmt->bind_param('ss', $table, $constraint);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $exists;
}

function jpa_ensure_schema(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS job_portal_award_periods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(180) NOT NULL,
        period_start DATE NOT NULL,
        period_end DATE NOT NULL,
        status ENUM('draft','locked','finalized') NOT NULL DEFAULT 'draft',
        min_active_months INT NOT NULL DEFAULT 3,
        target_volume INT NOT NULL DEFAULT 1,
        mandatory_vacancy_fields TEXT NOT NULL,
        complaint_penalty_factor DECIMAL(10,4) NOT NULL DEFAULT 25,
        target_progression_rate DECIMAL(10,4) NOT NULL DEFAULT 1,
        target_placement_rate DECIMAL(10,4) NOT NULL DEFAULT 1,
        impact_module_enabled TINYINT(1) NOT NULL DEFAULT 0,
        weights_json TEXT NOT NULL,
        locked_by INT NULL,
        locked_at DATETIME NULL,
        finalized_by INT NULL,
        finalized_at DATETIME NULL,
        created_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_jpa_period_status (status),
        INDEX idx_jpa_period_dates (period_start, period_end)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS job_portal_award_imports (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        period_id INT NOT NULL,
        original_filename VARCHAR(255) NOT NULL,
        total_rows INT NOT NULL DEFAULT 0,
        successful_rows INT NOT NULL DEFAULT 0,
        failed_rows INT NOT NULL DEFAULT 0,
        status ENUM('processing','completed','failed') NOT NULL DEFAULT 'processing',
        errors_json LONGTEXT NULL,
        imported_by INT NULL,
        imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_jpa_import_period FOREIGN KEY (period_id)
            REFERENCES job_portal_award_periods(id) ON DELETE CASCADE,
        INDEX idx_jpa_import_period (period_id, imported_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS job_portal_award_participants (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        period_id INT NOT NULL,
        partner_id VARCHAR(120) NOT NULL,
        partner_name VARCHAR(255) NOT NULL,
        integration_type ENUM('full','semi','inactive') NOT NULL DEFAULT 'inactive',
        partnership_active TINYINT(1) NOT NULL DEFAULT 0,
        critical_violation_resolved TINYINT(1) NOT NULL DEFAULT 1,
        data_traceable TINYINT(1) NOT NULL DEFAULT 0,
        records_sent_unique BIGINT UNSIGNED NOT NULL DEFAULT 0,
        published_unique_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
        active_months INT UNSIGNED NOT NULL DEFAULT 0,
        complete_vacancy_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
        employer_unique_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
        employer_valid_legal_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
        duplicate_vacancy_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
        valid_complaint_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
        severe_complaint_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
        applications_from_karirhub BIGINT UNSIGNED NOT NULL DEFAULT 0,
        progressed_candidate_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
        hired_candidate_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
        eligibility_status ENUM('pending','eligible','ineligible') NOT NULL DEFAULT 'pending',
        eligibility_reasons TEXT NULL,
        score_integration DECIMAL(7,4) NOT NULL DEFAULT 0,
        score_volume DECIMAL(7,4) NOT NULL DEFAULT 0,
        score_consistency DECIMAL(7,4) NOT NULL DEFAULT 0,
        score_completeness DECIMAL(7,4) NOT NULL DEFAULT 0,
        score_kyb DECIMAL(7,4) NOT NULL DEFAULT 0,
        score_duplicate DECIMAL(7,4) NOT NULL DEFAULT 0,
        score_complaint DECIMAL(7,4) NOT NULL DEFAULT 0,
        score_progression DECIMAL(7,4) NOT NULL DEFAULT 0,
        score_placement DECIMAL(7,4) NOT NULL DEFAULT 0,
        final_score DECIMAL(7,4) NOT NULL DEFAULT 0,
        award_rank INT NULL,
        source_import_id BIGINT NULL,
        change_reason VARCHAR(500) NULL,
        created_by INT NULL,
        updated_by INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_jpa_participant_period FOREIGN KEY (period_id)
            REFERENCES job_portal_award_periods(id) ON DELETE CASCADE,
        CONSTRAINT fk_jpa_participant_import FOREIGN KEY (source_import_id)
            REFERENCES job_portal_award_imports(id) ON DELETE SET NULL,
        UNIQUE KEY uniq_jpa_partner_period (period_id, partner_id),
        UNIQUE KEY uniq_jpa_participant_period_id (id, period_id),
        INDEX idx_jpa_participant_ranking (period_id, eligibility_status, final_score),
        INDEX idx_jpa_participant_name (partner_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS job_portal_award_red_flags (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        participant_id BIGINT NOT NULL,
        code ENUM('RF-1','RF-2','RF-4') NOT NULL,
        description TEXT NOT NULL,
        evidence_reference VARCHAR(1000) NULL,
        status ENUM('pending','confirmed','dismissed') NOT NULL DEFAULT 'pending',
        consequence ENUM('review','disqualified','score_held') NOT NULL DEFAULT 'review',
        committee_notes TEXT NULL,
        auto_generated TINYINT(1) NOT NULL DEFAULT 0,
        source_metric_count BIGINT UNSIGNED NULL,
        created_by INT NULL,
        decided_by INT NULL,
        decided_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_jpa_red_flag_participant FOREIGN KEY (participant_id)
            REFERENCES job_portal_award_participants(id) ON DELETE CASCADE,
        INDEX idx_jpa_red_flag_participant (participant_id, status),
        INDEX idx_jpa_red_flag_status (status, consequence)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS job_portal_award_winners (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        period_id INT NOT NULL,
        participant_id BIGINT NOT NULL,
        award_rank INT NOT NULL,
        partner_id_snapshot VARCHAR(120) NOT NULL,
        partner_name_snapshot VARCHAR(255) NOT NULL,
        final_score_snapshot DECIMAL(7,4) NOT NULL,
        scores_snapshot_json LONGTEXT NOT NULL,
        config_snapshot_json LONGTEXT NOT NULL,
        approved_by INT NULL,
        approved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT fk_jpa_winner_period FOREIGN KEY (period_id)
            REFERENCES job_portal_award_periods(id) ON DELETE CASCADE,
        CONSTRAINT fk_jpa_winner_participant_period FOREIGN KEY (participant_id, period_id)
            REFERENCES job_portal_award_participants(id, period_id) ON DELETE CASCADE,
        UNIQUE KEY uniq_jpa_winner_rank (period_id, award_rank),
        UNIQUE KEY uniq_jpa_winner_participant (period_id, participant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS job_portal_award_audit (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        period_id INT NULL,
        actor_user_id INT NULL,
        action VARCHAR(100) NOT NULL,
        entity_type VARCHAR(80) NOT NULL,
        entity_id VARCHAR(120) NULL,
        before_json LONGTEXT NULL,
        after_json LONGTEXT NULL,
        reason VARCHAR(500) NULL,
        ip_address VARCHAR(45) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_jpa_audit_period (period_id, created_at),
        INDEX idx_jpa_audit_entity (entity_type, entity_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Serialize idempotent migrations so concurrent first requests cannot race.
    $lockResult = $conn->query("SELECT GET_LOCK('job_portal_awards_schema_migration', 10) AS acquired");
    $lockRow = $lockResult ? $lockResult->fetch_assoc() : ['acquired' => 0];
    if (intval($lockRow['acquired'] ?? 0) !== 1) {
        throw new RuntimeException('Could not acquire Job Portal Awards schema migration lock.');
    }

    // Idempotent migrations for installations that loaded an earlier module revision.
    $addedAutoGenerated = false;
    if (!jpa_schema_column_exists($conn, 'job_portal_award_red_flags', 'auto_generated')) {
        $conn->query("ALTER TABLE job_portal_award_red_flags ADD COLUMN auto_generated TINYINT(1) NOT NULL DEFAULT 0 AFTER committee_notes");
        $addedAutoGenerated = true;
    }
    if (!jpa_schema_column_exists($conn, 'job_portal_award_red_flags', 'source_metric_count')) {
        $conn->query("ALTER TABLE job_portal_award_red_flags ADD COLUMN source_metric_count BIGINT UNSIGNED NULL AFTER auto_generated");
    } elseif (jpa_schema_column_type($conn, 'job_portal_award_red_flags', 'source_metric_count') !== 'bigint') {
        $conn->query("ALTER TABLE job_portal_award_red_flags MODIFY source_metric_count BIGINT UNSIGNED NULL");
    }
    if ($addedAutoGenerated) {
        $conn->query("UPDATE job_portal_award_red_flags SET
            auto_generated=1,
            source_metric_count=CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(description,' aduan',1),' ',-1) AS UNSIGNED)
            WHERE code='RF-1' AND description LIKE 'Sistem mendeteksi % aduan berat terverifikasi%'
              AND evidence_reference LIKE 'Sumber: severe_complaint_count%'");
    }
    if (!jpa_schema_index_exists($conn, 'job_portal_award_participants', 'uniq_jpa_participant_period_id')) {
        $conn->query('ALTER TABLE job_portal_award_participants ADD UNIQUE KEY uniq_jpa_participant_period_id (id, period_id)');
    }

    $winnerColumns = [
        'partner_id_snapshot' => "VARCHAR(120) NULL",
        'partner_name_snapshot' => "VARCHAR(255) NULL",
        'final_score_snapshot' => "DECIMAL(7,4) NULL",
        'scores_snapshot_json' => "LONGTEXT NULL",
        'config_snapshot_json' => "LONGTEXT NULL",
    ];
    $winnerNeedsMigration = false;
    foreach ($winnerColumns as $column => $definition) {
        if (!jpa_schema_column_exists($conn, 'job_portal_award_winners', $column)) {
            $conn->query("ALTER TABLE job_portal_award_winners ADD COLUMN {$column} {$definition} AFTER award_rank");
            $winnerNeedsMigration = true;
        } elseif (jpa_schema_column_nullable($conn, 'job_portal_award_winners', $column)) {
            $winnerNeedsMigration = true;
        }
    }
    if ($winnerNeedsMigration) {
        $conn->query("UPDATE job_portal_award_winners w
            JOIN job_portal_award_participants p ON p.id=w.participant_id AND p.period_id=w.period_id
            JOIN job_portal_award_periods ap ON ap.id=w.period_id
            SET w.partner_id_snapshot=COALESCE(w.partner_id_snapshot,p.partner_id),
                w.partner_name_snapshot=COALESCE(w.partner_name_snapshot,p.partner_name),
                w.final_score_snapshot=COALESCE(w.final_score_snapshot,p.final_score),
                w.scores_snapshot_json=COALESCE(w.scores_snapshot_json,JSON_OBJECT(
                    'score_integration',p.score_integration,'score_volume',p.score_volume,
                    'score_consistency',p.score_consistency,'score_completeness',p.score_completeness,
                    'score_kyb',p.score_kyb,'score_duplicate',p.score_duplicate,
                    'score_complaint',p.score_complaint,'score_progression',p.score_progression,
                    'score_placement',p.score_placement,'final_score',p.final_score
                )),
                w.config_snapshot_json=COALESCE(w.config_snapshot_json,JSON_OBJECT(
                    'name',ap.name,'period_start',ap.period_start,'period_end',ap.period_end,
                    'min_active_months',ap.min_active_months,'target_volume',ap.target_volume,
                    'mandatory_vacancy_fields',ap.mandatory_vacancy_fields,
                    'complaint_penalty_factor',ap.complaint_penalty_factor,
                    'target_progression_rate',ap.target_progression_rate,
                    'target_placement_rate',ap.target_placement_rate,
                    'impact_module_enabled',ap.impact_module_enabled,'weights_json',ap.weights_json
                ))
            WHERE w.partner_id_snapshot IS NULL OR w.partner_name_snapshot IS NULL
               OR w.final_score_snapshot IS NULL OR w.scores_snapshot_json IS NULL OR w.config_snapshot_json IS NULL");
        $conn->query("ALTER TABLE job_portal_award_winners
            MODIFY partner_id_snapshot VARCHAR(120) NOT NULL,
            MODIFY partner_name_snapshot VARCHAR(255) NOT NULL,
            MODIFY final_score_snapshot DECIMAL(7,4) NOT NULL,
            MODIFY scores_snapshot_json LONGTEXT NOT NULL,
            MODIFY config_snapshot_json LONGTEXT NOT NULL");
    }
    if (jpa_schema_constraint_exists($conn, 'job_portal_award_winners', 'fk_jpa_winner_participant')) {
        $conn->query('ALTER TABLE job_portal_award_winners DROP FOREIGN KEY fk_jpa_winner_participant');
    }
    if (!jpa_schema_constraint_exists($conn, 'job_portal_award_winners', 'fk_jpa_winner_participant_period')) {
        $conn->query("ALTER TABLE job_portal_award_winners ADD CONSTRAINT fk_jpa_winner_participant_period
            FOREIGN KEY (participant_id, period_id) REFERENCES job_portal_award_participants(id, period_id) ON DELETE CASCADE");
    }
    $conn->query("SELECT RELEASE_LOCK('job_portal_awards_schema_migration')");
}

