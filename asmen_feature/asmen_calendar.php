<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../access_helper.php';
require_once __DIR__ . '/asmen_lib.php';

if (!(current_user_can('asmen_view_calendar') || current_user_can('asmen_manage_assets'))) { http_response_code(403); echo 'Forbidden'; exit; }

// Fetch upcoming services for the next 90 days
$stmt = $conn->prepare('SELECT id, nama_barang, kode_barang, nup, no_polisi, nama_satker, next_service_date FROM asmen_assets WHERE next_service_date IS NOT NULL AND next_service_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND next_service_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) ORDER BY next_service_date ASC');
$stmt->execute();
$res = $stmt->get_result();
$events = [];
while ($r = $res->fetch_assoc()) {
	$events[] = [
		'title' => ($r['nama_barang'] ?: 'Asset') . ' #' . $r['id'],
		'date' => $r['next_service_date'],
		'url' => 'asmen_asset.php?id=' . $r['id']
	];
}
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>AsMen Calendar</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/main.min.css" rel="stylesheet">
	<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
</head>
<body class="bg-light">
<?php include '../navbar.php'; ?>
<div class="container py-4">
	<div class="d-flex justify-content-between align-items-center mb-3">
		<h2 class="mb-0">AsMen - Service Calendar</h2>
	</div>
	<div class="card">
		<div class="card-body">
			<div id="calendar"></div>
		</div>
	</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
	var calendarEl = document.getElementById('calendar');
	var calendar = new FullCalendar.Calendar(calendarEl, {
		initialView: 'dayGridMonth',
		headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' },
		events: <?php echo json_encode($events); ?>,
		eventClick: function(info) { if (info.event.url) { info.jsEvent.preventDefault(); window.location.href = info.event.url; } }
	});
	calendar.render();
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


