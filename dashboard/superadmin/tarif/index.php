<?php
    session_start();
    if(!isset($_SESSION['username'] )|| !isset($_SESSION['user_id'])){
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
            WHERE tp.id_cabang_asal = " . intval($_SESSION['id_cabang']) . "
            order by ca.kode_cabang asc;";

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
                </div>
                <div class="d-flex gap-2 mt-2 mt-md-0">
                    <a href="create" class="btn btn-success mb-3">
                        <i class="fa-solid fa-plus"></i>
                        Add New Tarif
                    </a>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col" class="ps-4">Dari Cabang</th>
                                    <th scope="col">Ke Cabang</th>
                                    <th scope="col">Tarif Dasar</th>
                                    <th scope="col">Batas Berat</th>
                                    <th scope="col">Tarif Tambahan</th>
                                    <th scope="col">Status</th>
                                    <th class="text-center" style="width: 100px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
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
                                        <div class="d-flex gap-2 justify-content-center">
                                            <a href="update?id=<?= $tarif['id']; ?>" class="btn btn-sm btn-primary" title="Edit">
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
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-box"></i>
                                        <p class="mb-0">Tidak ada data tarif</p>
                                    </td>
                                </tr>
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