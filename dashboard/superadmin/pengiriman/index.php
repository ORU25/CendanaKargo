<?php
    session_start();
    if(!isset($_SESSION['username'])){
        header("Location: ../../../auth/login.php");
        exit;
    }

    if(isset($_SESSION['role']) && $_SESSION['role'] !== 'superAdmin'){
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
    <div class="col-lg-2 d-none d-lg-block bg-light border-end vh-100">
      <div class="list-group list-group-flush">
        <a href="<?= BASE_URL; ?>dashboard/superadmin/" class="list-group-item list-group-item-action">Dashboard</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/pengiriman/" class="list-group-item list-group-item-action fw-bold text-danger">Pengiriman</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/surat_jalan/" class="list-group-item list-group-item-action">Surat Jalan</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/tarif/" class="list-group-item list-group-item-action">Tarif</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/kantor_cabang/" class="list-group-item list-group-item-action">Kantor Cabang</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/user/" class="list-group-item list-group-item-action">User</a>
      </div>
        <a href="<?= BASE_URL; ?>auth/logout.php" class="btn btn-outline-danger mt-3">Logout</a>
    </div>

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
                        <svg width="16" height="16" fill="currentColor" class="me-1">
                            <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                        </svg>
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
                                <svg width="16" height="16" fill="currentColor" class="me-1">
                                    <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/>
                                </svg>
                                Cari
                            </button>
                        </div>
                        <?php if ($search): ?>
                        <div class="col-12">
                            <a href="./" class="btn btn-sm btn-outline-secondary">
                                <svg width="14" height="14" fill="currentColor" class="me-1">
                                    <path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8 2.146 2.854Z"/>
                                </svg>
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
                                    <th class="text-end">Total Tarif</th>
                                    <th>Tanggal</th>
                                    <th>Status</th>
                                    <th class="text-center" style="width: 100px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pengirimans)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-5 text-muted">
                                            <svg width="48" height="48" fill="currentColor" class="mb-3 opacity-50">
                                                <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                                                <path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 4.995z"/>
                                            </svg>
                                            <p class="mb-0">Tidak ada data pengiriman<?= $search ? ' yang cocok dengan pencarian' : '' ?>.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($pengirimans as $p): 
                                        // Tentukan warna badge
                                        $badgeClass = 'secondary';
                                        switch(strtolower($p['status'])) {
                                            case 'dalam proses': $badgeClass = 'warning'; break;
                                            case 'dalam pengiriman': $badgeClass = 'primary'; break;
                                            case 'sampai tujuan': $badgeClass = 'info'; break;
                                            case 'selesai': $badgeClass = 'success'; break;
                                            case 'dibatalkan': $badgeClass = 'danger'; break;
                                        }
                                    ?>
                                    <tr>
                                        <td class="px-4 fw-semibold"><?= (int)$p['id']; ?></td>
                                        <td><span class="badge bg-dark"><?= htmlspecialchars($p['no_resi']); ?></span></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($p['nama_barang']); ?></td>
                                        <td class="small"><?= htmlspecialchars($p['nama_pengirim']); ?></td>
                                        <td class="small"><?= htmlspecialchars($p['nama_penerima']); ?></td>
                                        <td class="text-end fw-semibold">Rp <?= number_format($p['total_tarif'], 0, ',', '.'); ?></td>
                                        <td class="small"><?= date('d/m/Y', strtotime($p['tanggal'])); ?></td>
                                        <td><span class="badge text-bg-<?= $badgeClass; ?>"><?= htmlspecialchars($p['status']); ?></span></td>
                                        <td class="text-center">
                                            <a href="detail?id=<?= (int)$p['id']; ?>" class="btn btn-sm btn-outline-primary" title="Lihat Detail">
                                                <svg width="16" height="16" fill="currentColor">
                                                    <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"/>
                                                    <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"/>
                                                </svg>
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