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

$total_unit      = $db->query("SELECT COUNT(*) FROM rumah")->fetchColumn();
$unit_tersedia   = $db->query("SELECT COUNT(*) FROM rumah WHERE status='tersedia'")->fetchColumn();
$total_perumahan = $db->query("SELECT COUNT(*) FROM perumahan")->fetchColumn();
$total_customer  = $db->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
?>

<!-- HERO SECTION -->
<section class="hero">
    <!-- Floating shapes -->
    <div class="hero-shape hero-shape-1"></div>
    <div class="hero-shape hero-shape-2"></div>
    <div class="hero-shape hero-shape-3"></div>

    <div class="container hero-content">
        <div class="hero-badge">
            ✨ Platform KPR #1 Terpercaya di Indonesia
        </div>
        <h1>Temukan Rumah <span class="highlight">Impian Anda</span><br>Dengan KPR Mudah &amp; Cepat</h1>
        <p>Pilih dari ratusan unit rumah berkualitas, ajukan KPR melalui bank rekanan terpercaya kami. Proses transparan dan real-time tracking.</p>

        <!-- Search bar -->
        <form action="index.php" method="GET">
            <div class="hero-search">
                <span style="font-size:18px;opacity:.6;">🔍</span>
                <input type="text" name="s" placeholder="Cari nama komplek atau lokasi..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-white btn-sm">Cari Sekarang</button>
            </div>
        </form>

        <!-- CTA Buttons -->
        <div class="hero-cta">
            <a href="guest/katalog.php" class="btn btn-accent btn-lg">🏠 Lihat Katalog</a>
            <a href="guest/simulasi_kpr.php" class="btn btn-white btn-lg">🧮 Simulasi KPR</a>
        </div>

        <!-- Stats -->
        <div class="hero-stats">
            <div class="stat-item">
                <div class="angka" id="statPerumahan">0</div>
                <div class="keterangan">Komplek Tersedia</div>
            </div>
            <div class="stat-item">
                <div class="angka" id="statUnit">0</div>
                <div class="keterangan">Total Unit</div>
            </div>
            <div class="stat-item">
                <div class="angka" id="statTersedia">0</div>
                <div class="keterangan">Unit Tersedia</div>
            </div>
            <div class="stat-item">
                <div class="angka" id="statCustomer">0</div>
                <div class="keterangan">Customer Aktif</div>
            </div>
        </div>
    </div>
</section>

<!-- FITUR UNGGULAN -->
<section class="section" style="background:#fff;">
    <div class="container">
        <div style="text-align:center;margin-bottom:48px;">
            <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(37,99,235,.07);color:#2563eb;padding:6px 16px;border-radius:50px;font-size:13px;font-weight:700;margin-bottom:14px;border:1px solid rgba(37,99,235,.15);">
                ⭐ Keunggulan Kami
            </div>
            <h2 class="section-title">Mengapa Pilih RumahKPR?</h2>
            <p class="section-sub">Proses KPR yang transparan, cepat, dan terpercaya untuk keluarga Indonesia</p>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;">
            <?php
            $fitur = [
                ['🏠', 'Properti Terpilih', 'Ratusan unit rumah berkualitas dari komplek premium pilihan terbaik', 'rgba(37,99,235,.08)'],
                ['💳', 'KPR Mudah', 'Proses pengajuan KPR online, cepat, transparan dan bisa dipantau real-time', 'rgba(16,185,129,.08)'],
                ['🏦', 'Bank Terpercaya', 'Bermitra dengan 5+ bank-bank terkemuka di Indonesia dengan bunga kompetitif', 'rgba(245,158,11,.08)'],
                ['📊', 'Tracking Real-time', 'Pantau status KPR Anda dari pengajuan hingga akad kredit kapan saja', 'rgba(99,102,241,.08)'],
            ];
            foreach ($fitur as $f): ?>
            <div class="fitur-card">
                <div class="fitur-ico" style="background:<?= $f[3] ?>">
                    <span><?= $f[0] ?></span>
                </div>
                <h4><?= $f[1] ?></h4>
                <p><?= $f[2] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- PROSES MUDAH -->
