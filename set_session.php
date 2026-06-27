<?php
// set_session.php - Helper untuk set session manual (digunakan saat testing atau integrasi)
require_once 'config/session.php';
require_once 'config/koneksi.php';

// Digunakan untuk set session dari luar (misal setelah verifikasi token)
// Parameter: role=admin|customer, user_id=integer
$role    = $_GET['role']    ?? '';
$user_id = (int)($_GET['user_id'] ?? 0);

if ($role && $user_id) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id_user=?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user && in_array($role, ['admin','marketing','customer'])) {
        if (in_array($role, ['admin','marketing'])) {
            $_SESSION['admin'] = [
                'user_id'      => $user['id_user'],
                'nama_lengkap' => $user['nama_lengkap'],
                'email'        => $user['email'],
                'role'         => $user['role'],
            ];
            header('Location: admin/dashboard.php');
        } else {
            $_SESSION['customer'] = [
                'user_id'      => $user['id_user'],
                'nama_lengkap' => $user['nama_lengkap'],
                'email'        => $user['email'],
                'role'         => $user['role'],
            ];
            header('Location: customer/booking_saya.php');
        }
        exit;
    }
}

// Jika tidak ada parameter valid, redirect ke login
header('Location: login.php');
exit;
?>
