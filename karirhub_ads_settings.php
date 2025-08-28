<?php
// Debug: show all errors in this page
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
if (!(current_user_can('settings_karirhub_ads_manage') || current_user_can('manage_settings'))) { http_response_code(403); echo 'Forbidden'; exit; }

// Standalone DB connection for paskerid_db_prod
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db_prod';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Ensure table exists (first run friendly)
$conn->query(
    'CREATE TABLE IF NOT EXISTS karirhub_ads (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        job_title VARCHAR(255) NOT NULL,
        company_name VARCHAR(255) NOT NULL,
        city VARCHAR(255) NOT NULL,
        province VARCHAR(255) NOT NULL,
        salary_min INT NULL,
        salary_max INT NULL,
        secret TINYINT(1) DEFAULT 0,
        image_base64 LONGTEXT NULL,
        mime_type VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

// CRUD operations will be implemented here

// Helper function for image processing
function processImage($file) {
    if ($file['error'] == UPLOAD_ERR_OK) {
        $image_info = getimagesize($file['tmp_name']);
        if ($image_info) {
            $mime_type = $image_info['mime'];
            $image_base64 = base64_encode(file_get_contents($file['tmp_name']));
            return ['base64' => $image_base64, 'mime_type' => $mime_type];
        }
    }
    return null;
}

// Handle Create and Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $job_title = $_POST['job_title'];
    $company_name = $_POST['company_name'];
    $city = $_POST['city'];
    $province = $_POST['province'];
    $salary_min = empty($_POST['salary_min']) ? null : intval($_POST['salary_min']);
    $salary_max = empty($_POST['salary_max']) ? null : intval($_POST['salary_max']);
    $secret = isset($_POST['secret']) ? 1 : 0;

    $image_data = null;
    if (isset($_FILES['image_base64']) && $_FILES['image_base64']['error'] == UPLOAD_ERR_OK) {
        $image_data = processImage($_FILES['image_base64']);
    }

    if (isset($_POST['create'])) {
        $stmt = $conn->prepare("INSERT INTO karirhub_ads (job_title, company_name, city, province, salary_min, salary_max, secret, image_base64, mime_type, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        if ($image_data) {
            $stmt->bind_param("ssssiiiss", $job_title, $company_name, $city, $province, $salary_min, $salary_max, $secret, $image_data['base64'], $image_data['mime_type']);
        } else {
            $null_image_base64 = null;
            $null_mime_type = null;
            $stmt->bind_param("ssssiiiss", $job_title, $company_name, $city, $province, $salary_min, $salary_max, $secret, $null_image_base64, $null_mime_type);
        }
        $stmt->execute();
        $stmt->close();
    } elseif (isset($_POST['update'])) {
        $sql = "UPDATE karirhub_ads SET job_title=?, company_name=?, city=?, province=?, salary_min=?, salary_max=?, secret=?, updated_at=NOW()";
        $types = "ssssiiis";
        $params = [$job_title, $company_name, $city, $province, $salary_min, $salary_max, $secret];

        if ($image_data) {
            $sql .= ", image_base64=?, mime_type=?";
            $types .= "ss";
            $params[] = $image_data['base64'];
            $params[] = $image_data['mime_type'];
        }

        $sql .= " WHERE id=?";
        $types .= "i";
        $params[] = $id;
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: karirhub_ads_settings.php");
    exit();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM karirhub_ads WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: karirhub_ads_settings.php");
    exit();
}

// Handle Edit (fetch data)
$edit_ad = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM karirhub_ads WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_ad = $result->fetch_assoc();
    $stmt->close();
}