<section class="section" style="background:linear-gradient(135deg,#f8fafc,#eff6ff);">
    <div class="container">
        <div style="text-align:center;margin-bottom:48px;">
            <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(37,99,235,.07);color:#2563eb;padding:6px 16px;border-radius:50px;font-size:13px;font-weight:700;margin-bottom:14px;border:1px solid rgba(37,99,235,.15);">
                🚀 Cara Kerja
            </div>
            <h2 class="section-title">Proses KPR Semudah 4 Langkah</h2>
            <p class="section-sub">Dari pilih rumah sampai kunci di tangan, semua bisa dilakukan online</p>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:24px;position:relative;">
            <?php
            $steps = [
                ['1', '🏠', 'Pilih Unit', 'Telusuri katalog dan temukan unit rumah yang sesuai budget dan keinginan'],
                ['2', '📋', 'Booking & DP', 'Lakukan booking dan bayar uang tanda jadi untuk mengamankan unit pilihan'],
                ['3', '📝', 'Ajukan KPR', 'Upload dokumen dan pilih bank rekanan untuk proses pengajuan kredit'],
                ['4', '🤝', 'Akad Kredit', 'Setelah disetujui, tanda tangan akad dan kunci rumah siap diserahkan'],
            ];
            foreach ($steps as $s): ?>
            <div style="text-align:center;padding:28px 20px;background:#fff;border-radius:16px;border:1px solid #e2e8f0;box-shadow:0 4px 16px rgba(0,0,0,.05);position:relative;">
                <div style="position:absolute;top:-14px;left:50%;transform:translateX(-50%);width:28px;height:28px;background:linear-gradient(135deg,#2563eb,#1d4ed8);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:900;box-shadow:0 4px 10px rgba(37,99,235,.4);"><?= $s[0] ?></div>
                <div style="font-size:36px;margin:12px 0 12px;"><?= $s[1] ?></div>
                <h4 style="font-weight:800;color:#0f172a;margin-bottom:8px;font-size:15px;"><?= $s[2] ?></h4>
                <p style="font-size:13.5px;color:#94a3b8;line-height:1.6;"><?= $s[3] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- DAFTAR PERUMAHAN -->
<section class="section">
    <div class="container">
        <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:36px;flex-wrap:wrap;gap:16px;">
            <div>
                <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(37,99,235,.07);color:#2563eb;padding:6px 16px;border-radius:50px;font-size:13px;font-weight:700;margin-bottom:12px;border:1px solid rgba(37,99,235,.15);">
                    🏙️ Properti Pilihan
                </div>
                <h2 class="section-title"><?= $search ? "Hasil: \"".htmlspecialchars($search)."\"" : 'Komplek Perumahan Pilihan' ?></h2>
                <p class="section-sub" style="margin-bottom:0;"><?= count($list_perumahan) ?> komplek ditemukan</p>
            </div>
            <a href="guest/katalog.php" class="btn btn-outline">Lihat Semua Unit →</a>
        </div>

        <?php if (empty($list_perumahan)): ?>
            <div style="text-align:center;padding:80px 20px;">
                <div style="font-size:72px;margin-bottom:16px;opacity:.5;">🏚️</div>
                <h3 style="color:#475569;margin-bottom:8px;">Komplek tidak ditemukan</h3>
                <p style="color:#94a3b8;">Coba kata kunci lain atau <a href="index.php">lihat semua komplek</a>.</p>
            </div>
        <?php else: ?>
        <div class="grid-3">
            <?php
            $grad = [
                'linear-gradient(135deg,#0f172a,#1e3a8a)',
                'linear-gradient(135deg,#1e293b,#334155)',
                'linear-gradient(135deg,#1e3a8a,#3b82f6)',
                'linear-gradient(135deg,#0f172a,#3b82f6)',
            ];
            foreach ($list_perumahan as $i => $p):
                $unit_p = $db->prepare("SELECT COUNT(*) FROM rumah WHERE id_perumahan=? AND status='tersedia'");
                $unit_p->execute([$p['id_perumahan']]);
                $uts = $unit_p->fetchColumn();
            ?>
            <div class="kartu" style="animation:slideUp .5s ease <?= $i*.1 ?>s both;">
                <div class="kartu-img" style="background:<?= $grad[$i % 4] ?>;">
                    <span style="font-size:60px;position:relative;z-index:1;">🏢</span>
                    <span class="kartu-badge">Premium</span>
                </div>
                <div class="kartu-body">
                    <h3 class="kartu-title"><?= htmlspecialchars($p['nama_perumahan']) ?></h3>
                    <p class="kartu-loc">📍 <?= htmlspecialchars($p['alamat']) ?></p>
                    <p class="kartu-desc"><?= htmlspecialchars(substr($p['deskripsi'] ?? '', 0, 110)) ?>...</p>
                    <div class="kartu-info">
                        <span><?= $uts > 0 ? "✅ $uts Unit Tersedia" : "🔒 Habis Terjual" ?></span>
                    </div>
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

