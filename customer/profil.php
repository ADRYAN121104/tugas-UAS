<?php
// customer/profil.php
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';

$user = $db->prepare("SELECT * FROM users WHERE id_user=?"); 
$user->execute([id_user()]); 
$user = $user->fetch();

$page_title = 'Profil Saya - KPR Perumahan';
require_once '../includes/header.php';
?>
<main class="container" style="padding:40px 24px 60px;">
    <?php tampil_flash(); ?>
    <div style="margin-bottom:28px;">
        <h1 class="section-title">👤 Profil Saya</h1>
        <p class="section-sub">Kelola informasi pribadi dan pengaturan keamanan akun Anda</p>
    </div>

    <div style="display:grid;grid-template-columns:300px 1fr;gap:24px;" id="profil-grid">
        <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:30px 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); text-align:center;">
            <div style="width:90px;height:90px;border-radius:50%;background:linear-gradient(135deg,#1e3a8a,#2563eb);display:flex;align-items:center;justify-content:center;color:#fff;font-size:36px;font-weight:800;margin:0 auto 16px;">
                <?= strtoupper(substr($user['nama_lengkap'],0,1)) ?>
            </div>
            <h3 style="font-size:18px;font-weight:800;margin-bottom:4px;color:#0f172a;"><?= htmlspecialchars($user['nama_lengkap']) ?></h3>
            <p style="font-size:13px;color:#64748b;margin-bottom:16px;"><?= htmlspecialchars($user['email']) ?></p>
            <span style="background:#dbeafe;color:#1e40af;padding:6px 16px;border-radius:20px;font-size:12px;font-weight:700;">👤 Customer</span>
            
            <div style="margin-top:20px;padding-top:20px;border-top:1px solid #f1f5f9;">
                <div style="font-size:12px;color:#94a3b8;">Bergabung Sejak</div>
                <div style="font-weight:700;font-size:13.5px;margin-top:4px;color:#334155;"><?= format_tanggal($user['created_at']) ?></div>
            </div>
        </div>

        <div style="background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:24px; box-shadow: 0 4px 20px rgba(0,0,0,0.05);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; border-bottom:1px solid #f1f5f9; padding-bottom:10px;">
                <h3 style="font-size:16px; font-weight:800; margin:0;">Informasi Detail Akun</h3>
                <a href="edit_profil.php" class="btn btn-primary btn-sm">✏️ Edit Profil</a>
            </div>
            
            <table style="width:100%;font-size:14.5px; border-collapse:collapse;">
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:14px 0;color:#64748b;width:35%;font-weight:600;">Nama Lengkap</td>
                    <td style="padding:14px 0;font-weight:700; color:#334155;"><?= htmlspecialchars($user['nama_lengkap']) ?></td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:14px 0;color:#64748b;font-weight:600;">Alamat Email</td>
                    <td style="padding:14px 0;font-weight:700; color:#334155;"><?= htmlspecialchars($user['email']) ?></td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:14px 0;color:#64748b;font-weight:600;">Nomor HP / WhatsApp</td>
                    <td style="padding:14px 0;font-weight:700; color:#334155;"><?= htmlspecialchars($user['no_hp']??'-') ?></td>
                </tr>
                <tr>
                    <td style="padding:14px 0;color:#64748b;font-weight:600;">Hak Akses (Role)</td>
                    <td style="padding:14px 0;font-weight:700; color:#334155;"><?= ucfirst($user['role']) ?></td>
                </tr>
            </table>
        </div>
    </div>
</main>
<script src="../assets/js/script.js"></script>
<style>@media(max-width:768px){#profil-grid{grid-template-columns:1fr!important;}}</style>
<?php require_once '../includes/footer.php'; ?>
