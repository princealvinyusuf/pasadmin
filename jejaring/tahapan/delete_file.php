<?php
include "init.php";
error_reporting(E_ALL);
ini_set('display_errors', 1);

$id = $_GET['id'] ?? null;
$fileKey = $_GET['file_key'] ?? null;

$allowedFileKeys = ['file1', 'file2', 'file3'];
if (!$id || !is_numeric($id) || !in_array($fileKey, $allowedFileKeys)) {
    header("Location: index.php?error=invalid_request");
    exit();
}
$id = intval($id);

$stmt = $conn->prepare("SELECT `$fileKey` FROM tahapan_kerjasama WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if ($data && !empty($data[$fileKey])) {
    $fileName = $data[$fileKey];
    $filePath = __DIR__ . '/uploads/' . $fileName;

    if (file_exists($filePath)) {
        @unlink($filePath);
    }
}

$stmt_update = $conn->prepare("UPDATE tahapan_kerjasama SET `$fileKey` = NULL WHERE id = ?");
$stmt_update->bind_param("i", $id);

if ($stmt_update->execute()) {
    header("Location: index.php?success=file_deleted");
    exit();
} else {
    header("Location: index.php?error=db_update_failed");
    exit();
}


