<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
require 'db.php';
require 'auth.php';

$method = $_SERVER['REQUEST_METHOD'];

$fields = [
    'uid', 'title', 'description', 'location', 'salary', 'company_name', 'employment_type', 'experience_level', 'industry', 'remote_option', 'job_function', 'required_skills', 'education_level', 'application_deadline', 'benefits', 'company_website', 'how_to_apply', 'company_size', 'hiring_manager_contact', 'work_schedule', 'job_duration', 'languages_required', 'posted_by', 'province', 'city', 'amount_info', 'posting_date', 'scraping_date', 'job_source', 'source_type', 'platform', 'method_info', 'active_jobs', 'inactive_jobs'
];

$bulk = isset($_GET['bulk']) && $_GET['bulk'] == '1';

switch ($method) {
    case 'GET':
        // If export=1, return all jobs as a plain array (no pagination, no search)
        if (isset($_GET['export']) && $_GET['export'] == '1') {
            $result = $conn->query('SELECT * FROM jobs ORDER BY created_at DESC');
            $jobs = [];
            while ($row = $result->fetch_assoc()) {
                $jobs[] = $row;
            }
            echo json_encode($jobs);
            break;
        }
        // If counts=1, return only job counts (not paginated, not filtered)
        if (isset($_GET['counts']) && $_GET['counts'] == '1') {
            $total = 0;
            $open = 0;
            $closed = 0;
            $today = date('Y-m-d');
            $result = $conn->query('SELECT application_deadline FROM jobs');
            while ($row = $result->fetch_assoc()) {
                $total++;
                $deadline = $row['application_deadline'];
                if ($deadline && $deadline >= $today) {
                    $open++;
                } else {
                    $closed++;
                }
            }
            echo json_encode([
                'total' => $total,
                'open' => $open,
                'closed' => $closed
            ]);
            break;
        }
        // If top=5, return the latest 5 jobs as a plain array (no pagination, no search)
        if (isset($_GET['top']) && intval($_GET['top']) > 0) {
            $limit = intval($_GET['top']);
            $result = $conn->query("SELECT * FROM jobs ORDER BY created_at DESC LIMIT $limit");
            $jobs = [];
            while ($row = $result->fetch_assoc()) {
                $jobs[] = $row;
            }
            echo json_encode($jobs);
            break;
        }
        // If id is set, return only that job
        if (isset($_GET['id']) && intval($_GET['id']) > 0) {
            $id = intval($_GET['id']);
            $stmt = $conn->prepare('SELECT * FROM jobs WHERE id=?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $job = $result->fetch_assoc();
            $stmt->close();
            echo json_encode(['jobs' => $job ? [$job] : []]);
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
        // Filter by uid if provided
        if (isset($_GET['uid']) && $_GET['uid'] !== '') {
            $where = $where ? $where . ' AND uid=?' : 'WHERE uid=?';
            $params[] = $_GET['uid'];
            $types .= 's';
        }
        // Filter by year/month if provided (on application_deadline or created_at)
        $dateField = 'application_deadline';
        if (isset($_GET['year']) && $_GET['year'] !== '') {
            $where = $where ? $where . " AND YEAR($dateField)=?" : "WHERE YEAR($dateField)=?";
            $params[] = $_GET['year'];
            $types .= 'i';
        }
        if (isset($_GET['month']) && $_GET['month'] !== '') {
            $where = $where ? $where . " AND MONTH($dateField)=?" : "WHERE MONTH($dateField)=?";
            $params[] = $_GET['month'];
            $types .= 'i';
        }
        // Count total
        $count_sql = "SELECT COUNT(*) as total FROM jobs $where";
        $count_stmt = $conn->prepare($count_sql);
        if ($where !== '') {
            $count_stmt->bind_param($types, ...$params);
        }
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $total = $count_result->fetch_assoc()['total'] ?? 0;
        $count_stmt->close();
        // Fetch jobs for page
        $offset = ($page - 1) * $per_page;
        $sql = "SELECT * FROM jobs $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
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
        $jobs = [];
        while ($row = $result->fetch_assoc()) {
            $jobs[] = $row;
        }
        $stmt->close();
        echo json_encode([
            'jobs' => $jobs,
            'total' => intval($total),
            'page' => $page,
            'per_page' => $per_page
        ]);
        break;
    case 'POST':
        if ($bulk) {
            $data = json_decode(file_get_contents('php://input'), true);
            $jobs = $data['jobs'] ?? [];
            $inserted = 0;
            foreach ($jobs as $job) {
                // Set application_deadline to 1 month after today if empty or missing
                if (empty($job['application_deadline'])) {
                    $job['application_deadline'] = date('Y-m-d', strtotime('+1 month'));
                }
                // Generate a new UID if missing or empty
                if (empty($job['uid'])) {
                    $job['uid'] = uniqid('job_');
                }
                $placeholders = implode(',', array_fill(0, count($fields), '?'));
                $columns = implode(',', $fields);
                $types = str_repeat('s', count($fields));
                $stmt = $conn->prepare("INSERT INTO jobs ($columns) VALUES ($placeholders)");
                $values = [];
                foreach ($fields as $f) {
                    $values[] = $job[$f] ?? '';
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
        $stmt = $conn->prepare("INSERT INTO jobs ($columns) VALUES ($placeholders)");
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
        $stmt = $conn->prepare("UPDATE jobs SET $set WHERE id=?");
        $values = [];
        foreach ($fields as $f) {
            $values[] = $data[$f] ?? '';
        }
        $values[] = $data['job-id'] ?? $data['id'];
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        echo json_encode(['success' => $stmt->affected_rows > 0]);
        break;
    case 'DELETE':
        $id = intval($_GET['id'] ?? 0);
        $stmt = $conn->prepare('DELETE FROM jobs WHERE id=?');
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