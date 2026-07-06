<?php
// customer/bayar_dp.php — Customer bayar uang muka (DP) saat Akad Kredit
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';

$id_user      = id_user();
$id_pengajuan = (int)($_GET['id'] ?? 0);

// Ambil data pengajuan + pastikan milik user & status akad_kredit
$stmt = $db->prepare("
    SELECT pk.*, p.nama_perumahan, r.blok, r.kode_unit, r.harga, r.nama_tipe,
           b.nama_bank, b.bunga_kpr, u.nama_lengkap
    FROM pengajuan_kpr pk
    JOIN rumah r ON pk.id_rumah = r.id_rumah
    JOIN perumahan p ON r.id_perumahan = p.id_perumahan
    JOIN bank b ON pk.id_bank = b.id_bank
    JOIN users u ON pk.id_user = u.id_user
    WHERE pk.id_pengajuan = ? AND pk.id_user = ? AND pk.status_pengajuan = 'akad_kredit'
");
$stmt->execute([$id_pengajuan, $id_user]);
$pengajuan = $stmt->fetch();

if (!$pengajuan) {
    set_flash('gagal', 'Pengajuan tidak ditemukan atau belum sampai tahap Akad Kredit.');
    header('Location: status_kpr.php');
    exit;
}

// Cek status DP yang sudah ada
$dp_stmt = $db->prepare("SELECT * FROM pembayaran_dp WHERE id_pengajuan = ? ORDER BY created_at DESC LIMIT 1");
$dp_stmt->execute([$id_pengajuan]);
$dp_existing = $dp_stmt->fetch();

// Handle POST — upload bukti DP
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Jika sudah valid, tidak bisa submit lagi
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
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
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

    // Insert atau update pembayaran_dp
    if ($dp_existing && $dp_existing['status_verifikasi'] === 'ditolak') {
        // Re-upload setelah ditolak
        $db->prepare("UPDATE pembayaran_dp SET bukti_bayar=?, tanggal_bayar=NOW(), status_verifikasi='pending', catatan=NULL WHERE id_dp=?")
           ->execute([$fname, $dp_existing['id_dp']]);
    } else {
        $db->prepare("INSERT INTO pembayaran_dp (id_pengajuan, jumlah_dp, bukti_bayar, tanggal_bayar, status_verifikasi) VALUES (?, ?, ?, NOW(), 'pending')")
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
<main class="container" style="padding:40px 24px 80px;">
    <?php tampil_flash(); ?>

    <div style="margin-bottom:24px;">
        <a href="status_kpr.php?id=<?= $id_pengajuan ?>" style="display:inline-flex;align-items:center;gap:6px;color:#64748b;font-size:13px;font-weight:600;text-decoration:none;margin-bottom:16px;">← Kembali ke Status KPR</a>
        <h1 class="section-title">🤝 Pembayaran DP — Akad Kredit</h1>
        <p class="section-sub">Selesaikan pembayaran Uang Muka untuk mengkonfirmasi Akad Kredit KPR Anda</p>
    </div>

    <!-- Status DP jika sudah ada -->
    <?php if ($dp_existing): ?>
        <?php if ($dp_existing['status_verifikasi'] === 'valid'): ?>
        <div style="background:linear-gradient(135deg,#d1fae5,#a7f3d0);border:2px solid #10b981;border-radius:14px;padding:20px 24px;margin-bottom:24px;display:flex;align-items:center;gap:16px;">
            <div style="font-size:36px;">✅</div>
            <div>
                <div style="font-size:15px;font-weight:800;color:#065f46;">Pembayaran DP Terverifikasi!</div>
                <div style="font-size:13px;color:#047857;margin-top:4px;">DP sebesar <?= format_rupiah($dp_existing['jumlah_dp']) ?> telah diterima. Menunggu konfirmasi Akad dari admin.</div>
            </div>
        </div>
        <?php elseif ($dp_existing['status_verifikasi'] === 'pending'): ?>
        <div style="background:linear-gradient(135deg,#fef3c7,#fde68a);border:2px solid #f59e0b;border-radius:14px;padding:20px 24px;margin-bottom:24px;display:flex;align-items:center;gap:16px;">
            <div style="font-size:36px;">⏳</div>
            <div>
                <div style="font-size:15px;font-weight:800;color:#92400e;">Menunggu Verifikasi Admin</div>
                <div style="font-size:13px;color:#b45309;margin-top:4px;">Bukti DP Anda sedang diperiksa admin. Biasanya selesai dalam 1×24 jam.</div>
            </div>
        </div>
        <?php elseif ($dp_existing['status_verifikasi'] === 'ditolak'): ?>
        <div style="background:linear-gradient(135deg,#fee2e2,#fecaca);border:2px solid #ef4444;border-radius:14px;padding:20px 24px;margin-bottom:24px;display:flex;align-items:flex-start;gap:16px;">
            <div style="font-size:36px;">❌</div>
            <div>
                <div style="font-size:15px;font-weight:800;color:#991b1b;">Bukti Ditolak Admin</div>
                <?php if ($dp_existing['catatan']): ?>
                <div style="font-size:13px;color:#991b1b;margin-top:6px;background:#fff;padding:10px 14px;border-radius:8px;border-left:3px solid #ef4444;">📋 <b>Alasan:</b> <?= htmlspecialchars($dp_existing['catatan']) ?></div>
                <?php endif; ?>
                <div style="font-size:13px;color:#b91c1c;margin-top:8px;">Silakan upload ulang bukti pembayaran DP yang jelas di bawah.</div>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start;" id="dp-grid">
        <!-- Form Upload Bukti DP -->
        <?php if (!$dp_existing || $dp_existing['status_verifikasi'] === 'ditolak'): ?>
        <div>
            <div style="background:#fff;border-radius:16px;border:1px solid #e2e8f0;padding:28px;box-shadow:0 4px 20px rgba(0,0,0,0.05);margin-bottom:24px;">
                <h3 style="font-size:15px;font-weight:800;margin-bottom:18px;border-bottom:1px solid #f1f5f9;padding-bottom:10px;">💳 Instruksi Pembayaran DP</h3>

                <div style="background:linear-gradient(135deg,#eff6ff,#f8faff);border:1px solid #bfdbfe;border-radius:12px;padding:20px;margin-bottom:24px;">
                    <div style="font-size:13px;font-weight:700;color:#1e40af;margin-bottom:12px;">📋 Cara Pembayaran:</div>
                    <ol style="font-size:13px;color:#3b82f6;padding-left:18px;line-height:2;margin:0;">
                        <li>Transfer Uang Muka (DP) ke rekening berikut:</li>
                        <li style="list-style:none;padding:10px 14px;background:#fff;border-radius:8px;border-left:3px solid #2563eb;margin:6px 0 6px -18px;">
                            <b style="color:#0f172a;">Bank BCA</b><br>
                            <span style="font-size:15px;font-weight:900;color:#2563eb;letter-spacing:1px;">1234 5678 90</span><br>
                            <span style="font-size:12px;color:#64748b;">a.n. PT RumahKPR Indonesia</span>
                        </li>
                        <li>Jumlah: <b style="color:#0f172a;font-size:14px;"><?= format_rupiah($pengajuan['uang_muka']) ?></b></li>
                        <li>Screenshot/foto bukti transfer lalu upload di bawah</li>
                        <li>Admin akan konfirmasi dalam 1×24 jam kerja</li>
                    </ol>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <div style="margin-bottom:20px;">
                        <label style="display:block;font-size:13px;font-weight:700;color:#374151;margin-bottom:8px;">📎 Upload Bukti Transfer DP</label>
                        <label for="bukti_dp_input" style="display:block;border:2px dashed #93c5fd;border-radius:12px;padding:28px 20px;background:#eff6ff;cursor:pointer;text-align:center;transition:.2s;" id="upload-zone-dp">
                            <div style="font-size:36px;margin-bottom:8px;">📤</div>
                            <div style="font-size:14px;font-weight:700;color:#2563eb;margin-bottom:4px;">Klik untuk pilih file</div>
                            <div style="font-size:12px;color:#64748b;">JPG, PNG, atau PDF — Maks 5MB</div>
                            <div id="dp-file-preview" style="display:none;margin-top:12px;font-size:13px;font-weight:700;color:#10b981;"></div>
                        </label>
                        <input type="file" name="bukti_dp" id="bukti_dp_input" accept=".jpg,.jpeg,.png,.pdf" required style="display:none;" onchange="previewDp(this)">
                    </div>

                    <button type="submit" style="width:100%;padding:14px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;font-weight:800;font-size:14px;border:none;border-radius:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;box-shadow:0 4px 16px rgba(16,185,129,0.3);">
                        💰 Kirim Bukti Pembayaran DP
                    </button>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div>
            <div style="background:#fff;border-radius:16px;border:1px solid #e2e8f0;padding:28px;box-shadow:0 4px 20px rgba(0,0,0,0.05);">
                <h3 style="font-size:15px;font-weight:800;margin-bottom:14px;">📋 Riwayat Pembayaran DP</h3>
                <table style="width:100%;border-collapse:collapse;font-size:13.5px;">
                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:10px 0;">Jumlah DP</td><td style="font-weight:800;color:#0f172a;text-align:right;"><?= format_rupiah($dp_existing['jumlah_dp']) ?></td></tr>
                    <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:10px 0;">Tanggal Bayar</td><td style="font-weight:600;text-align:right;"><?= $dp_existing['tanggal_bayar'] ? format_datetime($dp_existing['tanggal_bayar']) : '-' ?></td></tr>
                    <tr><td style="color:#64748b;padding:10px 0;">Status</td><td style="text-align:right;"><?= badge_pembayaran($dp_existing['status_verifikasi']) ?></td></tr>
                </table>
                <?php if ($dp_existing['bukti_bayar']): ?>
                <a href="../uploads/bukti_dp/<?= htmlspecialchars($dp_existing['bukti_bayar']) ?>" target="_blank" style="display:inline-flex;align-items:center;gap:6px;margin-top:16px;padding:8px 16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;color:#2563eb;font-size:13px;font-weight:700;text-decoration:none;">📎 Lihat Bukti yang Dikirim</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Sidebar Ringkasan -->
        <div style="background:#fff;border-radius:16px;border:1px solid #e2e8f0;padding:24px;box-shadow:0 4px 20px rgba(0,0,0,0.05);position:sticky;top:80px;">
            <?php if (!empty($pengajuan['sertifikat_rumah'])): ?>
            <div style="margin-bottom:20px;">
                <div style="font-size:13px;font-weight:700;color:#64748b;margin-bottom:8px;">🏛️ Sertifikat Rumah</div>
                <img src="../uploads/sertifikat/<?= htmlspecialchars($pengajuan['sertifikat_rumah']) ?>"
                     alt="Sertifikat Rumah"
                     style="width:100%;border-radius:10px;border:2px solid #e2e8f0;cursor:pointer;"
                     onclick="window.open('../uploads/sertifikat/<?= htmlspecialchars($pengajuan['sertifikat_rumah']) ?>','_blank')">
                <div style="font-size:11px;color:#94a3b8;margin-top:6px;text-align:center;">Klik gambar untuk buka penuh</div>
            </div>
            <?php else: ?>
            <div style="background:#fef3c7;border-radius:10px;padding:12px;margin-bottom:16px;text-align:center;">
                <div style="font-size:20px;">⏳</div>
                <div style="font-size:12px;color:#92400e;font-weight:700;margin-top:4px;">Sertifikat sedang disiapkan admin</div>
            </div>
            <?php endif; ?>

            <h3 style="font-size:14px;font-weight:800;margin-bottom:14px;border-bottom:1px solid #f1f5f9;padding-bottom:8px;">🏠 Ringkasan KPR</h3>
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:8px 0;">Properti</td><td style="font-weight:700;text-align:right;"><?= htmlspecialchars($pengajuan['nama_perumahan']) ?></td></tr>
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:8px 0;">Unit</td><td style="font-weight:700;text-align:right;color:#2563eb;">Blok <?= htmlspecialchars($pengajuan['blok'].'-'.$pengajuan['kode_unit']) ?></td></tr>
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:8px 0;">Harga Rumah</td><td style="font-weight:800;text-align:right;color:#10b981;"><?= format_rupiah($pengajuan['harga']) ?></td></tr>
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:8px 0;">Uang Muka (DP)</td><td style="font-weight:800;text-align:right;color:#2563eb;font-size:15px;"><?= format_rupiah($pengajuan['uang_muka']) ?></td></tr>
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:8px 0;">Bank</td><td style="font-weight:700;text-align:right;"><?= htmlspecialchars($pengajuan['nama_bank']) ?></td></tr>
                <tr style="border-bottom:1px solid #f1f5f9;"><td style="color:#64748b;padding:8px 0;">Tenor</td><td style="font-weight:700;text-align:right;"><?= $pengajuan['tenor'] ?> Tahun</td></tr>
                <tr><td style="color:#64748b;padding:8px 0;">Est. Cicilan</td><td style="font-weight:900;text-align:right;color:#6366f1;font-size:14px;"><?= format_rupiah($cicilan) ?>/bln</td></tr>
            </table>
        </div>
    </div>
</main>

<script>
function previewDp(input) {
    const zone = document.getElementById('upload-zone-dp');
    const prev = document.getElementById('dp-file-preview');
    if (input.files && input.files[0]) {
        const f = input.files[0];
        zone.style.borderColor = '#10b981';
        zone.style.background = '#ecfdf5';
        prev.style.display = 'block';
        prev.textContent = '✓ ' + f.name + ' (' + (f.size / 1024).toFixed(1) + ' KB)';
    }
}
</script>
<style>
@media(max-width:768px) {
    #dp-grid { grid-template-columns: 1fr !important; }
}
</style>
<?php require_once '../includes/footer.php'; ?>
