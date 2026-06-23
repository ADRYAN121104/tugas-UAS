<?php
// guest/detail_rumah.php
require_once '../config/koneksi.php';
require_once '../config/functions.php';
require_once '../config/session.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: katalog.php'); exit; }

$stmt = $db->prepare("SELECT r.*,p.nama_perumahan,p.alamat,p.deskripsi as deskripsi_komplek,p.maps_link,
    t.nama_tipe,t.luas_tanah,t.luas_bangunan,t.jumlah_kamar,t.jumlah_kamar_mandi,t.harga,t.deskripsi as deskripsi_tipe,t.foto,t.id_tipe
    FROM rumah r JOIN perumahan p ON r.id_perumahan=p.id_perumahan JOIN tipe_rumah t ON r.id_tipe=t.id_tipe WHERE r.id_rumah=?");
$stmt->execute([$id]);
$unit = $stmt->fetch();
if (!$unit) { header('Location: katalog.php'); exit; }

$page_title = 'Detail Unit ' . $unit['nama_perumahan'] . ' - KPR Perumahan';
require_once '../includes/header.php';

// Unit lain di komplek yang sama
$lain = $db->prepare("SELECT r.*,t.nama_tipe,t.harga,t.foto FROM rumah r JOIN tipe_rumah t ON r.id_tipe=t.id_tipe WHERE r.id_perumahan=? AND r.id_rumah!=? AND r.status='tersedia' LIMIT 3");
$lain->execute([$unit['id_perumahan'], $id]);
$unit_lain = $lain->fetchAll();

// Cek status booking user untuk unit ini
$user_booking = null;
if (sudah_login() && role_user() === 'customer') {
    $bcek = $db->prepare("SELECT id_booking, status_booking FROM booking WHERE id_user = ? AND id_rumah = ? AND status_booking != 'dibatalkan' LIMIT 1");
    $bcek->execute([id_user(), $id]);
    $user_booking = $bcek->fetch();
}

// Ambil galeri foto dari database
$gstmt = $db->prepare("SELECT foto FROM galeri_rumah WHERE id_rumah = ?");
$gstmt->execute([$id]);
$galeri_db = $gstmt->fetchAll(PDO::FETCH_COLUMN);
// Jika galeri kosong, kita gunakan fallback default gambar interior & kitchen yang baru saja digenerate
$galeri = !empty($galeri_db) ? $galeri_db : ['interior.png', 'kitchen.png'];

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
                <?php if ($unit['foto'] && file_exists('../uploads/tipe_rumah/' . $unit['foto'])): ?>
                    <img id="mainImage" src="../uploads/tipe_rumah/<?= htmlspecialchars($unit['foto']) ?>" style="width:100%; height:100%; object-fit:cover; transition: opacity 0.3s ease;">
                <?php else: ?>
                    <img id="mainImage" src="../uploads/tipe_rumah/interior.png" style="width:100%; height:100%; object-fit:cover; transition: opacity 0.3s ease;">
                <?php endif; ?>
                <span style="position:absolute;top:16px;left:16px;background:<?= $unit['status']==='tersedia'?'#10b981':'#ef4444' ?>;color:#fff;padding:6px 14px;border-radius:20px;font-size:13px;font-weight:700;z-index:2;">
                    <?= $unit['status']==='tersedia' ? '✅ Tersedia' : ($unit['status']==='booking' ? '🔒 Dibooking' : '🏠 Terjual') ?>
                </span>
            </div>

            <!-- Thumbnail Gallery Grid -->
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:24px;">
                <!-- Main Image Thumbnail -->
                <div class="thumb-item active" onclick="changeMainImage(this)" style="cursor:pointer;border-radius:8px;overflow:hidden;height:70px;border:2px solid #2563eb;transition:.2s;background:#fff;display:flex;align-items:center;justify-content:center;">
                    <?php if ($unit['foto'] && file_exists('../uploads/tipe_rumah/' . $unit['foto'])): ?>
                        <img src="../uploads/tipe_rumah/<?= htmlspecialchars($unit['foto']) ?>" style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                        <img src="../uploads/tipe_rumah/interior.png" style="width:100%; height:100%; object-fit:cover; display:none;">
                        <span style="font-size:24px;">🏠</span>
                    <?php endif; ?>
                </div>
                <!-- Additional Images -->
                <?php foreach ($galeri as $g): 
                    $src = file_exists('../uploads/tipe_rumah/' . $g) ? '../uploads/tipe_rumah/' . $g : (file_exists('../uploads/galeri_rumah/' . $g) ? '../uploads/galeri_rumah/' . $g : '../uploads/tipe_rumah/' . $g);
                ?>
                    <div class="thumb-item" onclick="changeMainImage(this)" style="cursor:pointer;border-radius:8px;overflow:hidden;height:70px;border:2px solid transparent;transition:.2s;">
                        <img src="<?= $src ?>" style="width:100%; height:100%; object-fit:cover;">
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
                <p style="color:#64748b;line-height:1.7;"><?= htmlspecialchars($unit['deskripsi_tipe'] ?? '') ?></p>
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
            <div style="background:#fff;border-radius:12px;padding:18px;border:1px solid #e2e8f0;margin-top:16px;">
                <h4 style="font-size:14px;font-weight:700;margin-bottom:12px;">📍 Lokasi</h4>
                <a href="<?= htmlspecialchars($unit['maps_link']) ?>" target="_blank" class="btn btn-gray btn-block" style="justify-content:center;">🗺️ Buka Google Maps</a>
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
    // Reset borders
    document.querySelectorAll('.thumb-item').forEach(item => {
        item.style.borderColor = 'transparent';
    });
    // Set active border
    elem.style.borderColor = '#2563eb';
    // Get image source from thumbnail
    const img = elem.querySelector('img');
    if (img) {
        const newSrc = img.getAttribute('src');
        const mainImage = document.getElementById('mainImage');
        // Fade out
        mainImage.style.opacity = '0';
        setTimeout(() => {
            mainImage.setAttribute('src', newSrc);
            // Fade in
            mainImage.style.opacity = '1';
        }, 150);
    }
}
</script>
<style>
@media(max-width:768px){#detail-grid{grid-template-columns:1fr!important;}}
.thumb-item:hover {
    border-color: #93c5fd !important;
}
</style>
<?php require_once '../includes/footer.php'; ?>
