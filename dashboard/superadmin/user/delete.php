<?php
    session_start();
    if(!isset($_SESSION['username'] )|| !isset($_SESSION['user_id'])){
        header("Location: ../../../auth/login");
        exit;
    }
      
    if(isset($_SESSION['role']) && $_SESSION['role'] !== 'superAdmin'){
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

        // Cegah superadmin menghapus dirinya sendiri
        if($id === $_SESSION['user_id']){
            header("Location: ./?error=cannot_delete_self");
            exit;
        }

        $sql = "DELETE FROM user WHERE id = $id";
        if($conn->query($sql) === TRUE){
            header("Location: ./?success=deleted");
            exit;
        } else {
            header("Location: ./?error=failed");
            exit;
        }
    } else {
        header("Location: ./");
        exit;
    }
?>