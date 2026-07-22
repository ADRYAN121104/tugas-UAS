<?php
// admin/pengajuan_kpr/index.php
require_once "../../config/koneksi.php";
require_once "../../config/cek_admin.php";
require_once "../../config/functions.php";
require_once "../../config/midtrans.php";
require_once "../../includes/sidebar_admin.php";

$action = $_GET["action"] ?? "";
$id     = (int)($_GET["id"] ?? 0);

// Quick ACC
if ($action === "quick_acc" && $id > 0) {
    $current = $_GET["current"] ?? "";
    $next = ""; $ket = "";
    switch ($current) {
        case "pengajuan_masuk":    $next="verifikasi_dokumen"; $ket="Pengajuan masuk ditinjau admin. Customer diminta upload dokumen persyaratan."; break;
        case "verifikasi_dokumen": $next="survey";             $ket="Semua dokumen valid. Tahap selanjutnya: Survey & BI Checking."; break;
        case "survey":             $next="disetujui";          $ket="Survey & BI Checking selesai. Pengajuan KPR disetujui bank."; break;
    }
    if ($next) {
        try {
            $db->beginTransaction();
            $db->prepare("UPDATE pengajuan_kpr SET status_pengajuan=? WHERE id_pengajuan=?")->execute([$next,$id]);
            $db->prepare("INSERT INTO tracking_pengajuan (id_pengajuan,status,keterangan,tanggal_update) VALUES (?,?,?,NOW())")->execute([$id,$next,$ket]);
            $db->commit();
            set_flash("sukses","Status berhasil di-ACC ke: ".strtoupper(str_replace("_"," ",$next)));
        } catch (PDOException $e) { $db->rollBack(); set_flash("gagal","Gagal: ".$e->getMessage()); }
    }
    header("Location: index.php?action=detail&id=$id"); exit;
}

// Upload Sertifikat
if ($action === "upload_sertifikat" && $id > 0 && $_SERVER["REQUEST_METHOD"] === "POST") {
    if (!empty($_FILES["sertifikat"]["name"])) {
        $dir = "../../uploads/sertifikat/";
        if (!is_dir($dir)) mkdir($dir,0755,true);
        $ext = strtolower(pathinfo($_FILES["sertifikat"]["name"],PATHINFO_EXTENSION));
        if (!in_array($ext,["jpg","jpeg","png","pdf"])) { set_flash("gagal","Format tidak valid."); }
        elseif ($_FILES["sertifikat"]["size"] > 10*1024*1024) { set_flash("gagal","File terlalu besar."); }
        else {
            $fname = "sertifikat_kpr_".$id."_".time().".".$ext;
            move_uploaded_file($_FILES["sertifikat"]["tmp_name"],$dir.$fname);
            $db->prepare("UPDATE pengajuan_kpr SET sertifikat=? WHERE id_pengajuan=?")->execute([$fname,$id]);
            $db->prepare("INSERT INTO tracking_pengajuan (id_pengajuan,status,keterangan,tanggal_update) VALUES (?,?,?,NOW())")->execute([$id,"disetujui","Sertifikat KPR diunggah admin. Customer diminta untuk melakukan pembayaran DP."]);
            set_flash("sukses","Sertifikat berhasil diupload!");
        }
    } else { set_flash("gagal","Pilih file sertifikat."); }
    header("Location: index.php?action=detail&id=$id"); exit;
}

// Verifikasi DP
if ($action === "verif_dp" && $id > 0) {
    $id_dp=(int)($_GET["id_dp"]??0); $aksi=$_GET["aksi"]??"";
    if ($id_dp>0 && in_array($aksi,["valid","tolak"])) {
        $dp2=$db->prepare("SELECT * FROM pembayaran_dp WHERE id_dp=? AND id_pengajuan=?"); $dp2->execute([$id_dp,$id]); $dp=$dp2->fetch();
        if ($dp) {
            if ($aksi==="valid") {
                $db->beginTransaction();
                $db->prepare("UPDATE pembayaran_dp SET status_verifikasi=? WHERE id_dp=?")->execute(["valid",$id_dp]);
                $kr=$db->prepare("SELECT id_rumah FROM pengajuan_kpr WHERE id_pengajuan=?"); $kr->execute([$id]); $id_rumah=$kr->fetchColumn();
                $db->prepare("UPDATE pengajuan_kpr SET status_pengajuan=? WHERE id_pengajuan=?")->execute(["akad_kredit",$id]);
                if ($id_rumah) $db->prepare("UPDATE rumah SET status=? WHERE id_rumah=?")->execute(["terjual",$id_rumah]);
                $db->prepare("INSERT INTO tracking_pengajuan (id_pengajuan,status,keterangan,tanggal_update) VALUES (?,?,?,NOW())")->execute([$id,"akad_kredit","DP diverifikasi valid. KPR masuk Akad Kredit. Jadwal cicilan dibuat otomatis."]);
                $cek=$db->prepare("SELECT COUNT(*) FROM cicilan_kpr WHERE id_pengajuan=?"); $cek->execute([$id]);
                if ($cek->fetchColumn()==0) {
                    $kg2=$db->prepare("SELECT pk.uang_muka,pk.tenor,b.bunga_kpr,r.harga FROM pengajuan_kpr pk JOIN bank b ON pk.id_bank=b.id_bank JOIN rumah r ON pk.id_rumah=r.id_rumah WHERE pk.id_pengajuan=?"); $kg2->execute([$id]); $kg=$kg2->fetch();
                    if ($kg) {
                        $pokok=max(0,(float)$kg["harga"]-(float)$dp["jumlah_dp"]);
                        $bpa=(float)$kg["bunga_kpr"]/100; $tnr=(int)$kg["tenor"]*12; $bpb=$bpa/12;
                        if ($pokok>0&&$tnr>0) {
                            $cic=$bpb>0?$pokok*($bpb*pow(1+$bpb,$tnr))/(pow(1+$bpb,$tnr)-1):$pokok/$tnr;
                            $saldo=$pokok; $tgl=new DateTime(); $tgl->modify("+1 month");
                            for ($b2=1;$b2<=$tnr;$b2++) {
                                $bung=$saldo*$bpb; $pok=$cic-$bung; $saldo-=$pok; if($saldo<0)$saldo=0;
                                $tgl2=clone $tgl; $tgl2->modify("+".($b2-1)." month");
                                $db->prepare("INSERT INTO cicilan_kpr (id_pengajuan,bulan_ke,tanggal_jatuh_tempo,jumlah_cicilan,pokok,bunga,status_bayar) VALUES (?,?,?,?,?,?,?)")->execute([$id,$b2,$tgl2->format("Y-m-d"),round($cic,2),round($pok,2),round($bung,2),"belum"]);
                            }
                        }
                    }
                }
                $db->commit(); set_flash("sukses","DP Valid! KPR masuk Akad Kredit & jadwal cicilan dibuat!");
            } else {
                $db->prepare("UPDATE pembayaran_dp SET status_verifikasi=? WHERE id_dp=?")->execute(["ditolak",$id_dp]);
                $db->prepare("INSERT INTO tracking_pengajuan (id_pengajuan,status,keterangan,tanggal_update) VALUES (?,?,?,NOW())")->execute([$id,"disetujui","Bukti DP ditolak. Customer diminta kirim ulang bukti yang valid."]);
                set_flash("gagal","DP ditolak.");
            }
        }
    }
    header("Location: index.php?action=detail&id=$id"); exit;
}

