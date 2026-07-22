<?php
// customer/bayar_dp.php — Pembayaran Uang Muka (DP) via Midtrans Payment Gateway
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';
require_once '../config/midtrans.php';

$id_user      = id_user();
$id_pengajuan = (int)($_GET['id'] ?? 0);

if ($id_pengajuan === 0) {
    // Cari KPR aktif/disetujui otomatis
    $stmt_find = $db->prepare("SELECT id_pengajuan FROM pengajuan_kpr WHERE id_user=? AND status_pengajuan IN ('disetujui','akad_kredit') ORDER BY id_pengajuan DESC LIMIT 1");
    $stmt_find->execute([$id_user]);
    $id_pengajuan = (int)$stmt_find->fetchColumn();
    if ($id_pengajuan > 0) {
        header("Location: bayar_dp.php?id=$id_pengajuan");
        exit;
    }
}

// Ambil data pengajuan + pastikan milik user & status disetujui/akad_kredit
$stmt = $db->prepare("
    SELECT pk.*, p.nama_perumahan, r.blok, r.kode_unit, r.harga, r.nama_tipe,
           b.nama_bank, b.bunga_kpr, u.nama_lengkap, u.email
    FROM pengajuan_kpr pk
    JOIN rumah r      ON pk.id_rumah = r.id_rumah
    JOIN perumahan p  ON r.id_perumahan = p.id_perumahan
    JOIN bank b       ON pk.id_bank = b.id_bank
    JOIN users u      ON pk.id_user = u.id_user
    WHERE pk.id_pengajuan = ? AND pk.id_user = ? AND pk.status_pengajuan IN ('disetujui','akad_kredit')
");
$stmt->execute([$id_pengajuan, $id_user]);
$pengajuan = $stmt->fetch();

if (!$pengajuan) {
    set_flash('gagal', 'Anda belum memiliki pengajuan KPR yang disetujui untuk dibayar DP-nya.');
    header('Location: status_kpr.php');
    exit;
}

// Cek status DP yang sudah ada
$dp_stmt = $db->prepare("SELECT * FROM pembayaran_dp WHERE id_pengajuan = ? ORDER BY created_at DESC LIMIT 1");
$dp_stmt->execute([$id_pengajuan]);
$dp_existing = $dp_stmt->fetch();

// Jika DP sudah valid dan dibayar via gateway, redirect ke struk
if ($dp_existing && $dp_existing['status_verifikasi'] === 'valid' && $dp_existing['payment_method'] === 'gateway') {
    header("Location: struk_dp.php?id_pengajuan=$id_pengajuan&id_dp=" . $dp_existing['id_dp']);
    exit;
}

// Handle POST — upload bukti manual (opsi alternatif)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($dp_existing && $dp_existing['status_verifikasi'] === 'valid') {
        set_flash('gagal', 'Pembayaran DP Anda sudah terverifikasi valid.');
        header("Location: bayar_dp.php?id=$id_pengajuan");
        exit;
    }
    if (empty($_FILES['bukti_dp']['name'])) {
        set_flash('gagal', 'Pilih file bukti transfer DP terlebih dahulu.');
        header("Location: bayar_dp.php?id=$id_pengajuan");
        exit;
    }
    $dir = '../uploads/bukti_dp/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $ext = strtolower(pathinfo($_FILES['bukti_dp']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','pdf'])) {
        set_flash('gagal', 'Format file tidak valid. Gunakan JPG, PNG, atau PDF.');
        header("Location: bayar_dp.php?id=$id_pengajuan");
        exit;
    }
    if ($_FILES['bukti_dp']['size'] > 5 * 1024 * 1024) {
        set_flash('gagal', 'Ukuran file terlalu besar (maks 5MB).');
        header("Location: bayar_dp.php?id=$id_pengajuan");
        exit;
    }
    $fname = 'dp_' . $id_pengajuan . '_' . time() . '.' . $ext;
    if (!move_uploaded_file($_FILES['bukti_dp']['tmp_name'], $dir . $fname)) {
        set_flash('gagal', 'Gagal menyimpan file. Coba lagi.');
        header("Location: bayar_dp.php?id=$id_pengajuan");
        exit;
    }
    if ($dp_existing && $dp_existing['status_verifikasi'] === 'ditolak') {
        $db->prepare("UPDATE pembayaran_dp SET bukti_dp=?, tanggal_bayar=NOW(), status_verifikasi='pending', payment_method='manual', catatan=NULL WHERE id_dp=?")
           ->execute([$fname, $dp_existing['id_dp']]);
    } else {
        $db->prepare("INSERT INTO pembayaran_dp (id_pengajuan, jumlah_dp, bukti_dp, tanggal_bayar, status_verifikasi, payment_method) VALUES (?, ?, ?, NOW(), 'pending', 'manual')")
           ->execute([$id_pengajuan, $pengajuan['uang_muka'], $fname]);
    }
    set_flash('sukses', '✅ Bukti pembayaran DP berhasil dikirim! Admin akan memverifikasi dalam 1×24 jam.');
    header("Location: status_kpr.php?id=$id_pengajuan");
    exit;
}

