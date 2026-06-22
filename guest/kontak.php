<?php $page_title='Kontak - KPR Perumahan'; require_once '../includes/header.php';
$sukses = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $sukses = 'Pesan berhasil dikirim! Tim kami akan segera menghubungi Anda.';
}
?>
<main class="container" style="padding:60px 24px;">
    <div style="text-align:center;margin-bottom:40px;">
        <h1 class="section-title">📞 Hubungi Kami</h1>
        <p class="section-sub">Ada pertanyaan? Tim kami siap membantu Anda</p>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:40px;" id="kontak-grid">
        <div>
            <div style="background:#fff;border-radius:16px;padding:28px;border:1px solid #e2e8f0;margin-bottom:20px;">
                <h3 style="font-weight:800;margin-bottom:20px;">Informasi Kontak</h3>
                <?php $info=[['📞','Telepon','(021) 1234-5678'],['📱','WhatsApp','0812-3456-7890'],['📧','Email','info@rumahkpr.com'],['📍','Alamat','Jl. Raya Properti No. 1,<br>Jakarta Selatan 12345'],['⏰','Jam Operasional','Senin-Jumat: 08.00-17.00<br>Sabtu: 08.00-13.00']];
                foreach($info as $i): ?>
                <div style="display:flex;gap:14px;align-items:flex-start;margin-bottom:18px;">
                    <div style="width:42px;height:42px;background:#dbeafe;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;"><?= $i[0] ?></div>
                    <div><div style="font-size:12px;color:#94a3b8;font-weight:700;text-transform:uppercase;"><?= $i[1] ?></div><div style="font-weight:700;font-size:14px;margin-top:2px;"><?= $i[2] ?></div></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div style="background:#fff;border-radius:16px;padding:28px;border:1px solid #e2e8f0;">
            <h3 style="font-weight:800;margin-bottom:20px;">Kirim Pesan</h3>
            <?php if($sukses): ?><div class="alert alert-success"><?= $sukses ?></div><?php endif; ?>
            <form method="POST">
                <div class="form-group"><label>Nama Lengkap</label><input type="text" name="nama" class="form-control" placeholder="Nama Anda" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" placeholder="email@contoh.com" required></div>
                <div class="form-group"><label>Subjek</label><input type="text" name="subjek" class="form-control" placeholder="Perihal pesan" required></div>
                <div class="form-group"><label>Pesan</label><textarea name="pesan" class="form-control" placeholder="Tulis pesan Anda..." rows="5" required></textarea></div>
                <button type="submit" class="btn btn-primary btn-block btn-lg" style="justify-content:center;">📤 Kirim Pesan</button>
            </form>
        </div>
    </div>
</main>
<style>@media(max-width:768px){#kontak-grid{grid-template-columns:1fr!important;}}</style>
<?php require_once '../includes/footer.php'; ?>
