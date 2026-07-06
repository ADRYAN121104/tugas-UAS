// assets/js/script.js
// Script Umum Sistem Perumahan KPR

document.addEventListener('DOMContentLoaded', function () {

    // ── Toggle mobile navbar ──────────────────────────────────────────────────
    const hamburger = document.getElementById('hamburger');
    const navLinks  = document.getElementById('navLinks');
    if (hamburger && navLinks) {
        hamburger.addEventListener('click', () => navLinks.classList.toggle('open'));
    }

    // ── Toggle sidebar mobile (admin & customer) ──────────────────────────────
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar, .csidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    }

    // ── Auto-hide flash message ───────────────────────────────────────────────
    const flash = document.querySelector('.flash-msg');
    if (flash) {
        setTimeout(() => {
            flash.style.opacity = '0';
            flash.style.transition = 'opacity .5s';
            setTimeout(() => flash.remove(), 500);
        }, 4000);
    }

    // ── MODAL HAPUS MODERN (ganti confirm() bawaan browser) ──────────────────
    // Buat elemen modal sekali
    if (!document.getElementById('modal-hapus')) {
        const modalHTML = `
        <div id="modal-hapus">
          <div class="modal-hapus-box">
            <div class="modal-hapus-icon">🗑️</div>
            <h3>Hapus Data?</h3>
            <p>Anda akan menghapus:</p>
            <span class="modal-hapus-nama" id="modal-hapus-nama">-</span>
            <p style="margin-top:-14px; margin-bottom:24px;">Tindakan ini <strong>tidak dapat dibatalkan</strong>. Data akan hilang permanen.</p>
            <div class="modal-hapus-btns">
              <button class="btn-modal-batal" id="modal-hapus-batal">Batal</button>
              <button class="btn-modal-hapus" id="modal-hapus-ok">🗑️ Ya, Hapus!</button>
            </div>
          </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    const modalHapus    = document.getElementById('modal-hapus');
    const modalNama     = document.getElementById('modal-hapus-nama');
    const modalBatal    = document.getElementById('modal-hapus-batal');
    const modalOk       = document.getElementById('modal-hapus-ok');
    let   hapusUrl      = '';

    // Listener untuk semua tombol hapus dengan data-hapus
    document.querySelectorAll('[data-hapus]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            hapusUrl      = this.dataset.hapus;
            const nama    = this.dataset.nama || 'data ini';
            modalNama.textContent = nama;
            modalHapus.classList.add('show');
        });
    });

    // Tombol batal
    if (modalBatal) {
        modalBatal.addEventListener('click', () => {
            modalHapus.classList.remove('show');
            hapusUrl = '';
        });
    }

    // Tombol ya hapus
    if (modalOk) {
        modalOk.addEventListener('click', () => {
            if (hapusUrl) window.location.href = hapusUrl;
        });
    }

    // Klik di luar modal = tutup
    if (modalHapus) {
        modalHapus.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
                hapusUrl = '';
            }
        });
    }

    // ── Preview gambar sebelum upload ─────────────────────────────────────────
    document.querySelectorAll('input[type="file"][data-preview]').forEach(input => {
        input.addEventListener('change', function () {
            const previewId = this.dataset.preview;
            const preview = document.getElementById(previewId);
            if (preview && this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = e => { preview.src = e.target.result; preview.style.display = 'block'; };
                reader.readAsDataURL(this.files[0]);
            }
        });
    });

    // ── Format input angka menjadi ribuan ─────────────────────────────────────
    document.querySelectorAll('.format-angka').forEach(input => {
        input.addEventListener('input', function () {
            let val = this.value.replace(/\D/g, '');
            this.value = val ? parseInt(val).toLocaleString('id-ID') : '';
        });
    });

    // ── Kalkulator Cicilan KPR (jika ada elemen di halaman) ──────────────────
    const kalkForm = document.getElementById('kalk-cicilan-form');
    if (kalkForm) {
        const inputHarga    = document.getElementById('kalk-harga');
        const inputDP       = document.getElementById('kalk-dp');
        const inputTenor    = document.getElementById('kalk-tenor');
        const inputBunga    = document.getElementById('kalk-bunga');
        const inputCicilan  = document.getElementById('kalk-cicilan-per-bulan');
        const outputCicilan = document.getElementById('kalk-hasil-cicilan');
        const outputLama    = document.getElementById('kalk-hasil-lama');
        const outputTotal   = document.getElementById('kalk-hasil-total');

        function hitungCicilan() {
            const harga  = parseFloat((inputHarga?.value || '0').replace(/\./g,'').replace(',','.')) || 0;
            const dp     = parseFloat((inputDP?.value || '0').replace(/\./g,'').replace(',','.')) || 0;
            const tenor  = parseInt(inputTenor?.value || '0') || 0;
            const bunga  = parseFloat(inputBunga?.value || '0') / 100 / 12;
            const pokok  = harga - dp;

            if (pokok <= 0 || tenor <= 0) return;

            let cicilan = 0;
            if (bunga > 0) {
                const n = tenor * 12;
                cicilan = pokok * (bunga * Math.pow(1 + bunga, n)) / (Math.pow(1 + bunga, n) - 1);
            } else {
                cicilan = pokok / (tenor * 12);
            }

            if (outputCicilan) outputCicilan.textContent = 'Rp ' + Math.round(cicilan).toLocaleString('id-ID');
            if (outputTotal)   outputTotal.textContent   = 'Rp ' + Math.round(cicilan * tenor * 12).toLocaleString('id-ID');
        }

        function hitungLama() {
            const harga    = parseFloat((inputHarga?.value || '0').replace(/\./g,'').replace(',','.')) || 0;
            const dp       = parseFloat((inputDP?.value || '0').replace(/\./g,'').replace(',','.')) || 0;
            const cicilan  = parseFloat((inputCicilan?.value || '0').replace(/\./g,'').replace(',','.')) || 0;
            const bunga    = parseFloat(inputBunga?.value || '0') / 100 / 12;
            const pokok    = harga - dp;

            if (cicilan <= 0 || pokok <= 0) return;

            let bulan = 0;
            if (bunga > 0) {
                bulan = -Math.log(1 - (pokok * bunga) / cicilan) / Math.log(1 + bunga);
            } else {
                bulan = pokok / cicilan;
            }

            if (bulan <= 0 || !isFinite(bulan)) {
                if (outputLama) outputLama.textContent = 'Cicilan terlalu kecil';
                return;
            }

            const tahun    = Math.floor(bulan / 12);
            const sisaBulan = Math.ceil(bulan % 12);
            if (outputLama) outputLama.textContent = (tahun > 0 ? tahun + ' tahun ' : '') + (sisaBulan > 0 ? sisaBulan + ' bulan' : '');
            if (outputTotal) outputTotal.textContent = 'Rp ' + Math.round(cicilan * Math.ceil(bulan)).toLocaleString('id-ID');
        }

        [inputHarga, inputDP, inputTenor, inputBunga].forEach(el => {
            if (el) el.addEventListener('input', hitungCicilan);
        });
        if (inputCicilan) inputCicilan.addEventListener('input', hitungLama);
    }
});

// ── Konfirmasi hapus global (fallback, dipanggil langsung dari onclick) ───────
function konfirmasiHapus(url, nama) {
    const modalHapus = document.getElementById('modal-hapus');
    const modalNama  = document.getElementById('modal-hapus-nama');
    const modalOk    = document.getElementById('modal-hapus-ok');
    if (modalHapus && modalNama && modalOk) {
        modalNama.textContent = nama || 'data ini';
        modalHapus.classList.add('show');
        // Override click ok agar redirect ke url yang benar
        modalOk.onclick = () => { window.location.href = url; };
    } else {
        if (confirm('Yakin hapus "' + nama + '"?')) window.location.href = url;
    }
}
