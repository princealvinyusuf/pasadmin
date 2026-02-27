<?php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Independent RBAC connection (job_admin_prod)
$ac_host = 'localhost';
$ac_user = 'root';
$ac_pass = '';
$ac_db = 'job_admin_prod';
$ac_conn = new mysqli($ac_host, $ac_user, $ac_pass, $ac_db);

function ac_ensure_tables(mysqli $conn): void {
	$conn->query("CREATE TABLE IF NOT EXISTS access_groups (
		id INT AUTO_INCREMENT PRIMARY KEY,
		name VARCHAR(100) NOT NULL UNIQUE,
		description VARCHAR(255) DEFAULT NULL
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	$conn->query("CREATE TABLE IF NOT EXISTS access_permissions (
		id INT AUTO_INCREMENT PRIMARY KEY,
		code VARCHAR(100) NOT NULL UNIQUE,
		label VARCHAR(255) NOT NULL,
		category VARCHAR(100) DEFAULT NULL
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	$conn->query("CREATE TABLE IF NOT EXISTS group_permissions (
		group_id INT NOT NULL,
		permission_id INT NOT NULL,
		PRIMARY KEY (group_id, permission_id),
		FOREIGN KEY (group_id) REFERENCES access_groups(id) ON DELETE CASCADE,
		FOREIGN KEY (permission_id) REFERENCES access_permissions(id) ON DELETE CASCADE
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	$conn->query("CREATE TABLE IF NOT EXISTS user_access (
		id INT AUTO_INCREMENT PRIMARY KEY,
		user_id INT NOT NULL UNIQUE,
		account_type VARCHAR(50) DEFAULT 'staff',
		group_id INT NOT NULL,
		FOREIGN KEY (group_id) REFERENCES access_groups(id) ON DELETE CASCADE
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function ac_seed_permissions(mysqli $conn): void {
	$perms = [
		['manage_access_control','Manage Access Control','Admin'],
		['view_dashboard_jobs','View Dashboard Jobs','Dashboard'],
		['view_dashboard_job_seekers','View Dashboard Job Seekers','Dashboard'],
		['view_dashboard_kebutuhan_tk','View Dashboard Kebutuhan Tenaga Kerja','Dashboard'],
		['view_dashboard_persediaan_tk','View Dashboard Persediaan Tenaga Kerja','Dashboard'],
		['manage_jobs','Manage Jobs','Data'],
		['manage_job_seekers','Manage Job Seekers','Data'],
		['registrasi_kehadiran_manage','Manage Registrasi Kehadiran','Data'],
		['manage_api_keys','Manage API Keys','API'],
		['view_cleansing','View Cleansing Pages','Tools'],
		['manage_settings','Manage All Settings','Settings (Global)'],
		['settings_chart_manage','Manage Chart Settings','Settings'],
		['settings_contribution_manage','Manage Contribution Settings','Settings'],
		['settings_information_manage','Manage Information Settings','Settings'],
		['settings_news_manage','Manage News Settings','Settings'],
		['settings_services_manage','Manage Services Settings','Settings'],
		['settings_statistics_manage','Manage Statistics Settings','Settings'],
		['settings_testimonials_manage','Manage Testimonial Settings','Settings'],
		['settings_top_list_manage','Manage Top List Settings','Settings'],
		['settings_agenda_manage','Manage Agenda Settings','Settings'],
		['settings_job_fair_manage','Manage Job Fair Settings','Settings'],
		['settings_virtual_karir_service_manage','Manage Virtual Karir Service Settings','Settings'],
		['settings_mitra_kerja_manage','Manage Mitra Kerja Settings','Settings'],
		['settings_mitra_submission_manage','Manage Mitra Kerja Submission','Settings'],
		['settings_kemitraan_booked_manage','Manage Kemitraan Booked','Settings'],
		['settings_pasker_room_manage','Manage Pasker Room Settings','Settings'],
		['settings_minijobi_manage','Manage miniJobi Jobs','Settings'],
		['settings_database_contact_manage','Manage Database Contact','Settings'],
		['settings_iframe_manage','Manage iFrame Settings','Settings'],
		['jejaring_tahapan_manage','Manage Jejaring Tahapan','Jejaring'],
		['settings_partnership_type_manage','Manage Partnership Types','Settings'],
		['walkin_gallery_manage','Manage Walk-in Gallery','Layanan'],
		['walkin_survey_manage','Manage Walk-in Survey','Layanan'],
		['use_broadcast','Use Broadcast','Tools'],
		['view_extensions','View Extensions','Tools'],
		['view_db_sessions','View Active DB Sessions','Tools'],
		['kill_db_session','Kill DB Session','Tools'],
		['view_audit_trails','View Audit Trails','Admin'],
		// Naker Award permissions
		['naker_award_manage_assessment','Naker Award: Manage Initial Assessment','Naker Award'],
		['naker_award_view_stage1','Naker Award: View Stage 1 Shortlisted C','Naker Award'],
		['naker_award_manage_second','Naker Award: Manage Second Assessment','Naker Award'],
		['naker_award_view_stage2','Naker Award: View Stage 2 Shortlisted C','Naker Award'],
		['naker_award_manage_third','Naker Award: Manage Third Assessment','Naker Award'],
		['naker_award_verify','Naker Award: Verification','Naker Award'],
		['naker_award_final_nominees','Naker Award: Final Nominees','Naker Award'],
		['naker_award_manage_weights','Naker Award: Manage Weights','Naker Award'],
		['naker_award_manage_intervals','Naker Award: Manage Intervals','Naker Award'],
		['naker_award_backup_nominees','Naker Award: Backup Nominees','Naker Award'],
		// AsMen (Asset Management) permissions
		['asmen_manage_assets','AsMen: Manage Assets','AsMen'],
		['asmen_view_services','AsMen: View Services','AsMen'],
		['asmen_view_calendar','AsMen: View Calendar','AsMen'],
		['asmen_view_dashboard','AsMen: View Dashboard','AsMen'],
		['asmen_use_qr','AsMen: Use QR','AsMen'],
		// Career Boost Day permissions
		['career_boost_day_manage','Career Boost Day: Manage Submissions','Career Boost Day'],
		['career_boost_day_pic_manage','Career Boost Day: Manage PIC','Career Boost Day'],
		['career_boost_day_booked_view','Career Boost Day: View Booked','Career Boost Day'],
		['career_boost_day_testimonial_manage','Career Boost Day: Manage Testimonials','Career Boost Day'],
		// Konseling permissions
		['form_hasil_konseling_manage','Form Hasil Konseling: Manage Submissions','Konseling']
	];
	$stmt = $conn->prepare('INSERT IGNORE INTO access_permissions (code,label,category) VALUES (?,?,?)');
	foreach ($perms as [$c,$l,$cat]) { $stmt->bind_param('sss',$c,$l,$cat); $stmt->execute(); }
	$stmt->close();

	// Ensure Super Admin group exists with all permissions
	$conn->query("INSERT IGNORE INTO access_groups (id, name, description) VALUES (1,'Super Admin','Full access to everything')");
	$res = $conn->query('SELECT id FROM access_permissions');
	$permIds = [];
	while ($r = $res->fetch_assoc()) { $permIds[] = intval($r['id']); }
	if (!empty($permIds)) {
		$ins = $conn->prepare('INSERT IGNORE INTO group_permissions (group_id, permission_id) VALUES (1, ?)');
		foreach ($permIds as $pid) { $ins->bind_param('i', $pid); $ins->execute(); }
		$ins->close();
	}
}

function ac_bootstrap_for_current_user(mysqli $conn): void {
	ac_ensure_tables($conn);
	ac_seed_permissions($conn);
	if (empty($_SESSION['user_id'])) { return; }
	$userId = intval($_SESSION['user_id']);
	$stmt = $conn->prepare('SELECT id FROM user_access WHERE user_id=?');
	$stmt->bind_param('i', $userId);
	$stmt->execute();
	$stmt->store_result();
	if ($stmt->num_rows === 0) {
		$stmt->close();
		$ins = $conn->prepare("INSERT INTO user_access (user_id, account_type, group_id) VALUES (?, 'super_admin', 1)");
		$ins->bind_param('i', $userId);
		$ins->execute();
		$ins->close();
	} else {
		$stmt->close();
	}
}

function ac_user_has_permission(mysqli $conn, int $userId, string $code): bool {
	$q = $conn->prepare('SELECT g.name FROM user_access ua JOIN access_groups g ON g.id=ua.group_id WHERE ua.user_id=?');
	$q->bind_param('i', $userId);
	$q->execute();
	$res = $q->get_result();
	$row = $res->fetch_assoc();
	$q->close();
	if ($row && strtolower($row['name']) === 'super admin') { return true; }
	$p = $conn->prepare('SELECT 1 FROM user_access ua JOIN group_permissions gp ON gp.group_id=ua.group_id JOIN access_permissions p ON p.id=gp.permission_id WHERE ua.user_id=? AND p.code=? LIMIT 1');
	$p->bind_param('is', $userId, $code);
	$p->execute();
	$p->store_result();
	$ok = $p->num_rows > 0;
	$p->close();
	return $ok;
}

function current_user_can(string $code): bool {
	if (empty($_SESSION['user_id'])) { return false; }
	global $ac_conn;
	return ac_user_has_permission($ac_conn, intval($_SESSION['user_id']), $code);
}

function current_user_is_super_admin(): bool {
	if (empty($_SESSION['user_id'])) { return false; }
	global $ac_conn;
	$userId = intval($_SESSION['user_id']);
	$q = $ac_conn->prepare('SELECT LOWER(g.name) FROM user_access ua JOIN access_groups g ON g.id=ua.group_id WHERE ua.user_id=?');
	$q->bind_param('i', $userId);
	$q->execute();
	$q->bind_result($gname);
	$q->fetch();
	$q->close();
	return $gname === 'super admin';
}

// Bootstrap RBAC store
ac_bootstrap_for_current_user($ac_conn); 