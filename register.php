<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require 'db.php';

header('X-Content-Type-Options: nosniff');

// Render registration form on GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Register User</title>
        <style>
            body { font-family: Arial, sans-serif; background: #f4f4f4; }
            .register-container { max-width: 400px; margin: 60px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
            h2 { text-align: center; }
            label { display: block; margin-top: 15px; }
            input[type="text"], input[type="email"], input[type="password"] { width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ccc; border-radius: 4px; }
            button { width: 100%; padding: 10px; margin-top: 20px; background: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
            button:hover { background: #0056b3; }
        </style>
    </head>
    <body>
        <div class="register-container">
            <h2>Register User</h2>
            <form action="register.php" method="post">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
                <button type="submit">Register</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$usernameInput = isset($_POST['username']) ? trim($_POST['username']) : '';
$emailInput = isset($_POST['email']) ? trim($_POST['email']) : '';
$passwordInput = isset($_POST['password']) ? (string)$_POST['password'] : '';

if ($usernameInput === '' || $emailInput === '' || $passwordInput === '') {
    http_response_code(400);
    echo 'Missing required fields: username, email, password';
    exit;
}

if (!filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo 'Invalid email format';
    exit;
}

if (strlen($passwordInput) < 6) {
    http_response_code(400);
    echo 'Password must be at least 6 characters';
    exit;
}

try {
    $columns = [];
    $res = $conn->query('SHOW COLUMNS FROM users');
    while ($row = $res->fetch_assoc()) {
        $columns[] = $row['Field'];
    }

    $usernameField = in_array('username', $columns, true) ? 'username' : (in_array('name', $columns, true) ? 'name' : null);
    $emailField = in_array('email', $columns, true) ? 'email' : null;
    $passwordField = in_array('password', $columns, true) ? 'password' : null;
    $hasCreatedAt = in_array('created_at', $columns, true);
    $hasUpdatedAt = in_array('updated_at', $columns, true);

    if ($usernameField === null || $emailField === null || $passwordField === null) {
        http_response_code(500);
        echo 'users table does not have required columns. Needed one of [username|name], and email, password';
        exit;
    }

    $hashedPassword = password_hash($passwordInput, PASSWORD_DEFAULT);

    // Uniqueness checks
    $checkSql = "SELECT id FROM users WHERE $usernameField = ? OR $emailField = ? LIMIT 1";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('ss', $usernameInput, $emailInput);
    $checkStmt->execute();
    $checkStmt->store_result();
    if ($checkStmt->num_rows > 0) {
        http_response_code(409);
        echo 'Username or email already exists';
        $checkStmt->close();
        exit;
    }
    $checkStmt->close();

    // Build dynamic insert
    $fields = [$usernameField, $passwordField, $emailField];
    $placeholders = ['?', '?', '?'];
    $types = 'sss';
    $values = [$usernameInput, $hashedPassword, $emailInput];

    if ($hasCreatedAt) {
        $fields[] = 'created_at';
        $placeholders[] = 'NOW()';
    }
    if ($hasUpdatedAt) {
        $fields[] = 'updated_at';
        $placeholders[] = 'NOW()';
    }

    $sql = 'INSERT INTO users (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$values);
    $stmt->execute();
    $stmt->close();

    header('Content-Type: text/html; charset=utf-8');
    echo "User registered successfully. <a href='login.php'>Login here</a>";
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Registration error: ' . $e->getMessage();
}
?>