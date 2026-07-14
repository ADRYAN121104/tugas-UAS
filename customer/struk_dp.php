<?php
// customer/struk_dp.php — Struk / Tanda Terima Pembayaran DP KPR
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';

$id_user      = id_user();
$id_pengajuan = (int)($_GET['id_pengajuan'] ?? 0);
$id_dp        = (int)($_GET['id_dp'] ?? 0);

// Ambil data DP dan pengajuan
$stmt = $db->prepare("
    SELECT dp.*, pk.uang_muka, pk.tenor, pk.status_pengajuan,
           u.nama_lengkap, u.email, u.no_hp,
           p.nama_perumahan, p.alamat,
           r.blok, r.kode_unit, r.harga, r.nama_tipe,
           b.nama_bank, b.bunga_kpr
    FROM pembayaran_dp dp
    JOIN pengajuan_kpr pk ON dp.id_pengajuan = pk.id_pengajuan
    JOIN users u          ON pk.id_user = u.id_user
    JOIN rumah r          ON pk.id_rumah = r.id_rumah
    JOIN perumahan p      ON r.id_perumahan = p.id_perumahan
    JOIN bank b           ON pk.id_bank = b.id_bank
    WHERE dp.id_pengajuan = ? AND pk.id_user = ?
    " . ($id_dp ? " AND dp.id_dp = ?" : "") . "
    ORDER BY dp.created_at DESC LIMIT 1
");
$params = [$id_pengajuan, $id_user];
if ($id_dp) $params[] = $id_dp;
$stmt->execute($params);
$dp = $stmt->fetch();

if (!$dp) {
    set_flash('gagal', 'Struk pembayaran tidak ditemukan.');
    header('Location: status_kpr.php');
    exit;
}

$no_struk = 'STRUK-DP-' . str_pad($dp['id_dp'], 6, '0', STR_PAD_LEFT);
$cicilan  = hitung_cicilan($dp['harga'], $dp['uang_muka'], $dp['bunga_kpr'], $dp['tenor']);

$page_title = 'Struk Pembayaran DP - KPR Perumahan';
require_once '../includes/header.php';
?>
<main class="container" style="padding:40px 24px 80px;">
    <?php tampil_flash(); ?>

    <div style="max-width:640px;margin:0 auto;">
        <!-- Header Actions -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
            <a href="status_kpr.php?id=<?= $id_pengajuan ?>" style="display:inline-flex;align-items:center;gap:6px;color:#64748b;font-size:13px;font-weight:600;text-decoration:none;">
                ← Kembali ke Status KPR
            </a>
            <button onclick="window.print()" class="btn btn-primary btn-sm" style="gap:6px;">
                🖨️ Cetak Struk
            </button>
        </div>

        <!-- STRUK CARD -->
        <div id="struk-card" style="background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,0.12);border:1px solid #e2e8f0;">

            <!-- Header Struk -->
            <div style="background:linear-gradient(135deg,#1e3a8a,#2563eb,#0891b2);padding:32px 28px;text-align:center;color:#fff;">
                <div style="font-size:48px;margin-bottom:12px;">✅</div>
                <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;opacity:.7;margin-bottom:6px;">Pembayaran Berhasil</div>
                <div style="font-size:26px;font-weight:900;margin-bottom:4px;"><?= format_rupiah($dp['jumlah_dp']) ?></div>
                <div style="font-size:13px;opacity:.8;">Uang Muka (DP) KPR telah diterima</div>
            </div>

            <!-- Nomor & Tanggal Struk -->
            <div style="background:#f8fafc;padding:14px 28px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #e2e8f0;flex-wrap:wrap;gap:8px;">
                <div>
                    <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;">No. Struk</div>
                    <div style="font-size:14px;font-weight:800;color:#0f172a;font-family:monospace;"><?= $no_struk ?></div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;">Tanggal</div>
                    <div style="font-size:13px;font-weight:700;color:#374151;"><?= format_datetime($dp['tanggal_bayar']) ?></div>
                </div>
            </div>

            <!-- Metode Pembayaran -->
            <div style="padding:16px 28px;background:#eff6ff;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:12px;">
                <?php $is_gateway = ($dp['payment_method'] ?? 'manual') === 'gateway'; ?>
                <span style="font-size:24px;"><?= $is_gateway ? '⚡' : '🏦' ?></span>
                <div>
                    <div style="font-size:11px;color:#3b82f6;text-transform:uppercase;font-weight:700;letter-spacing:.5px;">Metode Pembayaran</div>
                    <div style="font-size:13px;font-weight:800;color:#1e40af;">
                        <?= $is_gateway ? 'Midtrans Payment Gateway (Otomatis)' : 'Transfer Manual (Terverifikasi Admin)' ?>
                    </div>
                    <?php if ($dp['midtrans_order_id']): ?>
                    <div style="font-size:11px;color:#64748b;margin-top:2px;font-family:monospace;">Order ID: <?= htmlspecialchars($dp['midtrans_order_id']) ?></div>
                    <?php endif; ?>
                </div>
                <div style="margin-left:auto;">
                    <span style="background:#d1fae5;color:#065f46;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:800;">✅ VALID</span>
                </div>
            </div>

            <!-- Detail Pembayaran -->
            <div style="padding:24px 28px;border-bottom:2px dashed #e2e8f0;">
                <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:.5px;margin-bottom:14px;font-weight:700;">Detail Pembayaran</div>
                <table style="width:100%;border-collapse:collapse;font-size:13.5px;">
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:10px 0;color:#64748b;">Jenis Pembayaran</td>
                        <td style="font-weight:800;text-align:right;color:#0f172a;">Uang Muka (DP) KPR</td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:10px 0;color:#64748b;">Nama Customer</td>
                        <td style="font-weight:700;text-align:right;"><?= htmlspecialchars($dp['nama_lengkap']) ?></td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:10px 0;color:#64748b;">Properti</td>
                        <td style="font-weight:700;text-align:right;"><?= htmlspecialchars($dp['nama_perumahan']) ?></td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:10px 0;color:#64748b;">Unit</td>
                        <td style="font-weight:700;text-align:right;color:#2563eb;">Blok <?= htmlspecialchars($dp['blok'].'-'.$dp['kode_unit']) ?></td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:10px 0;color:#64748b;">Tipe Rumah</td>
                        <td style="font-weight:700;text-align:right;"><?= htmlspecialchars($dp['nama_tipe']) ?></td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:10px 0;color:#64748b;">Harga Properti</td>
                        <td style="font-weight:700;text-align:right;"><?= format_rupiah($dp['harga']) ?></td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:10px 0;color:#64748b;">Bank KPR</td>
                        <td style="font-weight:700;text-align:right;"><?= htmlspecialchars($dp['nama_bank']) ?></td>
                    </tr>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:10px 0;color:#64748b;">Tenor</td>
                        <td style="font-weight:700;text-align:right;"><?= $dp['tenor'] ?> Tahun</td>
                    </tr>
                    <tr style="border-bottom:2px solid #e2e8f0;">
                        <td style="padding:10px 0;color:#64748b;">Est. Cicilan/Bulan</td>
                        <td style="font-weight:900;text-align:right;color:#6366f1;"><?= format_rupiah($cicilan) ?></td>
                    </tr>
                    <tr style="background:#eff6ff;">
                        <td style="padding:14px 8px;color:#1e40af;font-weight:800;font-size:15px;">TOTAL DP DIBAYAR</td>
                        <td style="font-weight:900;text-align:right;color:#1e3a8a;font-size:18px;"><?= format_rupiah($dp['jumlah_dp']) ?></td>
                    </tr>
                </table>
            </div>

            <!-- Status KPR -->
            <div style="padding:20px 28px;background:linear-gradient(135deg,#d1fae5,#a7f3d0);border-bottom:1px solid #e2e8f0;">
                <div style="display:flex;align-items:center;gap:16px;">
                    <span style="font-size:32px;">🤝</span>
                    <div>
                        <div style="font-weight:900;color:#065f46;font-size:15px;">Status KPR: AKAD KREDIT</div>
                        <div style="color:#047857;font-size:13px;margin-top:3px;">Pembayaran DP diterima. Proses KPR Anda berlanjut ke tahap Akad Kredit. Tim kami akan menghubungi Anda untuk jadwal penandatanganan akad.</div>
                    </div>
                </div>
            </div>

            <!-- Sisa Pelunasan -->
            <div style="padding:20px 28px;border-bottom:1px solid #e2e8f0;">
                <?php $sisa = max(0, $dp['harga'] - $dp['jumlah_dp']); ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div style="background:#f8fafc;border-radius:10px;padding:14px;text-align:center;border:1px solid #e2e8f0;">
                        <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;margin-bottom:4px;">DP Terbayar</div>
                        <div style="font-size:15px;font-weight:900;color:#10b981;"><?= format_rupiah($dp['jumlah_dp']) ?></div>
                    </div>
                    <div style="background:#fff5f5;border-radius:10px;padding:14px;text-align:center;border:1px solid #fee2e2;">
                        <div style="font-size:10px;color:#991b1b;text-transform:uppercase;margin-bottom:4px;">Sisa (via Cicilan)</div>
                        <div style="font-size:15px;font-weight:900;color:#ef4444;"><?= format_rupiah($sisa) ?></div>
                    </div>
                </div>
            </div>

            <!-- Footer Struk -->
            <div style="padding:20px 28px;text-align:center;background:#f8fafc;">
                <div style="font-size:11px;color:#94a3b8;line-height:1.8;">
                    Struk ini merupakan bukti resmi pembayaran Uang Muka (DP) KPR Anda.<br>
                    Simpan struk ini sebagai arsip. PT RumahKPR Indonesia.<br>
                    <b>Dicetak: <?= date('d/m/Y H:i') ?> WIB</b>
                </div>
                <div style="margin-top:12px;">
                    <a href="cicilan.php" class="btn btn-primary btn-sm" style="margin:4px;">📋 Lihat Jadwal Cicilan</a>
                    <a href="status_kpr.php?id=<?= $id_pengajuan ?>" class="btn btn-gray btn-sm" style="margin:4px;">← Status KPR</a>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
@media print {
    header, nav, footer, .topbar, .btn, a[href] { display: none !important; }
    body { background: #fff; }
    #struk-card { box-shadow: none !important; border: 1px solid #ccc !important; }
    .container { padding: 0 !important; }
}
@media(max-width:640px) {
    .container { padding: 16px !important; }
}
</style>
<?php require_once '../includes/footer.php'; ?>
