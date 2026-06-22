<?php
// config/functions.php

function format_rupiah($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }

function format_tanggal($t) {
    if (empty($t) || $t === '0000-00-00') return '-';
    $b = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $ts = strtotime($t);
    return date('d', $ts) . ' ' . $b[(int)date('m', $ts)] . ' ' . date('Y', $ts);
}

function format_datetime($t) {
    if (empty($t)) return '-';
    return format_tanggal($t) . ' ' . date('H:i', strtotime($t)) . ' WIB';
}

function hitung_cicilan($harga, $dp, $bunga_persen, $tenor_tahun) {
    $pokok = $harga - $dp;
    $i = ($bunga_persen / 100) / 12;
    $n = $tenor_tahun * 12;
    if ($i == 0) return $pokok / $n;
    return round($pokok * $i * pow(1+$i,$n) / (pow(1+$i,$n)-1));
}

function upload_file($file, $folder) {
    if ($file['error'] !== UPLOAD_ERR_OK) return ['ok'=>false,'pesan'=>'Upload gagal.'];
    if ($file['size'] > 5*1024*1024) return ['ok'=>false,'pesan'=>'File max 5MB.'];
    $mime = mime_content_type($file['tmp_name']);
    $tipe_ok = ['image/jpeg','image/png','image/jpg','application/pdf'];
    if (!in_array($mime, $tipe_ok)) return ['ok'=>false,'pesan'=>'Tipe file tidak diizinkan.'];
    
    // Deteksi gambar asli/palsu
    if (strpos($mime, 'image/') === 0) {
        if (!@getimagesize($file['tmp_name'])) {
            return ['ok'=>false,'pesan'=>'Berkas gambar rusak atau palsu.'];
        }
    }
    
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $nama = uniqid('kpr_',true).'.'.$ext;
    if (!is_dir($folder)) mkdir($folder, 0755, true);
    if (move_uploaded_file($file['tmp_name'], $folder.'/'.$nama)) return ['ok'=>true,'nama'=>$nama];
    return ['ok'=>false,'pesan'=>'Gagal menyimpan file.'];
}

function badge_booking($s) {
    $m = ['menunggu'=>['#fef3c7','#92400e','⏳ Menunggu'],'dikonfirmasi'=>['#d1fae5','#065f46','✅ Dikonfirmasi'],'dibatalkan'=>['#fee2e2','#991b1b','❌ Dibatalkan']];
    $v = $m[$s]??['#e2e8f0','#334155',ucfirst($s)];
    return "<span style='background:{$v[0]};color:{$v[1]};padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;'>{$v[2]}</span>";
}
function badge_pembayaran($s) {
    $m = ['pending'=>['#fef3c7','#92400e','⏳ Pending'],'valid'=>['#d1fae5','#065f46','✅ Valid'],'ditolak'=>['#fee2e2','#991b1b','❌ Ditolak']];
    $v = $m[$s]??['#e2e8f0','#334155',ucfirst($s)];
    return "<span style='background:{$v[0]};color:{$v[1]};padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;'>{$v[2]}</span>";
}
function badge_kpr($s) {
    $m = [
        'pengajuan_masuk'    =>['#dbeafe','#1e40af','📥 Pengajuan Masuk'],
        'verifikasi_dokumen' =>['#fef3c7','#92400e','📋 Verifikasi Dokumen'],
        'survey'             =>['#ede9fe','#5b21b6','🔍 Survey'],
        'disetujui'          =>['#d1fae5','#065f46','✅ Disetujui'],
        'akad_kredit'        =>['#a7f3d0','#064e3b','🤝 Akad Kredit'],
        'ditolak'            =>['#fee2e2','#991b1b','❌ Ditolak'],
    ];
    $v = $m[$s]??['#e2e8f0','#334155',strtoupper(str_replace('_',' ',$s))];
    return "<span style='background:{$v[0]};color:{$v[1]};padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;'>{$v[2]}</span>";
}
function badge_unit($s) {
    $m = ['tersedia'=>['#d1fae5','#065f46','✅ Tersedia'],'booking'=>['#fef3c7','#92400e','🔒 Booking'],'terjual'=>['#fee2e2','#991b1b','🏠 Terjual']];
    $v = $m[$s]??['#e2e8f0','#334155',ucfirst($s)];
    return "<span style='background:{$v[0]};color:{$v[1]};padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;'>{$v[2]}</span>";
}
?>
