<?php
session_start();
if(!isset($_SESSION['username'] )|| !isset($_SESSION['user_id'])){
    header("Location: ../../auth/login");
    exit;
}

if(isset($_SESSION['role']) && $_SESSION['role'] !== 'admin'){
    header("Location: ../../?error=unauthorized");
    exit;
}

include '../../config/database.php';

// Ambil id cabang admin
$stmt = $conn->prepare("SELECT id_cabang FROM user WHERE id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$rowCab = $stmt->get_result()->fetch_assoc();
$stmt->close();
$id_cabang_admin = $rowCab ? (int)$rowCab['id_cabang'] : 0;

// === Statistik Pengiriman Keluar (dari cabang admin) ===
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

// === Statistik Pengiriman Masuk (menuju cabang admin) ===
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

$stmt = $conn->prepare("
    SELECT * FROM pengiriman 
    WHERE id_cabang_pengirim = ? OR id_cabang_penerima = ? 
    ORDER BY id DESC LIMIT 5
");
$stmt->bind_param('ii', $id_cabang_admin, $id_cabang_admin);
$stmt->execute();
$recent_shipments = $stmt->get_result();
$stmt->close();

$title = "Dashboard - Cendana Kargo";
$page = "dashboard";
include '../../templates/header.php';
include '../../components/navDashboard.php';
include '../../components/sidebar_offcanvas.php';
?>
<div class="container-fluid">
    <div class="row">
        <?php include '../../components/sidebar.php'; ?>

        <div class="col-lg-10 bg-light">
            <div class="container-fluid p-4">
                
                <!-- Header -->
                <div class="mb-4">
                    <h1 class="h3 mb-1 fw-bold">Dashboard Admin</h1>
                    <p class="text-muted small mb-0">Selamat datang, <?= htmlspecialchars($_SESSION['username']); ?>!</p>
                    <p class="text-muted small fw-semibold">Cabang: <?= htmlspecialchars($_SESSION['cabang']); ?></p>
                </div>

                <?php if(isset($_GET['already_logined'])){ ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <strong>Info!</strong> Anda sudah login sebelumnya.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php } ?>

                <!-- Statistik per Status -->
                <div class="row g-4 mb-4">
                    <!-- Dalam Proses -->
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

                    <!-- Dalam Pengiriman -->
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

                    <!-- Pengiriman Datang -->
                    <div class="col-xl-3 col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1 small">Pengiriman Datang</p>
                                        <h2 class="mb-0 fw-bold"><?= $masuk_sampai_tujuan; ?></h2>
                                    </div>
                                    <div class="p-3 bg-primary bg-opacity-50 rounded">
                                        <i class="fa-solid fa-location-dot"></i>
                                    </div>
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

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 fw-bold">Pengiriman Terbaru</h5>
                            <a href="<?= BASE_URL; ?>dashboard/admin/pengiriman/" class="btn btn-sm btn-outline-primary">
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
                                                <td class="px-3 fw-bold"><span class="badge bg-dark bg-opacity-75"><?= htmlspecialchars($row['no_resi']); ?></span></td>
                                                <td><?= htmlspecialchars($row['nama_barang']); ?></td>
                                                <td><?= htmlspecialchars($row['nama_pengirim']); ?></td>
                                                <td><?= htmlspecialchars($row['nama_penerima']); ?></td>
                                                <td>
                                                    <?php
                                                    // Tentukan warna badge berdasarkan arah pengiriman
                                                    $isPengirimanKeluar = ($row['id_cabang_pengirim'] == $id_cabang_admin);
                                                    $badgeAsalClass = $isPengirimanKeluar ? 'primary' : 'secondary';
                                                    $badgeTujuanClass = $isPengirimanKeluar ? 'secondary' : 'success';
                                                    ?>
                                                    <span class="badge bg-<?= $badgeAsalClass; ?> bg-opacity-75"><?= htmlspecialchars($row['cabang_pengirim']); ?></span>
                                                    →
                                                    <span class="badge bg-<?= $badgeTujuanClass; ?> bg-opacity-75"><?= htmlspecialchars($row['cabang_penerima']); ?></span>
                                                </td>
                                                <td><?= date('d/m/Y', strtotime($row['tanggal'])); ?></td>
                                                <td>
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
                                                    <span class="badge text-bg-<?= $badgeClass; ?> bg-opacity-75"><?= htmlspecialchars($row['status']); ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <a href="<?= BASE_URL; ?>dashboard/admin/pengiriman/detail.php?id=<?= $row['id']; ?>" class="btn btn-sm btn-info text-white">
                                                        <i class="fa-solid fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-5 text-muted">
                                                <i class="fa-solid fa-box"></i>
                                                <p class="mb-0">Belum ada data pengiriman untuk cabang ini.</p>
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

<?php include '../../templates/footer.php'; ?>
