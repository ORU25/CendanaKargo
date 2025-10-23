<?php
header('Content-Type: application/json');
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
