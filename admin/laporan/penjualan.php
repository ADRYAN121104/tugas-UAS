<?php
// admin/laporan/penjualan.php — Laporan Komprehensif KPR
require_once '../../config/koneksi.php';
require_once '../../config/cek_admin.php';
require_once '../../config/functions.php';
require_once '../../includes/sidebar_admin.php';

$tgl_mulai   = $_GET['tgl_mulai'] ?? date('Y-m-01');
$tgl_selesai = $_GET['tgl_selesai'] ?? date('Y-m-d');

// ── 1. STATISTIK RINGKAS ──────────────────────────────────────────────────────

// Total booking terkonfirmasi
$st = $db->prepare("SELECT COUNT(*), COALESCE(SUM(booking_fee),0) FROM booking WHERE status_booking='dikonfirmasi' AND tanggal_booking BETWEEN ? AND ?");
$st->execute([$tgl_mulai, $tgl_selesai]);
[$total_booking_count, $total_booking_fee] = $st->fetch(PDO::FETCH_NUM);

// Total rumah selesai akad (terjual)
$st2 = $db->prepare("SELECT COUNT(*) FROM pengajuan_kpr WHERE status_pengajuan='akad_kredit' AND tanggal_pengajuan BETWEEN ? AND ?");
$st2->execute([$tgl_mulai, $tgl_selesai]);
$total_akad = $st2->fetchColumn();

// Total DP masuk (valid)
$st3 = $db->prepare("SELECT COALESCE(SUM(dp.jumlah_dp),0) FROM pembayaran_dp dp JOIN pengajuan_kpr pk ON dp.id_pengajuan=pk.id_pengajuan WHERE dp.status_verifikasi='valid' AND DATE(dp.created_at) BETWEEN ? AND ?");
$st3->execute([$tgl_mulai, $tgl_selesai]);
$total_dp = $st3->fetchColumn();

// Total cicilan masuk (lunas)
$st4 = $db->prepare("SELECT COALESCE(SUM(jumlah_cicilan),0), COUNT(*) FROM cicilan_kpr WHERE status_bayar='lunas' AND DATE(tanggal_bayar) BETWEEN ? AND ?");
$st4->execute([$tgl_mulai, $tgl_selesai]);
[$total_cicilan, $total_cicilan_count] = $st4->fetch(PDO::FETCH_NUM);

// Total KPR rumah sedang proses (bukan akad, bukan ditolak)
$st5 = $db->prepare("SELECT COUNT(*) FROM pengajuan_kpr WHERE status_pengajuan NOT IN ('akad_kredit','ditolak') AND tanggal_pengajuan BETWEEN ? AND ?");
$st5->execute([$tgl_mulai, $tgl_selesai]);
$total_proses = $st5->fetchColumn();

// Total pendapatan keseluruhan
$total_pendapatan = $total_booking_fee + $total_dp + $total_cicilan;

