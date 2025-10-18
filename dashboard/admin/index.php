<?php
    session_start();
    if(!isset($_SESSION['username'])){
        header("Location: ../../auth/login");
        exit;
    }

    if(isset($_SESSION['role']) && $_SESSION['role'] !== 'admin'){
        header("Location: ../../?error=unauthorized");
        exit;
    }
?>

<?php
    include '../../templates/header.php';
?>

<h1>Admin Dashboard</h1>

<?php
    include '../../templates/footer.php';
?>