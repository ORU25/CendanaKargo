<?php
    session_start();
    include '../config/database.php';

    if (isset($_SESSION['user_id'])) {
        // Kalau role superadmin, arahkan ke dashboard superadmin
        if ($_SESSION['role'] == 'superAdmin') {
            header("Location: ../dashboard/superadmin/?already_logined");
            exit;
        }
        // Kalau role admin
        elseif ($_SESSION['role'] == 'admin') {
            header("Location: ../dashboard/admin/?already_logined");
            exit;
        }
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            header("Location: login.php?error=invalid");
            exit;
        }
        $username = $_POST['username'];
        $password = $_POST['password'];

        $sql = sprintf("SELECT * FROM user WHERE username = '%s'", $username);

        $result = $conn->query($sql);
        $user = $result->fetch_assoc();

        if($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            if(isset($user['id_cabang'])){
              $sqlCabang = sprintf("SELECT nama_cabang FROM kantor_cabang WHERE id = %d", $user['id_cabang']);
              $resultCabang = $conn->query($sqlCabang);
              if($row = $resultCabang->fetch_assoc()){
                  $_SESSION['id_cabang'] = $user['id_cabang'];
                  $_SESSION['cabang'] = $row['nama_cabang'];
              }
            }

            if ($user['role'] == 'superAdmin') {
                header("Location: ../dashboard/superadmin/");
                exit;
            } elseif ($user['role'] == 'admin') {
                header("Location: ../dashboard/admin/");
                exit;
            }
        } else {
            header("Location: login.php?error=invalid");
        }
    }

?>
<?php
    $title = "Login - Cendana Kargo";
    include '../templates/header.php';
?>
  <div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="card shadow p-4" style="width: 350px; border-top: 5px solid #dc3545;">
      <h3 class="text-center text-danger mb-4">Login</h3>
      <form action="login" method="post">
        <?php if(isset($_GET['error']) && $_GET['error'] == 'invalid'){ 
            $type = "danger";
            $message = "Invalid username or password.";
            include '../components/alert.php';
        }?>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
        <div class="mb-3">
          <label for="username" class="form-label fw-semibold">Username</label>
          <input type="text" class="form-control" id="username" name="username" placeholder="Masukkan username" required>
        </div>
        <div class="mb-3">
          <label for="password" class="form-label fw-semibold">Password</label>
          <input type="password" class="form-control" id="password" name="password" placeholder="Masukkan password" required>
        </div>
        <div class="d-grid">
          <button type="submit" class="btn btn-danger fw-bold">Login</button>
        </div>
      </form>
    </div>
  </div>
<?php
    include '../templates/footer.php';
?>