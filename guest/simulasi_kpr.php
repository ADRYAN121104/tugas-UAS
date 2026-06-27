<?php
// guest/simulasi_kpr.php
$page_title = 'Simulasi KPR - Kalkulator Cicilan';
$harga_awal = (int)($_GET['harga'] ?? 500000000);
require_once '../config/koneksi.php';
require_once '../config/functions.php';
require_once '../includes/header.php';
$list_bank = $db->query("SELECT * FROM bank ORDER BY bunga_kpr ASC")->fetchAll();
?>
<style>
.sim-wrap{max-width:1100px;margin:0 auto;padding:48px 24px 72px;}
.sim-hero{text-align:center;margin-bottom:44px;}
.sim-hero h1{font-size:32px;font-weight:900;color:var(--dark);margin-bottom:10px;}
.sim-hero p{font-size:16px;color:var(--muted);max-width:550px;margin:0 auto;}
.sim-layout{display:grid;grid-template-columns:1fr 1fr;gap:28px;align-items:start;}
.sim-card{background:#fff;border-radius:20px;padding:32px;box-shadow:0 4px 24px rgba(37,99,235,.08);border:1px solid #e8eeff;}
.sim-card h3{font-size:17px;font-weight:800;color:var(--dark);margin-bottom:24px;display:flex;align-items:center;gap:9px;}

/* Hasil card */
.sim-result-card{
  background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 50%,#2563eb 100%);
  border-radius:20px;padding:32px;color:#fff;
  box-shadow:0 12px 40px rgba(37,99,235,.25);
  position:relative;overflow:hidden;
}
.sim-result-card::before{content:'';position:absolute;top:-40px;right:-40px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.04);}
.sim-result-card::after{content:'';position:absolute;bottom:-60px;left:-30px;width:160px;height:160px;border-radius:50%;background:rgba(255,255,255,.03);}
.sim-result-card h3{color:rgba(255,255,255,.7);font-size:14px;font-weight:700;margin-bottom:6px;position:relative;z-index:1;}
.sim-cicilan-label{font-size:13px;opacity:.65;margin-bottom:4px;font-weight:600;position:relative;z-index:1;}
.sim-cicilan-val{font-size:38px;font-weight:900;letter-spacing:-.5px;margin-bottom:6px;position:relative;z-index:1;}
.sim-cicilan-badge{display:inline-flex;align-items:center;gap:5px;background:rgba(255,255,255,.15);padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;border:1px solid rgba(255,255,255,.2);margin-bottom:22px;position:relative;z-index:1;}
.sim-rows{display:flex;flex-direction:column;gap:0;position:relative;z-index:1;}
.sim-row{display:flex;justify-content:space-between;align-items:center;padding:11px 0;border-bottom:1px solid rgba(255,255,255,.08);}
.sim-row:last-child{border-bottom:none;}
.sim-row-label{font-size:13px;opacity:.65;font-weight:600;}
.sim-row-val{font-size:13.5px;font-weight:800;}
.sim-note{font-size:12px;opacity:.45;margin-top:16px;font-style:italic;position:relative;z-index:1;}

