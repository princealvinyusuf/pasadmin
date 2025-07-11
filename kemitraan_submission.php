<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'paskerid_db';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Handle Update
if (isset($_POST['update'])) {
    $id = $_POST['id'];
    $pic_name = $_POST['pic_name'];
    $pic_position = $_POST['pic_position'];
    $pic_email = $_POST['pic_email'];
    $pic_whatsapp = $_POST['pic_whatsapp'];
    $sector_category = $_POST['sector_category'];
    $institution_name = $_POST['institution_name'];
    $business_sector = $_POST['business_sector'];
    $institution_addre = $_POST['institution_addre'];
    $partnership_type = $_POST['partnership_type'];
    $needs = $_POST['needs'];
    $schedule = $_POST['schedule'];
    $request_letter = $_POST['request_letter'];
    $status = $_POST['status'];
    $stmt = $conn->prepare("UPDATE kemitraan SET pic_name=?, pic_position=?, pic_email=?, pic_whatsapp=?, sector_category=?, institution_name=?, business_sector=?, institution_addre=?, partnership_type=?, needs=?, schedule=?, request_letter=?, status=?, updated_at=NOW() WHERE id=?");
    $stmt->bind_param("ssssssssssssssi", $pic_name, $pic_position, $pic_email, $pic_whatsapp, $sector_category, $institution_name, $business_sector, $institution_addre, $partnership_type, $needs, $schedule, $request_letter, $status, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: kemitraan_submission.php");
    exit();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM kemitraan WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: kemitraan_submission.php");
    exit();
}

// Handle Edit (fetch data)
$edit_kemitraan = null;
if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $result = $conn->query("SELECT * FROM kemitraan WHERE id=$id");
    $edit_kemitraan = $result->fetch_assoc();
}

