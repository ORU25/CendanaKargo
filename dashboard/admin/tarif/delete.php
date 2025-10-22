<?php
    session_start();
    if(!isset($_SESSION['username'])){
        header("Location: ../../../auth/login.php");
        exit;
    }

    if(isset($_SESSION['role']) && $_SESSION['role'] !== 'admin'){
        header("Location: ../../../?error=unauthorized");
        exit;
    }

    include '../../../config/database.php';

    if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])){
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            header("Location: ./?error=failed");
            exit;
        }
        $id = intval($_POST['id']);
        try {
            $sql = "DELETE FROM tarif_pengiriman WHERE id = $id";
            $conn->query($sql);
            header("Location: ./?success=deleted");
            exit;
        } catch (mysqli_sql_exception $e) {
            // Tangkap error dan arahkan dengan pesan error
            $errorMsg = urlencode($e->getMessage());
            header("Location: ./?error=$errorMsg");
            exit;
        }
    } else {
        header("Location: ./");
        exit;
    }
?>