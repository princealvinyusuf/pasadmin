<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
require 'db.php';
require 'auth.php';

$method = $_SERVER['REQUEST_METHOD'];

$fields = [
    'province', 'city', 'subdistrict', 'ward', 'age', 'age_group', 'gender',
    'physical_condition', 'marriage', 'working_status', 'draft_date', 'expired_date',
    'profile_status', 'seeker_status', 'education', 'experience', 'experience_year', 'certification',
    'institution', 'progpel', 'skill', 'plan_abroad', 'country_wish', 'submitted_application', 'major', 'school_name', 'month_regis', 'created_date'
];

$bulk = isset($_GET['bulk']) && $_GET['bulk'] == '1';

switch ($method) {
    case 'GET':
        // If export=1, return all job seekers as a plain array (no pagination, no search)
        if (isset($_GET['export']) && $_GET['export'] == '1') {
            $result = $conn->query('SELECT * FROM job_seekers ORDER BY id DESC');
            $seekers = [];
            while ($row = $result->fetch_assoc()) {
                $seekers[] = $row;
            }
            echo json_encode($seekers);
            break;
        }
        // If id is set, return only that job seeker
        if (isset($_GET['id']) && intval($_GET['id']) > 0) {
            $id = intval($_GET['id']);
            $stmt = $conn->prepare('SELECT * FROM job_seekers WHERE id=?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $seeker = $result->fetch_assoc();
            $stmt->close();
            echo json_encode(['seekers' => $seeker ? [$seeker] : []]);
            break;
        }
        // Pagination and search
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $per_page = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 50;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $where = '';
        $params = [];
        $types = '';
        if ($search !== '') {
            $search_like = '%' . $search . '%';
            $where_clauses = [];
            foreach ($fields as $f) {
                $where_clauses[] = "$f LIKE ?";
                $params[] = $search_like;
                $types .= 's';
            }
            $where = 'WHERE ' . implode(' OR ', $where_clauses);
        }
        // Count total
        $count_sql = "SELECT COUNT(*) as total FROM job_seekers $where";
        $count_stmt = $conn->prepare($count_sql);
        if ($where !== '') {
            $count_stmt->bind_param($types, ...$params);
        }
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total = $count_result->fetch_assoc()['total'] ?? 0;
        $count_stmt->close();
        // Fetch seekers for page
        $offset = ($page - 1) * $per_page;
        $sql = "SELECT * FROM job_seekers $where ORDER BY id DESC LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($sql);
        if ($where !== '') {
            $bind_types = $types . 'ii';
            $bind_params = array_merge($params, [$per_page, $offset]);
            $stmt->bind_param($bind_types, ...$bind_params);
        } else {
            $stmt->bind_param('ii', $per_page, $offset);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $seekers = [];
        while ($row = $result->fetch_assoc()) {
            $seekers[] = $row;
        }
        $stmt->close();
        echo json_encode([
            'seekers' => $seekers,
            'total' => intval($total),
            'page' => $page,
            'per_page' => $per_page
        ]);
        break;
    case 'POST':
        if ($bulk) {
            $data = json_decode(file_get_contents('php://input'), true);
            $seekers = $data['seekers'] ?? [];
            $inserted = 0;
            foreach ($seekers as $seeker) {
                $placeholders = implode(',', array_fill(0, count($fields), '?'));
                $columns = implode(',', $fields);
                $types = str_repeat('s', count($fields));
                $stmt = $conn->prepare("INSERT INTO job_seekers ($columns) VALUES ($placeholders)");
                $values = [];
                foreach ($fields as $f) {
                    $values[] = $seeker[$f] ?? '';
                }
                $stmt->bind_param($types, ...$values);
                $stmt->execute();
                if ($stmt->affected_rows > 0) $inserted++;
            }
            echo json_encode(['success' => true, 'count' => $inserted]);
            break;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $columns = implode(',', $fields);
        $types = str_repeat('s', count($fields));
        $stmt = $conn->prepare("INSERT INTO job_seekers ($columns) VALUES ($placeholders)");
        $values = [];
        foreach ($fields as $f) {
            $values[] = $data[$f] ?? '';
        }
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        echo json_encode(['success' => $stmt->affected_rows > 0]);
        break;
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $set = implode(',', array_map(fn($f) => "$f=?", $fields));
        $types = str_repeat('s', count($fields)) . 'i';
        $stmt = $conn->prepare("UPDATE job_seekers SET $set WHERE id=?");
        $values = [];
        foreach ($fields as $f) {
            $values[] = $data[$f] ?? '';
        }
        $values[] = $data['id'] ?? $data['seeker-id'];
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        echo json_encode(['success' => $stmt->affected_rows > 0]);
        break;
    case 'DELETE':
        $id = intval($_GET['id'] ?? 0);
        $stmt = $conn->prepare('DELETE FROM job_seekers WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        echo json_encode(['success' => $stmt->affected_rows > 0]);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
}
$conn->close();
?> 