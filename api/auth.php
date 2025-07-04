<?php
function getBearerToken() {
    $headers = apache_request_headers();
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
            return $matches[1];
        }
    }
    return null;
}

$validToken = "Getjoblivebetter!"; // samain kyk paswot db ajh
$token = getBearerToken();

if ($token !== $validToken) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}
?>