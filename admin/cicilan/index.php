<?php
// admin/cicilan/index.php - Kelola Cicilan & Pembayaran DP KPR (Modal Detail)
require_once '../../config/koneksi.php';
require_once '../../config/cek_admin.php';
require_once '../../config/functions.php';
require_once '../../includes/sidebar_admin.php';

$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

// ── VERIFIKASI PEMBAYARAN CICILAN ───────────────────────────────────────────
if ($id > 0 && in_array($action, ['valid', 'tolak'])) {
    $stmt = $db->prepare("SELECT * FROM cicilan_kpr WHERE id_cicilan = ?");
    $stmt->execute([$id]);
    $cic = $stmt->fetch();
    if ($cic) {
        $id_pengajuan_redir = $cic['id_pengajuan'];
        if ($action === 'valid') {
            $db->prepare("UPDATE cicilan_kpr SET status_verifikasi='valid', status_bayar='lunas' WHERE id_cicilan=?")->execute([$id]);
            set_flash('sukses', '✅ Pembayaran cicilan bulan ke-' . $cic['bulan_ke'] . ' diverifikasi VALID.');
        } else {
            $db->prepare("UPDATE cicilan_kpr SET status_verifikasi='ditolak', status_bayar='belum', tanggal_bayar=NULL, bukti_bayar=NULL WHERE id_cicilan=?")->execute([$id]);
            set_flash('gagal', '❌ Pembayaran cicilan ditolak. Customer perlu kirim ulang bukti.');
        }
    }
    header('Location: index.php' . ($id_pengajuan_redir ? '?id_pengajuan='.$id_pengajuan_redir : ''));
    exit;
}

