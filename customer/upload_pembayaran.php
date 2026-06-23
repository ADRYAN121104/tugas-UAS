<?php
// customer/upload_pembayaran.php
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';

$id_booking = (int)($_GET['id'] ?? 0);
$id_user = id_user();

// Validasi booking milik user ini (tarik info perumahan & maps_link juga)
$stmt = $db->prepare("
    SELECT b.*, p.nama_perumahan, p.alamat, p.maps_link, t.nama_tipe, t.harga, r.blok, r.kode_unit 
    FROM booking b 
    JOIN rumah r ON b.id_rumah=r.id_rumah 
    JOIN perumahan p ON r.id_perumahan=p.id_perumahan 
    JOIN tipe_rumah t ON r.id_tipe=t.id_tipe 
    WHERE b.id_booking=? AND b.id_user=?
");
$stmt->execute([$id_booking, $id_user]);
$booking = $stmt->fetch();

if (!$booking) { 
    set_flash('gagal','Booking tidak ditemukan.'); 
    header('Location: booking_saya.php'); 
    exit; 
}

$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $jumlah = (float)str_replace(['.','Rp',' '],'',$_POST['jumlah_bayar']??'0');
    if ($jumlah <= 0) { 
        $error = 'Jumlah bayar tidak valid.'; 
    }
    elseif ($jumlah < $booking['booking_fee']) { 
        $error = 'Jumlah pembayaran kurang dari Booking Fee yang ditentukan (minimal ' . format_rupiah($booking['booking_fee']) . ').'; 
    }
    elseif (empty($_FILES['bukti_bayar']['name'])) { 
        $error = 'Bukti pembayaran wajib diupload.'; 
    }
    else {
        $upload = upload_file($_FILES['bukti_bayar'], '../uploads/bukti_bayar');
        if (!$upload['ok']) { 
            $error = $upload['pesan']; 
        }
        else {
            // Hapus pembayaran lama jika ada
            $db->prepare("DELETE FROM pembayaran WHERE id_booking=?")->execute([$id_booking]);
            $ins = $db->prepare("INSERT INTO pembayaran(id_booking,tanggal_bayar,jumlah_bayar,bukti_bayar,status_verifikasi) VALUES(?,NOW(),?,?,'pending')");
            $ins->execute([$id_booking, $jumlah, $upload['nama']]);
            set_flash('sukses','Bukti pembayaran berhasil diupload! Menunggu verifikasi admin.');
            header('Location: pembayaran.php'); 
            exit;
        }
    }
}

$page_title = 'Upload Pembayaran - KPR Perumahan';
require_once '../includes/header.php';
?>
<main class="container" style="padding:40px 24px 60px;">
    <?php tampil_flash(); ?>
    <div style="margin-bottom:24px;">
        <h1 class="section-title">💳 Upload Bukti Pembayaran</h1>
        <p class="section-sub">Silakan unggah bukti transfer pembayaran booking fee Anda</p>
    </div>

    <div style="display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start;" id="upload-grid">
        <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:24px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
            <h3 style="font-size:16px; font-weight:800; margin-bottom:18px; border-bottom:1px solid #f1f5f9; padding-bottom:10px;">Form Bukti Bayar</h3>
            <?php if($error): ?><div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:6px;">Jumlah Pembayaran (Rp)</label>
                    <input type="text" name="jumlah_bayar" class="form-control format-angka" placeholder="<?= number_format($booking['booking_fee']??2000000,0,',','.') ?>" required>
                    <small style="color:#94a3b8;font-size:12px;">Booking fee minimal: <?= format_rupiah($booking['booking_fee']??2000000) ?></small>
                </div>
                <div class="form-group">
                    <label style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:6px;">Bukti Transfer (JPG/PNG/PDF, maks 5MB)</label>
                    <input type="file" name="bukti_bayar" class="form-control" accept=".jpg,.jpeg,.png,.pdf" data-preview="prevImg" required>
                    <img id="prevImg" style="max-width:100%;border-radius:8px;margin-top:10px;display:none; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                </div>
                <div style="display:flex;gap:10px;margin-top:20px;">
                    <a href="booking_saya.php" class="btn btn-gray">← Batal</a>
                    <button type="submit" class="btn btn-primary">📤 Kirim Konfirmasi</button>
                </div>
            </form>
        </div>

        <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:24px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
            <h3 style="font-size:16px; font-weight:800; margin-bottom:18px; border-bottom:1px solid #f1f5f9; padding-bottom:10px;">Ringkasan Booking</h3>
            <table style="width:100%;font-size:13.5px; border-collapse:collapse;">
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:10px 0;color:#64748b;">Komplek</td><td style="font-weight:700;padding:10px 0; text-align:right;"><?= htmlspecialchars($booking['nama_perumahan']) ?></td></tr>
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:10px 0;color:#64748b;">Blok / Unit</td><td style="font-weight:700;padding:10px 0; text-align:right;"><?= htmlspecialchars($booking['blok'].'-'.$booking['kode_unit']) ?></td></tr>
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:10px 0;color:#64748b;">Alamat</td><td style="font-weight:700;padding:10px 0; text-align:right; font-size:12px; color:#475569; max-width:180px; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($booking['alamat']) ?></td></tr>
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:10px 0;color:#64748b;">Peta Lokasi</td><td style="padding:10px 0; text-align:right;">
                    <?php if ($booking['maps_link']): ?>
                        <a href="<?= htmlspecialchars($booking['maps_link']) ?>" target="_blank" class="btn btn-white btn-sm" style="padding:2px 8px; font-size:11px; border:1px solid #cbd5e1; display:inline-flex; align-items:center; gap:4px;">🗺️ Buka Peta</a>
                    <?php else: ?>
                        <span style="color:#94a3b8; font-size:11px;">Tidak ada link</span>
                    <?php endif; ?>
                </td></tr>
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:10px 0;color:#64748b;">Tipe Rumah</td><td style="font-weight:700;padding:10px 0; text-align:right;"><?= htmlspecialchars($booking['nama_tipe']) ?></td></tr>
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:10px 0;color:#64748b;">Harga Unit</td><td style="font-weight:700;padding:10px 0;color:#2563eb; text-align:right;"><?= format_rupiah($booking['harga']) ?></td></tr>
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:10px 0;color:#64748b;">Tgl Booking</td><td style="font-weight:700;padding:10px 0; text-align:right;"><?= format_tanggal($booking['tanggal_booking']) ?></td></tr>
                <tr><td style="padding:10px 0;color:#64748b;">Status</td><td style="padding:10px 0; text-align:right;"><?= badge_booking($booking['status_booking']) ?></td></tr>
            </table>

            <div style="margin-top:20px;padding:14px;background:#f8fafc;border-radius:8px;font-size:13px;border:1px solid #e2e8f0;color:#334155;">
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
</main>
<script src="../assets/js/script.js"></script>
<style>@media(max-width:768px){#upload-grid{grid-template-columns:1fr!important;}}</style>
<?php require_once '../includes/footer.php'; ?>
