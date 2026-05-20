<?php

if (!function_exists('karirhub_proto_dataset')) {
    function karirhub_proto_dataset(): array
    {
        static $dataset = null;
        if ($dataset !== null) {
            return $dataset;
        }

        $units = [
            'UNIT-001' => ['kode' => 'UNIT-001', 'nama' => 'PT Contoh Nusantara - Kantor Pusat', 'kota' => 'Jakarta Selatan', 'provinsi' => 'DKI Jakarta'],
            'UNIT-002' => ['kode' => 'UNIT-002', 'nama' => 'PT Contoh Nusantara - Cabang Bandung', 'kota' => 'Bandung', 'provinsi' => 'Jawa Barat'],
            'UNIT-003' => ['kode' => 'UNIT-003', 'nama' => 'PT Contoh Nusantara - Cabang Surabaya', 'kota' => 'Surabaya', 'provinsi' => 'Jawa Timur'],
        ];

        $vacancies = [
            [
                'id_lowongan' => 'LK-000987',
                'no_reg_bukti' => 'WLLP-2026-0519-001278',
                'unit_kode' => 'UNIT-001',
                'jabatan' => 'Staff Operasional',
                'jumlah_kebutuhan' => 4,
                'jenis_kelamin' => 'Semua',
                'usia_min' => 21,
                'usia_max' => 35,
                'pendidikan_minimal' => 'D3',
                'keterampilan_utama' => 'Administrasi Operasional, Microsoft Office, Komunikasi',
                'pengalaman_min_tahun' => 1,
                'rentang_gaji' => 'Rp4.500.000 - Rp6.000.000',
                'domisili_kerja' => 'Jakarta Selatan',
                'mode_publikasi' => 'Publik',
                'status_lowongan' => 'Aktif',
                'status_keterisian' => 'Belum Terisi',
                'tanggal_lapor' => '2026-05-19',
                'masa_berlaku_mulai' => '2026-05-19',
                'masa_berlaku_sampai' => '2026-06-20',
                'tanggal_terisi' => null,
                'petugas_input' => 'admin@contoh.co.id',
                'status_verifikasi' => 'Terverifikasi',
                'catatan' => 'Prioritas untuk kandidat domisili Jabodetabek',
            ],
            [
                'id_lowongan' => 'LK-000984',
                'no_reg_bukti' => 'WLLP-2026-0518-001249',
                'unit_kode' => 'UNIT-001',
                'jabatan' => 'Admin HR',
                'jumlah_kebutuhan' => 2,
                'jenis_kelamin' => 'Semua',
                'usia_min' => 22,
                'usia_max' => 35,
                'pendidikan_minimal' => 'S1',
                'keterampilan_utama' => 'Administrasi HRIS, Rekrutmen, Komunikasi',
                'pengalaman_min_tahun' => 2,
                'rentang_gaji' => 'Rp5.500.000 - Rp7.500.000',
                'domisili_kerja' => 'Jakarta Selatan',
                'mode_publikasi' => 'Publik',
                'status_lowongan' => 'Aktif',
                'status_keterisian' => 'Proses Seleksi',
                'tanggal_lapor' => '2026-05-18',
                'masa_berlaku_mulai' => '2026-05-18',
                'masa_berlaku_sampai' => '2026-06-18',
                'tanggal_terisi' => null,
                'petugas_input' => 'hr@contoh.co.id',
                'status_verifikasi' => 'Terverifikasi',
                'catatan' => 'Sudah shortlist 7 kandidat',
            ],
            [
                'id_lowongan' => 'LK-000971',
                'no_reg_bukti' => 'WLLP-2026-0514-001180',
                'unit_kode' => 'UNIT-002',
                'jabatan' => 'Digital Marketing',
                'jumlah_kebutuhan' => 1,
                'jenis_kelamin' => 'Semua',
                'usia_min' => 22,
                'usia_max' => 32,
                'pendidikan_minimal' => 'S1',
                'keterampilan_utama' => 'Meta Ads, Google Ads, Copywriting',
                'pengalaman_min_tahun' => 2,
                'rentang_gaji' => 'Rp5.000.000 - Rp7.000.000',
                'domisili_kerja' => 'Bandung',
                'mode_publikasi' => 'Administratif',
                'status_lowongan' => 'Aktif',
                'status_keterisian' => 'Belum Update',
                'tanggal_lapor' => '2026-05-14',
                'masa_berlaku_mulai' => '2026-05-14',
                'masa_berlaku_sampai' => '2026-06-14',
                'tanggal_terisi' => null,
                'petugas_input' => 'hrd.bdg@contoh.co.id',
                'status_verifikasi' => 'Perlu Update',
                'catatan' => 'Belum kirim update status keterisian minggu ini',
            ],
            [
                'id_lowongan' => 'LK-000954',
                'no_reg_bukti' => 'WLLP-2026-0510-001032',
                'unit_kode' => 'UNIT-003',
                'jabatan' => 'Finance Officer',
                'jumlah_kebutuhan' => 2,
                'jenis_kelamin' => 'Semua',
                'usia_min' => 23,
                'usia_max' => 36,
                'pendidikan_minimal' => 'S1',
                'keterampilan_utama' => 'General Ledger, Pajak, Rekonsiliasi',
                'pengalaman_min_tahun' => 2,
                'rentang_gaji' => 'Rp6.000.000 - Rp8.000.000',
                'domisili_kerja' => 'Surabaya',
                'mode_publikasi' => 'Publik',
                'status_lowongan' => 'Tidak Aktif',
                'status_keterisian' => 'Terisi',
                'tanggal_lapor' => '2026-05-10',
                'masa_berlaku_mulai' => '2026-05-10',
                'masa_berlaku_sampai' => '2026-06-10',
                'tanggal_terisi' => '2026-05-17',
                'petugas_input' => 'finance.hr@contoh.co.id',
                'status_verifikasi' => 'Terverifikasi',
                'catatan' => 'Posisi terisi sesuai SLA',
            ],
            [
                'id_lowongan' => 'LK-000944',
                'no_reg_bukti' => 'WLLP-2026-0508-000994',
                'unit_kode' => 'UNIT-002',
                'jabatan' => 'Supervisor Gudang',
                'jumlah_kebutuhan' => 3,
                'jenis_kelamin' => 'Semua',
                'usia_min' => 24,
                'usia_max' => 40,
                'pendidikan_minimal' => 'D3',
                'keterampilan_utama' => 'Warehouse Management, SAP, Leadership',
                'pengalaman_min_tahun' => 3,
                'rentang_gaji' => 'Rp6.500.000 - Rp9.000.000',
                'domisili_kerja' => 'Bandung',
                'mode_publikasi' => 'Publik',
                'status_lowongan' => 'Aktif',
                'status_keterisian' => 'Proses Seleksi',
                'tanggal_lapor' => '2026-05-08',
                'masa_berlaku_mulai' => '2026-05-08',
                'masa_berlaku_sampai' => '2026-06-08',
                'tanggal_terisi' => null,
                'petugas_input' => 'ops.bdg@contoh.co.id',
                'status_verifikasi' => 'Terverifikasi',
                'catatan' => 'Interview final 3 kandidat',
            ],
            [
                'id_lowongan' => 'LK-000931',
                'no_reg_bukti' => 'WLLP-2026-0505-000950',
                'unit_kode' => 'UNIT-003',
                'jabatan' => 'Customer Service',
                'jumlah_kebutuhan' => 5,
                'jenis_kelamin' => 'Semua',
                'usia_min' => 20,
                'usia_max' => 30,
                'pendidikan_minimal' => 'SMA/SMK',
                'keterampilan_utama' => 'Komunikasi, Problem Solving, CRM',
                'pengalaman_min_tahun' => 0,
                'rentang_gaji' => 'Rp4.200.000 - Rp5.200.000',
                'domisili_kerja' => 'Surabaya',
                'mode_publikasi' => 'Publik',
                'status_lowongan' => 'Tidak Aktif',
                'status_keterisian' => 'Terisi',
                'tanggal_lapor' => '2026-05-05',
                'masa_berlaku_mulai' => '2026-05-05',
                'masa_berlaku_sampai' => '2026-06-05',
                'tanggal_terisi' => '2026-05-15',
                'petugas_input' => 'recruit.sby@contoh.co.id',
                'status_verifikasi' => 'Terverifikasi',
                'catatan' => '5 posisi terisi',
            ],
            [
                'id_lowongan' => 'LK-000922',
                'no_reg_bukti' => 'WLLP-2026-0503-000918',
                'unit_kode' => 'UNIT-001',
                'jabatan' => 'Legal Officer',
                'jumlah_kebutuhan' => 1,
                'jenis_kelamin' => 'Semua',
                'usia_min' => 24,
                'usia_max' => 38,
                'pendidikan_minimal' => 'S1 Hukum',
                'keterampilan_utama' => 'Contract Drafting, Compliance, Litigasi',
                'pengalaman_min_tahun' => 2,
                'rentang_gaji' => 'Rp7.000.000 - Rp9.500.000',
                'domisili_kerja' => 'Jakarta Selatan',
                'mode_publikasi' => 'Administratif',
                'status_lowongan' => 'Aktif',
                'status_keterisian' => 'Belum Update',
                'tanggal_lapor' => '2026-05-03',
                'masa_berlaku_mulai' => '2026-05-03',
                'masa_berlaku_sampai' => '2026-06-03',
                'tanggal_terisi' => null,
                'petugas_input' => 'legal@contoh.co.id',
                'status_verifikasi' => 'Perlu Update',
                'catatan' => 'Menunggu update status pelamar internal',
            ],
            [
                'id_lowongan' => 'LK-000910',
                'no_reg_bukti' => 'WLLP-2026-0501-000887',
                'unit_kode' => 'UNIT-003',
                'jabatan' => 'IT Support',
                'jumlah_kebutuhan' => 2,
                'jenis_kelamin' => 'Semua',
                'usia_min' => 21,
                'usia_max' => 34,
                'pendidikan_minimal' => 'D3 Teknik Informatika',
                'keterampilan_utama' => 'Troubleshooting, Networking, Helpdesk',
                'pengalaman_min_tahun' => 1,
                'rentang_gaji' => 'Rp5.000.000 - Rp6.500.000',
                'domisili_kerja' => 'Surabaya',
                'mode_publikasi' => 'Publik',
                'status_lowongan' => 'Aktif',
                'status_keterisian' => 'Belum Terisi',
                'tanggal_lapor' => '2026-05-01',
                'masa_berlaku_mulai' => '2026-05-01',
                'masa_berlaku_sampai' => '2026-06-01',
                'tanggal_terisi' => null,
                'petugas_input' => 'it.hr@contoh.co.id',
                'status_verifikasi' => 'Terverifikasi',
                'catatan' => 'Kandidat teknis belum sesuai standar',
            ],
        ];

        $jobOrderMap = [
            'LK-000987' => [
                'job_order_no' => 'JO-2026-OPS-001',
                'job_order_revision' => 'REV-02',
                'job_order_tanggal' => '2026-05-18',
                'job_order_status' => 'Approved',
                'job_order_priority' => 'High',
                'requested_by' => 'Rudi Hartono',
                'requester_divisi' => 'Operasional',
                'hiring_manager' => 'Nina Amelia',
                'cost_center' => 'CC-OPS-01',
                'employment_type' => 'PKWTT',
                'work_setup' => 'Onsite',
                'shift_type' => 'Shift',
                'lokasi_penempatan_detail' => 'Gudang Utama Pasar Minggu',
                'sumber_rekrutmen' => 'Karirhub + Referral',
                'target_tgl_join' => '2026-06-10',
                'sla_hiring_hari' => 21,
                'jumlah_lamaran_masuk' => 56,
                'jumlah_shortlist' => 13,
                'jumlah_interview' => 8,
                'jumlah_offer' => 4,
                'approval_state' => 'Final Approved',
                'approval_by' => 'Director Operations',
                'approval_date' => '2026-05-18',
                'budget_status' => 'On Budget',
            ],
            'LK-000984' => [
                'job_order_no' => 'JO-2026-HR-004',
                'job_order_revision' => 'REV-01',
                'job_order_tanggal' => '2026-05-17',
                'job_order_status' => 'Approved',
                'job_order_priority' => 'Medium',
                'requested_by' => 'Maya Putri',
                'requester_divisi' => 'Human Capital',
                'hiring_manager' => 'Agus Wibowo',
                'cost_center' => 'CC-HR-02',
                'employment_type' => 'PKWTT',
                'work_setup' => 'Hybrid',
                'shift_type' => 'Non-Shift',
                'lokasi_penempatan_detail' => 'Head Office - Lantai 12',
                'sumber_rekrutmen' => 'Karirhub',
                'target_tgl_join' => '2026-06-15',
                'sla_hiring_hari' => 30,
                'jumlah_lamaran_masuk' => 42,
                'jumlah_shortlist' => 9,
                'jumlah_interview' => 7,
                'jumlah_offer' => 2,
                'approval_state' => 'Final Approved',
                'approval_by' => 'Chief HR Officer',
                'approval_date' => '2026-05-17',
                'budget_status' => 'On Budget',
            ],
            'LK-000971' => [
                'job_order_no' => 'JO-2026-MKT-003',
                'job_order_revision' => 'REV-03',
                'job_order_tanggal' => '2026-05-13',
                'job_order_status' => 'Need Update',
                'job_order_priority' => 'Medium',
                'requested_by' => 'Santi Wulandari',
                'requester_divisi' => 'Marketing',
                'hiring_manager' => 'Yogi Pratama',
                'cost_center' => 'CC-MKT-03',
                'employment_type' => 'PKWT 12 Bulan',
                'work_setup' => 'Hybrid',
                'shift_type' => 'Non-Shift',
                'lokasi_penempatan_detail' => 'Bandung Creative Office',
                'sumber_rekrutmen' => 'Karirhub (Administratif)',
                'target_tgl_join' => '2026-06-20',
                'sla_hiring_hari' => 25,
                'jumlah_lamaran_masuk' => 18,
                'jumlah_shortlist' => 4,
                'jumlah_interview' => 2,
                'jumlah_offer' => 0,
                'approval_state' => 'Needs Revalidation',
                'approval_by' => 'VP Marketing',
                'approval_date' => '2026-05-13',
                'budget_status' => 'Pending Confirmation',
            ],
            'LK-000954' => [
                'job_order_no' => 'JO-2026-FIN-006',
                'job_order_revision' => 'REV-01',
                'job_order_tanggal' => '2026-05-09',
                'job_order_status' => 'Closed',
                'job_order_priority' => 'High',
                'requested_by' => 'Dewi Sartika',
                'requester_divisi' => 'Finance',
                'hiring_manager' => 'Benny Cahyono',
                'cost_center' => 'CC-FIN-01',
                'employment_type' => 'PKWTT',
                'work_setup' => 'Onsite',
                'shift_type' => 'Non-Shift',
                'lokasi_penempatan_detail' => 'Surabaya Branch Office',
                'sumber_rekrutmen' => 'Karirhub + Internal Mobility',
                'target_tgl_join' => '2026-05-20',
                'sla_hiring_hari' => 20,
                'jumlah_lamaran_masuk' => 31,
                'jumlah_shortlist' => 6,
                'jumlah_interview' => 4,
                'jumlah_offer' => 2,
                'approval_state' => 'Completed',
                'approval_by' => 'Finance Director',
                'approval_date' => '2026-05-09',
                'budget_status' => 'On Budget',
            ],
        ];

        $jobOrderDefaults = [
            'job_order_no' => 'JO-UNMAPPED',
            'job_order_revision' => 'REV-00',
            'job_order_tanggal' => '2026-05-01',
            'job_order_status' => 'Draft',
            'job_order_priority' => 'Medium',
            'requested_by' => 'N/A',
            'requester_divisi' => 'N/A',
            'hiring_manager' => 'N/A',
            'cost_center' => 'CC-NA',
            'employment_type' => 'PKWT',
            'work_setup' => 'Onsite',
            'shift_type' => 'Non-Shift',
            'lokasi_penempatan_detail' => '-',
            'sumber_rekrutmen' => 'Karirhub',
            'target_tgl_join' => '2026-06-30',
            'sla_hiring_hari' => 30,
            'jumlah_lamaran_masuk' => 0,
            'jumlah_shortlist' => 0,
            'jumlah_interview' => 0,
            'jumlah_offer' => 0,
            'approval_state' => 'Pending',
            'approval_by' => '-',
            'approval_date' => '-',
            'budget_status' => 'Pending',
        ];

        foreach ($vacancies as $index => $vacancy) {
            $vacancyId = (string)($vacancy['id_lowongan'] ?? '');
            $jobOrderMeta = $jobOrderMap[$vacancyId] ?? [];
            $vacancies[$index] = array_merge($vacancy, $jobOrderDefaults, $jobOrderMeta);
        }

        $activities = [
            ['waktu' => '20 Mei 2026 08:10', 'aksi' => 'Buat Laporan Lowongan', 'no_reg_bukti' => 'WLLP-2026-0519-001278', 'status' => 'Terverifikasi'],
            ['waktu' => '19 Mei 2026 16:34', 'aksi' => 'Cetak Bukti Lapor', 'no_reg_bukti' => 'WLLP-2026-0518-001249', 'status' => 'Dicetak'],
            ['waktu' => '19 Mei 2026 11:52', 'aksi' => 'Update Status Keterisian', 'no_reg_bukti' => 'WLLP-2026-0510-001032', 'status' => 'Posisi Terisi'],
            ['waktu' => '18 Mei 2026 09:14', 'aksi' => 'Buat Laporan Lowongan', 'no_reg_bukti' => 'WLLP-2026-0518-001249', 'status' => 'Terverifikasi'],
            ['waktu' => '17 Mei 2026 15:00', 'aksi' => 'Monitoring Kepatuhan', 'no_reg_bukti' => 'WLLP-2026-0514-001180', 'status' => 'Perlu Update'],
        ];

        $dataset = ['units' => $units, 'vacancies' => $vacancies, 'activities' => $activities];
        return $dataset;
    }
}

