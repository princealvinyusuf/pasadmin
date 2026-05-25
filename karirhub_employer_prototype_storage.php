<?php

if (!function_exists('kh_proto_derive_period')) {
    function kh_proto_derive_period(string $periodeTipe, string $anchorDate): array
    {
        $anchorTs = strtotime($anchorDate);
        if ($anchorTs === false) {
            $anchorTs = time();
        }
        $anchor = date('Y-m-d', $anchorTs);
        $tipe = strtolower(trim($periodeTipe)) === 'weekly' ? 'weekly' : 'monthly';
        if ($tipe === 'weekly') {
            $dayOfWeek = (int)date('N', $anchorTs); // 1..7
            $mulaiTs = strtotime('-' . ($dayOfWeek - 1) . ' days', $anchorTs);
            $selesaiTs = strtotime('+' . (7 - $dayOfWeek) . ' days', $anchorTs);
            return [
                'tipe' => 'weekly',
                'anchor' => $anchor,
                'mulai' => date('Y-m-d', $mulaiTs),
                'selesai' => date('Y-m-d', $selesaiTs),
            ];
        }

        return [
            'tipe' => 'monthly',
            'anchor' => $anchor,
            'mulai' => date('Y-m-01', $anchorTs),
            'selesai' => date('Y-m-t', $anchorTs),
        ];
    }
}

