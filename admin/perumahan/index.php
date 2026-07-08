<?php
// admin/perumahan/index.php
require_once '../../config/koneksi.php';
require_once '../../config/cek_admin.php';
require_once '../../config/functions.php';
require_once '../../includes/sidebar_admin.php';

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

// Proses Hapus
if ($action === 'hapus' && $id > 0) {
    try {
        $stmt = $db->prepare("DELETE FROM perumahan WHERE id_perumahan = ?");
        $stmt->execute([$id]);
        set_flash('sukses', 'Data komplek perumahan berhasil dihapus.');
    } catch (PDOException $e) {
        set_flash('gagal', 'Gagal menghapus komplek. Data ini mungkin masih digunakan oleh unit rumah.');
    }
    header('Location: index.php');
    exit;
}

$error = '';
// Proses Submit Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_perumahan = trim($_POST['nama_perumahan'] ?? '');
    $alamat         = trim($_POST['alamat'] ?? '');
    $deskripsi      = trim($_POST['deskripsi'] ?? '');
    $maps_link      = trim($_POST['maps_link'] ?? '');

    if (empty($nama_perumahan) || empty($alamat) || empty($deskripsi)) {
        $error = 'Nama komplek, alamat, dan deskripsi wajib diisi.';
    } else {
        if ($action === 'tambah') {
            $stmt = $db->prepare("INSERT INTO perumahan (nama_perumahan, alamat, deskripsi, maps_link) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nama_perumahan, $alamat, $deskripsi, $maps_link]);
            set_flash('sukses', 'Komplek perumahan berhasil ditambahkan.');
            header('Location: index.php');
            exit;
        } elseif ($action === 'edit' && $id > 0) {
            $stmt = $db->prepare("UPDATE perumahan SET nama_perumahan = ?, alamat = ?, deskripsi = ?, maps_link = ? WHERE id_perumahan = ?");
            $stmt->execute([$nama_perumahan, $alamat, $deskripsi, $maps_link, $id]);
            set_flash('sukses', 'Data komplek perumahan berhasil diperbarui.');
            header('Location: index.php');
            exit;
        }
    }
}

// Ambil data untuk Edit
$perumahan = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $db->prepare("SELECT * FROM perumahan WHERE id_perumahan = ?");
    $stmt->execute([$id]);
    $perumahan = $stmt->fetch();
    if (!$perumahan) {
        set_flash('gagal', 'Data komplek perumahan tidak ditemukan.');
        header('Location: index.php');
        exit;
    }
}

