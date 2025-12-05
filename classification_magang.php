<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Jika di sistemmu butuh login dulu, aktifkan ini
require __DIR__ . '/auth.php';

// Koneksi database (harus ada $conn = new mysqli(...))
require __DIR__ . '/db.php';

// Library XLSX satu file (BUKAN composer)
require __DIR__ . '/SimpleXLSX.php';

// Nama tabel untuk menyimpan data komentar
$tableName       = 'classification_magang';
// Nama sheet & kolom di Excel
$targetSheetName = 'tiket aduan peserta_november';
$targetColumn    = 'Comment';

// ========================================
// 1. GET → tampilkan form upload
// ========================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <title>Import Tiket Aduan</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
    <div class="container py-5" style="max-width:800px;">
        <h1 class="h4 mb-3">Import Tiket Aduan dari Excel</h1>
        <p class="text-muted">
            File Excel yang diupload akan dibaca dari sheet:
            <code><?= htmlspecialchars($targetSheetName, ENT_QUOTES, 'UTF-8') ?></code><br>
            dan hanya kolom dengan header:
            <code><?= htmlspecialchars($targetColumn, ENT_QUOTES, 'UTF-8') ?></code> yang disimpan.
        </p>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form action="" method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">File Excel (.xlsx)</label>
                        <input type="file" name="file_excel" accept=".xlsx" class="form-control" required>
                        <div class="form-text">Gunakan format .xlsx (bukan .xls).</div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        Upload & Import
                    </button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3">Data terbaru di tabel <code><?= htmlspecialchars($tableName) ?></code></h2>
                <?php
                // Coba tampilkan 20 data terakhir jika tabel sudah ada
                $existsRes = $conn->query("SHOW TABLES LIKE '$tableName'");
                if ($existsRes && $existsRes->num_rows > 0) {
                    $res = $conn->query("SELECT id, comment, created_at FROM `$tableName` ORDER BY id DESC LIMIT 20");
                    if ($res && $res->num_rows > 0) {
                        echo '<div class="table-responsive"><table class="table table-sm table-striped align-middle">';
                        echo '<thead><tr><th>ID</th><th>Comment</th><th>Created At</th></tr></thead><tbody>';
                        while ($row = $res->fetch_assoc()) {
                            echo '<tr>';
                            echo '<td>' . (int)$row['id'] . '</td>';
                            echo '<td>' . htmlspecialchars($row['comment'], ENT_QUOTES, 'UTF-8') . '</td>';
                            echo '<td>' . htmlspecialchars($row['created_at'], ENT_QUOTES, 'UTF-8') . '</td>';
                            echo '</tr>';
                        }
                        echo '</tbody></table></div>';
                    } else {
                        echo '<p class="text-muted mb-0">Belum ada data di tabel.</p>';
                    }
                } else {
                    echo '<p class="text-muted mb-0">Tabel belum ada. Akan dibuat otomatis saat pertama kali import.</p>';
                }
                ?>
            </div>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// ========================================
// 2. POST → proses upload & import
// ========================================

// Validasi upload
if (!isset($_FILES['file_excel']) || $_FILES['file_excel']['error'] !== UPLOAD_ERR_OK) {
    die('Upload file gagal. Pastikan kamu memilih file .xlsx');
}

$uploadedName = $_FILES['file_excel']['name'];
$ext          = strtolower(pathinfo($uploadedName, PATHINFO_EXTENSION));

if ($ext !== 'xlsx') {
    die('Untuk contoh ini hanya mendukung file .xlsx (bukan .xls).');
}

// Simpan di folder uploads
$uploadDir = __DIR__ . '/uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$tempName = $_FILES['file_excel']['tmp_name'];
$newName  = $uploadDir . '/' . time() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '_', $uploadedName);

if (!move_uploaded_file($tempName, $newName)) {
    die('Gagal memindahkan file upload.');
}

// Parse file dengan SimpleXLSX
if (!$xlsx = SimpleXLSX::parse($newName)) {
    die('Gagal membaca file XLSX: ' . SimpleXLSX::parseError());
}

