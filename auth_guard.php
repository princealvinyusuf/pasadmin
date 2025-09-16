<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
} 
// Basic audit trail: log each authenticated page hit into job_admin_prod.audits
try {
    $auditConn = new mysqli('localhost','root','', 'job_admin_prod');
    // Create table if missing
    $auditConn->query("CREATE TABLE IF NOT EXISTS audits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        username VARCHAR(150) DEFAULT NULL,
        ip_address VARCHAR(64) DEFAULT NULL,
        user_agent VARCHAR(255) DEFAULT NULL,
        method VARCHAR(10) DEFAULT NULL,
        path VARCHAR(255) NOT NULL,
        query_string TEXT DEFAULT NULL,
        post_data TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_created (user_id, created_at),
        INDEX idx_path_created (path(100), created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $userId = intval($_SESSION['user_id']);
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $method = $_SERVER['REQUEST_METHOD'] ?? null;
    $path = $_SERVER['SCRIPT_NAME'] ?? '';
    $query = $_SERVER['QUERY_STRING'] ?? '';
    // Only capture small POST bodies to avoid bloat; redact passwords
    $post = '';
    if (!empty($_POST)) {
        $san = $_POST;
        if (isset($san['password'])) { $san['password'] = '***'; }
        if (isset($san['pass'])) { $san['pass'] = '***'; }
        $json = json_encode($san, JSON_UNESCAPED_UNICODE);
        $post = substr($json, 0, 4000);
    }
    $stmt = $auditConn->prepare('INSERT INTO audits (user_id, username, ip_address, user_agent, method, path, query_string, post_data) VALUES (?,?,?,?,?,?,?,?)');
    if ($stmt) {
        $stmt->bind_param('isssssss', $userId, $username, $ip, $ua, $method, $path, $query, $post);
        $stmt->execute();
        $stmt->close();
    }
    $auditConn->close();
} catch (Throwable $e) {
    // Do not block requests if audit logging fails
}