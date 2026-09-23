<?php

require_once __DIR__ . '/../auth_guard.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../access_helper.php';
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/lib.php';

jpa_ensure_schema($conn);

