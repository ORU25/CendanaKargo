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
    
    if($_SERVER['REQUEST_METHOD'] === 'POST'){
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            header("Location: create?error=failed");
            exit;
        }
        $kode_cabang = trim($_POST['kode_cabang']);
        $nama_cabang = trim($_POST['nama_cabang']);
        $alamat = trim($_POST['alamat']);
        $telepon = trim($_POST['telepon']);

        $kode_safe = mysqli_real_escape_string($conn, $kode_cabang);
        $check = "SELECT id FROM kantor_cabang WHERE kode_cabang = '$kode_safe'";
        $result = $conn->query($check);
        if($result->num_rows > 0){
            header("Location: create?error=kode_taken");
            exit;
        }

        $nama_safe = mysqli_real_escape_string($conn, $nama_cabang);
        $alamat_safe = mysqli_real_escape_string($conn, $alamat);
        $telepon_safe = mysqli_real_escape_string($conn, $telepon);

        $sql = "INSERT INTO kantor_cabang (kode_cabang, nama_cabang, alamat_cabang, telp_cabang) 
                VALUES ('$kode_safe', '$nama_safe', '$alamat_safe', '$telepon_safe')";

        if($conn->query($sql) === TRUE){
            header("Location: ./?success=created");
            exit;
        } else {
            header("Location: create?error=failed");
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
        <h1>Tambah Kantor Cabang</h1>
        <form action="create" method="POST" class="col-5">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="mb-3">
                <label for="kode_cabang" class="form-label">Kode Cabang</label>
                <input type="text" class="form-control" id="kode_cabang" name="kode_cabang" required>
            </div>
            <div class="mb-3">
                <label for="nama_cabang" class="form-label">Nama Cabang</label>
                <input type="text" class="form-control" id="nama_cabang" name="nama_cabang" required>
            </div>
            <div class="mb-3">
                <label for="alamat" class="form-label">Alamat</label>
                <textarea class="form-control" id="alamat" name="alamat" rows="3" required></textarea>
            </div>
            <div>
                <label for="telepon" class="form-label">Telepon</label>
                <input type="text" class="form-control" id="telepon" name="telepon" required>
            </div>
            <button type="submit" class="btn btn-primary mt-3">Simpan</button>
        </form>
    </div>
  </div>
</div>
<?php
    include '../../../templates/footer.php';
?>