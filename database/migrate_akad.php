<?php
// database/migrate_akad.php
// Jalankan sekali untuk menambah kolom akad + tabel pembayaran_dp
require __DIR__ . '/../config/koneksi.php';

try {
    // 1. Kolom sertifikat_rumah di pengajuan_kpr
    $col1 = $db->query("SHOW COLUMNS FROM pengajuan_kpr LIKE 'sertifikat_rumah'")->fetch();
    if (!$col1) {
        $db->exec("ALTER TABLE pengajuan_kpr ADD COLUMN sertifikat_rumah VARCHAR(255) DEFAULT NULL AFTER catatan_admin");
        echo "✅ Kolom 'sertifikat_rumah' ditambahkan ke pengajuan_kpr.<br>";
    } else {
        echo "ℹ️ Kolom 'sertifikat_rumah' sudah ada.<br>";
    }

    // 2. Kolom akad_dikonfirmasi di pengajuan_kpr
    $col2 = $db->query("SHOW COLUMNS FROM pengajuan_kpr LIKE 'akad_dikonfirmasi'")->fetch();
    if (!$col2) {
        $db->exec("ALTER TABLE pengajuan_kpr ADD COLUMN akad_dikonfirmasi TINYINT(1) DEFAULT 0 AFTER sertifikat_rumah");
        echo "✅ Kolom 'akad_dikonfirmasi' ditambahkan ke pengajuan_kpr.<br>";
    } else {
        echo "ℹ️ Kolom 'akad_dikonfirmasi' sudah ada.<br>";
    }

    // 3. Kolom tanggal_akad di pengajuan_kpr
    $col3 = $db->query("SHOW COLUMNS FROM pengajuan_kpr LIKE 'tanggal_akad'")->fetch();
    if (!$col3) {
        $db->exec("ALTER TABLE pengajuan_kpr ADD COLUMN tanggal_akad DATE DEFAULT NULL AFTER akad_dikonfirmasi");
        echo "✅ Kolom 'tanggal_akad' ditambahkan ke pengajuan_kpr.<br>";
    } else {
        echo "ℹ️ Kolom 'tanggal_akad' sudah ada.<br>";
    }

    // 4. Tabel pembayaran_dp
    $tbl = $db->query("SHOW TABLES LIKE 'pembayaran_dp'")->fetch();
    if (!$tbl) {
        $db->exec("
            CREATE TABLE `pembayaran_dp` (
              `id_dp`              INT(11)         NOT NULL AUTO_INCREMENT,
              `id_pengajuan`       INT(11)         NOT NULL,
              `jumlah_dp`          DECIMAL(15,2)   NOT NULL,
              `bukti_bayar`        VARCHAR(255)    DEFAULT NULL,
              `tanggal_bayar`      DATETIME        DEFAULT NULL,
              `status_verifikasi`  ENUM('pending','valid','ditolak') DEFAULT 'pending',
              `catatan`            TEXT            DEFAULT NULL,
              `created_at`         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id_dp`),
              KEY `id_pengajuan` (`id_pengajuan`),
              CONSTRAINT `pembayaran_dp_ibfk_1`
                FOREIGN KEY (`id_pengajuan`) REFERENCES `pengajuan_kpr` (`id_pengajuan`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        echo "✅ Tabel 'pembayaran_dp' berhasil dibuat.<br>";
    } else {
        echo "ℹ️ Tabel 'pembayaran_dp' sudah ada.<br>";
    }

    echo "<br><b>✅ Migrasi selesai!</b>";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
