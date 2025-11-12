<?php
header('Content-Type: application/json');
require_once '../config/env.php';
require_once '../config/database.php';

// Get the resi number from request
$noResi = isset($_GET['no_resi']) ? trim($_GET['no_resi']) : '';

if (empty($noResi)) {
    echo json_encode([
        'success' => false,
        'message' => 'Nomor resi tidak boleh kosong'
    ]);
    exit;
}

// Verify CAPTCHA if token is provided (optional for backward compatibility)
$captchaToken = isset($_GET['captcha_token']) ? trim($_GET['captcha_token']) : '';

if (!empty($captchaToken)) {
    // Get Cloudflare Turnstile Secret Key from environment variable
    $secretKey = env('TURNSTILE_SECRET_KEY');
    
    if (empty($secretKey)) {
        echo json_encode([
            'success' => false,
            'message' => 'Server configuration error: TURNSTILE_SECRET_KEY not set',
            'error_code' => 'CONFIG_ERROR'
        ]);
        exit;
    }
    
    // Verify with Cloudflare
    $verifyUrl = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $verifyData = [
        'secret' => $secretKey,
        'response' => $captchaToken,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($verifyData),
            'timeout' => 10
        ]
    ];
    
    $context = stream_context_create($options);
    $response = @file_get_contents($verifyUrl, false, $context);
    
    if ($response === false) {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal memverifikasi CAPTCHA. Silakan coba lagi.',
            'error_code' => 'CAPTCHA_VERIFY_ERROR'
        ]);
        exit;
    }
    
    $result = json_decode($response);
    
    if (!$result->success) {
        echo json_encode([
            'success' => false,
            'message' => 'Verifikasi CAPTCHA gagal. Silakan refresh halaman dan coba lagi.',
            'error_code' => 'CAPTCHA_FAILED'
        ]);
        exit;
    }
}

// Query to get shipment data
$query = "SELECT 
    no_resi,
    nama_pengirim,
    nama_penerima,
    cabang_pengirim as asal,
    cabang_penerima as tujuan,
    total_tarif,
    status
FROM Pengiriman 
WHERE no_resi = ?";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $noResi);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    echo json_encode([
        'success' => true,
        'data' => [
            'no_resi' => $row['no_resi'],
            'nama_pengirim' => $row['nama_pengirim'],
            'nama_penerima' => $row['nama_penerima'],
            'asal' => $row['asal'],
            'tujuan' => $row['tujuan'],
            'total_tarif' => number_format($row['total_tarif'], 0, ',', '.'),
            'status' => $row['status']
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Nomor resi tidak ditemukan',
        'error_code' => 'RESI_NOT_FOUND'
    ]);
}

mysqli_stmt_close($stmt);
mysqli_close($conn);
?>
