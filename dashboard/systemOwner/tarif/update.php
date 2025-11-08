<?php
session_start();
if(!isset($_SESSION['username'] )|| !isset($_SESSION['user_id'])){
    header("Location: ../../../auth/login");
    exit;
}

if(isset($_SESSION['role']) && $_SESSION['role'] !== 'systemOwner'){
    header("Location: ../../../?error=unauthorized");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../../../config/database.php';
$title = "Dashboard - Cendana Kargo";

$sqlCabang = "SELECT * FROM kantor_cabang ORDER BY id ASC";
$resultCabang = $conn->query($sqlCabang);
$cabangs = $resultCabang->num_rows > 0 ? $resultCabang->fetch_all(MYSQLI_ASSOC) : [];

// Ambil data tarif berdasarkan ID
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
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: update?id=" . intval($_GET['id']) . "&error=failed");
        exit;
    }

    $asal = trim($_POST['id_cabang_asal']);
    $tujuan = trim($_POST['id_cabang_tujuan']);
    $tarif_dasar = trim($_POST['tarif_dasar']);
    $batas_berat = trim($_POST['batas_berat']);
    $tarif_tambahan = trim($_POST['tarif_tambahan_perkg']);
    $status = trim($_POST['status']);

    $asal_safe = mysqli_real_escape_string($conn, $asal);
    $tujuan_safe = mysqli_real_escape_string($conn, $tujuan);
    $tarif_safe = mysqli_real_escape_string($conn, $tarif_dasar);
    $batas_berat_safe = mysqli_real_escape_string($conn, $batas_berat);
    $tarif_tambahan_safe = mysqli_real_escape_string($conn, $tarif_tambahan);
    $status_safe = mysqli_real_escape_string($conn, $status);

    $id = intval($_POST['id']);
    if($asal_safe == $tujuan_safe){
        header("Location: update?id=$id&error=same_cabang");
        exit;
    }
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
            tarif_tambahan_perkg = '$tarif_tambahan_safe',
            status = '$status_safe'
            WHERE id = $id";

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
$page = "tarif";
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

        <h3 class="text-danger fw-bold mb-4">Edit Tarif Pengiriman</h3>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'failed'){
            $type = "danger";
            $message = "Gagal memperbarui data tarif";
            include '../../../components/alert.php';
        }?>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'kode_taken'){
            $type = "danger";
            $message = "Tarif antara cabang tersebut sudah ada.";
            include '../../../components/alert.php';
        }?>

        <?php if(isset($_GET['error']) && $_GET['error'] == 'same_cabang'){
            $type = "danger";
            $message = "Cabang asal dan tujuan tidak boleh sama.";
            include '../../../components/alert.php';
        }?>

        <form action="update" method="POST">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
          <input type="hidden" name="id" value="<?= $tarif['id']; ?>">

          <!-- Baris 1: Dari & Ke Cabang -->
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="id_cabang_asal" class="form-label fw-semibold">Dari Cabang</label>
              <select class="form-select" id="id_cabang_asal" name="id_cabang_asal" required>
                <option value="">-- Pilih Cabang Asal --</option>
                <?php foreach ($cabangs as $cabang): ?>
                  <option 
                    value="<?= htmlspecialchars($cabang['id']); ?>" 
                    <?= (!empty($tarif['id_cabang_asal']) && $tarif['id_cabang_asal'] == $cabang['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cabang['nama_cabang']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-6">
              <label for="id_cabang_tujuan" class="form-label fw-semibold">Ke Cabang</label>
              <select class="form-select" id="id_cabang_tujuan" name="id_cabang_tujuan" required>
                <option value="">-- Pilih Cabang Tujuan --</option>
                <?php foreach ($cabangs as $cabang): ?>
                  <option 
                    value="<?= htmlspecialchars($cabang['id']); ?>" 
                    <?= (!empty($tarif['id_cabang_tujuan']) && $tarif['id_cabang_tujuan'] == $cabang['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cabang['nama_cabang']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Baris 2: Tarif Dasar & Batas Berat -->
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="tarif_dasar" class="form-label fw-semibold">Tarif Dasar</label>
              <input type="number" class="form-control" id="tarif_dasar" name="tarif_dasar"
                     value="<?= htmlspecialchars($tarif['tarif_dasar']); ?>" required>
            </div>
            <div class="col-md-6">
              <label for="batas_berat" class="form-label fw-semibold">Batas Berat (kg)</label>
              <input type="number" class="form-control" id="batas_berat" name="batas_berat"
                     value="<?= htmlspecialchars($tarif['batas_berat_dasar']); ?>" required>
            </div>
          </div>

          <!-- Baris 3: Tarif Tambahan & Status -->
          <div class="row mb-4">
            <div class="col-md-6">
              <label for="tarif_tambahan_perkg" class="form-label fw-semibold">Tarif Tambahan per kg</label>
              <input type="number" class="form-control" id="tarif_tambahan_perkg" name="tarif_tambahan_perkg"
                     value="<?= htmlspecialchars($tarif['tarif_tambahan_perkg']); ?>" required>
            </div>
            <div class="col-md-6">
              <label for="status" class="form-label fw-semibold">Status</label>
              <select class="form-select" id="status" name="status" required>
                <option value="aktif" <?= ($tarif['status'] == 'aktif') ? 'selected' : '' ?>>Aktif</option>
                <option value="nonaktif" <?= ($tarif['status'] == 'nonaktif') ? 'selected' : '' ?>>Non-Aktif</option>
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
