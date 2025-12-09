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
    
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../../../config/database.php';
$title = "Dashboard - Cendana Kargo";

// Pagination settings
$limit = 10; // Data per halaman
$page_num = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page_num - 1) * $limit;

// Search functionality (nama_driver atau telp_driver)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get total records
if ($search !== '') {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as total FROM driver WHERE nama_driver LIKE ? OR telp_driver LIKE ?"
    );
    $searchParam = "%$search%";
    $stmt->bind_param('ss', $searchParam, $searchParam);
    $stmt->execute();
    $result = $stmt->get_result();
    $total_records = $result->fetch_assoc()['total'];
    $stmt->close();
} else {
    $result = $conn->query("SELECT COUNT(*) as total FROM driver");
    $total_records = $result->fetch_assoc()['total'];
}

$total_pages = ceil($total_records / $limit);

// Get paginated data
if ($search !== '') {
    $stmt = $conn->prepare(
        "SELECT id, nama_driver, telp_driver FROM driver WHERE nama_driver LIKE ? OR telp_driver LIKE ? ORDER BY id ASC LIMIT ? OFFSET ?"
    );
    $searchParam = "%$search%";
    $stmt->bind_param('ssii', $searchParam, $searchParam, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $drivers = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $stmt = $conn->prepare(
        "SELECT id, nama_driver, telp_driver FROM driver ORDER BY id ASC LIMIT ? OFFSET ?"
    );
    $stmt->bind_param('ii', $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $drivers = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<?php
    $page = "driver";
    include '../../../templates/header.php';
    include '../../../components/navDashboard.php';
    include '../../../components/sidebar_offcanvas.php';
?>
<div class="container-fluid">
  <div class="row">
    <?php include '../../../components/sidebar.php'; ?>

    <!-- Konten utama -->
    <div class="col-lg-10 bg-light">
        <div class="container-fluid p-4">
            <?php if(isset($_GET['success']) && $_GET['success'] == 'created'){
                $type = "success";
                $message = "Driver berhasil ditambahkan";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['success']) && $_GET['success'] == 'updated'){
                $type = "success";
                $message = "Driver berhasil diperbarui";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'not_found'){
                $type = "danger";
                $message = "Driver tidak ditemukan";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'failed'){
                $type = "danger";
                $message = "Gagal melakukan operasi pada driver";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'invalid_id'){
                $type = "danger";
                $message = "ID driver tidak valid";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['success']) && $_GET['success'] == 'deleted'){
                $type = "success";
                $message = "Driver berhasil dihapus";
                include '../../../components/alert.php';
            }?>

            <!-- Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h4 mb-1 fw-bold">Daftar Driver</h1>
                    <p class="text-muted small mb-0">
                        Menampilkan <?= count($drivers); ?> dari <?= $total_records; ?> driver
                        <?php if ($total_pages > 1): ?>
                            (Halaman <?= $page_num; ?> dari <?= $total_pages; ?>)
                        <?php endif; ?>
                    </p>
                </div>
                <div class="d-flex gap-2 mt-2 mt-md-0">
                    <a href="create" class="btn btn-success mb-3">
                        <i class="fa-solid fa-plus"></i>
                        Tambah Driver
                    </a>
                </div>
            </div>

            <!-- Search Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="" class="row g-2 align-items-center">
                        <div class="col-md-10">
                            <input type="text" name="search" class="form-control" placeholder="Cari berdasarkan nama atau telepon..." value="<?= htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                Cari
                            </button>
                        </div>
                        <?php if ($search): ?>
                        <div class="col-12">
                            <a href="./" class="btn btn-sm btn-outline-secondary">
                                <i class="fa-solid fa-x"></i>
                                Hapus Pencarian
                            </a>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                <th class="px-4" style="width: 70px;">No</th>
                                <th>Nama Driver</th>
                                <th>No. Telepon</th>
                                <th class="text-center" style="width: 100px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($drivers as $index => $driver): ?>
                                    <tr>
                                        <td class="px-4 fw-semibold"><?= htmlspecialchars($index+1); ?></td>
                                        <td><?= htmlspecialchars($driver['nama_driver']); ?></td>
                                        <td><?= htmlspecialchars($driver['telp_driver']); ?></td>
                                        <td>
                                            <a href="update?id=<?= $driver['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                            <button type="button" data-bs-toggle="modal" data-bs-target="#delete<?= $driver['id']; ?>" class="btn btn-sm btn-danger">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Delete Modal -->
                                    <div class="modal fade" id="delete<?= $driver['id']; ?>" tabindex="-1" aria-labelledby="deleteLabel<?= $driver['id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title" id="deleteLabel<?= $driver['id']; ?>">
                                                        <i class="fa-solid fa-trash me-2"></i>Konfirmasi Hapus Driver
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>

                                                <div class="modal-body text-center">
                                                    <div class="text-center mb-3">
                                                        <i class="fa-solid fa-triangle-exclamation fa-3x text-warning"></i>
                                                    </div>
                                                    <p>Apakah Anda yakin ingin menghapus driver <strong><?= htmlspecialchars($driver['nama_driver']); ?></strong>?</p>
                                                    <p class="text-muted mb-0">Tindakan ini tidak dapat dibatalkan.</p>
                                                </div>

                                                <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                        <form action="delete" method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <input type="hidden" name="id" value="<?= $driver['id']; ?>">
                                                            <button type="submit" name="delete" class="btn btn-danger"><i class="fa-solid fa-trash me-2"></i>Hapus</button>
                                                        </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (empty($drivers)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-5 text-muted">
                                            <i class="fa-solid fa-box"></i>
                                            <p class="mb-0">Tidak ada driver<?= $search ? ' yang cocok dengan pencarian' : '' ?></p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="text-muted small">
                    Menampilkan <?= $offset + 1; ?> - <?= min($offset + $limit, $total_records); ?> dari <?= $total_records; ?> data
                </div>
                <nav aria-label="Navigasi halaman">
                    <ul class="pagination pagination-sm mb-0">
                        <!-- Previous Button -->
                        <li class="page-item <?= $page_num <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?= $page_num - 1; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>

                        <?php
                        // Pagination logic: show first, last, current, and nearby pages
                        $range = 2; // Pages to show around current page
                        
                        for ($i = 1; $i <= $total_pages; $i++) {
                            // Always show first page, last page, current page, and pages within range
                            if ($i == 1 || $i == $total_pages || ($i >= $page_num - $range && $i <= $page_num + $range)) {
                                ?>
                                <li class="page-item <?= $i == $page_num ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?= $i; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>"><?= $i; ?></a>
                                </li>
                                <?php
                            } elseif ($i == $page_num - $range - 1 || $i == $page_num + $range + 1) {
                                // Show ellipsis for gaps
                                ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                                <?php
                            }
                        }
                        ?>

                        <!-- Next Button -->
                        <li class="page-item <?= $page_num >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?= $page_num + 1; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
  </div>
</div>
<?php
    include '../../../templates/footer.php';
?>