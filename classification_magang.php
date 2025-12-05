<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

// ----------------- FUNGSI BANTUAN -----------------

function normalizeText(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9áéíóúàèìòùâêîôûäëïöüçñ\s]/u', ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

function extractTokens(string $text): array {
    $stopwords = [
        'yang','dan','atau','di','ke','dari','untuk','pada','dengan','saya','kami',
        'itu','ini','ada','tidak','bisa','apakah','bagaimana','kenapa','mengapa',
        'karena','dalam','atas','akan','jadi','kalau','kalo','kok','sih','ya','kan'
    ];

    $text    = normalizeText($text);
    $tokens  = explode(' ', $text);
    $tokens  = array_filter($tokens, function ($t) use ($stopwords) {
        return $t !== '' && !in_array($t, $stopwords);
    });

    return array_values($tokens);
}

/**
 * Ambil data kolom "Comment" dari sheet tertentu.
 */
function getQuestionsFromExcel(string $filePath, string $sheetName): array {
    $spreadsheet = IOFactory::load($filePath);

    if (!$spreadsheet->sheetNameExists($sheetName)) {
        throw new Exception("Sheet '$sheetName' tidak ditemukan di file Excel.");
    }

    $sheet = $spreadsheet->getSheetByName($sheetName);
    $rows  = $sheet->toArray(null, true, true, true);

    $questions = [];
    $headerMap = [];

    $firstRow = true;
    foreach ($rows as $row) {
        if ($firstRow) {
            foreach ($row as $col => $value) {
                if (!$value) continue;
                $key = mb_strtolower(trim($value), 'UTF-8'); // misal "Comment" jadi "comment"
                $headerMap[$key] = $col;
            }
            $firstRow = false;
            continue;
        }

        if (isset($headerMap['comment'])) {
            $colKey = $headerMap['comment'];
            $question = isset($row[$colKey]) ? trim($row[$colKey]) : '';

            if ($question !== '') {
                $questions[] = $question;
            }
        }
    }

    return $questions;
}

function getKeywordFrequency(array $questions, int $minLength = 3): array {
    $freq = [];

    foreach ($questions as $q) {
        $tokens = extractTokens($q);
        foreach ($tokens as $t) {
            if (mb_strlen($t, 'UTF-8') < $minLength) continue;

            if (!isset($freq[$t])) {
                $freq[$t] = 0;
            }
            $freq[$t]++;
        }
    }

    arsort($freq);
    return $freq;
}

function classifyQuestion(string $question): string {
    $q = mb_strtolower($question, 'UTF-8');

    $rules = [
        'Perubahan Akun' => [
            'ubah akun','ganti akun','ubah email','ganti email','ubah nomor',
            'ganti nomor','reset password','lupa password','ganti password',
            'ubah username','ganti username','update profil','ubah profil'
        ],
        'Error Sistem' => [
            'error','bug','gagal','tidak bisa','nggak bisa','blank','hang',
            'lemot','lambat','tidak muncul','tidak loading','down',
            'server error','500','404','504'
        ],
        'Permasalahan Login' => [
            'tidak bisa login','nggak bisa login','login gagal',
            'akun terkunci','akun diblokir','akun tidak aktif'
        ],
        'Pembayaran / Tagihan' => [
            'bayar','pembayaran','tagihan','invoice','biaya','harga',
            'refund','pengembalian dana'
        ],
        'Fitur / Permintaan Baru' => [
            'bisa ditambahkan','tolong tambahkan','fitur baru',
            'request fitur','penambahan fitur'
        ]
    ];

    foreach ($rules as $category => $keywords) {
        foreach ($keywords as $kw) {
            if (mb_strpos($q, $kw) !== false) {
                return $category;
            }
        }
    }

    return 'Lainnya';
}

// ----------------- LOGIKA: PROSES & AUTO DOWNLOAD -----------------

$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel'])) {
    try {
        if ($_FILES['excel']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error upload file: " . $_FILES['excel']['error']);
        }

        $tmpPath = $_FILES['excel']['tmp_name'];
        if (!is_uploaded_file($tmpPath)) {
            throw new Exception("File upload tidak valid.");
        }

        $sheetName = "tiket aduan peserta_november";

        // 1. Baca semua comment dari Excel
        $questions = getQuestionsFromExcel($tmpPath, $sheetName);

        if (empty($questions)) {
            throw new Exception("Tidak ada data pada kolom 'Comment' di sheet '$sheetName'.");
        }

        // 2. Hitung keyword & klasifikasikan
        $keywordFreq    = getKeywordFrequency($questions);
        $classifiedData = [];

        foreach ($questions as $q) {
            $classifiedData[] = [
                'question' => $q,
                'kategori' => classifyQuestion($q)
            ];
        }

        // 3. Buat Excel hasil
        $spreadsheet = new Spreadsheet();

        // SHEET 1: Klasifikasi
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Klasifikasi');

        $sheet1->setCellValue('A1', 'No');
        $sheet1->setCellValue('B1', 'Comment');
        $sheet1->setCellValue('C1', 'Kategori');

        $rowNum = 2;
        foreach ($classifiedData as $idx => $row) {
            $sheet1->setCellValue('A' . $rowNum, $idx + 1);
            $sheet1->setCellValue('B' . $rowNum, $row['question']);
            $sheet1->setCellValue('C' . $rowNum, $row['kategori']);
            $rowNum++;
        }

        foreach (range('A', 'C') as $col) {
            $sheet1->getColumnDimension($col)->setAutoSize(true);
        }

        // SHEET 2: Keyword
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Keyword');

        $sheet2->setCellValue('A1', 'Keyword');
        $sheet2->setCellValue('B1', 'Frekuensi');

        $rowNum = 2;
        foreach ($keywordFreq as $kw => $count) {
            $sheet2->setCellValue('A' . $rowNum, $kw);
            $sheet2->setCellValue('B' . $rowNum, $count);
            $rowNum++;
        }

        $sheet2->getColumnDimension('A')->setAutoSize(true);
        $sheet2->getColumnDimension('B')->setAutoSize(true);

        // 4. Kirim sebagai download
        $filename = 'hasil_klasifikasi_' . date('Ymd_His') . '.xlsx';

        // Pastikan belum ada output sama sekali sebelum header
        if (ob_get_length()) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;

    } catch (Throwable $e) {
        // TANGKAP SEMUA ERROR DI SINI → tidak 500 lagi, tapi tampil ke layar
        $errorMsg = 'Terjadi error: ' . $e->getMessage();

        // Optional: tulis ke log file sendiri
        file_put_contents(
            __DIR__ . '/classification_magang_error.log',
            date('Y-m-d H:i:s') . ' ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL . '----' . PHP_EOL,
            FILE_APPEND
        );
    }
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Klasifikasi Komentar Client</title>
    <style>
        body { font-family: Arial; margin: 40px; }
        .box { border: 1px solid #ccc; padding: 20px; border-radius: 8px; max-width: 500px; }
        .error { color: red; white-space: pre-line; }
    </style>
</head>
<body>

<h1>Klasifikasi Komentar Client → Auto Download Excel</h1>

<div class="box">
    <p>Upload file Excel yang memiliki kolom <strong>"Comment"</strong> pada sheet <strong>"tiket aduan peserta_november"</strong>.</p>

    <?php if (!empty($errorMsg)): ?>
        <p class="error"><?= htmlspecialchars($errorMsg) ?></p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <label>Pilih File Excel:</label><br><br>
        <input type="file" name="excel" required><br><br>
        <button type="submit">Upload & Proses → Download</button>
    </form>
</div>

</body>
</html>
