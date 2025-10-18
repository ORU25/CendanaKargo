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
    
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    include '../../../config/database.php';
    $title = "Dashboard - Cendana Kargo";
    
    $sql = "SELECT 
                tp.id,
                ca.kode_cabang AS dari_cabang,
                ct.kode_cabang AS ke_cabang,
                tp.tarif_dasar,
                tp.batas_berat_dasar,
                tp.tarif_tambahan_perkg,
                tp.status
            FROM tarif_pengiriman tp
            JOIN kantor_cabang ca ON tp.id_cabang_asal = ca.id
            JOIN kantor_cabang ct ON tp.id_cabang_tujuan = ct.id;";

    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $tarifs = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $tarifs = [];
    }
?>

<?php
    $page = "tarif";
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
        <a href="<?= BASE_URL; ?>dashboard/superadmin/tarif/" class="list-group-item list-group-item-action fw-bold text-danger">Tarif</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/kantor_cabang/" class="list-group-item list-group-item-action ">Kantor Cabang</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/user/" class="list-group-item list-group-item-action">User</a>
      </div>
        <a href="<?= BASE_URL; ?>auth/logout.php" class="btn btn-outline-danger mt-3">Logout</a>
    </div>

    <!-- Konten utama -->
    <div class="col-lg-10">
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

        <h1>Tarif</h1>
        <a href="create" class="btn btn-success mb-3">
            <i class="fa-solid fa-plus"></i>    
            Add New Tarif
        </a>
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">ID</th>
                    <th scope="col">Dari Cabang</th>
                    <th scope="col">Ke Cabang</th>
                    <th scope="col">Tarif Dasar</th>
                    <th scope="col">Batas Berat</th>
                    <th scope="col">Tarif Tambahan</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tarifs as $tarif): ?>
                <tr>
                    <td><?= htmlspecialchars($tarif['id']); ?></td>
                    <td><?= htmlspecialchars($tarif['dari_cabang']); ?></td>
                    <td><?= htmlspecialchars($tarif['ke_cabang']); ?></td>
                    <td class="tarif"><?= htmlspecialchars($tarif['tarif_dasar']); ?></td>
                    <td><?= htmlspecialchars($tarif['batas_berat_dasar']); ?></td>
                    <td class="tarif"><?= htmlspecialchars($tarif['tarif_tambahan_perkg']); ?></td>
                    <td><?= htmlspecialchars($tarif['status']); ?></td>
                    <td>
                        <a href="update?id=<?= $tarif['id']; ?>" class="btn btn-sm btn-primary ">
                            <i class="fa-solid fa-pen-to-square"></i>
                        </a>
                        <button type="button" data-bs-toggle="modal" data-bs-target="#delete<?= $tarif['id']; ?>" class="btn btn-sm btn-danger">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <!-- Delete Modal -->
                <div class="modal fade" id="delete<?= $tarif['id']; ?>" tabindex="-1" aria-labelledby="deleteLabel<?= $tarif['id']; ?>" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered"> <!-- Tambahkan 'modal-dialog-centered' agar modal di tengah -->
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title" id="deleteLabel<?= $tarif['id']; ?>">
                                    Konfirmasi Hapus tarif
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body text-center">
                                <p>Apakah Anda yakin ingin menghapus tarif <strong><?= htmlspecialchars($tarif['dari_cabang'] . ' - ' . $tarif['ke_cabang']); ?></strong>?</p>
                                <p class="text-muted mb-0">Tindakan ini tidak dapat dibatalkan.</p>
                            </div>

                            <div class="modal-footer justify-content-center">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                    <form action="delete" method="POST" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        <input type="hidden" name="id" value="<?= $tarif['id']; ?>">
                                        <button type="submit" name="delete" class="btn btn-danger">Hapus</button>
                                    </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($tarifs)): ?>
                <tr>
                    <td colspan="5" class="text-center">No cabang found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
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