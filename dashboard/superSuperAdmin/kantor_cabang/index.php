<?php
    session_start();
    if(!isset($_SESSION['username'] )|| !isset($_SESSION['user_id'])){
        header("Location: ../../../auth/login.php");
        exit;
    }

    if(isset($_SESSION['role']) && $_SESSION['role'] !== 'superSuperAdmin'){
        header("Location: ../../../?error=unauthorized");
        exit;
    }
    
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    include '../../../config/database.php';
    $title = "Dashboard - Cendana Kargo";
    
    $sql = "SELECT * FROM kantor_cabang ORDER BY id ASC";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $cabangs = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $cabangs = [];
    }
?>

<?php
    $page = "kantor_cabang";
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
                $message = "Kantor cabang berhasil ditambahkan";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['success']) && $_GET['success'] == 'updated'){
                $type = "success";
                $message = "Kantor cabang berhasil diperbarui";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['success']) && $_GET['success'] == 'deleted'){
                $type = "success";
                $message = "Kantor cabang berhasil dihapus";
                include '../../../components/alert.php';
            }?>
            <?php if(isset($_GET['error']) && $_GET['error'] == 'not_found'){
                $type = "danger";
                $message = "Kantor cabang tidak ditemukan";
                include '../../../components/alert.php';
            }?>
            <!-- Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h4 mb-1 fw-bold">Daftar Kantor Cabang</h1>    
                </div>
                <div class="d-flex gap-2 mt-2 mt-md-0">
                    <a href="create" class="btn btn-success mb-3">
                        <i class="fa-solid fa-plus"></i>
                        Add New Cabang
                    </a>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="px-4">ID</th>
                                    <th>Kode Cabang</th>
                                    <th>Nama Cabang</th>
                                    <th>Alamat</th>
                                    <th>Telepon</th>
                                     <th class="text-center" style="width: 100px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cabangs as $cabang): ?>
                                <tr>
                                    <td class="px-4 fw-semibold"><?= htmlspecialchars($cabang['id']); ?></td>
                                    <td><?= htmlspecialchars($cabang['kode_cabang']); ?></td>
                                    <td><?= htmlspecialchars($cabang['nama_cabang']); ?></td>
                                    <td><?= htmlspecialchars($cabang['alamat_cabang']); ?></td>
                                    <td><?= htmlspecialchars($cabang['telp_cabang']); ?></td>
                                    <td>
                                        <a href="update?id=<?= $cabang['id']; ?>" class="btn btn-sm btn-primary ">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <button type="button" data-bs-toggle="modal" data-bs-target="#delete<?= $cabang['id']; ?>" class="btn btn-sm btn-danger">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <!-- Delete Modal -->
                                <div class="modal fade" id="delete<?= $cabang['id']; ?>" tabindex="-1" aria-labelledby="deleteLabel<?= $cabang['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered"> <!-- Tambahkan 'modal-dialog-centered' agar modal di tengah -->
                                        <div class="modal-content">
                                            <div class="modal-header bg-danger text-white">
                                                <h5 class="modal-title" id="deleteLabel<?= $cabang['id']; ?>">
                                                    Konfirmasi Hapus cabang
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>

                                            <div class="modal-body text-center">
                                                <p>Apakah Anda yakin ingin menghapus cabang <strong><?= htmlspecialchars($cabang['nama_cabang']); ?></strong>?</p>
                                                <p class="text-muted mb-0">Tindakan ini tidak dapat dibatalkan.</p>
                                            </div>

                                            <div class="modal-footer justify-content-center">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <form action="delete" method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                        <input type="hidden" name="id" value="<?= $cabang['id']; ?>">
                                                        <button type="submit" name="delete" class="btn btn-danger">Hapus</button>
                                                    </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php if (empty($cabangs)): ?>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-box"></i>
                                        <p class="mb-0">Tidak ada cabang</p>
                                    </td>
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
    include '../../../templates/footer.php';
?>