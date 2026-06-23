<?php
// includes/header.php - Header & Navbar untuk halaman publik
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/auth.php';
$base = '/perumahan_kpr/';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'Sistem KPR Perumahan' ?></title>
    <meta name="description" content="<?= isset($page_desc) ? htmlspecialchars($page_desc) : 'Portal KPR Perumahan terpercaya - temukan rumah impian dan ajukan kredit dengan mudah.' ?>">
    <link rel="stylesheet" href="<?= $base ?>assets/css/style.css?v=<?= time() ?>">
</head>
<body>
<nav class="navbar">
    <div class="nav-container">
        <a href="<?= $base ?>index.php" class="brand">
            <div class="brand-icon">🏠</div>
            <span>Rumah<span style="color:#2563eb">KPR</span></span>
        </a>
        <button class="hamburger" id="hamburger">☰</button>
        <ul class="nav-links" id="navLinks">
            <li><a href="<?= $base ?>index.php">Beranda</a></li>
            <li><a href="<?= $base ?>guest/katalog.php">Katalog</a></li>
            <li><a href="<?= $base ?>guest/simulasi_kpr.php">Simulasi KPR</a></li>
            <?php if (sudah_login()): ?>
                <?php if (in_array(role_user(), ['admin','marketing'])): ?>
                    <li><a href="<?= $base ?>admin/dashboard.php">Panel Admin</a></li>
                <?php else: ?>
                    <li><a href="<?= $base ?>customer/booking_saya.php">Booking Saya</a></li>
                    <li><a href="<?= $base ?>customer/status_kpr.php">Status KPR</a></li>
                    <li><a href="<?= $base ?>customer/profil.php">Profil Saya</a></li>
                <?php endif; ?>
                <li><a href="<?= $base ?>logout.php" class="btn btn-outline btn-sm">Keluar</a></li>
            <?php else: ?>
                <li><a href="<?= $base ?>login.php">Masuk</a></li>
                <li><a href="<?= $base ?>register.php" class="btn btn-primary btn-sm" style="color:#fff;">Daftar</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>
<script src="<?= $base ?>assets/js/script.js" defer></script>
