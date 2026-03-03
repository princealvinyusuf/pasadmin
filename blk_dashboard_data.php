<?php
declare(strict_types=1);

/**
 * Centralized BLK dashboard data provider.
 * This uses curated sample data aligned with BRD KPIs and is ready
 * to be replaced by SIAPkerja/Karirhub integration queries.
 */
function blk_get_dashboard_data(): array
{
    return [
        'filters' => [
            'period_options' => ['7 Hari', '30 Hari', '3 Bulan', 'Kustom'],
            'location_options' => [
                'Semua Lokasi',
                'Jawa Barat',
                'Jawa Timur',
                'Jawa Tengah',
                'DKI Jakarta',
                'Banten',
                'Sumatera Utara'
            ],
            'major_options' => [
                'Semua Kejuruan',
                'Teknik Otomotif',
                'Teknologi Informasi',
                'Pariwisata & Perhotelan',
                'Tata Busana',
                'Las & Fabrikasi'
            ],
            'source_options' => ['Semua Sumber', 'Kios SIAPkerja', 'Mandiri'],
        ],
        'summary_cards' => [
            [
                'id' => 'total-registered',
                'title' => 'Pencari Kerja Terdaftar',
                'value' => '12.450',
                'delta' => '+12%',
                'panel' => 'ringkasan',
                'table_title' => 'Detail Pencari Kerja Terdaftar Pelatihan',
                'columns' => ['Periode', 'Total Pendaftar', 'Kios', 'Mandiri', 'Pertumbuhan'],
                'rows' => [
                    ['Jan 2026', '11.030', '6.530', '4.500', '+6.1%'],
                    ['Feb 2026', '11.780', '6.970', '4.810', '+6.8%'],
                    ['Mar 2026', '12.450', '7.320', '5.130', '+5.7%'],
                ],
            ],
            [
                'id' => 'active-trainees',
                'title' => 'Peserta Pelatihan Aktif',
                'value' => '3.240',
                'delta' => '+5%',
                'panel' => 'ringkasan',
                'table_title' => 'Detail Peserta Pelatihan Aktif',
                'columns' => ['Balai', 'Program', 'Peserta Aktif', 'Mulai', 'Selesai'],
                'rows' => [
                    ['BPVP Bandung', 'Digital Marketing', '540', '03-02-2026', '28-03-2026'],
                    ['BPVP Surabaya', 'Welder 3G', '480', '10-02-2026', '05-04-2026'],
                    ['BLK Medan', 'Junior Web Dev', '390', '17-02-2026', '12-04-2026'],
                    ['BLK Makassar', 'Perhotelan', '310', '24-02-2026', '19-04-2026'],
                ],
            ],
            [
                'id' => 'graduates',
                'title' => 'Total Lulusan',
                'value' => '8.100',
                'delta' => '+8%',
                'panel' => 'ringkasan',
                'table_title' => 'Detail Lulusan Pelatihan',
                'columns' => ['Kejuruan', 'Lulus', 'Tidak Lulus', 'Tingkat Lulus'],
                'rows' => [
                    ['Teknik Otomotif', '1.420', '140', '91.0%'],
                    ['Teknologi Informasi', '1.310', '115', '91.9%'],
                    ['Pariwisata & Perhotelan', '1.080', '120', '90.0%'],
                    ['Las & Fabrikasi', '920', '105', '89.8%'],
                ],
            ],
            [
                'id' => 'certificates',
                'title' => 'Sertifikat Terbit',
                'value' => '7.850',
                'delta' => '+7%',
                'panel' => 'ringkasan',
                'table_title' => 'Detail Sertifikat Terbit',
                'columns' => ['Periode', 'Sertifikat Pelatihan', 'Sertifikat Kompetensi', 'Total'],
                'rows' => [
                    ['Jan 2026', '2.120', '420', '2.540'],
                    ['Feb 2026', '2.230', '460', '2.690'],
                    ['Mar 2026', '2.170', '450', '2.620'],
                ],
            ],
            [
                'id' => 'placement-rate',
                'title' => 'Tingkat Penempatan',
                'value' => '68%',
                'delta' => '+2.4%',
                'panel' => 'ringkasan',
                'table_title' => 'Detail Tingkat Penempatan Kerja',
                'columns' => ['Periode', 'Lulusan', 'Bekerja', 'Tingkat Penempatan'],
                'rows' => [
                    ['Jan 2026', '2.450', '1.590', '64.9%'],
                    ['Feb 2026', '2.620', '1.760', '67.2%'],
                    ['Mar 2026', '3.030', '2.060', '68.0%'],
                ],
            ],
        ],
        'panels' => [
            [
                'id' => 'partisipasi-tren',
                'panel' => 'partisipasi',
                'title' => 'Tren Pendaftaran Pelatihan',
                'chart' => 'line',
                'chart_labels' => ['Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des', 'Jan', 'Feb', 'Mar'],
                'series' => [
                    ['name' => 'Kios SIAPkerja', 'data' => [390, 420, 415, 445, 380, 470, 335, 310, 380, 395, 410, 430]]
                ],
                'table_title' => 'Detail Tren Pendaftaran Pelatihan',
                'columns' => ['Bulan', 'Jumlah Pendaftaran', 'Kios', 'Mandiri', 'YoY'],
                'rows' => [
                    ['Jan', '395', '238', '157', '+4.3%'],
                    ['Feb', '410', '248', '162', '+4.8%'],
                    ['Mar', '430', '260', '170', '+5.2%'],
                ],
            ],
            [
                'id' => 'partisipasi-rasio',
                'panel' => 'partisipasi',
                'title' => 'Rasio Pendaftaran',
                'chart' => 'doughnut',
                'chart_labels' => ['Kios SIAPkerja', 'Pendaftaran Mandiri'],
                'series' => [
                    ['name' => 'Rasio', 'data' => [62, 38]]
                ],
                'table_title' => 'Detail Rasio Pencari Kerja Kios vs Mandiri',
                'columns' => ['Sumber', 'Jumlah', 'Persentase'],
                'rows' => [
                    ['Kios SIAPkerja', '7.719', '62%'],
                    ['Mandiri', '4.731', '38%'],
                ],
            ],
            [
                'id' => 'output-lulusan-sertifikat',
                'panel' => 'output',
                'title' => 'Output Pelatihan (Lulusan & Sertifikat)',
                'chart' => 'bar',
                'chart_labels' => ['Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des', 'Jan', 'Feb', 'Mar'],
                'series' => [
                    ['name' => 'Lulusan', 'data' => [1210, 1290, 1040, 1130, 870, 990, 930, 1080, 1260, 990, 980, 1120]],
                    ['name' => 'Sertifikat', 'data' => [1410, 1500, 1210, 1360, 1040, 1170, 1100, 1250, 1490, 1170, 1160, 1310]],
                ],
                'table_title' => 'Detail Output Pelatihan Bulanan',
                'columns' => ['Bulan', 'Lulusan', 'Sertifikat', 'Rasio Sertifikasi'],
                'rows' => [
                    ['Jan', '990', '1.170', '118.2%'],
                    ['Feb', '980', '1.160', '118.4%'],
                    ['Mar', '1.120', '1.310', '117.0%'],
                ],
            ],
            [
                'id' => 'output-kelulusan',
                'panel' => 'output',
                'title' => 'Tingkat Kelulusan',
                'chart' => 'progress',
                'chart_labels' => ['Kelulusan'],
                'series' => [['name' => 'Kelulusan', 'data' => [88.5]]],
                'table_title' => 'Detail Tingkat Kelulusan',
                'columns' => ['Kejuruan', 'Peserta', 'Lulus', 'Tingkat Kelulusan'],
                'rows' => [
                    ['Teknik Otomotif', '1.560', '1.420', '91.0%'],
                    ['Teknologi Informasi', '1.425', '1.310', '91.9%'],
                    ['Pariwisata', '1.200', '1.080', '90.0%'],
                    ['Las & Fabrikasi', '1.025', '920', '89.8%'],
                ],
            ],
            [
                'id' => 'output-sertifikasi',
                'panel' => 'output',
                'title' => 'Rasio Sertifikasi',
                'chart' => 'progress',
                'chart_labels' => ['Sertifikasi'],
                'series' => [['name' => 'Sertifikasi', 'data' => [92.1]]],
                'table_title' => 'Detail Rasio Sertifikasi',
                'columns' => ['Balai', 'Lulusan', 'Sertifikat Terbit', 'Rasio Sertifikasi'],
                'rows' => [
                    ['BPVP Bandung', '1.220', '1.130', '92.6%'],
                    ['BPVP Surabaya', '1.070', '985', '92.1%'],
                    ['BLK Medan', '920', '842', '91.5%'],
                    ['BLK Makassar', '870', '803', '92.3%'],
                ],
            ],
            [
                'id' => 'distribusi-kejuruan',
                'panel' => 'distribusi',
                'title' => 'Top 10 Kejuruan Peminat Terbanyak',
                'chart' => 'bar-horizontal',
                'chart_labels' => ['Teknik Otomotif', 'Teknologi Informasi', 'Pariwisata & Perhotelan', 'Las & Fabrikasi', 'Tata Busana', 'Listrik & Elektronika', 'Bisnis & Manajemen', 'Pertanian', 'Bangunan', 'Desain Grafis'],
                'series' => [['name' => 'Peserta', 'data' => [1250, 980, 850, 720, 640, 590, 460, 330, 290, 220]]],
                'table_title' => 'Detail Top 10 Kejuruan',
                'columns' => ['Kejuruan', 'Jumlah Peserta', 'Persentase', 'Provinsi Dominan'],
                'rows' => [
                    ['Teknik Otomotif', '1.250', '15.4%', 'Jawa Barat'],
                    ['Teknologi Informasi', '980', '12.1%', 'Jawa Timur'],
                    ['Pariwisata & Perhotelan', '850', '10.5%', 'Bali'],
                    ['Las & Fabrikasi', '720', '8.9%', 'Jawa Tengah'],
                ],
            ],
            [
                'id' => 'distribusi-provinsi',
                'panel' => 'distribusi',
                'title' => 'Sebaran Peserta per Provinsi',
                'chart' => 'bar-horizontal',
                'chart_labels' => ['Jawa Barat', 'Jawa Timur', 'Jawa Tengah', 'DKI Jakarta', 'Banten', 'Sumatera Utara', 'Kalimantan Selatan'],
                'series' => [['name' => 'Peserta', 'data' => [1520, 1380, 1260, 980, 720, 610, 540]]],
                'table_title' => 'Detail Sebaran Peserta per Provinsi',
                'columns' => ['Provinsi', 'Peserta', 'Lulusan', 'Penempatan'],
                'rows' => [
                    ['Jawa Barat', '1.520', '1.390', '910'],
                    ['Jawa Timur', '1.380', '1.250', '850'],
                    ['Jawa Tengah', '1.260', '1.150', '760'],
                    ['DKI Jakarta', '980', '900', '620'],
                ],
            ],
            [
                'id' => 'integrasi-kpi',
                'panel' => 'integrasi',
                'title' => 'Integrasi Data (Kritis)',
                'chart' => 'kpi-group',
                'chart_labels' => ['Sinkronisasi', 'Belum Sinkron', 'Rata-rata Waktu Sinkron'],
                'series' => [['name' => 'KPI', 'data' => [94.5, 145, 1.2]]],
                'table_title' => 'Detail KPI Integrasi Data',
                'columns' => ['Indikator', 'Nilai', 'Target', 'Status'],
                'rows' => [
                    ['Sinkronisasi Karirhub', '94.5%', '> 95%', 'Perlu Optimasi'],
                    ['Data Belum Sinkron', '145 Peserta', '< 100', 'Perlu Review'],
                    ['Rata-rata Waktu Sinkron', '1.2 Hari', '<= 2 Hari', 'On Track'],
                ],
            ],
            [
                'id' => 'integrasi-gap',
                'panel' => 'integrasi',
                'title' => 'Gap Analisis Data (Kios vs Karirhub)',
                'chart' => 'bar-horizontal-stacked',
                'chart_labels' => ['Profil', 'Pelatihan', 'Sertifikat', 'Penempatan'],
                'series' => [
                    ['name' => 'Tersinkron', 'data' => [98, 95, 90, 72]],
                    ['name' => 'Belum Sinkron', 'data' => [2, 5, 10, 28]],
                ],
                'table_title' => 'Detail Gap Integrasi Data',
                'columns' => ['Domain Data', 'Tersinkron', 'Belum Sinkron', 'Gap'],
                'rows' => [
                    ['Profil', '98%', '2%', '2%'],
                    ['Pelatihan', '95%', '5%', '5%'],
                    ['Sertifikat', '90%', '10%', '10%'],
                    ['Penempatan', '72%', '28%', '28%'],
                ],
            ],
            [
                'id' => 'outcome-konversi',
                'panel' => 'outcome',
                'title' => 'Konversi Pasca Pelatihan',
                'chart' => 'bar-horizontal',
                'chart_labels' => ['Lulus Pelatihan', 'Melamar Kerja', 'Diterima Bekerja'],
                'series' => [['name' => 'Jumlah', 'data' => [1000, 750, 525]]],
                'table_title' => 'Detail Funnel Outcome',
                'columns' => ['Tahap', 'Jumlah', 'Konversi dari Tahap Sebelumnya'],
                'rows' => [
                    ['Lulus Pelatihan', '1.000', '-'],
                    ['Melamar Kerja', '750', '75.0%'],
                    ['Diterima Bekerja', '525', '70.0%'],
                ],
            ],
            [
                'id' => 'outcome-penempatan',
                'panel' => 'outcome',
                'title' => 'Tren Tingkat Penempatan (%)',
                'chart' => 'line',
                'chart_labels' => ['Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des', 'Jan', 'Feb', 'Mar'],
                'series' => [['name' => 'Penempatan (%)', 'data' => [62, 61, 66, 66, 57, 61, 64, 51, 54, 65, 55, 57]]],
                'table_title' => 'Detail Tren Penempatan',
                'columns' => ['Bulan', 'Lulusan', 'Bekerja', 'Tingkat Penempatan'],
                'rows' => [
                    ['Jan', '990', '644', '65.0%'],
                    ['Feb', '980', '539', '55.0%'],
                    ['Mar', '1.120', '638', '57.0%'],
                ],
            ],
            [
                'id' => 'teknis-skor',
                'panel' => 'teknis',
                'title' => 'Skor Kualitas Data',
                'chart' => 'quality-score',
                'chart_labels' => ['Kelengkapan', 'Konsistensi', 'Ketepatan Waktu'],
                'series' => [['name' => 'Skor', 'data' => [92, 95, 88]]],
                'table_title' => 'Detail Skor Kualitas Data',
                'columns' => ['Indikator', 'Skor', 'Target', 'Status'],
                'rows' => [
                    ['Kelengkapan (Completeness)', '92%', '> 95%', 'Perlu Peningkatan'],
                    ['Konsistensi (Consistency)', '95%', '> 95%', 'Sesuai Target'],
                    ['Ketepatan Waktu (Timeliness)', '88%', '> 90%', 'Perlu Akselerasi'],
                ],
            ],
            [
                'id' => 'teknis-isu',
                'panel' => 'teknis',
                'title' => 'Isu Kualitas Data',
                'chart' => 'issue-table',
                'chart_labels' => ['NIK Duplikat', 'Format Tanggal Salah', 'Data Profil Tidak Lengkap'],
                'series' => [['name' => 'Terdampak', 'data' => [12, 45, 126]]],
                'table_title' => 'Detail Isu Kualitas Data',
                'columns' => ['Jenis Isu', 'Jumlah Terdampak', 'Tingkat Keparahan', 'Status'],
                'rows' => [
                    ['NIK Duplikat', '12 record', 'High', 'Perlu Review'],
                    ['Format Tanggal Salah', '45 record', 'Medium', 'Perlu Review'],
                    ['Data Profil Tidak Lengkap', '126 record', 'Low', 'Perlu Review'],
                ],
            ],
        ],
    ];
}

function blk_find_item_by_id(array $data, string $id): ?array
{
    foreach ($data['summary_cards'] as $card) {
        if (($card['id'] ?? '') === $id) {
            return $card;
        }
    }

    foreach ($data['panels'] as $panel) {
        if (($panel['id'] ?? '') === $id) {
            return $panel;
        }
    }

    return null;
}
