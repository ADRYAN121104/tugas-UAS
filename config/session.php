<?php
// config/session.php
if (session_status() === PHP_SESSION_NONE) session_start();

function set_flash($tipe, $pesan) {
    $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan];
}
function tampil_flash() {
    if (!isset($_SESSION['flash'])) return;
    $f = $_SESSION['flash']; unset($_SESSION['flash']);
    $warna = [
        'sukses'  => ['#d1fae5','#065f46','✅'],
        'gagal'   => ['#fee2e2','#991b1b','❌'],
        'info'    => ['#dbeafe','#1e40af','ℹ️'],
        'warning' => ['#fef3c7','#92400e','⚠️'],
    ][$f['tipe']] ?? ['#e2e8f0','#334155','📢'];
    echo "<div style='background:{$warna[0]};color:{$warna[1]};padding:12px 18px;border-radius:8px;margin-bottom:20px;font-weight:600;font-family:sans-serif;'>{$warna[2]} ".htmlspecialchars($f['pesan'])."</div>";
}
?>
