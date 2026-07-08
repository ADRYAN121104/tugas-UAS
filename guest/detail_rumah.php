<?php
// guest/detail_rumah.php
require_once '../config/koneksi.php';
require_once '../config/functions.php';
require_once '../config/session.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: katalog.php'); exit; }

$stmt = $db->prepare("SELECT r.*, p.nama_perumahan, p.alamat, p.deskripsi as deskripsi_komplek, p.maps_link,
    t.foto as tipe_foto
    FROM rumah r
    JOIN perumahan p ON r.id_perumahan=p.id_perumahan
    LEFT JOIN tipe_rumah t ON r.id_tipe=t.id_tipe
    WHERE r.id_rumah=?");
$stmt->execute([$id]);
$unit = $stmt->fetch();
if (!$unit) { header('Location: katalog.php'); exit; }

$page_title = 'Detail Unit ' . $unit['nama_perumahan'] . ' - KPR Perumahan';
require_once '../includes/header.php';

// Unit lain di komplek yang sama
$lain = $db->prepare("SELECT r.* FROM rumah r WHERE r.id_perumahan=? AND r.id_rumah!=? AND r.status='tersedia' LIMIT 3");
$lain->execute([$unit['id_perumahan'], $id]);
$unit_lain = $lain->fetchAll();

// Cek status booking user untuk unit ini
$user_booking = null;
if (sudah_login() && role_user() === 'customer') {
    $bcek = $db->prepare("
        SELECT b.id_booking, b.status_booking, k.id_pengajuan, k.status_pengajuan, pay.status_verifikasi
        FROM booking b 
        LEFT JOIN pengajuan_kpr k ON (b.id_rumah = k.id_rumah AND b.id_user = k.id_user)
        LEFT JOIN pembayaran pay ON b.id_booking = pay.id_booking
        WHERE b.id_user = ? AND b.id_rumah = ? AND b.status_booking != 'dibatalkan' 
        LIMIT 1
    ");
    $bcek->execute([id_user(), $id]);
    $user_booking = $bcek->fetch();
}

// Ambil galeri foto dari database
$gstmt = $db->prepare("SELECT foto FROM galeri_rumah WHERE id_rumah = ?");
$gstmt->execute([$id]);
$galeri_db = $gstmt->fetchAll(PDO::FETCH_COLUMN);

// Kumpulkan semua gambar yang valid untuk unit ini
$all_images = [];
if ($unit['foto'] && file_exists('../uploads/tipe_rumah/' . $unit['foto'])) {
    $all_images[] = '../uploads/tipe_rumah/' . $unit['foto'];
}
if (!empty($galeri_db)) {
    foreach ($galeri_db as $g) {
        if (file_exists('../uploads/galeri_rumah/' . $g)) {
            $all_images[] = '../uploads/galeri_rumah/' . $g;
        }
    }
}
// Fallback: coba tipe_rumah.foto
if (empty($all_images) && !empty($unit['tipe_foto']) && file_exists('../uploads/tipe_rumah/' . $unit['tipe_foto'])) {
    $all_images[] = '../uploads/tipe_rumah/' . $unit['tipe_foto'];
}
// Fallback gambar default jika tidak ada foto sama sekali
if (empty($all_images)) {
    if (file_exists('../uploads/tipe_rumah/interior.png'))  $all_images[] = '../uploads/tipe_rumah/interior.png';
    if (file_exists('../uploads/tipe_rumah/kitchen.png'))   $all_images[] = '../uploads/tipe_rumah/kitchen.png';
}
$primary_foto = $all_images[0] ?? '';

// Ambil denah dari database
$dstmt = $db->prepare("SELECT gambar_denah FROM denah_rumah WHERE id_tipe = ?");
$dstmt->execute([$unit['id_tipe']]);
$denah_db = $dstmt->fetchAll(PDO::FETCH_COLUMN);
// Jika kosong, gunakan fallback default gambar denah yang baru saja digenerate
$denah = !empty($denah_db) ? $denah_db[0] : 'denah_sample.png';

$dsrc = file_exists('../uploads/tipe_rumah/' . $denah) ? '../uploads/tipe_rumah/' . $denah : (file_exists('../uploads/denah_rumah/' . $denah) ? '../uploads/denah_rumah/' . $denah : '../uploads/tipe_rumah/' . $denah);
?>
<main class="container" style="padding:40px 24px 60px;">
    <div class="breadcrumb" style="margin-bottom:24px;">
        <a href="../index.php">Beranda</a> <span>›</span>
        <a href="katalog.php">Katalog</a> <span>›</span>
        <span><?= htmlspecialchars($unit['nama_perumahan']) ?></span>
    </div>

    <div style="display:grid;grid-template-columns:1fr 380px;gap:32px;align-items:start;" id="detail-grid">
        <!-- Kiri -->
        <div>
            <!-- Main Photo Area -->
            <div style="height:380px;background:#f1f5f9;border-radius:16px;display:flex;align-items:center;justify-content:center;margin-bottom:12px;position:relative;overflow:hidden;border:1px solid #e2e8f0;" id="mainImageContainer">
                <img id="mainImage" src="<?= htmlspecialchars($primary_foto) ?>" style="width:100%; height:100%; object-fit:cover; transition: opacity 0.3s ease;">
                <span style="position:absolute;top:16px;left:16px;background:<?= $unit['status']==='tersedia'?'#10b981':'#ef4444' ?>;color:#fff;padding:6px 14px;border-radius:20px;font-size:13px;font-weight:700;z-index:2;">
                    <?= $unit['status']==='tersedia' ? '✅ Tersedia' : ($unit['status']==='booking' ? '🔒 Dibooking' : '🏠 Terjual') ?>
                </span>
            </div>

            <!-- Thumbnail Gallery Grid -->
            <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:24px;">
                <?php foreach ($all_images as $index => $img_src): ?>
                    <div class="thumb-item <?= $index === 0 ? 'active' : '' ?>" onclick="changeMainImage(this)" style="cursor:pointer;border-radius:8px;overflow:hidden;height:70px;border:2px solid <?= $index === 0 ? '#2563eb' : 'transparent' ?>;transition:.2s;background:#fff;display:flex;align-items:center;justify-content:center;">
                        <img src="<?= htmlspecialchars($img_src) ?>" style="width:100%; height:100%; object-fit:cover;">
                    </div>
                <?php endforeach; ?>
                
                <!-- Floor Plan / Denah Thumbnail -->
                <?php if ($denah): ?>
                    <div class="thumb-item" onclick="changeMainImage(this)" style="cursor:pointer;border-radius:8px;overflow:hidden;height:70px;border:2px solid transparent;transition:.2s;position:relative;">
                        <img src="<?= $dsrc ?>" style="width:100%; height:100%; object-fit:cover;">
                        <div style="position:absolute;inset:0;background:rgba(0,0,0,0.5);color:#fff;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;letter-spacing:1px;">DENAH</div>
                    </div>
                <?php endif; ?>
            </div>

            <h1 style="font-size:26px;font-weight:800;margin-bottom:6px;"><?= htmlspecialchars($unit['nama_perumahan']) ?></h1>
            <p style="color:#2563eb;font-weight:700;margin-bottom:16px;">📍 <?= htmlspecialchars($unit['alamat']) ?> &nbsp;·&nbsp; Blok <?= htmlspecialchars($unit['blok'].' - '.$unit['kode_unit']) ?></p>

            <!-- Specs Grid -->
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px;">
                <?php $specs=[['📐',$unit['luas_tanah'].' m²','Luas Tanah'],['🏗️',$unit['luas_bangunan'].' m²','Luas Bangunan'],['🛏️',$unit['jumlah_kamar'].' Kamar','Kamar Tidur'],['🚿',$unit['jumlah_kamar_mandi'].' Kamar','Kamar Mandi']];
                foreach($specs as $s): ?>
                <div style="background:#f8fafc;border-radius:10px;padding:16px;text-align:center;border:1px solid #e2e8f0;">
                    <div style="font-size:24px;"><?= $s[0] ?></div>
                    <div style="font-weight:800;font-size:15px;margin:4px 0;"><?= $s[1] ?></div>
                    <div style="font-size:12px;color:#94a3b8;"><?= $s[2] ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="background:#fff;border-radius:12px;padding:22px;border:1px solid #e2e8f0;margin-bottom:20px;">
                <h3 style="font-size:17px;font-weight:800;margin-bottom:12px;">Tipe: <?= htmlspecialchars($unit['nama_tipe']) ?></h3>
                <p style="color:#64748b;line-height:1.7;"><?= htmlspecialchars($unit['deskripsi'] ?? '') ?></p>
            </div>

            <div style="background:#fff;border-radius:12px;padding:22px;border:1px solid #e2e8f0;margin-bottom:20px;">
                <h3 style="font-size:17px;font-weight:800;margin-bottom:12px;">Tentang Komplek</h3>
                <p style="color:#64748b;line-height:1.7;"><?= htmlspecialchars($unit['deskripsi_komplek'] ?? '') ?></p>
            </div>

            <!-- Floor Plan (Denah) Area -->
            <div style="background:#fff;border-radius:12px;padding:22px;border:1px solid #e2e8f0;">
                <h3 style="font-size:17px;font-weight:800;margin-bottom:12px;">📐 Denah Rumah (Floor Plan)</h3>
                <div style="background:#f8fafc;border-radius:10px;padding:20px;text-align:center;border:1px solid #e2e8f0;overflow:hidden;">
                    <img src="<?= $dsrc ?>" style="max-width:100%;max-height:400px;border-radius:8px;object-fit:contain;box-shadow:0 4px 12px rgba(0,0,0,0.05);">
                </div>
            </div>

            <!-- ===== SIMULASI KPR INLINE ===== -->
            <div style="background:linear-gradient(135deg,#0f172a,#1e3a8a,#2563eb);border-radius:16px;padding:28px;margin-top:20px;position:relative;overflow:hidden;">
                <div style="position:absolute;top:-30px;right:-30px;width:150px;height:150px;border-radius:50%;background:rgba(255,255,255,.04);"></div>
                <div style="position:absolute;bottom:-40px;left:-20px;width:120px;height:120px;border-radius:50%;background:rgba(255,255,255,.03);"></div>
                <div style="position:relative;z-index:1;">
                    <h3 style="font-size:17px;font-weight:800;color:#fff;margin-bottom:4px;">🧮 Simulasi Cicilan KPR</h3>
                    <p style="font-size:13px;color:rgba(255,255,255,.6);margin-bottom:20px;">Estimasi cicilan untuk unit ini</p>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                        <div>
                            <label style="font-size:11px;color:rgba(255,255,255,.6);font-weight:700;display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;">Harga Properti</label>
                            <input type="text" id="sim_harga" value="<?= number_format($unit['harga'],0,',','.') ?>" readonly
                                   style="width:100%;padding:10px 14px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:9px;color:rgba(255,255,255,.8);font-weight:700;font-size:13px;font-family:inherit;">
                        </div>
                        <div>
                            <label style="font-size:11px;color:rgba(255,255,255,.6);font-weight:700;display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;">Uang Muka / DP</label>
                            <input type="text" id="sim_dp" placeholder="Contoh: 50.000.000"
                                   style="width:100%;padding:10px 14px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);border-radius:9px;color:#fff;font-size:13px;font-family:inherit;outline:none;"
                                   oninput="formatSimDP(this)" onkeyup="hitungSim()">
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                        <div>
                            <label style="font-size:11px;color:rgba(255,255,255,.6);font-weight:700;display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;">Bunga / Tahun (%)</label>
                            <input type="number" id="sim_bunga" value="7.5" min="0" max="30" step="0.1" onchange="hitungSim()" oninput="hitungSim()"
                                   style="width:100%;padding:10px 14px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);border-radius:9px;color:#fff;font-size:13px;font-family:inherit;outline:none;">
                        </div>
                        <div>
                            <label style="font-size:11px;color:rgba(255,255,255,.6);font-weight:700;display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;">Tenor Kredit</label>
                            <select id="sim_tenor" onchange="hitungSim()"
                                    style="width:100%;padding:10px 14px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);border-radius:9px;color:#fff;font-size:13px;font-family:inherit;outline:none;">
                                <?php for($t=5;$t<=30;$t+=5): ?>
                                <option value="<?= $t ?>" <?= $t==15?'selected':'' ?> style="background:#1e3a8a;"><?= $t ?> Tahun</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Hasil -->
                    <div style="background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);border-radius:12px;padding:16px;">
                        <div style="font-size:11px;color:rgba(255,255,255,.6);font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Cicilan Per Bulan</div>
                        <div style="font-size:30px;font-weight:900;color:#fff;letter-spacing:-.5px;" id="sim_cicilan">-</div>
                        <div id="sim_badge" style="display:inline-flex;align-items:center;gap:5px;background:rgba(37,99,235,.3);border:1px solid rgba(37,99,235,.4);padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;color:#93c5fd;margin-top:6px;">
                            📈 Bunga 7.5% / Tahun · 15 Tahun
                        </div>
                        <div style="margin-top:14px;padding-top:12px;border-top:1px solid rgba(255,255,255,.1);display:flex;flex-direction:column;gap:6px;">
                            <div style="display:flex;justify-content:space-between;font-size:12.5px;">
                                <span style="color:rgba(255,255,255,.6);">Pinjaman Pokok</span>
                                <span style="color:#fff;font-weight:700;" id="sim_pokok">-</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:12.5px;">
                                <span style="color:rgba(255,255,255,.6);">Total Bunga</span>
                                <span style="color:#fbbf24;font-weight:700;" id="sim_bunga_total">-</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:12.5px;padding-top:6px;border-top:1px solid rgba(255,255,255,.08);">
                                <span style="color:rgba(255,255,255,.6);">Total Pembayaran</span>
                                <span style="color:#fff;font-weight:800;" id="sim_total">-</span>
                            </div>
                        </div>
                        <p style="font-size:11px;color:rgba(255,255,255,.4);margin-top:12px;font-style:italic;">* Estimasi. Angka aktual tergantung kebijakan bank.</p>
                    </div>
                </div>
            </div>
        </div>



        <!-- Kanan - Harga & Aksi -->
        <div style="position:sticky;top:80px;">
            <div style="background:#fff;border-radius:16px;padding:26px;border:1px solid #e2e8f0;box-shadow:0 4px 20px rgba(0,0,0,.06);">
                <div style="font-size:28px;font-weight:800;color:#2563eb;margin-bottom:6px;"><?= format_rupiah($unit['harga']) ?></div>
                <p style="font-size:13px;color:#94a3b8;margin-bottom:20px;">Harga sebelum negosiasi</p>

                <div style="background:#f8fafc;border-radius:8px;padding:14px;margin-bottom:20px;">
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px;">
                        <span style="color:#64748b;">Kode Unit</span><span style="font-weight:700;"><?= htmlspecialchars($unit['kode_unit']) ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:8px;">
                        <span style="color:#64748b;">Blok</span><span style="font-weight:700;"><?= htmlspecialchars($unit['blok']) ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:13px;">
                        <span style="color:#64748b;">Status</span>
                        <span style="font-weight:700;color:<?= $unit['status']==='tersedia'?'#10b981':'#ef4444' ?>">
                            <?= ucfirst($unit['status']) ?>
                        </span>
                    </div>
                </div>

                <?php if ($unit['status'] === 'tersedia'): ?>
                    <?php if (sudah_login() && role_user()==='customer'): ?>
                        <?php if (!$user_booking): ?>
                            <a href="../customer/booking_baru.php?id_rumah=<?= $unit['id_rumah'] ?>" class="btn btn-primary btn-block btn-lg" style="justify-content:center;margin-bottom:10px;">🔑 Booking Unit Ini</a>
                        <?php elseif ($user_booking['status_booking'] === 'menunggu'): ?>
                            <a href="../customer/upload_pembayaran.php?id=<?= $user_booking['id_booking'] ?>" class="btn btn-accent btn-block btn-lg" style="justify-content:center;margin-bottom:10px;color:#fff;">⏳ Upload Pembayaran</a>
                        <?php elseif ($user_booking['status_booking'] === 'dikonfirmasi'): ?>
                            <a href="../customer/pengajuan_kpr.php?id_rumah=<?= $unit['id_rumah'] ?>" class="btn btn-success btn-block btn-lg" style="justify-content:center;margin-bottom:10px;">🚀 Ajukan KPR</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="../login.php" class="btn btn-primary btn-block btn-lg" style="justify-content:center;margin-bottom:10px;">🔐 Login untuk Booking</a>
                    <?php endif; ?>
                    <a href="simulasi_kpr.php?harga=<?= $unit['harga'] ?>" class="btn btn-outline btn-block" style="justify-content:center;">🧮 Simulasi KPR</a>
                <?php else: ?>
                    <?php if (sudah_login() && role_user()==='customer' && $user_booking && $user_booking['status_booking'] === 'dikonfirmasi'): ?>
                        <a href="../customer/pengajuan_kpr.php?id_rumah=<?= $unit['id_rumah'] ?>" class="btn btn-success btn-block btn-lg" style="justify-content:center;margin-bottom:10px;">🚀 Ajukan KPR</a>
                    <?php else: ?>
                        <div style="text-align:center;padding:16px;background:#fee2e2;border-radius:8px;color:#991b1b;font-weight:700;">Unit ini sudah tidak tersedia</div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ($unit['maps_link']): ?>
            <div style="background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;margin-top:16px;">
                <div style="padding:14px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
                    <h4 style="font-size:14px;font-weight:700;margin:0;display:flex;align-items:center;gap:6px;">📍 Lokasi di Google Maps</h4>
                    <a href="<?= htmlspecialchars($unit['maps_link']) ?>" target="_blank" style="font-size:12px;color:#2563eb;font-weight:700;text-decoration:none;">Buka di Maps →</a>
                </div>
                <?php
                // Coba embed Google Maps dari link yang ada
                $maps_url = $unit['maps_link'];
                // Ubah link google maps biasa ke embed
                $embed_url = '';
                if (strpos($maps_url, 'maps.google') !== false || strpos($maps_url, 'google.com/maps') !== false) {
                    // Jika ada @lat,lng di URL
                    if (preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $maps_url, $m)) {
                        $embed_url = "https://maps.google.com/maps?q={$m[1]},{$m[2]}&z=16&output=embed";
                    } elseif (strpos($maps_url, '/place/') !== false) {
                        // link /place/
                        $embed_url = str_replace('/maps/place/', '/maps/embed?pb=!1m18!1m12!1m3!1d1000&q=', $maps_url);
                        $embed_url = $maps_url; // fallback
                    } else {
                        $embed_url = "https://maps.google.com/maps?q=" . urlencode($unit['alamat']) . "&z=15&output=embed";
                    }
                } else {
                    $embed_url = "https://maps.google.com/maps?q=" . urlencode($unit['alamat']) . "&z=15&output=embed";
                }
                ?>
                <iframe
                    src="<?= htmlspecialchars($embed_url) ?>"
                    width="100%" height="220"
                    style="border:0;display:block;"
                    allowfullscreen="" loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
            <?php else: ?>
            <div style="background:#fff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0;margin-top:16px;">
                <div style="padding:14px 18px;border-bottom:1px solid #f1f5f9;">
                    <h4 style="font-size:14px;font-weight:700;margin:0;">📍 Lokasi</h4>
                </div>
                <iframe
                    src="https://maps.google.com/maps?q=<?= urlencode($unit['alamat']) ?>&z=14&output=embed"
                    width="100%" height="220"
                    style="border:0;display:block;"
                    allowfullscreen="" loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <?php if (!empty($unit_lain)): ?>
    <div style="margin-top:48px;">
        <h3 style="font-size:20px;font-weight:800;margin-bottom:20px;">Unit Lain di Komplek Ini</h3>
        <div class="grid-3">
            <?php foreach($unit_lain as $ul): ?>
            <div class="kartu">
                <div class="kartu-img" style="background:#f1f5f9; display:flex; align-items:center; justify-content:center; overflow:hidden;">
                    <?php if ($ul['foto'] && file_exists('../uploads/tipe_rumah/' . $ul['foto'])): ?>
                        <img src="../uploads/tipe_rumah/<?= htmlspecialchars($ul['foto']) ?>" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <div style="background:linear-gradient(135deg,#1e3a8a,#2563eb); width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-size:40px; color:#fff;">🏠</div>
                    <?php endif; ?>
                </div>
                <div class="kartu-body">
                    <h3 class="kartu-title">Blok <?= htmlspecialchars($ul['blok'].' - '.$ul['kode_unit']) ?></h3>
                    <p class="kartu-loc"><?= htmlspecialchars($ul['nama_tipe']) ?></p>
                    <div class="kartu-price"><?= format_rupiah($ul['harga']) ?></div>
                    <a href="detail_rumah.php?id=<?= $ul['id_rumah'] ?>" class="btn btn-outline btn-sm btn-block" style="justify-content:center;">Lihat Detail</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</main>
<script>
function changeMainImage(elem) {
    document.querySelectorAll('.thumb-item').forEach(item => {
        item.style.borderColor = 'transparent';
    });
    elem.style.borderColor = '#2563eb';
    const img = elem.querySelector('img');
    if (img) {
        const newSrc = img.getAttribute('src');
        const mainImage = document.getElementById('mainImage');
        mainImage.style.opacity = '0';
        setTimeout(() => {
            mainImage.setAttribute('src', newSrc);
            mainImage.style.opacity = '1';
        }, 150);
    }
}

// ===== Simulasi KPR Inline =====
function formatSimDP(el) {
    const raw = el.value.replace(/\D/g, '');
    el.value = raw ? parseInt(raw).toLocaleString('id-ID') : '';
    hitungSim();
}

function hitungSim() {
    const hargaRaw = document.getElementById('sim_harga').value.replace(/\./g,'').replace(/,/g,'');
    const dpRaw    = document.getElementById('sim_dp').value.replace(/\./g,'').replace(/,/g,'');
    const bunga    = parseFloat(document.getElementById('sim_bunga').value) || 0;
    const tenor    = parseInt(document.getElementById('sim_tenor').value);

    const harga = parseFloat(hargaRaw) || 0;
    const dp    = parseFloat(dpRaw) || 0;
    const pokok = harga - dp;
    const i     = (bunga / 100) / 12;   // bunga per bulan
    const n     = tenor * 12;            // total bulan

    // Formula annuity
    let cicilan;
    if (i === 0 || pokok <= 0) {
        cicilan = pokok > 0 && n > 0 ? pokok / n : 0;
    } else {
        cicilan = pokok * i * Math.pow(1+i,n) / (Math.pow(1+i,n) - 1);
    }
    cicilan = Math.round(cicilan);

    const totalBayar = cicilan * n;
    const totalBunga = totalBayar - pokok;

    const fmt = v => 'Rp ' + Math.round(v).toLocaleString('id-ID');

    document.getElementById('sim_cicilan').textContent = cicilan > 0 ? fmt(cicilan) : 'Rp 0';
    document.getElementById('sim_pokok').textContent   = pokok > 0 ? fmt(pokok) : '-';
    document.getElementById('sim_bunga_total').textContent = totalBunga > 0 ? fmt(totalBunga) : '-';
    document.getElementById('sim_total').textContent   = totalBayar > 0 ? fmt(totalBayar) : '-';

    // Update badge bunga
    const badge = document.getElementById('sim_badge');
    if (badge) badge.textContent = `📈 Bunga ${bunga}% / Tahun · ${tenor} Tahun`;
}

// Jalankan saat halaman load
hitungSim();

</script>
<style>
@media(max-width:768px){#detail-grid{grid-template-columns:1fr!important;}}
.thumb-item:hover {
    border-color: #93c5fd !important;
}
</style>
<?php require_once '../includes/footer.php'; ?>
