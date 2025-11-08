<?php
    session_start();
    if(!isset($_SESSION['username'] )|| !isset($_SESSION['user_id'])){
        header("Location: ../../../auth/login");
        exit;
    }
      
    if(isset($_SESSION['role']) && $_SESSION['role'] !== 'systemOwner'){
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

        $sql = "DELETE FROM driver WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        if($stmt->execute()){
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