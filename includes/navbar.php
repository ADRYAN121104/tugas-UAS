<?php
// includes/navbar.php - Komponen navbar standalone
$base = '/perumahan_kpr/';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/auth.php';
?>
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
                    <li><a href="<?= $base ?>customer/dashboard.php">Dashboard Saya</a></li>
                <?php endif; ?>
                <li><a href="<?= $base ?>logout.php" class="btn btn-outline btn-sm">Keluar</a></li>
            <?php else: ?>
                <li><a href="<?= $base ?>login.php">Masuk</a></li>
                <li><a href="<?= $base ?>register.php" class="btn btn-primary btn-sm" style="color:#fff;">Daftar</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>
