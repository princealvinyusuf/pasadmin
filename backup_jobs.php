<?php
require 'vendor/autoload.php'; // Make sure path is correct

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// DB connection
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'job_admin';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Fetch data
$result = $conn->query("SELECT * FROM jobs");
if (!$result) {
    die('Query failed: ' . $conn->error);
}

// Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Add headers
$fields = $result->fetch_fields();
$col = 'A';
foreach ($fields as $field) {
    $sheet->setCellValue($col . '1', $field->name);
    $col++;
}

// Add rows
$rowNum = 2;
while ($row = $result->fetch_assoc()) {
    $col = 'A';
    foreach ($row as $cell) {
        $sheet->setCellValue($col . $rowNum, $cell);
        $col++;
    }
    $rowNum++;
}

// Save to file
$savePath = __DIR__ . '/downloads/jobs_backup.xlsx';
$writer = new Xlsx($spreadsheet);
$writer->save($savePath);

echo "✅ Backup completed: $savePath\n";

$conn->close();
?>
