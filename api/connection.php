<?php
$host = '35.188.122.3';
$port = 3306;
$user = 'pasker';
$pass = 'Getjoblivebetter!';
$dbname = 'job_admin';

$conn = new mysqli($host, $user, $pass, $dbname, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>