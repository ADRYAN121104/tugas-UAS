<?php
// guest/simulasi_kpr.php
$page_title = 'Simulasi KPR - Kalkulator Cicilan';
$harga_awal = (int)($_GET['harga'] ?? 500000000);
require_once '../config/koneksi.php';
require_once '../config/functions.php';
require_once '../includes/header.php';
$list_bank = $db->query("SELECT * FROM bank ORDER BY bunga_kpr ASC")->fetchAll();
?>
<main class="container" style="padding:48px 24px 64px;">
    <div style="text-align:center;margin-bottom:40px;">
        <h1 class="section-title">🧮 Simulasi Cicilan KPR</h1>
        <p class="section-sub">Hitung estimasi cicilan KPR Anda berdasarkan harga properti dan tenor yang diinginkan</p>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:32px;align-items:start;" id="sim-grid">
        <!-- Form Simulasi -->
        <div style="background:#fff;border-radius:16px;padding:28px;box-shadow:0 4px 20px rgba(0,0,0,.06);border:1px solid #e2e8f0;">
            <h3 style="font-size:18px;font-weight:800;margin-bottom:22px;">Data Simulasi</h3>
            <form id="formSimulasi">
                <div class="form-group">
                    <label>Harga Properti (Rp)</label>
                    <input type="text" id="harga" class="form-control format-angka" placeholder="500.000.000" value="<?= number_format($harga_awal,0,',','.') ?>" required>
                </div>
                <div class="form-group">
                    <label>Uang Muka / DP (Rp)</label>
                    <input type="text" id="dp" class="form-control format-angka" placeholder="100.000.000">
                    <small style="color:#94a3b8;font-size:12px;">Min. 10-30% dari harga properti</small>
                </div>
                <div class="form-group">
                    <label>Pilih Bank Rekanan</label>
                    <select id="bankSelect" class="form-control" onchange="updateBunga()">
                        <option value="">-- Masukkan bunga manual --</option>
                        <?php foreach($list_bank as $b): ?>
                        <option value="<?= $b['bunga_kpr'] ?>" data-tenor="<?= $b['tenor_maksimal'] ?>"><?= htmlspecialchars($b['nama_bank']) ?> - <?= $b['bunga_kpr'] ?>% / <?= $b['tenor_maksimal'] ?> thn maks</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Bunga per Tahun (%)</label>
                        <input type="number" id="bunga" class="form-control" step="0.01" min="0" value="7.5" required>
                    </div>
                    <div class="form-group">
                        <label>Tenor (Tahun)</label>
                        <select id="tenor" class="form-control">
                            <?php for($t=5;$t<=30;$t+=5): ?>
                            <option value="<?= $t ?>" <?= $t==15?'selected':'' ?>><?= $t ?> Tahun</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg btn-block" style="justify-content:center;">🧮 Hitung Cicilan</button>
            </form>
        </div>

        <!-- Hasil -->
        <div id="hasilSimulasi" style="display:none;">
            <!-- Diisi oleh JavaScript -->
        </div>
        <div id="placeholder-hasil" style="background:#f8fafc;border-radius:16px;padding:40px;border:2px dashed #e2e8f0;text-align:center;color:#94a3b8;">
            <div style="font-size:60px;margin-bottom:16px;">📊</div>
            <h4 style="color:#475569;margin-bottom:8px;">Hasil Simulasi</h4>
            <p style="font-size:14px;">Isi form di sebelah kiri dan klik Hitung Cicilan</p>
        </div>
    </div>

    <!-- Tabel Bank Rekanan -->
    <div style="margin-top:48px;">
        <h3 style="font-size:20px;font-weight:800;margin-bottom:20px;">🏦 Daftar Bank Rekanan</h3>
        <div class="tabel-wrap" style="background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.06);overflow:hidden;">
            <table class="tabel">
                <thead><tr><th>Nama Bank</th><th>Bunga / Tahun</th><th>Tenor Maks</th><th>Aksi</th></tr></thead>
                <tbody>
                    <?php foreach($list_bank as $b): ?>
                    <tr>
                        <td><b>🏦 <?= htmlspecialchars($b['nama_bank']) ?></b></td>
                        <td><span style="color:#2563eb;font-weight:700;"><?= $b['bunga_kpr'] ?>%</span></td>
                        <td><?= $b['tenor_maksimal'] ?> Tahun</td>
                        <td><button class="btn btn-outline btn-sm" onclick="pilihBank(<?= $b['bunga_kpr'] ?>,<?= $b['tenor_maksimal'] ?>)">Gunakan Bank Ini</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<style>
.sim-result{background:#fff;border-radius:16px;padding:28px;border:1px solid #e2e8f0;box-shadow:0 4px 20px rgba(0,0,0,.06);}
.sim-result h3{font-size:18px;font-weight:800;margin-bottom:20px;color:#0f172a;}
.sim-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.sim-item{background:#f8fafc;border-radius:10px;padding:14px;}
.sim-item.highlight{background:linear-gradient(135deg,#2563eb,#6366f1);color:#fff;grid-column:span 2;}
.sim-label{display:block;font-size:12px;color:#94a3b8;margin-bottom:4px;}
.sim-item.highlight .sim-label{color:rgba(255,255,255,.7);}
.sim-value{font-size:15px;font-weight:700;color:#0f172a;}
.sim-value.big{font-size:26px;}
.sim-item.highlight .sim-value{color:#fff;}
.sim-note{font-size:12px;color:#94a3b8;margin-top:16px;font-style:italic;}
@media(max-width:768px){#sim-grid{grid-template-columns:1fr!important;}.sim-grid{grid-template-columns:1fr!important;}.sim-item.highlight{grid-column:span 1;}}
</style>
<script src="../assets/js/simulasi_kpr.js"></script>
<script>
function updateBunga(){
    const sel=document.getElementById('bankSelect');
    const opt=sel.options[sel.selectedIndex];
    if(sel.value){
        document.getElementById('bunga').value=sel.value;
        document.getElementById('tenor').value=Math.min(opt.dataset.tenor,30);
    }
}
function pilihBank(bunga,tenor){
    document.getElementById('bunga').value=bunga;
    document.getElementById('tenor').value=Math.min(tenor,30);
    document.querySelector('#formSimulasi button').click();
}
document.getElementById('hasilSimulasi').addEventListener('DOMNodeInserted',function(){
    document.getElementById('placeholder-hasil').style.display='none';
});
</script>
<?php require_once '../includes/footer.php'; ?>
