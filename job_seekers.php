<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once 'db.php';

$table = 'job_seekers';

function getSeekers($conn) {
    $perPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : 50;
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $year = isset($_GET['year']) ? $_GET['year'] : '';
    $month = isset($_GET['month']) ? $_GET['month'] : '';
    $export = isset($_GET['export']) ? true : false;

    $where = [];
    $params = [];
    if ($search) {
        $where[] = "(province LIKE ? OR city LIKE ? OR subdistrict LIKE ? OR ward LIKE ? OR age LIKE ? OR age_group LIKE ? OR gender LIKE ? OR physical_condition LIKE ? OR marriage LIKE ? OR working_status LIKE ? OR education LIKE ? OR experience LIKE ? OR skill LIKE ? OR institution LIKE ? OR major LIKE ? OR school_name LIKE ? OR country_wish LIKE ? OR plan_abroad LIKE ? OR certification LIKE ? OR progpel LIKE ? OR submitted_application LIKE ? OR profile_status LIKE ? OR seeker_status LIKE ? OR experience_year LIKE ? OR month_regis LIKE ? OR created_date LIKE ? OR draft_date LIKE ? OR expired_date LIKE ? OR id LIKE ?)";
        for ($i = 0; $i < 29; $i++) $params[] = "%$search%";
    }
    if ($year) {
        $where[] = "YEAR(created_date) = ?";
        $params[] = $year;
    }
    if ($month) {
        $where[] = "MONTH(created_date) = ?";
        $params[] = $month;
    }
    $whereSql = $where ? ("WHERE " . implode(' AND ', $where)) : '';

    $countSql = "SELECT COUNT(*) FROM $table $whereSql";
    $stmt = $conn->prepare($countSql);
    if ($params) $stmt->execute($params); else $stmt->execute();
    $total = $stmt->fetchColumn();

    $limitSql = $export ? '' : "LIMIT $perPage OFFSET " . (($page-1)*$perPage);
    $sql = "SELECT * FROM $table $whereSql ORDER BY id DESC $limitSql";
    $stmt = $conn->prepare($sql);
    if ($params) $stmt->execute($params); else $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($export) {
        echo json_encode($rows);
        exit;
    }
    echo json_encode([
        'seekers' => $rows,
        'total' => intval($total),
        'page' => $page
    ]);
    exit;
}

function getSeekerById($conn, $id) {
    global $table;
    $stmt = $conn->prepare("SELECT * FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['seekers' => $row]);
    exit;
}

function updateSeeker($conn) {
    global $table;
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['id'])) {
        echo json_encode(['error' => 'Invalid data']);
        exit;
    }
    $id = $data['id'];
    unset($data['id']);
    $fields = array_keys($data);
    $set = implode(', ', array_map(function($f) { return "$f = ?"; }, $fields));
    $sql = "UPDATE $table SET $set WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $params = array_values($data);
    $params[] = $id;
    $stmt->execute($params);
    echo json_encode(['success' => true]);
    exit;
}

function deleteSeeker($conn, $id) {
    global $table;
    $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
    exit;
}

// Routing
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    if (isset($_GET['id'])) {
        getSeekerById($conn, $_GET['id']);
    } else {
        getSeekers($conn);
    }
} elseif ($method === 'PUT') {
    updateSeeker($conn);
} elseif ($method === 'DELETE') {
    if (isset($_GET['id'])) {
        deleteSeeker($conn, $_GET['id']);
    } else {
        echo json_encode(['error' => 'Missing id']);
        exit;
    }
} else {
    echo json_encode(['error' => 'Invalid method']);
    exit;
} 