<?php $page_title='Tentang Kami - KPR Perumahan'; require_once '../includes/header.php'; ?>
<main class="container" style="padding:60px 24px;">
    <div style="text-align:center;margin-bottom:48px;">
        <h1 class="section-title">Tentang RumahKPR</h1>
        <p class="section-sub">Kami hadir untuk mempermudah perjalanan Anda menuju rumah impian</p>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:48px;align-items:center;margin-bottom:64px;" id="about-grid">
        <div>
            <h2 style="font-size:28px;font-weight:800;color:#0f172a;margin-bottom:16px;">Siapa Kami?</h2>
            <p style="color:#475569;line-height:1.8;margin-bottom:16px;">RumahKPR adalah platform manajemen properti dan kredit pemilikan rumah (KPR) yang didirikan dengan misi membantu masyarakat Indonesia mewujudkan impian memiliki rumah sendiri.</p>
            <p style="color:#475569;line-height:1.8;margin-bottom:24px;">Dengan teknologi modern dan jaringan bank rekanan terpercaya, kami menyederhanakan proses pencarian properti, pemesanan, hingga pengajuan KPR dalam satu platform yang terintegrasi.</p>
            <div style="display:flex;gap:16px;flex-wrap:wrap;">
                <div style="text-align:center;"><div style="font-size:32px;font-weight:800;color:#2563eb;">500+</div><div style="font-size:13px;color:#94a3b8;">Unit Terjual</div></div>
                <div style="text-align:center;"><div style="font-size:32px;font-weight:800;color:#10b981;">5+</div><div style="font-size:13px;color:#94a3b8;">Bank Rekanan</div></div>
                <div style="text-align:center;"><div style="font-size:32px;font-weight:800;color:#f59e0b;">1000+</div><div style="font-size:13px;color:#94a3b8;">Customer Puas</div></div>
            </div>
        </div>
        <div style="background:linear-gradient(135deg,#0f172a,#1e3a8a);border-radius:20px;height:300px;display:flex;align-items:center;justify-content:center;font-size:80px;">🏙️</div>
    </div>
    <div style="background:#f8fafc;border-radius:16px;padding:40px;text-align:center;margin-bottom:48px;">
        <h2 style="font-size:24px;font-weight:800;margin-bottom:32px;">Nilai-Nilai Kami</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:24px;">
            <?php $nilai=[['🎯','Transparansi','Semua proses terbuka dan dapat dipantau real-time oleh customer'],['🤝','Kepercayaan','Bermitra hanya dengan bank dan developer properti terpercaya'],['⚡','Kecepatan','Proses pengajuan KPR yang cepat dan efisien'],['💚','Integritas','Mengedepankan kejujuran dalam setiap transaksi']];
            foreach($nilai as $n): ?>
            <div style="padding:24px;background:#fff;border-radius:12px;border:1px solid #e2e8f0;">
                <div style="font-size:36px;margin-bottom:12px;"><?= $n[0] ?></div>
                <h4 style="font-weight:800;margin-bottom:8px;"><?= $n[1] ?></h4>
                <p style="font-size:13px;color:#94a3b8;line-height:1.6;"><?= $n[2] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>
<style>@media(max-width:768px){#about-grid{grid-template-columns:1fr!important;}}</style>
<?php require_once '../includes/footer.php'; ?>
