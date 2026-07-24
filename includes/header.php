<?php
// includes/header.php - Header & Navbar Publik Original Menu Structure
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
$base = '/perumahan_kpr/';

$current     = basename($_SERVER['SCRIPT_NAME']);
$current_dir = basename(dirname($_SERVER['SCRIPT_NAME']));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'RumahKPR - Temukan Rumah Impian Anda' ?></title>
    <meta name="description" content="<?= isset($page_desc) ? htmlspecialchars($page_desc) : 'Platform KPR Perumahan terpercaya. Temukan rumah impian dan ajukan kredit dengan mudah, cepat, transparan.' ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $base ?>assets/css/style.css?v=<?= time() ?>">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="nav-container">
        <!-- Brand -->
        <a href="<?= $base ?>index.php" class="brand">
            <div class="brand-icon"><img src="<?= $base ?>assets/images/logo/logo.png" alt="RumahKPR Logo"></div>
            <span>Rumah<span style="color:#4f46e5">KPR</span></span>
        </a>

        <button class="hamburger" id="hamburger" aria-label="Menu">☰</button>

        <!-- Nav Links (Struktur Asli Awal) -->
        <ul class="nav-links" id="navLinks">
            <!-- Sliding Indicator -->
            <div class="nav-indicator" id="navIndicator"></div>

            <li><a href="<?= $base ?>index.php" class="<?= $current==='index.php' ? 'active-link' : '' ?>">Beranda</a></li>
            <li><a href="<?= $base ?>guest/katalog.php" class="<?= $current==='katalog.php' ? 'active-link' : '' ?>">Katalog</a></li>

            <?php if (sudah_login()): ?>
                <?php if (in_array(role_user(), ['admin','marketing'])): ?>
                    <li><a href="<?= $base ?>admin/dashboard.php" style="color:#4f46e5;font-weight:700;">⚙️ Panel Admin</a></li>
                <?php else: ?>
                    <li><a href="<?= $base ?>customer/booking_saya.php" class="<?= $current==='booking_saya.php' ? 'active-link' : '' ?>">📋 Booking Saya</a></li>
                    <li><a href="<?= $base ?>customer/status_kpr.php"   class="<?= $current==='status_kpr.php'   ? 'active-link' : '' ?>">📈 Status KPR</a></li>
                    <li style="display:flex;align-items:center;padding:0 8px;white-space:nowrap;">
                        <span style="font-size:13px;font-weight:700;color:#4f46e5;">👤 <?= htmlspecialchars(nama_user()) ?></span>
                    </li>
                <?php endif; ?>
                <li>
                    <a href="<?= $base ?>logout.php" class="btn btn-sm"
                       style="background:#fee2e2;color:#dc2626;border:1px solid #fecaca;margin-left:4px;white-space:nowrap;">
                        Keluar
                    </a>
                </li>
            <?php else: ?>
                <li><a href="<?= $base ?>login.php" class="<?= $current==='login.php' ? 'active-link' : '' ?>">Masuk</a></li>
                <li>
                    <a href="<?= $base ?>register.php" class="btn btn-primary btn-sm" style="color:#fff;white-space:nowrap;">
                        ✨ Daftar
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<?php if (function_exists('tampil_flash')) tampil_flash(); ?>
<script src="<?= $base ?>assets/js/script.js" defer></script>

<!-- Sliding Nav Indicator JS -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const indicator = document.getElementById('navIndicator');
    const navLinks  = document.getElementById('navLinks');
    if (!indicator || !navLinks) return;

    function moveTo(el) {
        if (!el) { indicator.style.opacity = '0'; return; }
        const li      = el.closest('li');
        const target  = li || el;
        const liRect  = target.getBoundingClientRect();
        const navRect = navLinks.getBoundingClientRect();
        indicator.style.opacity = '1';
        indicator.style.left    = (liRect.left - navRect.left) + 'px';
        indicator.style.width   = liRect.width + 'px';
    }

    // Posisi awal dari link aktif
    moveTo(navLinks.querySelector('.active-link'));

    // Hover: ikut link yang di-hover
    navLinks.querySelectorAll('a:not(.btn)').forEach(function (a) {
        a.addEventListener('mouseenter', function () { moveTo(this); });
    });

    // Keluar dari nav: kembali ke aktif
    navLinks.addEventListener('mouseleave', function () {
        moveTo(navLinks.querySelector('.active-link'));
    });
});
</script>
