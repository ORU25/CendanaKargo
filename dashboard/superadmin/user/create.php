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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            header("Location: create?error=failed");
            exit;
        }
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_pasword']; 
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

?>

<?php
    $page = "user";
    include '../../../templates/header.php';
    include '../../../components/navDashboard.php';
    include '../../../components/sidebar_offcanvas.php';
?>
<div class="container-fluid">
  <div class="row">
    <div class="col-lg-2 d-none d-lg-block bg-light border-end vh-100">
      <div class="list-group list-group-flush">
        <a href="<?= BASE_URL; ?>dashboard/superadmin/" class="list-group-item list-group-item-action">Dashboard</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/pengiriman/" class="list-group-item list-group-item-action">Pengiriman</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/surat_jalan/" class="list-group-item list-group-item-action">Surat Jalan</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/tarif/" class="list-group-item list-group-item-action">Tarif</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/kantor_cabang/" class="list-group-item list-group-item-action">Kantor Cabang</a>
        <a href="<?= BASE_URL; ?>dashboard/superadmin/user/" class="list-group-item list-group-item-action fw-bold text-danger">User</a>
      </div>
        <a href="<?= BASE_URL; ?>auth/logout.php" class="btn btn-outline-danger mt-3">Logout</a>
    </div>

    <!-- Konten utama -->
    <div class="col-lg-10">
        <?php if(isset($_GET['error']) && $_GET['error'] == 'password_mismatch'){
            $type = "danger";
            $message = "Password dan Confirm Password tidak sesuai";
            include '../../../components/alert.php';
        }?>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'failed'){
            $type = "danger";
            $message = "Gagal menambahkan user baru";
            include '../../../components/alert.php';
        }?>
        <?php if(isset($_GET['error']) && $_GET['error'] == 'username_taken'){
            $type = "danger";
            $message = "Username sudah digunakan, silakan pilih username lain";
            include '../../../components/alert.php';
        }?>
        <h1>Create New User</h1>
        <form action="create" method="POST" class="col-5">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="mb-3">
                <label for="confirm_pasword" class="form-label">Confirm Password</label>
                <input type="password" class="form-control" id="confirm_pasword" name="confirm_pasword" required>
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">Role</label>
                <select class="form-select" id="role" name="role" required>
                    <option value="">Select Role</option>
                    <option value="admin">Admin</option>
                    <option value="superAdmin">Super Admin</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="cabang" class="form-label">Kantor Cabang</label>
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
            <button type="submit" class="btn btn-primary">Create User</button>
        </form>
    </div>
  </div>
</div>
<?php
    include '../../../templates/footer.php';
?>