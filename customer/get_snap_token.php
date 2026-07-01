<?php
// customer/get_snap_token.php
header('Content-Type: application/json');

require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';
require_once '../config/midtrans.php';

$id_user = id_user();
$id_booking = (int)($_GET['id_booking'] ?? 0);

if (!$id_booking) {
    echo json_encode(['status' => 'error', 'message' => 'ID Booking tidak valid.']);
    exit;
}

// Ambil info booking, user, dan rumah
$stmt = $db->prepare("
    SELECT b.*, u.nama_lengkap, u.email, u.no_hp 
    FROM booking b 
    JOIN users u ON b.id_user = u.id_user 
    WHERE b.id_booking = ? AND b.id_user = ?
");
$stmt->execute([$id_booking, $id_user]);
$booking = $stmt->fetch();

if (!$booking) {
    echo json_encode(['status' => 'error', 'message' => 'Booking tidak ditemukan.']);
    exit;
}

$gross_amount = $booking['booking_fee'];
$customer_name = $booking['nama_lengkap'];
$customer_email = $booking['email'];
$customer_phone = $booking['no_hp'] ?? '08123456789';

$token = get_midtrans_snap_token($id_booking, $gross_amount, $customer_name, $customer_email, $customer_phone);

if ($token) {
    echo json_encode(['status' => 'success', 'token' => $token]);
} else {
    echo json_encode(['status' => 'offline', 'message' => 'Gagal terhubung ke Midtrans (Offline / Mode Simulator).']);
}
?>
