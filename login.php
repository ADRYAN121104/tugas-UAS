<?php
// login.php — dengan password_verify() untuk keamanan
require_once 'config/koneksi.php';
require_once 'config/session.php';
require_once 'config/auth.php';

if (isset($_SESSION['admin']['user_id'])) {
    header('Location: admin/dashboard.php'); exit;
}
if (isset($_SESSION['customer']['user_id'])) {
    header('Location: customer/booking_saya.php'); exit;
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

        // Cek password: coba password_verify dulu, lalu fallback plain text (untuk data lama)
        $pass_ok = false;
        if ($user) {
            if (password_verify($password, $user['password'])) {
                $pass_ok = true;
            } elseif ($password === $user['password']) {
                // Data lama: plain text — otomatis upgrade ke hash
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $db->prepare("UPDATE users SET password=? WHERE id_user=?")->execute([$hashed, $user['id_user']]);
                $pass_ok = true;
            }
        }

        if ($pass_ok) {
            if (in_array($user['role'], ['admin', 'marketing'])) {
                $_SESSION['admin'] = [
                    'user_id'      => $user['id_user'],
                    'nama_lengkap' => $user['nama_lengkap'],
                    'email'        => $user['email'],
                    'role'         => $user['role'],
                ];
                set_flash('sukses', 'Selamat datang, ' . $user['nama_lengkap'] . '!');
                header('Location: admin/dashboard.php');
            } else {
                $_SESSION['customer'] = [
                    'user_id'      => $user['id_user'],
                    'nama_lengkap' => $user['nama_lengkap'],
                    'email'        => $user['email'],
                    'role'         => $user['role'],
                ];
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --primary:#2563eb;--primary-d:#1d4ed8;--accent:#f59e0b;
            --font:'Plus Jakarta Sans',sans-serif;
        }
        body{font-family:var(--font);min-height:100vh;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;background:#0f172a;}

        /* Animasi background */
        .bg-gradient{position:fixed;inset:0;z-index:0;}
        .bg-gradient::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 50%,#0f172a 100%);animation:bgShift 8s ease infinite alternate;}
        .bg-orb{position:absolute;border-radius:50%;filter:blur(80px);opacity:.25;animation:float 6s ease-in-out infinite;}
        .bg-orb-1{width:400px;height:400px;background:radial-gradient(circle,#3b82f6,transparent);top:-100px;right:-100px;animation-delay:0s;}
        .bg-orb-2{width:300px;height:300px;background:radial-gradient(circle,#f59e0b,transparent);bottom:-50px;left:-80px;animation-delay:-3s;}
        .bg-orb-3{width:200px;height:200px;background:radial-gradient(circle,#8b5cf6,transparent);top:40%;left:10%;animation-delay:-1.5s;}
        @keyframes float{0%,100%{transform:translateY(0) scale(1);}50%{transform:translateY(-30px) scale(1.05);}}
        @keyframes bgShift{0%{opacity:1;}100%{opacity:.8;}}

        /* Floating particles */
        .particles{position:fixed;inset:0;z-index:0;pointer-events:none;}
        .particle{position:absolute;width:4px;height:4px;background:rgba(255,255,255,.15);border-radius:50%;animation:particle-float linear infinite;}
        @keyframes particle-float{0%{transform:translateY(100vh) rotate(0deg);opacity:0;}10%{opacity:1;}90%{opacity:1;}100%{transform:translateY(-100px) rotate(360deg);opacity:0;}}

        /* Card */
        .auth-wrap{position:relative;z-index:10;width:100%;max-width:460px;padding:20px;}
        .auth-card{background:rgba(255,255,255,.05);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.12);border-radius:24px;padding:44px;box-shadow:0 25px 60px rgba(0,0,0,.4);}
        .auth-logo{width:64px;height:64px;background:linear-gradient(135deg,#1e3a8a,#2563eb,#3b82f6);border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 18px;box-shadow:0 8px 24px rgba(37,99,235,.4);}
        .auth-title{font-size:26px;font-weight:800;color:#fff;text-align:center;margin-bottom:6px;}
        .auth-sub{font-size:14px;color:rgba(255,255,255,.55);text-align:center;margin-bottom:30px;}

        /* Form */
        .form-group{margin-bottom:18px;}
        .form-group label{display:block;font-size:12.5px;font-weight:700;color:rgba(255,255,255,.7);margin-bottom:8px;letter-spacing:.5px;text-transform:uppercase;}
        .form-control{width:100%;padding:13px 16px;background:rgba(255,255,255,.08);border:1.5px solid rgba(255,255,255,.12);border-radius:12px;font-family:var(--font);font-size:14.5px;color:#fff;outline:none;transition:.3s;}
        .form-control:focus{border-color:rgba(59,130,246,.7);background:rgba(59,130,246,.1);box-shadow:0 0 0 3px rgba(59,130,246,.15);}
        .form-control::placeholder{color:rgba(255,255,255,.3);}

        /* Buttons */
        .btn-login{width:100%;padding:15px;background:linear-gradient(135deg,#2563eb,#1d4ed8);border:none;border-radius:12px;color:#fff;font-family:var(--font);font-size:15px;font-weight:700;cursor:pointer;transition:.3s;position:relative;overflow:hidden;}
        .btn-login::after{content:'';position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,255,255,.15),transparent);transform:translateX(-100%);transition:.5s;}
        .btn-login:hover::after{transform:translateX(100%);}
        .btn-login:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(37,99,235,.4);}
        .btn-login:active{transform:translateY(0);}

        /* Alert */
        .alert-danger{background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#fca5a5;padding:12px 16px;border-radius:10px;font-size:14px;font-weight:600;margin-bottom:20px;display:flex;align-items:center;gap:8px;}

        /* Links */
        .auth-links{text-align:center;margin-top:20px;font-size:13.5px;color:rgba(255,255,255,.5);}
        .auth-links a{color:#60a5fa;font-weight:700;text-decoration:none;transition:.2s;}
        .auth-links a:hover{color:#93c5fd;}

        /* Demo box */
        .demo-box{margin-top:20px;padding:14px 16px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:12px;font-size:12.5px;color:rgba(255,255,255,.45);}
        .demo-box b{color:rgba(255,255,255,.7);}

        /* Password toggle */
        .pass-wrap{position:relative;}
        .pass-wrap .form-control{padding-right:44px;}
        .pass-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:rgba(255,255,255,.4);font-size:18px;transition:.2s;padding:4px;}
        .pass-toggle:hover{color:rgba(255,255,255,.8);}

        .divider{display:flex;align-items:center;gap:12px;margin:20px 0;color:rgba(255,255,255,.25);font-size:12px;}
        .divider::before,.divider::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.1);}
    </style>
</head>
<body>
<div class="bg-gradient">
    <div class="bg-orb bg-orb-1"></div>
    <div class="bg-orb bg-orb-2"></div>
    <div class="bg-orb bg-orb-3"></div>
</div>
<div class="particles" id="particles"></div>

<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-logo">🏠</div>
        <h1 class="auth-title">Masuk ke Akun</h1>
        <p class="auth-sub">Sistem KPR Perumahan</p>

        <?php if ($error): ?>
            <div class="alert-danger">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php
        // Tampilkan flash success (dari register)
        if (isset($_SESSION['flash_sukses'])) {
            echo '<div style="background:rgba(16,185,129,.15);border:1px solid rgba(16,185,129,.3);color:#6ee7b7;padding:12px 16px;border-radius:10px;font-size:14px;font-weight:600;margin-bottom:20px;">✅ '.htmlspecialchars($_SESSION['flash_sukses']).'</div>';
            unset($_SESSION['flash_sukses']);
        }
        ?>

        <form method="POST" action="" autocomplete="off">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" placeholder="email@contoh.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group">
                <label>Password</label>
                <div class="pass-wrap">
                    <input type="password" name="password" id="passInput" class="form-control" placeholder="••••••••" required>
                    <button type="button" class="pass-toggle" onclick="togglePass()" id="passBtn">👁️</button>
                </div>
            </div>
            <div style="text-align:right;margin-bottom:20px;">
                <a href="lupa_password.php" style="font-size:13px;color:#60a5fa;font-weight:600;text-decoration:none;">Lupa password?</a>
            </div>
            <button type="submit" class="btn-login">🚀 Masuk Sekarang</button>
        </form>

        <div class="divider">atau</div>

        <div class="auth-links">
            Belum punya akun? <a href="register.php">Daftar di sini</a>
        </div>
        <div class="auth-links" style="margin-top:8px;">
            <a href="index.php">← Kembali ke Beranda</a>
        </div>

        <div class="demo-box">
            <b>Demo Login:</b><br>
            Admin: admin@kpr.com / 1234<br>
            Customer: budi@gmail.com / 1234
        </div>
    </div>
</div>

<script>
// Floating particles
(function() {
    const container = document.getElementById('particles');
    for (let i = 0; i < 20; i++) {
        const p = document.createElement('div');
        p.className = 'particle';
        p.style.cssText = `left:${Math.random()*100}%;width:${2+Math.random()*4}px;height:${2+Math.random()*4}px;animation-duration:${8+Math.random()*15}s;animation-delay:${Math.random()*10}s;opacity:${.05+Math.random()*.15};`;
        container.appendChild(p);
    }
})();

function togglePass() {
    const inp = document.getElementById('passInput');
    const btn = document.getElementById('passBtn');
    if (inp.type === 'password') { inp.type = 'text'; btn.textContent = '🙈'; }
    else { inp.type = 'password'; btn.textContent = '👁️'; }
}
</script>
</body>
</html>
