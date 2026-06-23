<?php
// login.php
require_once 'config/koneksi.php';
require_once 'config/session.php';
require_once 'config/auth.php';

if (isset($_SESSION['admin']['user_id'])) {
    header('Location: admin/dashboard.php');
    exit;
}
if (isset($_SESSION['customer']['user_id'])) {
    header('Location: customer/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi.';
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && $password === $user['password']) {
            if (in_array($user['role'], ['admin', 'marketing'])) {
                $_SESSION['admin']['user_id']      = $user['id_user'];
                $_SESSION['admin']['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['admin']['email']        = $user['email'];
                $_SESSION['admin']['role']         = $user['role'];
                set_flash('sukses', 'Selamat datang, ' . $user['nama_lengkap'] . '!');
                header('Location: admin/dashboard.php');
            } else {
                $_SESSION['customer']['user_id']      = $user['id_user'];
                $_SESSION['customer']['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['customer']['email']        = $user['email'];
                $_SESSION['customer']['role']         = $user['role'];
                set_flash('sukses', 'Selamat datang, ' . $user['nama_lengkap'] . '!');
                header('Location: customer/booking_saya.php');
            }
            exit;
        } else {
            $error = 'Email atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Login - Sistem KPR Perumahan</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <div style="text-align:center;margin-bottom:24px;">
            <div style="width:60px;height:60px;background:linear-gradient(135deg,#1e3a8a,#2563eb);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 14px;">🏠</div>
            <h2>Masuk ke Akun</h2>
            <p>Sistem KPR Perumahan</p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" placeholder="email@contoh.com" value="<?= htmlspecialchars($_POST['email']??'') ?>" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg" style="width:100%;justify-content:center;">Masuk Sekarang</button>
        </form>
        <div style="text-align:center;margin-top:18px;font-size:14px;color:#94a3b8;">
            Belum punya akun? <a href="register.php" style="font-weight:700;">Daftar di sini</a>
        </div>
        <div style="text-align:center;margin-top:10px;font-size:13px;color:#94a3b8;">
            <a href="index.php">← Kembali ke Beranda</a>
        </div>
        <div style="margin-top:24px;padding:14px;background:#f8fafc;border-radius:8px;font-size:12px;color:#64748b;">
            <b>Demo Login:</b><br>
            Admin: admin@kpr.com / 1234<br>
            Customer: budi@gmail.com / 1234
        </div>
    </div>
</div>
<script src="assets/js/script.js"></script>
</body>
</html>
