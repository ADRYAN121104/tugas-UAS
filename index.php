<?php
// index.php - Halaman Beranda
$page_title = 'Beranda - Sistem KPR Perumahan';
$page_desc  = 'Portal KPR Perumahan terpercaya. Temukan rumah impian dan ajukan kredit dengan mudah.';
require_once 'config/koneksi.php';
require_once 'config/functions.php';
require_once 'includes/header.php';

// Ambil 3 unit rumah tersedia terbaru untuk ditampilkan di beranda
$stmt_home_units = $db->query("SELECT r.*, p.nama_perumahan, p.alamat, t.foto as tipe_foto
    FROM rumah r
    JOIN perumahan p ON r.id_perumahan = p.id_perumahan
    LEFT JOIN tipe_rumah t ON r.id_tipe = t.id_tipe
    WHERE r.status = 'tersedia'
    ORDER BY r.id_rumah DESC
    LIMIT 3");
$home_units = $stmt_home_units->fetchAll();

$total_unit      = $db->query("SELECT COUNT(*) FROM rumah")->fetchColumn();
$unit_tersedia   = $db->query("SELECT COUNT(*) FROM rumah WHERE status='tersedia'")->fetchColumn();
$total_perumahan = $db->query("SELECT COUNT(*) FROM perumahan")->fetchColumn();
$total_customer  = $db->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
?>

<!-- HERO SECTION -->
<section class="hero">
    <!-- Gambar latar penuh -->
    <img src="assets/images/rumah/hero_rumah.png" alt="Rumah KPR" class="hero-bg-img">
    <!-- Overlay gelap biru -->
    <div class="hero-overlay"></div>

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

        <!-- Search bar - Mengarah langsung ke katalog -->
        <form action="guest/katalog.php" method="GET">
            <div class="hero-search">
                <span style="font-size:18px;opacity:.6;">🔍</span>
                <input type="text" name="s" placeholder="Cari nama komplek atau tipe...">
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
<section class="section" style="background: linear-gradient(180deg, #ffffff 0%, #f0f4ff 100%);"><style>
.fitur-card-v2 {
  background: #fff;
  border-radius: 20px;
  padding: 32px 24px;
  text-align: center;
  border: 1px solid #e2e8f0;
  box-shadow: 0 2px 12px rgba(0,0,0,.05);
  transition: all .3s ease;
  position: relative;
  overflow: hidden;
}
.fitur-card-v2::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
  background: var(--card-accent, linear-gradient(90deg,#2563eb,#7c3aed));
  border-radius: 20px 20px 0 0;
}
.fitur-card-v2:hover {
  transform: translateY(-8px);
  box-shadow: 0 20px 48px rgba(37,99,235,.13);
  border-color: rgba(99,102,241,.2);
}
.fitur-ico-v2 {
  width: 72px; height: 72px;
  border-radius: 20px;
  display: flex; align-items: center; justify-content: center;
  font-size: 32px;
  margin: 0 auto 18px;
  transition: .3s;
}
.fitur-card-v2:hover .fitur-ico-v2 { transform: scale(1.12) rotate(-3deg); }
.step-card-v2 {
  background: #fff;
  border-radius: 20px;
  padding: 32px 24px;
  text-align: center;
  border: 1px solid #e2e8f0;
  box-shadow: 0 2px 12px rgba(0,0,0,.05);
  transition: all .3s ease;
  position: relative;
}
.step-card-v2:hover {
  transform: translateY(-8px);
  box-shadow: 0 20px 48px rgba(99,102,241,.15);
  border-color: rgba(99,102,241,.25);
}
.step-num-v2 {
  width: 52px; height: 52px;
  border-radius: 16px;
  display: flex; align-items: center; justify-content: center;
  font-size: 22px; font-weight: 900; color: #fff;
  margin: 0 auto 14px;
  background: linear-gradient(135deg, #2563eb, #7c3aed);
  box-shadow: 0 6px 20px rgba(99,102,241,.35);
}
</style>
    <div class="container">
        <div style="text-align:center;margin-bottom:52px;">
            <div style="display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,#eff6ff,#eef2ff);color:#4f46e5;padding:7px 20px;border-radius:50px;font-size:12px;font-weight:800;margin-bottom:18px;border:1px solid #c7d2fe;letter-spacing:.5px;text-transform:uppercase;">
                ⭐ Keunggulan Kami
            </div>
            <h2 style="font-size:clamp(26px,4vw,42px);font-weight:900;letter-spacing:-.5px;margin-bottom:14px;background:linear-gradient(135deg,#1e3a8a 0%,#0077b6 50%,#00b4d8 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Mengapa Pilih RumahKPR?</h2>
            <p style="color:#64748b;font-size:16px;max-width:520px;margin:0 auto;">Proses KPR yang <strong style="color:#4f46e5;">transparan</strong>, cepat, dan terpercaya untuk keluarga Indonesia</p>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:24px;">
            <?php
            $fitur = [
                ['🏠', 'Properti Terpilih', 'Ratusan unit rumah berkualitas dari komplek premium pilihan terbaik', 'linear-gradient(135deg,rgba(37,99,235,.12),rgba(99,102,241,.08))', 'linear-gradient(90deg,#2563eb,#4f46e5)'],
                ['💳', 'KPR Mudah', 'Proses pengajuan KPR online, cepat, transparan dan bisa dipantau real-time', 'linear-gradient(135deg,rgba(16,185,129,.12),rgba(6,182,212,.08))', 'linear-gradient(90deg,#10b981,#06b6d4)'],
                ['🏦', 'Bank Terpercaya', 'Bermitra dengan 5+ bank-bank terkemuka di Indonesia dengan bunga kompetitif', 'linear-gradient(135deg,rgba(245,158,11,.12),rgba(249,115,22,.08))', 'linear-gradient(90deg,#f59e0b,#f97316)'],
                ['📊', 'Tracking Real-time', 'Pantau status KPR Anda dari pengajuan hingga akad kredit kapan saja', 'linear-gradient(135deg,rgba(139,92,246,.12),rgba(168,85,247,.08))', 'linear-gradient(90deg,#8b5cf6,#a855f7)'],
            ];
            foreach ($fitur as $f): ?>
            <div class="fitur-card-v2" style="--card-accent:<?= $f[4] ?>;">
                <div class="fitur-ico-v2" style="background:<?= $f[3] ?>">
                    <span><?= $f[0] ?></span>
                </div>
                <h4 style="font-size:16px;font-weight:800;margin-bottom:10px;background:linear-gradient(135deg,#1e3a8a 0%,#0077b6 50%,#00b4d8 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"><?= $f[1] ?></h4>
                <p style="font-size:13.5px;color:#64748b;line-height:1.65;"><?= $f[2] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- PROSES MUDAH -->
<section class="section" style="background: linear-gradient(160deg,#f8fafc 0%,#eef2ff 40%,#f5f3ff 70%,#faf5ff 100%);">
    <div class="container">
        <div style="text-align:center;margin-bottom:52px;">
            <div style="display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,#faf5ff,#f5f3ff);color:#7c3aed;padding:7px 20px;border-radius:50px;font-size:12px;font-weight:800;margin-bottom:18px;border:1px solid #e9d5ff;letter-spacing:.5px;text-transform:uppercase;">
                🚀 Cara Kerja
            </div>
            <h2 style="font-size:clamp(26px,4vw,42px);font-weight:900;letter-spacing:-.5px;margin-bottom:14px;background:linear-gradient(135deg,#1e3a8a 0%,#0077b6 50%,#00b4d8 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Proses KPR Semudah 4 Langkah</h2>
            <p style="color:#64748b;font-size:16px;max-width:500px;margin:0 auto;">Dari pilih rumah sampai <strong style="color:#7c3aed;">kunci di tangan</strong>, semua bisa dilakukan secara online</p>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:24px;">
            <?php
            $steps = [
                ['1', '🏠', 'Pilih Unit', 'Telusuri katalog dan temukan unit rumah yang sesuai budget dan keinginan', 'linear-gradient(135deg,#2563eb,#4f46e5)'],
                ['2', '📋', 'Booking & DP', 'Lakukan booking dan bayar uang tanda jadi untuk mengamankan unit pilihan', 'linear-gradient(135deg,#7c3aed,#a855f7)'],
                ['3', '📝', 'Ajukan KPR', 'Upload dokumen dan pilih bank rekanan untuk proses pengajuan kredit', 'linear-gradient(135deg,#0891b2,#06b6d4)'],
                ['4', '🤝', 'Akad Kredit', 'Setelah disetujui, tanda tangan akad dan kunci rumah siap diserahkan', 'linear-gradient(135deg,#059669,#10b981)'],
            ];
            foreach ($steps as $s): ?>
            <div class="step-card-v2">
                <div class="step-num-v2" style="background:<?= $s[4] ?>;"><?= $s[0] ?></div>
                <div style="font-size:38px;margin:10px 0 14px;"><?= $s[1] ?></div>
                <h4 style="font-weight:800;margin-bottom:10px;font-size:15.5px;background:linear-gradient(135deg,#1e3a8a 0%,#0077b6 50%,#00b4d8 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"><?= $s[2] ?></h4>
                <p style="font-size:13.5px;color:#64748b;line-height:1.65;"><?= $s[3] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- DAFTAR UNIT RUMAH -->
<section class="section" style="background: linear-gradient(180deg,#f0f9ff 0%,#ffffff 100%);"><style>
.beranda-fix-priority { /* Prioritas foto profil untuk unit di beranda */ }
</style>
    <div class="container">
        <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:40px;flex-wrap:wrap;gap:16px;">
            <div>
                <div style="display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,#eff6ff,#ecfdf5);color:#0891b2;padding:7px 18px;border-radius:50px;font-size:12px;font-weight:800;margin-bottom:14px;border:1px solid #bae6fd;letter-spacing:.5px;text-transform:uppercase;">
                    🏠 Properti Pilihan
                </div>
                <h2 style="font-size:clamp(24px,3.5vw,38px);font-weight:900;letter-spacing:-.5px;margin-bottom:10px;background:linear-gradient(135deg,#1e3a8a 0%,#0077b6 50%,#00b4d8 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Rekomendasi Unit Terkini</h2>
                <p style="color:#64748b;font-size:15px;margin:0;">Temukan unit rumah siap huni terbaik dari kami</p>
            </div>
            <a href="guest/katalog.php" class="btn btn-outline">Lihat Semua Unit →</a>
        </div>

        <?php if (empty($home_units)): ?>
            <div style="text-align:center;padding:80px 20px;">
                <div style="font-size:72px;margin-bottom:16px;opacity:.5;">🏚️</div>
                <h3 style="color:#475569;margin-bottom:8px;">Belum ada unit yang tersedia</h3>
                <p style="color:#94a3b8;">Silakan hubungi admin atau kembali beberapa saat lagi.</p>
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
            foreach ($home_units as $i => $u):
                // Prioritas: rumah.foto (profil/sampul) → tipe_foto → galeri (fallback)
                $card_foto = '';
                if ($u['foto'] && file_exists('uploads/tipe_rumah/' . $u['foto'])) {
                    $card_foto = 'uploads/tipe_rumah/' . $u['foto'];
                } elseif (!empty($u['tipe_foto']) && file_exists('uploads/tipe_rumah/' . $u['tipe_foto'])) {
                    $card_foto = 'uploads/tipe_rumah/' . $u['tipe_foto'];
                } else {
                    $galeri_stmt = $db->prepare("SELECT foto FROM galeri_rumah WHERE id_rumah = ? ORDER BY id_galeri ASC LIMIT 1");
                    $galeri_stmt->execute([$u['id_rumah']]);
                    $gf = $galeri_stmt->fetchColumn();
                    if ($gf && file_exists('uploads/galeri_rumah/' . $gf)) {
                        $card_foto = 'uploads/galeri_rumah/' . $gf;
                    }
                }
            ?>
            <div class="kartu" style="animation:slideUp .5s ease <?= $i*.1 ?>s both;">
                <div class="kartu-img" style="background:<?= $grad[$i % 4] ?>; display:flex; align-items:center; justify-content:center; overflow:hidden; position:relative;">
                    <?php if ($card_foto): ?>
                        <img src="<?= $card_foto ?>" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <span style="font-size:60px;position:relative;z-index:1;">🏠</span>
                    <?php endif; ?>
                    <span class="kartu-badge"><?= htmlspecialchars($u['nama_tipe']) ?></span>
                </div>
                <div class="kartu-body" style="padding:22px 24px;">
                    <h3 class="kartu-title"><?= htmlspecialchars($u['nama_perumahan']) ?></h3>
                    <p class="kartu-loc">📍 <?= htmlspecialchars($u['alamat']) ?> &nbsp;|&nbsp; Blok <?= htmlspecialchars($u['blok'].' - '.$u['kode_unit']) ?></p>
                    <div class="kartu-info" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px;">
                        <span style="font-size:12.5px; background:var(--bg); padding:5px 13px; border-radius:8px; color:var(--text); font-weight:700; border:1px solid var(--border);">📐 LT <?= $u['luas_tanah'] ?>m²</span>
                        <span style="font-size:12.5px; background:var(--bg); padding:5px 13px; border-radius:8px; color:var(--text); font-weight:700; border:1px solid var(--border);">🏗️ LB <?= $u['luas_bangunan'] ?>m²</span>
                        <span style="font-size:12.5px; background:var(--bg); padding:5px 13px; border-radius:8px; color:var(--text); font-weight:700; border:1px solid var(--border);">🛏️ <?= $u['jumlah_kamar'] ?> KT</span>
                        <span style="font-size:12.5px; background:var(--bg); padding:5px 13px; border-radius:8px; color:var(--text); font-weight:700; border:1px solid var(--border);">🚿 <?= $u['jumlah_kamar_mandi'] ?> KM</span>
                    </div>
                    <div class="kartu-price" style="font-size:22px; font-weight:900; color:var(--primary); margin-bottom:16px; letter-spacing:-.3px;"><?= format_rupiah($u['harga']) ?></div>
                    <div class="kartu-footer" style="display:flex; gap:8px; border-top:1px solid var(--border); padding-top:16px;">
                        <a href="guest/detail_rumah.php?id=<?= $u['id_rumah'] ?>" class="btn btn-outline btn-sm" style="flex:1; justify-content:center;">Detail</a>
                        <?php if (sudah_login() && role_user()==='customer'): ?>
                            <a href="customer/pengajuan_kpr.php?id_rumah=<?= $u['id_rumah'] ?>" class="btn btn-primary btn-sm" style="flex:1; justify-content:center;">Ajukan KPR</a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-primary btn-sm" style="flex:1; justify-content:center;">Booking</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- BANK REKANAN -->
<section class="section" style="background:linear-gradient(180deg,#ffffff 0%,#f0fdf4 100%);">
    <div class="container">
        <div style="text-align:center;margin-bottom:44px;">
            <div style="display:inline-flex;align-items:center;gap:7px;background:linear-gradient(135deg,#ecfdf5,#d1fae5);color:#059669;padding:7px 20px;border-radius:50px;font-size:12px;font-weight:800;margin-bottom:18px;border:1px solid #6ee7b7;letter-spacing:.5px;text-transform:uppercase;">
                🏦 Bank Rekanan
            </div>
            <h2 style="font-size:clamp(24px,3.5vw,38px);font-weight:900;letter-spacing:-.5px;margin-bottom:14px;background:linear-gradient(135deg,#1e3a8a 0%,#0077b6 50%,#00b4d8 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Bank Rekanan Terpercaya</h2>
            <p style="color:#64748b;font-size:16px;max-width:480px;margin:0 auto;">Pilih dari bank rekanan kami dengan bunga KPR paling kompetitif di Indonesia</p>
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
        <h2 style="font-size:clamp(26px,4vw,46px);font-weight:900;margin-bottom:16px;letter-spacing:-.5px;background:linear-gradient(135deg,#ffffff 0%,#90e0ef 50%,#00b4d8 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Siap Mewujudkan<br>Rumah Impian Anda?</h2>
        <p style="opacity:.8;font-size:17px;margin-bottom:36px;max-width:500px;margin-left:auto;margin-right:auto;color:#cbd5e1;">Daftar sekarang dan mulai perjalanan KPR Anda bersama kami. Proses cepat, transparan, terpercaya.</p>
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
