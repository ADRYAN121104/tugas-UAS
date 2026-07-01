<?php
// customer/proses_pembayaran_gateway.php
// FIXED: Pembayaran tidak lagi auto-konfirmasi.
// Status booking tetap 'menunggu' hingga admin memverifikasi di halaman Pembayaran.
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';

$id_user    = id_user();
$id_booking = (int)($_GET['booking_id'] ?? 0);
$status     = trim($_GET['status'] ?? '');

if (!$id_booking) {
    set_flash('gagal', 'Akses tidak sah.');
    header('Location: booking_saya.php');
    exit;
}

// Validasi booking milik user ini
$stmt = $db->prepare("
    SELECT b.*, r.id_rumah 
    FROM booking b 
    JOIN rumah r ON b.id_rumah = r.id_rumah 
    WHERE b.id_booking = ? AND b.id_user = ?
");
$stmt->execute([$id_booking, $id_user]);
$booking = $stmt->fetch();

if (!$booking) {
    set_flash('gagal', 'Booking tidak ditemukan.');
    header('Location: booking_saya.php');
    exit;
}

$booking_fee = $booking['booking_fee'];

if ($status === 'success') {
    try {
        $db->beginTransaction();

        // Hapus pembayaran sebelumnya jika ada (misal retry)
        $db->prepare("DELETE FROM pembayaran WHERE id_booking = ?")->execute([$id_booking]);

        // Simpan pembayaran sebagai PENDING — admin harus verifikasi dahulu
        $ins = $db->prepare("
            INSERT INTO pembayaran (id_booking, tanggal_bayar, jumlah_bayar, bukti_bayar, status_verifikasi) 
            VALUES (?, NOW(), ?, 'VIA_GATEWAY', 'pending')
        ");
        $ins->execute([$id_booking, $booking_fee]);

        // Status booking TETAP 'menunggu' — admin yang akan konfirmasi setelah verifikasi
        // (tidak ada perubahan status_booking di sini)

        $db->commit();
        set_flash('sukses', '✅ Pembayaran Booking Fee berhasil dikirim! Menunggu verifikasi admin (maks 1x24 jam). Anda akan notifikasi saat booking dikonfirmasi.');
    } catch (PDOException $e) {
        $db->rollBack();
        set_flash('gagal', 'Gagal memproses transaksi: ' . $e->getMessage());
    }
} elseif ($status === 'pending') {
    try {
        $db->beginTransaction();

        $db->prepare("DELETE FROM pembayaran WHERE id_booking = ?")->execute([$id_booking]);
        $ins = $db->prepare("
            INSERT INTO pembayaran (id_booking, tanggal_bayar, jumlah_bayar, bukti_bayar, status_verifikasi) 
            VALUES (?, NOW(), ?, 'VIA_GATEWAY_PENDING', 'pending')
        ");
        $ins->execute([$id_booking, $booking_fee]);

        $db->commit();
        set_flash('info', '⏳ Pembayaran Anda sedang tertunda. Segera selesaikan pembayaran di aplikasi/channel yang dipilih. Admin akan memverifikasi setelah dana diterima.');
    } catch (PDOException $e) {
        $db->rollBack();
        set_flash('gagal', 'Gagal menyimpan data transaksi: ' . $e->getMessage());
    }
} else {
    set_flash('gagal', '❌ Transaksi dibatalkan atau terjadi kesalahan. Silakan coba kembali.');
}

header('Location: booking_saya.php');
exit;
?>
