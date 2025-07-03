<?php
// Placeholder for future Makaryo filter logic
function filter_csv_makaryo($inputPath, $outputPath, &$debug = null, $uppercase = false, $remove_duplicate = false, $add_posting_date = false, $add_provinsi = false) {
    $debug = $debug ?? [];
    $rows = [];
    $unique = [];
    $in = fopen($inputPath, 'r');
    if (!$in) {
        $debug['file_error'] = 'Failed to open input file.';
        return false;
    }
    $header = fgetcsv($in, 0, ';');
    if ($add_posting_date) {
        $header[] = 'posting_date';
    }
    if ($add_provinsi) {
        $header[] = 'provinsi';
    }
    if ($uppercase) {
        $header = array_map(function($v) { return mb_strtoupper($v, 'UTF-8'); }, $header);
    }
    $rows[] = $header;
    while (($row = fgetcsv($in, 0, ';')) !== false) {
        if ($uppercase) {
            $row = array_map(function($v) { return mb_strtoupper($v, 'UTF-8'); }, $row);
        }
        if ($add_posting_date) {
            // Find exp_date column (case-insensitive)
            $exp_idx = null;
            foreach ($header as $i => $col) {
                if (strtolower($col) === 'exp_date') {
                    $exp_idx = $i;
                    break;
                }
            }
            if ($exp_idx !== null && isset($row[$exp_idx])) {
                $exp_date = $row[$exp_idx];
                $date = DateTime::createFromFormat('d/m/Y', $exp_date);
                if ($date) {
                    $date->modify('-1 month');
                    $posting_date = $date->format('d/m/Y');
                } else {
                    $posting_date = '';
                }
            } else {
                $posting_date = '';
            }
            $row[] = $posting_date;
        }
        if ($add_provinsi) {
            $row[] = $uppercase ? mb_strtoupper('Indonesia', 'UTF-8') : 'Indonesia';
        }
        $rows[] = $row;
    }
    fclose($in);
    // Remove duplicates if needed (skip header)
    if ($remove_duplicate) {
        $seen = [];
        $unique = [$rows[0]]; // keep header
        foreach (array_slice($rows, 1) as $row) {
            $key = implode('|', $row);
            if (!isset($seen[$key])) {
                $unique[] = $row;
                $seen[$key] = true;
            }
        }
        $rows = $unique;
        $debug['message'] = 'Duplicates removed.' . ($uppercase ? ' All content uppercased.' : '') . ($add_posting_date ? ' Posting date added.' : '') . ($add_provinsi ? ' Provinsi added.' : '');
    } else if ($uppercase || $add_posting_date || $add_provinsi) {
        $msg = '';
        if ($uppercase) $msg .= 'All content uppercased. ';
        if ($add_posting_date) $msg .= 'Posting date added. ';
        if ($add_provinsi) $msg .= 'Provinsi added.';
        $debug['message'] = trim($msg);
    } else {
        $debug['message'] = 'No filtering applied. Coming soon!';
    }
    // Write output
    $out = fopen($outputPath, 'w');
    if (!$out) {
        $debug['file_error'] = 'Failed to open output file.';
        return false;
    }
    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }
    fclose($out);
    return true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cleansing Makaryo</title>
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
        .coming-soon {
            color: #888;
            font-style: italic;
            margin-top: 10px;
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
                    <li class="nav-item">
                        <a class="nav-link" href="index.html">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="jobs.html">Jobs</a>
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
        <div class="section-title">Cleansing Makaryo <span class="coming-soon"></span></div>
        <form method="post" enctype="multipart/form-data">
            <div class="upload-card-container">
                <div class="upload-card">
                    <div class="upload-card-title">Upload CSV File</div>
                    <input type="file" name="csvfile" accept=".csv" required>
                </div>
            </div>
            <div class="features-row">
                <div class="feature-card">
                    <div class="feature-card-title">Uppercase All</div>
                    <div>Ubah semua data menjadi huruf kapital</div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="uppercase" id="uppercase">
                        <label class="form-check-label" for="uppercase">Aktifkan Uppercase All</label>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-card-title">Remove Duplicate</div>
                    <div>Hapus baris data yang sama persis (duplikat)</div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="remove_duplicate" id="remove_duplicate">
                        <label class="form-check-label" for="remove_duplicate">Aktifkan Remove Duplicate</label>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-card-title">Posting Date</div>
                    <div>Menambahkan kolom tanggal posting (1 bulan sebelum expired date)</div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="add_posting_date" id="add_posting_date">
                        <label class="form-check-label" for="add_posting_date">Aktifkan Posting Date</label>
                    </div>
                </div>
                <div class="feature-card">
                    <div class="feature-card-title">Provinsi</div>
                    <div>Menambahkan kolom Provinsi dengan isi 'Indonesia'</div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="add_provinsi" id="add_provinsi">
                        <label class="form-check-label" for="add_provinsi">Aktifkan Provinsi</label>
                    </div>
                </div>
            </div>
            <div style="display: flex; justify-content: center;">
                <button type="submit">Upload &amp; Filter</button>
            </div>
        </form>
        <div class="upload-card-container">
            <div class="upload-card">
                <?php
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvfile'])) {
                    $upload = $_FILES['csvfile'];
                    $debug = [];
                    $uppercase = isset($_POST['uppercase']) && $_POST['uppercase'] === 'on';
                    $remove_duplicate = isset($_POST['remove_duplicate']) && $_POST['remove_duplicate'] === 'on';
                    $add_posting_date = isset($_POST['add_posting_date']) && $_POST['add_posting_date'] === 'on';
                    $add_provinsi = isset($_POST['add_provinsi']) && $_POST['add_provinsi'] === 'on';
                    if ($upload['error'] === UPLOAD_ERR_OK) {
                        $tmpPath = $upload['tmp_name'];
                        $filteredName = 'filtered_makaryo_' . uniqid() . '.csv';
                        $filteredPath = __DIR__ . '/' . $filteredName;
                        if (filter_csv_makaryo($tmpPath, $filteredPath, $debug, $uppercase, $remove_duplicate, $add_posting_date, $add_provinsi)) {
                            $msg = 'File uploaded. ';
                            if ($remove_duplicate && $uppercase && $add_posting_date && $add_provinsi) {
                                $msg .= 'Duplikat dihapus, semua data diubah menjadi huruf kapital, posting date dan provinsi ditambahkan. ';
                            } else if ($remove_duplicate && $uppercase && $add_posting_date) {
                                $msg .= 'Duplikat dihapus, semua data diubah menjadi huruf kapital, dan posting date ditambahkan. ';
                            } else if ($remove_duplicate && $uppercase && $add_provinsi) {
                                $msg .= 'Duplikat dihapus, semua data diubah menjadi huruf kapital, dan provinsi ditambahkan. ';
                            } else if ($remove_duplicate && $add_posting_date && $add_provinsi) {
                                $msg .= 'Duplikat dihapus, posting date dan provinsi ditambahkan. ';
                            } else if ($uppercase && $add_posting_date && $add_provinsi) {
                                $msg .= 'Semua data diubah menjadi huruf kapital, posting date dan provinsi ditambahkan. ';
                            } else if ($remove_duplicate && $uppercase) {
                                $msg .= 'Duplikat dihapus & semua data diubah menjadi huruf kapital. ';
                            } else if ($remove_duplicate && $add_posting_date) {
                                $msg .= 'Duplikat dihapus & posting date ditambahkan. ';
                            } else if ($remove_duplicate && $add_provinsi) {
                                $msg .= 'Duplikat dihapus & provinsi ditambahkan. ';
                            } else if ($uppercase && $add_posting_date) {
                                $msg .= 'Semua data diubah menjadi huruf kapital & posting date ditambahkan. ';
                            } else if ($uppercase && $add_provinsi) {
                                $msg .= 'Semua data diubah menjadi huruf kapital & provinsi ditambahkan. ';
                            } else if ($add_posting_date && $add_provinsi) {
                                $msg .= 'Posting date dan provinsi ditambahkan. ';
                            } else if ($remove_duplicate) {
                                $msg .= 'Duplikat dihapus. ';
                            } else if ($uppercase) {
                                $msg .= 'Semua data diubah menjadi huruf kapital. ';
                            } else if ($add_posting_date) {
                                $msg .= 'Posting date ditambahkan. ';
                            } else if ($add_provinsi) {
                                $msg .= 'Provinsi ditambahkan. ';
                            } else {
                                $msg .= 'No filtering applied yet. ';
                            }
                            echo '<p class="success">' . $msg . '<a href="' . htmlspecialchars($filteredName) . '" download>Download CSV</a></p>';
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
