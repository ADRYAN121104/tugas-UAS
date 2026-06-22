<?php
// register.php
require_once 'config/koneksi.php';
require_once 'config/session.php';
require_once 'config/auth.php';

if (sudah_login()) { header('Location: customer/dashboard.php'); exit; }

$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama    = trim($_POST['nama_lengkap'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $pass    = trim($_POST['password'] ?? '');
    $pass2   = trim($_POST['konfirmasi'] ?? '');
    $no_hp   = trim($_POST['no_hp'] ?? '');

    if (!$nama || !$email || !$pass || !$no_hp) {
        $error = 'Semua field wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif ($pass !== $pass2) {
        $error = 'Password dan konfirmasi tidak cocok.';
    } elseif (strlen($pass) < 4) {
        $error = 'Password minimal 4 karakter.';
    } else {
        $cek = $db->prepare("SELECT id_user FROM users WHERE email=?");
        $cek->execute([$email]);
        if ($cek->fetch()) {
            $error = 'Email sudah terdaftar.';
        } else {
            $stmt = $db->prepare("INSERT INTO users(nama_lengkap,email,password,no_hp,role) VALUES(?,?,?,?,'customer')");
            $stmt->execute([$nama, $email, $pass, $no_hp]);
            set_flash('sukses', 'Pendaftaran berhasil! Silakan login.');
            header('Location: login.php'); exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Daftar Akun - Sistem KPR Perumahan</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card" style="max-width:500px;">
        <div style="text-align:center;margin-bottom:24px;">
            <div style="width:60px;height:60px;background:linear-gradient(135deg,#10b981,#2563eb);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 14px;">📝</div>
            <h2>Buat Akun Baru</h2>
            <p>Daftar sebagai customer untuk mulai booking</p>
        </div>
        <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="nama_lengkap" class="form-control" placeholder="Nama lengkap Anda" value="<?= htmlspecialchars($_POST['nama_lengkap']??'') ?>" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" placeholder="email@contoh.com" value="<?= htmlspecialchars($_POST['email']??'') ?>" required>
                </div>
                <div class="form-group">
                    <label>No. HP / WhatsApp</label>
                    <input type="text" name="no_hp" class="form-control" placeholder="08xxxxxxxxxx" value="<?= htmlspecialchars($_POST['no_hp']??'') ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Min. 4 karakter" required>
                </div>
                <div class="form-group">
                    <label>Konfirmasi Password</label>
                    <input type="password" name="konfirmasi" class="form-control" placeholder="Ulangi password" required>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;">Daftar Sekarang</button>
        </form>
        <div style="text-align:center;margin-top:16px;font-size:14px;color:#94a3b8;">
            Sudah punya akun? <a href="login.php" style="font-weight:700;">Masuk di sini</a>
        </div>
    </div>
</div>
</body>
</html>
