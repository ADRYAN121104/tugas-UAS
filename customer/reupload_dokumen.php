<?php
// customer/reupload_dokumen.php
// Handler AJAX untuk re-upload dokumen KPR yang bermasalah
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'pesan' => 'Method tidak valid.']);
    exit;
}

$id_user       = id_user();
$id_pengajuan  = (int)($_POST['id_pengajuan'] ?? 0);

if (!$id_pengajuan) {
    echo json_encode(['ok' => false, 'pesan' => 'ID pengajuan tidak valid.']);
    exit;
}

// Pastikan pengajuan milik user ini dan statusnya verifikasi_dokumen
$stmt = $db->prepare("SELECT pk.*, d.id_dokumen FROM pengajuan_kpr pk LEFT JOIN dokumen_kpr d ON pk.id_pengajuan = d.id_pengajuan WHERE pk.id_pengajuan = ? AND pk.id_user = ?");
$stmt->execute([$id_pengajuan, $id_user]);
$pengajuan = $stmt->fetch();

if (!$pengajuan) {
    echo json_encode(['ok' => false, 'pesan' => 'Pengajuan tidak ditemukan atau bukan milik Anda.']);
    exit;
}

if ($pengajuan['status_pengajuan'] !== 'verifikasi_dokumen') {
    echo json_encode(['ok' => false, 'pesan' => 'Re-upload hanya diizinkan saat status Verifikasi Dokumen.']);
    exit;
}

// Upload dokumen yang dikirim (hanya yang ada file-nya)
$docs = [];
$dok_map = [
    'ktp'       => '../uploads/ktp',
    'kk'        => '../uploads/kk',
    'slip_gaji' => '../uploads/slip_gaji',
    'npwp'      => '../uploads/ktp',
];

$err_dok = '';
$ada_upload = false;

foreach ($dok_map as $field => $folder) {
    if (!empty($_FILES[$field]['name']) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        $up = upload_file($_FILES[$field], $folder);
        if ($up['ok']) {
            $docs[$field] = $up['nama'];
            $ada_upload = true;
        } else {
            $err_dok .= "Dokumen {$field} gagal: {$up['pesan']} ";
        }
    }
}

if (!$ada_upload) {
    echo json_encode(['ok' => false, 'pesan' => 'Tidak ada dokumen baru yang diupload.']);
    exit;
}

if ($err_dok) {
    echo json_encode(['ok' => false, 'pesan' => $err_dok]);
    exit;
}

try {
    $db->beginTransaction();

    // Update kolom yang diupload di tabel dokumen_kpr
    if ($pengajuan['id_dokumen']) {
        // Update existing record
        $set_parts = [];
        $params    = [];
        foreach ($docs as $field => $nama) {
            $set_parts[] = "{$field} = ?";
            $params[]    = $nama;
        }
        $params[] = $id_pengajuan;
        $db->prepare("UPDATE dokumen_kpr SET " . implode(', ', $set_parts) . " WHERE id_pengajuan = ?")
           ->execute($params);
    } else {
        // Insert baru (seharusnya tidak terjadi, tapi antisipasi)
        $ins = $db->prepare("INSERT INTO dokumen_kpr(id_pengajuan,ktp,kk,slip_gaji,npwp) VALUES(?,?,?,?,?)");
        $ins->execute([
            $id_pengajuan,
            $docs['ktp']       ?? '',
            $docs['kk']        ?? '',
            $docs['slip_gaji'] ?? '',
            $docs['npwp']      ?? '',
        ]);
    }

    // Buat daftar dokumen yang diupload ulang untuk keterangan
    $daftar_dok = implode(', ', array_map('strtoupper', array_keys($docs)));

    // Tambahkan tracking bahwa customer sudah re-upload
    $db->prepare("INSERT INTO tracking_pengajuan(id_pengajuan, status, keterangan, tanggal_update) VALUES(?, 'verifikasi_dokumen', ?, NOW())")
       ->execute([$id_pengajuan, "Customer telah mengupload ulang dokumen: {$daftar_dok}. Menunggu verifikasi ulang oleh admin."]);

    $db->commit();

    echo json_encode(['ok' => true, 'pesan' => 'Dokumen berhasil diupload ulang! Menunggu verifikasi ulang dari admin.']);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['ok' => false, 'pesan' => 'Terjadi kesalahan sistem: ' . $e->getMessage()]);
}
