<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../auth/login");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../config/database.php';
$title = "Dashboard - Cendana Kargo";

// Proses form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: change_password?error=failed");
        exit;
    }

    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $user_id = $_SESSION['user_id'];

    // Cek password saat ini
    $stmt = $conn->prepare("SELECT password FROM user WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header("Location: change_password?error=user_not_found");
        exit;
    }

    $user = $result->fetch_assoc();
    
    if (!password_verify($current_password, $user['password'])) {
        header("Location: change_password?error=wrong_password");
        exit;
    }

    if ($new_password !== $confirm_password) {
        header("Location: change_password?error=password_mismatch");
        exit;
    }

    // Update password
    $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("UPDATE user SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $password_hash, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        header("Location: change_password?success=updated");
        exit;
    } else {
        header("Location: change_password?error=failed");
        exit;
    }
}

$page = "change_password";
include '../templates/header.php';
include '../components/navDashboard.php';
include '../components/sidebar_offcanvas.php';
?>

<div class="container-fluid">
  <div class="row">
    <?php include '../components/sidebar.php'; ?>

    <!-- Konten utama kiri -->
    <div class="col-lg-10 d-flex align-items-start justify-content-start" style="min-height: 90vh;">
      <div class="card shadow-sm border-0 p-4 mt-4 ms-3" style="width: 100%; max-width: 600px;">
        <h4 class="fw-bold text-danger mb-4">Ubah Password</h4>

        <!-- Alert -->
        <?php if (isset($_GET['success'])): ?>
          <?php
          $type = "success";
          switch ($_GET['success']) {
              case 'updated':
                  $message = "Password berhasil diubah!";
                  break;
              default:
                  $message = "";
          }
          if (!empty($message)){
                include '../components/alert.php';
          }
        ?>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
          <?php
          $type = "danger";
          switch ($_GET['error']) {
              case 'password_mismatch':
                  $message = "Password Baru dan Konfirmasi Password tidak sesuai!";
                  break;
              case 'wrong_password':
                  $message = "Password saat ini salah!";
                  break;
              case 'user_not_found':
                  $message = "User tidak ditemukan!";
                  break;
              case 'failed':
                  $message = "Gagal mengubah password.";
                  break;
              default:
                  $message = "";
          }
          if (!empty($message)){
                include '../components/alert.php';
          }
        ?>
        <?php endif; ?>
        
        <!-- Form -->
        <form action="change_password" method="POST">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

          <div class="mb-3">
            <label for="current_password" class="form-label fw-semibold">Password Saat Ini</label>
            <input type="password" class="form-control" id="current_password" name="current_password" required placeholder="Masukkan password saat ini">
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label for="new_password" class="form-label fw-semibold">Password Baru</label>
              <input type="password" class="form-control" id="new_password" name="new_password" required placeholder="Masukkan password baru">
            </div>
            <div class="col-md-6">
              <label for="confirm_password" class="form-label fw-semibold">Konfirmasi Password</label>
              <input type="password" class="form-control" id="confirm_password" name="confirm_password" required placeholder="Ulangi password baru">
            </div>
          </div>

          <div class="mt-3">
            <button type="submit" class="btn btn-danger fw-semibold px-4">Ubah Password</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include '../templates/footer.php'; ?>