// Fetch all kemitraan
$kemitraans = $conn->query("SELECT * FROM kemitraan ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mitra Kerja Submission</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .navbar-brand { font-weight: bold; letter-spacing: 1px; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f6f8fa;
            margin: 0;
            padding: 0;
        }
        h2, h3 {
            text-align: center;
            color: #222;
        }
        form {
            background: #f9fafb;
            border-radius: 8px;
            padding: 24px 20px 16px 20px;
            margin-bottom: 32px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        label {
            display: block;
            margin-bottom: 14px;
            color: #333;
            font-weight: 500;
        }
        input[type="text"], input[type="email"], textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
            margin-top: 4px;
            background: #fff;
            transition: border 0.2s;
        }
        input[type="text"]:focus, input[type="email"]:focus, textarea:focus {
            border: 1.5px solid #2563eb;
            outline: none;
        }
        textarea {
            min-height: 60px;
            resize: vertical;
        }
        .btn {
            display: inline-block;
            padding: 8px 22px;
            border: none;
            border-radius: 6px;
            background: #2563eb;
            color: #fff;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            margin-right: 8px;
            margin-top: 8px;
            transition: background 0.2s;
            text-decoration: none;
        }
        .btn:hover {
            background: #1d4ed8;
        }
        .btn.cancel {
            background: #e5e7eb;
            color: #222;
        }
        .btn.cancel:hover {
            background: #d1d5db;
        }
        .btn.delete {
            background: #ef4444;
        }
        .btn.delete:hover {
            background: #b91c1c;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        th, td {
            padding: 12px 10px;
            text-align: left;
        }
        th {
            background: #f1f5f9;
            color: #222;
            font-weight: 600;
        }
        tr:nth-child(even) {
            background: #f9fafb;
        }
        tr:hover {
            background: #e0e7ef;
        }
        td {
            vertical-align: top;
        }
        .actions a {
            margin-right: 8px;
        }
        @media (max-width: 900px) {
            .container { padding: 8px; }
            form { padding: 12px 6px; }
            th, td { font-size: 0.95rem; padding: 8px 4px; }
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
                        <a class="nav-link dropdown-toggle" href="#" id="settingsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Settings
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="settingsDropdown">
                            <li><a class="dropdown-item" href="mitra_kerja_settings.php">Mitra Kerja Settings</a></li>
                            <li><a class="dropdown-item" href="kemitraan_submission.php">Mitra Kerja Submission</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <!-- End Navigation Bar -->
    <div class="container">
        <h2>Mitra Kerja Submission</h2>
        <h3><?php echo $edit_kemitraan ? 'Edit Mitra Kerja Submission' : 'All Mitra Kerja Submission'; ?></h3>
        <?php if ($edit_kemitraan): ?>
        <form method="post">
            <input type="hidden" name="id" value="<?php echo $edit_kemitraan['id']; ?>">
            <label>PIC Name:
                <input type="text" name="pic_name" required value="<?php echo htmlspecialchars($edit_kemitraan['pic_name']); ?>">
            </label>
            <label>PIC Position:
                <input type="text" name="pic_position" value="<?php echo htmlspecialchars($edit_kemitraan['pic_position']); ?>">
            </label>
            <label>PIC Email:
                <input type="email" name="pic_email" value="<?php echo htmlspecialchars($edit_kemitraan['pic_email']); ?>">
            </label>
            <label>PIC Whatsapp:
                <input type="text" name="pic_whatsapp" value="<?php echo htmlspecialchars($edit_kemitraan['pic_whatsapp']); ?>">
            </label>
            <label>Sector Category:
                <input type="text" name="sector_category" value="<?php echo htmlspecialchars($edit_kemitraan['sector_category']); ?>">
            </label>
            <label>Institution Name:
                <input type="text" name="institution_name" value="<?php echo htmlspecialchars($edit_kemitraan['institution_name']); ?>">
            </label>
            <label>Business Sector:
                <input type="text" name="business_sector" value="<?php echo htmlspecialchars($edit_kemitraan['business_sector']); ?>">
            </label>
            <label>Institution Address:
                <input type="text" name="institution_addre" value="<?php echo htmlspecialchars($edit_kemitraan['institution_addre']); ?>">
            </label>
            <label>Partnership Type:
                <input type="text" name="partnership_type" value="<?php echo htmlspecialchars($edit_kemitraan['partnership_type']); ?>">
            </label>
            <label>Needs:
                <textarea name="needs"><?php echo htmlspecialchars($edit_kemitraan['needs']); ?></textarea>
            </label>
            <label>Schedule:
                <input type="text" name="schedule" value="<?php echo htmlspecialchars($edit_kemitraan['schedule']); ?>">
            </label>
            <label>Request Letter:
                <input type="text" name="request_letter" value="<?php echo htmlspecialchars($edit_kemitraan['request_letter']); ?>">
            </label>
            <label>Status:
                <input type="text" name="status" value="<?php echo htmlspecialchars($edit_kemitraan['status']); ?>">
            </label>
            <button type="submit" class="btn" name="update">Update</button>
            <a href="kemitraan_submission.php" class="btn cancel">Cancel</a>
        </form>
        <?php endif; ?>
        <h3>All Mitra Kerja Submission</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>PIC Name</th>
                <th>PIC Position</th>
                <th>PIC Email</th>
                <th>PIC Whatsapp</th>
                <th>Sector Category</th>
                <th>Institution Name</th>
                <th>Business Sector</th>
                <th>Institution Address</th>
                <th>Partnership Type</th>
                <th>Needs</th>
                <th>Schedule</th>
                <th>Request Letter</th>
                <th>Status</th>
                <th>Created At</th>
                <th>Updated At</th>
                <th>Actions</th>
            </tr>
            <?php while ($row = $kemitraans->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['id']; ?></td>
                <td><?php echo htmlspecialchars($row['pic_name']); ?></td>
                <td><?php echo htmlspecialchars($row['pic_position']); ?></td>
                <td><?php echo htmlspecialchars($row['pic_email']); ?></td>
                <td><?php echo htmlspecialchars($row['pic_whatsapp']); ?></td>
                <td><?php echo htmlspecialchars($row['sector_category']); ?></td>
                <td><?php echo htmlspecialchars($row['institution_name']); ?></td>
                <td><?php echo htmlspecialchars($row['business_sector']); ?></td>
                <td><?php echo htmlspecialchars($row['institution_addre']); ?></td>
                <td><?php echo htmlspecialchars($row['partnership_type']); ?></td>
                <td><?php echo nl2br(htmlspecialchars($row['needs'])); ?></td>
                <td><?php echo htmlspecialchars($row['schedule']); ?></td>
                <td><?php echo htmlspecialchars($row['request_letter']); ?></td>
                <td><?php echo htmlspecialchars($row['status']); ?></td>
                <td><?php echo $row['created_at']; ?></td>
                <td><?php echo $row['updated_at']; ?></td>
                <td class="actions">
                    <button type="button" class="btn btn-info detail-btn" data-id="<?php echo $row['id']; ?>">Detail</button>
                    <a href="kemitraan_submission.php?edit=<?php echo $row['id']; ?>" class="btn">Edit</a>
                    <a href="kemitraan_submission.php?delete=<?php echo $row['id']; ?>" class="btn delete" onclick="return confirm('Delete this submission?');">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
        <!-- Detail Modal -->
        <div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="detailModalLabel">Mitra Kerja Submission Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <table class="table table-bordered">
                  <tbody id="detailModalBody">
                    <!-- Details will be injected here -->
                  </tbody>
                </table>
                <div id="downloadLetterContainer" class="mb-2"></div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              </div>
            </div>
          </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
          const detailButtons = document.querySelectorAll('.detail-btn');
          detailButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
              const row = btn.closest('tr');
              const cells = row.querySelectorAll('td');
              const headers = [
                'ID', 'PIC Name', 'PIC Position', 'PIC Email', 'PIC Whatsapp', 'Sector Category',
                'Institution Name', 'Business Sector', 'Institution Address', 'Partnership Type',
                'Needs', 'Schedule', 'Request Letter', 'Status', 'Created At', 'Updated At'
              ];
              let html = '';
              for (let i = 0; i < headers.length; i++) {
                html += `<tr><th>${headers[i]}</th><td>${cells[i].innerHTML}</td></tr>`;
              }
              document.getElementById('detailModalBody').innerHTML = html;
              // Download Letter button logic
              const requestLetter = cells[12].innerText.trim();
              const downloadContainer = document.getElementById('downloadLetterContainer');
              if (requestLetter && requestLetter !== '-') {
                const url = 'https://www.psid.run.place/paskerid/storage/app/public/' + requestLetter;
                downloadContainer.innerHTML = `<a href="${url}" class="btn btn-success" target="_blank" download>Download Letter</a>`;
              } else {
                downloadContainer.innerHTML = '';
              }
              var detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
              detailModal.show();
            });
          });
        });
        </script>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?> 