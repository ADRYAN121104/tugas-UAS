<?php
// admin/booking/index.php
require_once '../../config/koneksi.php';
require_once '../../config/cek_admin.php';
require_once '../../config/functions.php';
require_once '../../includes/sidebar_admin.php';

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

// Proses Konfirmasi / Pembatalan Booking
if ($id > 0 && in_array($action, ['konfirmasi', 'batal'])) {
    $stmt = $db->prepare("SELECT id_rumah, status_booking FROM booking WHERE id_booking = ?");
    $stmt->execute([$id]);
    $booking = $stmt->fetch();

    if ($booking) {
        $id_rumah = $booking['id_rumah'];
        if ($action === 'konfirmasi') {
            $db->beginTransaction();
            $db->prepare("UPDATE booking SET status_booking = 'dikonfirmasi' WHERE id_booking = ?")->execute([$id]);
            $db->prepare("UPDATE rumah SET status = 'booking' WHERE id_rumah = ?")->execute([$id_rumah]);
            $db->commit();
            set_flash('sukses', 'Booking berhasil dikonfirmasi & status unit diperbarui menjadi Booking.');
        } elseif ($action === 'batal') {
            $db->beginTransaction();
            $db->prepare("UPDATE booking SET status_booking = 'dibatalkan' WHERE id_booking = ?")->execute([$id]);
            $db->prepare("UPDATE rumah SET status = 'tersedia' WHERE id_rumah = ?")->execute([$id_rumah]);
            $db->commit();
            set_flash('sukses', 'Booking berhasil dibatalkan & status unit dikembalikan menjadi Tersedia.');
        }
    } else {
        set_flash('gagal', 'Data booking tidak ditemukan.');
    }
    header('Location: index.php');
    exit;
}

// Filter status
$f_status = trim($_GET['f_status'] ?? '');

$query = "SELECT b.*, u.nama_lengkap, u.no_hp, p.nama_perumahan, r.blok, r.kode_unit, t.nama_tipe, t.harga 
          FROM booking b 
          JOIN users u ON b.id_user = u.id_user 
          JOIN rumah r ON b.id_rumah = r.id_rumah 
          JOIN perumahan p ON r.id_perumahan = p.id_perumahan 
          JOIN tipe_rumah t ON r.id_tipe = t.id_tipe 
          WHERE 1=1";
$params = [];

if (!empty($f_status)) {
    $query .= " AND b.status_booking = ?";
    $params[] = $f_status;
}
$query .= " ORDER BY b.id_booking DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$list_booking = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Booking Unit - RumahKPR Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <?php sidebar_admin('booking'); ?>
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
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a> / <span>Kelola Booking</span>
            </div>
            <?php tampil_flash(); ?>

            <div class="page-header">
                <div class="page-header-left">
                    <h2>📋 Booking Unit</h2>
                    <p>Konfirmasi pemesanan unit, kelola status, dan hubungi calon pembeli</p>
                </div>
            </div>

            <!-- FILTER BAR -->
            <div class="panel" style="margin-bottom: 18px;">
                <div class="panel-body" style="padding: 16px 20px;">
                    <form method="GET" action="" class="search-bar" style="margin-bottom:0;">
                        <select name="f_status" style="max-width:250px;">
                            <option value="">-- Semua Status Booking --</option>
                            <option value="menunggu" <?= $f_status === 'menunggu' ? 'selected' : '' ?>>Menunggu</option>
                            <option value="dikonfirmasi" <?= $f_status === 'dikonfirmasi' ? 'selected' : '' ?>>Dikonfirmasi</option>
                            <option value="dibatalkan" <?= $f_status === 'dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
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
                                        <th>Tanggal Booking</th>
                                        <th>Booking Fee</th>
                                        <th>Status</th>
                                        <th style="width:230px; text-align:center;">Aksi</th>
                                    </tr>
                                </thead>
                            </thead>
                            <tbody>
                                <?php if (empty($list_booking)): ?>
                                    <tr><td colspan="7" class="empty">Tidak ada data booking yang sesuai.</td></tr>
                                <?php else: foreach($list_booking as $b): ?>
                                    <tr>
                                        <td><b>#BKN-<?= $b['id_booking'] ?></b></td>
                                        <td>
                                            <b><?= htmlspecialchars($b['nama_lengkap']) ?></b><br>
                                            <small><a href="https://wa.me/<?= preg_replace('/\D/', '', $b['no_hp']) ?>" target="_blank" style="text-decoration:none; color:var(--success); font-weight:700;">📞 <?= htmlspecialchars($b['no_hp']) ?></a></small>
                                        </td>
                                        <td>
                                            <b><?= htmlspecialchars($b['nama_perumahan']) ?></b><br>
                                            <small>Tipe <?= htmlspecialchars($b['nama_tipe']) ?> &bull; Blok <?= htmlspecialchars($b['blok'].'-'.$b['kode_unit']) ?></small>
                                        </td>
                                        <td><?= format_tanggal($b['tanggal_booking']) ?></td>
                                        <td style="font-weight:700; color:var(--primary);"><?= format_rupiah($b['booking_fee']) ?></td>
                                        <td><?= badge_booking($b['status_booking']) ?></td>
                                        <td style="text-align:center;">
                                            <?php if ($b['status_booking'] === 'menunggu'): ?>
                                                <a href="index.php?action=konfirmasi&id=<?= $b['id_booking'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Apakah Anda yakin ingin MENGONFIRMASI booking ini?')">✅ Konfirmasi</a>
                                                <a href="index.php?action=batal&id=<?= $b['id_booking'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Apakah Anda yakin ingin MEMBATALKAN booking ini?')">❌ Batalkan</a>
                                            <?php else: ?>
                                                <span style="color:var(--muted); font-size:12px; font-weight:600;">Sudah diproses</span>
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