if (!function_exists('karirhub_proto_dashboard_metrics')) {
    function karirhub_proto_dashboard_metrics(array $vacancies): array
    {
        $metrics = [
            'total_dilaporkan' => count($vacancies),
            'lowongan_aktif' => 0,
            'sudah_terisi' => 0,
            'perlu_update' => 0,
            'bukti_terbaru' => null,
        ];

        foreach ($vacancies as $row) {
            if ($row['status_lowongan'] === 'Aktif') {
                $metrics['lowongan_aktif']++;
            }
            if ($row['status_keterisian'] === 'Terisi') {
                $metrics['sudah_terisi']++;
            }
            if ($row['status_keterisian'] === 'Belum Update' || $row['status_verifikasi'] === 'Perlu Update') {
                $metrics['perlu_update']++;
            }
            if ($metrics['bukti_terbaru'] === null || strcmp($row['tanggal_lapor'], $metrics['bukti_terbaru']['tanggal_lapor']) > 0) {
                $metrics['bukti_terbaru'] = $row;
            }
        }

        return $metrics;
    }
}

if (!function_exists('karirhub_proto_status_badge_class')) {
    function karirhub_proto_status_badge_class(string $status): string
    {
        $status = strtolower(trim($status));
        if ($status === 'terisi' || $status === 'terverifikasi' || $status === 'valid' || $status === 'patuh') {
            return 'success';
        }
        if ($status === 'proses seleksi') {
            return 'info';
        }
        if ($status === 'belum update' || $status === 'perlu update' || $status === 'perlu perhatian') {
            return 'warning';
        }
        if ($status === 'tidak patuh') {
            return 'danger';
        }
        return 'secondary';
    }
}

