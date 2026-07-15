<?php
// admin/pembayaran/index.php
require_once '../../config/koneksi.php';
require_once '../../config/cek_admin.php';

// ── AJAX: cek jumlah pending untuk polling real-time ────────────────────────
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

// ── Verifikasi Pembayaran (Valid / Tolak) ────────────────────────────────────
if ($id > 0 && in_array($action, ['valid', 'tolak'])) {
    $stmt = $db->prepare("
        SELECT p.id_booking, b.id_rumah 
        FROM pembayaran p 
        JOIN booking b ON p.id_booking = b.id_booking 
        WHERE p.id_pembayaran = ?
    ");
    $stmt->execute([$id]);
    $pay = $stmt->fetch();

    if ($pay) {
        $id_booking = $pay['id_booking'];
        $id_rumah   = $pay['id_rumah'];

        if ($action === 'valid') {
            $db->beginTransaction();
            // 1. Set pembayaran valid
            $db->prepare("UPDATE pembayaran SET status_verifikasi = 'valid' WHERE id_pembayaran = ?")->execute([$id]);
            // 2. Konfirmasi booking
            $db->prepare("UPDATE booking SET status_booking = 'dikonfirmasi' WHERE id_booking = ?")->execute([$id_booking]);
            // 3. Kunci unit
            $db->prepare("UPDATE rumah SET status = 'booking' WHERE id_rumah = ?")->execute([$id_rumah]);
            $db->commit();
            set_flash('sukses', '✅ Pembayaran diverifikasi VALID. Booking dikonfirmasi & unit dikunci. Customer sudah bisa ajukan KPR.');
        } elseif ($action === 'tolak') {
            $db->prepare("UPDATE pembayaran SET status_verifikasi = 'ditolak' WHERE id_pembayaran = ?")->execute([$id]);
            set_flash('gagal', '❌ Pembayaran ditolak. Customer perlu mengirim ulang bukti pembayaran yang valid.');
        }
    } else {
        set_flash('gagal', 'Data pembayaran tidak ditemukan.');
    }
    header('Location: index.php');
    exit;
}

// ── Filter status verifikasi ─────────────────────────────────────────────────
$f_status = trim($_GET['f_status'] ?? '');

$query = "
    SELECT pay.*, 
           b.tanggal_booking, b.booking_fee, b.status_booking,
           u.nama_lengkap, u.no_hp,
           pr.nama_perumahan, r.blok, r.kode_unit
    FROM pembayaran pay
    JOIN booking b   ON pay.id_booking = b.id_booking
    JOIN users u     ON b.id_user = u.id_user
    JOIN rumah r     ON b.id_rumah = r.id_rumah
    JOIN perumahan pr ON r.id_perumahan = pr.id_perumahan
    WHERE 1=1
";
$params = [];

if (!empty($f_status)) {
    $query  .= " AND pay.status_verifikasi = ?";
    $params[] = $f_status;
}
$query .= " ORDER BY pay.tanggal_bayar DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$list_pembayaran = $stmt->fetchAll();

$pending_count = (int)$db->query("SELECT COUNT(*) FROM pembayaran WHERE status_verifikasi = 'pending'")->fetchColumn();
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
                <a href="../dashboard.php">Dashboard</a> / <span>Verifikasi Pembayaran</span>
            </div>
            <?php tampil_flash(); ?>

            <div class="page-header">
                <div class="page-header-left">
                    <h2>💰 Pembayaran Booking Fee</h2>
                    <p>Verifikasi bukti pembayaran dari customer untuk mengkonfirmasi booking & mengunci unit rumah</p>
                </div>
                <?php if ($pending_count > 0): ?>
                <div style="background:#fef3c7; border:1px solid #fbbf24; border-radius:10px; padding:10px 18px; display:flex; align-items:center; gap:10px;">
                    <span style="font-size:20px;">⚠️</span>
                    <div>
                        <div style="font-weight:800; font-size:14px; color:#92400e;"><?= $pending_count ?> Pembayaran Menunggu Verifikasi</div>
                        <div style="font-size:12px; color:#b45309;">Segera verifikasi agar customer bisa melanjutkan pengajuan KPR</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- PANDUAN ALUR -->
            <div style="background:linear-gradient(135deg,#eff6ff,#e0f2fe); border:1px solid #bfdbfe; border-radius:12px; padding:16px 20px; margin-bottom:18px; display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
                <span style="font-size:24px;">📋</span>
                <div>
                    <div style="font-weight:800; font-size:13.5px; color:#1e40af; margin-bottom:4px;">Alur Verifikasi Pembayaran</div>
                    <div style="font-size:12px; color:#3b82f6; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                        <span>Customer Bayar</span>
                        <span style="color:#93c5fd;">→</span>
                        <span style="background:#fef3c7; padding:2px 8px; border-radius:10px; color:#92400e; font-weight:700;">⏳ Menunggu Verifikasi Admin</span>
                        <span style="color:#93c5fd;">→</span>
                        <span>Admin Klik ✅ Valid</span>
                        <span style="color:#93c5fd;">→</span>
                        <span style="background:#d1fae5; padding:2px 8px; border-radius:10px; color:#065f46; font-weight:700;">✅ Booking Dikonfirmasi</span>
                        <span style="color:#93c5fd;">→</span>
                        <span>Customer Ajukan KPR</span>
                    </div>
                </div>
            </div>

            <!-- FILTER BAR -->
            <div class="panel" style="margin-bottom: 18px;">
                <div class="panel-body" style="padding: 16px 20px;">
                    <form method="GET" action="" class="search-bar" style="margin-bottom:0;">
                        <select name="f_status" style="max-width:250px;">
                            <option value="">-- Semua Status Verifikasi --</option>
                            <option value="pending"  <?= $f_status === 'pending'  ? 'selected' : '' ?>>⏳ Pending (Menunggu)</option>
                            <option value="valid"    <?= $f_status === 'valid'    ? 'selected' : '' ?>>✅ Valid (Terkonfirmasi)</option>
                            <option value="ditolak"  <?= $f_status === 'ditolak'  ? 'selected' : '' ?>>❌ Ditolak</option>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">🔍 Filter</button>
                        <?php if (!empty($f_status)): ?>
                            <a href="index.php" class="btn btn-gray btn-sm">Reset</a>
                        <?php endif; ?>
                        <?php if ($pending_count > 0): ?>
                            <a href="index.php?f_status=pending" class="btn btn-sm" style="background:#fbbf24; color:#92400e; font-weight:700;">
                                ⚠️ Lihat <?= $pending_count ?> Pending
                            </a>
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
                                    <th style="text-align:center;">Metode / Bukti</th>
                                    <th>Status Booking</th>
                                    <th>Status Bayar</th>
                                    <th style="width:220px; text-align:center;">Aksi Admin</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($list_pembayaran)): ?>
                                    <tr><td colspan="8" class="empty">Tidak ada data pembayaran yang sesuai.</td></tr>
                                <?php else: foreach($list_pembayaran as $p): ?>
                                    <tr>
                                        <td>
                                            <b><?= htmlspecialchars($p['nama_lengkap']) ?></b><br>
                                            <small><a href="https://wa.me/<?= preg_replace('/\D/','',$p['no_hp']) ?>" target="_blank" style="color:var(--success); font-weight:700; text-decoration:none;">📞 <?= htmlspecialchars($p['no_hp']) ?></a></small>
                                        </td>
                                        <td>
                                            <b><?= htmlspecialchars($p['nama_perumahan']) ?></b><br>
                                            <small style="color:var(--primary); font-weight:700;">Blok <?= htmlspecialchars($p['blok'].'-'.$p['kode_unit']) ?></small>
                                        </td>
                                        <td><?= format_datetime($p['tanggal_bayar']) ?></td>
                                        <td style="font-weight:700; color:var(--success);"><?= format_rupiah($p['jumlah_bayar']) ?></td>
                                        <td style="text-align:center;">
                                            <?php
                                            $bukti = $p['bukti_bayar'] ?? '';
                                            $is_gateway = in_array(strtoupper($bukti), ['GATEWAY', 'VIA_GATEWAY', 'VIA_GATEWAY_PENDING', 'GATEWAY_PENDING']);
                                            ?>
                                            <?php if ($is_gateway): ?>
                                                <span style="display:inline-flex; align-items:center; gap:4px; background:#eff6ff; border:1px solid #bfdbfe; color:#1d4ed8; font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px;">
                                                    💳 Payment Gateway
                                                </span>
                                            <?php elseif ($bukti): ?>
                                                <a href="../../uploads/bukti_bayar/<?= htmlspecialchars($bukti) ?>" target="_blank" class="btn btn-outline btn-sm" style="padding:3px 8px; font-size:11px;">📎 Lihat Bukti</a>
                                            <?php else: ?>
                                                <span style="color:var(--muted); font-size:12px;">Tidak ada</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $sb = $p['status_booking'];
                                            $colors = ['menunggu'=>['bg'=>'#fef3c7','color'=>'#92400e','label'=>'⏳ Menunggu'], 'dikonfirmasi'=>['bg'=>'#d1fae5','color'=>'#065f46','label'=>'✅ Dikonfirmasi'], 'dibatalkan'=>['bg'=>'#fee2e2','color'=>'#991b1b','label'=>'❌ Dibatalkan']];
                                            $c = $colors[$sb] ?? ['bg'=>'#f1f5f9','color'=>'#64748b','label'=>ucfirst($sb)];
                                            echo "<span style=\"background:{$c['bg']}; color:{$c['color']}; font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; white-space:nowrap;\">{$c['label']}</span>";
                                            ?>
                                        </td>
                                        <td><?= badge_pembayaran($p['status_verifikasi']) ?></td>
                                        <td style="text-align:center;">
                                            <?php if ($p['status_verifikasi'] === 'pending'): ?>
                                                <div style="display:flex; flex-direction:column; gap:6px; align-items:center;">
                                                    <a href="index.php?action=valid&id=<?= $p['id_pembayaran'] ?>" 
                                                       class="btn btn-success btn-sm" style="width:100%; justify-content:center;"
                                                       onclick="return confirm('Konfirmasi bahwa pembayaran ini VALID?\n\nIni akan otomatis mengkonfirmasi booking dan mengunci unit rumah.')">
                                                        ✅ Valid & Konfirmasi Booking
                                                    </a>
                                                    <a href="index.php?action=tolak&id=<?= $p['id_pembayaran'] ?>" 
                                                       class="btn btn-danger btn-sm" style="width:100%; justify-content:center;"
                                                       onclick="return confirm('TOLAK pembayaran ini?\n\nCustomer akan diminta mengirim ulang bukti.')">
                                                        ❌ Tolak
                                                    </a>
                                                </div>
                                            <?php elseif ($p['status_verifikasi'] === 'valid'): ?>
                                                <span style="color:var(--success); font-size:12px; font-weight:700;">✅ Sudah Diverifikasi</span>
                                            <?php else: ?>
                                                <span style="color:var(--danger); font-size:12px; font-weight:700;">❌ Ditolak</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Real-time notification bar -->
            <div id="realtime-bar" style="display:none; position:fixed; bottom:20px; right:20px; background:#2563eb; color:#fff; padding:12px 20px; border-radius:12px; font-weight:700; font-size:13px; box-shadow:0 4px 20px rgba(37,99,235,0.4); z-index:9999; cursor:pointer;" onclick="window.location.reload()">
                🔔 Ada pembayaran baru masuk! Klik untuk refresh.
            </div>
        </main>
    </div>
    <script src="../../assets/js/script.js"></script>
    <script>
        let knownCount = <?= $pending_count ?>;
        setInterval(() => {
            fetch('index.php?ajax=check')
                .then(r => r.json())
                .then(data => {
                    if (data.count > knownCount) {
                        document.getElementById('realtime-bar').style.display = 'block';
                        document.title = '🔔 (' + data.count + ') Pembayaran Baru — RumahKPR Admin';
                    }
                    knownCount = data.count;
                })
                .catch(() => {});
        }, 8000); // Poll setiap 8 detik
    </script>
</body>
</html>
