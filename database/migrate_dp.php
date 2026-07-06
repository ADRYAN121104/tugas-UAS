<?php
require __DIR__ . '/../config/koneksi.php';
try {
    // Tambah kolom sertifikat ke pengajuan_kpr
    $col = $db->query("SHOW COLUMNS FROM pengajuan_kpr LIKE 'sertifikat'")->fetch();
    if (!$col) {
        $db->exec("ALTER TABLE pengajuan_kpr ADD COLUMN sertifikat VARCHAR(255) DEFAULT NULL AFTER catatan_admin");
        echo "Added sertifikat column\n";
    } else { echo "sertifikat column exists\n"; }

    // Buat tabel pembayaran_dp
    $t = $db->query("SHOW TABLES LIKE 'pembayaran_dp'")->fetch();
    if (!$t) {
        $db->exec("CREATE TABLE `pembayaran_dp` (
            `id_dp` int(11) NOT NULL AUTO_INCREMENT,
            `id_pengajuan` int(11) NOT NULL,
            `jumlah_dp` decimal(15,2) NOT NULL,
            `bukti_dp` varchar(255) DEFAULT NULL,
            `status_verifikasi` enum('pending','valid','ditolak') DEFAULT 'pending',
            `tanggal_bayar` datetime DEFAULT NULL,
            `catatan` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id_dp`),
            KEY `id_pengajuan` (`id_pengajuan`),
            CONSTRAINT `dp_ibfk_1` FOREIGN KEY (`id_pengajuan`) REFERENCES `pengajuan_kpr` (`id_pengajuan`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        echo "Created pembayaran_dp table\n";
    } else { echo "pembayaran_dp table exists\n"; }
    echo "Done!\n";
} catch(Exception $e) { echo "Error: " . $e->getMessage() . "\n"; }
