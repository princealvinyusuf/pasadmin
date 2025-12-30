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
		:root {
			--ovo-primary: #4f46e5;
			--ovo-surface: #ffffff;
			--ovo-muted: #6b7280;
		}
		.ovo-app-shell { min-height: calc(100vh - 56px); }
		.ovo-sidebar { border-right: 1px solid #e5e7eb; background-color: var(--ovo-surface); }
		.ovo-sidebar .nav-link { cursor: pointer; transition: background-color .15s ease; }
		.ovo-sidebar .nav-link.active { background-color: #eef2ff; font-weight: 600; color: #312e81; }
		.ovo-header { border-bottom: 1px solid #e5e7eb; background-color: var(--ovo-surface); position: sticky; top: 0; z-index: 10; }
		.ovo-section { display: none; }
		.ovo-section.active { display: block; }
		.badge-soft { background-color: #f3f4f6; color: #111827; font-weight: 500; }
		.table-sm td, .table-sm th { padding: .55rem .6rem; vertical-align: middle; }
		.ovo-card { border: 1px solid #e5e7eb; border-radius: 12px; background: var(--ovo-surface); box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06); }
		.ovo-pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 12px; background: #eef2ff; color: #312e81; }
		.ovo-pill.success { background: #ecfdf3; color: #166534; }
		.ovo-pill.warn { background: #fef3c7; color: #92400e; }
		.ovo-pill.neutral { background: #e5e7eb; color: #374151; }
		.ovo-stat { display: flex; flex-direction: column; gap: 4px; }
		.ovo-stat .label { color: var(--ovo-muted); font-size: 13px; }
		.ovo-stat .value { font-size: 24px; font-weight: 700; }
		.ovo-kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
		.ovo-progress { height: 8px; border-radius: 999px; background: #e5e7eb; overflow: hidden; }
		.ovo-progress > div { height: 100%; background: linear-gradient(90deg, #4f46e5, #22d3ee); }
	</style>
</head>
<body class="bg-light">
<?php require_once __DIR__ . '/navbar.php'; ?>

<?php
// ---------- Mock data (replace with real queries/API) ----------
$mockScrapers = [
	[ 'id' => 's1', 'name' => 'JobPortal A', 'enabled' => true,  'cron' => '0 */1 * * *', 'lastRun' => '2025-12-03 12:10', 'lastStatus' => 'OK', 'successRate' => 97, 'medianRuntime' => 42, 'lastError' => '' ],
	[ 'id' => 's2', 'name' => 'JobPortal B', 'enabled' => true,  'cron' => '0 0 */3 * *', 'lastRun' => '2025-12-03 09:00', 'lastStatus' => 'OK', 'successRate' => 91, 'medianRuntime' => 55, 'lastError' => '' ],
	[ 'id' => 's3', 'name' => 'Karihub Manual Upload', 'enabled' => false, 'cron' => '', 'lastRun' => '-', 'lastStatus' => 'Manual', 'successRate' => 100, 'medianRuntime' => 12, 'lastError' => 'Missing CSV mapping' ],
];

$cities = ['Jakarta', 'Surabaya', 'Bandung', 'Medan'];
$titles = ['Sales', 'Software Engineer', 'Driver', 'Teacher'];
$companies = ['PT Nusantara', 'PT Digital Karya', 'PT Logistik Maju', 'PT Edu Prima'];
$mockRawRecords = [];
for ($i = 0; $i < 8; $i++) {
	$mockRawRecords[] = [
		'id' => 'raw-'.($i+1),
		'source' => ($i % 2 === 0) ? 'JobPortal A' : 'JobPortal B',
		'company' => $companies[$i % count($companies)],
		'source_id' => 'SRC-10'.($i+1),
		'title' => $titles[$i % 4],
		'location' => $cities[$i % 4],
		'posted_at' => '2025-12-0'.(($i % 9)+1),
		'fetched_at' => '2025-12-03 1'.($i % 6).':0'.$i,
		'status' => ($i % 3 === 0) ? 'flagged' : (($i % 3 === 1) ? 'parsed' : 'new'),
		'flags' => ($i % 3 === 0) ? 'salary_missing' : '',
		'duplicate_score' => 12 + $i * 3,
		'completeness' => 72 + ($i % 5) * 5,
		'salary' => ($i % 3 === 0) ? '' : strval((3 + $i) * 1000000),
		'raw_html_snippet' => '<div>Job content snippet...</div>',
		'quality_notes' => ($i % 3 === 0) ? 'Missing salary' : 'OK',
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
		'normalized_location' => $r['location'].', ID',
		'confidence' => 82 + ($i * 3),
		'review_needed' => $i === 2,
		'cleaned_at' => '2025-12-03 13:'.(10+$i),
	];
}

$mockTransformJobs = [
	[ 'id' => 'tj-342', 'started_at' => '2025-12-03 12:50', 'duration' => '1m22s', 'status' => 'success', 'in_count' => 180, 'out_count' => 178, 'errors' => 0 ],
	[ 'id' => 'tj-341', 'started_at' => '2025-12-03 11:20', 'duration' => '56s', 'status' => 'success', 'in_count' => 210, 'out_count' => 209, 'errors' => 1 ],
	[ 'id' => 'tj-340', 'started_at' => '2025-12-03 10:01', 'duration' => '2m10s', 'status' => 'warning', 'in_count' => 190, 'out_count' => 185, 'errors' => 5 ],
];

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
					<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-3 gap-2">
						<div>
							<h2 class="h4 mb-1">OVO Dashboard</h2>
							<div class="text-muted small">Realtime view of vacancy ingestion, quality, and publication</div>
						</div>
						<div class="d-flex gap-2">
							<button class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-repeat me-1"></i>Refresh</button>
							<button class="btn btn-primary btn-sm"><i class="bi bi-cloud-upload me-1"></i>Publish snapshot</button>
						</div>
					</div>

					<div class="ovo-card p-3 mb-3">
						<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
							<div class="d-flex align-items-center gap-2">
								<span class="ovo-pill success"><i class="bi bi-check-circle"></i>Healthy</span>
								<span class="text-muted small">Last sync: 2025-12-03 13:10 (staging)</span>
							</div>
							<div class="d-flex align-items-center gap-3">
								<div class="text-muted small">Data quality</div>
								<div class="ovo-progress" style="width: 180px;">
									<div style="width: 86%;"></div>
								</div>
								<div class="fw-semibold small">86%</div>
							</div>
						</div>
					</div>

					<div class="ovo-kpi-grid mb-3">
						<div class="ovo-card p-3">
							<div class="ovo-stat">
								<div class="label">Raw vacancies</div>
								<div class="value"><?php echo count($mockRawRecords); ?></div>
								<div class="text-muted small">Updated 13:00 · 8 sources</div>
							</div>
						</div>
						<div class="ovo-card p-3">
							<div class="ovo-stat">
								<div class="label">Clean vacancies</div>
								<div class="value"><?php echo count($mockCleanRecords); ?></div>
								<div class="text-muted small">Processed today: 5</div>
							</div>
						</div>
						<div class="ovo-card p-3">
							<div class="ovo-stat">
								<div class="label">Active scrapers</div>
								<div class="value">
									<?php echo count(array_filter($mockScrapers, fn($s) => $s['enabled'])); ?>
								</div>
								<div class="text-muted small">Next run in 50m</div>
							</div>
						</div>
						<div class="ovo-card p-3">
							<div class="ovo-stat">
								<div class="label">Publishing</div>
								<div class="value">Tableau</div>
								<div class="text-muted small">Last publish: 2025-12-02 18:00</div>
							</div>
						</div>
					</div>

					<div class="row g-3">
						<div class="col-12 col-lg-6">
							<div class="ovo-card p-3 h-100">
								<div class="d-flex align-items-center justify-content-between mb-2">
									<h5 class="mb-0">Recent transform jobs</h5>
									<span class="ovo-pill neutral"><i class="bi bi-activity"></i>Last 24h</span>
								</div>
								<ul class="list-unstyled small mb-0">
									<li class="pb-1">2025-12-03 12:50 — Transform batch #342 — <span class="badge badge-soft">Success</span></li>
									<li class="pb-1">2025-12-03 11:20 — Deduplication run — <span class="badge badge-soft">Success</span></li>
									<li>2025-12-03 10:01 — NLP classification — <span class="badge badge-soft">Warning</span></li>
								</ul>
							</div>
						</div>
						<div class="col-12 col-lg-6">
							<div class="ovo-card p-3 h-100">
								<div class="d-flex align-items-center justify-content-between mb-2">
									<h5 class="mb-0">Quality & actions</h5>
									<span class="ovo-pill warn"><i class="bi bi-exclamation-triangle"></i>Attention</span>
								</div>
								<ul class="small mb-3">
									<li>2 sources paused: Karihub manual, Portal C — review credentials</li>
									<li>Classification warnings: 3 titles need KBJI confirmation</li>
									<li>Exports: Daily Tableau extract scheduled 18:00</li>
								</ul>
								<div class="d-flex gap-2">
									<button class="btn btn-outline-secondary btn-sm">View paused sources</button>
									<button class="btn btn-outline-secondary btn-sm">Open warnings</button>
								</div>
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

					<div class="mt-4 ovo-card p-3">
						<div class="d-flex align-items-center justify-content-between mb-2">
							<h6 class="mb-0">Run history (last 3)</h6>
							<span class="ovo-pill neutral"><i class="bi bi-clock-history"></i>Staging</span>
						</div>
						<ul class="list-unstyled small mb-0">
							<li>2025-12-03 12:10 — JobPortal A — 180 rows — OK</li>
							<li>2025-12-03 09:00 — JobPortal B — 210 rows — OK</li>
							<li>2025-12-03 08:00 — Karihub Manual — 42 rows — Manual</li>
						</ul>
					</div>

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
					<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-3 gap-2">
						<div>
							<h2 class="h4 mb-1">Raw Database (staging)</h2>
							<div class="text-muted small">Search, filter, and inspect raw scrapes before transformation</div>
						</div>
						<div class="d-flex gap-2">
							<button class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i>Export CSV</button>
							<button class="btn btn-outline-secondary btn-sm" id="raw-reset"><i class="bi bi-funnel me-1"></i>Reset filters</button>
						</div>
					</div>

					<div class="ovo-card p-3 mb-3">
						<div class="row g-3">
							<div class="col-12 col-md-3">
								<div class="small text-muted">Total records</div>
								<div class="fw-semibold" id="raw-total-count"><?php echo count($mockRawRecords); ?></div>
							</div>
							<div class="col-12 col-md-3">
								<div class="small text-muted">Filtered</div>
								<div class="fw-semibold" id="raw-filtered-count"><?php echo count($mockRawRecords); ?></div>
							</div>
							<div class="col-12 col-md-3">
								<div class="small text-muted">Avg completeness</div>
								<div class="fw-semibold" id="raw-completeness">–</div>
							</div>
							<div class="col-12 col-md-3">
								<div class="small text-muted">Warnings</div>
								<div class="fw-semibold">Salary missing: 2</div>
							</div>
						</div>
					</div>

					<div class="ovo-card p-3 mb-3">
						<div class="row g-3">
							<div class="col-12 col-lg-4">
								<label class="form-label small text-muted mb-1">Search</label>
								<input id="raw-search" class="form-control" placeholder="Title, company, location">
							</div>
							<div class="col-6 col-lg-2">
								<label class="form-label small text-muted mb-1">Source</label>
								<select id="raw-source" class="form-select">
									<option value="">All sources</option>
									<option>JobPortal A</option>
									<option>JobPortal B</option>
								</select>
							</div>
							<div class="col-6 col-lg-2">
								<label class="form-label small text-muted mb-1">Status</label>
								<select id="raw-status" class="form-select">
									<option value="">All</option>
									<option value="new">New</option>
									<option value="parsed">Parsed</option>
									<option value="flagged">Flagged</option>
								</select>
							</div>
							<div class="col-6 col-lg-2">
								<label class="form-label small text-muted mb-1">Posted from</label>
								<input id="raw-date-from" type="date" class="form-control">
							</div>
							<div class="col-6 col-lg-2">
								<label class="form-label small text-muted mb-1">Posted to</label>
								<input id="raw-date-to" type="date" class="form-control">
							</div>
						</div>
					</div>

					<div class="table-responsive border rounded">
						<table class="table table-sm mb-0" id="raw-table">
							<thead class="table-light">
								<tr>
									<th>ID</th>
									<th>Source</th>
									<th>Company</th>
									<th>Title</th>
									<th>Location</th>
									<th>Posted</th>
									<th>Fetched</th>
									<th>Salary</th>
									<th>Dup score</th>
									<th>Status</th>
									<th>Quality</th>
									<th class="text-end">Actions</th>
								</tr>
							</thead>
							<tbody id="raw-tbody">
								<?php foreach ($mockRawRecords as $r): ?>
									<tr data-id="<?php echo htmlspecialchars($r['id']); ?>">
										<td class="small"><?php echo htmlspecialchars($r['id']); ?></td>
										<td class="small"><?php echo htmlspecialchars($r['source']); ?></td>
										<td class="small"><?php echo htmlspecialchars($r['company']); ?></td>
										<td class="small"><?php echo htmlspecialchars($r['title']); ?></td>
										<td class="small"><?php echo htmlspecialchars($r['location']); ?></td>
										<td class="small"><?php echo htmlspecialchars($r['posted_at']); ?></td>
										<td class="small"><?php echo htmlspecialchars($r['fetched_at']); ?></td>
										<td class="small"><?php echo $r['salary'] !== '' ? htmlspecialchars($r['salary']) : '—'; ?></td>
										<td class="small"><?php echo htmlspecialchars($r['duplicate_score']); ?>%</td>
										<td class="small">
											<span class="badge rounded-pill text-bg-<?php echo $r['status'] === 'flagged' ? 'warning' : ($r['status'] === 'parsed' ? 'success' : 'secondary'); ?>">
												<?php echo htmlspecialchars($r['status']); ?>
											</span>
										</td>
										<td class="small">
											<div class="d-flex align-items-center gap-1">
												<div class="ovo-progress" style="width: 80px;"><div style="width: <?php echo $r['completeness']; ?>%;"></div></div>
												<span class="text-muted"><?php echo $r['completeness']; ?>%</span>
											</div>
										</td>
										<td class="small text-end">
											<div class="btn-group btn-group-sm">
												<button class="btn btn-outline-secondary" data-action="view-raw">View raw</button>
												<button class="btn btn-outline-secondary" data-action="flag">Flag</button>
												<button class="btn btn-outline-secondary" data-action="send-transform">Send</button>
											</div>
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
					<div class="ovo-card p-3 mb-3">
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

					<div class="ovo-card p-3 mb-3">
						<div class="d-flex align-items-center justify-content-between mb-2">
							<h6 class="mb-0">Recent transform jobs</h6>
							<span class="ovo-pill neutral"><i class="bi bi-activity"></i>Last 24h</span>
						</div>
						<div class="table-responsive">
							<table class="table table-sm mb-0">
								<thead class="table-light">
									<tr>
										<th>ID</th>
										<th>Started</th>
										<th>Duration</th>
										<th>Status</th>
										<th>In</th>
										<th>Out</th>
										<th>Errors</th>
										<th class="text-end">Actions</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($mockTransformJobs as $job): ?>
										<tr>
											<td class="small"><?php echo htmlspecialchars($job['id']); ?></td>
											<td class="small"><?php echo htmlspecialchars($job['started_at']); ?></td>
											<td class="small"><?php echo htmlspecialchars($job['duration']); ?></td>
											<td class="small">
												<span class="badge rounded-pill text-bg-<?php echo $job['status'] === 'success' ? 'success' : ($job['status'] === 'warning' ? 'warning' : 'secondary'); ?>">
													<?php echo htmlspecialchars($job['status']); ?>
												</span>
											</td>
											<td class="small"><?php echo htmlspecialchars($job['in_count']); ?></td>
											<td class="small"><?php echo htmlspecialchars($job['out_count']); ?></td>
											<td class="small"><?php echo htmlspecialchars($job['errors']); ?></td>
											<td class="small text-end">
												<div class="btn-group btn-group-sm">
													<button class="btn btn-outline-secondary" disabled>Logs</button>
													<button class="btn btn-outline-secondary" disabled>Rerun</button>
												</div>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
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
									<th>KBJI</th>
									<th>Location</th>
									<th>Conf.</th>
									<th>Review</th>
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
										<td class="small"><?php echo htmlspecialchars($c['kbji']); ?></td>
										<td class="small"><?php echo htmlspecialchars($c['normalized_location']); ?></td>
										<td class="small"><?php echo htmlspecialchars($c['confidence']); ?>%</td>
										<td class="small">
											<?php if ($c['review_needed']): ?>
												<span class="badge rounded-pill text-bg-warning">Needs review</span>
											<?php else: ?>
												<span class="badge rounded-pill text-bg-success">OK</span>
											<?php endif; ?>
										</td>
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
					<div class="ovo-card p-3 mb-3">
						<div class="row g-3">
							<div class="col-12 col-md-4">
								<div class="small text-muted">Last snapshot</div>
								<div class="fw-semibold">2025-12-02 18:00</div>
								<div class="text-muted small">Rows: 12,430 · Size: 18 MB</div>
							</div>
							<div class="col-12 col-md-4">
								<div class="small text-muted">Next publish</div>
								<div class="fw-semibold">Today 18:00</div>
								<div class="text-muted small">Destination: Tableau, S3</div>
							</div>
							<div class="col-12 col-md-4">
								<div class="small text-muted">API availability</div>
								<div class="fw-semibold">v1 / v2 (beta)</div>
								<div class="text-muted small">Rate limit: 200 rpm</div>
							</div>
						</div>
					</div>
					<div class="row g-3">
						<div class="col-12 col-md-6">
							<div class="border rounded p-3 h-100">
								<h6 class="mb-2">Tools / Dashboard</h6>
								<p class="small text-muted">Link: Tableau / Looker / internal BI. Provide scheduled extracts and row-level security for public vs internal dashboards.</p>
								<div class="d-flex gap-2">
									<button class="btn btn-primary btn-sm" disabled>Open Tableau</button>
									<button class="btn btn-outline-secondary btn-sm" disabled>Download extract</button>
								</div>
							</div>
						</div>
						<div class="col-12 col-md-6">
							<div class="border rounded p-3 h-100">
								<h6 class="mb-2">OVO Report Builder</h6>
								<p class="small text-muted">Create scheduled OVO public reports (CSV / PDF) and set publication cadence.</p>
								<div class="d-flex gap-2">
									<button class="btn btn-outline-secondary btn-sm" disabled>New scheduled report</button>
									<button class="btn btn-outline-secondary btn-sm" disabled>History</button>
								</div>
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
					<div class="ovo-card p-3 mb-3">
						<div class="row g-3">
							<div class="col-12 col-md-4">
								<label class="form-label small text-muted mb-1">Source</label>
								<select class="form-select form-select-sm" disabled>
									<option>All</option>
									<option>Scraper</option>
									<option>Transform</option>
									<option>Publish</option>
									<option>Auth</option>
								</select>
							</div>
							<div class="col-6 col-md-2">
								<label class="form-label small text-muted mb-1">Level</label>
								<select class="form-select form-select-sm" disabled>
									<option>All</option>
									<option>Info</option>
									<option>Warn</option>
									<option>Error</option>
								</select>
							</div>
							<div class="col-6 col-md-2">
								<label class="form-label small text-muted mb-1">From</label>
								<input type="date" class="form-control form-control-sm" disabled>
							</div>
							<div class="col-6 col-md-2">
								<label class="form-label small text-muted mb-1">To</label>
								<input type="date" class="form-control form-control-sm" disabled>
							</div>
							<div class="col-12 col-md-2 d-flex align-items-end">
								<button class="btn btn-outline-secondary btn-sm w-100" disabled>Export CSV</button>
							</div>
						</div>
					</div>
					<div class="border rounded p-3">
						<p class="small text-muted mb-2">Search logs, filter by source (scraper, transform, publish), and export for audits. This view is critical for anti-corruption transparency — keep immutable logs and RBAC for access.</p>
						<ul class="small mb-0">
							<li>2025-12-03 12:50 — transform — job tj-342 — status=success</li>
							<li>2025-12-03 12:12 — scraper — JobPortal A — fetched=180 — status=ok</li>
							<li>2025-12-03 10:02 — transform — job tj-340 — warnings=5 (classification)</li>
						</ul>
					</div>
				</div>
			</section>
		</main>
	</div>
</div>

<!-- Raw record modal -->
<div class="modal fade" id="rawModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-lg modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<div>
					<div class="small text-muted">Raw record</div>
					<h6 class="modal-title mb-0" id="rawModalTitle">—</h6>
				</div>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<div class="mb-3">
					<div class="small text-muted">Source</div>
					<div id="rawModalSource" class="fw-semibold">—</div>
				</div>
				<div class="row g-3 mb-3">
					<div class="col-md-6">
						<div class="small text-muted">Company</div>
						<div id="rawModalCompany" class="fw-semibold">—</div>
					</div>
					<div class="col-md-6">
						<div class="small text-muted">Location</div>
						<div id="rawModalLocation" class="fw-semibold">—</div>
					</div>
					<div class="col-md-4">
						<div class="small text-muted">Posted at</div>
						<div id="rawModalPosted">—</div>
					</div>
					<div class="col-md-4">
						<div class="small text-muted">Fetched at</div>
						<div id="rawModalFetched">—</div>
					</div>
					<div class="col-md-4">
						<div class="small text-muted">Salary</div>
						<div id="rawModalSalary">—</div>
					</div>
				</div>
				<div class="mb-3">
					<div class="small text-muted">Flags / Notes</div>
					<div id="rawModalFlags">—</div>
				</div>
				<div class="mb-3">
					<div class="small text-muted">Raw HTML snippet</div>
					<pre class="bg-light p-3 rounded small" id="rawModalSnippet" style="white-space: pre-wrap;">—</pre>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
				<button type="button" class="btn btn-primary btn-sm">Send to transform</button>
			</div>
		</div>
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
	const rawRecords = <?php echo json_encode($mockRawRecords); ?>;
	let rawFiltered = [...rawRecords];

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

	// -------- Raw Records UI --------
	const rawEls = {
		search: document.getElementById('raw-search'),
		source: document.getElementById('raw-source'),
		status: document.getElementById('raw-status'),
		dateFrom: document.getElementById('raw-date-from'),
		dateTo: document.getElementById('raw-date-to'),
		reset: document.getElementById('raw-reset'),
		tbody: document.getElementById('raw-tbody'),
		totalCount: document.getElementById('raw-total-count'),
		filteredCount: document.getElementById('raw-filtered-count'),
		completeness: document.getElementById('raw-completeness'),
		modal: document.getElementById('rawModal'),
		modalTitle: document.getElementById('rawModalTitle'),
		modalSource: document.getElementById('rawModalSource'),
		modalCompany: document.getElementById('rawModalCompany'),
		modalLocation: document.getElementById('rawModalLocation'),
		modalPosted: document.getElementById('rawModalPosted'),
		modalFetched: document.getElementById('rawModalFetched'),
		modalSalary: document.getElementById('rawModalSalary'),
		modalFlags: document.getElementById('rawModalFlags'),
		modalSnippet: document.getElementById('rawModalSnippet'),
	};

	let rawModalInstance = (window.bootstrap && rawEls.modal) ? new window.bootstrap.Modal(rawEls.modal) : null;

	function renderRawTable(list) {
		if (!rawEls.tbody) return;
		rawEls.tbody.innerHTML = list.map(r => {
			const statusColor = r.status === 'flagged' ? 'warning' : (r.status === 'parsed' ? 'success' : 'secondary');
			const salary = r.salary ? escapeHtml(r.salary) : '—';
			return `
				<tr data-id="${escapeAttr(r.id)}">
					<td class="small">${escapeHtml(r.id)}</td>
					<td class="small">${escapeHtml(r.source)}</td>
					<td class="small">${escapeHtml(r.company)}</td>
					<td class="small">${escapeHtml(r.title)}</td>
					<td class="small">${escapeHtml(r.location)}</td>
					<td class="small">${escapeHtml(r.posted_at)}</td>
					<td class="small">${escapeHtml(r.fetched_at)}</td>
					<td class="small">${salary}</td>
					<td class="small"><span class="badge rounded-pill text-bg-${statusColor}">${escapeHtml(r.status)}</span></td>
					<td class="small">
						<div class="d-flex align-items-center gap-1">
							<div class="ovo-progress" style="width: 80px;"><div style="width: ${r.completeness}%;"></div></div>
							<span class="text-muted">${r.completeness}%</span>
						</div>
					</td>
					<td class="small text-end">
						<div class="btn-group btn-group-sm">
							<button class="btn btn-outline-secondary" data-action="view-raw">View raw</button>
							<button class="btn btn-outline-secondary" data-action="flag">Flag</button>
							<button class="btn btn-outline-secondary" data-action="send-transform">Send</button>
						</div>
					</td>
				</tr>
			`;
		}).join('');
	}

	function avgCompleteness(list) {
		if (!list.length) return 0;
		return Math.round(list.reduce((sum, r) => sum + (r.completeness || 0), 0) / list.length);
	}

	function applyRawFilters() {
		const term = (rawEls.search?.value || '').toLowerCase();
		const source = rawEls.source?.value || '';
		const status = rawEls.status?.value || '';
		const from = rawEls.dateFrom?.value ? new Date(rawEls.dateFrom.value) : null;
		const to = rawEls.dateTo?.value ? new Date(rawEls.dateTo.value) : null;

		rawFiltered = rawRecords.filter(r => {
			const matchesTerm = term
				? [r.title, r.company, r.location].some(v => String(v).toLowerCase().includes(term))
				: true;
			const matchesSource = source ? r.source === source : true;
			const matchesStatus = status ? r.status === status : true;
			const posted = new Date(r.posted_at);
			const matchesFrom = from ? posted >= from : true;
			const matchesTo = to ? posted <= to : true;
			return matchesTerm && matchesSource && matchesStatus && matchesFrom && matchesTo;
		});

		renderRawTable(rawFiltered);
		updateRawStats();
	}

	function updateRawStats() {
		if (rawEls.totalCount) rawEls.totalCount.textContent = rawRecords.length;
		if (rawEls.filteredCount) rawEls.filteredCount.textContent = rawFiltered.length;
		if (rawEls.completeness) rawEls.completeness.textContent = rawFiltered.length ? `${avgCompleteness(rawFiltered)}%` : '–';
	}

	function resetRawFilters() {
		if (rawEls.search) rawEls.search.value = '';
		if (rawEls.source) rawEls.source.value = '';
		if (rawEls.status) rawEls.status.value = '';
		if (rawEls.dateFrom) rawEls.dateFrom.value = '';
		if (rawEls.dateTo) rawEls.dateTo.value = '';
		applyRawFilters();
	}

	function showRawModal(id) {
		if (!rawModalInstance && window.bootstrap && rawEls.modal) {
			rawModalInstance = new window.bootstrap.Modal(rawEls.modal);
		}
		if (!rawModalInstance) return;
		const rec = rawRecords.find(r => r.id === id);
		if (!rec) return;
		rawEls.modalTitle.textContent = `${rec.title} (${rec.id})`;
		rawEls.modalSource.textContent = `${rec.source} · ${rec.source_id || '-'}`;
		rawEls.modalCompany.textContent = `${rec.company || '-'}`;
		rawEls.modalLocation.textContent = `${rec.location || '-'}`;
		rawEls.modalPosted.textContent = rec.posted_at || '-';
		rawEls.modalFetched.textContent = rec.fetched_at || '-';
		rawEls.modalSalary.textContent = rec.salary || '—';
		rawEls.modalFlags.textContent = rec.flags || rec.quality_notes || '—';
		rawEls.modalSnippet.textContent = rec.raw_html_snippet || '—';
		rawModalInstance.show();
	}

	if (rawEls.tbody) {
		rawEls.tbody.addEventListener('click', function(e){
			const btn = e.target.closest('button[data-action]');
			if (!btn) return;
			const row = btn.closest('tr[data-id]');
			const id = row ? row.getAttribute('data-id') : null;
			const action = btn.getAttribute('data-action');
			if (action === 'view-raw' && id) {
				showRawModal(id);
			} else if (action === 'flag') {
				alert('Flagging stub.');
			} else if (action === 'send-transform') {
				alert('Send to transform stub.');
			}
		});
	}

	['input', 'change'].forEach(ev => {
		if (rawEls.search) rawEls.search.addEventListener(ev, applyRawFilters);
	});
	if (rawEls.source) rawEls.source.addEventListener('change', applyRawFilters);
	if (rawEls.status) rawEls.status.addEventListener('change', applyRawFilters);
	if (rawEls.dateFrom) rawEls.dateFrom.addEventListener('change', applyRawFilters);
	if (rawEls.dateTo) rawEls.dateTo.addEventListener('change', applyRawFilters);
	if (rawEls.reset) rawEls.reset.addEventListener('click', resetRawFilters);

	// Initial renders
	renderRawTable(rawFiltered);
	updateRawStats();

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


