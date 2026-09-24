<?php

const JPA_DEFAULT_WEIGHTS = [
    'integration' => 10,
    'volume' => 20,
    'consistency' => 10,
    'completeness' => 20,
    'kyb' => 10,
    'duplicate' => 5,
    'complaint' => 10,
    'progression' => 10,
    'placement' => 5,
];

const JPA_ELIGIBILITY_GATES = [
    'partnership_active' => 'E1',
    'active_months' => 'E2',
    'critical_violation_resolved' => 'E3',
    'data_traceable' => 'E4',
];

function jpa_clamp(float $value, float $minimum = 0.0, float $maximum = 100.0): float
{
    return max($minimum, min($maximum, $value));
}

function jpa_percentage(float $numerator, float $denominator): float
{
    if ($denominator <= 0) {
        return 0.0;
    }
    return ($numerator / $denominator) * 100.0;
}

function jpa_months_in_period(string $start, string $end): int
{
    try {
        $from = new DateTimeImmutable($start);
        $to = new DateTimeImmutable($end);
    } catch (Throwable $e) {
        return 1;
    }
    if ($from > $to) {
        return 1;
    }
    return max(1, (($to->format('Y') - $from->format('Y')) * 12) + ($to->format('n') - $from->format('n')) + 1);
}

function jpa_compute_scores(array $metrics, array $config): array
{
    $weights = $config['weights'] ?? JPA_DEFAULT_WEIGHTS;
    $months = max(1, intval($config['months_in_period'] ?? 1));
    $targetVolume = max(1, floatval($config['target_volume'] ?? 1));
    $penalty = max(0, floatval($config['complaint_penalty_factor'] ?? 25));
    $targetProgression = max(0.0001, floatval($config['target_progression_rate'] ?? 1));
    $targetPlacement = max(0.0001, floatval($config['target_placement_rate'] ?? 1));
    $impactEnabled = !empty($config['impact_module_enabled']);

    $integrationMap = ['full' => 100.0, 'semi' => 60.0, 'inactive' => 0.0];
    $integrationType = strtolower((string)($metrics['integration_type'] ?? 'inactive'));
    $published = max(0, floatval($metrics['published_unique_count'] ?? 0));
    $recordsSent = max(0, floatval($metrics['records_sent_unique'] ?? 0));
    $employers = max(0, floatval($metrics['employer_unique_count'] ?? 0));
    $applications = max(0, floatval($metrics['applications_from_karirhub'] ?? 0));

    $scores = [
        'integration' => $integrationMap[$integrationType] ?? 0.0,
        'volume' => jpa_clamp(jpa_percentage($published, $targetVolume)),
        'consistency' => jpa_clamp(jpa_percentage(floatval($metrics['active_months'] ?? 0), $months)),
        'completeness' => jpa_clamp(jpa_percentage(floatval($metrics['complete_vacancy_count'] ?? 0), $published)),
        'kyb' => jpa_clamp(jpa_percentage(floatval($metrics['employer_valid_legal_count'] ?? 0), $employers)),
        'duplicate' => $recordsSent > 0
            ? jpa_clamp(100.0 - jpa_percentage(floatval($metrics['duplicate_vacancy_count'] ?? 0), $recordsSent))
            : 0.0,
        'complaint' => $published > 0
            ? jpa_clamp(100.0 - (jpa_percentage(floatval($metrics['valid_complaint_count'] ?? 0), $published) * 10.0 * $penalty))
            : 0.0,
        'progression' => 0.0,
        'placement' => 0.0,
    ];

    if ($impactEnabled) {
        $progressionRate = jpa_percentage(floatval($metrics['progressed_candidate_count'] ?? 0), $applications);
        $placementRate = jpa_percentage(floatval($metrics['hired_candidate_count'] ?? 0), $applications);
        $scores['progression'] = jpa_clamp(($progressionRate / $targetProgression) * 100.0);
        $scores['placement'] = jpa_clamp(($placementRate / $targetPlacement) * 100.0);
    }

    $coreKeys = ['integration', 'volume', 'consistency', 'completeness', 'kyb', 'duplicate', 'complaint'];
    $impactKeys = ['progression', 'placement'];
    $activeKeys = $impactEnabled ? array_merge($coreKeys, $impactKeys) : $coreKeys;
    $weighted = [];
    $weightedTotal = 0.0;
    $activeWeight = 0.0;
    foreach ($scores as $key => $score) {
        $weighted[$key] = round($score * floatval($weights[$key] ?? 0) / 100.0, 4);
        if (in_array($key, $activeKeys, true)) {
            $weightedTotal += $weighted[$key];
            $activeWeight += floatval($weights[$key] ?? 0);
        }
    }
    $final = $activeWeight > 0 ? ($weightedTotal / $activeWeight) * 100.0 : 0.0;

    return [
        'scores' => array_map(static fn($v) => round($v, 4), $scores),
        'weighted' => $weighted,
        'active_weight' => $activeWeight,
        'final_score' => round(jpa_clamp($final), 4),
    ];
}

