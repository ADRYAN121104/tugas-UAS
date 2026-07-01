<?php
// customer/dashboard.php - Dashboard Customer Lengkap
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';

$id   = id_user();
$nama = nama_user();

// Cek action items untuk customer (Alur baru)
$stmt_alerts = $db->prepare("
    SELECT b.id_booking, b.id_rumah, b.status_booking, p.nama_perumahan, r.blok, r.kode_unit,
           k.id_pengajuan, k.status_pengajuan,
           pay.id_pembayaran, pay.status_verifikasi
    FROM booking b
    JOIN rumah r ON b.id_rumah = r.id_rumah
    JOIN perumahan p ON r.id_perumahan = p.id_perumahan
    LEFT JOIN pengajuan_kpr k ON (b.id_rumah = k.id_rumah AND b.id_user = k.id_user)
    LEFT JOIN pembayaran pay ON b.id_booking = pay.id_booking
    WHERE b.id_user = ? AND b.status_booking != 'dibatalkan'
    ORDER BY b.id_booking DESC LIMIT 1
");
$stmt_alerts->execute([$id]);
$active_alert_booking = $stmt_alerts->fetch();

// Statistik customer
$total_booking   = $db->prepare("SELECT COUNT(*) FROM booking WHERE id_user=?"); $total_booking->execute([$id]); $total_booking = $total_booking->fetchColumn();
$booking_konfirm = $db->prepare("SELECT COUNT(*) FROM booking WHERE id_user=? AND status_booking='dikonfirmasi'"); $booking_konfirm->execute([$id]); $booking_konfirm = $booking_konfirm->fetchColumn();
$total_kpr       = $db->prepare("SELECT COUNT(*) FROM pengajuan_kpr WHERE id_user=?"); $total_kpr->execute([$id]); $total_kpr = $total_kpr->fetchColumn();
$kpr_aktif       = $db->prepare("SELECT COUNT(*) FROM pengajuan_kpr WHERE id_user=? AND status_pengajuan NOT IN ('disetujui','ditolak','akad_kredit')"); $kpr_aktif->execute([$id]); $kpr_aktif = $kpr_aktif->fetchColumn();

// Booking terbaru
$bookings_terbaru = $db->prepare("
    SELECT b.*, p.nama_perumahan, r.blok, r.kode_unit, r.nama_tipe, r.harga
    FROM booking b
    JOIN rumah r ON b.id_rumah = r.id_rumah
    JOIN perumahan p ON r.id_perumahan = p.id_perumahan
    WHERE b.id_user = ?
    ORDER BY b.id_booking DESC LIMIT 3
");
$bookings_terbaru->execute([$id]);
$bookings_terbaru = $bookings_terbaru->fetchAll();

// KPR terbaru
$kpr_terbaru = $db->prepare("
    SELECT pk.*, p.nama_perumahan, r.blok, r.kode_unit, b.nama_bank
    FROM pengajuan_kpr pk
    JOIN rumah r ON pk.id_rumah = r.id_rumah
    JOIN perumahan p ON r.id_perumahan = p.id_perumahan
    JOIN bank b ON pk.id_bank = b.id_bank
    WHERE pk.id_user = ?
    ORDER BY pk.id_pengajuan DESC LIMIT 3
");
$kpr_terbaru->execute([$id]);
$kpr_terbaru = $kpr_terbaru->fetchAll();

$page_title = 'Dashboard - KPR Perumahan';
require_once '../includes/header.php';
?>

<main class="container" style="padding:40px 24px 60px;">
    <?php tampil_flash(); ?>

    <!-- Welcome Banner -->
    <div style="background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 50%,#1d4ed8 100%);border-radius:20px;padding:32px 36px;color:#fff;margin-bottom:28px;position:relative;overflow:hidden;">
        <div style="position:absolute;right:-20px;top:-20px;font-size:120px;opacity:.06;">🏠</div>
        <div style="position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.02) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.02) 1px,transparent 1px);background-size:24px 24px;"></div>
        <div style="position:relative;z-index:1;">
            <div style="font-size:13px;opacity:.65;margin-bottom:6px;">Selamat datang kembali 👋</div>
            <h1 style="font-size:26px;font-weight:900;margin-bottom:6px;"><?= htmlspecialchars($nama) ?></h1>
            <p style="opacity:.7;font-size:14.5px;margin-bottom:18px;">Kelola booking dan pantau pengajuan KPR Anda di sini</p>
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <a href="../guest/katalog.php" class="btn btn-accent btn-sm">🏠 Cari Properti</a>
                <a href="../guest/simulasi_kpr.php" class="btn btn-white btn-sm" style="background:rgba(255,255,255,.15);color:#fff;border:1px solid rgba(255,255,255,.25);">🧮 Simulasi KPR</a>
            </div>
        </div>
    <!-- ACTION ITEMS ALERTS (Alur Baru) -->
    <?php if ($active_alert_booking): 
        $sb = $active_alert_booking['status_booking'];
        $kp = $active_alert_booking['status_pengajuan'];
        $id_peng = $active_alert_booking['id_pengajuan'];
        $sv = $active_alert_booking['status_verifikasi'];
        $id_booking = $active_alert_booking['id_booking'];
        $id_rumah = $active_alert_booking['id_rumah'];
        $unit_name = htmlspecialchars($active_alert_booking['nama_perumahan'] . ' - Blok ' . $active_alert_booking['blok'] . '-' . $active_alert_booking['kode_unit']);
        
        if ($sb === 'menunggu'):
            if (!$id_peng): ?>
                <!-- Langkah 2: Ajukan Berkas KPR -->
                <div style="background:linear-gradient(135deg,#ede9fe,#f5f3ff); border-left:5px solid #8b5cf6; border-radius:14px; padding:18px 22px; margin-bottom:20px; box-shadow: 0 4px 12px rgba(139,92,246,0.08); display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
                    <div style="flex:1; min-width:250px;">
                        <h4 style="color:#5b21b6; font-size:14.5px; font-weight:800; margin-bottom:4px;">🚀 Langkah 2: Ajukan Berkas KPR</h4>
                        <p style="color:#4c1d95; font-size:13px; line-height:1.4; margin:0;">Unit <b><?= $unit_name ?></b> berhasil dibooking. Segera lengkapi dan unggah berkas KPR Anda (KTP, KK, Slip Gaji) agar berkas bisa segera ditinjau oleh Admin & Bank.</p>
                    </div>
                    <a href="pengajuan_kpr.php?id_rumah=<?= $id_rumah ?>" class="btn btn-primary btn-sm" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed); color:#fff; border-radius:8px; font-weight:700; white-space:nowrap; border:none;">🚀 Ajukan Berkas KPR</a>
                </div>
            <?php elseif (in_array($kp, ['pengajuan_masuk', 'verifikasi_dokumen', 'survey'])): ?>
                <!-- Langkah 3: Review KPR Sedang Berjalan -->
                <div style="background:linear-gradient(135deg,#e0f2fe,#f0f9ff); border-left:5px solid #0284c7; border-radius:14px; padding:18px 22px; margin-bottom:20px; box-shadow: 0 4px 12px rgba(2,132,199,0.08); display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
                    <div style="flex:1; min-width:250px;">
                        <h4 style="color:#0369a1; font-size:14.5px; font-weight:800; margin-bottom:4px;">⏳ Langkah 3: Review KPR Sedang Berjalan</h4>
                        <p style="color:#075985; font-size:13px; line-height:1.4; margin:0;">Pengajuan KPR Anda untuk unit <b><?= $unit_name ?></b> sedang ditinjau oleh Admin/Bank. Pembayaran booking fee akan dibuka secara otomatis setelah status pengajuan KPR Anda disetujui.</p>
                    </div>
                    <a href="status_kpr.php?id=<?= $id_peng ?>" class="btn btn-outline btn-sm" style="color:#0284c7; border-color:#0284c7; border-radius:8px; font-weight:700; white-space:nowrap;">📊 Lacak Status KPR</a>
                </div>
            <?php elseif ($kp === 'disetujui'): ?>
                <?php if (!$sv): ?>
                    <!-- Langkah 4: Bayar Booking Fee -->
                    <div style="background:linear-gradient(135deg,#fef3c7,#fffbeb); border-left:5px solid #d97706; border-radius:14px; padding:18px 22px; margin-bottom:20px; box-shadow: 0 4px 12px rgba(217,119,6,0.08); display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
                        <div style="flex:1; min-width:250px;">
                            <h4 style="color:#92400e; font-size:14.5px; font-weight:800; margin-bottom:4px;">💰 Langkah 4: Bayar Booking Fee</h4>
                            <p style="color:#78350f; font-size:13px; line-height:1.4; margin:0;">Selamat! Pengajuan KPR Anda untuk unit <b><?= $unit_name ?></b> telah <b>DISETUJUI</b>. Silakan lakukan pembayaran booking fee untuk mengunci unit dan mengonfirmasi pemesanan Anda.</p>
                        </div>
                        <a href="upload_pembayaran.php?id=<?= $id_booking ?>" class="btn btn-accent btn-sm" style="color:#fff; border-radius:8px; font-weight:700; white-space:nowrap; border:none;">💰 Bayar Booking Fee</a>
                    </div>
                <?php elseif ($sv === 'pending'): ?>
                    <!-- Langkah 5: Verifikasi Pembayaran Booking Fee -->
                    <div style="background:linear-gradient(135deg,#fef9c3,#fffbeb); border-left:5px solid #f59e0b; border-radius:14px; padding:18px 22px; margin-bottom:20px; box-shadow: 0 4px 12px rgba(245,158,11,0.08); display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
                        <div style="flex:1; min-width:250px;">
                            <h4 style="color:#92400e; font-size:14.5px; font-weight:800; margin-bottom:4px;">⏳ Langkah 5: Verifikasi Pembayaran</h4>
                            <p style="color:#78350f; font-size:13px; line-height:1.4; margin:0;">Bukti pembayaran booking fee untuk unit <b><?= $unit_name ?></b> telah dikirim dan saat ini sedang dalam proses verifikasi oleh Admin (maksimal 24 jam).</p>
                        </div>
                        <a href="booking_saya.php" class="btn btn-sm" style="background:#fbbf24; color:#78350f; border-radius:8px; font-weight:700; white-space:nowrap;">📋 Cek Status</a>
                    </div>
                <?php elseif ($sv === 'ditolak'): ?>
                    <!-- Pembayaran Ditolak -->
                    <div style="background:linear-gradient(135deg,#fee2e2,#fef2f2); border-left:5px solid #ef4444; border-radius:14px; padding:18px 22px; margin-bottom:20px; box-shadow: 0 4px 12px rgba(239,68,68,0.08); display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
                        <div style="flex:1; min-width:250px;">
                            <h4 style="color:#991b1b; font-size:14.5px; font-weight:800; margin-bottom:4px;">❌ Pembayaran Booking Fee Ditolak</h4>
                            <p style="color:#7f1d1d; font-size:13px; line-height:1.4; margin:0;">Pembayaran booking fee untuk unit <b><?= $unit_name ?></b> ditolak admin. Silakan upload ulang bukti transfer yang valid.</p>
                        </div>
                        <a href="upload_pembayaran.php?id=<?= $id_booking ?>" class="btn btn-danger btn-sm" style="background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff; border-radius:8px; font-weight:700; white-space:nowrap;">⚠️ Bayar Ulang</a>
                    </div>
                <?php endif; ?>
            <?php elseif ($kp === 'ditolak'): ?>
                <!-- KPR Ditolak -->
                <div style="background:linear-gradient(135deg,#fee2e2,#fef2f2); border-left:5px solid #ef4444; border-radius:14px; padding:18px 22px; margin-bottom:20px; box-shadow: 0 4px 12px rgba(239,68,68,0.08); display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
                    <div style="flex:1; min-width:250px;">
                        <h4 style="color:#991b1b; font-size:14.5px; font-weight:800; margin-bottom:4px;">❌ Pengajuan KPR Ditolak</h4>
                        <p style="color:#7f1d1d; font-size:13px; line-height:1.4; margin:0;">Maaf, pengajuan KPR Anda untuk unit <b><?= $unit_name ?></b> ditolak oleh bank/admin. Silakan hubungi marketing kami atau pilih unit rumah lain.</p>
                    </div>
                    <a href="booking_saya.php" class="btn btn-sm btn-gray" style="border-radius:8px; font-weight:700; white-space:nowrap;">📋 Ke Booking Saya</a>
                </div>
            <?php endif; ?>
        <?php elseif ($sb === 'dikonfirmasi'): ?>
            <?php if ($kp === 'disetujui'): ?>
                <!-- Langkah 6: Siap Akad Kredit -->
                <div style="background:linear-gradient(135deg,#d1fae5,#ecfdf5); border-left:5px solid #10b981; border-radius:14px; padding:18px 22px; margin-bottom:20px; box-shadow: 0 4px 12px rgba(16,185,129,0.08); display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
                    <div style="flex:1; min-width:250px;">
                        <h4 style="color:#065f46; font-size:14.5px; font-weight:800; margin-bottom:4px;">🎉 Langkah 6: Siap Akad Kredit</h4>
                        <p style="color:#064e3b; font-size:13px; line-height:1.4; margin:0;">Selamat! Booking unit <b><?= $unit_name ?></b> telah dikonfirmasi dan pembayaran valid. Silakan hubungi marketing untuk menjadwalkan Akad Kredit.</p>
                    </div>
                    <a href="status_kpr.php?id=<?= $id_peng ?>" class="btn btn-success btn-sm" style="background:linear-gradient(135deg,#10b981,#059669); color:#fff; border-radius:8px; font-weight:700; white-space:nowrap; border:none;">📊 Lacak Status KPR</a>
                </div>
            <?php elseif ($kp === 'akad_kredit'): ?>
                <!-- Akad Kredit Selesai -->
                <div style="background:linear-gradient(135deg,#d1fae5,#ecfdf5); border-left:5px solid #10b981; border-radius:14px; padding:18px 22px; margin-bottom:20px; box-shadow: 0 4px 12px rgba(16,185,129,0.08); display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
                    <div style="flex:1; min-width:250px;">
                        <h4 style="color:#065f46; font-size:14.5px; font-weight:800; margin-bottom:4px;">🏠 Akad Kredit Selesai</h4>
                        <p style="color:#064e3b; font-size:13px; line-height:1.4; margin:0;">Proses Akad Kredit untuk unit <b><?= $unit_name ?></b> telah selesai. Selamat atas kepemilikan rumah baru Anda!</p>
                    </div>
                    <a href="status_kpr.php?id=<?= $id_peng ?>" class="btn btn-success btn-sm" style="background:linear-gradient(135deg,#10b981,#059669); color:#fff; border-radius:8px; font-weight:700; white-space:nowrap; border:none;">📊 Lihat Dokumen</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <!-- PANDUAN ALUR PROSES PEMBELIAN (STEPPER) -->
    <div style="background:#fff; border-radius:16px; border:1px solid #e2e8f0; padding:24px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); margin-bottom:28px;">
        <h3 style="font-size:15px; font-weight:800; margin-bottom:18px; color:#0f172a;">🗺️ Alur Pembelian Rumah KPR (Langkah demi Langkah)</h3>
        <div style="display:flex; justify-content:space-between; align-items:flex-start; overflow-x:auto; padding-bottom:10px; gap:16px;" id="flow-stepper-dash">
            <div style="flex:1; min-width:140px; display:flex; flex-direction:column; align-items:center; text-align:center;">
                <div style="width:36px; height:36px; border-radius:50%; background:#d1fae5; color:#065f46; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; margin-bottom:8px; border:2px solid #34d399;">1</div>
                <div style="font-size:12.5px; font-weight:700; color:#065f46; margin-bottom:4px;">1. Booking Unit</div>
                <p style="font-size:11px; color:#64748b; line-height:1.4;">Pilih unit rumah di katalog, klik tombol Booking untuk mengamankan unit.</p>
            </div>
            <div style="align-self:center; color:#cbd5e1; font-size:18px; font-weight:bold; padding-top:8px;">➔</div>
            <div style="flex:1; min-width:140px; display:flex; flex-direction:column; align-items:center; text-align:center;">
                <div style="width:36px; height:36px; border-radius:50%; background:#ede9fe; color:#5b21b6; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; margin-bottom:8px; border:2px solid #a78bfa;">2</div>
                <div style="font-size:12.5px; font-weight:700; color:#6d28d9; margin-bottom:4px;">2. Ajukan Berkas KPR</div>
                <p style="font-size:11px; color:#64748b; line-height:1.4;">Unggah dokumen KPR (KTP, KK, Slip Gaji) untuk diproses & ditinjau Admin/Bank.</p>
            </div>
            <div style="align-self:center; color:#cbd5e1; font-size:18px; font-weight:bold; padding-top:8px;">➔</div>
            <div style="flex:1; min-width:140px; display:flex; flex-direction:column; align-items:center; text-align:center;">
                <div style="width:36px; height:36px; border-radius:50%; background:#fef3c7; color:#92400e; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; margin-bottom:8px; border:2px solid #fbbf24;">3</div>
                <div style="font-size:12.5px; font-weight:700; color:#b45309; margin-bottom:4px;">3. Bayar Booking Fee</div>
                <p style="font-size:11px; color:#64748b; line-height:1.4;">Setelah KPR disetujui, lakukan pembayaran booking fee online / upload manual.</p>
            </div>
            <div style="align-self:center; color:#cbd5e1; font-size:18px; font-weight:bold; padding-top:8px;">➔</div>
            <div style="flex:1; min-width:140px; display:flex; flex-direction:column; align-items:center; text-align:center;">
                <div style="width:36px; height:36px; border-radius:50%; background:#e0f2fe; color:#0369a1; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; margin-bottom:8px; border:2px solid #38bdf8;">4</div>
                <div style="font-size:12.5px; font-weight:700; color:#0369a1; margin-bottom:4px;">4. Booking Dikonfirmasi</div>
                <p style="font-size:11px; color:#64748b; line-height:1.4;">Admin verifikasi bayar -> Booking dikonfirmasi & lanjut Akad Kredit.</p>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:28px;">
        <?php
        $stats = [
            ['📋', $total_booking, 'Total Booking', '#dbeafe', '#1e40af'],
            ['✅', $booking_konfirm, 'Booking Aktif', '#d1fae5', '#065f46'],
            ['📝', $total_kpr, 'Pengajuan KPR', '#ede9fe', '#4c1d95'],
            ['🔄', $kpr_aktif, 'KPR Diproses', '#fef3c7', '#78350f'],
        ];
        foreach ($stats as $s): ?>
        <div style="background:#fff;border-radius:14px;padding:20px;border:1px solid #e2e8f0;box-shadow:0 2px 8px rgba(0,0,0,.05);display:flex;align-items:center;gap:14px;transition:.25s;" onmouseover="this.style.transform='translateY(-3px)';this.style.boxShadow='0 8px 24px rgba(0,0,0,.08)'" onmouseout="this.style.transform='';this.style.boxShadow='0 2px 8px rgba(0,0,0,.05)'">
            <div style="width:48px;height:48px;border-radius:12px;background:<?= $s[3] ?>;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;"><?= $s[0] ?></div>
            <div>
                <div style="font-size:26px;font-weight:900;color:<?= $s[4] ?>;line-height:1;"><?= $s[1] ?></div>
                <div style="font-size:12.5px;color:#64748b;margin-top:3px;font-weight:600;"><?= $s[2] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;" id="dash-grid">

        <!-- Booking Terbaru -->
        <div style="background:#fff;border-radius:16px;border:1px solid #e2e8f0;box-shadow:0 2px 8px rgba(0,0,0,.05);overflow:hidden;">
            <div style="padding:18px 22px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;background:linear-gradient(to right,#f8fafc,#fff);">
                <h3 style="font-size:15px;font-weight:800;">📋 Booking Terbaru</h3>
                <a href="booking_saya.php" class="btn btn-outline btn-sm">Lihat Semua</a>
            </div>
            <div style="padding:16px 22px;">
                <?php if (empty($bookings_terbaru)): ?>
                    <div style="text-align:center;padding:30px;color:#94a3b8;">
                        <div style="font-size:40px;margin-bottom:10px;">📋</div>
                        <p style="font-size:14px;">Belum ada booking</p>
                        <a href="../guest/katalog.php" class="btn btn-primary btn-sm" style="margin-top:12px;">Cari Properti</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($bookings_terbaru as $b): ?>
                    <div style="display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid #f8fafc;">
                        <div style="width:44px;height:44px;background:linear-gradient(135deg,#0f172a,#1e3a8a);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">🏠</div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:14px;font-weight:800;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($b['nama_perumahan']) ?></div>
                            <div style="font-size:12px;color:#2563eb;font-weight:600;">Blok <?= htmlspecialchars($b['blok'].'-'.$b['kode_unit']) ?> · <?= htmlspecialchars($b['nama_tipe']) ?></div>
                            <div style="font-size:11.5px;color:#94a3b8;margin-top:2px;"><?= format_tanggal($b['tanggal_booking']) ?></div>
                        </div>
                        <div><?= badge_booking($b['status_booking']) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status KPR Terbaru -->
        <div style="background:#fff;border-radius:16px;border:1px solid #e2e8f0;box-shadow:0 2px 8px rgba(0,0,0,.05);overflow:hidden;">
            <div style="padding:18px 22px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;background:linear-gradient(to right,#f8fafc,#fff);">
                <h3 style="font-size:15px;font-weight:800;">📝 Pengajuan KPR</h3>
                <a href="status_kpr.php" class="btn btn-outline btn-sm">Lihat Semua</a>
            </div>
            <div style="padding:16px 22px;">
                <?php if (empty($kpr_terbaru)): ?>
                    <div style="text-align:center;padding:30px;color:#94a3b8;">
                        <div style="font-size:40px;margin-bottom:10px;">📝</div>
                        <p style="font-size:14px;">Belum ada pengajuan KPR</p>
                        <a href="booking_saya.php" class="btn btn-primary btn-sm" style="margin-top:12px;">Ke Booking Saya</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($kpr_terbaru as $k): ?>
                    <div style="display:flex;align-items:center;gap:14px;padding:12px 0;border-bottom:1px solid #f8fafc;">
                        <div style="width:44px;height:44px;background:linear-gradient(135deg,#1e3a8a,#2563eb);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">🏦</div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:14px;font-weight:800;color:#0f172a;"><?= htmlspecialchars($k['nama_perumahan']) ?></div>
                            <div style="font-size:12px;color:#2563eb;font-weight:600;"><?= htmlspecialchars($k['nama_bank']) ?></div>
                            <div style="font-size:11.5px;color:#94a3b8;margin-top:2px;"><?= format_tanggal($k['tanggal_pengajuan']) ?></div>
                        </div>
                        <div><?= badge_kpr($k['status_pengajuan']) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Quick Links -->
    <div style="margin-top:22px;display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;">
        <?php
        $quick = [
            ['📋', 'Booking Saya', 'booking_saya.php', '#dbeafe', '#1e40af'],
            ['💰', 'Pembayaran', 'pembayaran.php', '#d1fae5', '#065f46'],
            ['📝', 'Ajukan KPR', 'pengajuan_kpr.php', '#ede9fe', '#4c1d95'],
            ['📈', 'Status KPR', 'status_kpr.php', '#fef3c7', '#78350f'],
            ['👤', 'Profil Saya', 'profil.php', '#fee2e2', '#991b1b'],
            ['🕐', 'Riwayat', 'riwayat_booking.php', '#f3e8ff', '#6b21a8'],
        ];
        foreach ($quick as $q): ?>
        <a href="<?= $q[2] ?>" style="display:flex;flex-direction:column;align-items:center;gap:10px;padding:20px 16px;background:#fff;border-radius:14px;border:1px solid #e2e8f0;box-shadow:0 2px 6px rgba(0,0,0,.04);text-decoration:none;transition:.25s;" onmouseover="this.style.transform='translateY(-3px)';this.style.borderColor='rgba(37,99,235,.2)'" onmouseout="this.style.transform='';this.style.borderColor='#e2e8f0'">
            <div style="width:44px;height:44px;border-radius:12px;background:<?= $q[3] ?>;display:flex;align-items:center;justify-content:center;font-size:22px;"><?= $q[0] ?></div>
            <span style="font-size:13px;font-weight:700;color:<?= $q[4] ?>;text-align:center;"><?= $q[1] ?></span>
        </a>
        <?php endforeach; ?>
    </div>

</main>

<?php require_once '../includes/footer.php'; ?>
<style>
@media(max-width:768px){#dash-grid{grid-template-columns:1fr!important;}}
</style>