// ── 2. DATA DETAIL BOOKING ────────────────────────────────────────────────────
$q_booking = $db->prepare("
    SELECT b.*, u.nama_lengkap, u.email, u.no_hp,
           p.nama_perumahan, r.blok, r.kode_unit, r.nama_tipe, r.harga
    FROM booking b
    JOIN users u ON b.id_user = u.id_user
    JOIN rumah r ON b.id_rumah = r.id_rumah
    JOIN perumahan p ON r.id_perumahan = p.id_perumahan
    WHERE b.status_booking = 'dikonfirmasi' AND b.tanggal_booking BETWEEN ? AND ?
    ORDER BY b.tanggal_booking DESC
");
$q_booking->execute([$tgl_mulai, $tgl_selesai]);
$bookings = $q_booking->fetchAll();

// ── 3. DATA AKAD KREDIT (Selesai) ────────────────────────────────────────────
$q_akad = $db->prepare("
    SELECT pk.*, u.nama_lengkap, u.email, u.no_hp,
           p.nama_perumahan, r.blok, r.kode_unit, r.nama_tipe, r.harga,
           b.nama_bank, b.bunga_kpr,
           dp.jumlah_dp, dp.status_verifikasi as dp_status,
           (SELECT COALESCE(SUM(c.jumlah_cicilan),0) FROM cicilan_kpr c WHERE c.id_pengajuan=pk.id_pengajuan AND c.status_bayar='lunas') as total_cicilan_dibayar,
           (SELECT COUNT(*) FROM cicilan_kpr c WHERE c.id_pengajuan=pk.id_pengajuan AND c.status_bayar='lunas') as jumlah_cicilan_lunas,
           (SELECT COUNT(*) FROM cicilan_kpr c WHERE c.id_pengajuan=pk.id_pengajuan) as total_angsuran
    FROM pengajuan_kpr pk
    JOIN users u ON pk.id_user = u.id_user
    JOIN rumah r ON pk.id_rumah = r.id_rumah
    JOIN perumahan p ON r.id_perumahan = p.id_perumahan
    JOIN bank b ON pk.id_bank = b.id_bank
    LEFT JOIN pembayaran_dp dp ON dp.id_pengajuan=pk.id_pengajuan AND dp.status_verifikasi='valid'
    WHERE pk.status_pengajuan = 'akad_kredit' AND pk.tanggal_pengajuan BETWEEN ? AND ?
    ORDER BY pk.id_pengajuan DESC
");
$q_akad->execute([$tgl_mulai, $tgl_selesai]);
$akad_list = $q_akad->fetchAll();

// ── 4. DATA CICILAN MASUK (periode ini) ──────────────────────────────────────
$q_cic = $db->prepare("
    SELECT c.*, u.nama_lengkap, p.nama_perumahan, r.blok, r.kode_unit
    FROM cicilan_kpr c
    JOIN pengajuan_kpr pk ON c.id_pengajuan = pk.id_pengajuan
    JOIN users u ON pk.id_user = u.id_user
    JOIN rumah r ON pk.id_rumah = r.id_rumah
    JOIN perumahan p ON r.id_perumahan = p.id_perumahan
    WHERE c.status_bayar='lunas' AND DATE(c.tanggal_bayar) BETWEEN ? AND ?
    ORDER BY c.tanggal_bayar DESC
");
$q_cic->execute([$tgl_mulai, $tgl_selesai]);
$cicilan_list = $q_cic->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan KPR Perumahan - Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
    @media print {
        .sidebar,.topbar,.breadcrumb,.filter-panel,.print-btn,.no-print { display:none !important; }
        .admin-main { margin-left:0 !important; }
        .content { padding:0 !important; }
        .panel { border:none !important; box-shadow:none !important; margin-bottom:20px !important; }
        .panel-body { padding:0 !important; }
        body { background:#fff !important; }
        .print-header { display:block !important; }
    }
    .print-header { display:none; text-align:center; margin-bottom:20px; border-bottom:3px double #000; padding-bottom:12px; }
    .rekap-total { background:linear-gradient(135deg,#0f172a,#1e3a8a); border-radius:16px; padding:24px; color:#fff; margin-bottom:24px; }
    .rekap-row { display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid rgba(255,255,255,.12); }
    .rekap-row:last-child { border:none; padding-top:14px; }
    .rekap-label { font-size:14px; opacity:.8; }
    .rekap-val { font-weight:800; font-size:16px; }
    .rekap-total-val { font-size:22px; font-weight:900; background:linear-gradient(135deg,#fbbf24,#f59e0b); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
    .badge-lunas { background:#d1fae5; color:#065f46; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
    .badge-proses { background:#dbeafe; color:#1e40af; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
    </style>
</head>
<body>
    <?php sidebar_admin('laporan'); ?>
    <div class="admin-main">
        <header class="topbar">
            <div style="display:flex;align-items:center;gap:12px;">
                <button class="btn btn-gray btn-sm" id="sidebarToggle" style="padding:6px 10px;">&#9776;</button>
                <div class="topbar-title">Sistem KPR Perumahan</div>
            </div>
            <div class="topbar-right">
                <span class="topbar-name"><?= htmlspecialchars(nama_user()) ?> (<?= ucfirst(role_user()) ?>)</span>
                <div class="topbar-avatar"><?= strtoupper(substr(nama_user(),0,1)) ?></div>
            </div>
        </header>
        <main class="content">
            <!-- Print Header -->
            <div class="print-header">
                <h1 style="font-size:22px;font-weight:900;">LAPORAN PENJUALAN KPR PERUMAHAN</h1>
                <p>Periode: <?= format_tanggal($tgl_mulai) ?> s.d <?= format_tanggal($tgl_selesai) ?></p>
                <p style="font-size:12px;">Dicetak: <?= format_datetime(date('Y-m-d H:i:s')) ?></p>
            </div>

            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a> / <span>Laporan</span>
            </div>

            <div class="page-header">
                <div class="page-header-left">
                    <h2 class="gradient-title">📈 Laporan KPR Perumahan</h2>
                    <p>Data booking, akad, cicilan, dan total pendapatan per periode</p>
                </div>
                <button onclick="window.print()" class="btn btn-primary print-btn">🖨️ Cetak</button>
            </div>

            <!-- FILTER -->
            <div class="panel filter-panel no-print" style="margin-bottom:22px;">
                <div class="panel-header"><h3>📅 Filter Periode Laporan</h3></div>
                <div class="panel-body" style="padding:16px 22px;">
                    <form method="GET" style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;">
                        <div class="form-group" style="margin:0;flex:1;min-width:160px;">
                            <label>Dari Tanggal</label>
                            <input type="date" name="tgl_mulai" class="form-control" value="<?= $tgl_mulai ?>" required>
                        </div>
                        <div class="form-group" style="margin:0;flex:1;min-width:160px;">
                            <label>Sampai Tanggal</label>
                            <input type="date" name="tgl_selesai" class="form-control" value="<?= $tgl_selesai ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary">🔍 Filter</button>
                        <a href="penjualan.php" class="btn btn-gray">Reset</a>
                    </form>
                </div>
            </div>

            <!-- STAT CARDS -->
            <div class="stat-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-bottom:24px;">
                <div class="stat-card">
                    <div class="stat-ico ico-biru">📋</div>
                    <div class="stat-info"><h3><?= $total_booking_count ?></h3><p>Booking Dikonfirmasi</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-ico ico-ungu">🤝</div>
                    <div class="stat-info"><h3><?= $total_akad ?></h3><p>Akad Selesai (Terjual)</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-ico ico-kuning">⚙️</div>
                    <div class="stat-info"><h3><?= $total_proses ?></h3><p>KPR Sedang Proses</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-ico ico-hijau">💳</div>
                    <div class="stat-info"><h3><?= $total_cicilan_count ?></h3><p>Cicilan Diterima</p></div>
                </div>
            </div>

            <!-- REKAP TOTAL PENDAPATAN -->
            <div class="rekap-total">
                <div style="font-size:13px;opacity:.7;margin-bottom:12px;text-transform:uppercase;letter-spacing:.5px;">Rekapitulasi Pendapatan — <?= format_tanggal($tgl_mulai) ?> s.d <?= format_tanggal($tgl_selesai) ?></div>
                <div class="rekap-row">
                    <span class="rekap-label">📋 Total Booking Fee</span>
                    <span class="rekap-val"><?= format_rupiah($total_booking_fee) ?></span>
                </div>
                <div class="rekap-row">
                    <span class="rekap-label">💰 Total DP (Uang Muka) Terverifikasi</span>
                    <span class="rekap-val"><?= format_rupiah($total_dp) ?></span>
                </div>
                <div class="rekap-row">
                    <span class="rekap-label">💳 Total Cicilan Masuk (<?= $total_cicilan_count ?> pembayaran)</span>
                    <span class="rekap-val"><?= format_rupiah($total_cicilan) ?></span>
                </div>
                <div class="rekap-row">
                    <span class="rekap-label" style="font-size:16px;font-weight:800;">TOTAL PENDAPATAN</span>
                    <span class="rekap-total-val"><?= format_rupiah($total_pendapatan) ?></span>
                </div>
            </div>

            <!-- TABEL 1: DATA BOOKING -->
            <div class="panel">
                <div class="panel-header">
                    <h3 style="background:linear-gradient(135deg,#1e40af,#6366f1);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">📋 Daftar Booking Dikonfirmasi</h3>
                    <span style="font-size:12px;color:var(--muted);"><?= count($bookings) ?> data</span>
                </div>
                <div class="panel-body" style="padding:0;">
                    <div class="tbl-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Customer</th>
                                    <th>No. HP</th>
                                    <th>Properti</th>
                                    <th>Tipe Rumah</th>
                                    <th>Harga Rumah</th>
                                    <th>Booking Fee</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($bookings)): ?>
                                <tr><td colspan="8" class="empty">Tidak ada booking terkonfirmasi pada periode ini.</td></tr>
                            <?php else: $no=1; foreach ($bookings as $b): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td>
                                        <b><?= htmlspecialchars($b['nama_lengkap']) ?></b><br>
                                        <small style="color:var(--muted);"><?= htmlspecialchars($b['email']) ?></small>
                                    </td>
                                    <td style="font-size:12px;"><?= htmlspecialchars($b['no_hp'] ?? '-') ?></td>
                                    <td>
                                        <b><?= htmlspecialchars($b['nama_perumahan']) ?></b><br>
                                        <small style="color:var(--primary);font-weight:700;">Blok <?= htmlspecialchars($b['blok'].'-'.$b['kode_unit']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($b['nama_tipe'] ?? '-') ?></td>
                                    <td style="font-weight:700;"><?= format_rupiah($b['harga'] ?? 0) ?></td>
                                    <td style="font-weight:800;color:var(--success);"><?= format_rupiah($b['booking_fee']) ?></td>
                                    <td style="font-size:12px;"><?= format_tanggal($b['tanggal_booking']) ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TABEL 2: AKAD KREDIT SELESAI (paling penting) -->
            <div class="panel">
                <div class="panel-header">
                    <h3 style="background:linear-gradient(135deg,#059669,#0891b2);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">🤝 Rumah Selesai Akad Kredit</h3>
                    <span style="font-size:12px;color:var(--muted);"><?= count($akad_list) ?> unit terjual</span>
                </div>
                <div class="panel-body" style="padding:0;">
                    <div class="tbl-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Customer</th>
                                    <th>Properti</th>
                                    <th>Harga Rumah</th>
                                    <th>DP Dibayar</th>
                                    <th>Total Cicilan Dibayar</th>
                                    <th>Total Keseluruhan</th>
                                    <th>Progres Cicilan</th>
                                    <th>Bank</th>
                                    <th>Tenor</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($akad_list)): ?>
                                <tr><td colspan="10" class="empty">Belum ada KPR yang selesai akad kredit pada periode ini.</td></tr>
                            <?php else: $no=1; foreach ($akad_list as $a): ?>
                                <?php
                                $total_dibayar = ($a['jumlah_dp'] ?? 0) + $a['total_cicilan_dibayar'];
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td>
                                        <b><?= htmlspecialchars($a['nama_lengkap']) ?></b><br>
                                        <small style="color:var(--muted);font-size:11px;"><?= htmlspecialchars($a['email']) ?></small><br>
                                        <small style="color:var(--muted);font-size:11px;"><?= htmlspecialchars($a['no_hp']) ?></small>
                                    </td>
                                    <td>
                                        <b><?= htmlspecialchars($a['nama_perumahan']) ?></b><br>
                                        <small style="color:var(--primary);font-weight:700;">Blok <?= htmlspecialchars($a['blok'].'-'.$a['kode_unit']) ?></small><br>
                                        <small><?= htmlspecialchars($a['nama_tipe']) ?></small>
                                    </td>
                                    <td style="font-weight:800;color:var(--primary);"><?= format_rupiah($a['harga']) ?></td>
                                    <td style="font-weight:700;color:var(--success);"><?= format_rupiah($a['jumlah_dp'] ?? 0) ?></td>
                                    <td style="font-weight:700;color:#6366f1;"><?= format_rupiah($a['total_cicilan_dibayar']) ?></td>
                                    <td style="font-weight:900;color:var(--primary);background:#eff6ff;"><?= format_rupiah($total_dibayar) ?></td>
                                    <td>
                                        <?php if ($a['total_angsuran'] > 0): ?>
                                            <div style="display:flex;align-items:center;gap:6px;">
                                                <div style="flex:1;height:6px;background:#e2e8f0;border-radius:3px;overflow:hidden;">
                                                    <div style="height:100%;width:<?= round($a['jumlah_cicilan_lunas']/$a['total_angsuran']*100) ?>%;background:linear-gradient(90deg,#10b981,#059669);border-radius:3px;"></div>
                                                </div>
                                                <small style="font-weight:700;color:var(--success);white-space:nowrap;"><?= $a['jumlah_cicilan_lunas'] ?>/<?= $a['total_angsuran'] ?></small>
                                            </div>
                                        <?php else: ?>
                                            <small style="color:var(--muted);">Belum ada</small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size:12px;"><?= htmlspecialchars($a['nama_bank']) ?> (<?= $a['bunga_kpr'] ?>%)</td>
                                    <td><?= $a['tenor'] ?> th</td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                            <?php if (!empty($akad_list)): ?>
                            <tfoot>
                                <tr style="background:linear-gradient(135deg,#f0f9ff,#eff6ff);font-weight:800;">
                                    <td colspan="4" style="padding:12px 16px;font-weight:800;">TOTAL</td>
                                    <td style="font-weight:900;color:var(--success);"><?= format_rupiah(array_sum(array_column($akad_list, 'jumlah_dp'))) ?></td>
                                    <td style="font-weight:900;color:#6366f1;"><?= format_rupiah(array_sum(array_column($akad_list, 'total_cicilan_dibayar'))) ?></td>
                                    <td style="font-weight:900;color:var(--primary);" colspan="4"><?= format_rupiah(array_sum(array_column($akad_list, 'jumlah_dp')) + array_sum(array_column($akad_list, 'total_cicilan_dibayar'))) ?></td>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TABEL 3: CICILAN MASUK PERIODE INI -->
            <div class="panel">
                <div class="panel-header">
                    <h3 style="background:linear-gradient(135deg,#7c3aed,#6366f1);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">💳 Cicilan Diterima Periode Ini</h3>
                    <span style="font-size:12px;color:var(--muted);"><?= count($cicilan_list) ?> transaksi · Total: <?= format_rupiah($total_cicilan) ?></span>
                </div>
                <div class="panel-body" style="padding:0;">
                    <div class="tbl-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Customer</th>
                                    <th>Properti</th>
                                    <th>Bulan Ke</th>
                                    <th>Jumlah Cicilan</th>
                                    <th>Tanggal Bayar</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($cicilan_list)): ?>
                                <tr><td colspan="6" class="empty">Belum ada cicilan masuk pada periode ini.</td></tr>
                            <?php else: $no=1; foreach ($cicilan_list as $c): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><b><?= htmlspecialchars($c['nama_lengkap']) ?></b></td>
                                    <td>
                                        <?= htmlspecialchars($c['nama_perumahan']) ?><br>
                                        <small style="color:var(--primary);font-weight:700;">Blok <?= htmlspecialchars($c['blok'].'-'.$c['kode_unit']) ?></small>
                                    </td>
                                    <td style="text-align:center;"><b>Bulan <?= $c['bulan_ke'] ?></b></td>
                                    <td style="font-weight:800;color:#6366f1;"><?= format_rupiah($c['jumlah_cicilan']) ?></td>
                                    <td style="font-size:12px;"><?= format_datetime($c['tanggal_bayar']) ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                            <?php if (!empty($cicilan_list)): ?>
                            <tfoot>
                                <tr style="background:#f0fdf4;font-weight:900;">
                                    <td colspan="4" style="padding:12px 16px;">TOTAL CICILAN</td>
                                    <td style="color:#059669;"><?= format_rupiah($total_cicilan) ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </div>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
