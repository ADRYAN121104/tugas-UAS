<?php
// admin/booking/index.php
require_once '../../config/koneksi.php';
require_once '../../config/cek_admin.php';
require_once '../../config/functions.php';
require_once '../../includes/sidebar_admin.php';

$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

// ── Proses Pembatalan Booking ────────────────────────────────────────────────
// Konfirmasi booking sekarang HANYA bisa dilakukan melalui halaman Pembayaran
// (admin klik "Valid & Konfirmasi Booking" di verifikasi pembayaran)
if ($id > 0 && $action === 'batal') {
    $stmt = $db->prepare("SELECT id_rumah, status_booking FROM booking WHERE id_booking = ?");
    $stmt->execute([$id]);
    $booking = $stmt->fetch();

    if ($booking) {
        $id_rumah = $booking['id_rumah'];
        $db->beginTransaction();
        $db->prepare("UPDATE booking SET status_booking = 'dibatalkan' WHERE id_booking = ?")->execute([$id]);
        $db->prepare("UPDATE rumah SET status = 'tersedia' WHERE id_rumah = ?")->execute([$id_rumah]);
        // Jika ada pembayaran terkait, tandai sebagai ditolak
        $db->prepare("UPDATE pembayaran SET status_verifikasi = 'ditolak' WHERE id_booking = ? AND status_verifikasi = 'pending'")->execute([$id]);
        $db->commit();
        set_flash('sukses', 'Booking #BKN-' . $id . ' berhasil dibatalkan & status unit dikembalikan ke Tersedia.');
    } else {
        set_flash('gagal', 'Data booking tidak ditemukan.');
    }
    header('Location: index.php');
    exit;
}

// Filter status
$f_status = trim($_GET['f_status'] ?? '');

$query = "
    SELECT b.*, 
           u.nama_lengkap, u.no_hp, 
           p.nama_perumahan, r.blok, r.kode_unit, 
           r.nama_tipe, r.harga,
           pay.status_verifikasi AS status_bayar,
           pay.bukti_bayar,
           pay.jumlah_bayar,
           pay.tanggal_bayar
    FROM booking b 
    JOIN users u     ON b.id_user = u.id_user 
    JOIN rumah r     ON b.id_rumah = r.id_rumah 
    JOIN perumahan p ON r.id_perumahan = p.id_perumahan 
    LEFT JOIN pembayaran pay ON b.id_booking = pay.id_booking
    WHERE 1=1
";
$params = [];

if (!empty($f_status)) {
    $query   .= " AND b.status_booking = ?";
    $params[] = $f_status;
}
$query .= " ORDER BY b.id_booking DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$list_booking = $stmt->fetchAll();

