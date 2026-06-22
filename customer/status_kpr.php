<?php
// customer/status_kpr.php
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';
require_once '../includes/sidebar_customer.php';
$id_user = id_user();
$id_pengajuan = (int)($_GET['id'] ?? 0);

// Ambil semua pengajuan KPR milik user
$all = $db->prepare("SELECT pk.*,p.nama_perumahan,b.nama_bank,r.blok,r.kode_unit,t.nama_tipe,t.harga FROM pengajuan_kpr pk JOIN rumah r ON pk.id_rumah=r.id_rumah JOIN perumahan p ON r.id_perumahan=p.id_perumahan JOIN tipe_rumah t ON r.id_tipe=t.id_tipe JOIN bank b ON pk.id_bank=b.id_bank WHERE pk.id_user=? ORDER BY pk.id_pengajuan DESC");
$all->execute([$id_user]); $list_kpr=$all->fetchAll();

$kpr_aktif = null; $tracking = [];
if ($id_pengajuan) {
    $stmt=$db->prepare("SELECT pk.*,p.nama_perumahan,b.nama_bank,b.bunga_kpr,r.blok,r.kode_unit,t.nama_tipe,t.harga FROM pengajuan_kpr pk JOIN rumah r ON pk.id_rumah=r.id_rumah JOIN perumahan p ON r.id_perumahan=p.id_perumahan JOIN tipe_rumah t ON r.id_tipe=t.id_tipe JOIN bank b ON pk.id_bank=b.id_bank WHERE pk.id_pengajuan=? AND pk.id_user=?");
    $stmt->execute([$id_pengajuan,$id_user]); $kpr_aktif=$stmt->fetch();
    if($kpr_aktif){$tr=$db->prepare("SELECT * FROM tracking_pengajuan WHERE id_pengajuan=? ORDER BY tanggal_update ASC");$tr->execute([$id_pengajuan]);$tracking=$tr->fetchAll();}
} elseif (!empty($list_kpr)) { $kpr_aktif=$list_kpr[0]; $id_pengajuan=$kpr_aktif['id_pengajuan'];
    $tr=$db->prepare("SELECT * FROM tracking_pengajuan WHERE id_pengajuan=? ORDER BY tanggal_update ASC");$tr->execute([$id_pengajuan]);$tracking=$tr->fetchAll();}

