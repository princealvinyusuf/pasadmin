<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db.php';

$table = "tiket_aduan";

// RULE BASED CLASSIFICATION
$rules = [
    "Login Issue"     => ["login", "password", "otp", "akun", "email", "masuk"],
    "Registrasi"      => ["daftar", "registrasi", "signup", "buat akun"],
    "Error System"    => ["error", "server", "gagal", "500", "tidak bisa akses", "blank"],
    "Verifikasi"      => ["kode", "verifikasi", "otp", "token"],
    "Upload Berkas"   => ["upload", "unggah", "berkas", "file", "dokumen"],
    "Data Pribadi"    => ["nik", "kk", "nama", "tanggal lahir"],
    "Lainnya"         => [] // Default jika tidak cocok
];

// Ambil semua komentar
$sql = "SELECT id, comment FROM $table WHERE comment IS NOT NULL AND comment != ''";
$res = $conn->query($sql);

$output = [];

while ($row = $res->fetch_assoc()) {

    $comment = strtolower($row['comment']);
    $assignedCategory = "Uncategorized";

    // cek satu per satu kategori
    foreach ($rules as $category => $keywords) {

        foreach ($keywords as $key) {

            if (trim($key) !== "" && strpos($comment, strtolower($key)) !== false) {
                $assignedCategory = $category;
                break 2; // keluar dari dua loop (keyword + kategori)
            }
        }
    }

    $output[] = [
        "id"        => $row['id'],
        "comment"   => $row['comment'],
        "category"  => $assignedCategory
    ];
}

echo json_encode($output, JSON_PRETTY_PRINT);
