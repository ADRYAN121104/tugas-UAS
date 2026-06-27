// assets/js/simulasi_kpr.js
// Kalkulator Simulasi KPR — Annuity Formula (real calculation)

document.addEventListener('DOMContentLoaded', function () {
    const formSim  = document.getElementById('formSimulasi');
    const hasilDiv = document.getElementById('hasilSimulasi');

    if (!formSim) return;

    formSim.addEventListener('submit', function (e) {
        e.preventDefault();

        const harga  = parseFloat(document.getElementById('harga').value.replace(/\D/g,'')) || 0;
        const dp     = parseFloat(document.getElementById('dp').value.replace(/\D/g,'')) || 0;
        const bunga  = parseFloat(document.getElementById('bunga').value) || 0;
        const tenor  = parseInt(document.getElementById('tenor').value) || 0;

        if (harga <= 0 || tenor <= 0) { alert('Isi semua field dengan benar.'); return; }
        if (dp >= harga) { alert('Uang muka tidak boleh melebihi harga properti.'); return; }

        const pokok = harga - dp;
        const i     = (bunga / 100) / 12;  // bunga per bulan
        const n     = tenor * 12;           // total bulan

        // Hitung cicilan annuity
        let cicilan;
        if (i === 0) {
            cicilan = pokok / n;
        } else {
            cicilan = pokok * i * Math.pow(1+i,n) / (Math.pow(1+i,n) - 1);
        }
        cicilan = Math.round(cicilan);

        const totalBayar = cicilan * n;
        const totalBunga = totalBayar - pokok;

        hasilDiv.innerHTML = `
            <div class="sim-result-card">
                <h3>📊 Hasil Simulasi KPR</h3>
                <div class="sim-cicilan-label">Cicilan Per Bulan</div>
                <div class="sim-cicilan-val">${formatRupiah(cicilan)}</div>
                <div class="sim-cicilan-badge">📈 Bunga ${bunga}% / Tahun · ${tenor} Tahun</div>
                <div class="sim-rows">
                    <div class="sim-row">
                        <span class="sim-row-label">Harga Properti</span>
                        <span class="sim-row-val">${formatRupiah(harga)}</span>
                    </div>
                    <div class="sim-row">
                        <span class="sim-row-label">Uang Muka (DP)</span>
                        <span class="sim-row-val">${formatRupiah(dp)} (${((dp/harga)*100).toFixed(1)}%)</span>
                    </div>
                    <div class="sim-row">
                        <span class="sim-row-label">Pinjaman Pokok</span>
                        <span class="sim-row-val">${formatRupiah(pokok)}</span>
                    </div>
                    <div class="sim-row">
                        <span class="sim-row-label">Tenor Kredit</span>
                        <span class="sim-row-val">${tenor} Tahun (${n} bulan)</span>
                    </div>
                    <div class="sim-row">
                        <span class="sim-row-label">Total Bunga</span>
                        <span class="sim-row-val">${formatRupiah(totalBunga)}</span>
                    </div>
                    <div class="sim-row">
                        <span class="sim-row-label">Total Pembayaran</span>
                        <span class="sim-row-val">${formatRupiah(totalBayar)}</span>
                    </div>
                </div>
                <p class="sim-note">* Estimasi berdasarkan bunga flat. Angka aktual tergantung kebijakan bank.</p>
            </div>
        `;
        hasilDiv.style.display = 'block';
        hasilDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    // Format angka input otomatis
    ['harga','dp'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', function () {
            const raw = this.value.replace(/\D/g,'');
            this.value = raw ? parseInt(raw).toLocaleString('id-ID') : '';
        });
    });
});

function formatRupiah(n) {
    return 'Rp ' + Math.round(n).toLocaleString('id-ID');
}
