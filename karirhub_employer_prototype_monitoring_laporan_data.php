<?php

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
require_once __DIR__ . '/karirhub_employer_prototype_ui.php';

header('Content-Type: application/json; charset=utf-8');

if (!kh_proto_can_access('karirhub_employer_prototype_monitoring_laporan_view')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/karirhub_employer_prototype_monitoring_storage.php';

try {
    kh_monitoring_ensure_tables($conn);
    $action = strtolower(trim((string)($_GET['action'] ?? 'summary')));
    $days = kh_monitoring_period_days((int)($_GET['days'] ?? 30));

    if ($action === 'summary') {
        echo json_encode([
            'ok' => true,
            'data' => kh_monitoring_dashboard_data($conn, $days),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'list') {
        $card = strtolower(trim((string)($_GET['card'] ?? '')));
        $allowedCards = [
            'reports', 'reporters', 'vacancies', 'pending',
            'reviewing', 'completed', 'blocked-vacancies', 'blocked-employers',
        ];
        if (!in_array($card, $allowedCards, true)) {
            throw new InvalidArgumentException('Jenis ringkasan tidak valid.');
        }
        echo json_encode([
            'ok' => true,
            'data' => kh_monitoring_list($conn, $card, $days),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'detail') {
        $type = strtolower(trim((string)($_GET['type'] ?? '')));
        $id = trim((string)($_GET['id'] ?? ''));
        if (!in_array($type, ['report', 'reporter', 'vacancy', 'employer'], true) || $id === '') {
            throw new InvalidArgumentException('Parameter detail tidak valid.');
        }
        $detail = kh_monitoring_detail($conn, $type, $id);
        if ($detail === null) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'message' => 'Data tidak ditemukan.']);
            exit;
        }
        echo json_encode([
            'ok' => true,
            'data' => $detail,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    throw new InvalidArgumentException('Aksi tidak valid.');
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Monitoring laporan data error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Gagal memuat data monitoring.']);
}

