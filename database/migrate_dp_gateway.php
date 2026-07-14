<?php
// database/migrate_dp_gateway.php
require __DIR__ . '/../config/koneksi.php';
try {
    // Tambah kolom midtrans ke pembayaran_dp
    $cols = $db->query("SHOW COLUMNS FROM pembayaran_dp")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('midtrans_order_id', $cols)) {
        $db->exec("ALTER TABLE pembayaran_dp ADD COLUMN midtrans_order_id VARCHAR(100) DEFAULT NULL AFTER catatan");
        echo "Added midtrans_order_id\n";
    } else { echo "midtrans_order_id exists\n"; }

    if (!in_array('midtrans_transaction_id', $cols)) {
        $db->exec("ALTER TABLE pembayaran_dp ADD COLUMN midtrans_transaction_id VARCHAR(100) DEFAULT NULL AFTER midtrans_order_id");
        echo "Added midtrans_transaction_id\n";
    } else { echo "midtrans_transaction_id exists\n"; }

    if (!in_array('payment_method', $cols)) {
        $db->exec("ALTER TABLE pembayaran_dp ADD COLUMN payment_method ENUM('gateway','manual') DEFAULT 'manual' AFTER midtrans_transaction_id");
        echo "Added payment_method\n";
    } else { echo "payment_method exists\n"; }

    if (!in_array('midtrans_payment_type', $cols)) {
        $db->exec("ALTER TABLE pembayaran_dp ADD COLUMN midtrans_payment_type VARCHAR(50) DEFAULT NULL AFTER payment_method");
        echo "Added midtrans_payment_type\n";
    } else { echo "midtrans_payment_type exists\n"; }

    // Kolom refund untuk tracking pengembalian dana
    if (!in_array('refund_id', $cols)) {
        $db->exec("ALTER TABLE pembayaran_dp ADD COLUMN refund_id VARCHAR(100) DEFAULT NULL AFTER midtrans_payment_type");
        echo "Added refund_id\n";
    } else { echo "refund_id exists\n"; }

    if (!in_array('refund_status', $cols)) {
        $db->exec("ALTER TABLE pembayaran_dp ADD COLUMN refund_status ENUM('none','requested','success','failed') DEFAULT 'none' AFTER refund_id");
        echo "Added refund_status\n";
    } else { echo "refund_status exists\n"; }

    echo "Done! Migration selesai.\n";
} catch(Exception $e) { echo "Error: " . $e->getMessage() . "\n"; }
