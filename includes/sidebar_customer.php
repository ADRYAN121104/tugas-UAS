<?php
// includes/sidebar_customer.php
// Gunakan: sidebar_customer($halaman_aktif)

function sidebar_customer($aktif = 'dashboard') {
    $base = '/perumahan_kpr/';
    $menu = [
        ['label'=>'Ke Beranda',        'ico'=>'🏠', 'key'=>'beranda',           'href'=>$base.'index.php'],
        ['label'=>'Dashboard',         'ico'=>'📊', 'key'=>'dashboard',          'href'=>$base.'customer/dashboard.php'],
        ['label'=>'Booking Saya',      'ico'=>'📋', 'key'=>'booking_saya',       'href'=>$base.'customer/booking_saya.php'],
        ['label'=>'Riwayat Booking',   'ico'=>'🕐', 'key'=>'riwayat_booking',    'href'=>$base.'customer/riwayat_booking.php'],
        ['label'=>'Pembayaran DP',     'ico'=>'💰', 'key'=>'pembayaran',         'href'=>$base.'customer/bayar_dp.php'],
        ['label'=>'Pengajuan KPR',     'ico'=>'📝', 'key'=>'pengajuan_kpr',     'href'=>$base.'customer/pengajuan_kpr.php'],
        ['label'=>'Status KPR',        'ico'=>'📈', 'key'=>'status_kpr',        'href'=>$base.'customer/status_kpr.php'],
        ['label'=>'Cicilan KPR',       'ico'=>'💳', 'key'=>'cicilan',           'href'=>$base.'customer/cicilan.php'],
        ['label'=>'Profil Saya',       'ico'=>'👤', 'key'=>'profil',            'href'=>$base.'customer/profil.php'],
    ];
    $nama    = nama_user();
    $inisial = strtoupper(substr($nama, 0, 1));
    ?>
    <aside class="csidebar" id="csidebar">
        <a href="<?= $base ?>index.php" class="csb-brand">
            <strong>🏠 RumahKPR</strong>
            <small>Portal Customer</small>
        </a>
        <div class="csb-profile">
            <div class="csb-avatar"><?= $inisial ?></div>
            <div class="csb-profile-info">
                <strong><?= htmlspecialchars($nama) ?></strong>
                <small>Customer</small>
            </div>
        </div>
        <div class="csb-menu">
            <?php foreach ($menu as $m): ?>
                <a href="<?= $m['href'] ?>" class="csb-item <?= $aktif === $m['key'] ? 'aktif' : '' ?>">
                    <span class="ico"><?= $m['ico'] ?></span>
                    <?= $m['label'] ?>
                </a>
            <?php endforeach; ?>
        </div>
        <a href="<?= $base ?>logout.php" class="csb-logout">
            <span>🚪</span> Keluar
        </a>
    </aside>
    <?php
}
?>