// Ambil daftar perumahan
$list_perumahan = $db->query("SELECT * FROM perumahan ORDER BY id_perumahan DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Komplek Perumahan - RumahKPR Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <?php sidebar_admin('perumahan'); ?>
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
                <a href="../dashboard.php">Dashboard</a> / <span>Komplek Perumahan</span>
            </div>
            <?php tampil_flash(); ?>

            <?php if ($action === 'tambah' || $action === 'edit'): ?>
                <div class="panel">
                    <div class="panel-header">
                        <h3><?= $action === 'tambah' ? '🏙️ Tambah Komplek Perumahan' : '✏️ Edit Komplek Perumahan' ?></h3>
                        <a href="index.php" class="btn btn-gray btn-sm">← Kembali</a>
                    </div>
                    <div class="panel-body">
                        <?php if ($error): ?><div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
                        <form method="POST">
                            <div class="form-group">
                                <label>Nama Komplek Perumahan</label>
                                <input type="text" name="nama_perumahan" class="form-control" placeholder="Contoh: Green Park Cluster" value="<?= htmlspecialchars($_POST['nama_perumahan'] ?? $perumahan['nama_perumahan'] ?? '') ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Alamat Lengkap</label>
                                <textarea name="alamat" class="form-control" placeholder="Tulis alamat komplek secara detail..." style="min-height:80px;" required><?= htmlspecialchars($_POST['alamat'] ?? $perumahan['alamat'] ?? '') ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Deskripsi Properti</label>
                                <textarea name="deskripsi" class="form-control" placeholder="Tuliskan deskripsi lengkap, keunggulan, fasilitas komplek..." style="min-height:120px;" required><?= htmlspecialchars($_POST['deskripsi'] ?? $perumahan['deskripsi'] ?? '') ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Link Google Maps (URL Share / Embed)</label>
                                <input type="url" name="maps_link" class="form-control" placeholder="https://maps.app.goo.gl/..." value="<?= htmlspecialchars($_POST['maps_link'] ?? $perumahan['maps_link'] ?? '') ?>">
                                <small class="form-hint">Kosongkan jika tidak ada link maps.</small>
                            </div>
                            <div style="margin-top:12px;">
                                <button type="submit" class="btn btn-primary">💾 Simpan Data</button>
                                <a href="index.php" class="btn btn-gray">Batal</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="page-header">
                    <div class="page-header-left">
                        <h2 class="gradient-title">🏙️ Komplek Perumahan</h2>
                        <p>Kelola data komplek perumahan, alamat, dan deskripsinya</p>
                    </div>
                    <a href="index.php?action=tambah" class="btn btn-primary">➕ Tambah Komplek</a>
                </div>

                <div class="panel">
                    <div class="panel-body" style="padding:0;">
                        <div class="tbl-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama Komplek</th>
                                        <th>Alamat</th>
                                        <th>Maps Link</th>
                                        <th style="width:180px; text-align:center;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($list_perumahan)): ?>
                                        <tr><td colspan="5" class="empty">Belum ada komplek perumahan terdaftar.</td></tr>
                                    <?php else: $no=1; foreach($list_perumahan as $p): ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td>
                                                <div style="font-size:15px; font-weight:700; color:var(--text);"><?= htmlspecialchars($p['nama_perumahan']) ?></div>
                                                
                                                <!-- List of houses inside this complex -->
                                                <div style="margin-top:12px; border-top:1px dashed var(--border); padding-top:10px;">
                                                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                                                        <span style="font-size:11px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:0.5px;">🏡 Unit Rumah Terdaftar:</span>
                                                    </div>
                                                    
                                                    <?php
                                                    // Fetch units for this perumahan dengan JOIN tipe_rumah untuk fallback foto
                                                    $units_stmt = $db->prepare("
                                                         SELECT r.*, t.foto as tipe_foto
                                                         FROM rumah r 
                                                         LEFT JOIN tipe_rumah t ON r.id_tipe = t.id_tipe
                                                         WHERE r.id_perumahan = ?
                                                         ORDER BY r.blok, r.kode_unit
                                                    ");
                                                    $units_stmt->execute([$p['id_perumahan']]);
                                                    $units = $units_stmt->fetchAll();
                                                    
                                                    if (empty($units)):
                                                    ?>
                                                        <span style="font-size:12px; color:var(--muted); font-style:italic;">Belum ada unit rumah terdaftar untuk komplek ini.</span>
                                                    <?php else: ?>
                                                        <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap:10px; margin-top:6px;">
                                                            <?php foreach($units as $u): ?>
                                                                <?php
                                                                // Fetch first photo from galeri_rumah if exists, otherwise fallback to unit foto / tipe foto
                                                                $foto_src = '';
                                                                $galeri_stmt = $db->prepare("SELECT foto FROM galeri_rumah WHERE id_rumah = ? ORDER BY id_galeri ASC LIMIT 1");
                                                                $galeri_stmt->execute([$u['id_rumah']]);
                                                                $gf = $galeri_stmt->fetchColumn();
                                                                if ($gf && file_exists('../../uploads/galeri_rumah/' . $gf)) {
                                                                    $foto_src = '../../uploads/galeri_rumah/' . $gf;
                                                                } elseif ($u['foto'] && file_exists('../../uploads/tipe_rumah/' . $u['foto'])) {
                                                                    $foto_src = '../../uploads/tipe_rumah/' . $u['foto'];
                                                                } elseif (!empty($u['tipe_foto']) && file_exists('../../uploads/tipe_rumah/' . $u['tipe_foto'])) {
                                                                    $foto_src = '../../uploads/tipe_rumah/' . $u['tipe_foto'];
                                                                }
                                                                ?>
                                                                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:8px; display:flex; gap:10px; align-items:center; position:relative; transition:var(--tr);">
                                                                    <!-- Foto Rumah -->
                                                                    <?php if($foto_src): ?>
                                                                        <img src="<?= $foto_src ?>" style="width:50px; height:50px; object-fit:cover; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,.1);">
                                                                    <?php else: ?>
                                                                        <div style="width:50px; height:50px; background:#e2e8f0; border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:18px;">🏠</div>
                                                                    <?php endif; ?>
                                                                    
                                                                    <!-- Detail Unit -->
                                                                    <div style="flex:1; min-width:0;">
                                                                        <div style="font-size:12.5px; font-weight:700; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                                                            Blok <?= htmlspecialchars($u['blok']) ?> - <?= htmlspecialchars($u['kode_unit']) ?>
                                                                        </div>
                                                                        <div style="font-size:11px; color:var(--muted);"><?= htmlspecialchars($u['nama_tipe']) ?></div>
                                                                        <div style="font-size:11.5px; font-weight:700; color:var(--success);"><?= format_rupiah($u['harga']) ?></div>
                                                                        
                                                                        <!-- Status Badge -->
                                                                        <?php 
                                                                        $status_bg = 'rgba(16,185,129,0.1)'; $status_col = '#10b981';
                                                                        if ($u['status'] === 'booking') { $status_bg = 'rgba(245,158,11,0.1)'; $status_col = '#f59e0b'; }
                                                                        elseif ($u['status'] === 'terjual') { $status_bg = 'rgba(239,68,68,0.1)'; $status_col = '#ef4444'; }
                                                                        ?>
                                                                        <span style="display:inline-block; font-size:9.5px; font-weight:700; padding:1px 6px; border-radius:4px; background:<?= $status_bg ?>; color:<?= $status_col ?>; margin-top:4px;">
                                                                            <?= ucfirst($u['status']) ?>
                                                                        </span>
                                                                    </div>
                                                                    
                                                                    <!-- Actions (Only Edit) -->
                                                                    <div style="display:flex; flex-direction:column; gap:4px; margin-left:auto;">
                                                                        <a href="../rumah/index.php?action=edit&id=<?= $u['id_rumah'] ?>&redirect=../perumahan/index.php" class="btn btn-warning" style="padding:4px 6px; font-size:10px; border-radius:4px; display:flex; align-items:center; justify-content:center; height:auto; width:auto; line-height:1;" title="Edit Unit">
                                                                            ✏️
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($p['alamat']) ?></td>
                                            <td>
                                                <?php if($p['maps_link']): ?>
                                                    <a href="<?= htmlspecialchars($p['maps_link']) ?>" target="_blank" class="btn btn-outline btn-sm" style="padding: 3px 8px; font-size: 11px;">📍 Lihat Maps</a>
                                                <?php else: ?>
                                                    <span style="color:var(--muted); font-size: 12px;">Tidak ada</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align:center;">
                                                <div class="aksi-table">
                                                <a href="index.php?action=edit&id=<?= $p['id_perumahan'] ?>" class="btn-edit">✏️ Edit</a>
                                                <a href="#" data-hapus="index.php?action=hapus&id=<?= $p['id_perumahan'] ?>" data-nama="<?= htmlspecialchars($p['nama_perumahan']) ?>" class="btn-delete">🗑️ Hapus</a>
                                                </div>
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
</body>
</html>
