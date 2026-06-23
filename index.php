<?php
// index.php - Halaman Beranda
$page_title = 'Beranda - Sistem KPR Perumahan';
$page_desc  = 'Portal KPR Perumahan terpercaya. Temukan rumah impian dan ajukan kredit dengan mudah.';
require_once 'config/koneksi.php';
require_once 'config/functions.php';
require_once 'includes/header.php';

$search = trim($_GET['s'] ?? '');
if ($search) {
    $stmt = $db->prepare("SELECT * FROM perumahan WHERE nama_perumahan LIKE ? OR alamat LIKE ? ORDER BY id_perumahan DESC");
    $stmt->execute(["%$search%", "%$search%"]);
} else {
    $stmt = $db->query("SELECT * FROM perumahan ORDER BY id_perumahan DESC");
}
$list_perumahan = $stmt->fetchAll();

$total_unit = $db->query("SELECT COUNT(*) FROM rumah")->fetchColumn();
$unit_tersedia = $db->query("SELECT COUNT(*) FROM rumah WHERE status='tersedia'")->fetchColumn();
$total_perumahan = $db->query("SELECT COUNT(*) FROM perumahan")->fetchColumn();
?>

<section class="hero">
    <div class="container">
        <h1>Temukan Rumah <span>Impian Anda</span><br>Dengan KPR Mudah & Cepat</h1>
        <p>Pilih dari ratusan unit rumah berkualitas, ajukan KPR melalui bank rekanan terpercaya kami.</p>
        <form action="index.php" method="GET">
            <div class="hero-search">
                <input type="text" name="s" placeholder="Cari nama komplek atau lokasi..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-white btn-sm">🔍 Cari</button>
            </div>
        </form>
        <div class="hero-stats">
            <div class="stat-item"><div class="angka"><?= $total_perumahan ?>+</div><div class="keterangan">Komplek Tersedia</div></div>
            <div class="stat-item"><div class="angka"><?= $total_unit ?>+</div><div class="keterangan">Total Unit</div></div>
            <div class="stat-item"><div class="angka"><?= $unit_tersedia ?>+</div><div class="keterangan">Unit Tersedia</div></div>
            <div class="stat-item"><div class="angka">5+</div><div class="keterangan">Bank Rekanan</div></div>
        </div>
    </div>
</section>

<!-- Fitur Unggulan -->
<section class="section" style="background:#fff;">
    <div class="container">
        <div style="text-align:center;margin-bottom:40px;">
            <h2 class="section-title">Mengapa Pilih Kami?</h2>
            <p class="section-sub">Proses KPR yang transparan, cepat, dan terpercaya</p>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:24px;">
            <?php $fitur=[['🏠','Properti Terpilih','Ratusan unit rumah berkualitas dari komplek premium'],['💳','KPR Mudah','Proses pengajuan KPR online, cepat, dan transparan'],['🏦','Bank Terpercaya','Bermitra dengan bank-bank terkemuka di Indonesia'],['📊','Tracking Real-time','Pantau status KPR Anda secara real-time kapan saja']];
            foreach($fitur as $f): ?>
            <div style="text-align:center;padding:28px 20px;border-radius:12px;border:1px solid #e2e8f0;transition:.2s;" onmouseover="this.style.boxShadow='0 8px 24px rgba(37,99,235,.1)';this.style.borderColor='#2563eb'" onmouseout="this.style.boxShadow='';this.style.borderColor='#e2e8f0'">
                <div style="font-size:40px;margin-bottom:14px;"><?= $f[0] ?></div>
                <h4 style="font-size:16px;font-weight:800;margin-bottom:8px;color:#0f172a;"><?= $f[1] ?></h4>
                <p style="font-size:14px;color:#94a3b8;line-height:1.6;"><?= $f[2] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Daftar Perumahan -->
<section class="section">
    <div class="container">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:32px;flex-wrap:wrap;gap:12px;">
            <div>
                <h2 class="section-title"><?= $search ? "Hasil Pencarian: \"$search\"" : 'Komplek Perumahan Pilihan' ?></h2>
                <p class="section-sub"><?= count($list_perumahan) ?> komplek ditemukan</p>
            </div>
            <a href="guest/katalog.php" class="btn btn-outline">Lihat Semua →</a>
        </div>
        <?php if (empty($list_perumahan)): ?>
            <div style="text-align:center;padding:60px 20px;color:#94a3b8;">
                <div style="font-size:60px;margin-bottom:16px;">🏚️</div>
                <h3 style="color:#475569;">Komplek tidak ditemukan</h3>
                <p>Coba kata kunci lain atau <a href="index.php">lihat semua komplek</a>.</p>
            </div>
        <?php else: ?>
        <div class="grid-3">
            <?php $grad=['linear-gradient(135deg,#0f172a,#1e3a8a)','linear-gradient(135deg,#1e293b,#334155)','linear-gradient(135deg,#1e3a8a,#3b82f6)','linear-gradient(135deg,#0f172a,#3b82f6)'];
            foreach ($list_perumahan as $i => $p): ?>
            <div class="kartu">
                <div class="kartu-img" style="background:<?= $grad[$i%4] ?>;">
                    <span style="font-size:50px;">🏢</span>
                    <span class="kartu-badge">Premium</span>
                </div>
                <div class="kartu-body">
                    <h3 class="kartu-title"><?= htmlspecialchars($p['nama_perumahan']) ?></h3>
                    <p class="kartu-loc">📍 <?= htmlspecialchars($p['alamat']) ?></p>
                    <p class="kartu-desc"><?= htmlspecialchars(substr($p['deskripsi']??'',0,110)).'...' ?></p>
                    <?php
                        $unit_p = $db->prepare("SELECT COUNT(*) FROM rumah WHERE id_perumahan=? AND status='tersedia'");
                        $unit_p->execute([$p['id_perumahan']]);
                        $uts = $unit_p->fetchColumn();
                    ?>
                    <div class="kartu-info"><span>✅ <?= $uts ?> Unit Tersedia</span></div>
                    <div class="kartu-footer">
                        <a href="guest/katalog.php?id=<?= $p['id_perumahan'] ?>" class="btn btn-primary btn-sm btn-block">Lihat Unit →</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- CTA Section -->
<section class="section" style="background:linear-gradient(135deg,#0f172a,#1e3a8a);color:#fff;text-align:center;">
    <div class="container">
        <h2 style="font-size:32px;font-weight:800;margin-bottom:14px;">Siap Mewujudkan Rumah Impian?</h2>
        <p style="opacity:.8;font-size:16px;margin-bottom:28px;">Daftar sekarang dan mulai proses KPR Anda hari ini.</p>
        <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;">
            <a href="register.php" class="btn btn-accent btn-lg">🚀 Daftar Sekarang</a>
            <a href="guest/simulasi_kpr.php" class="btn btn-white btn-lg">🧮 Simulasi KPR</a>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
