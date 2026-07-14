<?php
// admin/pembayaran/index.php - Verifikasi Uang Muka (DP) KPR
require_once '../../config/koneksi.php';
require_once '../../config/cek_admin.php';

// ── AJAX: cek jumlah pending untuk polling real-time ────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'check') {
    header('Content-Type: application/json');
    $count = $db->query("SELECT COUNT(*) FROM pembayaran_dp WHERE status_verifikasi = 'pending'")->fetchColumn();
    echo json_encode(['count' => (int)$count]);
    exit;
}

require_once '../../config/functions.php';
require_once '../../includes/sidebar_admin.php';

$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

// ── Verifikasi Pembayaran DP (Valid / Tolak) ──────────────────────────────────
if ($id > 0 && in_array($action, ['valid', 'tolak'])) {
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

        if ($action === 'valid') {
            $db->beginTransaction();
            // 1. Set pembayaran DP valid
            $db->prepare("UPDATE pembayaran_dp SET status_verifikasi = 'valid' WHERE id_dp = ?")->execute([$id]);
            // 2. Update status pengajuan KPR ke akad_kredit
            $db->prepare("UPDATE pengajuan_kpr SET status_pengajuan = 'akad_kredit' WHERE id_pengajuan = ?")->execute([$id_pengajuan]);
            // 3. Tambahkan tracking status akad kredit
            $db->prepare("INSERT INTO tracking_pengajuan (id_pengajuan, status, keterangan, tanggal_update) VALUES (?, 'akad_kredit', 'Pembayaran Uang Muka (DP) tervalidasi VALID. Pengajuan berlanjut ke tahap Akad Kredit.', NOW())")->execute([$id_pengajuan]);
            // 4. Ubah status rumah menjadi terjual
            $db->prepare("UPDATE rumah SET status = 'terjual' WHERE id_rumah = ?")->execute([$id_rumah]);
            
            $db->commit();
            set_flash('sukses', '✅ Pembayaran DP diverifikasi VALID. Status KPR saat ini adalah Akad Kredit & Unit Rumah telah terjual.');
        } elseif ($action === 'tolak') {
            $db->beginTransaction();
            // 1. Set pembayaran DP ditolak
            $db->prepare("UPDATE pembayaran_dp SET status_verifikasi = 'ditolak' WHERE id_dp = ?")->execute([$id]);
            // 2. Tambahkan tracking status re-upload DP
            $db->prepare("INSERT INTO tracking_pengajuan (id_pengajuan, status, keterangan, tanggal_update) VALUES (?, 'disetujui', 'Bukti pembayaran DP ditolak oleh admin. Customer diminta mengunggah ulang bukti transfer yang valid.', NOW())")->execute([$id_pengajuan]);
            
            $db->commit();
            set_flash('gagal', '❌ Pembayaran DP ditolak. Customer akan diminta mengirim ulang bukti pembayaran DP yang valid.');
        }
    } else {
        set_flash('gagal', 'Data pembayaran DP tidak ditemukan.');
    }
    header('Location: index.php');
    exit;
}

// ── Filter status verifikasi ─────────────────────────────────────────────────
$f_status = trim($_GET['f_status'] ?? '');

$query = "
    SELECT dp.*, 
           pk.tanggal_pengajuan, pk.status_pengajuan,
           u.nama_lengkap, u.no_hp,
           pr.nama_perumahan, r.blok, r.kode_unit
    FROM pembayaran_dp dp
    JOIN pengajuan_kpr pk ON dp.id_pengajuan = pk.id_pengajuan
    JOIN users u         ON pk.id_user = u.id_user
    JOIN rumah r         ON pk.id_rumah = r.id_rumah
    JOIN perumahan pr    ON r.id_perumahan = pr.id_perumahan
    WHERE 1=1
";
$params = [];

if (!empty($f_status)) {
    $query  .= " AND dp.status_verifikasi = ?";
    $params[] = $f_status;
}
$query .= " ORDER BY dp.tanggal_bayar DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$list_pembayaran = $stmt->fetchAll();

