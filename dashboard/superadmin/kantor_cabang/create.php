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
    <?php include '../../../components/sidebar.php'; ?>

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