<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Get current month and year
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Get first and last day of the month
$first_day = date('Y-m-01', strtotime("$year-$month-01"));
$last_day = date('Y-m-t', strtotime($first_day));

// Fetch all booked dates and activities for this month
$sql = "
    SELECT 
        bd.booked_date, 
        k.institution_name, 
        k.partnership_type, 
        k.needs
    FROM booked_date bd
    JOIN kemitraan k ON bd.kemitraan_id = k.id
    WHERE bd.booked_date BETWEEN '$first_day' AND '$last_day'
    ORDER BY bd.booked_date
";
$result = $conn->query($sql);

$activities = [];
while ($row = $result->fetch_assoc()) {
    $date = $row['booked_date'];
    $activities[$date][] = $row;
}

// Build calendar grid
$first_day_of_week = date('w', strtotime($first_day)); // 0=Sunday
$days_in_month = date('t', strtotime($first_day));

$calendar = [];
$week = [];
// Fill initial empty days
for ($i = 0; $i < $first_day_of_week; $i++) {
    $week[] = '';
}
for ($day = 1; $day <= $days_in_month; $day++) {
    $date_str = sprintf('%04d-%02d-%02d', $year, $month, $day);
    $week[] = $date_str;
    if (count($week) == 7) {
        $calendar[] = $week;
        $week = [];
    }
}
if (count($week) > 0) {
    while (count($week) < 7) $week[] = '';
    $calendar[] = $week;
}

// Add navigation logic
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) { $next_month = 1; $next_year++; }
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Kemitraan Booked Calendar</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f7fa; margin: 0; padding: 0; }
        .calendar-container { max-width: 900px; margin: 30px auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); padding: 24px; }
        .calendar-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .calendar-nav a { text-decoration: none; color: #388e3c; font-weight: bold; font-size: 1.2em; }
        .calendar-nav span { font-size: 1.3em; font-weight: 600; color: #333; }
        table.calendar { border-collapse: collapse; width: 100%; background: #fff; }
        table.calendar th, table.calendar td { border: 1px solid #e0e0e0; width: 14.2%; vertical-align: top; min-height: 90px; padding: 6px 4px; }
        table.calendar th { background: #8bc34a; color: #fff; font-size: 1.1em; }
        table.calendar td { background: #fafbfc; position: relative; }
        .today { background: #e3f2fd !important; border: 2px solid #1976d2 !important; }
        .activity { margin: 6px 0; padding: 6px 8px; border-radius: 6px; font-size: 0.98em; background: #f1f8e9; box-shadow: 0 1px 2px rgba(0,0,0,0.03);}
        .activity.Walk-in\ Interview { background: #e3f2fd; color: #1976d2; }
        .activity.Pendidikan\ Pasar\ Kerja { background: #ffebee; color: #c62828; }
        .activity.Talenta\ Muda { background: #e8f5e9; color: #388e3c; }
        .activity.Job\ Fair { background: #fffde7; color: #fbc02d; }
        .activity.Konsultasi\ Informasi\ Pasar\ Kerja { background: #efebe9; color: #6d4c41; }
        .date-num { font-weight: bold; font-size: 1.1em; margin-bottom: 4px; display: block; }
        @media (max-width: 700px) {
            .calendar-container { padding: 6px; }
            table.calendar th, table.calendar td { font-size: 0.95em; min-height: 60px; padding: 2px 1px; }
        }
        /* Tooltip styling */
        .activity[title] { position: relative; cursor: pointer; }
        .activity[title]:hover:after {
            content: attr(title);
            position: absolute;
            left: 0; top: 100%;
            background: #333; color: #fff; padding: 6px 10px; border-radius: 6px;
            white-space: pre-line; font-size: 0.95em; z-index: 10; min-width: 180px;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.html"><i class="bi bi-briefcase me-2"></i>Job Admin</a>
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
                            <li><a class="dropdown-item" href="index.html">Dashboard Jobs</a></li>
                            <li><a class="dropdown-item" href="job_seeker_dashboard.html">Dashboard Job Seekers</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="masterDataDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Master Data
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="masterDataDropdown">
                            <li><a class="dropdown-item" href="jobs.html">Jobs</a></li>
                            <li><a class="dropdown-item" href="job_seekers.html">Job Seekers</a></li>
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
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="cron_settings.php">Other Settings</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="extensions.html">Extensions</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <!-- End Navigation Bar -->
<div class="calendar-container">
    <div class="calendar-nav">
        <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>">&laquo; Prev</a>
        <span><?php echo date('F Y', strtotime($first_day)); ?></span>
        <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>">Next &raquo;</a>
    </div>
    <table class="calendar">
        <tr>
            <th>Sunday</th><th>Monday</th><th>Tuesday</th><th>Wednesday</th>
            <th>Thursday</th><th>Friday</th><th>Saturday</th>
        </tr>
        <?php foreach ($calendar as $week): ?>
        <tr>
            <?php foreach ($week as $date): ?>
            <?php
                $is_today = ($date && $date == $today);
            ?>
            <td class="<?php echo $is_today ? 'today' : ''; ?>">
                <?php if ($date): ?>
                    <span class="date-num"><?php echo date('j', strtotime($date)); ?></span>
                    <?php if (!empty($activities[$date])): ?>
                        <?php foreach ($activities[$date] as $act): ?>
                            <div class="activity <?php echo str_replace(' ', '\\ ', $act['partnership_type']); ?>"
                                 title="Instansi: <?php echo htmlspecialchars($act['institution_name']); ?>&#10;Kebutuhan: <?php echo htmlspecialchars($act['needs']); ?>">
                                <strong><?php echo htmlspecialchars($act['partnership_type']); ?></strong><br>
                                <?php echo htmlspecialchars($act['institution_name']); ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
