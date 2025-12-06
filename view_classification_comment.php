<?php
// Kalau pakai sistem login:
require __DIR__ . '/auth.php'; // atau auth_guard.php sesuai proyekmu
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Tiket & Klasifikasi</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS (opsional, hanya untuk tampilan rapi) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body { background:#f5f7fb; }
        .card-shadow { box-shadow:0 8px 20px rgba(15,23,42,.08); }
        .badge-cat { font-size: 0.75rem; }
        .table-fixed-header thead th { position: sticky; top: 0; background: #ffffff; z-index: 1; }
    </style>
</head>
<body>
<div class="container py-5" style="max-width: 1100px;">
    <h1 class="h4 mb-3">Daftar Tiket & Klasifikasi Otomatis</h1>
    <p class="text-muted">
        Data diambil dari <code>classify_comments.php</code> (JSON) lalu ditampilkan dalam tabel.
        Kolom berisi nomor urut, isi <code>comment</code>, dan hasil <code>category</code> (klasifikasi).
    </p>

    <div class="card card-shadow mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="small text-muted mb-1">Filter cepat (client-side):</div>
                <input type="text" id="searchInput" class="form-control form-control-sm" style="min-width: 260px;"
                       placeholder="Cari di comment atau kategori...">
            </div>
            <div class="text-end small text-muted">
                <div>Total data: <span id="totalCount">0</span></div>
                <div>Ditampilkan: <span id="shownCount">0</span></div>
            </div>
        </div>
    </div>

    <div class="card card-shadow">
        <div class="card-body">
            <div id="status" class="small text-muted mb-2">Memuat data klasifikasi...</div>
            <div class="table-responsive" style="max-height: 500px; overflow:auto;">
                <table class="table table-sm table-striped table-hover align-middle table-fixed-header mb-0">
                    <thead>
                    <tr>
                        <th style="width:60px;">No</th>
                        <th>Comment</th>
                        <th style="width:220px;">Klasifikasi</th>
                    </tr>
                    </thead>
                    <tbody id="dataBody">
                    <!-- diisi via JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS (opsional) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
(function() {
    const API_URL   = 'keyword_extractor.php'; // pastikan path-nya benar relatif ke file ini
    const statusEl  = document.getElementById('status');
    const dataBody  = document.getElementById('dataBody');
    const searchInp = document.getElementById('searchInput');
    const totalEl   = document.getElementById('totalCount');
    const shownEl   = document.getElementById('shownCount');

    let rawData = [];   // semua data dari API
    let filtered = [];  // data setelah filter

    function setStatus(msg, type = 'info') {
        let color = '#6c757d';
        if (type === 'success') color = '#198754';
        if (type === 'error') color = '#dc3545';
        if (type === 'warn') color = '#fd7e14';
        statusEl.textContent = msg;
        statusEl.style.color = color;
    }

    function renderTable(data) {
        dataBody.innerHTML = '';
        if (!data || data.length === 0) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 3;
            td.className = 'text-center text-muted';
            td.textContent = 'Tidak ada data untuk ditampilkan.';
            tr.appendChild(td);
            dataBody.appendChild(tr);

            shownEl.textContent = '0';
            return;
        }

        data.forEach((item, idx) => {
            const tr = document.createElement('tr');

            // kolom nomor
            const tdNo = document.createElement('td');
            tdNo.textContent = (idx + 1).toString();
            tr.appendChild(tdNo);

            // kolom comment
            const tdComment = document.createElement('td');
            tdComment.textContent = item.comment || '';
            tr.appendChild(tdComment);

            // kolom kategori
            const tdCat = document.createElement('td');
            const badge = document.createElement('span');
            badge.className = 'badge bg-primary badge-cat';
            badge.textContent = item.category || 'Uncategorized';
            tdCat.appendChild(badge);
            tr.appendChild(tdCat);

            dataBody.appendChild(tr);
        });

        shownEl.textContent = data.length.toString();
    }

    function applyFilter() {
        const q = (searchInp.value || '').toLowerCase();

        if (!q) {
            filtered = rawData.slice();
        } else {
            filtered = rawData.filter(item => {
                const c = (item.comment  || '').toLowerCase();
                const k = (item.category || '').toLowerCase();
                return c.includes(q) || k.includes(q);
            });
        }

        renderTable(filtered);
    }

    async function loadData() {
        try {
            setStatus('Mengambil data dari ' + API_URL + ' ...', 'info');

            const res = await fetch(API_URL, { cache: 'no-store' });
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }

            const json = await res.json();
            if (!Array.isArray(json)) {
                throw new Error('Respon bukan array JSON');
            }

            rawData = json;
            filtered = json.slice();

            totalEl.textContent = rawData.length.toString();
            setStatus('Berhasil memuat ' + rawData.length + ' baris data.', 'success');

            renderTable(filtered);
        } catch (err) {
            console.error(err);
            setStatus('Gagal memuat data: ' + err.message, 'error');
        }
    }

    searchInp.addEventListener('input', function() {
        applyFilter();
    });

    // initial load
    loadData();
})();
</script>
</body>
</html>
