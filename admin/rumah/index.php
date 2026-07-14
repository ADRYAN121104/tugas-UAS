<?php
// admin/rumah/index.php
require_once '../../config/koneksi.php';
require_once '../../config/cek_admin.php';
require_once '../../config/functions.php';
require_once '../../includes/sidebar_admin.php';

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? 'index.php';

// Proses Hapus Unit Rumah (beserta semua foto dan galeri)
if ($action === 'hapus' && $id > 0) {
    try {
        // Ambil data unit dan foto profil
        $stmt_r = $db->prepare("SELECT * FROM rumah WHERE id_rumah = ?");
        $stmt_r->execute([$id]);
        $unit = $stmt_r->fetch();
        if ($unit) {
            // Hapus foto profil/sampul dari server
            if ($unit['foto'] && file_exists('../../uploads/tipe_rumah/' . $unit['foto'])) {
                unlink('../../uploads/tipe_rumah/' . $unit['foto']);
            }
            // Hapus semua foto galeri dari server
            $galeri_list = $db->prepare("SELECT foto FROM galeri_rumah WHERE id_rumah = ?");
            $galeri_list->execute([$id]);
            foreach ($galeri_list->fetchAll() as $g) {
                $path = '../../uploads/galeri_rumah/' . $g['foto'];
                if (file_exists($path)) unlink($path);
            }
            // Hapus data galeri dari database
            $db->prepare("DELETE FROM galeri_rumah WHERE id_rumah = ?")->execute([$id]);
            // Hapus data unit dari database
            $db->prepare("DELETE FROM rumah WHERE id_rumah = ?")->execute([$id]);
            set_flash('sukses', 'Unit rumah Blok ' . $unit['blok'] . '-' . $unit['kode_unit'] . ' berhasil dihapus.');
        } else {
            set_flash('gagal', 'Unit tidak ditemukan.');
        }
    } catch (PDOException $e) {
        set_flash('gagal', 'Gagal menghapus unit: ' . $e->getMessage());
    }
    header('Location: index.php');
    exit;
}