if (!function_exists('karirhub_proto_compliance_by_unit')) {
    function karirhub_proto_compliance_by_unit(array $units, array $vacancies): array
    {
        $summary = [];
        foreach ($units as $unitKode => $unitInfo) {
            $summary[$unitKode] = [
                'unit' => $unitInfo['nama'],
                'total' => 0,
                'terisi' => 0,
                'belum_update' => 0,
                'patuh_pct' => 0,
                'status' => 'Patuh',
            ];
        }

        foreach ($vacancies as $row) {
            $unitKode = $row['unit_kode'];
            if (!isset($summary[$unitKode])) {
                continue;
            }
            $summary[$unitKode]['total']++;
            if ($row['status_keterisian'] === 'Terisi') {
                $summary[$unitKode]['terisi']++;
            }
            if ($row['status_keterisian'] === 'Belum Update' || $row['status_verifikasi'] === 'Perlu Update') {
                $summary[$unitKode]['belum_update']++;
            }
        }

        foreach ($summary as $unitKode => $item) {
            if ($item['total'] > 0) {
                $patuh = (($item['total'] - $item['belum_update']) / $item['total']) * 100;
                $summary[$unitKode]['patuh_pct'] = (int) round($patuh);
            }

            if ($summary[$unitKode]['belum_update'] >= 2) {
                $summary[$unitKode]['status'] = 'Perlu Perhatian';
            }
            if ($summary[$unitKode]['patuh_pct'] < 60) {
                $summary[$unitKode]['status'] = 'Tidak Patuh';
            }
        }

        return array_values($summary);
    }
}
