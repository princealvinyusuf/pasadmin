<?php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db.php';

$table = "tiket_aduan";

// Ambil semua komentar
$sql = "SELECT comment FROM $table WHERE comment IS NOT NULL AND comment != ''";
$res = $conn->query($sql);

$all = "";

// Gabungkan semua komentar jadi satu teks panjang
while ($row = $res->fetch_assoc()) {
    $all .= " " . strtolower($row['comment']);
}

// Bersihkan karakter non huruf/angka
$clean = preg_replace('/[^a-zA-Z0-9\s]/', ' ', $all);

// Pecah jadi array kata
$words = explode(" ", $clean);

// Stopword Indonesia – bisa ditambah kapan saja
$stopwords = [
    "yang","dan","di","ke","dari","untuk","dengan","saya","kami","itu",
    "iya","tidak","bisa","karena","jadi","sudah","belum","tp","atau",
    "pada","dalam","jika","ada","saja","kak","mohon","tolong","agar",
    "mau","ya","ko","kok","sih","jadi","udh","sudah","sdh"
];

$freq = [];

// Hitung frekuensi kata
foreach ($words as $w) {
    $w = trim($w);
    if ($w === "") continue;          // skip kosong
    if (strlen($w) <= 2) continue;    // skip kata sangat pendek
    if (in_array($w, $stopwords)) continue; // skip stopwords

    if (!isset($freq[$w])) $freq[$w] = 0;
    $freq[$w]++;
}

// Urutkan berdasarkan terbesar → terkecil
arsort($freq);

// Ambil 50 keyword teratas
$top = array_slice($freq, 0, 50, true);

// Output JSON
echo json_encode([
    "total_unique_keywords" => count($freq),
    "top_keywords" => $top
], JSON_PRETTY_PRINT);
