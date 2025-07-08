<?php
header("Content-Type: application/json");
require_once("auth.php");
require_once("connection.php");

$input = json_decode(file_get_contents("php://input"), true);

// Example: assuming these columns exist
$title = $conn->real_escape_string($input['title'] ?? '');
$company = $conn->real_escape_string($input['company'] ?? '');
$location = $conn->real_escape_string($input['location'] ?? '');

$sql = "INSERT INTO jobs (title, company, location) VALUES ('$title', '$company', '$location')";

if ($conn->query($sql)) {
    echo json_encode(["message" => "Job inserted successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => $conn->error]);
}