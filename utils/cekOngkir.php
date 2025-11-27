<?php
header('Content-Type: application/json');
require_once '../config/database.php';

// Get parameters
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Action: Get list of branches (kantor cabang)
if ($action === 'get_branches') {
    $query = "SELECT id, nama_cabang, kode_cabang FROM kantor_cabang ORDER BY nama_cabang ASC";
    $result = mysqli_query($conn, $query);
    
    $branches = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $branches[] = [
            'id' => $row['id'],
            'nama' => $row['nama_cabang'],
            'kode' => $row['kode_cabang']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $branches
    ]);
    exit;
}

// Action: Calculate shipping cost
if ($action === 'calculate') {
    $idCabangAsal = isset($_GET['id_cabang_asal']) ? intval($_GET['id_cabang_asal']) : 0;
    $idCabangTujuan = isset($_GET['id_cabang_tujuan']) ? intval($_GET['id_cabang_tujuan']) : 0;
    $berat = isset($_GET['berat']) ? floatval($_GET['berat']) : 0;
    
    // Validation
    if ($idCabangAsal <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Cabang asal tidak valid'
        ]);
        exit;
    }
    
    if ($idCabangTujuan <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Cabang tujuan tidak valid'
        ]);
        exit;
    }
    
    if ($idCabangAsal === $idCabangTujuan) {
        echo json_encode([
            'success' => false,
            'message' => 'Cabang asal dan tujuan tidak boleh sama'
        ]);
        exit;
    }
    
    if ($berat <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Berat harus lebih dari 0 kg'
        ]);
        exit;
    }
    
    // Get tarif data
    $query = "SELECT 
        t.tarif_dasar,
        t.batas_berat_dasar,
        t.tarif_tambahan_perkg,
        ca.nama_cabang as cabang_asal,
        ct.nama_cabang as cabang_tujuan
    FROM tarif_pengiriman t
    INNER JOIN kantor_cabang ca ON t.id_cabang_asal = ca.id
    INNER JOIN kantor_cabang ct ON t.id_cabang_tujuan = ct.id
    WHERE t.id_cabang_asal = ? 
    AND t.id_cabang_tujuan = ? 
    AND t.status = 'aktif'
    LIMIT 1";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $idCabangAsal, $idCabangTujuan);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        $tarifDasar = floatval($row['tarif_dasar']);
        $batasBerat = floatval($row['batas_berat_dasar']);
        $tarifTambahan = floatval($row['tarif_tambahan_perkg']);
        
        // Calculate total cost
        if ($berat <= $batasBerat) {
            $totalTarif = $tarifDasar;
        } else {
            $beratTambahan = $berat - $batasBerat;
            $totalTarif = $tarifDasar + ($beratTambahan * $tarifTambahan);
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'cabang_asal' => $row['cabang_asal'],
                'cabang_tujuan' => $row['cabang_tujuan'],
                'berat' => $berat,
                'tarif_dasar' => number_format($tarifDasar, 0, ',', '.'),
                'batas_berat_dasar' => $batasBerat,
                'tarif_tambahan_perkg' => number_format($tarifTambahan, 0, ',', '.'),
                'total_tarif' => number_format($totalTarif, 0, ',', '.'),
                'total_tarif_raw' => $totalTarif
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Tarif untuk rute tersebut belum tersedia'
        ]);
    }
    
    mysqli_stmt_close($stmt);
    exit;
}

// Invalid action
echo json_encode([
    'success' => false,
    'message' => 'Action tidak valid'
]);

mysqli_close($conn);
?>
