<?php
    session_start();
    if(!isset($_SESSION['username'])){
        header("Location: ../../auth/login.php");
        exit;
    }

    if(isset($_SESSION['role']) && $_SESSION['role'] !== 'superAdmin'){
        header("Location: ../../?error=unauthorized");
        exit;
    }
?>

<?php
    $title = "Dashboard - Cendana Kargo";
    include '../../templates/header.php';
?>

<nav class="navbar navbar-dark bg-danger">
  <div class="container-fluid">
        <div class="d-flex align-items-center">
            <button class="navbar-toggler d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Toggle sidebar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <a class="navbar-brand m-0" href="<?= BASE_URL; ?>">CendanaKargo</a>
        </div>
    <div class="d-flex">
        <span class="navbar-text text-white me-3">
            <?= $_SESSION['username'];?>
        </span>

    </div>
  </div>
</nav>

<!-- Sidebar Offcanvas -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="sidebarOffcanvasLabel">Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div class="list-group list-group-flush">
            <a href="<?= BASE_URL; ?>dashboard/superadmin/" class="list-group-item list-group-item-action fw-bold text-danger">Dashboard</a>
            <a href="<?= BASE_URL; ?>dashboard/superadmin/pengiriman/" class="list-group-item list-group-item-action">Pengiriman</a>
            <a href="<?= BASE_URL; ?>dashboard/superadmin/surat_jalan/" class="list-group-item list-group-item-action">Surat Jalan</a>
            <a href="<?= BASE_URL; ?>dashboard/superadmin/tarif/" class="list-group-item list-group-item-action">Tarif</a>
            <a href="<?= BASE_URL; ?>dashboard/superadmin/kantor_cabang/" class="list-group-item list-group-item-action">Kantor Cabang</a>
            <a href="<?= BASE_URL; ?>dashboard/superadmin/user/" class="list-group-item list-group-item-action">User</a>
        </div>
        <a href="<?= BASE_URL; ?>auth/logout.php" class="btn btn-outline-danger mt-3 ms-3">Logout</a>
    </div>
</div>

<div class="container-fluid">
  <div class="row">
    <div class="col-lg-2 d-none d-lg-block bg-light border-end vh-100">
      <div class="list-group list-group-flush">
        <a href="<?= BASE_URL; ?>dashboard/superadmin/" class="list-group-item list-group-item-action fw-bold text-danger">Dashboard</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/pengiriman/" class="list-group-item list-group-item-action">Pengiriman</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/surat_jalan/" class="list-group-item list-group-item-action">Surat Jalan</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/tarif/" class="list-group-item list-group-item-action">Tarif</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/kantor_cabang/" class="list-group-item list-group-item-action">Kantor Cabang</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/user/" class="list-group-item list-group-item-action">User</a>
      </div>
        <a href="<?= BASE_URL; ?>auth/logout.php" class="btn btn-outline-danger mt-3">Logout</a>
    </div>

    <!-- Konten utama -->
    <div class="col-lg-10">
        <?php if(isset($_GET['already_logined'])){ 
            $type = "info";
            $message = "You are already logged in.";
            include '../../components/alert.php';
        }?>
      <h1>Dashboard</h1>
      <p>Konten utama</p>
    </div>
  </div>
</div>



<?php
    include '../../templates/footer.php';
?>