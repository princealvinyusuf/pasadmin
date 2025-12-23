<?php
// OVO (Online Vacancy Outlook) - PHP/Bootstrap prototype page
// - Uses Bootstrap classes to integrate with existing pasadmin layout
// - Mock data only; wire to real APIs later
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>OVO Prototype</title>
	<link rel="icon" href="https://paskerid.kemnaker.go.id/images/services/logo.png" type="image/png">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
	<style>
		/* Minimal layout helpers for the sidebar-driven app shell */
		.ovo-app-shell {
			min-height: calc(100vh - 56px); /* account for navbar height */
		}
		.ovo-sidebar {
			border-right: 1px solid #e5e7eb;
			background-color: #ffffff;
		}
		.ovo-sidebar .nav-link {
			cursor: pointer;
		}
		.ovo-sidebar .nav-link.active {
			background-color: #f3f4f6;
			font-weight: 600;
		}
		.ovo-header {
			border-bottom: 1px solid #e5e7eb;
			background-color: #ffffff;
		}
		.ovo-section {
			display: none;
		}
		.ovo-section.active {
			display: block;
		}
		.badge-soft {
			background-color: #f3f4f6;
			color: #374151;
			font-weight: 500;
		}
		.table-sm td, .table-sm th {
			padding: .5rem .5rem;
		}
	</style>
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/navbar.php'; ?>

<?php
// ---------- Mock data (replace with real queries/API) ----------
$mockScrapers = [
	[ 'id' => 's1', 'name' => 'JobPortal A', 'enabled' => true,  'cron' => '0 */1 * * *', 'lastRun' => '2025-12-03 12:10', 'lastStatus' => 'OK' ],
	[ 'id' => 's2', 'name' => 'JobPortal B', 'enabled' => true,  'cron' => '0 0 */3 * *', 'lastRun' => '2025-12-03 09:00', 'lastStatus' => 'OK' ],
	[ 'id' => 's3', 'name' => 'Karihub Manual Upload', 'enabled' => false, 'cron' => '', 'lastRun' => '-', 'lastStatus' => 'Manual' ],
];

$cities = ['Jakarta', 'Surabaya', 'Bandung', 'Medan'];
$titles = ['Sales', 'Software Engineer', 'Driver', 'Teacher'];
$mockRawRecords = [];
for ($i = 0; $i < 8; $i++) {
	$mockRawRecords[] = [
		'id' => 'raw-'.($i+1),
		'source' => ($i % 2 === 0) ? 'JobPortal A' : 'JobPortal B',
		'title' => $titles[$i % 4],
		'location' => $cities[$i % 4],
		'posted_at' => '2025-12-0'.(($i % 9)+1),
		'salary' => ($i % 3 === 0) ? '' : strval((3 + $i) * 1000000),
		'raw_html_snippet' => '<div>Job content snippet...</div>',
	];
}

$mockTransformRules = [
	[ 'id' => 'r1', 'name' => 'Normalize Province Names', 'description' => 'Map "JKT" => "Jakarta"', 'enabled' => true ],
	[ 'id' => 'r2', 'name' => 'Deduplicate by title+company+location', 'description' => 'Keep newest', 'enabled' => true ],
];

$mockCleanRecords = [];
for ($i = 0; $i < 5; $i++) {
	$r = $mockRawRecords[$i];
	$mockCleanRecords[] = [
		'id' => $r['id'],
		'title' => $r['title'],
		'standardized_title' => strtolower($r['title']),
		'kbli' => '6201',
		'kbji' => 'X',
		'cleaned_at' => '2025-12-03 13:'.(10+$i),
	];
}

$NAV_ITEMS = [
	[ 'id' => 'dashboard', 'label' => 'Dashboard', 'icon' => '📊' ],
	[ 'id' => 'scrapers',  'label' => 'Scrapers',  'icon' => '🕸️' ],
	[ 'id' => 'raw',       'label' => 'Raw DB',    'icon' => '🧱' ],
	[ 'id' => 'transform', 'label' => 'Transform', 'icon' => '⚙️' ],
	[ 'id' => 'clean',     'label' => 'Clean DB',  'icon' => '🧹' ],
	[ 'id' => 'reports',   'label' => 'Reports',   'icon' => '📣' ],
	[ 'id' => 'logs',      'label' => 'Logs',      'icon' => '📜' ],
	[ 'id' => 'settings',  'label' => 'Settings',  'icon' => '⚙️' ],
];
?>

