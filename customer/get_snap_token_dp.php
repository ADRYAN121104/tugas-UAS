<?php
// customer/get_snap_token_dp.php
// Endpoint AJAX: Generate Midtrans Snap Token untuk Pembayaran DP
header('Content-Type: application/json');
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';
require_once '../config/midtrans.php';

$id_user      = id_user();
$id_pengajuan = (int)($_GET['id_pengajuan'] ?? 0);

if (!$id_pengajuan) {
    echo json_encode(['status' => 'error', 'message' => 'ID Pengajuan tidak valid.']);
    exit;
}

// Ambil data pengajuan KPR milik user ini
$stmt = $db->prepare("
    SELECT pk.*, p.nama_perumahan, r.blok, r.kode_unit,
           u.nama_lengkap, u.email, u.no_hp
    FROM pengajuan_kpr pk
    JOIN rumah r      ON pk.id_rumah = r.id_rumah
    JOIN perumahan p  ON r.id_perumahan = p.id_perumahan
    JOIN users u      ON pk.id_user = u.id_user
    WHERE pk.id_pengajuan = ? AND pk.id_user = ? AND pk.status_pengajuan = 'disetujui'
");
$stmt->execute([$id_pengajuan, $id_user]);
$pengajuan = $stmt->fetch();

if (!$pengajuan) {
    echo json_encode(['status' => 'error', 'message' => 'Pengajuan tidak ditemukan atau belum disetujui.']);
    exit;
}

// Cek apakah DP sudah dibayar dan valid
$dp_cek = $db->prepare("SELECT status_verifikasi FROM pembayaran_dp WHERE id_pengajuan=? AND status_verifikasi='valid' LIMIT 1");
$dp_cek->execute([$id_pengajuan]);
if ($dp_cek->fetchColumn()) {
    echo json_encode(['status' => 'error', 'message' => 'DP sudah terverifikasi valid.']);
    exit;
}

$gross_amount  = (float)$pengajuan['uang_muka'];
$nama_unit     = $pengajuan['nama_perumahan'] . ' Blok ' . $pengajuan['blok'] . '-' . $pengajuan['kode_unit'];
$customer_name = $pengajuan['nama_lengkap'];
$customer_email= $pengajuan['email'];
$customer_phone= $pengajuan['no_hp'] ?? '08123456789';

$result = get_midtrans_snap_token_dp($id_pengajuan, $gross_amount, $customer_name, $customer_email, $customer_phone, $nama_unit);

if ($result && isset($result['token'])) {
    // Simpan order_id sementara ke session agar bisa diverifikasi saat callback
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['dp_order_id_' . $id_pengajuan] = $result['order_id'];
    
    echo json_encode([
        'status'   => 'success',
        'token'    => $result['token'],
        'order_id' => $result['order_id']
    ]);
} else {
    echo json_encode(['status' => 'offline', 'message' => 'Midtrans tidak tersedia (offline/sandbox). Gunakan simulator.']);
}
?>