// Update Manual
if ($action==="update_status" && $id>0 && $_SERVER["REQUEST_METHOD"]==="POST") {
    $sb=trim($_POST["status_pengajuan"]??""); $kt=trim($_POST["keterangan"]??""); $ca=trim($_POST["catatan_admin"]??"");
    if (empty($sb)||empty($kt)) { set_flash("gagal","Status & keterangan wajib diisi."); header("Location: index.php?action=detail&id=$id"); exit; }
    try {
        $db->beginTransaction();
        $s=$db->prepare("SELECT id_rumah FROM pengajuan_kpr WHERE id_pengajuan=?"); $s->execute([$id]); $idr=$s->fetchColumn();
        $db->prepare("UPDATE pengajuan_kpr SET status_pengajuan=?,catatan_admin=? WHERE id_pengajuan=?")->execute([$sb,$ca,$id]);
        $db->prepare("INSERT INTO tracking_pengajuan (id_pengajuan,status,keterangan,tanggal_update) VALUES (?,?,?,NOW())")->execute([$id,$sb,$kt]);
        if ($sb==="ditolak"&&$idr) {
            $db->prepare("UPDATE rumah SET status=? WHERE id_rumah=?")->execute(["tersedia",$idr]);
            $db->prepare("UPDATE booking SET status_booking=? WHERE id_rumah=? AND status_booking=?")->execute(["dibatalkan",$idr,"dikonfirmasi"]);
        }
        $db->commit(); set_flash("sukses","Status diperbarui.");
    } catch (PDOException $e) { $db->rollBack(); set_flash("gagal","Error: ".$e->getMessage()); }
    header("Location: index.php?action=detail&id=$id"); exit;
}

