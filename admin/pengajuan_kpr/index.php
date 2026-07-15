<?php
// admin/pengajuan_kpr/index.php
require_once '../../config/koneksi.php';
require_once '../../config/cek_admin.php';
require_once '../../config/functions.php';
require_once '../../config/midtrans.php';
require_once '../../includes/sidebar_admin.php';

$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

// ── Upload Sertifikat oleh Admin ─────────────────────────────────────────────
if ($action === 'upload_sertifikat' && $id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_FILES['sertifikat']['name'])) {
        $dir = '../../uploads/sertifikat/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['sertifikat']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
            set_flash('gagal', 'Format tidak valid. Gunakan JPG/PNG/PDF.');
        } elseif ($_FILES['sertifikat']['size'] > 10 * 1024 * 1024) {
            set_flash('gagal', 'File sertifikat terlalu besar (maks 10MB).');
        } else {
            $fname = 'sertifikat_kpr_' . $id . '_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['sertifikat']['tmp_name'], $dir . $fname);
            $db->prepare("UPDATE pengajuan_kpr SET sertifikat=? WHERE id_pengajuan=?")->execute([$fname, $id]);
            // Catat di tracking
            $db->prepare("INSERT INTO tracking_pengajuan (id_pengajuan, status, keterangan, tanggal_update) VALUES (?, ?, ?, NOW())")
               ->execute([$id, 'disetujui', '📄 Sertifikat KPR telah diunggah oleh admin. Silakan lakukan pembayaran DP untuk melanjutkan ke Akad Kredit.']);
            set_flash('sukses', '✅ Sertifikat berhasil diupload. Customer akan melihat notifikasi untuk bayar DP.');
        }
    } else {
        set_flash('gagal', 'Pilih file sertifikat terlebih dahulu.');
    }
    header("Location: index.php?action=detail&id=$id");
    exit;
}

