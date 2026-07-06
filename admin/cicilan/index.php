<?php
// admin/cicilan/index.php - Kelola Cicilan KPR
require_once '../../config/koneksi.php';
require_once '../../config/cek_admin.php';
require_once '../../config/functions.php';
require_once '../../includes/sidebar_admin.php';

$action = $_GET['action'] ?? '';
$id     = (int)($_GET['id'] ?? 0);

// ── VERIFIKASI PEMBAYARAN CICILAN ───────────────────────────────────────────
if ($id > 0 && in_array($action, ['valid', 'tolak'])) {
    $stmt = $db->prepare("SELECT * FROM cicilan_kpr WHERE id_cicilan = ?");
    $stmt->execute([$id]);
    $cic = $stmt->fetch();
    if ($cic) {
        if ($action === 'valid') {
            $db->prepare("UPDATE cicilan_kpr SET status_verifikasi='valid', status_bayar='lunas' WHERE id_cicilan=?")->execute([$id]);
            set_flash('sukses', '✅ Pembayaran cicilan bulan ke-' . $cic['bulan_ke'] . ' diverifikasi VALID.');
        } else {
            $db->prepare("UPDATE cicilan_kpr SET status_verifikasi='ditolak', status_bayar='belum', tanggal_bayar=NULL, bukti_bayar=NULL WHERE id_cicilan=?")->execute([$id]);
            set_flash('gagal', '❌ Pembayaran cicilan ditolak. Customer perlu kirim ulang bukti.');
        }
    }
    header('Location: index.php');
    exit;
}

