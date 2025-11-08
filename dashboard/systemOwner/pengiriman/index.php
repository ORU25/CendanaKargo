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
    
    $title = "Pengiriman - Cendana Kargo";
    
    // Pagination settings
    $limit = 10; // Data per halaman
    $page_num = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
    $offset = ($page_num - 1) * $limit;
    
    // Search functionality
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Get total records
    if ($search !== '') {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM pengiriman WHERE no_resi LIKE ? OR nama_barang LIKE ? OR nama_pengirim LIKE ? OR nama_penerima LIKE ?");
        $searchParam = "%$search%";
        $stmt->bind_param('ssss', $searchParam, $searchParam, $searchParam, $searchParam);
        $stmt->execute();
        $result = $stmt->get_result();
        $total_records = $result->fetch_assoc()['total'];
        $stmt->close();
    } else {
        $result = $conn->query("SELECT COUNT(*) as total FROM pengiriman");
        $total_records = $result->fetch_assoc()['total'];
    }
    
    $total_pages = ceil($total_records / $limit);
    
    // Get paginated data
    if ($search !== '') {
        $stmt = $conn->prepare("SELECT * FROM pengiriman WHERE no_resi LIKE ? OR nama_barang LIKE ? OR nama_pengirim LIKE ? OR nama_penerima LIKE ? ORDER BY id DESC LIMIT ? OFFSET ?");
        $searchParam = "%$search%";
        $stmt->bind_param('ssssii', $searchParam, $searchParam, $searchParam, $searchParam, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $pengirimans = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $stmt = $conn->prepare("SELECT * FROM pengiriman ORDER BY id DESC LIMIT ? OFFSET ?");
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $pengirimans = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
?>

<?php
    $page = "pengiriman";
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
            <!-- Alerts -->
            <?php if(isset($_GET['success']) && $_GET['success'] == 'created' && isset($_GET['resi'])){
                $type = "success";
                $message = "Pengiriman berhasil ditambahkan. No Resi: " . htmlspecialchars($_GET['resi']);
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'not_found'){
                $type = "danger";
                $message = "Pengiriman tidak ditemukan";
                include '../../../components/alert.php';
            }?>

            <!-- Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h4 mb-1 fw-bold">Daftar Pengiriman</h1>
                    <p class="text-muted small mb-0">
                        Menampilkan <?= count($pengirimans); ?> dari <?= $total_records; ?> pengiriman
                        <?php if ($total_pages > 1): ?>
                            (Halaman <?= $page_num; ?> dari <?= $total_pages; ?>)
                        <?php endif; ?>
                    </p>
                </div>
                <div class="d-flex gap-2 mt-2 mt-md-0">
                    <a href="create.php" class="btn btn-success">
                        <i class="fa-solid fa-plus"></i>
                        Tambah Pengiriman
                    </a>
                </div>
            </div>

            <!-- Search & Filter Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="" class="row g-2 align-items-center">
                        <div class="col-md-10">
                            <input type="text" name="search" class="form-control" placeholder="Cari berdasarkan No Resi, Nama Barang, Pengirim, atau Penerima..." value="<?= htmlspecialchars($search); ?>">
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

            <!-- Table Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="px-4" style="width: 70px;">ID</th>
                                    <th>No Resi</th>
                                    <th>Nama Barang</th>
                                    <th>Pengirim</th>
                                    <th>Penerima</th>
                                    <th>Tujuan</th>
                                    <th class="text-end">Total Tarif</th>
                                    <th>Tanggal</th>
                                    <th>Status</th>
                                    <th class="text-center" style="width: 100px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pengirimans)): ?>
                                    <tr>
                                        <td colspan="10" class="text-center py-5 text-muted">
                                            <i class="fa-solid fa-box"></i>
                                            <p class="mb-0">Tidak ada data pengiriman<?= $search ? ' yang cocok dengan pencarian' : '' ?>.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($pengirimans as $p): 
                                        // Tentukan warna badge
                                        $badgeClass = 'secondary';
                                        switch(strtolower($p['status'])) {
                                            case 'bkd': $badgeClass = 'warning'; break;
                                            case 'dalam pengiriman': $badgeClass = 'primary'; break;
                                            case 'sampai tujuan': $badgeClass = 'info'; break;
                                            case 'pod': $badgeClass = 'success'; break;
                                            case 'dibatalkan': $badgeClass = 'danger'; break;
                                        }
                                    ?>
                                    <tr class="text-capitalize">
                                        <td class="px-4 fw-semibold"><?= (int)$p['id']; ?></td>
                                        <td><span class="badge bg-dark"><?= htmlspecialchars($p['no_resi']); ?></span></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($p['nama_barang']); ?></td>
                                        <td class="small"><?= htmlspecialchars($p['nama_pengirim']); ?></td>
                                        <td class="small"><?= htmlspecialchars($p['nama_penerima']); ?></td>
                                        <td class="small"><?= htmlspecialchars($p['cabang_penerima']); ?></td>
                                        <td class="text-end fw-semibold">Rp <?= number_format($p['total_tarif'], 0, ',', '.'); ?></td>
                                        <td class="small"><?= date('d/m/Y', strtotime($p['tanggal'])); ?></td>
                                        <td><span class="text-uppercase badge text-bg-<?= $badgeClass; ?>"><?= htmlspecialchars($p['status']); ?></span></td>
                                        <td class="text-center">
                                            <a href="detail?id=<?= (int)$p['id']; ?>" class="btn btn-sm btn-outline-primary" title="Lihat Detail">
                                                <i class="fa-solid fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
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