<?php
// database/migrate_cicilan.php - Jalankan sekali untuk membuat tabel cicilan_kpr
require __DIR__ . '/../config/koneksi.php';

try {
    // Cek tabel sudah ada
    $exists = $db->query("SHOW TABLES LIKE 'cicilan_kpr'")->fetch();
    if (!$exists) {
        $db->exec("
            CREATE TABLE `cicilan_kpr` (
              `id_cicilan` int(11) NOT NULL AUTO_INCREMENT,
              `id_pengajuan` int(11) NOT NULL,
              `bulan_ke` int(11) NOT NULL,
              `tanggal_jatuh_tempo` date NOT NULL,
              `jumlah_cicilan` decimal(15,2) NOT NULL,
              `pokok` decimal(15,2) DEFAULT NULL,
              `bunga` decimal(15,2) DEFAULT NULL,
              `status_bayar` enum('belum','lunas','terlambat') DEFAULT 'belum',
              `tanggal_bayar` datetime DEFAULT NULL,
              `bukti_bayar` varchar(255) DEFAULT NULL,
              `status_verifikasi` enum('pending','valid','ditolak') DEFAULT 'pending',
              `catatan` text DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id_cicilan`),
              KEY `id_pengajuan` (`id_pengajuan`),
              CONSTRAINT `cicilan_kpr_ibfk_1` FOREIGN KEY (`id_pengajuan`) REFERENCES `pengajuan_kpr` (`id_pengajuan`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        echo "Table 'cicilan_kpr' created successfully.\n";
    } else {
        echo "Table 'cicilan_kpr' already exists.\n";
    }

    // Pastikan kolom keterangan_admin ada di tracking_pengajuan  
    $col = $db->query("SHOW COLUMNS FROM tracking_pengajuan LIKE 'keterangan'")->fetch();
    if (!$col) {
        $db->exec("ALTER TABLE tracking_pengajuan ADD COLUMN keterangan TEXT DEFAULT NULL");
        echo "Added 'keterangan' column to tracking_pengajuan.\n";
    } else {
        echo "Column 'keterangan' already in tracking_pengajuan.\n";
    }

    echo "Migration complete!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