// ── GENERATE JADWAL CICILAN (Admin generate setelah status akad_kredit) ─────
if ($action === 'generate' && $id > 0) {
    $stmt = $db->prepare("
        SELECT pk.*, r.harga, b.bunga_kpr, b.tenor_maksimal
        FROM pengajuan_kpr pk
        JOIN rumah r ON pk.id_rumah = r.id_rumah
        JOIN bank b ON pk.id_bank = b.id_bank
        WHERE pk.id_pengajuan = ? AND pk.status_pengajuan = 'akad_kredit'
    ");
    $stmt->execute([$id]);
    $kpr = $stmt->fetch();
    if ($kpr) {
        // Cek apakah sudah ada jadwal
        $cek = $db->prepare("SELECT COUNT(*) FROM cicilan_kpr WHERE id_pengajuan=?");
        $cek->execute([$id]);
        if ($cek->fetchColumn() > 0) {
            set_flash('gagal', 'Jadwal cicilan sudah pernah dibuat untuk pengajuan ini.');
        } else {
            $harga      = (float)$kpr['harga'];
            $dp         = (float)$kpr['uang_muka'];
            $pokok_pinjaman = $harga - $dp;
            $bunga_pa   = (float)$kpr['bunga_kpr'] / 100;
            $tenor_bulan = (int)$kpr['tenor'] * 12;
            $bunga_pb   = $bunga_pa / 12;

            // Hitung cicilan per bulan (flat annuity)
            if ($bunga_pb > 0) {
                $cicilan_bulanan = $pokok_pinjaman * ($bunga_pb * pow(1 + $bunga_pb, $tenor_bulan)) / (pow(1 + $bunga_pb, $tenor_bulan) - 1);
            } else {
                $cicilan_bulanan = $pokok_pinjaman / $tenor_bulan;
            }

            $saldo_pokok = $pokok_pinjaman;
            $tgl_mulai = new DateTime();
            $tgl_mulai->modify('+1 month');

            $db->beginTransaction();
            for ($bulan = 1; $bulan <= $tenor_bulan; $bulan++) {
                $bunga = $saldo_pokok * $bunga_pb;
                $cicilan_pokok = $cicilan_bulanan - $bunga;
                $saldo_pokok -= $cicilan_pokok;
                if ($saldo_pokok < 0) $saldo_pokok = 0;

                $tgl_jatuh = clone $tgl_mulai;
                $tgl_jatuh->modify('+' . ($bulan - 1) . ' month');

                $ins = $db->prepare("INSERT INTO cicilan_kpr (id_pengajuan, bulan_ke, tanggal_jatuh_tempo, jumlah_cicilan, pokok, bunga, status_bayar) VALUES (?, ?, ?, ?, ?, ?, 'belum')");
                $ins->execute([$id, $bulan, $tgl_jatuh->format('Y-m-d'), round($cicilan_bulanan, 2), round($cicilan_pokok, 2), round($bunga, 2)]);
            }
            $db->commit();
            set_flash('sukses', '✅ Jadwal cicilan ' . $tenor_bulan . ' bulan berhasil dibuat! Total cicilan: ' . format_rupiah($cicilan_bulanan) . '/bln');
        }
    } else {
        set_flash('gagal', 'Pengajuan KPR tidak ditemukan atau belum berstatus Akad Kredit.');
    }
    header('Location: index.php');
    exit;
}

// ── DATA ─────────────────────────────────────────────────────────────────────
$id_pengajuan = (int)($_GET['id_pengajuan'] ?? 0);

// List pengajuan yang sudah akad (bisa generate)
$stmt_kpr = $db->prepare("
    SELECT pk.id_pengajuan, pk.status_pengajuan, pk.uang_muka, pk.tenor,
           u.nama_lengkap, b.nama_bank, b.bunga_kpr,
           p.nama_perumahan, r.blok, r.kode_unit, r.harga,
           (SELECT COUNT(*) FROM cicilan_kpr c WHERE c.id_pengajuan=pk.id_pengajuan) as jml_cicilan,
           (SELECT COUNT(*) FROM cicilan_kpr c WHERE c.id_pengajuan=pk.id_pengajuan AND c.status_bayar='lunas') as jml_lunas
    FROM pengajuan_kpr pk
    JOIN users u ON pk.id_user = u.id_user
    JOIN rumah r ON pk.id_rumah = r.id_rumah
    JOIN perumahan p ON r.id_perumahan = p.id_perumahan
    JOIN bank b ON pk.id_bank = b.id_bank
    WHERE pk.status_pengajuan IN ('disetujui','akad_kredit')
    ORDER BY pk.id_pengajuan DESC
");
$stmt_kpr->execute();
$list_kpr = $stmt_kpr->fetchAll();

// Cicilan per pengajuan (jika dipilih)
$cicilan_detail = [];
$kpr_detail = null;
if ($id_pengajuan > 0) {
    $dk = $db->prepare("
        SELECT pk.id_pengajuan, pk.uang_muka, pk.tenor,
               u.nama_lengkap, b.nama_bank, b.bunga_kpr,
               p.nama_perumahan, r.blok, r.kode_unit, r.harga
        FROM pengajuan_kpr pk
        JOIN users u ON pk.id_user = u.id_user
        JOIN rumah r ON pk.id_rumah = r.id_rumah
        JOIN perumahan p ON r.id_perumahan = p.id_perumahan
        JOIN bank b ON pk.id_bank = b.id_bank
        WHERE pk.id_pengajuan=?
    ");
    $dk->execute([$id_pengajuan]);
    $kpr_detail = $dk->fetch();

    $dc = $db->prepare("SELECT * FROM cicilan_kpr WHERE id_pengajuan=? ORDER BY bulan_ke ASC");
    $dc->execute([$id_pengajuan]);
    $cicilan_detail = $dc->fetchAll();
}

// Ringkasan total
$total_cicilan_valid   = (float)$db->query("SELECT COALESCE(SUM(jumlah_cicilan),0) FROM cicilan_kpr WHERE status_bayar='lunas'")->fetchColumn();
$total_cicilan_pending = (float)$db->query("SELECT COALESCE(SUM(jumlah_cicilan),0) FROM cicilan_kpr WHERE status_verifikasi='pending'")->fetchColumn();
$total_cicilan_belum   = (float)$db->query("SELECT COUNT(*) FROM cicilan_kpr WHERE status_bayar='belum'")->fetchColumn();

// Total DP masuk yang sudah valid
$total_dp_valid = (float)$db->query("SELECT COALESCE(SUM(jumlah_dp),0) FROM pembayaran_dp WHERE status_verifikasi='valid'")->fetchColumn();

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Cicilan KPR - RumahKPR Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <?php sidebar_admin('cicilan'); ?>
    <div class="admin-main">
        <header class="topbar">
            <div style="display:flex;align-items:center;gap:12px;">
                <button class="btn btn-gray btn-sm" id="sidebarToggle" style="padding:6px 10px;">&#9776;</button>
                <div class="topbar-title">Sistem KPR Perumahan</div>
            </div>
            <div class="topbar-right">
                <span class="topbar-name"><?= htmlspecialchars(nama_user()) ?> (<?= ucfirst(role_user()) ?>)</span>
                <div class="topbar-avatar"><?= strtoupper(substr(nama_user(),0,1)) ?></div>
            </div>
        </header>
        <main class="content">
            <div class="breadcrumb">
                <a href="../dashboard.php">Dashboard</a> / <span>Cicilan KPR</span>
            </div>
            <?php tampil_flash(); ?>

            <div class="page-header">
                <div class="page-header-left">
                    <h2 class="gradient-title-green">💳 Manajemen Cicilan KPR</h2>
                    <p>Generate jadwal cicilan, verifikasi pembayaran bulanan customer</p>
                </div>
            </div>

            <!-- STAT CARDS -->
            <div class="stat-grid" style="margin-bottom:24px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));">
                <div class="stat-card">
                    <div class="stat-ico ico-hijau">💰</div>
                    <div class="stat-info"><h3><?= format_rupiah($total_cicilan_valid) ?></h3><p>Total Cicilan Diterima</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-ico ico-ungu">🏦</div>
                    <div class="stat-info"><h3><?= format_rupiah($total_dp_valid) ?></h3><p>Total DP Terverifikasi</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-ico" style="background:linear-gradient(135deg,#059669,#0891b2);color:#fff;">🏆</div>
                    <div class="stat-info"><h3><?= format_rupiah($total_dp_valid + $total_cicilan_valid) ?></h3><p>Grand Total Pendapatan</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-ico ico-kuning">⏳</div>
                    <div class="stat-info"><h3><?= format_rupiah($total_cicilan_pending) ?></h3><p>Menunggu Verifikasi</p></div>
                </div>
                <div class="stat-card">
                    <div class="stat-ico ico-biru">📅</div>
                    <div class="stat-info"><h3><?= number_format($total_cicilan_belum) ?></h3><p>Cicilan Belum Dibayar</p></div>
                </div>
            </div>

            <!-- DAFTAR KPR AKTIF -->
            <div class="panel">
                <div class="panel-header">
                    <h3 style="background:linear-gradient(135deg,#059669,#0891b2);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">📋 Pengajuan KPR Aktif (Perlu Cicilan)</h3>
                </div>
                <div class="panel-body" style="padding:0;">
                    <div class="tbl-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Properti</th>
                                    <th>Bank & Bunga</th>
                                    <th>Harga / DP</th>
                                    <th>Tenor</th>
                                    <th>Status</th>
                                    <th>Progres Cicilan</th>
                                    <th style="text-align:center; width:200px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($list_kpr)): ?>
                                <tr><td colspan="8" class="empty">Belum ada pengajuan KPR yang disetujui / akad kredit.</td></tr>
                            <?php else: foreach ($list_kpr as $k): ?>
                                <tr>
                                    <td><b><?= htmlspecialchars($k['nama_lengkap']) ?></b></td>
                                    <td>
                                        <b><?= htmlspecialchars($k['nama_perumahan']) ?></b><br>
                                        <small style="color:var(--primary);font-weight:700;">Blok <?= htmlspecialchars($k['blok'].'-'.$k['kode_unit']) ?></small>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($k['nama_bank']) ?><br>
                                        <small style="color:var(--success);font-weight:700;"><?= $k['bunga_kpr'] ?>% / th</small>
                                    </td>
                                    <td>
                                        <div style="font-weight:700;"><?= format_rupiah($k['harga']) ?></div>
                                        <small style="color:var(--muted);">DP: <?= format_rupiah($k['uang_muka']) ?></small>
                                    </td>
                                    <td><?= $k['tenor'] ?> tahun</td>
                                    <td>
                                        <?php if ($k['status_pengajuan']==='akad_kredit'): ?>
                                            <span class="badge-lunas">🤝 Akad Kredit</span>
                                        <?php else: ?>
                                            <span class="badge-belum">✅ Disetujui</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($k['jml_cicilan'] > 0): ?>
                                            <div style="display:flex;align-items:center;gap:8px;">
                                                <div style="flex:1;height:8px;background:#e2e8f0;border-radius:4px;overflow:hidden;">
                                                    <div style="height:100%;width:<?= round($k['jml_lunas']/$k['jml_cicilan']*100) ?>%;background:linear-gradient(90deg,#10b981,#059669);border-radius:4px;transition:.3s;"></div>
                                                </div>
                                                <small style="font-weight:700;color:var(--success);white-space:nowrap;"><?= $k['jml_lunas'] ?>/<?= $k['jml_cicilan'] ?></small>
                                            </div>
                                        <?php else: ?>
                                            <span style="color:var(--muted);font-size:12px;">Belum di-generate</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <div class="aksi-table" style="flex-direction:column;">
                                        <?php if ($k['jml_cicilan'] == 0 && $k['status_pengajuan']==='akad_kredit'): ?>
                                            <a href="index.php?action=generate&id=<?= $k['id_pengajuan'] ?>"
                                               class="btn btn-success btn-sm" style="width:100%;justify-content:center;"
                                               onclick="return confirm('Generate jadwal cicilan untuk KPR ini?\n\nJadwal cicilan akan otomatis dibuat berdasarkan tenor dan bunga bank.')">
                                                ⚙️ Generate Jadwal
                                            </a>
                                        <?php endif; ?>
                                        <a href="index.php?id_pengajuan=<?= $k['id_pengajuan'] ?>" class="btn-edit" style="width:100%;justify-content:center;">
                                            📋 Lihat Cicilan
                                        </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($id_pengajuan > 0 && $kpr_detail): ?>
            <!-- DETAIL CICILAN -->
            <div class="panel">
                <div class="panel-header">
                    <h3>📅 Jadwal Cicilan: <?= htmlspecialchars($kpr_detail['nama_lengkap']) ?> — <?= htmlspecialchars($kpr_detail['nama_perumahan']) ?> Blok <?= htmlspecialchars($kpr_detail['blok'].'-'.$kpr_detail['kode_unit']) ?></h3>
                    <a href="index.php" class="btn btn-gray btn-sm">← Kembali</a>
                </div>
                <div class="panel-body" style="padding:0;">
                    <?php if (empty($cicilan_detail)): ?>
                        <div class="empty">
                            <div class="empty-ico">📅</div>
                            <h4>Jadwal belum di-generate</h4>
                            <p>Ubah status KPR ke Akad Kredit dulu, lalu klik Generate Jadwal.</p>
                        </div>
                    <?php else: ?>
                        <?php
                        // Ambil data DP customer ini
                        $dp_q_adm = $db->prepare("SELECT jumlah_dp FROM pembayaran_dp WHERE id_pengajuan=? AND status_verifikasi='valid' LIMIT 1");
                        $dp_q_adm->execute([$id_pengajuan]);
                        $dp_val = (float)($dp_q_adm->fetchColumn() ?: 0);
                        $harga_rumah = (float)($kpr_detail['harga'] ?? 0);
                        $total_lunas = 0; $total_belum = 0;
                        foreach ($cicilan_detail as $c) {
                            if ($c['status_bayar'] === 'lunas') $total_lunas += $c['jumlah_cicilan'];
                            else $total_belum += $c['jumlah_cicilan'];
                        }
                        $total_sudah_bayar = $dp_val + $total_lunas;
                        $sisa_harga = $harga_rumah - $total_sudah_bayar;
                        $persen_lunas = count($cicilan_detail) > 0 ? round(count(array_filter($cicilan_detail, fn($c) => $c['status_bayar']==='lunas')) / count($cicilan_detail) * 100) : 0;
                        $persen_harga = $harga_rumah > 0 ? round($total_sudah_bayar / $harga_rumah * 100) : 0;
                        ?>
                                <div style="font-size:20px;font-weight:900;color:var(--success);"><?= format_rupiah($total_lunas) ?></div>
                            </div>
                            <div style="text-align:center;">
                                <div style="font-size:13px;color:var(--muted);margin-bottom:4px;">Sisa Cicilan</div>
                                <div style="font-size:20px;font-weight:900;color:var(--danger);"><?= format_rupiah($total_belum) ?></div>
                            </div>
                            <div style="text-align:center;">
                                <div style="font-size:13px;color:var(--muted);margin-bottom:6px;">Progres Pembayaran</div>
                                <div style="height:10px;background:#e2e8f0;border-radius:5px;overflow:hidden;max-width:200px;margin:0 auto 4px;">
                                    <div style="height:100%;width:<?= $persen ?>%;background:linear-gradient(90deg,#10b981,#059669);border-radius:5px;"></div>
                                </div>
                                <small style="font-weight:700;color:var(--success);"><?= $persen ?>% Lunas</small>
                            </div>
                        </div>
                        <div class="tbl-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Bulan</th>
                                        <th>Jatuh Tempo</th>
                                        <th>Pokok</th>
                                        <th>Bunga</th>
                                        <th>Total Cicilan</th>
                                        <th>Status Bayar</th>
                                        <th>Tanggal Bayar</th>
                                        <th style="text-align:center; width:200px;">Aksi Admin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($cicilan_detail as $c): ?>
                                    <?php
                                    $is_late = ($c['status_bayar']==='belum' && strtotime($c['tanggal_jatuh_tempo']) < time());
                                    ?>
                                    <tr style="<?= $is_late ? 'background:#fff5f5;' : '' ?>">
                                        <td><b>Bulan <?= $c['bulan_ke'] ?></b><?= $is_late ? ' <span style="font-size:10px;color:#ef4444;font-weight:700;">⚠️ Terlambat</span>' : '' ?></td>
                                        <td><?= format_tanggal($c['tanggal_jatuh_tempo']) ?></td>
                                        <td><?= format_rupiah($c['pokok']) ?></td>
                                        <td style="color:var(--danger);"><?= format_rupiah($c['bunga']) ?></td>
                                        <td style="font-weight:800;color:var(--primary);"><?= format_rupiah($c['jumlah_cicilan']) ?></td>
                                        <td>
                                            <?php if ($c['status_bayar']==='lunas'): ?>
                                                <span class="badge-lunas">✅ Lunas</span>
                                            <?php elseif ($is_late): ?>
                                                <span class="badge-terlambat">⏰ Terlambat</span>
                                            <?php else: ?>
                                                <span class="badge-belum">⏳ Belum</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $c['tanggal_bayar'] ? format_datetime($c['tanggal_bayar']) : '<span style="color:var(--muted);">-</span>' ?></td>
                                        <td style="text-align:center;">
                                            <?php if ($c['status_verifikasi']==='pending' && $c['tanggal_bayar']): ?>
                                                <div class="aksi-table" style="flex-direction:column;">
                                                    <?php if ($c['bukti_bayar']): ?>
                                                    <a href="../../uploads/bukti_cicilan/<?= htmlspecialchars($c['bukti_bayar']) ?>" target="_blank" class="btn btn-outline btn-sm" style="width:100%;justify-content:center;font-size:11px;">📎 Lihat Bukti</a>
                                                    <?php endif; ?>
                                                    <a href="index.php?action=valid&id=<?= $c['id_cicilan'] ?>&id_pengajuan=<?= $id_pengajuan ?>" class="btn btn-success btn-sm" style="width:100%;justify-content:center;" onclick="return confirm('Konfirmasi cicilan bulan ke-<?= $c['bulan_ke'] ?> VALID?')">✅ Valid</a>
                                                    <a href="index.php?action=tolak&id=<?= $c['id_cicilan'] ?>&id_pengajuan=<?= $id_pengajuan ?>" class="btn-delete" style="width:100%;justify-content:center;" onclick="return confirm('Tolak pembayaran cicilan ini?')">❌ Tolak</a>
                                                </div>
                                            <?php elseif ($c['status_bayar']==='lunas'): ?>
                                                <span style="color:var(--success);font-size:12px;font-weight:700;">✅ Terverifikasi</span>
                                            <?php else: ?>
                                                <span style="color:var(--muted);font-size:12px;">Menunggu Pembayaran</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </main>
    </div>
    <script src="../../assets/js/script.js"></script>
</body>
</html>
