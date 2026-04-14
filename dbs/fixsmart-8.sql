-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Mar 07, 2026 at 03:24 AM
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
(2, 'INV-IT-2025-0002', 'Printer HP LaserJet', 'Printer', 'HP', 'LaserJet Pro M404dn', 'HPLJ404-0002', 'Baik', 'Terpakai', 3, 'Administrasi', 4, '(sesuai user)', '2022-06-20', 4800000, '2025-06-20', NULL, NULL, '2026-02-26 21:14:11', NULL),
(3, 'INV-IT-2025-0003', 'Switch Cisco Catalyst', 'Switch', 'Cisco', 'Catalyst 2960-X', 'CSC2960X-0003', 'Baik', 'Terpakai', 1, 'Server Room', 2, '(sesuai user)', '2021-03-10', 22000000, '2024-03-10', 'Core switch lantai 1', NULL, '2026-02-26 21:14:11', NULL),
(4, 'INV-IT-2025-0004', 'UPS APC Smart-UPS', 'UPS', 'APC', 'Smart-UPS 1500', 'APC1500-0004', 'Baik', 'Terpakai', 1, 'IT', 11, 'budi', '2020-09-05', 9500000, NULL, 'Battery perlu diganti', NULL, '2026-02-26 21:14:11', '2026-02-26 21:17:11'),
(5, 'INV-IT-2025-0005', 'Monitor LG 24 inch', 'Monitor', 'LG', '24MK430H', 'LG24MK-0005', 'Baik', 'Terpakai', 5, 'Keuangan', 6, '(sesuai user)', '2023-07-01', 1950000, '2026-07-01', NULL, NULL, '2026-02-26 21:14:11', '2026-02-28 08:48:11'),
(6, 'INV-IT-2026-0006', 'CPU Build UP', 'Komputer', 'Build UP', 'Build UP', '123.123.123.123', 'Baik', 'Tidak Terpakai', 10, 'Gudang IT', 9, 'Giano', '2026-02-26', 7800000, '2029-02-26', 'Ram 8GB , Core i7', 8, '2026-02-26 21:15:28', '2026-03-04 08:00:41');

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
(1, 26, 'BA-IT-2026-0001', '2026-03-07', 'pembelian_baru', 'Tolong di cek, terkendala tidak bisa nyala', 'Tidak bisa di perbaiki, ada part yang harus di ganti baru (kipas prosessor)', 'Beli baru saja yang lama di buang gpp', 550000, 12, 'Andi, S. Kom', 'Kanit IT', '-', '-', '-', '2026-03-07 08:58:08', NULL);

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
(23, 'Lainnya (Non-Medis)', 'Sarana & prasarana lainnya', 'fa-toolbox', 'Non-Medis', 48, 12, '2026-03-04 08:55:49');

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
(88, 2, 'online', 21.4, NULL, 'TCP OK', '2026-02-27 19:36:30'),
(89, 6, 'offline', 4174.7, 0, 'HTTP 0', '2026-02-27 20:43:32'),
(90, 7, 'offline', 4174.5, 404, 'HTTP 404', '2026-02-27 20:43:32'),
(91, 8, 'online', 4174.5, 200, 'HTTP 200', '2026-02-27 20:43:32'),
(92, 9, 'online', 4174.5, 301, 'HTTP 301', '2026-02-27 20:43:32'),
(93, 1, 'timeout', NULL, NULL, 'Timeout', '2026-02-27 20:43:32'),
(94, 2, 'online', 16.7, NULL, 'TCP OK', '2026-02-27 20:43:32'),
(95, 6, 'online', 295.4, 200, 'HTTP 200', '2026-02-28 08:15:26'),
(96, 7, 'offline', 152.1, 404, 'HTTP 404', '2026-02-28 08:15:31'),
(97, 7, 'offline', 116, 404, 'HTTP 404', '2026-02-28 08:15:33'),
(98, 7, 'offline', 4109.7, 0, 'SSL read: error:00000000:lib(0):func(0):reason(0), errno 54', '2026-02-28 08:15:37'),
(99, 2, 'online', 15, NULL, 'TCP OK', '2026-02-28 08:15:38'),
(100, 1, 'timeout', NULL, NULL, 'Timeout', '2026-02-28 08:15:42'),
(101, 6, 'offline', 4112.6, 0, 'SSL read: error:00000000:lib(0):func(0):reason(0), errno 54', '2026-02-28 08:15:47'),
(102, 6, 'online', 599.7, 200, 'HTTP 200', '2026-03-02 08:08:16'),
(103, 7, 'offline', 599.7, 404, 'HTTP 404', '2026-03-02 08:08:16'),
(104, 8, 'online', 599.7, 200, 'HTTP 200', '2026-03-02 08:08:16'),
(105, 9, 'online', 599.6, 301, 'HTTP 301', '2026-03-02 08:08:16'),
(106, 1, 'timeout', NULL, NULL, 'Timeout', '2026-03-02 08:08:16'),
(107, 2, 'online', 15.2, NULL, 'TCP OK', '2026-03-02 08:08:16'),
(108, 6, 'offline', 5005.9, 0, 'name lookup timed out', '2026-03-03 21:57:36'),
(109, 2, 'timeout', NULL, NULL, 'Timeout', '2026-03-03 21:57:45'),
(110, 6, 'offline', 15003.3, 0, 'HTTP 0', '2026-03-03 21:58:06'),
(111, 7, 'offline', 15003.3, 0, 'HTTP 0', '2026-03-03 21:58:06'),
(112, 8, 'offline', 15003.3, 0, 'HTTP 0', '2026-03-03 21:58:06'),
(113, 9, 'offline', 15003.2, 0, 'HTTP 0', '2026-03-03 21:58:06'),
(114, 1, 'timeout', NULL, NULL, 'Timeout', '2026-03-03 21:58:06'),
(115, 2, 'timeout', NULL, NULL, 'Timeout', '2026-03-03 21:58:06'),
(116, 6, 'online', 353.9, 200, 'HTTP 200', '2026-03-04 08:49:14'),
(117, 7, 'offline', 353.9, 404, 'HTTP 404', '2026-03-04 08:49:14'),
(118, 8, 'online', 353.8, 200, 'HTTP 200', '2026-03-04 08:49:14'),
(119, 9, 'online', 353.8, 301, 'HTTP 301', '2026-03-04 08:49:14'),
(120, 1, 'timeout', NULL, NULL, 'Timeout', '2026-03-04 08:49:14'),
(121, 2, 'online', 14.8, NULL, 'TCP OK', '2026-03-04 08:49:14'),
(122, 6, 'offline', 4249.1, 0, 'HTTP 0', '2026-03-04 15:15:57'),
(123, 7, 'offline', 4249.1, 404, 'HTTP 404', '2026-03-04 15:15:57'),
(124, 8, 'online', 4249.1, 200, 'HTTP 200', '2026-03-04 15:15:57'),
(125, 9, 'online', 4249.1, 301, 'HTTP 301', '2026-03-04 15:15:57'),
(126, 1, 'timeout', NULL, NULL, 'Timeout', '2026-03-04 15:15:57'),
(127, 2, 'online', 15.8, NULL, 'TCP OK', '2026-03-04 15:15:57'),
(128, 6, 'online', 320.4, 200, 'HTTP 200', '2026-03-05 14:07:57'),
(129, 7, 'offline', 320.4, 404, 'HTTP 404', '2026-03-05 14:07:57'),
(130, 8, 'online', 320.4, 200, 'HTTP 200', '2026-03-05 14:07:57'),
(131, 9, 'online', 320.4, 301, 'HTTP 301', '2026-03-05 14:07:57'),
(132, 1, 'timeout', NULL, NULL, 'Timeout', '2026-03-05 14:07:57'),
(133, 2, 'online', 14.2, NULL, 'TCP OK', '2026-03-05 14:07:57'),
(134, 6, 'offline', 4147.1, 0, 'HTTP 0', '2026-03-05 14:20:04'),
(135, 7, 'offline', 4147.1, 404, 'HTTP 404', '2026-03-05 14:20:04'),
(136, 8, 'online', 4147, 200, 'HTTP 200', '2026-03-05 14:20:04'),
(137, 9, 'online', 4147, 301, 'HTTP 301', '2026-03-05 14:20:04'),
(138, 1, 'timeout', NULL, NULL, 'Timeout', '2026-03-05 14:20:04'),
(139, 2, 'online', 14.7, NULL, 'TCP OK', '2026-03-05 14:20:04'),
(140, 6, 'online', 288.4, 200, 'HTTP 200', '2026-03-05 14:45:15'),
(141, 7, 'offline', 288.4, 404, 'HTTP 404', '2026-03-05 14:45:15'),
(142, 8, 'online', 288.4, 200, 'HTTP 200', '2026-03-05 14:45:15'),
(143, 9, 'online', 288.4, 301, 'HTTP 301', '2026-03-05 14:45:15'),
(144, 1, 'timeout', NULL, NULL, 'Timeout', '2026-03-05 14:45:15'),
(145, 2, 'online', 14.6, NULL, 'TCP OK', '2026-03-05 14:45:15'),
(146, 6, 'online', 322.9, 200, 'HTTP 200', '2026-03-05 15:18:10'),
(147, 7, 'offline', 322.9, 404, 'HTTP 404', '2026-03-05 15:18:10'),
(148, 8, 'online', 322.9, 200, 'HTTP 200', '2026-03-05 15:18:10'),
(149, 9, 'online', 322.9, 301, 'HTTP 301', '2026-03-05 15:18:10'),
(150, 1, 'timeout', NULL, NULL, 'Timeout', '2026-03-05 15:18:10'),
(151, 2, 'online', 14.8, NULL, 'TCP OK', '2026-03-05 15:18:10'),
(152, 6, 'online', 406.8, 200, 'HTTP 200', '2026-03-05 20:55:05'),
(153, 7, 'offline', 406.8, 404, 'HTTP 404', '2026-03-05 20:55:05'),
(154, 8, 'online', 406.8, 200, 'HTTP 200', '2026-03-05 20:55:05'),
(155, 9, 'online', 406.7, 301, 'HTTP 301', '2026-03-05 20:55:05'),
(156, 1, 'timeout', NULL, NULL, 'Timeout', '2026-03-05 20:55:05'),
(157, 2, 'online', 18.7, NULL, 'TCP OK', '2026-03-05 20:55:05'),
(158, 6, 'online', 2268.1, 200, 'HTTP 200', '2026-03-06 09:58:00'),
(159, 7, 'offline', 2268.1, 404, 'HTTP 404', '2026-03-06 09:58:00'),
(160, 8, 'online', 2268, 200, 'HTTP 200', '2026-03-06 09:58:00'),
(161, 9, 'online', 2268, 301, 'HTTP 301', '2026-03-06 09:58:00'),
(162, 1, 'timeout', NULL, NULL, 'Timeout', '2026-03-06 09:58:00'),
(163, 2, 'online', 14.7, NULL, 'TCP OK', '2026-03-06 09:58:00');

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
(25, 8, 'wiramuhammad16@gmail.com', 'berhasil', '::1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15', 'Desktop', 'Safari', 'macOS 10.15.7', NULL, 0, '2026-03-07 09:17:55');

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
(23, 'TKT-00012', 'komputer', 'komputer nya tidak nyala\n\n---\n📦 Aset terkait: INV-IT-2026-0006 | CPU Build UP | Build UP | Build UP', 1, 'Sedang', 'selesai', 10, 9, 'Lt.1, R.Operasional', 6, NULL, 'selesai ya', '2026-03-03 23:01:14', '2026-03-03 23:01:29', '2026-03-03 23:01:43', 0, 0, '2026-03-03 23:01:14', '2026-03-03 23:01:43'),
(24, 'TKT-00013', 'tidak bisa nyala', 'tidak bisa nyala\n\n---\n📦 Aset terkait: INV-IT-2026-0006 | CPU Build UP | Build UP | Build UP', 1, 'Sedang', 'selesai', 10, 8, 'Lt.1, R.Operasional', 6, NULL, 'sudah selesai ya', '2026-03-03 23:09:01', '2026-03-03 23:09:44', '2026-03-03 23:10:02', 0, 1, '2026-03-03 23:09:01', '2026-03-03 23:10:02'),
(25, 'TKT-00014', 'tidak keluar tinta', 'tidak keluar tinta nya\n\n---\n📦 Aset terkait: INV-IT-2025-0002 | Printer HP LaserJet | HP | LaserJet Pro M404dn', 5, 'Sedang', 'selesai', 10, 8, 'Lt.1, R.Operasional', 2, NULL, 'sudah selesai', '2026-03-03 23:09:21', '2026-03-03 23:14:19', '2026-03-03 23:14:49', 4, 5, '2026-03-03 23:09:21', '2026-03-03 23:14:49'),
(26, 'TKT-00004', 'Komputer kerja', 'Tolong di cek, terkendala tidak bisa nyala', 1, 'Sedang', 'tidak_bisa', 10, 12, 'Lt.2, R.Keuangan', NULL, NULL, 'Tidak bisa di perbaiki, ada part yang harus di ganti baru (kipas prosessor)', '2026-03-07 08:41:36', '2026-03-07 08:56:38', '2026-03-07 08:56:38', 15, 15, '2026-03-07 08:41:36', '2026-03-07 08:56:38');

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
(9, 'TKT-IPSRS-2026-0009', 'Ambulan', 'ban nya bocor', 22, 'Non-Medis', 'Sedang', 'selesai', 10, 9, 'Lt.1, R.Operasional', NULL, '2026-03-04 21:57:36', '2026-03-04 21:58:23', '2026-03-04 21:58:23', 0, 0, 'sudah ya', NULL, '2026-03-04 21:57:36', '2026-03-04 21:58:23');

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
(19, 9, 9, 'menunggu', 'selesai', 'Tiket IPSRS selesai ditangani.', '2026-03-04 21:58:23');

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
(69, 26, 12, 'tidak_bisa', 'tidak_bisa', 'Berita Acara dibuat: BA-IT-2026-0001', '2026-03-07 08:58:08');

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
  `role` enum('admin','teknisi','teknisi_ipsrs','user') NOT NULL DEFAULT 'user',
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
(9, 'Giano', 'Giano', 'giano@gmail.com', '$2y$10$0VjOHL4hCSnsJLjXaCylDurasZUsmhwXmSxPOU.G9Wx62pgTLIYsG', 'teknisi_ipsrs', 'IT', '082177846209', 'aktif', '2026-02-26 08:52:16'),
(10, 'Qiana', 'Qiana', 'Qiana@gmail.com', '$2y$10$nahJRhdJ6YUGvfPukFLZrOeUFSDGfIYTdEfVovqtTrv9fwGO6aKpC', 'user', 'Operasional', '082177846209', 'aktif', '2026-02-26 09:26:59'),
(11, 'budi', 'budi', 'budi@gmail.com', '$2y$10$/1hBK9yM7epNopZptjHmP.BAk8YtLMVvDiyX1HFuCQ8oxvCcIz9z2', 'teknisi_ipsrs', 'IT', '082177846209', 'aktif', '2026-02-26 09:28:47'),
(12, 'Danu', 'Danu', 'danu@gmail.com', '$2y$10$C4Xi0N8ubMaRT6gh.8yJxO4HwELs5xHYMzFvVJ4ErkMyahPAxxXaK', 'teknisi', 'Keuangan', '082177846209', 'aktif', '2026-03-06 13:18:04');

