<?php
// customer/proses_dp_gateway.php
// Handler setelah Midtrans DP berhasil / gagal — AUTO VERIFIED tanpa verifikasi manual admin
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';
require_once '../config/midtrans.php';

$id_user      = id_user();
$id_pengajuan = (int)($_GET['id_pengajuan'] ?? 0);
$status       = trim($_GET['status'] ?? '');
$order_id     = trim($_GET['order_id'] ?? '');

if (!$id_pengajuan) {
    set_flash('gagal', 'Akses tidak sah.');
    header('Location: status_kpr.php');
    exit;
}

// Validasi pengajuan milik user ini
$stmt = $db->prepare("
    SELECT pk.*, p.nama_perumahan, r.blok, r.kode_unit, r.id_rumah
    FROM pengajuan_kpr pk
    JOIN rumah r     ON pk.id_rumah = r.id_rumah
    JOIN perumahan p ON r.id_perumahan = p.id_perumahan
    WHERE pk.id_pengajuan = ? AND pk.id_user = ?
");
$stmt->execute([$id_pengajuan, $id_user]);
$pengajuan = $stmt->fetch();

if (!$pengajuan) {
    set_flash('gagal', 'Pengajuan tidak ditemukan.');
    header('Location: status_kpr.php');
    exit;
}

$jumlah_dp = (float)$pengajuan['uang_muka'];

if ($status === 'success') {
    try {
        $db->beginTransaction();

        // Hapus DP lama yang pending/ditolak jika ada
        $db->prepare("DELETE FROM pembayaran_dp WHERE id_pengajuan=? AND status_verifikasi != 'valid'")->execute([$id_pengajuan]);

        // Simpan pembayaran DP dengan status VALID otomatis (sudah terbayar via gateway)
        $ins = $db->prepare("
            INSERT INTO pembayaran_dp 
                (id_pengajuan, jumlah_dp, bukti_dp, tanggal_bayar, status_verifikasi,
                 midtrans_order_id, midtrans_transaction_id, payment_method, midtrans_payment_type)
            VALUES (?, ?, 'VIA_GATEWAY', NOW(), 'valid', ?, ?, 'gateway', 'snap')
        ");
        $ins->execute([$id_pengajuan, $jumlah_dp, $order_id, $order_id]);
        $id_dp = $db->lastInsertId();

        // Ubah status KPR ke akad_kredit (bypass verifikasi admin)
        $db->prepare("UPDATE pengajuan_kpr SET status_pengajuan='akad_kredit' WHERE id_pengajuan=?")->execute([$id_pengajuan]);
        
        // Kunci unit rumah sebagai terjual
        $db->prepare("UPDATE rumah SET status='terjual' WHERE id_rumah=?")->execute([$pengajuan['id_rumah']]);

        // Log tracking
        $db->prepare("INSERT INTO tracking_pengajuan (id_pengajuan, status, keterangan, tanggal_update) VALUES (?, 'akad_kredit', ?, NOW())")
           ->execute([$id_pengajuan, '✅ Uang Muka (DP) telah dibayar via Midtrans Payment Gateway dan terverifikasi otomatis. Pengajuan masuk tahap Akad Kredit.']);

        $db->commit();

        // Redirect ke halaman struk
        header("Location: struk_dp.php?id_pengajuan=$id_pengajuan&id_dp=$id_dp");
        exit;

    } catch (PDOException $e) {
        $db->rollBack();
        set_flash('gagal', 'Gagal memproses pembayaran DP: ' . $e->getMessage());
    }

} elseif ($status === 'pending') {
    // Simpan sebagai pending — customer perlu selesaikan di kanal bayar (VA, dll)
    $db->prepare("DELETE FROM pembayaran_dp WHERE id_pengajuan=? AND status_verifikasi='pending'")->execute([$id_pengajuan]);
    $ins = $db->prepare("
        INSERT INTO pembayaran_dp 
            (id_pengajuan, jumlah_dp, bukti_dp, tanggal_bayar, status_verifikasi,
             midtrans_order_id, payment_method, midtrans_payment_type)
        VALUES (?, ?, 'VIA_GATEWAY_PENDING', NOW(), 'pending', ?, 'gateway', 'snap')
    ");
    $ins->execute([$id_pengajuan, $jumlah_dp, $order_id]);
    set_flash('info', '⏳ Pembayaran DP sedang diproses. Segera selesaikan pembayaran sesuai instruksi yang dikirim ke email/WhatsApp Anda. Status akan diperbarui otomatis setelah dana diterima.');

} else {
    set_flash('gagal', '❌ Pembayaran DP dibatalkan atau terjadi kesalahan. Silakan coba kembali.');
}

header("Location: bayar_dp.php?id=$id_pengajuan");
exit;
?>
