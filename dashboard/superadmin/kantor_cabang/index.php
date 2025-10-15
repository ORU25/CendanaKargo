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
    <div class="col-lg-2 d-none d-lg-block bg-light border-end vh-100">
      <div class="list-group list-group-flush">
        <a href="<?= BASE_URL; ?>dashboard/superadmin/" class="list-group-item list-group-item-action">Dashboard</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/pengiriman/" class="list-group-item list-group-item-action">Pengiriman</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/surat_jalan/" class="list-group-item list-group-item-action">Surat Jalan</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/tarif/" class="list-group-item list-group-item-action">Tarif</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/kantor_cabang/" class="list-group-item list-group-item-action fw-bold text-danger">Kantor Cabang</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/user/" class="list-group-item list-group-item-action">User</a>
      </div>
        <a href="<?= BASE_URL; ?>auth/logout.php" class="btn btn-outline-danger mt-3">Logout</a>
    </div>

    <!-- Konten utama -->
    <div class="col-lg-10">
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
        <?php if(isset($_GET['error']) && $_GET['error'] == 'not_found'){
            $type = "danger";
            $message = "Kantor cabang tidak ditemukan";
            include '../../../components/alert.php';
        }?>
        <h1>Kantor Cabang</h1>
        <a href="create.php" class="btn btn-success mb-3">Add New Cabang</a>
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Kode Cabang</th>
                    <th scope="col">Nama Cabang</th>
                    <th scope="col">Alamat</th>
                    <th scope="col">Telepon</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cabangs as $cabang): ?>
                <tr>
                    <td><?= htmlspecialchars($cabang['id']); ?></td>
                    <td><?= htmlspecialchars($cabang['kode_cabang']); ?></td>
                    <td><?= htmlspecialchars($cabang['nama_cabang']); ?></td>
                    <td><?= htmlspecialchars($cabang['alamat_cabang']); ?></td>
                    <td><?= htmlspecialchars($cabang['telp_cabang']); ?></td>
                    <td>
                        <a href="update?id=<?= $cabang['id']; ?>" class="btn btn-sm btn-primary ">Edit</a>
                        <a href="delete?id=<?= $cabang['id']; ?>" class="btn btn-sm btn-danger ">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($cabangs)): ?>
                <tr>
                    <td colspan="5" class="text-center">No cabang found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
  </div>
</div>
<?php
    include '../../../templates/footer.php';
?>