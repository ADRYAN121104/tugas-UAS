<?php
// config/midtrans.php

define('MIDTRANS_SERVER_KEY', 'SB-Mid-server-yUtp14n48Tee81o_j7sY4K9A');
define('MIDTRANS_CLIENT_KEY', 'SB-Mid-client-nS6gS96e4y-s9jV-');
define('MIDTRANS_IS_PRODUCTION', false);

function get_midtrans_snap_token($id_booking, $gross_amount, $customer_name, $customer_email, $customer_phone) {
    $url = "https://app.sandbox.midtrans.com/snap/v1/transactions";
    
    // Siapkan data payload transaksi
    $params = [
        'transaction_details' => [
            'order_id' => 'BKN-' . $id_booking . '-' . time(),
            'gross_amount' => (int)$gross_amount
        ],
        'credit_card' => [
            'secure' => true
        ],
        'customer_details' => [
            'first_name' => $customer_name,
            'email' => $customer_email,
            'phone' => $customer_phone
        ]
    ];
    
    $payload = json_encode($params);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Basic ' . base64_encode(MIDTRANS_SERVER_KEY . ':')
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Kompatibilitas local env tanpa sertifikat SSL
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Timeout cepat agar tidak lag jika offline
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 201 && $result) {
        $res = json_decode($result, true);
        if (isset($res['token'])) {
            return $res['token'];
        }
    }
    
    return null;
}
?>