$page_title = 'Bayar DP - Akad Kredit KPR';
require_once '../includes/header.php';
$cicilan = hitung_cicilan($pengajuan['harga'], $pengajuan['uang_muka'], $pengajuan['bunga_kpr'], $pengajuan['tenor']);
?>
<!-- Midtrans Snap.js -->
<script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="<?= MIDTRANS_CLIENT_KEY ?>"></script>

<main class="container" style="padding:40px 24px 80px;">
    <?php tampil_flash(); ?>

    <div style="margin-bottom:24px;">
        <a href="status_kpr.php?id=<?= $id_pengajuan ?>" style="display:inline-flex;align-items:center;gap:6px;color:#64748b;font-size:13px;font-weight:600;text-decoration:none;margin-bottom:16px;">← Kembali ke Status KPR</a>
        <h1 class="section-title">🤝 Pembayaran DP — Akad Kredit</h1>
        <p class="section-sub">Selesaikan pembayaran Uang Muka untuk mengkonfirmasi Akad Kredit KPR Anda</p>
    </div>

    <!-- STATUS DP YANG SUDAH ADA -->
    <?php if ($dp_existing): ?>
        <?php if ($dp_existing['status_verifikasi'] === 'valid'): ?>
        <div style="background:linear-gradient(135deg,#d1fae5,#a7f3d0);border:2px solid #10b981;border-radius:14px;padding:20px 24px;margin-bottom:24px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <div style="font-size:36px;">✅</div>
            <div>
                <div style="font-size:15px;font-weight:800;color:#065f46;">Pembayaran DP Terverifikasi!</div>
                <div style="font-size:13px;color:#047857;margin-top:4px;">DP sebesar <?= format_rupiah($dp_existing['jumlah_dp']) ?> telah diterima. Pengajuan masuk tahap Akad Kredit.</div>
            </div>
            <?php if ($dp_existing['payment_method'] === 'gateway'): ?>
            <a href="struk_dp.php?id_pengajuan=<?= $id_pengajuan ?>&id_dp=<?= $dp_existing['id_dp'] ?>" class="btn btn-success btn-sm" style="margin-left:auto;">🧾 Lihat Struk</a>
            <?php endif; ?>
        </div>
        <?php elseif ($dp_existing['status_verifikasi'] === 'pending'): ?>
        <div style="background:linear-gradient(135deg,#fef3c7,#fde68a);border:2px solid #f59e0b;border-radius:14px;padding:20px 24px;margin-bottom:24px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <div style="font-size:36px;">⏳</div>
            <div>
                <div style="font-size:15px;font-weight:800;color:#92400e;">Menunggu Verifikasi</div>
                <div style="font-size:13px;color:#b45309;margin-top:4px;">
                    <?php if ($dp_existing['payment_method'] === 'gateway'): ?>
                        Pembayaran via Midtrans sedang diproses. Jika belum selesai, harap selesaikan di kanal bayar Anda.
                    <?php else: ?>
                        Bukti DP manual Anda sedang diperiksa admin. Biasanya selesai dalam 1×24 jam.
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php elseif ($dp_existing['status_verifikasi'] === 'ditolak'): ?>
        <div style="background:linear-gradient(135deg,#fee2e2,#fecaca);border:2px solid #ef4444;border-radius:14px;padding:20px 24px;margin-bottom:24px;display:flex;align-items:flex-start;gap:16px;">
            <div style="font-size:36px;">❌</div>
            <div>
                <div style="font-size:15px;font-weight:800;color:#991b1b;">Bukti Ditolak / Gagal</div>
                <?php if ($dp_existing['catatan']): ?>
                <div style="font-size:13px;color:#991b1b;margin-top:6px;background:#fff;padding:10px 14px;border-radius:8px;border-left:3px solid #ef4444;">📋 <b>Alasan:</b> <?= htmlspecialchars($dp_existing['catatan']) ?></div>
                <?php endif; ?>
                <div style="font-size:13px;color:#b91c1c;margin-top:8px;">Silakan bayar ulang menggunakan metode di bawah ini.</div>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start;" id="dp-grid">
        
        <!-- Panel Pembayaran -->
        <?php if (!$dp_existing || in_array($dp_existing['status_verifikasi'], ['ditolak', 'pending'])): ?>
        <div>
            <div style="background:#fff;border-radius:16px;border:1px solid #e2e8f0;padding:0;box-shadow:0 4px 20px rgba(0,0,0,0.06);overflow:hidden;">
                
                <!-- Tab Header -->
                <div style="display:flex;border-bottom:2px solid #e2e8f0;">
                    <button type="button" class="dp-tab active" onclick="switchDpTab('gateway', this)" style="flex:1;padding:16px;border:none;background:none;font-family:inherit;font-weight:800;font-size:13.5px;cursor:pointer;color:#2563eb;border-bottom:3px solid #2563eb;transition:.2s;">
                        ⚡ Payment Gateway
                    </button>
                    <button type="button" class="dp-tab" onclick="switchDpTab('manual', this)" style="flex:1;padding:16px;border:none;background:none;font-family:inherit;font-weight:700;font-size:13.5px;cursor:pointer;color:#94a3b8;border-bottom:3px solid transparent;transition:.2s;">
                        🏦 Transfer Manual
                    </button>
                </div>

                <!-- TAB: PAYMENT GATEWAY (Midtrans) -->
                <div id="tab-gateway" style="padding:32px 28px;text-align:center;">
                    <div style="width:64px;height:64px;background:linear-gradient(135deg,#2563eb,#0891b2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 20px;">⚡</div>
                    <h3 style="font-size:18px;font-weight:900;color:#0f172a;margin-bottom:8px;">Bayar Instan via Midtrans</h3>
                    <p style="color:#64748b;font-size:13.5px;margin-bottom:8px;line-height:1.6;">Pembayaran diverifikasi <b>otomatis</b> tanpa menunggu admin.<br>Struk digital langsung muncul setelah berhasil.</p>
                    
                    <!-- Nominal -->
                    <div style="background:linear-gradient(135deg,#eff6ff,#dbeafe);border-radius:12px;padding:16px 20px;margin-bottom:24px;display:inline-block;min-width:260px;">
                        <div style="font-size:11px;color:#3b82f6;text-transform:uppercase;font-weight:700;margin-bottom:6px;">Total Uang Muka (DP) yang harus dibayar</div>
                        <div style="font-size:28px;font-weight:900;color:#1e3a8a;"><?= format_rupiah($pengajuan['uang_muka']) ?></div>
                    </div>

                    <!-- Metode yang tersedia -->
                    <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;margin-bottom:24px;">
                        <?php
                        $methods = ['💳 Kartu Kredit', '🏧 Virtual Account', '📱 GoPay', '📱 QRIS', '💰 OVO'];
                        foreach ($methods as $m): ?>
                        <span style="background:#f1f5f9;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;color:#475569;"><?= $m ?></span>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" onclick="bayarDpGateway(<?= $id_pengajuan ?>)" id="btn-bayar-dp" 
                        style="width:100%;padding:16px;background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;font-weight:900;font-size:15px;border:none;border-radius:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;box-shadow:0 4px 20px rgba(37,99,235,0.4);transition:.2s;">
                        ⚡ Bayar Sekarang — <?= format_rupiah($pengajuan['uang_muka']) ?>
                    </button>
                    <p style="color:#94a3b8;font-size:11px;margin-top:12px;">🔒 Pembayaran aman diproses oleh Midtrans. Terverifikasi otomatis.</p>
                </div>

                <!-- TAB: TRANSFER MANUAL -->
                <div id="tab-manual" style="padding:28px;display:none;">
                    <h3 style="font-size:15px;font-weight:800;margin-bottom:16px;color:#0f172a;">🏦 Transfer Manual</h3>
                    <div style="background:#eff6ff;border-radius:10px;padding:14px 18px;margin-bottom:20px;font-size:13px;color:#1e40af;">
                        ⚠️ <b>Perhatian:</b> Transfer manual memerlukan verifikasi admin (1×24 jam). Silakan pilih rekening bank tujuan transfer Anda di bawah ini.
                    </div>

                    <!-- Pilih Bank -->
                    <div style="margin-bottom: 18px;">
                        <label style="display:block;font-size:12px;font-weight:800;color:#475569;margin-bottom:8px;">PILIH BANK TUJUAN TRANSFER:</label>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)); gap: 8px;">
                            <?php 
                            $banks = [
                                'bca' => 'BCA',
                                'mandiri' => 'Mandiri',
                                'bri' => 'BRI',
                                'bni' => 'BNI',
                                'btn' => 'BTN'
                            ];
                            $first = true;
                            foreach ($banks as $key => $name): ?>
                                <button type="button" class="btn-select-bank" onclick="selectTransferBank('<?= $key ?>', this)" 
                                    style="padding: 10px; border: 2px solid <?= $first ? '#2563eb' : '#e2e8f0' ?>; background: <?= $first ? '#eff6ff' : '#fff' ?>; color: <?= $first ? '#2563eb' : '#475569' ?>; border-radius: 8px; font-weight: 800; font-size: 13px; cursor: pointer; text-align: center; transition: all 0.2s;">
                                    <?= $name ?>
                                </button>
                            <?php $first = false; endforeach; ?>
                        </div>
                    </div>

                    <!-- Detail Rekening (Hidden/Shown via JS) -->
                    <div style="background:#f8fafc;border-radius:12px;padding:18px;margin-bottom:20px;font-size:13px;border:1px solid #e2e8f0; position: relative; overflow: hidden;">
                        <div id="bank-info-bca" class="bank-details-panel">
                            <b>🏦 Bank BCA</b><br>
                            Nomor Rekening: <code style="font-size:15px;font-weight:800;color:#2563eb;letter-spacing:0.5px;">1234 5678 90</code><br>
                            Atas Nama: <b>PT RumahKPR Indonesia</b>
                        </div>
                        <div id="bank-info-mandiri" class="bank-details-panel" style="display:none;">
                            <b>🏦 Bank Mandiri</b><br>
                            Nomor Rekening: <code style="font-size:15px;font-weight:800;color:#2563eb;letter-spacing:0.5px;">123 45 67890 123</code><br>
                            Atas Nama: <b>PT RumahKPR Indonesia</b>
                        </div>
                        <div id="bank-info-bri" class="bank-details-panel" style="display:none;">
                            <b>🏦 Bank BRI</b><br>
                            Nomor Rekening: <code style="font-size:15px;font-weight:800;color:#2563eb;letter-spacing:0.5px;">1234 56 789012 34 5</code><br>
                            Atas Nama: <b>PT RumahKPR Indonesia</b>
                        </div>
                        <div id="bank-info-bni" class="bank-details-panel" style="display:none;">
                            <b>🏦 Bank BNI</b><br>
                            Nomor Rekening: <code style="font-size:15px;font-weight:800;color:#2563eb;letter-spacing:0.5px;">1234 567 890</code><br>
                            Atas Nama: <b>PT RumahKPR Indonesia</b>
                        </div>
                        <div id="bank-info-btn" class="bank-details-panel" style="display:none;">
                            <b>🏦 Bank BTN</b><br>
                            Nomor Rekening: <code style="font-size:15px;font-weight:800;color:#2563eb;letter-spacing:0.5px;">1234 5678 9012 3</code><br>
                            Atas Nama: <b>PT RumahKPR Indonesia</b>
                        </div>
                        <div style="margin-top: 12px; padding-top: 10px; border-top: 1px dashed #cbd5e1; font-size: 12px;">
                            Nominal Transfer Uang Muka:<br>
                            <b style="font-size:16px; color:#10b981;"><?= format_rupiah($pengajuan['uang_muka']) ?></b>
                        </div>
                    </div>

                    <script>
                    function selectTransferBank(bank, btn) {
                        // Reset all buttons
                        document.querySelectorAll('.btn-select-bank').forEach(b => {
                            b.style.borderColor = '#e2e8f0';
                            b.style.background = '#fff';
                            b.style.color = '#475569';
                        });
                        // Activate current button
                        btn.style.borderColor = '#2563eb';
                        btn.style.background = '#eff6ff';
                        btn.style.color = '#2563eb';

                        // Hide all panel details
                        document.querySelectorAll('.bank-details-panel').forEach(p => {
                            p.style.display = 'none';
                        });
                        // Show selected bank detail panel
                        document.getElementById('bank-info-' + bank).style.display = 'block';
                    }
                    </script>

                    <form method="POST" enctype="multipart/form-data">
                        <div style="margin-bottom:16px;">
                            <label style="display:block;font-size:13px;font-weight:700;color:#374151;margin-bottom:8px;">📎 Upload Bukti Transfer DP</label>
                            <label for="bukti_dp_input" style="display:block;border:2px dashed #93c5fd;border-radius:12px;padding:24px;background:#eff6ff;cursor:pointer;text-align:center;transition:.2s;" id="upload-zone-dp">
                                <div style="font-size:32px;margin-bottom:8px;">📤</div>
                                <div style="font-size:14px;font-weight:700;color:#2563eb;">Klik untuk pilih file</div>
                                <div style="font-size:12px;color:#64748b;">JPG, PNG, atau PDF — Maks 5MB</div>
                                <div id="dp-file-preview" style="display:none;margin-top:10px;font-size:13px;font-weight:700;color:#10b981;"></div>
                            </label>
                            <input type="file" name="bukti_dp" id="bukti_dp_input" accept=".jpg,.jpeg,.png,.pdf" required style="display:none;" onchange="previewDp(this)">
                        </div>
                        <button type="submit" style="width:100%;padding:14px;background:linear-gradient(135deg,#64748b,#475569);color:#fff;font-weight:800;font-size:14px;border:none;border-radius:12px;cursor:pointer;">
                            📤 Kirim Bukti Transfer Manual
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- DP Sudah Valid, Tampilkan ringkasan -->
        <div>
            <div style="background:#fff;border-radius:16px;border:1px solid #e2e8f0;padding:28px;box-shadow:0 4px 20px rgba(0,0,0,0.05);">
                <h3 style="font-size:15px;font-weight:800;margin-bottom:14px;">📋 Riwayat Pembayaran DP</h3>
                <table style="width:100%;border-collapse:collapse;font-size:13.5px;">
                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:10px 0;">Jumlah DP</td><td style="font-weight:800;text-align:right;"><?= format_rupiah($dp_existing['jumlah_dp']) ?></td></tr>
                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:10px 0;">Tanggal Bayar</td><td style="font-weight:600;text-align:right;"><?= $dp_existing['tanggal_bayar'] ? format_datetime($dp_existing['tanggal_bayar']) : '-' ?></td></tr>
                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:10px 0;">Metode</td><td style="font-weight:700;text-align:right;"><?= ($dp_existing['payment_method'] ?? 'manual') === 'gateway' ? '⚡ Midtrans Gateway' : '🏦 Transfer Manual' ?></td></tr>
                    <tr><td style="color:#64748b;padding:10px 0;">Status</td><td style="text-align:right;"><?= badge_pembayaran($dp_existing['status_verifikasi']) ?></td></tr>
                </table>
                <?php if ($dp_existing['payment_method'] === 'gateway'): ?>
                <a href="struk_dp.php?id_pengajuan=<?= $id_pengajuan ?>&id_dp=<?= $dp_existing['id_dp'] ?>" class="btn btn-success" style="width:100%;justify-content:center;margin-top:16px;">🧾 Lihat & Cetak Struk</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- SIDEBAR RINGKASAN KPR -->
        <div style="background:#fff;border-radius:16px;border:1px solid #e2e8f0;padding:24px;box-shadow:0 4px 20px rgba(0,0,0,0.05);position:sticky;top:80px;">
            <?php if (!empty($pengajuan['sertifikat_rumah'])): ?>
            <div style="margin-bottom:16px;">
                <img src="../uploads/sertifikat/<?= htmlspecialchars($pengajuan['sertifikat_rumah']) ?>" alt="Sertifikat" style="width:100%;border-radius:10px;border:2px solid #e2e8f0;cursor:pointer;" onclick="window.open('../uploads/sertifikat/<?= htmlspecialchars($pengajuan['sertifikat_rumah']) ?>','_blank')">
                <div style="font-size:11px;color:#94a3b8;margin-top:4px;text-align:center;">Sertifikat Rumah</div>
            </div>
            <?php endif; ?>
            <h3 style="font-size:14px;font-weight:800;margin-bottom:14px;border-bottom:1px solid #f1f5f9;padding-bottom:8px;">🏠 Ringkasan KPR</h3>
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:8px 0;">Properti</td><td style="font-weight:700;text-align:right;"><?= htmlspecialchars($pengajuan['nama_perumahan']) ?></td></tr>
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:8px 0;">Unit</td><td style="font-weight:700;text-align:right;color:#2563eb;">Blok <?= htmlspecialchars($pengajuan['blok'].'-'.$pengajuan['kode_unit']) ?></td></tr>
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:8px 0;">Harga Rumah</td><td style="font-weight:800;text-align:right;color:#10b981;"><?= format_rupiah($pengajuan['harga']) ?></td></tr>
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:8px 0;">Uang Muka (DP)</td><td style="font-weight:900;text-align:right;color:#2563eb;font-size:15px;"><?= format_rupiah($pengajuan['uang_muka']) ?></td></tr>
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:8px 0;">Bank</td><td style="font-weight:700;text-align:right;"><?= htmlspecialchars($pengajuan['nama_bank']) ?></td></tr>
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:8px 0;">Tenor</td><td style="font-weight:700;text-align:right;"><?= $pengajuan['tenor'] ?> Tahun</td></tr>
                <tr><td style="color:#64748b;padding:8px 0;">Est. Cicilan</td><td style="font-weight:900;text-align:right;color:#6366f1;font-size:14px;"><?= format_rupiah($cicilan) ?>/bln</td></tr>
            </table>
        </div>
    </div>
