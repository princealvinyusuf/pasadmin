<?php
// Database configuration
$db_host = "localhost";
$db_username = "root";
$db_password = "";
$db_name = "job_admin_prod";

// Create connection with error handling
try {
    $conn = new mysqli($db_host, $db_username, $db_password, $db_name);
    
    // Set charset to handle Indonesian characters properly
    $conn->set_charset("utf8mb4");
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Koneksi database gagal: " . $conn->connect_error);
    }
    
    // Set timezone
    $conn->query("SET time_zone = '+07:00'");
    
} catch (Exception $e) {
    // Log error
    error_log("Database connection failed: " . $e->getMessage());
    
    // Show user-friendly error message
    die("
    <div style='font-family: Arial, sans-serif; padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px; text-align: center;'>
        <h3>🚫 Koneksi Database Bermasalah</h3>
        <p>Sistem tidak dapat terhubung ke database. Silakan periksa:</p>
        <ul style='text-align: left; display: inline-block;'>
            <li>Pastikan MySQL/MariaDB server berjalan</li>
            <li>Periksa konfigurasi database (host, username, password)</li>
            <li>Pastikan database 'job_admin_prod' sudah dibuat</li>
            <li>Periksa hak akses user database</li>
        </ul>
        <hr>
        <small><strong>Error Detail:</strong> " . htmlspecialchars($e->getMessage()) . "</small>
    </div>
    ");
}

// Function to execute safe queries with error handling
function executeQuery($conn, $sql, $params = [], $types = "") {
    try {
        if (empty($params)) {
            $result = $conn->query($sql);
            if ($result === false) {
                throw new Exception("Query failed: " . $conn->error);
            }
            return $result;
        } else {
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            if (!empty($types) && !empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            
            if ($stmt->execute() === false) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            return $stmt;
        }
    } catch (Exception $e) {
        error_log("Database query error: " . $e->getMessage());
        throw $e;
    }
}

// Function to check if table exists and create if not
function ensureTableExists($conn) {
    $checkTable = "SHOW TABLES LIKE 'tahapan_kerjasama'";
    $result = $conn->query($checkTable);
    
    if ($result->num_rows == 0) {
        $createTable = "
        CREATE TABLE `tahapan_kerjasama` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nama_mitra` varchar(255) NOT NULL,
            `jenis_mitra` varchar(100) NOT NULL,
            `sumber_usulan` varchar(255) DEFAULT NULL,
            `tandai` tinyint(1) DEFAULT 0,
            
            -- Kesepahaman fields
            `status_kesepahaman` varchar(50) DEFAULT NULL,
            `nomor_kesepahaman` varchar(255) DEFAULT NULL,
            `tanggal_kesepahaman` date DEFAULT NULL,
            `ruanglingkup_kesepahaman` text DEFAULT NULL,
            `status_pelaksanaan_kesepahaman` varchar(50) DEFAULT NULL,
            `rencana_pertemuan_kesepahaman` date DEFAULT NULL,
            `rencana_kolaborasi_kesepahaman` text DEFAULT NULL,
            `status_progres_kesepahaman` text DEFAULT NULL,
            `tindaklanjut_kesepahaman` text DEFAULT NULL,
            `keterangan_kesepahaman` varchar(500) DEFAULT NULL,
            
            -- PKS fields
            `status_pks` varchar(50) DEFAULT NULL,
            `nomor_pks` varchar(255) DEFAULT NULL,
            `tanggal_pks` date DEFAULT NULL,
            `ruanglingkup_pks` text DEFAULT NULL,
            `status_pelaksanaan_pks` varchar(50) DEFAULT NULL,
            `rencana_pertemuan_pks` date DEFAULT NULL,
            `status_progres_pks` text DEFAULT NULL,
            `tindaklanjut_pks` text DEFAULT NULL,
            `keterangan_pks` varchar(500) DEFAULT NULL,
            
            -- File fields
            `file1` varchar(255) DEFAULT NULL,
            `file2` varchar(255) DEFAULT NULL,
            `file3` varchar(255) DEFAULT NULL,
            
            -- Timestamps
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (`id`),
            INDEX `idx_nama_mitra` (`nama_mitra`),
            INDEX `idx_jenis_mitra` (`jenis_mitra`),
            INDEX `idx_tandai` (`tandai`),
            INDEX `idx_status_kesepahaman` (`status_kesepahaman`),
            INDEX `idx_status_pks` (`status_pks`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        if ($conn->query($createTable) === false) {
            throw new Exception("Failed to create table: " . $conn->error);
        }
    }
}

// Ensure table exists
try {
    ensureTableExists($conn);
} catch (Exception $e) {
    error_log("Table creation error: " . $e->getMessage());
    die("
    <div style='font-family: Arial, sans-serif; padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px;'>
        <h3>Database Table Error</h3>
        <p>Gagal membuat atau mengakses tabel database.</p>
        <small><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</small>
    </div>
    ");
}

// Create upload directory if it doesn't exist (align with create/edit using 'uploads')
$uploadDir = __DIR__ . "/uploads/";
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        error_log("Failed to create upload directory: " . $uploadDir);
    }
}

// Function to clean up old temporary files (optional maintenance)
function cleanupOldFiles($conn, $uploadDir, $daysOld = 30) {
    try {
        // Get all filenames from database
        $result = $conn->query("SELECT file1, file2, file3 FROM tahapan_kerjasama WHERE file1 IS NOT NULL OR file2 IS NOT NULL OR file3 IS NOT NULL");
        $dbFiles = [];
        
        while ($row = $result->fetch_assoc()) {
            for ($i = 1; $i <= 3; $i++) {
                if (!empty($row["file$i"])) {
                    $dbFiles[] = $row["file$i"];
                }
            }
        }
        
        // Scan upload directory
        if (is_dir($uploadDir)) {
            $files = scandir($uploadDir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    $filePath = $uploadDir . $file;
                    
                    // Delete if file is old and not in database
                    if (is_file($filePath) && 
                        !in_array($file, $dbFiles) && 
                        time() - filemtime($filePath) > ($daysOld * 24 * 60 * 60)) {
                        unlink($filePath);
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Cleanup error: " . $e->getMessage());
    }
}

// Optional: Run cleanup occasionally (uncomment if needed)
// if (rand(1, 100) == 1) { // 1% chance on each page load
//     cleanupOldFiles($conn, $uploadDir);
// }
?>


