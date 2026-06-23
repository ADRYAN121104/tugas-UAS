<?php
// customer/booking_saya.php
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';

$id = id_user();
$stmt = $db->prepare("SELECT b.*,p.nama_perumahan,p.alamat,p.maps_link,t.nama_tipe,t.harga,r.blok,r.kode_unit,r.id_rumah,
    pay.id_pembayaran,pay.jumlah_bayar,pay.status_verifikasi,pay.bukti_bayar
    FROM booking b JOIN rumah r ON b.id_rumah=r.id_rumah JOIN perumahan p ON r.id_perumahan=p.id_perumahan
    JOIN tipe_rumah t ON r.id_tipe=t.id_tipe LEFT JOIN pembayaran pay ON b.id_booking=pay.id_booking
    WHERE b.id_user=? ORDER BY b.id_booking DESC");
$stmt->execute([$id]); $bookings=$stmt->fetchAll();

$page_title = 'Booking Saya - KPR Perumahan';
require_once '../includes/header.php';
?>
<main class="container" style="padding:40px 24px 60px;">
    <?php tampil_flash(); ?>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:28px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 class="section-title">📋 Booking Saya</h1>
            <p class="section-sub">Kelola semua pemesanan unit rumah Anda</p>
        </div>
        <a href="../guest/katalog.php" class="btn btn-primary">+ Cari Properti Lain</a>
    </div>

    <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
        <div class="tabel-wrap">
            <table class="tabel">
                <thead>
                    <tr>
                        <th>Properti / Unit</th>
                        <th>Lokasi & Peta</th>
                        <th>Tipe & Harga</th>
                        <th>Tgl Booking</th>
                        <th>Booking Fee</th>
                        <th>Status Booking</th>
                        <th style="text-align:center;">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(empty($bookings)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;padding:40px;color:#94a3b8;">
                            <div style="font-size:40px; margin-bottom:12px;">📋</div>
                            <h4 style="color:#475569;">Belum ada booking terdaftar</h4>
                            <p style="margin-bottom:16px;">Cari dan pesan unit rumah impian Anda sekarang</p>
                            <a href="../guest/katalog.php" class="btn btn-primary btn-sm">Lihat Katalog</a>
                        </td>
                    </tr>
                <?php else: foreach($bookings as $b): ?>
                    <tr>
                        <td>
                            <b><?= htmlspecialchars($b['nama_perumahan']) ?></b><br>
                            <small style="color:#2563eb;font-weight:700;">Blok <?= htmlspecialchars($b['blok'].'-'.$b['kode_unit']) ?></small>
                        </td>
                        <td>
                            <small style="color:#64748b; display:block; margin-bottom:4px; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($b['alamat']) ?></small>
                            <?php if ($b['maps_link']): ?>
                                <a href="<?= htmlspecialchars($b['maps_link']) ?>" target="_blank" class="btn btn-white btn-sm" style="padding:2px 8px; font-size:11px; border:1px solid #cbd5e1; display:inline-flex; align-items:center; gap:4px;">🗺️ Buka Maps</a>
                            <?php else: ?>
                                <span style="color:#94a3b8; font-size:11px;">Tidak ada link peta</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($b['nama_tipe']) ?><br>
                            <span style="font-weight:700;color:#10b981;"><?= format_rupiah($b['harga']) ?></span>
                        </td>
                        <td><?= format_tanggal($b['tanggal_booking']) ?></td>
                        <td><?= format_rupiah($b['booking_fee']??0) ?></td>
                        <td><?= badge_booking($b['status_booking']) ?></td>
                        <td style="text-align:center;">
                            <?php if($b['status_booking']==='menunggu' && empty($b['bukti_bayar'])): ?>
                                <a href="upload_pembayaran.php?id=<?= $b['id_booking'] ?>" class="btn btn-primary btn-sm">💰 Bayar</a>
                            <?php elseif($b['status_booking']==='menunggu' && !empty($b['bukti_bayar'])): ?>
                                <span style="font-size:12px;color:#92400e;font-weight:700;background:#fef3c7;padding:4px 10px;border-radius:20px;">⏳ Verifikasi</span>
                            <?php elseif($b['status_booking']==='dikonfirmasi'): ?>
                                <?php $kpr=$db->prepare("SELECT id_pengajuan FROM pengajuan_kpr WHERE id_rumah=? AND id_user=?");$kpr->execute([$b['id_rumah'],$id]);$kex=$kpr->fetchColumn(); ?>
                                <?php if(!$kex): ?>
                                    <a href="pengajuan_kpr.php?id_rumah=<?= $b['id_rumah'] ?>" class="btn btn-primary btn-sm">🚀 Ajukan KPR</a>
                                <?php else: ?>
                                    <a href="status_kpr.php?id=<?= $kex ?>" class="btn btn-outline btn-sm">📊 Lacak KPR</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color:#94a3b8;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?php require_once '../includes/footer.php'; ?>