$count_menunggu = (int)$db->query("SELECT COUNT(*) FROM booking WHERE status_booking = 'menunggu'")->fetchColumn();
$count_belum_bayar = (int)$db->query("
    SELECT COUNT(*) FROM booking b 
    LEFT JOIN pembayaran pay ON b.id_booking = pay.id_booking 
    WHERE b.status_booking = 'menunggu' AND pay.id_pembayaran IS NULL
")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Booking Unit - RumahKPR Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css?v=3">
</head>
<body>
    <?php sidebar_admin('booking'); ?>
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
                <a href="../dashboard.php">Dashboard</a> / <span>Kelola Booking</span>
            </div>
            <?php tampil_flash(); ?>

            <div class="page-header">
                <div class="page-header-left">
                    <h2>📋 Booking Unit</h2>
                    <p>Kelola pemesanan unit. Konfirmasi booking dilakukan otomatis saat verifikasi pembayaran berhasil.</p>
                </div>
            </div>

            <!-- INFO ALUR -->
            <div style="background:linear-gradient(135deg,#f0fdf4,#dcfce7); border:1px solid #86efac; border-radius:12px; padding:14px 20px; margin-bottom:18px;">
                <div style="font-weight:800; font-size:13px; color:#15803d; margin-bottom:6px;">ℹ️ Cara Kerja Konfirmasi Booking</div>
                <div style="font-size:12px; color:#166534; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <span>Customer Booking</span>
                    <span style="color:#86efac;">→</span>
                    <span>Customer Bayar Booking Fee</span>
                    <span style="color:#86efac;">→</span>
                    <a href="../pembayaran/index.php?f_status=pending" style="background:#fef9c3; padding:2px 8px; border-radius:10px; color:#854d0e; font-weight:700; text-decoration:none;">⚠️ Admin Verifikasi di Pembayaran</a>
                    <span style="color:#86efac;">→</span>
                    <span style="background:#d1fae5; padding:2px 8px; border-radius:10px; color:#065f46; font-weight:700;">✅ Booking Otomatis Dikonfirmasi</span>
                </div>
            </div>

            <!-- STATS SINGKAT -->
            <?php if ($count_belum_bayar > 0): ?>
            <div style="background:#fff7ed; border:1px solid #fed7aa; border-radius:10px; padding:12px 18px; margin-bottom:18px; display:flex; align-items:center; gap:10px;">
                <span style="font-size:20px;">📭</span>
                <div>
                    <span style="font-weight:800; color:#c2410c; font-size:13px;"><?= $count_belum_bayar ?> booking belum ada pembayaran.</span>
                    <span style="color:#9a3412; font-size:12px;"> Customer mungkin belum melakukan pembayaran booking fee.</span>
                </div>
            </div>
            <?php endif; ?>

            <!-- FILTER BAR -->
            <div class="panel" style="margin-bottom: 18px;">
                <div class="panel-body" style="padding: 16px 20px;">
                    <form method="GET" action="" class="search-bar" style="margin-bottom:0;">
                        <select name="f_status" style="max-width:250px;">
                            <option value="">-- Semua Status Booking --</option>
                            <option value="menunggu"     <?= $f_status === 'menunggu'     ? 'selected' : '' ?>>⏳ Menunggu</option>
                            <option value="dikonfirmasi" <?= $f_status === 'dikonfirmasi' ? 'selected' : '' ?>>✅ Dikonfirmasi</option>
                            <option value="dibatalkan"   <?= $f_status === 'dibatalkan'   ? 'selected' : '' ?>>❌ Dibatalkan</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">🔍 Filter</button>
                        <?php if (!empty($f_status)): ?>
                            <a href="index.php" class="btn btn-gray btn-sm">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="panel">
                <div class="panel-body" style="padding:0;">
                    <div class="tbl-wrap">
                        <table>
                            <thead>
                                <thead>
                                    <tr>
                                        <th>No Booking</th>
                                        <th>Customer</th>
                                        <th>Detail Rumah & Tipe</th>
                                        <th>Tgl Booking</th>
                                        <th>Booking Fee</th>
                                        <th style="text-align:center;">Status Pembayaran</th>
                                        <th>Status Booking</th>
                                        <th style="width:200px; text-align:center;">Aksi</th>
                                    </tr>
                                </thead>
                            </thead>
                            <tbody>
                                <?php if (empty($list_booking)): ?>
                                    <tr><td colspan="8" class="empty">Tidak ada data booking yang sesuai.</td></tr>
                                <?php else: foreach($list_booking as $b): ?>
                                    <?php
                                    $status_bayar = $b['status_bayar'] ?? null;
                                    $bukti = $b['bukti_bayar'] ?? '';
                                    $is_gateway = in_array(strtoupper($bukti), ['GATEWAY','VIA_GATEWAY','VIA_GATEWAY_PENDING','GATEWAY_PENDING']);
                                    ?>
                                    <tr>
                                        <td><b>#BKN-<?= $b['id_booking'] ?></b></td>
                                        <td>
                                            <b><?= htmlspecialchars($b['nama_lengkap']) ?></b><br>
                                            <small><a href="https://wa.me/<?= preg_replace('/\D/','',$b['no_hp']) ?>" target="_blank" style="text-decoration:none; color:var(--success); font-weight:700;">📞 <?= htmlspecialchars($b['no_hp']) ?></a></small>
                                        </td>
                                        <td>
                                            <b><?= htmlspecialchars($b['nama_perumahan']) ?></b><br>
                                            <small>Tipe <?= htmlspecialchars($b['nama_tipe']) ?> &bull; Blok <?= htmlspecialchars($b['blok'].'-'.$b['kode_unit']) ?></small>
                                        </td>
                                        <td><?= format_tanggal($b['tanggal_booking']) ?></td>
                                        <td style="font-weight:700; color:var(--primary);"><?= format_rupiah($b['booking_fee']) ?></td>
                                        <td style="text-align:center;">
                                            <?php if (!$status_bayar): ?>
                                                <span style="background:#f1f5f9; color:#64748b; font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px;">📭 Belum Bayar</span>
                                            <?php elseif ($status_bayar === 'pending'): ?>
                                                <span style="background:#fef3c7; color:#92400e; font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px;">⏳ Menunggu Verifikasi</span><br>
                                                <a href="../pembayaran/index.php?f_status=pending" style="font-size:10px; color:#2563eb; text-decoration:underline; font-weight:600;">→ Verifikasi sekarang</a>
                                            <?php elseif ($status_bayar === 'valid'): ?>
                                                <?php if ($is_gateway): ?>
                                                    <span style="background:#d1fae5; color:#065f46; font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px;">✅ Valid (Gateway)</span>
                                                <?php else: ?>
                                                    <span style="background:#d1fae5; color:#065f46; font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px;">✅ Valid (Transfer)</span>
                                                <?php endif; ?>
                                            <?php elseif ($status_bayar === 'ditolak'): ?>
                                                <span style="background:#fee2e2; color:#991b1b; font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px;">❌ Ditolak</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= badge_booking($b['status_booking']) ?></td>
                                        <td style="text-align:center;">
                                            <?php if ($b['status_booking'] === 'menunggu'): ?>
                                                <?php if (!$status_bayar): ?>
                                                    <span style="color:#94a3b8; font-size:11px; line-height:1.4; display:block;">Menunggu customer<br>melakukan pembayaran</span>
                                                <?php elseif ($status_bayar === 'pending'): ?>
                                                    <a href="../pembayaran/index.php?f_status=pending" class="btn btn-sm" style="background:#fbbf24; color:#92400e; font-weight:700; font-size:11px;">⚠️ Verifikasi Pembayaran</a>
                                                <?php else: ?>
                                                    <a href="index.php?action=batal&id=<?= $b['id_booking'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Batalkan booking ini?')">❌ Batalkan</a>
                                                <?php endif; ?>
                                            <?php elseif ($b['status_booking'] === 'dikonfirmasi'): ?>
                                                <div style="display:flex; flex-direction:column; gap:4px; align-items:center;">
                                                    <span style="color:var(--success); font-size:11px; font-weight:700;">✅ Terkonfirmasi</span>
                                                    <a href="index.php?action=batal&id=<?= $b['id_booking'] ?>" class="btn btn-danger btn-sm" style="font-size:10px;" onclick="return confirm('BATALKAN booking yang sudah dikonfirmasi ini? Unit akan dikembalikan ke status Tersedia.')">Batalkan</a>
                                                </div>
                                            <?php else: ?>
                                                <span style="color:var(--muted); font-size:12px;">Dibatalkan</span>
                                            <?php endif; ?>
                                        </td>
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