if (!function_exists('kh_proto_generate_no_reg_from_anchor')) {
    function kh_proto_generate_no_reg_from_anchor(mysqli $conn, string $anchorDate): string
    {
        $anchorTs = strtotime($anchorDate);
        if ($anchorTs === false) {
            $anchorTs = time();
        }
        $prefix = 'WLLP-57' . date('ym', $anchorTs) . '-';
        $regex = '^' . preg_quote($prefix, '/') . '[0-9]{8}$';

        $stmt = $conn->prepare("
            SELECT COALESCE(MAX(CAST(RIGHT(no_reg_bukti, 8) AS UNSIGNED)), 0) AS max_seq
            FROM karirhub_proto_wllp_laporan
            WHERE no_reg_bukti LIKE CONCAT(?, '%')
              AND no_reg_bukti REGEXP ?
        ");
        $stmt->bind_param('ss', $prefix, $regex);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        $nextSeq = ((int)($row['max_seq'] ?? 0)) + 1;
        return $prefix . str_pad((string)$nextSeq, 8, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('kh_proto_generate_id_lowongan')) {
    function kh_proto_generate_id_lowongan(mysqli $conn): string
    {
        $stmt = $conn->prepare("
            SELECT COALESCE(MAX(CAST(SUBSTRING(id_lowongan, 4) AS UNSIGNED)), 0) AS max_seq
            FROM karirhub_proto_wllp_pelaporan
            WHERE id_lowongan REGEXP '^LK-[0-9]{6}$'
        ");
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        $nextSeq = ((int)($row['max_seq'] ?? 0)) + 1;
        return 'LK-' . str_pad((string)$nextSeq, 6, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('kh_proto_ensure_multi_tables')) {
    function kh_proto_ensure_multi_tables(mysqli $conn): void
    {
        $conn->query("
            CREATE TABLE IF NOT EXISTS karirhub_proto_wllp_laporan (
                no_reg_bukti VARCHAR(60) PRIMARY KEY,
                unit_kode VARCHAR(40) NOT NULL,
                unit_nama VARCHAR(255) NOT NULL,
                periode_tipe ENUM('weekly','monthly') NOT NULL DEFAULT 'monthly',
                periode_anchor DATE NOT NULL,
                periode_mulai DATE NOT NULL,
                periode_selesai DATE NOT NULL,
                status_verifikasi VARCHAR(60) NOT NULL DEFAULT 'Terverifikasi',
                catatan TEXT DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS karirhub_proto_wllp_pelaporan (
                no_reg_bukti VARCHAR(60) NOT NULL,
                id_lowongan VARCHAR(30) NOT NULL,
                unit_kode VARCHAR(40) NOT NULL,
                unit_nama VARCHAR(255) NOT NULL,
                jabatan VARCHAR(200) NOT NULL,
                jumlah_kebutuhan INT NOT NULL,
                jenis_kelamin VARCHAR(30) NOT NULL,
                usia_min INT NOT NULL,
                usia_max INT NOT NULL,
                pendidikan_minimal VARCHAR(120) NOT NULL,
                deskripsi_pekerjaan TEXT NOT NULL,
                keterampilan_utama TEXT NOT NULL,
                pengalaman_min_tahun INT NOT NULL,
                rentang_gaji VARCHAR(120) NOT NULL,
                kode_kbji VARCHAR(50) NOT NULL,
                provinsi VARCHAR(120) NOT NULL,
                kota VARCHAR(120) NOT NULL,
                kecamatan VARCHAR(120) NOT NULL,
                kelurahan VARCHAR(120) NOT NULL,
                bidang_pekerjaan VARCHAR(180) NOT NULL,
                industri_sektor VARCHAR(180) NOT NULL,
                status_pernikahan VARCHAR(40) NOT NULL,
                tipe_kerja VARCHAR(40) NOT NULL DEFAULT '',
                masa_berlaku_mulai DATE NOT NULL,
                masa_berlaku_sampai DATE NOT NULL,
                alamat_url_postingan_loker VARCHAR(500) NOT NULL,
                catatan TEXT DEFAULT NULL,
                status_verifikasi VARCHAR(60) NOT NULL DEFAULT 'Terverifikasi',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (no_reg_bukti, id_lowongan)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $conn->query("ALTER TABLE karirhub_proto_wllp_pelaporan ADD COLUMN IF NOT EXISTS tipe_kerja VARCHAR(40) NOT NULL DEFAULT '' AFTER status_pernikahan");

        $conn->query("
            CREATE TABLE IF NOT EXISTS karirhub_proto_wllp_status (
                no_reg_bukti VARCHAR(60) NOT NULL,
                id_lowongan VARCHAR(30) NOT NULL,
                jabatan VARCHAR(200) NOT NULL,
                unit_nama VARCHAR(255) NOT NULL,
                status_saat_ini VARCHAR(50) NOT NULL,
                tanggal_lapor DATE NOT NULL,
                tanggal_terisi DATE DEFAULT NULL,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (no_reg_bukti, id_lowongan)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS karirhub_proto_wllp_penempatan (
                no_reg_bukti VARCHAR(60) NOT NULL,
                id_lowongan VARCHAR(30) NOT NULL,
                nik VARCHAR(30) NOT NULL,
                nama_lengkap VARCHAR(180) NOT NULL,
                pendidikan VARCHAR(120) NOT NULL,
                jenis_kelamin VARCHAR(30) NOT NULL,
                tempat_lahir VARCHAR(120) NOT NULL,
                tanggal_lahir DATE NOT NULL,
                alamat TEXT NOT NULL,
                status_disabilitas VARCHAR(10) NOT NULL,
                tmt DATE NOT NULL,
                email VARCHAR(180) NOT NULL,
                nomor_hp VARCHAR(40) NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (no_reg_bukti, id_lowongan)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}

if (!function_exists('kh_proto_seed_multi_from_dataset')) {
    function kh_proto_seed_multi_from_dataset(mysqli $conn, array $dataset, array $units): void
    {
        $res = $conn->query("SELECT COUNT(*) AS c FROM karirhub_proto_wllp_pelaporan");
        $count = (int)($res->fetch_assoc()['c'] ?? 0);
        if ($count > 0) {
            return;
        }

        $vacancies = $dataset['vacancies'] ?? [];
        if (empty($vacancies)) {
            return;
        }

        $stmtHeader = $conn->prepare("
            INSERT INTO karirhub_proto_wllp_laporan
            (no_reg_bukti, unit_kode, unit_nama, periode_tipe, periode_anchor, periode_mulai, periode_selesai, status_verifikasi, catatan)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                unit_kode = VALUES(unit_kode),
                unit_nama = VALUES(unit_nama),
                periode_tipe = VALUES(periode_tipe),
                periode_anchor = VALUES(periode_anchor),
                periode_mulai = VALUES(periode_mulai),
                periode_selesai = VALUES(periode_selesai),
                status_verifikasi = VALUES(status_verifikasi),
                catatan = VALUES(catatan)
        ");
        $stmtItem = $conn->prepare("
            INSERT INTO karirhub_proto_wllp_pelaporan
            (no_reg_bukti, id_lowongan, unit_kode, unit_nama, jabatan, jumlah_kebutuhan, jenis_kelamin, usia_min, usia_max, pendidikan_minimal,
             deskripsi_pekerjaan, keterampilan_utama, pengalaman_min_tahun, rentang_gaji, kode_kbji, provinsi, kota, kecamatan, kelurahan,
             bidang_pekerjaan, industri_sektor, status_pernikahan, tipe_kerja, masa_berlaku_mulai, masa_berlaku_sampai, alamat_url_postingan_loker,
             catatan, status_verifikasi)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtStatus = $conn->prepare("
            INSERT INTO karirhub_proto_wllp_status
            (no_reg_bukti, id_lowongan, jabatan, unit_nama, status_saat_ini, tanggal_lapor, tanggal_terisi)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($vacancies as $row) {
            $noReg = (string)($row['no_reg_bukti'] ?? '');
            $idLowongan = (string)($row['id_lowongan'] ?? '');
            if ($noReg === '' || $idLowongan === '') {
                continue;
            }
            $unitKode = (string)($row['unit_kode'] ?? '');
            $unitNama = (string)($units[$unitKode]['nama'] ?? $unitKode);
            $tanggalLapor = (string)($row['tanggal_lapor'] ?? date('Y-m-d'));
            $period = kh_proto_derive_period('monthly', $tanggalLapor);

            $statusVerifikasi = (string)($row['status_verifikasi'] ?? 'Terverifikasi');
            $catatan = (string)($row['catatan'] ?? '');
            $stmtHeader->bind_param(
                'sssssssss',
                $noReg,
                $unitKode,
                $unitNama,
                $period['tipe'],
                $period['anchor'],
                $period['mulai'],
                $period['selesai'],
                $statusVerifikasi,
                $catatan
            );
            $stmtHeader->execute();

            $jabatan = (string)($row['jabatan'] ?? '');
            $jumlahKebutuhan = (int)($row['jumlah_kebutuhan'] ?? 0);
            $jenisKelamin = (string)($row['jenis_kelamin'] ?? 'Semua');
            $usiaMin = (int)($row['usia_min'] ?? 18);
            $usiaMax = (int)($row['usia_max'] ?? 35);
            $pendidikanMinimal = (string)($row['pendidikan_minimal'] ?? '-');
            $deskripsiPekerjaan = (string)($row['deskripsi_pekerjaan'] ?? $jabatan);
            $keterampilanUtama = (string)($row['keterampilan_utama'] ?? '-');
            $pengalamanMin = (int)($row['pengalaman_min_tahun'] ?? 0);
            $rentangGaji = (string)($row['rentang_gaji'] ?? '-');
            $kodeKbji = (string)($row['kode_kbji'] ?? '');
            $provinsi = (string)($row['provinsi'] ?? ($units[$unitKode]['provinsi'] ?? ''));
            $kota = (string)($row['kota'] ?? ($units[$unitKode]['kota'] ?? ''));
            $kecamatan = (string)($row['kecamatan'] ?? '');
            $kelurahan = (string)($row['kelurahan'] ?? '');
            $bidangPekerjaan = (string)($row['bidang_pekerjaan'] ?? '');
            $industriSektor = (string)($row['industri_sektor'] ?? '');
            $statusPernikahan = (string)($row['status_pernikahan'] ?? '');
            $tipeKerja = (string)($row['tipe_kerja'] ?? ($row['employment_type'] ?? ''));
            $masaMulai = (string)($row['masa_berlaku_mulai'] ?? $tanggalLapor);
            $masaSampai = (string)($row['masa_berlaku_sampai'] ?? $tanggalLapor);
            $urlPosting = (string)($row['alamat_url_postingan_loker'] ?? '');
            $catatanItem = (string)($row['catatan'] ?? '');

            $stmtItem->bind_param(
                str_repeat('s', 28),
                $noReg, $idLowongan, $unitKode, $unitNama, $jabatan, $jumlahKebutuhan, $jenisKelamin, $usiaMin, $usiaMax, $pendidikanMinimal,
                $deskripsiPekerjaan, $keterampilanUtama, $pengalamanMin, $rentangGaji, $kodeKbji, $provinsi, $kota, $kecamatan, $kelurahan,
                $bidangPekerjaan, $industriSektor, $statusPernikahan, $tipeKerja, $masaMulai, $masaSampai, $urlPosting, $catatanItem, $statusVerifikasi
            );
            $stmtItem->execute();

            $statusSaatIni = (string)($row['status_keterisian'] ?? 'Belum Terisi');
            $tanggalTerisi = (string)($row['tanggal_terisi'] ?? '');
            if ($tanggalTerisi === '') {
                $tanggalTerisi = null;
            }
            $stmtStatus->bind_param('sssssss', $noReg, $idLowongan, $jabatan, $unitNama, $statusSaatIni, $tanggalLapor, $tanggalTerisi);
            $stmtStatus->execute();
        }

        $stmtHeader->close();
        $stmtItem->close();
        $stmtStatus->close();
    }
}

