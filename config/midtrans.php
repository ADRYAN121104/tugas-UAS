<?php
// config/midtrans.php

define('MIDTRANS_SERVER_KEY', 'SB-Mid-server-yUtp14n48Tee81o_j7sY4K9A');
define('MIDTRANS_CLIENT_KEY', 'SB-Mid-client-nS6gS96e4y-s9jV-');
define('MIDTRANS_IS_PRODUCTION', false);
define('MIDTRANS_SNAP_URL', 'https://app.sandbox.midtrans.com/snap/v1/transactions');
define('MIDTRANS_API_URL', 'https://api.sandbox.midtrans.com/v2');

// ── Fungsi helper cURL ke Midtrans ───────────────────────────────────────────
function midtrans_curl($url, $method = 'POST', $payload = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode(MIDTRANS_SERVER_KEY . ':')
    ]);
    if ($method === 'POST' && $payload) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }
    $result    = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $http_code, 'body' => $result ? json_decode($result, true) : null];
}

// ── Snap Token untuk Booking Fee ─────────────────────────────────────────────
function get_midtrans_snap_token($id_booking, $gross_amount, $customer_name, $customer_email, $customer_phone) {
    $params = [
        'transaction_details' => [
            'order_id'     => 'BKN-' . $id_booking . '-' . time(),
            'gross_amount' => (int)$gross_amount
        ],
        'credit_card' => ['secure' => true],
        'customer_details' => [
            'first_name' => $customer_name,
            'email'      => $customer_email,
            'phone'      => $customer_phone
        ]
    ];
    $resp = midtrans_curl(MIDTRANS_SNAP_URL, 'POST', $params);
    return ($resp['code'] === 201 && isset($resp['body']['token'])) ? $resp['body']['token'] : null;
}

// ── Snap Token untuk Pembayaran DP ───────────────────────────────────────────
function get_midtrans_snap_token_dp($id_pengajuan, $gross_amount, $customer_name, $customer_email, $customer_phone, $nama_unit = '') {
    $order_id = 'DP-' . $id_pengajuan . '-' . time();
    $params = [
        'transaction_details' => [
            'order_id'     => $order_id,
            'gross_amount' => (int)$gross_amount
        ],
        'credit_card' => ['secure' => true],
        'customer_details' => [
            'first_name' => $customer_name,
            'email'      => $customer_email,
            'phone'      => $customer_phone
        ],
        'item_details' => [[
            'id'       => 'DP-' . $id_pengajuan,
            'price'    => (int)$gross_amount,
            'quantity' => 1,
            'name'     => 'Uang Muka (DP) KPR ' . ($nama_unit ?: 'Unit #' . $id_pengajuan)
        ]],
        'custom_expiry' => [
            'expiry_duration' => 60,
            'unit'            => 'minute'
        ]
    ];
    $resp = midtrans_curl(MIDTRANS_SNAP_URL, 'POST', $params);
    if ($resp['code'] === 201 && isset($resp['body']['token'])) {
        return ['token' => $resp['body']['token'], 'order_id' => $order_id];
    }
    return null;
}

// ── Cek Status Transaksi Midtrans ─────────────────────────────────────────────
function midtrans_get_status($order_id) {
    $resp = midtrans_curl(MIDTRANS_API_URL . '/' . $order_id . '/status');
    return $resp['body'] ?? null;
}

// ── Refund Transaksi Midtrans ─────────────────────────────────────────────────
function midtrans_refund($order_id, $refund_amount, $reason = 'Pengajuan KPR dibatalkan/ditolak') {
    $refund_key = 'REF-' . $order_id . '-' . time();
    $payload = [
        'refund_key' => $refund_key,
        'amount'     => (int)$refund_amount,
        'reason'     => $reason
    ];
    $resp = midtrans_curl(MIDTRANS_API_URL . '/' . $order_id . '/refund', 'POST', $payload);
    return [
        'success'    => in_array($resp['code'], [200, 201]),
        'code'       => $resp['code'],
        'data'       => $resp['body'],
        'refund_key' => $refund_key
    ];
}
?>
