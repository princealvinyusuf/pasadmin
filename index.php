<?php
// Simple admin welcome landing page
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Welcome | Job Admin</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
	<!-- Navigation Bar -->
	<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
		<div class="container">
			<a class="navbar-brand" href="job_dashboard.html"><i class="bi bi-briefcase me-2"></i>Job Admin</a>
			<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
				<span class="navbar-toggler-icon"></span>
			</button>
			<div class="collapse navbar-collapse" id="navbarNav">
				<ul class="navbar-nav ms-auto">
					<li class="nav-item dropdown">
						<a class="nav-link dropdown-toggle" href="#" id="dashboardDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
							Dashboard
						</a>
						<ul class="dropdown-menu" aria-labelledby="dashboardDropdown">
							<li><a class="dropdown-item" href="job_dashboard.html">Dashboard Jobs</a></li>
							<li><a class="dropdown-item" href="job_seeker_dashboard.html">Dashboard Job Seekers</a></li>
						</ul>
					</li>
					<li class="nav-item dropdown">
						<a class="nav-link dropdown-toggle" href="#" id="masterDataDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
							Master Data
						</a>
						<ul class="dropdown-menu" aria-labelledby="masterDataDropdown">
							<li><a class="dropdown-item" href="job_dashboard.html">Jobs</a></li>
							<li><a class="dropdown-item" href="job_seeker_dashboard.html">Job Seekers</a></li>
						</ul>
					</li>
					<li class="nav-item dropdown">
						<a class="nav-link dropdown-toggle" href="#" id="cleansingDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
							Cleansing
						</a>
						<ul class="dropdown-menu" aria-labelledby="cleansingDropdown">
							<li><a class="dropdown-item" href="cleansing_snaphunt.php">Snaphunt</a></li>
							<li><a class="dropdown-item" href="cleansing_makaryo.php">Makaryo</a></li>
						</ul>
					</li>
					<li class="nav-item dropdown">
						<a class="nav-link dropdown-toggle" href="#" id="settingsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
							Settings
						</a>
						<ul class="dropdown-menu" aria-labelledby="settingsDropdown">
							<li><a class="dropdown-item" href="chart_settings.php">Chart Settings</a></li>
							<li><a class="dropdown-item" href="contribution_settings.php">Contribution Settings</a></li>
							<li><a class="dropdown-item" href="information_settings.php">Information Settings</a></li>
							<li><a class="dropdown-item" href="news_settings.php">News Settings</a></li>
							<li><a class="dropdown-item" href="services_settings.php">Services Settings</a></li>
							<li><a class="dropdown-item" href="statistics_settings.php">Statistics Settings</a></li>
							<li><a class="dropdown-item" href="testimonials_settings.php">Testimonial Settings</a></li>
							<li><a class="dropdown-item" href="top_list_settings.php">Top List Settings</a></li>
							<li><hr class="dropdown-divider"></li>
							<li><a class="dropdown-item" href="agenda_settings.php">Agenda Settings</a></li>
							<li><a class="dropdown-item" href="job_fair_settings.php">Job Fair Settings</a></li>
							<li><a class="dropdown-item" href="virtual_karir_service_settings.php">Virtual Karir Service Settings</a></li>
							<li><hr class="dropdown-divider"></li>
							<li><a class="dropdown-item" href="mitra_kerja_settings.php">Mitra Kerja Settings</a></li>
							<li><a class="dropdown-item" href="kemitraan_submission.php">Mitra Kerja Submission</a></li>
							<li><a class="dropdown-item" href="kemitraan_booked.php">Kemitraan Booked</a></li>
							<li><a class="dropdown-item" href="pasker_room_settings.php">Pasker Room Settings</a></li>
							<li><hr class="dropdown-divider"></li>
							<li><a class="dropdown-item" href="cron_settings.php">Other Settings</a></li>
						</ul>
					</li>
					<li class="nav-item">
						<a class="nav-link" href="extensions.php">Extensions</a>
					</li>
				</ul>
			</div>
		</div>
	</nav>
	<!-- End Navigation Bar -->

	<div class="d-flex align-items-center" style="min-height: calc(100vh - 56px);">
		<div class="container">
		<div class="row justify-content-center">
			<div class="col-12 col-md-8 col-lg-6">
				<div class="card shadow-sm">
					<div class="card-body text-center p-5">
						<div class="display-4 mb-3">🎉</div>
						<h1 class="h3 mb-2">Welcome, Admin!</h1>
						<p class="text-muted mb-4">Use the button below to open your job dashboard.</p>
						<a href="job_dashboard.html" class="btn btn-primary btn-lg">
							<i class="bi bi-speedometer2 me-1"></i>
							Open Job Dashboard
						</a>
						<div class="mt-3">
							<a class="text-decoration-none" href="job_dashboard.html">Go to Jobs</a>
						</div>
					</div>
				</div>
			</div>
		</div>
		</div>
	</div>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


