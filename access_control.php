<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';

// Only allow users with permission or Super Admin
if (!current_user_can('manage_access_control')) {
	// If no groups existed yet, bootstrapper in helper will have granted current user super admin
	if (!current_user_can('manage_access_control')) {
		http_response_code(403);
		echo 'Forbidden';
		exit;
	}
}

$tab = $_GET['tab'] ?? 'users';
$action = $_POST['action'] ?? '';

// Super admin guard for destructive actions
if (isset($_POST['action']) && $_POST['action'] === 'delete_user' && !current_user_is_super_admin()) {
	http_response_code(403);
	echo 'Forbidden';
	exit;
}

// Handle actions
if ($action === 'save_group') {
	$groupId = isset($_POST['group_id']) && $_POST['group_id'] !== '' ? intval($_POST['group_id']) : null;
	$name = trim($_POST['name'] ?? '');
	$description = trim($_POST['description'] ?? '');
	if ($groupId) {
		$stmt = $conn->prepare('UPDATE access_groups SET name=?, description=? WHERE id=?');
		$stmt->bind_param('ssi', $name, $description, $groupId);
		$stmt->execute();
		$stmt->close();
	} else {
		$stmt = $conn->prepare('INSERT INTO access_groups (name, description) VALUES (?, ?)');
		$stmt->bind_param('ss', $name, $description);
		$stmt->execute();
		$groupId = $stmt->insert_id;
		$stmt->close();
	}
	// Update permissions
	$conn->prepare('DELETE FROM group_permissions WHERE group_id=' . intval($groupId))->execute();
	if (!empty($_POST['permissions']) && is_array($_POST['permissions'])) {
		$ins = $conn->prepare('INSERT INTO group_permissions (group_id, permission_id) VALUES (?, ?)');
		foreach ($_POST['permissions'] as $pid) {
			$pid = intval($pid);
			$ins->bind_param('ii', $groupId, $pid);
			$ins->execute();
		}
		$ins->close();
	}
	header('Location: access_control.php?tab=groups');
	exit;
}

if ($action === 'delete_group') {
	$gid = intval($_POST['group_id']);
	$stmt = $conn->prepare('DELETE FROM access_groups WHERE id=?');
	$stmt->bind_param('i', $gid);
	$stmt->execute();
	$stmt->close();
	header('Location: access_control.php?tab=groups');
	exit;
}

if ($action === 'save_user_access') {
	$userId = intval($_POST['user_id']);
	$accountType = trim($_POST['account_type'] ?? 'staff');
	$groupId = intval($_POST['group_id']);
	// Upsert mapping
	$stmt = $conn->prepare('INSERT INTO user_access (user_id, account_type, group_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE account_type=VALUES(account_type), group_id=VALUES(group_id)');
	$stmt->bind_param('isi', $userId, $accountType, $groupId);
	$stmt->execute();
	$stmt->close();
	header('Location: access_control.php?tab=users');
	exit;
}

if ($action === 'delete_user') {
	$userId = intval($_POST['user_id'] ?? 0);
	if ($userId > 0) {
		// Remove RBAC mapping
		$stmt = $conn->prepare('DELETE FROM user_access WHERE user_id=?');
		$stmt->bind_param('i', $userId);
		$stmt->execute();
		$stmt->close();
		// Optionally, delete from users table in job_admin_prod if exists
		try {
			$conn->query('DELETE FROM users WHERE id=' . $userId);
		} catch (Throwable $e) {}
	}
	header('Location: access_control.php?tab=users');
	exit;
}

if ($action === 'add_permission') {
	$code = trim($_POST['code']);
	$label = trim($_POST['label']);
	$category = trim($_POST['category']);
	$stmt = $conn->prepare('INSERT IGNORE INTO access_permissions (code, label, category) VALUES (?, ?, ?)');
	$stmt->bind_param('sss', $code, $label, $category);
	$stmt->execute();
	$stmt->close();
	header('Location: access_control.php?tab=permissions');
	exit;
}

// Fetch data for UI
$groups = [];
$res = $conn->query('SELECT id, name, description FROM access_groups ORDER BY name');
while ($r = $res->fetch_assoc()) { $groups[] = $r; }

$permissions = [];
$res = $conn->query('SELECT id, code, label, category FROM access_permissions ORDER BY category, code');
while ($r = $res->fetch_assoc()) { $permissions[] = $r; }

// Permissions tied to features removed from the UI (kept in DB for stability)
$deprecatedCodes = [
	'view_dashboard_jobs',
	'view_dashboard_job_seekers',
	'manage_job_seekers',
	'manage_jobs',
];

