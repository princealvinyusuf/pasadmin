<?php

require_once __DIR__ . '/wllp_external_api_common.php';
require_once __DIR__ . '/karirhub_employer_prototype_data.php';

if (!function_exists('wllp_api_create_report_from_payload')) {
    function wllp_api_create_report_from_payload(mysqli $conn, array $payload, array $auth): array
    {
        wllp_api_require_fields($payload, ['unit_id', 'period_type', 'period_anchor', 'items']);
        if (!is_array($payload['items']) || count($payload['items']) === 0) {
            wllp_api_client_error(422, 'VALIDATION_FAILED', 'items must contain at least one entry.', [
                'items' => 'At least one item is required.',
            ]);
        }
        $terms = wllp_api_validate_terms($payload);
        $scope = wllp_api_get_report_scope($payload);
        $period = wllp_external_derive_period((string)$payload['period_type'], (string)$payload['period_anchor']);
        $notes = trim((string)($payload['notes'] ?? ''));

        $conn->begin_transaction();
        try {
            $report = wllp_api_find_or_create_report($conn, $scope, $period, $notes, $auth['client_id']);
            $itemOut = [];
            foreach ($payload['items'] as $idx => $item) {
                if (!is_array($item)) {
                    wllp_api_client_error(422, 'VALIDATION_FAILED', 'Each item must be an object.', [
                        'items.' . $idx => 'Invalid object.',
                    ]);
                }
                $title = trim((string)($item['title'] ?? ''));
                $headcount = (int)($item['headcount_needed'] ?? 0);
                if ($title === '' || $headcount < 1) {
                    wllp_api_client_error(422, 'VALIDATION_FAILED', 'Invalid item field.', [
                        "items.$idx.title" => 'Required.',
                        "items.$idx.headcount_needed" => 'Must be > 0.',
                    ]);
                }

                $idLowongan = wllp_external_generate_id_lowongan($conn);
                $ins = $conn->prepare("
                    INSERT INTO wllp_report_items
                    (report_id, id_lowongan, karirhub_job_id, title, headcount_needed, gender_requirement, age_min, age_max, education_min_id, job_description, skills, experience_min_years, salary_min, salary_max, kbji_code, province_id, city_id, district_id, village_id, job_field_id, industry_id, marital_status_requirement, work_type, valid_from, valid_until, posting_url, verification_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted')
                ");
                $jobId = isset($item['job_id']) ? (string)$item['job_id'] : null;
                $gender = (string)($item['gender_requirement'] ?? 'Semua');
                $ageMin = isset($item['age_min']) ? (int)$item['age_min'] : null;
                $ageMax = isset($item['age_max']) ? (int)$item['age_max'] : null;
                $edu = isset($item['education_min_id']) ? (int)$item['education_min_id'] : null;
                $jobDesc = (string)($item['job_description'] ?? $title);
                $skills = isset($item['skills']) ? (string)$item['skills'] : null;
                $exp = isset($item['experience_min_years']) ? (int)$item['experience_min_years'] : null;
                $salaryMin = isset($item['salary_min']) ? (int)$item['salary_min'] : null;
                $salaryMax = isset($item['salary_max']) ? (int)$item['salary_max'] : null;
                $kbji = isset($item['kbji_code']) ? (string)$item['kbji_code'] : null;
                $province = isset($item['province_id']) ? (int)$item['province_id'] : null;
                $city = isset($item['city_id']) ? (int)$item['city_id'] : null;
                $district = isset($item['district_id']) ? (int)$item['district_id'] : null;
                $village = isset($item['village_id']) ? (int)$item['village_id'] : null;
                $jobField = isset($item['job_field_id']) ? (int)$item['job_field_id'] : null;
                $industry = isset($item['industry_id']) ? (int)$item['industry_id'] : null;
                $marital = isset($item['marital_status_requirement']) ? (string)$item['marital_status_requirement'] : null;
                $workType = isset($item['work_type']) ? (string)$item['work_type'] : null;
                $validFrom = isset($item['valid_from']) ? (string)$item['valid_from'] : null;
                $validUntil = isset($item['valid_until']) ? (string)$item['valid_until'] : null;
                $postingUrl = isset($item['posting_url']) ? (string)$item['posting_url'] : null;
                $ins->bind_param(
                    'isssissiissiiisiiiiiisssss',
                    $report['id'],
                    $idLowongan,
                    $jobId,
                    $title,
                    $headcount,
                    $gender,
                    $ageMin,
                    $ageMax,
                    $edu,
                    $jobDesc,
                    $skills,
                    $exp,
                    $salaryMin,
                    $salaryMax,
                    $kbji,
                    $province,
                    $city,
                    $district,
                    $village,
                    $jobField,
                    $industry,
                    $marital,
                    $workType,
                    $validFrom,
                    $validUntil,
                    $postingUrl
                );
                $ins->execute();
                $itemId = (int)$ins->insert_id;
                $ins->close();

                wllp_api_upsert_item_status($conn, $itemId, 'Belum Terisi', null, $auth['client_id']);
                $itemOut[] = [
                    'id' => $itemId,
                    'id_lowongan' => $idLowongan,
                    'status' => 'Belum Terisi',
                ];
            }

            $cons = $conn->prepare("
                INSERT INTO wllp_terms_consents(report_id, agreed, version, ip_address, user_agent, consented_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
            $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
            $cons->bind_param('iisss', $report['id'], $terms['agreed'], $terms['version'], $ip, $ua);
            $cons->execute();
            $cons->close();

            wllp_api_write_audit_log(
                $conn,
                'wllp_report',
                (string)$report['id'],
                $report['reused'] ? 'update' : 'create',
                [],
                ['no_reg_bukti' => $report['no_reg_bukti']],
                $auth
            );

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            wllp_api_client_error(500, 'INTERNAL_ERROR', 'Failed to persist report: ' . $e->getMessage());
        }

        return [
            'success' => true,
            'report' => [
                'id' => $report['id'],
                'no_reg_bukti' => $report['no_reg_bukti'],
                'period_start' => $period['period_start'],
                'period_end' => $period['period_end'],
                'verification_status' => 'submitted',
            ],
            'items' => $itemOut,
        ];
    }
}

if (!function_exists('wllp_api_handle_reports_get')) {
    function wllp_api_handle_reports_get(mysqli $conn): void
    {
        $employerId = wllp_api_get_query_int('employer_id', 0);
        if ($employerId <= 0) {
            wllp_api_client_error(422, 'VALIDATION_FAILED', 'employer_id query is required.');
        }
        $limit = min(200, max(1, wllp_api_get_query_int('limit', 50)));
        $offset = max(0, wllp_api_get_query_int('offset', 0));

        $stmt = $conn->prepare("
            SELECT id, no_reg_bukti, employer_id, employer_name, unit_name, period_type, period_start, period_end, verification_status, created_at
            FROM wllp_reports
            WHERE employer_id = ?
            ORDER BY id DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param('iii', $employerId, $limit, $offset);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $stmt->close();
        wllp_api_json_response(200, [
            'success' => true,
            'data' => $rows,
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'next_offset' => $offset + $limit,
                'has_more' => count($rows) === $limit,
            ],
        ]);
    }
}

if (!function_exists('wllp_api_handle_report_detail')) {
    function wllp_api_handle_report_detail(mysqli $conn, int $reportId): void
    {
        $stmt = $conn->prepare("
            SELECT r.*, COUNT(i.id) AS total_items
            FROM wllp_reports r
            LEFT JOIN wllp_report_items i ON i.report_id = r.id
            WHERE r.id = ?
            GROUP BY r.id
        ");
        $stmt->bind_param('i', $reportId);
        $stmt->execute();
        $res = $stmt->get_result();
        $report = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$report) {
            wllp_api_client_error(404, 'REPORT_NOT_FOUND', 'Report not found.');
        }

        $itemStmt = $conn->prepare("
            SELECT i.id, i.id_lowongan, i.title, i.headcount_needed, s.status, s.filled_count
            FROM wllp_report_items i
            LEFT JOIN wllp_item_statuses s ON s.item_id = i.id
            WHERE i.report_id = ?
            ORDER BY i.id ASC
        ");
        $itemStmt->bind_param('i', $reportId);
        $itemStmt->execute();
        $itemRes = $itemStmt->get_result();
        $items = [];
        while ($row = $itemRes->fetch_assoc()) {
            $items[] = $row;
        }
        $itemStmt->close();

        wllp_api_json_response(200, [
            'success' => true,
            'report' => $report,
            'items' => $items,
        ]);
    }
}

if (!function_exists('wllp_api_handle_report_pdf')) {
    function wllp_api_handle_report_pdf(mysqli $conn, int $reportId): void
    {
        $stmt = $conn->prepare("SELECT no_reg_bukti, employer_name, period_start, period_end FROM wllp_reports WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $reportId);
        $stmt->execute();
        $res = $stmt->get_result();
        $report = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$report) {
            wllp_api_client_error(404, 'REPORT_NOT_FOUND', 'Report not found.');
        }

        $text = "Bukti Lapor WLLP\\nNo Reg: {$report['no_reg_bukti']}\\nEmployer: {$report['employer_name']}\\nPeriod: {$report['period_start']} s/d {$report['period_end']}";
        $pdf = "%PDF-1.1\n";
        $pdf .= "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n";
        $pdf .= "2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj\n";
        $pdf .= "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources<<>> >>endobj\n";
        $pdf .= "4 0 obj<</Length " . strlen($text) + 35 . ">>stream\n";
        $pdf .= "BT /F1 12 Tf 72 720 Td (" . str_replace(["\n", "(", ")"], [' ', '\(', '\)'], $text) . ") Tj ET\n";
        $pdf .= "endstream endobj\n";
        $pdf .= "xref\n0 5\n0000000000 65535 f \n";
        $offsets = [9, 58, 115, 209];
        foreach ($offsets as $off) {
            $pdf .= str_pad((string)$off, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $pdf .= "trailer<</Size 5/Root 1 0 R>>\nstartxref\n290\n%%EOF";

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="wllp-report-' . $reportId . '.pdf"');
        echo $pdf;
        exit;
    }
}

if (!function_exists('wllp_api_handle_item_status_get')) {
    function wllp_api_handle_item_status_get(mysqli $conn, int $itemId): void
    {
        $stmt = $conn->prepare("
            SELECT i.id, i.id_lowongan, i.title, i.headcount_needed, s.status, s.note, s.filled_count, s.last_reported_at
            FROM wllp_report_items i
            LEFT JOIN wllp_item_statuses s ON s.item_id = i.id
            WHERE i.id = ?
            LIMIT 1
        ");
        $stmt->bind_param('i', $itemId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$row) {
            wllp_api_client_error(404, 'ITEM_NOT_FOUND', 'Item not found.');
        }
        wllp_api_json_response(200, ['success' => true, 'data' => $row]);
    }
}

if (!function_exists('wllp_api_handle_item_status_put')) {
    function wllp_api_handle_item_status_put(mysqli $conn, int $itemId, array $auth): void
    {
        $body = wllp_api_decode_json_body();
        $status = trim((string)($body['status'] ?? ''));
        $note = isset($body['note']) ? trim((string)$body['note']) : null;
        if ($status === '') {
            wllp_api_client_error(422, 'VALIDATION_FAILED', 'status is required.', ['status' => 'Required field.']);
        }

        $exists = $conn->prepare("SELECT id FROM wllp_report_items WHERE id = ? LIMIT 1");
        $exists->bind_param('i', $itemId);
        $exists->execute();
        $res = $exists->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $exists->close();
        if (!$row) {
            wllp_api_client_error(404, 'ITEM_NOT_FOUND', 'Item not found.');
        }

        wllp_api_upsert_item_status($conn, $itemId, $status, $note, $auth['client_id']);
        wllp_api_write_audit_log($conn, 'wllp_item', (string)$itemId, 'status_update', [], ['status' => $status, 'note' => $note], $auth);

        wllp_api_json_response(200, ['success' => true, 'item_id' => $itemId, 'status' => $status, 'note' => $note]);
    }
}

if (!function_exists('wllp_api_handle_add_placement')) {
    function wllp_api_handle_add_placement(mysqli $conn, int $itemId, array $auth): void
    {
        $body = wllp_api_decode_json_body();
        wllp_api_require_fields($body, ['nik', 'full_name', 'start_date']);
        $nik = (string)$body['nik'];
        $digits = preg_replace('/\D+/', '', $nik);
        if ($digits === null || strlen($digits) < 12 || strlen($digits) > 20) {
            wllp_api_client_error(422, 'VALIDATION_FAILED', 'nik invalid.', ['nik' => 'Must be 12-20 numeric characters.']);
        }

        $sel = $conn->prepare("
            SELECT id, headcount_needed
            FROM wllp_report_items
            WHERE id = ?
            LIMIT 1
        ");
        $sel->bind_param('i', $itemId);
        $sel->execute();
        $res = $sel->get_result();
        $item = $res ? $res->fetch_assoc() : null;
        $sel->close();
        if (!$item) {
            wllp_api_client_error(404, 'ITEM_NOT_FOUND', 'Item not found.');
        }

        $cnt = $conn->prepare("SELECT COUNT(*) AS c FROM wllp_placements WHERE item_id = ?");
        $cnt->bind_param('i', $itemId);
        $cnt->execute();
        $resCnt = $cnt->get_result();
        $rowCnt = $resCnt ? $resCnt->fetch_assoc() : ['c' => 0];
        $cnt->close();
        $current = (int)($rowCnt['c'] ?? 0);
        if ($current >= (int)$item['headcount_needed']) {
            wllp_api_client_error(409, 'PLACEMENT_LIMIT_EXCEEDED', 'Placement count exceeds headcount needed.');
        }

        $nikHash = hash('sha256', $digits);
        $nikMasked = wllp_external_mask_nik($digits);
        $ins = $conn->prepare("
            INSERT INTO wllp_placements
            (item_id, nik_hash, nik_masked, full_name, education_id, gender, birth_place, birth_date, address, disability_status, start_date, email, phone)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $educationId = isset($body['education_id']) ? (int)$body['education_id'] : null;
        $gender = isset($body['gender']) ? (string)$body['gender'] : null;
        $birthPlace = isset($body['birth_place']) ? (string)$body['birth_place'] : null;
        $birthDate = isset($body['birth_date']) ? (string)$body['birth_date'] : null;
        $address = isset($body['address']) ? (string)$body['address'] : null;
        $disability = !empty($body['disability_status']) ? 1 : 0;
        $startDate = (string)$body['start_date'];
        $email = isset($body['email']) ? (string)$body['email'] : null;
        $phone = isset($body['phone']) ? (string)$body['phone'] : null;
        $fullName = (string)$body['full_name'];
        $ins->bind_param(
            'isssissssisss',
            $itemId,
            $nikHash,
            $nikMasked,
            $fullName,
            $educationId,
            $gender,
            $birthPlace,
            $birthDate,
            $address,
            $disability,
            $startDate,
            $email,
            $phone
        );
        $ins->execute();
        $placementId = (int)$ins->insert_id;
        $ins->close();

        wllp_api_upsert_item_status($conn, $itemId, 'Terisi', 'Placement added via API', $auth['client_id']);
        wllp_api_write_audit_log($conn, 'wllp_item', (string)$itemId, 'placement_add', [], ['placement_id' => $placementId], $auth);

        wllp_api_json_response(201, [
            'success' => true,
            'placement' => [
                'id' => $placementId,
                'item_id' => $itemId,
                'nik_masked' => $nikMasked,
                'full_name' => $fullName,
            ],
        ]);
    }
}

if (!function_exists('wllp_api_handle_bulk_validate')) {
    function wllp_api_handle_bulk_validate(mysqli $conn, array $auth): void
    {
        $payload = [];
        if (isset($_FILES['file']) && is_array($_FILES['file'])) {
            $filename = (string)($_FILES['file']['name'] ?? '');
            if (!preg_match('/\.xlsx$/i', $filename)) {
                wllp_api_client_error(422, 'BULK_TEMPLATE_INVALID', 'Uploaded file must be .xlsx');
            }
            $payload = [
                'employer_id' => (int)($_POST['employer_id'] ?? 0),
                'rows' => [],
            ];
        } else {
            $payload = wllp_api_decode_json_body();
        }

        $employerId = (int)($payload['employer_id'] ?? 0);
        if ($employerId <= 0) {
            wllp_api_client_error(422, 'VALIDATION_FAILED', 'employer_id is required.');
        }
        $rows = $payload['rows'] ?? [];
        if (!is_array($rows)) {
            $rows = [];
        }
        $totalRows = count($rows);
        $errors = [];
        $validRows = 0;
        foreach ($rows as $i => $row) {
            if (!is_array($row)) {
                $errors[] = ['row' => $i + 1, 'field' => 'row', 'message' => 'Invalid row format.'];
                continue;
            }
            $title = trim((string)($row['title'] ?? ''));
            $needed = (int)($row['headcount_needed'] ?? 0);
            if ($title === '' || $needed < 1) {
                $errors[] = ['row' => $i + 1, 'field' => 'title/headcount_needed', 'message' => 'Title required and headcount must be > 0.'];
                continue;
            }
            $validRows++;
        }
        $invalidRows = max(0, $totalRows - $validRows);
        $batchId = 'BULK-' . date('YmdHis') . '-' . random_int(100, 999);
        $stmt = $conn->prepare("
            INSERT INTO wllp_bulk_batches
            (batch_id, client_id, employer_id, payload_json, total_rows, valid_rows, invalid_rows)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $payloadJson = json_encode($payload);
        $stmt->bind_param('ssisiii', $batchId, $auth['client_id'], $employerId, $payloadJson, $totalRows, $validRows, $invalidRows);
        $stmt->execute();
        $stmt->close();

        wllp_api_json_response(200, [
            'batch_id' => $batchId,
            'template_version' => 'WLLP-BULK-1.0',
            'total_rows' => $totalRows,
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'errors' => $errors,
        ]);
    }
}

if (!function_exists('wllp_api_handle_bulk_commit')) {
    function wllp_api_handle_bulk_commit(mysqli $conn, array $auth): void
    {
        $body = wllp_api_decode_json_body();
        wllp_api_require_fields($body, ['batch_id']);
        $terms = wllp_api_validate_terms($body);
        $batchId = (string)$body['batch_id'];

        $stmt = $conn->prepare("SELECT * FROM wllp_bulk_batches WHERE batch_id = ? LIMIT 1");
        $stmt->bind_param('s', $batchId);
        $stmt->execute();
        $res = $stmt->get_result();
        $batch = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$batch) {
            wllp_api_client_error(404, 'BATCH_NOT_FOUND', 'Batch not found.');
        }
        if (!empty($batch['committed_at'])) {
            wllp_api_client_error(409, 'BATCH_ALREADY_COMMITTED', 'Batch already committed.');
        }

        $payload = json_decode((string)$batch['payload_json'], true);
        if (!is_array($payload)) {
            wllp_api_client_error(422, 'BULK_TEMPLATE_INVALID', 'Stored batch payload invalid.');
        }

        $createPayload = [
            'employer_id' => (int)$batch['employer_id'],
            'unit_id' => (int)($payload['unit_id'] ?? 1),
            'period_type' => (string)($payload['period_type'] ?? 'monthly'),
            'period_anchor' => (string)($payload['period_anchor'] ?? date('Y-m-d')),
            'notes' => (string)($payload['notes'] ?? ('Bulk commit ' . $batchId)),
            'terms' => ['agreed' => (bool)$terms['agreed'], 'version' => $terms['version']],
            'items' => is_array($payload['rows'] ?? null) ? $payload['rows'] : [],
        ];
        $result = wllp_api_create_report_from_payload($conn, $createPayload, $auth);

        $upd = $conn->prepare("UPDATE wllp_bulk_batches SET committed_at = NOW() WHERE batch_id = ?");
        $upd->bind_param('s', $batchId);
        $upd->execute();
        $upd->close();

        wllp_api_json_response(200, [
            'success' => true,
            'batch_id' => $batchId,
            'report' => $result['report'],
            'items' => $result['items'],
        ]);
    }
}

if (!function_exists('wllp_api_handle_employer_dashboard')) {
    function wllp_api_handle_employer_dashboard(mysqli $conn): void
    {
        $employerId = wllp_api_get_query_int('employer_id', 0);
        if ($employerId <= 0) {
            wllp_api_client_error(422, 'VALIDATION_FAILED', 'employer_id query is required.');
        }
        $sql = "
            SELECT
                COUNT(DISTINCT r.id) AS total_reports,
                COUNT(DISTINCT i.id) AS total_items,
                SUM(CASE WHEN s.status = 'Terisi' THEN 1 ELSE 0 END) AS terisi_items,
                SUM(CASE WHEN s.status IS NULL OR s.status = 'Belum Terisi' THEN 1 ELSE 0 END) AS belum_terisi_items
            FROM wllp_reports r
            LEFT JOIN wllp_report_items i ON i.report_id = r.id
            LEFT JOIN wllp_item_statuses s ON s.item_id = i.id
            WHERE r.employer_id = ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $employerId);
        $stmt->execute();
        $res = $stmt->get_result();
        $summary = $res ? $res->fetch_assoc() : [];
        $stmt->close();

        wllp_api_json_response(200, ['success' => true, 'data' => $summary]);
    }
}

if (!function_exists('wllp_api_handle_karirhub_jobs_posted')) {
    function wllp_api_handle_karirhub_jobs_posted(): void
    {
        $dataset = karirhub_proto_dataset();
        $vacancies = $dataset['vacancies'] ?? [];
        $out = [];
        foreach ($vacancies as $idx => $v) {
            $jobId = (string)($v['id_lowongan'] ?? ('JOB-' . ($idx + 1)));
            $out[] = [
                'job_id' => $jobId,
                'title' => (string)($v['jabatan'] ?? 'Unknown Position'),
                'location' => trim((string)(($v['kota'] ?? '') . ', ' . ($v['provinsi'] ?? ''))),
                'status' => 'Aktif',
                'headcount' => (int)($v['jumlah_kebutuhan'] ?? 1),
                'posting_url' => (string)($v['alamat_url_postingan_loker'] ?? ''),
            ];
        }
        wllp_api_json_response(200, ['success' => true, 'data' => $out]);
    }
}

if (!function_exists('wllp_api_handle_add_job_to_wllp')) {
    function wllp_api_handle_add_job_to_wllp(mysqli $conn, string $jobId, array $auth): void
    {
        $body = wllp_api_decode_json_body();
        wllp_api_require_fields($body, ['period_type', 'period_anchor', 'employer_id', 'unit_id']);
        $terms = wllp_api_validate_terms($body);

        $dataset = karirhub_proto_dataset();
        $vacancies = $dataset['vacancies'] ?? [];
        $selected = null;
        foreach ($vacancies as $v) {
            if ((string)($v['id_lowongan'] ?? '') === $jobId) {
                $selected = $v;
                break;
            }
        }
        if ($selected === null) {
            wllp_api_client_error(404, 'JOB_NOT_FOUND', 'Karirhub job not found.');
        }

        $payload = [
            'employer_id' => (int)$body['employer_id'],
            'unit_id' => (int)$body['unit_id'],
            'period_type' => (string)$body['period_type'],
            'period_anchor' => (string)$body['period_anchor'],
            'notes' => 'Add from Karirhub posted jobs',
            'terms' => ['agreed' => (bool)$terms['agreed'], 'version' => $terms['version']],
            'items' => [[
                'job_id' => $jobId,
                'title' => (string)($selected['jabatan'] ?? 'Job'),
                'headcount_needed' => (int)($selected['jumlah_kebutuhan'] ?? 1),
                'gender_requirement' => (string)($selected['jenis_kelamin'] ?? 'Semua'),
                'age_min' => (int)($selected['usia_min'] ?? 18),
                'age_max' => (int)($selected['usia_max'] ?? 60),
                'education_min_id' => 5,
                'job_description' => (string)($selected['deskripsi_pekerjaan'] ?? ''),
                'skills' => (string)($selected['keterampilan_utama'] ?? ''),
                'experience_min_years' => (int)($selected['pengalaman_min_tahun'] ?? 0),
                'posting_url' => (string)($selected['alamat_url_postingan_loker'] ?? ''),
            ]],
        ];
        $result = wllp_api_create_report_from_payload($conn, $payload, $auth);
        $firstItem = $result['items'][0] ?? null;
        wllp_api_json_response(200, [
            'success' => true,
            'reused_report' => true,
            'no_reg_bukti' => $result['report']['no_reg_bukti'],
            'id_lowongan' => $firstItem['id_lowongan'] ?? null,
            'status_label' => 'Berhasil ditambahkan ke WLLP',
        ]);
    }
}

if (!function_exists('wllp_api_handle_admin_dashboard')) {
    function wllp_api_handle_admin_dashboard(mysqli $conn): void
    {
        $sql = "
            SELECT
                COUNT(DISTINCT employer_id) AS total_employers,
                COUNT(*) AS total_reports,
                (SELECT COUNT(*) FROM wllp_report_items) AS total_items,
                SUM(CASE WHEN verification_status = 'submitted' THEN 1 ELSE 0 END) AS submitted_reports,
                SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) AS verified_reports
            FROM wllp_reports
        ";
        $res = $conn->query($sql);
        $row = $res ? $res->fetch_assoc() : [];
        wllp_api_json_response(200, ['success' => true, 'data' => $row]);
    }
}

if (!function_exists('wllp_api_handle_admin_reports')) {
    function wllp_api_handle_admin_reports(mysqli $conn): void
    {
        $limit = min(500, max(1, wllp_api_get_query_int('limit', 100)));
        $offset = max(0, wllp_api_get_query_int('offset', 0));
        $stmt = $conn->prepare("
            SELECT id, no_reg_bukti, employer_id, employer_name, unit_name, period_type, period_start, period_end, verification_status, created_at
            FROM wllp_reports
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        $stmt->close();
        wllp_api_json_response(200, ['success' => true, 'data' => $rows]);
    }
}

if (!function_exists('wllp_api_handle_admin_compliance')) {
    function wllp_api_handle_admin_compliance(mysqli $conn): void
    {
        $sql = "
            SELECT
                r.employer_id,
                MAX(r.employer_name) AS employer_name,
                COUNT(DISTINCT r.id) AS reports,
                COUNT(i.id) AS total_items,
                SUM(CASE WHEN s.status = 'Terisi' THEN 1 ELSE 0 END) AS terisi_items,
                ROUND(
                    (SUM(CASE WHEN s.status = 'Terisi' THEN 1 ELSE 0 END) / NULLIF(COUNT(i.id), 0)) * 100,
                    2
                ) AS compliance_pct
            FROM wllp_reports r
            LEFT JOIN wllp_report_items i ON i.report_id = r.id
            LEFT JOIN wllp_item_statuses s ON s.item_id = i.id
            GROUP BY r.employer_id
            ORDER BY compliance_pct DESC
        ";
        $res = $conn->query($sql);
        $rows = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $rows[] = $r;
            }
        }
        wllp_api_json_response(200, ['success' => true, 'data' => $rows]);
    }
}

if (!function_exists('wllp_api_handle_admin_verification')) {
    function wllp_api_handle_admin_verification(mysqli $conn, int $reportId, array $auth): void
    {
        $body = wllp_api_decode_json_body();
        $status = trim((string)($body['status'] ?? ''));
        if (!in_array($status, ['verified', 'rejected', 'needs_update'], true)) {
            wllp_api_client_error(422, 'VALIDATION_FAILED', 'status must be verified, rejected, or needs_update.');
        }
        $note = trim((string)($body['note'] ?? ''));

        $sel = $conn->prepare("SELECT id, verification_status FROM wllp_reports WHERE id = ? LIMIT 1");
        $sel->bind_param('i', $reportId);
        $sel->execute();
        $res = $sel->get_result();
        $before = $res ? $res->fetch_assoc() : null;
        $sel->close();
        if (!$before) {
            wllp_api_client_error(404, 'REPORT_NOT_FOUND', 'Report not found.');
        }

        $upd = $conn->prepare("UPDATE wllp_reports SET verification_status = ? WHERE id = ?");
        $upd->bind_param('si', $status, $reportId);
        $upd->execute();
        $upd->close();

        $log = $conn->prepare("
            INSERT INTO wllp_verification_logs(report_id, actor_user_id, action, note)
            VALUES (?, NULL, ?, ?)
        ");
        $log->bind_param('iss', $reportId, $status, $note);
        $log->execute();
        $log->close();

        wllp_api_write_audit_log(
            $conn,
            'wllp_report',
            (string)$reportId,
            'verification_update',
            ['verification_status' => $before['verification_status']],
            ['verification_status' => $status, 'note' => $note],
            $auth
        );
        wllp_api_json_response(200, ['success' => true, 'report_id' => $reportId, 'verification_status' => $status]);
    }
}

if (!function_exists('wllp_api_handle_admin_export')) {
    function wllp_api_handle_admin_export(mysqli $conn): void
    {
        $sql = "
            SELECT
                r.id,
                r.no_reg_bukti,
                r.employer_id,
                r.employer_name,
                r.unit_name,
                r.period_type,
                r.period_start,
                r.period_end,
                r.verification_status,
                COUNT(i.id) AS total_items
            FROM wllp_reports r
            LEFT JOIN wllp_report_items i ON i.report_id = r.id
            GROUP BY r.id
            ORDER BY r.id DESC
        ";
        $res = $conn->query($sql);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="wllp-admin-export.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['report_id', 'no_reg_bukti', 'employer_id', 'employer_name', 'unit_name', 'period_type', 'period_start', 'period_end', 'verification_status', 'total_items']);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                fputcsv($out, $row);
            }
        }
        fclose($out);
        exit;
    }
}

