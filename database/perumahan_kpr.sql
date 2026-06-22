-- phpMyAdmin SQL Dump
-- Sistem Perumahan KPR
-- Database: `perumahan_kpr`

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+07:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- Tabel: bank
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `bank` (
  `id_bank` int(11) NOT NULL AUTO_INCREMENT,
  `nama_bank` varchar(100) DEFAULT NULL,
  `bunga_kpr` decimal(5,2) DEFAULT NULL,
  `tenor_maksimal` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_bank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `bank` (`nama_bank`, `bunga_kpr`, `tenor_maksimal`) VALUES
('Bank BRI', 7.50, 20),
('Bank Mandiri', 7.25, 25),
('Bank BCA', 6.99, 20),
('Bank BTN', 7.00, 30),
('Bank BNI', 7.50, 20);

-- --------------------------------------------------------
-- Tabel: booking
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `booking` (
  `id_booking` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) DEFAULT NULL,
  `id_rumah` int(11) DEFAULT NULL,
  `tanggal_booking` date DEFAULT NULL,
  `booking_fee` decimal(15,2) DEFAULT NULL,
  `status_booking` enum('menunggu','dikonfirmasi','dibatalkan') DEFAULT 'menunggu',
  PRIMARY KEY (`id_booking`),
  KEY `id_user` (`id_user`),
  KEY `id_rumah` (`id_rumah`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Tabel: denah_rumah
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `denah_rumah` (
  `id_denah` int(11) NOT NULL AUTO_INCREMENT,
  `id_tipe` int(11) DEFAULT NULL,
  `gambar_denah` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_denah`),
  KEY `id_tipe` (`id_tipe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Tabel: dokumen_kpr
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `dokumen_kpr` (
  `id_dokumen` int(11) NOT NULL AUTO_INCREMENT,
  `id_pengajuan` int(11) DEFAULT NULL,
  `ktp` varchar(255) DEFAULT NULL,
  `kk` varchar(255) DEFAULT NULL,
  `slip_gaji` varchar(255) DEFAULT NULL,
  `npwp` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_dokumen`),
  KEY `id_pengajuan` (`id_pengajuan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Tabel: galeri_rumah
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `galeri_rumah` (
  `id_galeri` int(11) NOT NULL AUTO_INCREMENT,
  `id_rumah` int(11) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_galeri`),
  KEY `id_rumah` (`id_rumah`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Tabel: pembayaran
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pembayaran` (
  `id_pembayaran` int(11) NOT NULL AUTO_INCREMENT,
  `id_booking` int(11) DEFAULT NULL,
  `tanggal_bayar` datetime DEFAULT NULL,
  `jumlah_bayar` decimal(15,2) DEFAULT NULL,
  `bukti_bayar` varchar(255) DEFAULT NULL,
  `status_verifikasi` enum('pending','valid','ditolak') DEFAULT 'pending',
  PRIMARY KEY (`id_pembayaran`),
  KEY `id_booking` (`id_booking`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Tabel: pengajuan_kpr
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pengajuan_kpr` (
  `id_pengajuan` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) DEFAULT NULL,
  `id_rumah` int(11) DEFAULT NULL,
  `id_bank` int(11) DEFAULT NULL,
  `penghasilan` decimal(15,2) DEFAULT NULL,
  `uang_muka` decimal(15,2) DEFAULT NULL,
  `tenor` int(11) DEFAULT NULL,
  `tanggal_pengajuan` date DEFAULT NULL,
  `status_pengajuan` enum('pengajuan_masuk','verifikasi_dokumen','survey','disetujui','ditolak','akad_kredit') DEFAULT 'pengajuan_masuk',
  `catatan_admin` text DEFAULT NULL,
  PRIMARY KEY (`id_pengajuan`),
  KEY `id_user` (`id_user`),
  KEY `id_rumah` (`id_rumah`),
  KEY `id_bank` (`id_bank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Tabel: perumahan
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `perumahan` (
  `id_perumahan` int(11) NOT NULL AUTO_INCREMENT,
  `nama_perumahan` varchar(100) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `maps_link` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_perumahan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `perumahan` (`nama_perumahan`, `alamat`, `deskripsi`, `maps_link`) VALUES
('Grand Residence Cibubur', 'Jl. Cibubur Raya No. 88, Jakarta Timur', 'Perumahan premium dengan konsep green living di kawasan strategis Cibubur. Dilengkapi fasilitas clubhouse, kolam renang, dan taman bermain.', 'https://maps.google.com'),
('Villa Harmoni Sentul', 'Jl. Sentul Raya Km. 35, Bogor', 'Hunian eksklusif di kaki pegunungan Sentul dengan udara segar dan pemandangan indah. Cocok untuk keluarga yang menginginkan ketenangan.', 'https://maps.google.com'),
('Green Park Depok', 'Jl. Margonda Raya No. 200, Depok', 'Perumahan modern dengan akses mudah ke berbagai fasilitas umum. Dekat dengan universitas dan pusat perbelanjaan.', 'https://maps.google.com');

-- --------------------------------------------------------
-- Tabel: rumah
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rumah` (
  `id_rumah` int(11) NOT NULL AUTO_INCREMENT,
  `id_perumahan` int(11) DEFAULT NULL,
  `id_tipe` int(11) DEFAULT NULL,
  `kode_unit` varchar(20) DEFAULT NULL,
  `blok` varchar(10) DEFAULT NULL,
  `status` enum('tersedia','booking','terjual') DEFAULT 'tersedia',
  PRIMARY KEY (`id_rumah`),
  KEY `id_perumahan` (`id_perumahan`),
  KEY `id_tipe` (`id_tipe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Tabel: tipe_rumah
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tipe_rumah` (
  `id_tipe` int(11) NOT NULL AUTO_INCREMENT,
  `nama_tipe` varchar(50) DEFAULT NULL,
  `luas_tanah` int(11) DEFAULT NULL,
  `luas_bangunan` int(11) DEFAULT NULL,
  `jumlah_kamar` int(11) DEFAULT NULL,
  `jumlah_kamar_mandi` int(11) DEFAULT NULL,
  `harga` decimal(15,2) DEFAULT NULL,
  `deskripsi` text DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_tipe`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `tipe_rumah` (`nama_tipe`, `luas_tanah`, `luas_bangunan`, `jumlah_kamar`, `jumlah_kamar_mandi`, `harga`, `deskripsi`) VALUES
('Tipe 36/72', 72, 36, 2, 1, 350000000.00, 'Tipe ekonomis dengan 2 kamar tidur, cocok untuk pasangan muda.'),
('Tipe 45/90', 90, 45, 3, 2, 550000000.00, 'Tipe menengah dengan 3 kamar tidur dan 2 kamar mandi.'),
('Tipe 60/120', 120, 60, 3, 2, 750000000.00, 'Tipe nyaman dengan ruang yang lebih luas dan carport.'),
('Tipe 72/150', 150, 72, 4, 3, 1200000000.00, 'Tipe premium dengan 4 kamar tidur, cocok untuk keluarga besar.');

-- --------------------------------------------------------
-- Tabel: tracking_pengajuan
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tracking_pengajuan` (
  `id_tracking` int(11) NOT NULL AUTO_INCREMENT,
  `id_pengajuan` int(11) DEFAULT NULL,
  `status` varchar(100) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `tanggal_update` datetime DEFAULT NULL,
  PRIMARY KEY (`id_tracking`),
  KEY `id_pengajuan` (`id_pengajuan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Tabel: users
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id_user` int(11) NOT NULL AUTO_INCREMENT,
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `role` enum('admin','marketing','customer') DEFAULT 'customer',
  `foto_profil` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`nama_lengkap`, `email`, `password`, `no_hp`, `role`) VALUES
('Admin KPR', 'admin@kpr.com', '1234', '081234567890', 'admin'),
('Marketing Satu', 'marketing@kpr.com', '1234', '082345678901', 'marketing'),
('Budi Santoso', 'budi@gmail.com', '1234', '083456789012', 'customer');

-- --------------------------------------------------------
-- Foreign Keys
-- --------------------------------------------------------
ALTER TABLE `booking`
  ADD CONSTRAINT `booking_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_ibfk_2` FOREIGN KEY (`id_rumah`) REFERENCES `rumah` (`id_rumah`) ON DELETE CASCADE;

ALTER TABLE `denah_rumah`
  ADD CONSTRAINT `denah_rumah_ibfk_1` FOREIGN KEY (`id_tipe`) REFERENCES `tipe_rumah` (`id_tipe`) ON DELETE CASCADE;

ALTER TABLE `dokumen_kpr`
  ADD CONSTRAINT `dokumen_kpr_ibfk_1` FOREIGN KEY (`id_pengajuan`) REFERENCES `pengajuan_kpr` (`id_pengajuan`) ON DELETE CASCADE;

ALTER TABLE `galeri_rumah`
  ADD CONSTRAINT `galeri_rumah_ibfk_1` FOREIGN KEY (`id_rumah`) REFERENCES `rumah` (`id_rumah`) ON DELETE CASCADE;

ALTER TABLE `pembayaran`
  ADD CONSTRAINT `pembayaran_ibfk_1` FOREIGN KEY (`id_booking`) REFERENCES `booking` (`id_booking`) ON DELETE CASCADE;

ALTER TABLE `pengajuan_kpr`
  ADD CONSTRAINT `pengajuan_kpr_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE,
  ADD CONSTRAINT `pengajuan_kpr_ibfk_2` FOREIGN KEY (`id_rumah`) REFERENCES `rumah` (`id_rumah`) ON DELETE CASCADE,
  ADD CONSTRAINT `pengajuan_kpr_ibfk_3` FOREIGN KEY (`id_bank`) REFERENCES `bank` (`id_bank`) ON DELETE CASCADE;

ALTER TABLE `rumah`
  ADD CONSTRAINT `rumah_ibfk_1` FOREIGN KEY (`id_perumahan`) REFERENCES `perumahan` (`id_perumahan`) ON DELETE CASCADE,
  ADD CONSTRAINT `rumah_ibfk_2` FOREIGN KEY (`id_tipe`) REFERENCES `tipe_rumah` (`id_tipe`) ON DELETE CASCADE;

ALTER TABLE `tracking_pengajuan`
  ADD CONSTRAINT `tracking_pengajuan_ibfk_1` FOREIGN KEY (`id_pengajuan`) REFERENCES `pengajuan_kpr` (`id_pengajuan`) ON DELETE CASCADE;

COMMIT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
