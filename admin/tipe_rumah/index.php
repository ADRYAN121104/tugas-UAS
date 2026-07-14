<?php
// admin/tipe_rumah/index.php
require_once '../../config/koneksi.php';
require_once '../../config/cek_admin.php';
require_once '../../config/functions.php';
require_once '../../includes/sidebar_admin.php';

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

// Proses Hapus Tipe
if ($action === 'hapus' && $id > 0) {
    try {
        // Ambil nama foto lama
        $stmt = $db->prepare("SELECT foto FROM tipe_rumah WHERE id_tipe = ?");
        $stmt->execute([$id]);
        $foto_lama = $stmt->fetchColumn();

        $del = $db->prepare("DELETE FROM tipe_rumah WHERE id_tipe = ?");
        $del->execute([$id]);

        // Hapus file foto lama jika ada
        if ($foto_lama && file_exists('../../uploads/tipe_rumah/' . $foto_lama)) {
            unlink('../../uploads/tipe_rumah/' . $foto_lama);
        }
        set_flash('sukses', 'Data tipe rumah berhasil dihapus.');
    } catch (PDOException $e) {
        set_flash('gagal', 'Gagal menghapus tipe rumah. Data ini mungkin masih digunakan oleh unit rumah.');
    }
    header('Location: index.php');
    exit;
}

$error = '';
// Proses Submit Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_tipe   = trim($_POST['nama_tipe'] ?? '');
    $luas_tanah  = (int)($_POST['luas_tanah'] ?? 0);
    $luas_bangunan = (int)($_POST['luas_bangunan'] ?? 0);
    $jumlah_kamar  = (int)($_POST['jumlah_kamar'] ?? 0);
    $jumlah_kamar_mandi = (int)($_POST['jumlah_kamar_mandi'] ?? 0);
    $harga       = (float)str_replace(['.', 'Rp', ' '], '', $_POST['harga'] ?? '0');
    $deskripsi   = trim($_POST['deskripsi'] ?? '');

    if (empty($nama_tipe) || $luas_tanah <= 0 || $luas_bangunan <= 0 || $harga <= 0) {
        $error = 'Nama tipe, luas tanah, luas bangunan, dan harga wajib diisi dengan benar.';
    } else {
        // Ambil data lama jika edit
        $foto_baru = '';
        if ($action === 'edit' && $id > 0) {
            $stmt = $db->prepare("SELECT foto FROM tipe_rumah WHERE id_tipe = ?");
            $stmt->execute([$id]);
            $foto_baru = $stmt->fetchColumn();
        }

        // Handle Upload Foto
        if (!empty($_FILES['foto']['name'])) {
            $upload = upload_file($_FILES['foto'], '../../uploads/tipe_rumah');
            if ($upload['ok']) {
                // Hapus foto lama jika edit
                if ($action === 'edit' && $foto_baru && file_exists('../../uploads/tipe_rumah/' . $foto_baru)) {
                    unlink('../../uploads/tipe_rumah/' . $foto_baru);
                }
                $foto_baru = $upload['nama'];
            } else {
                $error = 'Gagal upload foto: ' . $upload['pesan'];
            }
        }

        if (empty($error)) {
            if ($action === 'tambah') {
                $stmt = $db->prepare("INSERT INTO tipe_rumah (nama_tipe, luas_tanah, luas_bangunan, jumlah_kamar, jumlah_kamar_mandi, harga, deskripsi, foto) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nama_tipe, $luas_tanah, $luas_bangunan, $jumlah_kamar, $jumlah_kamar_mandi, $harga, $deskripsi, $foto_baru]);
                set_flash('sukses', 'Tipe rumah berhasil ditambahkan.');
                header('Location: index.php');
                exit;
            } elseif ($action === 'edit' && $id > 0) {
                $stmt = $db->prepare("UPDATE tipe_rumah SET nama_tipe = ?, luas_tanah = ?, luas_bangunan = ?, jumlah_kamar = ?, jumlah_kamar_mandi = ?, harga = ?, deskripsi = ?, foto = ? WHERE id_tipe = ?");
                $stmt->execute([$nama_tipe, $luas_tanah, $luas_bangunan, $jumlah_kamar, $jumlah_kamar_mandi, $harga, $deskripsi, $foto_baru, $id]);
                set_flash('sukses', 'Data tipe rumah berhasil diperbarui.');
                header('Location: index.php');
                exit;
            }
        }
    }
}

// Ambil data untuk Edit
$tipe = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $db->prepare("SELECT * FROM tipe_rumah WHERE id_tipe = ?");
    $stmt->execute([$id]);
    $tipe = $stmt->fetch();
    if (!$tipe) {
        set_flash('gagal', 'Data tipe rumah tidak ditemukan.');
        header('Location: index.php');
        exit;
    }
}