// Handle AJAX gallery upload (quick add photo without re-submitting full form)
if ($action === 'upload_galeri_ajax' && $id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!empty($_FILES['foto_galeri']['name'][0])) {
        $files = $_FILES['foto_galeri'];
        $total = count($files['name']);
        
        $set_as_cover = isset($_POST['set_as_cover']) && $_POST['set_as_cover'] === '1';
        
        $uploaded = 0;
        $errors = [];
        for ($i = 0; $i < $total; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $file_data = [
                    'name'     => $files['name'][$i],
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i]
                ];
                
                if ($i === 0 && $set_as_cover) {
                    // Upload ke folder tipe_rumah karena ini dijadikan foto sampul/profil
                    $upload = upload_file($file_data, '../../uploads/tipe_rumah');
                    if ($upload['ok']) {
                        // Hapus foto sampul lama jika ada
                        $old_stmt = $db->prepare("SELECT foto FROM rumah WHERE id_rumah = ?");
                        $old_stmt->execute([$id]);
                        $old_foto = $old_stmt->fetchColumn();
                        if ($old_foto && file_exists('../../uploads/tipe_rumah/' . $old_foto)) {
                            unlink('../../uploads/tipe_rumah/' . $old_foto);
                        }
                        
                        $db->prepare("UPDATE rumah SET foto = ? WHERE id_rumah = ?")->execute([$upload['nama'], $id]);
                        $uploaded++;
                    } else {
                        $errors[] = $upload['pesan'];
                    }
                } else {
                    // Masuk ke galeri
                    $upload = upload_file($file_data, '../../uploads/galeri_rumah');
                    if ($upload['ok']) {
                        $db->prepare("INSERT INTO galeri_rumah (id_rumah, foto) VALUES (?, ?)")->execute([$id, $upload['nama']]);
                        $uploaded++;
                    } else {
                        $errors[] = $upload['pesan'];
                    }
                }
            }
        }
        echo json_encode(['ok' => $uploaded > 0, 'uploaded' => $uploaded, 'errors' => $errors]);
    } else {
        echo json_encode(['ok' => false, 'uploaded' => 0, 'errors' => ['Tidak ada file dipilih.']]);
    }
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
    $id_tipe        = null; // Deprecated, we store directly in rumah now
    if ($action === 'edit' && $id > 0) {
        $stmt_old_tipe = $db->prepare("SELECT id_tipe FROM rumah WHERE id_rumah = ?");
        $stmt_old_tipe->execute([$id]);
        $old_id_tipe = $stmt_old_tipe->fetchColumn();
        if ($old_id_tipe > 0) {
            $id_tipe = $old_id_tipe;
        }
    }
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

        // Proses Upload Foto Profil Rumah (Single File)
        if (!empty($_FILES['foto_profil']['name'])) {
            $file_data = [
                'name'     => $_FILES['foto_profil']['name'],
                'type'     => $_FILES['foto_profil']['type'],
                'tmp_name' => $_FILES['foto_profil']['tmp_name'],
                'error'    => $_FILES['foto_profil']['error'],
                'size'     => $_FILES['foto_profil']['size']
            ];
            if ($file_data['error'] === UPLOAD_ERR_OK) {
                $upload = upload_file($file_data, '../../uploads/tipe_rumah');
                if ($upload['ok']) {
                    // Hapus foto profil lama jika ada
                    if ($action === 'edit' && $foto_baru && file_exists('../../uploads/tipe_rumah/' . $foto_baru)) {
                        unlink('../../uploads/tipe_rumah/' . $foto_baru);
                    }
                    $foto_baru = $upload['nama'];
                } else {
                    $error = 'Gagal upload foto profil: ' . $upload['pesan'];
                }
            }
        }

        // Proses Upload Foto Galeri Rumah (Multiple Files)
        $uploaded_galeri = [];
        if (empty($error) && !empty($_FILES['foto_galeri']['name'][0])) {
            $files = $_FILES['foto_galeri'];
            $total_files = count($files['name']);
            for ($i = 0; $i < $total_files; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file_data = [
                        'name'     => $files['name'][$i],
                        'type'     => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error'    => $files['error'][$i],
                        'size'     => $files['size'][$i]
                    ];
                    $upload = upload_file($file_data, '../../uploads/galeri_rumah');
                    if ($upload['ok']) {
                        $uploaded_galeri[] = $upload['nama'];
                    } else {
                        $error = 'Gagal upload file galeri ' . htmlspecialchars($files['name'][$i]) . ': ' . $upload['pesan'];
                        break;
                    }
                }
            }
        }

        if (empty($error)) {
            if ($action === 'tambah') {
                $stmt = $db->prepare("INSERT INTO rumah (id_perumahan, id_tipe, kode_unit, blok, status, nama_tipe, luas_tanah, luas_bangunan, jumlah_kamar, jumlah_kamar_mandi, harga, deskripsi, foto) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$id_perumahan, $id_tipe, $kode_unit, $blok, $status, $nama_tipe, $luas_tanah, $luas_bangunan, $jumlah_kamar, $jumlah_kamar_mandi, $harga, $deskripsi, $foto_baru]);
                $id_rumah = $db->lastInsertId();

                // Simpan foto galeri
                if (!empty($uploaded_galeri)) {
                    foreach ($uploaded_galeri as $fn) {
                        $db->prepare("INSERT INTO galeri_rumah (id_rumah, foto) VALUES (?, ?)")->execute([$id_rumah, $fn]);
                    }
                }

                set_flash('sukses', 'Unit rumah berhasil ditambahkan.');
                header('Location: ' . $redirect);
                exit;
            } elseif ($action === 'edit' && $id > 0) {
                $stmt = $db->prepare("UPDATE rumah SET id_perumahan = ?, id_tipe = ?, kode_unit = ?, blok = ?, status = ?, nama_tipe = ?, luas_tanah = ?, luas_bangunan = ?, jumlah_kamar = ?, jumlah_kamar_mandi = ?, harga = ?, deskripsi = ?, foto = ? WHERE id_rumah = ?");
                $stmt->execute([$id_perumahan, $id_tipe, $kode_unit, $blok, $status, $nama_tipe, $luas_tanah, $luas_bangunan, $jumlah_kamar, $jumlah_kamar_mandi, $harga, $deskripsi, $foto_baru, $id]);

                // Simpan foto galeri
                if (!empty($uploaded_galeri)) {
                    foreach ($uploaded_galeri as $fn) {
                        $db->prepare("INSERT INTO galeri_rumah (id_rumah, foto) VALUES (?, ?)")->execute([$id, $fn]);
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

$query = "SELECT r.*, p.nama_perumahan, t.foto as tipe_foto 
          FROM rumah r 
          JOIN perumahan p ON r.id_perumahan = p.id_perumahan 
          LEFT JOIN tipe_rumah t ON r.id_tipe = t.id_tipe
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
    <link rel="stylesheet" href="../../assets/css/admin.css?v=3">
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

                            <!-- BAGIAN ATAS: PROFIL RUMAH (1 FOTO SAMPUL) -->
                            <div style="background:#eef4ff; border:2px solid #3b82f6; border-radius:14px; padding:22px; margin-top:24px; margin-bottom:20px;">
                                <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                                    <span style="background:#3b82f6; color:#fff; font-size:12px; font-weight:800; padding:3px 10px; border-radius:20px;">FOTO PROFIL</span>
                                    <label style="font-weight:800; color:#1e40af; font-size:15px; margin:0;">Foto Profil Rumah</label>
                                </div>
                                <p style="font-size:12.5px; color:#64748b; margin:0 0 14px; line-height:1.5;">
                                    Foto ini akan tampil pertama di halaman <strong>katalog produk</strong>. Pilih 1 foto terbaik sebagai foto utama unit.
                                </p>
                                <input type="file" name="foto_profil" accept="image/*" 
                                    onchange="
                                        var f=this.files[0]; if(!f) return;
                                        var r=new FileReader(); 
                                        r.onload=function(e){ 
                                            document.getElementById('imgPrev').src=e.target.result;
                                            document.getElementById('imgPrev').style.display='block';
                                            var t=document.getElementById('noImgText'); if(t) t.style.display='none';
                                        }; r.readAsDataURL(f);"
                                    style="display:block; width:100%; padding:8px; border:1px solid #93c5fd; border-radius:8px; background:#fff; font-size:13px; cursor:pointer; margin-bottom:14px;">
                                
                                <?php 
                                $foto_src = '';
                                $display = 'none';
                                if (isset($rumah['foto']) && $rumah['foto'] && file_exists('../../uploads/tipe_rumah/' . $rumah['foto'])) {
                                    $foto_src = '../../uploads/tipe_rumah/' . $rumah['foto'];
                                    $display = 'block';
                                }
                                ?>
                                <div style="margin-top:4px;">
                                    <label style="font-size:12px; color:#64748b; font-weight:600; display:block; margin-bottom:8px;">Foto Profil Saat Ini:</label>
                                    <img id="imgPrev" src="<?= $foto_src ?>" style="max-height:220px; max-width:100%; border-radius:10px; display:<?= $display ?>; border:2px solid #93c5fd; box-shadow:0 2px 8px rgba(59,130,246,.15);">
                                    <?php if ($display === 'none'): ?>
                                        <div id="noImgText" style="padding:20px; background:#fff; border:1px dashed #93c5fd; border-radius:10px; text-align:center; color:#94a3b8; font-size:13px;">
                                            📷 Belum ada foto profil — pilih file di atas
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- BAGIAN BAWAH: FOTO DETAIL RUMAH (GALERI BANYAK) -->
                            <div style="background:#f0fdf4; border:2px solid #10b981; border-radius:14px; padding:22px; margin-top:0; margin-bottom:20px;">
                                <div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">
                                    <span style="background:#10b981; color:#fff; font-size:12px; font-weight:800; padding:3px 10px; border-radius:20px;">FOTO DETAIL</span>
                                    <label style="font-weight:800; color:#065f46; font-size:15px; margin:0;">Foto Detail Rumah</label>
                                </div>
                                <p style="font-size:12.5px; color:#475569; margin:0 0 14px; line-height:1.5;">
                                    Foto-foto ini tampil di halaman <strong>detail unit</strong>. Upload beberapa foto sekaligus (kamar tidur, dapur, toilet, tampak samping, dll).
                                </p>
                                <input type="file" name="foto_galeri[]" multiple accept="image/*"
                                    style="display:block; width:100%; padding:8px; border:1px solid #6ee7b7; border-radius:8px; background:#fff; font-size:13px; cursor:pointer; margin-bottom:14px;">
                                
                                <?php if ($action === 'edit' && !empty($galeri)): ?>
                                    <div style="margin-top:4px;">
                                        <label style="font-size:12px; color:#64748b; font-weight:600; display:block; margin-bottom:10px;">Foto Detail Saat Ini (klik ✕ untuk hapus):</label>
                                        <div style="display:flex; gap:12px; flex-wrap:wrap;">
                                            <?php foreach ($galeri as $g): ?>
                                                <div style="position:relative; width:130px; height:130px; border-radius:10px; overflow:hidden; border:2px solid #6ee7b7; box-shadow:0 2px 6px rgba(0,0,0,.08);">
                                                    <img src="../../uploads/galeri_rumah/<?= htmlspecialchars($g['foto']) ?>" style="width:100%; height:100%; object-fit:cover;">
                                                    <a href="index.php?action=hapus_foto&id=<?= $g['id_galeri'] ?>" 
                                                       onclick="return confirm('Hapus foto ini dari galeri?')"
                                                       title="Hapus foto ini"
                                                       style="position:absolute; top:5px; right:5px; background:#ef4444; color:#fff; border-radius:50%; width:24px; height:24px; display:flex; align-items:center; justify-content:center; text-decoration:none; font-size:13px; font-weight:900; line-height:1; box-shadow:0 1px 4px rgba(0,0,0,.3);">✕</a>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php elseif ($action === 'edit'): ?>
                                    <div style="padding:16px; background:#fff; border:1px dashed #6ee7b7; border-radius:10px; text-align:center; color:#94a3b8; font-size:13px;">
                                        🏠 Belum ada foto detail — pilih beberapa file di atas
                                    </div>
                                <?php endif; ?>
                            </div>

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
                    <div class="page-header-right">
                        <a href="index.php?action=tambah" class="btn btn-primary">+ Tambah Unit Baru</a>
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
                                        <th>Foto Unit</th>
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
                                        <tr><td colspan="9" class="empty">Tidak ada unit rumah terdaftar yang sesuai.</td></tr>
                                    <?php else: $no=1; foreach($list_rumah as $r): ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td>
                                                <div style="display: flex; flex-direction: column; align-items: center; gap: 4px;">
                                                    <?php
                                                    // Ambil thumbnail foto pertama dari galeri, sampul, tipe_rumah, atau placeholder kosong
                                                    $foto_src = '';
                                                    $foto_source = 'Tipe (Default)';
                                                    $badge_color = '#64748b'; // Grey
                                                    $badge_bg = '#f1f5f9';

                                                    $galeri_stmt = $db->prepare("SELECT foto FROM galeri_rumah WHERE id_rumah = ? ORDER BY id_galeri ASC LIMIT 1");
                                                    $galeri_stmt->execute([$r['id_rumah']]);
                                                    $gf = $galeri_stmt->fetchColumn();

                                                    if ($gf && file_exists('../../uploads/galeri_rumah/' . $gf)) {
                                                        $foto_src = '../../uploads/galeri_rumah/' . $gf;
                                                        $foto_source = 'Galeri';
                                                        $badge_color = '#10b981'; // Green
                                                        $badge_bg = '#d1fae5';
                                                    } elseif ($r['foto'] && file_exists('../../uploads/tipe_rumah/' . $r['foto'])) {
                                                        $foto_src = '../../uploads/tipe_rumah/' . $r['foto'];
                                                        $foto_source = 'Sampul';
                                                        $badge_color = '#2563eb'; // Blue
                                                        $badge_bg = '#dbeafe';
                                                    } elseif (!empty($r['tipe_foto']) && file_exists('../../uploads/tipe_rumah/' . $r['tipe_foto'])) {
                                                        $foto_src = '../../uploads/tipe_rumah/' . $r['tipe_foto'];
                                                    } else {
                                                        $foto_source = 'Kosong';
                                                        $badge_color = '#ef4444'; // Red
                                                        $badge_bg = '#fee2e2';
                                                    }
                                                    ?>
                                                    <?php if($foto_src): ?>
                                                        <img src="<?= $foto_src ?>" style="width:70px; height:50px; object-fit:cover; border-radius:8px; border: 1px solid #cbd5e1; box-shadow:0 1px 3px rgba(0,0,0,.08);">
                                                    <?php else: ?>
                                                        <div style="width:70px; height:50px; background:#f1f5f9; border: 1px dashed #cbd5e1; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:22px; color:#94a3b8;">📷</div>
                                                    <?php endif; ?>
                                                    <span style="font-size:9.5px; font-weight:800; padding:1px 6px; border-radius:4px; background:<?= $badge_bg ?>; color:<?= $badge_color ?>; text-transform:uppercase; letter-spacing:0.3px;">
                                                        <?= $foto_source ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td><b><?= htmlspecialchars($r['nama_perumahan']) ?></b></td>
                                            <td><span style="font-weight: 700; color: var(--primary);">Blok <?= htmlspecialchars($r['blok'] . '-' . $r['kode_unit']) ?></span></td>
                                            <td><?= htmlspecialchars($r['nama_tipe']) ?></td>
                                            <td style="font-weight: 700;"><?= format_rupiah($r['harga']) ?></td>
                                            <td><?= badge_unit($r['status']) ?></td>
                                            <td style="text-align:center;">
                                                <div class="aksi-table">
                                                    <a href="index.php?action=edit&id=<?= $r['id_rumah'] ?>" class="btn-edit">✏️ Edit</a>
                                                    <a href="index.php?action=hapus&id=<?= $r['id_rumah'] ?>" 
                                                       onclick="return confirm('Hapus unit Blok <?= addslashes($r['blok']) ?>-<?= addslashes($r['kode_unit']) ?>?\n\nSemua foto profil dan galeri unit ini akan ikut terhapus. Tindakan ini tidak dapat dibatalkan.')"
                                                       class="btn-delete">🗑️ Hapus</a>
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
