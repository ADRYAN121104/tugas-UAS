<?php
// customer/status_kpr.php
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';

$id_user = id_user();
$id_pengajuan = (int)($_GET['id'] ?? 0);

// Ambil semua pengajuan KPR milik user (termasuk alamat & maps_link)
$all = $db->prepare("
    SELECT pk.*, p.nama_perumahan, p.alamat, p.maps_link,
           b.nama_bank, b.bunga_kpr,
           r.blok, r.kode_unit, r.nama_tipe, r.harga 
    FROM pengajuan_kpr pk 
    JOIN rumah r ON pk.id_rumah = r.id_rumah 
    JOIN perumahan p ON r.id_perumahan = p.id_perumahan 
    JOIN bank b ON pk.id_bank = b.id_bank 
    WHERE pk.id_user = ? 
    ORDER BY pk.id_pengajuan DESC
");
$all->execute([$id_user]); 
$list_kpr = $all->fetchAll();

$kpr_aktif = null; 
$tracking = [];
if ($id_pengajuan) {
    $stmt = $db->prepare("
        SELECT pk.*, p.nama_perumahan, p.alamat, p.maps_link, b.nama_bank, b.bunga_kpr, r.blok, r.kode_unit, r.nama_tipe, r.harga 
        FROM pengajuan_kpr pk 
        JOIN rumah r ON pk.id_rumah = r.id_rumah 
        JOIN perumahan p ON r.id_perumahan = p.id_perumahan 
        JOIN bank b ON pk.id_bank = b.id_bank 
        WHERE pk.id_pengajuan = ? AND pk.id_user = ?
    ");
    $stmt->execute([$id_pengajuan, $id_user]); 
    $kpr_aktif = $stmt->fetch();
    if ($kpr_aktif) {
        $tr = $db->prepare("SELECT * FROM tracking_pengajuan WHERE id_pengajuan = ? ORDER BY tanggal_update ASC");
        $tr->execute([$id_pengajuan]);
        $tracking = $tr->fetchAll();
    }
} elseif (!empty($list_kpr)) {
    $kpr_aktif = $list_kpr[0]; 
    $id_pengajuan = $kpr_aktif['id_pengajuan'];
    $tr = $db->prepare("SELECT * FROM tracking_pengajuan WHERE id_pengajuan = ? ORDER BY tanggal_update ASC");
    $tr->execute([$id_pengajuan]);
    $tracking = $tr->fetchAll();
}

// ── AJAX: Cek apakah ada perubahan status KPR (untuk real-time polling) ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'check_status' && $id_pengajuan > 0) {
    header('Content-Type: application/json');
    $stmt_check = $db->prepare("SELECT status_pengajuan FROM pengajuan_kpr WHERE id_pengajuan = ? AND id_user = ?");
    $stmt_check->execute([$id_pengajuan, $id_user]);
    $kpr_check = $stmt_check->fetch();
    echo json_encode([
        'status' => $kpr_check['status_pengajuan'] ?? null
    ]);
    exit;
}

$tahapan = [
    ['pengajuan_masuk', '📥', 'Pengajuan Masuk'],
    ['verifikasi_dokumen', '📋', 'Verifikasi Dokumen'],
    ['survey', '🔍', 'Survey'],
    ['disetujui', '✅', 'Disetujui Bank'],
    ['akad_kredit', '🤝', 'Akad Kredit']
];
$status_skrg = $kpr_aktif['status_pengajuan'] ?? '';
$urutan = array_column($tahapan, null, 0);
$idx_skrg = array_search($status_skrg, array_column($tahapan, 0));

$page_title = 'Status KPR Saya - KPR Perumahan';
require_once '../includes/header.php';
?>
<style>
/* PROGRESS TRACKER STYLING */
.kpr-tracker {
    display: flex;
    align-items: flex-start;
    margin: 20px 0;
    overflow-x: auto;
    padding-bottom: 8px;
}
.tk-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    min-width: 100px;
    position: relative;
}
.tk-step:not(:last-child)::after {
    content: '';
    position: absolute;
    top: 18px;
    left: calc(50% + 18px);
    right: calc(-50% + 18px);
    height: 2px;
    background: #e2e8f0;
}
.tk-step.done:not(:last-child)::after {
    background: #2563eb;
}
.tk-dot {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    z-index: 1;
    border: 3px solid #fff;
    box-shadow: 0 0 0 2px #e2e8f0;
    color: #475569;
}
.tk-step.done .tk-dot {
    background: #2563eb;
    box-shadow: 0 0 0 2px #2563eb;
    color: #fff;
}
.tk-step.active .tk-dot {
    background: #f59e0b;
    box-shadow: 0 0 0 2px #f59e0b;
    color: #fff;
    animation: pulse 1.5s infinite;
}
.tk-step.reject .tk-dot {
    background: #ef4444;
    box-shadow: 0 0 0 2px #ef4444;
    color: #fff;
}
@keyframes pulse {
    0%, 100% { box-shadow: 0 0 0 2px #f59e0b; }
    50% { box-shadow: 0 0 0 6px rgba(245,158,11, 0.2); }
}
.tk-label {
    font-size: 11px;
    font-weight: 700;
    color: #94a3b8;
    margin-top: 8px;
    text-align: center;
    max-width: 90px;
    line-height: 1.3;
}
.tk-step.done .tk-label {
    color: #2563eb;
}
.tk-step.active .tk-label {
    color: #f59e0b;
}
</style>

<main class="container" style="padding:40px 24px 60px;">
    <?php tampil_flash(); ?>
    <div style="margin-bottom:28px;">
        <h1 class="section-title">📈 Pelacakan Pengajuan KPR</h1>
        <p class="section-sub">Pantau seluruh alur persetujuan berkas kredit Anda secara real-time</p>
    </div>

    <?php if(count($list_kpr) > 1): ?>
        <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:14px 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); margin-bottom:24px;">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <span style="font-size:13px;font-weight:700;color:#64748b;">Pilih Pengajuan KPR:</span>
                <?php foreach($list_kpr as $k): ?>
                    <a href="status_kpr.php?id=<?= $k['id_pengajuan'] ?>" class="btn <?= $k['id_pengajuan']==$id_pengajuan?'btn-primary':'btn-gray' ?> btn-sm">
                        <?= htmlspecialchars($k['nama_perumahan']) ?> (Blok <?= htmlspecialchars($k['blok'].'-'.$k['kode_unit']) ?>)
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if(!$kpr_aktif): ?>
        <div style="text-align:center;padding:60px 20px;background:#fff;border-radius:12px;border:1px solid #e2e8f0;box-shadow: 0 4px 20px rgba(0,0,0,0.05);color:#94a3b8;">
            <div style="font-size:48px; margin-bottom:12px;">📝</div>
            <h4 style="font-size:16px; font-weight:700; color:#475569; margin-bottom:8px;">Belum ada pengajuan KPR aktif</h4>
            <p style="margin-bottom:20px;">Silakan selesaikan booking dan tunggu konfirmasi pembayaran dari admin untuk mengajukan KPR.</p>
            <a href="booking_saya.php" class="btn btn-primary">Lihat Booking Saya</a>
        </div>
    <?php else: ?>
        <!-- Progress Tracker Card -->
        <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:24px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); margin-bottom:24px;">
            <h3 style="font-size:16px; font-weight:800; margin-bottom:18px; border-bottom:1px solid #f1f5f9; padding-bottom:10px;">🔄 Alur Persetujuan</h3>
            
            <?php if($status_skrg==='ditolak'): ?>
                <div class="alert alert-danger" style="margin-bottom:0;">❌ <b>Pengajuan KPR Ditolak.</b> <?= htmlspecialchars($kpr_aktif['catatan_admin']??'Silakan hubungi tim kami untuk informasi lebih lanjut.') ?></div>
            <?php else: ?>
                <div class="kpr-tracker">
                    <?php foreach($tahapan as $i=>[$key,$ico,$label]): 
                        $cls=''; 
                        if($status_skrg===$key) $cls='active'; 
                        elseif($idx_skrg!==false && $i<$idx_skrg) $cls='done';
                    ?>
                    <div class="tk-step <?= $cls ?>">
                        <div class="tk-dot"><?= $cls==='done'?'✓':$ico ?></div>
                        <div class="tk-label"><?= $label ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="text-align:center;margin-top:20px;"><?= badge_kpr($status_skrg) ?></div>
            <?php endif; ?>
        </div>

        <!-- Info Details Grid -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;" id="kpr-info">
            <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
                <h3 style="font-size:15px; font-weight:800; margin-bottom:14px; border-bottom:1px solid #f1f5f9; padding-bottom:8px;">🏠 Spesifikasi Properti</h3>
                <table style="width:100%; border-collapse:collapse; font-size:13.5px;">
                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:10px 0;">Komplek Perumahan</td><td style="font-weight:700; text-align:right;"><?= htmlspecialchars($kpr_aktif['nama_perumahan']) ?></td></tr>
                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:10px 0;">Blok / Unit</td><td style="font-weight:700; text-align:right;">Blok <?= htmlspecialchars($kpr_aktif['blok'].'-'.$kpr_aktif['kode_unit']) ?></td></tr>
                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:10px 0;">Alamat Unit</td><td style="font-weight:700; text-align:right; font-size:12px; color:#475569; max-width:180px; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($kpr_aktif['alamat']) ?></td></tr>
                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:10px 0;">Peta Google Maps</td><td style="padding:10px 0; text-align:right;">
                        <?php if ($kpr_aktif['maps_link']): ?>
                            <a href="<?= htmlspecialchars($kpr_aktif['maps_link']) ?>" target="_blank" class="btn btn-white btn-sm" style="padding:2px 8px; font-size:11px; border:1px solid #cbd5e1; display:inline-flex; align-items:center; gap:4px;">🗺️ Buka Peta</a>
                        <?php else: ?>
                            <span style="color:#94a3b8; font-size:11px;">Tidak ada link</span>
                        <?php endif; ?>
                    </td></tr>
                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:10px 0;">Tipe Rumah</td><td style="font-weight:700; text-align:right;"><?= htmlspecialchars($kpr_aktif['nama_tipe']) ?></td></tr>
                    <tr><td style="color:#64748b;padding:10px 0;">Harga Rumah</td><td style="font-weight:800;color:#2563eb; text-align:right;"><?= format_rupiah($kpr_aktif['harga']) ?></td></tr>
                </table>
            </div>

            <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
                <h3 style="font-size:15px; font-weight:800; margin-bottom:14px; border-bottom:1px solid #f1f5f9; padding-bottom:8px;">💳 Data Keuangan & Cicilan</h3>
                <?php $cicilan = hitung_cicilan($kpr_aktif['harga'], $kpr_aktif['uang_muka'], $kpr_aktif['bunga_kpr'], $kpr_aktif['tenor']); ?>
                <table style="width:100%; border-collapse:collapse; font-size:13.5px;">
                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:10px 0;">Bank Rekanan</td><td style="font-weight:700; text-align:right;"><?= htmlspecialchars($kpr_aktif['nama_bank']) ?> (Bunga <?= $kpr_aktif['bunga_kpr'] ?>%)</td></tr>
                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:10px 0;">Uang Muka (DP)</td><td style="font-weight:700; text-align:right;"><?= format_rupiah($kpr_aktif['uang_muka']) ?></td></tr>
                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:10px 0;">Tenor Kredit</td><td style="font-weight:700; text-align:right;"><?= $kpr_aktif['tenor'] ?> Tahun</td></tr>
                    <tr><td style="color:#64748b;padding:10px 0;">Estimasi Cicilan</td><td style="font-weight:800;color:#10b981; text-align:right; font-size:15px;"><?= format_rupiah($cicilan) ?> / bulan</td></tr>
                </table>
            </div>
        </div>

        <!-- History Timeline -->
        <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:24px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
            <h3 style="font-size:16px; font-weight:800; margin-bottom:18px; border-bottom:1px solid #f1f5f9; padding-bottom:10px;">📜 Riwayat Pelacakan Proses</h3>
            
            <?php if(empty($tracking)): ?>
                <p style="color:#94a3b8;text-align:center;padding:20px;">Belum ada riwayat proses.</p>
            <?php else: ?>
                <div style="position:relative;padding-left:24px;border-left:2px solid #e2e8f0; margin-left:10px; margin-top:10px;">
                    <?php foreach(array_reverse($tracking) as $t): ?>
                        <div style="position:relative;margin-bottom:24px;padding-left:16px;">
                            <div style="position:absolute;left:-31px;top:2px;width:12px;height:12px;border-radius:50%;background:#2563eb;border:2px solid #fff;box-shadow:0 0 0 2px #2563eb;"></div>
                            <div style="font-size:11px;color:#94a3b8;margin-bottom:4px;"><?= format_datetime($t['tanggal_update']) ?></div>
                            <div style="font-weight:700;font-size:13.5px;"><?= badge_kpr($t['status']) ?></div>
                            <p style="font-size:13px;color:#475569;margin-top:6px; line-height:1.5;"><?= htmlspecialchars($t['keterangan']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>
<script src="../assets/js/script.js"></script>
<?php if ($kpr_aktif): ?>
<script>
// ── Real-time polling: cek perubahan status KPR setiap 5 detik ──────────
const currentStatus = <?= json_encode($status_skrg) ?>;
const idPengajuan = <?= json_encode($id_pengajuan) ?>;

setInterval(function() {
    fetch(`status_kpr.php?id=${idPengajuan}&ajax=check_status`)
        .then(response => response.json())
        .then(data => {
            if (data && data.status && data.status !== currentStatus) {
                // Status berubah, refresh halaman secara otomatis untuk menampilkan alur baru
                window.location.reload();
            }
        })
        .catch(err => console.error("Gagal memeriksa status secara real-time:", err));
}, 5000);
</script>
<?php endif; ?>
<style>@media(max-width:768px){#kpr-info{grid-template-columns:1fr!important;}}</style>
<?php require_once '../includes/footer.php'; ?>
