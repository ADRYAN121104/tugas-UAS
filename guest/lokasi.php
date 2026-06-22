<?php $page_title='Lokasi - KPR Perumahan'; require_once '../config/koneksi.php'; require_once '../includes/header.php';
$list = $db->query("SELECT * FROM perumahan ORDER BY nama_perumahan")->fetchAll();
?>
<main class="container" style="padding:60px 24px;">
    <div style="text-align:center;margin-bottom:40px;">
        <h1 class="section-title">📍 Lokasi Perumahan</h1>
        <p class="section-sub">Temukan komplek perumahan kami di berbagai lokasi strategis</p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:24px;">
        <?php foreach($list as $p): ?>
        <div style="background:#fff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0;box-shadow:0 2px 8px rgba(0,0,0,.05);">
            <div style="height:160px;background:linear-gradient(135deg,#0f172a,#1e3a8a);display:flex;align-items:center;justify-content:center;font-size:50px;">🗺️</div>
            <div style="padding:20px;">
                <h3 style="font-size:16px;font-weight:800;margin-bottom:6px;"><?= htmlspecialchars($p['nama_perumahan']) ?></h3>
                <p style="font-size:13px;color:#2563eb;font-weight:600;margin-bottom:10px;">📍 <?= htmlspecialchars($p['alamat']) ?></p>
                <p style="font-size:13px;color:#64748b;line-height:1.6;margin-bottom:14px;"><?= htmlspecialchars(substr($p['deskripsi']??'',0,100)).'...' ?></p>
                <div style="display:flex;gap:8px;">
                    <a href="katalog.php?id=<?= $p['id_perumahan'] ?>" class="btn btn-outline btn-sm" style="flex:1;justify-content:center;">Lihat Unit</a>
                    <?php if($p['maps_link']): ?>
                    <a href="<?= htmlspecialchars($p['maps_link']) ?>" target="_blank" class="btn btn-gray btn-sm" style="flex:1;justify-content:center;">🗺️ Maps</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</main>
<?php require_once '../includes/footer.php'; ?>
