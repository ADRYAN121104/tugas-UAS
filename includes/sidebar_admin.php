<?php
// includes/sidebar_admin.php
// Gunakan: sidebar_admin($halaman_aktif)
// Contoh: sidebar_admin('dashboard')

function sidebar_admin($aktif = 'dashboard') {
    $base = '/perumahan_kpr/';
    $menu = [
        ['label'=>'Ke Beranda',      'ico'=>'🏠', 'key'=>'beranda',         'href'=>$base.'index.php'],
        ['label'=>'Dashboard',       'ico'=>'📊', 'key'=>'dashboard',       'href'=>$base.'admin/dashboard.php'],
        ['label'=>'Data Perumahan',  'ico'=>'🏙️', 'key'=>'perumahan',       'href'=>$base.'admin/perumahan/index.php'],
        ['label'=>'Tipe Rumah',      'ico'=>'📐', 'key'=>'tipe_rumah',      'href'=>$base.'admin/tipe_rumah/index.php'],
        ['label'=>'Unit Rumah',      'ico'=>'🚪', 'key'=>'rumah',           'href'=>$base.'admin/rumah/index.php'],
        ['label'=>'Data Customer',   'ico'=>'👤', 'key'=>'customer',        'href'=>$base.'admin/customer/index.php'],
        ['label'=>'Marketing',       'ico'=>'📣', 'key'=>'marketing',       'href'=>$base.'admin/marketing/index.php'],
        ['label'=>'Booking',         'ico'=>'📋', 'key'=>'booking',         'href'=>$base.'admin/booking/index.php'],
        ['label'=>'Pembayaran',      'ico'=>'💰', 'key'=>'pembayaran',      'href'=>$base.'admin/pembayaran/index.php'],
        ['label'=>'Pengajuan KPR',   'ico'=>'📝', 'key'=>'pengajuan_kpr',  'href'=>$base.'admin/pengajuan_kpr/index.php'],
        ['label'=>'Bank Rekanan',    'ico'=>'🏦', 'key'=>'bank',            'href'=>$base.'admin/bank/index.php'],
        ['label'=>'Laporan',         'ico'=>'📈', 'key'=>'laporan',         'href'=>$base.'admin/laporan/penjualan.php'],
    ];
    $nama  = nama_user();
    $inisial = strtoupper(substr($nama, 0, 1));
    $role  = role_user();
    ?>
    <aside class="sidebar" id="sidebar">
        <a href="<?= $base ?>admin/dashboard.php" class="sb-brand">
            <div class="sb-brand-icon">🏠</div>
            <div class="sb-brand-text">
                <strong>RumahKPR</strong>
                <small>Panel Admin</small>
            </div>
        </a>
        <div class="sb-menu">
            <div class="sb-label">Menu Utama</div>
            <?php foreach ($menu as $m): ?>
                <a href="<?= $m['href'] ?>" class="sb-item <?= $aktif === $m['key'] ? 'aktif' : '' ?>">
                    <span class="ico"><?= $m['ico'] ?></span>
                    <?= $m['label'] ?>
                </a>
            <?php endforeach; ?>
        </div>
        <a href="<?= $base ?>logout.php" class="sb-logout">
            <span>🚪</span> Keluar Sistem
        </a>
    </aside>
    <?php
}
?>
