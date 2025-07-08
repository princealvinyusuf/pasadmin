<?php
header("Content-Type: application/json");
require_once("auth.php");
require_once("connection.php");

$method = $_SERVER['REQUEST_METHOD'];

// List of allowed columns for filtering and updating
$allowed = [
    "id", "uid", "title", "description", "location", "salary", "company_name",
    "employment_type", "experience_level", "industry", "remote_option",
    "job_function", "education_level", "province", "city", "gender",
    "platform", "active_jobs", "inactive_jobs", "method_info",
    "experience_required", "company_website", "language_required"
];

switch ($method) {
    case 'GET':
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
        // Filtering, sorting, pagination
        $conditions = [];
        $params = [];
        $types = "";
        foreach ($_GET as $key => $value) {
            if (in_array($key, $allowed)) {
                if (strpos($value, '%') !== false) {
                    $conditions[] = "$key LIKE ?";
                } else {
                    $conditions[] = "$key = ?";
                }
                $params[] = $value;
                $types .= "s";
            }
        }
        $sort_by = isset($_GET['sort_by']) && in_array($_GET['sort_by'], $allowed) ? $_GET['sort_by'] : "created_at";
        $order = isset($_GET['order']) && strtolower($_GET['order']) === 'asc' ? 'ASC' : 'DESC';
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
        $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
        $sql = "SELECT * FROM jobs";
        if (count($conditions) > 0) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        $sql .= " ORDER BY $sort_by $order LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        echo json_encode($data);
        break;
    case 'POST':
        $input = json_decode(file_get_contents("php://input"), true);
        $columns = [];
        $placeholders = [];
        $values = [];
        $types = '';
        foreach ($allowed as $col) {
            if (isset($input[$col])) {
                $columns[] = $col;
                $placeholders[] = '?';
                $values[] = $input[$col];
                $types .= 's';
            }
        }
        if (count($columns) === 0) {
            http_response_code(400);
            echo json_encode(["error" => "No valid fields provided"]);
            break;
        }
        $sql = "INSERT INTO jobs (" . implode(",", $columns) . ") VALUES (" . implode(",", $placeholders) . ")";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $success = $stmt->execute();
        if ($success) {
            echo json_encode(["message" => "Job inserted successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => $conn->error]);
        }
        break;
    case 'PUT':
        $input = json_decode(file_get_contents("php://input"), true);
        if (!isset($input['id'])) {
            http_response_code(400);
            echo json_encode(["error" => "Missing job id"]);
            break;
        }
        $id = intval($input['id']);
        $set = [];
        $values = [];
        $types = '';
        foreach ($allowed as $col) {
            if (isset($input[$col])) {
                $set[] = "$col=?";
                $values[] = $input[$col];
                $types .= 's';
            }
        }
        if (count($set) === 0) {
            http_response_code(400);
            echo json_encode(["error" => "No valid fields to update"]);
            break;
        }
        $values[] = $id;
        $types .= 'i';
        $sql = "UPDATE jobs SET " . implode(",", $set) . " WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $stmt->execute();
        echo json_encode(["success" => $stmt->affected_rows > 0]);
        break;
    case 'DELETE':
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(["error" => "Missing or invalid job id"]);
            break;
        }
        $stmt = $conn->prepare('DELETE FROM jobs WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        echo json_encode(["success" => $stmt->affected_rows > 0]);
        break;
    default:
        http_response_code(405);
        echo json_encode(["error" => "Method Not Allowed"]);
}
$conn->close();
?> 