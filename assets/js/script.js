// assets/js/script.js
// Script Umum Sistem Perumahan KPR

// Toggle mobile navbar
document.addEventListener('DOMContentLoaded', function () {
    const hamburger = document.getElementById('hamburger');
    const navLinks  = document.getElementById('navLinks');
    if (hamburger && navLinks) {
        hamburger.addEventListener('click', () => navLinks.classList.toggle('open'));
    }

    // Toggle sidebar mobile (admin & customer)
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar, .csidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    }

    // Auto-hide flash message
    const flash = document.querySelector('.flash-msg');
    if (flash) {
        setTimeout(() => { flash.style.opacity = '0'; flash.style.transition = 'opacity .5s'; setTimeout(() => flash.remove(), 500); }, 4000);
    }

    // Konfirmasi hapus dengan modal
    document.querySelectorAll('[data-hapus]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const url  = this.dataset.hapus;
            const nama = this.dataset.nama || 'data ini';
            if (confirm('Yakin ingin menghapus "' + nama + '"? Tindakan ini tidak bisa dibatalkan!')) {
                window.location.href = url;
            }
        });
    });

    // Preview gambar sebelum upload
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

    // Format input angka menjadi ribuan
    document.querySelectorAll('.format-angka').forEach(input => {
        input.addEventListener('input', function () {
            let val = this.value.replace(/\D/g, '');
            this.value = val ? parseInt(val).toLocaleString('id-ID') : '';
        });
    });
});

// Fungsi konfirmasi hapus global
function konfirmasiHapus(url, nama) {
    if (confirm('Yakin hapus "' + nama + '"?')) window.location.href = url;
}
