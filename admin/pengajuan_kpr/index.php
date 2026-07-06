<?php
// admin/pengajuan_kpr/index.php
require_once '../../config/koneksi.php';
require_once '../../config/cek_admin.php';
require_once '../../config/functions.php';
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
                   ->execute([$id, '🤝 Pembayaran DP telah diverifikasi. Status berubah ke Akad Kredit. Jadwal cicilan akan segera dibuat.']);
                $db->commit();
                set_flash('sukses', '✅ DP diverifikasi VALID. Status KPR otomatis berubah ke Akad Kredit!');
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

        // Jika status ditolak, kembalikan status rumah menjadi tersedia & batalkan booking terkonfirmasi terkait
        if ($status_baru === 'ditolak' && $id_rumah) {
            $db->prepare("UPDATE rumah SET status = 'tersedia' WHERE id_rumah = ?")->execute([$id_rumah]);
            $db->prepare("UPDATE booking SET status_booking = 'dibatalkan' WHERE id_rumah = ? AND status_booking = 'dikonfirmasi'")->execute([$id_rumah]);
        }

        $db->commit();
        set_flash('sukses', 'Status pengajuan KPR berhasil diperbarui.');
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
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        .kpr-detail-grid { display: grid; grid-template-columns: 1fr 350px; gap: 24px; align-items: start; }
        .doc-link { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; text-decoration: none; color: #475569; font-weight: 600; font-size: 13px; transition: .2s; }
        .doc-link:hover { background: #e2e8f0; color: var(--primary); }
        .track-timeline { position: relative; padding-left: 20px; border-left: 2px solid #e2e8f0; margin-top: 14px; }
        .track-item { position: relative; margin-bottom: 16px; }
        .track-dot { position: absolute; left: -27px; top: 3px; width: 12px; height: 12px; border-radius: 50%; background: var(--primary); border: 2px solid #fff; box-shadow: 0 0 0 2px var(--primary); }
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
                        </div>

                        <!-- Dokumen Persyaratan -->
                        <div class="panel">
                            <div class="panel-header"><h3>📂 Dokumen Kelayakan KPR</h3></div>
                            <div class="panel-body">
                                <?php if (!$dokumen): ?>
                                    <p style="color:var(--muted);">Dokumen belum diunggah oleh pemohon.</p>
                                <?php else: ?>
                                    <div style="display:flex; gap:12px; flex-wrap:wrap;">
                                        <?php if ($dokumen['ktp']): ?>
                                            <a href="../../uploads/ktp/<?= htmlspecialchars($dokumen['ktp']) ?>" target="_blank" class="doc-link">🪪 Lihat KTP</a>
                                        <?php endif; ?>
                                        <?php if ($dokumen['kk']): ?>
                                            <a href="../../uploads/kk/<?= htmlspecialchars($dokumen['kk']) ?>" target="_blank" class="doc-link">👨‍👩‍👧‍👦 Lihat Kartu Keluarga</a>
                                        <?php endif; ?>
                                        <?php if ($dokumen['slip_gaji']): ?>
                                            <a href="../../uploads/slip_gaji/<?= htmlspecialchars($dokumen['slip_gaji']) ?>" target="_blank" class="doc-link">💵 Lihat Slip Gaji</a>
                                        <?php endif; ?>
                                        <?php if ($dokumen['npwp']): ?>
                                            <!-- NPWP disimpan di folder ktp di php pengajuan_kpr -->
                                            <a href="../../uploads/ktp/<?= htmlspecialchars($dokumen['npwp']) ?>" target="_blank" class="doc-link">📄 Lihat NPWP</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Catatan Admin & Tracking -->
                        <div class="panel">
                            <div class="panel-header"><h3>📜 Riwayat Tracking Status</h3></div>
                            <div class="panel-body">
                                <?php if (empty($tracking)): ?>
                                    <p style="color:var(--muted); text-align:center;">Belum ada riwayat update status.</p>
                                <?php else: ?>
                                    <div class="track-timeline">
                                        <?php foreach($tracking as $t): ?>
                                            <div class="track-item">
                                                <div class="track-dot"></div>
                                                <div style="font-size:11px; color:var(--muted);"><?= format_datetime($t['tanggal_update']) ?></div>
                                                <div style="margin:4px 0 6px;"><?= badge_kpr($t['status']) ?></div>
                                                <p style="font-size:13px; color:var(--sub); line-height:1.4;"><?= htmlspecialchars($t['keterangan']) ?></p>
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
                        <?php if ($kpr['status_pengajuan'] === 'disetujui' && $kpr['sertifikat']): ?>
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
