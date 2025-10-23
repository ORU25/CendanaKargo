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

// Token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../../../config/database.php';
$title = "Edit Barang Masuk - Cendana Kargo";

// Ambil data cabang untuk pilihan dropdown
$sqlCabang = "SELECT * FROM kantor_cabang ORDER BY id ASC";
$resultCabang = $conn->query($sqlCabang);
$cabangs = $resultCabang->num_rows > 0 ? $resultCabang->fetch_all(MYSQLI_ASSOC) : [];

// Ambil data barang berdasarkan ID
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "SELECT * FROM barang_masuk WHERE id = $id";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $barang = $result->fetch_assoc();
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
    $id_cabang = trim($_POST['id_cabang']);
    $nama_pengirim = trim($_POST['nama_pengirim']);
    $nama_penerima = trim($_POST['nama_penerima']);
    $alamat_penerima = trim($_POST['alamat_penerima']);
    $berat = trim($_POST['berat']);
    $status = trim($_POST['status']);
    $tanggal_masuk = trim($_POST['tanggal_masuk']);

    // Escape data
    $id_cabang_safe = mysqli_real_escape_string($conn, $id_cabang);
    $nama_pengirim_safe = mysqli_real_escape_string($conn, $nama_pengirim);
    $nama_penerima_safe = mysqli_real_escape_string($conn, $nama_penerima);
    $alamat_penerima_safe = mysqli_real_escape_string($conn, $alamat_penerima);
    $berat_safe = mysqli_real_escape_string($conn, $berat);
    $status_safe = mysqli_real_escape_string($conn, $status);
    $tanggal_masuk_safe = mysqli_real_escape_string($conn, $tanggal_masuk);

    $sql = "UPDATE barang_masuk SET 
                id_cabang = '$id_cabang_safe',
                nama_pengirim = '$nama_pengirim_safe',
                nama_penerima = '$nama_penerima_safe',
                alamat_penerima = '$alamat_penerima_safe',
                berat = '$berat_safe',
                status = '$status_safe',
                tanggal_masuk = '$tanggal_masuk_safe'
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
$page = "barang_masuk";
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
        <?php if(isset($_GET['error']) && $_GET['error'] == 'failed'){
            $type = "danger";
            $message = "Gagal memperbarui data barang masuk";
            include '../../../components/alert.php';
        }?>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'not_found'){
            $type = "danger";
            $message = "Data tidak ditemukan.";
            include '../../../components/alert.php';
        }?>

        <h3 class="text-danger fw-bold mb-4">Edit Barang Masuk</h3>

        <form action="update" method="POST">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="id" value="<?= $barang['id']; ?>">

          <!-- Baris 1: Cabang & Tanggal -->
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="id_cabang" class="form-label fw-semibold">Cabang</label>
              <select class="form-select" id="id_cabang" name="id_cabang" required>
                <option value="">-- Pilih Cabang --</option>
                <?php foreach ($cabangs as $cabang): ?>
                  <option 
                    value="<?= htmlspecialchars($cabang['id']); ?>" 
                    <?= ($barang['id_cabang'] == $cabang['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cabang['nama_cabang']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label for="tanggal_masuk" class="form-label fw-semibold">Tanggal Masuk</label>
              <input type="date" class="form-control" id="tanggal_masuk" name="tanggal_masuk"
                     value="<?= htmlspecialchars($barang['tanggal_masuk']); ?>" required>
            </div>
          </div>

          <!-- Baris 2: Pengirim & Penerima -->
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="nama_pengirim" class="form-label fw-semibold">Nama Pengirim</label>
              <input type="text" class="form-control" id="nama_pengirim" name="nama_pengirim"
                     value="<?= htmlspecialchars($barang['nama_pengirim']); ?>" required>
            </div>
            <div class="col-md-6">
              <label for="nama_penerima" class="form-label fw-semibold">Nama Penerima</label>
              <input type="text" class="form-control" id="nama_penerima" name="nama_penerima"
                     value="<?= htmlspecialchars($barang['nama_penerima']); ?>" required>
            </div>
          </div>

          <!-- Baris 3: Alamat & Berat -->
          <div class="row mb-3">
            <div class="col-md-8">
              <label for="alamat_penerima" class="form-label fw-semibold">Alamat Penerima</label>
              <input type="text" class="form-control" id="alamat_penerima" name="alamat_penerima"
                     value="<?= htmlspecialchars($barang['alamat_penerima']); ?>" required>
            </div>
            <div class="col-md-4">
              <label for="berat" class="form-label fw-semibold">Berat (kg)</label>
              <input type="number" step="0.1" class="form-control" id="berat" name="berat"
                     value="<?= htmlspecialchars($barang['berat']); ?>" required>
            </div>
          </div>

          <!-- Baris 4: Status -->
          <div class="row mb-4">
            <div class="col-md-6">
              <label for="status" class="form-label fw-semibold">Status</label>
              <select class="form-select" id="status" name="status" required>
                <option value="diproses" <?= ($barang['status'] == 'diproses') ? 'selected' : '' ?>>Diproses</option>
                <option value="dikirim" <?= ($barang['status'] == 'dikirim') ? 'selected' : '' ?>>Dikirim</option>
                <option value="selesai" <?= ($barang['status'] == 'selesai') ? 'selected' : '' ?>>Selesai</option>
              </select>
            </div>
          </div>

          <div>
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
