<?php
// admin/pembayaran/index.php
require_once '../../config/koneksi.php';
require_once '../../config/cek_admin.php';

if (isset($_GET['ajax']) && $_GET['ajax'] === 'check') {
    header('Content-Type: application/json');
    $count = $db->query("SELECT COUNT(*) FROM pembayaran WHERE status_verifikasi = 'pending'")->fetchColumn();
    echo json_encode(['count' => (int)$count]);
    exit;
}

require_once '../../config/functions.php';
require_once '../../includes/sidebar_admin.php';

$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

// Verifikasi Pembayaran
if ($id > 0 && in_array($action, ['valid', 'tolak'])) {
    // Ambil data booking & rumah terkait
    $stmt = $db->prepare("SELECT p.id_booking, b.id_rumah 
                          FROM pembayaran p 
                          JOIN booking b ON p.id_booking = b.id_booking 
                          WHERE p.id_pembayaran = ?");
    $stmt->execute([$id]);
    $pay = $stmt->fetch();

    if ($pay) {
        $id_booking = $pay['id_booking'];
        $id_rumah   = $pay['id_rumah'];

        if ($action === 'valid') {
            $db->beginTransaction();
            // Set status pembayaran menjadi valid
            $db->prepare("UPDATE pembayaran SET status_verifikasi = 'valid' WHERE id_pembayaran = ?")->execute([$id]);
            // Set status booking menjadi dikonfirmasi
            $db->prepare("UPDATE booking SET status_booking = 'dikonfirmasi' WHERE id_booking = ?")->execute([$id_booking]);
            // Set status rumah menjadi booking
            $db->prepare("UPDATE rumah SET status = 'booking' WHERE id_rumah = ?")->execute([$id_rumah]);
            $db->commit();
            set_flash('sukses', 'Pembayaran diverifikasi VALID. Booking dikonfirmasi & unit dikunci.');
        } elseif ($action === 'tolak') {
            $db->prepare("UPDATE pembayaran SET status_verifikasi = 'ditolak' WHERE id_pembayaran = ?")->execute([$id]);
            set_flash('sukses', 'Pembayaran ditolak.');
        }
    } else {
        set_flash('gagal', 'Data pembayaran tidak ditemukan.');
    }
    header('Location: index.php');
    exit;
}

// Filter status verifikasi
$f_status = trim($_GET['f_status'] ?? '');

$query = "SELECT pay.*, b.tanggal_booking, b.booking_fee, u.nama_lengkap, pr.nama_perumahan, r.blok, r.kode_unit 
          FROM pembayaran pay 
          JOIN booking b ON pay.id_booking = b.id_booking 
          JOIN users u ON b.id_user = u.id_user 
          JOIN rumah r ON b.id_rumah = r.id_rumah 
          JOIN perumahan pr ON r.id_perumahan = pr.id_perumahan 
          WHERE 1=1";
$params = [];

if (!empty($f_status)) {
    $query .= " AND pay.status_verifikasi = ?";
    $params[] = $f_status;
}
$query .= " ORDER BY pay.tanggal_bayar DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$list_pembayaran = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Pembayaran - RumahKPR Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <?php sidebar_admin('pembayaran'); ?>
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
                <a href="../dashboard.php">Dashboard</a> / <span>Verifikasi Pembayaran</span>
            </div>
            <?php tampil_flash(); ?>

            <div class="page-header">
                <div class="page-header-left">
                    <h2>💰 Pembayaran Booking Fee</h2>
                    <p>Verifikasi bukti pembayaran transfer bank dari customer untuk mengunci unit rumah</p>
                </div>
            </div>

            <!-- FILTER BAR -->
            <div class="panel" style="margin-bottom: 18px;">
                <div class="panel-body" style="padding: 16px 20px;">
                    <form method="GET" action="" class="search-bar" style="margin-bottom:0;">
                        <select name="f_status" style="max-width:250px;">
                            <option value="">-- Semua Status Verifikasi --</option>
                            <option value="pending" <?= $f_status === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                            <option value="valid" <?= $f_status === 'valid' ? 'selected' : '' ?>>✅ Valid</option>
                            <option value="ditolak" <?= $f_status === 'ditolak' ? 'selected' : '' ?>>❌ Ditolak</option>
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
                                <tr>
                                    <th>Customer</th>
                                    <th>Properti & Unit</th>
                                    <th>Tanggal Bayar</th>
                                    <th>Jumlah Bayar</th>
                                    <th style="text-align:center;">Bukti</th>
                                    <th>Status</th>
                                    <th style="width:220px; text-align:center;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($list_pembayaran)): ?>
                                    <tr><td colspan="7" class="empty">Tidak ada data pembayaran yang sesuai.</td></tr>
                                <?php else: foreach($list_pembayaran as $p): ?>
                                    <tr>
                                        <td><b><?= htmlspecialchars($p['nama_lengkap']) ?></b></td>
                                        <td>
                                            <b><?= htmlspecialchars($p['nama_perumahan']) ?></b><br>
                                            <small style="color:var(--primary); font-weight:700;">Blok <?= htmlspecialchars($p['blok'].'-'.$p['kode_unit']) ?></small>
                                        </td>
                                        <td><?= format_datetime($p['tanggal_bayar']) ?></td>
                                        <td style="font-weight:700; color:var(--success);"><?= format_rupiah($p['jumlah_bayar']) ?></td>
                                        <td style="text-align:center;">
                                            <?php if ($p['bukti_bayar']): ?>
                                                <a href="../../uploads/bukti_bayar/<?= htmlspecialchars($p['bukti_bayar']) ?>" target="_blank" class="btn btn-outline btn-sm" style="padding: 3px 8px; font-size: 11px;">📎 Lihat Bukti</a>
                                            <?php else: ?>
                                                <span style="color:var(--muted);">Tidak ada</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= badge_pembayaran($p['status_verifikasi']) ?></td>
                                        <td style="text-align:center;">
                                            <?php if ($p['status_verifikasi'] === 'pending'): ?>
                                                <a href="index.php?action=valid&id=<?= $p['id_pembayaran'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Konfirmasi bahwa bukti pembayaran ini VALID?')">✅ Valid</a>
                                                <a href="index.php?action=tolak&id=<?= $p['id_pembayaran'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('TOLAK bukti pembayaran ini?')">❌ Tolak</a>
                                            <?php else: ?>
                                                <span style="color:var(--muted); font-size:12px; font-weight:600;">Selesai diverifikasi</span>
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
    <script>
        let initialPendingCount = <?= (int)$db->query("SELECT COUNT(*) FROM pembayaran WHERE status_verifikasi = 'pending'")->fetchColumn() ?>;
        setInterval(() => {
            fetch('index.php?ajax=check')
                .then(res => res.json())
                .then(data => {
                    if (data.count !== initialPendingCount) {
                        window.location.reload();
                    }
                })
                .catch(err => console.error(err));
        }, 5000);
    </script>
</body>
</html>
