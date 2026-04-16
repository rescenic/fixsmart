-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 16, 2026 at 08:33 AM
-- Server version: 10.4.20-MariaDB
-- PHP Version: 8.0.8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `fixsmart`
--

-- --------------------------------------------------------

--
-- Table structure for table `absensi`
--

CREATE TABLE `absensi` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `jam_masuk` time DEFAULT NULL,
  `jam_keluar` time DEFAULT NULL,
  `status` enum('hadir','terlambat','izin','sakit','alpha') NOT NULL DEFAULT 'hadir',
  `terlambat_menit` smallint(6) NOT NULL DEFAULT 0,
  `pulang_awal_menit` smallint(6) NOT NULL DEFAULT 0,
  `durasi_kerja` smallint(6) DEFAULT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `foto_masuk` varchar(255) DEFAULT NULL,
  `foto_keluar` varchar(255) DEFAULT NULL,
  `device_info` varchar(255) DEFAULT NULL,
  `lat_masuk` decimal(10,7) DEFAULT NULL,
  `lon_masuk` decimal(10,7) DEFAULT NULL,
  `lat_keluar` decimal(10,7) DEFAULT NULL,
  `lon_keluar` decimal(10,7) DEFAULT NULL,
  `input_oleh` varchar(10) DEFAULT 'admin',
  `shift_id` int(10) UNSIGNED DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `dicatat_oleh` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `absensi`
--

