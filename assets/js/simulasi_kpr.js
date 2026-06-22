// assets/js/simulasi_kpr.js
// Kalkulator Simulasi KPR

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
        const i     = (bunga / 100) / 12;
        const n     = tenor * 12;
        let cicilan;

        if (i === 0) { cicilan = pokok / n; }
        else { cicilan = pokok * i * Math.pow(1+i,n) / (Math.pow(1+i,n) - 1); }

        const totalBayar = cicilan * n;
        const totalBunga = totalBayar - pokok;

        hasilDiv.innerHTML = `
            <div class="sim-result">
                <h3>📊 Hasil Simulasi KPR</h3>
                <div class="sim-grid">
                    <div class="sim-item">
                        <span class="sim-label">Harga Properti</span>
                        <span class="sim-value">${formatRupiah(harga)}</span>
                    </div>
                    <div class="sim-item">
                        <span class="sim-label">Uang Muka (DP)</span>
                        <span class="sim-value">${formatRupiah(dp)} (${((dp/harga)*100).toFixed(1)}%)</span>
                    </div>
                    <div class="sim-item">
                        <span class="sim-label">Pinjaman Pokok</span>
                        <span class="sim-value">${formatRupiah(pokok)}</span>
                    </div>
                    <div class="sim-item highlight">
                        <span class="sim-label">Cicilan / Bulan</span>
                        <span class="sim-value big">${formatRupiah(Math.round(cicilan))}</span>
                    </div>
                    <div class="sim-item">
                        <span class="sim-label">Total Bunga</span>
                        <span class="sim-value">${formatRupiah(Math.round(totalBunga))}</span>
                    </div>
                    <div class="sim-item">
                        <span class="sim-label">Total Pembayaran</span>
                        <span class="sim-value">${formatRupiah(Math.round(totalBayar))}</span>
                    </div>
                    <div class="sim-item">
                        <span class="sim-label">Tenor</span>
                        <span class="sim-value">${tenor} Tahun (${n} Bulan)</span>
                    </div>
                    <div class="sim-item">
                        <span class="sim-label">Bunga per Tahun</span>
                        <span class="sim-value">${bunga}%</span>
                    </div>
                </div>
                <p class="sim-note">* Simulasi ini bersifat estimasi. Cicilan aktual dapat berbeda sesuai kebijakan bank.</p>
            </div>
        `;
        hasilDiv.style.display = 'block';
        hasilDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    // Format angka input
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
