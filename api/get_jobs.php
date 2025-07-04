<?php
header("Content-Type: application/json");
require_once("auth.php");
require_once("connection.php");

$sql = "SELECT * FROM jobs";
$result = $conn->query($sql);

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
echo json_encode($data);