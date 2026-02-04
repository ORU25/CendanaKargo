<?php
    session_start();
    if(!isset($_SESSION['username'] )|| !isset($_SESSION['user_id'])){
        header("Location: ../../../auth/login");
        exit;
    }

    if(isset($_SESSION['role']) && $_SESSION['role'] !== 'superAdmin'){
        header("Location: ../../../?error=unauthorized");
        exit;
    }

    include '../../../config/database.php';
    session_write_close();
    
    $title = "Pengiriman - Cendana Kargo";
    
    $id_cabang_user = $_SESSION['id_cabang'] ?? null;
    
    if (!$id_cabang_user) {
        header("Location: ../../../?error=no_branch");
        exit;
    }
    
    $limit = 10;
    $page_num = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
    $offset = ($page_num - 1) * $limit;
    
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Badge class mapping
    $badgeMap = [
        'bkd' => 'warning',
        'dalam pengiriman' => 'primary', 
        'sampai tujuan' => 'info',
        'pod' => 'success',
        'dibatalkan' => 'danger'
    ];
    
    // Build query dynamically
    $whereClause = "WHERE id_cabang_pengirim = ?";
    $params = [$id_cabang_user];
    $types = "i";
    
    if ($search !== '') {
        $searchParam = "%$search%";
        $whereClause .= " AND (no_resi LIKE ? OR nama_barang LIKE ? OR nama_pengirim LIKE ? OR nama_penerima LIKE ?)";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
        $types .= "ssss";
    }
    
    // Fetch LIMIT + 1 to check if next page exists (no COUNT needed)
    $fetchLimit = $limit + 1;
    $dataParams = $params;
    $dataTypes = $types;
    $dataParams[] = $fetchLimit;
    $dataParams[] = $offset;
    $dataTypes .= "ii";
    
    $stmt = $conn->prepare("
        SELECT id, no_resi, nama_pengirim, nama_penerima, nama_barang, 
               cabang_penerima, total_tarif, status, tanggal 
        FROM pengiriman 
        $whereClause 
        ORDER BY id DESC LIMIT ? OFFSET ?
    ");
    
    $stmt->bind_param($dataTypes, ...$dataParams);
    $stmt->execute();
    $pengirimans = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Check if there's a next page
    $hasNextPage = count($pengirimans) > $limit;
    if ($hasNextPage) {
        array_pop($pengirimans); // Remove the extra record
    }
    $hasPrevPage = $page_num > 1;
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
                        Menampilkan <?= count($pengirimans); ?> data
                        <?php if ($hasPrevPage || $hasNextPage): ?>
                            (Halaman <?= $page_num; ?>)
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
                                    <th class="px-4">No Resi</th>
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
                                        $badgeClass = $badgeMap[strtolower($p['status'])] ?? 'secondary';
                                    ?>
                                    <tr class="text-capitalize">
                                        <td class="px-4"><span class="badge bg-dark"><?= htmlspecialchars($p['no_resi']); ?></span></td>
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
            <?php if ($hasPrevPage || $hasNextPage): ?>
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="text-muted small">
                    Halaman <?= $page_num; ?>
                </div>
                <nav aria-label="Navigasi halaman">
                    <ul class="pagination pagination-sm mb-0">
                        <!-- Previous Button -->
                        <li class="page-item <?= !$hasPrevPage ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?= $page_num - 1; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>" aria-label="Previous">
                                <i class="fa-solid fa-chevron-left"></i> Sebelumnya
                            </a>
                        </li>

                        <!-- Current Page -->
                        <li class="page-item active">
                            <span class="page-link"><?= $page_num; ?></span>
                        </li>

                        <!-- Next Button -->
                        <li class="page-item <?= !$hasNextPage ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?= $page_num + 1; ?><?= $search ? '&search=' . urlencode($search) : ''; ?>" aria-label="Next">
                                Selanjutnya <i class="fa-solid fa-chevron-right"></i>
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