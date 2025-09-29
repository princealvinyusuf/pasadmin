<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// API block: same file serves JSON for CRUD via ?api=1
if (isset($_GET['api']) && $_GET['api'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    require __DIR__ . '/../db.php';
    require __DIR__ . '/../auth.php';

    // Ensure table exists (first run friendly)
    $conn->query(
        'CREATE TABLE IF NOT EXISTS contacts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            job_title VARCHAR(150) NULL,
            email VARCHAR(190) NULL,
            phone VARCHAR(60) NULL,
            company VARCHAR(190) NULL,
            kemitraan VARCHAR(100) NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_name (name),
            INDEX idx_email (email),
            INDEX idx_job_title (job_title),
            INDEX idx_phone (phone),
            INDEX idx_company (company),
            INDEX idx_kemitraan (kemitraan)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    // Ensure job_title exists for older installs
    try {
        $checkCol = $conn->query("SHOW COLUMNS FROM contacts LIKE 'job_title'");
        if ($checkCol && $checkCol->num_rows === 0) {
            $conn->query('ALTER TABLE contacts ADD COLUMN job_title VARCHAR(150) NULL AFTER name');
        }
    } catch (Throwable $e) { /* ignore */ }

    // Ensure kemitraan exists for older installs
    try {
        $checkColKem = $conn->query("SHOW COLUMNS FROM contacts LIKE 'kemitraan'");
        if ($checkColKem && $checkColKem->num_rows === 0) {
            $conn->query('ALTER TABLE contacts ADD COLUMN kemitraan VARCHAR(100) NULL AFTER company');
            $conn->query('CREATE INDEX idx_kemitraan ON contacts (kemitraan)');
        }
    } catch (Throwable $e) { /* ignore */ }

    set_error_handler(function ($severity, $message, $file, $line) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    $method = $_SERVER['REQUEST_METHOD'];

    try {
        if ($method === 'GET') {
            if (isset($_GET['id']) && intval($_GET['id']) > 0) {
                $id = intval($_GET['id']);
                $stmt = $conn->prepare('SELECT * FROM contacts WHERE id=?');
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $res = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                echo json_encode(['contact' => $res]);
                exit;
            }

            $search = trim($_GET['search'] ?? '');
            $sort = $_GET['sort'] ?? 'name';
            $order = strtolower($_GET['order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
            $allowedSort = ['name', 'job_title', 'email', 'phone', 'company', 'kemitraan', 'created_at'];
            if (!in_array($sort, $allowedSort, true)) $sort = 'name';

            $where = '';
            $params = [];
            $types = '';
            if ($search !== '') {
                $searchLike = '%' . $search . '%';
                $where = 'WHERE name LIKE ? OR job_title LIKE ? OR email LIKE ? OR phone LIKE ? OR company LIKE ? OR kemitraan LIKE ?';
                $params = [$searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $searchLike];
                $types = 'ssssss';
            }

            // Optional exact kemitraan filter via query param
            $kemitraanFilter = trim($_GET['kemitraan'] ?? '');
            if ($kemitraanFilter !== '') {
                $where .= ($where ? ' AND ' : 'WHERE ') . 'kemitraan = ?';
                $params[] = $kemitraanFilter;
                $types .= 's';
            }

            // CSV export (full dataset honoring filters and sort)
            if (isset($_GET['export']) && strtolower($_GET['export']) === 'csv') {
                $exportSql = "SELECT id, name, job_title, email, phone, company, kemitraan, notes, created_at, updated_at FROM contacts $where ORDER BY $sort $order";
                if ($where) {
                    $exportStmt = $conn->prepare($exportSql);
                    $exportStmt->bind_param($types, ...$params);
                    $exportStmt->execute();
                    $exportRes = $exportStmt->get_result();
                } else {
                    $exportRes = $conn->query($exportSql);
                }

                $filename = 'contacts_' . date('Ymd_His') . '.csv';
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename=' . $filename);
                // UTF-8 BOM for Excel
                echo "\xEF\xBB\xBF";
                $out = fopen('php://output', 'w');
                fputcsv($out, ['ID', 'Name', 'Job Title', 'Email', 'Phone', 'Company', 'Kemitraan', 'Notes', 'Created At', 'Updated At']);
                while ($row = $exportRes->fetch_assoc()) {
                    fputcsv($out, [
                        $row['id'] ?? '',
                        $row['name'] ?? '',
                        $row['job_title'] ?? '',
                        $row['email'] ?? '',
                        $row['phone'] ?? '',
                        $row['company'] ?? '',
                        $row['kemitraan'] ?? '',
                        $row['notes'] ?? '',
                        $row['created_at'] ?? '',
                        $row['updated_at'] ?? ''
                    ]);
                }
                if (isset($exportStmt)) { $exportStmt->close(); }
                fclose($out);
                exit;
            }

            // Pagination inputs
            $page = max(1, intval($_GET['page'] ?? 1));
            $perPage = intval($_GET['per_page'] ?? 9);
            if ($perPage <= 0) { $perPage = 9; }
            if ($perPage > 100) { $perPage = 100; }
            $offset = ($page - 1) * $perPage;

            // Total count for pagination
            $countSql = "SELECT COUNT(*) as cnt FROM contacts $where";
            if ($where) {
                $countStmt = $conn->prepare($countSql);
                $countStmt->bind_param($types, ...$params);
                $countStmt->execute();
                $countRes = $countStmt->get_result();
                $total = intval(($countRes->fetch_assoc()['cnt'] ?? 0));
                $countStmt->close();
            } else {
                $countRes = $conn->query($countSql);
                $rowCnt = $countRes ? $countRes->fetch_assoc() : ['cnt' => 0];
                $total = intval($rowCnt['cnt'] ?? 0);
            }

            // Data page
            $sql = "SELECT * FROM contacts $where ORDER BY $sort $order LIMIT ? OFFSET ?";
            $stmt = $conn->prepare($sql);
            if ($types) {
                $bindTypes = $types . 'ii';
                $bindValues = array_merge($params, [$perPage, $offset]);
                $stmt->bind_param($bindTypes, ...$bindValues);
            } else {
                $stmt->bind_param('ii', $perPage, $offset);
            }
            $stmt->execute();
            $result = $stmt->get_result();

            $contacts = [];
            while ($row = $result->fetch_assoc()) { $contacts[] = $row; }
            if (isset($stmt)) $stmt->close();
            echo json_encode([
                'contacts' => $contacts,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ($perPage > 0 ? (int)ceil($total / $perPage) : 1)
                ]
            ]);
            exit;
        }

        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);
        if (!is_array($data)) { $data = $_POST; }

        if ($method === 'POST') {
            $stmt = $conn->prepare('INSERT INTO contacts (name, job_title, email, phone, company, kemitraan, notes) VALUES (?,?,?,?,?,?,?)');
            $name = trim($data['name'] ?? '');
            if ($name === '') { http_response_code(422); echo json_encode(['error' => 'Name is required']); exit; }
            $jobTitle = trim($data['job_title'] ?? '');
            $email = trim($data['email'] ?? '');
            $phone = trim($data['phone'] ?? '');
            $company = trim($data['company'] ?? '');
            $kemitraan = trim($data['kemitraan'] ?? '');
            $notes = trim($data['notes'] ?? '');
            $stmt->bind_param('sssssss', $name, $jobTitle, $email, $phone, $company, $kemitraan, $notes);
            $stmt->execute();
            echo json_encode(['success' => $stmt->affected_rows > 0, 'id' => $stmt->insert_id]);
            $stmt->close();
            exit;
        }

        if ($method === 'PUT') {
            $id = intval($data['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'Invalid ID']); exit; }
            $stmt = $conn->prepare('UPDATE contacts SET name=?, job_title=?, email=?, phone=?, company=?, kemitraan=?, notes=? WHERE id=?');
            $name = trim($data['name'] ?? '');
            if ($name === '') { http_response_code(422); echo json_encode(['error' => 'Name is required']); exit; }
            $jobTitle = trim($data['job_title'] ?? '');
            $email = trim($data['email'] ?? '');
            $phone = trim($data['phone'] ?? '');
            $company = trim($data['company'] ?? '');
            $kemitraan = trim($data['kemitraan'] ?? '');
            $notes = trim($data['notes'] ?? '');
            $stmt->bind_param('sssssssi', $name, $jobTitle, $email, $phone, $company, $kemitraan, $notes, $id);
            $stmt->execute();
            echo json_encode(['success' => $stmt->affected_rows >= 0]);
            $stmt->close();
            exit;
        }

        if ($method === 'DELETE') {
            $id = intval($_GET['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'Invalid ID']); exit; }
            $stmt = $conn->prepare('DELETE FROM contacts WHERE id=?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            echo json_encode(['success' => $stmt->affected_rows > 0]);
            $stmt->close();
            exit;
        }

        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Server error',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    } finally {
        if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
    }
    exit;
}

// HTML/UI branch requires normal auth guard (redirect to login if needed)
require_once __DIR__ . '/../auth_guard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Contact</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .header-bar { background: linear-gradient(135deg, #0d6efd, #2a77ff); }
        .search-input { border-radius: 9999px; padding-left: 42px; }
        .search-icon { position:absolute; left:14px; top:10px; color:#6c757d; }
        .empty-state { color:#6c757d; }
        .rounded-xl { border-radius: 16px; }
    </style>
    <script>
        // Small helper to know API url on this same file
        const API_URL = location.pathname + '?api=1';
    </script>
</head>
<body class="bg-light">
    <?php include '../navbar.php'; ?>

    <header class="header-bar py-4 mb-4 text-white">
        <div class="container d-flex flex-wrap align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <div class="fs-2"><i class="bi bi-person-lines-fill"></i></div>
                <div>
                    <h1 class="h4 mb-0">Database Contact</h1>
                    <small class="opacity-75">Manage contacts with search, sort and quick actions</small>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button id="btn-export" class="btn btn-outline-light text-white border-0">
                    <i class="bi bi-file-earmark-excel me-1"></i> Export to Excel
                </button>
                <button id="btn-add-contact-top" class="btn btn-light text-primary fw-semibold">
                    <i class="bi bi-plus-lg me-1"></i> Add Contact
                </button>
            </div>
        </div>
    </header>

    <main class="container" style="max-width:1100px;">
        <div class="card shadow-sm rounded-xl mb-4">
            <div class="card-body">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-md-8 position-relative">
                        <i class="bi bi-search search-icon"></i>
                        <input type="text" id="search" class="form-control search-input" placeholder="Search contacts by name, email, phone, or company...">
                    </div>
                    <div class="col-6 col-md-2">
                        <select id="filter-kemitraan" class="form-select">
                            <option value="">All Kemitraan</option>
                            <option value="Kemitraan/Lembaga">Kemitraan/Lembaga</option>
                            <option value="Pemerintah Daerah">Pemerintah Daerah</option>
                            <option value="Swasta/Perusahaan">Swasta/Perusahaan</option>
                            <option value="Job Portal">Job Portal</option>
                            <option value="Universitas">Universitas</option>
                            <option value="Asosiasi/Komunitas">Asosiasi/Komunitas</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <select id="sort" class="form-select">
                            <option value="name" selected>Name</option>
                            <option value="kemitraan">Kemitraan</option>
                            <option value="email">Email</option>
                            <option value="phone">Phone</option>
                            <option value="company">Company</option>
                            <option value="created_at">Created</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <select id="order" class="form-select">
                            <option value="asc">Asc</option>
                            <option value="desc">Desc</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm rounded-xl">
            <div class="card-body">
                <div id="empty-state" class="text-center py-5 empty-state" style="display:none;">
                    <div class="fs-1 mb-2 text-primary"><i class="bi bi-person-plus"></i></div>
                    <h2 class="h5 mb-2">No contacts yet</h2>
                    <p class="mb-3">Get started by adding your first contact to the database.</p>
                    <button id="btn-add-contact-empty" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add Your First Contact</button>
                </div>

                <div id="cards-container" class="row g-3"></div>
                <nav class="mt-3" aria-label="Pagination">
                    <ul id="pagination" class="pagination justify-content-center mb-0"></ul>
                </nav>
            </div>
        </div>
    </main>

    <footer class="text-center py-4 bg-white border-top mt-5">
        <span class="text-muted small">&copy; 2025 Database Contact</span>
    </footer>

    <!-- Modal: Add/Edit Contact -->
    <div class="modal fade" id="contactModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="contactModalLabel">Add Contact</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="contact-form">
                    <div class="modal-body">
                        <input type="hidden" id="contact-id">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" id="contact-name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Job Title</label>
                            <input type="text" id="contact-job-title" class="form-control" placeholder="IT Engineer, HR Manager, ...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Kemitraan</label>
                            <select id="contact-kemitraan" class="form-select">
                                <option value="">- Pilih Kemitraan -</option>
                                <option value="Kemitraan/Lembaga">Kemitraan/Lembaga</option>
                                <option value="Pemerintah Daerah">Pemerintah Daerah</option>
                                <option value="Swasta/Perusahaan">Swasta/Perusahaan</option>
                                <option value="Job Portal">Job Portal</option>
                                <option value="Universitas">Universitas</option>
                                <option value="Asosiasi/Komunitas">Asosiasi/Komunitas</option>
                            </select>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Email</label>
                                <input type="email" id="contact-email" class="form-control" placeholder="name@example.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="text" id="contact-phone" class="form-control" placeholder="+62 ...">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Company</label>
                            <input type="text" id="contact-company" class="form-control" placeholder="Company name">
                        </div>
                        <div class="mt-3">
                            <label class="form-label">Notes</label>
                            <textarea id="contact-notes" class="form-control" rows="3" placeholder="Additional notes"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function() {
        const cardsContainer = document.getElementById('cards-container');
        const paginationEl = document.getElementById('pagination');
        const emptyState = document.getElementById('empty-state');
        const searchInput = document.getElementById('search');
        const filterKemitraan = document.getElementById('filter-kemitraan');
        const sortSelect = document.getElementById('sort');
        const orderSelect = document.getElementById('order');
        const modalEl = document.getElementById('contactModal');
        const modal = new bootstrap.Modal(modalEl);
        const form = document.getElementById('contact-form');

        function fmtDate(s) { if (!s) return ''; const d = new Date(s); if (isNaN(d)) return s; return d.toLocaleDateString(); }

        function renderRows(contacts) {
            cardsContainer.innerHTML = '';
            if (!contacts || contacts.length === 0) {
                emptyState.style.display = 'block';
                return;
            }
            emptyState.style.display = 'none';
            contacts.forEach(c => {
                const col = document.createElement('div');
                col.className = 'col-12 col-md-6 col-lg-4';
                const initials = (c.name || '?')
                    .split(' ')
                    .filter(Boolean)
                    .map(s => s[0].toUpperCase())
                    .slice(0,2)
                    .join('');
                col.innerHTML = `
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width:42px;height:42px; font-weight:600;">${initials}</div>
                                    <div>
                                        <div class="fw-semibold">${escapeHtml(c.name || '')}</div>
                                        <div class="text-muted small">${escapeHtml(c.job_title || '')}</div>
                                    </div>
                                </div>
                                <div>
                                    <button class="btn btn-light border btn-sm me-1" title="Edit" data-action="edit" data-id="${c.id}"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-light border btn-sm me-1" title="Duplicate" data-action="duplicate" data-id="${c.id}"><i class="bi bi-files"></i></button>
                                    <button class="btn btn-light border btn-sm text-danger" title="Delete" data-action="delete" data-id="${c.id}"><i class="bi bi-trash"></i></button>
                                </div>
                            </div>
                            <hr>
                            <div class="small mb-1"><i class="bi bi-envelope me-2 text-muted"></i>${escapeHtml(c.email || '')}</div>
                            <div class="small mb-1"><i class="bi bi-telephone me-2 text-muted"></i>${escapeHtml(c.phone || '')}</div>
                            <div class="small mb-1"><i class="bi bi-building me-2 text-muted"></i>${escapeHtml(c.company || '')}</div>
                            <div class="small mb-2"><i class="bi bi-people me-2 text-muted"></i>${escapeHtml(c.kemitraan || '')}</div>
                            <div class="small text-muted"><i class="bi bi-calendar2-plus me-2"></i>Added ${fmtDate(c.created_at)}</div>
                        </div>
                    </div>`;
                cardsContainer.appendChild(col);
            });
        }

        function escapeHtml(s) {
            return String(s).replace(/[&<>"]/g, function(m) { return ({'&':'&amp;','<' :'&lt;','>' :'&gt;','"':'&quot;'}[m]); });
        }

        let currentPage = 1;
        const perPage = 9;

        function renderPagination(pagination) {
            paginationEl.innerHTML = '';
            if (!pagination || pagination.total_pages <= 1) { return; }
            const { page, total_pages } = pagination;

            function pageItem(p, label = null, disabled = false, active = false) {
                const li = document.createElement('li');
                li.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
                const a = document.createElement('a');
                a.className = 'page-link';
                a.href = '#';
                a.textContent = label || String(p);
                a.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (!disabled && !active) {
                        currentPage = p;
                        loadContacts();
                    }
                });
                li.appendChild(a);
                return li;
            }

            paginationEl.appendChild(pageItem(page - 1, '«', page <= 1, false));
            const start = Math.max(1, page - 2);
            const end = Math.min(total_pages, page + 2);
            for (let p = start; p <= end; p++) {
                paginationEl.appendChild(pageItem(p, null, false, p === page));
            }
            paginationEl.appendChild(pageItem(page + 1, '»', page >= total_pages, false));
        }

        async function loadContacts() {
            const params = new URLSearchParams({
                api: '1',
                search: searchInput.value.trim(),
                sort: sortSelect.value,
                order: orderSelect.value,
                page: String(currentPage),
                per_page: String(perPage)
            });
            if (filterKemitraan.value) { params.set('kemitraan', filterKemitraan.value); }
            const res = await fetch('' + location.pathname + '?' + params.toString());
            const data = await res.json();
            renderRows(data.contacts || []);
            renderPagination(data.pagination || null);
        }

        async function saveContact(payload) {
            const method = payload.id ? 'PUT' : 'POST';
            const res = await fetch(API_URL, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            return res.json();
        }

        async function deleteContact(id) {
            const res = await fetch(API_URL + '&id=' + encodeURIComponent(id), { method: 'DELETE' });
            return res.json();
        }

        // Event bindings
        document.getElementById('btn-add-contact-top').addEventListener('click', () => { openAdd(); });
        document.getElementById('btn-add-contact-empty').addEventListener('click', () => { openAdd(); });
        document.getElementById('btn-export').addEventListener('click', () => {
            const params = new URLSearchParams({
                api: '1',
                export: 'csv',
                search: searchInput.value.trim(),
                sort: sortSelect.value,
                order: orderSelect.value
            });
            if (filterKemitraan.value) { params.set('kemitraan', filterKemitraan.value); }
            window.open('' + location.pathname + '?' + params.toString(), '_blank');
        });
        function resetToFirstAndLoad() { currentPage = 1; loadContacts(); }
        searchInput.addEventListener('input', debounce(resetToFirstAndLoad, 300));
        sortSelect.addEventListener('change', resetToFirstAndLoad);
        orderSelect.addEventListener('change', resetToFirstAndLoad);
        filterKemitraan.addEventListener('change', resetToFirstAndLoad);

        cardsContainer.addEventListener('click', async (e) => {
            const btn = e.target.closest('button[data-action]');
            if (!btn) return;
            const id = btn.getAttribute('data-id');
            const action = btn.getAttribute('data-action');
            if (action === 'edit') {
                // Fetch detail and open modal
                const res = await fetch(API_URL + '&id=' + encodeURIComponent(id));
                const data = await res.json();
                const c = data.contact || {};
                document.getElementById('contact-id').value = c.id || '';
                document.getElementById('contact-name').value = c.name || '';
                document.getElementById('contact-job-title').value = c.job_title || '';
                document.getElementById('contact-email').value = c.email || '';
                document.getElementById('contact-phone').value = c.phone || '';
                document.getElementById('contact-company').value = c.company || '';
                document.getElementById('contact-kemitraan').value = c.kemitraan || '';
                document.getElementById('contact-notes').value = c.notes || '';
                document.getElementById('contactModalLabel').innerText = 'Edit Contact';
                modal.show();
            } else if (action === 'duplicate') {
                const res = await fetch(API_URL + '&id=' + encodeURIComponent(id));
                const data = await res.json();
                const c = data.contact || {};
                const payload = {
                    name: c.name || '',
                    job_title: c.job_title || '',
                    email: c.email || '',
                    phone: c.phone || '',
                    company: c.company || '',
                    kemitraan: c.kemitraan || '',
                    notes: c.notes || ''
                };
                const result = await saveContact(payload);
                if (result && (result.success || result.id)) {
                    loadContacts();
                } else {
                    alert('Failed to duplicate contact');
                }
            } else if (action === 'delete') {
                if (confirm('Delete this contact?')) {
                    await deleteContact(id);
                    loadContacts();
                }
            }
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                id: parseInt(document.getElementById('contact-id').value || '0', 10) || undefined,
                name: document.getElementById('contact-name').value.trim(),
                job_title: document.getElementById('contact-job-title').value.trim(),
                email: document.getElementById('contact-email').value.trim(),
                phone: document.getElementById('contact-phone').value.trim(),
                company: document.getElementById('contact-company').value.trim(),
                kemitraan: document.getElementById('contact-kemitraan').value.trim(),
                notes: document.getElementById('contact-notes').value.trim()
            };
            const res = await saveContact(payload);
            if (res && (res.success || res.id)) {
                modal.hide();
                form.reset();
                document.getElementById('contact-id').value = '';
                document.getElementById('contactModalLabel').innerText = 'Add Contact';
                loadContacts();
            } else {
                alert((res && res.error) ? res.error : 'Failed to save contact');
            }
        });

        function openAdd() {
            form.reset();
            document.getElementById('contact-id').value = '';
            document.getElementById('contact-kemitraan').value = '';
            document.getElementById('contactModalLabel').innerText = 'Add Contact';
            modal.show();
        }

        function debounce(fn, wait) {
            let t; return function() { clearTimeout(t); t = setTimeout(() => fn.apply(this, arguments), wait); };
        }

        // Initial load
        loadContacts();
    })();
    </script>
</body>
</html>


