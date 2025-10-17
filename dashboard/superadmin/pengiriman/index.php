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
    
    $sql = "SELECT * FROM pengiriman ORDER BY id ASC";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $pengirimans = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $pengirimans = [];
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
    <div class="col-lg-10">
        <?php if(isset($_GET['success']) && $_GET['success'] == 'created' && isset($_GET['resi'])){
            $type = "success";
            $message = "Pengiriman berhasil ditambahkan. No Resi: " . htmlspecialchars($_GET['resi']);
            include '../../../components/alert.php';
        }?>
        <?php if(isset($_GET['success']) && $_GET['success'] == 'updated'){
            $type = "success";
            $message = "Pengiriman berhasil diperbarui";
            include '../../../components/alert.php';
        }?>
        <?php if(isset($_GET['success']) && $_GET['success'] == 'deleted'){
            $type = "success";
            $message = "Pengiriman berhasil dihapus";
            include '../../../components/alert.php';
        }?>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'not_found'){
            $type = "danger";
            $message = "Pengiriman tidak ditemukan";
            include '../../../components/alert.php';
        }?>
        <?php if(isset($_GET['error'])){
            $type = "danger";
            $message = "Error:".$_GET['error'];
            include '../../../components/alert.php';
        }?>
        <h1>Pengiriman</h1>
        <a href="create" class="btn btn-success mb-3">Add New Pengiriman</a>
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">No Resi</th>
                    <th scope="col">Nama Barang</th>
                    <th scope="col">Total Tarif</th>
                    <th scope="col">Tanggal</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pengirimans as $pengiriman): ?>
                <tr>
                    <td><?= htmlspecialchars($pengiriman['id']); ?></td>
                    <td><?= htmlspecialchars($pengiriman['no_resi']); ?></td>
                    <td><?= htmlspecialchars($pengiriman['nama_barang']); ?></td>
                    <td class="tarif"><?= htmlspecialchars($pengiriman['total_tarif']); ?></td>
                    <td><?= htmlspecialchars($pengiriman['tanggal']); ?></td>
                    <td><?= htmlspecialchars($pengiriman['status']); ?></td>
                    <td>
                        <a href="detail?id=<?= $pengiriman['id']; ?>" class="btn btn-sm btn-secondary ">Detail</a>
                        <a href="update?id=<?= $pengiriman['id']; ?>" class="btn btn-sm btn-primary ">Edit</a>
                        <button type="button" data-bs-toggle="modal" data-bs-target="#delete<?= $pengiriman['id']; ?>" class="btn btn-sm btn-danger">Delete</button>
                    </td>
                </tr>
                <!-- Delete Modal -->
                <div class="modal fade" id="delete<?= $pengiriman['id']; ?>" tabindex="-1" aria-labelledby="deleteLabel<?= $pengiriman['id']; ?>" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered"> <!-- Tambahkan 'modal-dialog-centered' agar modal di tengah -->
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title" id="deleteLabel<?= $pengiriman['id']; ?>">
                                    Konfirmasi Hapus cabang
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body text-center">
                                <p>Apakah Anda yakin ingin menghapus cabang <strong><?= htmlspecialchars($pengiriman['nama_cabang']); ?></strong>?</p>
                                <p class="text-muted mb-0">Tindakan ini tidak dapat dibatalkan.</p>
                            </div>

                            <div class="modal-footer justify-content-center">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                    <form action="delete" method="POST" class="d-inline">
                                        <input type="hidden" name="id" value="<?= $pengiriman['id']; ?>">
                                        <button type="submit" name="delete" class="btn btn-danger">Hapus</button>
                                    </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($pengirimans)): ?>
                <tr>
                    <td colspan="5" class="text-center">No cabang found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
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
</div>
<?php
    include '../../../templates/footer.php';
?>