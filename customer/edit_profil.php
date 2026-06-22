<?php
// customer/edit_profil.php
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';
require_once '../includes/sidebar_customer.php';
$id = id_user();
$user = $db->prepare("SELECT * FROM users WHERE id_user=?"); $user->execute([$id]); $user=$user->fetch();
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $nama = trim($_POST['nama_lengkap']);
    $hp   = trim($_POST['no_hp']);
    $pass = trim($_POST['password']);
    $pass2= trim($_POST['konfirmasi']);
    if (!$nama || !$hp) { $error='Nama dan HP wajib diisi.'; }
    elseif ($pass && $pass!==$pass2) { $error='Konfirmasi password tidak cocok.'; }
    else {
        if ($pass) $db->prepare("UPDATE users SET nama_lengkap=?,no_hp=?,password=? WHERE id_user=?")->execute([$nama,$hp,$pass,$id]);
        else $db->prepare("UPDATE users SET nama_lengkap=?,no_hp=? WHERE id_user=?")->execute([$nama,$hp,$id]);
        $_SESSION['customer']['nama_lengkap']=$nama;
        set_flash('sukses','Profil berhasil diperbarui!');
        header('Location: profil.php'); exit;
    }
}
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Edit Profil</title>
<link rel="stylesheet" href="../assets/css/customer.css"></head><body>
<?php sidebar_customer('profil'); ?>
<div class="cmain"><div class="ccontent">
    <div style="margin-bottom:22px;"><h2 style="font-size:22px;font-weight:800;">✏️ Edit Profil</h2></div>
    <div class="cpanel" style="max-width:560px;">
        <div class="cpanel-header"><h3>Perbarui Informasi Akun</h3></div>
        <div class="cpanel-body">
            <?php if($error): ?><div class="calert calert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="POST">
                <div class="cform-group"><label>Nama Lengkap</label><input type="text" name="nama_lengkap" class="cform-control" value="<?= htmlspecialchars($user['nama_lengkap']) ?>" required></div>
                <div class="cform-group"><label>Email</label><input type="email" class="cform-control" value="<?= htmlspecialchars($user['email']) ?>" readonly style="background:#f8fafc;color:#94a3b8;"></div>
                <div class="cform-group"><label>No. HP / WhatsApp</label><input type="text" name="no_hp" class="cform-control" value="<?= htmlspecialchars($user['no_hp']??'') ?>" required></div>
                <hr style="border:0;border-top:1px solid #f1f5f9;margin:20px 0;">
                <p style="font-size:13px;color:#94a3b8;margin-bottom:14px;">Kosongkan jika tidak ingin mengubah password.</p>
                <div class="cform-row">
                    <div class="cform-group"><label>Password Baru</label><input type="password" name="password" class="cform-control" placeholder="Password baru..."></div>
                    <div class="cform-group"><label>Konfirmasi Password</label><input type="password" name="konfirmasi" class="cform-control" placeholder="Ulangi password..."></div>
                </div>
                <div style="display:flex;gap:10px;"><a href="profil.php" class="cbtn cbtn-gray">← Batal</a><button type="submit" class="cbtn cbtn-primary">💾 Simpan Perubahan</button></div>
            </form>
        </div>
    </div>
</div></div>
<script src="../assets/js/script.js"></script>
</body></html>
