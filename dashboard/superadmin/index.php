<?php
    session_start();
    if(!isset($_SESSION['username'])){
        header("Location: ../../auth/login");
        exit;
    }

    if(isset($_SESSION['role']) && $_SESSION['role'] !== 'superAdmin'){
        header("Location: ../../?error=unauthorized");
        exit;
    }

    include '../../config/database.php';
    
    // Get statistics
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM pengiriman ");
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
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM user");
    $stmt->execute();
    $total_user = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tarif_pengiriman WHERE status = 'aktif'");
    $stmt->execute();
    $total_tarif = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    // Get recent shipments
    $stmt = $conn->prepare("SELECT * FROM pengiriman ORDER BY id DESC LIMIT 5");
    $stmt->execute();
    $recent_shipments = $stmt->get_result();
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
    <?php include '../../components/sidebar.php'; ?>
    <!-- Konten utama -->
    <div class="col-lg-10 bg-light">
        <div class="container-fluid p-4">
            <!-- Header -->
            <div class="mb-4">
                <h1 class="h3 mb-1 fw-bold">Dashboard SuperAdmin</h1>
                <p class="text-muted small mb-0">Selamat datang, <?= htmlspecialchars($_SESSION['username']); ?>! Kelola semua data sistem Cendana Kargo</p>
                <p class="text-muted small fw-semibold">Cabang: <?= htmlspecialchars($_SESSION['cabang']); ?></p>
            </div>

            <?php if(isset($_GET['already_logined'])){ ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <strong>Info!</strong> Anda sudah login sebelumnya.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php } ?>

            <!-- Statistics Cards Row 1 -->
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
                                <div class="p-3 bg-primary bg-opacity-50 rounded">
                                    <i class="fa-solid fa-box"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <a href="pengiriman/" class="text-decoration-none text-primary small">
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
                                <div class="p-3 bg-warning bg-opacity-50 rounded">
                                    <i class="fa-solid fa-hourglass-half"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <span class="badge text-bg-warning bg-opacity-50">Menunggu Proses</span>
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
                                <div class="p-3 bg-info bg-opacity-50 rounded">
                                    <i class="fa-solid fa-truck-fast"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <span class="badge text-bg-info bg-opacity-50">Sedang Dikirim</span>
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
                                <div class="p-3 bg-success bg-opacity-50 rounded">
                                    <i class="fa-solid fa-square-check"></i>
                                </div>
                            </div>
                            <div class="mt-3">
                                <span class="badge text-black text-bg-success bg-opacity-50">Terkirim</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards Row 2 -->
            <div class="row g-4 mb-4">
                <!-- Total User -->
                <div class="col-xl-4 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted mb-1 small">Total User</p>
                                    <h2 class="mb-0 fw-bold"><?= $total_user; ?></h2>
                                    <a href="user/" class="text-decoration-none text-primary small mt-2 d-inline-block">
                                        Kelola User →
                                    </a>
                                </div>
                                <div class="p-3 bg-secondary bg-opacity-50 rounded">
                                    <i class="fa-solid fa-user-tie"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Tarif -->
                <div class="col-xl-4 col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted mb-1 small">Tarif Aktif</p>
                                    <h2 class="mb-0 fw-bold"><?= $total_tarif; ?></h2>
                                    <a href="tarif/" class="text-decoration-none text-primary small mt-2 d-inline-block">
                                        Kelola Tarif →
                                    </a>
                                </div>
                                <div class="p-3 bg-dark bg-opacity-50 rounded">
                                    <i class="fa-solid fa-dollar-sign"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Shipments -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold">Pengiriman Terbaru</h5>
                        <a href="pengiriman/" class="btn btn-sm btn-outline-primary">
                            Lihat Semua
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-3">No. Resi</th>
                                    <th>Nama Barang</th>
                                    <th>Pengirim</th>
                                    <th>Penerima</th>
                                    <th>Rute</th>
                                    <th>Tanggal</th>
                                    <th>Status</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($recent_shipments->num_rows > 0): ?>
                                    <?php while($row = $recent_shipments->fetch_assoc()): ?>
                                        <tr class="text-capitalize">
                                            <td class="px-3 fw-bold "><span class="badge bg-dark  bg-opacity-75"><?= htmlspecialchars($row['no_resi']); ?></span></td>
                                            <td><?= htmlspecialchars($row['nama_barang']); ?></td>
                                            <td><?= htmlspecialchars($row['nama_pengirim']); ?></td>
                                            <td><?= htmlspecialchars($row['nama_penerima']); ?></td>
                                            <td>
                                                <span class="badge bg-primary  bg-opacity-75"><?= htmlspecialchars($row['cabang_pengirim']); ?></span>
                                                →
                                                <span class="badge bg-success  bg-opacity-75"><?= htmlspecialchars($row['cabang_penerima']); ?></span>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($row['tanggal'])); ?></td>
                                            <td class="">
                                                <?php
                                                $badgeClass = 'secondary';
                                                switch(strtolower($row['status'])) {
                                                    case 'dalam proses': $badgeClass = 'warning'; break;
                                                    case 'dalam pengiriman': $badgeClass = 'primary'; break;
                                                    case 'sampai tujuan': $badgeClass = 'info'; break;
                                                    case 'selesai': $badgeClass = 'success'; break;
                                                    case 'dibatalkan': $badgeClass = 'danger'; break;
                                                }
                                                ?>
                                                <span class="badge text-bg-<?= $badgeClass; ?>  bg-opacity-75"><?= htmlspecialchars($row['status']); ?></span>
                                            </td>
                                            <td class="text-center">
                                                <a href="pengiriman/detail.php?id=<?= $row['id']; ?>" class="btn btn-sm btn-info text-white">
                                                    <i class="fa-solid fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5 text-muted">
                                            <i class="fa-solid fa-box"></i>
                                            <p class="mb-0">Belum ada data pengiriman.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
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