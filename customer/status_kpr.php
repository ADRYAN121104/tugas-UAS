<?php
// customer/status_kpr.php
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';

$id_user = id_user();
$id_pengajuan = (int)($_GET['id'] ?? 0);

// ── PROSES UPLOAD BUKTI DP (embedded form) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_dp'])) {
    $id_peng = (int)($_POST['id_pengajuan'] ?? 0);
    // Verifikasi kepemilikan
    $vf = $db->prepare("SELECT id_pengajuan, uang_muka, status_pengajuan, sertifikat FROM pengajuan_kpr WHERE id_pengajuan=? AND id_user=?");
    $vf->execute([$id_peng, $id_user]);
    $kpr_vf = $vf->fetch();
    if (!$kpr_vf || $kpr_vf['status_pengajuan'] !== 'disetujui') {
        set_flash('gagal', 'Pengajuan tidak valid atau belum disetujui.');
    } elseif (empty($_FILES['bukti_dp']['name'])) {
        set_flash('gagal', 'Pilih file bukti transfer DP terlebih dahulu.');
    } else {
        $dir = '../uploads/bukti_dp/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['bukti_dp']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','pdf'])) {
            set_flash('gagal', 'Format tidak valid. Gunakan JPG/PNG/PDF.');
        } elseif ($_FILES['bukti_dp']['size'] > 5*1024*1024) {
            set_flash('gagal', 'File terlalu besar, maksimal 5MB.');
        } else {
            $fname = 'dp_' . $id_peng . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['bukti_dp']['tmp_name'], $dir . $fname);
            // Cek apakah sudah ada
            $cek_dp = $db->prepare("SELECT id_dp FROM pembayaran_dp WHERE id_pengajuan=? LIMIT 1");
            $cek_dp->execute([$id_peng]);
            $dp_old = $cek_dp->fetchColumn();
            if ($dp_old) {
                $db->prepare("UPDATE pembayaran_dp SET bukti_dp=?, jumlah_dp=?, tanggal_bayar=NOW(), status_verifikasi='pending' WHERE id_dp=?")
                   ->execute([$fname, $kpr_vf['uang_muka'], $dp_old]);
            } else {
                $db->prepare("INSERT INTO pembayaran_dp (id_pengajuan, jumlah_dp, bukti_dp, tanggal_bayar, status_verifikasi) VALUES (?, ?, ?, NOW(), 'pending')")
                   ->execute([$id_peng, $kpr_vf['uang_muka'], $fname]);
            }
            $db->prepare("INSERT INTO tracking_pengajuan (id_pengajuan, status, keterangan, tanggal_update) VALUES (?, 'disetujui', ?, NOW())")
               ->execute([$id_peng, '\ud83d\udcb0 Customer telah mengirimkan bukti pembayaran DP sebesar ' . format_rupiah($kpr_vf['uang_muka']) . '. Menunggu verifikasi admin.']);
            set_flash('sukses', '\u2705 Bukti DP berhasil dikirim! Admin akan memverifikasi segera. Halaman ini akan update otomatis.');
        }
    }
    header("Location: status_kpr.php?id=$id_peng");
    exit;
}

