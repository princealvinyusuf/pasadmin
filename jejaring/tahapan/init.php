<?php
// Use the root DB connection to avoid duplicate db.php files
require_once __DIR__ . '/../../db.php';

// Optional: keep charset and timezone consistent with previous implementation
if (isset($conn) && $conn instanceof mysqli) {
    @$conn->set_charset('utf8mb4');
    @$conn->query("SET time_zone = '+07:00'");
}

// Ensure required table exists (idempotent)
function tahapanEnsureTableExists(mysqli $conn): void {
    $result = $conn->query("SHOW TABLES LIKE 'tahapan_kerjasama'");
    if ($result && $result->num_rows === 0) {
        $createTable = "
        CREATE TABLE `tahapan_kerjasama` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nama_mitra` varchar(255) NOT NULL,
            `jenis_mitra` varchar(100) NOT NULL,
            `sumber_usulan` varchar(255) DEFAULT NULL,
            `tandai` tinyint(1) DEFAULT 0,

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

            `status_pks` varchar(50) DEFAULT NULL,
            `nomor_pks` varchar(255) DEFAULT NULL,
            `tanggal_pks` date DEFAULT NULL,
            `ruanglingkup_pks` text DEFAULT NULL,
            `status_pelaksanaan_pks` varchar(50) DEFAULT NULL,
            `rencana_pertemuan_pks` date DEFAULT NULL,
            `status_progres_pks` text DEFAULT NULL,
            `tindaklanjut_pks` text DEFAULT NULL,
            `keterangan_pks` varchar(500) DEFAULT NULL,

            `file1` varchar(255) DEFAULT NULL,
            `file2` varchar(255) DEFAULT NULL,
            `file3` varchar(255) DEFAULT NULL,

            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (`id`),
            INDEX `idx_nama_mitra` (`nama_mitra`),
            INDEX `idx_jenis_mitra` (`jenis_mitra`),
            INDEX `idx_tandai` (`tandai`),
            INDEX `idx_status_kesepahaman` (`status_kesepahaman`),
            INDEX `idx_status_pks` (`status_pks`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $conn->query($createTable);
    }
}

if (isset($conn) && $conn instanceof mysqli) {
    tahapanEnsureTableExists($conn);
}

// Ensure uploads directory exists
$tahapanUploadDir = __DIR__ . '/uploads/';
if (!is_dir($tahapanUploadDir)) {
    @mkdir($tahapanUploadDir, 0755, true);
}