function jpa_evaluate_eligibility(array $participant, array $period): array
{
    $reasons = [];
    if (empty($participant['partnership_active'])) {
        $reasons[] = JPA_ELIGIBILITY_GATES['partnership_active'] . ': Status kemitraan tidak aktif.';
    }
    if (intval($participant['active_months'] ?? 0) < intval($period['min_active_months'] ?? 3)) {
        $reasons[] = JPA_ELIGIBILITY_GATES['active_months'] . ': Bulan aktif kurang dari minimum.';
    }
    if (empty($participant['critical_violation_resolved'])) {
        $reasons[] = JPA_ELIGIBILITY_GATES['critical_violation_resolved'] . ': Pelanggaran kritis belum diselesaikan.';
    }
    if (empty($participant['data_traceable'])) {
        $reasons[] = JPA_ELIGIBILITY_GATES['data_traceable'] . ': Data tidak dapat ditelusuri secara memadai.';
    }
    return [
        'status' => empty($reasons) ? 'eligible' : 'ineligible',
        'reasons' => $reasons,
    ];
}

function jpa_rank_rows(array $rows): array
{
    $eligible = array_values(array_filter($rows, static function (array $row): bool {
        return ($row['eligibility_status'] ?? '') === 'eligible' && empty($row['ranking_blocked']);
    }));

    usort($eligible, static function (array $a, array $b): int {
        $scoreA = floatval($a['final_score'] ?? 0);
        $scoreB = floatval($b['final_score'] ?? 0);
        if ($scoreA !== $scoreB) {
            return $scoreA < $scoreB ? 1 : -1;
        }
        $nameCompare = strcasecmp((string)($a['partner_name'] ?? ''), (string)($b['partner_name'] ?? ''));
        return $nameCompare !== 0 ? $nameCompare : (intval($a['id'] ?? 0) <=> intval($b['id'] ?? 0));
    });

    $groups = [];
    foreach ($eligible as $row) {
        $lastIndex = count($groups) - 1;
        if ($lastIndex < 0 || abs(floatval($groups[$lastIndex][0]['final_score']) - floatval($row['final_score'])) >= 0.5) {
            $groups[] = [$row];
        } else {
            $groups[$lastIndex][] = $row;
        }
    }
    $eligible = [];
    foreach ($groups as $group) {
        usort($group, static function (array $a, array $b): int {
        foreach (['score_complaint', 'score_completeness', 'published_unique_count'] as $field) {
            $aValue = floatval($a[$field] ?? 0);
            $bValue = floatval($b[$field] ?? 0);
            if ($aValue !== $bValue) {
                return $aValue < $bValue ? 1 : -1;
            }
        }
        $nameCompare = strcasecmp((string)($a['partner_name'] ?? ''), (string)($b['partner_name'] ?? ''));
        if ($nameCompare !== 0) {
            return $nameCompare;
        }
        return intval($a['id'] ?? 0) <=> intval($b['id'] ?? 0);
        });
        array_push($eligible, ...$group);
    }

    foreach ($eligible as $index => &$row) {
        $row['award_rank'] = $index + 1;
    }
    unset($row);
    return $eligible;
}

