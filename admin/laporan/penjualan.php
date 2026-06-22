<?php
// admin/laporan/penjualan.php
require_once '../../config/koneksi.php';
require_once '../../config/cek_admin.php';
require_once '../../config/functions.php';
require_once '../../includes/sidebar_admin.php';

// Default tanggal: 1 bulan terakhir
$tgl_mulai   = $_GET['tgl_mulai'] ?? date('Y-m-01');
$tgl_selesai = $_GET['tgl_selesai'] ?? date('Y-m-d');

// --- 1. Query Ringkasan Statistik ---
// Total booking terkonfirmasi
$q_stats = $db->prepare("SELECT COUNT(*), SUM(booking_fee) FROM booking WHERE status_booking = 'dikonfirmasi' AND tanggal_booking BETWEEN ? AND ?");
$q_stats->execute([$tgl_mulai, $tgl_selesai]);
$stats = $q_stats->fetch(PDO::FETCH_NUM);
$total_booking_count = $stats[0] ?? 0;
$total_booking_fee   = $stats[1] ?? 0;

// Total KPR disetujui / akad
$q_kpr_ok = $db->prepare("SELECT COUNT(*) FROM pengajuan_kpr WHERE status_pengajuan IN ('disetujui', 'akad_kredit') AND tanggal_pengajuan BETWEEN ? AND ?");
$q_kpr_ok->execute([$tgl_mulai, $tgl_selesai]);
$total_kpr_disetujui = $q_kpr_ok->fetchColumn();

