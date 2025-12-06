<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// =======================
// SESUAIKAN BAGIAN INI
// =======================

// Jika butuh proteksi login, pakai salah satu:
// require __DIR__ . '/auth_guard.php';
require __DIR__ . '/auth.php';

// Koneksi database: pastikan file ini mendefinisikan $conn = new mysqli(...)
require __DIR__ . '/db.php';

// Nama tabel & struktur data
$tableName       = 'tiket_aduan';
$targetSheetName = 'tiket aduan peserta_november'; // nama sheet di Excel
$targetColumn    = 'Comment';                      // nama header kolom di Excel

// =======================
// HANDLER POST (API JSON)
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data) || !isset($data['comments']) || !is_array($data['comments'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payload JSON tidak valid. Harus berisi field "comments" berupa array.']);
        exit;
    }

    $comments = $data['comments'];

    // 1. Buat tabel kalau belum ada
    $createSql = "
        CREATE TABLE IF NOT EXISTS `$tableName` (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            comment TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    if (!$conn->query($createSql)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Gagal membuat tabel: ' . $conn->error
        ]);
        exit;
    }

    // 2. Cek apakah sudah ada data
    $hasData = 0;
    if ($res = $conn->query("SELECT COUNT(*) AS cnt FROM `$tableName`")) {
        $row     = $res->fetch_assoc();
        $hasData = (int)($row['cnt'] ?? 0);
        $res->free();
    }

    // 3. Jika sudah ada data → TRUNCATE
    if ($hasData > 0) {
        if (!$conn->query("TRUNCATE TABLE `$tableName`")) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Gagal truncate tabel: ' . $conn->error
            ]);
            exit;
        }
    }

    // 4. Insert data baru
    $inserted = 0;

    if (!empty($comments)) {
        $stmt = $conn->prepare("INSERT INTO `$tableName` (comment) VALUES (?)");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Gagal prepare statement: ' . $conn->error
            ]);
            exit;
        }

        foreach ($comments as $comment) {
            $comment = trim((string)$comment);
            if ($comment === '') {
                continue;
            }
            $stmt->bind_param('s', $comment);
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $inserted++;
            }
        }

        $stmt->close();
    }

    echo json_encode([
        'success'          => true,
        'message'          => 'Import berhasil.',
        'total_received'   => count($comments),
        'total_inserted'   => $inserted,
        'truncated_before' => $hasData
    ]);
    exit;
}