$kpr=null; $dokumen=null; $tracking=[]; $dp_data=null;
if ($action==="detail" && $id>0) {
    $st=$db->prepare("SELECT pk.*,u.nama_lengkap,u.email,u.no_hp,p.nama_perumahan,r.blok,r.kode_unit,r.nama_tipe,r.harga,b.nama_bank,b.bunga_kpr FROM pengajuan_kpr pk JOIN users u ON pk.id_user=u.id_user JOIN rumah r ON pk.id_rumah=r.id_rumah JOIN perumahan p ON r.id_perumahan=p.id_perumahan JOIN bank b ON pk.id_bank=b.id_bank WHERE pk.id_pengajuan=?");
    $st->execute([$id]); $kpr=$st->fetch();
    if (!$kpr) { set_flash("gagal","Data tidak ditemukan."); header("Location: index.php"); exit; }
    $d=$db->prepare("SELECT * FROM dokumen_kpr WHERE id_pengajuan=?"); $d->execute([$id]); $dokumen=$d->fetch();
    $tr=$db->prepare("SELECT * FROM tracking_pengajuan WHERE id_pengajuan=? ORDER BY tanggal_update DESC"); $tr->execute([$id]); $tracking=$tr->fetchAll();
    $dp=$db->prepare("SELECT * FROM pembayaran_dp WHERE id_pengajuan=? AND status_verifikasi != ? ORDER BY created_at DESC LIMIT 1"); $dp->execute([$id,"ditolak"]); $dp_data=$dp->fetch();
}
$fs=trim($_GET["f_status"]??"");
$q="SELECT pk.*,u.nama_lengkap,p.nama_perumahan,r.blok,r.kode_unit,b.nama_bank FROM pengajuan_kpr pk JOIN users u ON pk.id_user=u.id_user JOIN rumah r ON pk.id_rumah=r.id_rumah JOIN perumahan p ON r.id_perumahan=p.id_perumahan JOIN bank b ON pk.id_bank=b.id_bank WHERE 1=1";
$pm=[];
if (!empty($fs)) { $q.=" AND pk.status_pengajuan=?"; $pm[]=$fs; }
$q.=" ORDER BY pk.id_pengajuan DESC";
$sl=$db->prepare($q); $sl->execute($pm); $list_kpr=$sl->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Kelola Pengajuan KPR - Admin</title>
<link rel="stylesheet" href="../../assets/css/admin.css?v=5">
<style>
.step-bar{display:flex;align-items:center;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:20px 24px;margin-bottom:24px;gap:0;overflow-x:auto;}
.step-item{display:flex;flex-direction:column;align-items:center;flex:1;min-width:80px;position:relative;}
.step-item:not(:last-child)::after{content:"";position:absolute;right:-50%;top:19px;width:100%;height:2px;background:#e2e8f0;z-index:0;}
.step-item.done:not(:last-child)::after{background:#22c55e;}
.step-item.active:not(:last-child)::after{background:linear-gradient(90deg,#3b82f6 30%,#e2e8f0);}
.step-icon{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:17px;position:relative;z-index:1;border:2px solid #e2e8f0;background:#f8fafc;color:#94a3b8;font-weight:700;transition:.3s;}
.step-item.done .step-icon{background:#22c55e;border-color:#22c55e;color:#fff;box-shadow:0 4px 12px #22c55e44;}
.step-item.active .step-icon{background:linear-gradient(135deg,#3b82f6,#6366f1);border-color:#3b82f6;color:#fff;box-shadow:0 4px 16px #3b82f640;animation:pulsr 2s infinite;}
.step-item.rejected .step-icon{background:#ef4444;border-color:#ef4444;color:#fff;}
@keyframes pulsr{0%{box-shadow:0 0 0 0 #3b82f680;}70%{box-shadow:0 0 0 10px transparent;}100%{box-shadow:0 0 0 0 transparent;}}
.step-lbl{font-size:10.5px;font-weight:700;text-align:center;margin-top:6px;line-height:1.3;color:#94a3b8;}
.step-item.done .step-lbl{color:#22c55e;}
.step-item.active .step-lbl{color:#3b82f6;}
.step-item.rejected .step-lbl{color:#ef4444;}
.proc-card{border-radius:20px;overflow:hidden;box-shadow:0 8px 32px rgba(0,0,0,0.09);margin-bottom:24px;animation:fadeUp .4s ease;}
@keyframes fadeUp{from{opacity:0;transform:translateY(16px);}to{opacity:1;transform:translateY(0);}}
.proc-head{padding:20px 26px;display:flex;align-items:center;justify-content:space-between;}
.proc-head h2{margin:0;font-size:17px;font-weight:800;color:#fff;display:flex;align-items:center;gap:10px;}
.proc-head .bak{background:rgba(255,255,255,0.25);color:#fff;padding:4px 14px;border-radius:20px;font-size:11px;font-weight:700;}
.proc-body{background:#fff;padding:28px;}
.doc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:14px;margin-top:14px;}
.doc-card{border:2px solid #e2e8f0;border-radius:14px;overflow:hidden;background:#f8fafc;text-align:center;transition:.25s;}
.doc-card:hover{border-color:#3b82f6;transform:translateY(-3px);box-shadow:0 8px 24px #3b82f620;}
.doc-card a{display:block;text-decoration:none;color:inherit;}
.doc-thumb{height:120px;display:flex;align-items:center;justify-content:center;background:#f1f5f9;overflow:hidden;}
.doc-thumb img{width:100%;height:100%;object-fit:cover;}
.doc-ico{font-size:44px;}
.doc-lbl{padding:10px 8px;font-size:12px;font-weight:700;color:#475569;}
.btn-acc{display:inline-flex;align-items:center;gap:10px;padding:13px 26px;border-radius:12px;font-size:14px;font-weight:800;color:#fff;text-decoration:none;border:none;cursor:pointer;transition:.2s;letter-spacing:.3px;}
.btn-acc:hover{transform:translateY(-2px);filter:brightness(1.08);}
.btn-acc-blue{background:linear-gradient(135deg,#2563eb,#1d4ed8);box-shadow:0 6px 20px #2563eb40;}
.btn-acc-green{background:linear-gradient(135deg,#059669,#10b981);box-shadow:0 6px 20px #05966940;}
.btn-acc-purple{background:linear-gradient(135deg,#7c3aed,#8b5cf6);box-shadow:0 6px 20px #7c3aed40;}
.btn-acc-red{background:linear-gradient(135deg,#dc2626,#ef4444);box-shadow:0 6px 20px #dc262640;}
.infobox{border-radius:12px;padding:14px 18px;margin-bottom:18px;font-size:13.5px;line-height:1.7;}
.infobox.blue{background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;}
.infobox.green{background:#f0fdf4;border:1px solid #86efac;color:#14532d;}
.infobox.yellow{background:#fffbeb;border:1px solid #fde68a;color:#78350f;}
.infobox.red{background:#fff5f5;border:1px solid #fca5a5;color:#991b1b;}
.track-wrap{position:relative;padding-left:24px;border-left:2px solid #e2e8f0;}
.track-item{position:relative;margin-bottom:22px;}
.track-dot{position:absolute;left:-31px;top:5px;width:14px;height:14px;border-radius:50%;background:var(--primary);border:2px solid #fff;box-shadow:0 0 0 2px var(--primary);}
.track-dot.latest{background:#22c55e;box-shadow:0 0 0 3px #22c55e44;width:16px;height:16px;left:-32px;}
.kpr-grid{display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;}
@media(max-width:1100px){.kpr-grid{grid-template-columns:1fr!important;}}
</style>
</head>
<body>
<?php sidebar_admin("pengajuan_kpr"); ?>
<div class="admin-main">
<header class="topbar">
  <div style="display:flex;align-items:center;gap:12px;">
    <button class="btn btn-gray btn-sm" id="sidebarToggle" style="padding:6px 10px;">&#9776;</button>
    <div class="topbar-title">Sistem KPR Perumahan</div>
  </div>
  <div class="topbar-right">
    <span class="topbar-name"><?= htmlspecialchars(nama_user()) ?> (<?= ucfirst(role_user()) ?>)</span>
    <div class="topbar-avatar"><?= strtoupper(substr(nama_user(),0,1)) ?></div>
  </div>
</header>
<main class="content">
<div class="breadcrumb">
  <a href="../dashboard.php">Dashboard</a> / <a href="index.php">Pengajuan KPR</a>
  <?php if ($action==="detail"): ?> / <span>Detail #<?= $id ?></span><?php endif; ?>
</div>
<?php tampil_flash(); ?>

<?php if ($action==="detail" && $kpr):
  $status=$kpr["status_pengajuan"];
  $cicilan=hitung_cicilan($kpr["harga"],$kpr["uang_muka"],$kpr["bunga_kpr"],$kpr["tenor"]);
  $steps=["pengajuan_masuk"=>["l"=>"Pengajuan Masuk","i"=>"&#x1F4E5;"],"verifikasi_dokumen"=>["l"=>"Verifikasi Dokumen","i"=>"&#x1F4CB;"],"survey"=>["l"=>"Survey & BI Cek","i"=>"&#x1F50D;"],"disetujui"=>["l"=>"Disetujui & DP","i"=>"&#x2705;"],"akad_kredit"=>["l"=>"Akad Kredit","i"=>"&#x1F91D;"]];
  $skeys=array_keys($steps);
  $cidx=array_search($status,$skeys);
  $rejected=($status==="ditolak");
?>
<div class="page-header">
  <div class="page-header-left">
    <h2>&#x1F4DD; Detail Pengajuan KPR #<?= $kpr["id_pengajuan"] ?></h2>
    <p><b><?= htmlspecialchars($kpr["nama_lengkap"]) ?></b> &mdash; <?= htmlspecialchars($kpr["nama_perumahan"]) ?> Blok <?= htmlspecialchars($kpr["blok"]."-".$kpr["kode_unit"]) ?></p>
  </div>
  <a href="index.php" class="btn btn-gray">&larr; Kembali</a>
</div>

<div class="step-bar">
<?php if ($rejected): ?>
  <div style="display:flex;align-items:center;gap:12px;color:#ef4444;font-weight:800;font-size:16px;">&#x274C; Pengajuan ini telah <b>DITOLAK</b></div>
<?php else: foreach ($steps as $sk=>$sv): $si=array_search($sk,$skeys); $sc=$si<$cidx?"done":($si==$cidx?"active":""); ?>
  <div class="step-item <?= $sc ?>">
    <div class="step-icon"><?= $si<$cidx?"&#x2713;":$sv["i"] ?></div>
    <div class="step-lbl"><?= $sv["l"] ?></div>
  </div>
<?php endforeach; endif; ?>
</div>

<div class="kpr-grid">
<div>

<div class="panel" style="margin-bottom:20px;">
  <div class="panel-header"><h3>&#x1F464; Profil Pemohon &amp; Unit</h3></div>
  <div class="panel-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;font-size:13.5px;">
      <div>
        <div style="font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:700;margin-bottom:6px;">Data Pemohon</div>
        <div style="font-weight:800;font-size:15px;margin-bottom:3px;"><?= htmlspecialchars($kpr["nama_lengkap"]) ?></div>
        <div style="color:var(--muted);"><?= htmlspecialchars($kpr["email"]) ?></div>
        <div style="color:var(--muted);">&#x1F4DE; <?= htmlspecialchars($kpr["no_hp"]) ?></div>
      </div>
      <div>
        <div style="font-size:11px;color:var(--muted);text-transform:uppercase;font-weight:700;margin-bottom:6px;">Data Properti</div>
        <div style="font-weight:800;font-size:15px;margin-bottom:3px;"><?= htmlspecialchars($kpr["nama_perumahan"]) ?></div>
        <div style="color:var(--primary);font-weight:700;">Blok <?= htmlspecialchars($kpr["blok"]."-".$kpr["kode_unit"]) ?> &bull; <?= htmlspecialchars($kpr["nama_tipe"]) ?></div>
        <div style="color:var(--success);font-weight:700;"><?= format_rupiah($kpr["harga"]) ?></div>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-top:16px;padding-top:16px;border-top:1px solid #f1f5f9;text-align:center;font-size:13px;">
      <div><div style="font-size:11px;color:var(--muted);margin-bottom:4px;">Bank</div><div style="font-weight:700;"><?= htmlspecialchars($kpr["nama_bank"]) ?></div><div style="font-size:11px;color:var(--muted);">Bunga <?= $kpr["bunga_kpr"] ?>%</div></div>
      <div><div style="font-size:11px;color:var(--muted);margin-bottom:4px;">Penghasilan/bln</div><div style="font-weight:700;"><?= format_rupiah($kpr["penghasilan"]) ?></div></div>
      <div><div style="font-size:11px;color:var(--muted);margin-bottom:4px;">Uang Muka (DP)</div><div style="font-weight:700;color:#d97706;"><?= format_rupiah($kpr["uang_muka"]) ?></div></div>
      <div><div style="font-size:11px;color:var(--muted);margin-bottom:4px;">Cicilan/bln Est.</div><div style="font-weight:700;color:var(--success);"><?= format_rupiah($cicilan) ?></div><div style="font-size:11px;color:var(--muted);"><?= $kpr["tenor"] ?> Tahun</div></div>
    </div>
  </div>
</div>

<?php if ($rejected): ?>
<div class="proc-card" style="border:2px solid #ef4444;">
  <div class="proc-head" style="background:linear-gradient(135deg,#dc2626,#ef4444);">
    <h2>&#x274C; Pengajuan Ditolak</h2>
  </div>
  <div class="proc-body">
    <div class="infobox red"><b>Alasan Penolakan:</b><br><?= htmlspecialchars($kpr["catatan_admin"]??"Pengajuan KPR dibatalkan/ditolak.") ?></div>
  </div>
</div>

<?php elseif ($status==="pengajuan_masuk"): ?>
<div class="proc-card" style="border:2px solid #3b82f6;">
  <div class="proc-head" style="background:linear-gradient(135deg,#1e3a8a,#3b82f6);">
    <h2>&#x1F4E5; Langkah 1 &mdash; Pengajuan Masuk</h2>
    <span class="bak">AKTIF</span>
  </div>
  <div class="proc-body">
    <div class="infobox blue"><b>&#x1F4CC; Tinjau Data Pengajuan Baru</b><br>Periksa data pemohon, properti, dan bank di atas. Jika sesuai, klik <b>ACC</b> untuk meminta customer upload berkas persyaratan.</div>
    <div style="text-align:center;padding:36px 20px;background:linear-gradient(135deg,#eff6ff,#dbeafe);border-radius:16px;margin-bottom:24px;">
      <div style="font-size:80px;margin-bottom:12px;">&#x1F4CB;</div>
      <div style="font-weight:800;font-size:18px;color:#1e40af;margin-bottom:8px;">Pengajuan KPR Baru Masuk</div>
      <div style="font-size:13px;color:#3b82f6;margin-bottom:16px;">Menunggu Tinjauan Admin</div>
      <div style="display:inline-flex;gap:20px;background:#fff;border-radius:12px;padding:12px 20px;font-size:13px;color:#1e40af;font-weight:700;box-shadow:0 2px 8px #3b82f620;flex-wrap:wrap;">
        <span>&#x1F464; <?= htmlspecialchars($kpr["nama_lengkap"]) ?></span>
        <span>&#x1F3E0; Blok <?= htmlspecialchars($kpr["blok"]."-".$kpr["kode_unit"]) ?></span>
        <span>&#x1F3E6; <?= htmlspecialchars($kpr["nama_bank"]) ?></span>
      </div>
    </div>
    <div style="display:flex;justify-content:flex-end;">
      <a href="index.php?action=quick_acc&id=<?= $id ?>&current=pengajuan_masuk" class="btn-acc btn-acc-blue"
         onclick="return confirm(&apos;ACC Pengajuan ini?\nCustomer akan diminta mengunggah berkas persyaratan.&apos;)">
        &#x2705; ACC &amp; Minta Customer Upload Dokumen
      </a>
    </div>
  </div>
</div>

<?php elseif ($status==="verifikasi_dokumen"): ?>
<div class="proc-card" style="border:2px solid #3b82f6;">
  <div class="proc-head" style="background:linear-gradient(135deg,#1d4ed8,#3b82f6);">
    <h2>&#x1F4CB; Langkah 2 &mdash; Verifikasi Dokumen</h2>
    <span class="bak">AKTIF</span>
  </div>
  <div class="proc-body">
    <?php if (!$dokumen): ?>
      <div class="infobox yellow"><b>&#x23F3; Menunggu Customer Upload Dokumen</b><br>Customer belum mengunggah berkas persyaratan. Refresh halaman untuk cek.</div>
      <div style="text-align:center;padding:48px 0;background:#f8fafc;border:2px dashed #e2e8f0;border-radius:16px;">
        <div style="font-size:64px;margin-bottom:12px;">&#x23F3;</div>
        <div style="font-weight:700;color:#64748b;font-size:15px;">Belum ada dokumen yang diunggah</div>
      </div>
    <?php else: ?>
      <div class="infobox blue"><b>&#x1F4CC; Verifikasi Berkas Persyaratan Customer</b><br>Klik gambar untuk melihat ukuran penuh. Pastikan semua dokumen jelas dan valid.</div>
      <div class="doc-grid">
      <?php
      $docs=[["label"=>"KTP Pemohon","ico"=>"&#x1FAA6;","file"=>$dokumen["ktp"]??"","dir"=>"ktp"],["label"=>"Kartu Keluarga","ico"=>"&#x1F46A;","file"=>$dokumen["kk"]??"","dir"=>"kk"],["label"=>"Slip Gaji","ico"=>"&#x1F4B5;","file"=>$dokumen["slip_gaji"]??"","dir"=>"slip_gaji"],["label"=>"NPWP","ico"=>"&#x1F4C4;","file"=>$dokumen["npwp"]??"","dir"=>"ktp"]];
      foreach ($docs as $d2):
        if (empty($d2["file"])) continue;
        $ex=strtolower(pathinfo($d2["file"],PATHINFO_EXTENSION));
        $isImg=in_array($ex,["jpg","jpeg","png","gif","webp"]);
        $href="../../uploads/".$d2["dir"]."/".htmlspecialchars($d2["file"]);
      ?>
      <div class="doc-card">
        <a href="<?= $href ?>" target="_blank">
          <div class="doc-thumb">
            <?php if ($isImg): ?><img src="<?= $href ?>" alt="<?= htmlspecialchars($d2["label"]) ?>" loading="lazy"><?php else: ?><span class="doc-ico"><?= $d2["ico"] ?></span><?php endif; ?>
          </div>
          <div class="doc-lbl"><?= htmlspecialchars($d2["label"]) ?></div>
        </a>
      </div>
      <?php endforeach; ?>
      </div>
      <div style="display:flex;justify-content:flex-end;margin-top:24px;">
        <a href="index.php?action=quick_acc&id=<?= $id ?>&current=verifikasi_dokumen" class="btn-acc btn-acc-green"
           onclick="return confirm(&apos;Semua dokumen valid?\nACC akan melanjutkan ke Survey & BI Checking.&apos;)">
          &#x2705; Dokumen Valid &mdash; Lanjut ke Survey &amp; BI Cek
        </a>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($status==="survey"): ?>
<div class="proc-card" style="border:2px solid #7c3aed;">
  <div class="proc-head" style="background:linear-gradient(135deg,#5b21b6,#7c3aed);">
    <h2>&#x1F50D; Langkah 3 &mdash; Survey &amp; BI Checking</h2>
    <span class="bak">AKTIF</span>
  </div>
  <div class="proc-body">
    <div class="infobox blue" style="background:#faf5ff;border-color:#c4b5fd;color:#5b21b6;"><b>&#x1F4CB; Proses Survey & BI Checking</b><br>Lakukan kunjungan survey ke unit properti dan cek BI Checking (SLIK OJK). Jika layak, klik ACC.</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">
      <div style="background:linear-gradient(135deg,#f5f3ff,#ede9fe);border:1px solid #c4b5fd;border-radius:16px;padding:30px;text-align:center;">
        <div style="font-size:56px;margin-bottom:12px;">&#x1F3E0;</div>
        <div style="font-weight:800;color:#5b21b6;font-size:14px;">Survey Unit Properti</div>
        <div style="font-size:12px;color:#7c3aed;margin-top:6px;">Blok <?= htmlspecialchars($kpr["blok"]."-".$kpr["kode_unit"]) ?></div>
        <div style="font-size:12px;color:#7c3aed;"><?= htmlspecialchars($kpr["nama_perumahan"]) ?></div>
      </div>
      <div style="background:linear-gradient(135deg,#f5f3ff,#ede9fe);border:1px solid #c4b5fd;border-radius:16px;padding:30px;text-align:center;">
        <div style="font-size:56px;margin-bottom:12px;">&#x1F4CA;</div>
        <div style="font-weight:800;color:#5b21b6;font-size:14px;">BI Checking / SLIK OJK</div>
        <div style="font-size:12px;color:#7c3aed;margin-top:6px;"><?= htmlspecialchars($kpr["nama_lengkap"]) ?></div>
        <div style="font-size:12px;color:#7c3aed;">Penghasilan: <?= format_rupiah($kpr["penghasilan"]) ?>/bln</div>
      </div>
    </div>
    <div style="display:flex;justify-content:flex-end;">
      <a href="index.php?action=quick_acc&id=<?= $id ?>&current=survey" class="btn-acc btn-acc-purple"
         onclick="return confirm(&apos;Survey & BI Checking LAYAK?\nACC akan mengubah status ke Disetujui Bank.&apos;)">
        &#x2705; ACC &mdash; Survey Lolos &amp; Disetujui Bank
      </a>
    </div>
  </div>
</div>

<?php elseif ($status==="disetujui"): ?>
<div class="proc-card" style="border:2px solid #10b981;">
  <div class="proc-head" style="background:linear-gradient(135deg,#047857,#10b981);">
    <h2>&#x1F3E6; Langkah 4 &mdash; Upload Sertifikat &amp; Verifikasi DP</h2>
    <span class="bak">AKTIF</span>
  </div>
  <div class="proc-body">
    <div style="padding-bottom:24px;margin-bottom:24px;border-bottom:2px dashed #e2e8f0;">
      <div style="font-weight:800;font-size:14px;color:#064e3b;margin-bottom:14px;">&#x1F4C4; A. Upload Sertifikat KPR</div>
      <?php if ($kpr["sertifikat"]): ?>
        <div class="infobox green">&#x2705; Sertifikat sudah diupload. Customer dapat melanjutkan ke pembayaran DP.</div>
        <div style="text-align:center;padding:20px;background:#f0fdf4;border:1px solid #86efac;border-radius:14px;margin-bottom:14px;">
          <?php $sxt=strtolower(pathinfo($kpr["sertifikat"],PATHINFO_EXTENSION)); if (in_array($sxt,["jpg","jpeg","png","webp"])): ?>
          <img src="../../uploads/sertifikat/<?= htmlspecialchars($kpr["sertifikat"]) ?>" style="max-height:200px;border-radius:10px;box-shadow:0 4px 16px #0000001a;" alt="Sertifikat">
          <?php else: ?><div style="font-size:60px;">&#x1F4C4;</div><?php endif; ?>
          <div style="margin-top:10px;"><a href="../../uploads/sertifikat/<?= htmlspecialchars($kpr["sertifikat"]) ?>" target="_blank" class="btn btn-outline btn-sm">&#x1F4CE; Lihat Sertifikat</a></div>
        </div>
      <?php else: ?>
        <div class="infobox yellow">&#x26A0;&#xFE0F; Sertifikat belum diupload. Customer tidak bisa bayar DP sebelum sertifikat tersedia.</div>
      <?php endif; ?>
      <form method="POST" action="index.php?action=upload_sertifikat&id=<?= $id ?>" enctype="multipart/form-data">
        <div style="display:flex;gap:10px;align-items:flex-end;">
          <div style="flex:1;">
            <label style="font-size:12px;font-weight:700;color:#475569;display:block;margin-bottom:6px;"><?= $kpr["sertifikat"]?"Ganti":"Upload" ?> Sertifikat (JPG/PNG/PDF, maks 10MB)</label>
            <input type="file" name="sertifikat" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
          </div>
          <button type="submit" class="btn btn-success" style="white-space:nowrap;">&#x1F4E4; Upload</button>
        </div>
      </form>
    </div>
    <div>
      <div style="font-weight:800;font-size:14px;color:#064e3b;margin-bottom:14px;">&#x1F4B0; B. Verifikasi Pembayaran DP Customer</div>
      <?php if (!$dp_data): ?>
        <div style="text-align:center;padding:40px;background:#f8fafc;border:2px dashed #e2e8f0;border-radius:16px;color:#94a3b8;">
          <div style="font-size:52px;margin-bottom:10px;">&#x23F3;</div>
          <div style="font-weight:700;font-size:15px;">Menunggu Customer Mengirim Bukti DP</div>
          <div style="font-size:12px;margin-top:6px;">Pastikan sertifikat sudah diupload agar customer bisa melanjutkan.</div>
        </div>
      <?php elseif ($dp_data["status_verifikasi"]==="valid"): ?>
        <div class="infobox green">&#x2705; DP telah diverifikasi VALID &mdash; <?= format_rupiah($dp_data["jumlah_dp"]) ?>. Status akan pindah ke Akad Kredit.</div>
      <?php else: ?>
        <div style="background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1px solid #fbbf24;border-radius:16px;padding:22px;margin-bottom:18px;">
          <div style="font-weight:800;font-size:16px;color:#92400e;margin-bottom:6px;">&#x1F4B8; Bukti Pembayaran DP Masuk!</div>
          <div style="font-size:24px;font-weight:900;color:#d97706;margin-bottom:8px;"><?= format_rupiah($dp_data["jumlah_dp"]) ?></div>
          <div style="font-size:12px;color:#b45309;">Metode: <?= ($dp_data["payment_method"]==="gateway")?"Midtrans (Online)":"Transfer Manual" ?> | <?= format_datetime($dp_data["tanggal_bayar"]) ?></div>
          <?php if ($dp_data["bukti_dp"] && $dp_data["bukti_dp"]!=="VIA_GATEWAY"): ?>
            <?php $bxt=strtolower(pathinfo($dp_data["bukti_dp"],PATHINFO_EXTENSION)); ?>
            <div style="margin-top:14px;padding:12px;background:#fff;border-radius:10px;border:1px solid #fde68a;">
              <div style="font-size:11px;font-weight:700;color:#b45309;margin-bottom:8px;">&#x1F4CE; Bukti Transfer:</div>
              <?php if (in_array($bxt,["jpg","jpeg","png","webp"])): ?>
              <img src="../../uploads/bukti_dp/<?= htmlspecialchars($dp_data["bukti_dp"]) ?>" style="max-height:180px;border-radius:8px;display:block;" alt="Bukti DP">
              <?php else: ?><a href="../../uploads/bukti_dp/<?= htmlspecialchars($dp_data["bukti_dp"]) ?>" target="_blank" class="btn btn-outline btn-sm">&#x1F4C4; Lihat File Bukti DP</a><?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
        <div style="display:flex;gap:12px;justify-content:flex-end;flex-wrap:wrap;">
          <a href="index.php?action=verif_dp&id=<?= $id ?>&id_dp=<?= $dp_data["id_dp"] ?>&aksi=tolak" class="btn-acc btn-acc-red" style="padding:12px 20px;font-size:13px;" onclick="return confirm(&apos;Tolak DP ini?\nCustomer akan diminta kirim ulang bukti.&apos;)">&#x274C; Tolak DP</a>
          <a href="index.php?action=verif_dp&id=<?= $id ?>&id_dp=<?= $dp_data["id_dp"] ?>&aksi=valid" class="btn-acc btn-acc-green" onclick="return confirm(&apos;DP ini VALID?\nStatus KPR akan pindah ke AKAD KREDIT dan jadwal cicilan dibuat!&apos;)">&#x2705; Setujui DP &rarr; Akad Kredit</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php elseif ($status==="akad_kredit"): ?>
<?php
$qbk=$db->prepare("SELECT booking_fee FROM booking WHERE id_user=? AND id_rumah=? ORDER BY id_booking DESC LIMIT 1"); $qbk->execute([$kpr["id_user"],$kpr["id_rumah"]]); $bfee=(float)($qbk->fetchColumn()?:0);
$qdp2=$db->prepare("SELECT jumlah_dp FROM pembayaran_dp WHERE id_pengajuan=? AND status_verifikasi=? LIMIT 1"); $qdp2->execute([$id,"valid"]); $dp_paid=(float)($qdp2->fetchColumn()?:0);
$qcic=$db->prepare("SELECT COALESCE(SUM(jumlah_cicilan),0) FROM cicilan_kpr WHERE id_pengajuan=? AND status_bayar=?"); $qcic->execute([$id,"lunas"]); $cic_paid=(float)($qcic->fetchColumn()?:0);
$total_paid=$bfee+$dp_paid+$cic_paid;
$harga_r=(float)$kpr["harga"]; $sisa=max(0,$harga_r-$total_paid);
$pct=$harga_r>0?round($total_paid/$harga_r*100):0;
$qsc=$db->prepare("SELECT COUNT(*) FROM cicilan_kpr WHERE id_pengajuan=? AND status_bayar=?"); $qsc->execute([$id,"belum"]); $sisa_cic=(int)$qsc->fetchColumn();
?>
<div class="proc-card" style="border:2px solid #0f172a;">
  <div class="proc-head" style="background:linear-gradient(135deg,#0f172a,#1e293b);">
    <h2>&#x1F91D; Langkah 5 &mdash; Akad Kredit &amp; Cicilan</h2>
    <span class="bak">BERJALAN</span>
  </div>
  <div class="proc-body">
    <div class="infobox green">&#x1F389; KPR resmi masuk tahap <b>Akad Kredit</b>. Jadwal cicilan sudah dibuat otomatis.</div>
    <div style="background:linear-gradient(135deg,#0f172a,#1e293b);border-radius:18px;padding:26px;color:#fff;margin-bottom:22px;">
      <div style="font-weight:700;font-size:12px;color:#94a3b8;margin-bottom:18px;letter-spacing:.8px;">&#x1F4CA; REKAP PELUNASAN PROPERTI</div>
      <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:14px;margin-bottom:20px;">
        <div style="background:rgba(255,255,255,0.07);border-radius:12px;padding:14px;"><div style="font-size:11px;color:#94a3b8;margin-bottom:4px;">Harga Rumah</div><div style="font-size:16px;font-weight:800;"><?= format_rupiah($harga_r) ?></div></div>
        <div style="background:rgba(255,255,255,0.07);border-radius:12px;padding:14px;"><div style="font-size:11px;color:#94a3b8;margin-bottom:4px;">Booking Fee</div><div style="font-size:16px;font-weight:800;color:#22c55e;"><?= format_rupiah($bfee) ?></div></div>
        <div style="background:rgba(255,255,255,0.07);border-radius:12px;padding:14px;"><div style="font-size:11px;color:#94a3b8;margin-bottom:4px;">Uang Muka (DP)</div><div style="font-size:16px;font-weight:800;color:#fbbf24;"><?= format_rupiah($dp_paid) ?></div></div>
        <div style="background:rgba(255,255,255,0.07);border-radius:12px;padding:14px;"><div style="font-size:11px;color:#94a3b8;margin-bottom:4px;">Cicilan Lunas</div><div style="font-size:16px;font-weight:800;color:#60a5fa;"><?= format_rupiah($cic_paid) ?></div></div>
      </div>
      <div style="background:rgba(255,255,255,0.1);border-radius:100px;height:12px;margin-bottom:12px;overflow:hidden;"><div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,#22c55e,#10b981);border-radius:100px;"></div></div>
      <div style="display:flex;justify-content:space-between;font-size:12px;flex-wrap:wrap;gap:6px;">
        <span style="color:#22c55e;font-weight:700;">Dibayar: <?= format_rupiah($total_paid) ?> (<?= $pct ?>%)</span>
        <span style="color:#f87171;font-weight:700;">Sisa: <?= format_rupiah($sisa) ?> | <?= $sisa_cic ?> cicilan lagi</span>
      </div>
    </div>
    <div style="display:flex;justify-content:center;">
      <a href="../cicilan/index.php?id_pengajuan=<?= $id ?>" class="btn-acc btn-acc-blue">&#x1F4B3; Kelola &amp; Verifikasi Cicilan Bulanan</a>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="panel">
  <div class="panel-header"><h3>&#x1F4DC; Riwayat Alur Proses KPR</h3></div>
  <div class="panel-body">
    <?php if (empty($tracking)): ?><p style="color:var(--muted);text-align:center;">Belum ada riwayat proses.</p>
    <?php else: ?>
    <div class="track-wrap">
      <?php foreach ($tracking as $ti=>$t): ?>
      <div class="track-item">
        <div class="track-dot <?= $ti===0?"latest":"" ?>"></div>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
          <span style="font-size:11px;color:var(--muted);"><?= format_datetime($t["tanggal_update"]) ?></span>
          <?php if ($ti===0): ?><span style="font-size:10px;background:#22c55e;color:#fff;padding:2px 8px;border-radius:20px;font-weight:700;">TERKINI</span><?php endif; ?>
        </div>
        <div style="margin:4px 0 6px;"><?= badge_kpr($t["status"]) ?></div>
        <p style="font-size:13px;color:var(--sub);line-height:1.5;background:#f8fafc;padding:8px 12px;border-radius:8px;border-left:3px solid #3b82f6;margin:0;"><?= htmlspecialchars($t["keterangan"]) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
</div>

<div>
<div class="panel" style="margin-bottom:16px;">
  <div class="panel-header"><h3>&#x1F4CA; Status Saat Ini</h3></div>
  <div class="panel-body" style="text-align:center;padding:20px;">
    <?= badge_kpr($kpr["status_pengajuan"]) ?>
    <div style="font-size:12px;color:var(--muted);margin-top:10px;">Pengajuan sejak <?= format_tanggal($kpr["tanggal_pengajuan"]) ?></div>
  </div>
</div>
<div class="panel" style="position:sticky;top:80px;">
  <div class="panel-header"><h3>&#x1F504; Update Status Manual</h3></div>
  <div class="panel-body">
    <form method="POST" action="index.php?action=update_status&id=<?= $id ?>">
      <div class="form-group">
        <label>Ubah Status KPR</label>
        <select name="status_pengajuan" class="form-control" required>
          <option value="pengajuan_masuk"    <?= $status==="pengajuan_masuk"    ?"selected":"" ?>>Pengajuan Masuk</option>
          <option value="verifikasi_dokumen" <?= $status==="verifikasi_dokumen" ?"selected":"" ?>>Verifikasi Dokumen</option>
          <option value="survey"             <?= $status==="survey"             ?"selected":"" ?>>Survey & BI Cek</option>
          <option value="disetujui"          <?= $status==="disetujui"          ?"selected":"" ?>>Disetujui Bank</option>
          <option value="ditolak"            <?= $status==="ditolak"            ?"selected":"" ?>>Ditolak</option>
        </select>
      </div>
      <div class="form-group">
        <label>Keterangan (Riwayat)</label>
        <textarea name="keterangan" class="form-control" rows="3" placeholder="Berkas lengkap dan valid..." required></textarea>
        <small class="form-hint">Terlihat oleh customer.</small>
      </div>
      <div class="form-group">
        <label>Catatan Internal Admin</label>
        <textarea name="catatan_admin" class="form-control" rows="2" placeholder="Memo internal..."><?= htmlspecialchars($kpr["catatan_admin"]??"") ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">&#x1F4BE; Perbarui Status</button>
    </form>
  </div>
</div>
</div>
</div>

<?php else: ?>

<div class="page-header">
  <div class="page-header-left"><h2>&#x1F4DD; Pengajuan KPR</h2><p>Kelola verifikasi KPR, survey, hingga akad kredit</p></div>
</div>
<div class="panel" style="margin-bottom:18px;">
  <div class="panel-body" style="padding:16px 20px;">
    <form method="GET" action="" class="search-bar" style="margin:0;">
      <select name="f_status" style="max-width:260px;">
        <option value="">-- Semua Status --</option>
        <option value="pengajuan_masuk"    <?= $fs==="pengajuan_masuk"    ?"selected":"" ?>>Pengajuan Masuk</option>
        <option value="verifikasi_dokumen" <?= $fs==="verifikasi_dokumen" ?"selected":"" ?>>Verifikasi Dokumen</option>
        <option value="survey"             <?= $fs==="survey"             ?"selected":"" ?>>Survey</option>
        <option value="disetujui"          <?= $fs==="disetujui"          ?"selected":"" ?>>Disetujui</option>
        <option value="akad_kredit"        <?= $fs==="akad_kredit"        ?"selected":"" ?>>Akad Kredit</option>
        <option value="ditolak"            <?= $fs==="ditolak"            ?"selected":"" ?>>Ditolak</option>
      </select>
      <button type="submit" class="btn btn-primary btn-sm">Filter</button>
      <?php if (!empty($fs)): ?><a href="index.php" class="btn btn-gray btn-sm">Reset</a><?php endif; ?>
    </form>
  </div>
</div>
<div class="panel"><div class="panel-body" style="padding:0;"><div class="tbl-wrap"><table>
  <thead><tr><th>No</th><th>Customer</th><th>Detail Rumah</th><th>Mitra Bank</th><th>Tanggal</th><th>Status</th><th style="width:140px;text-align:center;">Aksi</th></tr></thead>
  <tbody>
  <?php if (empty($list_kpr)): ?>
    <tr><td colspan="7" class="empty">Tidak ada pengajuan KPR.</td></tr>
  <?php else: $no=1; foreach ($list_kpr as $k): ?>
    <tr>
      <td><?= $no++ ?></td>
      <td><b><?= htmlspecialchars($k["nama_lengkap"]) ?></b></td>
      <td><b><?= htmlspecialchars($k["nama_perumahan"]) ?></b><br><small style="color:var(--primary);font-weight:700;">Blok <?= htmlspecialchars($k["blok"]."-".$k["kode_unit"]) ?></small></td>
      <td><b><?= htmlspecialchars($k["nama_bank"]) ?></b></td>
      <td><?= format_tanggal($k["tanggal_pengajuan"]) ?></td>
      <td><?= badge_kpr($k["status_pengajuan"]) ?></td>
      <td style="text-align:center;"><a href="index.php?action=detail&id=<?= $k["id_pengajuan"] ?>" class="btn btn-primary btn-sm">&#x1F441;&#xFE0F; Detail</a></td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table></div></div></div>
<?php endif; ?>
</main>
</div>
<script src="../../assets/js/script.js"></script>
</body>
</html>
