<?php
include "init.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index?error=invalid_id");
    exit;
}

$id = intval($_GET['id']);
if ($id <= 0) {
    header("Location: index?error=invalid_id");
    exit;
}

$stmt = $conn->prepare("DELETE FROM tahapan_kerjasama WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $stmt->close();
    header("Location: index?success=deleted");
    exit;
} else {
    $stmt->close();
    header("Location: index?error=not_found");
    exit;
}


