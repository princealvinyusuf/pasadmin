<?php

function jv3_ensure_schema(mysqli $conn): void
{
    $queries = [
        "CREATE TABLE IF NOT EXISTS joss_v3_proto_clients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id VARCHAR(80) NOT NULL UNIQUE,
            client_name VARCHAR(180) NOT NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            allow_dcf_write TINYINT(1) NOT NULL DEFAULT 0,
            allow_dcf_read TINYINT(1) NOT NULL DEFAULT 0,
            allow_dynamic_apply TINYINT(1) NOT NULL DEFAULT 0,
            max_fields INT NOT NULL DEFAULT 10,
            allowed_types_json TEXT NOT NULL,
            change_reason VARCHAR(500) NULL,
            updated_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS joss_v3_proto_field_definitions (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            scope ENUM('global','company','partner') NOT NULL,
            owner_company VARCHAR(180) NULL,
            source_client_id VARCHAR(80) NULL,
            field_key VARCHAR(100) NOT NULL,
            status ENUM('active','inactive','blocked') NOT NULL DEFAULT 'active',
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_jv3_field_scope (scope, owner_company, source_client_id, field_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS joss_v3_proto_field_versions (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            definition_id BIGINT NOT NULL,
            version_no INT NOT NULL,
            label VARCHAR(180) NOT NULL,
            data_type ENUM('string','character','integer','decimal','boolean','date','enum','url') NOT NULL,
            is_required TINYINT(1) NOT NULL DEFAULT 0,
            unit VARCHAR(40) NULL,
            options_json TEXT NULL,
            validation_json TEXT NULL,
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_jv3_version_definition FOREIGN KEY (definition_id) REFERENCES joss_v3_proto_field_definitions(id),
            UNIQUE KEY uniq_jv3_definition_version (definition_id, version_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS joss_v3_proto_vacancies (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            vacancy_code VARCHAR(60) NOT NULL UNIQUE,
            title VARCHAR(200) NOT NULL,
            company_name VARCHAR(180) NOT NULL,
            source_client_id VARCHAR(80) NOT NULL,
            revision_no INT NOT NULL DEFAULT 1,
            publication_status ENUM('draft','published','held','rejected') NOT NULL DEFAULT 'draft',
            verification_status ENUM('pending','approved','revision','rejected') NOT NULL DEFAULT 'pending',
            created_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS joss_v3_proto_vacancy_values (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            vacancy_id BIGINT NOT NULL,
            revision_no INT NOT NULL,
            field_version_id BIGINT NOT NULL,
            value_text TEXT NOT NULL,
            comparison_operator ENUM('eq','gte','lte') NOT NULL DEFAULT 'eq',
            display_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_jv3_value_vacancy FOREIGN KEY (vacancy_id) REFERENCES joss_v3_proto_vacancies(id),
            CONSTRAINT fk_jv3_value_version FOREIGN KEY (field_version_id) REFERENCES joss_v3_proto_field_versions(id),
            UNIQUE KEY uniq_jv3_vacancy_field (vacancy_id, revision_no, field_version_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS joss_v3_proto_routes (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            vacancy_id BIGINT NOT NULL,
            destination_client_id VARCHAR(80) NOT NULL,
            route_status ENUM('ready','sent','held','standard_only') NOT NULL,
            reason VARCHAR(500) NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_jv3_route_vacancy FOREIGN KEY (vacancy_id) REFERENCES joss_v3_proto_vacancies(id),
            UNIQUE KEY uniq_jv3_route (vacancy_id, destination_client_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS joss_v3_proto_variables (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            variable_key VARCHAR(100) NOT NULL UNIQUE,
            label VARCHAR(180) NOT NULL,
            source_type ENUM('PROFILE','INPUT') NOT NULL,
            source_key VARCHAR(160) NULL,
            data_type ENUM('string','character','integer','decimal','boolean','date','enum','url') NOT NULL,
            options_json TEXT NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS joss_v3_proto_schemas (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            client_id VARCHAR(80) NOT NULL,
            version_no INT NOT NULL,
            status ENUM('draft','published','retired') NOT NULL DEFAULT 'draft',
            recipient_label VARCHAR(200) NOT NULL,
            purpose VARCHAR(500) NOT NULL,
            published_by INT NULL,
            published_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_jv3_schema_version (client_id, version_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS joss_v3_proto_schema_fields (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            schema_id BIGINT NOT NULL,
            variable_id BIGINT NOT NULL,
            is_required TINYINT(1) NOT NULL DEFAULT 0,
            display_order INT NOT NULL,
            purpose VARCHAR(500) NOT NULL,
            CONSTRAINT fk_jv3_schema_field_schema FOREIGN KEY (schema_id) REFERENCES joss_v3_proto_schemas(id) ON DELETE CASCADE,
            CONSTRAINT fk_jv3_schema_field_variable FOREIGN KEY (variable_id) REFERENCES joss_v3_proto_variables(id),
            UNIQUE KEY uniq_jv3_schema_variable (schema_id, variable_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS joss_v3_proto_applications (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            application_code VARCHAR(60) NOT NULL UNIQUE,
            vacancy_id BIGINT NOT NULL,
            client_id VARCHAR(80) NOT NULL,
            schema_id BIGINT NOT NULL,
            status ENUM('submitted','held','delivered') NOT NULL DEFAULT 'submitted',
            applicant_name VARCHAR(180) NOT NULL,
            values_json LONGTEXT NOT NULL,
            consent_json LONGTEXT NOT NULL,
            recipient_snapshot VARCHAR(200) NOT NULL,
            purpose_snapshot VARCHAR(500) NOT NULL,
            consent_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_jv3_application_vacancy FOREIGN KEY (vacancy_id) REFERENCES joss_v3_proto_vacancies(id),
            CONSTRAINT fk_jv3_application_schema FOREIGN KEY (schema_id) REFERENCES joss_v3_proto_schemas(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS joss_v3_proto_deliveries (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            application_id BIGINT NOT NULL,
            event_id VARCHAR(80) NOT NULL UNIQUE,
            status ENUM('pending','success','failed','held') NOT NULL DEFAULT 'pending',
            attempt_count INT NOT NULL DEFAULT 0,
            payload_json LONGTEXT NOT NULL,
            last_error_code VARCHAR(100) NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_jv3_delivery_application FOREIGN KEY (application_id) REFERENCES joss_v3_proto_applications(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS joss_v3_proto_audit (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            actor_user_id INT NULL,
            action VARCHAR(100) NOT NULL,
            entity_type VARCHAR(80) NOT NULL,
            entity_id VARCHAR(100) NULL,
            details_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_jv3_audit_created (created_at),
            INDEX idx_jv3_audit_entity (entity_type, entity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
    foreach ($queries as $query) {
        $conn->query($query);
    }
    jv3_seed_data($conn);
}

function jv3_seed_data(mysqli $conn): void
{
    $count = (int)$conn->query("SELECT COUNT(*) c FROM joss_v3_proto_clients")->fetch_assoc()['c'];
    if ($count > 0) {
        return;
    }
    $conn->begin_transaction();
    try {
        $types = json_encode(['string','character','integer','decimal','boolean','date','enum','url']);
        $stmt = $conn->prepare("INSERT INTO joss_v3_proto_clients
            (client_id,client_name,status,allow_dcf_write,allow_dcf_read,allow_dynamic_apply,max_fields,allowed_types_json,change_reason)
            VALUES (?,?,?,?,?,?,?,?,?)");
        $rows = [
            ['HIREDTODAY','HiredToday','active',1,1,1,15,$types,'Seed: klien mendukung alur V3 penuh'],
            ['PORTAL-A','Portal A','active',1,0,0,8,$types,'Seed: hanya pengirim DCF'],
            ['PORTAL-LEGACY','Portal Legacy','active',0,0,0,0,$types,'Seed: kontrak lama'],
        ];
        foreach ($rows as $row) {
            [$clientId,$clientName,$status,$allowWrite,$allowRead,$allowApply,$maxFields,$allowedTypes,$reason] = $row;
            $stmt->bind_param('sssiiiiss', $clientId, $clientName, $status, $allowWrite, $allowRead, $allowApply, $maxFields, $allowedTypes, $reason);
            $stmt->execute();
        }
        $stmt->close();

        $definitions = [
            ['global',null,null,'minimum_body_height','Minimum Body Height','integer',1,'cm',null],
            ['company','PT Nusantara Digital',null,'work_shift','Kesediaan Kerja Shift','boolean',1,null,null],
            ['partner','PT Nusantara Digital','HIREDTODAY','language_level','Language Level','enum',0,null,'["A1","A2","B1","B2"]'],
        ];
        $defStmt = $conn->prepare("INSERT INTO joss_v3_proto_field_definitions (scope,owner_company,source_client_id,field_key,status) VALUES (?,?,?,?,'active')");
        $verStmt = $conn->prepare("INSERT INTO joss_v3_proto_field_versions (definition_id,version_no,label,data_type,is_required,unit,options_json,validation_json) VALUES (?,1,?,?,?,?,?,?)");
        $versionIds = [];
        foreach ($definitions as $definition) {
            [$scope,$owner,$source,$key,$label,$type,$required,$unit,$options] = $definition;
            $defStmt->bind_param('ssss', $scope, $owner, $source, $key);
            $defStmt->execute();
            $definitionId = (int)$conn->insert_id;
            $validation = $type === 'integer' ? '{"min":100,"max":230}' : '{}';
            $verStmt->bind_param('ississs', $definitionId, $label, $type, $required, $unit, $options, $validation);
            $verStmt->execute();
            $versionIds[] = (int)$conn->insert_id;
        }
        $defStmt->close();
        $verStmt->close();

        $conn->query("INSERT INTO joss_v3_proto_vacancies
            (vacancy_code,title,company_name,source_client_id,revision_no,publication_status,verification_status)
            VALUES ('VAC-001','FB Leader','PT Nusantara Digital','HIREDTODAY',1,'draft','pending')");
        $vacancyId = (int)$conn->insert_id;
        $valStmt = $conn->prepare("INSERT INTO joss_v3_proto_vacancy_values (vacancy_id,revision_no,field_version_id,value_text,comparison_operator,display_order) VALUES (?,1,?,?,?,?)");
        $values = [
            [$versionIds[0], '170', 'gte', 1],
            [$versionIds[1], 'true', 'eq', 2],
            [$versionIds[2], 'B1', 'eq', 3],
        ];
        foreach ($values as [$versionId,$value,$operator,$order]) {
            $valStmt->bind_param('iissi', $vacancyId, $versionId, $value, $operator, $order);
            $valStmt->execute();
        }
        $valStmt->close();

        $variables = [
            ['email','Alamat Email','PROFILE','job_seeker.email','string',null],
            ['phone','Nomor Telepon','PROFILE','job_seeker.phone','string',null],
            ['profile_cv','Profil / CV','PROFILE','job_seeker.profile','string',null],
            ['portfolio_url','URL Portofolio','INPUT',null,'url',null],
            ['shift_available','Kesediaan Kerja Shift','INPUT',null,'boolean','["true","false"]'],
            ['salary_expectation','Ekspektasi Gaji','INPUT',null,'integer',null],
        ];
        $varStmt = $conn->prepare("INSERT INTO joss_v3_proto_variables (variable_key,label,source_type,source_key,data_type,options_json) VALUES (?,?,?,?,?,?)");
        $variableIds = [];
        foreach ($variables as $variable) {
            [$variableKey,$variableLabel,$sourceType,$sourceKey,$dataType,$variableOptions] = $variable;
            $varStmt->bind_param('ssssss', $variableKey, $variableLabel, $sourceType, $sourceKey, $dataType, $variableOptions);
            $varStmt->execute();
            $variableIds[$variableKey] = (int)$conn->insert_id;
        }
        $varStmt->close();

        $conn->query("INSERT INTO joss_v3_proto_schemas (client_id,version_no,status,recipient_label,purpose,published_at)
            VALUES ('HIREDTODAY',1,'published','HiredToday dan PT Nusantara Digital','Seleksi dan proses rekrutmen kandidat',NOW())");
        $schemaId = (int)$conn->insert_id;
        $schemaField = $conn->prepare("INSERT INTO joss_v3_proto_schema_fields (schema_id,variable_id,is_required,display_order,purpose) VALUES (?,?,?,?,?)");
        $schemaRows = [
            [$variableIds['email'],1,1],[$variableIds['phone'],1,2],[$variableIds['profile_cv'],1,3],
            [$variableIds['portfolio_url'],0,4],[$variableIds['shift_available'],1,5],
        ];
        foreach ($schemaRows as [$variableId,$required,$order]) {
            $purpose = 'Digunakan untuk proses seleksi lowongan';
            $schemaField->bind_param('iiiis', $schemaId, $variableId, $required, $order, $purpose);
            $schemaField->execute();
        }
        $schemaField->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}
