<?php
// customer/riwayat_booking.php
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';

$id = id_user();
$stmt = $db->prepare("
    SELECT b.*, p.nama_perumahan, p.alamat, p.maps_link, t.nama_tipe, t.harga, r.blok, r.kode_unit, pay.status_verifikasi, pay.jumlah_bayar 
    FROM booking b 
    JOIN rumah r ON b.id_rumah=r.id_rumah 
    JOIN perumahan p ON r.id_perumahan=p.id_perumahan 
    JOIN tipe_rumah t ON r.id_tipe=t.id_tipe 
    LEFT JOIN pembayaran pay ON b.id_booking=pay.id_booking 
    WHERE b.id_user=? 
    ORDER BY b.tanggal_booking DESC
");
$stmt->execute([$id]); 
$list = $stmt->fetchAll();

$page_title = 'Riwayat Booking - KPR Perumahan';
require_once '../includes/header.php';
?>
<main class="container" style="padding:40px 24px 60px;">
    <?php tampil_flash(); ?>
    <div style="margin-bottom:28px;">
        <h1 class="section-title">🕐 Riwayat Booking</h1>
        <p class="section-sub">Semua riwayat pemesanan unit rumah Anda</p>
    </div>

    <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
        <div class="tabel-wrap">
            <table class="tabel">
                <thead>
                    <tr>
                        <th>Tanggal Booking</th>
                        <th>Properti / Unit</th>
                        <th>Lokasi & Peta</th>
                        <th>Tipe & Harga</th>
                        <th>Booking Fee</th>
                        <th>Status Booking</th>
                        <th>Status Verifikasi</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(empty($list)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;padding:40px;color:#94a3b8;">
                            <div style="font-size:40px; margin-bottom:12px;">🕐</div>
                            <h4 style="color:#475569;">Belum ada riwayat booking</h4>
                            <p style="margin-bottom:16px;">Semua riwayat pemesanan Anda akan tampil di sini.</p>
                            <a href="../guest/katalog.php" class="btn btn-primary btn-sm">Mulai Cari Rumah</a>
                        </td>
                    </tr>
                <?php else: foreach($list as $b): ?>
                    <tr>
                        <td><?= format_tanggal($b['tanggal_booking']) ?></td>
                        <td>
                            <b><?= htmlspecialchars($b['nama_perumahan']) ?></b><br>
                            <small style="color:#2563eb;font-weight:700;">Blok <?= htmlspecialchars($b['blok'].'-'.$b['kode_unit']) ?></small>
                        </td>
                        <td>
                            <small style="color:#64748b; display:block; margin-bottom:4px; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($b['alamat']) ?></small>
                            <?php if ($b['maps_link']): ?>
                                <a href="<?= htmlspecialchars($b['maps_link']) ?>" target="_blank" class="btn btn-white btn-sm" style="padding:2px 8px; font-size:11px; border:1px solid #cbd5e1; display:inline-flex; align-items:center; gap:4px;">🗺️ Buka Peta</a>
                            <?php else: ?>
                                <span style="color:#94a3b8; font-size:11px;">Tidak ada link</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($b['nama_tipe']) ?><br>
                            <span style="font-weight:700;color:#10b981;font-size:13px;"><?= format_rupiah($b['harga']) ?></span>
                        </td>
                        <td><?= format_rupiah($b['booking_fee']??0) ?></td>
                        <td><?= badge_booking($b['status_booking']) ?></td>
                        <td><?= $b['status_verifikasi'] ? badge_pembayaran($b['status_verifikasi']) : '<span style="color:#94a3b8;font-size:12px; font-weight:600;">Belum bayar</span>' ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<script src="../assets/js/script.js"></script>
<?php require_once '../includes/footer.php'; ?>
