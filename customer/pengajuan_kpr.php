<?php
// customer/pengajuan_kpr.php
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';

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
    
    if ($cek->fetch()) {
        $error = 'Anda sudah mengajukan KPR untuk unit ini.';
    }
    elseif ($penghasilan <= 0 || $uang_muka <= 0 || !$id_bank || !$tenor) {
        $error = 'Semua kolom data kredit wajib diisi.';
    }
    // VALIDASI: Minimal DP Rp 5.000.000
    elseif ($uang_muka < 5000000) {
        $error = 'Uang Muka (DP) minimal adalah ' . format_rupiah(5000000) . '.';
    }
    // VALIDASI: Penghasilan minimal Rp 5.000.000 per bulan (karena estimasi cicilan Rp 3.000.000)
    elseif ($penghasilan < 5000000) {
        $error = 'Penghasilan bulanan minimal adalah ' . format_rupiah(5000000) . ' per bulan (karena estimasi cicilan berkisar Rp 3.000.000/bulan).';
    }
    else {
        // Upload dokumen
        $docs=['ktp'=>'','kk'=>'','slip_gaji'=>'','npwp'=>''];
        $err_dok='';
        foreach(['ktp','kk','slip_gaji'] as $dok){
            if(!empty($_FILES[$dok]['name'])){
                $up=upload_file($_FILES[$dok],'../uploads/'.($dok==='slip_gaji'?'slip_gaji':$dok));
                if($up['ok']) $docs[$dok]=$up['nama']; else $err_dok.=$dok.' gagal. ';
            }
        }
        if(!empty($_FILES['npwp']['name'])){
            $up=upload_file($_FILES['npwp'],'../uploads/ktp');
            if($up['ok'])$docs['npwp']=$up['nama'];
        }
        if($err_dok){
            $error='Error upload dokumen: '.$err_dok;
        }
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
            header('Location: status_kpr.php?id='.$id_peng); 
            exit;
        }
    }
}

