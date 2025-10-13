<?php
session_start(); // penting untuk mengakses session yang sedang aktif

// Hapus semua data session
$_SESSION = [];

// Hapus cookie session jika ada (opsional, tapi bagus untuk keamanan)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hapus session di server
session_destroy();

// Redirect ke halaman login
header("Location: ../auth/login.php");
exit;
?>
