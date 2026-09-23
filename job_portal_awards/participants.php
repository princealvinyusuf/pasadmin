<?php
require_once __DIR__ . '/bootstrap.php';
jpa_require_any(['job_portal_award_manage_data']);

function jpa_parse_bool($value): array
{
    $normalized = strtolower(trim((string)$value));
    if (in_array($normalized, ['1', 'true', 'yes', 'ya', 'y'], true)) {
        return [1, true];
    }
    if (in_array($normalized, ['0', 'false', 'no', 'tidak', 'n', ''], true)) {
        return [0, true];
    }
    return [0, false];
}

function jpa_import_headers(): array
{
    return [
        'partner_id','partner_name','integration_type','partnership_active','critical_violation_resolved','data_traceable',
        'records_sent_unique','published_unique_count','active_months','complete_vacancy_count','employer_unique_count',
        'employer_valid_legal_count','duplicate_vacancy_count','valid_complaint_count','severe_complaint_count',
        'applications_from_karirhub','progressed_candidate_count','hired_candidate_count',
    ];
}

function jpa_validate_participant_row(array $input, array $period): array
{
    $errors = [];
    $data = [
        'partner_id' => trim((string)($input['partner_id'] ?? '')),
        'partner_name' => trim((string)($input['partner_name'] ?? '')),
        'integration_type' => strtolower(trim((string)($input['integration_type'] ?? 'inactive'))),
    ];
    foreach (['partnership_active','critical_violation_resolved','data_traceable'] as $booleanField) {
        [$booleanValue, $booleanValid] = jpa_parse_bool($input[$booleanField] ?? 0);
        $data[$booleanField] = $booleanValue;
        if (!$booleanValid) {
            $errors[] = $booleanField . ' harus bernilai 1/0, true/false, yes/no, atau ya/tidak.';
        }
    }
    $integerFields = [
        'records_sent_unique', 'published_unique_count', 'active_months', 'complete_vacancy_count',
        'employer_unique_count', 'employer_valid_legal_count', 'duplicate_vacancy_count',
        'valid_complaint_count', 'severe_complaint_count', 'applications_from_karirhub',
        'progressed_candidate_count', 'hired_candidate_count',
    ];
    foreach ($integerFields as $field) {
        $raw = $input[$field] ?? 0;
        if (filter_var($raw, FILTER_VALIDATE_INT) === false || intval($raw) < 0) {
            $errors[] = $field . ' harus bilangan bulat non-negatif.';
        }
        $data[$field] = max(0, intval($raw));
    }
    if ($data['partner_id'] === '') {
        $errors[] = 'partner_id wajib diisi.';
    } elseif (strlen($data['partner_id']) > 120) {
        $errors[] = 'partner_id maksimal 120 karakter.';
    }
    if ($data['partner_name'] === '') {
        $errors[] = 'partner_name wajib diisi.';
    } elseif (strlen($data['partner_name']) > 255) {
        $errors[] = 'partner_name maksimal 255 karakter.';
    }
    if (!in_array($data['integration_type'], ['full', 'semi', 'inactive'], true)) {
        $errors[] = 'integration_type harus full, semi, atau inactive.';
    }
    if ($data['active_months'] > jpa_months_in_period($period['period_start'], $period['period_end'])) {
        $errors[] = 'active_months melebihi jumlah bulan periode.';
    }
    $relationships = [
        ['complete_vacancy_count', 'published_unique_count'],
        ['employer_valid_legal_count', 'employer_unique_count'],
        ['duplicate_vacancy_count', 'records_sent_unique'],
        ['severe_complaint_count', 'valid_complaint_count'],
        ['progressed_candidate_count', 'applications_from_karirhub'],
        ['hired_candidate_count', 'applications_from_karirhub'],
    ];
    foreach ($relationships as [$numerator, $denominator]) {
        if ($data[$numerator] > $data[$denominator]) {
            $errors[] = $numerator . ' tidak boleh melebihi ' . $denominator . '.';
        }
    }
    return [$data, $errors];
}

