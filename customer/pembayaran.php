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
require_once '../includes/sidebar_customer.php';
$id = id_user();
$stmt = $db->prepare("SELECT pay.*,b.tanggal_booking,b.booking_fee,b.status_booking,p.nama_perumahan,r.blok,r.kode_unit,t.nama_tipe
    FROM pembayaran pay JOIN booking b ON pay.id_booking=b.id_booking JOIN rumah r ON b.id_rumah=r.id_rumah
    JOIN perumahan p ON r.id_perumahan=p.id_perumahan JOIN tipe_rumah t ON r.id_tipe=t.id_tipe
    WHERE b.id_user=? ORDER BY pay.tanggal_bayar DESC");
$stmt->execute([$id]); $list=$stmt->fetchAll();
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Riwayat Pembayaran</title>
<link rel="stylesheet" href="../assets/css/customer.css"></head><body>
<?php sidebar_customer('pembayaran'); ?>
<div class="cmain"><div class="ccontent">
<?php tampil_flash(); ?>
<div style="margin-bottom:22px;"><h2 style="font-size:22px;font-weight:800;">💰 Riwayat Pembayaran</h2><p style="color:#64748b;font-size:14px;">Semua riwayat pembayaran booking fee Anda</p></div>
<div class="cpanel">
    <div class="ctbl-wrap"><table class="ctbl">
        <thead><tr><th>Properti</th><th>Tgl Bayar</th><th>Jumlah</th><th>Bukti</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php if(empty($list)): ?>
            <tr><td colspan="6"><div class="cempty"><div class="ico">💰</div><h4>Belum ada pembayaran</h4><p>Lakukan booking dan bayar booking fee untuk memulai</p></div></td></tr>
        <?php else: foreach($list as $p): ?>
            <tr>
                <td><b><?= htmlspecialchars($p['nama_perumahan']) ?></b><br><small>Blok <?= htmlspecialchars($p['blok'].'-'.$p['kode_unit']) ?></small></td>
                <td><?= format_datetime($p['tanggal_bayar']) ?></td>
                <td style="font-weight:700;color:#2563eb;"><?= format_rupiah($p['jumlah_bayar']) ?></td>
                <td><?php if($p['bukti_bayar']): ?><a href="../uploads/bukti_bayar/<?= htmlspecialchars($p['bukti_bayar']) ?>" target="_blank" class="cbtn cbtn-outline cbtn-sm">📎 Lihat</a><?php else: ?><span style="color:#94a3b8;">-</span><?php endif; ?></td>
                <td><?= badge_pembayaran($p['status_verifikasi']) ?></td>
                <td><?php if($p['status_verifikasi']==='ditolak'): ?><a href="upload_pembayaran.php?id=<?= $p['id_booking'] ?>" class="cbtn cbtn-primary cbtn-sm">Upload Ulang</a><?php endif; ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table></div>
</div>
</div></div>
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
</script></body></html>