// Ambil semua pengajuan KPR milik user
$all = $db->prepare("
    SELECT pk.*, pk.sertifikat, p.nama_perumahan, p.alamat, p.maps_link,
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
        SELECT pk.*, pk.sertifikat, p.nama_perumahan, p.alamat, p.maps_link, b.nama_bank, b.bunga_kpr, r.blok, r.kode_unit, r.nama_tipe, r.harga 
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

// Ambil dokumen KPR saat ini (jika ada)
$dokumen_kpr = null;
if ($id_pengajuan) {
    $dr = $db->prepare("SELECT * FROM dokumen_kpr WHERE id_pengajuan = ?");
    $dr->execute([$id_pengajuan]);
    $dokumen_kpr = $dr->fetch();
}

// Ambil catatan admin terbaru (dari tracking verifikasi_dokumen)
$catatan_verifikasi = '';
if (!empty($tracking)) {
    foreach (array_reverse($tracking) as $t) {
        if ($t['status'] === 'verifikasi_dokumen' && strpos($t['keterangan'], 'Customer telah') === false) {
            $catatan_verifikasi = $t['keterangan'];
            break;
        }
    }
}

// Ambil data pembayaran DP (jika status akad_kredit)
$dp_akad = null;
if ($id_pengajuan && isset($kpr_aktif['status_pengajuan']) && $kpr_aktif['status_pengajuan'] === 'akad_kredit') {
    $dp_q = $db->prepare("SELECT * FROM pembayaran_dp WHERE id_pengajuan = ? ORDER BY created_at DESC LIMIT 1");
    $dp_q->execute([$id_pengajuan]);
    $dp_akad = $dp_q->fetch();
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

/* MODAL REUPLOAD */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,0.55);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
    backdrop-filter: blur(4px);
}
.modal-overlay.open {
    display: flex;
}
.modal-box {
    background: #fff;
    border-radius: 16px;
    padding: 28px 32px;
    max-width: 560px;
    width: 100%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    animation: modalIn .25s ease;
    max-height: 90vh;
    overflow-y: auto;
}
@keyframes modalIn {
    from { opacity:0; transform:translateY(-20px) scale(0.97); }
    to   { opacity:1; transform:translateY(0) scale(1); }
}
.upload-zone {
    border: 2px dashed #cbd5e1;
    border-radius: 10px;
    padding: 14px 16px;
    background: #f8fafc;
    cursor: pointer;
    transition: border-color .2s, background .2s;
}
.upload-zone:hover {
    border-color: #2563eb;
    background: #eff6ff;
}
.upload-zone.has-file {
    border-color: #10b981;
    background: #ecfdf5;
}
.file-preview-name {
    font-size: 11.5px;
    color: #10b981;
    font-weight: 700;
    margin-top: 4px;
    display: none;
}
.dok-row {
    margin-bottom: 14px;
}
.dok-label {
    font-size: 13px;
    font-weight: 700;
    color: #374151;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.dok-current {
    font-size: 11px;
    color: #64748b;
    margin-bottom: 5px;
}
.dok-current span {
    font-weight: 600;
    color: #2563eb;
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

        <!-- Notifikasi Verifikasi Dokumen - tombol re-upload -->
        <?php if ($status_skrg === 'verifikasi_dokumen'): ?>
        <div style="background:linear-gradient(135deg,#fffbeb,#fef3c7); border:2px solid #f59e0b; border-radius:14px; padding:20px 24px; margin-bottom:24px; display:flex; align-items:flex-start; gap:16px; box-shadow:0 4px 20px rgba(245,158,11,0.15);">
            <div style="font-size:32px; flex-shrink:0;">⚠️</div>
            <div style="flex:1;">
                <div style="font-size:15px; font-weight:800; color:#92400e; margin-bottom:6px;">Dokumen Perlu Diperbaiki</div>
                <?php if ($catatan_verifikasi): ?>
                <div style="font-size:13.5px; color:#78350f; margin-bottom:14px; line-height:1.6; background:#fff; border-radius:8px; padding:10px 14px; border-left:3px solid #f59e0b;">
                    📋 <b>Catatan Admin:</b> <?= htmlspecialchars($catatan_verifikasi) ?>
                </div>
                <?php endif; ?>
                <div style="font-size:13px; color:#92400e; margin-bottom:14px;">Silakan upload ulang dokumen yang bermasalah. Anda tetap berada di halaman ini, tidak perlu berpindah halaman.</div>
                <button id="btnOpenReupload" onclick="openReuploadModal()" class="btn btn-primary" style="background:linear-gradient(135deg,#f59e0b,#d97706); border:none; color:#fff; font-weight:800; padding:10px 20px; border-radius:10px; cursor:pointer; display:inline-flex; align-items:center; gap:8px;">
                    📤 Upload Ulang Dokumen
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- ═══ NOTIFIKASI: Status DISETUJUI BANK → Form Bayar DP Embedded ═══ -->
        <?php if ($status_skrg === 'disetujui'): ?>
        <?php
        // Cek sertifikat dan status DP
        $sertif_file = $kpr_aktif['sertifikat'] ?? null;
        $dp_check = $db->prepare("SELECT * FROM pembayaran_dp WHERE id_pengajuan=? ORDER BY created_at DESC LIMIT 1");
        $dp_check->execute([$id_pengajuan]);
        $dp_status_data = $dp_check->fetch();
        ?>

        <!-- CARD: KPR Disetujui Bank! -->
        <div style="background:linear-gradient(135deg,#0f172a,#064e3b,#059669);border-radius:16px;padding:24px 28px;color:#fff;margin-bottom:20px;position:relative;overflow:hidden;">
            <div style="position:absolute;right:-10px;top:-10px;font-size:110px;opacity:.06;">🏦</div>
            <div style="position:relative;display:flex;align-items:flex-start;gap:16px;">
                <div style="font-size:40px;flex-shrink:0;">✅</div>
                <div>
                    <div style="font-size:18px;font-weight:900;margin-bottom:6px;">🎉 KPR Disetujui Bank!</div>
                    <div style="opacity:.8;font-size:13px;">Selamat! Bank menyetujui pengajuan KPR Anda untuk properti <b><?= htmlspecialchars($kpr_aktif['nama_perumahan'] ?? '') ?></b> Blok <?= htmlspecialchars(($kpr_aktif['blok'] ?? '').'-'.($kpr_aktif['kode_unit'] ?? '')) ?>.</div>
                    <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
                        <div style="background:rgba(255,255,255,.12);border-radius:8px;padding:8px 14px;font-size:13px;">
                            <span style="opacity:.7;font-size:11px;display:block;">Harga Rumah</span>
                            <b><?= format_rupiah($kpr_aktif['harga'] ?? 0) ?></b>
                        </div>
                        <div style="background:rgba(255,255,255,.12);border-radius:8px;padding:8px 14px;font-size:13px;">
                            <span style="opacity:.7;font-size:11px;display:block;">Uang Muka (DP)</span>
                            <b><?= format_rupiah($kpr_aktif['uang_muka'] ?? 0) ?></b>
                        </div>
                        <div style="background:rgba(255,255,255,.12);border-radius:8px;padding:8px 14px;font-size:13px;">
                            <span style="opacity:.7;font-size:11px;display:block;">Bank</span>
                            <b><?= htmlspecialchars($kpr_aktif['nama_bank'] ?? '') ?> (<?= $kpr_aktif['bunga_kpr'] ?? 0 ?>%)</b>
                        </div>
                        <div style="background:rgba(255,255,255,.12);border-radius:8px;padding:8px 14px;font-size:13px;">
                            <span style="opacity:.7;font-size:11px;display:block;">Tenor</span>
                            <b><?= $kpr_aktif['tenor'] ?? 0 ?> Tahun</b>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ALUR LANGKAH -->
        <div style="background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:20px 24px;margin-bottom:20px;box-shadow:0 4px 16px rgba(0,0,0,.04);">
            <div style="font-size:13px;font-weight:800;color:#1e40af;margin-bottom:14px;">📋 Langkah Selanjutnya</div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;">
                <?php
                $dp_done = ($dp_status_data && $dp_status_data['status_verifikasi']==='valid');
                $langkah_dp = [
                    ['📄','Lihat Sertifikat', $sertif_file ? 'done' : 'active'],
                    ['💰','Bayar DP', $dp_done ? 'done' : ($dp_status_data && $dp_status_data['status_verifikasi']==='pending' ? 'active' : 'pending')],
                    ['🤝','Konfirmasi Akad', $dp_done ? 'active' : 'pending'],
                    ['💳','Bayar Cicilan','pending'],
                ];
                foreach ($langkah_dp as $l):
                    $col = $l[2]==='done' ? '#10b981' : ($l[2]==='active' ? '#f59e0b' : '#94a3b8');
                    $bg  = $l[2]==='done' ? '#d1fae5' : ($l[2]==='active' ? '#fef3c7' : '#f8faff');
                ?>
                <div style="text-align:center;background:<?= $bg ?>;border-radius:10px;padding:12px 8px;border:1px solid <?= $l[2]==='active' ? '#fbbf24' : '#e2e8f0' ?>;">
                    <div style="font-size:22px;margin-bottom:4px;"><?= $l[0] ?></div>
                    <div style="font-size:11px;font-weight:800;color:<?= $col ?>;"><?= $l[1] ?></div>
                    <?php if ($l[2]==='done'): ?><div style="font-size:10px;color:#10b981;">✓ Selesai</div><?php endif; ?>
                    <?php if ($l[2]==='active'): ?><div style="font-size:10px;color:#f59e0b;font-weight:700;">← Saat ini</div><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- SERTIFIKAT KPR dari Admin -->
        <div style="background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:20px 24px;margin-bottom:20px;box-shadow:0 4px 16px rgba(0,0,0,.04);">
            <div style="font-size:14px;font-weight:800;margin-bottom:12px;">📄 Sertifikat KPR dari Admin</div>
            <?php if ($sertif_file): ?>
                <?php $ext_s = strtolower(pathinfo($sertif_file, PATHINFO_EXTENSION)); ?>
                <?php if (in_array($ext_s, ['jpg','jpeg','png'])): ?>
                    <img src="../uploads/sertifikat/<?= htmlspecialchars($sertif_file) ?>"
                         style="max-width:100%;max-height:320px;border-radius:10px;border:1px solid #e2e8f0;margin-bottom:12px;display:block;"
                         alt="Sertifikat KPR">
                <?php else: ?>
                    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px;text-align:center;margin-bottom:12px;">
                        <div style="font-size:32px;margin-bottom:6px;">📄</div>
                        <div style="font-weight:700;color:#1e40af;">Sertifikat KPR (PDF)</div>
                    </div>
                <?php endif; ?>
                <a href="../uploads/sertifikat/<?= htmlspecialchars($sertif_file) ?>" target="_blank"
                   class="btn btn-primary" style="display:inline-flex;align-items:center;gap:8px;font-size:13px;">
                    📥 Download Sertifikat Lengkap
                </a>
            <?php else: ?>
                <div style="background:#fef3c7;border:1px solid #fbbf24;border-radius:10px;padding:16px;text-align:center;color:#92400e;">
                    <div style="font-size:28px;margin-bottom:6px;">⏳</div>
                    <div style="font-weight:700;margin-bottom:4px;">Sertifikat Belum Tersedia</div>
                    <div style="font-size:12px;">Admin sedang menyiapkan sertifikat KPR Anda. Halaman ini update otomatis.</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- FORM BAYAR DP (selalu tampil setelah disetujui bank) -->
        <div style="background:#fff;border-radius:14px;border:2px solid <?= $dp_done ? '#10b981' : '#fbbf24' ?>;padding:20px 24px;margin-bottom:20px;box-shadow:0 4px 16px rgba(0,0,0,.04);">
            <div style="font-size:14px;font-weight:800;margin-bottom:4px;">💰 Pembayaran Uang Muka (DP)</div>
            <p style="font-size:12px;color:#64748b;margin-bottom:16px;">Transfer DP sebesar <b style="color:#d97706;"><?= format_rupiah($kpr_aktif['uang_muka'] ?? 0) ?></b> lalu upload bukti transfer di bawah ini.</p>

            <!-- Info rekening -->
            <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px 16px;margin-bottom:16px;font-size:13px;line-height:1.9;">
                🏦 <b>Transfer ke:</b><br>
                Bank: <b>BCA</b> &bull; No. Rekening: <b>1234-5678-90</b><br>
                Atas Nama: <b>PT RumahKPR Indonesia</b><br>
                Nominal DP: <b style="color:#d97706;font-size:15px;"><?= format_rupiah($kpr_aktif['uang_muka'] ?? 0) ?></b>
            </div>

            <?php if ($dp_done): ?>
                <!-- DP sudah valid -->
                <div style="background:#d1fae5;border:2px solid #10b981;border-radius:12px;padding:16px;text-align:center;">
                    <div style="font-size:32px;margin-bottom:8px;">✅</div>
                    <div style="font-weight:900;color:#065f46;font-size:16px;">DP Sudah Terverifikasi!</div>
                    <div style="font-size:13px;color:#059669;margin-top:6px;"><?= format_rupiah($dp_status_data['jumlah_dp']) ?> — Menunggu konfirmasi Akad Kredit oleh admin</div>
                </div>
            <?php elseif ($dp_status_data && $dp_status_data['status_verifikasi']==='pending'): ?>
                <!-- DP pending -->
                <div style="background:#fef3c7;border:2px solid #fbbf24;border-radius:12px;padding:16px;margin-bottom:14px;">
                    <div style="font-weight:800;color:#92400e;margin-bottom:4px;">⏳ Bukti DP Sedang Diverifikasi Admin</div>
                    <div style="font-size:12px;color:#b45309;">Dikirim: <?= format_datetime($dp_status_data['tanggal_bayar']) ?></div>
                    <?php if ($dp_status_data['bukti_dp']): ?>
                        <a href="../uploads/bukti_dp/<?= htmlspecialchars($dp_status_data['bukti_dp']) ?>" target="_blank"
                           style="font-size:12px;color:#2563eb;font-weight:700;display:inline-flex;align-items:center;gap:4px;margin-top:8px;">📎 Lihat bukti yang dikirim</a>
                    <?php endif; ?>
                </div>
                <!-- Opsi upload ulang -->
                <details style="border:1px solid #e2e8f0;border-radius:10px;padding:12px;">
                    <summary style="cursor:pointer;font-size:12px;font-weight:700;color:#64748b;">📤 Upload ulang jika ada revisi</summary>
                    <form method="POST" enctype="multipart/form-data" style="margin-top:12px;">
                        <input type="hidden" name="upload_dp" value="1">
                        <input type="hidden" name="id_pengajuan" value="<?= $id_pengajuan ?>">
                        <input type="file" name="bukti_dp" accept=".jpg,.jpeg,.png,.pdf" required
                               style="width:100%;padding:10px;border:2px dashed #93c5fd;border-radius:8px;background:#eff6ff;margin-bottom:10px;">
                        <button type="submit" style="background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;border:none;padding:10px 20px;border-radius:8px;font-weight:700;cursor:pointer;width:100%;">
                            📤 Kirim Ulang Bukti DP
                        </button>
                    </form>
                </details>
            <?php elseif ($dp_status_data && $dp_status_data['status_verifikasi']==='ditolak'): ?>
                <!-- DP ditolak -->
                <div style="background:#fee2e2;border:2px solid #ef4444;border-radius:12px;padding:14px;margin-bottom:16px;">
                    <div style="font-weight:800;color:#991b1b;margin-bottom:4px;">❌ Bukti DP Ditolak Admin</div>
                    <div style="font-size:12px;color:#b91c1c;">Silakan upload ulang bukti transfer yang valid.</div>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="upload_dp" value="1">
                    <input type="hidden" name="id_pengajuan" value="<?= $id_pengajuan ?>">
                    <input type="file" name="bukti_dp" accept=".jpg,.jpeg,.png,.pdf" required
                           style="width:100%;padding:12px;border:2px dashed #fca5a5;border-radius:10px;background:#fff5f5;margin-bottom:12px;">
                    <button type="submit" style="background:linear-gradient(135deg,#059669,#047857);color:#fff;border:none;padding:12px 20px;border-radius:10px;font-weight:800;cursor:pointer;width:100%;font-size:14px;">
                        💰 Kirim Ulang Bukti DP
                    </button>
                </form>
            <?php else: ?>
                <!-- Belum bayar DP -->
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="upload_dp" value="1">
                    <input type="hidden" name="id_pengajuan" value="<?= $id_pengajuan ?>">
                    <label style="display:block;border:2px dashed #93c5fd;border-radius:12px;background:#eff6ff;padding:20px;text-align:center;cursor:pointer;transition:.2s;margin-bottom:12px;" id="dpUploadZone">
                        <div style="font-size:28px;margin-bottom:6px;">📎</div>
                        <div style="font-weight:700;color:#1e40af;margin-bottom:3px;">Klik untuk pilih file bukti transfer</div>
                        <div style="font-size:12px;color:#64748b;">Format JPG/PNG/PDF · Maks 5MB</div>
                        <div id="dpFileName" style="display:none;margin-top:8px;font-size:12px;font-weight:700;color:#10b981;"></div>
                        <input type="file" name="bukti_dp" id="dpFileInput" accept=".jpg,.jpeg,.png,.pdf" required style="display:none;"
                               onchange="const f=this.files[0]; if(f){document.getElementById('dpFileName').style.display='block'; document.getElementById('dpFileName').textContent='✓ '+f.name; document.getElementById('dpUploadZone').style.borderColor='#10b981'; document.getElementById('dpUploadZone').style.background='#ecfdf5';}">
                    </label>
                    <button type="submit" style="background:linear-gradient(135deg,#059669,#047857);color:#fff;border:none;padding:14px 20px;border-radius:12px;font-weight:900;cursor:pointer;width:100%;font-size:15px;display:flex;align-items:center;justify-content:center;gap:8px;">
                        💰 Kirim Bukti Pembayaran DP
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ═══ NOTIFIKASI: Akad Kredit → Lihat Cicilan ═══ -->
        <?php if ($status_skrg === 'akad_kredit'): ?>
        <div style="background:linear-gradient(135deg,#0f172a,#1e3a8a,#2563eb);border-radius:16px;padding:24px;color:#fff;margin-bottom:20px;position:relative;overflow:hidden;">
            <div style="position:absolute;right:-10px;top:-10px;font-size:110px;opacity:.06;">💳</div>
            <div style="position:relative;display:flex;align-items:flex-start;gap:16px;">
                <div style="font-size:40px;flex-shrink:0;">🤝</div>
                <div>
                    <div style="font-size:18px;font-weight:900;margin-bottom:6px;">Akad Kredit Selesai!</div>
                    <div style="opacity:.8;font-size:13px;margin-bottom:14px;">Selamat! Rumah sudah resmi menjadi milik Anda. Bayar cicilan tepat waktu setiap bulan.</div>
                    <a href="cicilan.php" style="display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);border-radius:10px;padding:10px 20px;color:#fff;font-weight:800;text-decoration:none;font-size:13px;">
                        💳 Lihat Jadwal Cicilan & Bayar →
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>




        <!-- History Timeline -->
        <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:24px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
            <h3 style="font-size:16px; font-weight:800; margin-bottom:18px; border-bottom:1px solid #f1f5f9; padding-bottom:10px;">📜 Riwayat Pelacakan Proses</h3>
            
            <?php if(empty($tracking)): ?>
                <p style="color:#94a3b8;text-align:center;padding:20px;">Belum ada riwayat proses.</p>
            <?php else: ?>
                <div style="position:relative;padding-left:24px;border-left:2px solid #e2e8f0; margin-left:10px; margin-top:10px;">
                    <?php foreach(array_reverse($tracking) as $t): ?>
                        <?php
                        // Tandai apakah ini adalah tracking re-upload dari customer
                        $is_reupload = (strpos($t['keterangan'], 'Customer telah mengupload ulang') !== false);
                        ?>
                        <div style="position:relative;margin-bottom:24px;padding-left:16px;">
                            <div style="position:absolute;left:-31px;top:2px;width:12px;height:12px;border-radius:50%;background:<?= $is_reupload ? '#10b981' : '#2563eb' ?>;border:2px solid #fff;box-shadow:0 0 0 2px <?= $is_reupload ? '#10b981' : '#2563eb' ?>;"></div>
                            <div style="font-size:11px;color:#94a3b8;margin-bottom:4px;"><?= format_datetime($t['tanggal_update']) ?></div>
                            <div style="font-weight:700;font-size:13.5px;">
                                <?= badge_kpr($t['status']) ?>
                                <?php if ($is_reupload): ?>
                                    <span style="font-size:10.5px; background:#d1fae5; color:#065f46; padding:2px 8px; border-radius:12px; margin-left:6px; font-weight:700;">✅ Re-upload Customer</span>
                                <?php endif; ?>
                            </div>
                            <p style="font-size:13px;color:#475569;margin-top:6px; line-height:1.5;"><?= htmlspecialchars($t['keterangan']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>

<!-- Modal Re-upload Dokumen -->
<?php if ($kpr_aktif && $status_skrg === 'verifikasi_dokumen'): ?>
<div class="modal-overlay" id="modalReupload">
    <div class="modal-box">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
            <div>
                <h2 style="font-size:17px; font-weight:800; color:#0f172a; margin:0;">📤 Upload Ulang Dokumen KPR</h2>
                <p style="font-size:12.5px; color:#64748b; margin:4px 0 0;">Upload hanya dokumen yang perlu diperbaiki. Dokumen lain tetap tersimpan.</p>
            </div>
            <button onclick="closeReuploadModal()" style="background:none; border:none; font-size:20px; cursor:pointer; color:#94a3b8; line-height:1; padding:4px 8px;" title="Tutup">✕</button>
        </div>

        <div id="reupload-alert" style="display:none; border-radius:10px; padding:12px 16px; margin-bottom:16px; font-size:13px; font-weight:600;"></div>

        <form id="formReupload" enctype="multipart/form-data">
            <input type="hidden" name="id_pengajuan" value="<?= $id_pengajuan ?>">

            <!-- KTP -->
            <div class="dok-row">
                <div class="dok-label">
                    🪪 KTP
                    <?php if (!empty($dokumen_kpr['ktp'])): ?>
                        <span style="font-size:10.5px; background:#d1fae5; color:#065f46; padding:2px 8px; border-radius:10px; font-weight:600;">✓ Sudah ada</span>
                    <?php else: ?>
                        <span style="font-size:10.5px; background:#fee2e2; color:#991b1b; padding:2px 8px; border-radius:10px; font-weight:600;">✗ Belum ada</span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($dokumen_kpr['ktp'])): ?>
                    <div class="dok-current">File saat ini: <span><?= htmlspecialchars($dokumen_kpr['ktp']) ?></span></div>
                <?php endif; ?>
                <label class="upload-zone" id="zone-ktp" for="reupload_ktp">
                    <div style="font-size:13px; color:#64748b; text-align:center;">📎 Klik untuk pilih file baru <span style="color:#94a3b8;">(JPG/PNG/PDF, maks 5MB)</span></div>
                    <div class="file-preview-name" id="prev-ktp"></div>
                </label>
                <input type="file" id="reupload_ktp" name="ktp" accept=".jpg,.jpeg,.png,.pdf" style="display:none;" onchange="previewFile(this,'zone-ktp','prev-ktp')">
            </div>

            <!-- KK -->
            <div class="dok-row">
                <div class="dok-label">
                    👨‍👩‍👧 Kartu Keluarga (KK)
                    <?php if (!empty($dokumen_kpr['kk'])): ?>
                        <span style="font-size:10.5px; background:#d1fae5; color:#065f46; padding:2px 8px; border-radius:10px; font-weight:600;">✓ Sudah ada</span>
                    <?php else: ?>
                        <span style="font-size:10.5px; background:#fee2e2; color:#991b1b; padding:2px 8px; border-radius:10px; font-weight:600;">✗ Belum ada</span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($dokumen_kpr['kk'])): ?>
                    <div class="dok-current">File saat ini: <span><?= htmlspecialchars($dokumen_kpr['kk']) ?></span></div>
                <?php endif; ?>
                <label class="upload-zone" id="zone-kk" for="reupload_kk">
                    <div style="font-size:13px; color:#64748b; text-align:center;">📎 Klik untuk pilih file baru <span style="color:#94a3b8;">(JPG/PNG/PDF, maks 5MB)</span></div>
                    <div class="file-preview-name" id="prev-kk"></div>
                </label>
                <input type="file" id="reupload_kk" name="kk" accept=".jpg,.jpeg,.png,.pdf" style="display:none;" onchange="previewFile(this,'zone-kk','prev-kk')">
            </div>

            <!-- Slip Gaji -->
            <div class="dok-row">
                <div class="dok-label">
                    💼 Slip Gaji
                    <?php if (!empty($dokumen_kpr['slip_gaji'])): ?>
                        <span style="font-size:10.5px; background:#d1fae5; color:#065f46; padding:2px 8px; border-radius:10px; font-weight:600;">✓ Sudah ada</span>
                    <?php else: ?>
                        <span style="font-size:10.5px; background:#fee2e2; color:#991b1b; padding:2px 8px; border-radius:10px; font-weight:600;">✗ Belum ada</span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($dokumen_kpr['slip_gaji'])): ?>
                    <div class="dok-current">File saat ini: <span><?= htmlspecialchars($dokumen_kpr['slip_gaji']) ?></span></div>
                <?php endif; ?>
                <label class="upload-zone" id="zone-slip" for="reupload_slip_gaji">
                    <div style="font-size:13px; color:#64748b; text-align:center;">📎 Klik untuk pilih file baru <span style="color:#94a3b8;">(JPG/PNG/PDF, maks 5MB)</span></div>
                    <div class="file-preview-name" id="prev-slip"></div>
                </label>
                <input type="file" id="reupload_slip_gaji" name="slip_gaji" accept=".jpg,.jpeg,.png,.pdf" style="display:none;" onchange="previewFile(this,'zone-slip','prev-slip')">
            </div>

            <!-- NPWP -->
            <div class="dok-row">
                <div class="dok-label">
                    🧾 NPWP <span style="font-size:11px; font-weight:400; color:#94a3b8;">(Opsional)</span>
                    <?php if (!empty($dokumen_kpr['npwp'])): ?>
                        <span style="font-size:10.5px; background:#d1fae5; color:#065f46; padding:2px 8px; border-radius:10px; font-weight:600;">✓ Sudah ada</span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($dokumen_kpr['npwp'])): ?>
                    <div class="dok-current">File saat ini: <span><?= htmlspecialchars($dokumen_kpr['npwp']) ?></span></div>
                <?php endif; ?>
                <label class="upload-zone" id="zone-npwp" for="reupload_npwp">
                    <div style="font-size:13px; color:#64748b; text-align:center;">📎 Klik untuk pilih file baru <span style="color:#94a3b8;">(JPG/PNG/PDF, maks 5MB)</span></div>
                    <div class="file-preview-name" id="prev-npwp"></div>
                </label>
                <input type="file" id="reupload_npwp" name="npwp" accept=".jpg,.jpeg,.png,.pdf" style="display:none;" onchange="previewFile(this,'zone-npwp','prev-npwp')">
            </div>

            <div style="display:flex; gap:10px; margin-top:20px;">
                <button type="button" onclick="closeReuploadModal()" style="flex:1; background:#f1f5f9; border:none; color:#374151; font-weight:700; padding:12px; border-radius:10px; cursor:pointer; font-size:13px;">Batal</button>
                <button type="submit" id="btnSubmitReupload" style="flex:2; background:linear-gradient(135deg,#2563eb,#1d4ed8); border:none; color:#fff; font-weight:800; padding:12px; border-radius:10px; cursor:pointer; font-size:13.5px; display:flex; align-items:center; justify-content:center; gap:8px;">
                    <span id="submitSpinner" style="display:none; width:16px; height:16px; border:2px solid rgba(255,255,255,0.4); border-top-color:#fff; border-radius:50%; animation:spin .6s linear infinite;"></span>
                    📤 Kirim Dokumen
                </button>
            </div>
        </form>
    </div>
