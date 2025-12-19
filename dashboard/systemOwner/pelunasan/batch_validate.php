<?php
    session_start();
    if(!isset($_SESSION['username']) || !isset($_SESSION['user_id'])){
        header("Location: ../../../auth/login");
        exit;
    }

    if(isset($_SESSION['role']) && $_SESSION['role'] !== 'systemOwner'){
        header("Location: ../../../?error=unauthorized");
        exit;
    }

    include '../../../config/database.php';
    
    // Hanya terima POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: ./index.php?error=invalid_request");
        exit;
    }
    
    // Get filter parameters
    $filter_cabang = isset($_POST['cabang']) ? trim($_POST['cabang']) : '';
    $filter_bulan = isset($_POST['bulan']) ? trim($_POST['bulan']) : '';
    
    // Validasi cabang dan bulan harus ada
    if (empty($filter_cabang)) {
        header("Location: ./index.php?error=missing_cabang");
        exit;
    }
    
    if (empty($filter_bulan)) {
        header("Location: ./index.php?error=missing_bulan");
        exit;
    }
    
    // Get current user ID dan timestamp
    $user_id = $_SESSION['user_id'];
    $tanggal_pembayaran = date('Y-m-d H:i:s');
    
    // Build WHERE clause sama seperti di index.php
    $where_conditions = [
        "pembayaran = 'invoice'",
        "status_pembayaran = 'Belum Dibayar'",
        "status != 'dibatalkan'"
    ];
    
    // Add filter cabang
    if (!empty($filter_cabang)) {
        $where_conditions[] = "cabang_pengirim = '" . $conn->real_escape_string($filter_cabang) . "'";
    }
    
    // Add filter bulan/tahun
    if (!empty($filter_bulan)) {
        $where_conditions[] = "DATE_FORMAT(tanggal, '%Y-%m') = '" . $conn->real_escape_string($filter_bulan) . "'";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Mulai transaction
    $conn->begin_transaction();
    
    try {
        // Update semua invoice yang sesuai filter
        $sql_update = "UPDATE pengiriman 
                       SET status_pembayaran = 'Sudah Dibayar',
                           tanggal_pembayaran = ?,
                           validasi_oleh = ?
                       WHERE " . $where_clause;
        
        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param('si', $tanggal_pembayaran, $user_id);
        $stmt->execute();
        
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        // Redirect dengan success message
        $query_string = 'success=batch_validated&count=' . $affected_rows;
        if ($filter_cabang) $query_string .= '&cabang=' . urlencode($filter_cabang);
        if ($filter_bulan) $query_string .= '&bulan=' . urlencode($filter_bulan);
        
        header("Location: ./index.php?" . $query_string);
        exit;
        
    } catch (Exception $e) {
        // Rollback jika ada error
        $conn->rollback();
        
        $query_string = 'error=batch_failed';
        if ($filter_cabang) $query_string .= '&cabang=' . urlencode($filter_cabang);
        if ($filter_bulan) $query_string .= '&bulan=' . urlencode($filter_bulan);
        
        header("Location: ./index.php?" . $query_string);
        exit;
    }
?>
