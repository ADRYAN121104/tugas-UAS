<?php
// customer/booking_saya.php
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';

$id = id_user();

// ── AJAX: Cek apakah ada perubahan status booking (untuk real-time polling) ──
if (isset($_GET['ajax']) && $_GET['ajax'] === 'check_status') {
    header('Content-Type: application/json');
    $stmt = $db->prepare("SELECT id_booking, status_booking FROM booking WHERE id_user = ? ORDER BY id_booking DESC");
    $stmt->execute([$id]);
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // id_booking => status_booking
    echo json_encode($rows);
    exit;
}

$stmt = $db->prepare("
    SELECT b.*, p.nama_perumahan, p.alamat, p.maps_link, r.nama_tipe, r.harga, r.blok, r.kode_unit, r.id_rumah,
           pay.id_pembayaran, pay.jumlah_bayar, pay.status_verifikasi, pay.bukti_bayar,
           k.id_pengajuan, k.status_pengajuan
    FROM booking b 
    JOIN rumah r      ON b.id_rumah = r.id_rumah 
    JOIN perumahan p  ON r.id_perumahan = p.id_perumahan
    LEFT JOIN pembayaran pay ON b.id_booking = pay.id_booking
    LEFT JOIN pengajuan_kpr k ON (b.id_rumah = k.id_rumah AND b.id_user = k.id_user)
    WHERE b.id_user = ? ORDER BY b.id_booking DESC
");
$stmt->execute([$id]);
$bookings = $stmt->fetchAll();

// Build status map for real-time comparison
$status_map = [];
foreach ($bookings as $bk) {
    $status_map[$bk['id_booking']] = $bk['status_booking'];
}

$page_title = 'Booking Saya - KPR Perumahan';
require_once '../includes/header.php';
?>
<main class="container" style="padding:40px 24px 60px;">
    <?php tampil_flash(); ?>

    <!-- Real-time status change notification -->
    <div id="status-changed-bar" style="display:none; background:linear-gradient(135deg,#10b981,#059669); color:#fff; border-radius:12px; padding:14px 20px; margin-bottom:20px; display:none; align-items:center; gap:12px; box-shadow: 0 4px 16px rgba(16,185,129,0.3);">
        <span style="font-size:22px;">🎉</span>
        <div>
            <div style="font-weight:800; font-size:14px;">Status Booking Anda Berubah!</div>
            <div style="font-size:12px; opacity:0.9;">Klik untuk memuat ulang halaman dan melihat status terbaru.</div>
        </div>
        <button onclick="window.location.reload()" style="margin-left:auto; background:rgba(255,255,255,0.25); border:1px solid rgba(255,255,255,0.4); color:#fff; border-radius:8px; padding:6px 14px; cursor:pointer; font-weight:700; font-size:13px;">Refresh Sekarang</button>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:28px;flex-wrap:wrap;gap:12px;">
        <div>
            <h1 class="section-title">📋 Booking Saya</h1>
            <p class="section-sub">Kelola semua pemesanan unit rumah Anda</p>
        </div>
        <a href="../guest/katalog.php" class="btn btn-primary">+ Cari Properti Lain</a>
    </div>

    <!-- ALUR PROSES PEMBELIAN (STEPPER) -->
    <div style="background:#fff; border-radius:16px; border:1px solid #e2e8f0; padding:24px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); margin-bottom:28px;">
        <h3 style="font-size:15px; font-weight:800; margin-bottom:18px; color:#0f172a;">🗺️ Alur Proses Pembelian Rumah via KPR</h3>
        <div style="display:flex; justify-content:space-between; align-items:flex-start; overflow-x:auto; padding-bottom:10px; gap:8px;" id="flow-stepper">
            <div style="flex:1; min-width:120px; display:flex; flex-direction:column; align-items:center; text-align:center;">
                <div style="width:36px; height:36px; border-radius:50%; background:#d1fae5; color:#065f46; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; margin-bottom:8px; border:2px solid #34d399;">1</div>
                <div style="font-size:12px; font-weight:700; color:#065f46; margin-bottom:3px;">Booking Unit</div>
                <p style="font-size:10.5px; color:#64748b; line-height:1.4;">Pilih unit & klik Booking di katalog</p>
            </div>
            <div style="align-self:center; color:#cbd5e1; font-size:16px; padding-top:8px;">➔</div>
            <div style="flex:1; min-width:120px; display:flex; flex-direction:column; align-items:center; text-align:center;">
                <div style="width:36px; height:36px; border-radius:50%; background:#ede9fe; color:#5b21b6; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; margin-bottom:8px; border:2px solid #a78bfa;">2</div>
                <div style="font-size:12px; font-weight:700; color:#6d28d9; margin-bottom:3px;">Ajukan Berkas KPR</div>
                <p style="font-size:10.5px; color:#64748b; line-height:1.4;">Upload KTP, KK, Slip Gaji & data kredit</p>
            </div>
            <div style="align-self:center; color:#cbd5e1; font-size:16px; padding-top:8px;">➔</div>
            <div style="flex:1; min-width:120px; display:flex; flex-direction:column; align-items:center; text-align:center;">
                <div style="width:36px; height:36px; border-radius:50%; background:#e0f2fe; color:#0369a1; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; margin-bottom:8px; border:2px solid #38bdf8;">3</div>
                <div style="font-size:12px; font-weight:700; color:#0369a1; margin-bottom:3px;">Review & Persetujuan</div>
                <p style="font-size:10.5px; color:#64748b; line-height:1.4;">Admin & bank memverifikasi & menyetujui berkas KPR</p>
            </div>
            <div style="align-self:center; color:#cbd5e1; font-size:16px; padding-top:8px;">➔</div>
            <div style="flex:1; min-width:120px; display:flex; flex-direction:column; align-items:center; text-align:center;">
                <div style="width:36px; height:36px; border-radius:50%; background:#fef3c7; color:#92400e; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; margin-bottom:8px; border:2px solid #fbbf24;">4</div>
                <div style="font-size:12px; font-weight:700; color:#b45309; margin-bottom:3px;">Bayar Booking Fee</div>
                <p style="font-size:10.5px; color:#64748b; line-height:1.4;">KPR Disetujui -> Bayar online / upload transfer</p>
            </div>
            <div style="align-self:center; color:#cbd5e1; font-size:16px; padding-top:8px;">➔</div>
            <div style="flex:1; min-width:120px; display:flex; flex-direction:column; align-items:center; text-align:center;">
                <div style="width:36px; height:36px; border-radius:50%; background:#d1fae5; color:#065f46; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; margin-bottom:8px; border:2px solid #34d399;">5</div>
                <div style="font-size:12px; font-weight:700; color:#065f46; margin-bottom:3px;">Unit Dikonfirmasi</div>
                <p style="font-size:10.5px; color:#64748b; line-height:1.4;">Pembayaran divalidasi, booking disetujui & unit dikunci</p>
            </div>
            <div style="align-self:center; color:#cbd5e1; font-size:16px; padding-top:8px;">➔</div>
            <div style="flex:1; min-width:120px; display:flex; flex-direction:column; align-items:center; text-align:center;">
                <div style="width:36px; height:36px; border-radius:50%; background:#fce7f3; color:#9d174d; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:14px; margin-bottom:8px; border:2px solid #f9a8d4;">6</div>
                <div style="font-size:12px; font-weight:700; color:#be185d; margin-bottom:3px;">Akad Kredit</div>
                <p style="font-size:10.5px; color:#64748b; line-height:1.4;">Tanda tangan akad kredit & serah terima kunci unit</p>
            </div>
        </div>
    </div>

    <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
        <div class="tabel-wrap">
            <table class="tabel">
                <thead>
                    <tr>
                        <th>Properti / Unit</th>
                        <th>Lokasi & Peta</th>
                        <th>Tipe & Harga</th>
                        <th>Tgl Booking</th>
                        <th>Booking Fee</th>
                        <th>Status Pembayaran</th>
                        <th>Status Booking</th>
                        <th style="text-align:center; width:200px;">Tindakan Selanjutnya</th>
                    </tr>
                </thead>
                <tbody>
                <?php if(empty($bookings)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center;padding:40px;color:#94a3b8;">
                            <div style="font-size:40px; margin-bottom:12px;">📋</div>
                            <h4 style="color:#475569;">Belum ada booking terdaftar</h4>
                            <p style="margin-bottom:16px;">Cari dan pesan unit rumah impian Anda sekarang</p>
                            <a href="../guest/katalog.php" class="btn btn-primary btn-sm">Lihat Katalog</a>
                        </td>
                    </tr>
                <?php else: foreach($bookings as $b): ?>
                    <?php
                    $sv  = $b['status_verifikasi'] ?? null;   // status pembayaran
                    $sb  = $b['status_booking'];               // status booking
                    $bukti = $b['bukti_bayar'] ?? '';
                    $is_gateway = in_array(strtoupper($bukti), ['GATEWAY','VIA_GATEWAY','VIA_GATEWAY_PENDING','GATEWAY_PENDING']);
                    ?>
                    <tr data-booking-id="<?= $b['id_booking'] ?>" data-status="<?= htmlspecialchars($sb) ?>">
                        <td>
                            <b><?= htmlspecialchars($b['nama_perumahan']) ?></b><br>
                            <small style="color:#2563eb;font-weight:700;">Blok <?= htmlspecialchars($b['blok'].'-'.$b['kode_unit']) ?></small>
                        </td>
                        <td>
                            <small style="color:#64748b; display:block; margin-bottom:4px; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= htmlspecialchars($b['alamat']) ?></small>
                            <?php if ($b['maps_link']): ?>
                                <a href="<?= htmlspecialchars($b['maps_link']) ?>" target="_blank" class="btn btn-white btn-sm" style="padding:2px 8px; font-size:11px; border:1px solid #cbd5e1; display:inline-flex; align-items:center; gap:4px;">🗺️ Buka Maps</a>
                            <?php else: ?>
                                <span style="color:#94a3b8; font-size:11px;">Tidak ada link peta</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($b['nama_tipe']) ?><br>
                            <span style="font-weight:700;color:#10b981;"><?= format_rupiah($b['harga']) ?></span>
                        </td>
                        <td><?= format_tanggal($b['tanggal_booking']) ?></td>
                        <td><?= format_rupiah($b['booking_fee']??0) ?></td>

                        <!-- Kolom Status Pembayaran -->
                        <td>
                            <?php if (!$sv): ?>
                                <span style="background:#f1f5f9; color:#64748b; font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; white-space:nowrap;">📭 Belum Bayar</span>
                            <?php elseif ($sv === 'pending'): ?>
                                <span style="background:#fef3c7; color:#92400e; font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; white-space:nowrap;">⏳ Menunggu Verifikasi</span><br>
                                <small style="color:#64748b; font-size:10px;">Admin akan verifikasi maks 24 jam</small>
                            <?php elseif ($sv === 'valid'): ?>
                                <span style="background:#d1fae5; color:#065f46; font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; white-space:nowrap;">✅ Pembayaran Valid<?= $is_gateway ? ' (Gateway)' : '' ?></span>
                            <?php elseif ($sv === 'ditolak'): ?>
                                <span style="background:#fee2e2; color:#991b1b; font-size:11px; font-weight:700; padding:3px 10px; border-radius:20px; white-space:nowrap;">❌ Pembayaran Ditolak</span>
                            <?php endif; ?>
                        </td>

                        <!-- Kolom Status Booking -->
                        <td><?= badge_booking($sb) ?></td>

                        <!-- Kolom Tindakan -->
                        <td style="text-align:center;">
                            <?php 
                            $kp = $b['status_pengajuan'] ?? null;
                            $id_peng = $b['id_pengajuan'] ?? null;

                            if ($sb === 'menunggu'): ?>
                                <?php if (!$id_peng): ?>
                                    <!-- KPR belum diajukan -->
                                    <div style="display:flex; flex-direction:column; align-items:center; gap:6px;">
                                        <a href="pengajuan_kpr.php?id_rumah=<?= $b['id_rumah'] ?>" class="btn btn-primary btn-sm" style="justify-content:center; width:100%; font-weight:800;">🚀 Ajukan Berkas KPR</a>
                                        <span style="font-size:10px; color:#ef4444; font-weight:700; text-align:center;">⚠️ Upload berkas KPR terlebih dahulu sebelum membayar</span>
                                    </div>
                                <?php elseif (in_array($kp, ['pengajuan_masuk', 'verifikasi_dokumen', 'survey'])): ?>
                                    <!-- KPR sedang direview -->
                                    <div style="display:flex; flex-direction:column; align-items:center; gap:6px;">
                                        <a href="status_kpr.php?id=<?= $id_peng ?>" class="btn btn-outline btn-sm" style="justify-content:center; width:100%;">📊 Lacak Status KPR</a>
                                        <span style="font-size:10px; color:#d97706; font-weight:700; text-align:center;">⏳ KPR sedang direview. Pembayaran dibuka setelah KPR disetujui.</span>
                                    </div>
                                <?php elseif ($kp === 'disetujui'): ?>
                                    <!-- KPR disetujui, saatnya bayar booking fee -->
                                    <?php if (!$sv): ?>
                                        <div style="display:flex; flex-direction:column; align-items:center; gap:6px;">
                                            <a href="upload_pembayaran.php?id=<?= $b['id_booking'] ?>" class="btn btn-accent btn-sm" style="justify-content:center; width:100%; color:#fff; font-weight:800;">💰 Bayar Booking Fee</a>
                                            <span style="font-size:10px; color:#10b981; font-weight:700; text-align:center;">✓ KPR Disetujui! Segera bayar untuk mengunci unit.</span>
                                        </div>
                                    <?php elseif ($sv === 'pending'): ?>
                                        <div style="display:flex; flex-direction:column; align-items:center; gap:6px;">
                                            <span style="font-size:11.5px; color:#92400e; font-weight:700; background:#fef3c7; padding:4px 10px; border-radius:20px; border:1px solid #fcd34d; white-space:nowrap;">⏳ Diverifikasi Admin</span>
                                            <span style="font-size:10px; color:#64748b; text-align:center; line-height:1.3; max-width:180px;">Pembayaran diterima. Sedang diverifikasi admin (maks 24 jam)</span>
                                            <a href="upload_pembayaran.php?id=<?= $b['id_booking'] ?>" style="font-size:10px; color:#64748b; text-decoration:underline;">Kirim ulang bukti</a>
                                        </div>
                                    <?php elseif ($sv === 'ditolak'): ?>
                                        <div style="display:flex; flex-direction:column; align-items:center; gap:6px;">
                                            <a href="upload_pembayaran.php?id=<?= $b['id_booking'] ?>" class="btn btn-sm" style="background:#ef4444; color:#fff; font-weight:700; justify-content:center; width:100%;">⚠️ Bayar Ulang</a>
                                            <span style="font-size:10px; color:#ef4444; font-weight:700; text-align:center;">Bukti pembayaran ditolak admin</span>
                                        </div>
                                    <?php endif; ?>
                                <?php elseif ($kp === 'ditolak'): ?>
                                    <div style="display:flex; flex-direction:column; align-items:center; gap:6px;">
                                        <span style="font-size:11.5px; color:#991b1b; font-weight:700; background:#fee2e2; padding:4px 10px; border-radius:20px; border:1px solid #fca5a5; white-space:nowrap;">❌ KPR Ditolak</span>
                                        <span style="font-size:10px; color:#ef4444; font-weight:700; text-align:center;">Pengajuan KPR ditolak oleh bank/admin</span>
                                    </div>
                                <?php endif; ?>

                            <?php elseif ($sb === 'dikonfirmasi'): ?>
                                <!-- Booking dikonfirmasi -->
                                <div style="display:flex; flex-direction:column; align-items:center; gap:6px;">
                                    <span style="font-size:11.5px; color:#065f46; font-weight:700; background:#d1fae5; padding:4px 10px; border-radius:20px; border:1px solid #a7f3d0; white-space:nowrap;">✅ Booking Aktif</span>
                                    <?php if ($kp === 'disetujui'): ?>
                                        <a href="status_kpr.php?id=<?= $id_peng ?>" class="btn btn-outline btn-sm" style="justify-content:center; width:100%;">📊 Lacak Status KPR (Siap Akad)</a>
                                    <?php elseif ($kp === 'akad_kredit'): ?>
                                        <span style="font-size:10px; color:#059669; font-weight:700;">🎉 Akad Kredit Selesai</span>
                                    <?php endif; ?>
                                </div>

                            <?php elseif ($sb === 'dibatalkan'): ?>
                                <span style="color:#94a3b8; font-size:12px;">Booking dibatalkan</span>

                            <?php else: ?>
                                <span style="color:#94a3b8;">-</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
<?php require_once '../includes/footer.php'; ?>
<script>
// ── Real-time polling: cek perubahan status booking setiap 15 detik ──────────
const knownStatuses = <?= json_encode($status_map) ?>;
let pollingActive = true;

function pollBookingStatus() {
    if (!pollingActive) return;
    fetch('booking_saya.php?ajax=check_status')
        .then(r => r.json())
        .then(current => {
            for (const [id, status] of Object.entries(current)) {
                if (knownStatuses[id] && knownStatuses[id] !== status) {
                    // Status berubah! Tampilkan notifikasi
                    document.getElementById('status-changed-bar').style.display = 'flex';
                    pollingActive = false; // Stop polling, user sudah notif
                    return;
                }
            }
        })
        .catch(() => {});
}

// Mulai polling setelah 15 detik, ulangi tiap 15 detik
setInterval(pollBookingStatus, 15000);
</script>