$pending_count = (int)$db->query("SELECT COUNT(*) FROM pembayaran_dp WHERE status_verifikasi = 'pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Pembayaran DP - RumahKPR Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css?v=3">
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
                <a href="../dashboard.php">Dashboard</a> / <span>Verifikasi Pembayaran DP</span>
            </div>
            <?php tampil_flash(); ?>

            <div class="page-header">
                <div class="page-header-left">
                    <h2>💰 Pembayaran Uang Muka (DP)</h2>
                    <p>Verifikasi bukti pembayaran uang muka (DP) dari customer untuk melanjutkan ke tahap Akad Kredit</p>
                </div>
                <?php if ($pending_count > 0): ?>
                <div style="background:#fef3c7; border:1px solid #fbbf24; border-radius:10px; padding:10px 18px; display:flex; align-items:center; gap:10px;">
                    <span style="font-size:20px;">⚠️</span>
                    <div>
                        <div style="font-weight:800; font-size:14px; color:#92400e;"><?= $pending_count ?> DP Menunggu Verifikasi</div>
                        <div style="font-size:12px; color:#b45309;">Segera lakukan verifikasi agar proses KPR customer dapat diselesaikan</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- PANDUAN ALUR -->
            <div style="background:linear-gradient(135deg,#eff6ff,#e0f2fe); border:1px solid #bfdbfe; border-radius:12px; padding:16px 20px; margin-bottom:18px; display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
                <span style="font-size:24px;">📋</span>
                <div>
                    <div style="font-weight:800; font-size:13.5px; color:#1e40af; margin-bottom:4px;">Alur Verifikasi Uang Muka (DP)</div>
                    <div style="font-size:12px; color:#3b82f6; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                        <span>KPR Disetujui Bank</span>
                        <span style="color:#93c5fd;">→</span>
                        <span>Customer Bayar DP</span>
                        <span style="color:#93c5fd;">→</span>
                        <span style="background:#fef3c7; padding:2px 8px; border-radius:10px; color:#92400e; font-weight:700;">⏳ Menunggu Verifikasi Admin</span>
                        <span style="color:#93c5fd;">→</span>
                        <span>Admin Klik ✅ Valid</span>
                        <span style="color:#93c5fd;">→</span>
                        <span style="background:#d1fae5; padding:2px 8px; border-radius:10px; color:#065f46; font-weight:700;">🤝 Tahap Akad Kredit</span>
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
                                    <th>Jumlah DP Awal</th>
                                    <th style="text-align:center;">Bukti DP</th>
                                    <th>Status KPR</th>
                                    <th>Status Bayar</th>
                                    <th style="width:220px; text-align:center;">Aksi Admin</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($list_pembayaran)): ?>
                                    <tr><td colspan="8" class="empty">Tidak ada data pembayaran DP yang sesuai.</td></tr>
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
                                        <td style="font-weight:700; color:var(--success);"><?= format_rupiah($p['jumlah_dp']) ?></td>
                                        <td style="text-align:center;">
                                            <?php if ($p['bukti_dp']): ?>
                                                <a href="../../uploads/bukti_dp/<?= htmlspecialchars($p['bukti_dp']) ?>" target="_blank" class="btn btn-outline btn-sm" style="padding:3px 8px; font-size:11px;">📎 Lihat Bukti</a>
                                            <?php else: ?>
                                                <span style="color:var(--muted); font-size:12px;">Tidak ada</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= badge_kpr($p['status_pengajuan']) ?></td>
                                        <td><?= badge_pembayaran($p['status_verifikasi']) ?></td>
                                        <td style="text-align:center;">
                                            <?php if ($p['status_verifikasi'] === 'pending'): ?>
                                                <div class="aksi-table">
                                                    <a href="index.php?action=valid&id=<?= $p['id_dp'] ?>" 
                                                       class="btn-edit"
                                                       onclick="return confirm('Konfirmasi bahwa pembayaran DP ini VALID?\n\nIni akan memperbarui status pengajuan KPR ke tahap Akad Kredit.')">
                                                        ✅ Valid
                                                    </a>
                                                    <a href="index.php?action=tolak&id=<?= $p['id_dp'] ?>" 
                                                       class="btn-delete"
                                                       onclick="return confirm('TOLAK bukti pembayaran DP ini?\n\nCustomer akan diminta mengirim ulang bukti transfer DP.')">
                                                        ❌ Tolak
                                                    </a>
                                                </div>
                                            <?php elseif ($p['status_verifikasi'] === 'valid'): ?>
                                                <span style="color:var(--success); font-size:12px; font-weight:700;">✅ Terverifikasi</span>
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
                🔔 Ada pembayaran DP baru masuk! Klik untuk refresh.
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
                        document.title = '🔔 (' + data.count + ') Pembayaran DP Baru — RumahKPR Admin';
                    }
                    knownCount = data.count;
                })
                .catch(() => {});
        }, 8000);
    </script>
</body>
</html>
