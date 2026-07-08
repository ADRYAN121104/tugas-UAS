<?php
// includes/header.php - Header & Navbar untuk halaman publik - Redesign Premium
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
$base = '/perumahan_kpr/';

// Tentukan halaman aktif berdasarkan URL
$current = basename($_SERVER['SCRIPT_NAME']);
$current_dir = basename(dirname($_SERVER['SCRIPT_NAME']));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'Sistem KPR Perumahan' ?></title>
    <meta name="description" content="<?= isset($page_desc) ? htmlspecialchars($page_desc) : 'Portal KPR Perumahan terpercaya - temukan rumah impian dan ajukan kredit dengan mudah.' ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="<?= $base ?>assets/css/style.css?v=<?= date('YmdHi') ?>">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="nav-container">
        <a href="<?= $base ?>index.php" class="brand">
            <div class="brand-icon">🏠</div>
            <span>Rumah<span style="color:#2563eb">KPR</span></span>
        </a>
        <button class="hamburger" id="hamburger" aria-label="Menu">☰</button>
        <ul class="nav-links" id="navLinks">
            <li><a href="<?= $base ?>index.php" class="<?= $current==='index.php'?'active-link':'' ?>">Beranda</a></li>
            <li><a href="<?= $base ?>guest/katalog.php" class="<?= $current==='katalog.php'?'active-link':'' ?>">Katalog</a></li>

            <?php if (sudah_login()): ?>
                <?php if (in_array(role_user(), ['admin','marketing'])): ?>
                    <li><a href="<?= $base ?>admin/dashboard.php" style="color:#2563eb;font-weight:700;">⚙️ Panel Admin</a></li>
                <?php else: ?>
                    <li><a href="<?= $base ?>customer/booking_saya.php" class="<?= $current==='booking_saya.php'?'active-link':'' ?>">📋 Booking Saya</a></li>
                    <li><a href="<?= $base ?>customer/status_kpr.php" class="<?= $current==='status_kpr.php'?'active-link':'' ?>">📈 Status KPR</a></li>
                    <li style="display:flex;align-items:center;gap:8px;padding:4px 0;">
                        <span style="font-size:13px;font-weight:600;color:#64748b;">
                            👤 <?= htmlspecialchars(nama_user()) ?>
                        </span>
                    </li>
                <?php endif; ?>
                <li><a href="<?= $base ?>logout.php" class="btn btn-outline btn-sm" style="color:#ef4444;border-color:#ef4444;" onmouseover="this.style.background='#ef4444';this.style.color='#fff'" onmouseout="this.style.background='transparent';this.style.color='#ef4444'">Keluar</a></li>
            <?php else: ?>
                <li><a href="<?= $base ?>login.php" class="<?= $current==='login.php'?'active-link':'' ?>">Masuk</a></li>
                <li><a href="<?= $base ?>register.php" class="btn btn-primary btn-sm" style="color:#fff;">✨ Daftar</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<?php
// Tampilkan flash message jika ada
if (function_exists('tampil_flash')) tampil_flash();
?>
<script src="<?= $base ?>assets/js/script.js" defer></script>