/* Bank grid */
.bank-section{margin-top:44px;}
.bank-section h3{font-size:18px;font-weight:800;margin-bottom:18px;color:var(--dark);}
.bank-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;}
.bank-card{background:#fff;border-radius:14px;padding:18px;border:2px solid #e8eeff;text-align:center;transition:.25s;cursor:pointer;}
.bank-card:hover{border-color:#2563eb;transform:translateY(-3px);box-shadow:0 8px 24px rgba(37,99,235,.12);}
.bank-card .bname{font-size:13.5px;font-weight:800;color:var(--dark);margin-bottom:4px;}
.bank-card .btenor{font-size:12px;color:var(--muted);}
.bank-card .bbunga{font-size:20px;font-weight:900;color:#2563eb;margin:6px 0;}

/* Placeholder */
.placeholder-sim{background:linear-gradient(135deg,#f8faff,#f0f4ff);border-radius:20px;padding:52px 32px;border:2px dashed #c7d8ff;text-align:center;}
.placeholder-sim .ico{font-size:56px;margin-bottom:16px;}
.placeholder-sim h4{font-size:17px;font-weight:800;color:#1e3a8a;margin-bottom:8px;}
.placeholder-sim p{font-size:14px;color:var(--muted);}

@media(max-width:768px){.sim-layout{grid-template-columns:1fr;}.bank-grid{grid-template-columns:1fr 1fr;}}
@media(max-width:480px){.bank-grid{grid-template-columns:1fr;}.sim-cicilan-val{font-size:28px;}}
</style>

<main class="sim-wrap">
    <?php tampil_flash(); ?>

    <div class="sim-hero">
        <h1>🧮 Simulasi Cicilan KPR</h1>
        <p>Hitung estimasi cicilan KPR Anda berdasarkan harga properti dan tenor yang diinginkan</p>
    </div>

    <div class="sim-layout">
        <!-- Form -->
        <div class="sim-card">
            <h3>📋 Data Simulasi</h3>
            <form id="formSimulasi">
                <div class="form-group">
                    <label>Harga Properti (Rp)</label>
                    <input type="text" id="harga" class="form-control format-angka"
                           placeholder="500.000.000"
                           value="<?= number_format($harga_awal,0,',','.') ?>" required>
                </div>
                <div class="form-group">
                    <label>Uang Muka / DP (Rp)</label>
                    <input type="text" id="dp" class="form-control format-angka" placeholder="100.000.000">
                    <small style="color:#94a3b8;font-size:12px;">Min. 10–30% dari harga properti</small>
                </div>
                <div class="form-group">
                    <label>Pilih Bank Rekanan</label>
                    <select id="bankSelect" class="form-control" onchange="updateBunga()">
                        <option value="">-- Pilih Bank --</option>
                        <?php foreach($list_bank as $b): ?>
                        <option value="<?= $b['bunga_kpr'] ?>" data-tenor="<?= $b['tenor_maksimal'] ?>">
                            <?= htmlspecialchars($b['nama_bank']) ?> (maks. <?= $b['tenor_maksimal'] ?> thn)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tenor (Tahun)</label>
                    <select id="tenor" class="form-control">
                        <?php for($t=5;$t<=30;$t+=5): ?>
                        <option value="<?= $t ?>" <?= $t==15?'selected':'' ?>><?= $t ?> Tahun</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <input type="hidden" id="bunga" value="7.5">
                <button type="submit" class="btn btn-primary btn-lg btn-block" style="justify-content:center;margin-top:8px;">
                    🧮 Hitung Cicilan
                </button>
            </form>
        </div>

        <!-- Hasil -->
        <div>
            <div id="hasilSimulasi" style="display:none;"></div>
            <div id="placeholder-hasil" class="placeholder-sim">
                <div class="ico">📊</div>
                <h4>Hasil Simulasi</h4>
                <p>Isi form di sebelah kiri<br>dan klik <b>Hitung Cicilan</b></p>
            </div>
        </div>
    </div>

    <!-- Bank Grid -->
    <div class="bank-section">
        <h3>🏦 Bank Rekanan Tersedia</h3>
        <div class="bank-grid">
            <?php foreach($list_bank as $b): ?>
            <div class="bank-card" onclick="pilihBank(<?= $b['bunga_kpr'] ?>,<?= $b['tenor_maksimal'] ?>)">
                <div class="bname">🏦 <?= htmlspecialchars($b['nama_bank']) ?></div>
                <div class="bbunga"><?= $b['bunga_kpr'] ?>%</div>
                <div class="btenor">Tenor maks. <?= $b['tenor_maksimal'] ?> Tahun</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>

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
    document.querySelector('#formSimulasi button[type=submit]').click();
    window.scrollTo({top:0,behavior:'smooth'});
}
document.getElementById('hasilSimulasi').addEventListener('DOMNodeInserted',function(){
    document.getElementById('placeholder-hasil').style.display='none';
    document.getElementById('hasilSimulasi').style.display='block';
});
</script>
<?php require_once '../includes/footer.php'; ?>