// Fetch all records
$records = $conn->query("SELECT * FROM karirhub_ads ORDER BY id DESC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KarirHub Ads Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container mt-4">
        <h1 class="mb-4">KarirHub Ads Settings</h1>

        <?php if ($edit_ad): ?>
            <div class="card mb-4">
                <div class="card-header">
                    Edit Ad
                </div>
                <div class="card-body">
                    <form action="karirhub_ads_settings.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_ad['id']); ?>">
                        <div class="mb-3">
                            <label for="job_title" class="form-label">Job Title</label>
                            <input type="text" class="form-control" id="job_title" name="job_title" value="<?php echo htmlspecialchars($edit_ad['job_title']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="company_name" class="form-label">Company Name</label>
                            <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo htmlspecialchars($edit_ad['company_name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="city" class="form-label">City</label>
                            <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($edit_ad['city']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="province" class="form-label">Province</label>
                            <input type="text" class="form-control" id="province" name="province" value="<?php echo htmlspecialchars($edit_ad['province']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="salary_min" class="form-label">Salary Min</label>
                            <input type="number" class="form-control" id="salary_min" name="salary_min" value="<?php echo htmlspecialchars($edit_ad['salary_min']); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="salary_max" class="form-label">Salary Max</label>
                            <input type="number" class="form-control" id="salary_max" name="salary_max" value="<?php echo htmlspecialchars($edit_ad['salary_max']); ?>">
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="secret" name="secret" value="1" <?php echo $edit_ad['secret'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="secret">Secret</label>
                        </div>
                        <div class="mb-3">
                            <label for="image_base64" class="form-label">Image (Base64)</label>
                            <input type="file" class="form-control" id="image_base64" name="image_base64" accept="image/*">
                            <?php if ($edit_ad['image_base64']): ?>
                                <small class="form-text text-muted">Current image will be replaced if a new one is uploaded.</small>
                                <br>
                                <img src="data:<?php echo htmlspecialchars($edit_ad['mime_type']); ?>;base64,<?php echo htmlspecialchars($edit_ad['image_base64']); ?>" alt="Current Image" class="img-thumbnail mt-2" style="max-width: 200px;">
                            <?php endif; ?>
                        </div>
                        <button type="submit" name="update" class="btn btn-primary">Update Ad</button>
                        <a href="karirhub_ads_settings.php" class="btn btn-secondary">Cancel</a>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="card mb-4">
                <div class="card-header">
                    Add New Ad
                </div>
                <div class="card-body">
                    <form action="karirhub_ads_settings.php" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="job_title" class="form-label">Job Title</label>
                            <input type="text" class="form-control" id="job_title" name="job_title" required>
                        </div>
                        <div class="mb-3">
                            <label for="company_name" class="form-label">Company Name</label>
                            <input type="text" class="form-control" id="company_name" name="company_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="city" class="form-label">City</label>
                            <input type="text" class="form-control" id="city" name="city" required>
                        </div>
                        <div class="mb-3">
                            <label for="province" class="form-label">Province</label>
                            <input type="text" class="form-control" id="province" name="province" required>
                        </div>
                        <div class="mb-3">
                            <label for="salary_min" class="form-label">Salary Min</label>
                            <input type="number" class="form-control" id="salary_min" name="salary_min">
                        </div>
                        <div class="mb-3">
                            <label for="salary_max" class="form-label">Salary Max</label>
                            <input type="number" class="form-control" id="salary_max" name="salary_max">
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="secret" name="secret" value="1">
                            <label class="form-check-label" for="secret">Secret</label>
                        </div>
                        <div class="mb-3">
                            <label for="image_base64" class="form-label">Image (Base64)</label>
                            <input type="file" class="form-control" id="image_base64" name="image_base64" accept="image/*">
                        </div>
                        <button type="submit" name="create" class="btn btn-primary">Add Ad</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                Existing KarirHub Ads
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Job Title</th>
                                <th>Company Name</th>
                                <th>City</th>
                                <th>Province</th>
                                <th>Salary Min</th>
                                <th>Salary Max</th>
                                <th>Secret</th>
                                <th>Image</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($records->num_rows > 0): ?>
                                <?php while($row = $records->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                                        <td><?php echo htmlspecialchars($row['job_title']); ?></td>
                                        <td><?php echo htmlspecialchars($row['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['city']); ?></td>
                                        <td><?php echo htmlspecialchars($row['province']); ?></td>
                                        <td><?php echo htmlspecialchars($row['salary_min']); ?></td>
                                        <td><?php echo htmlspecialchars($row['salary_max']); ?></td>
                                        <td><?php echo $row['secret'] ? 'Yes' : 'No'; ?></td>
                                        <td>
                                            <?php if ($row['image_base64']): ?>
                                                <img src="data:<?php echo htmlspecialchars($row['mime_type']); ?>;base64,<?php echo htmlspecialchars($row['image_base64']); ?>" alt="Ad Image" style="max-width: 100px;">
                                            <?php else: ?>
                                                No Image
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="karirhub_ads_settings.php?edit=<?php echo htmlspecialchars($row['id']); ?>" class="btn btn-sm btn-warning">Edit</a>
                                            <a href="karirhub_ads_settings.php?delete=<?php echo htmlspecialchars($row['id']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this ad?');">Delete</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10">No ads found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
