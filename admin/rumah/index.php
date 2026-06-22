<?php
// admin/rumah/index.php
require_once '../../config/koneksi.php';
require_once '../../config/cek_admin.php';
require_once '../../config/functions.php';
require_once '../../includes/sidebar_admin.php';

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

// Proses Hapus
if ($action === 'hapus' && $id > 0) {
    try {
        $stmt = $db->prepare("DELETE FROM rumah WHERE id_rumah = ?");
        $stmt->execute([$id]);
        set_flash('sukses', 'Data unit rumah berhasil dihapus.');
    } catch (PDOException $e) {
        set_flash('gagal', 'Gagal menghapus unit. Data ini mungkin masih digunakan oleh booking atau pengajuan KPR.');
    }
    header('Location: index.php');
    exit;
}

$error = '';
// Proses Submit Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_perumahan = (int)($_POST['id_perumahan'] ?? 0);
    $id_tipe      = (int)($_POST['id_tipe'] ?? 0);
    $kode_unit    = trim($_POST['kode_unit'] ?? '');
    $blok         = trim($_POST['blok'] ?? '');
    $status       = trim($_POST['status'] ?? 'tersedia');

    if (!$id_perumahan || !$id_tipe || empty($kode_unit) || empty($blok)) {
        $error = 'Semua kolom wajib diisi.';
    } else {
        if ($action === 'tambah') {
            $stmt = $db->prepare("INSERT INTO rumah (id_perumahan, id_tipe, kode_unit, blok, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$id_perumahan, $id_tipe, $kode_unit, $blok, $status]);
            set_flash('sukses', 'Unit rumah berhasil ditambahkan.');
            header('Location: index.php');
            exit;
        } elseif ($action === 'edit' && $id > 0) {
            $stmt = $db->prepare("UPDATE rumah SET id_perumahan = ?, id_tipe = ?, kode_unit = ?, blok = ?, status = ? WHERE id_rumah = ?");
            $stmt->execute([$id_perumahan, $id_tipe, $kode_unit, $blok, $status, $id]);
            set_flash('sukses', 'Data unit rumah berhasil diperbarui.');
            header('Location: index.php');
            exit;
        }
    }
}

// Ambil data untuk Edit
$rumah = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $db->prepare("SELECT * FROM rumah WHERE id_rumah = ?");
    $stmt->execute([$id]);
    $rumah = $stmt->fetch();
    if (!$rumah) {
        set_flash('gagal', 'Data unit rumah tidak ditemukan.');
        header('Location: index.php');
        exit;
    }
}

// Ambil data dropdown
$list_perumahan = $db->query("SELECT id_perumahan, nama_perumahan FROM perumahan ORDER BY nama_perumahan ASC")->fetchAll();
$list_tipe      = $db->query("SELECT id_tipe, nama_tipe, harga FROM tipe_rumah ORDER BY nama_tipe ASC")->fetchAll();

// Filter Pencarian
$f_perumahan = (int)($_GET['f_perumahan'] ?? 0);
$f_status    = trim($_GET['f_status'] ?? '');

$query = "SELECT r.*, p.nama_perumahan, t.nama_tipe, t.harga 
          FROM rumah r 
          JOIN perumahan p ON r.id_perumahan = p.id_perumahan 
          JOIN tipe_rumah t ON r.id_tipe = t.id_tipe 
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
                <div class="panel">
                    <div class="panel-header">
                        <h3><?= $action === 'tambah' ? '🚪 Tambah Unit Rumah' : '✏️ Edit Unit Rumah' ?></h3>
                        <a href="index.php" class="btn btn-gray btn-sm">← Kembali</a>
                    </div>
                    <div class="panel-body">
                        <?php if ($error): ?><div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
                        <form method="POST">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Komplek Perumahan</label>
                                    <select name="id_perumahan" class="form-control" required>
                                        <option value="">-- Pilih Komplek --</option>
                                        <?php foreach($list_perumahan as $p): ?>
                                            <option value="<?= $p['id_perumahan'] ?>" <?= (isset($rumah['id_perumahan']) && $rumah['id_perumahan'] == $p['id_perumahan']) || (isset($_POST['id_perumahan']) && $_POST['id_perumahan'] == $p['id_perumahan']) ? 'selected' : '' ?>><?= htmlspecialchars($p['nama_perumahan']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Tipe Rumah</label>
                                    <select name="id_tipe" class="form-control" required>
                                        <option value="">-- Pilih Tipe --</option>
                                        <?php foreach($list_tipe as $t): ?>
                                            <option value="<?= $t['id_tipe'] ?>" <?= (isset($rumah['id_tipe']) && $rumah['id_tipe'] == $t['id_tipe']) || (isset($_POST['id_tipe']) && $_POST['id_tipe'] == $t['id_tipe']) ? 'selected' : '' ?>><?= htmlspecialchars($t['nama_tipe']) ?> (<?= format_rupiah($t['harga']) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
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
                        <h2>🚪 Unit Rumah</h2>
                        <p>Kelola blok, kode unit, tipe, komplek perumahan, dan status penjualan</p>
                    </div>
                    <a href="index.php?action=tambah" class="btn btn-primary">➕ Tambah Unit</a>
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
                                                <a href="index.php?action=edit&id=<?= $r['id_rumah'] ?>" class="btn btn-warning btn-sm">✏️ Edit</a>
                                                <a href="#" data-hapus="index.php?action=hapus&id=<?= $r['id_rumah'] ?>" data-nama="Blok <?= htmlspecialchars($r['blok'] . '-' . $r['kode_unit']) ?>" class="btn btn-danger btn-sm">🗑️ Hapus</a>
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