<div class="container-fluid ovo-app-shell">
	<div class="row h-100">
		<!-- Sidebar -->
		<aside class="col-12 col-md-3 col-lg-2 p-0 ovo-sidebar">
			<div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
				<div class="d-flex align-items-center">
					<div class="fs-4 me-2">OVO</div>
					<div class="text-muted small">Online Vacancy Outlook</div>
				</div>
			</div>
			<nav class="nav flex-column p-2">
				<?php foreach ($NAV_ITEMS as $item): ?>
					<a class="nav-link rounded px-3 py-2" data-ovo-link="<?php echo htmlspecialchars($item['id']); ?>">
						<span class="me-2"><?php echo $item['icon']; ?></span><?php echo htmlspecialchars($item['label']); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<div class="px-3 py-3 small text-muted border-top">
				<div>Version: 1.0</div>
				<div>User: Data Engineer</div>
			</div>
		</aside>

		<!-- Main content -->
		<main class="col-12 col-md-9 col-lg-10 p-0">
			<header class="ovo-header px-3 py-2 d-flex align-items-center justify-content-between">
				<div class="d-flex align-items-center gap-3">
					<h1 class="h6 mb-0" id="ovo-page-title">DASHBOARD</h1>
					<span class="text-muted small">Connected: Staging</span>
				</div>
				<div class="d-flex align-items-center gap-2">
					<a class="btn btn-outline-secondary btn-sm" href="#">Notifications</a>
					<img src="https://placehold.co/32" alt="avatar" class="rounded-circle" />
				</div>
			</header>

			<section class="p-3">
				<!-- Dashboard -->
				<div class="ovo-section" data-ovo-page="dashboard">
					<h2 class="h4 mb-3">OVO Dashboard</h2>
					<div class="row g-3 mb-3">
						<div class="col-12 col-md-4">
							<div class="border rounded p-3 h-100">
								<div class="text-muted small">Raw vacancies</div>
								<div class="fs-3 fw-semibold"><?php echo count($mockRawRecords); ?></div>
								<div class="text-muted small">Updated: 2025-12-03 13:00</div>
							</div>
						</div>
						<div class="col-12 col-md-4">
							<div class="border rounded p-3 h-100">
								<div class="text-muted small">Clean vacancies</div>
								<div class="fs-3 fw-semibold"><?php echo count($mockCleanRecords); ?></div>
								<div class="text-muted small">Processed today: 5</div>
							</div>
						</div>
						<div class="col-12 col-md-4">
							<div class="border rounded p-3 h-100">
								<div class="text-muted small">Active scrapers</div>
								<div class="fs-3 fw-semibold">
									<?php echo count(array_filter($mockScrapers, fn($s) => $s['enabled'])); ?>
								</div>
								<div class="text-muted small">Next run: in 50m</div>
							</div>
						</div>
					</div>

					<div class="row g-3">
						<div class="col-12 col-md-6">
							<div class="border rounded p-3 h-100">
								<h5 class="mb-2">Recent transform jobs</h5>
								<ul class="list-unstyled small mb-0">
									<li>2025-12-03 12:50 — Transform batch #342 — <span class="badge badge-soft">Success</span></li>
									<li>2025-12-03 11:20 — Deduplication run — <span class="badge badge-soft">Success</span></li>
									<li>2025-12-03 10:01 — NLP classification — <span class="badge badge-soft">Warning</span></li>
								</ul>
							</div>
						</div>
						<div class="col-12 col-md-6">
							<div class="border rounded p-3 h-100">
								<h5 class="mb-2">Publish status</h5>
								<p class="small text-muted mb-0">Last published report to Tableau: 2025-12-02 18:00</p>
							</div>
						</div>
					</div>
				</div>

				<!-- Scrapers -->
				<div class="ovo-section" data-ovo-page="scrapers">
					<div class="d-flex align-items-center justify-content-between mb-3">
						<h2 class="h4 mb-0">Scraping Manager</h2>
						<div>
							<button class="btn btn-primary btn-sm" id="btn-add-scraper">Add Scraper</button>
						</div>
					</div>
					<div id="scraper-list" class="vstack gap-2"></div>

					<div class="mt-4 border rounded p-3">
						<h5 class="mb-2">Manual Input (Karihub / other)</h5>
						<form class="row g-2">
							<div class="col-md-6">
								<input class="form-control" placeholder="Company" disabled>
							</div>
							<div class="col-md-6">
								<input class="form-control" placeholder="Job title" disabled>
							</div>
							<div class="col-md-6">
								<input class="form-control" placeholder="City/Province" disabled>
							</div>
							<div class="col-md-12">
								<textarea class="form-control" placeholder="Description / raw text" rows="3" disabled></textarea>
							</div>
							<div class="col-12 text-end">
								<button class="btn btn-primary btn-sm" disabled>Submit manual job</button>
							</div>
						</form>
					</div>

					<div class="mt-4">
						<h5 class="mb-2">Cron job status / scheduler</h5>
						<div class="border rounded p-3">
							<p class="small text-muted mb-0">Suggestion: Use a centralized cron manager (e.g., Airflow / Prefect / Kubernetes CronJob). Store schedules in DB and expose an API to start/stop immediate runs.</p>
						</div>
					</div>
				</div>

				<!-- Raw DB -->
				<div class="ovo-section" data-ovo-page="raw">
					<h2 class="h4 mb-3">Raw Database (staging)</h2>
					<div class="d-flex align-items-center gap-2 mb-3">
						<input class="form-control" style="max-width: 320px;" placeholder="Search raw records..." disabled>
						<select class="form-select" style="max-width: 200px;" disabled>
							<option>All sources</option>
							<option>JobPortal A</option>
							<option>JobPortal B</option>
						</select>
						<button class="btn btn-outline-secondary btn-sm" disabled>Export CSV</button>
					</div>
					<div class="table-responsive border rounded">
						<table class="table table-sm mb-0">
							<thead class="table-light">
								<tr>
									<th>ID</th>
									<th>Source</th>
									<th>Title</th>
									<th>Location</th>
									<th>Salary</th>
									<th>Actions</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($mockRawRecords as $r): ?>
									<tr>
										<td class="small"><?php echo htmlspecialchars($r['id']); ?></td>
										<td class="small"><?php echo htmlspecialchars($r['source']); ?></td>
										<td class="small"><?php echo htmlspecialchars($r['title']); ?></td>
										<td class="small"><?php echo htmlspecialchars($r['location']); ?></td>
										<td class="small"><?php echo $r['salary'] !== '' ? htmlspecialchars($r['salary']) : '—'; ?></td>
										<td class="small">
											<button class="btn btn-outline-secondary btn-sm" disabled>View</button>
											<button class="btn btn-outline-secondary btn-sm" disabled>Flag</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>

				<!-- Transform -->
				<div class="ovo-section" data-ovo-page="transform">
					<h2 class="h4 mb-3">Data Transformation Pipeline</h2>
					<div class="border rounded p-3 mb-3">
						<h6 class="mb-2">Pipeline steps (suggested)</h6>
						<ol class="small mb-0 ps-3">
							<li>Cleansing (remove HTML, normalize whitespace)</li>
							<li>Normalization (province, city, job level, gender, salary formats)</li>
							<li>Deduplication (title+company+location timestamps)</li>
							<li>Standardization (KBJI/KBLI mapping, job types)</li>
							<li>Classification (NLP model: KBJI, KBLI, Skills extraction)</li>
						</ol>
					</div>

					<div class="mb-3">
						<div class="d-flex align-items-center justify-content-between mb-2">
							<h6 class="mb-0">Transformation rules</h6>
							<button class="btn btn-primary btn-sm" id="btn-add-rule">Add rule</button>
						</div>
						<div id="rules-list" class="vstack gap-2"></div>
					</div>

					<div class="border rounded p-3">
						<h6 class="mb-2">Manual transform run</h6>
						<div class="d-flex gap-2">
							<button class="btn btn-success btn-sm" disabled>Run pipeline on all raw</button>
							<button class="btn btn-outline-secondary btn-sm" disabled>Run on selection</button>
							<button class="btn btn-outline-secondary btn-sm" disabled>Preview changes (dry run)</button>
						</div>
					</div>
				</div>

				<!-- Clean DB -->
				<div class="ovo-section" data-ovo-page="clean">
					<h2 class="h4 mb-3">Database Clean (canonical)</h2>
					<div class="text-muted small mb-3">This is the canonical dataset used by reporting and publishing systems (Tableau, APIs)</div>
					<div class="table-responsive border rounded">
						<table class="table table-sm mb-0">
							<thead class="table-light">
								<tr>
									<th>ID</th>
									<th>Title</th>
									<th>Std Title</th>
									<th>KBLI</th>
									<th>Cleaned at</th>
									<th>Actions</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($mockCleanRecords as $c): ?>
									<tr>
										<td class="small"><?php echo htmlspecialchars($c['id']); ?></td>
										<td class="small"><?php echo htmlspecialchars($c['title']); ?></td>
										<td class="small"><?php echo htmlspecialchars($c['standardized_title']); ?></td>
										<td class="small"><?php echo htmlspecialchars($c['kbli']); ?></td>
										<td class="small"><?php echo htmlspecialchars($c['cleaned_at']); ?></td>
										<td class="small">
											<button class="btn btn-outline-secondary btn-sm" disabled>Re-classify</button>
											<button class="btn btn-outline-secondary btn-sm" disabled>Export</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<div class="mt-3 border rounded p-3">
						<h6 class="mb-2">Publish / API</h6>
						<p class="small text-muted mb-2">This dataset should be exposed via a versioned API and ETL push to Tableau / reporting tools.</p>
						<div>
							<button class="btn btn-primary btn-sm me-2" disabled>Push to Tableau</button>
							<button class="btn btn-outline-secondary btn-sm" disabled>Download snapshot</button>
						</div>
					</div>
				</div>

				<!-- Reports -->
				<div class="ovo-section" data-ovo-page="reports">
					<h2 class="h4 mb-3">Reports & Publication</h2>
					<div class="row g-3">
						<div class="col-12 col-md-6">
							<div class="border rounded p-3 h-100">
								<h6 class="mb-2">Tools / Dashboard</h6>
								<p class="small text-muted">Link: Tableau / Looker / internal BI. Provide scheduled extracts and row-level security for public vs internal dashboards.</p>
								<button class="btn btn-primary btn-sm" disabled>Open Tableau</button>
							</div>
						</div>
						<div class="col-12 col-md-6">
							<div class="border rounded p-3 h-100">
								<h6 class="mb-2">OVO Report Builder</h6>
								<p class="small text-muted">Create scheduled OVO public reports (CSV / PDF) and set publication cadence.</p>
								<button class="btn btn-outline-secondary btn-sm" disabled>New scheduled report</button>
							</div>
						</div>
					</div>
				</div>

				<!-- Settings -->
				<div class="ovo-section" data-ovo-page="settings">
					<h2 class="h4 mb-3">Settings & Users</h2>
					<div class="row g-3">
						<div class="col-12 col-md-6">
							<div class="border rounded p-3 h-100">
								<h6 class="mb-2">User management</h6>
								<p class="small text-muted mb-0">Roles: SuperAdmin, DataEngineer, LMDataExpert, ScraperAdmin, Viewer</p>
							</div>
						</div>
						<div class="col-12 col-md-6">
							<div class="border rounded p-3 h-100">
								<h6 class="mb-2">Integrations</h6>
								<p class="small text-muted mb-0">Tableau, S3/Cloud storage, DB credentials, NLP model endpoints, Scheduler (Airflow)</p>
							</div>
						</div>
					</div>
				</div>

				<!-- Logs -->
				<div class="ovo-section" data-ovo-page="logs">
					<h2 class="h4 mb-3">System Logs & Audits</h2>
					<div class="border rounded p-3">
						<p class="small text-muted mb-0">Search logs, filter by source (scraper, transform, publish), and export for audits. This view is critical for anti-corruption transparency — keep immutable logs and RBAC for access.</p>
					</div>
				</div>
			</section>
		</main>
	</div>
