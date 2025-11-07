<?php
    session_start();
    if(!isset($_SESSION['username'] )|| !isset($_SESSION['user_id'])){
        header("Location: ../../../auth/login.php");
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
    
    // Ambil daftar cabang untuk filter
    $sqlCabang = "SELECT * FROM kantor_cabang ORDER BY nama_cabang ASC";
    $resultCabang = $conn->query($sqlCabang);
    $cabangs = $resultCabang->num_rows > 0 ? $resultCabang->fetch_all(MYSQLI_ASSOC) : [];
    
    // Pagination settings
    $limit = 10; // Data per halaman
    $page_num = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
    $offset = ($page_num - 1) * $limit;
    
    // Filter functionality
    $filter_asal = isset($_GET['filter_asal']) && is_numeric($_GET['filter_asal']) ? (int)$_GET['filter_asal'] : 0;
    $filter_tujuan = isset($_GET['filter_tujuan']) && is_numeric($_GET['filter_tujuan']) ? (int)$_GET['filter_tujuan'] : 0;
    
    // Build query with filters
    $whereClause = "WHERE 1=1";
    $params = [];
    $types = "";
    
    if ($filter_asal > 0) {
        $whereClause .= " AND tp.id_cabang_asal = ?";
        $params[] = $filter_asal;
        $types .= "i";
    }
    
    if ($filter_tujuan > 0) {
        $whereClause .= " AND tp.id_cabang_tujuan = ?";
        $params[] = $filter_tujuan;
        $types .= "i";
    }
    
    // Get total records
    $countSql = "SELECT COUNT(*) as total 
                 FROM tarif_pengiriman tp 
                 JOIN kantor_cabang ca ON tp.id_cabang_asal = ca.id
                 JOIN kantor_cabang ct ON tp.id_cabang_tujuan = ct.id
                 $whereClause";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($countSql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $total_records = $result->fetch_assoc()['total'];
        $stmt->close();
    } else {
        $result = $conn->query($countSql);
        $total_records = $result->fetch_assoc()['total'];
    }
    
    $total_pages = ceil($total_records / $limit);
    
    // Get paginated data
    $sql = "SELECT 
                tp.id,
                ca.kode_cabang AS dari_cabang,
                ca.nama_cabang AS nama_dari_cabang,
                ct.kode_cabang AS ke_cabang,
                ct.nama_cabang AS nama_ke_cabang,
                tp.tarif_dasar,
                tp.batas_berat_dasar,
                tp.tarif_tambahan_perkg,
                tp.status
            FROM tarif_pengiriman tp
            JOIN kantor_cabang ca ON tp.id_cabang_asal = ca.id
            JOIN kantor_cabang ct ON tp.id_cabang_tujuan = ct.id
            $whereClause
            ORDER BY ca.nama_cabang ASC, ct.nama_cabang ASC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $tarifs = $result->num_rows > 0 ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
?>

