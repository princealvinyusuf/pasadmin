<?php
// Simple admin welcome landing page
require_once __DIR__ . '/auth_guard.php';
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
	<?php include 'navbar.php'; ?>
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
						<a href="index.php" class="btn btn-primary btn-lg">
							<i class="bi bi-speedometer2 me-1"></i>
							Open Job Dashboard
						</a>
						<div class="mt-3">
							<a class="text-decoration-none" href="index.php">Go to Jobs</a>
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


