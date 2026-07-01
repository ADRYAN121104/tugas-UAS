<?php
// customer/upload_pembayaran.php
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';

$id_booking = (int)($_GET['id'] ?? 0);
$id_user = id_user();

// Validasi booking milik user ini (tarik info perumahan & maps_link juga)
$stmt = $db->prepare("
    SELECT b.*, p.nama_perumahan, p.alamat, p.maps_link, r.nama_tipe, r.harga, r.blok, r.kode_unit,
           k.status_pengajuan, k.id_pengajuan
    FROM booking b 
    JOIN rumah r ON b.id_rumah=r.id_rumah 
    JOIN perumahan p ON r.id_perumahan=p.id_perumahan 
    LEFT JOIN pengajuan_kpr k ON (b.id_rumah = k.id_rumah AND b.id_user = k.id_user)
    WHERE b.id_booking=? AND b.id_user=?
");
$stmt->execute([$id_booking, $id_user]);
$booking = $stmt->fetch();

if (!$booking) { 
    set_flash('gagal','Booking tidak ditemukan.'); 
    header('Location: booking_saya.php'); 
    exit; 
}

if (($booking['status_pengajuan'] ?? '') !== 'disetujui') {
    set_flash('gagal', 'Pembayaran belum diizinkan. Pengajuan KPR Anda untuk unit ini harus disetujui oleh Bank/Admin terlebih dahulu.');
    header('Location: booking_saya.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $jumlah = (float)str_replace(['.','Rp',' '],'',$_POST['jumlah_bayar']??'0');
    if ($jumlah <= 0) { 
        $error = 'Jumlah bayar tidak valid.'; 
    }
    elseif ($jumlah < $booking['booking_fee']) { 
        $error = 'Jumlah pembayaran kurang dari Booking Fee yang ditentukan (minimal ' . format_rupiah($booking['booking_fee']) . ').'; 
    }
    elseif (empty($_FILES['bukti_bayar']['name'])) { 
        $error = 'Bukti pembayaran wajib diupload.'; 
    }
    else {
        $upload = upload_file($_FILES['bukti_bayar'], '../uploads/bukti_bayar');
        if (!$upload['ok']) { 
            $error = $upload['pesan']; 
        }
        else {
            // Hapus pembayaran lama jika ada
            $db->prepare("DELETE FROM pembayaran WHERE id_booking=?")->execute([$id_booking]);
            $ins = $db->prepare("INSERT INTO pembayaran(id_booking,tanggal_bayar,jumlah_bayar,bukti_bayar,status_verifikasi) VALUES(?,NOW(),?,?,'pending')");
            $ins->execute([$id_booking, $jumlah, $upload['nama']]);
            set_flash('sukses','Bukti pembayaran berhasil diupload! Menunggu verifikasi admin.');
            header('Location: pembayaran.php'); 
            exit;
        }
    }
}

$page_title = 'Upload Pembayaran - KPR Perumahan';
require_once '../includes/header.php';
?>
<main class="container" style="padding:40px 24px 60px;">
    <?php tampil_flash(); ?>
    <div style="margin-bottom:24px;">
        <h1 class="section-title">💳 Upload Bukti Pembayaran</h1>
        <p class="section-sub">Silakan unggah bukti transfer pembayaran booking fee Anda</p>
    </div>

    <!-- PANDUAN PENTING PROSES TRANSFER & VERIFIKASI -->
    <div style="background:linear-gradient(135deg,#eff6ff,#dbeafe); border-left:5px solid #2563eb; border-radius:14px; padding:18px 22px; margin-bottom:28px; box-shadow: 0 4px 12px rgba(37,99,235,0.05);">
        <h3 style="font-size:14.5px; font-weight:800; color:#1e40af; margin-bottom:10px;">💡 Panduan Pengisian & Pembayaran</h3>
        <ol style="font-size:13px; color:#1e3a8a; padding-left:20px; line-height:1.6; margin:0;">
            <li>Silakan transfer nominal booking fee sebesar <b><?= format_rupiah($booking['booking_fee'] ?? 2000000) ?></b> ke salah satu rekening developer yang tercantum di samping kanan.</li>
            <li>Pastikan Anda menulis berita transfer: <b>#BKN-<?= $booking['id_booking'] ?></b> agar mempercepat validasi.</li>
            <li>Foto atau Screenshot bukti transfer Anda, lalu upload melalui form di bawah ini. Setelah Anda kirim, Tim Admin akan memverifikasi dalam waktu <b>maksimal 1x24 jam</b>. Jika valid, status booking Anda akan berubah menjadi <b>Dikonfirmasi</b> dan tombol <b>Ajukan KPR</b> akan terbuka secara otomatis di menu booking.</li>
        </ol>
    </div>

    <!-- Midtrans Snap library -->
    <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="SB-Mid-client-nS6gS96e4y-s9jV-"></script>

    <div style="display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start;" id="upload-grid">
        <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:24px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
            
            <!-- Tab Headers -->
            <div style="display:flex; border-bottom:2px solid #e2e8f0; margin-bottom:24px; gap:10px; flex-wrap:wrap;">
                <button type="button" class="pay-tab active" onclick="switchPayTab('gateway')" style="padding:10px 16px; border:none; background:none; font-family:inherit; font-weight:800; font-size:13.5px; cursor:pointer; color:#2563eb; border-bottom:3px solid #2563eb; padding-bottom:8px; transition:.2s; outline:none;">💳 Payment Gateway (Otomatis)</button>
                <button type="button" class="pay-tab" onclick="switchPayTab('manual')" style="padding:10px 16px; border:none; background:none; font-family:inherit; font-weight:700; font-size:13.5px; cursor:pointer; color:#64748b; padding-bottom:8px; transition:.2s; outline:none;">🏦 Transfer Manual (Upload Bukti)</button>
            </div>

            <!-- Payment Gateway Content -->
            <div id="pay-gateway-content" style="display:block;">
                <div style="text-align:center; padding:30px 15px;">
                    <div style="font-size:60px; margin-bottom:16px;">💳</div>
                    <h4 style="font-size:16px; font-weight:800; color:#0f172a; margin-bottom:8px;">Pembayaran Otomatis Online</h4>
                    <p style="color:#64748b; font-size:13px; margin-bottom:28px; line-height:1.6; max-width:420px; margin-left:auto; margin-right:auto;">Bayar Booking Fee secara aman dan otomatis menggunakan Virtual Account Bank, E-Wallet (GoPay, ShopeePay), QRIS, atau Kartu Kredit melalui Midtrans Sandbox.</p>
                    
                    <button type="button" onclick="payWithGateway(<?= $id_booking ?>)" class="btn btn-primary btn-lg" style="justify-content:center; width:100%; max-width:320px; margin:0 auto; font-size:14.5px; padding:12px 24px; box-shadow: 0 4px 14px rgba(37,99,235,0.35);">
                        ⚡ Bayar Sekarang (Online)
                    </button>
                    <p style="color:#94a3b8; font-size:11px; margin-top:14px; font-style:italic;">* Mendukung simulasi pembayaran otomatis & simulasi mode offline jika tidak terkoneksi internet.</p>
                </div>
            </div>

            <!-- Manual Transfer Content -->
            <div id="pay-manual-content" style="display:none;">
                <h3 style="font-size:15px; font-weight:800; margin-bottom:18px; border-bottom:1px solid #f1f5f9; padding-bottom:10px; color:#0f172a;">Form Bukti Bayar</h3>
                <?php if($error): ?><div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:6px;">Jumlah Pembayaran (Rp)</label>
                        <input type="text" name="jumlah_bayar" class="form-control format-angka" placeholder="<?= number_format($booking['booking_fee']??2000000,0,',','.') ?>" required>
                        <small style="color:#94a3b8;font-size:12px;">Booking fee minimal: <?= format_rupiah($booking['booking_fee']??2000000) ?></small>
                    </div>
                    <div class="form-group">
                        <label style="font-weight:700; font-size:13px; color:#475569; display:block; margin-bottom:6px;">Bukti Transfer (JPG/PNG/PDF, maks 5MB)</label>
                        <input type="file" name="bukti_bayar" class="form-control" accept=".jpg,.jpeg,.png,.pdf" data-preview="prevImg" required>
                        <img id="prevImg" style="max-width:100%;border-radius:8px;margin-top:10px;display:none; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    </div>
                    <div style="display:flex;gap:10px;margin-top:20px;">
                        <a href="booking_saya.php" class="btn btn-gray">← Batal</a>
                        <button type="submit" class="btn btn-primary">📤 Kirim Konfirmasi</button>
                    </div>
                </form>
            </div>

        </div>

        <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:24px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
            <h3 style="font-size:16px; font-weight:800; margin-bottom:18px; border-bottom:1px solid #f1f5f9; padding-bottom:10px;">Ringkasan Booking</h3>
            <table style="width:100%;font-size:13.5px; border-collapse:collapse;">
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:10px 0;color:#64748b;">Komplek</td><td style="font-weight:700;padding:10px 0; text-align:right;"><?= htmlspecialchars($booking['nama_perumahan']) ?></td></tr>
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:10px 0;color:#64748b;">Blok / Unit</td><td style="font-weight:700;padding:10px 0; text-align:right;"><?= htmlspecialchars($booking['blok'].'-'.$booking['kode_unit']) ?></td></tr>
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:10px 0;color:#64748b;">Alamat</td><td style="font-weight:700;padding:10px 0; text-align:right; font-size:12px; color:#475569; max-width:180px; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($booking['alamat']) ?></td></tr>
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:10px 0;color:#64748b;">Peta Lokasi</td><td style="padding:10px 0; text-align:right;">
                    <?php if ($booking['maps_link']): ?>
                        <a href="<?= htmlspecialchars($booking['maps_link']) ?>" target="_blank" class="btn btn-white btn-sm" style="padding:2px 8px; font-size:11px; border:1px solid #cbd5e1; display:inline-flex; align-items:center; gap:4px;">🗺️ Buka Peta</a>
                    <?php else: ?>
                        <span style="color:#94a3b8; font-size:11px;">Tidak ada link</span>
                    <?php endif; ?>
                </td></tr>
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:10px 0;color:#64748b;">Tipe Rumah</td><td style="font-weight:700;padding:10px 0; text-align:right;"><?= htmlspecialchars($booking['nama_tipe']) ?></td></tr>
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:10px 0;color:#64748b;">Harga Unit</td><td style="font-weight:700;padding:10px 0;color:#2563eb; text-align:right;"><?= format_rupiah($booking['harga']) ?></td></tr>
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="padding:10px 0;color:#64748b;">Tgl Booking</td><td style="font-weight:700;padding:10px 0; text-align:right;"><?= format_tanggal($booking['tanggal_booking']) ?></td></tr>
                <tr><td style="padding:10px 0;color:#64748b;">Status</td><td style="padding:10px 0; text-align:right;"><?= badge_booking($booking['status_booking']) ?></td></tr>
            </table>

            <div style="margin-top:20px;padding:14px;background:#f8fafc;border-radius:8px;font-size:13px;border:1px solid #e2e8f0;color:#334155;">
                <b>🏦 Rekening Transfer Developer:</b><br>
                Silakan transfer Booking Fee sebesar <b><?= format_rupiah($booking['booking_fee'] ?? 2000000) ?></b> ke salah satu rekening resmi berikut:<br><br>
                <b>Bank BTN (Utama):</b><br>
                No. Rek: <code style="font-size:14px; font-weight:700; color:#2563eb;">1092-8822-7711</code><br>
                A.n: PT Sinar KPR Indonesia<br><br>
                <b>Bank Mandiri:</b><br>
                No. Rek: <code style="font-size:14px; font-weight:700; color:#2563eb;">157-000-999-888</code><br>
                A.n: PT Sinar KPR Indonesia<br><br>
                <b>Bank BCA:</b><br>
                No. Rek: <code style="font-size:14px; font-weight:700; color:#2563eb;">012-345-6789</code><br>
                A.n: PT Sinar KPR Indonesia<br><br>
                <hr style="border:0; border-top:1px solid #e2e8f0; margin:10px 0;">
                <b>⚠️ Penting:</b><br>
                - Cantumkan berita transfer: <b>#BKN-<?= $booking['id_booking'] ?></b>.<br>
                - Simpan bukti transfer dalam format gambar/PDF untuk diunggah.
            </div>
        </div>
    </div>

    <!-- SIMULATOR PAYMENT GATEWAY MODAL -->
    <div id="simulatorModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.6); backdrop-filter:blur(5px); z-index:9999; align-items:center; justify-content:center; padding:16px;">
        <div style="background:#fff; border-radius:20px; max-width:440px; width:100%; overflow:hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.3); animation: zoomIn .25s ease;">
            <!-- Header -->
            <div style="background:#2563eb; color:#fff; padding:20px 24px; display:flex; justify-content:space-between; align-items:center;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="font-size:20px;">💳</span>
                    <div>
                        <h4 style="margin:0; font-weight:800; font-size:14px; color:#fff;">Antigravity Snap Simulator</h4>
                        <small style="color:rgba(255,255,255,0.7); font-size:10px;">Offline/Sandbox Fallback</small>
                    </div>
                </div>
                <button type="button" onclick="closeSimulator()" style="background:none; border:none; color:#fff; font-size:22px; cursor:pointer; font-weight:bold;">✕</button>
            </div>
            <!-- Body -->
            <div style="padding:24px;">
                <div style="text-align:center; margin-bottom:20px;">
                    <div style="font-size:11px; color:#64748b; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin-bottom:4px;">Total Tagihan</div>
                    <div style="font-size:28px; font-weight:900; color:#2563eb;"><?= format_rupiah($booking['booking_fee'] ?? 2000000) ?></div>
                </div>
                
                <h5 style="font-size:12.5px; font-weight:800; color:#334155; margin-bottom:12px;">Pilih Metode Pembayaran:</h5>
                
                <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:24px;">
                    <label style="display:flex; align-items:center; gap:12px; padding:12px 14px; border:1px solid #e2e8f0; border-radius:10px; cursor:pointer; transition:.2s; text-align:left; width:100%;" class="sim-pay-option" onclick="selectSimOption(this)">
                        <input type="radio" name="sim_method" value="va" checked style="accent-color:#2563eb;">
                        <div>
                            <div style="font-size:13px; font-weight:700; color:#0f172a;">Virtual Account (BCA, Mandiri, BNI, BRI)</div>
                            <small style="color:#64748b; font-size:11px;">Transfer otomatis via bank VA</small>
                        </div>
                    </label>
                    <label style="display:flex; align-items:center; gap:12px; padding:12px 14px; border:1px solid #e2e8f0; border-radius:10px; cursor:pointer; transition:.2s; text-align:left; width:100%;" class="sim-pay-option" onclick="selectSimOption(this)">
                        <input type="radio" name="sim_method" value="gopay" style="accent-color:#2563eb;">
                        <div>
                            <div style="font-size:13px; font-weight:700; color:#0f172a;">E-Wallet (GoPay, ShopeePay, OVO, QRIS)</div>
                            <small style="color:#64748b; font-size:11px;">Scan kode QRIS instan</small>
                        </div>
                    </label>
                    <label style="display:flex; align-items:center; gap:12px; padding:12px 14px; border:1px solid #e2e8f0; border-radius:10px; cursor:pointer; transition:.2s; text-align:left; width:100%;" class="sim-pay-option" onclick="selectSimOption(this)">
                        <input type="radio" name="sim_method" value="cc" style="accent-color:#2563eb;">
                        <div>
                            <div style="font-size:13px; font-weight:700; color:#0f172a;">Kartu Kredit / Debit Online</div>
                            <small style="color:#64748b; font-size:11px;">Simulasi kartu Visa/Mastercard</small>
                        </div>
                    </label>
                </div>
                
                <div style="display:flex; gap:12px;">
                    <button type="button" onclick="closeSimulator()" class="btn btn-gray" style="flex:1; justify-content:center;">Batal</button>
                    <button type="button" onclick="submitSimPayment(<?= $id_booking ?>)" class="btn btn-success" style="flex:1.5; justify-content:center; font-weight:800; background:linear-gradient(135deg,#10b981,#059669); color:#fff; box-shadow:0 3px 10px rgba(16,185,129,0.3);">Bayar Sukses ✓</button>
                </div>
            </div>
        </div>
    </div>
</main>
<script src="../assets/js/script.js"></script>
<script>
function switchPayTab(tab) {
    const tabs = document.querySelectorAll('.pay-tab');
    tabs.forEach(t => {
        t.style.color = '#64748b';
        t.style.borderBottom = 'none';
        t.style.fontWeight = '700';
        t.classList.remove('active');
    });
    
    const activeTab = event.currentTarget;
    activeTab.style.color = '#2563eb';
    activeTab.style.borderBottom = '3px solid #2563eb';
    activeTab.style.fontWeight = '800';
    activeTab.classList.add('active');

    if (tab === 'gateway') {
        document.getElementById('pay-gateway-content').style.display = 'block';
        document.getElementById('pay-manual-content').style.display = 'none';
    } else {
        document.getElementById('pay-gateway-content').style.display = 'none';
        document.getElementById('pay-manual-content').style.display = 'block';
    }
}

function selectSimOption(label) {
    document.querySelectorAll('.sim-pay-option').forEach(l => {
        l.style.borderColor = '#e2e8f0';
        l.style.background = 'none';
    });
    label.style.borderColor = '#2563eb';
    label.style.background = '#eff6ff';
    label.querySelector('input').checked = true;
}

function closeSimulator() {
    document.getElementById('simulatorModal').style.display = 'none';
}

function payWithGateway(id_booking) {
    const btn = event.currentTarget;
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⌛ Memuat Pembayaran...';
    
    fetch('get_snap_token.php?id_booking=' + id_booking)
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = origText;
            
            if (data.status === 'success' && typeof window.snap !== 'undefined') {
                window.snap.pay(data.token, {
                    onSuccess: function(result) {
                        window.location.href = 'proses_pembayaran_gateway.php?booking_id=' + id_booking + '&status=success';
                    },
                    onPending: function(result) {
                        window.location.href = 'proses_pembayaran_gateway.php?booking_id=' + id_booking + '&status=pending';
                    },
                    onError: function(result) {
                        alert("Pembayaran gagal!");
                    },
                    onClose: function() {
                        console.log('Customer closed payment popup.');
                    }
                });
            } else {
                console.log('Falling back to local offline simulator...');
                document.getElementById('simulatorModal').style.display = 'flex';
                selectSimOption(document.querySelector('.sim-pay-option'));
            }
        })
        .catch(err => {
            console.error(err);
            btn.disabled = false;
            btn.innerHTML = origText;
            document.getElementById('simulatorModal').style.display = 'flex';
            selectSimOption(document.querySelector('.sim-pay-option'));
        });
}

function submitSimPayment(id_booking) {
    closeSimulator();
    window.location.href = 'proses_pembayaran_gateway.php?booking_id=' + id_booking + '&status=success';
}
</script>
<style>
@keyframes zoomIn{from{transform:scale(.85);opacity:0}to{transform:scale(1);opacity:1}}
@media(max-width:768px){#upload-grid{grid-template-columns:1fr!important;}}
</style>
<?php require_once '../includes/footer.php'; ?>
