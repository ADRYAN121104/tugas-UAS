<?php
// admin/cicilan/cetak_kpr.php - Cetak Laporan Keuangan Pengajuan KPR Per Customer
require_once '../../config/koneksi.php';
require_once '../../config/cek_admin.php';
require_once '../../config/functions.php';

$id_pengajuan = (int)($_GET['id'] ?? 0);

if ($id_pengajuan <= 0) {
    echo "ID Pengajuan tidak valid.";
    exit;
}

// Fetch KPR details
$stmt = $db->prepare("
    SELECT pk.*, u.nama_lengkap, u.email, u.no_hp,
           p.nama_perumahan, r.blok, r.kode_unit, r.nama_tipe, r.harga,
           b.nama_bank, b.bunga_kpr
    FROM pengajuan_kpr pk
    JOIN users u ON pk.id_user = u.id_user
    JOIN rumah r ON pk.id_rumah = r.id_rumah
    JOIN perumahan p ON r.id_perumahan = p.id_perumahan
    JOIN bank b ON pk.id_bank = b.id_bank
    WHERE pk.id_pengajuan = ?
");
$stmt->execute([$id_pengajuan]);
$kpr = $stmt->fetch();

if (!$kpr) {
    echo "Data pengajuan KPR tidak ditemukan.";
    exit;
}

// Fetch Booking Fee
$q_bf = $db->prepare("SELECT booking_fee, tanggal_booking, status_booking FROM booking WHERE id_user=? AND id_rumah=? AND status_booking='dikonfirmasi' ORDER BY id_booking DESC LIMIT 1");
$q_bf->execute([$kpr['id_user'], $kpr['id_rumah']]);
$booking = $q_bf->fetch();
$booking_fee = (float)($booking['booking_fee'] ?? 0);

// Fetch DP details
$q_dp = $db->prepare("SELECT * FROM pembayaran_dp WHERE id_pengajuan = ? AND status_verifikasi = 'valid' ORDER BY created_at DESC LIMIT 1");
$q_dp->execute([$id_pengajuan]);
$dp = $q_dp->fetch();
$dp_paid = (float)($dp['jumlah_dp'] ?? 0);

// Fetch Cicilan list
$q_cic = $db->prepare("SELECT * FROM cicilan_kpr WHERE id_pengajuan = ? ORDER BY bulan_ke ASC");
$q_cic->execute([$id_pengajuan]);
$cicilan_list = $q_cic->fetchAll();

$total_cic_paid = 0.0;
$jml_lunas = 0;
foreach ($cicilan_list as $c) {
    if ($c['status_bayar'] === 'lunas') {
        $total_cic_paid += (float)$c['jumlah_cicilan'];
        $jml_lunas++;
    }
}

$total_masuk = $booking_fee + $dp_paid + $total_cic_paid;
$sisa_pelunasan = max(0, (float)$kpr['harga'] - $total_masuk);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan_Keuangan_KPR_<?= htmlspecialchars($kpr['nama_lengkap']) ?>.pdf</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            margin: 0;
            padding: 30px;
            background-color: #fff;
            font-size: 13px;
            line-height: 1.5;
        }
        .header-print {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px double #1e3a8a;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .logo-area h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 800;
            color: #1e3a8a;
            letter-spacing: 0.5px;
        }
        .logo-area p {
            margin: 3px 0 0;
            font-size: 11px;
            color: #666;
        }
        .report-meta {
            text-align: right;
        }
        .report-meta h2 {
            margin: 0 0 5px;
            font-size: 16px;
            color: #2563eb;
            font-weight: 700;
        }
        .report-meta p {
            margin: 2px 0;
            font-size: 11px;
            color: #666;
        }
        .grid-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        .card-info {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 15px;
            background: #f8fafc;
        }
        .card-info h3 {
            margin: 0 0 10px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 6px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 5px 0;
            vertical-align: top;
        }
        .info-table td.lbl {
            width: 120px;
            color: #64748b;
            font-weight: 600;
        }
        .info-table td.val {
            font-weight: 700;
            color: #0f172a;
        }
        .rekap-box {
            background: linear-gradient(135deg, #1e3a8a, #0f172a);
            border-radius: 12px;
            color: #fff;
            padding: 20px;
            margin-bottom: 25px;
        }
        .rekap-box h3 {
            margin: 0 0 15px;
            font-size: 13px;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: #94a3b8;
        }
        .rekap-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 15px;
        }
        .rekap-item {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 8px;
            padding: 10px;
        }
        .rekap-item .lbl {
            font-size: 10px;
            color: #94a3b8;
            margin-bottom: 4px;
        }
        .rekap-item .val {
            font-size: 13px;
            font-weight: 800;
        }
        .progress-line {
            height: 6px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .progress-line-fill {
            height: 100%;
            background: #10b981;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .data-table th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: 700;
            text-align: left;
            padding: 8px 10px;
            border-bottom: 2px solid #cbd5e1;
            font-size: 11px;
            text-transform: uppercase;
        }
        .data-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
        }
        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-lunas { background-color: #d1fae5; color: #065f46; }
        .badge-belum { background-color: #f3f4f6; color: #4b5563; }
        .badge-late { background-color: #fee2e2; color: #991b1b; }
        .footer-print {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .note-print {
            max-width: 400px;
            font-size: 11px;
            color: #64748b;
        }
        .signature-box {
            text-align: center;
            width: 200px;
        }
        .signature-box p {
            margin: 0;
        }
        .signature-space {
            height: 60px;
        }
        @media print {
            body {
                padding: 10px;
                font-size: 11px;
            }
            .no-print {
                display: none !important;
            }
            .card-info {
                background: #fff !important;
            }
            .rekap-item {
                background: rgba(0, 0, 0, 0.05) !important;
                color: #000 !important;
            }
            .rekap-item .lbl {
                color: #555 !important;
            }
            .rekap-box {
                background: #f1f5f9 !important;
                color: #000 !important;
                border: 1px solid #cbd5e1;
            }
            .rekap-box h3 {
                color: #333 !important;
            }
            .progress-line {
                background: #e2e8f0 !important;
            }
            .progress-line-fill {
                background: #10b981 !important;
            }
        }
    </style>
</head>
<body>

    <div class="no-print" style="background: #f1f5f9; padding: 12px 20px; border-radius: 8px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
        <span style="font-weight: 700; color: #475569;">📄 Pratinjau Laporan KPR</span>
        <div style="display: flex; gap: 10px;">
            <button onclick="window.print()" style="background: #2563eb; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 700; cursor: pointer;">🖨️ Cetak Laporan</button>
            <button onclick="window.close()" style="background: #64748b; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 700; cursor: pointer;">Tutup Halaman</button>
        </div>
    </div>

    <!-- Header Laporan -->
    <div class="header-print">
        <div class="logo-area">
            <h1>PT RUMAHKPR INDONESIA</h1>
            <p>Kantor Pemasaran Pusat: Jl. Serpong Raya No. 45, Tangerang Selatan</p>
            <p>Telp: (021) 8899-7766 | Email: finance@rumahkpr.co.id</p>
        </div>
        <div class="report-meta">
            <h2>LAPORAN KEUANGAN KPR</h2>
            <p>ID Pengajuan: <b>#<?= $kpr['id_pengajuan'] ?></b></p>
            <p>Tanggal: <?= date('d M Y') ?></p>
            <p>Status: <?= strtoupper(str_replace('_', ' ', $kpr['status_pengajuan'])) ?></p>
        </div>
    </div>

    <!-- Grid Data Pemohon & Unit -->
    <div class="grid-info">
        <div class="card-info">
            <h3>👤 Data Customer / Pemohon</h3>
            <table class="info-table">
                <tr>
                    <td class="lbl">Nama Lengkap</td>
                    <td class="val">: <?= htmlspecialchars($kpr['nama_lengkap']) ?></td>
                </tr>
                <tr>
                    <td class="lbl">Email</td>
                    <td class="val">: <?= htmlspecialchars($kpr['email']) ?></td>
                </tr>
                <tr>
                    <td class="lbl">No. HP</td>
                    <td class="val">: <?= htmlspecialchars($kpr['no_hp']) ?></td>
                </tr>
                <tr>
                    <td class="lbl">Pekerjaan</td>
                    <td class="val">: <?= htmlspecialchars($kpr['pekerjaan'] ?? '-') ?></td>
                </tr>
                <tr>
                    <td class="lbl">Penghasilan / Bln</td>
                    <td class="val">: <?= format_rupiah($kpr['penghasilan']) ?></td>
                </tr>
            </table>
        </div>
        <div class="card-info">
            <h3>🏠 Detail Properti & Bank</h3>
            <table class="info-table">
                <tr>
                    <td class="lbl">Perumahan / Unit</td>
                    <td class="val">: <?= htmlspecialchars($kpr['nama_perumahan']) ?> (Blok <?= htmlspecialchars($kpr['blok'].'-'.$kpr['kode_unit']) ?>)</td>
                </tr>
                <tr>
                    <td class="lbl">Tipe Unit</td>
                    <td class="val">: Tipe <?= htmlspecialchars($kpr['nama_tipe']) ?></td>
                </tr>
                <tr>
                    <td class="lbl">Harga Properti</td>
                    <td class="val" style="color: #10b981;">: <?= format_rupiah($kpr['harga']) ?></td>
                </tr>
                <tr>
                    <td class="lbl">Mitra Bank</td>
                    <td class="val">: <?= htmlspecialchars($kpr['nama_bank']) ?> (Bunga: <?= $kpr['bunga_kpr'] ?>%)</td>
                </tr>
                <tr>
                    <td class="lbl">Tenor Kredit</td>
                    <td class="val">: <?= $kpr['tenor'] ?> Tahun (<?= $kpr['tenor']*12 ?> Bulan)</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Rekap Pelunasan -->
    <div class="rekap-box">
        <h3>📊 Rekapitulasi Pelunasan Rumah</h3>
        <div class="rekap-grid">
            <div class="rekap-item">
                <div class="lbl">Booking Fee</div>
                <div class="val" style="color: #60a5fa;"><?= format_rupiah($booking_fee) ?></div>
            </div>
            <div class="rekap-item">
                <div class="lbl">Uang Muka (DP)</div>
                <div class="val" style="color: #fbbf24;"><?= format_rupiah($dp_paid) ?></div>
            </div>
            <div class="rekap-item">
                <div class="lbl">Cicilan Lunas</div>
                <div class="val" style="color: #34d399;"><?= format_rupiah($total_cic_paid) ?></div>
            </div>
            <div class="rekap-item">
                <div class="lbl">Total Dana Masuk</div>
                <div class="val" style="color: #a7f3d0;"><?= format_rupiah($total_masuk) ?></div>
            </div>
        </div>
        <div class="progress-line">
            <?php $pct = round(($total_masuk / (float)$kpr['harga']) * 100); ?>
            <div class="progress-line-fill" style="width: <?= $pct ?>%;"></div>
        </div>
        <div style="display: flex; justify-content: space-between; font-size: 11px;">
            <span>Progres Pelunasan Properti: <b><?= $pct ?>%</b></span>
            <span style="color: #f87171; font-weight: 800;">SISA TARGET PELUNASAN: <?= format_rupiah($sisa_pelunasan) ?></span>
        </div>
    </div>

    <!-- Buku Pembantu Cicilan -->
    <h3 style="margin: 0 0 10px; font-size: 14px; font-weight: 700; color: #0f172a; border-bottom: 2px solid #e2e8f0; padding-bottom: 6px;">📅 Buku Pembantu Cicilan Bulanan</h3>
    <?php if (empty($cicilan_list)): ?>
        <p style="color: #64748b; text-align: center; padding: 20px; border: 1px dashed #cbd5e1; border-radius: 8px;">Jadwal cicilan bulanan belum di-generate.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 80px;">Bulan</th>
                    <th>Tanggal Jatuh Tempo</th>
                    <th>Pokok</th>
                    <th>Bunga</th>
                    <th>Total Angsuran</th>
                    <th>Status</th>
                    <th>Tanggal Bayar</th>
                    <th>Keterangan / Metode</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sisa_harga_berjalan = (float)$kpr['harga'] - $booking_fee - $dp_paid;
                foreach ($cicilan_list as $c): 
                    $is_late = ($c['status_bayar']==='belum' && strtotime($c['tanggal_jatuh_tempo']) < time());
                    
                    // Hitung tagihan riil menyesuaikan sisa harga berjalan
                    $tagihan_riil = min((float)$c['jumlah_cicilan'], $sisa_harga_berjalan);
                    if ($tagihan_riil < 0) $tagihan_riil = 0;
                    
                    if ($c['status_bayar'] === 'lunas') {
                        $sisa_harga_berjalan = max(0, $sisa_harga_berjalan - $c['jumlah_cicilan']);
                    }
                    ?>
                    <tr>
                        <td><b>Bulan <?= $c['bulan_ke'] ?></b></td>
                        <td><?= format_tanggal($c['tanggal_jatuh_tempo']) ?></td>
                        <td><?= format_rupiah($c['pokok']) ?></td>
                        <td style="color: #ef4444;"><?= format_rupiah($c['bunga']) ?></td>
                        <td style="font-weight: 700; color: #1e3a8a;"><?= format_rupiah($tagihan_riil) ?></td>
                        <td>
                            <?php if ($c['status_bayar'] === 'lunas'): ?>
                                <span class="badge badge-lunas">Lunas</span>
                            <?php elseif ($is_late): ?>
                                <span class="badge badge-late">Terlambat</span>
                            <?php else: ?>
                                <span class="badge badge-belum">Belum</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $c['tanggal_bayar'] ? format_datetime($c['tanggal_bayar']) : '-' ?></td>
                        <td style="font-size: 11px; color: #64748b;">
                            <?php if ($c['status_bayar'] === 'lunas'): ?>
                                Terverifikasi Admin
                            <?php elseif ($c['status_verifikasi'] === 'pending'): ?>
                                ⏳ Menunggu Verifikasi Bukti
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Print Footer -->
    <div class="footer-print">
        <div class="note-print">
            <p><b>Catatan:</b></p>
            <ul>
                <li>Laporan ini adalah bukti catatan keuangan resmi KPR dari PT RumahKPR Indonesia.</li>
                <li>Cicilan terbayar adalah cicilan yang status pembayarannya telah terverifikasi VALID oleh Admin Keuangan.</li>
                <li>Jika terdapat perbedaan data transaksi, hubungi Bagian Finance dengan melampirkan bukti transfer bank asli.</li>
            </ul>
        </div>
        <div class="signature-box">
            <p>Tangerang Selatan, <?= date('d F Y') ?></p>
            <p style="font-weight: bold; color: #64748b; margin-top: 5px;">Bagian Keuangan / Finance</p>
            <div class="signature-space"></div>
            <p style="text-decoration: underline; font-weight: 800; color: #0f172a;">ADMIN FINANCE KPR</p>
            <p style="font-size: 10px; color: #64748b;">PT RumahKPR Indonesia</p>
        </div>
    </div>

</body>
</html>
