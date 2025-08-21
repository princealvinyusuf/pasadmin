<?php
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/access_helper.php';
if (!current_user_can('manage_settings')) { http_response_code(403); echo 'Forbidden'; exit; }
// Top List Settings - CRUD for 'top_lists' table in paskerid_db
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db_prod';
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Handle Add
if (isset($_POST['add'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $type = $conn->real_escape_string($_POST['type']);
    $data_json = $conn->real_escape_string($_POST['data_json']);
    $date = $conn->real_escape_string($_POST['date']);
    $now = date('Y-m-d H:i:s');
    $sql = "INSERT INTO top_lists (title, type, data_json, date, created_at, updated_at) VALUES ('$title', '$type', '$data_json', '$date', '$now', '$now')";
    $conn->query($sql);
    header('Location: top_list_settings.php');
    exit();
}
// Handle Edit
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_result = $conn->query("SELECT * FROM top_lists WHERE id=$edit_id");
    $edit_top_list = $edit_result->fetch_assoc();
}
// Handle Update
if (isset($_POST['update'])) {
    $id = intval($_POST['id']);
    $title = $conn->real_escape_string($_POST['title']);
    $type = $conn->real_escape_string($_POST['type']);
    $data_json = $conn->real_escape_string($_POST['data_json']);
    $date = $conn->real_escape_string($_POST['date']);
    $now = date('Y-m-d H:i:s');
    $sql = "UPDATE top_lists SET title='$title', type='$type', data_json='$data_json', date='$date', updated_at='$now' WHERE id=$id";
    $conn->query($sql);
    header('Location: top_list_settings.php');
    exit();
}
// Handle Delete
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $conn->query("DELETE FROM top_lists WHERE id=$delete_id");
    header('Location: top_list_settings.php');
    exit();
}
// Fetch all top lists
$top_lists = $conn->query("SELECT * FROM top_lists ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top List Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: linear-gradient(120deg, #f8fafc 60%, #e3e9f7 100%); min-height: 100vh; }
        .main-header { display: flex; align-items: center; gap: 18px; margin-bottom: 32px; }
        .main-header i { font-size: 2.2rem; color: #2563eb; }
        .container { max-width: 950px; margin-top: 48px; }
        .card-form { box-shadow: 0 4px 24px rgba(49,130,206,0.08); border-radius: 18px; border: none; margin-bottom: 32px; transition: box-shadow 0.2s; background: #fff; animation: fadeInDown 0.7s; }
        @keyframes fadeInDown { from { opacity: 0; transform: translateY(-30px); } to { opacity: 1; transform: translateY(0); } }
        .card-form .card-title { font-size: 1.3rem; font-weight: 600; color: #2563eb; }
        .form-label { font-weight: 500; color: #374151; }
        .form-control { border-radius: 8px; border: 1px solid #cbd5e1; }
        .btn-primary, .btn-outline-primary { border-radius: 7px; font-weight: 500; transition: background 0.18s, color 0.18s; }
        .btn-primary { background: linear-gradient(90deg, #2563eb 0%, #38bdf8 100%); border: none; }
        .btn-primary:hover { background: linear-gradient(90deg, #38bdf8 0%, #2563eb 100%); }
        .btn-outline-primary { border: 1.5px solid #2563eb; color: #2563eb; }
        .btn-outline-primary:hover { background: #2563eb; color: #fff; }
        .btn-outline-danger { border-radius: 7px; }
        .btn-secondary { border-radius: 7px; }
        .table-responsive { box-shadow: 0 2px 16px rgba(49,130,206,0.07); border-radius: 16px; background: #fff; padding: 18px 18px 8px 18px; }
        table { background: transparent; border-radius: 10px; overflow: hidden; }
        th, td { vertical-align: middle; }
        th { background: #f1f5f9; color: #2563eb; font-weight: 600; border-bottom: 2px solid #e2e8f0; }
        tr:not(:last-child) td { border-bottom: 1px solid #e2e8f0; }
        .actions a { margin-right: 8px; min-width: 70px; }
        .actions .btn { box-shadow: 0 1px 4px rgba(49,130,206,0.07); }
        .actions .btn-outline-primary { border-color: #38bdf8; color: #38bdf8; }
        .actions .btn-outline-primary:hover { background: #38bdf8; color: #fff; }
        .actions .btn-outline-danger { border-color: #f87171; color: #f87171; }
        .actions .btn-outline-danger:hover { background: #f87171; color: #fff; }
        @media (max-width: 800px) { .container { padding: 10px; } th, td { font-size: 0.97em; padding: 8px 4px; } .main-header { flex-direction: column; gap: 8px; } }
        .navbar-brand { font-weight: bold; letter-spacing: 1px; }
    </style>
</head>
<body class="bg-light">
      <?php include 'navbar.php'; ?>
      <!-- End Navigation Bar -->
    <div class="container">
        <div class="main-header mb-4">
            <i class="bi bi-list-stars"></i>
            <div>
                <h1 class="mb-0" style="font-size:2rem;font-weight:700;letter-spacing:1px;">Top List Settings</h1>
                <div class="text-muted" style="font-size:1.08em;">Manage your platform's top lists</div>
            </div>
        </div>
        <div class="card card-form mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3"><?php echo isset($edit_top_list) ? '<i class="bi bi-pencil-square"></i> Edit Top List' : '<i class="bi bi-plus-circle"></i> Add New Top List'; ?></h5>
                <form method="post" autocomplete="off">
                    <?php if (isset($edit_top_list)): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_top_list['id']; ?>">
                    <?php endif; ?>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" required value="<?php echo isset($edit_top_list) ? htmlspecialchars($edit_top_list['title']) : ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Type</label>
                            <input type="text" name="type" class="form-control" required value="<?php echo isset($edit_top_list) ? htmlspecialchars($edit_top_list['type']) : ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" class="form-control" required value="<?php echo isset($edit_top_list) ? htmlspecialchars($edit_top_list['date']) : ''; ?>">
                        </div>
                        <div class="col-md-12 mt-3">
                            <label class="form-label">Data JSON</label>
                            <textarea name="data_json" class="form-control" rows="3" required><?php echo isset($edit_top_list) ? htmlspecialchars($edit_top_list['data_json']) : ''; ?></textarea>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" name="<?php echo isset($edit_top_list) ? 'update' : 'add'; ?>" class="btn btn-primary px-4">
                            <?php echo isset($edit_top_list) ? '<i class="bi bi-save"></i> Update' : '<i class="bi bi-plus-circle"></i> Add'; ?>
                        </button>
                        <?php if (isset($edit_top_list)): ?>
                            <a href="top_list_settings.php" class="btn btn-secondary ms-2 px-4"><i class="bi bi-x-circle"></i> Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <h4 class="mb-3" style="font-weight:600;color:#2563eb;"><i class="bi bi-list-stars"></i> All Top Lists</h4>
        <div class="table-responsive">
            <table class="table table-hover align-middle mt-2">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Data JSON</th>
                        <th>Created At</th>
                        <th>Updated At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = $top_lists->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td style="font-weight:500;"><?php echo htmlspecialchars($row['title']); ?></td>
                        <td><?php echo htmlspecialchars($row['type']); ?></td>
                        <td><?php echo $row['date']; ?></td>
                        <td><pre style="max-width:300px;white-space:pre-wrap;word-break:break-all;background:#f8fafc;border-radius:6px;padding:6px 8px;font-size:0.97em;"><?php echo htmlspecialchars($row['data_json']); ?></pre></td>
                        <td><span class="badge bg-light text-dark border border-1 border-secondary-subtle"><?php echo $row['created_at']; ?></span></td>
                        <td><span class="badge bg-light text-dark border border-1 border-secondary-subtle"><?php echo $row['updated_at']; ?></span></td>
                        <td class="actions">
                            <a href="top_list_settings.php?edit=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Edit</a>
                            <a href="top_list_settings.php?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this top list?');"><i class="bi bi-trash"></i> Delete</a>
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