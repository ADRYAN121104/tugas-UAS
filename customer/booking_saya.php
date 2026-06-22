<?php
// customer/booking_saya.php
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';
require_once '../includes/sidebar_customer.php';
$id = id_user();
$stmt = $db->prepare("SELECT b.*,p.nama_perumahan,t.nama_tipe,t.harga,r.blok,r.kode_unit,r.id_rumah,
    pay.id_pembayaran,pay.jumlah_bayar,pay.status_verifikasi,pay.bukti_bayar
    FROM booking b JOIN rumah r ON b.id_rumah=r.id_rumah JOIN perumahan p ON r.id_perumahan=p.id_perumahan
    JOIN tipe_rumah t ON r.id_tipe=t.id_tipe LEFT JOIN pembayaran pay ON b.id_booking=pay.id_booking
    WHERE b.id_user=? ORDER BY b.id_booking DESC");
$stmt->execute([$id]); $bookings=$stmt->fetchAll();
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Booking Saya</title>
<link rel="stylesheet" href="../assets/css/customer.css"></head><body>
<?php sidebar_customer('booking_saya'); ?>
<div class="cmain"><div class="ccontent">
<?php tampil_flash(); ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;flex-wrap:wrap;gap:12px;">
    <div><h2 style="font-size:22px;font-weight:800;">📋 Booking Saya</h2><p style="color:#64748b;font-size:14px;">Kelola semua pemesanan unit rumah Anda</p></div>
    <a href="../guest/katalog.php" class="cbtn cbtn-primary">+ Cari Properti</a>
</div>
<div class="cpanel">
    <div class="ctbl-wrap"><table class="ctbl">
        <thead><tr><th>Properti / Unit</th><th>Tipe & Harga</th><th>Tgl Booking</th><th>Booking Fee</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php if(empty($bookings)): ?>
            <tr><td colspan="6"><div class="cempty"><div class="ico">📋</div><h4>Belum ada booking</h4><p>Cari dan pesan unit rumah impian Anda sekarang</p><br><a href="../guest/katalog.php" class="cbtn cbtn-primary">Lihat Katalog</a></div></td></tr>
        <?php else: foreach($bookings as $b): ?>
            <tr>
                <td><b><?= htmlspecialchars($b['nama_perumahan']) ?></b><br><small style="color:#2563eb;font-weight:700;">Blok <?= htmlspecialchars($b['blok'].'-'.$b['kode_unit']) ?></small></td>
                <td><?= htmlspecialchars($b['nama_tipe']) ?><br><span style="font-weight:700;color:#10b981;"><?= format_rupiah($b['harga']) ?></span></td>
                <td><?= format_tanggal($b['tanggal_booking']) ?></td>
                <td><?= format_rupiah($b['booking_fee']??0) ?></td>
                <td><?= badge_booking($b['status_booking']) ?></td>
                <td>
                    <?php if($b['status_booking']==='menunggu' && empty($b['bukti_bayar'])): ?>
                        <a href="upload_pembayaran.php?id=<?= $b['id_booking'] ?>" class="cbtn cbtn-primary cbtn-sm">💰 Bayar</a>
                    <?php elseif($b['status_booking']==='menunggu' && !empty($b['bukti_bayar'])): ?>
                        <span style="font-size:12px;color:#92400e;font-weight:700;">⏳ Menunggu verifikasi</span>
                    <?php elseif($b['status_booking']==='dikonfirmasi'): ?>
                        <?php $kpr=$db->prepare("SELECT id_pengajuan FROM pengajuan_kpr WHERE id_rumah=? AND id_user=?");$kpr->execute([$b['id_rumah'],$id]);$kex=$kpr->fetchColumn(); ?>
                        <?php if(!$kex): ?>
                            <a href="pengajuan_kpr.php?id_rumah=<?= $b['id_rumah'] ?>" class="cbtn cbtn-primary cbtn-sm">🚀 Ajukan KPR</a>
                        <?php else: ?>
                            <a href="status_kpr.php?id=<?= $kex ?>" class="cbtn cbtn-outline cbtn-sm">📊 Lacak KPR</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="font-size:12px;color:#94a3b8;">-</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</div></div>
<script src="../assets/js/script.js"></script></body></html>
