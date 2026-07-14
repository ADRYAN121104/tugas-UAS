<?php
// admin/marketing/index.php
require_once '../../config/koneksi.php';
require_once '../../config/cek_admin.php';
require_once '../../config/functions.php';
require_once '../../includes/sidebar_admin.php';

$action = $_GET['action'] ?? '';
$id = (int)($_GET['id'] ?? 0);

// Proses Hapus
if ($action === 'hapus' && $id > 0) {
    try {
        $stmt = $db->prepare("DELETE FROM users WHERE id_user = ? AND role = 'marketing'");
        $stmt->execute([$id]);
        set_flash('sukses', 'Akun marketing berhasil dihapus.');
    } catch (PDOException $e) {
        set_flash('gagal', 'Gagal menghapus akun marketing.');
    }
    header('Location: index.php');
    exit;
}

$error = '';
// Proses Submit Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama  = trim($_POST['nama_lengkap'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $pass  = trim($_POST['password'] ?? '');

    if (empty($nama) || empty($email) || empty($no_hp)) {
        $error = 'Nama, email, dan nomor HP wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } else {
        // Cek email ganda
        $cek = $db->prepare("SELECT id_user FROM users WHERE email = ? AND id_user != ?");
        $cek->execute([$email, $id]);
        if ($cek->fetch()) {
            $error = 'Email ini sudah digunakan oleh akun lain.';
        } else {
            if ($action === 'tambah') {
                if (empty($pass)) {
                    $error = 'Password wajib diisi untuk akun baru.';
                } elseif (strlen($pass) < 6) {
                    $error = 'Password minimal 6 karakter.';
                } else {
                    $hash = password_hash($pass, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO users (nama_lengkap, email, password, no_hp, role) VALUES (?, ?, ?, ?, 'marketing')");
                    $stmt->execute([$nama, $email, $hash, $no_hp]);
                    set_flash('sukses', 'Akun marketing baru berhasil ditambahkan.');
                    header('Location: index.php');
                    exit;
                }
            } elseif ($action === 'edit' && $id > 0) {
                if (!empty($pass)) {
                    $hash = password_hash($pass, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET nama_lengkap = ?, email = ?, password = ?, no_hp = ? WHERE id_user = ? AND role = 'marketing'");
                    $stmt->execute([$nama, $email, $hash, $no_hp, $id]);
                } else {
                    $stmt = $db->prepare("UPDATE users SET nama_lengkap = ?, email = ?, no_hp = ? WHERE id_user = ? AND role = 'marketing'");
                    $stmt->execute([$nama, $email, $no_hp, $id]);
                }
                set_flash('sukses', 'Data akun marketing berhasil diperbarui.');
                header('Location: index.php');
                exit;
            }
        }
    }
}

// Ambil data untuk Edit
$marketing = null;
if ($action === 'edit' && $id > 0) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id_user = ? AND role = 'marketing'");
    $stmt->execute([$id]);
    $marketing = $stmt->fetch();
    if (!$marketing) {
        set_flash('gagal', 'Data akun marketing tidak ditemukan.');
        header('Location: index.php');
        exit;
    }
}

// Ambil list marketing
$search = trim($_GET['s'] ?? '');
if (!empty($search)) {
    $stmt = $db->prepare("SELECT * FROM users WHERE role = 'marketing' AND (nama_lengkap LIKE ? OR email LIKE ? OR no_hp LIKE ?) ORDER BY created_at DESC");
    $stmt->execute(["%$search%", "%$search%", "%$search%"]);
} else {
    $stmt = $db->prepare("SELECT * FROM users WHERE role = 'marketing' ORDER BY created_at DESC");
    $stmt->execute();
}
$list_marketing = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Akun Marketing - RumahKPR Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css?v=3">
</head>
<body>
    <?php sidebar_admin('marketing'); ?>
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
                <a href="../dashboard.php">Dashboard</a> / <span>Akun Marketing</span>
            </div>
            <?php tampil_flash(); ?>

            <?php if ($action === 'tambah' || $action === 'edit'): ?>
                <div class="panel">
                    <div class="panel-header">
                        <h3><?= $action === 'tambah' ? '📣 Tambah Akun Marketing' : '✏️ Edit Akun Marketing' ?></h3>
                        <a href="index.php" class="btn btn-gray btn-sm">← Kembali</a>
                    </div>
                    <div class="panel-body">
                        <?php if ($error): ?><div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
                        <form method="POST">
                            <div class="form-group">
                                <label>Nama Lengkap</label>
                                <input type="text" name="nama_lengkap" class="form-control" placeholder="Contoh: Marketing KPR" value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? $marketing['nama_lengkap'] ?? '') ?>" required>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="email" class="form-control" placeholder="marketing@kpr.com" value="<?= htmlspecialchars($_POST['email'] ?? $marketing['email'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>No. HP / WhatsApp</label>
                                    <input type="text" name="no_hp" class="form-control" placeholder="08xxxxxxxxxx" value="<?= htmlspecialchars($_POST['no_hp'] ?? $marketing['no_hp'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Password</label>
                                <input type="text" name="password" class="form-control" placeholder="<?= $action === 'edit' ? 'Biarkan kosong jika tidak ingin mengubah password' : 'Min. 4 karakter' ?>" <?= $action === 'tambah' ? 'required' : '' ?>>
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
                        <h2>📣 Akun Marketing</h2>
                        <p>Kelola data profil, email, no telepon/WhatsApp, dan akses akun marketing</p>
                    </div>
                    <a href="index.php?action=tambah" class="btn btn-primary">➕ Tambah Marketing</a>
                </div>

                <!-- SEARCH BAR -->
                <div class="panel" style="margin-bottom: 18px;">
                    <div class="panel-body" style="padding: 16px 20px;">
                        <form method="GET" action="" class="search-bar" style="margin-bottom:0;">
                            <input type="text" name="s" placeholder="Cari nama, email, no HP marketing..." value="<?= htmlspecialchars($search) ?>" style="flex:1;">
                            <button type="submit" class="btn btn-primary btn-sm">🔍 Cari</button>
                            <?php if (!empty($search)): ?>
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
                                        <th>Nama Lengkap</th>
                                        <th>Email</th>
                                        <th>No. HP / WA</th>
                                        <th>Tanggal Registrasi</th>
                                        <th style="width:180px; text-align:center;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($list_marketing)): ?>
                                        <tr><td colspan="6" class="empty">Tidak ada akun marketing yang ditemukan.</td></tr>
                                    <?php else: $no=1; foreach($list_marketing as $m): ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td><b><?= htmlspecialchars($m['nama_lengkap']) ?></b></td>
                                            <td><?= htmlspecialchars($m['email']) ?></td>
                                            <td>
                                                <a href="https://wa.me/<?= preg_replace('/\D/', '', $m['no_hp']) ?>" target="_blank" style="text-decoration:none; color:var(--success); font-weight:700;">
                                                    📞 <?= htmlspecialchars($m['no_hp']) ?>
                                                </a>
                                            </td>
                                            <td><?= format_tanggal($m['created_at']) ?></td>
                                            <td style="text-align:center;">
                                                <a href="index.php?action=edit&id=<?= $m['id_user'] ?>" class="btn btn-warning btn-sm">✏️ Edit</a>
                                                <a href="#" data-hapus="index.php?action=hapus&id=<?= $m['id_user'] ?>" data-nama="<?= htmlspecialchars($m['nama_lengkap']) ?>" class="btn btn-danger btn-sm">🗑️ Hapus</a>
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
