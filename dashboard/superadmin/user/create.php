<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../../../auth/login.php");
    exit;
}

if (isset($_SESSION['role']) && $_SESSION['role'] !== 'superAdmin') {
    header("Location: ../../../?error=unauthorized");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../../../config/database.php';
$title = "Dashboard - Cendana Kargo";

// Ambil data kantor cabang
$sqlCabang = "SELECT * FROM kantor_cabang ORDER BY id ASC";
$resultCabang = $conn->query($sqlCabang);
$cabangs = ($resultCabang->num_rows > 0) ? $resultCabang->fetch_all(MYSQLI_ASSOC) : [];

// Proses form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: create?error=failed");
        exit;
    }

    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    $id_cabang = !empty($_POST['id_cabang']) ? $_POST['id_cabang'] : null;

    $username_safe = mysqli_real_escape_string($conn, $username);
    $check = "SELECT id FROM user WHERE username = '$username_safe'";
    $result = $conn->query($check);
    if ($result->num_rows > 0) {
        header("Location: create?error=username_taken");
        exit;
    }

    if ($password !== $confirm_password) {
        header("Location: create?error=password_mismatch");
        exit;
    }

    $password_hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO user (username, password, role, id_cabang) VALUES (?, ?, ?, ?)");
    $id_cabang_param = $id_cabang ? (int)$id_cabang : null;
    $stmt->bind_param("sssi", $username, $password_hash, $role, $id_cabang_param);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        header("Location: ./?success=created");
        exit;
    } else {
        header("Location: create?error=failed");
        exit;
    }
}

$page = "user";
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
        <h4 class="fw-bold text-danger mb-4">Tambah User Baru</h4>

        <!-- Alert -->
        <?php if (isset($_GET['error'])): ?>
          <?php
          $type = "danger";
          switch ($_GET['error']) {
              case 'password_mismatch':
                  $message = "Password dan Confirm Password tidak sesuai!";
                  break;
              case 'failed':
                  $message = "Gagal menambahkan user baru.";
                  break;
              case 'username_taken':
                  $message = "Username sudah digunakan, silakan pilih username lain.";
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

          <div class="row mb-3">
            <div class="col-md-6">
              <label for="username" class="form-label fw-semibold">Username</label>
              <input type="text" class="form-control" id="username" name="username" required placeholder="Masukkan username">
            </div>
            <div class="col-md-6">
              <label for="role" class="form-label fw-semibold">Role</label>
              <select class="form-select" id="role" name="role" required>
                <option value="">Pilih Role</option>
                <option value="admin">Admin</option>
                <option value="superAdmin">Super Admin</option>
              </select>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label for="password" class="form-label fw-semibold">Password</label>
              <input type="password" class="form-control" id="password" name="password" required placeholder="Masukkan password">
            </div>
            <div class="col-md-6">
              <label for="confirm_password" class="form-label fw-semibold">Confirm Password</label>
              <input type="password" class="form-control" id="confirm_password" name="confirm_password" required placeholder="Ulangi password">
            </div>
          </div>

          <div class="mb-4">
            <label for="cabang" class="form-label fw-semibold">Kantor Cabang</label>
            <select class="form-select" id="cabang" name="id_cabang">
              <option value="">-- Pilih Cabang --</option>
              <?php if (!empty($cabangs)): ?>
                <?php foreach ($cabangs as $cabang): ?>
                  <option value="<?= $cabang['id']; ?>"><?= htmlspecialchars($cabang['nama_cabang']); ?></option>
                <?php endforeach; ?>
              <?php else: ?>
                <option disabled>Tidak ada cabang tersedia</option>
              <?php endif; ?>
            </select>
          </div>

          <div class="mt-3">
            <button type="submit" class="btn btn-danger fw-semibold px-4">Create User</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include '../../../templates/footer.php'; ?>
