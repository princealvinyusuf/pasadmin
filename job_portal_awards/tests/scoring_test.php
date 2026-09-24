<?php
require_once __DIR__ . '/../lib.php';

$failures = [];

function expect_near(string $label, float $actual, float $expected, float $epsilon = 0.001): void
{
    global $failures;
    if (abs($actual - $expected) > $epsilon) {
        $failures[] = "{$label}: expected {$expected}, got {$actual}";
    }
}

function expect_true(string $label, bool $condition): void
{
    global $failures;
    if (!$condition) {
        $failures[] = "{$label}: condition was false";
    }
}

$metrics = [
    'integration_type' => 'full',
    'records_sent_unique' => 100,
    'published_unique_count' => 2500,
    'active_months' => 3,
    'complete_vacancy_count' => 2375,
    'employer_unique_count' => 100,
    'employer_valid_legal_count' => 97,
    'duplicate_vacancy_count' => 2,
    'valid_complaint_count' => 1,
    'applications_from_karirhub' => 200,
    'progressed_candidate_count' => 16,
    'hired_candidate_count' => 7,
];
$config = [
    'weights' => JPA_DEFAULT_WEIGHTS,
    'months_in_period' => 3,
    'target_volume' => 10000 / 3,
    'complaint_penalty_factor' => 25,
    'target_progression_rate' => 10,
    'target_placement_rate' => 5,
    'impact_module_enabled' => true,
];
$result = jpa_compute_scores($metrics, $config);
expect_near('framework example total', $result['final_score'], 89.10, 0.01);
expect_near('complaint rate score', $result['scores']['complaint'], 90.0);
expect_near('progression score', $result['scores']['progression'], 80.0);
expect_near('placement score', $result['scores']['placement'], 70.0);

$config['impact_module_enabled'] = false;
$coreOnly = jpa_compute_scores($metrics, $config);
expect_near('core 85 percent normalization', $coreOnly['final_score'], (77.6 / 85) * 100, 0.01);
expect_near('disabled impact progression', $coreOnly['scores']['progression'], 0.0);

$zero = jpa_compute_scores([], [
    'weights' => JPA_DEFAULT_WEIGHTS,
    'months_in_period' => 1,
    'target_volume' => 100,
    'complaint_penalty_factor' => 25,
    'target_progression_rate' => 1,
    'target_placement_rate' => 1,
    'impact_module_enabled' => true,
]);
expect_near('zero completeness denominator', $zero['scores']['completeness'], 0.0);
expect_near('zero duplicate denominator', $zero['scores']['duplicate'], 0.0);
expect_near('zero complaint denominator', $zero['scores']['complaint'], 0.0);

$eligibility = jpa_evaluate_eligibility([
    'partnership_active' => 1,
    'active_months' => 3,
    'critical_violation_resolved' => 1,
    'data_traceable' => 1,
], ['min_active_months' => 3]);
expect_true('eligible participant', $eligibility['status'] === 'eligible');

$ineligible = jpa_evaluate_eligibility([
    'partnership_active' => 0,
    'active_months' => 1,
    'critical_violation_resolved' => 0,
    'data_traceable' => 0,
], ['min_active_months' => 3]);
expect_true('all eligibility failures recorded', $ineligible['status'] === 'ineligible' && count($ineligible['reasons']) === 4);
expect_true('eligibility gates use continuous E1-E4 sequence', array_values(JPA_ELIGIBILITY_GATES) === ['E1', 'E2', 'E3', 'E4']);
expect_true('traceability failure is E4', ($ineligible['reasons'][3] ?? '') === 'E4: Data tidak dapat ditelusuri secara memadai.');

$ranked = jpa_rank_rows([
    ['id' => 1, 'partner_name' => 'Portal A', 'eligibility_status' => 'eligible', 'ranking_blocked' => 0, 'final_score' => 90.0, 'score_complaint' => 80, 'score_completeness' => 90, 'published_unique_count' => 100],
    ['id' => 2, 'partner_name' => 'Portal B', 'eligibility_status' => 'eligible', 'ranking_blocked' => 0, 'final_score' => 89.7, 'score_complaint' => 95, 'score_completeness' => 80, 'published_unique_count' => 90],
    ['id' => 3, 'partner_name' => 'Portal C', 'eligibility_status' => 'eligible', 'ranking_blocked' => 1, 'final_score' => 99.0, 'score_complaint' => 100, 'score_completeness' => 100, 'published_unique_count' => 1000],
    ['id' => 4, 'partner_name' => 'Portal D', 'eligibility_status' => 'ineligible', 'ranking_blocked' => 0, 'final_score' => 98.0, 'score_complaint' => 100, 'score_completeness' => 100, 'published_unique_count' => 1000],
]);
expect_true('tie breaker complaint first', intval($ranked[0]['id']) === 2);
expect_true('blocked and ineligible excluded', count($ranked) === 2);
expect_true('ranks unique and sequential', intval($ranked[0]['award_rank']) === 1 && intval($ranked[1]['award_rank']) === 2);

$tieRows = [
    ['id' => 10, 'partner_name' => 'Ten', 'eligibility_status' => 'eligible', 'ranking_blocked' => 0, 'final_score' => 90.0, 'score_complaint' => 70, 'score_completeness' => 90, 'published_unique_count' => 100],
    ['id' => 11, 'partner_name' => 'Eleven', 'eligibility_status' => 'eligible', 'ranking_blocked' => 0, 'final_score' => 89.7, 'score_complaint' => 95, 'score_completeness' => 80, 'published_unique_count' => 90],
    ['id' => 12, 'partner_name' => 'Twelve', 'eligibility_status' => 'eligible', 'ranking_blocked' => 0, 'final_score' => 89.2, 'score_complaint' => 100, 'score_completeness' => 100, 'published_unique_count' => 1000],
];
$orders = [$tieRows, [$tieRows[2],$tieRows[0],$tieRows[1]], [$tieRows[1],$tieRows[2],$tieRows[0]]];
$rankSequences = [];
foreach ($orders as $order) {
    $rankSequences[] = array_map(static fn($row) => intval($row['id']), jpa_rank_rows($order));
}
expect_true('tie groups deterministic across input permutations', count(array_unique(array_map('json_encode', $rankSequences))) === 1);

if ($failures) {
    fwrite(STDERR, "FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "OK: Job Portal Awards scoring and ranking tests passed.\n";

