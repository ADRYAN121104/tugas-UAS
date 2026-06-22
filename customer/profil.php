<?php
// customer/profil.php
require_once '../config/koneksi.php';
require_once '../config/cek_customer.php';
require_once '../config/functions.php';
require_once '../includes/sidebar_customer.php';
$user = $db->prepare("SELECT * FROM users WHERE id_user=?"); $user->execute([id_user()]); $user=$user->fetch();
?>
<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Profil Saya</title>
<link rel="stylesheet" href="../assets/css/customer.css"></head><body>
<?php sidebar_customer('profil'); ?>
<div class="cmain"><div class="ccontent">
<?php tampil_flash(); ?>
<div style="margin-bottom:22px;"><h2 style="font-size:22px;font-weight:800;">👤 Profil Saya</h2></div>
<div style="display:grid;grid-template-columns:280px 1fr;gap:24px;" id="profil-grid">
    <div class="cpanel">
        <div class="cpanel-body" style="text-align:center;padding:30px 20px;">
            <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#2563eb,#8b5cf6);display:flex;align-items:center;justify-content:center;color:#fff;font-size:32px;font-weight:800;margin:0 auto 16px;"><?= strtoupper(substr($user['nama_lengkap'],0,1)) ?></div>
            <h3 style="font-size:17px;font-weight:800;margin-bottom:4px;"><?= htmlspecialchars($user['nama_lengkap']) ?></h3>
            <p style="font-size:13px;color:#94a3b8;margin-bottom:16px;"><?= htmlspecialchars($user['email']) ?></p>
            <span style="background:#dbeafe;color:#1e40af;padding:4px 14px;border-radius:20px;font-size:12px;font-weight:700;">👤 Customer</span>
            <div style="margin-top:20px;padding-top:20px;border-top:1px solid #f1f5f9;">
                <div style="font-size:12px;color:#94a3b8;">Bergabung sejak</div>
                <div style="font-weight:700;font-size:13px;margin-top:4px;"><?= format_tanggal($user['created_at']) ?></div>
            </div>
        </div>
    </div>
    <div class="cpanel">
        <div class="cpanel-header"><h3>Informasi Akun</h3><a href="edit_profil.php" class="cbtn cbtn-primary cbtn-sm">✏️ Edit Profil</a></div>
        <div class="cpanel-body">
            <table style="width:100%;font-size:14px;">
                <?php $info=[['Nama Lengkap',$user['nama_lengkap']],['Email',$user['email']],['No. HP / WA',$user['no_hp']??'-'],['Role',ucfirst($user['role'])]]; foreach($info as $i): ?>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:14px 0;color:#64748b;width:40%;font-weight:600;"><?= $i[0] ?></td>
                    <td style="padding:14px 0;font-weight:700;"><?= htmlspecialchars($i[1]) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</div>
</div></div>
<script src="../assets/js/script.js"></script>
<style>@media(max-width:768px){#profil-grid{grid-template-columns:1fr!important;}}</style>
</body></html>
