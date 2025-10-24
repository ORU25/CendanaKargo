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

    $sqlCabang = "SELECT * FROM kantor_cabang ORDER BY id ASC";
    $resultCabang = $conn->query($sqlCabang);
    if ($resultCabang->num_rows > 0) {
        $cabangs = $resultCabang->fetch_all(MYSQLI_ASSOC);
    } else {
        $cabangs = [];
    }
    
    if($_SERVER['REQUEST_METHOD'] === 'POST'){
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            header("Location: create?error=failed");
            exit;
        }

        $asal = trim($_POST['id_cabang_asal']);
        $tujuan = trim($_POST['id_cabang_tujuan']);
        $tarif = trim($_POST['tarif_dasar']);
        $batas_berat = trim($_POST['batas_berat']);
        $tarif_tambahan_perkg = trim($_POST['tarif_tambahan_perkg']);

        $asal_safe = mysqli_real_escape_string($conn, $asal);
        $tujuan_safe = mysqli_real_escape_string($conn, $tujuan);
        $tarif_safe = mysqli_real_escape_string($conn, $tarif);
        $batas_berat_safe = mysqli_real_escape_string($conn, $batas_berat);
        $tarif_tambahan_perkg_safe = mysqli_real_escape_string($conn, $tarif_tambahan_perkg);

        $checkQuery = "SELECT COUNT(*) AS total FROM tarif_pengiriman 
                    WHERE id_cabang_asal = '$asal_safe' 
                    AND id_cabang_tujuan = '$tujuan_safe'";
        $checkResult = $conn->query($checkQuery);
        $row = $checkResult->fetch_assoc();

        if ($row['total'] > 0) {
            header("Location: create?error=duplicate");
            exit;
        }

        $sql = "INSERT INTO tarif_pengiriman 
                (id_cabang_asal, id_cabang_tujuan, tarif_dasar, batas_berat_dasar, tarif_tambahan_perkg, status) 
                VALUES 
                ('$asal_safe', '$tujuan_safe', '$tarif_safe', '$batas_berat_safe', '$tarif_tambahan_perkg_safe', 'aktif')";

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
    $page = "tarif";
    include '../../../templates/header.php';
    include '../../../components/navDashboard.php';
    include '../../../components/sidebar_offcanvas.php';
?>

<div class="container-fluid">
  <div class="row">
    <?php include '../../../components/sidebar.php'; ?>

    <!-- Konten utama -->
    <div class="col-lg-10 d-flex align-items-start justify-content-start" style="min-height: 90vh;">
      <div class="card shadow-sm p-4 mt-4 ms-3" style="width: 100%; max-width: 800px;">
          
        <h3 class="text-danger fw-bold mb-4">Tambah Tarif</h3>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'failed'){
            $type = "danger";
            $message = "Gagal menambahkan tarif baru";
            include '../../../components/alert.php';
        }?>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'duplicate'){
            $type = "danger";
            $message = "Tarif sudah ada";
            include '../../../components/alert.php';
        }?>


        <form action="create" method="POST">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

          <div class="row">
            <!-- Kolom kiri -->
            <div class="col-md-6">
              <div class="mb-3">
                <label for="id_cabang_asal" class="form-label fw-semibold">Dari Cabang</label>
                <select class="form-select" id="id_cabang_asal" name="id_cabang_asal" required>
                  <option value="">-- Pilih Cabang Asal --</option>
                  <?php foreach ($cabangs as $cabang): ?>
                    <option value="<?= $cabang['id']; ?>"><?= htmlspecialchars($cabang['nama_cabang']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <label for="tarif_dasar" class="form-label fw-semibold">Tarif Dasar</label>
                <input type="number" class="form-control" id="tarif_dasar" name="tarif_dasar" required>
              </div>
            </div>

            <!-- Kolom kanan -->
            <div class="col-md-6">
              <div class="mb-3">
                <label for="id_cabang_tujuan" class="form-label fw-semibold">Ke Cabang</label>
                <select class="form-select" id="id_cabang_tujuan" name="id_cabang_tujuan" required>
                  <option value="">-- Pilih Cabang Tujuan --</option>
                  <?php foreach ($cabangs as $cabang): ?>
                    <option value="<?= $cabang['id']; ?>"><?= htmlspecialchars($cabang['nama_cabang']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="mb-3">
                <label for="batas_berat" class="form-label fw-semibold">Batas Berat (kg)</label>
                <input type="number" class="form-control" id="batas_berat" name="batas_berat" required value="10">
              </div>
            </div>
          </div>

          <!-- Baris terakhir full width -->
          <div class="mb-4">
            <label for="tarif_tambahan_perkg" class="form-label fw-semibold">Tarif Tambahan per kg</label>
            <input type="number" class="form-control" id="tarif_tambahan_perkg" name="tarif_tambahan_perkg" required>
          </div>

          <div class="d-flex justify-content-start">
            <button type="submit" class="btn btn-danger fw-semibold px-4 py-2" style="width: 120px;">Simpan</button>
          </div>

        </form>

      </div>
    </div>
  </div>
</div>

<?php
    include '../../../templates/footer.php';
?>