// Ambil daftar tipe rumah
$list_tipe = $db->query("SELECT * FROM tipe_rumah ORDER BY harga ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Tipe Rumah - RumahKPR Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css?v=3">
</head>
<body>
    <?php sidebar_admin('tipe_rumah'); ?>
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
                <a href="../dashboard.php">Dashboard</a> / <span>Tipe Rumah</span>
            </div>
            <?php tampil_flash(); ?>

            <?php if ($action === 'tambah' || $action === 'edit'): ?>
                <div class="panel">
                    <div class="panel-header">
                        <h3><?= $action === 'tambah' ? '📐 Tambah Tipe Rumah' : '✏️ Edit Tipe Rumah' ?></h3>
                        <a href="index.php" class="btn btn-gray btn-sm">← Kembali</a>
                    </div>
                    <div class="panel-body">
                        <?php if ($error): ?><div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label>Nama Tipe Rumah</label>
                                <input type="text" name="nama_tipe" class="form-control" placeholder="Contoh: Tipe 36/72" value="<?= htmlspecialchars($_POST['nama_tipe'] ?? $tipe['nama_tipe'] ?? '') ?>" required>
                            </div>
                            <div class="form-row-3">
                                <div class="form-group">
                                    <label>Luas Tanah (m²)</label>
                                    <input type="number" name="luas_tanah" class="form-control" placeholder="72" value="<?= htmlspecialchars($_POST['luas_tanah'] ?? $tipe['luas_tanah'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Luas Bangunan (m²)</label>
                                    <input type="number" name="luas_bangunan" class="form-control" placeholder="36" value="<?= htmlspecialchars($_POST['luas_bangunan'] ?? $tipe['luas_bangunan'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Harga Jual (Rp)</label>
                                    <input type="text" name="harga" class="form-control format-angka" placeholder="350.000.000" value="<?= htmlspecialchars(isset($_POST['harga']) ? $_POST['harga'] : (isset($tipe['harga']) ? number_format($tipe['harga'], 0, ',', '.') : '')) ?>" required>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Jumlah Kamar Tidur</label>
                                    <input type="number" name="jumlah_kamar" class="form-control" placeholder="2" value="<?= htmlspecialchars($_POST['jumlah_kamar'] ?? $tipe['jumlah_kamar'] ?? '2') ?>">
                                </div>
                                <div class="form-group">
                                    <label>Jumlah Kamar Mandi</label>
                                    <input type="number" name="jumlah_kamar_mandi" class="form-control" placeholder="1" value="<?= htmlspecialchars($_POST['jumlah_kamar_mandi'] ?? $tipe['jumlah_kamar_mandi'] ?? '1') ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Deskripsi / Spesifikasi Bangunan</label>
                                <textarea name="deskripsi" class="form-control" placeholder="Tuliskan detail spesifikasi seperti pondasi, dinding, atap, dll..." style="min-height:100px;"><?= htmlspecialchars($_POST['deskripsi'] ?? $tipe['deskripsi'] ?? '') ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Foto Tipe Rumah</label>
                                <input type="file" name="foto" class="form-control" accept=".jpg,.jpeg,.png" data-preview="imgPrev">
                                <small class="form-hint">Kosongkan jika tidak ingin mengubah foto tipe rumah. Format: JPG/PNG, Max 5MB</small>
                                <div style="margin-top:12px;">
                                    <?php 
                                    $foto_src = '';
                                    $display = 'none';
                                    if (isset($tipe['foto']) && $tipe['foto'] && file_exists('../../uploads/tipe_rumah/' . $tipe['foto'])) {
                                        $foto_src = '../../uploads/tipe_rumah/' . $tipe['foto'];
                                        $display = 'block';
                                    }
                                    ?>
                                    <img id="imgPrev" src="<?= $foto_src ?>" style="max-height:200px; border-radius:10px; display:<?= $display ?>; box-shadow:var(--shadow);">
                                </div>
                            </div>
                            <div style="margin-top:14px;">
                                <button type="submit" class="btn btn-primary">💾 Simpan Data</button>
                                <a href="index.php" class="btn btn-gray">Batal</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="page-header">
                    <div class="page-header-left">
                        <h2 class="gradient-title">📐 Tipe Rumah</h2>
                        <p>Kelola data tipe, dimensi, jumlah kamar, harga, dan foto tipe rumah</p>
                    </div>
                    <a href="index.php?action=tambah" class="btn btn-primary">➕ Tambah Tipe</a>
                </div>

                <div class="panel">
                    <div class="panel-body" style="padding:0;">
                        <div class="tbl-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Foto</th>
                                        <th>Nama Tipe</th>
                                        <th>LT / LB</th>
                                        <th>Kamar (T / M)</th>
                                        <th>Harga Jual</th>
                                        <th style="width:180px; text-align:center;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($list_tipe)): ?>
                                        <tr><td colspan="6" class="empty">Belum ada tipe rumah terdaftar.</td></tr>
                                    <?php else: foreach($list_tipe as $t): ?>
                                        <tr>
                                            <td>
                                                <?php if($t['foto'] && file_exists('../../uploads/tipe_rumah/' . $t['foto'])): ?>
                                                    <img src="../../uploads/tipe_rumah/<?= htmlspecialchars($t['foto']) ?>" style="width:70px; height:50px; object-fit:cover; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,.1);">
                                                <?php else: ?>
                                                    <div style="width:70px; height:50px; background:#e2e8f0; border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:18px;">📐</div>
                                                <?php endif; ?>
                                            </td>
                                            <td><b><?= htmlspecialchars($t['nama_tipe']) ?></b></td>
                                            <td><?= $t['luas_tanah'] ?> m² / <?= $t['luas_bangunan'] ?> m²</td>
                                            <td>🛏️ <?= $t['jumlah_kamar'] ?> &nbsp;|&nbsp; 🚿 <?= $t['jumlah_kamar_mandi'] ?></td>
                                            <td style="font-weight:700; color:var(--success);"><?= format_rupiah($t['harga']) ?></td>
                                            <td style="text-align:center;">
                                                <div class="aksi-table">
                                                <a href="index.php?action=edit&id=<?= $t['id_tipe'] ?>" class="btn-edit">✏️ Edit</a>
                                                <a href="#" data-hapus="index.php?action=hapus&id=<?= $t['id_tipe'] ?>" data-nama="<?= htmlspecialchars($t['nama_tipe']) ?>" class="btn-delete">🗑️ Hapus</a>
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
