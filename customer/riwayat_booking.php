<?php
// customer/riwayat_booking.php
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';
require_once '../includes/sidebar_customer.php';
$id = id_user();
$stmt = $db->prepare("SELECT b.*,p.nama_perumahan,t.nama_tipe,t.harga,r.blok,r.kode_unit,pay.status_verifikasi,pay.jumlah_bayar FROM booking b JOIN rumah r ON b.id_rumah=r.id_rumah JOIN perumahan p ON r.id_perumahan=p.id_perumahan JOIN tipe_rumah t ON r.id_tipe=t.id_tipe LEFT JOIN pembayaran pay ON b.id_booking=pay.id_booking WHERE b.id_user=? ORDER BY b.tanggal_booking DESC");
$stmt->execute([$id]); $list=$stmt->fetchAll();
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Riwayat Booking</title>
<link rel="stylesheet" href="../assets/css/customer.css"></head><body>
<?php sidebar_customer('riwayat_booking'); ?>
<div class="cmain"><div class="ccontent">
<div style="margin-bottom:22px;"><h2 style="font-size:22px;font-weight:800;">🕐 Riwayat Semua Booking</h2><p style="color:#64748b;font-size:14px;">Seluruh riwayat pemesanan unit rumah Anda</p></div>
<div class="cpanel">
    <div class="ctbl-wrap"><table class="ctbl">
        <thead><tr><th>Tgl Booking</th><th>Properti</th><th>Tipe & Harga</th><th>Booking Fee</th><th>Status Booking</th><th>Status Bayar</th></tr></thead>
        <tbody>
        <?php if(empty($list)): ?>
            <tr><td colspan="6"><div class="cempty"><div class="ico">🕐</div><h4>Belum ada riwayat booking</h4><p>Semua riwayat booking Anda akan tampil di sini</p></div></td></tr>
        <?php else: foreach($list as $b): ?>
            <tr>
                <td><?= format_tanggal($b['tanggal_booking']) ?></td>
                <td><b><?= htmlspecialchars($b['nama_perumahan']) ?></b><br><small style="color:#2563eb;">Blok <?= htmlspecialchars($b['blok'].'-'.$b['kode_unit']) ?></small></td>
                <td><?= htmlspecialchars($b['nama_tipe']) ?><br><span style="font-weight:700;color:#10b981;font-size:13px;"><?= format_rupiah($b['harga']) ?></span></td>
                <td><?= format_rupiah($b['booking_fee']??0) ?></td>
                <td><?= badge_booking($b['status_booking']) ?></td>
                <td><?= $b['status_verifikasi'] ? badge_pembayaran($b['status_verifikasi']) : '<span style="color:#94a3b8;font-size:12px;">Belum bayar</span>' ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</div></div>
<script src="../assets/js/script.js"></script>
</body></html>
