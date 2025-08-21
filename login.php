<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();
require 'db.php';

// Render login form on GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f4f4f4; }
            .login-container { max-width: 400px; margin: 60px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
            h2 { text-align: center; }
            label { display: block; margin-top: 15px; }
            input[type="text"], input[type="password"] { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; }
            button { width: 100%; padding: 10px; margin-top: 20px; background: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
            button:hover { background: #0056b3; }
            .register-link { display: block; text-align: center; margin-top: 15px; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h2>Login</h2>
            <form action="login.php" method="post">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
                <button type="submit">Login</button>
            </form>
            <a class="register-link" href="register.html">Don't have an account? Register</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Handle login on POST
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
            header('Location: index.php');
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
