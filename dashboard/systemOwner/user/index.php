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
    
    // Handle reopen closing
    if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reopen'])){
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            header("Location: ./?error=failed");
            exit;
        }
        $id = intval($_POST['id']);
        $today = date('Y-m-d');
        
        $delete_sql = "DELETE FROM closing WHERE id_user = $id AND tanggal_closing = '$today'";
        if($conn->query($delete_sql) === TRUE){
            header("Location: ./?success=reopened");
            exit;
        } else {
            header("Location: ./?error=failed");
            exit;
        }
    }
    
    // Pagination settings
    $limit = 10; // Data per halaman
    $page_num = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
    $offset = ($page_num - 1) * $limit;
    
    // Search functionality
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Get total records
    if ($search !== '') {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM user AS u 
            LEFT JOIN kantor_cabang AS c ON u.id_cabang = c.id 
            WHERE u.username LIKE ?  OR c.kode_cabang LIKE ?
        ");
        $searchParam = "%$search%";
        $stmt->bind_param('ss',  $searchParam, $searchParam);
        $stmt->execute();
        $result = $stmt->get_result();
        $total_records = $result->fetch_assoc()['total'];
        $stmt->close();
    } else {
        $result = $conn->query("SELECT COUNT(*) as total FROM user");
        $total_records = $result->fetch_assoc()['total'];
    }
    
    $total_pages = ceil($total_records / $limit);
    
    // Get paginated data
    if ($search !== '') {
        $stmt = $conn->prepare("
            SELECT u.id, u.username, u.role, c.kode_cabang,
            (SELECT COUNT(*) FROM closing WHERE id_user = u.id AND tanggal_closing = CURDATE()) as is_closing,
            (SELECT waktu_closing FROM closing WHERE id_user = u.id AND tanggal_closing = CURDATE() LIMIT 1) as waktu_closing
            FROM user AS u 
            LEFT JOIN kantor_cabang AS c ON u.id_cabang = c.id 
            WHERE u.username LIKE ? OR c.kode_cabang LIKE ?
            ORDER BY u.id ASC 
            LIMIT ? OFFSET ?
        ");
        $searchParam = "%$search%";
        $stmt->bind_param('ssii', $searchParam, $searchParam, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $users = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $stmt = $conn->prepare("
            SELECT u.id, u.username, u.role, c.kode_cabang,
            (SELECT COUNT(*) FROM closing WHERE id_user = u.id AND tanggal_closing = CURDATE()) as is_closing,
            (SELECT waktu_closing FROM closing WHERE id_user = u.id AND tanggal_closing = CURDATE() LIMIT 1) as waktu_closing
            FROM user AS u 
            LEFT JOIN kantor_cabang AS c ON u.id_cabang = c.id 
            ORDER BY u.id ASC 
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        $users = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
?>

<?php
    $page = "user";
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
                $message = "User berhasil ditambahkan";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['success']) && $_GET['success'] == 'updated'){
                $type = "success";
                $message = "User berhasil diperbarui";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'not_found'){
                $type = "danger";
                $message = "User tidak ditemukan";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'failed'){
                $type = "danger";
                $message = "Gagal melakukan operasi pada user";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'invalid_id'){
                $type = "danger";
                $message = "ID user tidak valid";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['success']) && $_GET['success'] == 'deleted'){
                $type = "success";
                $message = "User berhasil dihapus";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['success']) && $_GET['success'] == 'cannot_delete_self'){
                $type = "success";
                $message = "Tidak dapat menghapus user sendiri";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['success']) && $_GET['success'] == 'reopened'){
                $type = "success";
                $message = "User berhasil dibuka dari closing";
                include '../../../components/alert.php';
            }?>

            <!-- Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h4 mb-1 fw-bold">Daftar User</h1>
                    <p class="text-muted small mb-0">
                        Menampilkan <?= count($users); ?> dari <?= $total_records; ?> user
                        <?php if ($total_pages > 1): ?>
                            (Halaman <?= $page_num; ?> dari <?= $total_pages; ?>)
                        <?php endif; ?>
                    </p>
                </div>
                <div class="d-flex gap-2 mt-2 mt-md-0">
                    <a href="create" class="btn btn-success mb-3">
                        <i class="fa-solid fa-plus"></i>
                        Tambah User
                    </a>
                </div>
            </div>

            <!-- Search Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="" class="row g-2 align-items-center">
                        <div class="col-md-10">
                            <input type="text" name="search" class="form-control" placeholder="Cari berdasarkan Username atau Kode Cabang..." value="<?= htmlspecialchars($search); ?>">
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
                                <th class="px-4" style="width: 70px;">ID</th>
                                <th >Username</th>
                                <th >Role</th>
                                <th >Kode Cabang</th>
                                <th class="text-center">Status Closing</th>
                                <th class="text-center" style="width: 150px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($users as $index => $user): ?>
                                    <?php if($user['id'] !== $_SESSION['user_id']): ?>
                                        <tr>
                                            <td class="px-4 fw-semibold"><?= htmlspecialchars($user['id']); ?></td>
                                            <td><?= htmlspecialchars($user['username']); ?></td>
                                            <td><?= htmlspecialchars($user['role']); ?></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?= htmlspecialchars($user['kode_cabang'] ?? '-'); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?php if($user['is_closing'] > 0): ?>
                                                    <span class="badge bg-danger">Tutup</span>
                                                    <br>
                                                    <small class="text-muted" style="font-size: 0.75rem;">
                                                        <?php 
                                                        if($user['waktu_closing']){
                                                            $waktu = new DateTime($user['waktu_closing']);
                                                            echo $waktu->format('d/m/Y H:i');
                                                        }
                                                        ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Aktif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="">
                                                <a href="update?id=<?= $user['id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </a>
                                                <button type="button" data-bs-toggle="modal" data-bs-target="#delete<?= $user['id']; ?>" class="btn btn-sm btn-danger" title="Hapus">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                                <?php if($user['is_closing'] > 0): ?>
                                                    <button type="button" data-bs-toggle="modal" data-bs-target="#reopen<?= $user['id']; ?>" class="btn btn-sm btn-success" title="Buka Closing">
                                                        <i class="fa-solid fa-unlock"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <!-- Delete Modal -->
                                        <div class="modal fade" id="delete<?= $user['id']; ?>" tabindex="-1" aria-labelledby="deleteLabel<?= $user['id']; ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered"> <!-- Tambahkan 'modal-dialog-centered' agar modal di tengah -->
                                                <div class="modal-content">
                                                    <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title" id="deleteLabel<?= $user['id']; ?>">
                                                            <i class="fa-solid fa-trash me-2"></i>Konfirmasi Hapus User
                                                        </h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                        
                                                    <div class="modal-body text-center">
                                                        <div class="text-center mb-3">
                                                            <i class="fa-solid fa-triangle-exclamation fa-3x text-warning"></i>
                                                        </div>
                                                        <p>Apakah Anda yakin ingin menghapus user <strong><?= htmlspecialchars($user['username']); ?></strong>?</p>
                                                        <p class="text-muted mb-0">Tindakan ini tidak dapat dibatalkan.</p>
                                                    </div>
                        
                                                    <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                            <form action="delete" method="POST" class="d-inline">
                                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                <input type="hidden" name="id" value="<?= $user['id']; ?>">
                                                                <button type="submit" name="delete" class="btn btn-danger"><i class="fa-solid fa-trash me-2"></i>Hapus</button>
                                                            </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if($user['is_closing'] > 0): ?>
                                        <!-- Reopen Modal -->
                                        <div class="modal fade" id="reopen<?= $user['id']; ?>" tabindex="-1" aria-labelledby="reopenLabel<?= $user['id']; ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-success text-white">
                                                        <h5 class="modal-title" id="reopenLabel<?= $user['id']; ?>">
                                                            <i class="fa-solid fa-unlock me-2"></i>Konfirmasi Buka Closing
                                                        </h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                        
                                                    <div class="modal-body text-center">
                                                        <div class="text-center mb-3">
                                                            <i class="fa-solid fa-unlock fa-3x text-success"></i>
                                                        </div>
                                                        <p>Apakah Anda yakin ingin membuka closing untuk user <strong><?= htmlspecialchars($user['username']); ?></strong>?</p>
                                                        <p class="text-muted mb-0">User akan aktif kembali setelah dibuka.</p>
                                                    </div>
                        
                                                    <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                            <form action="" method="POST" class="d-inline">
                                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                <input type="hidden" name="id" value="<?= $user['id']; ?>">
                                                                <button type="submit" name="reopen" class="btn btn-success"><i class="fa-solid fa-lock-open me-2"></i>Buka Closing</button>
                                                            </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="fa-solid fa-box"></i>
                                            <p class="mb-0">Tidak ada User<?= $search ? ' yang cocok dengan pencarian' : '' ?></p>
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