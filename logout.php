<?php
// logout.php
require_once 'config/session.php';
$role = $_GET['role'] ?? '';
if ($role === 'admin') {
    unset($_SESSION['admin']);
} elseif ($role === 'customer') {
    unset($_SESSION['customer']);
} else {
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (strpos($referer, '/admin/') !== false) {
        unset($_SESSION['admin']);
    } elseif (strpos($referer, '/customer/') !== false) {
        unset($_SESSION['customer']);
    } else {
        unset($_SESSION['admin']);
        unset($_SESSION['customer']);
    }
}
header('Location: login.php');
exit;
?>
