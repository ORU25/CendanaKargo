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

    // For Data Statistics (filtered by admin's branch)
    include '../../config/database.php';

    // Get admin's branch id
    $stmt = $conn->prepare("SELECT id_cabang FROM user WHERE id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $rowCab = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $id_cabang_admin = $rowCab ? (int)$rowCab['id_cabang'] : 0;

    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM pengiriman WHERE id_cabang_pengirim = ?");
    $stmt->bind_param('i', $id_cabang_admin);
    $stmt->execute();
    $total_keluar = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM pengiriman WHERE id_cabang_pengirim = ? AND status = 'dalam proses'");
    $stmt->bind_param('i', $id_cabang_admin);
    $stmt->execute();
    $keluar_dalam_proses = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM pengiriman WHERE id_cabang_pengirim = ? AND status = 'dalam pengiriman'");
    $stmt->bind_param('i', $id_cabang_admin);
    $stmt->execute();
    $keluar_dalam_pengiriman = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // Pengiriman Masuk (menuju cabang admin)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM pengiriman WHERE id_cabang_penerima = ?");
    $stmt->bind_param('i', $id_cabang_admin);
    $stmt->execute();
    $total_masuk = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM pengiriman WHERE id_cabang_penerima = ? AND status = 'sampai tujuan'");
    $stmt->bind_param('i', $id_cabang_admin);
    $stmt->execute();
    $masuk_sampai_tujuan = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM pengiriman WHERE id_cabang_penerima = ? AND status = 'selesai'");
    $stmt->bind_param('i', $id_cabang_admin);
    $stmt->execute();
    $masuk_selesai = (int)$stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

?>


<?php
    $title = "Dashboard - Cendana Kargo";
    $page = "dashboard";
    include '../../templates/header.php';
    include '../../components/navDashboard.php';
    include '../../components/sidebar_offcanvas_admin.php';
?>
<div class="container-fluid">
    <div class="row">
    <?php include '../../components/sidebaradmin.php'; ?>

        <div class="col-lg-10 bg-light">
            <div class="container-fluid p-4">
                
                <!-- Header -->
                <div class="mb-4">
                    <h1 class="h3 mb-1 fw-bold">Dashboard Admin</h1>
                    <p class="text-muted small mb-0">Selamat datang, <?= htmlspecialchars($_SESSION['username']); ?>! Kelola data pengiriman Cendana Kargo</p>
                    <p class=" text-muted small fw-semibold">Cabang: <?= htmlspecialchars($_SESSION['cabang']); ?></p>
                </div>

                <?php if(isset($_GET['already_logined'])){ ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <strong>Info!</strong> Anda sudah login sebelumnya.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php } ?>

                <!-- Statistik per Status -->
                <div class="row g-4 mb-4">
                    <!-- Keluar: Dalam Proses -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1 small">Dalam Proses</p>
                                        <h2 class="mb-0 fw-bold"><?= $keluar_dalam_proses; ?></h2>
                                    </div>
                                    <div class="p-3 bg-warning bg-opacity-50 rounded">
                                        <i class="fa-solid fa-hourglass-half"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Keluar: Dalam Pengiriman -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1 small">Dalam Pengiriman</p>
                                        <h2 class="mb-0 fw-bold"><?= $keluar_dalam_pengiriman; ?></h2>
                                    </div>
                                    <div class="p-3 bg-info bg-opacity-50 rounded">
                                        <i class="fa-solid fa-truck-fast"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Masuk: Sampai Tujuan -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1 small">Pengiriman Datang</p>
                                        <h2 class="mb-0 fw-bold"><?= $masuk_sampai_tujuan; ?></h2>
                                    </div>
                                    <div class="p-3 bg-info bg-opacity-50 rounded">
                                        <i class="fa-solid fa-location-dot"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Masuk: Selesai -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1 small">Selesai</p>
                                        <h2 class="mb-0 fw-bold"><?= $masuk_selesai; ?></h2>
                                    </div>
                                    <div class="p-3 bg-success bg-opacity-50 rounded">
                                        <i class="fa-solid fa-square-check"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
    include '../../templates/footer.php';
?>