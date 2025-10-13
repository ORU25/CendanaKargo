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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'];
        $password = $_POST['password'];

        $sql = sprintf("SELECT * FROM user WHERE username = '%s'", $username);

        $result = $conn->query($sql);
        $user = $result->fetch_assoc();

        if($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

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
      <form action="login.php" method="post">
        <?php if(isset($_GET['error']) && $_GET['error'] == 'invalid'){ 
            $type = "danger";
            $message = "Invalid username or password.";
            include '../components/alert.php';
        }?>
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