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
?>
<!DOCTYPE html>
<html>
<head>
    <title>Kemitraan Booked Calendar</title>
    <style>
        table.calendar { border-collapse: collapse; width: 100%; }
        table.calendar th, table.calendar td { border: 1px solid #aaa; width: 14.2%; vertical-align: top; min-height: 80px; padding: 4px; }
        table.calendar th { background: #8bc34a; color: #fff; }
        .activity { margin: 2px 0; padding: 2px 4px; border-radius: 4px; font-size: 0.95em; }
        .activity.Walk-in\ Interview { color: #1976d2; }
        .activity.Pendidikan\ Pasar\ Kerja { color: #c62828; }
        .activity.Talenta\ Muda { color: #388e3c; }
        .activity.Job\ Fair { color: #fbc02d; }
        .activity.Konsultasi\ Informasi\ Pasar\ Kerja { color: #6d4c41; }
        .date-num { font-weight: bold; }
    </style>
</head>
<body>
    <h2>Kemitraan Booked Calendar - <?php echo date('F Y', strtotime($first_day)); ?></h2>
    <table class="calendar">
        <tr>
            <th>Sunday</th><th>Monday</th><th>Tuesday</th><th>Wednesday</th>
            <th>Thursday</th><th>Friday</th><th>Saturday</th>
        </tr>
        <?php foreach ($calendar as $week): ?>
        <tr>
            <?php foreach ($week as $date): ?>
            <td>
                <?php if ($date): ?>
                    <div class="date-num"><?php echo date('j', strtotime($date)); ?></div>
                    <?php if (!empty($activities[$date])): ?>
                        <?php foreach ($activities[$date] as $act): ?>
                            <div class="activity <?php echo str_replace(' ', '\\ ', $act['partnership_type']); ?>">
                                <strong><?php echo htmlspecialchars($act['partnership_type']); ?></strong><br>
                                <?php echo htmlspecialchars($act['institution_name']); ?><br>
                                <small><?php echo htmlspecialchars($act['needs']); ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
<?php $conn->close(); ?>