--
-- Indexes for dumped tables
--

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
-- AUTO_INCREMENT for table `bagian`
--
ALTER TABLE `bagian`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `berita_acara`
--
ALTER TABLE `berita_acara`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `kategori`
--
ALTER TABLE `kategori`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `kategori_ipsrs`
--
ALTER TABLE `kategori_ipsrs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `komentar`
--
ALTER TABLE `komentar`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `komentar_ipsrs`
--
ALTER TABLE `komentar_ipsrs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `koneksi_log`
--
ALTER TABLE `koneksi_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=164;

--
-- AUTO_INCREMENT for table `koneksi_monitor`
--
ALTER TABLE `koneksi_monitor`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_log`
--
ALTER TABLE `login_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

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
-- AUTO_INCREMENT for table `server_room_log`
--
ALTER TABLE `server_room_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tiket`
--
ALTER TABLE `tiket`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `tiket_foto`
--
ALTER TABLE `tiket_foto`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tiket_ipsrs`
--
ALTER TABLE `tiket_ipsrs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `tiket_ipsrs_foto`
--
ALTER TABLE `tiket_ipsrs_foto`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tiket_ipsrs_log`
--
ALTER TABLE `tiket_ipsrs_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `tiket_log`
--
ALTER TABLE `tiket_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

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
-- Constraints for table `maintenance_ipsrs`
--
ALTER TABLE `maintenance_ipsrs`
  ADD CONSTRAINT `fk_mnt_ipsrs_aset` FOREIGN KEY (`aset_id`) REFERENCES `aset_ipsrs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mnt_ipsrs_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mnt_ipsrs_teknisi` FOREIGN KEY (`teknisi_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

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
