<?php
// customer/cicilan.php - Halaman Cicilan KPR Customer
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';

$id_user = id_user();

// ── AJAX: check status ──────────────────────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'check') {
    header('Content-Type: application/json');
    $s = $db->prepare("SELECT id_cicilan, status_verifikasi FROM cicilan_kpr c JOIN pengajuan_kpr pk ON c.id_pengajuan=pk.id_pengajuan WHERE pk.id_user=?");
    $s->execute([$id_user]);
    echo json_encode($s->fetchAll(PDO::FETCH_KEY_PAIR));
    exit;
}

// ── PROSES UPLOAD BUKTI CICILAN ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_cicilan_bayar'])) {
    $id_cic_b     = (int)$_POST['id_cicilan_bayar'];
    $id_pengajuan_b = (int)($_POST['id_pengajuan'] ?? 0);
    $cek_b = $db->prepare("SELECT c.* FROM cicilan_kpr c JOIN pengajuan_kpr pk ON c.id_pengajuan=pk.id_pengajuan WHERE c.id_cicilan=? AND pk.id_user=?");
    $cek_b->execute([$id_cic_b, $id_user]);
    $cic_b = $cek_b->fetch();

    if (!$cic_b) {
        set_flash('gagal', 'Data cicilan tidak ditemukan.');
    } elseif ($cic_b['status_bayar'] === 'lunas') {
        set_flash('gagal', 'Cicilan ini sudah lunas.');
    } elseif (empty($_FILES['bukti_cicilan']['name'])) {
        set_flash('gagal', 'Pilih file bukti pembayaran terlebih dahulu.');
    } else {
        $dir = '../uploads/bukti_cicilan/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['bukti_cicilan']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','pdf'])) {
            set_flash('gagal', 'Format tidak valid. Gunakan JPG/PNG/PDF.');
        } elseif ($_FILES['bukti_cicilan']['size'] > 5*1024*1024) {
            set_flash('gagal', 'File terlalu besar (maks 5MB).');
        } else {
            $fname = 'cicilan_' . $id_cic_b . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['bukti_cicilan']['tmp_name'], $dir . $fname);
            $db->prepare("UPDATE cicilan_kpr SET bukti_bayar=?, tanggal_bayar=NOW(), status_verifikasi='pending' WHERE id_cicilan=?")
               ->execute([$fname, $id_cic_b]);
            set_flash('sukses', '✅ Bukti cicilan bulan ke-' . $cic_b['bulan_ke'] . ' berhasil dikirim! Admin akan verifikasi dalam 1×24 jam.');
        }
    }
    header('Location: cicilan.php' . ($id_pengajuan_b > 0 ? '?id='.$id_pengajuan_b : ''));
    exit;
}

