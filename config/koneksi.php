<?php
// config/koneksi.php
$host    = "localhost";
$user    = "root";
$pass    = "";
$db_name = "perumahan_kpr";

try {
    $db = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $user, $pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("<div style='padding:20px;background:#f8d7da;color:#721c24;font-family:Arial;border-radius:8px;margin:20px;'>
        <h3>🚨 Koneksi Database Gagal!</h3>
        <p>Pastikan MySQL sudah berjalan dan database <b>$db_name</b> sudah dibuat.</p>
        <p>Import file <b>database/perumahan_kpr.sql</b> ke phpMyAdmin.</p>
        <b>Error:</b> <code>" . $e->getMessage() . "</code>
    </div>");
}
?>