$tahapan=[['pengajuan_masuk','📥','Pengajuan Masuk'],['verifikasi_dokumen','📋','Verifikasi Dokumen'],['survey','🔍','Survey'],['disetujui','✅','Disetujui Bank'],['akad_kredit','🤝','Akad Kredit']];
$status_skrg = $kpr_aktif['status_pengajuan'] ?? '';
$urutan = array_column($tahapan,null,0);
$idx_skrg = array_search($status_skrg, array_column($tahapan,0));
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Status KPR</title>
<link rel="stylesheet" href="../assets/css/customer.css"></head><body>
<?php sidebar_customer('status_kpr'); ?>
<div class="cmain"><div class="ccontent">
    <div style="margin-bottom:22px;"><h2 style="font-size:22px;font-weight:800;">📈 Status Pengajuan KPR</h2><p style="color:#64748b;font-size:14px;">Pantau perkembangan proses KPR Anda secara real-time</p></div>

    <?php if(count($list_kpr)>1): ?>
    <div class="cpanel" style="margin-bottom:20px;">
        <div class="cpanel-body" style="padding:14px 20px;">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <span style="font-size:13px;font-weight:700;color:#64748b;">Pilih Pengajuan:</span>
                <?php foreach($list_kpr as $k): ?>
                <a href="status_kpr.php?id=<?= $k['id_pengajuan'] ?>" class="cbtn <?= $k['id_pengajuan']==$id_pengajuan?'cbtn-primary':'cbtn-gray' ?> cbtn-sm"><?= htmlspecialchars($k['nama_perumahan']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!$kpr_aktif): ?>
        <div class="cempty"><div class="ico">📝</div><h4>Belum ada pengajuan KPR</h4><p>Ajukan KPR setelah booking Anda dikonfirmasi admin</p><br><a href="pengajuan_kpr.php" class="cbtn cbtn-primary">Ajukan KPR Sekarang</a></div>
    <?php else: ?>
    <!-- Progress Tracker -->
    <div class="cpanel">
        <div class="cpanel-header"><h3>🔄 Alur Proses KPR</h3></div>
        <div class="cpanel-body">
            <?php if($status_skrg==='ditolak'): ?>
                <div class="calert calert-danger">❌ <b>Pengajuan KPR Ditolak.</b> <?= htmlspecialchars($kpr_aktif['catatan_admin']??'Silakan hubungi tim kami untuk informasi lebih lanjut.') ?></div>
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
            <div style="text-align:center;margin-top:16px;"><?= badge_kpr($status_skrg) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Info KPR -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;" id="kpr-info">
        <div class="cpanel">
            <div class="cpanel-header"><h3>🏠 Info Properti</h3></div>
            <div class="cpanel-body" style="font-size:13.5px;">
                <table style="width:100%;">
                    <tr><td style="color:#64748b;padding:6px 0;">Komplek</td><td style="font-weight:700;"><?= htmlspecialchars($kpr_aktif['nama_perumahan']) ?></td></tr>
                    <tr><td style="color:#64748b;padding:6px 0;">Blok/Unit</td><td style="font-weight:700;"><?= htmlspecialchars($kpr_aktif['blok'].'-'.$kpr_aktif['kode_unit']) ?></td></tr>
                    <tr><td style="color:#64748b;padding:6px 0;">Tipe</td><td style="font-weight:700;"><?= htmlspecialchars($kpr_aktif['nama_tipe']) ?></td></tr>
                    <tr><td style="color:#64748b;padding:6px 0;">Harga</td><td style="font-weight:800;color:#2563eb;"><?= format_rupiah($kpr_aktif['harga']) ?></td></tr>
                </table>
            </div>
        </div>
        <div class="cpanel">
            <div class="cpanel-header"><h3>💳 Info KPR</h3></div>
            <div class="cpanel-body" style="font-size:13.5px;">
                <?php $cicilan=hitung_cicilan($kpr_aktif['harga'],$kpr_aktif['uang_muka'],$kpr_aktif['bunga_kpr'],$kpr_aktif['tenor']); ?>
                <table style="width:100%;">
                    <tr><td style="color:#64748b;padding:6px 0;">Bank</td><td style="font-weight:700;"><?= htmlspecialchars($kpr_aktif['nama_bank']) ?></td></tr>
                    <tr><td style="color:#64748b;padding:6px 0;">Uang Muka</td><td style="font-weight:700;"><?= format_rupiah($kpr_aktif['uang_muka']) ?></td></tr>
                    <tr><td style="color:#64748b;padding:6px 0;">Tenor</td><td style="font-weight:700;"><?= $kpr_aktif['tenor'] ?> Tahun</td></tr>
                    <tr><td style="color:#64748b;padding:6px 0;">Est. Cicilan</td><td style="font-weight:800;color:#10b981;"><?= format_rupiah($cicilan) ?>/bln</td></tr>
                </table>
            </div>
        </div>
    </div>

    <!-- Tracking History -->
    <div class="cpanel">
        <div class="cpanel-header"><h3>📜 Riwayat Proses</h3></div>
        <div class="cpanel-body">
            <?php if(empty($tracking)): ?>
                <p style="color:#94a3b8;text-align:center;padding:20px;">Belum ada riwayat proses.</p>
            <?php else: ?>
            <div style="position:relative;padding-left:24px;border-left:2px solid #e2e8f0;">
                <?php foreach(array_reverse($tracking) as $t): ?>
                <div style="position:relative;margin-bottom:20px;padding-left:16px;">
                    <div style="position:absolute;left:-31px;top:2px;width:14px;height:14px;border-radius:50%;background:#2563eb;border:2px solid #fff;box-shadow:0 0 0 2px #2563eb;"></div>
                    <div style="font-size:12px;color:#94a3b8;margin-bottom:4px;"><?= format_datetime($t['tanggal_update']) ?></div>
                    <div style="font-weight:700;font-size:13.5px;"><?= badge_kpr($t['status']) ?></div>
                    <p style="font-size:13px;color:#475569;margin-top:6px;"><?= htmlspecialchars($t['keterangan']) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div></div>
<script src="../assets/js/script.js"></script>
<style>@media(max-width:768px){#kpr-info{grid-template-columns:1fr!important;}}</style>
</body></html>
