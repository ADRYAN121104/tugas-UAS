<?php
// admin/rumah/index.php
require_once '../../config/koneksi.php';
require_once '../../config/cek_admin.php';
require_once '../../config/functions.php';
require_once '../../includes/sidebar_admin.php';

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? 'index.php';

// Proses Hapus - Dibatasi
if ($action === 'hapus' && $id > 0) {
    set_flash('gagal', 'Fitur menghapus unit dinonaktifkan sementara.');
    header('Location: ' . $redirect);
    exit;
}

// Proses Tambah - Dibatasi
if ($action === 'tambah') {
    set_flash('gagal', 'Fitur menambah unit baru dinonaktifkan sementara.');
    header('Location: index.php');
    exit;
}

// Proses Hapus Foto Galeri
if ($action === 'hapus_foto' && $id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM galeri_rumah WHERE id_galeri = ?");
        $stmt->execute([$id]);
        $g = $stmt->fetch();
        if ($g) {
            $id_rumah = $g['id_rumah'];
            $file_path = '../../uploads/galeri_rumah/' . $g['foto'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
            $db->prepare("DELETE FROM galeri_rumah WHERE id_galeri = ?")->execute([$id]);
            set_flash('sukses', 'Foto galeri berhasil dihapus.');
            header("Location: index.php?action=edit&id=" . $id_rumah);
            exit;
        }
    } catch (PDOException $e) {
        set_flash('gagal', 'Gagal menghapus foto galeri.');
    }
    header('Location: index.php');
    exit;
}

$error = '';
// Proses Submit Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_perumahan   = (int)($_POST['id_perumahan'] ?? 0);
    $id_tipe        = 0; // Deprecated, we store directly in rumah now
    $kode_unit      = trim($_POST['kode_unit'] ?? '');
    $blok           = trim($_POST['blok'] ?? '');
    $status         = trim($_POST['status'] ?? 'tersedia');
    
    $nama_tipe      = trim($_POST['nama_tipe'] ?? '');
    $luas_tanah     = (int)($_POST['luas_tanah'] ?? 0);
    $luas_bangunan  = (int)($_POST['luas_bangunan'] ?? 0);
    $jumlah_kamar   = (int)($_POST['jumlah_kamar'] ?? 0);
    $jumlah_kamar_mandi = (int)($_POST['jumlah_kamar_mandi'] ?? 0);
    $harga          = (float)str_replace(['.', 'Rp', ' '], '', $_POST['harga'] ?? '0');
    $deskripsi      = trim($_POST['deskripsi'] ?? '');

    if (!$id_perumahan || empty($kode_unit) || empty($blok) || empty($nama_tipe) || $harga <= 0) {
        $error = 'Nama tipe, Blok, Nomor Unit, dan Harga wajib diisi.';
    } else {
        // Ambil data foto lama jika edit
        $foto_baru = '';
        if ($action === 'edit' && $id > 0) {
            $old_stmt = $db->prepare("SELECT foto FROM rumah WHERE id_rumah = ?");
            $old_stmt->execute([$id]);
            $foto_baru = $old_stmt->fetchColumn();
        }

        // Handle Upload Foto Utama
        if (!empty($_FILES['foto']['name'])) {
            $upload = upload_file($_FILES['foto'], '../../uploads/tipe_rumah');
            if ($upload['ok']) {
                if ($action === 'edit' && $foto_baru && file_exists('../../uploads/tipe_rumah/' . $foto_baru)) {
                    unlink('../../uploads/tipe_rumah/' . $foto_baru);
                }
                $foto_baru = $upload['nama'];
            } else {
                $error = 'Gagal upload foto utama: ' . $upload['pesan'];
            }
        }

        if (empty($error)) {
            if ($action === 'tambah') {
                $stmt = $db->prepare("INSERT INTO rumah (id_perumahan, id_tipe, kode_unit, blok, status, nama_tipe, luas_tanah, luas_bangunan, jumlah_kamar, jumlah_kamar_mandi, harga, deskripsi, foto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$id_perumahan, $id_tipe, $kode_unit, $blok, $status, $nama_tipe, $luas_tanah, $luas_bangunan, $jumlah_kamar, $jumlah_kamar_mandi, $harga, $deskripsi, $foto_baru]);
                $id_rumah = $db->lastInsertId();

                // Upload foto galeri jika ada
                if (!empty($_FILES['foto_galeri']['name'][0])) {
                    $files = $_FILES['foto_galeri'];
                    $total_files = count($files['name']);
                    for ($i = 0; $i < $total_files; $i++) {
                        if ($files['error'][$i] === UPLOAD_ERR_OK) {
                            $file_data = [
                                'name' => $files['name'][$i],
                                'type' => $files['type'][$i],
                                'tmp_name' => $files['tmp_name'][$i],
                                'error' => $files['error'][$i],
                                'size' => $files['size'][$i]
                            ];
                            $upload = upload_file($file_data, '../../uploads/galeri_rumah');
                            if ($upload['ok']) {
                                $db->prepare("INSERT INTO galeri_rumah (id_rumah, foto) VALUES (?, ?)")->execute([$id_rumah, $upload['nama']]);
                            }
                        }
                    }
                }

                set_flash('sukses', 'Unit rumah berhasil ditambahkan.');
                header('Location: ' . $redirect);
                exit;
            } elseif ($action === 'edit' && $id > 0) {
                $stmt = $db->prepare("UPDATE rumah SET id_perumahan = ?, id_tipe = ?, kode_unit = ?, blok = ?, status = ?, nama_tipe = ?, luas_tanah = ?, luas_bangunan = ?, jumlah_kamar = ?, jumlah_kamar_mandi = ?, harga = ?, deskripsi = ?, foto = ? WHERE id_rumah = ?");
                $stmt->execute([$id_perumahan, $id_tipe, $kode_unit, $blok, $status, $nama_tipe, $luas_tanah, $luas_bangunan, $jumlah_kamar, $jumlah_kamar_mandi, $harga, $deskripsi, $foto_baru, $id]);

                // Upload foto galeri jika ada
                if (!empty($_FILES['foto_galeri']['name'][0])) {
                    $files = $_FILES['foto_galeri'];
                    $total_files = count($files['name']);
                    for ($i = 0; $i < $total_files; $i++) {
                        if ($files['error'][$i] === UPLOAD_ERR_OK) {
                            $file_data = [
                                'name' => $files['name'][$i],
                                'type' => $files['type'][$i],
                                'tmp_name' => $files['tmp_name'][$i],
                                'error' => $files['error'][$i],
                                'size' => $files['size'][$i]
                            ];
                            $upload = upload_file($file_data, '../../uploads/galeri_rumah');
                            if ($upload['ok']) {
                                $db->prepare("INSERT INTO galeri_rumah (id_rumah, foto) VALUES (?, ?)")->execute([$id, $upload['nama']]);
                            }
                        }
                    }
                }

                set_flash('sukses', 'Data unit rumah berhasil diperbarui.');
                header('Location: ' . $redirect);
                exit;
            }
        }
    }
}

