<?php
// index.php - Halaman Beranda
$page_title = 'RumahKPR - Temukan Rumah Impian Anda';
$page_desc  = 'Platform KPR Perumahan terpercaya. Temukan rumah impian dan ajukan kredit dengan mudah, cepat, dan transparan.';
require_once 'config/koneksi.php';
require_once 'config/functions.php';
require_once 'includes/header.php';

$stmt = $db->query("SELECT r.*, p.nama_perumahan, p.alamat, t.foto as tipe_foto
    FROM rumah r
    JOIN perumahan p ON r.id_perumahan = p.id_perumahan
    LEFT JOIN tipe_rumah t ON r.id_tipe = t.id_tipe
    WHERE r.status = 'tersedia'
    ORDER BY r.id_rumah DESC
    LIMIT 3");
$home_units = $stmt->fetchAll();

$total_unit      = $db->query("SELECT COUNT(*) FROM rumah")->fetchColumn();
$unit_tersedia   = $db->query("SELECT COUNT(*) FROM rumah WHERE status='tersedia'")->fetchColumn();
$total_perumahan = $db->query("SELECT COUNT(*) FROM perumahan")->fetchColumn();
$total_customer  = $db->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
?>

<!-- ================================================================ -->
<!-- HERO SECTION                                                       -->
<!-- ================================================================ -->
<section class="hero">
    <div class="hero-inner">

        <!-- LEFT -->
        <div class="hero-left">
            <div class="hero-badge-new">✨ Platform KPR #1 Terpercaya</div>
            <h1>Temukan Rumah<br><span>Impian Anda</span></h1>
            <p class="hero-sub">Ajukan KPR dengan mudah, cepat,<br>dan terpercaya.</p>

            <!-- Search Bar — desain kedua -->
            <form action="guest/katalog.php" method="GET">
                <div class="hero-search-new">
                    <span class="search-ico">📍</span>
                    <input type="text" name="s" placeholder="Cari lokasi, perumahan, atau nama properti...">
                    <button type="submit" class="btn btn-primary btn-sm" style="flex-shrink:0;">Cari</button>
                </div>
            </form>

            <!-- CTA -->
            <div class="hero-cta-new">
                <a href="guest/katalog.php" class="btn btn-primary btn-lg" style="color:#fff;">🏠 Cari Properti</a>
                <a href="guest/simulasi_kpr.php" class="btn btn-outline-white btn-sm" style="border-color:rgba(79,70,229,.35);color:#4f46e5;background:rgba(79,70,229,.07);">📊 Simulasi KPR</a>
            </div>
        </div>

        <!-- RIGHT: Gambar Rumah -->
        <div class="hero-right">
            <div class="hero-img-container">
                <img src="assets/images/rumah/hero_rumah.png" alt="Rumah Impian KPR" class="hero-img-main">
                <div class="hero-float-badge hero-float-1">
                    <div class="hero-float-ico" style="background:linear-gradient(135deg,#ede9fe,#ddd6fe);">🏡</div>
                    <div class="hero-float-text">
                        <strong><?= (int)$unit_tersedia ?>+ Unit</strong>
                        <span>Siap Huni</span>
                    </div>
                </div>
                <div class="hero-float-badge hero-float-2">
                    <div class="hero-float-ico" style="background:linear-gradient(135deg,#d1fae5,#a7f3d0);">✅</div>
                    <div class="hero-float-text">
                        <strong>KPR Disetujui</strong>
                        <span>Proses Cepat</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Stats Strip -->
    <div class="hero-stats-strip">
        <div class="hero-stats-inner">
            <div class="hero-stat-item">
                <div class="num" id="statPerumahan">0</div>
                <div class="lbl">Komplek Tersedia</div>
            </div>
            <div class="hero-stat-item">
                <div class="num" id="statUnit">0</div>
                <div class="lbl">Total Unit</div>
            </div>
            <div class="hero-stat-item">
                <div class="num" id="statTersedia">0</div>
                <div class="lbl">Unit Tersedia</div>
            </div>
            <div class="hero-stat-item">
                <div class="num" id="statCustomer">0</div>
                <div class="lbl">Customer Aktif</div>
            </div>
        </div>
    </div>
</section>

<!-- ================================================================ -->
<!-- REKOMENDASI UNIT                                                   -->
<!-- ================================================================ -->
<section class="section" style="background:#ffffff;">
    <div class="container">
        <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:32px;flex-wrap:wrap;gap:12px;">
            <div>
                <h2 style="font-size:clamp(20px,2.8vw,30px);font-weight:900;color:#0f172a;letter-spacing:-.5px;margin-bottom:4px;">Rekomendasi Unit Terbaik</h2>
                <p style="color:#64748b;font-size:13.5px;">Unit rumah pilihan berkualitas untuk keluarga Anda</p>
            </div>
            <a href="guest/katalog.php" style="font-size:13.5px;font-weight:700;color:#4f46e5;display:flex;align-items:center;gap:4px;">Lihat Semua &rarr;</a>
        </div>

        <?php if (empty($home_units)): ?>
            <div style="text-align:center;padding:80px 20px;">
                <div style="font-size:56px;opacity:.35;margin-bottom:16px;">🏚️</div>
                <h3 style="color:#475569;margin-bottom:8px;">Belum ada unit tersedia</h3>
                <p style="color:#94a3b8;">Silakan hubungi admin atau cek kembali nanti.</p>
            </div>
        <?php else: ?>
        <div class="grid-3">
            <?php foreach ($home_units as $i => $u):
                $foto = '';
                if (!empty($u['foto']) && file_exists('uploads/tipe_rumah/'.$u['foto'])) $foto='uploads/tipe_rumah/'.$u['foto'];
                elseif (!empty($u['tipe_foto']) && file_exists('uploads/tipe_rumah/'.$u['tipe_foto'])) $foto='uploads/tipe_rumah/'.$u['tipe_foto'];
                else {
                    $gs = $db->prepare("SELECT foto FROM galeri_rumah WHERE id_rumah=? LIMIT 1");
                    $gs->execute([$u['id_rumah']]);
                    $gf = $gs->fetchColumn();
                    if ($gf && file_exists('uploads/galeri_rumah/'.$gf)) $foto='uploads/galeri_rumah/'.$gf;
                }
            ?>
            <div class="kartu">
                <div class="kartu-img">
                    <?php if($foto): ?>
                        <img src="<?= $foto ?>" alt="<?= htmlspecialchars($u['nama_perumahan']) ?>">
                    <?php else: ?>
                        <div class="kartu-img-placeholder">🏠</div>
                    <?php endif; ?>
                    <span class="kartu-badge"><?= htmlspecialchars($u['nama_tipe'] ?? 'Tipe') ?></span>
                </div>
                <div class="kartu-body">
                    <h3 class="kartu-title"><?= htmlspecialchars($u['nama_perumahan']) ?></h3>
                    <p class="kartu-loc">📍 <?= htmlspecialchars($u['alamat']) ?></p>
                    <div class="kartu-info">
                        <span>LT <?= $u['luas_tanah'] ?>m²</span>
                        <span>LB <?= $u['luas_bangunan'] ?>m²</span>
                        <span><?= $u['jumlah_kamar'] ?> KT</span>
                        <span><?= $u['jumlah_kamar_mandi'] ?> KM</span>
                    </div>
                    <div class="kartu-price"><?= format_rupiah($u['harga']) ?></div>
                    <div class="kartu-footer">
                        <a href="guest/detail_rumah.php?id=<?= $u['id_rumah'] ?>" class="btn btn-outline-gray btn-sm" style="flex:1;justify-content:center;">Detail</a>
                        <?php if(sudah_login() && role_user()==='customer'): ?>
                            <a href="customer/pengajuan_kpr.php?id_rumah=<?= $u['id_rumah'] ?>" class="btn btn-primary btn-sm" style="flex:1;justify-content:center;color:#fff;">Booking</a>
                        <?php else: ?>
                            <a href="login.php" class="btn btn-primary btn-sm" style="flex:1;justify-content:center;color:#fff;">Booking</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ================================================================ -->
<!-- MENGAPA MEMILIH RUMAHKPR                                          -->
<!-- ================================================================ -->
<section class="section" style="background:#f8faff;">
    <div class="container">
        <div class="section-header">
            <div class="section-tag">Keunggulan Kami</div>
            <h2 class="section-title">Mengapa Memilih RumahKPR?</h2>
            <p class="section-sub" style="max-width:520px;margin:0 auto;">Proses KPR yang transparan, cepat, dan terpercaya untuk keluarga Indonesia</p>
        </div>
        <div class="fitur-grid">
            <?php
            $fiturs = [
                ['🏠','Properti Berkualitas','Ratusan unit rumah pilihan dengan lokasi strategis'],
                ['🏦','Bank Terpercaya','Bermitra dengan 10+ bank terkemuka di Indonesia'],
                ['⚡','Proses Cepat','Pengajuan mudah dengan persetujuan lebih cepat'],
                ['📱','Tracking Online','Pantau status KPR Anda secara real-time'],
            ];
            foreach($fiturs as $f): ?>
            <div class="fitur-card-new">
                <div class="fitur-ico-circle"><span><?= $f[0] ?></span></div>
                <h4><?= $f[1] ?></h4>
                <p><?= $f[2] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ================================================================ -->
<!-- PROSES KPR HANYA 4 LANGKAH                                        -->
<!-- ================================================================ -->
<section class="section" style="background:#ffffff;">
    <div class="container">
        <div class="section-header">
            <div class="section-tag">Cara Kerja</div>
            <h2 class="section-title">Proses KPR Hanya 4 Langkah</h2>
            <p class="section-sub" style="max-width:520px;margin:0 auto;">Dari pilih rumah sampai kunci di tangan, semua bisa dilakukan secara online</p>
        </div>
        <div class="proses-grid">
            <?php
            $steps = [
                ['1','🏠','Pilih Rumah','Temukan properti impian dari katalog kami','linear-gradient(135deg,#6d28d9,#7c3aed)'],
                ['2','📋','Ajukan KPR','Lengkapi data dan ajukan secara online','linear-gradient(135deg,#4f46e5,#6366f1)'],
                ['3','✅','Verifikasi','Tim kami akan memproses dan verifikasi data Anda','linear-gradient(135deg,#7c3aed,#a78bfa)'],
                ['4','🤝','Akad & Serah Terima','Setelah disetujui, akad dan terima kunci rumah','linear-gradient(135deg,#4338ca,#4f46e5)'],
            ];
            foreach($steps as $s): ?>
            <div class="proses-card">
                <div class="proses-num" style="background:<?= $s[4] ?>;"><?= $s[0] ?></div>
                <div class="proses-ico"><?= $s[1] ?></div>
                <h4><?= $s[2] ?></h4>
                <p><?= $s[3] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ================================================================ -->
<!-- BANK PARTNER                                                       -->
<!-- ================================================================ -->
<div class="bank-strip">
    <div class="container">
        <div style="text-align:center;margin-bottom:28px;">
            <h3 style="font-size:18px;font-weight:900;color:#0f172a;">Bank Partner Kami</h3>
        </div>
        <div class="bank-logos">
            <?php
            $banks_db = $db->query("SELECT * FROM bank ORDER BY nama_bank ASC")->fetchAll();
            if(!empty($banks_db)):
                foreach($banks_db as $bk): ?>
                <div class="bank-logo-item">
                    <span class="bank-name"><?= htmlspecialchars($bk['nama_bank']) ?></span>
                </div>
            <?php endforeach;
            else:
                $statics = ['mandiri','BNI','BRI','BTN','BCA','CIMB NIAGA','BSI'];
                foreach($statics as $bk): ?>
                <div class="bank-logo-item">
                    <span class="bank-name"><?= $bk ?></span>
                </div>
            <?php endforeach;
            endif; ?>
        </div>
    </div>
</div>

<!-- ================================================================ -->
<!-- CTA SECTION                                                        -->
<!-- ================================================================ -->
<section class="section-cta">
    <div class="cta-inner">
        <div class="cta-left">
            <div class="cta-home-ico">🏠</div>
            <div class="cta-text">
                <h3>Siap memiliki rumah impian Anda?</h3>
                <p>Ajukan KPR sekarang dan wujudkan rumah idaman bersama kami.</p>
            </div>
        </div>
        <a href="register.php" class="btn btn-white btn-lg" style="flex-shrink:0;color:#4f46e5;">Mulai Sekarang &rarr;</a>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>

<script>
// Counter animation
function animateCounter(el, target, suffix) {
    if (!el) return;
    let current = 0;
    const step = Math.max(1, Math.ceil(target / 60));
    const interval = setInterval(() => {
        current = Math.min(current + step, target);
        el.textContent = current + suffix;
        if (current >= target) clearInterval(interval);
    }, 25);
}
const statsObs = new IntersectionObserver((entries) => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            animateCounter(document.getElementById('statPerumahan'), <?= (int)$total_perumahan ?>, '+');
            animateCounter(document.getElementById('statUnit'),      <?= (int)$total_unit ?>, '+');
            animateCounter(document.getElementById('statTersedia'),  <?= (int)$unit_tersedia ?>, '+');
            animateCounter(document.getElementById('statCustomer'),  <?= (int)$total_customer ?>, '+');
            statsObs.disconnect();
        }
    });
}, {threshold: 0.2});
const stripEl = document.querySelector('.hero-stats-strip');
if (stripEl) statsObs.observe(stripEl);

// Scroll reveal with fallback
const revealElements = document.querySelectorAll('.kartu, .fitur-card-new, .proses-card');
if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(e => {
            if (e.isIntersecting) {
                e.target.style.opacity = '1';
                e.target.style.transform = 'translateY(0)';
                observer.unobserve(e.target);
            }
        });
    }, { threshold: 0.05 });

    revealElements.forEach((el, i) => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = `opacity 0.5s ease ${i * 0.06}s, transform 0.5s ease ${i * 0.06}s`;
        observer.observe(el);
    });
}
</script>
