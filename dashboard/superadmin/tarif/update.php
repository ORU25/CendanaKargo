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

    $sqlCabang = "SELECT * FROM kantor_cabang ORDER BY id ASC";
    $resultCabang = $conn->query($sqlCabang);
    if ($resultCabang->num_rows > 0) {
        $cabangs = $resultCabang->fetch_all(MYSQLI_ASSOC);
    } else {
        $cabangs = [];
    }

    # --- Ambil data tarif berdasarkan ID ---
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $sql = "SELECT * FROM tarif_pengiriman WHERE id = $id";
        $result = $conn->query($sql);
        if ($result->num_rows > 0) {
            $tarif = $result->fetch_assoc();
        } else {
            header("Location: ./?error=not_found");
            exit;
        }
    }
    
    if($_SERVER['REQUEST_METHOD'] === 'POST'){
        $asal = trim($_POST['id_cabang_asal']);
        $tujuan = trim($_POST['id_cabang_tujuan']);
        $tarif = trim($_POST['tarif_dasar']);
        $batas_berat = trim($_POST['batas_berat']);
        $tarif_tambahan_perkg = trim($_POST['tarif_tambahan_perkg']);
        $status = trim($_POST['status']);

        $asal_safe = mysqli_real_escape_string($conn, $asal);
        $tujuan_safe = mysqli_real_escape_string($conn, $tujuan);
        $tarif_safe = mysqli_real_escape_string($conn, $tarif);
        $batas_berat_safe = mysqli_real_escape_string($conn, $batas_berat);
        $tarif_tambahan_perkg_safe = mysqli_real_escape_string($conn, $tarif_tambahan_perkg);
        $status_safe = mysqli_real_escape_string($conn, $status);

        $id = intval($_POST['id']);
        $checkQuery = "SELECT COUNT(*) AS total 
                FROM tarif_pengiriman 
                WHERE id_cabang_asal = '$asal_safe' 
                  AND id_cabang_tujuan = '$tujuan_safe' 
                  AND id != '$id'"; 
        $checkResult = $conn->query($checkQuery);
        $row = $checkResult->fetch_assoc();

        if($row['total'] > 0){
            header("Location: update?id=$id&error=kode_taken");
            exit;
        }

        $sql = "UPDATE tarif_pengiriman SET 
                id_cabang_asal = '$asal_safe', 
                id_cabang_tujuan = '$tujuan_safe', 
                tarif_dasar = '$tarif_safe', 
                batas_berat_dasar = '$batas_berat_safe', 
                tarif_tambahan_perkg = '$tarif_tambahan_perkg_safe',
                status = '$status_safe'
                WHERE id = " . intval($_POST['id']);

        if($conn->query($sql) === TRUE){
            header("Location: ./?success=updated");
            exit;
        } else {
            header("Location: update?error=failed");
            exit;
        }
    }

?>

<?php  
    $page = "kantor_cabang";
    include '../../../templates/header.php';
    include '../../../components/navDashboard.php';
    include '../../../components/sidebar_offcanvas.php';
?>

<!-- Sidebar Offcanvas -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="sidebarOffcanvasLabel">Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div class="list-group list-group-flush">
            <a href="<?= BASE_URL; ?>dashboard/superadmin/" class="list-group-item list-group-item-action">Dashboard</a>
            <a href="<?= BASE_URL; ?>dashboard/superadmin/pengiriman/" class="list-group-item list-group-item-action">Pengiriman</a>
            <a href="<?= BASE_URL; ?>dashboard/superadmin/surat_jalan/" class="list-group-item list-group-item-action">Surat Jalan</a>
            <a href="<?= BASE_URL; ?>dashboard/superadmin/tarif/" class="list-group-item list-group-item-action">Tarif</a>
            <a href="<?= BASE_URL; ?>dashboard/superadmin/kantor_cabang/" class="list-group-item list-group-item-action fw-bold text-danger">Kantor Cabang</a>
            <a href="<?= BASE_URL; ?>dashboard/superadmin/user/" class="list-group-item list-group-item-action">User</a>
        </div>
        <a href="<?= BASE_URL; ?>auth/logout.php" class="btn btn-outline-danger mt-3 ms-3">Logout</a>
    </div>
