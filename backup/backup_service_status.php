<?php
$status_file = __DIR__ . '/backup_service.status';

if (file_exists($status_file)) {
    echo trim(file_get_contents($status_file));
} else {
    echo 'Stopped';
}
?>


