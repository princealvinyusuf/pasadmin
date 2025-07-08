<?php
header("Content-Type: application/json");
require_once("auth.php");
require_once("connection.php");

// Daftar kolom yang boleh difilter
$allowed = [
    "id", "uid", "title", "description", "location", "salary", "company_name",
    "employment_type", "experience_level", "industry", "remote_option",
    "job_function", "education_level", "province", "city", "gender",
    "platform", "active_jobs", "inactive_jobs", "method_info",
    "experience_required", "company_website", "language_required"
];

$conditions = [];
$params = [];
$types = "";

// Proses filter (dengan LIKE jika pakai wildcard)
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

// Sorting
$sort_by = isset($_GET['sort_by']) && in_array($_GET['sort_by'], $allowed) ? $_GET['sort_by'] : "created_at";
$order = isset($_GET['order']) && strtolower($_GET['order']) === 'asc' ? 'ASC' : 'DESC';

// Pagination
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

$sql = "SELECT * FROM jobs";
if (count($conditions) > 0) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}
$sql .= " ORDER BY $sort_by $order LIMIT ? OFFSET ?";

// Tambahkan limit dan offset ke bind param
$params[] = $limit;
$params[] = $offset;
$types .= "ii"; // dua integer

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
?>