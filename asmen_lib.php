<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/access_helper.php';

function asmen_ensure_tables(mysqli $conn): void {
	// Assets master table
	$conn->query("CREATE TABLE IF NOT EXISTS asmen_assets (
		id INT AUTO_INCREMENT PRIMARY KEY,
		no VARCHAR(50) DEFAULT NULL,
		jenis_bmn VARCHAR(255) DEFAULT NULL,
		kode_satker VARCHAR(100) DEFAULT NULL,
		nama_satker VARCHAR(255) DEFAULT NULL,
		kode_barang VARCHAR(100) DEFAULT NULL,
		nup VARCHAR(100) DEFAULT NULL,
		nama_barang VARCHAR(255) DEFAULT NULL,
		merk VARCHAR(255) DEFAULT NULL,
		tipe VARCHAR(255) DEFAULT NULL,
		kondisi VARCHAR(100) DEFAULT NULL,
		umur_aset INT DEFAULT NULL,
		intra_extra VARCHAR(100) DEFAULT NULL,
		henti_guna VARCHAR(100) DEFAULT NULL,
		status_sbsn VARCHAR(100) DEFAULT NULL,
		status_bmn_idle VARCHAR(100) DEFAULT NULL,
		status_kemitraan VARCHAR(100) DEFAULT NULL,
		bpybds VARCHAR(100) DEFAULT NULL,
		usulan_barang_hilang VARCHAR(100) DEFAULT NULL,
		usulan_barang_rb VARCHAR(100) DEFAULT NULL,
		usul_hapus VARCHAR(100) DEFAULT NULL,
		hibah_dktp VARCHAR(100) DEFAULT NULL,
		konsensi_jasa VARCHAR(100) DEFAULT NULL,
		properti_investasi VARCHAR(100) DEFAULT NULL,
		jenis_dokumen VARCHAR(100) DEFAULT NULL,
		no_dokumen VARCHAR(100) DEFAULT NULL,
		no_bpkp VARCHAR(100) DEFAULT NULL,
		no_polisi VARCHAR(100) DEFAULT NULL,
		status_sertifikasi VARCHAR(100) DEFAULT NULL,
		jenis_sertipikat VARCHAR(100) DEFAULT NULL,
		no_sertifikat VARCHAR(150) DEFAULT NULL,
		nama VARCHAR(255) DEFAULT NULL,
		tanggal_buku_pertama DATE DEFAULT NULL,
		tanggal_perolehan DATE DEFAULT NULL,
		tanggal_pengapusan DATE DEFAULT NULL,
		nilai_perolehan_pertama DECIMAL(18,2) DEFAULT NULL,
		nilai_mutasi DECIMAL(18,2) DEFAULT NULL,
		nilai_perolehan DECIMAL(18,2) DEFAULT NULL,
		nilai_penyusutan DECIMAL(18,2) DEFAULT NULL,
		nilai_buku DECIMAL(18,2) DEFAULT NULL,
		luas_tanah_seluruhnya DECIMAL(18,2) DEFAULT NULL,
		luas_tanah_untuk_bangunan DECIMAL(18,2) DEFAULT NULL,
		luas_tanah_untuk_sarana_lingkungan DECIMAL(18,2) DEFAULT NULL,
		luas_lahan_kosong DECIMAL(18,2) DEFAULT NULL,
		luas_bangunan DECIMAL(18,2) DEFAULT NULL,
		luas_tapak_bangunan DECIMAL(18,2) DEFAULT NULL,
		luas_pemanfaatan DECIMAL(18,2) DEFAULT NULL,
		jumlah_lantai INT DEFAULT NULL,
		jumlah_foto INT DEFAULT NULL,
		status_penggunaan VARCHAR(100) DEFAULT NULL,
		no_psp VARCHAR(150) DEFAULT NULL,
		tanggal_psp DATE DEFAULT NULL,
		alamat VARCHAR(500) DEFAULT NULL,
		rt_rw VARCHAR(50) DEFAULT NULL,
		kelurahan_desa VARCHAR(150) DEFAULT NULL,
		kecamatan VARCHAR(150) DEFAULT NULL,
		kab_kota VARCHAR(150) DEFAULT NULL,
		kode_kab_kota VARCHAR(50) DEFAULT NULL,
		provinsi VARCHAR(150) DEFAULT NULL,
		kode_provinsi VARCHAR(50) DEFAULT NULL,
		kode_pos VARCHAR(20) DEFAULT NULL,
		sbsk VARCHAR(100) DEFAULT NULL,
		optimalisasi VARCHAR(100) DEFAULT NULL,
		penghuni VARCHAR(150) DEFAULT NULL,
		pengguna VARCHAR(150) DEFAULT NULL,
		kode_kpknl VARCHAR(100) DEFAULT NULL,
		uraian_kpknl VARCHAR(255) DEFAULT NULL,
		uraian_kanwil_djkn VARCHAR(255) DEFAULT NULL,
		nama_kl VARCHAR(255) DEFAULT NULL,
		nama_e1 VARCHAR(255) DEFAULT NULL,
		nama_korwil VARCHAR(255) DEFAULT NULL,
		kode_register VARCHAR(150) DEFAULT NULL,
		lokasi_ruang VARCHAR(255) DEFAULT NULL,
		-- AsMen operational fields
		qr_secret VARCHAR(64) DEFAULT NULL,
		service_interval_months INT DEFAULT NULL,
		last_service_date DATE DEFAULT NULL,
		next_service_date DATE DEFAULT NULL,
		service_priority ENUM('Low','Medium','High') DEFAULT 'Medium',
		service_reason VARCHAR(500) DEFAULT NULL,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		KEY idx_kode_barang (kode_barang),
		KEY idx_nup (nup),
		KEY idx_no_polisi (no_polisi),
		KEY idx_kode_register (kode_register),
		UNIQUE KEY uq_qr_secret (qr_secret)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

	// Service history table
	$conn->query("CREATE TABLE IF NOT EXISTS asmen_service_history (
		id INT AUTO_INCREMENT PRIMARY KEY,
		asset_id INT NOT NULL,
		service_date DATE NOT NULL,
		action VARCHAR(150) NOT NULL,
		notes VARCHAR(1000) DEFAULT NULL,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		FOREIGN KEY (asset_id) REFERENCES asmen_assets(id) ON DELETE CASCADE,
		KEY idx_asset_date (asset_id, service_date)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function asmen_random_hex(int $length = 32): string {
	$bytes = random_bytes((int)ceil($length / 2));
	return substr(bin2hex($bytes), 0, $length);
}

function asmen_ensure_qr_secret(mysqli $conn, int $assetId): string {
	$sel = $conn->prepare('SELECT qr_secret FROM asmen_assets WHERE id=?');
	$sel->bind_param('i', $assetId);
	$sel->execute();
	$sel->bind_result($secret);
	$sel->fetch();
	$sel->close();
	if (!empty($secret)) { return $secret; }
	$secret = asmen_random_hex(32);
	$upd = $conn->prepare('UPDATE asmen_assets SET qr_secret=? WHERE id=?');
	$upd->bind_param('si', $secret, $assetId);
	$upd->execute();
	$upd->close();
	return $secret;
}

function asmen_compute_service_plan(array $asset): array {
	$kondisi = strtolower(trim((string)($asset['kondisi'] ?? '')));
	$namaBarang = strtolower(trim((string)($asset['nama_barang'] ?? '')));
	$jenisBmn = strtolower(trim((string)($asset['jenis_bmn'] ?? '')));
	$noPolisi = trim((string)($asset['no_polisi'] ?? ''));
	$umur = intval($asset['umur_aset'] ?? 0); // assume years

	$interval = 12; // default months
	$priority = 'Medium';
	$reason = [];

	if ($noPolisi !== '') { $interval = 6; $reason[] = 'Vehicle (no_polisi present)'; }
	if (preg_match('/(laptop|komputer|pc|printer|server)/i', $namaBarang)) { $interval = min($interval, 12); $reason[] = 'IT equipment'; }
	if (preg_match('/(tanah|bangunan|gedung)/i', $jenisBmn)) { $interval = max($interval, 24); $reason[] = 'Land/Building'; }

	if ($umur >= 5) { $interval = (int)ceil($interval * 0.75); $reason[] = 'Older asset (umur_aset >= 5y)'; }
	if (strpos($kondisi, 'rusak') !== false) { $interval = 1; $priority = 'High'; $reason[] = 'Condition indicates damage'; }

	$lastService = !empty($asset['last_service_date']) ? strtotime($asset['last_service_date']) : null;
	$baseDate = $lastService ? $lastService : time();
	$nextDate = (new DateTime('@' . $baseDate))->setTimezone(new DateTimeZone(date_default_timezone_get()));
	$nextDate->modify('+' . $interval . ' months');

	return [
		'interval_months' => $interval,
		'next_service_date' => $nextDate->format('Y-m-d'),
		'priority' => $priority,
		'reason' => implode('; ', $reason)
	];
}

// Ensure tables are present on include
asmen_ensure_tables($conn);
?>


