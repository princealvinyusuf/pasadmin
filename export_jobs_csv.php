<?php
include 'db.php';
require 'auth.php';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="jobs_backup.csv"');

$output = fopen('downloads/jobs_backup.csv', 'w');
$result = $conn->query("SELECT * FROM jobs");
if ($row = $result->fetch_assoc()) {
    fputcsv($output, array_keys($row)); // headers
    fputcsv($output, $row);
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
}
fclose($output);
echo "Exported to downloads/jobs_backup.csv\n";
?>
