<?php
// admin/dashboard.php
require_once '../config/koneksi.php';
require_once '../config/cek_admin.php';
require_once '../config/functions.php';
require_once '../includes/sidebar_admin.php';

// Menghitung statistik
$total_perumahan = $db->query("SELECT COUNT(*) FROM perumahan")->fetchColumn();
$total_tipe      = $db->query("SELECT COUNT(*) FROM tipe_rumah")->fetchColumn();
$total_unit      = $db->query("SELECT COUNT(*) FROM rumah")->fetchColumn();
$unit_tersedia   = $db->query("SELECT COUNT(*) FROM rumah WHERE status='tersedia'")->fetchColumn();
$total_customer  = $db->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$total_booking   = $db->query("SELECT COUNT(*) FROM booking")->fetchColumn();
$pending_bayar   = $db->query("SELECT COUNT(*) FROM pembayaran WHERE status_verifikasi='pending'")->fetchColumn();
$kpr_proses      = $db->query("SELECT COUNT(*) FROM pengajuan_kpr WHERE status_pengajuan NOT IN ('disetujui','ditolak','akad_kredit')")->fetchColumn();

// Booking terbaru
$booking_terbaru = $db->query("SELECT b.*, u.nama_lengkap, p.nama_perumahan, r.blok, r.kode_unit 
    FROM booking b 
    JOIN users u ON b.id_user=u.id_user 
    JOIN rumah r ON b.id_rumah=r.id_rumah 
    JOIN perumahan p ON r.id_perumahan=p.id_perumahan 
    ORDER BY b.id_booking DESC LIMIT 5")->fetchAll();

// KPR terbaru
$kpr_terbaru = $db->query("SELECT pk.*, u.nama_lengkap, p.nama_perumahan, r.blok, r.kode_unit, b.nama_bank 
    FROM pengajuan_kpr pk 
    JOIN users u ON pk.id_user=u.id_user 
    JOIN rumah r ON pk.id_rumah=r.id_rumah 
    JOIN perumahan p ON r.id_perumahan=p.id_perumahan 
    JOIN bank b ON pk.id_bank=b.id_bank 
    ORDER BY pk.id_pengajuan DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - RumahKPR</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php sidebar_admin('dashboard'); ?>
    <div class="admin-main">
        <header class="topbar">
            <div style="display:flex;align-items:center;gap:12px;">
                <button class="btn btn-gray btn-sm" id="sidebarToggle" style="padding:6px 10px;">☰</button>
                <div class="topbar-title">Panel Kontrol Administrator</div>
            </div>
            <div class="topbar-right">
                <span class="topbar-name"><?= htmlspecialchars(nama_user()) ?> (<?= ucfirst(role_user()) ?>)</span>
                <div class="topbar-avatar"><?= strtoupper(substr(nama_user(),0,1)) ?></div>
            </div>
        </header>
        <main class="content">
            <?php tampil_flash(); ?>
            <div class="page-header">
                <div class="page-header-left">
                    <h2>📊 Dashboard</h2>
                    <p>Ringkasan performa sistem KPR dan booking saat ini</p>
                </div>
            </div>

            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-ico ico-biru">🏙️</div>
                    <div class="stat-info">
                        <h3><?= $total_perumahan ?></h3>
                        <p>Komplek Perumahan</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-ico ico-ungu">📐</div>
                    <div class="stat-info">
                        <h3><?= $total_tipe ?></h3>
                        <p>Tipe Rumah</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-ico ico-hijau">🚪</div>
                    <div class="stat-info">
                        <h3><?= $total_unit ?></h3>
                        <p>Total Unit (<?= $unit_tersedia ?> Ready)</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-ico ico-kuning">👤</div>
                    <div class="stat-info">
                        <h3><?= $total_customer ?></h3>
                        <p>Total Customer</p>
                    </div>
                </div>
            </div>

            <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
                <div class="stat-card">
                    <div class="stat-ico ico-biru">📋</div>
                    <div class="stat-info">
                        <h3><?= $total_booking ?></h3>
                        <p>Total Booking</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-ico ico-merah">💰</div>
                    <div class="stat-info">
                        <h3><?= $pending_bayar ?></h3>
                        <p>Pembayaran Pending</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-ico ico-hijau">📝</div>
                    <div class="stat-info">
                        <h3><?= $kpr_proses ?></h3>
                        <p>Pengajuan KPR Aktif</p>
                    </div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:12px;" id="dashboard-tables">
                <div class="panel">
                    <div class="panel-header">
                        <h3>📋 Booking Terbaru</h3>
                        <a href="booking/index.php" class="btn btn-outline btn-sm">Lihat Semua</a>
                    </div>
                    <div class="panel-body" style="padding:0;">
                        <div class="tbl-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Unit</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($booking_terbaru)): ?>
                                        <tr><td colspan="3" class="empty">Tidak ada data booking.</td></tr>
                                    <?php else: foreach($booking_terbaru as $b): ?>
                                        <tr>
                                            <td><b><?= htmlspecialchars($b['nama_lengkap']) ?></b><br><small><?= format_tanggal($b['tanggal_booking']) ?></small></td>
                                            <td><?= htmlspecialchars($b['nama_perumahan']) ?><br><small style="color:var(--primary);font-weight:700;">Blok <?= htmlspecialchars($b['blok'].'-'.$b['kode_unit']) ?></small></td>
                                            <td><?= badge_booking($b['status_booking']) ?></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h3>📝 Pengajuan KPR Terbaru</h3>
                        <a href="pengajuan_kpr/index.php" class="btn btn-outline btn-sm">Lihat Semua</a>
                    </div>
                    <div class="panel-body" style="padding:0;">
                        <div class="tbl-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Bank / Unit</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($kpr_terbaru)): ?>
                                        <tr><td colspan="3" class="empty">Tidak ada data pengajuan KPR.</td></tr>
                                    <?php else: foreach($kpr_terbaru as $k): ?>
                                        <tr>
                                            <td><b><?= htmlspecialchars($k['nama_lengkap']) ?></b><br><small><?= format_tanggal($k['tanggal_pengajuan']) ?></small></td>
                                            <td><?= htmlspecialchars($k['nama_bank']) ?><br><small>Blok <?= htmlspecialchars($k['blok'].'-'.$k['kode_unit']) ?></small></td>
                                            <td><?= badge_kpr($k['status_pengajuan']) ?></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="../assets/js/script.js"></script>
    <style>
        @media(max-width: 1024px) {
            #dashboard-tables { grid-template-columns: 1fr !important; }
        }
    </style>
</body>
</html>
