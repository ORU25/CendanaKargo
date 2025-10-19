<?php
    session_start();
    if(!isset($_SESSION['username'])){
        header("Location: ../../auth/login.php");
        exit;
    }

    if(isset($_SESSION['role']) && $_SESSION['role'] !== 'admin'){
        header("Location: ../../?error=unauthorized");
        exit;
    }

    // For Data Statistics
    include '../../config/database.php';

    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM pengiriman");
    $stmt->execute();
    $total_pengiriman = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM pengiriman WHERE status = 'dalam proses'");
    $stmt->execute();
    $dalam_proses = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM pengiriman WHERE status = 'dalam pengiriman'");
    $stmt->execute();
    $dalam_pengiriman = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM pengiriman WHERE status = 'selesai'");
    $stmt->execute();
    $selesai = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

?>


<?php
    $title = "Dashboard - Cendana Kargo";
    $page = "dashboard";
    include '../../templates/header.php';
    include '../../components/navDashboard.php';
    include '../../components/sidebar_offcanvas.php';
?>
<div class="container-fluid">
    <div class="row">
    <div class="col-lg-2 d-none d-lg-block bg-light border-end vh-100">
        <div class="list-group list-group-flush">
        <a href="<?= BASE_URL; ?>dashboard/admin/" class="list-group-item list-group-item-action fw-bold text-danger">Dashboard</a>
        <a href="<?= BASE_URL; ?>dashboard/admin/pengiriman/" class="list-group-item list-group-item-action">Pengiriman</a>
        <a href="<?= BASE_URL; ?>dashboard/admin/surat_jalan/" class="list-group-item list-group-item-action">Surat Jalan</a>
        </div>
        <a href="<?= BASE_URL; ?>auth/logout.php" class="btn btn-outline-danger mt-3">Logout</a>
    </div>

    <div class="col-lg-10 bg-light">
        <div class="container-fluid p-4">
            
            <!-- Header -->
            <div class="mb-4">
                <h1 class="h3 mb-1 fw-bold">Dashboard Admin</h1>
                <p class="text-muted small mb-0">Selamat datang, <?= htmlspecialchars($_SESSION['username']); ?>! Kelola data pengiriman Cendana Kargo</p>
            </div>

            <?php if(isset($_GET['already_logined'])){ ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <strong>Info!</strong> Anda sudah login sebelumnya.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php } ?>

            <!-- Statistics Pengiriman -->
            <div class="row g-4 mb-4">
                <!-- Total Pengiriman -->
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted mb-1 small">Total Pengiriman</p>
                                    <h2 class="mb-0 fw-bold"><?= $total_pengiriman; ?></h2>
                                </div>
                                <div class="p-3 bg-primary bg-opacity-10 rounded">
                                    <i class="fa-solid fa-box"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <a href="<?= BASE_URL; ?>dashboard/superadmin/pengiriman/" class="text-decoration-none text-primary small">
                                    Lihat Semua →
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dalam Proses -->
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted mb-1 small">Dalam Proses</p>
                                    <h2 class="mb-0 fw-bold"><?= $dalam_proses; ?></h2>
                                </div>
                                <div class="p-3 bg-warning bg-opacity-10 rounded">
                                    <i class="fa-solid fa-hourglass-half"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <span class="badge text-bg-warning">Menunggu Proses</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dalam Pengiriman -->
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted mb-1 small">Dalam Pengiriman</p>
                                    <h2 class="mb-0 fw-bold"><?= $dalam_pengiriman; ?></h2>
                                </div>
                                <div class="p-3 bg-info bg-opacity-10 rounded">
                                    <i class="fa-solid fa-truck-fast"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <span class="badge text-bg-info">Sedang Dikirim</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Selesai -->
                <div class="col-xl-3 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted mb-1 small">Selesai</p>
                                    <h2 class="mb-0 fw-bold"><?= $selesai; ?></h2>
                                </div>
                                <div class="p-3 bg-success bg-opacity-10 rounded">
                                    <i class="fa-solid fa-square-check"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <span class="badge text-bg-success">Terkirim</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

<?php
    include '../../templates/footer.php';
?>