function jpa_json($value): string
{
    return (string)json_encode(
        $value,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES |
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
}

function jpa_participant_field_definitions(): array
{
    return [
        'partner_id' => [
            'label' => 'Kode Mitra',
            'description' => 'Kode unik portal atau mitra. Gunakan kode yang sama pada setiap pembaruan dalam satu periode.',
            'example' => 'PORTAL-001',
        ],
        'partner_name' => [
            'label' => 'Nama Portal',
            'description' => 'Nama portal lowongan kerja yang menjadi peserta penilaian.',
            'example' => 'Portal Karier Nusantara',
        ],
        'integration_type' => [
            'label' => 'Jenis Integrasi',
            'description' => 'Full: data terhubung penuh; Semi: sebagian proses masih manual; Tidak aktif: tidak ada integrasi aktif.',
            'example' => 'full',
        ],
        'partnership_active' => [
            'label' => 'Kemitraan Aktif',
            'description' => 'Centang jika kerja sama dengan mitra masih aktif selama periode penilaian.',
            'example' => 1,
        ],
        'critical_violation_resolved' => [
            'label' => 'Pelanggaran Kritis Selesai',
            'description' => 'Centang jika seluruh pelanggaran kritis telah diselesaikan oleh mitra.',
            'example' => 1,
        ],
        'data_traceable' => [
            'label' => 'Data Dapat Ditelusuri',
            'description' => 'Centang jika sumber dan riwayat data dapat diverifikasi.',
            'example' => 1,
        ],
        'records_sent_unique' => [
            'label' => 'Data Lowongan Unik Dikirim',
            'description' => 'Jumlah data lowongan unik yang dikirim mitra sebelum proses validasi dan publikasi.',
            'example' => 12500,
        ],
        'published_unique_count' => [
            'label' => 'Lowongan Unik Berhasil Tayang',
            'description' => 'Jumlah lowongan unik dari mitra yang berhasil dipublikasikan.',
            'example' => 10000,
        ],
        'active_months' => [
            'label' => 'Jumlah Bulan Aktif',
            'description' => 'Jumlah bulan dalam periode penilaian ketika mitra aktif memasok data.',
            'example' => 6,
        ],
        'complete_vacancy_count' => [
            'label' => 'Lowongan dengan Data Lengkap',
            'description' => 'Jumlah lowongan tayang yang seluruh atribut wajibnya terisi.',
            'example' => 9200,
        ],
        'employer_unique_count' => [
            'label' => 'Pemberi Kerja Unik',
            'description' => 'Jumlah pemberi kerja unik yang mengirim lowongan melalui mitra.',
            'example' => 800,
        ],
        'employer_valid_legal_count' => [
            'label' => 'Pemberi Kerja Berlegalitas Valid',
            'description' => 'Jumlah pemberi kerja unik yang legalitas atau KYB-nya telah dinyatakan valid.',
            'example' => 760,
        ],
        'duplicate_vacancy_count' => [
            'label' => 'Lowongan Duplikat',
            'description' => 'Jumlah data lowongan yang teridentifikasi sebagai duplikat dari seluruh data unik yang dikirim.',
            'example' => 125,
        ],
        'valid_complaint_count' => [
            'label' => 'Aduan Valid',
            'description' => 'Jumlah aduan terkait lowongan mitra yang telah diverifikasi sebagai aduan valid.',
            'example' => 2,
        ],
        'severe_complaint_count' => [
            'label' => 'Aduan Berat Terverifikasi',
            'description' => 'Jumlah aduan valid berkategori berat. Nilai di atas nol akan membuat red flag otomatis.',
            'example' => 0,
        ],
        'applications_from_karirhub' => [
            'label' => 'Lamaran dari Karirhub',
            'description' => 'Jumlah lamaran ke lowongan mitra yang berasal dari Karirhub.',
            'example' => 2400,
        ],
        'progressed_candidate_count' => [
            'label' => 'Kandidat Lolos Tahap Berikutnya',
            'description' => 'Jumlah pelamar dari Karirhub yang lolos kurasi atau maju ke tahap rekrutmen berikutnya.',
            'example' => 480,
        ],
        'hired_candidate_count' => [
            'label' => 'Kandidat Diterima Bekerja',
            'description' => 'Jumlah pelamar dari Karirhub yang akhirnya diterima bekerja.',
            'example' => 120,
        ],
        'eligibility_status' => [
            'label' => 'Status Kelayakan',
            'description' => 'Hasil pemeriksaan syarat kelayakan peserta pada periode penilaian.',
            'example' => 'eligible',
        ],
        'final_score' => [
            'label' => 'Nilai Akhir',
            'description' => 'Nilai akhir hasil perhitungan seluruh indikator aktif dan bobot periode.',
            'example' => 90,
        ],
    ];
}

function jpa_participant_field_definition(string $field): array
{
    $definitions = jpa_participant_field_definitions();
    return $definitions[$field] ?? [
        'label' => ucwords(str_replace('_', ' ', $field)),
        'description' => 'Data peserta untuk kebutuhan penilaian.',
        'example' => '',
    ];
}

function jpa_field_help_html(string $field, bool $showVariable = false): string
{
    $definition = jpa_participant_field_definition($field);
    $tooltip = $definition['description'] . ' Variabel: ' . $field . '.';
    $html = '<span class="d-inline-flex align-items-center gap-1">';
    $html .= '<span>' . htmlspecialchars((string)$definition['label']) . '</span>';
    $html .= '<span role="button" tabindex="0" class="text-secondary lh-1"';
    $html .= ' data-bs-toggle="tooltip" data-bs-placement="top" title="' . htmlspecialchars($tooltip) . '"';
    $html .= ' aria-label="Penjelasan ' . htmlspecialchars((string)$definition['label']) . '">';
    $html .= '<i class="bi bi-info-circle" aria-hidden="true"></i></span></span>';
    if ($showVariable) {
        $html .= '<div><code class="small">' . htmlspecialchars($field) . '</code></div>';
    }
    return $html;
}

function jpa_example_participants(int $monthsInPeriod = 6): array
{
    $activeMonths = max(1, min(6, $monthsInPeriod));
    return [
        [
            'partner_id' => 'PORTAL-001',
            'partner_name' => 'Portal Karier Nusantara',
            'integration_type' => 'full',
            'partnership_active' => 1,
            'critical_violation_resolved' => 1,
            'data_traceable' => 1,
            'records_sent_unique' => 12500,
            'published_unique_count' => 10000,
            'active_months' => $activeMonths,
            'complete_vacancy_count' => 9200,
            'employer_unique_count' => 800,
            'employer_valid_legal_count' => 760,
            'duplicate_vacancy_count' => 125,
            'valid_complaint_count' => 2,
            'severe_complaint_count' => 0,
            'applications_from_karirhub' => 2400,
            'progressed_candidate_count' => 480,
            'hired_candidate_count' => 120,
        ],
        [
            'partner_id' => 'PORTAL-002',
            'partner_name' => 'Kerja Hebat Indonesia',
            'integration_type' => 'semi',
            'partnership_active' => 1,
            'critical_violation_resolved' => 1,
            'data_traceable' => 1,
            'records_sent_unique' => 7200,
            'published_unique_count' => 6000,
            'active_months' => max(1, $activeMonths - 1),
            'complete_vacancy_count' => 5100,
            'employer_unique_count' => 475,
            'employer_valid_legal_count' => 420,
            'duplicate_vacancy_count' => 180,
            'valid_complaint_count' => 4,
            'severe_complaint_count' => 0,
            'applications_from_karirhub' => 1300,
            'progressed_candidate_count' => 210,
            'hired_candidate_count' => 45,
        ],
        [
            'partner_id' => 'PORTAL-003',
            'partner_name' => 'Loker Contoh',
            'integration_type' => 'inactive',
            'partnership_active' => 0,
            'critical_violation_resolved' => 0,
            'data_traceable' => 0,
            'records_sent_unique' => 1000,
            'published_unique_count' => 700,
            'active_months' => 1,
            'complete_vacancy_count' => 350,
            'employer_unique_count' => 80,
            'employer_valid_legal_count' => 40,
            'duplicate_vacancy_count' => 150,
            'valid_complaint_count' => 8,
            'severe_complaint_count' => 2,
            'applications_from_karirhub' => 100,
            'progressed_candidate_count' => 10,
            'hired_candidate_count' => 1,
        ],
    ];
}

function jpa_audit(
    mysqli $conn,
    ?int $periodId,
    string $action,
    string $entityType,
    $entityId,
    $before = null,
    $after = null,
    ?string $reason = null
): void {
    $userId = intval($_SESSION['user_id'] ?? 0) ?: null;
    $entityIdString = $entityId === null ? null : (string)$entityId;
    $beforeJson = $before === null ? null : jpa_json($before);
    $afterJson = $after === null ? null : jpa_json($after);
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $stmt = $conn->prepare("INSERT INTO job_portal_award_audit
        (period_id, actor_user_id, action, entity_type, entity_id, before_json, after_json, reason, ip_address)
        VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param('iisssssss', $periodId, $userId, $action, $entityType, $entityIdString, $beforeJson, $afterJson, $reason, $ip);
    $stmt->execute();
    $stmt->close();
}

function jpa_period_config(array $period): array
{
    $weights = json_decode((string)($period['weights_json'] ?? ''), true);
    if (!is_array($weights)) {
        $weights = JPA_DEFAULT_WEIGHTS;
    }
    return [
        'target_volume' => intval($period['target_volume'] ?? 1),
        'complaint_penalty_factor' => floatval($period['complaint_penalty_factor'] ?? 25),
        'target_progression_rate' => floatval($period['target_progression_rate'] ?? 1),
        'target_placement_rate' => floatval($period['target_placement_rate'] ?? 1),
        'impact_module_enabled' => !empty($period['impact_module_enabled']),
        'months_in_period' => jpa_months_in_period((string)$period['period_start'], (string)$period['period_end']),
        'weights' => $weights,
    ];
}

function jpa_get_period(mysqli $conn, int $periodId): ?array
{
    if ($periodId <= 0) {
        return null;
    }
    $stmt = $conn->prepare('SELECT * FROM job_portal_award_periods WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $periodId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function jpa_lock_period(mysqli $conn, int $periodId): ?array
{
    $stmt = $conn->prepare('SELECT * FROM job_portal_award_periods WHERE id=? FOR UPDATE');
    $stmt->bind_param('i', $periodId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function jpa_selected_period(mysqli $conn): ?array
{
    $periodId = intval($_GET['period_id'] ?? $_POST['period_id'] ?? 0);
    if ($periodId > 0) {
        return jpa_get_period($conn, $periodId);
    }
    $result = $conn->query("SELECT * FROM job_portal_award_periods ORDER BY
        CASE status WHEN 'locked' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END, period_end DESC, id DESC LIMIT 1");
    return $result ? ($result->fetch_assoc() ?: null) : null;
}

function jpa_get_participant(mysqli $conn, int $participantId): ?array
{
    $stmt = $conn->prepare('SELECT * FROM job_portal_award_participants WHERE id=? LIMIT 1');
    $stmt->bind_param('i', $participantId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function jpa_recalculate_participant(mysqli $conn, int $participantId): ?array
{
    $participant = jpa_get_participant($conn, $participantId);
    if (!$participant) {
        return null;
    }
    $period = jpa_get_period($conn, intval($participant['period_id']));
    if (!$period) {
        return null;
    }
    $eligibility = jpa_evaluate_eligibility($participant, $period);
    $calculation = jpa_compute_scores($participant, jpa_period_config($period));
    $scores = $calculation['scores'];
    $reasonText = implode("\n", $eligibility['reasons']);
    $stmt = $conn->prepare("UPDATE job_portal_award_participants SET
        eligibility_status=?, eligibility_reasons=?,
        score_integration=?, score_volume=?, score_consistency=?, score_completeness=?,
        score_kyb=?, score_duplicate=?, score_complaint=?, score_progression=?,
        score_placement=?, final_score=? WHERE id=?");
    $stmt->bind_param(
        'ssddddddddddi',
        $eligibility['status'],
        $reasonText,
        $scores['integration'],
        $scores['volume'],
        $scores['consistency'],
        $scores['completeness'],
        $scores['kyb'],
        $scores['duplicate'],
        $scores['complaint'],
        $scores['progression'],
        $scores['placement'],
        $calculation['final_score'],
        $participantId
    );
    $stmt->execute();
    $stmt->close();
    return jpa_get_participant($conn, $participantId);
}

function jpa_recalculate_period(mysqli $conn, int $periodId, bool $manageTransaction = true): array
{
    $ids = [];
    $stmt = $conn->prepare('SELECT id FROM job_portal_award_participants WHERE period_id=?');
    $stmt->bind_param('i', $periodId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $ids[] = intval($row['id']);
    }
    $stmt->close();
    foreach ($ids as $id) {
        jpa_recalculate_participant($conn, $id);
    }

    $query = $conn->prepare("SELECT p.*,
        EXISTS(
            SELECT 1 FROM job_portal_award_red_flags rf
            WHERE rf.participant_id=p.id
              AND (rf.status='pending' OR (rf.status='confirmed' AND rf.consequence IN ('disqualified','score_held')))
        ) AS ranking_blocked
        FROM job_portal_award_participants p WHERE p.period_id=?");
    $query->bind_param('i', $periodId);
    $query->execute();
    $rows = [];
    $result = $query->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $query->close();
    $ranked = jpa_rank_rows($rows);

    if ($manageTransaction) {
        $conn->begin_transaction();
    }
    try {
        $clear = $conn->prepare('UPDATE job_portal_award_participants SET award_rank=NULL WHERE period_id=?');
        $clear->bind_param('i', $periodId);
        $clear->execute();
        $clear->close();
        $rankUpdate = $conn->prepare('UPDATE job_portal_award_participants SET award_rank=? WHERE id=?');
        foreach ($ranked as $row) {
            $rank = intval($row['award_rank']);
            $id = intval($row['id']);
            $rankUpdate->bind_param('ii', $rank, $id);
            $rankUpdate->execute();
        }
        $rankUpdate->close();
        if ($manageTransaction) {
            $conn->commit();
        }
    } catch (Throwable $e) {
        if ($manageTransaction) {
            $conn->rollback();
        }
        throw $e;
    }
    return $ranked;
}

function jpa_csrf_token(): string
{
    if (empty($_SESSION['jpa_csrf'])) {
        $_SESSION['jpa_csrf'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['jpa_csrf'];
}

function jpa_verify_csrf(): void
{
    $provided = (string)($_POST['csrf_token'] ?? '');
    if ($provided === '' || !hash_equals(jpa_csrf_token(), $provided)) {
        http_response_code(419);
        exit('Invalid or expired request token.');
    }
}

function jpa_can_any(array $permissions): bool
{
    foreach ($permissions as $permission) {
        if (current_user_can($permission)) {
            return true;
        }
    }
    return false;
}

function jpa_require_any(array $permissions): void
{
    if (!jpa_can_any($permissions)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function jpa_assert_not_finalized(array $period): void
{
    if (($period['status'] ?? '') === 'finalized') {
        http_response_code(409);
        exit('Periode sudah difinalisasi dan tidak dapat diubah.');
    }
}

function jpa_set_flash(string $type, string $message): void
{
    $_SESSION['jpa_flash'] = ['type' => $type, 'message' => $message];
}

function jpa_redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function jpa_status_label(string $group, string $value): string
{
    $labels = [
        'period' => ['draft' => 'Draf', 'locked' => 'Terkunci', 'finalized' => 'Final'],
        'eligibility' => ['pending' => 'Menunggu', 'eligible' => 'Layak', 'ineligible' => 'Tidak layak'],
        'red_flag' => ['pending' => 'Menunggu', 'confirmed' => 'Dikonfirmasi', 'dismissed' => 'Ditutup'],
        'consequence' => ['review' => 'Tanpa konsekuensi', 'disqualified' => 'Diskualifikasi', 'score_held' => 'Nilai ditahan'],
        'integration' => ['full' => 'Penuh', 'semi' => 'Sebagian', 'inactive' => 'Tidak aktif'],
        'import' => ['processing' => 'Diproses', 'completed' => 'Selesai', 'failed' => 'Gagal'],
    ];
    return $labels[$group][$value] ?? ucwords(str_replace('_', ' ', $value));
}

function jpa_status_badge(string $group, string $value): string
{
    $colors = [
        'period' => ['draft' => 'secondary', 'locked' => 'warning', 'finalized' => 'success'],
        'eligibility' => ['pending' => 'warning', 'eligible' => 'success', 'ineligible' => 'secondary'],
        'red_flag' => ['pending' => 'warning', 'confirmed' => 'danger', 'dismissed' => 'secondary'],
        'consequence' => ['review' => 'secondary', 'disqualified' => 'danger', 'score_held' => 'warning'],
        'import' => ['processing' => 'info', 'completed' => 'success', 'failed' => 'danger'],
    ];
    $color = $colors[$group][$value] ?? 'secondary';
    return '<span class="badge text-bg-' . $color . '">' . htmlspecialchars(jpa_status_label($group, $value)) . '</span>';
}

function jpa_score_fields(): array
{
    return [
        'score_integration' => 'Integrasi',
        'score_volume' => 'Volume',
        'score_consistency' => 'Konsistensi',
        'score_completeness' => 'Kelengkapan',
        'score_kyb' => 'KYB',
        'score_duplicate' => 'Duplikasi',
        'score_complaint' => 'Aduan',
        'score_progression' => 'Progres Kandidat',
        'score_placement' => 'Penempatan',
    ];
}

function jpa_render_page_header(
    string $title,
    string $subtitle,
    mysqli $conn,
    ?array $period,
    string $action
): void {
    ?>
    <header class="jpa-page-header">
        <div>
            <h1 class="h3 mb-1"><?php echo htmlspecialchars($title); ?></h1>
            <p class="text-muted mb-0"><?php echo htmlspecialchars($subtitle); ?></p>
        </div>
        <?php jpa_render_period_selector($conn, $period, $action); ?>
    </header>
    <?php
}

function jpa_render_no_period(string $message = 'Pilih periode penilaian untuk menampilkan data.'): void
{
    ?>
    <div class="card"><div class="jpa-empty">
        <i class="bi bi-calendar2-week" aria-hidden="true"></i>
        <div><?php echo htmlspecialchars($message); ?></div>
    </div></div>
    <?php
}

function jpa_render_period_banner(array $period): void
{
    ?>
    <div class="card jpa-period-banner mb-4">
        <div class="card-body d-flex flex-wrap gap-3 justify-content-between align-items-center">
            <div>
                <h2 class="h5 mb-1"><?php echo htmlspecialchars($period['name']); ?></h2>
                <span class="text-muted"><i class="bi bi-calendar3 me-1" aria-hidden="true"></i><?php echo htmlspecialchars($period['period_start'] . ' – ' . $period['period_end']); ?></span>
            </div>
            <?php echo jpa_status_badge('period', (string)$period['status']); ?>
        </div>
    </div>
    <?php
}

function jpa_render_partner_cell(array $row, bool $linked = true): void
{
    $name = htmlspecialchars((string)($row['partner_name'] ?? ''));
    $participantId = intval($row['participant_id'] ?? $row['id'] ?? 0);
    if ($linked && $participantId > 0) {
        echo '<a class="fw-semibold text-decoration-none" href="participant?participant_id=' . $participantId . '">' . $name . '</a>';
    } else {
        echo '<span class="fw-semibold">' . $name . '</span>';
    }
    if (!empty($row['partner_id'])) {
        echo '<br><small class="text-muted">' . htmlspecialchars((string)$row['partner_id']) . '</small>';
    }
}

function jpa_render_empty_row(int $colspan, string $message): void
{
    echo '<tr><td colspan="' . $colspan . '"><div class="jpa-empty"><i class="bi bi-inbox" aria-hidden="true"></i>'
        . htmlspecialchars($message) . '</div></td></tr>';
}

function jpa_render_header(string $title, ?array $period = null): void
{
    $periodQuery = $period ? '?period_id=' . intval($period['id']) : '';
    $items = [
        ['Dashboard', 'dashboard', 'dashboard' . $periodQuery, ['job_portal_award_view']],
        ['Periode & Parameter', 'periods', 'periods' . $periodQuery, ['job_portal_award_manage_config']],
        ['Peserta & Import', 'participants', 'participants' . $periodQuery, ['job_portal_award_manage_data']],
        ['Kelayakan', 'eligibility', 'eligibility' . $periodQuery, ['job_portal_award_review_eligibility']],
        ['Penilaian', 'scoring', 'scoring' . $periodQuery, ['job_portal_award_view_scores', 'job_portal_award_recalculate']],
        ['Red Flags', 'red_flags', 'red_flags' . $periodQuery, ['job_portal_award_committee']],
        ['Peringkat & Pemenang', 'ranking', 'ranking' . $periodQuery, ['job_portal_award_approve_winners', 'job_portal_award_view_scores']],
        ['Audit & Ekspor', 'audit', 'audit' . $periodQuery, ['job_portal_award_view_audit', 'job_portal_award_export']],
    ];
    $currentPage = pathinfo((string)($_SERVER['SCRIPT_NAME'] ?? ''), PATHINFO_FILENAME);
    ?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - Job Portal Awards</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/jpa.css">
</head>
<body>
<?php include __DIR__ . '/../navbar.php'; ?>
<nav class="bg-white border-bottom jpa-subnav" aria-label="Navigasi Job Portal Awards">
    <div class="container py-2 d-flex gap-1">
        <?php foreach ($items as [$label, $page, $href, $permissions]): ?>
            <?php if (jpa_can_any($permissions)): ?>
                <a class="nav-link <?php echo $currentPage === $page ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($href); ?>" <?php echo $currentPage === $page ? 'aria-current="page"' : ''; ?>><?php echo htmlspecialchars($label); ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</nav>
<main class="container py-4">
<?php
    if (!empty($_SESSION['jpa_flash'])) {
        $flash = $_SESSION['jpa_flash'];
        unset($_SESSION['jpa_flash']);
        echo '<div class="alert alert-' . htmlspecialchars($flash['type']) . ' alert-dismissible fade show" role="alert">'
            . htmlspecialchars($flash['message'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>';
    }
}

function jpa_render_period_selector(mysqli $conn, ?array $period, string $action): void
{
    $periods = [];
    $result = $conn->query('SELECT id,name,status,period_start,period_end FROM job_portal_award_periods ORDER BY period_end DESC,id DESC');
    while ($result && ($row = $result->fetch_assoc())) {
        $periods[] = $row;
    }
    ?>
    <form method="get" action="<?php echo htmlspecialchars($action); ?>" class="no-print d-flex gap-2 align-items-center">
        <label class="form-label mb-0 text-nowrap" for="period_id">Periode</label>
        <select class="form-select form-select-sm" id="period_id" name="period_id" onchange="this.form.submit()" aria-label="Pilih periode penilaian">
            <option value="">Pilih periode</option>
            <?php foreach ($periods as $item): ?>
                <option value="<?php echo intval($item['id']); ?>" <?php echo $period && intval($period['id']) === intval($item['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($item['name'] . ' [' . jpa_status_label('period', $item['status']) . ']'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php
}

function jpa_render_footer(): void
{
    ?></main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
    new bootstrap.Tooltip(element);
});
</script>
</body>
</html><?php
}