// Ambil data untuk Edit
$rumah = null;
$galeri = [];
if ($action === 'edit' && $id > 0) {
    $stmt = $db->prepare("SELECT * FROM rumah WHERE id_rumah = ?");
    $stmt->execute([$id]);
    $rumah = $stmt->fetch();
    if (!$rumah) {
        set_flash('gagal', 'Data unit rumah tidak ditemukan.');
        header('Location: index.php');
        exit;
    }
    // Ambil galeri foto
    $stmt_g = $db->prepare("SELECT * FROM galeri_rumah WHERE id_rumah = ?");
    $stmt_g->execute([$id]);
    $galeri = $stmt_g->fetchAll();
}

// Ambil data dropdown
$list_perumahan = $db->query("SELECT id_perumahan, nama_perumahan FROM perumahan ORDER BY nama_perumahan ASC")->fetchAll();
$list_tipe      = $db->query("SELECT id_tipe, nama_tipe, harga FROM tipe_rumah ORDER BY nama_tipe ASC")->fetchAll();

// Filter Pencarian
$f_perumahan = (int)($_GET['f_perumahan'] ?? 0);
$f_status    = trim($_GET['f_status'] ?? '');

$query = "SELECT r.*, p.nama_perumahan 
          FROM rumah r 
          JOIN perumahan p ON r.id_perumahan = p.id_perumahan 
          WHERE 1=1";
$params = [];

