<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db_prod';

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';

if (!(current_user_can('settings_minijobi_manage') || current_user_can('manage_settings'))) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

// Ensure table exists for standalone miniJobi data source.
$conn->query("CREATE TABLE IF NOT EXISTS mini_jobi_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    location VARCHAR(255) NOT NULL,
    employment_type VARCHAR(255) NULL,
    category VARCHAR(255) NULL,
    salary_range VARCHAR(255) NULL,
    description TEXT NOT NULL,
    requirements TEXT NULL,
    apply_url VARCHAR(255) NULL,
    deadline_date DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active_created (is_active, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Handle Create
if (isset($_POST['add'])) {
    $title = trim($_POST['title'] ?? '');
    $companyName = trim($_POST['company_name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $employmentType = trim($_POST['employment_type'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $salaryRange = trim($_POST['salary_range'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    $applyUrl = trim($_POST['apply_url'] ?? '');
    $deadlineDate = trim($_POST['deadline_date'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $employmentType = ($employmentType === '') ? null : $employmentType;
    $category = ($category === '') ? null : $category;
    $salaryRange = ($salaryRange === '') ? null : $salaryRange;
    $requirements = ($requirements === '') ? null : $requirements;
    $applyUrl = ($applyUrl === '') ? null : $applyUrl;
    $deadlineDate = ($deadlineDate === '') ? null : $deadlineDate;

    $stmt = $conn->prepare("INSERT INTO mini_jobi_jobs
        (title, company_name, location, employment_type, category, salary_range, description, requirements, apply_url, deadline_date, is_active, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->bind_param(
        "ssssssssssi",
        $title,
        $companyName,
        $location,
        $employmentType,
        $category,
        $salaryRange,
        $description,
        $requirements,
        $applyUrl,
        $deadlineDate,
        $isActive
    );
    $stmt->execute();
    $stmt->close();

    header('Location: mini_jobi_jobs.php');
    exit();
}

// Handle Update
if (isset($_POST['update'])) {
    $id = (int) ($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $companyName = trim($_POST['company_name'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $employmentType = trim($_POST['employment_type'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $salaryRange = trim($_POST['salary_range'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    $applyUrl = trim($_POST['apply_url'] ?? '');
    $deadlineDate = trim($_POST['deadline_date'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $employmentType = ($employmentType === '') ? null : $employmentType;
    $category = ($category === '') ? null : $category;
    $salaryRange = ($salaryRange === '') ? null : $salaryRange;
    $requirements = ($requirements === '') ? null : $requirements;
    $applyUrl = ($applyUrl === '') ? null : $applyUrl;
    $deadlineDate = ($deadlineDate === '') ? null : $deadlineDate;

    $stmt = $conn->prepare("UPDATE mini_jobi_jobs SET
        title = ?,
        company_name = ?,
        location = ?,
        employment_type = ?,
        category = ?,
        salary_range = ?,
        description = ?,
        requirements = ?,
        apply_url = ?,
        deadline_date = ?,
        is_active = ?,
        updated_at = NOW()
        WHERE id = ?");
    $stmt->bind_param(
        "ssssssssssii",
        $title,
        $companyName,
        $location,
        $employmentType,
        $category,
        $salaryRange,
        $description,
        $requirements,
        $applyUrl,
        $deadlineDate,
        $isActive,
        $id
    );
    $stmt->execute();
    $stmt->close();

    header('Location: mini_jobi_jobs.php');
    exit();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM mini_jobi_jobs WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    header('Location: mini_jobi_jobs.php');
    exit();
}

// Handle Edit (fetch one row)
$editJob = null;
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM mini_jobi_jobs WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $editJob = $result->fetch_assoc();
    $stmt->close();
}

// Fetch all rows
$jobs = $conn->query("SELECT * FROM mini_jobi_jobs ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>miniJobi Jobs Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f6f8fa; }
        h2, h3 { text-align: center; color: #222; }
        form {
            background: #f9fafb; border-radius: 8px; padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03); margin-bottom: 28px;
        }
        .btn-primary-custom { background: #2563eb; border-color: #2563eb; color: #fff; }
        .btn-primary-custom:hover { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }
        .table-wrap { overflow-x: auto; }
        table { background: #fff; border-radius: 8px; overflow: hidden; }
        .desc-col { min-width: 260px; }
        .req-col { min-width: 220px; }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>
    <div class="container py-4">
        <h2>miniJobi Jobs Settings</h2>
        <h3><?php echo $editJob ? 'Edit Job' : 'Add Job'; ?></h3>

        <form method="post">
            <?php if ($editJob): ?>
                <input type="hidden" name="id" value="<?php echo (int) $editJob['id']; ?>">
            <?php endif; ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Job Title</label>
                    <input type="text" name="title" class="form-control" required value="<?php echo h($editJob['title'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Company Name</label>
                    <input type="text" name="company_name" class="form-control" required value="<?php echo h($editJob['company_name'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control" required value="<?php echo h($editJob['location'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Employment Type</label>
                    <input type="text" name="employment_type" class="form-control" value="<?php echo h($editJob['employment_type'] ?? ''); ?>" placeholder="Full-time / Contract / Freelance">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Category</label>
                    <input type="text" name="category" class="form-control" value="<?php echo h($editJob['category'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Salary Range</label>
                    <input type="text" name="salary_range" class="form-control" value="<?php echo h($editJob['salary_range'] ?? ''); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Apply URL</label>
                    <input type="url" name="apply_url" class="form-control" value="<?php echo h($editJob['apply_url'] ?? ''); ?>" placeholder="https://...">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Deadline Date</label>
                    <input type="date" name="deadline_date" class="form-control" value="<?php echo h($editJob['deadline_date'] ?? ''); ?>">
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input
                            type="checkbox"
                            class="form-check-input"
                            id="is_active"
                            name="is_active"
                            value="1"
                            <?php echo (isset($editJob['is_active']) ? ((int) $editJob['is_active'] === 1) : true) ? 'checked' : ''; ?>
                        >
                        <label class="form-check-label" for="is_active">Active job posting</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="4" class="form-control" required><?php echo h($editJob['description'] ?? ''); ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Requirements</label>
                    <textarea name="requirements" rows="3" class="form-control"><?php echo h($editJob['requirements'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary-custom" name="<?php echo $editJob ? 'update' : 'add'; ?>">
                    <?php echo $editJob ? 'Update Job' : 'Add Job'; ?>
                </button>
                <?php if ($editJob): ?>
                    <a href="mini_jobi_jobs.php" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
        </form>

        <h3>All miniJobi Jobs</h3>
        <div class="table-wrap">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Company</th>
                        <th>Location</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Salary</th>
                        <th>Apply URL</th>
                        <th>Deadline</th>
                        <th>Status</th>
                        <th class="desc-col">Description</th>
                        <th class="req-col">Requirements</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $jobs->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo (int) $row['id']; ?></td>
                            <td><?php echo h($row['title']); ?></td>
                            <td><?php echo h($row['company_name']); ?></td>
                            <td><?php echo h($row['location']); ?></td>
                            <td><?php echo h($row['employment_type']); ?></td>
                            <td><?php echo h($row['category']); ?></td>
                            <td><?php echo h($row['salary_range']); ?></td>
                            <td>
                                <?php if (!empty($row['apply_url'])): ?>
                                    <a href="<?php echo h($row['apply_url']); ?>" target="_blank" rel="noopener noreferrer">Link</a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo h($row['deadline_date']); ?></td>
                            <td>
                                <?php if ((int) $row['is_active'] === 1): ?>
                                    <span class="badge text-bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo nl2br(h($row['description'])); ?></td>
                            <td><?php echo nl2br(h($row['requirements'])); ?></td>
                            <td><?php echo h($row['updated_at']); ?></td>
                            <td>
                                <a href="mini_jobi_jobs.php?edit=<?php echo (int) $row['id']; ?>" class="btn btn-sm btn-primary-custom mb-1">Edit</a>
                                <a href="mini_jobi_jobs.php?delete=<?php echo (int) $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this job posting?');">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>