// ── AMBIL DATA KPR CUSTOMER ─────────────────────────────────────────────────
$stmt = $db->prepare("
    SELECT pk.id_pengajuan, pk.uang_muka, pk.tenor, pk.status_pengajuan, pk.id_rumah,
           u.nama_lengkap, b.nama_bank, b.bunga_kpr,
           p.nama_perumahan, r.blok, r.kode_unit, r.harga,
           (SELECT COUNT(*) FROM cicilan_kpr c WHERE c.id_pengajuan=pk.id_pengajuan) as jml_cicilan,
           (SELECT COUNT(*) FROM cicilan_kpr c WHERE c.id_pengajuan=pk.id_pengajuan AND c.status_bayar='lunas') as jml_lunas,
           (SELECT COALESCE(SUM(jumlah_cicilan),0) FROM cicilan_kpr c WHERE c.id_pengajuan=pk.id_pengajuan AND c.status_bayar='lunas') as total_lunas,
           (SELECT jumlah_cicilan FROM cicilan_kpr c WHERE c.id_pengajuan=pk.id_pengajuan ORDER BY bulan_ke ASC LIMIT 1) as cicilan_per_bulan
    FROM pengajuan_kpr pk
    JOIN users u ON pk.id_user = u.id_user
    JOIN rumah r ON pk.id_rumah = r.id_rumah
    JOIN perumahan p ON r.id_perumahan = p.id_perumahan
    JOIN bank b ON pk.id_bank = b.id_bank
    WHERE pk.id_user=? AND pk.status_pengajuan IN ('disetujui','akad_kredit')
    ORDER BY pk.id_pengajuan DESC
");
$stmt->execute([$id_user]);
$list_kpr = $stmt->fetchAll();

$id_pengajuan = (int)($_GET['id'] ?? ($list_kpr[0]['id_pengajuan'] ?? 0));
$kpr_aktif = null;
$cicilan_list = [];
$dp_customer = null;
$booking_fee = 0.0;

if ($id_pengajuan > 0) {
    foreach ($list_kpr as $k) {
        if ($k['id_pengajuan'] == $id_pengajuan) { $kpr_aktif = $k; break; }
    }
    if ($kpr_aktif) {
        $dc = $db->prepare("SELECT * FROM cicilan_kpr WHERE id_pengajuan=? ORDER BY bulan_ke ASC");
        $dc->execute([$id_pengajuan]);
        $cicilan_list = $dc->fetchAll();

        // Ambil DP yang sudah valid
        $dp_q = $db->prepare("SELECT * FROM pembayaran_dp WHERE id_pengajuan=? AND status_verifikasi='valid' LIMIT 1");
        $dp_q->execute([$id_pengajuan]);
        $dp_customer = $dp_q->fetch();

        // Ambil Booking Fee
        $q_bf = $db->prepare("SELECT booking_fee FROM booking WHERE id_user=? AND id_rumah=? AND status_booking='dikonfirmasi' ORDER BY id_booking DESC LIMIT 1");
        $q_bf->execute([$id_user, $kpr_aktif['id_rumah']]);
        $booking_fee = (float)($q_bf->fetchColumn() ?: 0);
    }
}

$page_title = 'Cicilan KPR Saya';
require_once '../includes/header.php';
?>
<style>
.cic-card { background:#fff; border-radius:16px; border:1px solid #e2e8f0; padding:24px; box-shadow:0 4px 20px rgba(0,0,0,.05); margin-bottom:20px; }
.progress-bar-wrap { background:#e2e8f0; border-radius:8px; height:12px; overflow:hidden; }
.progress-bar-fill { height:100%; border-radius:8px; background:linear-gradient(90deg,#10b981,#059669); transition:.4s; }
.rekap-total { background:linear-gradient(135deg,#0f172a,#1e3a8a); border-radius:14px; padding:20px 24px; color:#fff; margin-bottom:20px; }
.rekap-row { display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid rgba(255,255,255,.1); }
.rekap-row:last-child { border:none; padding-top:12px; }
.rekap-lbl { font-size:13px; opacity:.8; }
.rekap-val { font-weight:800; font-size:14px; }
.rekap-grand { font-size:20px; font-weight:900; background:linear-gradient(135deg,#fbbf24,#f59e0b); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
.form-bayar-box { background:linear-gradient(135deg,#f0fdf4,#eff6ff); border:2px dashed #93c5fd; border-radius:14px; padding:20px; margin-top:14px; }
</style>

<main class="container" style="padding:40px 24px 80px;">
<?php tampil_flash(); ?>

<!-- HEADER -->
<div style="background:linear-gradient(135deg,#0f172a,#1e3a8a,#2563eb);border-radius:20px;padding:28px 32px;color:#fff;margin-bottom:24px;position:relative;overflow:hidden;">
    <div style="position:absolute;right:-10px;top:-10px;font-size:100px;opacity:.06;">💳</div>
    <h1 style="font-size:22px;font-weight:900;margin-bottom:6px;position:relative;">💳 Cicilan KPR Saya</h1>
    <p style="opacity:.75;font-size:14px;position:relative;">Pantau jadwal, bayar cicilan bulanan, dan lihat total pembayaran Anda</p>
</div>

<?php if (empty($list_kpr)): ?>
<div class="cic-card" style="text-align:center;padding:60px 20px;">
    <div style="font-size:56px;margin-bottom:16px;">💳</div>
    <h3 style="font-size:18px;font-weight:800;margin-bottom:8px;">Belum Ada Cicilan KPR</h3>
    <p style="color:#64748b;margin-bottom:20px;">Cicilan muncul setelah KPR Anda disetujui bank dan Akad Kredit selesai.</p>
    <a href="status_kpr.php" class="btn btn-primary">📈 Lihat Status KPR</a>
</div>
<?php else: ?>

<!-- PILIH KPR jika lebih dari 1 -->
<?php if (count($list_kpr) > 1): ?>
<div class="cic-card" style="padding:14px 20px;margin-bottom:16px;">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <span style="font-size:12px;font-weight:700;color:#64748b;">Pilih KPR:</span>
        <?php foreach ($list_kpr as $k): ?>
        <a href="cicilan.php?id=<?= $k['id_pengajuan'] ?>"
           class="btn <?= $k['id_pengajuan']==$id_pengajuan ? 'btn-primary' : 'btn-gray' ?> btn-sm">
            <?= htmlspecialchars($k['nama_perumahan']) ?> Blok <?= htmlspecialchars($k['blok'].'-'.$k['kode_unit']) ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($kpr_aktif): ?>

<!-- ── REKAP TOTAL PEMBAYARAN (DP + Cicilan) ─────────────────────────────── -->
<?php
$total_cicilan_paid = (float)($kpr_aktif['total_lunas'] ?? 0);
$total_dp_paid      = $dp_customer ? (float)$dp_customer['jumlah_dp'] : 0;
// Total Bayar = Booking Fee + DP + Cicilan
$total_grand        = $booking_fee + $total_dp_paid + $total_cicilan_paid;
$harga_rumah        = (float)$kpr_aktif['harga'];
$sisa_harga_rumah   = max(0, $harga_rumah - $total_grand);

// Menghitung sisa kali cicilan tergantung sisa harga properti
$sisa_kali_cicilan = 0;
if ($sisa_harga_rumah > 0 && $kpr_aktif['cicilan_per_bulan'] > 0) {
    $sisa_kali_cicilan = ceil($sisa_harga_rumah / $kpr_aktif['cicilan_per_bulan']);
    // Batasi agar tidak melebihi sisa tenor bulan aslinya
    $sisa_kali_cicilan = min($kpr_aktif['jml_cicilan'] - $kpr_aktif['jml_lunas'], $sisa_kali_cicilan);
}
?>
<div class="rekap-total">
    <div style="font-size:12px;opacity:.6;margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px;">Rekap Total Pembayaran Properti Anda</div>
    
    <div class="rekap-row">
        <span class="rekap-lbl">🏙️ Harga Rumah Properti</span>
        <span class="rekap-val" style="color:#ffffff; font-size:15px;"><?= format_rupiah($harga_rumah) ?></span>
    </div>
    <div class="rekap-row">
        <span class="rekap-lbl">📋 Booking Fee Terbayar</span>
        <span class="rekap-val" style="color:#a7f3d0;"><?= format_rupiah($booking_fee) ?></span>
    </div>
    <div class="rekap-row">
        <span class="rekap-lbl">💰 Uang Muka (DP) Terbayar</span>
        <span class="rekap-val" style="color:#fbbf24;"><?= format_rupiah($total_dp_paid) ?></span>
    </div>
    <div class="rekap-row">
        <span class="rekap-lbl">💳 Total Cicilan Terbayar (<?= $kpr_aktif['jml_lunas'] ?> bulan)</span>
        <span class="rekap-val" style="color:#6ee7b7;"><?= format_rupiah($total_cicilan_paid) ?></span>
    </div>
    <div class="rekap-row" style="border-top:1px solid rgba(255,255,255,0.25); padding-top:10px;">
        <span class="rekap-lbl" style="font-weight:bold;">💵 TOTAL SUDAH DIBAYAR (BF + DP + CICILAN)</span>
        <span class="rekap-grand" style="color:#fbbf24; font-size:16px; font-weight:900;"><?= format_rupiah($total_grand) ?></span>
    </div>
    <div class="rekap-row" style="background: rgba(239, 68, 68, 0.15); padding: 8px 10px; border-radius: 6px; margin-top: 8px;">
        <span class="rekap-lbl" style="color:#f87171; font-weight:bold;">🚨 Sisa Harga Properti Belum Lunas</span>
        <span class="rekap-val" style="color:#f87171; font-weight:900; font-size:15px;"><?= format_rupiah($sisa_harga_rumah) ?></span>
    </div>
    <div class="rekap-row" style="background: rgba(59, 130, 246, 0.15); padding: 8px 10px; border-radius: 6px; margin-top: 6px;">
        <span class="rekap-lbl" style="color:#60a5fa; font-weight:bold;">📅 Sisa Kali Cicilan Untuk Lunas</span>
        <span class="rekap-val" style="color:#60a5fa; font-weight:900; font-size:15px;"><?= $sisa_kali_cicilan ?> Kali Cicilan Lagi</span>
    </div>
</div>

<!-- INFO KPR -->
<div class="cic-card">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;" id="kpr-grid">
        <div>
            <div style="font-size:12px;color:#64748b;margin-bottom:3px;">Properti</div>
            <div style="font-size:15px;font-weight:800;color:#0f172a;"><?= htmlspecialchars($kpr_aktif['nama_perumahan']) ?></div>
            <div style="font-size:12px;color:#2563eb;font-weight:700;">Blok <?= htmlspecialchars($kpr_aktif['blok'].'-'.$kpr_aktif['kode_unit']) ?></div>
        </div>
        <div>
            <div style="font-size:12px;color:#64748b;margin-bottom:3px;">Bank & Tenor</div>
            <div style="font-size:14px;font-weight:800;"><?= htmlspecialchars($kpr_aktif['nama_bank']) ?></div>
            <div style="font-size:12px;color:#64748b;"><?= $kpr_aktif['tenor'] ?> tahun · <?= $kpr_aktif['bunga_kpr'] ?>% p.a</div>
        </div>
        <div>
            <div style="font-size:12px;color:#64748b;margin-bottom:3px;">Harga Rumah</div>
            <div style="font-size:16px;font-weight:900;color:#10b981;"><?= format_rupiah($kpr_aktif['harga']) ?></div>
        </div>
        <div>
            <div style="font-size:12px;color:#64748b;margin-bottom:3px;">Cicilan Per Bulan</div>
            <div style="font-size:18px;font-weight:900;background:linear-gradient(135deg,#2563eb,#6366f1);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">
                <?= $kpr_aktif['cicilan_per_bulan'] ? format_rupiah($kpr_aktif['cicilan_per_bulan']) : '-' ?>
            </div>
        </div>
    </div>
    <!-- Progress -->
    <?php if ($kpr_aktif['jml_cicilan'] > 0): ?>
    <?php $persen = round($kpr_aktif['jml_lunas'] / $kpr_aktif['jml_cicilan'] * 100); ?>
    <div style="background:#f8faff;border-radius:10px;padding:14px;">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
            <span style="font-size:12px;font-weight:700;color:#475569;">Progres Pelunasan</span>
            <span style="font-size:12px;font-weight:800;color:#10b981;"><?= $kpr_aktif['jml_lunas'] ?>/<?= $kpr_aktif['jml_cicilan'] ?> bulan (<?= $persen ?>%)</span>
        </div>
        <div class="progress-bar-wrap">
            <div class="progress-bar-fill" style="width:<?= $persen ?>%;"></div>
        </div>
    </div>
    <?php else: ?>
    <div style="background:#fef3c7;border:1px solid #fbbf24;border-radius:10px;padding:12px;text-align:center;">
        <span style="font-weight:700;color:#92400e;font-size:13px;">⏳ Jadwal cicilan sedang disiapkan admin</span>
    </div>
    <?php endif; ?>
</div>

<!-- JADWAL CICILAN + FORM BAYAR -->
<?php if (!empty($cicilan_list)): ?>
<div class="cic-card" style="padding:0;">
    <div style="padding:16px 22px;border-bottom:1px solid #e2e8f0;background:linear-gradient(135deg,#f8faff,#fff);">
        <h3 style="font-size:15px;font-weight:800;margin:0;">📅 Jadwal Cicilan Bulanan</h3>
        <p style="font-size:12px;color:#64748b;margin:4px 0 0;">Klik tombol <b>Bayar</b> untuk upload bukti transfer</p>
    </div>

    <!-- INFO REKENING BAYAR -->
    <div style="background:#eff6ff;border-bottom:1px solid #dbeafe;padding:14px 22px;display:flex;align-items:center;gap:12px;">
        <span style="font-size:22px;">🏦</span>
        <div style="font-size:13px;line-height:1.7;">
            Transfer ke: <b>BCA 1234567890</b> a.n. <b>PT RumahKPR Indonesia</b> &bull;
            Nominal normal: <b style="color:#2563eb;"><?= $kpr_aktif['cicilan_per_bulan'] ? format_rupiah($kpr_aktif['cicilan_per_bulan']) : 'Sesuai tagihan' ?></b>
        </div>
    </div>

    <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:13.5px;">
        <thead>
            <tr style="background:linear-gradient(135deg,#f8faff,#f0f4ff);">
                <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase;">Bulan</th>
                <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase;">Jatuh Tempo</th>
                <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase;">Tagihan Riil</th>
                <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase;">Status</th>
                <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase;">Upload Bukti Bayar</th>
            </tr>
        </thead>
        <tbody>
        <?php 
        // Lacak sisa harga rumah dinamis untuk membatasi tagihan terakhir agar tidak melebihi harga rumah
        $sisa_harga_berjalan = $harga_rumah - $booking_fee - $total_dp_paid;
        
        foreach ($cicilan_list as $c): 
            $is_late = ($c['status_bayar']==='belum' && strtotime($c['tanggal_jatuh_tempo']) < time());
            
            // Tagihan riil dibatasi sisa harga berjalan agar tidak melebihi harga rumah
            $tagihan_riil = min((float)$c['jumlah_cicilan'], $sisa_harga_berjalan);
            if ($tagihan_riil < 0) $tagihan_riil = 0;
            
            // Jika sudah lunas, kurangi sisa harga berjalan
            if ($c['status_bayar'] === 'lunas') {
                $sisa_harga_berjalan = max(0, $sisa_harga_berjalan - $c['jumlah_cicilan']);
            }
            ?>
            <tr style="border-bottom:1px solid #f1f5f9;<?= $is_late ? 'background:#fff5f5;' : '' ?>">
                <td style="padding:12px 16px;">
                    <b style="color:#0f172a;">Bulan <?= $c['bulan_ke'] ?></b>
                    <?= $is_late ? ' <span style="font-size:10px;color:#ef4444;font-weight:700;">⚠️ Terlambat</span>' : '' ?>
                </td>
                <td style="padding:12px 16px;color:#64748b;font-size:12px;"><?= format_tanggal($c['tanggal_jatuh_tempo']) ?></td>
                <td style="padding:12px 16px;font-weight:800;color:#2563eb;">
                    <?= format_rupiah($tagihan_riil) ?>
                    <?php if ($tagihan_riil < (float)$c['jumlah_cicilan'] && $tagihan_riil > 0): ?>
                        <br><small style="color:#d97706;font-size:10px;font-weight:700;">(Disesuaikan sisa pelunasan)</small>
                    <?php elseif ($tagihan_riil <= 0 && $c['status_bayar'] !== 'lunas'): ?>
                        <br><small style="color:#10b981;font-size:10px;font-weight:700;">(Sudah lunas penuh)</small>
                    <?php endif; ?>
                </td>
                <td style="padding:12px 16px;">
                    <?php if ($c['status_bayar']==='lunas'): ?>
                        <span class="badge" style="background:#d1fae5;color:#065f46;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;">✅ Lunas</span>
                    <?php elseif ($c['status_verifikasi']==='pending' && $c['tanggal_bayar']): ?>
                        <span class="badge" style="background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;">⏳ Diverifikasi</span>
                    <?php elseif ($c['status_verifikasi']==='ditolak'): ?>
                        <span class="badge" style="background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;">❌ Ditolak</span>
                    <?php elseif ($is_late): ?>
                        <span class="badge" style="background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;">⏰ Terlambat</span>
                    <?php else: ?>
                        <span class="badge" style="background:#f1f5f9;color:#64748b;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;">⏳ Belum</span>
                    <?php endif; ?>
                </td>
                <td style="padding:10px 16px;">
                    <?php if ($c['status_bayar']==='lunas'): ?>
                        <span style="color:#10b981;font-size:12px;font-weight:700;">✅ Terbayar</span>
                    <?php elseif ($c['status_verifikasi']==='pending' && $c['tanggal_bayar']): ?>
                        <div style="font-size:12px;color:#92400e;font-weight:600;">⏳ Menunggu Admin</div>
                        <div style="font-size:11px;color:#b45309;"><?= format_datetime($c['tanggal_bayar']) ?></div>
                    <?php elseif ($tagihan_riil <= 0): ?>
                        <span style="color:#10b981;font-size:12px;font-weight:700;">Sudah Lunas Penuh</span>
                    <?php else: ?>
                        <!-- FORM UPLOAD INLINE -->
                        <details style="<?= $c['status_verifikasi']==='ditolak' ? 'open' : '' ?>">
                            <summary style="cursor:pointer;font-size:12px;font-weight:700;color:<?= $is_late ? '#ef4444' : '#2563eb' ?>;list-style:none;display:flex;align-items:center;gap:6px;">
                                <span style="background:<?= $is_late ? '#fee2e2' : '#eff6ff' ?>;color:<?= $is_late ? '#ef4444' : '#2563eb' ?>;border:1px solid <?= $is_late ? '#fca5a5' : '#bfdbfe' ?>;padding:5px 12px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;">
                                    💰 <?= $c['status_verifikasi']==='ditolak' ? 'Upload Ulang' : 'Bayar Sekarang' ?>
                                </span>
                            </summary>
                            <form method="POST" enctype="multipart/form-data" style="margin-top:10px;background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px;">
                                <input type="hidden" name="id_cicilan_bayar" value="<?= $c['id_cicilan'] ?>">
                                <input type="hidden" name="id_pengajuan" value="<?= $id_pengajuan ?>">
                                <div style="font-size:12px;color:#64748b;margin-bottom:8px;">
                                    Upload bukti transfer <b style="color:#2563eb;"><?= format_rupiah($tagihan_riil) ?></b>
                                </div>
                                <input type="file" name="bukti_cicilan" accept=".jpg,.jpeg,.png,.pdf" required
                                       style="width:100%;padding:8px;border:2px dashed #93c5fd;border-radius:8px;background:#eff6ff;font-size:12px;margin-bottom:8px;">
                                <button type="submit" style="background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;border:none;border-radius:8px;padding:8px 16px;font-size:12px;font-weight:700;cursor:pointer;width:100%;">
                                    📤 Kirim Bukti Pembayaran
                                </button>
                            </form>
                        </details>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<!-- KALKULATOR CICILAN -->
<div style="background:linear-gradient(135deg,#f8faff,#eff6ff);border-radius:16px;border:1px solid #bfdbfe;padding:24px;margin-bottom:20px;">
    <h3 style="font-size:16px;font-weight:800;background:linear-gradient(135deg,#1e40af,#6366f1);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;margin-bottom:4px;">🧮 Kalkulator Simulasi Cicilan</h3>
    <p style="font-size:12px;color:#64748b;margin-bottom:16px;">Hitung estimasi cicilan dengan tenor atau anggaran berbeda</p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;" id="kalk-grid">
        <div>
            <label style="font-size:12px;font-weight:700;color:#475569;display:block;margin-bottom:5px;">Harga Rumah (Rp)</label>
            <input type="text" id="kalk-harga" class="form-control" value="<?= $kpr_aktif ? number_format($kpr_aktif['harga'],0,',','.') : '' ?>" placeholder="500.000.000">
        </div>
        <div>
            <label style="font-size:12px;font-weight:700;color:#475569;display:block;margin-bottom:5px;">Uang Muka / DP (Rp)</label>
            <input type="text" id="kalk-dp" class="form-control" value="<?= $kpr_aktif ? number_format($kpr_aktif['uang_muka'],0,',','.') : '' ?>" placeholder="100.000.000">
        </div>
        <div>
            <label style="font-size:12px;font-weight:700;color:#475569;display:block;margin-bottom:5px;">Tenor (Tahun)</label>
            <input type="number" id="kalk-tenor" class="form-control" value="<?= $kpr_aktif ? $kpr_aktif['tenor'] : '' ?>" min="1" max="30" placeholder="15">
        </div>
        <div>
            <label style="font-size:12px;font-weight:700;color:#475569;display:block;margin-bottom:5px;">Bunga (% / tahun)</label>
            <input type="number" id="kalk-bunga" class="form-control" value="<?= $kpr_aktif ? $kpr_aktif['bunga_kpr'] : '' ?>" step="0.01" placeholder="7.5">
        </div>
    </div>
    <div style="background:#fff;border-radius:10px;padding:16px;margin-top:14px;display:grid;grid-template-columns:1fr 1fr;gap:14px;" id="kalk-hasil-grid">
        <div style="text-align:center;">
            <div style="font-size:12px;color:#64748b;margin-bottom:3px;">Cicilan Per Bulan</div>
            <div id="kalk-hasil-cicilan" style="font-size:20px;font-weight:900;background:linear-gradient(135deg,#2563eb,#6366f1);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">-</div>
        </div>
        <div style="text-align:center;">
            <div style="font-size:12px;color:#64748b;margin-bottom:3px;">Total Bayar (Pokok + Bunga)</div>
            <div id="kalk-hasil-total" style="font-size:16px;font-weight:800;color:#ef4444;">-</div>
        </div>
    </div>
    <div style="margin-top:16px;padding-top:14px;border-top:1px solid #dbeafe;">
        <p style="font-size:12px;font-weight:700;color:#1e40af;margin-bottom:10px;">🔄 Hitung berapa lama cicil berdasarkan kemampuan bayar:</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;" id="kalk-alt-grid">
            <div>
                <label style="font-size:12px;font-weight:700;color:#475569;display:block;margin-bottom:5px;">Kemampuan Bayar / Bulan (Rp)</label>
                <input type="text" id="kalk-cicilan-per-bulan" class="form-control" placeholder="3.000.000">
            </div>
            <div>
                <label style="font-size:12px;font-weight:700;color:#475569;display:block;margin-bottom:5px;">Estimasi Lama Pelunasan</label>
                <div style="background:#f8faff;border:2px solid #e2e8f0;border-radius:10px;padding:10px 14px;font-size:15px;font-weight:900;" id="kalk-hasil-lama">-</div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>
<?php endif; ?>
</main>

<script src="../assets/js/script.js"></script>
<script>
// Polling real-time
const cicInitial = {};
setInterval(() => {
    fetch('cicilan.php?ajax=check')
        .then(r => r.json())
        .then(data => {
            let changed = false;
            for (const k in data) {
                if (cicInitial[k] !== undefined && cicInitial[k] !== data[k]) { changed = true; break; }
            }
            if (changed) window.location.reload();
            Object.assign(cicInitial, data);
        }).catch(() => {});
}, 8000);

// Responsive grids
function fixGrids() {
    const w = window.innerWidth;
    ['kpr-grid','kalk-grid','kalk-hasil-grid','kalk-alt-grid'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.gridTemplateColumns = w < 600 ? '1fr' : '1fr 1fr';
    });
}
window.addEventListener('resize', fixGrids); fixGrids();
</script>
<style>
@media(max-width:600px){
    #kpr-grid,#kalk-grid,#kalk-hasil-grid,#kalk-alt-grid{grid-template-columns:1fr!important;}
}
</style>
<?php require_once '../includes/footer.php'; ?>
