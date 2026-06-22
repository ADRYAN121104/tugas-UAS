<?php
// customer/booking_baru.php
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';
require_once '../includes/sidebar_customer.php';

$id_user = id_user();
$id_rumah = (int)($_GET['id_rumah'] ?? 0);

if (!$id_rumah) {
    set_flash('gagal', 'Unit rumah tidak valid.');
    header('Location: ../guest/katalog.php');
    exit;
}

// Ambil info unit rumah
$stmt = $db->prepare("
    SELECT r.*, p.nama_perumahan, p.alamat, t.nama_tipe, t.harga, t.luas_tanah, t.luas_bangunan, t.jumlah_kamar, t.jumlah_kamar_mandi
    FROM rumah r
    JOIN perumahan p ON r.id_perumahan = p.id_perumahan
    JOIN tipe_rumah t ON r.id_tipe = t.id_tipe
    WHERE r.id_rumah = ?
");
$stmt->execute([$id_rumah]);
$unit = $stmt->fetch();

if (!$unit) {
    set_flash('gagal', 'Unit rumah tidak ditemukan.');
    header('Location: ../guest/katalog.php');
    exit;
}

if ($unit['status'] !== 'tersedia') {
    set_flash('gagal', 'Unit rumah ini sudah tidak tersedia untuk dibooking.');
    header('Location: ../guest/detail_rumah.php?id=' . $id_rumah);
    exit;
}

// Cek apakah customer sudah membooking unit ini sebelumnya
$cek = $db->prepare("SELECT id_booking, status_booking FROM booking WHERE id_user = ? AND id_rumah = ? AND status_booking != 'dibatalkan'");
$cek->execute([$id_user, $id_rumah]);
$booking_exist = $cek->fetch();

if ($booking_exist) {
    set_flash('info', 'Anda sudah mengajukan booking untuk unit ini.');
    header('Location: booking_saya.php');
    exit;
}

$booking_fee = 2000000; // Flat Rp 2.000.000
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // 1. Tambah data booking
        $ins = $db->prepare("
            INSERT INTO booking (id_user, id_rumah, tanggal_booking, booking_fee, status_booking)
            VALUES (?, ?, CURDATE(), ?, 'menunggu')
        ");
        $ins->execute([$id_user, $id_rumah, $booking_fee]);
        $id_booking = $db->lastInsertId();

        $db->commit();
        set_flash('sukses', 'Booking unit berhasil dibuat! Silakan lakukan pembayaran booking fee.');
        header('Location: upload_pembayaran.php?id=' . $id_booking);
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Konfirmasi Booking Unit - RumahKPR</title>
    <link rel="stylesheet" href="../assets/css/customer.css">
</head>
<body>
<?php sidebar_customer('booking_saya'); ?>
<div class="cmain">
    <div class="ccontent">
        <?php tampil_flash(); ?>
        <div style="margin-bottom:22px;">
            <h2 style="font-size:22px;font-weight:800;">🔑 Konfirmasi Booking Unit</h2>
            <p style="color:#64748b;font-size:14px;">Silakan tinjau kembali unit rumah yang Anda pilih sebelum melanjutkan pemesanan</p>
        </div>

        <?php if($error): ?><div class="calert calert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div style="display:grid; grid-template-columns: 1fr 400px; gap: 24px; align-items: start;" id="booking-grid">
            <div class="cpanel">
                <div class="cpanel-header"><h3>Ketentuan & Perjanjian Booking</h3></div>
                <div class="cpanel-body">
                    <p style="font-size:14px; color:#475569; margin-bottom:16px; line-height:1.7;">
                        Dengan melanjutkan proses booking unit ini, Anda menyetujui ketentuan berikut:
                    </p>
                    <ul style="font-size:13.5px; color:#475569; margin-left: 20px; margin-bottom: 24px; line-height: 1.8;">
                        <li>Booking fee sebesar <b><?= format_rupiah($booking_fee) ?></b> wajib dibayarkan dalam waktu 1x24 jam setelah pengajuan.</li>
                        <li>Bukti pembayaran wajib diunggah melalui portal customer agar admin dapat segera mengunci unit rumah pilihan Anda.</li>
                        <li>Booking fee tidak dapat dikembalikan apabila Anda melakukan pembatalan sepihak setelah proses verifikasi disetujui.</li>
                        <li>Setelah status booking berubah menjadi <b>Dikonfirmasi</b>, Anda dipersilakan untuk melanjutkan proses pengajuan KPR.</li>
                    </ul>

                    <form method="POST">
                        <div style="display:flex; gap:10px;">
                            <a href="../guest/detail_rumah.php?id=<?= $id_rumah ?>" class="cbtn cbtn-gray">← Batal</a>
                            <button type="submit" class="cbtn cbtn-primary">✅ Konfirmasi & Booking Sekarang</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="cpanel">
                <div class="cpanel-header"><h3>Ringkasan Unit Properti</h3></div>
                <div class="cpanel-body" style="font-size:13.5px;">
                    <div style="height:140px; background:linear-gradient(135deg,#0f172a,#1e3a8a); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:60px; margin-bottom:16px; color:#fff;">🏠</div>
                    <h3 style="font-size:16px; font-weight:800; margin-bottom:6px;"><?= htmlspecialchars($unit['nama_perumahan']) ?></h3>
                    <p style="color:#2563eb; font-weight:700; margin-bottom:14px;">Blok <?= htmlspecialchars($unit['blok'] . '-' . $unit['kode_unit']) ?></p>
                    
                    <table style="width:100%; border-collapse:collapse; margin-bottom:16px;">
                        <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b; padding:8px 0;">Tipe Rumah</td><td style="font-weight:700; text-align:right;"><?= htmlspecialchars($unit['nama_tipe']) ?></td></tr>
                        <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b; padding:8px 0;">Dimensi LT / LB</td><td style="font-weight:700; text-align:right;"><?= $unit['luas_tanah'] ?> m² / <?= $unit['luas_bangunan'] ?> m²</td></tr>
                        <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b; padding:8px 0;">Spesifikasi</td><td style="font-weight:700; text-align:right;">🛏️ <?= $unit['jumlah_kamar'] ?> KT | 🚿 <?= $unit['jumlah_kamar_mandi'] ?> KM</td></tr>
                        <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b; padding:8px 0;">Harga Unit</td><td style="font-weight:800; color:#2563eb; text-align:right;"><?= format_rupiah($unit['harga']) ?></td></tr>
                        <tr><td style="color:#64748b; padding:8px 0; font-weight:700;">Biaya Booking Fee</td><td style="font-weight:800; color:#10b981; text-align:right; font-size:15px;"><?= format_rupiah($booking_fee) ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="../assets/js/script.js"></script>
<style>@media(max-width:768px){#booking-grid{grid-template-columns:1fr!important;}}</style>
</body>
</html>
