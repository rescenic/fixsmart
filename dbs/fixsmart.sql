-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Feb 26, 2026 at 05:12 AM
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

INSERT INTO `tiket` (`id`, `nomor`, `judul`, `deskripsi`, `kategori_id`, `prioritas`, `status`, `user_id`, `teknisi_id`, `lokasi`, `catatan_penolakan`, `waktu_submit`, `waktu_diproses`, `waktu_selesai`, `durasi_respon_menit`, `durasi_selesai_menit`, `created_at`, `updated_at`) VALUES
(12, 'TKT-00001', 'Jaringan komputer', 'Komputer saya tidak berfungsi jaringan internet nya', 3, 'Tinggi', 'selesai', 9, 8, 'Lt.3, R.Marketing', 'Sudah selesai ya', '2026-02-26 08:52:50', '2026-02-26 08:53:42', '2026-02-26 08:57:05', 0, 4, '2026-02-26 08:52:50', '2026-02-26 08:57:05'),
(13, 'TKT-00002', 'Simrs tidak bisa di buka', 'tolong diperiksa simrs tidak bisa di buka', 2, 'Tinggi', 'selesai', 10, NULL, 'Lt.1, R.Operasional', 'sudah selesai, coba di cek kembali', '2026-02-26 09:27:38', NULL, '2026-02-26 09:29:51', NULL, 2, '2026-02-26 09:27:38', '2026-02-26 09:29:51'),
(14, 'TKT-00003', 'Jaringan komputer', 'Jaringan komputer saya lelet sekali', 3, 'Sedang', 'selesai', 10, NULL, 'Lt.1, R.Operasional', 'selesai', '2026-02-26 09:28:16', NULL, '2026-02-26 09:37:57', NULL, 9, '2026-02-26 09:28:16', '2026-02-26 09:37:57'),
(15, 'TKT-00004', 'CCTV tidak record', 'cctv nya tidak bisa merecord', 6, 'Sedang', 'selesai', 10, 9, 'Lt.4, R.Direksi', 'Sudah selesai', '2026-02-26 09:42:05', '2026-02-26 09:45:37', '2026-02-26 09:45:52', 3, 3, '2026-02-26 09:42:05', '2026-02-26 09:45:52'),
(16, 'TKT-00005', 'Printer EPSON', 'Bantuannya, printer epson dari tadi tidak bisa digunakan untuk print', 5, 'Tinggi', 'tidak_bisa', 10, 9, 'Lt.2, R.Keuangan', 'tidak bisa di perbaiki , order ke toko lain', '2026-02-26 10:10:25', '2026-02-26 10:11:29', '2026-02-26 10:11:55', 1, 1, '2026-02-26 10:10:25', '2026-02-26 10:11:55');

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
(39, 16, 9, 'diproses', 'tidak_bisa', 'Tidak dapat ditangani. Keterangan: tidak bisa di perbaiki , order ke toko lain', '2026-02-26 10:11:55');

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
-- AUTO_INCREMENT for table `tiket`
--
ALTER TABLE `tiket`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `tiket_log`
--
ALTER TABLE `tiket_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

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