</div>

<div class="container-fluid">
  <div class="row">
    <div class="col-lg-2 d-none d-lg-block bg-light border-end vh-100">
      <div class="list-group list-group-flush">
        <a href="<?= BASE_URL; ?>dashboard/superadmin/" class="list-group-item list-group-item-action">Dashboard</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/pengiriman/" class="list-group-item list-group-item-action">Pengiriman</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/surat_jalan/" class="list-group-item list-group-item-action">Surat Jalan</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/tarif/" class="list-group-item list-group-item-action fw-bold text-danger">Tarif</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/kantor_cabang/" class="list-group-item list-group-item-action">Kantor Cabang</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/user/" class="list-group-item list-group-item-action">User</a>
      </div>
        <a href="<?= BASE_URL; ?>auth/logout.php" class="btn btn-outline-danger mt-3">Logout</a>
    </div>

    <!-- Konten utama -->
    <div class="col-lg-10">
        <?php if(isset($_GET['error']) && $_GET['error'] == 'failed'){
            $type = "danger";
            $message = "Gagal menambahkan kantor cabang baru";
            include '../../../components/alert.php';
        }?>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'kode_taken'){
            $type = "danger";
            $message = "Kode cabang sudah ada, silakan gunakan kode lain.";
            include '../../../components/alert.php';
        }?>
        <h1>Tambah Tarif</h1>
        <form action="update" method="POST" class="col-5">
            <input type="hidden" name="id" value="<?= $tarif['id']; ?>">
            <div class="mb-3">
                <label for="id_cabang_asal" class="form-label">Dari Cabang</label>
                <select class="form-select" id="id_cabang_asal" name="id_cabang_asal" required>
                    <option value="">Select Cabang</option>
                    <?php foreach ($cabangs as $cabang): ?>
                            <option 
                                value="<?= htmlspecialchars($cabang['id']); ?>" 
                                <?= (!empty($tarif['id_cabang_asal']) && $tarif['id_cabang_asal'] == $cabang['id']) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($cabang['nama_cabang']); ?>
                            </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="id_cabang_tujuan" class="form-label">Ke Cabang</label>
                <select class="form-select" id="id_cabang_tujuan" name="id_cabang_tujuan" required>
                    <option value="">Select Cabang</option>
                    <?php foreach ($cabangs as $cabang): ?>
                            <option 
                                value="<?= htmlspecialchars($cabang['id']); ?>" 
                                <?= (!empty($tarif['id_cabang_tujuan']) && $tarif['id_cabang_tujuan'] == $cabang['id']) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($cabang['nama_cabang']); ?>
                            </option>
                        <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="tarif_dasar" class="form-label">Tarif Dasar</label>
                <input type="number" class="form-control" id="tarif_dasar" name="tarif_dasar" required <?= !empty($tarif['tarif_dasar']) ? 'value="'.$tarif['tarif_dasar'].'"' : '';?>>
            </div>
            <div class="mb-3">
                <label for="batas_berat" class="form-label">Batas Berat (kg)</label>
                <input type="number" class="form-control" id="batas_berat" name="batas_berat" required <?= !empty($tarif['batas_berat_dasar']) ? 'value="'.$tarif['batas_berat_dasar'].'"' : '';?>>
            </div>
            <div class="mb-3">
                <label for="tarif_tambahan_perkg" class="form-label">Tarif Tambahan per kg</label>
                <input type="number" class="form-control" id="tarif_tambahan_perkg" name="tarif_tambahan_perkg" required <?= !empty($tarif['tarif_tambahan_perkg']) ? 'value="'.$tarif['tarif_tambahan_perkg'].'"' : '';?>>
            </div>
            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status" required>
                    <option value="aktif" <?= (!empty($tarif['status']) && $tarif['status'] == 'aktif') ? 'selected' : '' ?>>Aktif</option>
                    <option value="nonaktif" <?= (!empty($tarif['status']) && $tarif['status'] == 'nonaktif') ? 'selected' : '' ?>>Non-Aktif</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary mt-3">Simpan</button>
        </form>
    </div>
  </div>
</div>
<?php
    include '../../../templates/footer.php';
?>