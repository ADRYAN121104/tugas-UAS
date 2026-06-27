<?php
// register.php — dengan password_hash() untuk keamanan
require_once 'config/koneksi.php';
require_once 'config/session.php';
require_once 'config/auth.php';

if (sudah_login()) { header('Location: customer/booking_saya.php'); exit; }

$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama  = trim($_POST['nama_lengkap'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['konfirmasi'] ?? '';
    $no_hp = trim($_POST['no_hp'] ?? '');

    if (!$nama || !$email || !$pass || !$no_hp) {
        $error = 'Semua field wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif ($pass !== $pass2) {
        $error = 'Password dan konfirmasi tidak cocok.';
    } elseif (strlen($pass) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif (!preg_match('/[A-Za-z]/', $pass) || !preg_match('/[0-9]/', $pass)) {
        $error = 'Password harus mengandung huruf dan angka.';
    } else {
        $cek = $db->prepare("SELECT id_user FROM users WHERE email=?");
        $cek->execute([$email]);
        if ($cek->fetch()) {
            $error = 'Email sudah terdaftar.';
        } else {
            // Hash password dengan bcrypt
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users(nama_lengkap,email,password,no_hp,role) VALUES(?,?,?,?,'customer')");
            $stmt->execute([$nama, $email, $hashed, $no_hp]);
            $_SESSION['flash_sukses'] = 'Pendaftaran berhasil! Silakan login.';
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{--primary:#2563eb;--font:'Plus Jakarta Sans',sans-serif;}
        body{font-family:var(--font);min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0f172a;position:relative;overflow:hidden;padding:20px;}

        .bg-gradient{position:fixed;inset:0;z-index:0;}
        .bg-orb{position:absolute;border-radius:50%;filter:blur(80px);opacity:.2;animation:float 7s ease-in-out infinite;}
        .bg-orb-1{width:500px;height:500px;background:radial-gradient(circle,#1e3a8a,transparent);top:-150px;left:-100px;}
        .bg-orb-2{width:350px;height:350px;background:radial-gradient(circle,#f59e0b,transparent);bottom:-100px;right:-80px;animation-delay:-3.5s;}
        @keyframes float{0%,100%{transform:translateY(0);}50%{transform:translateY(-25px);}}

        .auth-wrap{position:relative;z-index:10;width:100%;max-width:520px;}
        .auth-card{background:rgba(255,255,255,.05);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.12);border-radius:24px;padding:44px;box-shadow:0 25px 60px rgba(0,0,0,.4);}
        .auth-logo{width:64px;height:64px;background:linear-gradient(135deg,#1e3a8a,#2563eb);border-radius:20px;display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 18px;box-shadow:0 8px 24px rgba(37,99,235,.4);}
        .auth-title{font-size:24px;font-weight:800;color:#fff;text-align:center;margin-bottom:6px;}
        .auth-sub{font-size:14px;color:rgba(255,255,255,.5);text-align:center;margin-bottom:28px;}

        .form-group{margin-bottom:16px;}
        .form-group label{display:block;font-size:12px;font-weight:700;color:rgba(255,255,255,.65);margin-bottom:7px;text-transform:uppercase;letter-spacing:.5px;}
        .form-control{width:100%;padding:12px 15px;background:rgba(255,255,255,.07);border:1.5px solid rgba(255,255,255,.1);border-radius:11px;font-family:var(--font);font-size:14px;color:#fff;outline:none;transition:.3s;}
        .form-control:focus{border-color:rgba(59,130,246,.6);background:rgba(59,130,246,.08);box-shadow:0 0 0 3px rgba(59,130,246,.12);}
        .form-control::placeholder{color:rgba(255,255,255,.25);}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
        @media(max-width:480px){.form-row{grid-template-columns:1fr;}}

        .pass-wrap{position:relative;}
        .pass-wrap .form-control{padding-right:44px;}
        .pass-toggle{position:absolute;right:13px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:rgba(255,255,255,.35);font-size:17px;padding:4px;transition:.2s;}
        .pass-toggle:hover{color:rgba(255,255,255,.75);}

        /* Password strength indicator */
        .pass-strength{margin-top:8px;height:4px;border-radius:4px;background:rgba(255,255,255,.08);overflow:hidden;}
        .pass-strength-bar{height:100%;width:0;border-radius:4px;transition:.3s;}
        .pass-strength-text{font-size:11px;margin-top:5px;font-weight:600;}

        .btn-register{width:100%;padding:14px;background:linear-gradient(135deg,#2563eb,#1d4ed8);border:none;border-radius:12px;color:#fff;font-family:var(--font);font-size:15px;font-weight:700;cursor:pointer;transition:.3s;margin-top:6px;position:relative;overflow:hidden;}
        .btn-register::after{content:'';position:absolute;inset:0;background:linear-gradient(90deg,transparent,rgba(255,255,255,.15),transparent);transform:translateX(-100%);transition:.5s;}
        .btn-register:hover::after{transform:translateX(100%);}
        .btn-register:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(37,99,235,.4);}

        .alert-danger{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.25);color:#fca5a5;padding:12px 16px;border-radius:10px;font-size:13.5px;font-weight:600;margin-bottom:20px;display:flex;align-items:center;gap:8px;}
        .auth-links{text-align:center;margin-top:18px;font-size:13.5px;color:rgba(255,255,255,.45);}
        .auth-links a{color:#60a5fa;font-weight:700;text-decoration:none;}
        .auth-links a:hover{color:#93c5fd;}

        .rule-list{font-size:11.5px;color:rgba(255,255,255,.35);margin-top:7px;list-style:none;display:flex;flex-wrap:wrap;gap:6px;}
        .rule-item{display:flex;align-items:center;gap:4px;}
        .rule-item.ok{color:#34d399;}
    </style>
</head>
<body>
<div class="bg-gradient">
    <div class="bg-orb bg-orb-1"></div>
    <div class="bg-orb bg-orb-2"></div>
</div>

<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-logo">📝</div>
        <h1 class="auth-title">Buat Akun Baru</h1>
        <p class="auth-sub">Daftar untuk mulai memesan properti impian Anda</p>

        <?php if ($error): ?>
            <div class="alert-danger">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" id="regForm" autocomplete="off">
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="nama_lengkap" class="form-control" placeholder="Nama lengkap Anda"
                       value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? '') ?>" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" placeholder="email@contoh.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>No. HP / WhatsApp</label>
                    <input type="text" name="no_hp" class="form-control" placeholder="08xxxxxxxxxx"
                           value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Password</label>
                    <div class="pass-wrap">
                        <input type="password" name="password" id="passNew" class="form-control" placeholder="Min. 6 karakter" required oninput="checkStrength()">
                        <button type="button" class="pass-toggle" onclick="togglePass('passNew','btn1')" id="btn1">👁️</button>
                    </div>
                    <div class="pass-strength"><div class="pass-strength-bar" id="strengthBar"></div></div>
                    <div class="pass-strength-text" id="strengthText"></div>
                    <ul class="rule-list" id="ruleList">
                        <li class="rule-item" id="ruleLen">⬜ Min. 6 karakter</li>
                        <li class="rule-item" id="ruleAlpha">⬜ Ada huruf</li>
                        <li class="rule-item" id="ruleNum">⬜ Ada angka</li>
                    </ul>
                </div>
                <div class="form-group">
                    <label>Konfirmasi Password</label>
                    <div class="pass-wrap">
                        <input type="password" name="konfirmasi" id="passConf" class="form-control" placeholder="Ulangi password" required oninput="checkMatch()">
                        <button type="button" class="pass-toggle" onclick="togglePass('passConf','btn2')" id="btn2">👁️</button>
                    </div>
                    <div class="pass-strength-text" id="matchText"></div>
                </div>
            </div>
            <button type="submit" class="btn-register">🚀 Daftar Sekarang</button>
        </form>
        <div class="auth-links">
            Sudah punya akun? <a href="login.php">Masuk di sini</a>
        </div>
        <div class="auth-links" style="margin-top:8px;">
            <a href="index.php">← Kembali ke Beranda</a>
        </div>
    </div>
</div>

<script>
function togglePass(id, btnId) {
    const inp = document.getElementById(id);
    const btn = document.getElementById(btnId);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.textContent = inp.type === 'password' ? '👁️' : '🙈';
}

function checkStrength() {
    const val = document.getElementById('passNew').value;
    const bar = document.getElementById('strengthBar');
    const txt = document.getElementById('strengthText');
    const rLen = document.getElementById('ruleLen');
    const rAlpha = document.getElementById('ruleAlpha');
    const rNum = document.getElementById('ruleNum');

    const hasLen = val.length >= 6;
    const hasAlpha = /[A-Za-z]/.test(val);
    const hasNum = /[0-9]/.test(val);

    rLen.textContent = (hasLen ? '✅' : '⬜') + ' Min. 6 karakter';
    rLen.className = 'rule-item' + (hasLen ? ' ok' : '');
    rAlpha.textContent = (hasAlpha ? '✅' : '⬜') + ' Ada huruf';
    rAlpha.className = 'rule-item' + (hasAlpha ? ' ok' : '');
    rNum.textContent = (hasNum ? '✅' : '⬜') + ' Ada angka';
    rNum.className = 'rule-item' + (hasNum ? ' ok' : '');

    let score = [hasLen, hasAlpha, hasNum, val.length >= 10, /[^A-Za-z0-9]/.test(val)].filter(Boolean).length;
    const colors = ['#ef4444','#f59e0b','#f59e0b','#10b981','#10b981'];
    const labels = ['Sangat Lemah','Lemah','Cukup','Kuat','Sangat Kuat'];
    bar.style.width = (score * 20) + '%';
    bar.style.background = colors[score-1] || '#ef4444';
    txt.textContent = val ? labels[score-1] || 'Sangat Lemah' : '';
    txt.style.color = colors[score-1] || '#ef4444';
}

function checkMatch() {
    const p1 = document.getElementById('passNew').value;
    const p2 = document.getElementById('passConf').value;
    const txt = document.getElementById('matchText');
    if (!p2) { txt.textContent = ''; return; }
    if (p1 === p2) { txt.textContent = '✅ Password cocok'; txt.style.color = '#34d399'; }
    else { txt.textContent = '❌ Password tidak cocok'; txt.style.color = '#fca5a5'; }
}
</script>
</body>
</html>