INSERT INTO `absensi` (`id`, `user_id`, `tanggal`, `jam_masuk`, `jam_keluar`, `status`, `terlambat_menit`, `pulang_awal_menit`, `durasi_kerja`, `keterangan`, `foto_masuk`, `foto_keluar`, `device_info`, `lat_masuk`, `lon_masuk`, `lat_keluar`, `lon_keluar`, `input_oleh`, `shift_id`, `created_by`, `updated_by`, `dicatat_oleh`, `created_at`) VALUES
(17, 8, '2026-04-05', '12:41:15', '12:42:29', 'hadir', 0, 0, 1, NULL, 'uploads/absensi/2026/04/absen_8_masuk_20260405_124115.jpg', 'uploads/absensi/2026/04/absen_8_keluar_20260405_124229.jpg', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', NULL, NULL, '-1.3244699', '102.0954191', 'self', NULL, 8, 8, NULL, '2026-04-05 12:41:15');

-- --------------------------------------------------------

--
-- Table structure for table `aset_ipsrs`
--

CREATE TABLE `aset_ipsrs` (
  `id` int(10) UNSIGNED NOT NULL,
  `no_inventaris` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nomor inventaris unik, contoh: INV-IPSRS-2025-0001',
  `jenis_aset` enum('Medis','Non-Medis') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Non-Medis' COMMENT 'Jenis peralatan',
  `nama_aset` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama / deskripsi aset',
  `kategori` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kategori sesuai jenis (mis: Ventilator, Pompa, HVAC)',
  `merek` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model_aset` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Model / tipe perangkat',
  `serial_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_aset_rs` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nomor registrasi aset internal rumah sakit',
  `kondisi` enum('Baik','Dalam Perbaikan','Rusak','Tidak Aktif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Baik',
  `status_pakai` enum('Terpakai','Tidak Terpakai','Dipinjam') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Terpakai' COMMENT 'Status penggunaan aset',
  `bagian_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK → bagian.id',
  `lokasi` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Cache nama bagian/instalasi',
  `pj_user_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK → users.id',
  `penanggung_jawab` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Cache nama PJ / teknisi',
  `tanggal_beli` date DEFAULT NULL,
  `harga_beli` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Harga perolehan dalam Rupiah',
  `sumber_dana` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'APBN, APBD, BLUD, Hibah, dll',
  `no_bast` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nomor Berita Acara Serah Terima',
  `garansi_sampai` date DEFAULT NULL,
  `tgl_kalibrasi_terakhir` date DEFAULT NULL,
  `tgl_kalibrasi_berikutnya` date DEFAULT NULL,
  `no_sertifikat_kalibrasi` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tgl_service_terakhir` date DEFAULT NULL,
  `tgl_service_berikutnya` date DEFAULT NULL,
  `vendor_service` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nama vendor atau teknisi service',
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK → users.id',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Aset IPSRS — Medis & Non-Medis';

--
-- Dumping data for table `aset_ipsrs`
--

INSERT INTO `aset_ipsrs` (`id`, `no_inventaris`, `jenis_aset`, `nama_aset`, `kategori`, `merek`, `model_aset`, `serial_number`, `no_aset_rs`, `kondisi`, `status_pakai`, `bagian_id`, `lokasi`, `pj_user_id`, `penanggung_jawab`, `tanggal_beli`, `harga_beli`, `sumber_dana`, `no_bast`, `garansi_sampai`, `tgl_kalibrasi_terakhir`, `tgl_kalibrasi_berikutnya`, `no_sertifikat_kalibrasi`, `tgl_service_terakhir`, `tgl_service_berikutnya`, `vendor_service`, `keterangan`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'INV-IPSRS-2026-0001', 'Non-Medis', 'Pompa Air', 'Peralatan Listrik', 'Philips', 'A11', '009992888989811', 'RS-MED-01', 'Baik', 'Terpakai', 5, 'Operasional', 9, 'Giano', '2026-03-04', 7500000, 'Lainnya', 'BA/01/0001/2026', '2026-03-04', '2026-03-04', '2026-03-11', '1100029990901', '2026-03-04', '2026-03-11', 'Internal', '-', 8, '2026-03-04 15:07:26', NULL),
(2, 'INV-IPSRS-2024-0001', 'Medis', 'Ventilator ICU', 'Ventilator', 'Drager', 'Evita V800', 'DRG-2024-00123', 'RS-MED-001', 'Baik', 'Terpakai', NULL, 'Ruang ICU Lt. 2', NULL, 'Teknisi IPSRS', '2022-03-15', 385000000, 'BLUD', 'BAST/2022/03/001', '2025-03-15', '2024-01-10', '2025-01-10', 'KAL/2024/001', '2024-06-01', '2025-06-01', 'PT Drager Indonesia', 'Ventilator dewasa kapasitas penuh, termasuk modul humidifier', 1, '2026-03-04 15:09:21', NULL),
(3, 'INV-IPSRS-2024-0002', 'Medis', 'USG Color Doppler', 'USG / Imaging', 'GE Healthcare', 'LOGIQ E10', 'GE-LOG-E10-4892', 'RS-MED-002', 'Baik', 'Terpakai', NULL, 'Poli Kandungan', NULL, 'Teknisi IPSRS', '2023-07-20', 520000000, 'APBD', 'BAST/2023/07/005', '2026-07-20', '2024-07-15', '2025-07-15', 'KAL/2024/002', '2024-07-15', '2025-07-15', 'PT GE Healthcare Indonesia', 'USG 4D dengan probe konveks dan linear, untuk kandungan & umum', 1, '2026-03-04 15:09:21', NULL),
(4, 'INV-IPSRS-2024-0003', 'Medis', 'Defibrilator AED', 'Defibrilator / AED', 'Philips', 'HeartStart MRx', 'PHL-MRX-2023-881', 'RS-MED-003', 'Baik', 'Terpakai', NULL, 'IGD / UGD', NULL, 'Teknisi IPSRS', '2023-01-10', 145000000, 'BLUD', 'BAST/2023/01/002', '2026-01-10', '2024-03-01', '2025-03-01', 'KAL/2024/003', '2024-03-01', '2025-03-01', 'PT Philips Indonesia', 'AED dengan kemampuan monitoring 12 lead dan pacu jantung eksternal', 1, '2026-03-04 15:09:21', '2026-03-06 13:47:32'),
(5, 'INV-IPSRS-2024-0004', 'Medis', 'Infus Pump Syringe', 'Infus Pump / Syringe Pump', 'Terumo', 'TE-SS700', 'TRM-SS700-2022-44', 'RS-MED-004', 'Dalam Perbaikan', 'Tidak Terpakai', NULL, 'Ruang Perawatan Anak', NULL, 'Teknisi IPSRS', '2022-09-05', 28500000, 'BLUD', 'BAST/2022/09/003', '2025-09-05', '2023-09-10', '2024-09-10', 'KAL/2023/004', '2024-09-10', '2025-09-10', 'PT Terumo Indonesia', 'Syringe pump presisi tinggi, sedang dalam perbaikan sensor tekanan', 1, '2026-03-04 15:09:21', NULL),
(6, 'INV-IPSRS-2024-0005', 'Medis', 'Inkubator Bayi', 'Inkubator / Infant Warmer', 'Draeger', 'Isolette C2', 'DRG-ISO-C2-2023-77', 'RS-MED-005', 'Baik', 'Terpakai', NULL, 'Ruang Perinatologi', NULL, 'Teknisi IPSRS', '2023-11-12', 195000000, 'APBD', 'BAST/2023/11/004', '2026-11-12', '2024-05-20', '2025-05-20', 'KAL/2024/005', '2024-05-20', '2025-05-20', 'PT Draeger Medical Indonesia', 'Inkubator neonatus dengan kontrol suhu & kelembaban otomatis', 1, '2026-03-04 15:09:21', NULL),
(7, 'INV-IPSRS-2024-0006', 'Non-Medis', 'Generator Set (Genset)', 'Generator / Panel Listrik', 'Caterpillar', 'DE400E0', 'CAT-DE400-2021-005', 'RS-NM-001', 'Baik', 'Terpakai', NULL, 'Ruang Genset Basement', NULL, 'Teknisi IPSRS', '2021-04-01', 875000000, 'APBN', 'BAST/2021/04/001', '2026-04-01', NULL, NULL, NULL, '2024-10-01', '2025-04-01', 'PT Trakindo Utama', 'Genset 400 KVA cadangan utama RS, servis berkala 6 bulan sekali', 1, '2026-03-04 15:09:21', NULL),
(8, 'INV-IPSRS-2024-0007', 'Non-Medis', 'AC Central Chiller', 'Peralatan HVAC / AC', 'Daikin', 'EWAQ016BAVP', 'DKN-CHILLER-2022-11', 'RS-NM-002', 'Baik', 'Terpakai', NULL, 'Ruang Mekanikal Atap Lt. 4', NULL, 'Teknisi IPSRS', '2022-06-15', 650000000, 'BLUD', 'BAST/2022/06/002', '2027-06-15', NULL, NULL, NULL, '2024-08-15', '2025-02-15', 'PT Daikin Airconditioning Indonesia', 'Chiller 160 TR untuk sistem AC central gedung utama RS', 1, '2026-03-04 15:09:21', '2026-03-04 15:23:32'),
(9, 'INV-IPSRS-2024-0008', 'Non-Medis', 'Pompa Air Sentrifugal', 'Pompa / Kompresor', 'Grundfos', 'CM10-3', 'GRF-CM10-2023-33', 'RS-NM-003', 'Rusak', 'Tidak Terpakai', NULL, 'Ruang Pompa Basement', NULL, 'Teknisi IPSRS', '2023-02-20', 18500000, 'BLUD', 'BAST/2023/02/003', '2026-02-20', NULL, NULL, NULL, '2023-08-20', '2024-08-20', 'PT Grundfos Pumps Indonesia', 'Pompa distribusi air bersih lantai 3-4, bearing rusak menunggu suku cadang', 1, '2026-03-04 15:09:21', NULL),
(10, 'INV-IPSRS-2024-0009', 'Non-Medis', 'Ambulans Transport', 'Kendaraan / Ambulans', 'Toyota', 'HiAce Commuter 2.8', 'MHFH3FH40P0-12345', 'RS-NM-004', 'Baik', 'Terpakai', NULL, 'Garasi Kendaraan RS', NULL, 'Teknisi IPSRS', '2023-05-17', 425000000, 'APBD', 'BAST/2023/05/004', '2026-05-17', NULL, NULL, NULL, '2024-11-01', '2025-05-01', 'Auto2000 Authorized Service', 'Ambulans transport pasien rujukan, kapasitas 1 tandu + 2 pendamping', 1, '2026-03-04 15:09:21', '2026-03-06 13:46:46'),
(11, 'INV-IPSRS-2024-0010', 'Non-Medis', 'Sistem CCTV 32 Channel', 'Peralatan Keamanan / CCTV', 'Hikvision', 'DS-7732NI-I4', 'HKV-NVR32-2023-09', 'RS-NM-005', 'Baik', 'Terpakai', NULL, 'Ruang Security / Control Room', NULL, 'Teknisi IPSRS', '2023-08-10', 95000000, 'BLUD', 'BAST/2023/08/005', '2026-08-10', NULL, NULL, NULL, '2024-08-10', '2025-08-10', 'PT Hikvision Indonesia', 'NVR 32 channel dengan 28 unit IP camera resolusi 4MP, storage 16TB', 1, '2026-03-04 15:09:21', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `aset_it`
--

CREATE TABLE `aset_it` (
  `id` int(10) UNSIGNED NOT NULL,
  `no_inventaris` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nomor inventaris unik, contoh: INV-IT-2025-0001',
  `nama_aset` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama / deskripsi aset',
  `kategori` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Laptop, Printer, Switch, dll',
  `merek` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model_aset` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Model / tipe perangkat',
  `serial_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kondisi` enum('Baik','Dalam Perbaikan','Rusak','Tidak Aktif') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Baik',
  `status_pakai` enum('Terpakai','Tidak Terpakai','Dipinjam') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Terpakai' COMMENT 'Status penggunaan aset',
  `bagian_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK → bagian.id',
  `lokasi` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Cache nama bagian (fallback display)',
  `pj_user_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK → users.id',
  `penanggung_jawab` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Cache nama PJ (fallback display)',
  `tanggal_beli` date DEFAULT NULL,
  `harga_beli` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Harga dalam Rupiah',
  `garansi_sampai` date DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK → users.id',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventaris / Aset IT';

--
-- Dumping data for table `aset_it`
--

INSERT INTO `aset_it` (`id`, `no_inventaris`, `nama_aset`, `kategori`, `merek`, `model_aset`, `serial_number`, `kondisi`, `status_pakai`, `bagian_id`, `lokasi`, `pj_user_id`, `penanggung_jawab`, `tanggal_beli`, `harga_beli`, `garansi_sampai`, `keterangan`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'INV-IT-2025-0001', 'Laptop Dell Latitude', 'Laptop', 'Dell', 'Latitude 5520', 'DLLAT5520-0001', 'Baik', 'Terpakai', 1, 'IT / Helpdesk', 2, '(sesuai user)', '2023-01-15', 14500000, '2026-01-15', 'Laptop staf IT helpdesk', NULL, '2026-02-26 21:14:11', '2026-02-26 22:25:28'),
(2, 'INV-IT-2025-0002', 'Printer HP LaserJet', 'Printer', 'HP', 'LaserJet Pro M404dn', 'HPLJ404-0002', 'Baik', 'Terpakai', 3, 'HRD', 11, 'budi', '2022-06-20', 4800000, '2025-06-20', NULL, NULL, '2026-02-26 21:14:11', '2026-03-14 12:15:57'),
(3, 'INV-IT-2025-0003', 'Switch Cisco Catalyst', 'Switch', 'Cisco', 'Catalyst 2960-X', 'CSC2960X-0003', 'Baik', 'Terpakai', 1, 'Server Room', 2, '(sesuai user)', '2021-03-10', 22000000, '2024-03-10', 'Core switch lantai 1', NULL, '2026-02-26 21:14:11', NULL),
(4, 'INV-IT-2025-0004', 'UPS APC Smart-UPS', 'UPS', 'APC', 'Smart-UPS 1500', 'APC1500-0004', 'Baik', 'Terpakai', 1, 'IT', 11, 'budi', '2020-09-05', 9500000, NULL, 'Battery perlu diganti', NULL, '2026-02-26 21:14:11', '2026-02-26 21:17:11'),
(5, 'INV-IT-2025-0005', 'Monitor LG 24 inch', 'Monitor', 'LG', '24MK430H', 'LG24MK-0005', 'Baik', 'Terpakai', 5, 'Keuangan', 6, '(sesuai user)', '2023-07-01', 1950000, '2026-07-01', NULL, NULL, '2026-02-26 21:14:11', '2026-02-28 08:48:11'),
(6, 'INV-IT-2026-0006', 'CPU Build UP', 'Komputer', 'Build UP', 'Build UP', '123.123.123.123', 'Baik', 'Terpakai', 7, 'Legal', 10, 'Qiana Almashyra Wiandra', '2026-02-26', 7800000, '2029-02-26', 'Ram 8GB , Core i7', 8, '2026-02-26 21:15:28', '2026-03-13 14:28:31');

-- --------------------------------------------------------

--
-- Table structure for table `backup_logs`
--

CREATE TABLE `backup_logs` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Nama file backup .sql',
  `filesize` bigint(20) NOT NULL DEFAULT 0 COMMENT 'Ukuran file dalam bytes',
  `type` enum('manual','auto') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual' COMMENT 'Jenis backup',
  `status` enum('success','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'success' COMMENT 'Status backup',
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Catatan tambahan / pesan error',
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'System' COMMENT 'Nama user yang memicu backup',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu backup dibuat'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Riwayat backup database FixSmart Helpdesk';

--
-- Dumping data for table `backup_logs`
--

INSERT INTO `backup_logs` (`id`, `filename`, `filesize`, `type`, `status`, `keterangan`, `created_by`, `created_at`) VALUES
(1, 'backup_manual_20260307_195154.sql', 80152, 'manual', 'success', NULL, 'M Wira', '2026-03-07 19:51:54'),
(2, 'backup_manual_20260307_195905.sql', 80514, 'manual', 'success', NULL, 'M Wira', '2026-03-07 19:59:05'),
(3, 'backup_manual_20260307_200103.sql', 80572, 'manual', 'success', NULL, 'M Wira', '2026-03-07 20:01:03'),
(4, 'backup_manual_20260307_200217.sql', 80685, 'manual', 'success', NULL, 'M Wira', '2026-03-07 20:02:17');

-- --------------------------------------------------------

--
-- Table structure for table `bagian`
--

CREATE TABLE `bagian` (
  `id` int(10) UNSIGNED NOT NULL,
  `nama` varchar(100) NOT NULL,
  `kode` varchar(20) DEFAULT NULL COMMENT 'Kode singkat, misal: IT, FIN, HRD',
  `deskripsi` text DEFAULT NULL,
  `lokasi` varchar(150) DEFAULT NULL COMMENT 'Lantai / Gedung / Ruangan utama',
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `urutan` int(11) NOT NULL DEFAULT 0 COMMENT 'Urutan tampil di dropdown',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `bagian`
--

INSERT INTO `bagian` (`id`, `nama`, `kode`, `deskripsi`, `lokasi`, `status`, `urutan`, `created_at`) VALUES
(1, 'IT', 'IT', 'Divisi Teknologi Informasi', 'Lt.1, Server Room', 'aktif', 1, '2026-02-25 20:31:32'),
(2, 'Keuangan', 'FIN', 'Divisi Keuangan & Akuntansi', 'Lt.2, R.Keuangan', 'aktif', 2, '2026-02-25 20:31:32'),
(3, 'HRD', 'HRD', 'Human Resource Development', 'Lt.1, R.HRD', 'aktif', 3, '2026-02-25 20:31:32'),
(4, 'Marketing', 'MKT', 'Divisi Pemasaran & Penjualan', 'Lt.3, R.Marketing', 'aktif', 4, '2026-02-25 20:31:32'),
(5, 'Operasional', 'OPS', 'Divisi Operasional', 'Lt.1, R.Operasional', 'aktif', 5, '2026-02-25 20:31:32'),
(6, 'Direksi', 'DIR', 'Kantor Direksi & Manajemen', 'Lt.4, R.Direksi', 'aktif', 6, '2026-02-25 20:31:32'),
(7, 'Legal', 'LGL', 'Divisi Hukum & Kepatuhan', 'Lt.2, R.Legal', 'aktif', 7, '2026-02-25 20:31:32'),
(8, 'Procurement', 'PRC', 'Divisi Pengadaan Barang & Jasa', 'Lt.1, R.Procurement', 'aktif', 8, '2026-02-25 20:31:32'),
(9, 'Lainnya', 'ETC', 'Departemen / Bagian lainnya', NULL, 'aktif', 99, '2026-02-25 20:31:32'),
(10, 'Gudang IT', 'IT', 'Gudang penyimpanan IT', 'Lt.1, Server Room', 'aktif', 9, '2026-03-04 07:56:10');

-- --------------------------------------------------------

--
-- Table structure for table `berita_acara`
--

CREATE TABLE `berita_acara` (
  `id` int(11) NOT NULL,
  `tiket_id` int(11) NOT NULL,
  `nomor_ba` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal_ba` date NOT NULL,
  `jenis_tindak` enum('pembelian_baru','perbaikan_eksternal','penghapusan_aset','penggantian_suku_cadang','lainnya') COLLATE utf8mb4_unicode_ci DEFAULT 'lainnya',
  `uraian_masalah` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kesimpulan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tindak_lanjut` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nilai_estimasi` bigint(20) DEFAULT NULL,
  `dibuat_oleh` int(11) NOT NULL,
  `diketahui_nama` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `diketahui_jabatan` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mengetahui_nama` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mengetahui_jabatan` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `catatan_tambahan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `berita_acara`
--

INSERT INTO `berita_acara` (`id`, `tiket_id`, `nomor_ba`, `tanggal_ba`, `jenis_tindak`, `uraian_masalah`, `kesimpulan`, `tindak_lanjut`, `nilai_estimasi`, `dibuat_oleh`, `diketahui_nama`, `diketahui_jabatan`, `mengetahui_nama`, `mengetahui_jabatan`, `catatan_tambahan`, `created_at`, `updated_at`) VALUES
(1, 26, 'BA-IT-2026-0001', '2026-03-07', 'pembelian_baru', 'Tolong di cek, terkendala tidak bisa nyala', 'Tidak bisa di perbaiki, ada part yang harus di ganti baru (kipas prosessor)', 'Beli baru saja yang lama di buang gpp', 550000, 12, 'Andi, S. Kom', 'Kanit IT', '-', '-', '-', '2026-03-07 08:58:08', NULL),
(2, 27, 'BA-IT-2026-0002', '2026-03-12', 'pembelian_baru', 'Tester notif\r\n\r\n---\r\n📦 Aset terkait: INV-IT-2025-0005 | Monitor LG 24 inch | LG | 24MK430H', 'tidak bisa di selesaikan oleh petugas internal', 'Ajukan pembelian barang baru', 6750000, 8, 'Andika', 'Kepala IT', '-', '-', '-', '2026-03-12 19:47:27', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `berkas_karyawan`
--

CREATE TABLE `berkas_karyawan` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `jenis_berkas_id` int(10) UNSIGNED DEFAULT NULL,
  `kategori_id` int(10) UNSIGNED DEFAULT NULL,
  `kategori` varchar(50) DEFAULT NULL,
  `jenis_berkas` varchar(100) DEFAULT NULL,
  `nama_file` varchar(255) NOT NULL,
  `nama_asli` varchar(255) NOT NULL,
  `ukuran` int(10) UNSIGNED DEFAULT 0,
  `mime_type` varchar(100) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `tgl_dokumen` date DEFAULT NULL,
  `tgl_exp` date DEFAULT NULL,
  `status_verif` enum('pending','terverifikasi','ditolak') DEFAULT 'pending',
  `catatan_verif` text DEFAULT NULL,
  `verified_by` int(10) UNSIGNED DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `uploaded_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `berkas_karyawan`
--

INSERT INTO `berkas_karyawan` (`id`, `user_id`, `jenis_berkas_id`, `kategori_id`, `kategori`, `jenis_berkas`, `nama_file`, `nama_asli`, `ukuran`, `mime_type`, `keterangan`, `tgl_dokumen`, `tgl_exp`, `status_verif`, `catatan_verif`, `verified_by`, `verified_at`, `uploaded_by`, `created_at`, `updated_at`) VALUES
(2, 8, 45, 3, NULL, NULL, 'bk_69def492d76eb6.78513310.jpeg', 'pisang2.jpeg', 6746, 'image/jpeg', '-', '2026-04-15', '2029-04-15', 'terverifikasi', NULL, 8, '2026-04-15 09:19:31', 8, '2026-04-15 09:14:42', '2026-04-15 09:19:31');

-- --------------------------------------------------------

--
-- Table structure for table `cuti_tanggal`
--

CREATE TABLE `cuti_tanggal` (
  `id` int(10) UNSIGNED NOT NULL,
  `pengajuan_id` int(10) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `cuti_tanggal`
--

INSERT INTO `cuti_tanggal` (`id`, `pengajuan_id`, `tanggal`) VALUES
(11, 4, '2026-03-20'),
(12, 4, '2026-03-21'),
(13, 4, '2026-03-24');

-- --------------------------------------------------------

--
-- Table structure for table `data_gaji`
--

CREATE TABLE `data_gaji` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `gaji_pokok` decimal(15,2) NOT NULL DEFAULT 0.00,
  `ptkp_id` int(10) UNSIGNED DEFAULT NULL,
  `pph21` decimal(15,2) NOT NULL DEFAULT 0.00,
  `catatan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `data_gaji`
--

INSERT INTO `data_gaji` (`id`, `user_id`, `gaji_pokok`, `ptkp_id`, `pph21`, `catatan`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES
(5, 15, '11000000.00', 1, '600000.00', '', 8, '2026-04-16 11:37:42', NULL, NULL),
(6, 10, '8500000.00', 1, '200000.00', 'Gaji tetap', 8, '2026-04-16 11:44:54', NULL, NULL),
(7, 8, '8500000.00', 3, '400000.00', '', 8, '2026-04-16 13:30:15', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `data_gaji_detail`
--

CREATE TABLE `data_gaji_detail` (
  `id` int(10) UNSIGNED NOT NULL,
  `data_gaji_id` int(10) UNSIGNED NOT NULL,
  `tipe` enum('penerimaan','potongan') COLLATE utf8mb4_unicode_ci NOT NULL,
  `penerimaan_id` int(10) UNSIGNED DEFAULT NULL,
  `potongan_id` int(10) UNSIGNED DEFAULT NULL,
  `nilai` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `data_gaji_detail`
--

INSERT INTO `data_gaji_detail` (`id`, `data_gaji_id`, `tipe`, `penerimaan_id`, `potongan_id`, `nilai`) VALUES
(17, 5, 'penerimaan', 7, NULL, '750000.00'),
(18, 5, 'potongan', NULL, 9, '20000.00'),
(19, 5, 'potongan', NULL, 6, '45000.00'),
(20, 6, 'penerimaan', 8, NULL, '1000000.00'),
(21, 6, 'potongan', NULL, 9, '20000.00'),
(32, 7, 'penerimaan', 8, NULL, '1000000.00'),
(33, 7, 'penerimaan', 9, NULL, '500000.00'),
(34, 7, 'penerimaan', 11, NULL, '1000000.00'),
(35, 7, 'penerimaan', 12, NULL, '1750000.00'),
(36, 7, 'potongan', NULL, 6, '45000.00'),
(37, 7, 'potongan', NULL, 7, '65000.00'),
(38, 7, 'potongan', NULL, 8, '30000.00'),
(39, 7, 'potongan', NULL, 9, '20000.00');

-- --------------------------------------------------------

--
-- Table structure for table `dokumen_akreditasi`
--

CREATE TABLE `dokumen_akreditasi` (
  `id` int(10) UNSIGNED NOT NULL,
  `pokja_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `judul` varchar(255) NOT NULL,
  `nomor_doc` varchar(100) DEFAULT NULL,
  `kategori` varchar(100) DEFAULT NULL,
  `elemen_penilaian` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(10) UNSIGNED DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `status` enum('draft','aktif','kadaluarsa') DEFAULT 'draft',
  `tgl_terbit` date DEFAULT NULL,
  `tgl_exp` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `dokumen_akreditasi`
--

INSERT INTO `dokumen_akreditasi` (`id`, `pokja_id`, `user_id`, `judul`, `nomor_doc`, `kategori`, `elemen_penilaian`, `file_path`, `file_name`, `file_size`, `keterangan`, `status`, `tgl_terbit`, `tgl_exp`, `created_at`, `updated_at`) VALUES
(1, 2, 8, 'SPO Keamanan Server', 'SPO/MIRM/001/01/2026', 'SPO', NULL, 'uploads/akreditasi/20260317_222208_2_DOKUMEN_TES.pdf', 'DOKUMEN TES.pdf', 77123, 'EP. 1 A', 'aktif', '2025-01-17', NULL, '2026-03-17 22:22:08', '2026-03-17 22:22:08'),
(2, 2, 8, 'Pedoman Pengorganisasian SIMRS', 'PED/SIMRS/01/01/2026', 'Pedoman', NULL, 'uploads/akreditasi/20260317_222326_2_DOKUMEN_TES.pdf', 'DOKUMEN TES.pdf', 77123, 'EP 1.B', 'aktif', '2026-01-01', NULL, '2026-03-17 22:23:26', '2026-03-17 22:23:26'),
(3, 1, 10, 'SPO Rekrut Karyawan', 'SPO/TKRS/01/01/2026', 'SPO', NULL, 'uploads/akreditasi/20260318_083747_1_DOKUMEN_TES.pdf', 'DOKUMEN TES.pdf', 77123, '-', 'aktif', NULL, NULL, '2026-03-18 08:37:47', '2026-03-18 08:37:47');

-- --------------------------------------------------------

--
-- Table structure for table `jabatan`
--

CREATE TABLE `jabatan` (
  `id` int(10) UNSIGNED NOT NULL,
  `nama` varchar(100) NOT NULL COMMENT 'Nama jabatan, e.g. Staff IT',
  `kode` varchar(20) DEFAULT NULL COMMENT 'Kode singkat, e.g. MGR, SPV, STF',
  `deskripsi` text DEFAULT NULL COMMENT 'Uraian singkat tanggung jawab',
  `level` tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=Staff 2=Supervisor 3=Manager 4=Direktur 5=Eksekutif',
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `urutan` int(11) NOT NULL DEFAULT 0 COMMENT 'Urutan tampil di dropdown',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `jabatan`
--

INSERT INTO `jabatan` (`id`, `nama`, `kode`, `deskripsi`, `level`, `status`, `urutan`, `created_at`) VALUES
(1, 'Direktur Utama', 'DIR', 'Pimpinan tertinggi organisasi', 5, 'aktif', 1, '2026-03-10 20:07:27'),
(2, 'Kepala Bagian', 'KABAG', 'Memimpin satu bagian / divisi', 3, 'aktif', 2, '2026-03-10 20:07:27'),
(3, 'Supervisor', 'SPV', 'Mengawasi dan membimbing tim kerja', 2, 'aktif', 3, '2026-03-10 20:07:27'),
(4, 'Staff', 'STF', 'Pelaksana tugas operasional harian', 1, 'aktif', 4, '2026-03-10 20:07:27'),
(5, 'Teknisi', 'TEK', 'Teknisi lapangan bidang IT / IPSRS', 1, 'aktif', 5, '2026-03-10 20:07:27'),
(6, 'HRD Officer', 'HRD', 'Pengelola sumber daya manusia', 2, 'aktif', 6, '2026-03-10 20:07:27'),
(7, 'Admin', 'ADM', 'Administrasi umum', 1, 'aktif', 7, '2026-03-10 20:07:27'),
(8, 'Security', 'SCR', 'Securtiy penjaga keamanan', 1, 'aktif', 0, '2026-03-13 13:56:03'),
(9, 'AJP', 'AJP', 'Antar Jemput Pasien', 1, 'aktif', 0, '2026-03-13 13:56:27'),
(10, 'tes', 'TES', 'tes', 1, 'aktif', 0, '2026-03-13 14:00:21');

-- --------------------------------------------------------

--
-- Table structure for table `jadwal_karyawan`
--

CREATE TABLE `jadwal_karyawan` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `shift_id` int(10) UNSIGNED DEFAULT NULL,
  `lokasi_id` int(10) UNSIGNED DEFAULT NULL,
  `tanggal` date NOT NULL,
  `keterangan` varchar(100) DEFAULT NULL COMMENT 'LIBUR / CUTI / IZIN / DINAS dll',
  `tipe` enum('shift','libur','cuti','dinas','izin','kosong') NOT NULL DEFAULT 'shift',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `jadwal_karyawan`
--

INSERT INTO `jadwal_karyawan` (`id`, `user_id`, `shift_id`, `lokasi_id`, `tanggal`, `keterangan`, `tipe`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(13, 8, 27, 3, '2026-03-16', NULL, 'shift', 8, 8, '2026-03-16 20:08:01', '2026-03-16 21:08:05'),
(14, 8, 26, 3, '2026-03-17', NULL, 'shift', 8, 8, '2026-03-16 20:08:16', '2026-03-16 20:46:01'),
(15, 8, 27, 6, '2026-03-18', NULL, 'shift', 8, 8, '2026-03-16 20:08:24', '2026-03-18 20:47:49'),
(16, 8, 26, 3, '2026-03-19', NULL, 'shift', 8, 8, '2026-03-16 20:08:28', '2026-03-16 20:45:57'),
(17, 8, 26, 3, '2026-03-20', NULL, 'shift', 8, 8, '2026-03-16 20:08:32', '2026-03-16 20:45:54'),
(18, 8, 27, 3, '2026-03-21', NULL, 'shift', 8, 8, '2026-03-16 20:08:36', '2026-03-16 20:45:48'),
(19, 8, NULL, NULL, '2026-03-22', NULL, 'libur', 8, NULL, '2026-03-16 20:08:44', '2026-03-16 20:08:44'),
(20, 8, 26, NULL, '2026-03-23', NULL, 'shift', 8, NULL, '2026-03-16 20:34:09', '2026-03-16 20:34:09'),
(21, 8, 26, NULL, '2026-03-24', NULL, 'shift', 8, NULL, '2026-03-16 20:34:09', '2026-03-16 20:34:09'),
(22, 8, 26, NULL, '2026-03-25', NULL, 'shift', 8, NULL, '2026-03-16 20:34:09', '2026-03-16 20:34:09'),
(23, 8, 26, NULL, '2026-03-26', NULL, 'shift', 8, NULL, '2026-03-16 20:34:09', '2026-03-16 20:34:09'),
(24, 8, 26, NULL, '2026-03-27', NULL, 'shift', 8, NULL, '2026-03-16 20:34:09', '2026-03-16 20:34:09'),
(25, 8, 27, NULL, '2026-03-28', NULL, 'shift', 8, NULL, '2026-03-16 20:34:09', '2026-03-16 20:34:09'),
(26, 8, NULL, NULL, '2026-03-29', NULL, 'libur', 8, NULL, '2026-03-16 20:34:09', '2026-03-16 20:34:09'),
(27, 8, 26, NULL, '2026-03-30', NULL, 'shift', 8, NULL, '2026-03-16 20:34:15', '2026-03-16 20:34:15'),
(28, 8, 26, NULL, '2026-03-31', NULL, 'shift', 8, NULL, '2026-03-16 20:34:15', '2026-03-16 20:34:15'),
(29, 8, 26, NULL, '2026-04-01', NULL, 'shift', 8, NULL, '2026-03-16 20:34:15', '2026-03-16 20:34:15'),
(30, 8, 26, NULL, '2026-04-02', NULL, 'shift', 8, NULL, '2026-03-16 20:34:15', '2026-03-16 20:34:15'),
(31, 8, 26, NULL, '2026-04-03', NULL, 'shift', 8, NULL, '2026-03-16 20:34:15', '2026-03-16 20:34:15'),
(32, 8, 27, NULL, '2026-04-04', NULL, 'shift', 8, NULL, '2026-03-16 20:34:15', '2026-03-16 20:34:15'),
(33, 8, NULL, NULL, '2026-04-05', NULL, 'libur', 8, NULL, '2026-03-16 20:34:15', '2026-03-16 20:34:15'),
(34, 11, 28, 2, '2026-03-16', NULL, 'shift', 8, 8, '2026-03-16 20:34:27', '2026-03-16 20:46:21'),
(35, 11, 28, 2, '2026-03-17', NULL, 'shift', 8, 8, '2026-03-16 20:34:30', '2026-03-16 20:46:24'),
(36, 11, 29, 2, '2026-03-18', NULL, 'shift', 8, 8, '2026-03-16 20:34:34', '2026-03-16 20:46:27'),
(37, 11, 29, 2, '2026-03-19', NULL, 'shift', 8, 8, '2026-03-16 20:34:36', '2026-03-16 20:46:30'),
(38, 11, 30, 2, '2026-03-20', NULL, 'shift', 8, 8, '2026-03-16 20:34:40', '2026-03-16 20:46:33'),
(39, 11, 30, 2, '2026-03-21', NULL, 'shift', 8, 8, '2026-03-16 20:34:46', '2026-03-16 20:46:36'),
(40, 11, NULL, NULL, '2026-03-22', NULL, 'libur', 8, NULL, '2026-03-16 20:34:52', '2026-03-16 20:34:52'),
(41, 9, 29, 3, '2026-03-16', NULL, 'shift', 8, 8, '2026-03-16 20:34:58', '2026-03-16 20:46:42'),
(42, 9, 30, 3, '2026-03-17', NULL, 'shift', 8, 8, '2026-03-16 20:35:01', '2026-03-16 20:46:45'),
(43, 9, 30, NULL, '2026-03-18', NULL, 'shift', 8, NULL, '2026-03-16 20:35:05', '2026-03-16 20:35:05'),
(44, 9, NULL, NULL, '2026-03-19', NULL, 'libur', 8, NULL, '2026-03-16 20:35:09', '2026-03-16 20:35:09'),
(45, 9, NULL, NULL, '2026-03-20', NULL, 'libur', 8, NULL, '2026-03-16 20:35:13', '2026-03-16 20:35:13'),
(46, 9, NULL, NULL, '2026-03-21', NULL, 'cuti', 8, NULL, '2026-03-16 20:35:18', '2026-03-16 20:35:18'),
(47, 9, NULL, NULL, '2026-03-22', NULL, 'cuti', 8, NULL, '2026-03-16 20:35:24', '2026-03-16 20:35:24'),
(48, 14, 30, NULL, '2026-03-16', NULL, 'shift', 8, NULL, '2026-03-16 20:35:29', '2026-03-16 20:35:29'),
(49, 14, NULL, NULL, '2026-03-17', NULL, 'libur', 8, NULL, '2026-03-16 20:35:33', '2026-03-16 20:35:33'),
(50, 14, NULL, NULL, '2026-03-18', NULL, 'libur', 8, NULL, '2026-03-16 20:35:37', '2026-03-16 20:35:37'),
(51, 14, 29, NULL, '2026-03-19', NULL, 'shift', 8, NULL, '2026-03-16 20:35:42', '2026-03-16 20:35:42'),
(52, 14, 29, NULL, '2026-03-20', NULL, 'shift', 8, NULL, '2026-03-16 20:35:45', '2026-03-16 20:35:45'),
(53, 14, 30, NULL, '2026-03-21', NULL, 'shift', 8, NULL, '2026-03-16 20:35:50', '2026-03-16 20:35:50'),
(54, 14, 30, NULL, '2026-03-22', NULL, 'shift', 8, NULL, '2026-03-16 20:35:53', '2026-03-16 20:35:53'),
(55, 12, 29, NULL, '2026-03-16', NULL, 'shift', 8, NULL, '2026-03-16 20:38:29', '2026-03-16 20:38:29');

-- --------------------------------------------------------

--
-- Table structure for table `jatah_cuti`
--

CREATE TABLE `jatah_cuti` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `jenis_cuti_id` int(10) UNSIGNED NOT NULL,
  `tahun` year(4) NOT NULL,
  `kuota` tinyint(3) UNSIGNED NOT NULL DEFAULT 12,
  `terpakai` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `sisa` tinyint(3) UNSIGNED NOT NULL DEFAULT 12,
  `catatan` varchar(255) DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `jatah_cuti`
--

INSERT INTO `jatah_cuti` (`id`, `user_id`, `jenis_cuti_id`, `tahun`, `kuota`, `terpakai`, `sisa`, `catatan`, `updated_by`, `updated_at`) VALUES
(1, 8, 7, 2026, 12, 3, 9, NULL, NULL, '2026-03-19 20:35:35'),
(2, 15, 8, 2026, 0, 0, 0, NULL, 8, '2026-03-19 20:43:47'),
(3, 15, 7, 2026, 12, 0, 12, NULL, 8, '2026-03-19 20:43:47'),
(4, 11, 8, 2026, 0, 0, 0, NULL, 8, '2026-03-19 20:44:01'),
(5, 11, 7, 2026, 12, 0, 12, NULL, 8, '2026-03-19 20:44:01');

-- --------------------------------------------------------

--
-- Table structure for table `kategori`
--

CREATE TABLE `kategori` (
  `id` int(10) UNSIGNED NOT NULL,
  `nama` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT 'fa-tag',
  `sla_jam` int(11) NOT NULL DEFAULT 24 COMMENT 'Target penyelesaian dalam jam',
  `sla_respon_jam` int(11) NOT NULL DEFAULT 4 COMMENT 'Target respon pertama dalam jam',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `kategori`
--

INSERT INTO `kategori` (`id`, `nama`, `deskripsi`, `icon`, `sla_jam`, `sla_respon_jam`, `created_at`) VALUES
(1, 'Hardware', 'Masalah perangkat keras (PC, laptop, monitor, dll)', 'fa-desktop', 24, 4, '2026-02-25 20:31:32'),
(2, 'Software', 'Instalasi aplikasi, error sistem, OS', 'fa-laptop-code', 8, 2, '2026-02-25 20:31:32'),
(3, 'Jaringan', 'Koneksi internet, WiFi, LAN, VPN', 'fa-network-wired', 4, 1, '2026-02-25 20:31:32'),
(4, 'Email & Akun', 'Reset password, akses email, akun sistem', 'fa-envelope', 4, 1, '2026-02-25 20:31:32'),
(5, 'Printer', 'Printer, scanner, perangkat cetak', 'fa-print', 24, 4, '2026-02-25 20:31:32'),
(6, 'CCTV', 'Kamera CCTV, DVR/NVR, sistem keamanan', 'fa-video', 48, 8, '2026-02-25 20:31:32'),
(7, 'Server', 'Server down, backup, database', 'fa-server', 2, 1, '2026-02-25 20:31:32'),
(8, 'Lainnya', 'Masalah IT lainnya', 'fa-question-circle', 48, 8, '2026-02-25 20:31:32'),
(9, 'Tes', 'Tes', 'fa-video', 12, 1, '2026-03-14 11:49:34');

-- --------------------------------------------------------

--
-- Table structure for table `kategori_ipsrs`
--

CREATE TABLE `kategori_ipsrs` (
  `id` int(10) UNSIGNED NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fa-toolbox',
  `jenis` enum('Medis','Non-Medis') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Non-Medis',
  `sla_jam` int(5) NOT NULL DEFAULT 24,
  `sla_respon_jam` int(5) NOT NULL DEFAULT 4,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `kategori_ipsrs`
--

INSERT INTO `kategori_ipsrs` (`id`, `nama`, `deskripsi`, `icon`, `jenis`, `sla_jam`, `sla_respon_jam`, `created_at`) VALUES
(1, 'Peralatan Diagnostik', 'USG, EKG, monitor pasien', 'fa-stethoscope', 'Medis', 8, 2, '2026-03-04 08:55:49'),
(2, 'Ventilator / Respirator', 'Masalah pada ventilator & CPAP', 'fa-lungs', 'Medis', 4, 1, '2026-03-04 08:55:49'),
(3, 'Infus Pump / Syringe', 'Infus pump dan syringe pump', 'fa-syringe', 'Medis', 4, 1, '2026-03-04 08:55:49'),
(4, 'Sterilisasi / Autoclave', 'Peralatan sterilisasi alat medis', 'fa-flask-vial', 'Medis', 8, 2, '2026-03-04 08:55:49'),
(5, 'Peralatan Bedah', 'Alat dan instrumen bedah', 'fa-bandage', 'Medis', 12, 3, '2026-03-04 08:55:49'),
(6, 'Peralatan Laboratorium', 'Alat lab klinik dan PA', 'fa-flask-vial', 'Medis', 12, 3, '2026-03-04 08:55:49'),
(7, 'Radiologi / Imaging', 'Rontgen, CT-scan, MRI', 'fa-radiation', 'Medis', 24, 4, '2026-03-04 08:55:49'),
(8, 'Peralatan Gigi', 'Dental unit dan aksesoris', 'fa-tooth', 'Medis', 24, 4, '2026-03-04 08:55:49'),
(9, 'Peralatan Mata', 'Slit lamp, tonometer, dll', 'fa-eye', 'Medis', 24, 4, '2026-03-04 08:55:49'),
(10, 'Inkubator / Infant', 'Inkubator bayi, infant warmer', 'fa-baby', 'Medis', 4, 1, '2026-03-04 08:55:49'),
(11, 'Lainnya (Medis)', 'Peralatan medis lainnya', 'fa-kit-medical', 'Medis', 24, 4, '2026-03-04 08:55:49'),
(12, 'Instalasi Listrik', 'Panel, kabel, MCB, dll', 'fa-bolt', 'Non-Medis', 8, 2, '2026-03-04 08:55:49'),
(13, 'Generator / UPS', 'Genset, ATS, UPS', 'fa-plug-circle-bolt', 'Non-Medis', 4, 1, '2026-03-04 08:55:49'),
(14, 'HVAC / AC', 'AC, chiller, exhaust fan', 'fa-wind', 'Non-Medis', 12, 3, '2026-03-04 08:55:49'),
(15, 'Sanitasi / Plumbing', 'Pipa, pompa air, WC, wastafel', 'fa-droplet', 'Non-Medis', 12, 3, '2026-03-04 08:55:49'),
(16, 'Pompa / Kompresor', 'Pompa air, kompresor udara', 'fa-gear', 'Non-Medis', 8, 2, '2026-03-04 08:55:49'),
(17, 'Dapur / Gizi', 'Peralatan dapur dan gizi', 'fa-utensils', 'Non-Medis', 24, 8, '2026-03-04 08:55:49'),
(18, 'Laundry', 'Mesin cuci, pengering, setrika', 'fa-shirt', 'Non-Medis', 24, 8, '2026-03-04 08:55:49'),
(19, 'Kebersihan', 'Alat kebersihan & pest control', 'fa-broom', 'Non-Medis', 48, 12, '2026-03-04 08:55:49'),
(20, 'Kendaraan / Ambulans', 'Perawatan kendaraan operasional', 'fa-car', 'Non-Medis', 24, 4, '2026-03-04 08:55:49'),
(21, 'Keamanan / CCTV', 'CCTV, akses kontrol, alarm', 'fa-camera', 'Non-Medis', 12, 2, '2026-03-04 08:55:49'),
(22, 'Alat Angkat / Angkut', 'Brankar, kursi roda, lift barang', 'fa-dolly', 'Non-Medis', 12, 3, '2026-03-04 08:55:49'),
(23, 'Lainnya (Non-Medis)', 'Sarana & prasarana lainnya', 'fa-toolbox', 'Non-Medis', 48, 12, '2026-03-04 08:55:49'),
(25, 'TES KATEGORi IPSRS', 'TES TES', 'fa-bolt', 'Non-Medis', 24, 4, '2026-03-14 11:56:43');

-- --------------------------------------------------------

--
-- Table structure for table `komentar`
--

CREATE TABLE `komentar` (
  `id` int(10) UNSIGNED NOT NULL,
  `tiket_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `isi` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `komentar_ipsrs`
--

CREATE TABLE `komentar_ipsrs` (
  `id` int(10) UNSIGNED NOT NULL,
  `tiket_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `isi` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `komentar_ipsrs`
--

INSERT INTO `komentar_ipsrs` (`id`, `tiket_id`, `user_id`, `isi`, `created_at`) VALUES
(1, 10, 10, 'Mohon segera ya mas', '2026-03-07 10:59:43');

-- --------------------------------------------------------

--
-- Table structure for table `koneksi_log`
--

CREATE TABLE `koneksi_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `monitor_id` int(10) UNSIGNED NOT NULL,
  `status` enum('online','offline','timeout') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ping_ms` float DEFAULT NULL COMMENT 'Latensi dalam milidetik',
  `http_code` smallint(6) DEFAULT NULL COMMENT 'HTTP status code jika tipe URL',
  `pesan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Pesan error / info tambahan',
  `cek_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log hasil pengecekan koneksi per host';

--
-- Dumping data for table `koneksi_log`
--

INSERT INTO `koneksi_log` (`id`, `monitor_id`, `status`, `ping_ms`, `http_code`, `pesan`, `cek_at`) VALUES
(2, 2, 'online', 22.12, NULL, 'OK', '2026-02-27 13:32:40'),
(7, 2, 'online', 16.95, NULL, 'OK', '2026-02-27 13:33:09'),
(11, 2, 'online', 19.89, NULL, 'OK', '2026-02-27 13:33:16'),
(14, 2, 'online', 18.59, NULL, 'OK', '2026-02-27 13:33:25'),
(16, 2, 'online', 16.26, NULL, 'OK', '2026-02-27 13:36:41'),
(18, 2, 'online', 19.93, NULL, 'OK', '2026-02-27 13:36:47'),
(19, 6, 'offline', 4129.43, 0, 'SSL read: error:00000000:lib(0):func(0):reason(0), errno 54', '2026-02-27 13:36:51'),
(21, 2, 'online', 18.42, NULL, 'OK', '2026-02-27 13:36:58'),
(22, 6, 'timeout', NULL, 0, 'Timeout setelah 5s', '2026-02-27 13:37:03'),
(24, 2, 'online', 17.09, NULL, 'OK', '2026-02-27 13:38:22'),
(25, 6, 'offline', 4373, 0, 'SSL read: error:00000000:lib(0):func(0):reason(0), errno 54', '2026-02-27 13:38:26'),
(27, 2, 'online', 19.91, NULL, 'OK', '2026-02-27 13:39:18'),
(28, 6, 'offline', 4126.77, 0, 'SSL read: error:00000000:lib(0):func(0):reason(0), errno 54', '2026-02-27 13:39:22'),
(29, 7, 'offline', 146.17, 404, 'HTTP 404 Error', '2026-02-27 13:39:22'),
(31, 2, 'online', 21.64, NULL, 'OK', '2026-02-27 13:40:34'),
(32, 6, 'offline', 4172.1, 0, 'SSL read: error:00000000:lib(0):func(0):reason(0), errno 54', '2026-02-27 13:40:38'),
(33, 7, 'offline', 180.96, 404, 'HTTP 404 Error', '2026-02-27 13:40:38'),
(34, 8, 'online', 176.12, 200, 'HTTP 200 OK', '2026-02-27 13:40:38'),
(36, 2, 'online', 19.33, NULL, 'OK', '2026-02-27 13:42:14'),
(37, 6, 'online', 318.2, 200, 'HTTP 200 OK', '2026-02-27 13:42:15'),
(38, 7, 'offline', 163.38, 404, 'HTTP 404 Error', '2026-02-27 13:42:15'),
(39, 8, 'online', 234.3, 200, 'HTTP 200 OK', '2026-02-27 13:42:15'),
(41, 2, 'online', 23.92, NULL, 'OK', '2026-02-27 13:43:39'),
(42, 6, 'online', 298.09, 200, 'HTTP 200 OK', '2026-02-27 13:43:39'),
(43, 7, 'offline', 152.49, 404, 'HTTP 404 Error', '2026-02-27 13:43:39'),
(44, 8, 'online', 219.92, 200, 'HTTP 200 OK', '2026-02-27 13:43:39'),
(45, 9, 'offline', 193.47, 0, 'Failed to connect to apijkn.bpjs-kesehatan.go.id port 80: Connection refused', '2026-02-27 13:43:39'),
(47, 2, 'online', 17.74, NULL, 'OK', '2026-02-27 13:45:24'),
(48, 6, 'offline', 4124.53, 0, 'SSL read: error:00000000:lib(0):func(0):reason(0), errno 54', '2026-02-27 13:45:28'),
(49, 7, 'offline', 168.72, 404, 'HTTP 404 Error', '2026-02-27 13:45:28'),
(50, 8, 'online', 188.94, 200, 'HTTP 200 OK', '2026-02-27 13:45:29'),
(51, 9, 'offline', 186.37, 0, 'Failed to connect to apijkn.bpjs-kesehatan.go.id port 80: Connection refused', '2026-02-27 13:45:29'),
(52, 6, 'timeout', NULL, 0, 'Timeout setelah 5s', '2026-02-27 13:45:39'),
(53, 6, 'online', 3724.8, 200, 'HTTP 200', '2026-02-27 13:46:29'),
(54, 7, 'offline', 3724.9, 404, 'HTTP 404', '2026-02-27 13:46:29'),
(55, 8, 'online', 3724.8, 200, 'HTTP 200', '2026-02-27 13:46:29'),
(56, 9, 'online', 3724.8, 301, 'HTTP 301', '2026-02-27 13:46:29'),
(58, 2, 'online', 17.5, NULL, 'TCP OK', '2026-02-27 13:46:29'),
(59, 6, 'online', 3837.8, 200, 'HTTP 200', '2026-02-27 13:47:01'),
(60, 7, 'offline', 3837.8, 404, 'HTTP 404', '2026-02-27 13:47:01'),
(61, 8, 'online', 3837.8, 200, 'HTTP 200', '2026-02-27 13:47:01'),
(62, 9, 'online', 3837.8, 301, 'HTTP 301', '2026-02-27 13:47:01'),
(64, 2, 'online', 18.6, NULL, 'TCP OK', '2026-02-27 13:47:01'),
(65, 6, 'offline', 4119.4, 0, 'HTTP 0', '2026-02-27 13:47:11'),
(66, 7, 'offline', 4119.4, 404, 'HTTP 404', '2026-02-27 13:47:11'),
(67, 8, 'online', 4119.4, 200, 'HTTP 200', '2026-02-27 13:47:11'),
(68, 9, 'online', 4119.4, 301, 'HTTP 301', '2026-02-27 13:47:11'),
(70, 2, 'online', 17.3, NULL, 'TCP OK', '2026-02-27 13:47:11'),
(71, 6, 'online', 2351.6, 200, 'HTTP 200', '2026-02-27 13:48:07'),
(72, 7, 'offline', 2351.6, 404, 'HTTP 404', '2026-02-27 13:48:07'),
(73, 8, 'online', 2351.5, 200, 'HTTP 200', '2026-02-27 13:48:07'),
(74, 9, 'online', 2351.5, 301, 'HTTP 301', '2026-02-27 13:48:07'),
(76, 2, 'online', 17.3, NULL, 'TCP OK', '2026-02-27 13:48:07'),
(77, 6, 'online', 3774.8, 200, 'HTTP 200', '2026-02-27 13:49:51'),
(78, 7, 'offline', 3774.8, 404, 'HTTP 404', '2026-02-27 13:49:51'),
(79, 8, 'online', 3774.8, 200, 'HTTP 200', '2026-02-27 13:49:51'),
(80, 9, 'online', 3774.8, 301, 'HTTP 301', '2026-02-27 13:49:51'),
(82, 2, 'online', 17.7, NULL, 'TCP OK', '2026-02-27 13:49:51'),
(83, 6, 'online', 2530.4, 200, 'HTTP 200', '2026-02-27 19:36:30'),
(84, 7, 'offline', 2530.4, 404, 'HTTP 404', '2026-02-27 19:36:30'),
(85, 8, 'online', 2530.3, 200, 'HTTP 200', '2026-02-27 19:36:30'),
(86, 9, 'online', 2530.3, 301, 'HTTP 301', '2026-02-27 19:36:30'),
(88, 2, 'online', 21.4, NULL, 'TCP OK', '2026-02-27 19:36:30'),
(89, 6, 'offline', 4174.7, 0, 'HTTP 0', '2026-02-27 20:43:32'),
(90, 7, 'offline', 4174.5, 404, 'HTTP 404', '2026-02-27 20:43:32'),
(91, 8, 'online', 4174.5, 200, 'HTTP 200', '2026-02-27 20:43:32'),
(92, 9, 'online', 4174.5, 301, 'HTTP 301', '2026-02-27 20:43:32'),
(94, 2, 'online', 16.7, NULL, 'TCP OK', '2026-02-27 20:43:32'),
(95, 6, 'online', 295.4, 200, 'HTTP 200', '2026-02-28 08:15:26'),
(96, 7, 'offline', 152.1, 404, 'HTTP 404', '2026-02-28 08:15:31'),
(97, 7, 'offline', 116, 404, 'HTTP 404', '2026-02-28 08:15:33'),
(98, 7, 'offline', 4109.7, 0, 'SSL read: error:00000000:lib(0):func(0):reason(0), errno 54', '2026-02-28 08:15:37'),
(99, 2, 'online', 15, NULL, 'TCP OK', '2026-02-28 08:15:38'),
(101, 6, 'offline', 4112.6, 0, 'SSL read: error:00000000:lib(0):func(0):reason(0), errno 54', '2026-02-28 08:15:47'),
(102, 6, 'online', 599.7, 200, 'HTTP 200', '2026-03-02 08:08:16'),
(103, 7, 'offline', 599.7, 404, 'HTTP 404', '2026-03-02 08:08:16'),
(104, 8, 'online', 599.7, 200, 'HTTP 200', '2026-03-02 08:08:16'),
(105, 9, 'online', 599.6, 301, 'HTTP 301', '2026-03-02 08:08:16'),
(107, 2, 'online', 15.2, NULL, 'TCP OK', '2026-03-02 08:08:16'),
(108, 6, 'offline', 5005.9, 0, 'name lookup timed out', '2026-03-03 21:57:36'),
(109, 2, 'timeout', NULL, NULL, 'Timeout', '2026-03-03 21:57:45'),
(110, 6, 'offline', 15003.3, 0, 'HTTP 0', '2026-03-03 21:58:06'),
(111, 7, 'offline', 15003.3, 0, 'HTTP 0', '2026-03-03 21:58:06'),
(112, 8, 'offline', 15003.3, 0, 'HTTP 0', '2026-03-03 21:58:06'),
(113, 9, 'offline', 15003.2, 0, 'HTTP 0', '2026-03-03 21:58:06'),
(115, 2, 'timeout', NULL, NULL, 'Timeout', '2026-03-03 21:58:06'),
(116, 6, 'online', 353.9, 200, 'HTTP 200', '2026-03-04 08:49:14'),
(117, 7, 'offline', 353.9, 404, 'HTTP 404', '2026-03-04 08:49:14'),
(118, 8, 'online', 353.8, 200, 'HTTP 200', '2026-03-04 08:49:14'),
(119, 9, 'online', 353.8, 301, 'HTTP 301', '2026-03-04 08:49:14'),
(121, 2, 'online', 14.8, NULL, 'TCP OK', '2026-03-04 08:49:14'),
(122, 6, 'offline', 4249.1, 0, 'HTTP 0', '2026-03-04 15:15:57'),
(123, 7, 'offline', 4249.1, 404, 'HTTP 404', '2026-03-04 15:15:57'),
(124, 8, 'online', 4249.1, 200, 'HTTP 200', '2026-03-04 15:15:57'),
(125, 9, 'online', 4249.1, 301, 'HTTP 301', '2026-03-04 15:15:57'),
(127, 2, 'online', 15.8, NULL, 'TCP OK', '2026-03-04 15:15:57'),
(128, 6, 'online', 320.4, 200, 'HTTP 200', '2026-03-05 14:07:57'),
(129, 7, 'offline', 320.4, 404, 'HTTP 404', '2026-03-05 14:07:57'),
(130, 8, 'online', 320.4, 200, 'HTTP 200', '2026-03-05 14:07:57'),
(131, 9, 'online', 320.4, 301, 'HTTP 301', '2026-03-05 14:07:57'),
(133, 2, 'online', 14.2, NULL, 'TCP OK', '2026-03-05 14:07:57'),
(134, 6, 'offline', 4147.1, 0, 'HTTP 0', '2026-03-05 14:20:04'),
(135, 7, 'offline', 4147.1, 404, 'HTTP 404', '2026-03-05 14:20:04'),
(136, 8, 'online', 4147, 200, 'HTTP 200', '2026-03-05 14:20:04'),
(137, 9, 'online', 4147, 301, 'HTTP 301', '2026-03-05 14:20:04'),
(139, 2, 'online', 14.7, NULL, 'TCP OK', '2026-03-05 14:20:04'),
(140, 6, 'online', 288.4, 200, 'HTTP 200', '2026-03-05 14:45:15'),
(141, 7, 'offline', 288.4, 404, 'HTTP 404', '2026-03-05 14:45:15'),
(142, 8, 'online', 288.4, 200, 'HTTP 200', '2026-03-05 14:45:15'),
(143, 9, 'online', 288.4, 301, 'HTTP 301', '2026-03-05 14:45:15'),
(145, 2, 'online', 14.6, NULL, 'TCP OK', '2026-03-05 14:45:15'),
(146, 6, 'online', 322.9, 200, 'HTTP 200', '2026-03-05 15:18:10'),
(147, 7, 'offline', 322.9, 404, 'HTTP 404', '2026-03-05 15:18:10'),
(148, 8, 'online', 322.9, 200, 'HTTP 200', '2026-03-05 15:18:10'),
(149, 9, 'online', 322.9, 301, 'HTTP 301', '2026-03-05 15:18:10'),
(151, 2, 'online', 14.8, NULL, 'TCP OK', '2026-03-05 15:18:10'),
(152, 6, 'online', 406.8, 200, 'HTTP 200', '2026-03-05 20:55:05'),
(153, 7, 'offline', 406.8, 404, 'HTTP 404', '2026-03-05 20:55:05'),
(154, 8, 'online', 406.8, 200, 'HTTP 200', '2026-03-05 20:55:05'),
(155, 9, 'online', 406.7, 301, 'HTTP 301', '2026-03-05 20:55:05'),
(157, 2, 'online', 18.7, NULL, 'TCP OK', '2026-03-05 20:55:05'),
(158, 6, 'online', 2268.1, 200, 'HTTP 200', '2026-03-06 09:58:00'),
(159, 7, 'offline', 2268.1, 404, 'HTTP 404', '2026-03-06 09:58:00'),
(160, 8, 'online', 2268, 200, 'HTTP 200', '2026-03-06 09:58:00'),
(161, 9, 'online', 2268, 301, 'HTTP 301', '2026-03-06 09:58:00'),
(163, 2, 'online', 14.7, NULL, 'TCP OK', '2026-03-06 09:58:00'),
(164, 8, 'online', 330.9, 200, 'HTTP 200', '2026-03-07 10:29:20'),
(165, 7, 'offline', 200.8, 404, 'HTTP 404', '2026-03-07 10:29:27'),
(166, 6, 'online', 3263.5, 200, 'HTTP 200', '2026-03-07 10:29:58'),
(167, 6, 'online', 1137.5, 200, 'HTTP 200', '2026-03-09 09:43:31'),
(168, 7, 'offline', 1137.5, 404, 'HTTP 404', '2026-03-09 09:43:31'),
(169, 8, 'online', 1137.5, 200, 'HTTP 200', '2026-03-09 09:43:31'),
(170, 9, 'online', 1137.5, 301, 'HTTP 301', '2026-03-09 09:43:31'),
(172, 2, 'online', 16.6, NULL, 'TCP OK', '2026-03-09 09:43:31'),
(173, 6, 'online', 4348.6, 302, 'HTTP 302 · TTFB 4348.6ms', '2026-03-09 09:51:13'),
(174, 7, 'offline', 191.9, 404, 'HTTP 404', '2026-03-09 09:51:13'),
(175, 8, 'online', 2607.2, 200, 'HTTP 200 · TTFB 2607.2ms', '2026-03-09 09:51:13'),
(176, 9, 'online', 205, 301, 'HTTP 301 · TTFB 205ms', '2026-03-09 09:51:13'),
(178, 2, 'online', 15, NULL, 'TCP OK · 15ms', '2026-03-09 09:51:13'),
(179, 8, 'online', 114.2, 200, 'HTTP 200 · TTFB 114.2ms', '2026-03-09 09:51:29'),
(180, 6, 'offline', NULL, 0, 'SSL read: error:00000000:lib(0):func(0):reason(0), errno 54', '2026-03-09 09:51:35'),
(181, 6, 'online', 142.2, 200, 'HTTP 200 · TTFB 142.2ms', '2026-03-09 09:51:38'),
(182, 7, 'offline', 197.6, 404, 'HTTP 404', '2026-03-09 09:51:41'),
(183, 7, 'offline', 159.6, 404, 'HTTP 404', '2026-03-09 09:51:42'),
(184, 7, 'offline', 269, 404, 'HTTP 404', '2026-03-09 09:51:43'),
(185, 7, 'offline', 3217, 404, 'HTTP 404', '2026-03-09 09:51:46'),
(191, 7, 'offline', 234.9, 404, 'HTTP 404', '2026-03-09 09:55:15'),
(192, 7, 'timeout', NULL, 0, 'Timeout', '2026-03-09 09:55:21'),
(193, 7, 'offline', 145, 404, 'HTTP 404', '2026-03-09 09:55:21'),
(194, 7, 'offline', 154.5, 404, 'HTTP 404', '2026-03-09 09:55:22'),
(195, 7, 'offline', 152.1, 404, 'HTTP 404', '2026-03-09 09:55:23'),
(196, 7, 'offline', 169.2, 404, 'HTTP 404', '2026-03-09 09:55:24'),
(198, 6, 'online', 114.4, 200, 'HTTP 200 · TTFB 114.4ms', '2026-03-09 09:55:34'),
(199, 7, 'offline', 202.7, 404, 'HTTP 404', '2026-03-09 09:55:34'),
(200, 8, 'online', 102.2, 200, 'HTTP 200 · TTFB 102.2ms', '2026-03-09 09:55:34'),
(201, 9, 'online', 222.2, 301, 'HTTP 301 · TTFB 222.2ms', '2026-03-09 09:55:34'),
(203, 2, 'online', 15.9, NULL, 'TCP OK · 15.9ms', '2026-03-09 09:55:34'),
(205, 6, 'online', 4389.9, 302, 'HTTP 302 · TTFB 4389.9ms', '2026-03-09 09:56:51'),
(206, 7, 'offline', 4185.7, 0, 'HTTP 0', '2026-03-09 09:56:51'),
(207, 8, 'online', 115.8, 200, 'HTTP 200 · TTFB 115.8ms', '2026-03-09 09:56:51'),
(208, 9, 'online', 270.2, 301, 'HTTP 301 · TTFB 270.2ms', '2026-03-09 09:56:51'),
(209, 2, 'online', 15.4, NULL, 'TCP OK · 15.4ms', '2026-03-09 09:56:51'),
(210, 6, 'offline', NULL, 0, 'SSL read: error:00000000:lib(0):func(0):reason(0), errno 54', '2026-03-09 09:57:00'),
(211, 6, 'offline', NULL, 0, 'SSL read: error:00000000:lib(0):func(0):reason(0), errno 54', '2026-03-09 09:57:05'),
(212, 6, 'online', 125.9, 200, 'HTTP 200 · TTFB 125.9ms', '2026-03-09 09:57:07'),
(213, 6, 'offline', 4887.1, 0, 'HTTP 0', '2026-04-04 15:54:09'),
(214, 7, 'offline', 713.8, 404, 'HTTP 404', '2026-04-04 15:54:09'),
(215, 8, 'online', 247.8, 200, 'HTTP 200 · TTFB 247.8ms', '2026-04-04 15:54:09'),
(216, 9, 'online', 434, 301, 'HTTP 301 · TTFB 434ms', '2026-04-04 15:54:09'),
(217, 2, 'online', 28.8, NULL, 'TCP OK · 28.8ms', '2026-04-04 15:54:09'),
(218, 6, 'offline', 4160.6, 0, 'HTTP 0', '2026-04-04 15:54:16'),
(219, 7, 'offline', 161.4, 404, 'HTTP 404', '2026-04-04 15:54:16'),
(220, 8, 'online', 79.8, 200, 'HTTP 200 · TTFB 79.8ms', '2026-04-04 15:54:16'),
(221, 9, 'online', 181.9, 301, 'HTTP 301 · TTFB 181.9ms', '2026-04-04 15:54:16'),
(222, 2, 'online', 18.1, NULL, 'TCP OK · 18.1ms', '2026-04-04 15:54:16'),
(223, 6, 'offline', 4168.1, 0, 'HTTP 0', '2026-04-04 15:54:22'),
(224, 7, 'offline', 167.2, 404, 'HTTP 404', '2026-04-04 15:54:22'),
(225, 8, 'online', 98.9, 200, 'HTTP 200 · TTFB 98.9ms', '2026-04-04 15:54:22'),
(226, 9, 'online', 187, 301, 'HTTP 301 · TTFB 187ms', '2026-04-04 15:54:22'),
(227, 2, 'online', 17.8, NULL, 'TCP OK · 17.8ms', '2026-04-04 15:54:22'),
(228, 6, 'offline', 4198.3, 0, 'HTTP 0', '2026-04-04 15:54:35'),
(229, 7, 'offline', 204.3, 404, 'HTTP 404', '2026-04-04 15:54:35'),
(230, 8, 'online', 83.5, 200, 'HTTP 200 · TTFB 83.5ms', '2026-04-04 15:54:35'),
(231, 9, 'online', 223.2, 301, 'HTTP 301 · TTFB 223.2ms', '2026-04-04 15:54:35'),
(232, 2, 'online', 18.6, NULL, 'TCP OK · 18.6ms', '2026-04-04 15:54:35'),
(233, 6, 'offline', 4999.8, 0, 'HTTP 0', '2026-04-16 10:27:23'),
(234, 7, 'offline', 400.7, 404, 'HTTP 404', '2026-04-16 10:27:23'),
(235, 8, 'online', 116.6, 200, 'HTTP 200 · TTFB 116.6ms', '2026-04-16 10:27:23'),
(236, 9, 'online', 302.1, 301, 'HTTP 301 · TTFB 302.1ms', '2026-04-16 10:27:23'),
(237, 2, 'online', 18.3, NULL, 'TCP OK · 18.3ms', '2026-04-16 10:27:23');

-- --------------------------------------------------------

--
-- Table structure for table `koneksi_monitor`
--

CREATE TABLE `koneksi_monitor` (
  `id` int(10) UNSIGNED NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Label tampilan, cth: Server SIMRS',
  `host` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'IP atau URL, cth: 192.168.1.1 / https://simrs.rspermata.com',
  `tipe` enum('ip','url') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'url' COMMENT 'ip = ping ICMP, url = HTTP request',
  `kategori` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Umum' COMMENT 'Pengelompokan: Server, Printer, Internet, dsb.',
  `port` smallint(5) UNSIGNED DEFAULT NULL COMMENT 'Port opsional untuk tipe IP',
  `timeout_detik` tinyint(3) UNSIGNED NOT NULL DEFAULT 5 COMMENT 'Batas waktu cek dalam detik',
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daftar host / URL yang dipantau via cek_koneksi.php';

--
-- Dumping data for table `koneksi_monitor`
--

INSERT INTO `koneksi_monitor` (`id`, `nama`, `host`, `tipe`, `kategori`, `port`, `timeout_detik`, `aktif`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 'Cloudflare DNS', '1.1.1.1', 'ip', 'Internet', NULL, 3, 1, NULL, '2026-02-27 13:30:22', NULL),
(6, 'Aplicare BPJS', 'https://new-api.bpjs-kesehatan.go.id/aplicaresws', 'url', 'BPJS', NULL, 5, 1, 5, '2026-02-27 13:36:41', '2026-02-27 13:38:16'),
(7, 'I-Care', 'https://apijkn.bpjs-kesehatan.go.id/wsihs/api/rs', 'url', 'BPJS', NULL, 5, 1, 5, '2026-02-27 13:39:12', NULL),
(8, 'Finger BPJS', 'https://fp.bpjs-kesehatan.go.id/finger-rest', 'url', 'BPJS', NULL, 5, 1, 5, '2026-02-27 13:40:28', NULL),
(9, 'Vclaim', 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest', 'url', 'BPJS', NULL, 5, 1, 8, '2026-02-27 13:43:32', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `blocked_until` datetime DEFAULT NULL,
  `last_attempt` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_log`
--

CREATE TABLE `login_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `username_input` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('berhasil','gagal','terkunci') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_type` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `browser` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `os` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_new_ip` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_log`
--

INSERT INTO `login_log` (`id`, `user_id`, `username_input`, `status`, `ip_address`, `user_agent`, `device_type`, `browser`, `os`, `keterangan`, `is_new_ip`, `created_at`) VALUES
(1, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 1, '2026-03-05 15:07:58'),
(2, NULL, 'giano', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-05 15:12:50'),
(3, NULL, 'giano', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-05 15:12:55'),
(4, NULL, 'wiramuhammad16@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-05 15:13:09'),
(5, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-05 15:13:20'),
(6, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-05 19:47:42'),
(7, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-05 20:54:32'),
(8, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-06 08:05:05'),
(9, 8, 'wiramuhammad16@gmail.com', 'berhasil', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Desktop', 'Chrome 145', 'Windows 10/11', NULL, 1, '2026-03-06 08:07:12'),
(10, 8, 'wiramuhammad16@gmail.com', 'berhasil', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Desktop', 'Chrome 145', 'Windows 10/11', NULL, 0, '2026-03-06 08:41:18'),
(11, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-06 08:41:38'),
(12, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-06 13:18:14'),
(13, 12, 'danu', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 1, '2026-03-06 13:18:31'),
(14, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-06 13:19:35'),
(15, 8, 'wiramuhammad16@gmail.com', 'berhasil', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Desktop', 'Chrome 145', 'Windows 10/11', NULL, 0, '2026-03-06 13:22:44'),
(16, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-06 19:04:27'),
(17, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-06 19:15:19'),
(18, 10, 'qiana', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 1, '2026-03-06 21:44:49'),
(19, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-06 22:32:26'),
(20, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 07:52:08'),
(21, 10, 'qiana', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 08:40:40'),
(22, 9, 'giano@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 1, '2026-03-07 08:41:56'),
(23, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 08:42:20'),
(24, 12, 'danu', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 08:42:52'),
(25, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 09:17:55'),
(26, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 10:19:26'),
(27, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 10:27:32'),
(28, 10, 'qiana', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 10:42:52'),
(29, NULL, 'wiramuhammad16@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-07 10:51:43'),
(30, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 10:51:54'),
(31, 10, 'qiana', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 10:58:02'),
(32, NULL, 'giano', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-07 11:00:11'),
(33, 9, 'giano', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 11:00:19'),
(34, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 11:02:01'),
(35, 10, 'qiana', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Password salah', 0, '2026-03-07 11:02:40'),
(36, 10, 'qiana', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 11:02:48'),
(37, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 11:05:27'),
(38, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 19:31:26'),
(39, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-09 09:43:04'),
(40, 10, 'qiana', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-09 22:44:58'),
(41, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-09 22:45:36'),
(42, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-10 09:14:31'),
(43, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-10 19:32:46'),
(44, NULL, 'wiramuhammad16@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-10 19:47:21'),
(45, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-10 19:47:31'),
(46, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-10 19:55:52'),
(47, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-10 19:57:05'),
(48, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-10 20:10:34'),
(49, NULL, 'wiramuhammad16@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-10 20:26:52'),
(50, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-10 20:27:42'),
(51, 14, 'nora@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 1, '2026-03-10 20:28:54'),
(52, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-10 20:29:09'),
(53, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-10 20:41:04'),
(54, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-10 20:50:22'),
(55, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-11 07:53:12'),
(56, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-12 19:46:11'),
(57, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-13 13:16:17'),
(58, 10, 'qiana@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-13 13:43:35'),
(59, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-13 13:45:11'),
(60, 10, 'qiana@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-13 13:51:58'),
(61, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-13 14:01:03'),
(62, 8, 'wiramuhammad16@gmail.com', 'berhasil', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Desktop', 'Chrome 145', 'Windows 10/11', NULL, 0, '2026-03-13 14:03:51'),
(63, 8, 'wiramuhammad16@gmail.com', 'berhasil', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Desktop', 'Chrome 145', 'Windows 10/11', NULL, 0, '2026-03-13 14:05:44'),
(64, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-14 11:31:18'),
(65, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-14 22:04:16'),
(66, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-15 01:22:46'),
(67, 8, 'wiramuhammad16@gmail.com', 'berhasil', '192.168.1.8', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_3_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/145.0.7632.108 Mobile/15E148 Safari/604.1', 'Mobile', 'Safari', 'iOS 26.3.1', NULL, 1, '2026-03-15 01:47:44'),
(68, 8, 'wiramuhammad16@gmail.com', 'berhasil', '192.168.1.8', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_3_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/145.0.7632.108 Mobile/15E148 Safari/604.1', 'Mobile', 'Safari', 'iOS 26.3.1', NULL, 0, '2026-03-15 01:50:23'),
(69, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-16 19:52:13'),
(70, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-16 19:55:25'),
(71, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 10:02:56'),
(72, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 10:31:28'),
(73, 10, 'qiana@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 10:32:03'),
(74, 10, 'qiana@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 10:40:34'),
(75, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 10:44:11'),
(76, 9, 'giano@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 10:47:04'),
(77, 9, 'giano@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 10:48:07'),
(78, NULL, 'wiramuhammad16@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-17 11:06:33'),
(79, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 11:06:43'),
(80, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 13:23:34'),
(81, 10, 'qiana@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 13:27:35'),
(82, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 13:47:04'),
(83, 10, 'qiana@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 13:51:49'),
(84, NULL, 'wiramuhammad16@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-17 14:37:30'),
(85, NULL, 'wiramuhammad16@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-17 14:37:37'),
(86, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 14:37:50'),
(87, NULL, 'qiana@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-17 14:51:08'),
(88, 10, 'qiana@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 14:51:15'),
(89, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 14:51:34'),
(90, 10, 'qiana@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 14:51:58'),
(91, NULL, 'wiramuhammad16@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-17 14:53:30'),
(92, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 14:53:36'),
(93, 8, 'wiramuhammad16@gmail.com', 'berhasil', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Desktop', 'Chrome 145', 'Windows 10/11', NULL, 0, '2026-03-17 14:57:11'),
(94, 10, 'qiana@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 15:15:53'),
(95, 8, 'wiramuhammad16@gmail.com', 'gagal', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Desktop', 'Chrome 145', 'Windows 10/11', 'Password salah', 0, '2026-03-17 15:16:33'),
(96, 8, 'wiramuhammad16@gmail.com', 'berhasil', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Desktop', 'Chrome 145', 'Windows 10/11', NULL, 0, '2026-03-17 15:16:40'),
(97, NULL, 'Qiana@gmail.com', 'gagal', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Desktop', 'Chrome 145', 'Windows 10/11', 'Captcha salah', 0, '2026-03-17 15:17:06'),
(98, 10, 'qiana@gmail.com', 'berhasil', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Desktop', 'Chrome 145', 'Windows 10/11', NULL, 1, '2026-03-17 15:17:19'),
(99, 8, 'wiramuhammad16@gmail.com', 'berhasil', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Desktop', 'Chrome 145', 'Windows 10/11', NULL, 0, '2026-03-17 15:20:36'),
(100, NULL, 'wiramuhammad16@gmail.com', 'gagal', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Desktop', 'Chrome 145', 'Windows 10/11', 'Captcha salah', 0, '2026-03-17 15:28:47'),
(101, 8, 'wiramuhammad16@gmail.com', 'berhasil', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Desktop', 'Chrome 145', 'Windows 10/11', NULL, 0, '2026-03-17 15:28:58'),
(102, NULL, 'giano@gmail.com', 'gagal', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Desktop', 'Chrome 145', 'Windows 10/11', 'Captcha salah', 0, '2026-03-17 15:29:44'),
(103, NULL, 'giano@gmail.com', 'gagal', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Desktop', 'Chrome 145', 'Windows 10/11', 'Captcha salah', 0, '2026-03-17 15:29:52'),
(104, 9, 'giano@gmail.com', 'berhasil', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Desktop', 'Chrome 145', 'Windows 10/11', NULL, 1, '2026-03-17 15:30:09'),
(105, 9, 'giano@gmail.com', 'berhasil', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Desktop', 'Chrome 145', 'Windows 10/11', NULL, 0, '2026-03-17 15:30:44'),
(106, 9, 'giano@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 15:31:05'),
(107, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 21:02:17'),
(108, 12, 'danu@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 21:06:31'),
(109, 14, 'nora@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 21:07:33'),
(110, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 21:19:09'),
(111, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 21:49:24'),
(112, 9, 'giano@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 22:27:19'),
(113, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-17 22:28:11'),
(114, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-18 08:03:06'),
(115, 10, 'qiana@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-18 08:04:24'),
(116, NULL, 'wiramuhammad16@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-18 08:05:41'),
(117, NULL, 'wiramuhammad16@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-18 08:05:50'),
(118, NULL, 'wiramuhammad16@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-18 08:05:57'),
(119, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-18 08:06:08'),
(120, 10, 'qiana@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-18 08:13:33'),
(121, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-18 08:13:53'),
(122, NULL, 'qiana@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-18 08:24:38'),
(123, 10, 'qiana@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-18 08:24:48'),
(124, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-18 08:38:44'),
(125, NULL, 'giano@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-18 08:44:06'),
(126, 9, 'giano@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-18 08:44:17'),
(127, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-18 08:44:44'),
(128, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-18 09:17:07'),
(129, 8, 'wiramuhammad16@gmail.com', 'berhasil', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Desktop', 'Chrome 145', 'Windows 10/11', NULL, 0, '2026-03-18 09:25:26'),
(130, 15, 'rudi@gmail.com', 'berhasil', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'Desktop', 'Chrome 145', 'Windows 10/11', NULL, 1, '2026-03-18 09:26:55'),
(131, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-18 09:40:58'),
(132, 8, 'wiramuhammad16@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Password salah', 0, '2026-03-18 14:20:47'),
(133, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-18 14:20:56'),
(134, NULL, 'wiramuhammad16@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-18 20:39:22'),
(135, NULL, 'wiramuhammad16@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-18 20:39:30'),
(136, NULL, 'wiramuhammad16@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-18 20:39:37'),
(137, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-18 20:39:50'),
(138, 12, 'danu@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-18 21:55:21'),
(139, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-18 21:58:36'),
(140, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-19 20:17:55'),
(141, 12, 'danu@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Password salah', 0, '2026-03-19 20:23:27'),
(142, 12, 'danu@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-19 20:23:34'),
(143, 14, 'nora@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-19 20:25:11'),
(144, 14, 'nora@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Password salah', 0, '2026-03-19 20:25:49'),
(145, 14, 'nora@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-19 20:25:56'),
(146, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-19 20:26:06'),
(147, NULL, 'nora@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-19 20:26:56'),
(148, 14, 'nora@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-19 20:27:04'),
(149, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-19 20:27:49'),
(150, 12, 'danu@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-19 20:35:02'),
(151, 14, 'nora@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-19 20:35:27'),
(152, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-19 20:35:53'),
(153, NULL, 'qiana@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-03-19 22:17:51'),
(154, 10, 'qiana@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-19 22:17:58'),
(155, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-19 22:21:06'),
(156, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-22 21:42:29'),
(157, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-23 21:38:23'),
(158, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-26 19:14:05'),
(159, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-04-04 15:52:58'),
(160, 8, 'wiramuhammad16@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Password salah', 0, '2026-04-05 12:39:56'),
(161, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-04-05 12:40:04'),
(162, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-04-14 09:35:45'),
(163, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-04-14 09:46:35'),
(164, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-04-14 09:49:59'),
(165, NULL, 'giano@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-04-14 15:19:21'),
(166, 9, 'giano@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-04-14 15:19:33'),
(167, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-04-14 15:20:00'),
(168, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-04-15 08:53:48'),
(169, 8, 'wiramuhammad16@gmail.com', 'berhasil', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'Desktop', 'Chrome 147', 'Windows 10/11', NULL, 0, '2026-04-15 09:23:57'),
(170, 8, 'wiramuhammad16@gmail.com', 'berhasil', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'Desktop', 'Chrome 147', 'Windows 10/11', NULL, 0, '2026-04-15 09:46:38'),
(171, 8, 'wiramuhammad16@gmail.com', 'berhasil', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'Desktop', 'Chrome 147', 'Windows 10/11', NULL, 0, '2026-04-15 10:07:38'),
(172, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-04-15 14:49:25'),
(173, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-04-15 18:56:38'),
(174, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-04-15 19:16:21'),
(175, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-04-15 19:45:11'),
(176, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-04-15 19:51:43'),
(177, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-04-16 10:11:38'),
(178, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-04-16 10:57:31'),
(179, NULL, 'qiana@gmail.com', 'gagal', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', 'Captcha salah', 0, '2026-04-16 13:13:51'),
(180, 10, 'qiana@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-04-16 13:14:05'),
(181, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-04-16 13:14:30'),
(182, 8, 'wiramuhammad16@gmail.com', 'berhasil', '172.16.10.134', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', 'Desktop', 'Chrome 147', 'Windows 10/11', NULL, 0, '2026-04-16 13:24:57');

-- --------------------------------------------------------

--
-- Table structure for table `lokasi_absen`
--

CREATE TABLE `lokasi_absen` (
  `id` int(10) UNSIGNED NOT NULL,
  `nama` varchar(100) NOT NULL,
  `alamat` varchar(255) DEFAULT NULL,
  `lat` decimal(10,7) NOT NULL,
  `lon` decimal(10,7) NOT NULL,
  `radius` int(11) NOT NULL DEFAULT 100 COMMENT 'meter',
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `keterangan` varchar(255) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `lokasi_absen`
--

INSERT INTO `lokasi_absen` (`id`, `nama`, `alamat`, `lat`, `lon`, `radius`, `status`, `keterangan`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'Kantor', 'Kantor', '-1.5228592', '102.1250838', 100, 'aktif', 'Kantor', 8, 8, '2026-03-16 13:30:11', '2026-03-16 13:59:01'),
(2, 'Gudang Obat', 'Gudang Obat', '-1.5228527', '102.1250840', 100, 'aktif', 'Gudang Obat', 8, 8, '2026-03-16 13:30:29', '2026-03-16 13:39:03'),
(3, '.Kantor', 'Kantor', '-1.4883776', '102.1031685', 100, 'aktif', NULL, 8, 8, '2026-03-16 13:40:29', '2026-03-17 03:18:30'),
(4, 'Gedung 1', 'Gedung 1', '-1.5228541', '102.1250839', 100, 'aktif', 'Gedung 1', 8, 8, '2026-03-16 13:59:32', '2026-03-16 13:59:32'),
(5, 'Parikir', 'parkir', '-62.0000000', '108.0000000', 100, 'aktif', 'parkir', 8, 8, '2026-03-16 14:02:49', '2026-03-16 14:02:49'),
(6, 'Rumah', 'Rumah', '-1.5230103', '102.1250839', 100, 'aktif', NULL, 8, 8, '2026-03-18 13:47:26', '2026-03-18 13:47:26');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_ipsrs`
--

CREATE TABLE `maintenance_ipsrs` (
  `id` int(10) UNSIGNED NOT NULL,
  `no_maintenance` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Format: MNT-IPSRS-YYYYMM-0001',
  `aset_id` int(10) UNSIGNED DEFAULT NULL,
  `aset_nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Snapshot nama aset saat dicatat',
  `teknisi_id` int(10) UNSIGNED DEFAULT NULL,
  `teknisi_nama` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Snapshot nama teknisi saat dicatat',
  `tgl_maintenance` date NOT NULL,
  `tgl_maintenance_berikut` date DEFAULT NULL COMMENT 'Otomatis +3 bulan dari tgl_maintenance',
  `jenis_maintenance` enum('Preventif','Korektif','Rutin Bulanan','Penggantian Part','Kalibrasi','Inspeksi','Servis Berkala','Lainnya') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Preventif',
  `kondisi_sebelum` enum('Baik','Dalam Perbaikan','Rusak','Tidak Aktif') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kondisi_sesudah` enum('Baik','Dalam Perbaikan','Rusak','Tidak Aktif') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `temuan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Temuan / masalah saat maintenance',
  `tindakan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tindakan yang dilakukan',
  `biaya` int(10) UNSIGNED DEFAULT NULL COMMENT 'Biaya maintenance dalam Rupiah',
  `status` enum('Selesai','Dalam Proses','Ditunda','Dibatalkan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Selesai',
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catatan maintenance aset IPSRS (medis & non-medis)';

--
-- Dumping data for table `maintenance_ipsrs`
--

INSERT INTO `maintenance_ipsrs` (`id`, `no_maintenance`, `aset_id`, `aset_nama`, `teknisi_id`, `teknisi_nama`, `tgl_maintenance`, `tgl_maintenance_berikut`, `jenis_maintenance`, `kondisi_sebelum`, `kondisi_sesudah`, `temuan`, `tindakan`, `biaya`, `status`, `keterangan`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'MNT-IPSRS-202603-0001', 8, 'INV-IPSRS-2024-0007 – AC Central Chiller', NULL, '', '2026-03-04', '2026-06-04', 'Preventif', 'Baik', 'Baik', 'tes', 'tes', 170000, 'Selesai', 'tes', 8, '2026-03-04 15:23:32', NULL),
(2, 'MNT-IPSRS-202603-0002', 10, 'INV-IPSRS-2024-0009 – Ambulans Transport', 9, 'Giano', '2026-03-06', '2026-06-06', 'Lainnya', 'Baik', 'Baik', '-', '-', 500000, 'Selesai', '-', 8, '2026-03-06 13:46:46', NULL),
(3, 'MNT-IPSRS-202603-0003', 4, 'INV-IPSRS-2024-0003 – Defibrilator AED', 9, 'Giano', '2026-03-06', '2026-06-06', 'Rutin Bulanan', 'Baik', 'Baik', '-', '-', 430000, 'Selesai', '-', 8, '2026-03-06 13:47:32', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_it`
--

CREATE TABLE `maintenance_it` (
  `id` int(10) UNSIGNED NOT NULL,
  `no_maintenance` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Format: MNT-YYYYMM-0001',
  `aset_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK → aset_it.id',
  `aset_nama` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Cache nama aset (no_inv – nama_aset)',
  `teknisi_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK → users.id',
  `teknisi_nama` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Cache nama teknisi',
  `tgl_maintenance` date NOT NULL COMMENT 'Tanggal maintenance dilakukan',
  `tgl_maintenance_berikut` date DEFAULT NULL COMMENT 'Pengingat: tgl_maintenance + 3 bulan',
  `jenis_maintenance` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Preventif, Korektif, Rutin, dll',
  `kondisi_sebelum` enum('Baik','Dalam Perbaikan','Rusak','Tidak Aktif') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kondisi_sesudah` enum('Baik','Dalam Perbaikan','Rusak','Tidak Aktif') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `temuan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Temuan / masalah yang ditemukan',
  `tindakan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tindakan yang dilakukan',
  `biaya` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'Biaya maintenance dalam Rupiah',
  `status` enum('Selesai','Dalam Proses','Ditunda','Dibatalkan') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Selesai',
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK → users.id',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catatan Maintenance / Perawatan Aset IT';

--
-- Dumping data for table `maintenance_it`
--

INSERT INTO `maintenance_it` (`id`, `no_maintenance`, `aset_id`, `aset_nama`, `teknisi_id`, `teknisi_nama`, `tgl_maintenance`, `tgl_maintenance_berikut`, `jenis_maintenance`, `kondisi_sebelum`, `kondisi_sesudah`, `temuan`, `tindakan`, `biaya`, `status`, `keterangan`, `created_by`, `created_at`, `updated_at`) VALUES
(5, 'MNT-202602-0001', 6, 'INV-IT-2026-0006 – CPU Build UP', 11, 'budi', '2026-02-26', '2026-05-26', 'Lainnya', 'Baik', 'Baik', 'bagus semua', 'bagus semua', 0, 'Selesai', '-', 8, '2026-02-26 21:27:01', NULL),
(6, 'MNT-202602-0002', 1, 'INV-IT-2025-0001 – Laptop Dell Latitude', 9, 'Giano', '2026-02-26', '2026-05-26', 'Rutin Bulanan', 'Baik', 'Baik', 'Bagus', 'Bagus', 0, 'Selesai', 'ganti pasta', 8, '2026-02-26 22:25:28', NULL),
(7, 'MNT-202602-0003', 5, 'INV-IT-2025-0005 – Monitor LG 24 inch', 9, 'Giano', '2026-02-28', '2026-05-28', 'Rutin Bulanan', 'Baik', 'Baik', '-', '-', 75000, 'Selesai', 'beli pasta', 8, '2026-02-28 08:48:11', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `master_jenis_berkas`
--

CREATE TABLE `master_jenis_berkas` (
  `id` int(10) UNSIGNED NOT NULL,
  `kategori_id` int(10) UNSIGNED NOT NULL,
  `nama` varchar(150) NOT NULL,
  `icon` varchar(50) DEFAULT 'fa-file',
  `keterangan` text DEFAULT NULL,
  `wajib` tinyint(1) DEFAULT 0,
  `has_exp` tinyint(1) DEFAULT 0,
  `has_tgl_terbit` tinyint(1) DEFAULT 0,
  `format_file` varchar(100) DEFAULT 'pdf,jpg,jpeg,png',
  `urutan` tinyint(3) UNSIGNED DEFAULT 0,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `master_jenis_berkas`
--

INSERT INTO `master_jenis_berkas` (`id`, `kategori_id`, `nama`, `icon`, `keterangan`, `wajib`, `has_exp`, `has_tgl_terbit`, `format_file`, `urutan`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(42, 4, 'FC KTP', 'fa-id-card', 'Kartu Tanda Penduduk', 1, 0, 0, 'pdf,jpg,jpeg,png', 0, 'aktif', 8, '2026-04-14 14:13:02', '2026-04-14 14:13:02'),
(43, 3, 'STR', 'fa-briefcase', 'Surat Tanda Registrasi', 0, 1, 1, 'pdf,jpg,jpeg,png', 0, 'aktif', 8, '2026-04-14 14:13:34', '2026-04-14 14:13:34'),
(44, 4, 'SKCK', 'fa-shield', 'SKCK', 1, 0, 1, 'pdf,jpg,jpeg,png', 0, 'aktif', 8, '2026-04-14 14:13:57', '2026-04-14 14:13:57'),
(45, 3, 'SIP', 'fa-graduation-cap', 'Surat Izin Praktik', 0, 1, 1, 'pdf,jpg,jpeg,png', 0, 'aktif', 8, '2026-04-14 14:14:39', '2026-04-14 14:14:39'),
(46, 4, 'Kartu BPJS Kes', 'fa-hospital', 'Kartu BPJS Kesehatan', 1, 0, 0, 'pdf,jpg,jpeg,png', 0, 'aktif', 8, '2026-04-14 14:15:16', '2026-04-14 14:15:16'),
(47, 2, 'Sertifikat BTCLS', 'fa-certificate', 'Sertifikat Pelatihan BTCLS', 1, 0, 1, 'pdf,jpg,jpeg,png', 0, 'aktif', 8, '2026-04-14 14:16:28', '2026-04-14 14:16:28');

-- --------------------------------------------------------

--
-- Table structure for table `master_jenis_cuti`
--

CREATE TABLE `master_jenis_cuti` (
  `id` int(10) UNSIGNED NOT NULL,
  `kode` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `kuota_default` tinyint(3) UNSIGNED NOT NULL DEFAULT 12 COMMENT 'Hari per tahun (0 = tidak terbatas)',
  `perlu_lampiran` tinyint(1) NOT NULL DEFAULT 0,
  `keterangan` varchar(255) DEFAULT NULL,
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `urutan` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `master_jenis_cuti`
--

INSERT INTO `master_jenis_cuti` (`id`, `kode`, `nama`, `kuota_default`, `perlu_lampiran`, `keterangan`, `status`, `urutan`, `created_at`) VALUES
(7, 'TAHUNAN', 'Cuti Tahunan', 12, 0, 'Cuti Tahunan', 'aktif', 0, '2026-03-19 20:22:20'),
(8, 'MELAHIRKAN', 'Cuti Melahirkan', 60, 1, NULL, 'aktif', 0, '2026-03-19 20:43:05');

-- --------------------------------------------------------

--
-- Table structure for table `master_kategori_berkas`
--

CREATE TABLE `master_kategori_berkas` (
  `id` int(10) UNSIGNED NOT NULL,
  `nama` varchar(100) NOT NULL,
  `icon` varchar(50) DEFAULT 'fa-folder',
  `warna` varchar(20) DEFAULT '#6366f1',
  `urutan` tinyint(3) UNSIGNED DEFAULT 0,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `master_kategori_berkas`
--

INSERT INTO `master_kategori_berkas` (`id`, `nama`, `icon`, `warna`, `urutan`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Identitas & Administrasi', 'fa-id-card', '#1d4ed8', 1, 'aktif', '2026-04-14 13:54:17', '2026-04-14 13:54:17'),
(2, 'Pendidikan & Pelatihan', 'fa-graduation-cap', '#059669', 2, 'aktif', '2026-04-14 13:54:17', '2026-04-14 13:54:17'),
(3, 'Lisensi & Registrasi', 'fa-id-badge', '#9d174d', 3, 'aktif', '2026-04-14 13:54:17', '2026-04-14 13:54:17'),
(4, 'Kepegawaian', 'fa-briefcase', '#b45309', 4, 'aktif', '2026-04-14 13:54:17', '2026-04-14 13:54:17'),
(5, 'Jaminan & Keuangan', 'fa-building-columns', '#5b21b6', 5, 'aktif', '2026-04-14 13:54:17', '2026-04-14 13:54:17'),
(6, 'Berkas Tambahan', 'fa-folder-plus', '#475569', 6, 'aktif', '2026-04-14 13:54:17', '2026-04-14 13:54:17');

-- --------------------------------------------------------

--
-- Table structure for table `master_penerimaan`
--

CREATE TABLE `master_penerimaan` (
  `id` int(10) UNSIGNED NOT NULL,
  `kode` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jenis` enum('tetap','variable') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tetap',
  `nilai_default` decimal(15,2) NOT NULL DEFAULT 0.00,
  `kena_pajak` tinyint(1) NOT NULL DEFAULT 0,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `keterangan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `master_penerimaan`
--

INSERT INTO `master_penerimaan` (`id`, `kode`, `nama`, `jenis`, `nilai_default`, `kena_pajak`, `aktif`, `keterangan`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES
(7, 'TKP01', 'Masa Kerja', 'tetap', '750000.00', 0, 1, 'Tunjangan Masa Kerja', 8, '2026-04-16 11:00:06', NULL, NULL),
(8, 'TKP02', 'Masa Kerja', 'tetap', '1000000.00', 0, 1, 'Tunjangan Masa Kerja', 8, '2026-04-16 11:00:31', NULL, NULL),
(9, 'TKP03', 'Kesehatan', 'tetap', '500000.00', 0, 1, 'Tunjangan Kesehatan', 8, '2026-04-16 11:00:54', NULL, NULL),
(10, 'TKP04', 'Fungsional', 'tetap', '1500000.00', 0, 1, 'Tunjangan Fungsional', 8, '2026-04-16 11:01:18', NULL, NULL),
(11, 'TKP05', 'Fungsional', 'tetap', '1000000.00', 0, 1, 'Tunjangan Fungsional', 8, '2026-04-16 11:01:40', NULL, NULL),
(12, 'TKP06', 'Struktural', 'tetap', '1750000.00', 0, 1, 'Tunjangan Struktural', 8, '2026-04-16 11:02:04', NULL, NULL),
(13, 'TKP07', 'Gaji Pokok', 'tetap', '3450000.00', 0, 1, 'Gaji Pokok', 8, '2026-04-16 11:02:26', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `master_pokja`
--

CREATE TABLE `master_pokja` (
  `id` int(10) UNSIGNED NOT NULL,
  `kode` varchar(20) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `ketua` varchar(100) DEFAULT NULL,
  `ketua_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `urutan` tinyint(3) UNSIGNED DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `master_pokja`
--

INSERT INTO `master_pokja` (`id`, `kode`, `nama`, `deskripsi`, `ketua`, `ketua_id`, `status`, `urutan`, `created_at`, `updated_at`) VALUES
(1, 'TKRS', 'Tata Kelola Rumah Sakit', '-', 'budi', 2, 'aktif', 1, '2026-03-17 21:49:47', '2026-03-18 08:46:56'),
(2, 'MIRM', 'Manajemen Informasi dan Rekam Medis', '-', 'Danu', 4, 'aktif', 0, '2026-03-17 21:52:48', '2026-03-18 08:46:46'),
(3, 'PN', 'Prognas (Program Nasional)', '-', NULL, NULL, 'aktif', 0, '2026-03-18 08:46:20', '2026-03-18 08:46:20'),
(4, 'PPI', 'Pencegahan dan Pengendalian Infeksi', '-', NULL, NULL, 'aktif', 0, '2026-03-18 08:47:19', '2026-03-18 08:47:19');

-- --------------------------------------------------------

--
-- Table structure for table `master_potongan`
--

CREATE TABLE `master_potongan` (
  `id` int(10) UNSIGNED NOT NULL,
  `kode` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jenis` enum('tetap','variable') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tetap',
  `tipe_hitung` enum('nominal','persen') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'nominal',
  `nilai_default` decimal(15,2) NOT NULL DEFAULT 0.00,
  `persentase` decimal(5,2) NOT NULL DEFAULT 0.00,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `keterangan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `master_potongan`
--

INSERT INTO `master_potongan` (`id`, `kode`, `nama`, `jenis`, `tipe_hitung`, `nilai_default`, `persentase`, `aktif`, `keterangan`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES
(6, 'TKPT01', 'BPJS Kes', 'tetap', 'nominal', '45000.00', '0.00', 1, 'Bpjs Kesehatan', 8, '2026-04-16 11:02:57', 8, '2026-04-16 11:03:29'),
(7, 'TKPT02', 'BPJS TK JHT', 'tetap', 'nominal', '65000.00', '0.00', 1, 'BPJS Ketenagakerjaan', 8, '2026-04-16 11:03:25', 8, '2026-04-16 11:03:43'),
(8, 'TKPT03', 'BPJS TK JP', 'tetap', 'nominal', '30000.00', '0.00', 1, 'BPJS Ketenagakerjaan/Pensiun', 8, '2026-04-16 11:04:33', NULL, NULL),
(9, 'TKPT04', 'Dana Sosial', 'tetap', 'nominal', '20000.00', '0.00', 1, 'Dana Sosial', 8, '2026-04-16 11:05:15', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `master_pph21_ptkp`
--

CREATE TABLE `master_pph21_ptkp` (
  `id` int(10) UNSIGNED NOT NULL,
  `kode` varchar(10) NOT NULL COMMENT 'misal: TER_A, TER_B, TER_C',
  `nama` varchar(100) NOT NULL COMMENT 'misal: TER A',
  `status_kawin` varchar(200) NOT NULL COMMENT 'misal: TK/0',
  `ptkp_setahun` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Nilai PTKP per tahun',
  `keterangan` varchar(255) DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `urutan` tinyint(4) NOT NULL DEFAULT 0,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `master_pph21_ptkp`
--

INSERT INTO `master_pph21_ptkp` (`id`, `kode`, `nama`, `status_kawin`, `ptkp_setahun`, `keterangan`, `aktif`, `urutan`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES
(1, 'TER_A', 'TER A', 'TK/0', '54000000.00', 'Tidak Kawin tanpa tanggungan', 1, 1, NULL, NULL, NULL, NULL),
(2, 'TER_B', 'TER B', 'TK/1, K/0', '58500000.00', 'Tidak Kawin 1 tanggungan / Kawin tanpa tanggungan', 1, 2, NULL, NULL, NULL, NULL),
(3, 'TER_C', 'TER C', 'TK/2, TK/3, K/1, K/2, K/3', '63000000.00', 'Kawin dengan tanggungan', 1, 3, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `master_pph21_ter`
--

CREATE TABLE `master_pph21_ter` (
  `id` int(10) UNSIGNED NOT NULL,
  `ptkp_id` int(10) UNSIGNED NOT NULL COMMENT 'FK ke master_pph21_ptkp',
  `penghasilan_dari` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Batas bawah bruto bulanan',
  `penghasilan_sd` decimal(15,2) DEFAULT NULL COMMENT 'Batas atas, NULL = tak terbatas',
  `tarif_persen` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Tarif efektif %',
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `master_pph21_ter`
--

INSERT INTO `master_pph21_ter` (`id`, `ptkp_id`, `penghasilan_dari`, `penghasilan_sd`, `tarif_persen`, `aktif`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES
(1, 1, '0.00', '5400000.00', '0.00', 1, NULL, NULL, NULL, NULL),
(2, 1, '5400000.00', '5650000.00', '0.25', 1, NULL, NULL, NULL, NULL),
(3, 1, '5650000.00', '5950000.00', '0.50', 1, NULL, NULL, NULL, NULL),
(4, 1, '5950000.00', '6300000.00', '0.75', 1, NULL, NULL, NULL, NULL),
(5, 1, '6300000.00', '6750000.00', '1.00', 1, NULL, NULL, NULL, NULL),
(6, 1, '6750000.00', '7500000.00', '1.25', 1, NULL, NULL, NULL, NULL),
(7, 1, '7500000.00', '8550000.00', '1.50', 1, NULL, NULL, NULL, NULL),
(8, 1, '8550000.00', '9650000.00', '2.00', 1, NULL, NULL, NULL, NULL),
(9, 1, '9650000.00', '10050000.00', '2.50', 1, NULL, NULL, NULL, NULL),
(10, 1, '10050000.00', '10350000.00', '3.00', 1, NULL, NULL, NULL, NULL),
(11, 1, '10350000.00', '10700000.00', '3.50', 1, NULL, NULL, NULL, NULL),
(12, 1, '10700000.00', '11050000.00', '4.00', 1, NULL, NULL, NULL, NULL),
(13, 1, '11050000.00', '11600000.00', '4.50', 1, NULL, NULL, NULL, NULL),
(14, 1, '11600000.00', '12500000.00', '5.00', 1, NULL, NULL, NULL, NULL),
(15, 1, '12500000.00', '13750000.00', '5.50', 1, NULL, NULL, NULL, NULL),
(16, 1, '13750000.00', '15100000.00', '6.00', 1, NULL, NULL, NULL, NULL),
(17, 1, '15100000.00', '16950000.00', '7.00', 1, NULL, NULL, NULL, NULL),
(18, 1, '16950000.00', '19750000.00', '7.50', 1, NULL, NULL, NULL, NULL),
(19, 1, '19750000.00', '24150000.00', '8.50', 1, NULL, NULL, NULL, NULL),
(20, 1, '24150000.00', '26450000.00', '9.50', 1, NULL, NULL, NULL, NULL),
(21, 1, '26450000.00', '28000000.00', '10.00', 1, NULL, NULL, NULL, NULL),
(22, 1, '28000000.00', '30050000.00', '11.00', 1, NULL, NULL, NULL, NULL),
(23, 1, '30050000.00', '32400000.00', '12.00', 1, NULL, NULL, NULL, NULL),
(24, 1, '32400000.00', '35400000.00', '13.00', 1, NULL, NULL, NULL, NULL),
(25, 1, '35400000.00', '39100000.00', '14.00', 1, NULL, NULL, NULL, NULL),
(26, 1, '39100000.00', '43850000.00', '15.00', 1, NULL, NULL, NULL, NULL),
(27, 1, '43850000.00', '47800000.00', '16.00', 1, NULL, NULL, NULL, NULL),
(28, 1, '47800000.00', '51400000.00', '17.00', 1, NULL, NULL, NULL, NULL),
(29, 1, '51400000.00', '56300000.00', '18.00', 1, NULL, NULL, NULL, NULL),
(30, 1, '56300000.00', '62200000.00', '19.00', 1, NULL, NULL, NULL, NULL),
(31, 1, '62200000.00', '77100000.00', '20.00', 1, NULL, NULL, NULL, NULL),
(32, 1, '77100000.00', '103600000.00', '21.00', 1, NULL, NULL, NULL, NULL),
(33, 1, '103600000.00', '134000000.00', '22.00', 1, NULL, NULL, NULL, NULL),
(34, 1, '134000000.00', '169000000.00', '23.00', 1, NULL, NULL, NULL, NULL),
(35, 1, '169000000.00', '213000000.00', '24.00', 1, NULL, NULL, NULL, NULL),
(36, 1, '213000000.00', '337000000.00', '25.00', 1, NULL, NULL, NULL, NULL),
(37, 1, '337000000.00', '405000000.00', '26.00', 1, NULL, NULL, NULL, NULL),
(38, 1, '405000000.00', '405000000.00', '27.00', 1, NULL, NULL, NULL, NULL),
(39, 1, '405000000.00', NULL, '30.00', 1, NULL, NULL, NULL, NULL),
(40, 2, '0.00', '6200000.00', '0.00', 1, NULL, NULL, NULL, NULL),
(41, 2, '6200000.00', '6500000.00', '0.25', 1, NULL, NULL, NULL, NULL),
(42, 2, '6500000.00', '6850000.00', '0.50', 1, NULL, NULL, NULL, NULL),
(43, 2, '6850000.00', '7300000.00', '0.75', 1, NULL, NULL, NULL, NULL),
(44, 2, '7300000.00', '9200000.00', '1.00', 1, NULL, NULL, NULL, NULL),
(45, 2, '9200000.00', '10750000.00', '1.50', 1, NULL, NULL, NULL, NULL),
(46, 2, '10750000.00', '11250000.00', '2.00', 1, NULL, NULL, NULL, NULL),
(47, 2, '11250000.00', '11600000.00', '2.50', 1, NULL, NULL, NULL, NULL),
(48, 2, '11600000.00', '12600000.00', '3.00', 1, NULL, NULL, NULL, NULL),
(49, 2, '12600000.00', '13600000.00', '3.50', 1, NULL, NULL, NULL, NULL),
(50, 2, '13600000.00', '14950000.00', '4.00', 1, NULL, NULL, NULL, NULL),
(51, 2, '14950000.00', '16400000.00', '5.00', 1, NULL, NULL, NULL, NULL),
(52, 2, '16400000.00', '18450000.00', '6.00', 1, NULL, NULL, NULL, NULL),
(53, 2, '18450000.00', '21850000.00', '7.00', 1, NULL, NULL, NULL, NULL),
(54, 2, '21850000.00', '26000000.00', '8.00', 1, NULL, NULL, NULL, NULL),
(55, 2, '26000000.00', '27700000.00', '9.00', 1, NULL, NULL, NULL, NULL),
(56, 2, '27700000.00', '29350000.00', '10.00', 1, NULL, NULL, NULL, NULL),
(57, 2, '29350000.00', '31450000.00', '11.00', 1, NULL, NULL, NULL, NULL),
(58, 2, '31450000.00', '33950000.00', '12.00', 1, NULL, NULL, NULL, NULL),
(59, 2, '33950000.00', '37100000.00', '13.00', 1, NULL, NULL, NULL, NULL),
(60, 2, '37100000.00', '41100000.00', '14.00', 1, NULL, NULL, NULL, NULL),
(61, 2, '41100000.00', '45800000.00', '15.00', 1, NULL, NULL, NULL, NULL),
(62, 2, '45800000.00', '49500000.00', '16.00', 1, NULL, NULL, NULL, NULL),
(63, 2, '49500000.00', '53800000.00', '17.00', 1, NULL, NULL, NULL, NULL),
(64, 2, '53800000.00', '58500000.00', '18.00', 1, NULL, NULL, NULL, NULL),
(65, 2, '58500000.00', '64000000.00', '19.00', 1, NULL, NULL, NULL, NULL),
(66, 2, '64000000.00', '71000000.00', '20.00', 1, NULL, NULL, NULL, NULL),
(67, 2, '71000000.00', '80000000.00', '21.00', 1, NULL, NULL, NULL, NULL),
(68, 2, '80000000.00', '93000000.00', '22.00', 1, NULL, NULL, NULL, NULL),
(69, 2, '93000000.00', '133000000.00', '23.00', 1, NULL, NULL, NULL, NULL),
(70, 2, '133000000.00', '173000000.00', '24.00', 1, NULL, NULL, NULL, NULL),
(71, 2, '173000000.00', NULL, '25.00', 1, NULL, NULL, NULL, NULL),
(72, 3, '0.00', '6600000.00', '0.00', 1, NULL, NULL, NULL, NULL),
(73, 3, '6600000.00', '6950000.00', '0.25', 1, NULL, NULL, NULL, NULL),
(74, 3, '6950000.00', '7350000.00', '0.50', 1, NULL, NULL, NULL, NULL),
(75, 3, '7350000.00', '7800000.00', '0.75', 1, NULL, NULL, NULL, NULL),
(76, 3, '7800000.00', '8850000.00', '1.00', 1, NULL, NULL, NULL, NULL),
(77, 3, '8850000.00', '9800000.00', '1.25', 1, NULL, NULL, NULL, NULL),
(78, 3, '9800000.00', '10950000.00', '1.50', 1, NULL, NULL, NULL, NULL),
(79, 3, '10950000.00', '11200000.00', '2.00', 1, NULL, NULL, NULL, NULL),
(80, 3, '11200000.00', '12050000.00', '2.50', 1, NULL, NULL, NULL, NULL),
(81, 3, '12050000.00', '12950000.00', '3.00', 1, NULL, NULL, NULL, NULL),
(82, 3, '12950000.00', '14150000.00', '3.50', 1, NULL, NULL, NULL, NULL),
(83, 3, '14150000.00', '15550000.00', '4.00', 1, NULL, NULL, NULL, NULL),
(84, 3, '15550000.00', '17050000.00', '5.00', 1, NULL, NULL, NULL, NULL),
(85, 3, '17050000.00', '19500000.00', '6.00', 1, NULL, NULL, NULL, NULL),
(86, 3, '19500000.00', '22700000.00', '7.00', 1, NULL, NULL, NULL, NULL),
(87, 3, '22700000.00', '26600000.00', '8.00', 1, NULL, NULL, NULL, NULL),
(88, 3, '26600000.00', '28100000.00', '9.00', 1, NULL, NULL, NULL, NULL),
(89, 3, '28100000.00', '30100000.00', '10.00', 1, NULL, NULL, NULL, NULL),
(90, 3, '30100000.00', '32600000.00', '11.00', 1, NULL, NULL, NULL, NULL),
(91, 3, '32600000.00', '35400000.00', '12.00', 1, NULL, NULL, NULL, NULL),
(92, 3, '35400000.00', '38900000.00', '13.00', 1, NULL, NULL, NULL, NULL),
(93, 3, '38900000.00', '43000000.00', '14.00', 1, NULL, NULL, NULL, NULL),
(94, 3, '43000000.00', '47400000.00', '15.00', 1, NULL, NULL, NULL, NULL),
(95, 3, '47400000.00', '51200000.00', '16.00', 1, NULL, NULL, NULL, NULL),
(96, 3, '51200000.00', '55800000.00', '17.00', 1, NULL, NULL, NULL, NULL),
(97, 3, '55800000.00', '60400000.00', '18.00', 1, NULL, NULL, NULL, NULL),
(98, 3, '60400000.00', '66700000.00', '19.00', 1, NULL, NULL, NULL, NULL),
(99, 3, '66700000.00', '74500000.00', '20.00', 1, NULL, NULL, NULL, NULL),
(100, 3, '74500000.00', '83200000.00', '21.00', 1, NULL, NULL, NULL, NULL),
(101, 3, '83200000.00', '95600000.00', '22.00', 1, NULL, NULL, NULL, NULL),
(102, 3, '95600000.00', '110000000.00', '23.00', 1, NULL, NULL, NULL, NULL),
(103, 3, '110000000.00', '134000000.00', '24.00', 1, NULL, NULL, NULL, NULL),
(104, 3, '134000000.00', NULL, '25.00', 1, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `master_shift`
--

CREATE TABLE `master_shift` (
  `id` int(10) UNSIGNED NOT NULL,
  `kode` varchar(10) NOT NULL,
  `nama` varchar(80) NOT NULL,
  `jam_masuk` time NOT NULL,
  `jam_keluar` time NOT NULL,
  `lintas_hari` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = shift malam melewati tengah malam',
  `toleransi_masuk` smallint(6) NOT NULL DEFAULT 15 COMMENT 'menit keterlambatan yg dimaafkan',
  `toleransi_pulang` smallint(6) NOT NULL DEFAULT 0 COMMENT 'menit lebih cepat pulang yg dimaafkan',
  `durasi_istirahat` smallint(6) NOT NULL DEFAULT 60 COMMENT 'menit',
  `warna` varchar(7) NOT NULL DEFAULT '#6366f1',
  `jenis` enum('pagi','siang','malam','reguler','oncall','custom') NOT NULL DEFAULT 'reguler',
  `berlaku_untuk` varchar(30) NOT NULL DEFAULT 'semua' COMMENT 'semua / medis / non-medis',
  `deskripsi` varchar(255) DEFAULT NULL,
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `urutan` smallint(6) NOT NULL DEFAULT 0,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `master_shift`
--

INSERT INTO `master_shift` (`id`, `kode`, `nama`, `jam_masuk`, `jam_keluar`, `lintas_hari`, `toleransi_masuk`, `toleransi_pulang`, `durasi_istirahat`, `warna`, `jenis`, `berlaku_untuk`, `deskripsi`, `status`, `urutan`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(26, 'K1', 'Kantor1', '08:00:00', '16:00:00', 0, 15, 0, 60, '#10b981', 'reguler', 'non-medis', 'jam kantor senin sd jumat', 'aktif', 1, 8, 8, '2026-03-16 20:04:35', '2026-03-16 20:04:35'),
(27, 'K2', 'Kantor2', '08:00:00', '13:00:00', 1, 15, 0, 0, '#10b981', 'reguler', 'semua', 'Jam kantor hari sabtu', 'aktif', 2, 8, 8, '2026-03-16 20:05:07', '2026-03-16 20:07:08'),
(28, 'S1', 'Pagi', '08:00:00', '14:00:00', 1, 15, 0, 0, '#6366f1', 'pagi', 'medis', 'Jam dinas medis pagi', 'aktif', 1, 8, 8, '2026-03-16 20:05:51', '2026-03-16 20:07:37'),
(29, 'S2', 'Siang', '14:00:00', '20:00:00', 0, 15, 0, 0, '#8b5cf6', 'siang', 'medis', 'Jam dinas medis siang', 'aktif', 0, 8, 8, '2026-03-16 20:06:17', '2026-03-16 20:15:29'),
(30, 'S3', 'Malam', '20:00:00', '08:00:00', 1, 15, 0, 0, '#64748b', 'reguler', 'medis', 'Jam dinas medis malam', 'aktif', 0, 8, 8, '2026-03-16 20:06:50', '2026-03-16 20:07:31');

-- --------------------------------------------------------

--
-- Table structure for table `mutasi_aset`
--

CREATE TABLE `mutasi_aset` (
  `id` int(10) UNSIGNED NOT NULL,
  `no_mutasi` varchar(30) NOT NULL,
  `aset_id` int(10) UNSIGNED NOT NULL,
  `tanggal_mutasi` date NOT NULL,
  `jenis` enum('pindah_lokasi','pindah_pic','keduanya') NOT NULL DEFAULT 'keduanya',
  `dari_bagian_id` int(10) UNSIGNED DEFAULT NULL,
  `dari_bagian_nama` varchar(100) DEFAULT NULL,
  `dari_pic_id` int(10) UNSIGNED DEFAULT NULL,
  `dari_pic_nama` varchar(100) DEFAULT NULL,
  `ke_bagian_id` int(10) UNSIGNED DEFAULT NULL,
  `ke_bagian_nama` varchar(100) DEFAULT NULL,
  `ke_pic_id` int(10) UNSIGNED DEFAULT NULL,
  `ke_pic_nama` varchar(100) DEFAULT NULL,
  `kondisi_sebelum` varchar(50) DEFAULT NULL,
  `kondisi_sesudah` varchar(50) DEFAULT NULL,
  `status_pakai` varchar(30) DEFAULT 'Terpakai',
  `keterangan` text DEFAULT NULL,
  `dibuat_oleh` int(10) UNSIGNED DEFAULT NULL,
  `dibuat_nama` varchar(100) DEFAULT NULL,
  `status_mutasi` enum('draft','selesai','batal') NOT NULL DEFAULT 'selesai',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `mutasi_aset`
--

INSERT INTO `mutasi_aset` (`id`, `no_mutasi`, `aset_id`, `tanggal_mutasi`, `jenis`, `dari_bagian_id`, `dari_bagian_nama`, `dari_pic_id`, `dari_pic_nama`, `ke_bagian_id`, `ke_bagian_nama`, `ke_pic_id`, `ke_pic_nama`, `kondisi_sebelum`, `kondisi_sesudah`, `status_pakai`, `keterangan`, `dibuat_oleh`, `dibuat_nama`, `status_mutasi`, `created_at`) VALUES
(1, 'MUT-202603-0001', 6, '2026-03-13', 'keduanya', 10, 'Gudang IT', 9, 'Giano', 1, 'IT', 11, 'budi', 'Baik', 'Baik', 'Terpakai', '-', 8, 'M Wira', 'selesai', '2026-03-13 14:26:23'),
(2, 'MUT-202603-0002', 6, '2026-03-13', 'keduanya', 1, 'IT', 11, 'budi', 7, 'Legal', 10, 'Qiana Almashyra Wiandra', 'Baik', 'Baik', 'Terpakai', '', 8, 'M Wira', 'selesai', '2026-03-13 14:28:31'),
(3, 'MUT-202603-0003', 2, '2026-03-14', 'keduanya', 3, 'HRD', 4, '', 3, 'HRD', 11, 'budi', 'Baik', 'Baik', 'Terpakai', 'Tes', 8, 'M Wira', 'selesai', '2026-03-14 12:15:57');

-- --------------------------------------------------------

--
-- Table structure for table `pengajuan_cuti`
--

CREATE TABLE `pengajuan_cuti` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `jenis_cuti_id` int(10) UNSIGNED NOT NULL,
  `jumlah_hari` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `tgl_mulai` date NOT NULL,
  `tgl_selesai` date NOT NULL,
  `keperluan` text DEFAULT NULL,
  `delegasi_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'user_id penerima delegasi tugas',
  `catatan_delegasi` varchar(255) DEFAULT NULL,
  `lampiran` varchar(255) DEFAULT NULL,
  `approver1_id` int(10) UNSIGNED DEFAULT NULL,
  `status_approver1` enum('menunggu','disetujui','ditolak') NOT NULL DEFAULT 'menunggu',
  `catatan_approver1` varchar(255) DEFAULT NULL,
  `tgl_approver1` datetime DEFAULT NULL,
  `approver2_id` int(10) UNSIGNED DEFAULT NULL,
  `status_approver2` enum('menunggu','disetujui','ditolak') NOT NULL DEFAULT 'menunggu',
  `catatan_approver2` varchar(255) DEFAULT NULL,
  `tgl_approver2` datetime DEFAULT NULL,
  `status` enum('draft','menunggu','disetujui','ditolak','dibatalkan') NOT NULL DEFAULT 'menunggu',
  `dibatalkan_oleh` int(10) UNSIGNED DEFAULT NULL,
  `tgl_dibatalkan` datetime DEFAULT NULL,
  `alasan_batal` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `pengajuan_cuti`
--

INSERT INTO `pengajuan_cuti` (`id`, `user_id`, `jenis_cuti_id`, `jumlah_hari`, `tgl_mulai`, `tgl_selesai`, `keperluan`, `delegasi_id`, `catatan_delegasi`, `lampiran`, `approver1_id`, `status_approver1`, `catatan_approver1`, `tgl_approver1`, `approver2_id`, `status_approver2`, `catatan_approver2`, `tgl_approver2`, `status`, `dibatalkan_oleh`, `tgl_dibatalkan`, `alasan_batal`, `created_at`, `updated_at`) VALUES
(4, 8, 7, 3, '2026-03-20', '2026-03-24', 'Cuti tahunan', 11, NULL, NULL, 12, 'disetujui', 'acc', '2026-03-19 20:35:13', 14, 'disetujui', NULL, '2026-03-19 20:35:35', 'disetujui', NULL, NULL, NULL, '2026-03-19 20:34:47', '2026-03-19 20:35:35');

-- --------------------------------------------------------

--
-- Table structure for table `rencana_kerja`
--

CREATE TABLE `rencana_kerja` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `divisi` varchar(100) DEFAULT NULL,
  `judul` varchar(255) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `kategori` enum('individu','divisi') DEFAULT 'individu',
  `prioritas` enum('rendah','sedang','tinggi') DEFAULT 'sedang',
  `tgl_mulai` date DEFAULT NULL,
  `tgl_deadline` date NOT NULL,
  `status` enum('belum_mulai','sedang_berjalan','tercapai','tidak_tercapai','ditunda') DEFAULT 'belum_mulai',
  `persentase` tinyint(3) UNSIGNED DEFAULT 0,
  `catatan` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `rencana_kerja`
--

INSERT INTO `rencana_kerja` (`id`, `user_id`, `divisi`, `judul`, `deskripsi`, `kategori`, `prioritas`, `tgl_mulai`, `tgl_deadline`, `status`, `persentase`, `catatan`, `created_at`, `updated_at`) VALUES
(1, 8, 'IT', 'Kirim Imaging Study Satu Sehat Kemenkes', '-', 'individu', 'tinggi', '2026-04-14', '2026-06-14', 'tercapai', 100, 'Selesai', '2026-04-14 15:16:58', '2026-04-14 15:21:04'),
(2, 8, 'IT', 'Kirim Allergi Intollerance Satu Sehat', NULL, 'individu', 'tinggi', '2026-04-14', '2026-05-14', 'belum_mulai', 0, NULL, '2026-04-14 15:18:38', '2026-04-14 15:18:38');

-- --------------------------------------------------------

--
-- Table structure for table `rencana_kerja_bukti`
--

CREATE TABLE `rencana_kerja_bukti` (
  `id` int(10) UNSIGNED NOT NULL,
  `rencana_id` int(10) UNSIGNED NOT NULL,
  `nama_file` varchar(255) NOT NULL,
  `nama_asli` varchar(255) NOT NULL,
  `ukuran` int(10) UNSIGNED DEFAULT 0,
  `keterangan` varchar(255) DEFAULT NULL,
  `uploaded_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `rencana_kerja_kegiatan`
--

CREATE TABLE `rencana_kerja_kegiatan` (
  `id` int(10) UNSIGNED NOT NULL,
  `rencana_id` int(10) UNSIGNED NOT NULL,
  `nama` varchar(255) NOT NULL,
  `tgl_target` date DEFAULT NULL,
  `selesai` tinyint(1) DEFAULT 0,
  `selesai_at` datetime DEFAULT NULL,
  `urutan` tinyint(3) UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `rencana_kerja_kegiatan`
--

INSERT INTO `rencana_kerja_kegiatan` (`id`, `rencana_id`, `nama`, `tgl_target`, `selesai`, `selesai_at`, `urutan`) VALUES
(2, 1, 'Buat server PACS Orthanc', NULL, 1, NULL, 1),
(3, 1, 'Instal Dicom Router Kemenkes', NULL, 1, NULL, 2);

-- --------------------------------------------------------

--
-- Table structure for table `sdm_karyawan`
--

CREATE TABLE `sdm_karyawan` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'FK ke tabel users',
  `nik_ktp` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'NIK KTP 16 digit',
  `nik_rs` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'NIK / Nomor Induk RS',
  `gelar_depan` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'dr., drg., Ns., dst.',
  `gelar_belakang` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'M.Kes, Sp.A, S.Kep, dst.',
  `tempat_lahir` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tgl_lahir` date DEFAULT NULL,
  `jenis_kelamin` enum('L','P') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `golongan_darah` enum('A','B','AB','O','A+','A-','B+','B-','AB+','AB-','O+','O-') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `agama` enum('Islam','Kristen Protestan','Kristen Katolik','Hindu','Buddha','Konghucu') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_pernikahan` enum('Belum Menikah','Menikah','Cerai Hidup','Cerai Mati') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jumlah_anak` tinyint(3) UNSIGNED DEFAULT 0,
  `kewarganegaraan` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'WNI',
  `suku` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_ktp` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'No. KTP (sama dengan NIK KTP)',
  `no_hp` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_hp_darurat` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kontak_darurat` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nama kontak darurat',
  `hubungan_darurat` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_pribadi` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alamat_ktp` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kelurahan_ktp` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kecamatan_ktp` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kota_ktp` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provinsi_ktp` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kode_pos_ktp` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alamat_domisili` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kosong jika sama dengan KTP',
  `kota_domisili` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pendidikan_terakhir` enum('SD','SMP','SMA/SMK','D1','D3','D4','S1','S2','S3','Profesi','Spesialis','Sub-Spesialis') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jurusan` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `universitas` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tahun_lulus` year(4) DEFAULT NULL,
  `jabatan_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK ke tabel jabatan',
  `divisi` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_kerja` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Sub-unit di bawah divisi',
  `jenis_karyawan` enum('Medis','Non-Medis','Penunjang Medis') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jenis_tenaga` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Dokter Umum, Perawat, Apoteker, Cleaning Service, dst.',
  `status_kepegawaian` enum('Tetap','Kontrak','Honorer','Magang','PPPK','Outsourcing') COLLATE utf8mb4_unicode_ci DEFAULT 'Tetap',
  `tgl_masuk` date DEFAULT NULL,
  `tgl_kontrak_mulai` date DEFAULT NULL,
  `tgl_kontrak_selesai` date DEFAULT NULL,
  `tgl_pengangkatan` date DEFAULT NULL COMMENT 'Tanggal jadi karyawan tetap',
  `masa_kerja_tahun` tinyint(3) UNSIGNED DEFAULT NULL COMMENT 'Dihitung otomatis dari tgl_masuk',
  `no_bpjs_kes` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_bpjs_tk` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_npwp` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_rekening` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `atas_nama_rek` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_str` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Surat Tanda Registrasi',
  `tgl_terbit_str` date DEFAULT NULL,
  `tgl_exp_str` date DEFAULT NULL,
  `no_sip` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Surat Izin Praktik',
  `tgl_terbit_sip` date DEFAULT NULL,
  `tgl_exp_sip` date DEFAULT NULL,
  `no_sik` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Surat Izin Kerja (Perawat, Bidan, dll)',
  `tgl_exp_sik` date DEFAULT NULL,
  `spesialisasi` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sub_spesialisasi` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kompetensi` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Sertifikasi / kompetensi tambahan',
  `status` enum('aktif','nonaktif','cuti','resign','pensiun') COLLATE utf8mb4_unicode_ci DEFAULT 'aktif',
  `tgl_resign` date DEFAULT NULL,
  `alasan_resign` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `catatan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `atasan_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Data SDM lengkap karyawan Rumah Sakit';

--
-- Dumping data for table `sdm_karyawan`
--

INSERT INTO `sdm_karyawan` (`id`, `user_id`, `nik_ktp`, `nik_rs`, `gelar_depan`, `gelar_belakang`, `tempat_lahir`, `tgl_lahir`, `jenis_kelamin`, `golongan_darah`, `agama`, `status_pernikahan`, `jumlah_anak`, `kewarganegaraan`, `suku`, `no_ktp`, `no_hp`, `no_hp_darurat`, `kontak_darurat`, `hubungan_darurat`, `email_pribadi`, `alamat_ktp`, `kelurahan_ktp`, `kecamatan_ktp`, `kota_ktp`, `provinsi_ktp`, `kode_pos_ktp`, `alamat_domisili`, `kota_domisili`, `pendidikan_terakhir`, `jurusan`, `universitas`, `tahun_lulus`, `jabatan_id`, `divisi`, `unit_kerja`, `jenis_karyawan`, `jenis_tenaga`, `status_kepegawaian`, `tgl_masuk`, `tgl_kontrak_mulai`, `tgl_kontrak_selesai`, `tgl_pengangkatan`, `masa_kerja_tahun`, `no_bpjs_kes`, `no_bpjs_tk`, `no_npwp`, `no_rekening`, `bank`, `atas_nama_rek`, `no_str`, `tgl_terbit_str`, `tgl_exp_str`, `no_sip`, `tgl_terbit_sip`, `tgl_exp_sip`, `no_sik`, `tgl_exp_sik`, `spesialisasi`, `sub_spesialisasi`, `kompetensi`, `status`, `tgl_resign`, `alasan_resign`, `catatan`, `foto`, `updated_by`, `created_at`, `updated_at`, `atasan_id`) VALUES
(1, 8, '1508181909000001', '16216046', NULL, 'S. Kom', 'JAMBI', '1989-09-17', 'L', 'B', 'Islam', 'Menikah', 2, 'WNI', 'Minang', NULL, '082177846209', '-', '-', '-', NULL, 'Gang Beo Nias, Blok A', NULL, NULL, 'Bungo', 'Jambi', '73211', NULL, NULL, 'S1', 'Sistem Informasi', 'UNH', 2015, 4, 'IT', NULL, 'Non-Medis', NULL, 'Tetap', '2016-03-07', '2016-03-07', '2017-03-07', '2017-03-07', NULL, '00818988999901001', '00818988999901001', '00818988999901003', '7122787', 'Syariah Indonesia', 'M Wira Satria Buana', '-', NULL, NULL, '-', NULL, NULL, '-', NULL, NULL, NULL, '-', 'aktif', NULL, NULL, '-', NULL, 8, '2026-03-13 13:31:12', '2026-03-19 20:38:55', 12),
(2, 11, '1898787878790001', '16216045', NULL, NULL, 'Jambi', '1990-03-01', 'L', 'O', 'Islam', 'Menikah', 1, 'WNI', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'D4', 'Manajemen Informasi', 'UNH', 2019, NULL, NULL, NULL, NULL, NULL, 'Tetap', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'aktif', NULL, NULL, NULL, NULL, 8, '2026-03-13 13:38:12', '2026-03-13 13:38:12', NULL),
(3, 10, '0082787871900001', '16216043', NULL, 'S.H', 'Jambi', '2002-10-10', 'P', 'B', 'Islam', 'Belum Menikah', NULL, 'WNI', NULL, NULL, '082177846209', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'S1', 'Hukum', 'UNJA', 2019, NULL, NULL, NULL, NULL, NULL, 'Tetap', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'aktif', NULL, NULL, NULL, NULL, 10, '2026-03-13 13:44:48', '2026-03-13 13:44:48', NULL),
(4, 12, '1231231231231231', '16216002', NULL, 'S. Kom', 'Jambi', '1990-03-17', 'L', 'B', 'Islam', 'Menikah', 1, 'WNI', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'S1', 'Teknik Informatika', 'UNH', 2016, NULL, NULL, NULL, NULL, NULL, 'Tetap', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'aktif', NULL, NULL, NULL, NULL, 8, '2026-03-17 21:51:02', '2026-03-17 21:51:02', NULL),
(5, 9, '6736248768876876', '16216098', NULL, 'S. Kom', 'Jambi', '1997-03-17', 'L', 'B', 'Islam', 'Belum Menikah', NULL, 'WNI', NULL, NULL, '087878', '090989798', 'tes', 'tes', NULL, 'jkhsdfhjkhk', 'sdjfhjk', 'skdjfh', 'sdkjfh', 'sdkfh', '87987', 'hskdjhf', 'sdkfjh', 'S1', 'Keperawatan', 'STikes', 2018, 5, NULL, 'sdjhgf', 'Non-Medis', 'hgsdhjg', 'Tetap', '2020-01-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'jshdg', 'sdjhgf', NULL, 'aktif', NULL, NULL, 'asd', NULL, 8, '2026-03-17 21:51:54', '2026-03-18 09:37:10', NULL),
(9, 14, '7778687656757657', '162160100', 'Ns', 'S. Kep', 'Jambi', '1990-09-17', 'P', 'AB', 'Islam', 'Menikah', 2, 'WNI', 'Minang', '76876876876876876876', '082177846209', NULL, NULL, NULL, 'nora@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Profesi', 'Perawat', 'STIKES', 2017, 6, NULL, NULL, NULL, NULL, 'Tetap', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'aktif', NULL, NULL, NULL, NULL, 14, '2026-03-17 22:17:00', '2026-03-19 20:25:30', NULL),
(10, 15, NULL, '16216020', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'WNI', NULL, NULL, '082177846209', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Gudang IT', NULL, NULL, NULL, 'Tetap', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'aktif', NULL, NULL, NULL, NULL, 15, '2026-03-18 09:26:38', '2026-03-18 09:26:38', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `server_room_log`
--

CREATE TABLE `server_room_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `tanggal` date NOT NULL,
  `waktu` time NOT NULL,
  `petugas` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nama petugas / teknisi',
  `suhu_in` decimal(5,2) DEFAULT NULL COMMENT 'Suhu dalam ruangan (°C)',
  `suhu_out` decimal(5,2) DEFAULT NULL COMMENT 'Suhu luar ruangan (°C)',
  `kelembaban` decimal(5,2) DEFAULT NULL COMMENT 'Kelembaban udara (%RH)',
  `tegangan_pln` decimal(7,2) DEFAULT NULL COMMENT 'Tegangan masuk PLN (Volt)',
  `tegangan_ups` decimal(7,2) DEFAULT NULL COMMENT 'Tegangan output UPS (Volt)',
  `beban_ups` decimal(5,2) DEFAULT NULL COMMENT 'Beban UPS (%)',
  `baterai_ups` decimal(5,2) DEFAULT NULL COMMENT 'Kapasitas baterai UPS (%)',
  `kondisi_ac1` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Normal' COMMENT 'Kondisi AC unit 1',
  `kondisi_ac2` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Normal' COMMENT 'Kondisi AC unit 2',
  `kondisi_listrik` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Normal' COMMENT 'Kondisi instalasi listrik',
  `kondisi_kebersihan` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Bersih' COMMENT 'Kebersihan ruangan',
  `kondisi_pintu` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Terkunci' COMMENT 'Kondisi pintu akses',
  `kondisi_cctv` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Normal' COMMENT 'Kondisi CCTV',
  `ada_alarm` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 jika alarm berbunyi',
  `ada_banjir` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 jika ada deteksi air/banjir',
  `ada_asap` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 jika ada deteksi asap/kebakaran',
  `catatan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Catatan / temuan petugas',
  `status_overall` enum('Normal','Perhatian','Kritis') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Normal',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log pemantauan ruangan server';

--
-- Dumping data for table `server_room_log`
--

INSERT INTO `server_room_log` (`id`, `tanggal`, `waktu`, `petugas`, `suhu_in`, `suhu_out`, `kelembaban`, `tegangan_pln`, `tegangan_ups`, `beban_ups`, `baterai_ups`, `kondisi_ac1`, `kondisi_ac2`, `kondisi_listrik`, `kondisi_kebersihan`, `kondisi_pintu`, `kondisi_cctv`, `ada_alarm`, `ada_banjir`, `ada_asap`, `catatan`, `status_overall`, `created_by`, `created_at`, `updated_at`) VALUES
(1, '2026-02-27', '20:20:00', 'M Wira', '22.50', '30.50', '55.00', '220.00', '220.00', '3.00', '76.00', 'Normal', 'Normal', 'Normal', 'Bersih', 'Rusak', 'Normal', 1, 1, 1, 'bagus', 'Normal', 8, '2026-02-27 20:20:54', '2026-02-27 21:10:06'),
(2, '2026-02-28', '08:11:00', 'M Wira', '22.50', '30.00', '67.00', '210.00', '210.00', NULL, NULL, 'Normal', 'Normal', 'Normal', 'Bersih', 'Terkunci', 'Normal', 1, 1, 1, 'selesai', 'Normal', 8, '2026-02-28 08:12:11', NULL),
(3, '2026-03-02', '08:07:00', 'M Wira', '28.00', '39.00', '60.00', '220.00', '220.00', '89.00', '87.00', 'Normal', 'Normal', 'Normal', 'Bersih', 'Terkunci', 'Normal', 1, 1, 1, '-', 'Normal', 8, '2026-03-02 08:08:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`key`, `value`, `updated_at`) VALUES
('backup_auto_enabled', '1', '2026-03-07 19:54:33'),
('backup_auto_retention', '12', '2026-03-07 19:42:50'),
('backup_auto_schedule', 'daily', '2026-03-07 19:42:50'),
('backup_auto_time', '19:58', '2026-03-07 19:56:24'),
('backup_compress', '1', '2026-03-07 19:54:23'),
('backup_notif_email', '0', '2026-03-07 19:42:50'),
('backup_notif_telegram', '1', '2026-03-07 19:54:23'),
('backup_storage_local', '1', '2026-03-07 19:42:50'),
('ipsrs_telegram_bot_token', '1', '2026-03-07 11:08:12'),
('ipsrs_telegram_chat_id', '1', '2026-03-07 11:08:17'),
('ipsrs_telegram_enabled', '1', '2026-03-07 10:39:55'),
('ipsrs_telegram_notif_diproses', '1', '2026-03-07 10:57:37'),
('ipsrs_telegram_notif_ditolak', '1', '2026-03-07 10:57:37'),
('ipsrs_telegram_notif_komentar', '1', '2026-03-07 10:57:37'),
('ipsrs_telegram_notif_selesai', '1', '2026-03-07 10:57:37'),
('ipsrs_telegram_notif_tiket_baru', '1', '2026-03-07 10:57:37'),
('telegram_bot_token', '1', '2026-03-07 20:00:21'),
('telegram_chat_id', '1', '2026-03-07 20:00:29'),
('telegram_enabled', '1', '2026-03-07 10:40:03'),
('telegram_notif_diproses', '1', '2026-02-25 20:31:32'),
('telegram_notif_ditolak', '1', '2026-02-25 20:31:32'),
('telegram_notif_komentar', '1', '2026-02-25 20:33:42'),
('telegram_notif_selesai', '1', '2026-02-25 20:31:32'),
('telegram_notif_tiket_baru', '1', '2026-02-25 20:31:32');

-- --------------------------------------------------------

--
-- Table structure for table `shift`
--

CREATE TABLE `shift` (
  `id` int(10) UNSIGNED NOT NULL,
  `nama` varchar(80) NOT NULL,
  `jam_masuk` time NOT NULL,
  `jam_keluar` time NOT NULL,
  `toleransi` smallint(6) NOT NULL DEFAULT 15,
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `tiket`
--

CREATE TABLE `tiket` (
  `id` int(10) UNSIGNED NOT NULL,
  `nomor` varchar(20) NOT NULL,
  `judul` varchar(255) NOT NULL,
  `deskripsi` text NOT NULL,
  `kategori_id` int(10) UNSIGNED DEFAULT NULL,
  `prioritas` enum('Tinggi','Sedang','Rendah') NOT NULL DEFAULT 'Sedang',
  `status` enum('menunggu','diproses','selesai','ditolak','tidak_bisa') NOT NULL DEFAULT 'menunggu',
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'Pemohon',
  `teknisi_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Teknisi yang handle',
  `lokasi` varchar(255) DEFAULT NULL,
  `aset_id` int(11) DEFAULT NULL,
  `aset_nama_cache` varchar(200) DEFAULT NULL,
  `catatan_penolakan` text DEFAULT NULL COMMENT 'Alasan jika ditolak/tidak bisa',
  `waktu_submit` datetime NOT NULL DEFAULT current_timestamp(),
  `waktu_diproses` datetime DEFAULT NULL COMMENT 'Kapan IT mulai handle',
  `waktu_selesai` datetime DEFAULT NULL COMMENT 'Kapan diselesaikan/ditolak/tidak bisa',
  `durasi_respon_menit` int(11) DEFAULT NULL COMMENT 'Menit dari submit ke diproses',
  `durasi_selesai_menit` int(11) DEFAULT NULL COMMENT 'Menit dari submit ke selesai',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `tiket`
--

INSERT INTO `tiket` (`id`, `nomor`, `judul`, `deskripsi`, `kategori_id`, `prioritas`, `status`, `user_id`, `teknisi_id`, `lokasi`, `aset_id`, `aset_nama_cache`, `catatan_penolakan`, `waktu_submit`, `waktu_diproses`, `waktu_selesai`, `durasi_respon_menit`, `durasi_selesai_menit`, `created_at`, `updated_at`) VALUES
(23, 'TKT-00012', 'komputer', 'komputer nya tidak nyala\n\n---\n📦 Aset terkait: INV-IT-2026-0006 | CPU Build UP | Build UP | Build UP', 1, 'Sedang', 'selesai', 10, 9, 'Lt.1, R.Operasional', 6, NULL, 'selesai ya', '2026-03-03 23:01:14', '2026-03-03 23:01:29', '2026-03-03 23:01:43', 0, 0, '2026-03-03 23:01:14', '2026-03-03 23:01:43'),
(24, 'TKT-00013', 'tidak bisa nyala', 'tidak bisa nyala\n\n---\n📦 Aset terkait: INV-IT-2026-0006 | CPU Build UP | Build UP | Build UP', 1, 'Sedang', 'selesai', 10, 8, 'Lt.1, R.Operasional', 6, NULL, 'sudah selesai ya', '2026-03-03 23:09:01', '2026-03-03 23:09:44', '2026-03-03 23:10:02', 0, 1, '2026-03-03 23:09:01', '2026-03-03 23:10:02'),
(25, 'TKT-00014', 'tidak keluar tinta', 'tidak keluar tinta nya\n\n---\n📦 Aset terkait: INV-IT-2025-0002 | Printer HP LaserJet | HP | LaserJet Pro M404dn', 5, 'Sedang', 'selesai', 10, 8, 'Lt.1, R.Operasional', 2, NULL, 'sudah selesai', '2026-03-03 23:09:21', '2026-03-03 23:14:19', '2026-03-03 23:14:49', 4, 5, '2026-03-03 23:09:21', '2026-03-03 23:14:49'),
(26, 'TKT-00004', 'Komputer kerja', 'Tolong di cek, terkendala tidak bisa nyala', 1, 'Sedang', 'tidak_bisa', 10, 12, 'Lt.2, R.Keuangan', NULL, NULL, 'Tidak bisa di perbaiki, ada part yang harus di ganti baru (kipas prosessor)', '2026-03-07 08:41:36', '2026-03-07 08:56:38', '2026-03-07 08:56:38', 15, 15, '2026-03-07 08:41:36', '2026-03-07 08:56:38'),
(27, 'TKT-00005', 'Tester', 'Tester notif\n\n---\n📦 Aset terkait: INV-IT-2025-0005 | Monitor LG 24 inch | LG | 24MK430H', 7, 'Sedang', 'tidak_bisa', 10, 8, 'Lt.1, Server Room', 5, NULL, 'tidak bisa di selesaikan oleh petugas internal', '2026-03-07 11:03:18', '2026-03-12 19:46:43', '2026-03-12 19:46:43', 7723, 7723, '2026-03-07 11:03:18', '2026-03-12 19:46:43'),
(28, 'TKT-00006', 'Tes edit', 'tesss\n\n---\n📦 Aset terkait: INV-IT-2026-0006 | CPU Build UP | Build UP | Build UP', 6, 'Sedang', 'menunggu', 10, NULL, 'Lt.1, R.Operasional', 6, NULL, NULL, '2026-03-18 08:25:20', NULL, NULL, NULL, NULL, '2026-03-18 08:25:20', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tiket_foto`
--

CREATE TABLE `tiket_foto` (
  `id` int(10) UNSIGNED NOT NULL,
  `tiket_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `nama_file` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tiket_foto`
--

INSERT INTO `tiket_foto` (`id`, `tiket_id`, `user_id`, `nama_file`, `path`, `created_at`) VALUES
(1, 25, 8, 'IT KERJA 1.jpeg', 'uploads/tiket_foto/tiket_25_1772554477_0.jpeg', '2026-03-03 23:14:37');

-- --------------------------------------------------------

--
-- Table structure for table `tiket_ipsrs`
--

CREATE TABLE `tiket_ipsrs` (
  `id` int(10) UNSIGNED NOT NULL,
  `nomor` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `judul` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `kategori_id` int(10) UNSIGNED DEFAULT NULL,
  `jenis_tiket` enum('Medis','Non-Medis') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Non-Medis',
  `prioritas` enum('Rendah','Sedang','Tinggi') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Sedang',
  `status` enum('menunggu','diproses','selesai','ditolak','tidak_bisa') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'menunggu',
  `user_id` int(10) UNSIGNED NOT NULL,
  `teknisi_id` int(10) UNSIGNED DEFAULT NULL,
  `lokasi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aset_id` int(10) UNSIGNED DEFAULT NULL,
  `waktu_submit` datetime NOT NULL DEFAULT current_timestamp(),
  `waktu_proses` datetime DEFAULT NULL,
  `waktu_selesai` datetime DEFAULT NULL,
  `durasi_respon_menit` int(10) UNSIGNED DEFAULT NULL,
  `durasi_selesai_menit` int(10) UNSIGNED DEFAULT NULL,
  `catatan_teknisi` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rating` tinyint(1) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tiket_ipsrs`
--

INSERT INTO `tiket_ipsrs` (`id`, `nomor`, `judul`, `deskripsi`, `kategori_id`, `jenis_tiket`, `prioritas`, `status`, `user_id`, `teknisi_id`, `lokasi`, `aset_id`, `waktu_submit`, `waktu_proses`, `waktu_selesai`, `durasi_respon_menit`, `durasi_selesai_menit`, `catatan_teknisi`, `rating`, `created_at`, `updated_at`) VALUES
(1, 'TKT-IPSRS-2026-0001', 'tes', 'tes', 9, 'Medis', 'Sedang', 'selesai', 10, 8, 'Lt.3, R.Marketing', NULL, '2026-03-04 09:12:26', '2026-03-04 13:48:14', '2026-03-04 13:48:14', 275, 275, NULL, NULL, '2026-03-04 09:12:26', '2026-03-04 13:48:14'),
(2, 'TKT-IPSRS-2026-0002', 'Bocor', 'bocor', 3, 'Medis', 'Sedang', 'selesai', 10, 9, 'Lt.1, R.Operasional', NULL, '2026-03-04 14:26:49', '2026-03-04 14:27:19', '2026-03-04 14:27:19', 0, 0, 'sudah selesai ya', NULL, '2026-03-04 14:26:49', '2026-03-04 14:27:19'),
(3, 'TKT-IPSRS-2026-0003', 'Tidak bisa nyala', 'Tidak bisa nyala automatis\n\n---\n🔧 Aset terkait: INV-IPSRS-2024-0006 | Generator Set (Genset) | Caterpillar | DE400E0', 13, 'Non-Medis', 'Sedang', 'selesai', 10, 9, 'Lt.1, R.Operasional', 7, '2026-03-04 20:58:17', '2026-03-04 20:59:25', '2026-03-04 20:59:25', 1, 1, 'sudah selesai', NULL, '2026-03-04 20:58:17', '2026-03-04 20:59:25'),
(4, 'TKT-IPSRS-2026-0004', 'listrik mati hidup', 'Listrik tidak stabil, mati hidup\n\n---\n🔧 Aset terkait: INV-IPSRS-2024-0006 | Generator Set (Genset) | Caterpillar | DE400E0', 12, 'Non-Medis', 'Sedang', 'selesai', 10, 9, 'Lt.1, R.Operasional', 7, '2026-03-04 21:11:17', '2026-03-04 21:11:41', '2026-03-04 21:11:41', 0, 0, 'sudah selesai', NULL, '2026-03-04 21:11:17', '2026-03-04 21:11:41'),
(5, 'TKT-IPSRS-2026-0005', 'ambulan bocor halus , tapi keliling', 'ambulan bocor\n\n---\n🔧 Aset terkait: INV-IPSRS-2024-0009 | Ambulans Transport | Toyota | HiAce Commuter 2.8', 22, 'Non-Medis', 'Sedang', 'selesai', 10, 9, 'Lt.1, R.Operasional', 10, '2026-03-04 21:20:11', '2026-03-04 21:21:24', '2026-03-04 21:21:24', 1, 1, 'sudah selesai', NULL, '2026-03-04 21:20:11', '2026-03-04 21:21:24'),
(6, 'TKT-IPSRS-2026-0006', 'sapu patah', 'sapu mbak cs patah , tolong di perbaiki', 23, 'Non-Medis', 'Sedang', 'selesai', 10, 9, 'Lt.1, R.Operasional', NULL, '2026-03-04 21:26:56', '2026-03-04 21:27:14', '2026-03-04 21:27:24', 0, 0, 'sudah', NULL, '2026-03-04 21:26:56', '2026-03-04 21:27:24'),
(7, 'TKT-IPSRS-2026-0007', 'Ambulan', 'ambulan bocor\n\n---\n🔧 Aset terkait: INV-IPSRS-2024-0009 | Ambulans Transport | Toyota | HiAce Commuter 2.8', 22, 'Non-Medis', 'Sedang', 'diproses', 10, 9, 'Lt.1, R.Operasional', 10, '2026-03-04 21:30:26', '2026-03-04 21:30:48', NULL, 0, NULL, NULL, NULL, '2026-03-04 21:30:26', '2026-03-04 21:30:48'),
(8, 'TKT-IPSRS-2026-0008', 'ambulan', 'ambulan bocor\n\n---\n🔧 Aset terkait: INV-IPSRS-2024-0009 | Ambulans Transport | Toyota | HiAce Commuter 2.8', 22, 'Non-Medis', 'Sedang', 'selesai', 10, 9, 'Lt.1, R.Operasional', 10, '2026-03-04 21:34:20', '2026-03-04 21:34:53', '2026-03-04 21:34:53', 0, 0, 'sudah ya', NULL, '2026-03-04 21:34:20', '2026-03-04 21:34:53'),
(9, 'TKT-IPSRS-2026-0009', 'Ambulan', 'ban nya bocor', 22, 'Non-Medis', 'Sedang', 'selesai', 10, 9, 'Lt.1, R.Operasional', NULL, '2026-03-04 21:57:36', '2026-03-04 21:58:23', '2026-03-04 21:58:23', 0, 0, 'sudah ya', NULL, '2026-03-04 21:57:36', '2026-03-04 21:58:23'),
(10, 'TKT-IPSRS-2026-0010', 'Listrik nya redup', 'Listrik nya tidak stabil\n\n---\n🔧 Aset terkait: INV-IPSRS-2024-0006 | Generator Set (Genset) | Caterpillar | DE400E0', 12, 'Non-Medis', 'Sedang', 'selesai', 10, 9, 'Lt.4, R.Direksi', 7, '2026-03-07 10:58:48', '2026-03-07 11:00:26', '2026-03-07 11:00:50', 1, 2, 'sudah ya, harap di periksa lagi', NULL, '2026-03-07 10:58:48', '2026-03-07 11:00:50');

-- --------------------------------------------------------

--
-- Table structure for table `tiket_ipsrs_foto`
--

CREATE TABLE `tiket_ipsrs_foto` (
  `id` int(10) UNSIGNED NOT NULL,
  `tiket_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `nama_file` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tiket_ipsrs_log`
--

CREATE TABLE `tiket_ipsrs_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `tiket_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `status_dari` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_ke` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tiket_ipsrs_log`
--

INSERT INTO `tiket_ipsrs_log` (`id`, `tiket_id`, `user_id`, `status_dari`, `status_ke`, `keterangan`, `created_at`) VALUES
(1, 1, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-03-04 09:12:26'),
(2, 1, 8, 'menunggu', 'selesai', 'Tiket IPSRS selesai ditangani.', '2026-03-04 13:48:14'),
(3, 2, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-03-04 14:26:49'),
(4, 2, 9, 'menunggu', 'selesai', 'Tiket IPSRS selesai ditangani.', '2026-03-04 14:27:19'),
(5, 3, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-03-04 20:58:17'),
(6, 3, 9, 'menunggu', 'selesai', 'Tiket IPSRS selesai ditangani.', '2026-03-04 20:59:25'),
(7, 4, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-03-04 21:11:17'),
(8, 4, 9, 'menunggu', 'selesai', 'Tiket IPSRS selesai ditangani.', '2026-03-04 21:11:41'),
(9, 5, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-03-04 21:20:11'),
(10, 5, 9, 'menunggu', 'selesai', 'Tiket IPSRS selesai ditangani.', '2026-03-04 21:21:24'),
(11, 6, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-03-04 21:26:56'),
(12, 6, 9, 'menunggu', 'diproses', 'Tiket diambil dan mulai diproses oleh Giano', '2026-03-04 21:27:14'),
(13, 6, 9, 'diproses', 'selesai', 'Tiket IPSRS selesai ditangani.', '2026-03-04 21:27:24'),
(14, 7, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-03-04 21:30:26'),
(15, 7, 9, 'menunggu', 'diproses', 'Tiket diambil dan mulai diproses oleh Giano', '2026-03-04 21:30:48'),
(16, 8, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-03-04 21:34:20'),
(17, 8, 9, 'menunggu', 'selesai', 'Tiket IPSRS selesai ditangani.', '2026-03-04 21:34:53'),
(18, 9, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-03-04 21:57:37'),
(19, 9, 9, 'menunggu', 'selesai', 'Tiket IPSRS selesai ditangani.', '2026-03-04 21:58:23'),
(20, 10, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-03-07 10:58:48'),
(21, 10, 10, 'menunggu', 'menunggu', 'Komentar: Mohon segera ya mas', '2026-03-07 10:59:43'),
(22, 10, 9, 'menunggu', 'diproses', 'Tiket diambil dan mulai diproses oleh Giano', '2026-03-07 11:00:26'),
(23, 10, 9, 'diproses', 'selesai', 'Tiket IPSRS selesai ditangani.', '2026-03-07 11:00:50');

-- --------------------------------------------------------

--
-- Table structure for table `tiket_log`
--

CREATE TABLE `tiket_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `tiket_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `status_dari` varchar(20) DEFAULT NULL,
  `status_ke` varchar(20) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `tiket_log`
--

INSERT INTO `tiket_log` (`id`, `tiket_id`, `user_id`, `status_dari`, `status_ke`, `keterangan`, `created_at`) VALUES
(57, 23, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-03-03 23:01:14'),
(58, 23, 9, 'menunggu', 'diproses', 'Tiket diambil dan mulai diproses oleh Giano', '2026-03-03 23:01:29'),
(59, 23, 9, 'diproses', 'selesai', 'Tiket selesai ditangani.', '2026-03-03 23:01:43'),
(60, 24, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-03-03 23:09:01'),
(61, 25, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-03-03 23:09:21'),
(62, 24, 8, 'menunggu', 'diproses', 'Tiket diambil dan mulai diproses oleh M Wira', '2026-03-03 23:09:44'),
(63, 24, 8, 'diproses', 'selesai', 'Tiket selesai ditangani.', '2026-03-03 23:10:02'),
(64, 25, 8, 'menunggu', 'diproses', 'Tiket diambil dan mulai diproses oleh M Wira', '2026-03-03 23:14:19'),
(65, 25, 8, 'diproses', 'diproses', 'Upload 1 foto bukti pengerjaan.', '2026-03-03 23:14:37'),
(66, 25, 8, 'diproses', 'selesai', 'Tiket selesai ditangani.', '2026-03-03 23:14:49'),
(67, 26, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-03-07 08:41:36'),
(68, 26, 12, 'menunggu', 'tidak_bisa', 'Tidak dapat ditangani. Keterangan: Tidak bisa di perbaiki, ada part yang harus di ganti baru (kipas prosessor)', '2026-03-07 08:56:38'),
(69, 26, 12, 'tidak_bisa', 'tidak_bisa', 'Berita Acara dibuat: BA-IT-2026-0001', '2026-03-07 08:58:08'),
(70, 27, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-03-07 11:03:18'),
(71, 27, 8, 'menunggu', 'tidak_bisa', 'Tidak dapat ditangani. Keterangan: tidak bisa di selesaikan oleh petugas internal', '2026-03-12 19:46:43'),
(72, 27, 8, 'tidak_bisa', 'tidak_bisa', 'Berita Acara dibuat: BA-IT-2026-0002', '2026-03-12 19:47:27'),
(73, 28, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana Almashyra Wiandra', '2026-03-18 08:25:20');

-- --------------------------------------------------------

--
-- Table structure for table `transaksi_gaji`
--

CREATE TABLE `transaksi_gaji` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `periode` char(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gaji_pokok` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_penerimaan` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total_potongan` decimal(15,2) NOT NULL DEFAULT 0.00,
  `pph21` decimal(15,2) NOT NULL DEFAULT 0.00,
  `ptkp_id` int(10) UNSIGNED DEFAULT NULL,
  `ptkp_kode` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_rekening` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `atas_nama_rek` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gaji_bersih` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','dibayar') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `tgl_bayar` date DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transaksi_gaji`
--

INSERT INTO `transaksi_gaji` (`id`, `user_id`, `periode`, `gaji_pokok`, `total_penerimaan`, `total_potongan`, `pph21`, `ptkp_id`, `ptkp_kode`, `bank`, `no_rekening`, `atas_nama_rek`, `gaji_bersih`, `status`, `tgl_bayar`, `created_by`, `created_at`, `approved_by`, `approved_at`) VALUES
(7, 8, '2026-04', '3500000.00', '5700000.00', '130000.00', '300000.00', 1, 'TER_A', 'Syariah Indonesia', '7122787', 'M Wira Satria Buana', '8770000.00', 'draft', NULL, 8, '2026-04-16 11:52:20', NULL, NULL),
(8, 15, '2026-04', '11000000.00', '750000.00', '65000.00', '600000.00', 1, 'TER_A', NULL, NULL, NULL, '11085000.00', 'draft', NULL, 8, '2026-04-16 11:52:20', NULL, NULL),
(9, 10, '2026-04', '8500000.00', '1000000.00', '20000.00', '200000.00', 1, 'TER_A', NULL, NULL, NULL, '9280000.00', 'draft', NULL, 8, '2026-04-16 11:52:20', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `transaksi_gaji_detail`
--

CREATE TABLE `transaksi_gaji_detail` (
  `id` int(10) UNSIGNED NOT NULL,
  `transaksi_id` int(10) UNSIGNED NOT NULL,
  `tipe` enum('penerimaan','potongan') COLLATE utf8mb4_unicode_ci NOT NULL,
  `penerimaan_id` int(10) UNSIGNED DEFAULT NULL,
  `potongan_id` int(10) UNSIGNED DEFAULT NULL,
  `nama_komponen` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nilai` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transaksi_gaji_detail`
--

INSERT INTO `transaksi_gaji_detail` (`id`, `transaksi_id`, `tipe`, `penerimaan_id`, `potongan_id`, `nama_komponen`, `nilai`) VALUES
(24, 7, 'penerimaan', 7, NULL, 'Masa Kerja', '750000.00'),
(25, 7, 'penerimaan', 10, NULL, 'Fungsional', '1500000.00'),
(26, 7, 'penerimaan', 13, NULL, 'Gaji Pokok', '3450000.00'),
(27, 7, 'potongan', NULL, 6, 'BPJS Kes', '45000.00'),
(28, 7, 'potongan', NULL, 7, 'BPJS TK JHT', '65000.00'),
(29, 7, 'potongan', NULL, 9, 'Dana Sosial', '20000.00'),
(30, 8, 'penerimaan', 7, NULL, 'Masa Kerja', '750000.00'),
(31, 8, 'potongan', NULL, 9, 'Dana Sosial', '20000.00'),
(32, 8, 'potongan', NULL, 6, 'BPJS Kes', '45000.00'),
(33, 9, 'penerimaan', 8, NULL, 'Masa Kerja', '1000000.00'),
(34, 9, 'potongan', NULL, 9, 'Dana Sosial', '20000.00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','teknisi','teknisi_ipsrs','admin','hrd','keuangan','akreditasi') NOT NULL DEFAULT 'user',
  `divisi` varchar(100) DEFAULT NULL COMMENT 'Nama bagian/divisi',
  `pokja_id` int(10) UNSIGNED DEFAULT NULL,
  `is_akreditasi` tinyint(1) UNSIGNED NOT NULL DEFAULT 0,
  `jabatan_id` int(10) UNSIGNED DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama`, `username`, `email`, `password`, `role`, `divisi`, `pokja_id`, `is_akreditasi`, `jabatan_id`, `no_hp`, `status`, `created_at`) VALUES
(8, 'M Wira Satria Buana', 'wira', 'wiramuhammad16@gmail.com', '$2y$10$43m6VDpatRFEhv8tGPmLs.2Y42lKho6Qhi762RF9SUkAALPa0dKfa', 'admin', 'IT', NULL, 0, NULL, '082177846209', 'aktif', '2026-02-26 08:30:41'),
(9, 'Giano', 'Giano', 'giano@gmail.com', '$2y$10$0VjOHL4hCSnsJLjXaCylDurasZUsmhwXmSxPOU.G9Wx62pgTLIYsG', 'teknisi', 'IT', 2, 1, NULL, '087878', 'aktif', '2026-02-26 08:52:16'),
(10, 'Qiana Almashyra Wiandra', 'Qiana', 'Qiana@gmail.com', '$2y$10$nahJRhdJ6YUGvfPukFLZrOeUFSDGfIYTdEfVovqtTrv9fwGO6aKpC', 'keuangan', 'Operasional', NULL, 0, NULL, '082177846209', 'aktif', '2026-02-26 09:26:59'),
(11, 'budi', 'budi', 'budi@gmail.com', '$2y$10$/1hBK9yM7epNopZptjHmP.BAk8YtLMVvDiyX1HFuCQ8oxvCcIz9z2', 'teknisi_ipsrs', 'IT', NULL, 0, NULL, NULL, 'aktif', '2026-02-26 09:28:47'),
(12, 'Danu', 'Danu', 'danu@gmail.com', '$2y$10$C4Xi0N8ubMaRT6gh.8yJxO4HwELs5xHYMzFvVJ4ErkMyahPAxxXaK', 'teknisi_ipsrs', 'Lainnya', NULL, 0, NULL, NULL, 'aktif', '2026-03-06 13:18:04'),
(14, 'Nora', 'nora', 'nora@gmail.com', '$2y$10$j/6G9RbTupoUYEAqG6fnL.yN4dR1uTRtdThW4CrmmbPPKbD4qI5MS', 'hrd', 'HRD', 2, 1, 4, '082177846209', 'aktif', '2026-03-10 20:28:43'),
(15, 'Rudi', 'rudi', 'rudi@gmail.com', '$2y$10$BlEPhCi8kiq00qjms6U1xutNYAGAE7.3ssvaCmXznXUJ9EZkP07ym', 'user', 'Gudang IT', NULL, 0, NULL, '082177846209', 'aktif', '2026-03-18 09:26:38');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_absensi` (`user_id`,`tanggal`);

--
-- Indexes for table `aset_ipsrs`
--
ALTER TABLE `aset_ipsrs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_no_inventaris` (`no_inventaris`),
  ADD KEY `idx_jenis_aset` (`jenis_aset`),
  ADD KEY `idx_kondisi` (`kondisi`),
  ADD KEY `idx_status_pakai` (`status_pakai`),
  ADD KEY `idx_bagian_id` (`bagian_id`),
  ADD KEY `idx_pj_user_id` (`pj_user_id`),
  ADD KEY `idx_kal_berikutnya` (`tgl_kalibrasi_berikutnya`),
  ADD KEY `idx_svc_berikutnya` (`tgl_service_berikutnya`);

--
-- Indexes for table `aset_it`
--
ALTER TABLE `aset_it`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_inventaris` (`no_inventaris`),
  ADD KEY `idx_kondisi` (`kondisi`),
  ADD KEY `idx_kategori` (`kategori`),
  ADD KEY `idx_bagian_id` (`bagian_id`),
  ADD KEY `idx_pj_user_id` (`pj_user_id`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `backup_logs`
--
ALTER TABLE `backup_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_filename` (`filename`(100));

--
-- Indexes for table `bagian`
--
ALTER TABLE `bagian`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_nama` (`nama`);

--
-- Indexes for table `berita_acara`
--
ALTER TABLE `berita_acara`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tiket` (`tiket_id`);

--
-- Indexes for table `berkas_karyawan`
--
ALTER TABLE `berkas_karyawan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_kategori` (`kategori`),
  ADD KEY `idx_jenis` (`jenis_berkas_id`);

--
-- Indexes for table `cuti_tanggal`
--
ALTER TABLE `cuti_tanggal`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pengajuan_tgl` (`pengajuan_id`,`tanggal`),
  ADD KEY `idx_pengajuan` (`pengajuan_id`),
  ADD KEY `idx_tanggal` (`tanggal`);

--
-- Indexes for table `data_gaji`
--
ALTER TABLE `data_gaji`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `data_gaji_detail`
--
ALTER TABLE `data_gaji_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_data_gaji_id` (`data_gaji_id`),
  ADD KEY `idx_penerimaan_id` (`penerimaan_id`),
  ADD KEY `idx_potongan_id` (`potongan_id`);

--
-- Indexes for table `dokumen_akreditasi`
--
ALTER TABLE `dokumen_akreditasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pokja` (`pokja_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `jabatan`
--
ALTER TABLE `jabatan`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `jadwal_karyawan`
--
ALTER TABLE `jadwal_karyawan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_tanggal` (`user_id`,`tanggal`),
  ADD KEY `idx_tanggal` (`tanggal`),
  ADD KEY `idx_shift` (`shift_id`);

--
-- Indexes for table `jatah_cuti`
--
ALTER TABLE `jatah_cuti`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_jenis_tahun` (`user_id`,`jenis_cuti_id`,`tahun`);

--
-- Indexes for table `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kategori_ipsrs`
--
ALTER TABLE `kategori_ipsrs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_jenis` (`jenis`);

--
-- Indexes for table `komentar`
--
ALTER TABLE `komentar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tiket_id` (`tiket_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `komentar_ipsrs`
--
ALTER TABLE `komentar_ipsrs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tiket` (`tiket_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `koneksi_log`
--
ALTER TABLE `koneksi_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_monitor` (`monitor_id`),
  ADD KEY `idx_cek_at` (`cek_at`);

--
-- Indexes for table `koneksi_monitor`
--
ALTER TABLE `koneksi_monitor`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_aktif` (`aktif`),
  ADD KEY `idx_tipe` (`tipe`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_ip` (`ip_address`);

--
-- Indexes for table `login_log`
--
ALTER TABLE `login_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_ip` (`ip_address`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `lokasi_absen`
--
ALTER TABLE `lokasi_absen`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `maintenance_ipsrs`
--
ALTER TABLE `maintenance_ipsrs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_no_maintenance` (`no_maintenance`),
  ADD KEY `idx_aset_id` (`aset_id`),
  ADD KEY `idx_teknisi_id` (`teknisi_id`),
  ADD KEY `idx_tgl_maintenance` (`tgl_maintenance`),
  ADD KEY `idx_tgl_maintenance_berikut` (`tgl_maintenance_berikut`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_jenis_maintenance` (`jenis_maintenance`),
  ADD KEY `fk_mnt_ipsrs_creator` (`created_by`);

--
-- Indexes for table `maintenance_it`
--
ALTER TABLE `maintenance_it`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_maintenance` (`no_maintenance`),
  ADD KEY `idx_aset_id` (`aset_id`),
  ADD KEY `idx_teknisi_id` (`teknisi_id`),
  ADD KEY `idx_tgl` (`tgl_maintenance`),
  ADD KEY `idx_tgl_berikut` (`tgl_maintenance_berikut`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `master_jenis_berkas`
--
ALTER TABLE `master_jenis_berkas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_kategori` (`kategori_id`);

--
-- Indexes for table `master_jenis_cuti`
--
ALTER TABLE `master_jenis_cuti`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_kode` (`kode`);

--
-- Indexes for table `master_kategori_berkas`
--
ALTER TABLE `master_kategori_berkas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `master_penerimaan`
--
ALTER TABLE `master_penerimaan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indexes for table `master_pokja`
--
ALTER TABLE `master_pokja`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `master_potongan`
--
ALTER TABLE `master_potongan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indexes for table `master_pph21_ptkp`
--
ALTER TABLE `master_pph21_ptkp`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indexes for table `master_pph21_ter`
--
ALTER TABLE `master_pph21_ter`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ptkp` (`ptkp_id`);

--
-- Indexes for table `master_shift`
--
ALTER TABLE `master_shift`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indexes for table `mutasi_aset`
--
ALTER TABLE `mutasi_aset`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_mutasi` (`no_mutasi`);

--
-- Indexes for table `pengajuan_cuti`
--
ALTER TABLE `pengajuan_cuti`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_tgl` (`tgl_mulai`,`tgl_selesai`);

--
-- Indexes for table `rencana_kerja`
--
ALTER TABLE `rencana_kerja`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_divisi` (`divisi`);

--
-- Indexes for table `rencana_kerja_bukti`
--
ALTER TABLE `rencana_kerja_bukti`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rencana` (`rencana_id`);

--
-- Indexes for table `rencana_kerja_kegiatan`
--
ALTER TABLE `rencana_kerja_kegiatan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rencana` (`rencana_id`);

--
-- Indexes for table `sdm_karyawan`
--
ALTER TABLE `sdm_karyawan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `uq_nik_ktp` (`nik_ktp`),
  ADD UNIQUE KEY `uq_nik_rs` (`nik_rs`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_divisi` (`divisi`),
  ADD KEY `idx_jabatan` (`jabatan_id`),
  ADD KEY `idx_tgl_exp_str` (`tgl_exp_str`),
  ADD KEY `idx_tgl_exp_sip` (`tgl_exp_sip`);

--
-- Indexes for table `server_room_log`
--
ALTER TABLE `server_room_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tanggal` (`tanggal`),
  ADD KEY `idx_status` (`status_overall`),
  ADD KEY `idx_tgl_waktu` (`tanggal`,`waktu`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `shift`
--
ALTER TABLE `shift`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tiket`
--
ALTER TABLE `tiket`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor` (`nomor`),
  ADD KEY `kategori_id` (`kategori_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `teknisi_id` (`teknisi_id`);

--
-- Indexes for table `tiket_foto`
--
ALTER TABLE `tiket_foto`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tiket_id` (`tiket_id`);

--
-- Indexes for table `tiket_ipsrs`
--
ALTER TABLE `tiket_ipsrs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor` (`nomor`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_teknisi` (`teknisi_id`),
  ADD KEY `idx_kategori` (`kategori_id`),
  ADD KEY `idx_jenis` (`jenis_tiket`),
  ADD KEY `idx_aset` (`aset_id`),
  ADD KEY `idx_waktu` (`waktu_submit`);

--
-- Indexes for table `tiket_ipsrs_foto`
--
ALTER TABLE `tiket_ipsrs_foto`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tiket` (`tiket_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `tiket_ipsrs_log`
--
ALTER TABLE `tiket_ipsrs_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tiket` (`tiket_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `tiket_log`
--
ALTER TABLE `tiket_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tiket_id` (`tiket_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `transaksi_gaji`
--
ALTER TABLE `transaksi_gaji`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_periode` (`user_id`,`periode`),
  ADD KEY `idx_periode` (`periode`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `transaksi_gaji_detail`
--
ALTER TABLE `transaksi_gaji_detail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transaksi_id` (`transaksi_id`),
  ADD KEY `idx_penerimaan_id` (`penerimaan_id`),
  ADD KEY `idx_potongan_id` (`potongan_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_users_jabatan` (`jabatan_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `absensi`
--
ALTER TABLE `absensi`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `aset_ipsrs`
--
ALTER TABLE `aset_ipsrs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `aset_it`
--
ALTER TABLE `aset_it`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `backup_logs`
--
ALTER TABLE `backup_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `bagian`
--
ALTER TABLE `bagian`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `berita_acara`
--
ALTER TABLE `berita_acara`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `berkas_karyawan`
--
ALTER TABLE `berkas_karyawan`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `cuti_tanggal`
--
ALTER TABLE `cuti_tanggal`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `data_gaji`
--
ALTER TABLE `data_gaji`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `data_gaji_detail`
--
ALTER TABLE `data_gaji_detail`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `dokumen_akreditasi`
--
ALTER TABLE `dokumen_akreditasi`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `jabatan`
--
ALTER TABLE `jabatan`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `jadwal_karyawan`
--
ALTER TABLE `jadwal_karyawan`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `jatah_cuti`
--
ALTER TABLE `jatah_cuti`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `kategori_ipsrs`
--
ALTER TABLE `kategori_ipsrs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `komentar`
--
ALTER TABLE `komentar`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `komentar_ipsrs`
--
ALTER TABLE `komentar_ipsrs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `koneksi_log`
--
ALTER TABLE `koneksi_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=238;

--
-- AUTO_INCREMENT for table `koneksi_monitor`
--
ALTER TABLE `koneksi_monitor`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_log`
--
ALTER TABLE `login_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=183;

--
-- AUTO_INCREMENT for table `lokasi_absen`
--
ALTER TABLE `lokasi_absen`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `maintenance_ipsrs`
--
ALTER TABLE `maintenance_ipsrs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `maintenance_it`
--
ALTER TABLE `maintenance_it`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `master_jenis_berkas`
--
ALTER TABLE `master_jenis_berkas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `master_jenis_cuti`
--
ALTER TABLE `master_jenis_cuti`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `master_kategori_berkas`
--
ALTER TABLE `master_kategori_berkas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `master_penerimaan`
--
ALTER TABLE `master_penerimaan`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `master_pokja`
--
ALTER TABLE `master_pokja`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `master_potongan`
--
ALTER TABLE `master_potongan`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `master_pph21_ptkp`
--
ALTER TABLE `master_pph21_ptkp`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `master_pph21_ter`
--
ALTER TABLE `master_pph21_ter`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT for table `master_shift`
--
ALTER TABLE `master_shift`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `mutasi_aset`
--
ALTER TABLE `mutasi_aset`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `pengajuan_cuti`
--
ALTER TABLE `pengajuan_cuti`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `rencana_kerja`
--
ALTER TABLE `rencana_kerja`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `rencana_kerja_bukti`
--
ALTER TABLE `rencana_kerja_bukti`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rencana_kerja_kegiatan`
--
ALTER TABLE `rencana_kerja_kegiatan`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sdm_karyawan`
--
ALTER TABLE `sdm_karyawan`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `server_room_log`
--
ALTER TABLE `server_room_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `shift`
--
ALTER TABLE `shift`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tiket`
--
ALTER TABLE `tiket`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `tiket_foto`
--
ALTER TABLE `tiket_foto`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tiket_ipsrs`
--
ALTER TABLE `tiket_ipsrs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tiket_ipsrs_foto`
--
ALTER TABLE `tiket_ipsrs_foto`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tiket_ipsrs_log`
--
ALTER TABLE `tiket_ipsrs_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `tiket_log`
--
ALTER TABLE `tiket_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT for table `transaksi_gaji`
--
ALTER TABLE `transaksi_gaji`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `transaksi_gaji_detail`
--
ALTER TABLE `transaksi_gaji_detail`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `absensi`
--
ALTER TABLE `absensi`
  ADD CONSTRAINT `absensi_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `komentar`
--
ALTER TABLE `komentar`
  ADD CONSTRAINT `komentar_ibfk_1` FOREIGN KEY (`tiket_id`) REFERENCES `tiket` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `komentar_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `koneksi_log`
--
ALTER TABLE `koneksi_log`
  ADD CONSTRAINT `fk_log_monitor` FOREIGN KEY (`monitor_id`) REFERENCES `koneksi_monitor` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `maintenance_ipsrs`
--
ALTER TABLE `maintenance_ipsrs`
  ADD CONSTRAINT `fk_mnt_ipsrs_aset` FOREIGN KEY (`aset_id`) REFERENCES `aset_ipsrs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mnt_ipsrs_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mnt_ipsrs_teknisi` FOREIGN KEY (`teknisi_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `sdm_karyawan`
--
ALTER TABLE `sdm_karyawan`
  ADD CONSTRAINT `fk_sdm_jabatan` FOREIGN KEY (`jabatan_id`) REFERENCES `jabatan` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sdm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tiket`
--
ALTER TABLE `tiket`
  ADD CONSTRAINT `tiket_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `kategori` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tiket_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tiket_ibfk_3` FOREIGN KEY (`teknisi_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tiket_log`
--
ALTER TABLE `tiket_log`
  ADD CONSTRAINT `tiket_log_ibfk_1` FOREIGN KEY (`tiket_id`) REFERENCES `tiket` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tiket_log_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_jabatan` FOREIGN KEY (`jabatan_id`) REFERENCES `jabatan` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
