<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Check if username already exists
    $check = $conn->query("SELECT id FROM users WHERE username='$username'");
    if ($check && $check->num_rows > 0) {
        echo "Username already exists.";
        exit();
    }

    $sql = "INSERT INTO users (username, password, email, created_at) VALUES ('$username', '$hashed_password', '$email', NOW())";
    if ($conn->query($sql) === TRUE) {
        echo "User registered successfully. <a href='login.html'>Login here</a>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?> 