</main>

<!-- Simulator Modal (Fallback Offline) -->
<div id="sim-modal-dp" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.65);backdrop-filter:blur(5px);z-index:9999;align-items:center;justify-content:center;padding:16px;">
    <div style="background:#fff;border-radius:20px;max-width:420px;width:100%;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <div style="background:linear-gradient(135deg,#2563eb,#0891b2);color:#fff;padding:20px 24px;display:flex;justify-content:space-between;align-items:center;">
            <div>
                <div style="font-weight:900;font-size:15px;">💳 Simulator Pembayaran DP</div>
                <div style="font-size:11px;opacity:.7;">Offline / Sandbox Mode</div>
            </div>
            <button onclick="document.getElementById('sim-modal-dp').style.display='none'" style="background:rgba(255,255,255,.15);border:none;color:#fff;width:34px;height:34px;border-radius:50%;font-size:20px;cursor:pointer;">✕</button>
        </div>
        <div style="padding:24px;">
            <div style="text-align:center;margin-bottom:20px;">
                <div style="font-size:11px;color:#64748b;font-weight:700;text-transform:uppercase;margin-bottom:4px;">Total Pembayaran DP</div>
                <div style="font-size:28px;font-weight:900;color:#2563eb;"><?= format_rupiah($pengajuan['uang_muka']) ?></div>
            </div>
            <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:20px;">
                <?php foreach(['va' => 'Virtual Account Bank','gopay' => 'GoPay / QRIS / E-Wallet','cc' => 'Kartu Kredit/Debit'] as $val => $label): ?>
                <label style="display:flex;align-items:center;gap:12px;padding:12px;border:1px solid #e2e8f0;border-radius:10px;cursor:pointer;transition:.2s;" class="sim-opt" onclick="selectSim(this)">
                    <input type="radio" name="sim_dp_method" value="<?= $val ?>" <?= $val === 'va' ? 'checked' : '' ?> style="accent-color:#2563eb;">
                    <span style="font-size:13px;font-weight:700;"><?= $label ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            <div style="display:flex;gap:10px;">
                <button onclick="document.getElementById('sim-modal-dp').style.display='none'" class="btn btn-gray" style="flex:1;justify-content:center;">Batal</button>
                <button onclick="simulasiSukses()" class="btn btn-success" style="flex:2;justify-content:center;background:linear-gradient(135deg,#10b981,#059669);color:#fff;font-weight:900;">✅ Bayar Sukses</button>
            </div>
        </div>
    </div>
</div>

<script>
const ID_PENGAJUAN = <?= $id_pengajuan ?>;

function switchDpTab(tab, btn) {
    document.querySelectorAll('.dp-tab').forEach(t => {
        t.style.color = '#94a3b8';
        t.style.borderBottom = '3px solid transparent';
        t.style.fontWeight = '700';
    });
    btn.style.color = '#2563eb';
    btn.style.borderBottom = '3px solid #2563eb';
    btn.style.fontWeight = '800';
    document.getElementById('tab-gateway').style.display = tab === 'gateway' ? 'block' : 'none';
    document.getElementById('tab-manual').style.display  = tab === 'manual'  ? 'block' : 'none';
}

function selectSim(label) {
    document.querySelectorAll('.sim-opt').forEach(l => { l.style.borderColor='#e2e8f0'; l.style.background='none'; });
    label.style.borderColor = '#2563eb'; label.style.background = '#eff6ff';
    label.querySelector('input').checked = true;
}

function bayarDpGateway(id_pengajuan) {
    const btn = document.getElementById('btn-bayar-dp');
    btn.disabled = true;
    btn.innerHTML = '⌛ Menghubungi Midtrans...';

    fetch('get_snap_token_dp.php?id_pengajuan=' + id_pengajuan)
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '⚡ Bayar Sekarang — <?= format_rupiah($pengajuan['uang_muka']) ?>';
            if (data.status === 'success' && typeof window.snap !== 'undefined') {
                window.snap.pay(data.token, {
                    onSuccess: function(result) {
                        window.location.href = 'proses_dp_gateway.php?id_pengajuan=' + id_pengajuan + '&status=success&order_id=' + (data.order_id || result.order_id);
                    },
                    onPending: function(result) {
                        window.location.href = 'proses_dp_gateway.php?id_pengajuan=' + id_pengajuan + '&status=pending&order_id=' + (data.order_id || '');
                    },
                    onError: function(result) {
                        alert('Pembayaran gagal: ' + (result.status_message || 'Terjadi kesalahan.'));
                    },
                    onClose: function() { console.log('Popup ditutup.'); }
                });
            } else {
                // Fallback ke simulator offline
                document.getElementById('sim-modal-dp').style.display = 'flex';
                selectSim(document.querySelector('.sim-opt'));
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '⚡ Bayar Sekarang — <?= format_rupiah($pengajuan['uang_muka']) ?>';
            document.getElementById('sim-modal-dp').style.display = 'flex';
            selectSim(document.querySelector('.sim-opt'));
        });
}

function simulasiSukses() {
    document.getElementById('sim-modal-dp').style.display = 'none';
    const fakeOrderId = 'DP-' + ID_PENGAJUAN + '-SIM-' + Date.now();
    window.location.href = 'proses_dp_gateway.php?id_pengajuan=' + ID_PENGAJUAN + '&status=success&order_id=' + fakeOrderId;
}

function previewDp(input) {
    const zone = document.getElementById('upload-zone-dp');
    const prev = document.getElementById('dp-file-preview');
    if (input.files && input.files[0]) {
        const f = input.files[0];
        zone.style.borderColor = '#10b981'; zone.style.background = '#ecfdf5';
        prev.style.display = 'block';
        prev.textContent = '✓ ' + f.name + ' (' + (f.size/1024).toFixed(1) + ' KB)';
    }
}
</script>
<style>
@media(max-width:768px) { #dp-grid { grid-template-columns: 1fr !important; } }
</style>
<?php require_once '../includes/footer.php'; ?>
