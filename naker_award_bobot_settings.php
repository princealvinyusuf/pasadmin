<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';
if (!(current_user_can('naker_award_manage_weights') || current_user_can('manage_settings'))) { http_response_code(403); echo 'Forbidden'; exit; }

// Ensure weights table exists and a single row is present
$conn->query("CREATE TABLE IF NOT EXISTS naker_award_weights (
    id INT PRIMARY KEY,
    weight_postings INT NOT NULL DEFAULT 30,
    weight_quota INT NOT NULL DEFAULT 25,
    weight_ratio INT NOT NULL DEFAULT 10,
    weight_realization INT NOT NULL DEFAULT 20,
    weight_disability INT NOT NULL DEFAULT 15,
    weight_tindak INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure new column exists on older deployments
try { $conn->query("ALTER TABLE naker_award_weights ADD COLUMN IF NOT EXISTS weight_tindak INT NOT NULL DEFAULT 0"); } catch (Throwable $e) {
    try {
        $chk = $conn->query("SHOW COLUMNS FROM naker_award_weights LIKE 'weight_tindak'");
        if ($chk && $chk->num_rows === 0) { $conn->query("ALTER TABLE naker_award_weights ADD COLUMN weight_tindak INT NOT NULL DEFAULT 0"); }
    } catch (Throwable $e2) {}
}

$res = $conn->query('SELECT COUNT(*) AS c FROM naker_award_weights');
$row = $res ? $res->fetch_assoc() : ['c' => 0];
if (intval($row['c'] ?? 0) === 0) {
    // Insert default row with id=1
    $conn->query("INSERT INTO naker_award_weights (id) VALUES (1)");
}

// Load current weights
function load_current_weights(mysqli $conn): array {
    $defaults = [30,25,10,20,15,0];
    try {
        $q = $conn->query('SELECT weight_postings, weight_quota, weight_ratio, weight_realization, weight_disability, weight_tindak FROM naker_award_weights WHERE id=1');
        if ($q && ($r = $q->fetch_assoc())) {
            return [
                intval($r['weight_postings']),
                intval($r['weight_quota']),
                intval($r['weight_ratio']),
                intval($r['weight_realization']),
                intval($r['weight_disability']),
                intval($r['weight_tindak'])
            ];
        }
    } catch (Throwable $e) {}
    return $defaults;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wPost = max(0, min(100, intval($_POST['weight_postings'] ?? 0)));
    $wQuota = max(0, min(100, intval($_POST['weight_quota'] ?? 0)));
    $wRatio = max(0, min(100, intval($_POST['weight_ratio'] ?? 0)));
    $wReal = max(0, min(100, intval($_POST['weight_realization'] ?? 0)));
    $wDis = max(0, min(100, intval($_POST['weight_disability'] ?? 0)));
    $wTindak = max(0, min(100, intval($_POST['weight_tindak'] ?? 0)));

    $sum = $wPost + $wQuota + $wRatio + $wReal + $wDis + $wTindak;
    if ($sum !== 100) {
        $error = 'Total bobot harus 100%. Saat ini: ' . $sum . '%.';
    } else {
        $stmt = $conn->prepare('UPDATE naker_award_weights SET weight_postings=?, weight_quota=?, weight_ratio=?, weight_realization=?, weight_disability=?, weight_tindak=? WHERE id=1');
        $stmt->bind_param('iiiiii', $wPost, $wQuota, $wRatio, $wReal, $wDis, $wTindak);
        $stmt->execute();
        $stmt->close();
        $message = 'Bobot tersimpan.';
    }
}

list($curPost, $curQuota, $curRatio, $curReal, $curDis, $curTindak) = load_current_weights($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WLLP Award - Bobot Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f6f8fa; }
        .card { box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
    </style>
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">WLLP Award - Bobot Settings</h2>
    </div>
    <div class="card">
        <div class="card-body">
            <?php if (!empty($message)): ?><div class="alert alert-success py-2 px-3 mb-3"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if (!empty($error)): ?><div class="alert alert-danger py-2 px-3 mb-3"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <form method="post" class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label">Jumlah Postingan Lowongan</label>
                    <div class="input-group">
                        <input type="number" name="weight_postings" class="form-control" min="0" max="100" value="<?php echo intval($curPost); ?>" required>
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">Jumlah Kuota Lowongan</label>
                    <div class="input-group">
                        <input type="number" name="weight_quota" class="form-control" min="0" max="100" value="<?php echo intval($curQuota); ?>" required>
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">Ratio Lowongan Terhadap WLKP</label>
                    <div class="input-group">
                        <input type="number" name="weight_ratio" class="form-control" min="0" max="100" value="<?php echo intval($curRatio); ?>" required>
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">Realisasi Penempatan TK</label>
                    <div class="input-group">
                        <input type="number" name="weight_realization" class="form-control" min="0" max="100" value="<?php echo intval($curReal); ?>" required>
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">Jumlah Kebutuhan Disabilitas</label>
                    <div class="input-group">
                        <input type="number" name="weight_disability" class="form-control" min="0" max="100" value="<?php echo intval($curDis); ?>" required>
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">Tindak Lanjut Lamaran</label>
                    <div class="input-group">
                        <input type="number" name="weight_tindak" class="form-control" min="0" max="100" value="<?php echo intval($curTindak); ?>" required>
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <div class="col-12">
                    <div class="text-muted small mb-2">Total bobot harus 100%.</div>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


