<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extensions</title>
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background: #f7fafc;
            margin: 0;
            padding: 0;
        }
        h1 {
            text-align: center;
            color: #2d3748;
            margin-bottom: 32px;
        }
        .card-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 32px;
            justify-content: center;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.07);
            padding: 32px 24px;
            min-width: 220px;
            max-width: 260px;
            flex: 1 1 220px;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: box-shadow 0.2s;
        }
        .card:hover {
            box-shadow: 0 8px 32px rgba(0,0,0,0.13);
        }
        .card-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #3182ce;
            margin-bottom: 16px;
        }
        .card-btn {
            display: inline-block;
            padding: 10px 28px;
            background: #3182ce;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            margin-top: 12px;
            transition: background 0.2s;
            cursor: pointer;
        }
        .card-btn:hover {
            background: #225ea8;
        }
        @media (max-width: 700px) {
            .card-grid {
                flex-direction: column;
                gap: 20px;
            }
        }
    </style>
</head>
<body class="bg-light">
     <?php include 'navbar.php'; ?>
     <!-- End Navigation Bar -->
     <div class="container">
        <h1>Extensions</h1>
        <div class="card-grid">
            <div class="card">
                <div class="card-title">Laporan</div>
                <a class="card-btn" href="http://psid.run.place/paskerid/public/pasadmin/information_settings.php" target="_blank">Go to Laporan</a>
            </div>
            <div class="card">
                <div class="card-title">Kaloka</div>
                <a class="card-btn" href="https://jobcodes.kemnaker.go.id/login" target="_blank">Go to Kaloka</a>
            </div>
            <div class="card">
                <div class="card-title">SosMed</div>
                <a class="card-btn" href="https://paskerid.kemnaker.go.id/dashboardmedsos/publikasi" target="_blank">Go to SosMed</a>
            </div>
            <div class="card">
                <div class="card-title">Jobita</div>
                <a class="card-btn" href="https://jfo.kemnaker.go.id/tu/login.php" target="_blank">Go to Jobita</a>
            </div>
            <div class="card">
                <div class="card-title">Job Portal</div>
                <a class="card-btn" href="https://paskerid.kemnaker.go.id/dashboardjp/publikasi" target="_blank">Go to Job Portal</a>
            </div>
        </div>
     </div>
     <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