</div>
<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>
<script>
function openReuploadModal() {
    document.getElementById('modalReupload').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeReuploadModal() {
    document.getElementById('modalReupload').classList.remove('open');
    document.body.style.overflow = '';
}
document.getElementById('modalReupload').addEventListener('click', function(e) {
    if (e.target === this) closeReuploadModal();
});

function previewFile(input, zoneId, prevId) {
    const zone = document.getElementById(zoneId);
    const prev = document.getElementById(prevId);
    if (input.files && input.files[0]) {
        const f = input.files[0];
        zone.classList.add('has-file');
        prev.style.display = 'block';
        prev.textContent = '✓ ' + f.name + ' (' + (f.size/1024).toFixed(1) + ' KB)';
    } else {
        zone.classList.remove('has-file');
        prev.style.display = 'none';
        prev.textContent = '';
    }
}

document.getElementById('formReupload').addEventListener('submit', function(e) {
    e.preventDefault();

    // Cek minimal satu file dipilih
    const inputs = this.querySelectorAll('input[type=file]');
    let adaFile = false;
    inputs.forEach(inp => { if (inp.files && inp.files.length > 0) adaFile = true; });
    if (!adaFile) {
        showAlert('danger', '⚠️ Pilih minimal satu dokumen yang ingin diupload ulang.');
        return;
    }

    const btn = document.getElementById('btnSubmitReupload');
    const spinner = document.getElementById('submitSpinner');
    btn.disabled = true;
    spinner.style.display = 'inline-block';

    const fd = new FormData(this);
    fetch('reupload_dokumen.php', {
        method: 'POST',
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        spinner.style.display = 'none';
        if (data.ok) {
            showAlert('success', '✅ ' + data.pesan);
            // Reload halaman setelah 2 detik agar tracking terupdate
            setTimeout(() => { window.location.reload(); }, 2000);
        } else {
            showAlert('danger', '❌ ' + data.pesan);
        }
    })
    .catch(() => {
        btn.disabled = false;
        spinner.style.display = 'none';
        showAlert('danger', '❌ Gagal terhubung ke server. Coba lagi.');
    });
});

function showAlert(type, msg) {
    const el = document.getElementById('reupload-alert');
    el.style.display = 'block';
    if (type === 'success') {
        el.style.background = '#d1fae5';
        el.style.color = '#065f46';
        el.style.border = '1px solid #a7f3d0';
    } else {
        el.style.background = '#fee2e2';
        el.style.color = '#991b1b';
        el.style.border = '1px solid #fca5a5';
    }
    el.textContent = msg;
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
</script>
<?php endif; ?>
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