<?php
    $page = "tarif";
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
                $message = "Tarif berhasil ditambahkan";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['success']) && $_GET['success'] == 'updated'){
                $type = "success";
                $message = "Tarif berhasil diperbarui";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['success']) && $_GET['success'] == 'deleted'){
                $type = "success";
                $message = "Tarif berhasil dihapus";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'not_found'){
                $type = "danger";
                $message = "Tarif tidak ditemukan";
                include '../../../components/alert.php';
            }?>

            <!-- Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h4 mb-1 fw-bold">Daftar Tarif</h1>
                    <p class="text-muted small mb-0">
                        Menampilkan <?= count($tarifs); ?> dari <?= $total_records; ?> tarif
                        <?php if ($total_pages > 1): ?>
                            (Halaman <?= $page_num; ?> dari <?= $total_pages; ?>)
                        <?php endif; ?>
                    </p>
                </div>
                <div class="d-flex gap-2 mt-2 mt-md-0">
                    <a href="create" class="btn btn-success mb-3">
                        <i class="fa-solid fa-plus"></i>
                        Add New Tarif
                    </a>
                </div>
            </div>

            <!-- Filter Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="" class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <label for="filter_asal" class="form-label small fw-semibold mb-1">Cabang Asal</label>
                            <select name="filter_asal" id="filter_asal" class="form-select">
                                <option value="0">Semua Asal</option>
                                <?php foreach($cabangs as $c): ?>
                                    <option value="<?= $c['id']; ?>" <?= $filter_asal == $c['id'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($c['nama_cabang']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="filter_tujuan" class="form-label small fw-semibold mb-1">Cabang Tujuan</label>
                            <select name="filter_tujuan" id="filter_tujuan" class="form-select">
                                <option value="0">Semua Tujuan</option>
                                <?php foreach($cabangs as $c): ?>
                                    <option value="<?= $c['id']; ?>" <?= $filter_tujuan == $c['id'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($c['nama_cabang']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fa-solid fa-filter"></i>
                                Filter
                            </button>
                        </div>
                        <?php if ($filter_asal > 0 || $filter_tujuan > 0): ?>
                        <div class="col-12">
                            <a href="./" class="btn btn-sm btn-outline-secondary">
                                <i class="fa-solid fa-x"></i>
                                Reset Filter
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
                                    <th scope="col" class="ps-4">Dari Cabang</th>
                                    <th scope="col">Ke Cabang</th>
                                    <th scope="col">Tarif Dasar</th>
                                    <th scope="col">Batas Berat</th>
                                    <th scope="col">Tarif Tambahan/kg</th>
                                    <th scope="col">Status</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tarifs)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-box"></i>
                                        <p class="mb-0">Tidak ada data tarif<?= ($filter_asal > 0 || $filter_tujuan > 0) ? ' dengan filter yang dipilih' : '' ?>.</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($tarifs as $tarif): ?>
                                <tr>
                                    <td class="ps-4">
                                        <span class="badge bg-dark">
                                            <?= htmlspecialchars($tarif['dari_cabang']); ?>
                                        </span>
                                        <small class="d-block text-muted mt-1"><?= htmlspecialchars($tarif['nama_dari_cabang']); ?></small>
                                    </td>
                                    <td>                                        
                                        <span class="badge bg-secondary">
                                            <?= htmlspecialchars($tarif['ke_cabang']); ?>
                                        </span>
                                        <small class="d-block text-muted mt-1"><?= htmlspecialchars($tarif['nama_ke_cabang']); ?></small>
                                    </td>
                                    <td class="tarif fw-semibold"><?= htmlspecialchars($tarif['tarif_dasar']); ?></td>
                                    <td><?= htmlspecialchars($tarif['batas_berat_dasar']); ?> kg</td>
                                    <td class="tarif"><?= htmlspecialchars($tarif['tarif_tambahan_perkg']); ?></td>
                                    <td>
                                        <span class="badge text-bg-<?= $tarif['status'] == 'aktif' ? 'success' : 'secondary'; ?>">
                                            <?= htmlspecialchars($tarif['status']); ?>
                                        </span>
                                    </td>
                                    <td class="">
                                        <div class="d-flex align-items-center justify-content-center gap-2">
                                            <a href="update?id=<?= $tarif['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </a>
                                            <button type="button" data-bs-toggle="modal" data-bs-target="#delete<?= $tarif['id']; ?>" class="btn btn-sm btn-danger" title="Hapus">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Delete Modal -->
                                <div class="modal fade" id="delete<?= $tarif['id']; ?>" tabindex="-1" aria-labelledby="deleteLabel<?= $tarif['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header bg-danger text-white">
                                                <h5 class="modal-title" id="deleteLabel<?= $tarif['id']; ?>">
                                                    <i class="fa-solid fa-trash me-2"></i>Konfirmasi Hapus
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>

                                            <div class="modal-body text-center">
                                                <div class="text-center mb-3">
                                                    <i class="fa-solid fa-triangle-exclamation fa-3x text-warning"></i>
                                                </div>
                                                <p>Apakah Anda yakin ingin menghapus tarif <strong><?= htmlspecialchars($tarif['dari_cabang'] . ' - ' . $tarif['ke_cabang']); ?></strong>?</p>
                                                <p class="text-muted mb-0">Tindakan ini tidak dapat dibatalkan.</p>
                                            </div>

                                            <div class="modal-footer ">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <form action="delete" method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <input type="hidden" name="id" value="<?= $tarif['id']; ?>">
                                                        <button type="submit" name="delete" class="btn btn-danger"><i class="fa-solid fa-trash me-2"></i>Hapus</button>
                                                    </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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
                            <a class="page-link" href="?page=<?= $page_num - 1; ?><?= $filter_asal > 0 ? '&filter_asal=' . $filter_asal : ''; ?><?= $filter_tujuan > 0 ? '&filter_tujuan=' . $filter_tujuan : ''; ?>" aria-label="Previous">
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
                                    <a class="page-link" href="?page=<?= $i; ?><?= $filter_asal > 0 ? '&filter_asal=' . $filter_asal : ''; ?><?= $filter_tujuan > 0 ? '&filter_tujuan=' . $filter_tujuan : ''; ?>"><?= $i; ?></a>
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
                            <a class="page-link" href="?page=<?= $page_num + 1; ?><?= $filter_asal > 0 ? '&filter_asal=' . $filter_asal : ''; ?><?= $filter_tujuan > 0 ? '&filter_tujuan=' . $filter_tujuan : ''; ?>" aria-label="Next">
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

<script>
    const tarifElements = document.getElementsByClassName('tarif');

    for (let i = 0; i < tarifElements.length; i++) {
        const value = parseFloat(tarifElements[i].textContent); 
        tarifElements[i].textContent = new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR'
        }).format(value);
    }
</script>
<?php
    include '../../../templates/footer.php';
?>