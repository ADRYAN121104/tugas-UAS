<?php
// admin/bank/index.php
require_once '../../config/koneksi.php';
require_once '../../config/cek_admin.php';
require_once '../../config/functions.php';
require_once '../../includes/sidebar_admin.php';

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

// Proses Hapus Bank
if ($action === 'hapus' && $id > 0) {
    try {
        $stmt = $db->prepare("DELETE FROM bank WHERE id_bank = ?");
        $stmt->execute([$id]);
        set_flash('sukses', 'Data bank rekanan berhasil dihapus.');
    } catch (PDOException $e) {
        set_flash('gagal', 'Gagal menghapus bank. Data ini mungkin masih digunakan oleh pengajuan KPR.');
    }
    header('Location: index.php');
    exit;
}

$error = '';
// Proses Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_bank = trim($_POST['nama_bank'] ?? '');
    $bunga_kpr = (float)($_POST['bunga_kpr'] ?? 0);
    $tenor_maksimal = (int)($_POST['tenor_maksimal'] ?? 0);

    if (empty($nama_bank) || $bunga_kpr <= 0 || $tenor_maksimal <= 0) {
        $error = 'Semua bidang wajib diisi dengan benar.';
    } else {
        if ($action === 'tambah') {
            $stmt = $db->prepare("INSERT INTO bank (nama_bank, bunga_kpr, tenor_maksimal) VALUES (?, ?, ?)");
            $stmt->execute([$nama_bank, $bunga_kpr, $tenor_maksimal]);
            set_flash('sukses', 'Bank rekanan berhasil ditambahkan.');
            header('Location: index.php');
            exit;
        } elseif ($action === 'edit' && $id > 0) {
            $stmt = $db->prepare("UPDATE bank SET nama_bank = ?, bunga_kpr = ?, tenor_maksimal = ? WHERE id_bank = ?");
            $stmt->execute([$nama_bank, $bunga_kpr, $tenor_maksimal, $id]);
            set_flash('sukses', 'Data bank rekanan berhasil diperbarui.');
            header('Location: index.php');
            exit;
        }
    }
}

// Ambil data untuk Edit
$bank = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $db->prepare("SELECT * FROM bank WHERE id_bank = ?");
    $stmt->execute([$id]);
    $bank = $stmt->fetch();
    if (!$bank) {
        set_flash('gagal', 'Data bank tidak ditemukan.');
        header('Location: index.php');
        exit;
    }
}

// Ambil semua bank
$list_bank = $db->query("SELECT * FROM bank ORDER BY nama_bank ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Bank Rekanan - RumahKPR Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <?php sidebar_admin('bank'); ?>
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
                <a href="../dashboard.php">Dashboard</a> / <span>Bank Rekanan</span>
            </div>
            <?php tampil_flash(); ?>

            <?php if ($action === 'tambah' || $action === 'edit'): ?>
                <!-- FORM TAMBAH / EDIT -->
                <div class="panel">
                    <div class="panel-header">
                        <h3><?= $action === 'tambah' ? '➕ Tambah Bank Rekanan' : '✏️ Edit Bank Rekanan' ?></h3>
                        <a href="index.php" class="btn btn-gray btn-sm">← Kembali</a>
                    </div>
                    <div class="panel-body">
                        <?php if ($error): ?><div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
                        <form method="POST">
                            <div class="form-group">
                                <label>Nama Bank</label>
                                <input type="text" name="nama_bank" class="form-control" placeholder="Contoh: Bank BRI" value="<?= htmlspecialchars($_POST['nama_bank'] ?? $bank['nama_bank'] ?? '') ?>" required>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Suku Bunga KPR Efektif (% / tahun)</label>
                                    <input type="number" name="bunga_kpr" step="0.01" class="form-control" placeholder="Contoh: 7.50" value="<?= htmlspecialchars($_POST['bunga_kpr'] ?? $bank['bunga_kpr'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Tenor Maksimal KPR (tahun)</label>
                                    <input type="number" name="tenor_maksimal" class="form-control" placeholder="Contoh: 20" value="<?= htmlspecialchars($_POST['tenor_maksimal'] ?? $bank['tenor_maksimal'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div style="margin-top:10px;">
                                <button type="submit" class="btn btn-primary">💾 Simpan Data</button>
                                <a href="index.php" class="btn btn-gray">Batal</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- LIST DATA -->
                <div class="page-header">
                    <div class="page-header-left">
                        <h2 class="gradient-title">🏦 Bank Rekanan</h2>
                        <p>Kelola daftar mitra bank penyedia layanan pembiayaan KPR</p>
                    </div>
                    <a href="index.php?action=tambah" class="btn btn-primary">➕ Tambah Bank</a>
                </div>

                <div class="panel">
                    <div class="panel-body" style="padding:0;">
                        <div class="tbl-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama Bank</th>
                                        <th>Suku Bunga KPR / thn</th>
                                        <th>Tenor Maksimal</th>
                                        <th style="width:180px; text-align:center;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($list_bank)): ?>
                                        <tr><td colspan="5" class="empty">Belum ada bank rekanan terdaftar.</td></tr>
                                    <?php else: $no=1; foreach($list_bank as $b): ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><b><?= htmlspecialchars($b['nama_bank']) ?></b></td>
                                            <td><span class="badge" style="background:#dbeafe; color:#1e40af;"><?= $b['bunga_kpr'] ?>% p.a</span></td>
                                            <td><?= $b['tenor_maksimal'] ?> Tahun</td>
                                            <td style="text-align:center;">
                                                <div class="aksi-table">
                                                <a href="index.php?action=edit&id=<?= $b['id_bank'] ?>" class="btn-edit">✏️ Edit</a>
                                                <a href="#" data-hapus="index.php?action=hapus&id=<?= $b['id_bank'] ?>" data-nama="<?= htmlspecialchars($b['nama_bank']) ?>" class="btn-delete">🗑️ Hapus</a>
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