if ($f_perumahan > 0) {
    $query .= " AND r.id_perumahan = ?";
    $params[] = $f_perumahan;
}
if (!empty($f_status)) {
    $query .= " AND r.status = ?";
    $params[] = $f_status;
}
$query .= " ORDER BY p.nama_perumahan ASC, r.blok ASC, r.kode_unit ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$list_rumah = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Unit Rumah - RumahKPR Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <?php sidebar_admin('rumah'); ?>
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
                <a href="../dashboard.php">Dashboard</a> / <span>Unit Rumah</span>
            </div>
            <?php tampil_flash(); ?>

            <?php if ($action === 'tambah' || $action === 'edit'): ?>
                <?php 
                $back_url = $_GET['redirect'] ?? $_POST['redirect'] ?? 'index.php';
                $prefilled_perumahan = (int)($_GET['id_perumahan'] ?? 0);
                ?>
                <div class="panel">
                    <div class="panel-header">
                        <h3><?= $action === 'tambah' ? '🚪 Tambah Unit Rumah' : '✏️ Edit Unit Rumah' ?></h3>
                        <a href="<?= htmlspecialchars($back_url) ?>" class="btn btn-gray btn-sm">← Kembali</a>
                    </div>
                    <div class="panel-body">
                        <?php if ($error): ?><div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="redirect" value="<?= htmlspecialchars($back_url) ?>">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Komplek Perumahan</label>
                                    <select name="id_perumahan" class="form-control" required>
                                        <option value="">-- Pilih Komplek --</option>
                                        <?php foreach($list_perumahan as $p): ?>
                                            <option value="<?= $p['id_perumahan'] ?>" <?= (isset($rumah['id_perumahan']) && $rumah['id_perumahan'] == $p['id_perumahan']) || (isset($_POST['id_perumahan']) && $_POST['id_perumahan'] == $p['id_perumahan']) || ($prefilled_perumahan == $p['id_perumahan']) ? 'selected' : '' ?>><?= htmlspecialchars($p['nama_perumahan']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Nama Model / Tipe Unit Rumah</label>
                                    <input type="text" name="nama_tipe" class="form-control" placeholder="Contoh: Tipe 36 Rose" value="<?= htmlspecialchars($_POST['nama_tipe'] ?? $rumah['nama_tipe'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="form-row-3">
                                <div class="form-group">
                                    <label>Blok</label>
                                    <input type="text" name="blok" class="form-control" placeholder="Contoh: A, B, C" value="<?= htmlspecialchars($_POST['blok'] ?? $rumah['blok'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Nomor / Kode Unit</label>
                                    <input type="text" name="kode_unit" class="form-control" placeholder="Contoh: 12" value="<?= htmlspecialchars($_POST['kode_unit'] ?? $rumah['kode_unit'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Status Ketersediaan</label>
                                    <select name="status" class="form-control" required>
                                        <option value="tersedia" <?= (isset($rumah['status']) && $rumah['status'] === 'tersedia') ? 'selected' : '' ?>>Tersedia</option>
                                        <option value="booking" <?= (isset($rumah['status']) && $rumah['status'] === 'booking') ? 'selected' : '' ?>>Booking</option>
                                        <option value="terjual" <?= (isset($rumah['status']) && $rumah['status'] === 'terjual') ? 'selected' : '' ?>>Terjual</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row-3">
                                <div class="form-group">
                                    <label>Luas Tanah (m²)</label>
                                    <input type="number" name="luas_tanah" class="form-control" placeholder="72" value="<?= htmlspecialchars($_POST['luas_tanah'] ?? $rumah['luas_tanah'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Luas Bangunan (m²)</label>
                                    <input type="number" name="luas_bangunan" class="form-control" placeholder="36" value="<?= htmlspecialchars($_POST['luas_bangunan'] ?? $rumah['luas_bangunan'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Harga Jual (Rp)</label>
                                    <input type="text" name="harga" class="form-control format-angka" placeholder="350.000.000" value="<?= htmlspecialchars(isset($_POST['harga']) ? $_POST['harga'] : (isset($rumah['harga']) ? number_format($rumah['harga'], 0, ',', '.') : '')) ?>" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Jumlah Kamar Tidur</label>
                                    <input type="number" name="jumlah_kamar" class="form-control" placeholder="2" value="<?= htmlspecialchars($_POST['jumlah_kamar'] ?? $rumah['jumlah_kamar'] ?? '2') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Jumlah Kamar Mandi</label>
                                    <input type="number" name="jumlah_kamar_mandi" class="form-control" placeholder="1" value="<?= htmlspecialchars($_POST['jumlah_kamar_mandi'] ?? $rumah['jumlah_kamar_mandi'] ?? '1') ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Deskripsi / Spesifikasi Lengkap Unit</label>
                                <textarea name="deskripsi" class="form-control" placeholder="Tuliskan spesifikasi teknis unit..." style="min-height:100px;"><?= htmlspecialchars($_POST['deskripsi'] ?? $rumah['deskripsi'] ?? '') ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Foto Utama / Sampul Unit Rumah</label>
                                <input type="file" name="foto" class="form-control" accept=".jpg,.jpeg,.png" data-preview="imgPrev">
                                <small class="form-hint">Kosongkan jika tidak ingin mengubah foto utama. Format: JPG/PNG, Max 5MB</small>
                                <div style="margin-top:12px;">
                                    <?php 
                                    $foto_src = '';
                                    $display = 'none';
                                    if (isset($rumah['foto']) && $rumah['foto'] && file_exists('../../uploads/tipe_rumah/' . $rumah['foto'])) {
                                        $foto_src = '../../uploads/tipe_rumah/' . $rumah['foto'];
                                        $display = 'block';
                                    }
                                    ?>
                                    <img id="imgPrev" src="<?= $foto_src ?>" style="max-height:200px; border-radius:10px; display:<?= $display ?>; box-shadow:var(--shadow);">
                                </div>
                            </div>
                            <div class="form-group" style="margin-top:14px;">
                                <label>Upload Foto Galeri (Bisa pilih beberapa gambar sekaligus)</label>
                                <input type="file" name="foto_galeri[]" multiple class="form-control" accept="image/*">
                                <small class="form-hint">Format yang didukung: JPG, PNG, JPEG. Ukuran maks 5MB per file.</small>
                            </div>
                            <?php if ($action === 'edit' && !empty($galeri)): ?>
                                <div class="form-group" style="margin-top:20px;">
                                    <label>Daftar Foto Galeri Saat Ini (Klik ✕ untuk menghapus)</label>
                                    <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:10px;">
                                        <?php foreach ($galeri as $g): ?>
                                            <div style="position:relative; width:120px; height:120px; border:2px solid var(--border); border-radius:10px; overflow:hidden; box-shadow: var(--shadow);">
                                                <img src="../../uploads/galeri_rumah/<?= htmlspecialchars($g['foto']) ?>" style="width:100%; height:100%; object-fit:cover;">
                                                <a href="index.php?action=hapus_foto&id=<?= $g['id_galeri'] ?>" 
                                                   onclick="return confirm('Apakah Anda yakin ingin menghapus foto ini dari galeri?')"
                                                   style="position:absolute; top:6px; right:6px; background:rgba(239,68,68,0.9); color:#fff; border-radius:50%; width:22px; height:22px; display:flex; align-items:center; justify-content:center; text-decoration:none; font-size:11px; font-weight:800; border:1px solid #fff; transition: var(--tr);"
                                                   onmouseover="this.style.background='#ef4444'; this.style.transform='scale(1.1)';"
                                                   onmouseout="this.style.background='rgba(239,68,68,0.9)'; this.style.transform='scale(1)';">
                                                    ✕
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div style="margin-top:20px; padding-top:14px; border-top:1px solid var(--border);">
                                <button type="submit" class="btn btn-primary">💾 Simpan Data</button>
                                <a href="index.php" class="btn btn-gray">Batal</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="page-header">
                    <div class="page-header-left">
                        <h2 class="gradient-title">🚪 Unit Rumah</h2>
                        <p>Kelola blok, kode unit, tipe, komplek perumahan, dan status penjualan</p>
                    </div>
                </div>

                <!-- FILTER BAR -->
                <div class="panel" style="margin-bottom: 18px;">
                    <div class="panel-body" style="padding: 16px 20px;">
                        <form method="GET" action="" class="search-bar" style="margin-bottom:0;">
                            <select name="f_perumahan">
                                <option value="0">-- Semua Komplek Perumahan --</option>
                                <?php foreach($list_perumahan as $p): ?>
                                    <option value="<?= $p['id_perumahan'] ?>" <?= $f_perumahan == $p['id_perumahan'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nama_perumahan']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="f_status">
                                <option value="">-- Semua Status --</option>
                                <option value="tersedia" <?= $f_status === 'tersedia' ? 'selected' : '' ?>>Tersedia</option>
                                <option value="booking" <?= $f_status === 'booking' ? 'selected' : '' ?>>Booking</option>
                                <option value="terjual" <?= $f_status === 'terjual' ? 'selected' : '' ?>>Terjual</option>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">🔍 Filter</button>
                            <?php if ($f_perumahan > 0 || !empty($f_status)): ?>
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
                                        <th>Komplek Perumahan</th>
                                        <th>Blok / Unit</th>
                                        <th>Tipe Rumah</th>
                                        <th>Harga Tipe</th>
                                        <th>Status</th>
                                        <th style="width:180px; text-align:center;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($list_rumah)): ?>
                                        <tr><td colspan="7" class="empty">Tidak ada unit rumah terdaftar yang sesuai.</td></tr>
                                    <?php else: $no=1; foreach($list_rumah as $r): ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><b><?= htmlspecialchars($r['nama_perumahan']) ?></b></td>
                                            <td><span style="font-weight: 700; color: var(--primary);">Blok <?= htmlspecialchars($r['blok'] . '-' . $r['kode_unit']) ?></span></td>
                                            <td><?= htmlspecialchars($r['nama_tipe']) ?></td>
                                            <td style="font-weight: 700;"><?= format_rupiah($r['harga']) ?></td>
                                            <td><?= badge_unit($r['status']) ?></td>
                                            <td style="text-align:center;">
                                                <a href="index.php?action=edit&id=<?= $r['id_rumah'] ?>" class="btn-edit">✏️ Edit Unit</a>
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
