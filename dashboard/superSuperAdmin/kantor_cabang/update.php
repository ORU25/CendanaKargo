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

if($_GET['id']) {
    $id = intval($_GET['id']);
    $sql = "SELECT * FROM kantor_cabang WHERE id = $id";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $cabang = $result->fetch_assoc();
    } else {
        header("Location: ./?error=not_found");
        exit;
    }
}

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: update?id=" . intval($_GET['id']) . "&error=failed");
        exit;
    }

    $id = intval($_POST['id']);
    $kode_cabang = trim($_POST['kode_cabang']);
    $nama_cabang = trim($_POST['nama_cabang']);
    $alamat = trim($_POST['alamat']);
    $telepon = trim($_POST['telepon']);

    $kode_safe = mysqli_real_escape_string($conn, $kode_cabang);
    $check = "SELECT id FROM kantor_cabang WHERE kode_cabang = '$kode_safe' AND id != $id";
    $result = $conn->query($check);
    if($result->num_rows > 0){
        header("Location: update?id=$id&error=kode_taken");
        exit;
    }

    $nama_safe = mysqli_real_escape_string($conn, $nama_cabang);
    $alamat_safe = mysqli_real_escape_string($conn, $alamat);
    $telepon_safe = mysqli_real_escape_string($conn, $telepon);

    $sql = "UPDATE kantor_cabang 
            SET kode_cabang = '$kode_safe', nama_cabang = '$nama_safe', alamat_cabang = '$alamat_safe', telp_cabang = '$telepon_safe'
            WHERE id = $id";

    if($conn->query($sql) === TRUE){
        header("Location: ./?success=updated");
        exit;
    } else {
        header("Location: update?id=$id&error=failed");
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
    <div class="col-lg-10 d-flex align-items-start justify-content-start py-4 px-5">
      <div class="card shadow-sm p-4" style="width: 100%; max-width: 750px;">
        <h3 class="text-danger fw-bold mb-4">Edit Kantor Cabang <?= isset($cabang['nama_cabang']) ? htmlspecialchars($cabang['nama_cabang']) : '' ?></h3>
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


        <form action="update" method="POST">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="id" value="<?= $id; ?>">

          <!-- Baris 1: Kode & Nama Cabang -->
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="kode_cabang" class="form-label fw-semibold">Kode Cabang</label>
              <input type="text" class="form-control" id="kode_cabang" name="kode_cabang"
                     value="<?= isset($cabang['kode_cabang']) ? htmlspecialchars($cabang['kode_cabang']) : '' ?>" required>
            </div>
            <div class="col-md-6">
              <label for="nama_cabang" class="form-label fw-semibold">Nama Cabang</label>
              <input type="text" class="form-control" id="nama_cabang" name="nama_cabang"
                     value="<?= isset($cabang['nama_cabang']) ? htmlspecialchars($cabang['nama_cabang']) : '' ?>" required>
            </div>
          </div>

          <!-- Baris 2: Alamat & Telepon -->
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="alamat" class="form-label fw-semibold">Alamat</label>
              <textarea class="form-control" id="alamat" name="alamat" rows="3" required><?= isset($cabang['alamat_cabang']) ? htmlspecialchars($cabang['alamat_cabang']) : '' ?></textarea>
            </div>
            <div class="col-md-6">
              <label for="telepon" class="form-label fw-semibold">Telepon</label>
              <input type="text" class="form-control" id="telepon" name="telepon"
                     value="<?= isset($cabang['telp_cabang']) ? htmlspecialchars($cabang['telp_cabang']) : '' ?>" required>
            </div>
          </div>

          <div class="mt-3">
            <button type="submit" class="btn btn-danger fw-semibold px-4">Simpan</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
include '../../../templates/footer.php';
?>
