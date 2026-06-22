<?php
// customer/dashboard.php
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';
require_once '../includes/sidebar_customer.php';
$id = id_user();
$total_booking  = $db->prepare("SELECT COUNT(*) FROM booking WHERE id_user=?"); $total_booking->execute([$id]); $total_booking=$total_booking->fetchColumn();
$total_kpr      = $db->prepare("SELECT COUNT(*) FROM pengajuan_kpr WHERE id_user=?"); $total_kpr->execute([$id]); $total_kpr=$total_kpr->fetchColumn();
$booking_aktif  = $db->prepare("SELECT COUNT(*) FROM booking WHERE id_user=? AND status_booking='menunggu'"); $booking_aktif->execute([$id]); $booking_aktif=$booking_aktif->fetchColumn();
$kpr_disetujui  = $db->prepare("SELECT COUNT(*) FROM pengajuan_kpr WHERE id_user=? AND status_pengajuan='disetujui'"); $kpr_disetujui->execute([$id]); $kpr_disetujui=$kpr_disetujui->fetchColumn();
$booking_terbaru= $db->prepare("SELECT b.*,p.nama_perumahan,t.nama_tipe,t.harga,r.blok,r.kode_unit FROM booking b JOIN rumah r ON b.id_rumah=r.id_rumah JOIN perumahan p ON r.id_perumahan=p.id_perumahan JOIN tipe_rumah t ON r.id_tipe=t.id_tipe WHERE b.id_user=? ORDER BY b.id_booking DESC LIMIT 5");
$booking_terbaru->execute([$id]); $bookings=$booking_terbaru->fetchAll();
$kpr_terbaru = $db->prepare("SELECT pk.*,p.nama_perumahan,b.nama_bank,r.blok,r.kode_unit FROM pengajuan_kpr pk JOIN rumah r ON pk.id_rumah=r.id_rumah JOIN perumahan p ON r.id_perumahan=p.id_perumahan JOIN bank b ON pk.id_bank=b.id_bank WHERE pk.id_user=? ORDER BY pk.id_pengajuan DESC LIMIT 3");
$kpr_terbaru->execute([$id]); $kprs=$kpr_terbaru->fetchAll();
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard - Customer KPR</title>
<link rel="stylesheet" href="../assets/css/customer.css"></head>
<body>
<?php sidebar_customer('dashboard'); ?>
<div class="cmain"><div class="ccontent">
<?php tampil_flash(); ?>
<div class="welcome-banner">
    <h2>👋 Selamat Datang, <?= htmlspecialchars(nama_user()) ?>!</h2>
    <p>Pantau semua aktivitas booking dan pengajuan KPR Anda di sini.</p>
</div>
<div class="cstat-grid">
    <div class="cstat"><div class="angka" style="color:#2563eb;"><?= $total_booking ?></div><div class="label">Total Booking</div></div>
    <div class="cstat"><div class="angka" style="color:#f59e0b;"><?= $booking_aktif ?></div><div class="label">Booking Aktif</div></div>
    <div class="cstat"><div class="angka" style="color:#6366f1;"><?= $total_kpr ?></div><div class="label">Pengajuan KPR</div></div>
    <div class="cstat"><div class="angka" style="color:#10b981;"><?= $kpr_disetujui ?></div><div class="label">KPR Disetujui</div></div>
</div>

<div class="cpanel">
    <div class="cpanel-header"><h3>📋 Booking Terbaru</h3><a href="booking_saya.php" class="cbtn cbtn-outline cbtn-sm">Lihat Semua</a></div>
    <div class="ctbl-wrap">
    <table class="ctbl">
        <thead><tr><th>Properti</th><th>Tipe</th><th>Tgl Booking</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php if(empty($bookings)): ?>
            <tr><td colspan="5" style="text-align:center;padding:30px;color:#94a3b8;">Belum ada booking. <a href="../guest/katalog.php">Cari properti sekarang</a></td></tr>
        <?php else: foreach($bookings as $b): ?>
            <tr>
                <td><b><?= htmlspecialchars($b['nama_perumahan']) ?></b><br><small style="color:#2563eb;">Blok <?= htmlspecialchars($b['blok'].'-'.$b['kode_unit']) ?></small></td>
                <td><?= htmlspecialchars($b['nama_tipe']) ?></td>
                <td><?= format_tanggal($b['tanggal_booking']) ?></td>
                <td><?= badge_booking($b['status_booking']) ?></td>
                <td><a href="booking_saya.php" class="cbtn cbtn-outline cbtn-sm">Detail</a></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="cpanel">
    <div class="cpanel-header"><h3>📝 Status KPR Terbaru</h3><a href="status_kpr.php" class="cbtn cbtn-outline cbtn-sm">Lihat Semua</a></div>
    <div class="ctbl-wrap">
    <table class="ctbl">
        <thead><tr><th>Properti</th><th>Bank</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php if(empty($kprs)): ?>
            <tr><td colspan="4" style="text-align:center;padding:30px;color:#94a3b8;">Belum ada pengajuan KPR.</td></tr>
        <?php else: foreach($kprs as $k): ?>
            <tr>
                <td><b><?= htmlspecialchars($k['nama_perumahan']) ?></b><br><small>Blok <?= htmlspecialchars($k['blok'].'-'.$k['kode_unit']) ?></small></td>
                <td><?= htmlspecialchars($k['nama_bank']) ?></td>
                <td><?= badge_kpr($k['status_pengajuan']) ?></td>
                <td><a href="status_kpr.php?id=<?= $k['id_pengajuan'] ?>" class="cbtn cbtn-outline cbtn-sm">Lacak</a></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</div>
</div></div>
<script src="../assets/js/script.js"></script>
</body></html>