// Cari index sheet "tiket aduan peserta_november"
$sheetNames = $xlsx->sheetNames();
$sheetIndex = array_search($targetSheetName, $sheetNames);

if ($sheetIndex === false) {
    die(
        'Sheet "' . htmlspecialchars($targetSheetName, ENT_QUOTES, 'UTF-8') . '" tidak ditemukan.<br>' .
        'Sheet yang ada: ' . htmlspecialchars(implode(', ', $sheetNames), ENT_QUOTES, 'UTF-8')
    );
}

// Ambil semua baris pada sheet tersebut (array 2D)
$rows = $xlsx->rows($sheetIndex);

if (empty($rows)) {
    die('Sheet kosong atau tidak ada data.');
}

// Baris pertama = header
$header          = $rows[0];
$commentColIndex = null;

// Cari indeks kolom dengan header "Comment" (case-insensitive)
foreach ($header as $idx => $colName) {
    if (strcasecmp(trim((string)$colName), $targetColumn) === 0) {
        $commentColIndex = $idx;
        break;
    }
}

if ($commentColIndex === null) {
    die('Kolom "' . htmlspecialchars($targetColumn, ENT_QUOTES, 'UTF-8') . '" tidak ditemukan di baris header.');
}

// Loop mulai baris index 1 (0 = header), ambil nilai Comment
$comments = [];
for ($i = 1; $i < count($rows); $i++) {
    $row = $rows[$i];

    // Antisipasi baris pendek / kolom kosong
    $comment = isset($row[$commentColIndex]) ? trim((string)$row[$commentColIndex]) : '';

    if ($comment === '') {
        continue;
    }

    $comments[] = $comment;
}

// ========================================
// 3. Pastikan tabel ada (CREATE TABLE IF NOT EXISTS)
// ========================================
$createSql = "
    CREATE TABLE IF NOT EXISTS `$tableName` (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        comment TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if (!$conn->query($createSql)) {
    die('Gagal membuat tabel `'.$tableName.'`: ' . $conn->error);
}

// ========================================
// 4. Insert data ke tabel
// ========================================
$insertedCount = 0;

if (!empty($comments)) {
    $stmt = $conn->prepare("INSERT INTO `$tableName` (comment) VALUES (?)");
    if (!$stmt) {
        die('Gagal prepare statement: ' . $conn->error);
    }

    foreach ($comments as $comment) {
        $stmt->bind_param('s', $comment);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $insertedCount++;
        }
    }

    $stmt->close();
}

// ========================================
// 5. Tampilkan ringkasan hasil import
// ========================================
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Hasil Import Tiket Aduan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:800px;">
    <h1 class="h4 mb-3">Hasil Import Tiket Aduan</h1>

    <div class="alert alert-info">
        <div><strong>File:</strong> <?= htmlspecialchars($uploadedName, ENT_QUOTES, 'UTF-8') ?></div>
        <div><strong>Sheet:</strong> <?= htmlspecialchars($targetSheetName, ENT_QUOTES, 'UTF-8') ?></div>
        <div><strong>Kolom:</strong> <?= htmlspecialchars($targetColumn, ENT_QUOTES, 'UTF-8') ?></div>
        <div><strong>Jumlah komentar terbaca:</strong> <?= count($comments) ?></div>
        <div><strong>Jumlah baris yang berhasil disimpan ke DB:</strong> <?= $insertedCount ?></div>
        <div><strong>Nama tabel:</strong> <code><?= htmlspecialchars($tableName, ENT_QUOTES, 'UTF-8') ?></code></div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h5 mb-3">Preview komentar yang diimport</h2>
            <?php if (empty($comments)): ?>
                <p class="text-muted mb-0">Tidak ada komentar yang bisa diimport.</p>
            <?php else: ?>
                <ol class="mb-0">
                    <?php foreach ($comments as $c): ?>
                        <li><?= htmlspecialchars($c, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </div>
    </div>

    <a href="import_tiket.php" class="btn btn-primary">← Kembali ke halaman import</a>
</div>
</body>
</html>