// =======================
// GET → TAMPILKAN HALAMAN
// =======================
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Import Tiket Aduan (Magang) - Browser XLSX</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap optional, biar rapi saja -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Library XLSX di browser -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

    <style>
        body { background: #f5f7fb; }
        .card-shadow { box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08); }
        .code { font-family: monospace; font-size: 0.9rem; background:#f1f3f7; padding:2px 6px; border-radius:4px; }
        .badge-sheet { background:#e3f2fd; color:#0d47a1; }
    </style>
</head>
<body>
<div class="container py-5" style="max-width: 960px;">
    <h1 class="h4 mb-3">Import Tiket Aduan (Magang) dari Excel</h1>
    <p class="text-muted mb-4">
        File Excel akan dibaca langsung di browser (tanpa library PHP).<br>
        Sheet yang digunakan: <span class="badge badge-sheet"><?= htmlspecialchars($targetSheetName, ENT_QUOTES, 'UTF-8') ?></span>  
        Kolom yang dipakai: <span class="code"><?= htmlspecialchars($targetColumn, ENT_QUOTES, 'UTF-8') ?></span><br>
        Hasilnya dikirim ke server sebagai JSON, lalu disimpan ke tabel <span class="code"><?= htmlspecialchars($tableName, ENT_QUOTES, 'UTF-8') ?></span>.
    </p>

    <div class="card card-shadow mb-4">
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Pilih file Excel (.xlsx)</label>
                <input type="file" id="excelFile" accept=".xlsx" class="form-control">
                <div class="form-text">
                    Pastikan nama sheet adalah <span class="code"><?= htmlspecialchars($targetSheetName, ENT_QUOTES, 'UTF-8') ?></span>
                    dan header kolom <span class="code"><?= htmlspecialchars($targetColumn, ENT_QUOTES, 'UTF-8') ?></span> ada di baris pertama.
                </div>
            </div>

            <div class="d-flex gap-2">
                <button id="btnPreview" class="btn btn-secondary" type="button">
                    1. Baca & Preview
                </button>
                <button id="btnSend" class="btn btn-primary" type="button" disabled>
                    2. Kirim ke Server & Simpan ke DB
                </button>
                <button id="btnNext" class="btn btn-success" type="button" disabled>
                    3. Lihat Klasifikasi
                </button>
            </div>

            <div id="status" class="mt-3 small text-muted"></div>
        </div>
    </div>

    <div class="card card-shadow">
        <div class="card-body">
            <h2 class="h6 mb-3">Preview Komentar dari Excel</h2>
            <p class="text-muted mb-2">
                Hanya menampilkan maksimal 100 komentar pertama untuk preview.
            </p>
            <ol id="previewList" class="mb-0" style="max-height: 350px; overflow:auto;"></ol>
        </div>
    </div>
</div>

<script>
(function() {
    const excelInput  = document.getElementById('excelFile');
    const btnPreview  = document.getElementById('btnPreview');
    const btnSend     = document.getElementById('btnSend');
    const previewList = document.getElementById('previewList');
    const statusEl    = document.getElementById('status');

    const SHEET_NAME  = <?= json_encode($targetSheetName) ?>;
    const COLUMN_NAME = <?= json_encode($targetColumn) ?>;

    let cachedComments = []; // Menyimpan hasil baca Excel sebelum dikirim ke server

    function setStatus(msg, type = 'info') {
        let color = '#6c757d';
        if (type === 'success') color = '#198754';
        if (type === 'error') color = '#dc3545';
        if (type === 'warn') color = '#fd7e14';
        statusEl.textContent = msg;
        statusEl.style.color = color;
    }

    btnPreview.addEventListener('click', function() {
        const file = excelInput.files && excelInput.files[0];
        if (!file) {
            setStatus('Silakan pilih file Excel terlebih dahulu.', 'warn');
            return;
        }

        setStatus('Membaca file di browser...', 'info');
        btnPreview.disabled = true;
        btnSend.disabled = true;
        previewList.innerHTML = '';
        cachedComments = [];

        const reader = new FileReader();
        reader.onload = function(evt) {
            try {
                const data = new Uint8Array(evt.target.result);
                const workbook = XLSX.read(data, { type: 'array' });

                const sheet = workbook.Sheets[SHEET_NAME];
                if (!sheet) {
                    setStatus('Sheet "' + SHEET_NAME + '" tidak ditemukan di file Excel.', 'error');
                    btnPreview.disabled = false;
                    return;
                }

                // Konversi ke JSON (header baris pertama)
                const rows = XLSX.utils.sheet_to_json(sheet, { defval: '' });

                if (!rows.length) {
                    setStatus('Sheet "' + SHEET_NAME + '" kosong atau tidak ada data.', 'warn');
                    btnPreview.disabled = false;
                    return;
                }

                // Ambil kolom "Comment"
                const comments = [];
                rows.forEach(r => {
                    const val = (r[COLUMN_NAME] || '').toString().trim();
                    if (val !== '') comments.push(val);
                });

                if (!comments.length) {
                    setStatus('Tidak ada data di kolom "' + COLUMN_NAME + '".', 'warn');
                    btnPreview.disabled = false;
                    return;
                }

                cachedComments = comments;
                setStatus('Berhasil membaca ' + comments.length + ' komentar dari Excel.', 'success');

                // Tampilkan max 100 untuk preview
                previewList.innerHTML = '';
                comments.slice(0, 100).forEach(c => {
                    const li = document.createElement('li');
                    li.textContent = c;
                    previewList.appendChild(li);
                });

                btnSend.disabled = false;
            } catch (err) {
                console.error(err);
                setStatus('Terjadi error saat membaca Excel di browser: ' + err.message, 'error');
            } finally {
                btnPreview.disabled = false;
            }
        };

        reader.onerror = function() {
            setStatus('Gagal membaca file. Coba lagi.', 'error');
            btnPreview.disabled = false;
        };

        reader.readAsArrayBuffer(file);
    });

    btnSend.addEventListener('click', function() {
        if (!cachedComments.length) {
            setStatus('Belum ada data komentar yang siap dikirim. Klik "Baca & Preview" dulu.', 'warn');
            return;
        }

        if (!confirm('Kirim ' + cachedComments.length + ' komentar ke server?\nTabel di server akan di-TRUNCATE terlebih dahulu.')) {
            return;
        }

        btnSend.disabled = true;
        setStatus('Mengirim data ke server dan menyimpan ke database...', 'info');

        fetch(location.pathname, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ comments: cachedComments })
        })
        .then(res => res.json())
        .then(json => {
            if (json && json.success) {
                setStatus(
                    'Berhasil menyimpan ' + (json.total_inserted || 0) +
                    ' baris ke tabel ' + <?= json_encode($tableName) ?> +
                    '. (Sebelumnya ada ' + (json.truncated_before || 0) + ' baris yang sudah di-TRUNCATE.)',
                    'success'
                );
            } else {
                setStatus('Gagal menyimpan ke server: ' + (json && json.message ? json.message : 'Unknown error'), 'error');
            }
        })
        .catch(err => {
            console.error(err);
            setStatus('Error jaringan / server: ' + err.message, 'error');
        })
        .finally(() => {
            btnSend.disabled = false;
        });
    });
})();
</script>
</body>
</html>
