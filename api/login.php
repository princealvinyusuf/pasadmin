<?php
header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

// Dummy login
if ($username === 'pasker' && $password === 'pasker123') {
    echo json_encode(["token" => "Getjoblivebetter!"]);
} else {
    http_response_code(403);
    echo json_encode(["error" => "Login failed"]);
}