// --- 2. Query Detail Booking Terkonfirmasi ---
$q_booking = $db->prepare("SELECT b.*, u.nama_lengkap, p.nama_perumahan, r.blok, r.kode_unit 
                           FROM booking b 
                           JOIN users u ON b.id_user = u.id_user 
                           JOIN rumah r ON b.id_rumah = r.id_rumah 
                           JOIN perumahan p ON r.id_perumahan = p.id_perumahan 
                           WHERE b.status_booking = 'dikonfirmasi' AND b.tanggal_booking BETWEEN ? AND ? 
                           ORDER BY b.tanggal_booking DESC");
$q_booking->execute([$tgl_mulai, $tgl_selesai]);
$bookings = $q_booking->fetchAll();

// --- 3. Query Detail Pengajuan KPR ---
$q_kpr = $db->prepare("SELECT pk.*, u.nama_lengkap, p.nama_perumahan, r.blok, r.kode_unit, b.nama_bank 
                       FROM pengajuan_kpr pk 
                       JOIN users u ON pk.id_user = u.id_user 
                       JOIN rumah r ON pk.id_rumah = r.id_rumah 
                       JOIN perumahan p ON r.id_perumahan = p.id_perumahan 
                       JOIN bank b ON pk.id_bank = b.id_bank 
                       WHERE pk.tanggal_pengajuan BETWEEN ? AND ? 
                       ORDER BY pk.tanggal_pengajuan DESC");
$q_kpr->execute([$tgl_mulai, $tgl_selesai]);
$kprs = $q_kpr->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan & KPR - RumahKPR Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        @media print {
            .sidebar, .topbar, .breadcrumb, .page-header button, .panel-header, .filter-panel, .print-btn { display: none !important; }
            .admin-main { margin-left: 0 !important; }
            .content { padding: 0 !important; }
            .panel { border: none !important; box-shadow: none !important; margin-bottom: 30px !important; }
            .panel-body { padding: 0 !important; }
            body { background: #fff !important; }
            .print-header { display: block !important; margin-bottom: 24px; text-align: center; border-bottom: 3px double #000; padding-bottom: 12px; }
        }
        .print-header { display: none; }
    </style>
</head>
<body>
    <?php sidebar_admin('laporan'); ?>
    <div class="admin-main">
        <header class="topbar">
            <div style="display:flex;align-items:center;gap:12px;">
                <button class="btn btn-gray btn-sm" id="sidebarToggle" style="padding:6px 10px;">\u2630</button>
                <div class="topbar-title">Sistem KPR Perumahan</div>
            </div>
            <div class="topbar-right">
                <span class="topbar-name"><?= htmlspecialchars(nama_user()) ?> (<?= ucfirst(role_user()) ?>)</span>
                <div class="topbar-avatar"><?= strtoupper(substr(nama_user(),0,1)) ?></div>
            </div>
        </header>
        <main class="content">
            <!-- Header khusus Cetak Kertas -->
            <div class="print-header">
                <h1 style="font-size:24px; font-weight:800; margin-bottom:4px;">LAPORAN PENJUALAN & KPR RUMAH</h1>
                <p style="font-size:14px; color:#475569;">Periode: <?= format_tanggal($tgl_mulai) ?> s.d <?= format_tanggal($tgl_selesai) ?></p>
            </div>

            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a> / <span>Laporan Penjualan</span>
            </div>
            <?php tampil_flash(); ?>

            <div class="page-header">
                <div class="page-header-left">
                    <h2>📈 Laporan Penjualan</h2>
                    <p>Statistik performa booking terverifikasi dan pengajuan berkas KPR masuk</p>
                </div>
                <button onclick="window.print()" class="btn btn-primary print-btn">🖨️ Cetak Laporan</button>
            </div>

            <!-- FILTER PERIODE -->
            <div class="panel filter-panel" style="margin-bottom: 22px;">
                <div class="panel-header"><h3>📅 Filter Rentang Tanggal Laporan</h3></div>
                <div class="panel-body" style="padding: 16px 22px;">
                    <form method="GET" action="" style="display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap;">
                        <div class="form-group" style="margin-bottom:0; flex:1; min-width:180px;">
                            <label>Tanggal Mulai</label>
                            <input type="date" name="tgl_mulai" class="form-control" value="<?= $tgl_mulai ?>" required>
                        </div>
                        <div class="form-group" style="margin-bottom:0; flex:1; min-width:180px;">
                            <label>Tanggal Selesai</label>
                            <input type="date" name="tgl_selesai" class="form-control" value="<?= $tgl_selesai ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary" style="height:42px;">🔍 Filter Laporan</button>
                        <a href="penjualan.php" class="btn btn-gray" style="height:42px;">Reset</a>
                    </form>
                </div>
            </div>

            <!-- SUMMARY STATS -->
            <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
                <div class="stat-card">
                    <div class="stat-ico ico-hijau">💰</div>
                    <div class="stat-info">
                        <h3><?= format_rupiah($total_booking_fee) ?></h3>
                        <p>Total Booking Fee Masuk</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-ico ico-biru">📋</div>
                    <div class="stat-info">
                        <h3><?= $total_booking_count ?></h3>
                        <p>Booking Terkonfirmasi</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-ico ico-ungu">🤝</div>
                    <div class="stat-info">
                        <h3><?= $total_kpr_disetujui ?></h3>
                        <p>KPR Sukses / Akad</p>
                    </div>
                </div>
            </div>

            <!-- LAPORAN DETAIL BOOKING -->
            <div class="panel">
                <div class="panel-header">
                    <h3>📋 Detail Booking Terkonfirmasi (Lunas Booking Fee)</h3>
                </div>
                <div class="panel-body" style="padding:0;">
                    <div class="tbl-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>No Booking</th>
                                    <th>Customer</th>
                                    <th>Komplek / Blok / Unit</th>
                                    <th>Tanggal Booking</th>
                                    <th>Booking Fee</th>
                                    <th>Status Booking</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($bookings)): ?>
                                    <tr><td colspan="6" class="empty">Tidak ada booking terkonfirmasi pada periode ini.</td></tr>
                                <?php else: foreach($bookings as $b): ?>
                                    <tr>
                                        <td><b>#BKN-<?= $b['id_booking'] ?></b></td>
                                        <td><?= htmlspecialchars($b['nama_lengkap']) ?></td>
                                        <td><?= htmlspecialchars($b['nama_perumahan']) ?> &bull; Blok <?= htmlspecialchars($b['blok'].'-'.$b['kode_unit']) ?></td>
                                        <td><?= format_tanggal($b['tanggal_booking']) ?></td>
                                        <td style="font-weight:700;"><?= format_rupiah($b['booking_fee']) ?></td>
                                        <td><?= badge_booking($b['status_booking']) ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- LAPORAN DETAIL KPR -->
            <div class="panel">
                <div class="panel-header">
                    <h3>📝 Riwayat Pengajuan KPR Masuk</h3>
                </div>
                <div class="panel-body" style="padding:0;">
                    <div class="tbl-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>No Pengajuan</th>
                                    <th>Customer</th>
                                    <th>Komplek & Unit</th>
                                    <th>Mitra Bank</th>
                                    <th>Uang Muka (DP)</th>
                                    <th>Tenor</th>
                                    <th>Status KPR</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($kprs)): ?>
                                    <tr><td colspan="7" class="empty">Tidak ada pengajuan KPR pada periode ini.</td></tr>
                                <?php else: foreach($kprs as $k): ?>
                                    <tr>
                                        <td><b>#KPR-<?= $k['id_pengajuan'] ?></b></td>
                                        <td><?= htmlspecialchars($k['nama_lengkap']) ?></td>
                                        <td><?= htmlspecialchars($k['nama_perumahan']) ?> &bull; Blok <?= htmlspecialchars($k['blok'].'-'.$k['kode_unit']) ?></td>
                                        <td><?= htmlspecialchars($k['nama_bank']) ?></td>
                                        <td><?= format_rupiah($k['uang_muka']) ?></td>
                                        <td><?= $k['tenor'] ?> Tahun</td>
                                        <td><?= badge_kpr($k['status_pengajuan']) ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