</div>

<script>
// Simple client-side navigation for sections
(function(){
	const sidebarLinks = document.querySelectorAll('[data-ovo-link]');
	const sections = document.querySelectorAll('[data-ovo-page]');
	const titleEl = document.getElementById('ovo-page-title');

	function setActive(pageId) {
		sections.forEach(s => {
			s.classList.toggle('active', s.getAttribute('data-ovo-page') === pageId);
		});
		sidebarLinks.forEach(a => {
			a.classList.toggle('active', a.getAttribute('data-ovo-link') === pageId);
		});
		titleEl.textContent = String(pageId || '').toUpperCase();
	}

	sidebarLinks.forEach(a => {
		a.addEventListener('click', function(){
			setActive(this.getAttribute('data-ovo-link'));
		});
	});

	// default
	setActive('dashboard');
})();

// React-like client-side logic for Scrapers and Transform Rules
(function(){
	// Seed from PHP mocks
	const scrapers = <?php echo json_encode($mockScrapers); ?>;
	const transformRules = <?php echo json_encode($mockTransformRules); ?>;

	// -------- Scrapers UI --------
	const scraperListEl = document.getElementById('scraper-list');
	const addScraperBtn = document.getElementById('btn-add-scraper');

	function renderScrapers() {
		if (!scraperListEl) return;
		scraperListEl.innerHTML = scrapers.map(s => {
			return `
				<div class="d-flex align-items-center justify-content-between border rounded p-2" data-id="${s.id}">
					<div>
						<div class="fw-semibold">${escapeHtml(s.name)}</div>
						<div class="text-muted small">Cron: ${s.cron ? escapeHtml(s.cron) : '—'} · Last: ${escapeHtml(s.lastRun)} · ${escapeHtml(s.lastStatus)}</div>
					</div>
					<div class="d-flex align-items-center gap-2">
						<input type="text" class="form-control form-control-sm" style="width: 150px;" value="${escapeAttr(s.cron)}" disabled>
						${s.enabled
							? `<button class="btn btn-danger btn-sm" data-action="toggle">Disable</button>`
							: `<button class="btn btn-success btn-sm" data-action="toggle">Enable</button>`
						}
						<button class="btn btn-outline-secondary btn-sm" data-action="run">Run now</button>
						<button class="btn btn-outline-secondary btn-sm" data-action="edit">Edit</button>
					</div>
				</div>
			`;
		}).join('');
	}

	function addScraper() {
		const nextId = 's' + (scrapers.length + 1);
		scrapers.push({ id: nextId, name: 'New Portal', enabled: false, cron: '0 0 * * *', lastRun: '-', lastStatus: 'Never' });
		renderScrapers();
	}

	function toggleScraper(id) {
		const idx = scrapers.findIndex(s => s.id === id);
		if (idx >= 0) {
			scrapers[idx].enabled = !scrapers[idx].enabled;
			renderScrapers();
		}
	}

	// Event delegation for scraper actions
	if (scraperListEl) {
		scraperListEl.addEventListener('click', function(e){
			const btn = e.target.closest('button');
			if (!btn) return;
			const card = btn.closest('[data-id]');
			const id = card ? card.getAttribute('data-id') : null;
			const action = btn.getAttribute('data-action');
			if (action === 'toggle' && id) {
				toggleScraper(id);
			} else if (action === 'run') {
				alert('Trigger immediate run (stub).');
			} else if (action === 'edit') {
				alert('Open edit dialog (stub).');
			}
		});
	}
	if (addScraperBtn) {
		addScraperBtn.addEventListener('click', addScraper);
	}

	// -------- Transform Rules UI --------
	const rulesListEl = document.getElementById('rules-list');
	const addRuleBtn = document.getElementById('btn-add-rule');

	function renderRules() {
		if (!rulesListEl) return;
		rulesListEl.innerHTML = transformRules.map(r => {
			return `
				<div class="border rounded p-2 d-flex align-items-center justify-content-between" data-id="${r.id}">
					<div>
						<div class="fw-semibold">${escapeHtml(r.name)}</div>
						<div class="text-muted small">${escapeHtml(r.description || '')}</div>
					</div>
					<div class="d-flex align-items-center">
						<div class="form-check form-switch">
							<input class="form-check-input" type="checkbox" data-action="toggle" ${r.enabled ? 'checked' : ''}>
							<label class="form-check-label small ms-2">Enabled</label>
						</div>
						<button class="btn btn-outline-secondary btn-sm ms-2" data-action="edit">Edit</button>
					</div>
				</div>
			`;
		}).join('');
	}

	function addRule() {
		const nextId = 'r' + (transformRules.length + 1);
		transformRules.push({ id: nextId, name: 'New Rule', description: '', enabled: false });
		renderRules();
	}

	function toggleRuleByEl(el) {
		const wrap = el.closest('[data-id]');
		const id = wrap ? wrap.getAttribute('data-id') : null;
		if (!id) return;
		const idx = transformRules.findIndex(r => r.id === id);
		if (idx >= 0) {
			transformRules[idx].enabled = !!el.checked;
		}
	}

	if (rulesListEl) {
		rulesListEl.addEventListener('change', function(e){
			const input = e.target.closest('input[data-action="toggle"]');
			if (input) toggleRuleByEl(input);
		});
		rulesListEl.addEventListener('click', function(e){
			const btn = e.target.closest('button[data-action="edit"]');
			if (btn) alert('Open rule editor (stub).');
		});
	}
	if (addRuleBtn) {
		addRuleBtn.addEventListener('click', addRule);
	}

	// Utility
	function escapeHtml(str) {
		return String(str ?? '').replace(/[&<>"']/g, s => ({
			'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
		}[s]));
	}
	function escapeAttr(str) {
		return escapeHtml(str).replace(/"/g, '&quot;');
	}

	// Initial renders
	renderScrapers();
	renderRules();
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


