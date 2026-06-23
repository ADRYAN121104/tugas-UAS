<?php
// customer/edit_profil.php
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';

$id = id_user();
$user = $db->prepare("SELECT * FROM users WHERE id_user=?"); 
$user->execute([$id]); 
$user = $user->fetch();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama_lengkap']);
    $hp   = trim($_POST['no_hp']);
    $pass = trim($_POST['password']);
    $pass2= trim($_POST['konfirmasi']);
    
    if (!$nama || !$hp) { 
        $error = 'Nama dan HP wajib diisi.'; 
    }
    elseif ($pass && $pass !== $pass2) { 
        $error = 'Konfirmasi password tidak cocok.'; 
    }
    else {
        if ($pass) {
            $db->prepare("UPDATE users SET nama_lengkap=?, no_hp=?, password=? WHERE id_user=?")->execute([$nama, $hp, $pass, $id]);
        } else {
            $db->prepare("UPDATE users SET nama_lengkap=?, no_hp=? WHERE id_user=?")->execute([$nama, $hp, $id]);
        }
        $_SESSION['customer']['nama_lengkap'] = $nama;
        set_flash('sukses', 'Profil berhasil diperbarui!');
        header('Location: profil.php'); 
        exit;
    }
}

$page_title = 'Edit Profil - KPR Perumahan';
require_once '../includes/header.php';
?>
<main class="container" style="padding:40px 24px 60px;">
    <?php tampil_flash(); ?>
    <div style="margin-bottom:28px;">
        <h1 class="section-title">✏️ Edit Profil</h1>
        <p class="section-sub">Perbarui informasi pribadi dan keamanan akun Anda</p>
    </div>

    <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:24px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); max-width:600px;">
        <h3 style="font-size:16px; font-weight:800; margin-bottom:18px; border-bottom:1px solid #f1f5f9; padding-bottom:10px;">Perbarui Akun</h3>
        <?php if($error): ?><div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:6px;">Nama Lengkap</label>
                <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($user['nama_lengkap']) ?>" required>
            </div>
            
            <div class="form-group">
                <label style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:6px;">Alamat Email</label>
                <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" readonly style="background:#f8fafc;color:#94a3b8; border-color:#cbd5e1; cursor:not-allowed;">
            </div>
            
            <div class="form-group">
                <label style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:6px;">No. HP / WhatsApp</label>
                <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($user['no_hp']??'') ?>" required>
            </div>
            
            <hr style="border:0; border-top:1px solid #f1f5f9; margin:24px 0;">
            <p style="font-size:13px;color:#94a3b8;margin-bottom:14px;">*Kosongkan kolom password di bawah jika Anda tidak berniat untuk mengganti password.</p>
            
            <div class="form-row">
                <div class="form-group">
                    <label style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:6px;">Password Baru</label>
                    <input type="password" name="password" class="form-control" placeholder="Password baru...">
                </div>
                <div class="form-group">
                    <label style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:6px;">Konfirmasi Password</label>
                    <input type="password" name="konfirmasi" class="form-control" placeholder="Ulangi password...">
                </div>
            </div>
            
            <div style="display:flex;gap:10px; margin-top:20px;">
                <a href="profil.php" class="btn btn-gray">← Batal</a>
                <button type="submit" class="btn btn-primary">💾 Simpan Perubahan</button>
            </div>
        </form>
    </div>
</main>
<script src="../assets/js/script.js"></script>
<?php require_once '../includes/footer.php'; ?>