$groupIdToPermissionIds = [];
$res = $conn->query('SELECT group_id, permission_id FROM group_permissions');
while ($r = $res->fetch_assoc()) {
	$gid = intval($r['group_id']);
	$pid = intval($r['permission_id']);
	$groupIdToPermissionIds[$gid] = $groupIdToPermissionIds[$gid] ?? [];
	$groupIdToPermissionIds[$gid][] = $pid;
}

$userAccessRows = [];
$res = $conn->query('SELECT ua.id, ua.user_id, ua.account_type, ua.group_id, g.name AS group_name FROM user_access ua JOIN access_groups g ON g.id=ua.group_id ORDER BY ua.user_id');
while ($r = $res->fetch_assoc()) { $userAccessRows[] = $r; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Access Control</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<?php include 'navbar.php'; ?>
<div class="container py-4">
	<div class="d-flex justify-content-between align-items-center mb-3">
		<h2 class="mb-0">Access Control</h2>
	</div>
	<ul class="nav nav-tabs mb-3">
		<li class="nav-item"><a class="nav-link <?php echo $tab==='users'?'active':''; ?>" href="access_control.php?tab=users">Users</a></li>
		<li class="nav-item"><a class="nav-link <?php echo $tab==='groups'?'active':''; ?>" href="access_control.php?tab=groups">Groups</a></li>
		<li class="nav-item"><a class="nav-link <?php echo $tab==='permissions'?'active':''; ?>" href="access_control.php?tab=permissions">Permissions</a></li>
	</ul>

	<?php if ($tab === 'users'): ?>
	<div class="card mb-3">
		<div class="card-body">
			<h5 class="mb-3">Assign User to Group</h5>
			<form method="post" class="row g-2 align-items-end">
				<input type="hidden" name="action" value="save_user_access">
				<div class="col-12 col-md-3">
					<label class="form-label">User ID</label>
					<input type="number" class="form-control" name="user_id" required>
				</div>
				<div class="col-12 col-md-3">
					<label class="form-label">Account Type</label>
					<select class="form-select" name="account_type">
						<option value="super_admin">Super Admin</option>
						<option value="admin">Admin</option>
						<option value="staff" selected>Staff</option>
					</select>
				</div>
				<div class="col-12 col-md-4">
					<label class="form-label">Access Group</label>
					<select class="form-select" name="group_id" required>
						<?php foreach ($groups as $g): ?>
						<option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-12 col-md-2">
					<button class="btn btn-primary w-100" type="submit">Save</button>
				</div>
			</form>
		</div>
	</div>
	<div class="card">
		<div class="table-responsive">
			<table class="table table-striped mb-0">
				<thead>
					<tr>
						<th>User ID</th>
						<th>Account Type</th>
						<th>Group</th>
						<?php if (current_user_is_super_admin()): ?><th>Actions</th><?php endif; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($userAccessRows as $row): ?>
					<tr>
						<td><?php echo intval($row['user_id']); ?></td>
						<td><?php echo htmlspecialchars($row['account_type']); ?></td>
						<td><?php echo htmlspecialchars($row['group_name']); ?></td>
						<?php if (current_user_is_super_admin()): ?>
						<td>
							<form method="post" class="d-inline" onsubmit="return confirm('Delete this user account and its access mapping?');">
								<input type="hidden" name="action" value="delete_user">
								<input type="hidden" name="user_id" value="<?php echo intval($row['user_id']); ?>">
								<button type="submit" class="btn btn-sm btn-outline-danger">Delete User</button>
							</form>
						</td>
						<?php endif; ?>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php endif; ?>

	<?php if ($tab === 'groups'): ?>
	<div class="row g-3">
		<div class="col-12 col-lg-5">
			<div class="card">
				<div class="card-body">
					<h5 class="mb-3">Create / Edit Group</h5>
					<form method="post">
						<input type="hidden" name="action" value="save_group">
						<input type="hidden" name="group_id" id="group_id" value="">
						<div class="mb-2">
							<label class="form-label">Group Name</label>
							<input class="form-control" type="text" name="name" id="group_name" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Description</label>
							<input class="form-control" type="text" name="description" id="group_desc">
						</div>
						<div class="mb-3">
							<label class="form-label">Permissions</label>
							<div class="row">
								<?php
								$byCat = [];
								foreach ($permissions as $p) { $byCat[$p['category'] ?? 'Other'][] = $p; }
								foreach ($byCat as $cat => $items): ?>
								<div class="col-12 mb-2"><strong><?php echo htmlspecialchars($cat); ?></strong></div>
								<?php foreach ($items as $p): ?>
								<div class="col-12 col-md-6">
									<div class="form-check">
										<input class="form-check-input perm-checkbox" type="checkbox" name="permissions[]" value="<?php echo $p['id']; ?>" id="perm_<?php echo $p['id']; ?>">
							<label class="form-check-label" for="perm_<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['label']); ?> <span class="text-muted small">(<?php echo htmlspecialchars($p['code']); ?>)</span><?php if (in_array($p['code'], $deprecatedCodes, true)): ?><span class="badge bg-warning text-dark ms-1">deprecated</span><?php endif; ?></label>
									</div>
								</div>
								<?php endforeach; endforeach; ?>
							</div>
						</div>
						<button class="btn btn-primary" type="submit">Save Group</button>
					</form>
				</div>
			</div>
		</div>
		<div class="col-12 col-lg-7">
			<div class="card">
				<div class="table-responsive">
					<table class="table table-striped mb-0">
						<thead>
							<tr><th>Name</th><th>Description</th><th>Permissions</th><th>Actions</th></tr>
						</thead>
						<tbody>
						<?php foreach ($groups as $g): $permIds = $groupIdToPermissionIds[$g['id']] ?? []; ?>
						<tr>
							<td><?php echo htmlspecialchars($g['name']); ?></td>
							<td><?php echo htmlspecialchars($g['description'] ?? ''); ?></td>
							<td><?php echo count($permIds); ?> perms</td>
							<td>
								<button class="btn btn-sm btn-outline-primary" onclick='loadGroup(<?php echo $g['id']; ?>, <?php echo json_encode($g['name']); ?>, <?php echo json_encode($g['description']); ?>, <?php echo json_encode($permIds); ?>)'>Edit</button>
								<?php if (strtolower($g['name']) !== 'super admin'): ?>
								<form method="post" class="d-inline" onsubmit="return confirm('Delete this group?');">
									<input type="hidden" name="action" value="delete_group">
									<input type="hidden" name="group_id" value="<?php echo $g['id']; ?>">
									<button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
								</form>
								<?php endif; ?>
							</td>
						</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
	<script>
	function loadGroup(id, name, desc, permIds) {
		document.getElementById('group_id').value = id;
		document.getElementById('group_name').value = name || '';
		document.getElementById('group_desc').value = desc || '';
		for (const cb of document.querySelectorAll('.perm-checkbox')) { cb.checked = false; }
		if (Array.isArray(permIds)) {
			for (const pid of permIds) {
				const el = document.getElementById('perm_' + pid);
				if (el) el.checked = true;
			}
		}
		window.scrollTo({ top: 0, behavior: 'smooth' });
	}
	</script>
	<?php endif; ?>

	<?php if ($tab === 'permissions'): ?>
	<div class="row g-3">
		<div class="col-12 col-lg-5">
			<div class="card">
				<div class="card-body">
					<h5 class="mb-3">Add Permission</h5>
					<form method="post">
						<input type="hidden" name="action" value="add_permission">
						<div class="mb-2">
							<label class="form-label">Code</label>
						<input class="form-control" type="text" name="code" placeholder="e.g. use_broadcast" required>
						</div>
						<div class="mb-2">
							<label class="form-label">Label</label>
							<input class="form-control" type="text" name="label" placeholder="Readable name" required>
						</div>
						<div class="mb-3">
							<label class="form-label">Category</label>
							<input class="form-control" type="text" name="category" placeholder="e.g. Admin">
						</div>
						<button class="btn btn-primary" type="submit">Add</button>
					</form>
				</div>
			</div>
		</div>
		<div class="col-12 col-lg-7">
			<div class="card">
				<div class="table-responsive">
					<table class="table table-striped mb-0">
						<thead><tr><th>Code</th><th>Label</th><th>Category</th></tr></thead>
						<tbody>
						<?php foreach ($permissions as $p): ?>
						<tr>
						<td><code><?php echo htmlspecialchars($p['code']); ?></code><?php if (in_array($p['code'], $deprecatedCodes, true)): ?><span class="badge bg-warning text-dark ms-1">deprecated</span><?php endif; ?></td>
							<td><?php echo htmlspecialchars($p['label']); ?></td>
							<td><?php echo htmlspecialchars($p['category'] ?? ''); ?></td>
						</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
	<?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 