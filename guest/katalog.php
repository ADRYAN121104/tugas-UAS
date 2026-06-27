<?php
// guest/katalog.php — dengan Pagination
$page_title = 'Katalog Properti - KPR Perumahan';
require_once '../config/koneksi.php';
require_once '../config/functions.php';
require_once '../includes/header.php';

$id_perumahan = (int)($_GET['id'] ?? 0);
$search       = trim($_GET['s'] ?? '');
$tipe_filter  = (int)($_GET['tipe'] ?? 0);
$page         = max(1, (int)($_GET['page'] ?? 1));
$per_page     = 9;
$offset       = ($page - 1) * $per_page;

// Ambil semua tipe untuk filter
$list_tipe       = $db->query("SELECT * FROM tipe_rumah ORDER BY harga ASC")->fetchAll();
$list_perumahan  = $db->query("SELECT * FROM perumahan ORDER BY nama_perumahan")->fetchAll();

// Query total untuk pagination
$where = "WHERE r.status = 'tersedia'";
$params = [];
if ($id_perumahan) { $where .= " AND r.id_perumahan=?"; $params[] = $id_perumahan; }
if ($tipe_filter)  { $where .= " AND r.id_tipe=?"; $params[] = $tipe_filter; }
if ($search)       { $where .= " AND (p.nama_perumahan LIKE ? OR t.nama_tipe LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$count_stmt = $db->prepare("SELECT COUNT(*) FROM rumah r JOIN perumahan p ON r.id_perumahan=p.id_perumahan JOIN tipe_rumah t ON r.id_tipe=t.id_tipe $where");
$count_stmt->execute($params);
$total_units  = $count_stmt->fetchColumn();
$total_pages  = max(1, ceil($total_units / $per_page));
$page         = min($page, $total_pages);
$offset       = ($page - 1) * $per_page;

// Query unit dengan limit
$stmt = $db->prepare("SELECT r.*,p.nama_perumahan,p.alamat,t.nama_tipe,t.harga,t.luas_tanah,t.luas_bangunan,t.jumlah_kamar,t.jumlah_kamar_mandi,t.foto
    FROM rumah r JOIN perumahan p ON r.id_perumahan=p.id_perumahan JOIN tipe_rumah t ON r.id_tipe=t.id_tipe
    $where ORDER BY t.harga ASC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$units = $stmt->fetchAll();

// Build query string untuk pagination
$query_base = http_build_query(array_filter(['id'=>$id_perumahan,'tipe'=>$tipe_filter,'s'=>$search]));
?>

<main class="container" style="padding:40px 24px 60px;">
    <?php tampil_flash(); ?>

    <!-- Header -->
    <div style="margin-bottom:28px;">
        <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(37,99,235,.07);color:#2563eb;padding:5px 14px;border-radius:50px;font-size:12.5px;font-weight:700;margin-bottom:12px;border:1px solid rgba(37,99,235,.15);">🏠 Katalog Properti</div>
        <h1 class="section-title">Temukan Unit Impian Anda</h1>
        <p class="section-sub">Tersedia <?= $total_units ?> unit siap dipesan dari berbagai komplek pilihan</p>
    </div>

    <!-- Filter -->
    <form method="GET" style="background:#fff;padding:20px;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:28px;border:1px solid #e2e8f0;">
        <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div style="flex:1;min-width:180px;">
                <label style="font-size:11.5px;font-weight:700;color:#64748b;display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px;">Komplek Perumahan</label>
                <select name="id" class="form-control">
                    <option value="">-- Semua Komplek --</option>
                    <?php foreach($list_perumahan as $p): ?>
                    <option value="<?= $p['id_perumahan'] ?>" <?= $id_perumahan==$p['id_perumahan']?'selected':'' ?>><?= htmlspecialchars($p['nama_perumahan']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:1;min-width:160px;">
                <label style="font-size:11.5px;font-weight:700;color:#64748b;display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px;">Tipe Rumah</label>
                <select name="tipe" class="form-control">
                    <option value="">-- Semua Tipe --</option>
                    <?php foreach($list_tipe as $t): ?>
                    <option value="<?= $t['id_tipe'] ?>" <?= $tipe_filter==$t['id_tipe']?'selected':'' ?>><?= htmlspecialchars($t['nama_tipe']) ?> — <?= format_rupiah($t['harga']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:2;min-width:200px;">
                <label style="font-size:11.5px;font-weight:700;color:#64748b;display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px;">Kata Kunci</label>
                <input type="text" name="s" class="form-control" placeholder="Cari komplek atau tipe..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="btn btn-primary">🔍 Cari</button>
            <a href="katalog.php" class="btn btn-gray">Reset</a>
        </div>
    </form>

    <!-- Hasil info -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px;">
        <p style="color:#64748b;font-size:14px;"><b><?= $total_units ?></b> unit ditemukan <?= $search?"· pencarian \"".htmlspecialchars($search)."\"":'' ?></p>
        <?php if ($total_pages > 1): ?>
        <p style="color:#94a3b8;font-size:13px;">Halaman <?= $page ?> dari <?= $total_pages ?></p>
        <?php endif; ?>
    </div>

    <!-- Grid Unit -->
    <?php if (empty($units)): ?>
        <div style="text-align:center;padding:80px 20px;">
            <div style="font-size:72px;margin-bottom:16px;opacity:.5;">🔍</div>
            <h3 style="color:#475569;margin-bottom:8px;">Tidak ada unit yang cocok</h3>
            <p style="color:#94a3b8;">Coba ubah filter pencarian Anda.</p>
            <a href="katalog.php" class="btn btn-primary" style="margin-top:16px;">Lihat Semua Unit</a>
        </div>
    <?php else: ?>
    <div class="grid-3">
        <?php
        $grad = ['linear-gradient(135deg,#1e3a8a,#2563eb)','linear-gradient(135deg,#1e293b,#334155)','linear-gradient(135deg,#1e3a8a,#3b82f6)','linear-gradient(135deg,#0f172a,#3b82f6)'];
        foreach ($units as $i => $u): ?>
        <div class="kartu">
            <div class="kartu-img" style="background:#f1f5f9;overflow:hidden;">
                <?php if ($u['foto'] && file_exists('../uploads/tipe_rumah/' . $u['foto'])): ?>
                    <img src="../uploads/tipe_rumah/<?= htmlspecialchars($u['foto']) ?>" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                    <div style="background:<?= $grad[$i%4] ?>;width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:60px;">🏠</div>
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

    <!-- PAGINATION -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <!-- Prev -->
        <?php if ($page > 1): ?>
        <a href="katalog.php?<?= $query_base ?>&page=<?= $page-1 ?>" class="page-item">‹</a>
        <?php else: ?>
        <span class="page-item disabled">‹</span>
        <?php endif; ?>

        <!-- Pages -->
        <?php
        $start = max(1, $page - 2);
        $end   = min($total_pages, $page + 2);
        if ($start > 1): ?>
            <a href="katalog.php?<?= $query_base ?>&page=1" class="page-item">1</a>
            <?php if ($start > 2): ?><span class="page-item disabled">…</span><?php endif; ?>
        <?php endif;
        for ($p = $start; $p <= $end; $p++): ?>
            <a href="katalog.php?<?= $query_base ?>&page=<?= $p ?>" class="page-item <?= $p==$page?'active':'' ?>"><?= $p ?></a>
        <?php endfor;
        if ($end < $total_pages): ?>
            <?php if ($end < $total_pages - 1): ?><span class="page-item disabled">…</span><?php endif; ?>
            <a href="katalog.php?<?= $query_base ?>&page=<?= $total_pages ?>" class="page-item"><?= $total_pages ?></a>
        <?php endif; ?>

        <!-- Next -->
        <?php if ($page < $total_pages): ?>
        <a href="katalog.php?<?= $query_base ?>&page=<?= $page+1 ?>" class="page-item">›</a>
        <?php else: ?>
        <span class="page-item disabled">›</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</main>

<?php require_once '../includes/footer.php'; ?>
