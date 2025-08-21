<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$identifier = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? (string)$_POST['password'] : '';
if ($identifier === '' || $password === '') {
    http_response_code(400);
    echo 'Missing username and/or password';
    exit;
}

try {
    $columns = [];
    $res = $conn->query('SHOW COLUMNS FROM users');
    while ($row = $res->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    $usernameField = in_array('username', $columns, true) ? 'username' : (in_array('name', $columns, true) ? 'name' : null);
    if ($usernameField === null) {
        http_response_code(500);
        echo 'users table missing username or name column';
        exit;
    }

    $sql = "SELECT id, $usernameField AS username, password FROM users WHERE $usernameField = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $identifier);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (isset($user['password']) && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header('Location: job_dashboard.html');
            exit();
        }
    }
    echo 'Invalid username or password!';
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Login error: ' . $e->getMessage();
}
?>