// ── Verifikasi Pembayaran DP oleh Admin ──────────────────────────────────────
if ($action === 'verif_dp' && $id > 0) {
    $id_dp = (int)($_GET['id_dp'] ?? 0);
    $aksi  = $_GET['aksi'] ?? '';
    if ($id_dp > 0 && in_array($aksi, ['valid', 'tolak'])) {
        $dp_data = $db->prepare("SELECT * FROM pembayaran_dp WHERE id_dp=? AND id_pengajuan=?");
        $dp_data->execute([$id_dp, $id]);
        $dp = $dp_data->fetch();
        if ($dp) {
            if ($aksi === 'valid') {
                $db->beginTransaction();
                $db->prepare("UPDATE pembayaran_dp SET status_verifikasi='valid' WHERE id_dp=?")->execute([$id_dp]);
                // Ubah status KPR ke akad_kredit
                $kpr_r = $db->prepare("SELECT id_rumah FROM pengajuan_kpr WHERE id_pengajuan=?");
                $kpr_r->execute([$id]);
                $id_rumah = $kpr_r->fetchColumn();
                $db->prepare("UPDATE pengajuan_kpr SET status_pengajuan='akad_kredit' WHERE id_pengajuan=?")->execute([$id]);
                if ($id_rumah) $db->prepare("UPDATE rumah SET status='terjual' WHERE id_rumah=?")->execute([$id_rumah]);
                $db->prepare("INSERT INTO tracking_pengajuan (id_pengajuan, status, keterangan, tanggal_update) VALUES (?, 'akad_kredit', ?, NOW())")
                   ->execute([$id, '🤝 Pembayaran DP telah diverifikasi. Status berubah ke Akad Kredit. Jadwal cicilan otomatis dibuat.']);

                // ── AUTO-GENERATE JADWAL CICILAN ──────────────────────────────
                $cek_cic2 = $db->prepare("SELECT COUNT(*) FROM cicilan_kpr WHERE id_pengajuan=?");
                $cek_cic2->execute([$id]);
                if ($cek_cic2->fetchColumn() == 0) {
                    $kpr_gen2 = $db->prepare("
                        SELECT pk.uang_muka, pk.tenor, b.bunga_kpr, r.harga
                        FROM pengajuan_kpr pk
                        JOIN bank b ON pk.id_bank = b.id_bank
                        JOIN rumah r ON pk.id_rumah = r.id_rumah
                        WHERE pk.id_pengajuan = ?
                    ");
                    $kpr_gen2->execute([$id]);
                    $kpr_g2 = $kpr_gen2->fetch();
                    if ($kpr_g2) {
                        $harga_g2        = (float)$kpr_g2['harga'];
                        $dp_g2           = (float)$dp['jumlah_dp'];
                        $pokok_pinjaman2 = max(0, $harga_g2 - $dp_g2);
                        $bunga_pa2       = (float)$kpr_g2['bunga_kpr'] / 100;
                        $tenor_bulan2    = (int)$kpr_g2['tenor'] * 12;
                        $bunga_pb2       = $bunga_pa2 / 12;

                        if ($pokok_pinjaman2 > 0 && $tenor_bulan2 > 0) {
                            $cicilan_bulanan2 = ($bunga_pb2 > 0)
                                ? $pokok_pinjaman2 * ($bunga_pb2 * pow(1 + $bunga_pb2, $tenor_bulan2)) / (pow(1 + $bunga_pb2, $tenor_bulan2) - 1)
                                : $pokok_pinjaman2 / $tenor_bulan2;

                            $saldo_pokok2 = $pokok_pinjaman2;
                            $tgl_mulai2   = new DateTime();
                            $tgl_mulai2->modify('+1 month');

                            for ($bulan2 = 1; $bulan2 <= $tenor_bulan2; $bulan2++) {
                                $bunga_bln2    = $saldo_pokok2 * $bunga_pb2;
                                $cicilan_pokok2= $cicilan_bulanan2 - $bunga_bln2;
                                $saldo_pokok2 -= $cicilan_pokok2;
                                if ($saldo_pokok2 < 0) $saldo_pokok2 = 0;
                                $tgl_jatuh2 = clone $tgl_mulai2;
                                $tgl_jatuh2->modify('+' . ($bulan2 - 1) . ' month');
                                $db->prepare("INSERT INTO cicilan_kpr (id_pengajuan, bulan_ke, tanggal_jatuh_tempo, jumlah_cicilan, pokok, bunga, status_bayar) VALUES (?, ?, ?, ?, ?, ?, 'belum')")
                                   ->execute([$id, $bulan2, $tgl_jatuh2->format('Y-m-d'), round($cicilan_bulanan2,2), round($cicilan_pokok2,2), round($bunga_bln2,2)]);
                            }
                        }
                    }
                }
                // ── END AUTO-GENERATE ─────────────────────────────────────────

                $db->commit();
                set_flash('sukses', '✅ DP diverifikasi VALID. Status KPR → Akad Kredit & jadwal cicilan otomatis dibuat!');
            } else {
                $db->prepare("UPDATE pembayaran_dp SET status_verifikasi='ditolak' WHERE id_dp=?")->execute([$id_dp]);
                $db->prepare("INSERT INTO tracking_pengajuan (id_pengajuan, status, keterangan, tanggal_update) VALUES (?, 'disetujui', ?, NOW())")
                   ->execute([$id, '❌ Bukti pembayaran DP ditolak. Customer diminta mengirim ulang bukti DP yang valid.']);
                set_flash('gagal', '❌ Pembayaran DP ditolak. Customer perlu kirim ulang bukti.');
            }
        }
    }
    header("Location: index.php?action=detail&id=$id");
    exit;
}

// Proses Update Status KPR
if ($action === 'update_status' && $id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $status_baru   = trim($_POST['status_pengajuan'] ?? '');
    $keterangan    = trim($_POST['keterangan'] ?? '');
    $catatan_admin = trim($_POST['catatan_admin'] ?? '');

    if (empty($status_baru) || empty($keterangan)) {
        set_flash('gagal', 'Status dan keterangan tracking wajib diisi.');
        header("Location: index.php?action=detail&id=$id");
        exit;
    }

    try {
        $db->beginTransaction();

        // Ambil data pengajuan & rumah
        $stmt = $db->prepare("SELECT id_rumah FROM pengajuan_kpr WHERE id_pengajuan = ?");
        $stmt->execute([$id]);
        $id_rumah = $stmt->fetchColumn();

        // Update status pengajuan & catatan admin
        $up = $db->prepare("UPDATE pengajuan_kpr SET status_pengajuan = ?, catatan_admin = ? WHERE id_pengajuan = ?");
        $up->execute([$status_baru, $catatan_admin, $id]);

        // Catat di tabel tracking
        $track = $db->prepare("INSERT INTO tracking_pengajuan (id_pengajuan, status, keterangan, tanggal_update) VALUES (?, ?, ?, NOW())");
        $track->execute([$id, $status_baru, $keterangan]);

        // Jika status ditolak, kembalikan status rumah & refund DP via Midtrans jika dibayar via gateway
        if ($status_baru === 'ditolak' && $id_rumah) {
            $db->prepare("UPDATE rumah SET status = 'tersedia' WHERE id_rumah = ?")->execute([$id_rumah]);
            $db->prepare("UPDATE booking SET status_booking = 'dibatalkan' WHERE id_rumah = ? AND status_booking = 'dikonfirmasi'")->execute([$id_rumah]);

            // ── AUTO REFUND: Jika DP dibayar via Midtrans gateway, proses pengembalian dana
            $dp_gateway = $db->prepare("
                SELECT id_dp, midtrans_order_id, jumlah_dp, refund_status, payment_method
                FROM pembayaran_dp
                WHERE id_pengajuan=? AND status_verifikasi='valid' AND payment_method='gateway'
                ORDER BY created_at DESC LIMIT 1
            ");
            $dp_gateway->execute([$id]);
            $dp_row = $dp_gateway->fetch();

            if ($dp_row && $dp_row['refund_status'] === 'none' && $dp_row['midtrans_order_id']) {
                // Panggil Midtrans Refund API
                $refund_result = midtrans_refund(
                    $dp_row['midtrans_order_id'],
                    $dp_row['jumlah_dp'],
                    'Pengajuan KPR dibatalkan/ditolak oleh admin.'
                );

                if ($refund_result['success']) {
                    $db->prepare("UPDATE pembayaran_dp SET refund_status='success', refund_id=? WHERE id_dp=?")
                       ->execute([$refund_result['refund_key'], $dp_row['id_dp']]);
                    $db->prepare("INSERT INTO tracking_pengajuan (id_pengajuan, status, keterangan, tanggal_update) VALUES (?, 'ditolak', ?, NOW())")
                       ->execute([$id, '💰 Refund Uang Muka (DP) sebesar ' . number_format($dp_row['jumlah_dp'], 0, ',', '.') . ' berhasil diproses via Midtrans. Dana akan kembali ke rekening customer dalam 1-3 hari kerja.']);
                } else {
                    // Refund gagal (mungkin sandbox) — tandai sebagai failed
                    $db->prepare("UPDATE pembayaran_dp SET refund_status='failed' WHERE id_dp=?")
                       ->execute([$dp_row['id_dp']]);
                    $db->prepare("INSERT INTO tracking_pengajuan (id_pengajuan, status, keterangan, tanggal_update) VALUES (?, 'ditolak', ?, NOW())")
                       ->execute([$id, '⚠️ Refund DP via Midtrans gagal diproses otomatis. Admin perlu memproses pengembalian dana secara manual ke customer.']);
                }
            }
        }

        $db->commit();
        set_flash('sukses', 'Status pengajuan KPR berhasil diperbarui.' . ($status_baru === 'ditolak' ? ' Proses refund DP (jika ada) sudah diajukan ke Midtrans.' : ''));
    } catch (PDOException $e) {
        $db->rollBack();
        set_flash('gagal', 'Terjadi kesalahan sistem: ' . $e->getMessage());
    }

    header("Location: index.php?action=detail&id=$id");
    exit;
}

// Ambil data Detail KPR
$kpr = null;
$dokumen = null;
$tracking = [];
if ($action === 'detail' && $id > 0) {
    // Ambil detail pengajuan
    $stmt = $db->prepare("SELECT pk.*, u.nama_lengkap, u.email, u.no_hp, p.nama_perumahan, r.blok, r.kode_unit, r.nama_tipe, r.harga, b.nama_bank, b.bunga_kpr
                          FROM pengajuan_kpr pk 
                          JOIN users u ON pk.id_user = u.id_user 
                          JOIN rumah r ON pk.id_rumah = r.id_rumah 
                          JOIN perumahan p ON r.id_perumahan = p.id_perumahan 
                          JOIN bank b ON pk.id_bank = b.id_bank 
                          WHERE pk.id_pengajuan = ?");
    $stmt->execute([$id]);
    $kpr = $stmt->fetch();

    if (!$kpr) {
        set_flash('gagal', 'Data pengajuan KPR tidak ditemukan.');
        header('Location: index.php');
        exit;
    }

    // Ambil dokumen persyaratan
    $stmt_dok = $db->prepare("SELECT * FROM dokumen_kpr WHERE id_pengajuan = ?");
    $stmt_dok->execute([$id]);
    $dokumen = $stmt_dok->fetch();

    // Ambil riwayat tracking
    $stmt_tr = $db->prepare("SELECT * FROM tracking_pengajuan WHERE id_pengajuan = ? ORDER BY tanggal_update DESC");
    $stmt_tr->execute([$id]);
    $tracking = $stmt_tr->fetchAll();

    // Ambil data DP (pembayaran_dp)
    $stmt_dp = $db->prepare("SELECT * FROM pembayaran_dp WHERE id_pengajuan=? ORDER BY created_at DESC LIMIT 1");
    $stmt_dp->execute([$id]);
    $dp_data = $stmt_dp->fetch();
}

// Ambil daftar pengajuan (untuk List)
$f_status = trim($_GET['f_status'] ?? '');

$query = "SELECT pk.*, u.nama_lengkap, p.nama_perumahan, r.blok, r.kode_unit, b.nama_bank 
          FROM pengajuan_kpr pk 
          JOIN users u ON pk.id_user = u.id_user 
          JOIN rumah r ON pk.id_rumah = r.id_rumah 
          JOIN perumahan p ON r.id_perumahan = p.id_perumahan 
          JOIN bank b ON pk.id_bank = b.id_bank 
          WHERE 1=1";
$params = [];

if (!empty($f_status)) {
    $query .= " AND pk.status_pengajuan = ?";
    $params[] = $f_status;
}
$query .= " ORDER BY pk.id_pengajuan DESC";

$stmt_list = $db->prepare($query);
$stmt_list->execute($params);
$list_kpr = $stmt_list->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengajuan KPR - RumahKPR Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css?v=3">
    <style>
        .kpr-detail-grid { display: grid; grid-template-columns: 1fr 360px; gap: 24px; align-items: start; }
        /* DOC LINK */
        .doc-link { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; text-decoration: none; color: #475569; font-weight: 600; font-size: 13px; transition: .2s; }
        .doc-link:hover { background: #e2e8f0; color: var(--primary); transform: translateY(-1px); }
        /* TRACK TIMELINE */
        .track-timeline { position: relative; padding-left: 22px; border-left: 2px solid #e2e8f0; margin-top: 14px; }
        .track-item { position: relative; margin-bottom: 20px; }
        .track-dot { position: absolute; left: -29px; top: 4px; width: 14px; height: 14px; border-radius: 50%; background: var(--primary); border: 2px solid #fff; box-shadow: 0 0 0 2px var(--primary); }
        .track-dot.latest { background: #22c55e; box-shadow: 0 0 0 3px #22c55e44; width: 16px; height: 16px; left: -30px; }
        /* STEP INDICATOR */
        .kpr-steps { display: flex; align-items: center; gap: 0; margin-bottom: 24px; background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px 20px; overflow-x: auto; }
        .kpr-step { display: flex; flex-direction: column; align-items: center; gap: 4px; flex: 1; min-width: 90px; position: relative; }
        .kpr-step:not(:last-child)::after { content: ''; position: absolute; right: -50%; top: 16px; width: 100%; height: 2px; background: #e2e8f0; z-index: 0; }
        .kpr-step.done:not(:last-child)::after { background: #22c55e; }
        .kpr-step.active:not(:last-child)::after { background: linear-gradient(90deg, #3b82f6, #e2e8f0); }
        .step-circle { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 15px; position: relative; z-index: 1; border: 2px solid #e2e8f0; background: #f8fafc; color: #94a3b8; font-weight: 700; }
        .kpr-step.done .step-circle { background: #22c55e; border-color: #22c55e; color: #fff; }
        .kpr-step.active .step-circle { background: linear-gradient(135deg,#3b82f6,#6366f1); border-color: #3b82f6; color: #fff; box-shadow: 0 4px 12px #3b82f640; }
        .kpr-step.rejected .step-circle { background: #ef4444; border-color: #ef4444; color: #fff; }
        .step-label { font-size: 11px; color: #94a3b8; font-weight: 600; text-align: center; }
        .kpr-step.done .step-label { color: #22c55e; }
        .kpr-step.active .step-label { color: #3b82f6; font-weight: 700; }
        .kpr-step.rejected .step-label { color: #ef4444; }
        /* DOC PREVIEW CARD */
        .doc-preview-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 14px; margin-top: 12px; }
        .doc-card { border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; background: #f8fafc; text-align: center; transition: .2s; }
        .doc-card:hover { border-color: var(--primary); transform: translateY(-2px); box-shadow: 0 4px 16px #3b82f622; }
        .doc-card-thumb { height: 100px; display: flex; align-items: center; justify-content: center; background: #f1f5f9; overflow: hidden; }
        .doc-card-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .doc-card-thumb .doc-icon { font-size: 40px; }
        .doc-card-label { padding: 8px 6px; font-size: 11.5px; font-weight: 700; color: #475569; }
        .doc-card a { display: block; text-decoration: none; color: inherit; }
        /* CONTEXT PANEL */
        .ctx-panel { background: linear-gradient(135deg,#f0f9ff,#e0f2fe); border: 1px solid #bae6fd; border-radius: 12px; padding: 16px 18px; margin-bottom: 16px; }
        .ctx-panel h4 { color: #0369a1; font-size: 14px; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
        .ctx-panel.warning { background: linear-gradient(135deg,#fffbeb,#fef3c7); border-color: #fde68a; }
        .ctx-panel.warning h4 { color: #92400e; }
        .ctx-panel.success { background: linear-gradient(135deg,#f0fdf4,#dcfce7); border-color: #86efac; }
        .ctx-panel.success h4 { color: #14532d; }
        @media(max-width: 1024px) { .kpr-detail-grid { grid-template-columns: 1fr !important; } }
    </style>
</head>
<body>
    <?php sidebar_admin('pengajuan_kpr'); ?>
    <div class="admin-main">
        <header class="topbar">
            <div style="display:flex;align-items:center;gap:12px;">
                <button class="btn btn-gray btn-sm" id="sidebarToggle" style="padding:6px 10px;">\u2630</button>
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
                <?php if ($action === 'detail'): ?>/ <span>Detail #<?= $id ?></span><?php endif; ?>
            </div>
            <?php tampil_flash(); ?>

            <?php if ($action === 'detail' && $kpr): ?>
                <!-- DETAIL VIEW -->
                <div class="page-header">
                    <div class="page-header-left">
                        <h2>📝 Detail Pengajuan KPR #<?= $kpr['id_pengajuan'] ?></h2>
                        <p>Daftar berkas, data keuangan pemohon, dan alur verifikasi KPR</p>
                    </div>
                    <a href="index.php" class="btn btn-gray">← Kembali</a>
                </div>

                <?php
                // Tentukan step berdasarkan status
                $all_steps = [
                    'pengajuan_masuk'    => ['label'=>'Pengajuan Masuk',   'icon'=>'📥'],
                    'verifikasi_dokumen' => ['label'=>'Verifikasi Dokumen','icon'=>'📋'],
                    'survey'             => ['label'=>'Survey & BI Cek',   'icon'=>'🔍'],
                    'disetujui'          => ['label'=>'Disetujui Bank',    'icon'=>'✅'],
                    'akad_kredit'        => ['label'=>'Akad Kredit',       'icon'=>'🤝'],
                ];
                $rejected = $kpr['status_pengajuan'] === 'ditolak';
                $step_order = array_keys($all_steps);
                $current_idx = array_search($kpr['status_pengajuan'], $step_order);
                ?>
                <!-- STEP INDICATOR -->
                <div class="kpr-steps">
                    <?php if ($rejected): ?>
                        <div style="display:flex;align-items:center;gap:10px;color:#ef4444;font-weight:700;"><span style="font-size:28px;">❌</span> Pengajuan ini telah <b>DITOLAK</b>. Status tidak bisa dilanjutkan.</div>
                    <?php else: foreach ($all_steps as $skey => $sval): $sidx = array_search($skey, $step_order); $scls = $sidx < $current_idx ? 'done' : ($sidx == $current_idx ? 'active' : ''); ?>
                        <div class="kpr-step <?= $scls ?>">
                            <div class="step-circle"><?= $sidx < $current_idx ? '✓' : $sval['icon'] ?></div>
                            <div class="step-label"><?= $sval['label'] ?></div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>

                <div class="kpr-detail-grid">
                    <div>
                        <!-- Info Ringkasan -->
                        <div class="panel">
                            <div class="panel-header"><h3>👤 Profil Pemohon & Unit</h3></div>
                            <div class="panel-body">
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                                    <div>
                                        <h4 style="margin-bottom:8px; color:var(--muted); font-size:12px; text-transform:uppercase;">Data Pemohon</h4>
                                        <p style="margin-bottom:4px;"><b><?= htmlspecialchars($kpr['nama_lengkap']) ?></b></p>
                                        <p style="margin-bottom:4px; font-size:13px; color:var(--sub);"><?= htmlspecialchars($kpr['email']) ?></p>
                                        <p style="font-size:13px; color:var(--sub);">📞 <?= htmlspecialchars($kpr['no_hp']) ?></p>
                                    </div>
                                    <div>
                                        <h4 style="margin-bottom:8px; color:var(--muted); font-size:12px; text-transform:uppercase;">Data Properti</h4>
                                        <p style="margin-bottom:4px;"><b><?= htmlspecialchars($kpr['nama_perumahan']) ?></b></p>
                                        <p style="margin-bottom:4px; font-size:13px; color:var(--primary); font-weight:700;">Blok <?= htmlspecialchars($kpr['blok'] . '-' . $kpr['kode_unit']) ?></p>
                                        <p style="font-size:13px; color:var(--success); font-weight:700;"><?= htmlspecialchars($kpr['nama_tipe']) ?> &bull; <?= format_rupiah($kpr['harga']) ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Data Keuangan -->
                        <div class="panel">
                            <div class="panel-header"><h3>💳 Data Pembiayaan & KPR</h3></div>
                            <div class="panel-body">
                                <?php $cicilan = hitung_cicilan($kpr['harga'], $kpr['uang_muka'], $kpr['bunga_kpr'], $kpr['tenor']); ?>
                                <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:16px; font-size:13.5px;" id="finance-details">
                                    <div>
                                        <span style="color:var(--muted); font-size:12px;">Bank Dipilih</span><br>
                                        <b style="font-size:15px; color:var(--text);"><?= htmlspecialchars($kpr['nama_bank']) ?></b><br>
                                        <small style="color:var(--muted); font-size:11px;">Bunga <?= $kpr['bunga_kpr'] ?>%</small>
                                    </div>
                                    <div>
                                        <span style="color:var(--muted); font-size:12px;">Penghasilan / bln</span><br>
                                        <b style="font-size:15px; color:var(--text);"><?= format_rupiah($kpr['penghasilan']) ?></b>
                                    </div>
                                    <div>
                                        <span style="color:var(--muted); font-size:12px;">Uang Muka (DP)</span><br>
                                        <b style="font-size:15px; color:var(--text);"><?= format_rupiah($kpr['uang_muka']) ?></b>
                                    </div>
                                    <div>
                                        <span style="color:var(--muted); font-size:12px;">Tenor & Est. Cicilan</span><br>
                                        <b style="font-size:15px; color:var(--success);"><?= format_rupiah($cicilan) ?>/bln</b><br>
                                        <small style="color:var(--muted); font-size:11px;"><?= $kpr['tenor'] ?> Tahun</small>
                                    </div>
                                </div>
                            </div>
                        </div>                        <!-- REKAPITULASI PEMBAYARAN PROPERTI (Booking + DP + Cicilan) -->
                        <?php if (in_array($kpr['status_pengajuan'], ['disetujui', 'akad_kredit'])): ?>
                        <?php
                        $q_booking = $db->prepare("SELECT booking_fee FROM booking WHERE id_user=? AND id_rumah=? ORDER BY id_booking DESC LIMIT 1");
                        $q_booking->execute([$kpr['id_user'], $kpr['id_rumah']]);
                        $booking_fee = (float)($q_booking->fetchColumn() ?: 0);

                        $q_dp = $db->prepare("SELECT jumlah_dp FROM pembayaran_dp WHERE id_pengajuan=? AND status_verifikasi='valid' LIMIT 1");
                        $q_dp->execute([$id]);
                        $dp_paid = (float)($q_dp->fetchColumn() ?: 0);

                        $q_cicilan = $db->prepare("SELECT COALESCE(SUM(jumlah_cicilan),0) FROM cicilan_kpr WHERE id_pengajuan=? AND status_bayar='lunas'");
                        $q_cicilan->execute([$id]);
                        $cicilan_paid = (float)($q_cicilan->fetchColumn() ?: 0);

                        $total_paid = $booking_fee + $dp_paid + $cicilan_paid;
                        $harga_rumah = (float)$kpr['harga'];
                        $sisa_harga_rumah = max(0, $harga_rumah - $total_paid);

                        $q_cicilan_unpaid = $db->prepare("SELECT COALESCE(SUM(jumlah_cicilan),0) FROM cicilan_kpr WHERE id_pengajuan=? AND status_bayar='belum'");
                        $q_cicilan_unpaid->execute([$id]);
                        $cicilan_unpaid = (float)($q_cicilan_unpaid->fetchColumn() ?: 0);
                        
                        $persen_bayar = $harga_rumah > 0 ? round($total_paid / $harga_rumah * 100) : 0;
                        ?>
                        <div class="panel" style="border: 2px solid #3b82f6;">
                            <div class="panel-header" style="background: linear-gradient(135deg, #1e3a8a, #3b82f6); color: #fff;">
                                <h3>📊 Rekapitulasi Pembayaran Keuangan Properti</h3>
                            </div>
                            <div class="panel-body">
                                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-bottom:16px;">
                                    <div style="background:#f8fafc; padding:10px; border-radius:6px; border:1px solid #e2e8f0;">
                                        <span style="font-size:11px; color:var(--muted); display:block;">Harga Rumah</span>
                                        <b style="font-size:14px; color:#1e293b;"><?= format_rupiah($harga_rumah) ?></b>
                                    </div>
                                    <div style="background:#f8fafc; padding:10px; border-radius:6px; border:1px solid #e2e8f0;">
                                        <span style="font-size:11px; color:var(--muted); display:block;">Booking Fee</span>
                                        <b style="font-size:14px; color:var(--success);"><?= format_rupiah($booking_fee) ?></b>
                                    </div>
                                    <div style="background:#f8fafc; padding:10px; border-radius:6px; border:1px solid #e2e8f0;">
                                        <span style="font-size:11px; color:var(--muted); display:block;">Uang Muka (DP) Valid</span>
                                        <b style="font-size:14px; color:#d97706;"><?= format_rupiah($dp_paid) ?></b>
                                    </div>
                                    <div style="background:#f8fafc; padding:10px; border-radius:6px; border:1px solid #e2e8f0;">
                                        <span style="font-size:11px; color:var(--muted); display:block;">Cicilan Bulanan Lunas</span>
                                        <b style="font-size:14px; color:#3b82f6;"><?= format_rupiah($cicilan_paid) ?></b>
                                    </div>
                                </div>

                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; background:#eff6ff; padding:12px; border-radius:8px; border:1px solid #bfdbfe; margin-bottom:14px;">
                                    <div>
                                        <span style="font-size:11px; color:#1e40af; font-weight:bold; display:block;">TOTAL SUDAH DIBAYAR</span>
                                        <b style="font-size:16px; color:#1e3a8a;"><?= format_rupiah($total_paid) ?> (<?= $persen_bayar ?>%)</b>
                                    </div>
                                    <div>
                                        <span style="font-size:11px; color:#ef4444; font-weight:bold; display:block;">SISA HARGA RUMAH</span>
                                        <b style="font-size:16px; color:#ef4444;"><?= format_rupiah($sisa_harga_rumah) ?></b>
                                    </div>
                                </div>

                                <?php if ($cicilan_unpaid > 0): ?>
                                <div style="font-size:12px; color:var(--muted);">
                                    ⏳ Estimasi sisa cicilan berjalan (KPR + Bunga): <b><?= format_rupiah($cicilan_unpaid) ?></b>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- PANEL KONTEKSTUAL PER STATUS -->
                        <?php
                        // Helper: render doc card (image preview or icon)
                        function doc_card($label, $icon, $path, $href) {
                            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                            $is_img = in_array($ext, ['jpg','jpeg','png','gif','webp']);
                            echo '<div class="doc-card">';
                            echo '<a href="'.$href.'" target="_blank">';
                            echo '<div class="doc-card-thumb">';
                            if ($is_img) {
                                echo '<img src="'.$href.'" alt="'.$label.'" loading="lazy">';
                            } else {
                                echo '<span class="doc-icon">'.$icon.'</span>';
                            }
                            echo '</div>';
                            echo '<div class="doc-card-label">'.$label.'</div>';
                            echo '</a></div>';
                        }
                        ?>

                        <!-- STATUS: PENGAJUAN MASUK -->
                        <?php if (in_array($kpr['status_pengajuan'], ['pengajuan_masuk'])): ?>
                        <div class="panel">
                            <div class="panel-header" style="background:linear-gradient(135deg,#1e3a8a,#3b82f6);color:#fff;"><h3>📥 Pengajuan Masuk — Data Awal Customer</h3></div>
                            <div class="panel-body">
                                <div class="ctx-panel">
                                    <h4>ℹ️ Yang Perlu Diperiksa Sekarang</h4>
                                    <ul style="margin:0;padding-left:18px;font-size:13px;color:#0369a1;line-height:1.8;">
                                        <li>Periksa kelengkapan data pemohon (nama, email, no. HP)</li>
                                        <li>Pastikan data properti dan bank KPR sesuai</li>
                                        <li>Cek penghasilan vs estimasi cicilan (rasio max 30-40%)</li>
                                        <li>Jika data valid, ubah status ke <b>Verifikasi Dokumen</b></li>
                                    </ul>
                                </div>
                                <?php if (!$dokumen): ?>
                                    <div class="ctx-panel warning"><h4>⚠️ Belum Ada Dokumen</h4><p style="margin:0;font-size:13px;color:#92400e;">Customer belum mengunggah dokumen persyaratan KPR.</p></div>
                                <?php else: ?>
                                <p style="font-size:13px;color:var(--muted);margin-bottom:10px;">Dokumen yang telah diunggah customer:</p>
                                <div class="doc-preview-grid">
                                    <?php if ($dokumen['ktp']) doc_card('KTP','🪪',$dokumen['ktp'],'../../uploads/ktp/'.htmlspecialchars($dokumen['ktp'])); ?>
                                    <?php if ($dokumen['kk'])  doc_card('Kartu Keluarga','👨‍👩‍👧‍👦',$dokumen['kk'],'../../uploads/kk/'.htmlspecialchars($dokumen['kk'])); ?>
                                    <?php if ($dokumen['slip_gaji']) doc_card('Slip Gaji','💵',$dokumen['slip_gaji'],'../../uploads/slip_gaji/'.htmlspecialchars($dokumen['slip_gaji'])); ?>
                                    <?php if ($dokumen['npwp']) doc_card('NPWP','📄',$dokumen['npwp'],'../../uploads/ktp/'.htmlspecialchars($dokumen['npwp'])); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- STATUS: VERIFIKASI DOKUMEN -->
                        <?php if (in_array($kpr['status_pengajuan'], ['verifikasi_dokumen'])): ?>
                        <div class="panel" style="border:2px solid #3b82f6;">
                            <div class="panel-header" style="background:linear-gradient(135deg,#1d4ed8,#3b82f6);color:#fff;"><h3>📋 Verifikasi Dokumen — Berkas yang Diupload</h3></div>
                            <div class="panel-body">
                                <div class="ctx-panel">
                                    <h4>📌 Panduan Verifikasi Berkas</h4>
                                    <ul style="margin:0;padding-left:18px;font-size:13px;color:#0369a1;line-height:1.8;">
                                        <li><b>KTP</b>: Pastikan foto jelas, nama sesuai pengajuan, belum kadaluarsa</li>
                                        <li><b>KK</b>: Pastikan nama pemohon tercantum di KK</li>
                                        <li><b>Slip Gaji</b>: Periksa penghasilan konsisten dengan yang diinput</li>
                                        <li><b>NPWP</b>: Wajib jika penghasilan ≥ Rp 4,5 juta/bulan</li>
                                        <li>Jika semua valid, ubah status ke <b>Survey & BI Cek</b></li>
                                    </ul>
                                </div>
                                <?php if (!$dokumen): ?>
                                    <div class="ctx-panel warning"><h4>⚠️ Dokumen Belum Diunggah</h4><p style="margin:0;font-size:13px;color:#92400e;">Customer belum mengunggah dokumen. Hubungi customer untuk segera mengupload berkas persyaratan.</p></div>
                                <?php else: ?>
                                <p style="font-size:13px;font-weight:600;margin-bottom:10px;">Klik gambar/ikon untuk membuka dokumen fullscreen:</p>
                                <div class="doc-preview-grid">
                                    <?php if ($dokumen['ktp']) doc_card('KTP Pemohon','🪪',$dokumen['ktp'],'../../uploads/ktp/'.htmlspecialchars($dokumen['ktp'])); ?>
                                    <?php if ($dokumen['kk'])  doc_card('Kartu Keluarga','👨‍👩‍👧‍👦',$dokumen['kk'],'../../uploads/kk/'.htmlspecialchars($dokumen['kk'])); ?>
                                    <?php if ($dokumen['slip_gaji']) doc_card('Slip Gaji','💵',$dokumen['slip_gaji'],'../../uploads/slip_gaji/'.htmlspecialchars($dokumen['slip_gaji'])); ?>
                                    <?php if ($dokumen['npwp']) doc_card('NPWP','📄',$dokumen['npwp'],'../../uploads/ktp/'.htmlspecialchars($dokumen['npwp'])); ?>
                                </div>
                                <?php $total_dok = (int)(!empty($dokumen['ktp'])) + (int)(!empty($dokumen['kk'])) + (int)(!empty($dokumen['slip_gaji'])); ?>
                                <div style="margin-top:14px;padding:10px 14px;border-radius:8px;background:<?= $total_dok >= 3 ? '#f0fdf4;border:1px solid #86efac' : '#fffbeb;border:1px solid #fde68a' ?>;font-size:13px;">
                                    <?= $total_dok >= 3 ? '✅ <b style="color:#15803d;">Dokumen lengkap</b> ('.($dokumen['npwp']?'4':'3').' berkas). Siap untuk proses selanjutnya.' : '⚠️ <b style="color:#92400e;">Dokumen belum lengkap</b> ('.$total_dok.' dari 3 dokumen wajib).' ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- STATUS: SURVEY -->
                        <?php if (in_array($kpr['status_pengajuan'], ['survey'])): ?>
                        <div class="panel" style="border:2px solid #8b5cf6;">
                            <div class="panel-header" style="background:linear-gradient(135deg,#6d28d9,#8b5cf6);color:#fff;"><h3>🔍 Survey Lokasi & BI Checking</h3></div>
                            <div class="panel-body">
                                <div class="ctx-panel" style="background:linear-gradient(135deg,#faf5ff,#ede9fe);border-color:#c4b5fd;">
                                    <h4 style="color:#6d28d9;">📌 Tahap Survey & BI Checking</h4>
                                    <ul style="margin:0;padding-left:18px;font-size:13px;color:#6d28d9;line-height:1.8;">
                                        <li><b>Survey Lokasi</b>: Verifikasi fisik unit rumah, kondisi bangunan, dan legalitas tanah</li>
                                        <li><b>BI/SLIK Checking</b>: Cek riwayat kredit nasabah (Kol 1 = lancar = layak KPR)</li>
                                        <li>Jika lolos survey & BI bersih, ubah ke <b>Disetujui Bank</b></li>
                                        <li>Jika gagal, gunakan status <b>Ditolak</b> dengan keterangan jelas</li>
                                    </ul>
                                </div>
                                <p style="font-size:13px;font-weight:600;margin-bottom:8px;">📂 Dokumen yang diserahkan customer:</p>
                                <?php if (!$dokumen): ?>
                                    <div class="ctx-panel warning"><h4>⚠️ Dokumen Belum Ada</h4></div>
                                <?php else: ?>
                                <div class="doc-preview-grid">
                                    <?php if ($dokumen['ktp']) doc_card('KTP','🪪',$dokumen['ktp'],'../../uploads/ktp/'.htmlspecialchars($dokumen['ktp'])); ?>
                                    <?php if ($dokumen['kk'])  doc_card('Kartu Keluarga','👨‍👩‍👧‍👦',$dokumen['kk'],'../../uploads/kk/'.htmlspecialchars($dokumen['kk'])); ?>
                                    <?php if ($dokumen['slip_gaji']) doc_card('Slip Gaji','💵',$dokumen['slip_gaji'],'../../uploads/slip_gaji/'.htmlspecialchars($dokumen['slip_gaji'])); ?>
                                    <?php if ($dokumen['npwp']) doc_card('NPWP','📄',$dokumen['npwp'],'../../uploads/ktp/'.htmlspecialchars($dokumen['npwp'])); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- STATUS: DISETUJUI / AKAD / DITOLAK -->
                        <?php if (in_array($kpr['status_pengajuan'], ['disetujui','akad_kredit','ditolak'])): ?>
                        <div class="panel">
                            <div class="panel-header"><h3>📂 Dokumen Persyaratan KPR</h3></div>
                            <div class="panel-body">
                                <?php if (!$dokumen): ?>
                                    <p style="color:var(--muted);">Dokumen belum diunggah.</p>
                                <?php else: ?>
                                <div class="doc-preview-grid">
                                    <?php if ($dokumen['ktp']) doc_card('KTP Pemohon','🪪',$dokumen['ktp'],'../../uploads/ktp/'.htmlspecialchars($dokumen['ktp'])); ?>
                                    <?php if ($dokumen['kk'])  doc_card('Kartu Keluarga','👨‍👩‍👧‍👦',$dokumen['kk'],'../../uploads/kk/'.htmlspecialchars($dokumen['kk'])); ?>
                                    <?php if ($dokumen['slip_gaji']) doc_card('Slip Gaji','💵',$dokumen['slip_gaji'],'../../uploads/slip_gaji/'.htmlspecialchars($dokumen['slip_gaji'])); ?>
                                    <?php if ($dokumen['npwp']) doc_card('NPWP','📄',$dokumen['npwp'],'../../uploads/ktp/'.htmlspecialchars($dokumen['npwp'])); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Riwayat Tracking Status (enhanced) -->
                        <div class="panel">
                            <div class="panel-header"><h3>📜 Riwayat Alur Proses KPR</h3></div>
                            <div class="panel-body">
                                <?php if (empty($tracking)): ?>
                                    <p style="color:var(--muted); text-align:center;">Belum ada riwayat update status.</p>
                                <?php else: ?>
                                    <div class="track-timeline">
                                        <?php foreach($tracking as $ti => $t): ?>
                                            <div class="track-item">
                                                <div class="track-dot <?= $ti === 0 ? 'latest' : '' ?>"></div>
                                                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                                                    <span style="font-size:11px; color:var(--muted);"><?= format_datetime($t['tanggal_update']) ?></span>
                                                    <?php if ($ti === 0): ?><span style="font-size:10px;background:#22c55e;color:#fff;padding:1px 7px;border-radius:20px;font-weight:700;">TERKINI</span><?php endif; ?>
                                                </div>
                                                <div style="margin:4px 0 6px;"><?= badge_kpr($t['status']) ?></div>
                                                <p style="font-size:13px; color:var(--sub); line-height:1.5; background:#f8fafc; padding:8px 10px; border-radius:6px; border-left:3px solid #3b82f6; margin:0;"><?= htmlspecialchars($t['keterangan']) ?></p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Panel Samping: Tindakan Admin -->
                    <div>
                        <!-- PANEL: Upload Sertifikat (jika status disetujui) -->
                        <?php if (in_array($kpr['status_pengajuan'], ['disetujui', 'akad_kredit'])): ?>
                        <div class="panel" style="margin-bottom:18px;">
                            <div class="panel-header">
                                <h3 style="background:linear-gradient(135deg,#059669,#0891b2);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">📄 Sertifikat KPR</h3>
                            </div>
                            <div class="panel-body">
                                <?php if ($kpr['sertifikat']): ?>
                                    <div style="background:#d1fae5;border:1px solid #6ee7b7;border-radius:10px;padding:12px 14px;margin-bottom:14px;display:flex;align-items:center;gap:10px;">
                                        <span style="font-size:24px;">✅</span>
                                        <div>
                                            <div style="font-weight:800;color:#065f46;font-size:13px;">Sertifikat sudah diupload</div>
                                            <a href="../../uploads/sertifikat/<?= htmlspecialchars($kpr['sertifikat']) ?>" target="_blank" class="btn btn-outline btn-sm" style="margin-top:6px;font-size:11px;">📄 Lihat Sertifikat</a>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div style="background:#fef3c7;border:1px solid #fbbf24;border-radius:10px;padding:12px 14px;margin-bottom:14px;">
                                        <div style="font-weight:700;color:#92400e;font-size:13px;margin-bottom:4px;">⏳ Belum ada sertifikat</div>
                                        <div style="font-size:12px;color:#b45309;">Upload sertifikat agar customer bisa bayar DP</div>
                                    </div>
                                <?php endif; ?>
                                <form method="POST" action="index.php?action=upload_sertifikat&id=<?= $kpr['id_pengajuan'] ?>" enctype="multipart/form-data">
                                    <div class="form-group" style="margin-bottom:10px;">
                                        <label>Upload / Ganti Sertifikat</label>
                                        <input type="file" name="sertifikat" class="form-control" accept=".jpg,.jpeg,.png,.pdf" required>
                                        <small class="form-hint">JPG/PNG/PDF, Maks 10MB</small>
                                    </div>
                                    <button type="submit" class="btn btn-success" style="width:100%;justify-content:center;">📤 Upload Sertifikat</button>
                                </form>
                            </div>
                        </div>

                        <!-- PANEL: Verifikasi Pembayaran DP -->
                        <?php if ($kpr['status_pengajuan'] === 'disetujui'): ?>
                        <div class="panel" style="margin-bottom:18px;<?= (!$dp_data) ? 'opacity:.6;' : '' ?>">
                            <div class="panel-header">
                                <h3 style="background:linear-gradient(135deg,#d97706,#ea580c);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">💰 Verifikasi Pembayaran DP</h3>
                            </div>
                            <div class="panel-body">
                                <?php if (!$dp_data): ?>
                                    <div style="text-align:center;padding:16px;color:#94a3b8;">
                                        <div style="font-size:32px;margin-bottom:8px;">⏳</div>
                                        <div style="font-size:13px;">Menunggu customer mengirim bukti pembayaran DP</div>
                                    </div>
                                <?php elseif ($dp_data['status_verifikasi'] === 'valid'): ?>
                                    <div style="background:#d1fae5;border:1px solid #6ee7b7;border-radius:10px;padding:14px;text-align:center;">
                                        <div style="font-size:28px;">✅</div>
                                        <div style="font-weight:800;color:#065f46;margin-top:6px;">DP Sudah Diverifikasi</div>
                                        <div style="font-size:13px;color:#059669;margin-top:4px;"><?= format_rupiah($dp_data['jumlah_dp']) ?></div>
                                    </div>
                                <?php else: ?>
                                    <div style="background:linear-gradient(135deg,#fffbeb,#fef3c7);border:1px solid #fbbf24;border-radius:10px;padding:14px;margin-bottom:14px;">
                                        <div style="font-weight:700;color:#92400e;font-size:13px;margin-bottom:4px;">⏳ Bukti DP Masuk</div>
                                        <div style="font-size:14px;font-weight:800;color:#d97706;"><?= format_rupiah($dp_data['jumlah_dp']) ?></div>
                                        <div style="font-size:12px;color:#b45309;margin-top:4px;">Dikirim: <?= format_datetime($dp_data['tanggal_bayar']) ?></div>
                                        <?php if ($dp_data['bukti_dp']): ?>
                                        <a href="../../uploads/bukti_dp/<?= htmlspecialchars($dp_data['bukti_dp']) ?>" target="_blank" class="btn btn-outline btn-sm" style="margin-top:8px;font-size:11px;width:100%;justify-content:center;">📎 Lihat Bukti DP</a>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display:flex;flex-direction:column;gap:8px;">
                                        <a href="index.php?action=verif_dp&id=<?= $kpr['id_pengajuan'] ?>&id_dp=<?= $dp_data['id_dp'] ?>&aksi=valid"
                                           class="btn btn-success" style="width:100%;justify-content:center;"
                                           onclick="return confirm('Konfirmasi DP VALID?\n\nIni akan otomatis mengubah status KPR ke AKAD KREDIT!')">
                                            ✅ Valid → Konfirmasi Akad Kredit
                                        </a>
                                        <a href="index.php?action=verif_dp&id=<?= $kpr['id_pengajuan'] ?>&id_dp=<?= $dp_data['id_dp'] ?>&aksi=tolak"
                                           class="btn-delete" style="width:100%;justify-content:center;text-align:center;"
                                           onclick="return confirm('Tolak pembayaran DP ini?')">
                                            ❌ Tolak DP
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>

                        <div class="panel" style="position:sticky; top:80px;">
                            <div class="panel-header"><h3>🔄 Update Status Manual</h3></div>
                            <div class="panel-body">
                                <div style="margin-bottom:18px; text-align:center;">
                                    <span style="font-size:12px; color:var(--muted);">Status Saat Ini:</span><br>
                                    <div style="margin-top:6px;"><?= badge_kpr($kpr['status_pengajuan']) ?></div>
                                </div>

                                <form method="POST" action="index.php?action=update_status&id=<?= $kpr['id_pengajuan'] ?>">
                                    <div class="form-group">
                                        <label>Ubah Status KPR</label>
                                        <select name="status_pengajuan" class="form-control" required>
                                            <option value="pengajuan_masuk" <?= $kpr['status_pengajuan'] === 'pengajuan_masuk' ? 'selected' : '' ?>>📥 Pengajuan Masuk</option>
                                            <option value="verifikasi_dokumen" <?= $kpr['status_pengajuan'] === 'verifikasi_dokumen' ? 'selected' : '' ?>>📋 Verifikasi Dokumen</option>
                                            <option value="survey" <?= $kpr['status_pengajuan'] === 'survey' ? 'selected' : '' ?>>🔍 Survey Lokasi & BI Cek</option>
                                            <option value="disetujui" <?= $kpr['status_pengajuan'] === 'disetujui' ? 'selected' : '' ?>>✅ Disetujui Bank</option>
                                            <option value="ditolak" <?= $kpr['status_pengajuan'] === 'ditolak' ? 'selected' : '' ?>>❌ Ditolak</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Keterangan Alur (Riwayat)</label>
                                        <textarea name="keterangan" class="form-control" placeholder="Contoh: Berkas KTP dan KK valid, masuk tahap verifikasi slip gaji." style="min-height:70px;" required></textarea>
                                        <small class="form-hint">Keterangan ini akan terlihat oleh customer di pelacak status KPR.</small>
                                    </div>
                                    <div class="form-group">
                                        <label>Catatan Admin (Internal/Memo)</label>
                                        <textarea name="catatan_admin" class="form-control" placeholder="Memo internal admin..." style="min-height:60px;"><?= htmlspecialchars($kpr['catatan_admin'] ?? '') ?></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-block" style="width:100%; justify-content:center;">💾 Perbarui Status</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- LIST VIEW -->
                <div class="page-header">
                    <div class="page-header-left">
                        <h2>📝 Pengajuan KPR</h2>
                        <p>Kelola verifikasi kelayakan KPR, tracking proses, survey, hingga akad kredit perumahan</p>
                    </div>
                </div>

                <!-- FILTER BAR -->
                <div class="panel" style="margin-bottom: 18px;">
                    <div class="panel-body" style="padding: 16px 20px;">
                        <form method="GET" action="" class="search-bar" style="margin-bottom:0;">
                            <select name="f_status" style="max-width:250px;">
                                <option value="">-- Semua Status Pengajuan --</option>
                                <option value="pengajuan_masuk" <?= $f_status === 'pengajuan_masuk' ? 'selected' : '' ?>>Pengajuan Masuk</option>
                                <option value="verifikasi_dokumen" <?= $f_status === 'verifikasi_dokumen' ? 'selected' : '' ?>>Verifikasi Dokumen</option>
                                <option value="survey" <?= $f_status === 'survey' ? 'selected' : '' ?>>Survey</option>
                                <option value="disetujui" <?= $f_status === 'disetujui' ? 'selected' : '' ?>>Disetujui</option>
                                <option value="akad_kredit" <?= $f_status === 'akad_kredit' ? 'selected' : '' ?>>Akad Kredit</option>
                                <option value="ditolak" <?= $f_status === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">🔍 Filter</button>
                            <?php if (!empty($f_status)): ?>
                                <a href="index.php" class="btn btn-gray btn-sm">Reset</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-body" style="padding:0;">
                        <div class="tbl-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Customer</th>
                                        <th>Detail Rumah</th>
                                        <th>Mitra Bank</th>
                                        <th>Tanggal Masuk</th>
                                        <th>Status KPR</th>
                                        <th style="width:140px; text-align:center;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($list_kpr)): ?>
                                        <tr><td colspan="7" class="empty">Tidak ada pengajuan KPR yang masuk.</td></tr>
                                    <?php else: $no=1; foreach($list_kpr as $k): ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><b><?= htmlspecialchars($k['nama_lengkap']) ?></b></td>
                                            <td>
                                                <b><?= htmlspecialchars($k['nama_perumahan']) ?></b><br>
                                                <small style="color:var(--primary); font-weight:700;">Blok <?= htmlspecialchars($k['blok'].'-'.$k['kode_unit']) ?></small>
                                            </td>
                                            <td><b><?= htmlspecialchars($k['nama_bank']) ?></b></td>
                                            <td><?= format_tanggal($k['tanggal_pengajuan']) ?></td>
                                            <td><?= badge_kpr($k['status_pengajuan']) ?></td>
                                            <td style="text-align:center;">
                                                <a href="index.php?action=detail&id=<?= $k['id_pengajuan'] ?>" class="btn btn-primary btn-sm">👁️ Detail / Proses</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <script src="../../assets/js/script.js"></script>
    <style>
        @media(max-width: 768px) {
            #finance-details { grid-template-columns: 1fr 1fr !important; gap: 12px !important; }
        }
    </style>
</body>
</html>