$page_title = 'Pengajuan KPR - KPR Perumahan';
require_once '../includes/header.php';
?>
<main class="container" style="padding:40px 24px 60px;">
    <?php tampil_flash(); ?>
    <div style="margin-bottom:22px;">
        <h1 class="section-title">🚀 Pengajuan KPR</h1>
        <p class="section-sub">Isi form berikut untuk mengajukan Kredit Pemilikan Rumah</p>
    </div>
    
    <?php if($error): ?><div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data">
        <div style="display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start;" id="kpr-grid">
            <div>
                <!-- Panel Pilih Unit -->
                <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:20px; box-shadow:0 4px 20px rgba(0,0,0,0.05); margin-bottom:24px;">
                    <h3 style="font-size:15px; font-weight:800; margin-bottom:14px; border-bottom:1px solid #f1f5f9; padding-bottom:8px;">📋 Pilih Unit Rumah</h3>
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
                    <div class="form-group">
                        <label style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:6px;">Unit Rumah</label>
                        <select name="id_rumah" class="form-control" required onchange="if(this.value)window.location.href='pengajuan_kpr.php?id_rumah='+this.value">
                            <option value="">-- Pilih Unit --</option>
                            <?php foreach($units_tersedia as $u): ?>
                            <option value="<?= $u['id_rumah'] ?>" <?= $id_rumah==$u['id_rumah']?'selected':'' ?>><?= htmlspecialchars($u['nama_perumahan'].' - Blok '.$u['blok'].' ('.$u['nama_tipe'].') - '.number_format($u['harga'],0,',','.')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Panel Data Kredit -->
                <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:20px; box-shadow:0 4px 20px rgba(0,0,0,0.05); margin-bottom:24px;">
                    <h3 style="font-size:15px; font-weight:800; margin-bottom:14px; border-bottom:1px solid #f1f5f9; padding-bottom:8px;">🏦 Data Pembiayaan</h3>
                    
                    <div class="form-group">
                        <label style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:6px;">Bank Pilihan</label>
                        <select name="id_bank" class="form-control" required>
                            <option value="">-- Pilih Bank --</option>
                            <?php foreach($list_bank as $b): ?>
                                <option value="<?= $b['id_bank'] ?>"><?= htmlspecialchars($b['nama_bank']) ?> - <?= $b['bunga_kpr'] ?>%/thn (maks <?= $b['tenor_maksimal'] ?> thn)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:6px;">Penghasilan Bulanan (Rp)</label>
                            <input type="text" name="penghasilan" class="form-control format-angka" placeholder="5.000.000" required>
                            <small style="color:#94a3b8; font-size:11px;">Minimal Rp 5.000.000/bulan</small>
                        </div>
                        <div class="form-group">
                            <label style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:6px;">Uang Muka / DP (Rp)</label>
                            <input type="text" name="uang_muka" class="form-control format-angka" placeholder="<?= $unit?number_format($unit['harga']*0.2,0,',','.'):'5.000.000' ?>" required>
                            <small style="color:#94a3b8; font-size:11px;">Minimal Rp 5.000.000</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:6px;">Tenor (Tahun)</label>
                        <select name="tenor" class="form-control" required>
                            <?php for($t=5;$t<=30;$t+=5): ?><option value="<?= $t ?>"><?= $t ?> Tahun</option><?php endfor; ?>
                        </select>
                    </div>
                </div>

                <!-- Panel Upload Berkas -->
                <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:20px; box-shadow:0 4px 20px rgba(0,0,0,0.05); margin-bottom:24px;">
                    <h3 style="font-size:15px; font-weight:800; margin-bottom:14px; border-bottom:1px solid #f1f5f9; padding-bottom:8px;">📂 Unggah Dokumen Pendukung</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:6px;">KTP (JPG/PNG/PDF)</label>
                            <input type="file" name="ktp" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                        </div>
                        <div class="form-group">
                            <label style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:6px;">Kartu Keluarga (KK)</label>
                            <input type="file" name="kk" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:6px;">Slip Gaji 3 Bulan Terakhir</label>
                            <input type="file" name="slip_gaji" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                        </div>
                        <div class="form-group">
                            <label style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:6px;">NPWP (Opsional)</label>
                            <input type="file" name="npwp" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                    </div>
                </div>

                <div style="display:flex;gap:10px;">
                    <a href="booking_saya.php" class="btn btn-gray">← Batal</a>
                    <button type="submit" class="btn btn-primary">🚀 Kirim Pengajuan KPR</button>
                </div>
            </div>
            
            <?php if($unit): ?>
            <!-- Sidebar Ringkasan Unit -->
            <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:20px; box-shadow:0 4px 20px rgba(0,0,0,0.05); position:sticky; top:80px;">
                <h3 style="font-size:15px; font-weight:800; margin-bottom:14px; border-bottom:1px solid #f1f5f9; padding-bottom:8px;">Detail Unit</h3>
                <div style="font-size:13.5px;">
                    <div style="height:120px;background:linear-gradient(135deg,#0f172a,#1e3a8a);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:50px;margin-bottom:16px;color:#fff;">🏠</div>
                    <b style="font-size:15px;"><?= htmlspecialchars($unit['nama_perumahan']) ?></b><br>
                    <span style="color:#2563eb;font-weight:700;">Blok <?= htmlspecialchars($unit['blok'].'-'.$unit['kode_unit']) ?></span>
                    <table style="width:100%;margin-top:14px;border-collapse:collapse;">
                        <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:8px 0;">Tipe</td><td style="font-weight:700; text-align:right;"><?= htmlspecialchars($unit['nama_tipe']) ?></td></tr>
                        <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:8px 0;">Harga</td><td style="font-weight:800;color:#2563eb; text-align:right;"><?= format_rupiah($unit['harga']) ?></td></tr>
                        <tr><td style="color:#64748b;padding:8px 0;">LT / LB</td><td style="font-weight:700; text-align:right;"><?= $unit['luas_tanah'] ?>/<?= $unit['luas_bangunan'] ?> m²</td></tr>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </form>
</main>
<script src="../assets/js/script.js"></script>
<style>@media(max-width:768px){#kpr-grid{grid-template-columns:1fr!important;}}</style>
<?php require_once '../includes/footer.php'; ?>
