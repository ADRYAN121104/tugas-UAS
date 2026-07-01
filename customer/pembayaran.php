<?php
// customer/pembayaran.php
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';

if (isset($_GET['ajax']) && $_GET['ajax'] === 'check') {
    header('Content-Type: application/json');
    $id = id_user();
    $status_list = $db->prepare("SELECT pay.id_pembayaran, pay.status_verifikasi FROM pembayaran pay JOIN booking b ON pay.id_booking = b.id_booking WHERE b.id_user = ?");
    $status_list->execute([$id]);
    echo json_encode($status_list->fetchAll(PDO::FETCH_KEY_PAIR));
    exit;
}

require_once '../config/functions.php';

$id = id_user();
$stmt = $db->prepare("SELECT pay.*,b.tanggal_booking,b.booking_fee,b.status_booking,p.nama_perumahan,p.alamat,p.maps_link,r.blok,r.kode_unit,r.nama_tipe
    FROM pembayaran pay JOIN booking b ON pay.id_booking=b.id_booking JOIN rumah r ON b.id_rumah=r.id_rumah
    JOIN perumahan p ON r.id_perumahan=p.id_perumahan
    WHERE b.id_user=? ORDER BY pay.tanggal_bayar DESC");
$stmt->execute([$id]); $list=$stmt->fetchAll();

$page_title = 'Riwayat Pembayaran - KPR Perumahan';
require_once '../includes/header.php';
?>
<main class="container" style="padding:40px 24px 60px;">
    <?php tampil_flash(); ?>
    <div style="margin-bottom:28px;">
        <h1 class="section-title">💰 Riwayat Pembayaran</h1>
        <p class="section-sub">Semua riwayat pembayaran booking fee Anda</p>
    </div>

    <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
        <div class="tabel-wrap">
            <table class="tabel">
                <thead>
                    <tr>
                        <th>Properti / Unit</th>
                        <th>Lokasi & Peta</th>
                        <th>Tanggal Bayar</th>
                        <th>Jumlah Pembayaran</th>
                        <th style="text-align:center;">Bukti Transfer</th>
                        <th>Status Verifikasi</th>
                        <th style="text-align:center;">Tindakan</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(empty($list)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;padding:40px;color:#94a3b8;">
                            <div style="font-size:40px; margin-bottom:12px;">💰</div>
                            <h4 style="color:#475569;">Belum ada riwayat pembayaran</h4>
                            <p style="margin-bottom:16px;">Lakukan booking dan bayar booking fee untuk memulai pemesanan unit.</p>
                            <a href="booking_saya.php" class="btn btn-primary btn-sm">Lihat Booking Saya</a>
                        </td>
                    </tr>
                <?php else: foreach($list as $p): ?>
                    <tr>
                        <td>
                            <b><?= htmlspecialchars($p['nama_perumahan']) ?></b><br>
                            <small style="color:#2563eb;font-weight:700;">Blok <?= htmlspecialchars($p['blok'].'-'.$p['kode_unit']) ?></small>
                        </td>
                        <td>
                            <small style="color:#64748b; display:block; margin-bottom:4px; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($p['alamat']) ?></small>
                            <?php if ($p['maps_link']): ?>
                                <a href="<?= htmlspecialchars($p['maps_link']) ?>" target="_blank" class="btn btn-white btn-sm" style="padding:2px 8px; font-size:11px; border:1px solid #cbd5e1; display:inline-flex; align-items:center; gap:4px;">🗺️ Buka Peta</a>
                            <?php else: ?>
                                <span style="color:#94a3b8; font-size:11px;">Tidak ada link</span>
                            <?php endif; ?>
                        </td>
                        <td><?= format_datetime($p['tanggal_bayar']) ?></td>
                        <td style="font-weight:700;color:#2563eb;"><?= format_rupiah($p['jumlah_bayar']) ?></td>
                        <td style="text-align:center;">
                            <?php if($p['bukti_bayar']): ?>
                                <a href="../uploads/bukti_bayar/<?= htmlspecialchars($p['bukti_bayar']) ?>" target="_blank" class="btn btn-white btn-sm" style="padding:4px 10px; border:1px solid #cbd5e1;">📎 Lihat Bukti</a>
                            <?php else: ?>
                                <span style="color:#94a3b8;">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= badge_pembayaran($p['status_verifikasi']) ?></td>
                        <td style="text-align:center;">
                            <?php if($p['status_verifikasi']==='ditolak'): ?>
                                <a href="upload_pembayaran.php?id=<?= $p['id_booking'] ?>" class="btn btn-primary btn-sm">Upload Ulang</a>
                            <?php else: ?>
                                <span style="color:#94a3b8; font-size:12px;">Selesai</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<?php
$stmt_stat = $db->prepare("SELECT pay.id_pembayaran, pay.status_verifikasi FROM pembayaran pay JOIN booking b ON pay.id_booking = b.id_booking WHERE b.id_user = ?");
$stmt_stat->execute([$id]);
$initial_statuses = $stmt_stat->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<script src="../assets/js/script.js"></script>
<script>
    let initialStatuses = <?= json_encode($initial_statuses) ?>;
    setInterval(() => {
        fetch('pembayaran.php?ajax=check')
            .then(res => res.json())
            .then(data => {
                let changed = false;
                for (let key in data) {
                    if (data[key] !== initialStatuses[key]) {
                        changed = true;
                        break;
                    }
                }
                if (Object.keys(data).length !== Object.keys(initialStatuses).length) {
                    changed = true;
                }
                if (changed) {
                    window.location.reload();
                }
            })
            .catch(err => console.error(err));
    }, 5000);
</script>
<?php require_once '../includes/footer.php'; ?>
