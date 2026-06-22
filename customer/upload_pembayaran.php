<?php
// customer/upload_pembayaran.php
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';
require_once '../includes/sidebar_customer.php';

$id_booking = (int)($_GET['id'] ?? 0);
$id_user = id_user();

// Validasi booking milik user ini
$stmt = $db->prepare("SELECT b.*,p.nama_perumahan,t.nama_tipe,t.harga,r.blok,r.kode_unit FROM booking b JOIN rumah r ON b.id_rumah=r.id_rumah JOIN perumahan p ON r.id_perumahan=p.id_perumahan JOIN tipe_rumah t ON r.id_tipe=t.id_tipe WHERE b.id_booking=? AND b.id_user=?");
$stmt->execute([$id_booking, $id_user]);
$booking = $stmt->fetch();
if (!$booking) { set_flash('gagal','Booking tidak ditemukan.'); header('Location: booking_saya.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $jumlah = (float)str_replace(['.','Rp',' '],'',$_POST['jumlah_bayar']??'0');
    if ($jumlah <= 0) { $error = 'Jumlah bayar tidak valid.'; }
    elseif ($jumlah < $booking['booking_fee']) { 
        $error = 'Jumlah pembayaran kurang dari Booking Fee yang ditentukan (minimal ' . format_rupiah($booking['booking_fee']) . ').'; 
    }
    elseif (empty($_FILES['bukti_bayar']['name'])) { $error = 'Bukti pembayaran wajib diupload.'; }
    else {
        $upload = upload_file($_FILES['bukti_bayar'], '../uploads/bukti_bayar');
        if (!$upload['ok']) { $error = $upload['pesan']; }
        else {
            // Hapus pembayaran lama jika ada
            $db->prepare("DELETE FROM pembayaran WHERE id_booking=?")->execute([$id_booking]);
            $ins = $db->prepare("INSERT INTO pembayaran(id_booking,tanggal_bayar,jumlah_bayar,bukti_bayar,status_verifikasi) VALUES(?,NOW(),?,?,'pending')");
            $ins->execute([$id_booking, $jumlah, $upload['nama']]);
            set_flash('sukses','Bukti pembayaran berhasil diupload! Menunggu verifikasi admin.');
            header('Location: pembayaran.php'); exit;
        }
    }
}
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Upload Pembayaran</title>
<link rel="stylesheet" href="../assets/css/customer.css"></head><body>
<?php sidebar_customer('pembayaran'); ?>
<div class="cmain"><div class="ccontent">
    <div style="margin-bottom:22px;"><h2 style="font-size:22px;font-weight:800;">💳 Upload Bukti Pembayaran</h2></div>
    <div style="display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start;" id="upload-grid">
        <div class="cpanel">
            <div class="cpanel-header"><h3>Form Upload Pembayaran</h3></div>
            <div class="cpanel-body">
                <?php if($error): ?><div class="calert calert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
                <form method="POST" enctype="multipart/form-data">
                    <div class="cform-group">
                        <label>Jumlah Pembayaran (Rp)</label>
                        <input type="text" name="jumlah_bayar" class="cform-control format-angka" placeholder="<?= number_format($booking['booking_fee']??2000000,0,',','.') ?>" required>
                        <small style="color:#94a3b8;font-size:12px;">Booking fee: <?= format_rupiah($booking['booking_fee']??2000000) ?></small>
                    </div>
                    <div class="cform-group">
                        <label>Bukti Transfer (JPG/PNG/PDF, maks 5MB)</label>
                        <input type="file" name="bukti_bayar" class="cform-control" accept=".jpg,.jpeg,.png,.pdf" data-preview="prevImg" required>
                        <img id="prevImg" style="max-width:100%;border-radius:8px;margin-top:10px;display:none;">
                    </div>
                    <div style="display:flex;gap:10px;">
                        <a href="booking_saya.php" class="cbtn cbtn-gray">← Batal</a>
                        <button type="submit" class="cbtn cbtn-primary">📤 Upload Pembayaran</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="cpanel">
            <div class="cpanel-header"><h3>Info Booking</h3></div>
            <div class="cpanel-body">
                <table style="width:100%;font-size:13.5px;">
                    <tr><td style="padding:8px 0;color:#64748b;">Properti</td><td style="font-weight:700;padding:8px 0;"><?= htmlspecialchars($booking['nama_perumahan']) ?></td></tr>
                    <tr><td style="padding:8px 0;color:#64748b;">Blok / Unit</td><td style="font-weight:700;padding:8px 0;"><?= htmlspecialchars($booking['blok'].'-'.$booking['kode_unit']) ?></td></tr>
                    <tr><td style="padding:8px 0;color:#64748b;">Tipe</td><td style="font-weight:700;padding:8px 0;"><?= htmlspecialchars($booking['nama_tipe']) ?></td></tr>
                    <tr><td style="padding:8px 0;color:#64748b;">Harga</td><td style="font-weight:700;padding:8px 0;color:#2563eb;"><?= format_rupiah($booking['harga']) ?></td></tr>
                    <tr><td style="padding:8px 0;color:#64748b;">Tgl Booking</td><td style="font-weight:700;padding:8px 0;"><?= format_tanggal($booking['tanggal_booking']) ?></td></tr>
                    <tr><td style="padding:8px 0;color:#64748b;">Status</td><td style="padding:8px 0;"><?= badge_booking($booking['status_booking']) ?></td></tr>
                </table>
                <div style="margin-top:16px;padding:14px;background:#f8fafc;border-radius:8px;font-size:13px;border:1px solid #e2e8f0;color:#334155;">
                    <b>🏦 Rekening Transfer Developer:</b><br>
                    Silakan transfer Booking Fee sebesar <b><?= format_rupiah($booking['booking_fee'] ?? 2000000) ?></b> ke salah satu rekening resmi berikut:<br><br>
                    <b>Bank BTN (Utama):</b><br>
                    No. Rek: <code style="font-size:14px; font-weight:700; color:#2563eb;">1092-8822-7711</code><br>
                    A.n: PT Sinar KPR Indonesia<br><br>
                    <b>Bank Mandiri:</b><br>
                    No. Rek: <code style="font-size:14px; font-weight:700; color:#2563eb;">157-000-999-888</code><br>
                    A.n: PT Sinar KPR Indonesia<br><br>
                    <b>Bank BCA:</b><br>
                    No. Rek: <code style="font-size:14px; font-weight:700; color:#2563eb;">012-345-6789</code><br>
                    A.n: PT Sinar KPR Indonesia<br><br>
                    <hr style="border:0; border-top:1px solid #e2e8f0; margin:10px 0;">
                    <b>⚠️ Penting:</b><br>
                    - Cantumkan berita transfer: <b>#BKN-<?= $booking['id_booking'] ?></b>.<br>
                    - Simpan bukti transfer dalam format gambar/PDF untuk diunggah.
                </div>
            </div>
        </div>
    </div>
</div></div>
<script src="../assets/js/script.js"></script>
<style>@media(max-width:768px){#upload-grid{grid-template-columns:1fr!important;}}</style>
</body></html>
