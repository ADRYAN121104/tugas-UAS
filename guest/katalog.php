<?php
// guest/katalog.php
$page_title = 'Katalog Properti - KPR Perumahan';
require_once '../config/koneksi.php';
require_once '../config/functions.php';
require_once '../includes/header.php';

$id_perumahan = (int)($_GET['id'] ?? 0);
$search = trim($_GET['s'] ?? '');
$tipe_filter = (int)($_GET['tipe'] ?? 0);

// Ambil semua tipe untuk filter
$list_tipe = $db->query("SELECT * FROM tipe_rumah ORDER BY harga ASC")->fetchAll();

// Query unit rumah
$where = "WHERE r.status = 'tersedia'";
$params = [];
if ($id_perumahan) { $where .= " AND r.id_perumahan=?"; $params[] = $id_perumahan; }
if ($tipe_filter)  { $where .= " AND r.id_tipe=?"; $params[] = $tipe_filter; }
if ($search) { $where .= " AND (p.nama_perumahan LIKE ? OR t.nama_tipe LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$stmt = $db->prepare("SELECT r.*,p.nama_perumahan,p.alamat,t.nama_tipe,t.harga,t.luas_tanah,t.luas_bangunan,t.jumlah_kamar,t.jumlah_kamar_mandi,t.foto
    FROM rumah r JOIN perumahan p ON r.id_perumahan=p.id_perumahan JOIN tipe_rumah t ON r.id_tipe=t.id_tipe $where ORDER BY t.harga ASC");
$stmt->execute($params);
$units = $stmt->fetchAll();

$list_perumahan = $db->query("SELECT * FROM perumahan ORDER BY nama_perumahan")->fetchAll();
?>
<main class="container" style="padding:40px 24px;">
    <div style="margin-bottom:28px;">
        <h1 class="section-title">🏠 Katalog Properti</h1>
        <p class="section-sub">Temukan unit rumah impian Anda dari berbagai pilihan komplek</p>
    </div>

    <!-- Filter -->
    <form method="GET" style="background:#fff;padding:20px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.06);margin-bottom:28px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div style="flex:1;min-width:180px;">
            <label style="font-size:12px;font-weight:700;color:#64748b;display:block;margin-bottom:6px;">Komplek Perumahan</label>
            <select name="id" class="form-control">
                <option value="">-- Semua Komplek --</option>
                <?php foreach($list_perumahan as $p): ?>
                <option value="<?= $p['id_perumahan'] ?>" <?= $id_perumahan==$p['id_perumahan']?'selected':'' ?>><?= htmlspecialchars($p['nama_perumahan']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:1;min-width:160px;">
            <label style="font-size:12px;font-weight:700;color:#64748b;display:block;margin-bottom:6px;">Tipe Rumah</label>
            <select name="tipe" class="form-control">
                <option value="">-- Semua Tipe --</option>
                <?php foreach($list_tipe as $t): ?>
                <option value="<?= $t['id_tipe'] ?>" <?= $tipe_filter==$t['id_tipe']?'selected':'' ?>><?= htmlspecialchars($t['nama_tipe']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:2;min-width:200px;">
            <label style="font-size:12px;font-weight:700;color:#64748b;display:block;margin-bottom:6px;">Kata Kunci</label>
            <input type="text" name="s" class="form-control" placeholder="Cari komplek atau tipe..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <button type="submit" class="btn btn-primary">🔍 Cari</button>
        <a href="katalog.php" class="btn btn-gray">Reset</a>
    </form>

    <!-- Hasil -->
    <p style="color:#64748b;font-size:14px;margin-bottom:20px;"><b><?= count($units) ?></b> unit ditemukan</p>

    <?php if (empty($units)): ?>
        <div style="text-align:center;padding:60px;color:#94a3b8;">
            <div style="font-size:60px;">🔍</div>
            <h3 style="margin:16px 0 8px;color:#475569;">Tidak ada unit yang cocok</h3>
            <p>Coba ubah filter pencarian Anda.</p>
        </div>
    <?php else: ?>
    <div class="grid-3">
        <?php $grad=['linear-gradient(135deg,#1e3a8a,#2563eb)','linear-gradient(135deg,#1e293b,#334155)','linear-gradient(135deg,#1e3a8a,#3b82f6)','linear-gradient(135deg,#0f172a,#3b82f6)'];
        foreach ($units as $i => $u): ?>
        <div class="kartu">
            <div class="kartu-img" style="background:#f1f5f9; display:flex; align-items:center; justify-content:center; overflow:hidden; position:relative;">
                <?php if ($u['foto'] && file_exists('../uploads/tipe_rumah/' . $u['foto'])): ?>
                    <img src="../uploads/tipe_rumah/<?= htmlspecialchars($u['foto']) ?>" style="width:100%; height:100%; object-fit:cover;">
                <?php else: ?>
                    <div style="background:<?= $grad[$i%4] ?>; width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-size:40px; color:#fff;">🏠</div>
                <?php endif; ?>
                <span class="kartu-badge"><?= htmlspecialchars($u['nama_tipe']) ?></span>
            </div>
            <div class="kartu-body">
                <h3 class="kartu-title"><?= htmlspecialchars($u['nama_perumahan']) ?></h3>
                <p class="kartu-loc">📍 <?= htmlspecialchars($u['alamat']) ?> &nbsp;|&nbsp; Blok <?= htmlspecialchars($u['blok'].' - '.$u['kode_unit']) ?></p>
                <div class="kartu-info">
                    <span>📐 LT <?= $u['luas_tanah'] ?>m²</span>
                    <span>🏗️ LB <?= $u['luas_bangunan'] ?>m²</span>
                    <span>🛏️ <?= $u['jumlah_kamar'] ?> KT</span>
                    <span>🚿 <?= $u['jumlah_kamar_mandi'] ?> KM</span>
                </div>
                <div class="kartu-price"><?= format_rupiah($u['harga']) ?></div>
                <div class="kartu-footer" style="display:flex;gap:8px;">
                    <a href="detail_rumah.php?id=<?= $u['id_rumah'] ?>" class="btn btn-outline btn-sm" style="flex:1;justify-content:center;">Detail</a>
                    <?php if (sudah_login() && role_user()==='customer'): ?>
                        <a href="../customer/pengajuan_kpr.php?id_rumah=<?= $u['id_rumah'] ?>" class="btn btn-primary btn-sm" style="flex:1;justify-content:center;">Ajukan KPR</a>
                    <?php else: ?>
                        <a href="../login.php" class="btn btn-primary btn-sm" style="flex:1;justify-content:center;">Booking</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>
<?php require_once '../includes/footer.php'; ?>
