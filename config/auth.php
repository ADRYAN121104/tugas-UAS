<?php
// config/auth.php
require_once __DIR__ . '/session.php';

define('BASE_URL', '/perumahan_kpr/');

function sudah_login() {
    $uri = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($uri, '/admin/') !== false) {
        return isset($_SESSION['admin']['user_id']);
    }
    if (strpos($uri, '/customer/') !== false) {
        return isset($_SESSION['customer']['user_id']);
    }
    return isset($_SESSION['admin']['user_id']) || isset($_SESSION['customer']['user_id']);
}

function role_user() {
    $uri = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($uri, '/admin/') !== false) {
        return $_SESSION['admin']['role'] ?? null;
    }
    if (strpos($uri, '/customer/') !== false) {
        return $_SESSION['customer']['role'] ?? null;
    }
    if (isset($_SESSION['admin']['role'])) return $_SESSION['admin']['role'];
    return $_SESSION['customer']['role'] ?? null;
}

function id_user() {
    $uri = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($uri, '/admin/') !== false) {
        return $_SESSION['admin']['user_id'] ?? null;
    }
    if (strpos($uri, '/customer/') !== false) {
        return $_SESSION['customer']['user_id'] ?? null;
    }
    if (isset($_SESSION['admin']['user_id'])) return $_SESSION['admin']['user_id'];
    return $_SESSION['customer']['user_id'] ?? null;
}

function nama_user() {
    $uri = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($uri, '/admin/') !== false) {
        return $_SESSION['admin']['nama_lengkap'] ?? 'Admin';
    }
    if (strpos($uri, '/customer/') !== false) {
        return $_SESSION['customer']['nama_lengkap'] ?? 'Customer';
    }
    if (isset($_SESSION['admin']['nama_lengkap'])) return $_SESSION['admin']['nama_lengkap'];
    return $_SESSION['customer']['nama_lengkap'] ?? 'Pengguna';
}

function cek_role(array $roles) {
    $has_role = false;
    if (in_array('customer', $roles)) {
        if (isset($_SESSION['customer']['role']) && in_array($_SESSION['customer']['role'], $roles)) {
            $has_role = true;
        }
        if (!$has_role) {
            header("Location: " . BASE_URL . "login.php");
            exit;
        }
    } else {
        if (isset($_SESSION['admin']['role']) && in_array($_SESSION['admin']['role'], $roles)) {
            $has_role = true;
        }
        if (!$has_role) {
            http_response_code(403);
            die("<!DOCTYPE html><html lang='id'><head><meta charset='UTF-8'><title>Akses Ditolak</title>
            <style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f1f5f9;margin:0;}
            .box{background:#fff;padding:50px;border-radius:16px;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,.1);}
            h1{font-size:50px;margin:0;}h2{color:#e74c3c;}p{color:#666;}
            a{display:inline-block;background:#3b82f6;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:700;margin-top:10px;}</style>
            </head><body><div class='box'><h1>🚫</h1><h2>Akses Ditolak!</h2>
            <p>Anda tidak memiliki izin mengakses halaman ini.</p>
            <a href='" . BASE_URL . "'>🏠 Kembali ke Beranda</a></div></body></html>");
        }
    }
}
?>