// ── VERIFIKASI PEMBAYARAN DP (Disatukan ke halaman ini) ─────────────────────
if ($id > 0 && in_array($action, ['valid_dp', 'tolak_dp'])) {
    $stmt = $db->prepare("
        SELECT dp.*, pk.id_rumah 
        FROM pembayaran_dp dp 
        JOIN pengajuan_kpr pk ON dp.id_pengajuan = pk.id_pengajuan 
        WHERE dp.id_dp = ?
    ");
    $stmt->execute([$id]);
    $pay = $stmt->fetch();

    if ($pay) {
        $id_pengajuan = $pay['id_pengajuan'];
        $id_rumah     = $pay['id_rumah'];

        if ($action === 'valid_dp') {
            $db->beginTransaction();
            // 1. Set DP valid
            $db->prepare("UPDATE pembayaran_dp SET status_verifikasi = 'valid' WHERE id_dp = ?")->execute([$id]);
            // 2. KPR masuk ke tahap akad kredit
            $db->prepare("UPDATE pengajuan_kpr SET status_pengajuan = 'akad_kredit' WHERE id_pengajuan = ?")->execute([$id_pengajuan]);
            // 3. Log tracking
            $db->prepare("INSERT INTO tracking_pengajuan (id_pengajuan, status, keterangan, tanggal_update) VALUES (?, 'akad_kredit', 'Pembayaran Uang Muka (DP) tervalidasi VALID. Pengajuan berlanjut ke tahap Akad Kredit.', NOW())")->execute([$id_pengajuan]);
            // 4. Kunci unit rumah sebagai terjual
            $db->prepare("UPDATE rumah SET status = 'terjual' WHERE id_rumah = ?")->execute([$id_rumah]);

            // ── 5. AUTO-GENERATE JADWAL CICILAN ──────────────────────────────
            // Cek apakah cicilan sudah pernah dibuat
            $cek_cic = $db->prepare("SELECT COUNT(*) FROM cicilan_kpr WHERE id_pengajuan=?");
            $cek_cic->execute([$id_pengajuan]);
            if ($cek_cic->fetchColumn() == 0) {
                // Ambil detail KPR untuk menghitung cicilan
                $kpr_gen = $db->prepare("
                    SELECT pk.uang_muka, pk.tenor, b.bunga_kpr, r.harga
                    FROM pengajuan_kpr pk
                    JOIN bank b ON pk.id_bank = b.id_bank
                    JOIN rumah r ON pk.id_rumah = r.id_rumah
                    WHERE pk.id_pengajuan = ?
                ");
                $kpr_gen->execute([$id_pengajuan]);
                $kpr_g = $kpr_gen->fetch();
                if ($kpr_g) {
                    $harga_g       = (float)$kpr_g['harga'];
                    $dp_g          = (float)$pay['jumlah_dp']; // Gunakan nominal DP yang dibayar
                    $pokok_pinjaman= max(0, $harga_g - $dp_g);
                    $bunga_pa      = (float)$kpr_g['bunga_kpr'] / 100;
                    $tenor_bulan   = (int)$kpr_g['tenor'] * 12;
                    $bunga_pb      = $bunga_pa / 12;

                    if ($pokok_pinjaman > 0 && $tenor_bulan > 0) {
                        if ($bunga_pb > 0) {
                            $cicilan_bulanan = $pokok_pinjaman * ($bunga_pb * pow(1 + $bunga_pb, $tenor_bulan)) / (pow(1 + $bunga_pb, $tenor_bulan) - 1);
                        } else {
                            $cicilan_bulanan = $pokok_pinjaman / $tenor_bulan;
                        }

                        $saldo_pokok = $pokok_pinjaman;
                        $tgl_mulai   = new DateTime();
                        $tgl_mulai->modify('+1 month');

                        for ($bulan = 1; $bulan <= $tenor_bulan; $bulan++) {
                            $bunga_bln    = $saldo_pokok * $bunga_pb;
                            $cicilan_pokok= $cicilan_bulanan - $bunga_bln;
                            $saldo_pokok -= $cicilan_pokok;
                            if ($saldo_pokok < 0) $saldo_pokok = 0;

                            $tgl_jatuh = clone $tgl_mulai;
                            $tgl_jatuh->modify('+' . ($bulan - 1) . ' month');

                            $db->prepare("INSERT INTO cicilan_kpr (id_pengajuan, bulan_ke, tanggal_jatuh_tempo, jumlah_cicilan, pokok, bunga, status_bayar) VALUES (?, ?, ?, ?, ?, ?, 'belum')")
                               ->execute([$id_pengajuan, $bulan, $tgl_jatuh->format('Y-m-d'), round($cicilan_bulanan, 2), round($cicilan_pokok, 2), round($bunga_bln, 2)]);
                        }
                    }
                }
            }
            // ── END AUTO-GENERATE ─────────────────────────────────────────────

            $db->commit();
            set_flash('sukses', '✅ Pembayaran DP diverifikasi VALID. Unit KPR diperbarui ke tahap Akad Kredit, status rumah Terjual, dan jadwal cicilan otomatis dibuat.');
        } elseif ($action === 'tolak_dp') {
            $db->beginTransaction();
            $db->prepare("UPDATE pembayaran_dp SET status_verifikasi = 'ditolak' WHERE id_dp = ?")->execute([$id]);
            $db->prepare("INSERT INTO tracking_pengajuan (id_pengajuan, status, keterangan, tanggal_update) VALUES (?, 'disetujui', 'Bukti pembayaran DP ditolak oleh admin. Customer diminta mengunggah ulang bukti transfer yang valid.', NOW())")->execute([$id_pengajuan]);
            $db->commit();
            set_flash('gagal', '❌ Pembayaran DP ditolak. Customer akan diminta mengirim ulang bukti.');
        }
    }
    header('Location: index.php' . ($id_pengajuan > 0 ? '?id_pengajuan=' . $id_pengajuan : ''));
    exit;
}

// ── GENERATE JADWAL CICILAN ──────────────────────────────────────────────────
if ($action === 'generate' && $id > 0) {
    $stmt = $db->prepare("
        SELECT pk.*, r.harga, b.bunga_kpr, b.tenor_maksimal
        FROM pengajuan_kpr pk
        JOIN rumah r ON pk.id_rumah = r.id_rumah
        JOIN bank b ON pk.id_bank = b.id_bank
        WHERE pk.id_pengajuan = ? AND pk.status_pengajuan = 'akad_kredit'
    ");
    $stmt->execute([$id]);
    $kpr = $stmt->fetch();
    if ($kpr) {
        $cek = $db->prepare("SELECT COUNT(*) FROM cicilan_kpr WHERE id_pengajuan=?");
        $cek->execute([$id]);
        if ($cek->fetchColumn() > 0) {
            set_flash('gagal', 'Jadwal cicilan sudah pernah dibuat untuk pengajuan ini.');
        } else {
            $harga      = (float)$kpr['harga'];
            $dp         = (float)$kpr['uang_muka'];
            $pokok_pinjaman = $harga - $dp;
            $bunga_pa   = (float)$kpr['bunga_kpr'] / 100;
            $tenor_bulan = (int)$kpr['tenor'] * 12;
            $bunga_pb   = $bunga_pa / 12;

            if ($bunga_pb > 0) {
                $cicilan_bulanan = $pokok_pinjaman * ($bunga_pb * pow(1 + $bunga_pb, $tenor_bulan)) / (pow(1 + $bunga_pb, $tenor_bulan) - 1);
            } else {
                $cicilan_bulanan = $pokok_pinjaman / $tenor_bulan;
            }

            $saldo_pokok = $pokok_pinjaman;
            $tgl_mulai = new DateTime();
            $tgl_mulai->modify('+1 month');

            $db->beginTransaction();
            for ($bulan = 1; $bulan <= $tenor_bulan; $bulan++) {
                $bunga = $saldo_pokok * $bunga_pb;
                $cicilan_pokok = $cicilan_bulanan - $bunga;
                $saldo_pokok -= $cicilan_pokok;
                if ($saldo_pokok < 0) $saldo_pokok = 0;

                $tgl_jatuh = clone $tgl_mulai;
                $tgl_jatuh->modify('+' . ($bulan - 1) . ' month');

                $ins = $db->prepare("INSERT INTO cicilan_kpr (id_pengajuan, bulan_ke, tanggal_jatuh_tempo, jumlah_cicilan, pokok, bunga, status_bayar) VALUES (?, ?, ?, ?, ?, ?, 'belum')");
                $ins->execute([$id, $bulan, $tgl_jatuh->format('Y-m-d'), round($cicilan_bulanan, 2), round($cicilan_pokok, 2), round($bunga, 2)]);
            }
            $db->commit();
            set_flash('sukses', '✅ Jadwal cicilan ' . $tenor_bulan . ' bulan berhasil dibuat!');
        }
    }
    header('Location: index.php?id_pengajuan=' . $id);
    exit;
}

// ── DATA ─────────────────────────────────────────────────────────────────────
$id_pengajuan = (int)($_GET['id_pengajuan'] ?? 0);

// List pengajuan yang sudah disetujui / akad
$stmt_kpr = $db->prepare("
    SELECT pk.id_pengajuan, pk.status_pengajuan, pk.uang_muka, pk.tenor,
           pk.id_user, pk.id_rumah,
           u.nama_lengkap, b.nama_bank, b.bunga_kpr,
           p.nama_perumahan, r.blok, r.kode_unit, r.harga,
           (SELECT COUNT(*) FROM cicilan_kpr c WHERE c.id_pengajuan=pk.id_pengajuan) as jml_cicilan,
           (SELECT COUNT(*) FROM cicilan_kpr c WHERE c.id_pengajuan=pk.id_pengajuan AND c.status_bayar='lunas') as jml_lunas,
           (SELECT COALESCE(SUM(jumlah_cicilan),0) FROM cicilan_kpr c WHERE c.id_pengajuan=pk.id_pengajuan AND c.status_bayar='lunas') as total_cicilan_lunas,
           (SELECT jumlah_dp FROM pembayaran_dp WHERE id_pengajuan=pk.id_pengajuan AND status_verifikasi='valid' LIMIT 1) as dp_masuk,
           (SELECT status_verifikasi FROM pembayaran_dp WHERE id_pengajuan=pk.id_pengajuan ORDER BY created_at DESC LIMIT 1) as dp_status,
           (SELECT jumlah_cicilan FROM cicilan_kpr c WHERE c.id_pengajuan=pk.id_pengajuan ORDER BY bulan_ke ASC LIMIT 1) as cicilan_per_bulan
    FROM pengajuan_kpr pk
    JOIN users u ON pk.id_user = u.id_user
    JOIN rumah r ON pk.id_rumah = r.id_rumah
    JOIN perumahan p ON r.id_perumahan = p.id_perumahan
    JOIN bank b ON pk.id_bank = b.id_bank
    WHERE pk.status_pengajuan IN ('disetujui','akad_kredit')
    ORDER BY pk.id_pengajuan DESC
");
$stmt_kpr->execute();
$list_kpr = $stmt_kpr->fetchAll();

// Cicilan & DP per pengajuan
$cicilan_detail = [];
$kpr_detail = null;
$dp_data = null;
$booking_fee = 0.0;

if ($id_pengajuan > 0) {
    $dk = $db->prepare("
        SELECT pk.id_pengajuan, pk.uang_muka, pk.tenor, pk.id_user, pk.id_rumah,
               u.nama_lengkap, b.nama_bank, b.bunga_kpr,
               p.nama_perumahan, r.blok, r.kode_unit, r.harga
        FROM pengajuan_kpr pk
        JOIN users u ON pk.id_user = u.id_user
        JOIN rumah r ON pk.id_rumah = r.id_rumah
        JOIN perumahan p ON r.id_perumahan = p.id_perumahan
        JOIN bank b ON pk.id_bank = b.id_bank
        WHERE pk.id_pengajuan=?
    ");
    $dk->execute([$id_pengajuan]);
    $kpr_detail = $dk->fetch();

    if ($kpr_detail) {
        $dc = $db->prepare("SELECT * FROM cicilan_kpr WHERE id_pengajuan=? ORDER BY bulan_ke ASC");
        $dc->execute([$id_pengajuan]);
        $cicilan_detail = $dc->fetchAll();

        // Ambil data DP
        $dp_q_adm = $db->prepare("SELECT * FROM pembayaran_dp WHERE id_pengajuan=? ORDER BY created_at DESC LIMIT 1");
        $dp_q_adm->execute([$id_pengajuan]);
        $dp_data = $dp_q_adm->fetch();

        // Ambil Booking Fee
        $q_bf = $db->prepare("SELECT booking_fee FROM booking WHERE id_user=? AND id_rumah=? AND status_booking='dikonfirmasi' ORDER BY id_booking DESC LIMIT 1");
        $q_bf->execute([$kpr_detail['id_user'], $kpr_detail['id_rumah']]);
        $booking_fee = (float)($q_bf->fetchColumn() ?: 0);
    }
}

// Ringkasan total
$total_cicilan_valid   = (float)$db->query("SELECT COALESCE(SUM(jumlah_cicilan),0) FROM cicilan_kpr WHERE status_bayar='lunas'")->fetchColumn();
$total_cicilan_pending = (float)$db->query("SELECT COALESCE(SUM(jumlah_cicilan),0) FROM cicilan_kpr WHERE status_verifikasi='pending'")->fetchColumn();
$total_cicilan_belum   = (float)$db->query("SELECT COUNT(*) FROM cicilan_kpr WHERE status_bayar='belum'")->fetchColumn();
$total_dp_valid        = (float)$db->query("SELECT COALESCE(SUM(jumlah_dp),0) FROM pembayaran_dp WHERE status_verifikasi='valid'")->fetchColumn();
$total_dp_pending      = (int)$db->query("SELECT COUNT(*) FROM pembayaran_dp WHERE status_verifikasi='pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Keuangan KPR - RumahKPR Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css?v=3">
</head>
<body>
    <?php sidebar_admin('cicilan'); ?>
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
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a> / <span>Kelola Keuangan & Cicilan</span>
            </div>
            <?php tampil_flash(); ?>

            <div class="page-header">
                <div class="page-header-left">
                    <h2 class="gradient-title-green">💳 Keuangan KPR: DP & Cicilan</h2>
                    <p>Verifikasi pembayaran Uang Muka (DP), generate cicilan, dan verifikasi cicilan bulanan</p>
                </div>
                <?php if ($total_dp_pending > 0): ?>
                <div style="background:#fef3c7; border:1px solid #fbbf24; border-radius:10px; padding:10px 18px; display:flex; align-items:center; gap:10px;">
                    <span style="font-size:20px;">⚠️</span>
                    <div style="font-weight:800; font-size:13px; color:#92400e;">
                        <?= $total_dp_pending ?> Pembayaran DP Baru Menunggu Verifikasi
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- STAT CARDS -->
            <div class="stat-grid" style="margin-bottom:24px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));">
                <div class="stat-card">
                    <div class="stat-ico ico-hijau">💳</div>
                    <div class="stat-info"><h3><?= format_rupiah($total_cicilan_valid) ?></h3><p>Cicilan Terverifikasi</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-ico ico-ungu">🏦</div>
                    <div class="stat-info"><h3><?= format_rupiah($total_dp_valid) ?></h3><p>DP Terverifikasi</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-ico" style="background:linear-gradient(135deg,#059669,#0891b2);color:#fff;">💰</div>
                    <div class="stat-info"><h3><?= format_rupiah($total_dp_valid + $total_cicilan_valid) ?></h3><p>Total Dana Masuk</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-ico ico-kuning">⏳</div>
                    <div class="stat-info"><h3><?= format_rupiah($total_cicilan_pending) ?></h3><p>Cicilan Pending</p></div>
                </div>
            </div>

            <!-- DAFTAR KPR AKTIF -->
            <div class="panel">
                <div class="panel-header">
                    <h3>📋 Daftar Pengajuan KPR Aktif</h3>
                </div>
                <div class="panel-body" style="padding:0;">
                    <div class="tbl-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Properti</th>
                                    <th>Bank & Bunga</th>
                                    <th>Rincian Keuangan</th>
                                    <th>Tenor</th>
                                    <th>Status KPR</th>
                                    <th>Progres Cicilan</th>
                                    <th style="text-align:center; width:150px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($list_kpr)): ?>
                                <tr><td colspan="8" class="empty">Belum ada pengajuan KPR yang disetujui / akad kredit.</td></tr>
                            <?php else: foreach ($list_kpr as $k):
                                // Hitung booking fee per customer
                                $q_bf2 = $db->prepare("SELECT booking_fee FROM booking WHERE id_user=? AND id_rumah=? AND status_booking='dikonfirmasi' ORDER BY id_booking DESC LIMIT 1");
                                $q_bf2->execute([$k['id_user'], $k['id_rumah']]);
                                $bf2 = (float)($q_bf2->fetchColumn() ?: 0);

                                $dp_masuk_k      = (float)($k['dp_masuk'] ?? 0);
                                $cicilan_masuk_k = (float)($k['total_cicilan_lunas'] ?? 0);
                                $total_masuk_k   = $bf2 + $dp_masuk_k + $cicilan_masuk_k;
                                $harga_k         = (float)$k['harga'];
                                $sisa_k          = max(0, $harga_k - $total_masuk_k);
                                $cicilan_pb_k    = (float)($k['cicilan_per_bulan'] ?? 0);
                                $sisa_bulan_k    = ($sisa_k > 0 && $cicilan_pb_k > 0)
                                    ? min($k['jml_cicilan'] - $k['jml_lunas'], (int)ceil($sisa_k / $cicilan_pb_k))
                                    : 0;
                                ?>
                                <tr style="transition: background 0.2s; border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 16px 12px; vertical-align: middle;">
                                        <span style="font-weight: 800; font-size: 14px; color: #0f172a; display: block;"><?= htmlspecialchars($k['nama_lengkap']) ?></span>
                                        <small style="color: #64748b; font-weight: 500;">ID KPR: #<?= $k['id_pengajuan'] ?></small>
                                    </td>
                                    <td style="padding: 16px 12px; vertical-align: middle;">
                                        <span style="font-weight: 800; color: #1e293b; display: block; font-size: 13.5px;"><?= htmlspecialchars($k['nama_perumahan']) ?></span>
                                        <span style="display: inline-block; background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 6px; font-weight: 700; font-size: 11px; margin-top: 4px;">Blok <?= htmlspecialchars($k['blok'].'-'.$k['kode_unit']) ?></span>
                                    </td>
                                    <td style="padding: 16px 12px; vertical-align: middle;">
                                        <span style="font-weight: 700; color: #334155; display: block;"><?= htmlspecialchars($k['nama_bank']) ?></span>
                                        <span style="color: #059669; font-weight: 700; font-size: 12px;">📊 Bunga: <?= $k['bunga_kpr'] ?>% / th</span>
                                    </td>
                                    <td style="padding: 16px 12px; min-width: 250px;">
                                        <?php
                                        $dp_s = $k['dp_status'] ?? '';
                                        $dp_badge = $dp_s === 'valid' ? '<span style="background: #d1fae5; color: #065f46; padding: 1px 6px; border-radius: 4px; font-size: 9.5px; font-weight: 800;">✅ Valid</span>'
                                            : ($dp_s === 'pending' ? '<span style="background: #fef3c7; color: #92400e; padding: 1px 6px; border-radius: 4px; font-size: 9.5px; font-weight: 800;">⏳ Pending</span>'
                                            : '<span style="background: #f3f4f6; color: #6b7280; padding: 1px 6px; border-radius: 4px; font-size: 9.5px; font-weight: 700;">— Belum</span>');
                                        ?>
                                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; box-shadow: inset 0 1px 3px rgba(0,0,0,0.02);">
                                            <table style="font-size: 12px; width: 100%; border-collapse: collapse; line-height: 1.8;">
                                                <tr>
                                                    <td style="color: #64748b; font-weight: 600;">🏦 DP Masuk</td>
                                                    <td style="font-weight: 800; color: #d97706; text-align: right;"><?= format_rupiah($dp_masuk_k) ?> <?= $dp_badge ?></td>
                                                </tr>
                                                <tr>
                                                    <td style="color: #64748b; font-weight: 600;">💳 Cicilan Masuk</td>
                                                    <td style="font-weight: 800; color: #10b981; text-align: right;"><?= format_rupiah($cicilan_masuk_k) ?></td>
                                                </tr>
                                                <tr style="border-top: 1px dashed #e2e8f0; margin-top: 4px; padding-top: 4px;">
                                                    <td style="color: #475569; font-weight: 700;">💰 Total Masuk</td>
                                                    <td style="font-weight: 900; color: #1e3a8a; text-align: right;"><?= format_rupiah($total_masuk_k) ?></td>
                                                </tr>
                                                <tr style="background: #fee2e2; border-radius: 6px;">
                                                    <td style="color: #b91c1c; font-weight: 800; padding: 2px 6px;">🚨 Sisa Pelunasan</td>
                                                    <td style="font-weight: 900; color: #b91c1c; text-align: right; padding: 2px 6px;"><?= format_rupiah($sisa_k) ?></td>
                                                </tr>
                                                <?php if ($sisa_bulan_k > 0 && $cicilan_pb_k > 0): ?>
                                                <tr style="background: #e0f2fe; border-radius: 6px;">
                                                    <td style="color: #0369a1; font-weight: 800; padding: 2px 6px;">📅 Sisa Cicilan</td>
                                                    <td style="font-weight: 800; color: #0369a1; text-align: right; padding: 2px 6px;"><?= $sisa_bulan_k ?>× (<?= format_rupiah(min($cicilan_pb_k, $sisa_k)) ?>/bln)</td>
                                                </tr>
                                                <?php elseif ($sisa_k <= 0): ?>
                                                <tr style="background: #d1fae5; border-radius: 6px;">
                                                    <td colspan="2" style="text-align: center; font-weight: 900; color: #065f46; padding: 2px 6px; text-transform: uppercase; font-size: 10px; letter-spacing: 0.5px;">🎉 LUNAS SEPENUHNYA</td>
                                                </tr>
                                                <?php endif; ?>
                                            </table>
                                        </div>
                                    </td>
                                    <td style="padding: 16px 12px; vertical-align: middle; font-weight: 700; color: #334155;"><?= $k['tenor'] ?> tahun</td>
                                    <td style="padding: 16px 12px; vertical-align: middle;"><?= badge_kpr($k['status_pengajuan']) ?></td>
                                    <td style="padding: 16px 12px; vertical-align: middle;">
                                        <?php if ($k['jml_cicilan'] > 0): ?>
                                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                                <div style="display: flex; justify-content: space-between; font-weight: 800; font-size: 11px; color: #059669;">
                                                    <span>Progres</span>
                                                    <span><?= $k['jml_lunas'] ?>/<?= $k['jml_cicilan'] ?> bln</span>
                                                </div>
                                                <div style="height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);">
                                                    <div style="height: 100%; width: <?= round($k['jml_lunas']/$k['jml_cicilan']*100) ?>%; background: linear-gradient(90deg, #10b981, #059669); border-radius: 4px;"></div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-size: 12px; font-weight: 600; font-style: italic;">Belum di-generate</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 16px 12px; vertical-align: middle; text-align: center;">
                                        <div style="display: flex; flex-direction: column; gap: 6px;">
                                            <?php if ($k['jml_cicilan'] == 0 && $k['status_pengajuan']==='akad_kredit'): ?>
                                                <a href="index.php?action=generate&id=<?= $k['id_pengajuan'] ?>"
                                                   class="btn btn-success btn-sm" style="width: 100%; justify-content: center; font-weight: 800; font-size: 12px; box-shadow: 0 2px 6px rgba(16,185,129,0.2);"
                                                   onclick="return confirm('Generate jadwal cicilan untuk KPR ini?\n\nJadwal cicilan akan otomatis dibuat berdasarkan tenor dan bunga bank.')">
                                                    ⚙️ Generate Jadwal
                                                </a>
                                            <?php endif; ?>
                                            <a href="index.php?id_pengajuan=<?= $k['id_pengajuan'] ?>" class="btn-edit" style="width: 100%; justify-content: center; font-weight: 800; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;">
                                                📋 Kelola Detail
                                            </a>
                                            <a href="cetak_kpr.php?id=<?= $k['id_pengajuan'] ?>" target="_blank" class="btn btn-outline btn-sm" style="width: 100%; justify-content: center; font-weight: 800; font-size: 11px; border-color: #64748b; color: #475569; display: inline-flex; align-items: center; gap: 4px;">
                                                📄 Cetak Laporan
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($id_pengajuan > 0 && $kpr_detail): ?>
            <!-- MODAL DETAIL CICILAN & DP -->
            <div id="modal-detail" style="
                position:fixed;inset:0;z-index:9999;
                background:rgba(10,18,40,0.65);backdrop-filter:blur(4px);
                display:flex;align-items:flex-start;justify-content:center;
                padding:20px 16px;overflow-y:auto;
                opacity:0;transition:opacity .25s;
            ">
                <div style="
                    background:#fff;border-radius:20px;width:100%;max-width:1100px;
                    box-shadow:0 30px 80px rgba(0,0,0,.35);
                    transform:translateY(40px);transition:transform .3s ease,opacity .3s ease;
                    opacity:0;margin:auto;
                " id="modal-inner">

                    <!-- MODAL HEADER -->
                    <div style="background:linear-gradient(135deg,#1e3a8a,#2563eb,#0891b2);border-radius:20px 20px 0 0;padding:18px 24px;display:flex;align-items:center;justify-content:space-between;">
                        <div style="color:#fff;">
                            <div style="font-size:11px;opacity:.7;margin-bottom:2px;text-transform:uppercase;letter-spacing:.5px;">Pengelolaan Keuangan KPR</div>
                            <div style="font-size:16px;font-weight:900;"><?= htmlspecialchars($kpr_detail['nama_lengkap']) ?> &mdash; <?= htmlspecialchars($kpr_detail['nama_perumahan']) ?> Blok <?= htmlspecialchars($kpr_detail['blok'].'-'.$kpr_detail['kode_unit']) ?></div>
                        </div>
                        <a href="index.php" id="btn-tutup-modal" style="background:rgba(255,255,255,.15);border:none;color:#fff;width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px;cursor:pointer;text-decoration:none;flex-shrink:0;transition:.2s;" title="Tutup">&times;</a>
                    </div>

                    <!-- BOX VERIFIKASI DP (Jika pending) -->
                    <?php if ($dp_data && $dp_data['status_verifikasi'] === 'pending'): ?>
                    <div style="background:linear-gradient(135deg,#fef3c7,#fffbeb);border:2px solid #fbbf24;border-radius:12px;padding:20px;margin:20px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                        <div>
                            <div style="font-weight:800;color:#92400e;font-size:14px;margin-bottom:4px;">⏳ Uang Muka (DP) Menunggu Verifikasi</div>
                            <div style="font-size:12.5px;color:#b45309;">
                                Customer telah mengirim pembayaran DP sebesar <b><?= format_rupiah($dp_data['jumlah_dp']) ?></b> pada <?= format_datetime($dp_data['tanggal_bayar']) ?>
                            </div>
                            <div style="margin-top:10px;">
                                <a href="../../uploads/bukti_dp/<?= htmlspecialchars($dp_data['bukti_dp']) ?>" target="_blank" class="btn btn-outline btn-sm" style="border-color:#fbbf24;color:#b45309;font-weight:700;">📎 Lihat Bukti Transfer DP</a>
                            </div>
                        </div>
                        <div class="aksi-table" style="flex-wrap:nowrap;">
                            <a href="index.php?action=valid_dp&id=<?= $dp_data['id_dp'] ?>" class="btn-edit" onclick="return confirm('Konfirmasi pembayaran DP VALID? Ini akan mengunci status rumah Terjual dan masuk tahap akad.')">✅ Set Valid</a>
                            <a href="index.php?action=tolak_dp&id=<?= $dp_data['id_dp'] ?>" class="btn-delete" onclick="return confirm('Tolak pembayaran DP ini?')">❌ Tolak</a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php
                    $dp_val = (float)(($dp_data && $dp_data['status_verifikasi'] === 'valid') ? $dp_data['jumlah_dp'] : 0);
                    $harga_rumah = (float)($kpr_detail['harga'] ?? 0);
                    
                    $total_lunas = 0; $total_belum = 0;
                    foreach ($cicilan_detail as $c) {
                        if ($c['status_bayar'] === 'lunas') $total_lunas += $c['jumlah_cicilan'];
                        else $total_belum += $c['jumlah_cicilan'];
                    }

                    $total_sudah_bayar = $booking_fee + $dp_val + $total_lunas;
                    $sisa_harga_rumah  = max(0, $harga_rumah - $total_sudah_bayar);
                    $persen_harga = $harga_rumah > 0 ? round(($total_sudah_bayar / $harga_rumah) * 100) : 0;
                    $persen_lunas = count($cicilan_detail) > 0 ? round(count(array_filter($cicilan_detail, fn($c) => $c['status_bayar']==='lunas')) / count($cicilan_detail) * 100) : 0;
                    ?>
                    
                    <!-- REKAP KEUANGAN DETAIL -->
                    <div style="padding:18px 20px;background:linear-gradient(135deg,#f0fdf4,#eff6ff);border-bottom:1px solid var(--border);display:grid;grid-template-columns:repeat(auto-fit,minmax(155px,1fr));gap:12px;">
                        <div style="background:#fff;padding:10px 12px;border-radius:8px;border:1px solid #e2e8f0;text-align:center;">
                            <div style="font-size:10px;color:var(--muted);margin-bottom:3px;text-transform:uppercase;">Harga Properti Rumah</div>
                            <div style="font-size:14px;font-weight:800;color:var(--primary);"><?= format_rupiah($harga_rumah) ?></div>
                        </div>
                        <div style="background:#fff;padding:10px 12px;border-radius:8px;border:1px solid #e2e8f0;text-align:center;">
                            <div style="font-size:10px;color:var(--muted);margin-bottom:3px;text-transform:uppercase;">Booking Fee</div>
                            <div style="font-size:14px;font-weight:800;color:#0284c7;"><?= format_rupiah($booking_fee) ?></div>
                        </div>
                        <div style="background:#fff;padding:10px 12px;border-radius:8px;border:1px solid #e2e8f0;text-align:center;">
                            <div style="font-size:10px;color:var(--muted);margin-bottom:3px;text-transform:uppercase;">DP (Uang Muka)</div>
                            <div style="font-size:14px;font-weight:800;color:#d97706;"><?= format_rupiah($dp_val) ?> <?= $dp_data ? badge_pembayaran($dp_data['status_verifikasi']) : '<span style="color:var(--danger)">Belum</span>' ?></div>
                        </div>
                        <div style="background:#fff;padding:10px 12px;border-radius:8px;border:1px solid #e2e8f0;text-align:center;">
                            <div style="font-size:10px;color:var(--muted);margin-bottom:3px;text-transform:uppercase;">Cicilan Terbayar</div>
                            <div style="font-size:14px;font-weight:800;color:var(--success);"><?= format_rupiah($total_lunas) ?></div>
                        </div>
                        <div style="background:#fff;padding:10px 12px;border-radius:8px;border:1px solid #e2e8f0;text-align:center;">
                            <div style="font-size:10px;color:var(--muted);margin-bottom:3px;text-transform:uppercase;">Total Pembayaran Masuk</div>
                            <div style="font-size:14px;font-weight:800;color:#1e3a8a;"><?= format_rupiah($total_sudah_bayar) ?> (<?= $persen_harga ?>%)</div>
                        </div>
                        <div style="background:#fff5f5;padding:10px 12px;border-radius:8px;border:1px solid #fee2e2;text-align:center;grid-column:span 2;">
                            <div style="font-size:10px;color:#991b1b;margin-bottom:3px;text-transform:uppercase;font-weight:bold;">Sisa Target Pelunasan Rumah</div>
                            <div style="font-size:16px;font-weight:900;color:#ef4444;"><?= format_rupiah($sisa_harga_rumah) ?></div>
                            <div style="font-size:10px;color:#64748b;margin-top:1px;">(Sisa Tagihan Cicilan Berjalan: <?= format_rupiah($total_belum) ?>)</div>
                        </div>
                    </div>

                    <?php if (empty($cicilan_detail)): ?>
                        <div class="empty" style="padding:40px 20px;">
                            <div class="empty-ico">📅</div>
                            <h4>Jadwal Cicilan KPR Belum Di-generate</h4>
                            <p>Pastikan Pembayaran DP customer disetujui VALID, lalu tombol Generate Jadwal akan muncul di daftar.</p>
                        </div>
                    <?php else: ?>
                        <div style="padding:14px 20px;background:#fff;border-bottom:1px solid var(--border);">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                <span style="font-size:13px;font-weight:700;color:#374151;">Progres Cicilan Bulanan</span>
                                <span style="font-size:13px;font-weight:bold;color:var(--success);"><?= count(array_filter($cicilan_detail, fn($c) => $c['status_bayar']==='lunas')) ?> / <?= count($cicilan_detail) ?> Bulan (<?= $persen_lunas ?>%)</span>
                            </div>
                            <div style="height:10px;background:#e2e8f0;border-radius:5px;overflow:hidden;">
                                <div style="height:100%;width:<?= $persen_lunas ?>%;background:linear-gradient(90deg,#10b981,#059669);border-radius:5px;"></div>
                            </div>
                        </div>

                        <div class="tbl-wrap" style="border-radius:0 0 20px 20px;overflow:hidden;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Bulan</th>
                                        <th>Jatuh Tempo</th>
                                        <th>Pokok</th>
                                        <th>Bunga</th>
                                        <th>Total Cicilan</th>
                                        <th>Status Bayar</th>
                                        <th>Tanggal Bayar</th>
                                        <th style="text-align:center;width:200px;">Aksi Admin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php 
                                $sisa_harga_berjalan_adm = $harga_rumah - $booking_fee - $dp_val;
                                foreach ($cicilan_detail as $c): 
                                    $is_late = ($c['status_bayar']==='belum' && strtotime($c['tanggal_jatuh_tempo']) < time());
                                    
                                    // Hitung tagihan riil menyesuaikan sisa harga berjalan
                                    $tagihan_riil_adm = min((float)$c['jumlah_cicilan'], $sisa_harga_berjalan_adm);
                                    if ($tagihan_riil_adm < 0) $tagihan_riil_adm = 0;
                                    
                                    if ($c['status_bayar'] === 'lunas') {
                                        $sisa_harga_berjalan_adm = max(0, $sisa_harga_berjalan_adm - $c['jumlah_cicilan']);
                                    }
                                    ?>
                                    <tr style="<?= $is_late ? 'background:#fff5f5;' : '' ?>">
                                        <td><b>Bulan <?= $c['bulan_ke'] ?></b><?= $is_late ? ' <span style="font-size:10px;color:#ef4444;font-weight:700;">⚠️ Terlambat</span>' : '' ?></td>
                                        <td><?= format_tanggal($c['tanggal_jatuh_tempo']) ?></td>
                                        <td><?= format_rupiah($c['pokok']) ?></td>
                                        <td style="color:var(--danger);"><?= format_rupiah($c['bunga']) ?></td>
                                        <td style="font-weight:800;color:var(--primary);">
                                            <?= format_rupiah($tagihan_riil_adm) ?>
                                            <?php if ($tagihan_riil_adm < (float)$c['jumlah_cicilan'] && $tagihan_riil_adm > 0): ?>
                                                <br><small style="color:#d97706;font-size:10px;font-weight:700;">(Disesuaikan sisa pelunasan)</small>
                                            <?php elseif ($tagihan_riil_adm <= 0 && $c['status_bayar'] !== 'lunas'): ?>
                                                <br><small style="color:#10b981;font-size:10px;font-weight:700;">(Lunas penuh)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($c['status_bayar']==='lunas'): ?>
                                                <span class="badge-lunas">✅ Lunas</span>
                                            <?php elseif ($is_late): ?>
                                                <span class="badge-terlambat">⏰ Terlambat</span>
                                            <?php else: ?>
                                                <span class="badge-belum">⏳ Belum</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $c['tanggal_bayar'] ? format_datetime($c['tanggal_bayar']) : '<span style="color:var(--muted);">-</span>' ?></td>
                                        <td style="text-align:center;">
                                            <?php if ($c['status_verifikasi']==='pending' && $c['tanggal_bayar']): ?>
                                                <div class="aksi-table">
                                                    <?php if ($c['bukti_bayar']): ?>
                                                    <a href="../../uploads/bukti_cicilan/<?= htmlspecialchars($c['bukti_bayar']) ?>" target="_blank" class="btn btn-outline btn-sm" style="font-size:11px;">📎 Bukti</a>
                                                    <?php endif; ?>
                                                    <a href="index.php?action=valid&id=<?= $c['id_cicilan'] ?>&id_pengajuan=<?= $id_pengajuan ?>" class="btn btn-success btn-sm" onclick="return confirm('Konfirmasi cicilan bulan ke-<?= $c['bulan_ke'] ?> VALID?')">✅ Valid</a>
                                                    <a href="index.php?action=tolak&id=<?= $c['id_cicilan'] ?>&id_pengajuan=<?= $id_pengajuan ?>" class="btn-delete" onclick="return confirm('Tolak pembayaran cicilan ini?')">❌ Tolak</a>
                                                </div>
                                            <?php elseif ($c['status_bayar']==='lunas'): ?>
                                                <span style="color:var(--success);font-size:12px;font-weight:700;">✅ Terverifikasi</span>
                                            <?php else: ?>
                                                <span style="color:var(--muted);font-size:12px;">Menunggu Pembayaran</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div><!-- /modal-inner -->
            </div><!-- /modal-detail -->
            <?php endif; ?>
        </main>
    </div>
    <script src="../../assets/js/script.js"></script>
    <script>
    <?php if ($id_pengajuan > 0 && $kpr_detail): ?>
    // Buka modal otomatis dengan animasi
    window.addEventListener('DOMContentLoaded', function() {
        const overlay = document.getElementById('modal-detail');
        const inner   = document.getElementById('modal-inner');
        requestAnimationFrame(() => {
            overlay.style.opacity = '1';
            inner.style.opacity   = '1';
            inner.style.transform = 'translateY(0)';
        });
        // Tutup saat klik backdrop
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) closeModal();
        });
    });
    function closeModal() {
        const overlay = document.getElementById('modal-detail');
        const inner   = document.getElementById('modal-inner');
        overlay.style.opacity = '0';
        inner.style.opacity   = '0';
        inner.style.transform = 'translateY(40px)';
        setTimeout(() => { window.location.href = 'index.php'; }, 280);
    }
    document.getElementById('btn-tutup-modal').addEventListener('click', function(e) {
        e.preventDefault(); closeModal();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });
    <?php endif; ?>
    </script>
</body>
</html>