function jpa_sync_severe_complaint_flag(mysqli $conn, int $participantId, int $periodId, int $severeCount, int $userId): void
{
    $stmt = $conn->prepare("SELECT * FROM job_portal_award_red_flags
        WHERE participant_id=? AND code='RF-1' AND auto_generated=1 ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('i', $participantId);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();

    if ($severeCount <= 0) {
        if ($existing && $existing['status'] === 'pending') {
            $notes = 'Ditutup otomatis karena severe_complaint_count diperbarui menjadi 0.';
            $stmt = $conn->prepare("UPDATE job_portal_award_red_flags SET
                status='dismissed',consequence='review',committee_notes=?,source_metric_count=0,
                decided_by=?,decided_at=NOW() WHERE id=? AND status='pending'");
            $stmt->bind_param('sii', $notes, $userId, $existing['id']);
            $stmt->execute();
            $stmt->close();
            jpa_audit($conn, $periodId, 'red_flag.auto_dismissed', 'red_flag', $existing['id'], $existing, ['source_metric_count' => 0], $notes);
        } elseif ($existing && $existing['status'] === 'dismissed' && intval($existing['source_metric_count']) !== 0) {
            $stmt = $conn->prepare('UPDATE job_portal_award_red_flags SET source_metric_count=0 WHERE id=?');
            $stmt->bind_param('i', $existing['id']);
            $stmt->execute();
            $stmt->close();
        }
        return;
    }
    $description = "Sistem mendeteksi {$severeCount} aduan berat terverifikasi pada data penilaian.";
    $evidence = 'Sumber: severe_complaint_count pada import/manual entry; komite wajib mencantumkan referensi bukti pada catatan keputusan.';
    if (!$existing) {
        $stmt = $conn->prepare("INSERT INTO job_portal_award_red_flags
            (participant_id,code,description,evidence_reference,auto_generated,source_metric_count,created_by)
            VALUES (?,'RF-1',?,?,1,?,?)");
        $stmt->bind_param('issii', $participantId, $description, $evidence, $severeCount, $userId);
        $stmt->execute();
        $flagId = intval($conn->insert_id);
        $stmt->close();
        jpa_audit($conn, $periodId, 'red_flag.auto_created', 'red_flag', $flagId, null, ['participant_id' => $participantId, 'severe_complaint_count' => $severeCount]);
        return;
    }
    if ($existing['status'] === 'pending') {
        $stmt = $conn->prepare('UPDATE job_portal_award_red_flags SET description=?,source_metric_count=? WHERE id=?');
        $stmt->bind_param('sii', $description, $severeCount, $existing['id']);
        $stmt->execute();
        $stmt->close();
    } elseif ($existing['status'] === 'dismissed' && intval($existing['source_metric_count']) !== $severeCount) {
        $stmt = $conn->prepare("UPDATE job_portal_award_red_flags SET
            description=?,source_metric_count=?,status='pending',consequence='review',
            committee_notes=NULL,decided_by=NULL,decided_at=NULL WHERE id=?");
        $stmt->bind_param('sii', $description, $severeCount, $existing['id']);
        $stmt->execute();
        $stmt->close();
        jpa_audit($conn, $periodId, 'red_flag.auto_reopened', 'red_flag', $existing['id'], $existing, ['source_metric_count' => $severeCount]);
    }
}

function jpa_upsert_participant(mysqli $conn, int $periodId, array $data, ?int $importId, int $userId, string $reason): int
{
    $stmt = $conn->prepare("INSERT INTO job_portal_award_participants
        (period_id,partner_id,partner_name,integration_type,partnership_active,critical_violation_resolved,
         data_traceable,records_sent_unique,published_unique_count,active_months,complete_vacancy_count,
         employer_unique_count,employer_valid_legal_count,duplicate_vacancy_count,valid_complaint_count,
         severe_complaint_count,applications_from_karirhub,progressed_candidate_count,hired_candidate_count,
         source_import_id,change_reason,created_by,updated_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
         partner_name=VALUES(partner_name),integration_type=VALUES(integration_type),
         partnership_active=VALUES(partnership_active),critical_violation_resolved=VALUES(critical_violation_resolved),
         data_traceable=VALUES(data_traceable),records_sent_unique=VALUES(records_sent_unique),
         published_unique_count=VALUES(published_unique_count),active_months=VALUES(active_months),
         complete_vacancy_count=VALUES(complete_vacancy_count),employer_unique_count=VALUES(employer_unique_count),
         employer_valid_legal_count=VALUES(employer_valid_legal_count),duplicate_vacancy_count=VALUES(duplicate_vacancy_count),
         valid_complaint_count=VALUES(valid_complaint_count),severe_complaint_count=VALUES(severe_complaint_count),
         applications_from_karirhub=VALUES(applications_from_karirhub),
         progressed_candidate_count=VALUES(progressed_candidate_count),hired_candidate_count=VALUES(hired_candidate_count),
         source_import_id=VALUES(source_import_id),change_reason=VALUES(change_reason),updated_by=VALUES(updated_by)");
    $types = 'isss' . str_repeat('i', 16) . 'sii';
    $stmt->bind_param(
        $types,
        $periodId,
        $data['partner_id'],
        $data['partner_name'],
        $data['integration_type'],
        $data['partnership_active'],
        $data['critical_violation_resolved'],
        $data['data_traceable'],
        $data['records_sent_unique'],
        $data['published_unique_count'],
        $data['active_months'],
        $data['complete_vacancy_count'],
        $data['employer_unique_count'],
        $data['employer_valid_legal_count'],
        $data['duplicate_vacancy_count'],
        $data['valid_complaint_count'],
        $data['severe_complaint_count'],
        $data['applications_from_karirhub'],
        $data['progressed_candidate_count'],
        $data['hired_candidate_count'],
        $importId,
        $reason,
        $userId,
        $userId
    );
    $stmt->execute();
    $stmt->close();
    $lookup = $conn->prepare('SELECT id FROM job_portal_award_participants WHERE period_id=? AND partner_id=?');
    $lookup->bind_param('is', $periodId, $data['partner_id']);
    $lookup->execute();
    $row = $lookup->get_result()->fetch_assoc();
    $lookup->close();
    return intval($row['id'] ?? 0);
}

$period = jpa_selected_period($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    jpa_verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $periodId = intval($_POST['period_id'] ?? 0);
    $period = jpa_get_period($conn, $periodId);
    if (!$period || $period['status'] !== 'locked') {
        http_response_code(409);
        exit('Data peserta hanya dapat diubah pada periode berstatus locked.');
    }
    $userId = intval($_SESSION['user_id'] ?? 0);

    if ($action === 'save_manual') {
        [$data, $errors] = jpa_validate_participant_row($_POST, $period);
        $reason = trim((string)($_POST['reason'] ?? ''));
        if ($reason === '') {
            $errors[] = 'Alasan penambahan/perubahan wajib diisi.';
        } elseif (strlen($reason) > 500) {
            $errors[] = 'Alasan maksimal 500 karakter.';
        }
        if ($errors) {
            jpa_set_flash('danger', implode(' ', $errors));
            jpa_redirect('participants?period_id=' . $periodId . '&edit_id=' . intval($_POST['edit_id'] ?? 0));
        }
        $conn->begin_transaction();
        try {
            $lockedPeriod = jpa_lock_period($conn, $periodId);
            if (!$lockedPeriod || $lockedPeriod['status'] !== 'locked') {
                throw new RuntimeException('Periode tidak lagi dapat diubah.');
            }
            $editId = intval($_POST['edit_id'] ?? 0);
            if ($editId > 0) {
                $editRow = jpa_get_participant($conn, $editId);
                if (!$editRow || intval($editRow['period_id']) !== $periodId || $editRow['partner_id'] !== $data['partner_id']) {
                    throw new RuntimeException('Identitas peserta yang dikoreksi tidak valid.');
                }
            }
            $find = $conn->prepare('SELECT * FROM job_portal_award_participants WHERE period_id=? AND partner_id=?');
            $find->bind_param('is', $periodId, $data['partner_id']);
            $find->execute();
            $existing = $find->get_result()->fetch_assoc() ?: null;
            $find->close();
            $participantId = jpa_upsert_participant($conn, $periodId, $data, null, $userId, $reason);
            $after = jpa_recalculate_participant($conn, $participantId);
            jpa_sync_severe_complaint_flag($conn, $participantId, $periodId, $data['severe_complaint_count'], $userId);
            jpa_recalculate_period($conn, $periodId, false);
            jpa_audit($conn, $periodId, $existing ? 'participant.updated' : 'participant.created', 'participant', $participantId, $existing, $after, $reason);
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
        jpa_set_flash('success', 'Data peserta disimpan dan skor dihitung ulang.');
        jpa_redirect('participants?period_id=' . $periodId);
    }

    if ($action === 'import') {
        $payload = (string)($_POST['import_payload'] ?? '');
        if (strlen($payload) > 5000000) {
            jpa_set_flash('danger', 'Payload import melebihi batas 5 MB.');
            jpa_redirect('participants?period_id=' . $periodId);
        }
        $rows = json_decode($payload, true);
        $filename = substr(trim((string)($_POST['filename'] ?? 'import.xlsx')), 0, 255);
        $reason = trim((string)($_POST['reason'] ?? ''));
        if (!is_array($rows) || !$rows || count($rows) > 1000 || $reason === '' || strlen($reason) > 500) {
            jpa_set_flash('danger', 'File harus berisi 1–1000 baris dan alasan import wajib diisi.');
            jpa_redirect('participants?period_id=' . $periodId);
        }
        $errors = [];
        $validRows = [];
        $seen = [];
        $requiredHeaders = jpa_import_headers();
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                $errors[] = ['row' => $index + 2, 'errors' => ['Format baris tidak valid.']];
                continue;
            }
            $missingHeaders = array_values(array_diff($requiredHeaders, array_keys($row)));
            [$data, $rowErrors] = jpa_validate_participant_row($row, $period);
            if ($missingHeaders) {
                $rowErrors[] = 'Kolom wajib hilang: ' . implode(', ', $missingHeaders) . '.';
            }
            if (isset($seen[$data['partner_id']])) {
                $rowErrors[] = 'partner_id duplikat di dalam file.';
            }
            $seen[$data['partner_id']] = true;
            if ($rowErrors) {
                $errors[] = ['row' => $index + 2, 'partner_id' => $data['partner_id'], 'errors' => $rowErrors];
            } else {
                $validRows[] = $data;
            }
        }

        $conn->begin_transaction();
        try {
            $lockedPeriod = jpa_lock_period($conn, $periodId);
            if (!$lockedPeriod || $lockedPeriod['status'] !== 'locked') {
                throw new RuntimeException('Periode tidak lagi dapat diubah.');
            }
            $status = 'processing';
            $total = count($rows);
            $zero = 0;
            $stmt = $conn->prepare("INSERT INTO job_portal_award_imports
                (period_id,original_filename,total_rows,successful_rows,failed_rows,status,imported_by)
                VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param('isiiisi', $periodId, $filename, $total, $zero, $zero, $status, $userId);
            $stmt->execute();
            $importId = intval($conn->insert_id);
            $stmt->close();
            foreach ($validRows as $data) {
                $find = $conn->prepare('SELECT * FROM job_portal_award_participants WHERE period_id=? AND partner_id=?');
                $find->bind_param('is', $periodId, $data['partner_id']);
                $find->execute();
                $before = $find->get_result()->fetch_assoc() ?: null;
                $find->close();
                $participantId = jpa_upsert_participant($conn, $periodId, $data, $importId, $userId, $reason);
                $after = jpa_recalculate_participant($conn, $participantId);
                jpa_sync_severe_complaint_flag($conn, $participantId, $periodId, $data['severe_complaint_count'], $userId);
                jpa_audit($conn, $periodId, $before ? 'participant.import_updated' : 'participant.import_created', 'participant', $participantId, $before, $after, $reason);
            }
            $success = count($validRows);
            $failed = count($errors);
            $errorJson = jpa_json($errors);
            $finalStatus = $success > 0 ? 'completed' : 'failed';
            $update = $conn->prepare("UPDATE job_portal_award_imports SET successful_rows=?,failed_rows=?,status=?,errors_json=? WHERE id=?");
            $update->bind_param('iissi', $success, $failed, $finalStatus, $errorJson, $importId);
            $update->execute();
            $update->close();
            jpa_audit($conn, $periodId, 'participants.imported', 'import', $importId, null, ['filename' => $filename, 'success' => $success, 'failed' => $failed], $reason);
            jpa_recalculate_period($conn, $periodId, false);
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
        jpa_set_flash($failed ? 'warning' : 'success', "Import selesai: {$success} berhasil, {$failed} ditolak. Detail tersimpan pada riwayat import.");
        jpa_redirect('participants?period_id=' . $periodId);
    }
}

$edit = null;
if ($period && intval($_GET['edit_id'] ?? 0) > 0) {
    $edit = jpa_get_participant($conn, intval($_GET['edit_id']));
    if ($edit && intval($edit['period_id']) !== intval($period['id'])) {
        $edit = null;
    }
}
$participants = [];
$imports = [];
if ($period) {
    $stmt = $conn->prepare('SELECT * FROM job_portal_award_participants WHERE period_id=? ORDER BY partner_name');
    $stmt->bind_param('i', $period['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $participants[] = $row;
    }
    $stmt->close();
    $stmt = $conn->prepare('SELECT * FROM job_portal_award_imports WHERE period_id=? ORDER BY imported_at DESC LIMIT 20');
    $stmt->bind_param('i', $period['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $imports[] = $row;
    }
    $stmt->close();
}

$headers = jpa_import_headers();

jpa_render_header('Peserta & Data Import', $period);
?>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h1 class="h3 mb-1">Peserta &amp; Data Import</h1><p class="text-muted mb-0">Kunci parameter periode sebelum mengubah data peserta.</p></div>
    <?php jpa_render_period_selector($conn, $period, 'participants'); ?>
</div>
<?php if (!$period): ?>
    <div class="alert alert-info">Buat periode penilaian terlebih dahulu.</div>
<?php else: ?>
    <div class="alert alert-<?php echo $period['status'] === 'locked' ? 'success' : 'warning'; ?>">Status periode: <strong><?php echo htmlspecialchars($period['status']); ?></strong></div>
    <?php if ($period['status'] === 'locked'): ?>
    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between"><strong>Import Excel</strong><button type="button" id="downloadTemplate" class="btn btn-sm btn-outline-primary">Download Template</button></div>
                <div class="card-body">
                    <form method="post" id="importForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(jpa_csrf_token()); ?>">
                        <input type="hidden" name="action" value="import">
                        <input type="hidden" name="period_id" value="<?php echo intval($period['id']); ?>">
                        <input type="hidden" name="filename" id="filename">
                        <input type="hidden" name="import_payload" id="importPayload">
                        <input type="file" class="form-control mb-3" id="excelFile" accept=".xlsx,.xls,.csv" required>
                        <input class="form-control mb-3" name="reason" required placeholder="Alasan / sumber import dan nomor berita acara">
                        <div id="preview" class="small text-muted mb-3">Pilih file untuk melihat validasi awal.</div>
                        <button class="btn btn-primary" id="importButton" disabled>Import Data</button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header"><strong><?php echo $edit ? 'Koreksi Peserta' : 'Tambah Peserta Manual'; ?></strong></div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(jpa_csrf_token()); ?>">
                        <input type="hidden" name="action" value="save_manual">
                        <input type="hidden" name="period_id" value="<?php echo intval($period['id']); ?>">
                        <input type="hidden" name="edit_id" value="<?php echo intval($edit['id'] ?? 0); ?>">
                        <div class="row g-2">
                            <div class="col-5"><input class="form-control" name="partner_id" required placeholder="partner_id" value="<?php echo htmlspecialchars($edit['partner_id'] ?? ''); ?>" <?php echo $edit ? 'readonly' : ''; ?>></div>
                            <div class="col-7"><input class="form-control" name="partner_name" required placeholder="Nama portal" value="<?php echo htmlspecialchars($edit['partner_name'] ?? ''); ?>"></div>
                            <div class="col-6"><select class="form-select" name="integration_type"><?php foreach (['full','semi','inactive'] as $type): ?><option <?php echo ($edit['integration_type'] ?? '') === $type ? 'selected' : ''; ?>><?php echo $type; ?></option><?php endforeach; ?></select></div>
                            <?php foreach (['partnership_active'=>'Mitra aktif','critical_violation_resolved'=>'Pelanggaran selesai','data_traceable'=>'Data terlacak'] as $field=>$label): ?>
                                <div class="col-6"><label class="form-check"><input class="form-check-input" type="checkbox" name="<?php echo $field; ?>" value="1" <?php echo !empty($edit[$field]) ? 'checked' : ''; ?>> <?php echo $label; ?></label></div>
                            <?php endforeach; ?>
                            <?php foreach (array_slice($headers, 6) as $field): ?>
                                <div class="col-6"><label class="form-label small mb-0"><?php echo htmlspecialchars($field); ?></label><input type="number" min="0" class="form-control form-control-sm" name="<?php echo $field; ?>" value="<?php echo intval($edit[$field] ?? 0); ?>"></div>
                            <?php endforeach; ?>
                            <div class="col-12"><input class="form-control" name="reason" required placeholder="Alasan penambahan/koreksi"></div>
                            <div class="col-12 d-flex gap-2"><button class="btn btn-primary">Simpan &amp; Hitung</button><?php if ($edit): ?><a class="btn btn-outline-secondary" href="participants?period_id=<?php echo intval($period['id']); ?>">Batal</a><?php endif; ?></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header"><strong>Daftar Peserta (<?php echo count($participants); ?>)</strong></div>
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0"><thead><tr><th>Partner ID</th><th>Portal</th><th>Integrasi</th><th>Eligibility</th><th>Nilai</th><th></th></tr></thead><tbody>
                <?php foreach ($participants as $row): ?><tr>
                    <td><?php echo htmlspecialchars($row['partner_id']); ?></td><td><?php echo htmlspecialchars($row['partner_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['integration_type']); ?></td><td><?php echo htmlspecialchars($row['eligibility_status']); ?></td>
                    <td class="jpa-score"><?php echo number_format((float)$row['final_score'], 2); ?></td>
                    <td><?php if ($period['status'] === 'locked'): ?><a class="btn btn-sm btn-outline-primary" href="participants?period_id=<?php echo intval($period['id']); ?>&edit_id=<?php echo intval($row['id']); ?>">Koreksi</a><?php endif; ?> <a class="btn btn-sm btn-outline-secondary" href="participant?participant_id=<?php echo intval($row['id']); ?>">Detail</a></td>
                </tr><?php endforeach; ?>
                <?php if (!$participants): ?><tr><td colspan="6" class="text-center text-muted py-4">Belum ada peserta.</td></tr><?php endif; ?>
            </tbody></table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Riwayat Import</strong></div>
        <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Waktu</th><th>File</th><th>Status</th><th>Berhasil</th><th>Ditolak</th><th>Kesalahan</th></tr></thead><tbody>
            <?php foreach ($imports as $item): ?><tr><td><?php echo htmlspecialchars($item['imported_at']); ?></td><td><?php echo htmlspecialchars($item['original_filename']); ?></td><td><?php echo htmlspecialchars($item['status']); ?></td><td><?php echo intval($item['successful_rows']); ?></td><td><?php echo intval($item['failed_rows']); ?></td><td><details><summary>Lihat</summary><pre class="small"><?php echo htmlspecialchars($item['errors_json'] ?: '[]'); ?></pre></details></td></tr><?php endforeach; ?>
            <?php if (!$imports): ?><tr><td colspan="6" class="text-center text-muted">Belum ada import.</td></tr><?php endif; ?>
        </tbody></table></div>
    </div>
<?php endif; ?>
<script>
const templateHeaders = <?php echo jpa_json($headers); ?>;
document.getElementById('downloadTemplate')?.addEventListener('click', () => {
    const example = {};
    templateHeaders.forEach(h => example[h] = h.includes('name') ? 'Contoh Portal' : (h === 'partner_id' ? 'JOSS-001' : (h === 'integration_type' ? 'full' : 0)));
    example.partnership_active = 1; example.critical_violation_resolved = 1; example.data_traceable = 1;
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, XLSX.utils.json_to_sheet([example], {header: templateHeaders}), 'Participants');
    XLSX.writeFile(workbook, 'job_portal_awards_import_template.xlsx');
});
document.getElementById('excelFile')?.addEventListener('change', async function () {
    const file = this.files[0];
    if (!file) return;
    const workbook = XLSX.read(await file.arrayBuffer(), {type:'array'});
    const rows = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]], {defval:0});
    const missing = templateHeaders.filter(h => rows.length && !(h in rows[0]));
    document.getElementById('filename').value = file.name;
    document.getElementById('importPayload').value = JSON.stringify(rows);
    const okay = rows.length > 0 && rows.length <= 1000 && missing.length === 0;
    document.getElementById('importButton').disabled = !okay;
    document.getElementById('preview').innerHTML = okay
        ? `<span class="text-success">${rows.length} baris siap dikirim untuk validasi server.</span>`
        : `<span class="text-danger">Tidak valid. Baris: ${rows.length}. Kolom hilang: ${missing.join(', ') || '-'}</span>`;
});
</script>
<?php jpa_render_footer(); ?>

