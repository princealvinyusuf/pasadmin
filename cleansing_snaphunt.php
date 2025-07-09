<?php
function detect_delimiter($filePath) {
    $handle = fopen($filePath, 'r');
    $line = fgets($handle);
    fclose($handle);
    $comma = substr_count($line, ',');
    $semicolon = substr_count($line, ';');
    return ($comma > $semicolon) ? ',' : ';';
}

function filter_csv($inputPath, $outputPath, $use_location = false, $use_upper = false, $remove_perks_tagline = false, $remove_duplicate = false, $add_posting_date = false, $add_expired_date = false, $add_provinsi = false, $add_kota = false, &$debug = null) {
    $delimiter = detect_delimiter($inputPath);
    $debug['delimiter'] = $delimiter;
    $in = fopen($inputPath, 'r');
    $out = fopen($outputPath, 'w');
    if (!$in || !$out) {
        $debug['file_error'] = 'Failed to open input or output file.';
        return false;
    }
    $header = fgetcsv($in, 0, $delimiter);
    $debug['header'] = $header;
    $remove_indexes = [];
    if ($remove_perks_tagline && $header) {
        foreach ($header as $i => $col) {
            if (strtolower(trim($col)) === 'perks' || strtolower(trim($col)) === 'tagline') {
                $remove_indexes[] = $i;
            }
        }
    }
    if ($header) {
        $header_out = $use_upper ? array_map('mb_strtoupper', $header) : $header;
        if ($remove_perks_tagline && $remove_indexes) {
            foreach (array_reverse($remove_indexes) as $idx) {
                unset($header_out[$idx]);
            }
            $header_out = array_values($header_out);
        }
        if ($add_posting_date) {
            $header_out[] = $use_upper ? mb_strtoupper('Posting Date') : 'Posting Date';
        }
        if ($add_expired_date) {
            $header_out[] = $use_upper ? mb_strtoupper('Expired Date') : 'Expired Date';
        }
        if ($add_provinsi) {
            $header_out[] = $use_upper ? mb_strtoupper('Provinsi') : 'Provinsi';
        }
        if ($add_kota) {
            $header_out[] = $use_upper ? mb_strtoupper('Kota') : 'Kota';
        }
        fputcsv($out, $header_out, $delimiter);
    }
    $locationIndex = array_search('location', $header);
    if ($use_location && $locationIndex === false) {
        fclose($in); fclose($out);
        $debug['location_error'] = 'No location column found in header.';
        return false;
    }
    $rowCount = 0;
    $seen = [];
    $today = date('Y-m-d');
    $expired = date('Y-m-d', strtotime('+1 month'));
    $provinsi_val = $use_upper ? mb_strtoupper('Indonesia') : 'Indonesia';
    $kota_val = $use_upper ? mb_strtoupper('Indonesia') : 'Indonesia';
    while (($row = fgetcsv($in, 0, $delimiter)) !== false) {
        $include = true;
        if ($use_location) {
            $include = isset($row[$locationIndex]) && stripos($row[$locationIndex], 'Indonesia') !== false;
        }
        if ($include) {
            $row_out = $use_upper ? array_map('mb_strtoupper', $row) : $row;
            if ($remove_perks_tagline && $remove_indexes) {
                foreach (array_reverse($remove_indexes) as $idx) {
                    unset($row_out[$idx]);
                }
                $row_out = array_values($row_out);
            }
            if ($add_posting_date) {
                $row_out[] = $today;
            }
            if ($add_expired_date) {
                $row_out[] = $expired;
            }
            if ($add_provinsi) {
                $row_out[] = $provinsi_val;
            }
            if ($add_kota) {
                $row_out[] = $kota_val;
            }
            // Remove duplicate rows if enabled
            if ($remove_duplicate) {
                $row_hash = md5(json_encode($row_out));
                if (isset($seen[$row_hash])) {
                    continue;
                }
                $seen[$row_hash] = true;
            }
            fputcsv($out, $row_out, $delimiter);
            $rowCount++;
        }
    }
    fclose($in);
    fclose($out);
    $debug['rows_written'] = $rowCount;
    return true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cleansing Snaphunt</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .navbar-brand {
            font-weight: bold;
            letter-spacing: 1px;
        }
        .bulk-upload-box {
            border: 2px dotted #0d6efd;
            border-radius: 10px;
            padding: 18px 18px 10px 18px;
            margin-bottom: 1.5rem;
            background: #f8faff;
        }
        body { font-family: Arial, sans-serif; margin: 0; background: #f4f6f8; }
        .big-card {
            border: 3px dotted #1976d2;
            border-radius: 18px;
            background: #e3f0fc;
            padding: 32px 24px 32px 24px;
            margin: 40px auto 40px auto;
            max-width: 1300px;
            box-shadow: 0 4px 24px rgba(25, 118, 210, 0.08);
            position: relative;
        }
        .section-title {
            text-align: center;
            font-size: 2rem;
            font-weight: bold;
            margin-top: 40px;
            margin-bottom: 30px;
            letter-spacing: 1px;
        }
        .upload-card-container {
            display: flex;
            justify-content: center;
            margin-bottom: 32px;
        }
        .upload-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            padding: 32px 28px 24px 28px;
            width: 90vw;
            max-width: 1200px;
            min-width: 320px;
            min-height: 100px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .upload-card-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 18px;
            color: #1976d2;
            letter-spacing: 0.5px;
        }
        .features-row {
            display: flex;
            justify-content: center;
            gap: 32px;
            margin-bottom: 32px;
            flex-wrap: wrap;
        }
        .feature-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            padding: 32px 28px 24px 28px;
            min-width: 220px;
            min-height: 160px;
            max-width: 320px;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            box-sizing: border-box;
        }
        .feature-card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 18px;
            color: #1976d2;
            letter-spacing: 0.5px;
        }
        .filter-option {
            margin-bottom: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        button[type=submit] {
            background: #1976d2;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 10px 22px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 24px;
        }
        button[type=submit]:hover {
            background: #125ea7;
        }
        .success { color: #388e3c; margin-top: 16px; }
        .error { color: #d32f2f; margin-top: 16px; }
        .debug {
            font-size: 0.9em;
            color: #555;
            background: #f9f9f9;
            padding: 8px;
            border-radius: 4px;
            margin-top: 10px;
            width: 100%;
            word-break: break-all;
        }
        @media (max-width: 900px) {
            .features-row { flex-direction: column; gap: 18px; align-items: center; }
            .feature-card { max-width: 90vw; }
        }
    </style>
</head>
<body class="bg-light">
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
    <div class="big-card">
        <div class="section-title">Cleansing Snaphunt</div>
        <form method="post" enctype="multipart/form-data">
            <div class="upload-card-container">
                <div class="upload-card">
                    <div class="upload-card-title">Upload CSV File</div>
                    <input type="file" name="csvfile" accept=".csv" required>
                </div>
            </div>
            <div class="features-row">
                <div class="feature-card">
                    <div class="feature-card-title">Filter Location</div>
                    <div class="filter-option">
                        <input type="checkbox" id="location" name="location" value="1" <?php if(isset($_POST['location'])) echo 'checked'; ?>>
                        <label for="location">Only include rows where location contains "Indonesia"</label>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-card-title">Capitalisation / Upper Case</div>
                    <div class="filter-option">
                        <input type="checkbox" id="upper" name="upper" value="1" <?php if(isset($_POST['upper'])) echo 'checked'; ?>>
                        <label for="upper">Convert all text to UPPER CASE</label>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-card-title">Remove Perks & Tagline</div>
                    <div class="filter-option">
                        <input type="checkbox" id="remove_perks_tagline" name="remove_perks_tagline" value="1" <?php if(isset($_POST['remove_perks_tagline'])) echo 'checked'; ?>>
                        <label for="remove_perks_tagline">Remove columns "Perks" and "Tagline"</label>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-card-title">Remove Duplicate</div>
                    <div class="filter-option">
                        <input type="checkbox" id="remove_duplicate" name="remove_duplicate" value="1" <?php if(isset($_POST['remove_duplicate'])) echo 'checked'; ?>>
                        <label for="remove_duplicate">Remove duplicate rows</label>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-card-title">Posting Date</div>
                    <div class="filter-option">
                        <input type="checkbox" id="add_posting_date" name="add_posting_date" value="1" <?php if(isset($_POST['add_posting_date'])) echo 'checked'; ?>>
                        <label for="add_posting_date">Add Posting Date column (today)</label>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-card-title">Expired Date</div>
                    <div class="filter-option">
                        <input type="checkbox" id="add_expired_date" name="add_expired_date" value="1" <?php if(isset($_POST['add_expired_date'])) echo 'checked'; ?>>
                        <label for="add_expired_date">Add Expired Date column (one month from today)</label>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-card-title">Provinsi</div>
                    <div class="filter-option">
                        <input type="checkbox" id="add_provinsi" name="add_provinsi" value="1" <?php if(isset($_POST['add_provinsi'])) echo 'checked'; ?>>
                        <label for="add_provinsi">Add Provinsi column ("Indonesia")</label>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-card-title">Kota</div>
                    <div class="filter-option">
                        <input type="checkbox" id="add_kota" name="add_kota" value="1" <?php if(isset($_POST['add_kota'])) echo 'checked'; ?>>
                        <label for="add_kota">Add Kota column ("Indonesia")</label>
                    </div>
                </div>
            </div>
            <div style="display: flex; justify-content: center;">
                <button type="submit">Upload & Filter</button>
            </div>
        </form>
        <div class="upload-card-container">
            <div class="upload-card">
                <?php
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvfile'])) {
                    $upload = $_FILES['csvfile'];
                    $debug = [];
                    $use_location = isset($_POST['location']) && $_POST['location'] == '1';
                    $use_upper = isset($_POST['upper']) && $_POST['upper'] == '1';
                    $remove_perks_tagline = isset($_POST['remove_perks_tagline']) && $_POST['remove_perks_tagline'] == '1';
                    $remove_duplicate = isset($_POST['remove_duplicate']) && $_POST['remove_duplicate'] == '1';
                    $add_posting_date = isset($_POST['add_posting_date']) && $_POST['add_posting_date'] == '1';
                    $add_expired_date = isset($_POST['add_expired_date']) && $_POST['add_expired_date'] == '1';
                    $add_provinsi = isset($_POST['add_provinsi']) && $_POST['add_provinsi'] == '1';
                    $add_kota = isset($_POST['add_kota']) && $_POST['add_kota'] == '1';
                    if ($upload['error'] === UPLOAD_ERR_OK) {
                        $tmpPath = $upload['tmp_name'];
                        $filteredName = 'filtered_' . uniqid() . '.csv';
                        $filteredPath = __DIR__ . '/' . $filteredName;
                        if (filter_csv($tmpPath, $filteredPath, $use_location, $use_upper, $remove_perks_tagline, $remove_duplicate, $add_posting_date, $add_expired_date, $add_provinsi, $add_kota, $debug)) {
                            echo '<p class="success">File filtered successfully. <a href="' . htmlspecialchars($filteredName) . '" download>Download filtered CSV</a></p>';
                        } else {
                            echo '<p class="error">Failed to process the CSV file. Please check the format.</p>';
                        }
                    } else {
                        echo '<p class="error">File upload error.</p>';
                    }
                    // Debug output
                    echo '<div class="debug"><strong>Debug info:</strong><br>';
                    foreach ($debug as $k => $v) {
                        echo htmlspecialchars($k) . ': ' . (is_array($v) ? htmlspecialchars(json_encode($v)) : htmlspecialchars($v)) . '<br>';
                    }
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
