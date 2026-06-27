<?php
// lupa_password.php — Reset password via verifikasi email+no HP
require_once 'config/koneksi.php';
require_once 'config/session.php';

$step    = 1; // 1=form, 2=reset password
$user_id = 0;
$msg     = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cari'])) {
        // Step 1: cek email + no HP
        $email = trim($_POST['email'] ?? '');
        $no_hp = trim($_POST['no_hp'] ?? '');
        $stmt  = $db->prepare("SELECT * FROM users WHERE email=? AND no_hp=?");
        $stmt->execute([$email, $no_hp]);
        $user  = $stmt->fetch();
        if ($user) {
            $_SESSION['reset_user_id'] = $user['id_user'];
            $_SESSION['reset_nama']    = $user['nama_lengkap'];
            $step    = 2;
            $user_id = $user['id_user'];
        } else {
            $error = 'Email atau nomor HP tidak cocok dalam sistem kami.';
        }
    } elseif (isset($_POST['reset'])) {
        // Step 2: ganti password
        $uid   = (int)($_SESSION['reset_user_id'] ?? 0);
        $pass  = $_POST['password_baru'] ?? '';
        $pass2 = $_POST['konfirmasi_baru'] ?? '';
        if (!$uid) {
            $error = 'Sesi tidak valid. Silakan ulangi.'; $step = 1;
        } elseif (strlen($pass) < 6) {
            $error = 'Password minimal 6 karakter.'; $step = 2; $user_id = $uid;
        } elseif ($pass !== $pass2) {
            $error = 'Password tidak cocok.'; $step = 2; $user_id = $uid;
        } else {
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET password=? WHERE id_user=?")->execute([$hashed, $uid]);
            unset($_SESSION['reset_user_id'], $_SESSION['reset_nama']);
            $_SESSION['flash_sukses'] = 'Password berhasil diubah! Silakan login.';
            header('Location: login.php'); exit;
        }
    }
} elseif (isset($_SESSION['reset_user_id'])) {
    $step    = 2;
    $user_id = $_SESSION['reset_user_id'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Lupa Password - KPR Perumahan</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Plus Jakarta Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0f172a;position:relative;overflow:hidden;padding:20px;}
        .bg-orb{position:fixed;border-radius:50%;filter:blur(80px);opacity:.2;animation:float 7s ease-in-out infinite;}
        .bg-orb-1{width:400px;height:400px;background:radial-gradient(circle,#1e3a8a,transparent);top:-100px;right:-80px;}
        .bg-orb-2{width:300px;height:300px;background:radial-gradient(circle,#7c3aed,transparent);bottom:-80px;left:-60px;animation-delay:-3s;}
        @keyframes float{0%,100%{transform:translateY(0);}50%{transform:translateY(-20px);}}
        .card{position:relative;z-index:10;background:rgba(255,255,255,.05);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.1);border-radius:24px;padding:44px;width:100%;max-width:440px;box-shadow:0 25px 60px rgba(0,0,0,.4);}
        .logo{width:64px;height:64px;background:linear-gradient(135deg,#7c3aed,#6d28d9);border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 18px;box-shadow:0 8px 24px rgba(124,58,237,.4);}
        h1{font-size:22px;font-weight:800;color:#fff;text-align:center;margin-bottom:6px;}
        p.sub{font-size:13.5px;color:rgba(255,255,255,.5);text-align:center;margin-bottom:28px;}
        .form-group{margin-bottom:16px;}
        .form-group label{display:block;font-size:12px;font-weight:700;color:rgba(255,255,255,.6);margin-bottom:7px;text-transform:uppercase;letter-spacing:.5px;}
        .form-control{width:100%;padding:13px 16px;background:rgba(255,255,255,.07);border:1.5px solid rgba(255,255,255,.1);border-radius:11px;font-family:inherit;font-size:14px;color:#fff;outline:none;transition:.3s;}
        .form-control:focus{border-color:rgba(124,58,237,.6);background:rgba(124,58,237,.08);}
        .form-control::placeholder{color:rgba(255,255,255,.25);}
        .btn{width:100%;padding:14px;background:linear-gradient(135deg,#7c3aed,#6d28d9);border:none;border-radius:12px;color:#fff;font-family:inherit;font-size:15px;font-weight:700;cursor:pointer;transition:.3s;margin-top:6px;}
        .btn:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(124,58,237,.4);}
        .alert{padding:12px 16px;border-radius:10px;font-size:13.5px;font-weight:600;margin-bottom:20px;display:flex;align-items:center;gap:8px;}
        .alert-danger{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.25);color:#fca5a5;}
        .links{text-align:center;margin-top:18px;font-size:13px;color:rgba(255,255,255,.4);}
        .links a{color:#a78bfa;font-weight:700;text-decoration:none;}
        .step-info{background:rgba(124,58,237,.12);border:1px solid rgba(124,58,237,.25);color:#c4b5fd;padding:12px 16px;border-radius:10px;font-size:13.5px;margin-bottom:20px;}
    </style>
</head>
<body>
<div class="bg-orb bg-orb-1"></div>
<div class="bg-orb bg-orb-2"></div>

<div class="card">
    <div class="logo">🔐</div>
    <?php if ($step === 1): ?>
        <h1>Lupa Password</h1>
        <p class="sub">Masukkan email dan nomor HP yang terdaftar</p>
        <?php if ($error): ?><div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" placeholder="email@contoh.com" required>
            </div>
            <div class="form-group">
                <label>No. HP / WhatsApp</label>
                <input type="text" name="no_hp" class="form-control" placeholder="08xxxxxxxxxx" required>
            </div>
            <button type="submit" name="cari" class="btn">🔍 Verifikasi Identitas</button>
        </form>
    <?php else: ?>
        <h1>Buat Password Baru</h1>
        <p class="sub">Halo, <?= htmlspecialchars($_SESSION['reset_nama'] ?? 'pengguna') ?>!</p>
        <?php if ($error): ?><div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
        <div class="step-info">✅ Identitas terverifikasi. Silakan masukkan password baru Anda.</div>
        <form method="POST">
            <div class="form-group">
                <label>Password Baru</label>
                <input type="password" name="password_baru" class="form-control" placeholder="Min. 6 karakter" required minlength="6">
            </div>
            <div class="form-group">
                <label>Konfirmasi Password</label>
                <input type="password" name="konfirmasi_baru" class="form-control" placeholder="Ulangi password baru" required>
            </div>
            <button type="submit" name="reset" class="btn">🔑 Ubah Password</button>
        </form>
    <?php endif; ?>
    <div class="links">
        <a href="login.php">← Kembali ke Login</a>
    </div>
</div>
</body>
</html>
