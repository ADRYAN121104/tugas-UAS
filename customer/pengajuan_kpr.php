<?php
// customer/pengajuan_kpr.php
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';
require_once '../includes/sidebar_customer.php';

$id_user  = id_user();
$id_rumah = (int)($_GET['id_rumah'] ?? 0);

// Validasi unit dan booking milik customer ini
$unit = null;
if ($id_rumah) {
    $stmt = $db->prepare("
        SELECT r.*, p.nama_perumahan, t.nama_tipe, t.harga, t.luas_bangunan, t.luas_tanah 
        FROM rumah r 
        JOIN perumahan p ON r.id_perumahan = p.id_perumahan 
        JOIN tipe_rumah t ON r.id_tipe = t.id_tipe 
        JOIN booking b ON r.id_rumah = b.id_rumah
        WHERE r.id_rumah = ? AND b.id_user = ? AND b.status_booking = 'dikonfirmasi'
    ");
    $stmt->execute([$id_rumah, $id_user]);
    $unit = $stmt->fetch();
    if (!$unit) {
        set_flash('gagal', 'Anda harus memiliki booking terkonfirmasi untuk unit ini sebelum mengajukan KPR.');
        header('Location: booking_saya.php');
        exit;
    }
}
$list_bank = $db->query("SELECT * FROM bank ORDER BY bunga_kpr ASC")->fetchAll();

$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $id_rumah_p = (int)$_POST['id_rumah'];
    $id_bank    = (int)$_POST['id_bank'];
    $penghasilan = (float)str_replace(['.','Rp',' '],'',$_POST['penghasilan']);
    $uang_muka   = (float)str_replace(['.','Rp',' '],'',$_POST['uang_muka']);
    $tenor       = (int)$_POST['tenor'];

    // Cek sudah ada pengajuan
    $cek=$db->prepare("SELECT id_pengajuan FROM pengajuan_kpr WHERE id_user=? AND id_rumah=?");
    $cek->execute([$id_user,$id_rumah_p]);
    if($cek->fetch()){$error='Anda sudah mengajukan KPR untuk unit ini.';}
    elseif($penghasilan<=0||$uang_muka<=0||!$id_bank||!$tenor){$error='Semua field wajib diisi.';}
    else{
        // Upload dokumen
        $docs=['ktp'=>'','kk'=>'','slip_gaji'=>'','npwp'=>''];
        $err_dok='';
        foreach(['ktp','kk','slip_gaji'] as $dok){
            if(!empty($_FILES[$dok]['name'])){
                $up=upload_file($_FILES[$dok],'../uploads/'.($dok==='slip_gaji'?'slip_gaji':$dok));
                if($up['ok'])$docs[$dok]=$up['nama'];else $err_dok.=$dok.' gagal. ';
            }
        }
        if(!empty($_FILES['npwp']['name'])){$up=upload_file($_FILES['npwp'],'../uploads/ktp');if($up['ok'])$docs['npwp']=$up['nama'];}
        if($err_dok){$error='Error upload: '.$err_dok;}
        else{
            $db->beginTransaction();
            $ins=$db->prepare("INSERT INTO pengajuan_kpr(id_user,id_rumah,id_bank,penghasilan,uang_muka,tenor,tanggal_pengajuan,status_pengajuan) VALUES(?,?,?,?,?,?,CURDATE(),'pengajuan_masuk')");
            $ins->execute([$id_user,$id_rumah_p,$id_bank,$penghasilan,$uang_muka,$tenor]);
            $id_peng=$db->lastInsertId();
            $ins_dok=$db->prepare("INSERT INTO dokumen_kpr(id_pengajuan,ktp,kk,slip_gaji,npwp) VALUES(?,?,?,?,?)");
            $ins_dok->execute([$id_peng,$docs['ktp'],$docs['kk'],$docs['slip_gaji'],$docs['npwp']]);
            $db->prepare("INSERT INTO tracking_pengajuan(id_pengajuan,status,keterangan,tanggal_update) VALUES(?,'pengajuan_masuk','Berkas KPR berhasil diajukan oleh customer.',NOW())")->execute([$id_peng]);
            $db->commit();
            set_flash('sukses','Pengajuan KPR berhasil dikirim! Tim kami akan memproses dalam 1-3 hari kerja.');
            header('Location: status_kpr.php?id='.$id_peng); exit;
        }
    }
}
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pengajuan KPR</title>
<link rel="stylesheet" href="../assets/css/customer.css"></head><body>
<?php sidebar_customer('pengajuan_kpr'); ?>
<div class="cmain"><div class="ccontent">
    <div style="margin-bottom:22px;"><h2 style="font-size:22px;font-weight:800;">🚀 Pengajuan KPR</h2><p style="color:#64748b;font-size:14px;">Isi form berikut untuk mengajukan Kredit Pemilikan Rumah</p></div>
    <?php if($error): ?><div class="calert calert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
        <div style="display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start;" id="kpr-grid">
            <div>
                <div class="cpanel">
                    <div class="cpanel-header"><h3>📋 Pilih Unit Rumah</h3></div>
                    <div class="cpanel-body">
                        <?php 
                        $stmt_avail = $db->prepare("
                            SELECT r.*, p.nama_perumahan, t.nama_tipe, t.harga 
                            FROM rumah r 
                            JOIN perumahan p ON r.id_perumahan = p.id_perumahan 
                            JOIN tipe_rumah t ON r.id_tipe = t.id_tipe 
                            JOIN booking b ON r.id_rumah = b.id_rumah
                            WHERE b.id_user = ? AND b.status_booking = 'dikonfirmasi'
                            ORDER BY p.nama_perumahan
                        ");
                        $stmt_avail->execute([$id_user]);
                        $units_tersedia = $stmt_avail->fetchAll();
                        ?>
                        <div class="cform-group">
                            <label>Unit Rumah</label>
                            <select name="id_rumah" class="cform-control" required onchange="if(this.value)window.location.href='pengajuan_kpr.php?id_rumah='+this.value">
                                <option value="">-- Pilih Unit --</option>
                                <?php foreach($units_tersedia as $u): ?>
                                <option value="<?= $u['id_rumah'] ?>" <?= $id_rumah==$u['id_rumah']?'selected':'' ?>><?= htmlspecialchars($u['nama_perumahan'].' - Blok '.$u['blok'].' ('.$u['nama_tipe'].') - '.number_format($u['harga'],0,',','.')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="cpanel">
                    <div class="cpanel-header"><h3>🏦 Data Kredit</h3></div>
                    <div class="cpanel-body">
                        <div class="cform-group"><label>Bank Pilihan</label>
                            <select name="id_bank" class="cform-control" required>
                                <option value="">-- Pilih Bank --</option>
                                <?php foreach($list_bank as $b): ?><option value="<?= $b['id_bank'] ?>"><?= htmlspecialchars($b['nama_bank']) ?> - <?= $b['bunga_kpr'] ?>%/thn (maks <?= $b['tenor_maksimal'] ?> thn)</option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="cform-row">
                            <div class="cform-group"><label>Penghasilan Bulanan (Rp)</label><input type="text" name="penghasilan" class="cform-control format-angka" placeholder="10.000.000" required></div>
                            <div class="cform-group"><label>Uang Muka / DP (Rp)</label><input type="text" name="uang_muka" class="cform-control format-angka" placeholder="<?= $unit?number_format($unit['harga']*0.2,0,',','.'):'' ?>" required></div>
                        </div>
                        <div class="cform-group"><label>Tenor (Tahun)</label>
                            <select name="tenor" class="cform-control" required>
                                <?php for($t=5;$t<=30;$t+=5): ?><option value="<?= $t ?>"><?= $t ?> Tahun</option><?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="cpanel">
                    <div class="cpanel-header"><h3>📂 Upload Dokumen</h3></div>
                    <div class="cpanel-body">
                        <div class="cform-row">
                            <div class="cform-group"><label>KTP (JPG/PNG/PDF)</label><input type="file" name="ktp" class="cform-control" accept=".jpg,.jpeg,.png,.pdf" required></div>
                            <div class="cform-group"><label>Kartu Keluarga (KK)</label><input type="file" name="kk" class="cform-control" accept=".jpg,.jpeg,.png,.pdf" required></div>
                        </div>
                        <div class="cform-row">
                            <div class="cform-group"><label>Slip Gaji 3 Bulan</label><input type="file" name="slip_gaji" class="cform-control" accept=".jpg,.jpeg,.png,.pdf" required></div>
                            <div class="cform-group"><label>NPWP (Opsional)</label><input type="file" name="npwp" class="cform-control" accept=".jpg,.jpeg,.png,.pdf"></div>
                        </div>
                    </div>
                </div>
                <div style="display:flex;gap:10px;">
                    <a href="dashboard.php" class="cbtn cbtn-gray">← Batal</a>
                    <button type="submit" class="cbtn cbtn-primary cbtn-lg">🚀 Kirim Pengajuan KPR</button>
                </div>
            </div>
            <?php if($unit): ?>
            <div class="cpanel" style="position:sticky;top:80px;">
                <div class="cpanel-header"><h3>Info Properti</h3></div>
                <div class="cpanel-body" style="font-size:13.5px;">
                    <div style="height:120px;background:linear-gradient(135deg,#0f172a,#1e3a8a);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:50px;margin-bottom:16px;">🏠</div>
                    <b style="font-size:15px;"><?= htmlspecialchars($unit['nama_perumahan']) ?></b><br>
                    <span style="color:#2563eb;font-weight:700;">Blok <?= htmlspecialchars($unit['blok'].'-'.$unit['kode_unit']) ?></span>
                    <table style="width:100%;margin-top:14px;">
                        <tr><td style="color:#64748b;padding:5px 0;">Tipe</td><td style="font-weight:700;"><?= htmlspecialchars($unit['nama_tipe']) ?></td></tr>
                        <tr><td style="color:#64748b;padding:5px 0;">Harga</td><td style="font-weight:800;color:#2563eb;"><?= format_rupiah($unit['harga']) ?></td></tr>
                        <tr><td style="color:#64748b;padding:5px 0;">LT/LB</td><td style="font-weight:700;"><?= $unit['luas_tanah'] ?>/<?= $unit['luas_bangunan'] ?> m²</td></tr>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </form>
</div></div>
<script src="../assets/js/script.js"></script>
<style>@media(max-width:768px){#kpr-grid{grid-template-columns:1fr!important;}}</style>
</body></html>
