-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Feb 27, 2026 at 02:27 PM
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

INSERT INTO `aset_it` (`id`, `no_inventaris`, `nama_aset`, `kategori`, `merek`, `model_aset`, `serial_number`, `kondisi`, `bagian_id`, `lokasi`, `pj_user_id`, `penanggung_jawab`, `tanggal_beli`, `harga_beli`, `garansi_sampai`, `keterangan`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'INV-IT-2025-0001', 'Laptop Dell Latitude', 'Laptop', 'Dell', 'Latitude 5520', 'DLLAT5520-0001', 'Baik', 1, 'IT / Helpdesk', 2, '(sesuai user)', '2023-01-15', 14500000, '2026-01-15', 'Laptop staf IT helpdesk', NULL, '2026-02-26 21:14:11', '2026-02-26 22:25:28'),
(2, 'INV-IT-2025-0002', 'Printer HP LaserJet', 'Printer', 'HP', 'LaserJet Pro M404dn', 'HPLJ404-0002', 'Baik', 3, 'Administrasi', 4, '(sesuai user)', '2022-06-20', 4800000, '2025-06-20', NULL, NULL, '2026-02-26 21:14:11', NULL),
(3, 'INV-IT-2025-0003', 'Switch Cisco Catalyst', 'Switch', 'Cisco', 'Catalyst 2960-X', 'CSC2960X-0003', 'Baik', 1, 'Server Room', 2, '(sesuai user)', '2021-03-10', 22000000, '2024-03-10', 'Core switch lantai 1', NULL, '2026-02-26 21:14:11', NULL),
(4, 'INV-IT-2025-0004', 'UPS APC Smart-UPS', 'UPS', 'APC', 'Smart-UPS 1500', 'APC1500-0004', 'Baik', 1, 'IT', 11, 'budi', '2020-09-05', 9500000, NULL, 'Battery perlu diganti', NULL, '2026-02-26 21:14:11', '2026-02-26 21:17:11'),
(5, 'INV-IT-2025-0005', 'Monitor LG 24 inch', 'Monitor', 'LG', '24MK430H', 'LG24MK-0005', 'Baik', 5, 'Keuangan', 6, '(sesuai user)', '2023-07-01', 1950000, '2026-07-01', NULL, NULL, '2026-02-26 21:14:11', '2026-02-26 21:25:55'),
(6, 'INV-IT-2026-0006', 'CPU Build UP', 'Komputer', 'Build UP', 'Build UP', '123.123.123.123', 'Baik', 4, 'Marketing', 9, 'Giano', '2026-02-26', 7800000, '2029-02-26', 'Ram 8GB , Core i7', 8, '2026-02-26 21:15:28', '2026-02-26 21:27:01');

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
(9, 'Lainnya', 'ETC', 'Departemen / Bagian lainnya', NULL, 'aktif', 99, '2026-02-25 20:31:32');

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
(8, 'Lainnya', 'Masalah IT lainnya', 'fa-question-circle', 48, 8, '2026-02-25 20:31:32');

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
(1, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:32:40'),
(2, 2, 'online', 22.12, NULL, 'OK', '2026-02-27 13:32:40'),
(6, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:33:09'),
(7, 2, 'online', 16.95, NULL, 'OK', '2026-02-27 13:33:09'),
(10, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:33:16'),
(11, 2, 'online', 19.89, NULL, 'OK', '2026-02-27 13:33:16'),
(13, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:33:25'),
(14, 2, 'online', 18.59, NULL, 'OK', '2026-02-27 13:33:25'),
(15, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:36:41'),
(16, 2, 'online', 16.26, NULL, 'OK', '2026-02-27 13:36:41'),
(17, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:36:47'),
(18, 2, 'online', 19.93, NULL, 'OK', '2026-02-27 13:36:47'),
(19, 6, 'offline', 4129.43, 0, 'SSL read: error:00000000:lib(0):func(0):reason(0), errno 54', '2026-02-27 13:36:51'),
(20, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:36:58'),
(21, 2, 'online', 18.42, NULL, 'OK', '2026-02-27 13:36:58'),
(22, 6, 'timeout', NULL, 0, 'Timeout setelah 5s', '2026-02-27 13:37:03'),
(23, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:38:22'),
(24, 2, 'online', 17.09, NULL, 'OK', '2026-02-27 13:38:22'),
(25, 6, 'offline', 4373, 0, 'SSL read: error:00000000:lib(0):func(0):reason(0), errno 54', '2026-02-27 13:38:26'),
(26, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:39:18'),
(27, 2, 'online', 19.91, NULL, 'OK', '2026-02-27 13:39:18'),
(28, 6, 'offline', 4126.77, 0, 'SSL read: error:00000000:lib(0):func(0):reason(0), errno 54', '2026-02-27 13:39:22'),
(29, 7, 'offline', 146.17, 404, 'HTTP 404 Error', '2026-02-27 13:39:22'),
(30, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:40:34'),
(31, 2, 'online', 21.64, NULL, 'OK', '2026-02-27 13:40:34'),
(32, 6, 'offline', 4172.1, 0, 'SSL read: error:00000000:lib(0):func(0):reason(0), errno 54', '2026-02-27 13:40:38'),
(33, 7, 'offline', 180.96, 404, 'HTTP 404 Error', '2026-02-27 13:40:38'),
(34, 8, 'online', 176.12, 200, 'HTTP 200 OK', '2026-02-27 13:40:38'),
(35, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:42:14'),
(36, 2, 'online', 19.33, NULL, 'OK', '2026-02-27 13:42:14'),
(37, 6, 'online', 318.2, 200, 'HTTP 200 OK', '2026-02-27 13:42:15'),
(38, 7, 'offline', 163.38, 404, 'HTTP 404 Error', '2026-02-27 13:42:15'),
(39, 8, 'online', 234.3, 200, 'HTTP 200 OK', '2026-02-27 13:42:15'),
(40, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:43:38'),
(41, 2, 'online', 23.92, NULL, 'OK', '2026-02-27 13:43:39'),
(42, 6, 'online', 298.09, 200, 'HTTP 200 OK', '2026-02-27 13:43:39'),
(43, 7, 'offline', 152.49, 404, 'HTTP 404 Error', '2026-02-27 13:43:39'),
(44, 8, 'online', 219.92, 200, 'HTTP 200 OK', '2026-02-27 13:43:39'),
(45, 9, 'offline', 193.47, 0, 'Failed to connect to apijkn.bpjs-kesehatan.go.id port 80: Connection refused', '2026-02-27 13:43:39'),
(46, 1, 'timeout', NULL, NULL, 'Request Timeout', '2026-02-27 13:45:24'),
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
(57, 1, 'timeout', NULL, NULL, 'Timeout', '2026-02-27 13:46:29'),
(58, 2, 'online', 17.5, NULL, 'TCP OK', '2026-02-27 13:46:29'),
(59, 6, 'online', 3837.8, 200, 'HTTP 200', '2026-02-27 13:47:01'),
(60, 7, 'offline', 3837.8, 404, 'HTTP 404', '2026-02-27 13:47:01'),
(61, 8, 'online', 3837.8, 200, 'HTTP 200', '2026-02-27 13:47:01'),
(62, 9, 'online', 3837.8, 301, 'HTTP 301', '2026-02-27 13:47:01'),
(63, 1, 'timeout', NULL, NULL, 'Timeout', '2026-02-27 13:47:01'),
(64, 2, 'online', 18.6, NULL, 'TCP OK', '2026-02-27 13:47:01'),
(65, 6, 'offline', 4119.4, 0, 'HTTP 0', '2026-02-27 13:47:11'),
(66, 7, 'offline', 4119.4, 404, 'HTTP 404', '2026-02-27 13:47:11'),
(67, 8, 'online', 4119.4, 200, 'HTTP 200', '2026-02-27 13:47:11'),
(68, 9, 'online', 4119.4, 301, 'HTTP 301', '2026-02-27 13:47:11'),
(69, 1, 'timeout', NULL, NULL, 'Timeout', '2026-02-27 13:47:11'),
(70, 2, 'online', 17.3, NULL, 'TCP OK', '2026-02-27 13:47:11'),
(71, 6, 'online', 2351.6, 200, 'HTTP 200', '2026-02-27 13:48:07'),
(72, 7, 'offline', 2351.6, 404, 'HTTP 404', '2026-02-27 13:48:07'),
(73, 8, 'online', 2351.5, 200, 'HTTP 200', '2026-02-27 13:48:07'),
(74, 9, 'online', 2351.5, 301, 'HTTP 301', '2026-02-27 13:48:07'),
(75, 1, 'timeout', NULL, NULL, 'Timeout', '2026-02-27 13:48:07'),
(76, 2, 'online', 17.3, NULL, 'TCP OK', '2026-02-27 13:48:07'),
(77, 6, 'online', 3774.8, 200, 'HTTP 200', '2026-02-27 13:49:51'),
(78, 7, 'offline', 3774.8, 404, 'HTTP 404', '2026-02-27 13:49:51'),
(79, 8, 'online', 3774.8, 200, 'HTTP 200', '2026-02-27 13:49:51'),
(80, 9, 'online', 3774.8, 301, 'HTTP 301', '2026-02-27 13:49:51'),
(81, 1, 'timeout', NULL, NULL, 'Timeout', '2026-02-27 13:49:51'),
(82, 2, 'online', 17.7, NULL, 'TCP OK', '2026-02-27 13:49:51'),
(83, 6, 'online', 2530.4, 200, 'HTTP 200', '2026-02-27 19:36:30'),
(84, 7, 'offline', 2530.4, 404, 'HTTP 404', '2026-02-27 19:36:30'),
(85, 8, 'online', 2530.3, 200, 'HTTP 200', '2026-02-27 19:36:30'),
(86, 9, 'online', 2530.3, 301, 'HTTP 301', '2026-02-27 19:36:30'),
(87, 1, 'timeout', NULL, NULL, 'Timeout', '2026-02-27 19:36:30'),
(88, 2, 'online', 21.4, NULL, 'TCP OK', '2026-02-27 19:36:30');

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
(1, 'Google DNS', '8.8.8.8', 'ip', 'Internet', NULL, 3, 1, NULL, '2026-02-27 13:30:22', NULL),
(2, 'Cloudflare DNS', '1.1.1.1', 'ip', 'Internet', NULL, 3, 1, NULL, '2026-02-27 13:30:22', NULL),
(6, 'Aplicare BPJS', 'https://new-api.bpjs-kesehatan.go.id/aplicaresws', 'url', 'BPJS', NULL, 5, 1, 5, '2026-02-27 13:36:41', '2026-02-27 13:38:16'),
(7, 'I-Care', 'https://apijkn.bpjs-kesehatan.go.id/wsihs/api/rs', 'url', 'BPJS', NULL, 5, 1, 5, '2026-02-27 13:39:12', NULL),
(8, 'Finger BPJS', 'https://fp.bpjs-kesehatan.go.id/finger-rest', 'url', 'BPJS', NULL, 5, 1, 5, '2026-02-27 13:40:28', NULL),
(9, 'Vclaim', 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest', 'url', 'BPJS', NULL, 5, 1, 8, '2026-02-27 13:43:32', NULL);

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
(6, 'MNT-202602-0002', 1, 'INV-IT-2025-0001 – Laptop Dell Latitude', 9, 'Giano', '2026-02-26', '2026-05-26', 'Rutin Bulanan', 'Baik', 'Baik', 'Bagus', 'Bagus', 0, 'Selesai', 'ganti pasta', 8, '2026-02-26 22:25:28', NULL);

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
(1, '2026-02-27', '20:20:00', 'M Wira', '22.50', '30.50', '55.00', '220.00', '220.00', '3.00', '76.00', 'Normal', 'Normal', 'Normal', 'Bersih', 'Terkunci', 'Normal', 1, 1, 1, 'bagus', 'Normal', 8, '2026-02-27 20:20:54', NULL);

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
('telegram_bot_token', '1', '2026-02-26 11:12:28'),
('telegram_chat_id', '1', '2026-02-26 11:12:35'),
('telegram_enabled', '1', '2026-02-25 20:38:52'),
('telegram_notif_diproses', '1', '2026-02-25 20:31:32'),
('telegram_notif_ditolak', '1', '2026-02-25 20:31:32'),
('telegram_notif_komentar', '1', '2026-02-25 20:33:42'),
('telegram_notif_selesai', '1', '2026-02-25 20:31:32'),
('telegram_notif_tiket_baru', '1', '2026-02-25 20:31:32');

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
(12, 'TKT-00001', 'Jaringan komputer', 'Komputer saya tidak berfungsi jaringan internet nya', 3, 'Tinggi', 'selesai', 9, 8, 'Lt.3, R.Marketing', NULL, NULL, 'Sudah selesai ya', '2026-02-26 08:52:50', '2026-02-26 08:53:42', '2026-02-26 08:57:05', 0, 4, '2026-02-26 08:52:50', '2026-02-26 08:57:05'),
(13, 'TKT-00002', 'Simrs tidak bisa di buka', 'tolong diperiksa simrs tidak bisa di buka', 2, 'Tinggi', 'selesai', 10, NULL, 'Lt.1, R.Operasional', NULL, NULL, 'sudah selesai, coba di cek kembali', '2026-02-26 09:27:38', NULL, '2026-02-26 09:29:51', NULL, 2, '2026-02-26 09:27:38', '2026-02-26 09:29:51'),
(14, 'TKT-00003', 'Jaringan komputer', 'Jaringan komputer saya lelet sekali', 3, 'Sedang', 'selesai', 10, NULL, 'Lt.1, R.Operasional', NULL, NULL, 'selesai', '2026-02-26 09:28:16', NULL, '2026-02-26 09:37:57', NULL, 9, '2026-02-26 09:28:16', '2026-02-26 09:37:57'),
(15, 'TKT-00004', 'CCTV tidak record', 'cctv nya tidak bisa merecord', 6, 'Sedang', 'selesai', 10, 9, 'Lt.4, R.Direksi', NULL, NULL, 'Sudah selesai', '2026-02-26 09:42:05', '2026-02-26 09:45:37', '2026-02-26 09:45:52', 3, 3, '2026-02-26 09:42:05', '2026-02-26 09:45:52'),
(16, 'TKT-00005', 'Printer EPSON', 'Bantuannya, printer epson dari tadi tidak bisa digunakan untuk print', 5, 'Tinggi', 'tidak_bisa', 10, 9, 'Lt.2, R.Keuangan', NULL, NULL, 'tidak bisa di perbaiki , order ke toko lain', '2026-02-26 10:10:25', '2026-02-26 10:11:29', '2026-02-26 10:11:55', 1, 1, '2026-02-26 10:10:25', '2026-02-26 10:11:55'),
(17, 'TKT-00006', 'PC tidak nyala', 'tidak bisa nyala', 1, 'Sedang', 'selesai', 10, 9, 'Lt.1, R.HRD', NULL, NULL, 'sudah', '2026-02-27 19:36:02', '2026-02-27 19:37:05', '2026-02-27 19:37:17', 1, 1, '2026-02-27 19:36:02', '2026-02-27 19:37:17');

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
(27, 12, 9, NULL, 'menunggu', 'Tiket dibuat oleh Giano', '2026-02-26 08:52:50'),
(28, 12, 8, 'menunggu', 'diproses', 'Tiket diambil dan mulai diproses oleh M Wira', '2026-02-26 08:53:42'),
(29, 12, 8, 'diproses', 'selesai', 'Tiket selesai ditangani.', '2026-02-26 08:57:05'),
(30, 13, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-02-26 09:27:38'),
(31, 14, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-02-26 09:28:16'),
(32, 13, 9, 'menunggu', 'selesai', 'Tiket selesai ditangani.', '2026-02-26 09:29:51'),
(33, 14, 9, 'menunggu', 'selesai', 'Tiket selesai ditangani.', '2026-02-26 09:37:57'),
(34, 15, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-02-26 09:42:05'),
(35, 15, 9, 'menunggu', 'diproses', 'Tiket diambil dan mulai diproses oleh Giano', '2026-02-26 09:45:37'),
(36, 15, 9, 'diproses', 'selesai', 'Tiket selesai ditangani.', '2026-02-26 09:45:52'),
(37, 16, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-02-26 10:10:25'),
(38, 16, 9, 'menunggu', 'diproses', 'Tiket diambil dan mulai diproses oleh Giano', '2026-02-26 10:11:29'),
(39, 16, 9, 'diproses', 'tidak_bisa', 'Tidak dapat ditangani. Keterangan: tidak bisa di perbaiki , order ke toko lain', '2026-02-26 10:11:55'),
(40, 17, 10, NULL, 'menunggu', 'Tiket dibuat oleh Qiana', '2026-02-27 19:36:02'),
(41, 17, 9, 'menunggu', 'diproses', 'Tiket diambil dan mulai diproses oleh Giano', '2026-02-27 19:37:05'),
(42, 17, 9, 'diproses', 'selesai', 'Tiket selesai ditangani.', '2026-02-27 19:37:17');

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
  `role` enum('admin','teknisi','user') NOT NULL DEFAULT 'user',
  `divisi` varchar(100) DEFAULT NULL COMMENT 'Nama bagian/divisi',
  `no_hp` varchar(20) DEFAULT NULL,
  `status` enum('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama`, `username`, `email`, `password`, `role`, `divisi`, `no_hp`, `status`, `created_at`) VALUES
(8, 'M Wira', 'wira', 'wiramuhammad16@gmail.com', '$2y$10$43m6VDpatRFEhv8tGPmLs.2Y42lKho6Qhi762RF9SUkAALPa0dKfa', 'admin', 'IT', '082177846209', 'aktif', '2026-02-26 08:30:41'),
(9, 'Giano', 'Giano', 'giano@gmail.com', '$2y$10$0VjOHL4hCSnsJLjXaCylDurasZUsmhwXmSxPOU.G9Wx62pgTLIYsG', 'teknisi', 'IT', '082177846209', 'aktif', '2026-02-26 08:52:16'),
(10, 'Qiana', 'Qiana', 'Qiana@gmail.com', '$2y$10$nahJRhdJ6YUGvfPukFLZrOeUFSDGfIYTdEfVovqtTrv9fwGO6aKpC', 'user', 'Operasional', '082177846209', 'aktif', '2026-02-26 09:26:59'),
(11, 'budi', 'budi', 'budi@gmail.com', '$2y$10$/1hBK9yM7epNopZptjHmP.BAk8YtLMVvDiyX1HFuCQ8oxvCcIz9z2', 'teknisi', 'IT', '082177846209', 'aktif', '2026-02-26 09:28:47');

--
-- Indexes for dumped tables
--

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
-- Indexes for table `bagian`
--
ALTER TABLE `bagian`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_nama` (`nama`);

--
-- Indexes for table `kategori`
--
ALTER TABLE `kategori`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `komentar`
--
ALTER TABLE `komentar`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tiket_id` (`tiket_id`),
  ADD KEY `user_id` (`user_id`);

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
-- Indexes for table `tiket`
--
ALTER TABLE `tiket`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nomor` (`nomor`),
  ADD KEY `kategori_id` (`kategori_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `teknisi_id` (`teknisi_id`);

--
-- Indexes for table `tiket_log`
--
ALTER TABLE `tiket_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tiket_id` (`tiket_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `aset_it`
--
ALTER TABLE `aset_it`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `bagian`
--
ALTER TABLE `bagian`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `komentar`
--
ALTER TABLE `komentar`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `koneksi_log`
--
ALTER TABLE `koneksi_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT for table `koneksi_monitor`
--
ALTER TABLE `koneksi_monitor`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `maintenance_it`
--
ALTER TABLE `maintenance_it`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `server_room_log`
--
ALTER TABLE `server_room_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tiket`
--
ALTER TABLE `tiket`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `tiket_log`
--
ALTER TABLE `tiket_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
