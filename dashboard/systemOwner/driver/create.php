<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../../../auth/login");
    exit;
}

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'systemOwner') {
    header("Location: ../../../?error=unauthorized");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../../../config/database.php';
$title = "Dashboard - Cendana Kargo";

// Proses form tambah driver
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header("Location: create?error=failed");
    exit;
  }

  $nama_driver = trim($_POST['nama_driver'] ?? '');
  $telp_driver = trim($_POST['telp_driver'] ?? '');

  if (empty($nama_driver) || empty($telp_driver)) {
    header("Location: create?error=missing_fields");
    exit;
  }

  // Validasi format nomor telepon (10-15 digit angka)
  if (!preg_match('/^[0-9]{10,15}$/', $telp_driver)) {
    header("Location: create?error=invalid_phone");
    exit;
  }

  // Cek duplikat nama driver
  $check_stmt = $conn->prepare("SELECT id FROM driver WHERE nama_driver = ?");
  $check_stmt->bind_param('s', $nama_driver);
  $check_stmt->execute();
  $check_stmt->store_result();
  if ($check_stmt->num_rows > 0) {
    $check_stmt->close();
    header("Location: create?error=duplicate_name");
    exit;
  }
  $check_stmt->close();

  $stmt = $conn->prepare("INSERT INTO driver (nama_driver, telp_driver) VALUES (?, ?)");
  $stmt->bind_param('ss', $nama_driver, $telp_driver);
  $stmt->execute();

  if ($stmt->affected_rows > 0) {
    header("Location: ./?success=created");
    exit;
  } else {
    header("Location: create?error=failed");
    exit;
  }
}

$page = "driver";
include '../../../templates/header.php';
include '../../../components/navDashboard.php';
include '../../../components/sidebar_offcanvas.php';
?>

<div class="container-fluid">
  <div class="row">
    <?php include '../../../components/sidebar.php'; ?>

    <!-- Konten utama kiri -->
    <div class="col-lg-10 d-flex align-items-start justify-content-start" style="min-height: 90vh;">
      <div class="card shadow-sm border-0 p-4 mt-4 ms-3" style="width: 100%; max-width: 600px;">
        <h4 class="fw-bold text-danger mb-4">Tambah Driver Baru</h4>

        <!-- Alert -->
      <?php if (isset($_GET['error'])): ?>
          <?php
          $type = "danger";
          switch ($_GET['error']) {
              case 'missing_fields':
                  $message = "Nama driver dan nomor telepon wajib diisi.";
                  break;
        case 'invalid_phone':
          $message = "Format nomor telepon tidak valid. Harus 10-15 digit angka.";
          break;
              case 'duplicate_name':
                  $message = "Nama driver sudah terdaftar. Gunakan nama yang berbeda.";
                  break;
              case 'failed':
                  $message = "Gagal menambahkan driver.";
                  break;
              default:
                  $message = "";
          }
          if (!empty($message)){
                include '../../../components/alert.php';
          }
        ?>
        <?php endif; ?>
        
        <!-- Form -->
        <form action="create" method="POST">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

          <div class="mb-3">
            <label for="nama_driver" class="form-label fw-semibold">Nama Driver</label>
            <input type="text" class="form-control" id="nama_driver" name="nama_driver" required placeholder="Masukkan nama driver">
          </div>

          <div class="mb-3">
            <label for="telp_driver" class="form-label fw-semibold">No. Telepon</label>
            <input type="tel" class="form-control" id="telp_driver" name="telp_driver" required placeholder="contoh: 081234567890"
                   pattern="[0-9]{10,15}"
                   title="Nomor telepon harus 10-15 digit angka (contoh: 081234567890)">
          </div>

          <div class="mt-3">
            <button type="submit" class="btn btn-danger fw-semibold px-4">Create Driver</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include '../../../templates/footer.php'; ?>