<!-- BANK REKANAN -->
<section class="section" style="background:#fff;">
    <div class="container">
        <div style="text-align:center;margin-bottom:40px;">
            <div style="display:inline-flex;align-items:center;gap:8px;background:rgba(16,185,129,.07);color:#10b981;padding:6px 16px;border-radius:50px;font-size:13px;font-weight:700;margin-bottom:14px;border:1px solid rgba(16,185,129,.15);">
                🏦 Bank Rekanan
            </div>
            <h2 class="section-title">Bank Rekanan Terpercaya</h2>
            <p class="section-sub">Pilih dari 5 bank rekanan kami dengan bunga KPR paling kompetitif</p>
        </div>
        <?php $banks = $db->query("SELECT * FROM bank ORDER BY bunga_kpr ASC")->fetchAll(); ?>
        <div style="display:flex;gap:16px;flex-wrap:wrap;justify-content:center;">
            <?php foreach ($banks as $bk): ?>
            <div style="background:linear-gradient(135deg,#f8fafc,#fff);border:1px solid #e2e8f0;border-radius:14px;padding:20px 28px;text-align:center;min-width:160px;box-shadow:0 2px 8px rgba(0,0,0,.04);transition:.25s;" onmouseover="this.style.transform='translateY(-4px)';this.style.boxShadow='0 8px 24px rgba(37,99,235,.1)';this.style.borderColor='rgba(37,99,235,.2)'" onmouseout="this.style.transform='';this.style.boxShadow='0 2px 8px rgba(0,0,0,.04)';this.style.borderColor='#e2e8f0'">
                <div style="font-size:28px;margin-bottom:8px;">🏦</div>
                <div style="font-size:13px;font-weight:800;color:#0f172a;margin-bottom:4px;"><?= htmlspecialchars($bk['nama_bank']) ?></div>
                <div style="font-size:18px;font-weight:900;color:#2563eb;"><?= $bk['bunga_kpr'] ?>%</div>
                <div style="font-size:11px;color:#94a3b8;margin-top:2px;">per tahun · maks <?= $bk['tenor_maksimal'] ?> thn</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CTA SECTION -->
<section class="section-cta">
    <div class="section-cta-content">
        <h2 style="font-size:clamp(24px,4vw,40px);font-weight:900;margin-bottom:14px;letter-spacing:-.3px;">Siap Mewujudkan<br>Rumah Impian Anda?</h2>
        <p style="opacity:.75;font-size:17px;margin-bottom:32px;max-width:500px;margin-left:auto;margin-right:auto;">Daftar sekarang dan mulai perjalanan KPR Anda bersama kami. Proses cepat, transparan, terpercaya.</p>
        <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;">
            <a href="register.php" class="btn btn-accent btn-lg">🚀 Daftar Gratis Sekarang</a>
            <a href="guest/simulasi_kpr.php" class="btn btn-white btn-lg">🧮 Coba Simulasi KPR</a>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>

<script>
// Counter animation untuk stats
function animateCounter(el, target, suffix) {
    let current = 0;
    const step = Math.ceil(target / 50);
    const interval = setInterval(() => {
        current = Math.min(current + step, target);
        el.textContent = current + suffix;
        if (current >= target) clearInterval(interval);
    }, 30);
}

// Trigger counter saat section terlihat
const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            animateCounter(document.getElementById('statPerumahan'), <?= (int)$total_perumahan ?>, '+');
            animateCounter(document.getElementById('statUnit'), <?= (int)$total_unit ?>, '+');
            animateCounter(document.getElementById('statTersedia'), <?= (int)$unit_tersedia ?>, '+');
            animateCounter(document.getElementById('statCustomer'), <?= (int)$total_customer ?>, '+');
            observer.disconnect();
        }
    });
}, { threshold: 0.3 });

const statsEl = document.querySelector('.hero-stats');
if (statsEl) observer.observe(statsEl);

// Scroll reveal untuk kartu
const cards = document.querySelectorAll('.kartu,.fitur-card');
const cardObserver = new IntersectionObserver((entries) => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            e.target.style.opacity = '1';
            e.target.style.transform = 'translateY(0)';
        }
    });
}, { threshold: 0.1 });

cards.forEach(card => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(30px)';
    card.style.transition = 'opacity .5s ease, transform .5s ease';
    cardObserver.observe(card);
});
</script>
